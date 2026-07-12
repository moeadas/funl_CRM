<?php
/**
 * api/ni-webhook.php
 * 
 * Handles async payment notifications from Network International Gateway.
 * 
 * 3DS Flow:
 *   1. User authenticates with issuer → Gateway sends INBOUND_AUTH webhook
 *   2. We update payment_transactions with 3DS result + update session
 *   3. User is redirected to return_url by checkout.js
 * 
 * Other events:
 *   - PAYMENT_SUCCESS: payment captured, activate subscription
 *   - PAYMENT_FAILED: payment declined
 *   - REFUND: refund processed
 * 
 * NI Gateway webhook docs:
 * https://test-gateway.mastercard.com/api/documentation/integrationGuidelines/
 */

// Load config + functions for getNIGatewaySettings()
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Load NI Gateway helpers (getNIGatewaySettings, niGatewayRequest)
require_once __DIR__ . '/ni-checkout-helpers.php';

// =====================================================================
// Security: Verify webhook authenticity
// =====================================================================
$settings = getNIGatewaySettings();
$expectedSecret = $settings['ni_webhook_secret'] ?? null;

// Verify request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Parse JSON body
$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);
if (!$payload) {
    error_log('NI Webhook: Failed to parse JSON payload');
    http_response_code(400);
    exit('Invalid JSON');
}

// Extract basic fields
$eventType = $payload['eventType'] ?? $payload['type'] ?? 'UNKNOWN';
$merchantId = $payload['merchantId'] ?? $payload['merchant'] ?? '';
$sessionId = $payload['sessionId'] ?? $payload['session'] ?? '';
$transactionId = $payload['orderId'] ?? $payload['transactionId'] ?? '';

// Log all incoming webhooks for debugging
error_log('NI Webhook received: ' . json_encode(['event' => $eventType, 'session' => $sessionId, 'order' => $transactionId]));

// Verify merchantId matches our configured merchant
$configuredMerchant = $settings['ni_merchant_id'] ?? '';
if ($merchantId && $configuredMerchant && $merchantId !== $configuredMerchant) {
    error_log("NI Webhook: merchantId mismatch. Expected $configuredMerchant, got $merchantId");
    http_response_code(403);
    exit('Forbidden');
}

// Verify webhook signature. When a secret is configured, a VALID signature is
// REQUIRED — a missing or mismatched signature is rejected. This prevents
// spoofed PAYMENT_SUCCESS events from activating subscriptions for free.
// NI signs with a header like X-Webhook-Signature (or X-Signature).
$webhookSig = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? $_SERVER['HTTP_X_SIGNATURE'] ?? '';
if (!empty($expectedSecret)) {
    if (empty($webhookSig)) {
        error_log('NI Webhook: secret configured but no signature header present — rejecting.');
        http_response_code(401);
        exit('Unauthorized');
    }
    $expected = hash_hmac('sha256', $rawInput, $expectedSecret);
    if (!hash_equals($expected, $webhookSig)) {
        error_log('NI Webhook: Signature verification failed');
        http_response_code(401);
        exit('Unauthorized');
    }
} else {
    // No secret configured: log loudly. Merchant-ID check above is the only guard.
    error_log('NI Webhook: WARNING — ni_webhook_secret not configured; accepting on merchantId match only. Configure a secret to secure this endpoint.');
}

// =====================================================================
// Handle webhook events
// =====================================================================
require_once __DIR__ . '/../config/database.php';
$db = Database::getInstance();

switch ($eventType) {
    
    // ── 3DS Authentication Result ─────────────────────────────────────────
    // Sent when cardholder completes (or fails) 3DS authentication
    case 'INBOUND_AUTH':
    case '3DS_AUTHENTICATION':
    case '3DS_AUTHENTICATION_RESULT': {
        $result = $payload['result'] ?? $payload['authenticationResult'] ?? '';
        $resultStatus = $payload['resultStatus'] ?? '';
        $eci = $payload['eci'] ?? ''; // Electronic Commerce Indicator
        $xid = $payload['xid'] ?? '';
        $errorCode = $payload['errorCode'] ?? '';
        $errorMessage = $payload['errorMessage'] ?? '';
        
        // Find the pending transaction by session ID
        $txn = $db->query(
            "SELECT * FROM payment_transactions WHERE session_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1",
            [$sessionId]
        )->fetch(PDO::FETCH_ASSOC);
        
        if (!$txn) {
            error_log("NI Webhook INBOUND_AUTH: No pending transaction found for session $sessionId");
            // Don't 404 — the webhook will be retried
            http_response_code(200);
            exit('Transaction not found');
        }
        
        // Check authentication result
        $authSuccess = ($result === 'SUCCESS' || $result === 'AUTHENTICATION_SUCCESSFUL' || $resultStatus === 'SUCCESS');
        
        $db->query(
            "UPDATE payment_transactions SET 
                auth_result = ?,
                auth_eci = ?,
                auth_xid = ?,
                auth_status = ?,
                auth_error_code = ?,
                auth_error_message = ?,
                updated_at = NOW()
             WHERE id = ?",
            [
                $result,
                $eci,
                $xid,
                $authSuccess ? 'success' : 'failed',
                $errorCode,
                $errorMessage,
                $txn['id']
            ]
        );
        
        error_log("NI Webhook INBOUND_AUTH: session=$sessionId result=$result authSuccess=" . ($authSuccess ? 'yes' : 'no'));
        
        // If 3DS succeeded, the next step is the return_url redirect to billing.php
        // The billing.php page will call verify_payment to confirm and activate
        
        http_response_code(200);
        exit('OK');
    }
    
    // ── Payment Success ────────────────────────────────────────────────────
    case 'PAYMENT_SUCCESS':
    case 'TRANSACTION_COMPLETED':
    case 'PAYMENT_COMPLETED': {
        $txn = $db->query(
            "SELECT * FROM payment_transactions WHERE session_id = ? ORDER BY id DESC LIMIT 1",
            [$sessionId]
        )->fetch(PDO::FETCH_ASSOC);
        
        if (!$txn) {
            // Try by order ID
            $txn = $db->query(
                "SELECT * FROM payment_transactions WHERE order_id = ? ORDER BY id DESC LIMIT 1",
                [$transactionId]
            )->fetch(PDO::FETCH_ASSOC);
        }
        
        if (!$txn) {
            error_log("NI Webhook PAYMENT_SUCCESS: Transaction not found for session=$sessionId order=$transactionId");
            http_response_code(200);
            exit('Transaction not found');
        }
        
        // Payment succeeded — activate subscription
        $companyId = $txn['company_id'];
        
        // Look up plan details
        $plan = $db->query("SELECT * FROM plans WHERE plan_key = ? AND is_active = 1", [$txn['plan_key']])->fetch(PDO::FETCH_ASSOC);
        
        if ($plan && $companyId) {
            $periodStart = date('Y-m-d H:i:s');
            $periodEnd = ($txn['billing_cycle'] === 'yearly')
                ? date('Y-m-d H:i:s', strtotime('+1 year'))
                : date('Y-m-d H:i:s', strtotime('+1 month'));
            
            $db->query(
                "UPDATE companies SET
                    subscription_status = 'active',
                    plan_id = ?,
                    plan_name = ?,
                    plan_user_limit = ?,
                    plan_price_monthly = ?,
                    billing_cycle = ?,
                    current_period_start = ?,
                    current_period_end = ?,
                    cancel_at_period_end = 0,
                    updated_at = NOW()
                 WHERE company_id = ?",
                [
                    $txn['plan_key'],
                    $plan['plan_name'],
                    $plan['user_limit'],
                    $plan['monthly_price'],
                    $txn['billing_cycle'],
                    $periodStart,
                    $periodEnd,
                    $companyId
                ]
            );
            
            $db->query(
                "UPDATE payment_transactions SET
                    status = 'completed',
                    completed_at = NOW(),
                    gateway_reference = ?,
                    updated_at = NOW()
                 WHERE id = ?",
                [$payload['transactionId'] ?? $transactionId, $txn['id']]
            );
            
            error_log("NI Webhook PAYMENT_SUCCESS: company_id=$companyId plan={$txn['plan_key']} activated");
        }
        
        http_response_code(200);
        exit('OK');
    }
    
    // ── Payment Failed ─────────────────────────────────────────────────────
    case 'PAYMENT_FAILED':
    case 'TRANSACTION_DECLINED': {
        $txn = $db->query(
            "SELECT * FROM payment_transactions WHERE session_id = ? ORDER BY id DESC LIMIT 1",
            [$sessionId]
        )->fetch(PDO::FETCH_ASSOC);
        
        if ($txn) {
            $failureReason = $payload['failureReason'] ?? $payload['errorMessage'] ?? 'Payment declined';
            $db->query(
                "UPDATE payment_transactions SET
                    status = 'failed',
                    failure_reason = ?,
                    updated_at = NOW()
                 WHERE id = ?",
                [$failureReason, $txn['id']]
            );
            
            // Mark company as past_due if it had an active subscription before
            $db->query(
                "UPDATE companies SET subscription_status = 'past_due', updated_at = NOW() WHERE company_id = ? AND subscription_status = 'active'",
                [$txn['company_id']]
            );
        }
        
        error_log("NI Webhook PAYMENT_FAILED: session=$sessionId reason=" . ($failureReason ?? 'unknown'));
        http_response_code(200);
        exit('OK');
    }
    
    // ── Refund ──────────────────────────────────────────────────────────────
    case 'REFUND':
    case 'REFUND_COMPLETED': {
        $txn = $db->query(
            "SELECT * FROM payment_transactions WHERE order_id = ? ORDER BY id DESC LIMIT 1",
            [$transactionId]
        )->fetch(PDO::FETCH_ASSOC);
        
        if ($txn) {
            $refundAmount = $payload['refundAmount'] ?? $payload['amount'] ?? $txn['amount'];
            $db->query(
                "UPDATE payment_transactions SET
                    status = 'refunded',
                    refunded_at = NOW(),
                    refund_amount = ?,
                    updated_at = NOW()
                 WHERE id = ?",
                [$refundAmount, $txn['id']]
            );
        }
        
        http_response_code(200);
        exit('OK');
    }
    
    default: {
        error_log("NI Webhook: Unhandled event type '$eventType' — " . json_encode($payload));
        // Always return 200 so gateway doesn't retry unknown events
        http_response_code(200);
        exit('OK');
    }
}