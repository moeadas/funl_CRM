<?php
/**
 * pages/ni-payment.php
 * 
 * Hosts Mastercard's checkout.min.js for NI Gateway payments.
 * 
 * Flow:
 *   1. Redirected here from billing.php with ?session_id=XXX&order_id=XXX
 *   2. Loads checkout.min.js from Mastercard gateway
 *   3. Calls Checkout.showPaymentPage() with the session ID
 *   4. User enters card details on Mastercard's hosted page (3DS handled there)
 *   5. On completion, redirected to return_url (billing.php?status=success&session_id=XXX)
 *   6. billing.php calls verify_payment to finalize
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/company-functions.php';

$sessionId = $_GET['session_id'] ?? '';
$orderId = $_GET['order_id'] ?? '';
$returnUrl = $_GET['return_url'] ?? 'https://app.funl.online/pages/billing.php?status=success&session_id=' . urlencode($sessionId);
$gatewayUrl = $_GET['gateway_url'] ?? '';

// Get NI settings for checkout.js configuration
$settings = getNIGatewaySettings();
$merchantId = $settings['ni_merchant_id'] ?? '';
$enabled = $settings['ni_enabled'] ?? '0';

// Get the transaction to show order details
$txnInfo = null;
if ($orderId) {
    $db = Database::getInstance();
    $txn = $db->query("SELECT amount, currency, plan_key FROM payment_transactions WHERE order_id = ?", [$orderId])->fetch(PDO::FETCH_ASSOC);
    if ($txn) {
        $plan = $db->query("SELECT plan_name FROM plans WHERE plan_key = ?", [$txn['plan_key']])->fetch(PDO::FETCH_ASSOC);
        $txnInfo = [
            'amount' => $txn['amount'],
            'currency' => $txn['currency'],
            'plan_name' => $plan['plan_name'] ?? $txn['plan_key'],
        ];
    }
}

// If no session ID, redirect back to billing
if (!$sessionId) {
    header('Location: /pages/billing.php');
    exit;
}

// Determine which checkout.js URL to use based on gateway URL
$isTest = (strpos($gatewayUrl, 'test-') !== false) || (strpos($gatewayUrl, 'mtf') !== false);
$checkoutJsUrl = $isTest
    ? 'https://test-gateway.mastercard.com/static/checkout/checkout.min.js'
    : 'https://gateway.mastercard.com/static/checkout/checkout.min.js';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Payment — FunL CRM</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f4f6f9; min-height: 100vh; display: flex; flex-direction: column; }
        
        .header { background: white; border-bottom: 1px solid #e0e0e0; padding: 16px 24px; display: flex; align-items: center; gap: 12px; }
        .header img { height: 36px; }
        .header-title { font-size: 18px; font-weight: 600; color: #333; }
        
        .main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
        
        #payment-page { width: 100%; max-width: 600px; }
        
        .order-summary { background: white; border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .order-summary h2 { font-size: 16px; color: #666; margin-bottom: 16px; text-transform: uppercase; letter-spacing: .5px; }
        .order-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .order-row:last-child { border: none; font-weight: 600; font-size: 18px; padding-top: 12px; }
        .order-row span:first-child { color: #555; }
        .order-row span:last-child { color: #333; }
        
        .secure-badge { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #666; margin-top: 16px; justify-content: center; }
        .secure-badge img { height: 20px; }
        
        /* Checkout.js container */
        #payment-page > div { min-height: 400px; }
        
        .error-state { text-align: center; padding: 40px; }
        .error-state .icon { font-size: 48px; margin-bottom: 16px; }
        .error-state h2 { margin-bottom: 8px; }
        .error-state p { color: #666; margin-bottom: 24px; }
        
        .footer { text-align: center; padding: 16px; font-size: 12px; color: #999; }
        .footer a { color: #667eea; text-decoration: none; }
        
        /* Loading state */
        .loading-state { text-align: center; padding: 60px 20px; }
        .spinner { width: 48px; height: 48px; border: 4px solid rgba(102,126,234,.2); border-top-color: #667eea; border-radius: 50%; animation: spin .8s linear infinite; margin: 0 auto 16px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading-state p { color: #666; font-size: 15px; }
    </style>
</head>
<body>
    <div class="header">
        <span class="header-title">🔒 Secure Payment — FunL CRM</span>
    </div>
    
    <div class="main">
        <div id="payment-page">
            <?php if ($enabled !== '1'): ?>
            <div class="error-state">
                <div class="icon">⚠️</div>
                <h2>Payments Not Enabled</h2>
                <p>Please contact support to enable online payments.</p>
                <a href="/pages/billing.php" class="btn" style="background:#667eea;color:white;padding:10px 24px;border-radius:8px;text-decoration:none;">← Back to Billing</a>
            </div>
            <?php elseif (!$merchantId): ?>
            <div class="error-state">
                <div class="icon">⚠️</div>
                <h2>Payment Gateway Not Configured</h2>
                <p>Please contact support.</p>
                <a href="/pages/billing.php" class="btn" style="background:#667eea;color:white;padding:10px 24px;border-radius:8px;text-decoration:none;">← Back to Billing</a>
            </div>
            <?php else: ?>
            
            <?php if ($txnInfo): ?>
            <div class="order-summary">
                <h2>Order Summary</h2>
                <div class="order-row">
                    <span>Plan</span>
                    <span><?= htmlspecialchars($txnInfo['plan_name']) ?></span>
                </div>
                <div class="order-row">
                    <span>Amount</span>
                    <span>$<?= number_format($txnInfo['amount'], 2) ?> USD</span>
                </div>
                <div class="order-row">
                    <span>Total</span>
                    <span>$<?= number_format($txnInfo['amount'], 2) ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="loading-state" id="loading-state">
                <div class="spinner"></div>
                <p>Loading secure payment form...</p>
            </div>
            
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($enabled === '1' && $merchantId && $sessionId): ?>
    <!-- Mastercard Checkout.js -->
    <script src="<?= htmlspecialchars($checkoutJsUrl) ?>"></script>
    <script>
    function initCheckout() {
        var paymentPage = document.getElementById('payment-page');
        var loadingState = document.getElementById('loading-state');
        
        Checkout.configure({
            merchant: '<?= htmlspecialchars($merchantId) ?>',
            session: {
                id: '<?= htmlspecialchars($sessionId) ?>'
            },
            order: {
                amount: '<?= $txnInfo ? htmlspecialchars((string)$txnInfo['amount']) : '0' ?>',
                currency: '<?= $txnInfo ? htmlspecialchars($txnInfo['currency']) : 'USD' ?>',
                description: '<?= $txnInfo ? htmlspecialchars('FunL CRM - ' . $txnInfo['plan_name']) : 'FunL CRM Subscription' ?>',
                id: '<?= htmlspecialchars($orderId ?: $sessionId) ?>'
            },
            interaction: {
                merchant: {
                    name: 'FunL CRM',
                    logo: 'https://app.funl.online/assets/img/logo.png'
                },
                displayControl: {
                    billingAddress: 'HIDE',
                    shippingAddress: 'HIDE'
                }
            },
            // 3DS: show the challenge even for test cards
            // (actual 3DS prompt is controlled by the gateway based on card issuer)
        });
        
        // Hide loading state and show payment form
        loadingState.style.display = 'none';
        
        // Show the payment page
        Checkout.showPaymentPage();
    }
    
    // When checkout.js is ready, initialize
    if (typeof Checkout !== 'undefined') {
        initCheckout();
    } else {
        // checkout.min.js loaded but Checkout not ready yet
        window.addEventListener('load', function() {
            if (typeof Checkout !== 'undefined') {
                initCheckout();
            } else {
                // Try after a short delay
                setTimeout(function() {
                    if (typeof Checkout !== 'undefined') {
                        initCheckout();
                    } else {
                        document.getElementById('loading-state').innerHTML = 
                            '<div class="error-state"><div class="icon">⚠️</div><h2>Failed to load payment form</h2><p>Please refresh the page or contact support.</p></div>';
                    }
                }, 2000);
            }
        });
    }
    
    // Handle checkout.js events
    window.addEventListener('message', function(event) {
        // checkout.js may post messages about payment completion
        if (event.data && event.data.type === 'checkoutComplete') {
            console.log('Checkout complete:', event.data);
        }
    });
    </script>
    <?php endif; ?>
    
    <div class="footer">
        <a href="/pages/billing.php">← Cancel and return to billing</a>
        &nbsp;|&nbsp;
        Secured by <strong>Mastercard</strong> — Your card data never touches our servers
    </div>
</body>
</html>