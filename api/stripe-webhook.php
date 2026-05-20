<?php
/**
 * White Label CRM - Stripe Webhook Handler
 * Processes Stripe events for subscription management
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Load Stripe secret key
$stripeSecretKey = getenv('STRIPE_SECRET_KEY') ?: '';
$webhookSecret = getenv('STRIPE_WEBHOOK_SECRET') ?: '';

if (empty($stripeSecretKey)) {
    http_response_code(500);
    echo json_encode(['error' => 'Stripe not configured']);
    exit;
}

// Get payload
$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (empty($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty payload']);
    exit;
}

// Verify signature if webhook secret is configured
if (!empty($webhookSecret)) {
    $event = json_decode($payload, true);
    // In production, verify with \Stripe\Webhook::constructEvent()
    // For now, parse the event directly
} else {
    $event = json_decode($payload, true);
}

if (!$event || !isset($event['type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid event']);
    exit;
}

$eventType = $event['type'];
$eventId = $event['id'] ?? null;

try {
    $db = Database::getInstance();
    
    // Idempotency check
    if ($eventId) {
        $existing = $db->query("SELECT 1 FROM stripe_events WHERE stripe_event_id = ?", [$eventId])->fetch();
        if ($existing) {
            echo json_encode(['success' => true, 'message' => 'Event already processed']);
            exit;
        }
        $db->insert('stripe_events', [
            'stripe_event_id' => $eventId,
            'event_type' => $eventType,
            'event_data' => json_encode($event),
        ]);
    }
    
    switch ($eventType) {
        case 'checkout.session.completed':
            $session = $event['data']['object'] ?? [];
            $subscriptionId = $session['subscription'] ?? null;
            $customerId = $session['customer'] ?? null;
            $metadata = $session['metadata'] ?? [];
            $companyId = $metadata['company_id'] ?? null;
            $planKey = $metadata['plan_key'] ?? null;
            $extraUsers = intval($metadata['extra_users'] ?? 0);
            
            if ($companyId && $subscriptionId) {
                // Get subscription details from Stripe
                $ch = curl_init('https://api.stripe.com/v1/subscriptions/' . $subscriptionId);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $stripeSecretKey,
                ]);
                $subResponse = curl_exec($ch);
                curl_close($ch);
                
                $subscription = json_decode($subResponse, true);
                $periodStart = date('Y-m-d H:i:s', $subscription['current_period_start'] ?? time());
                $periodEnd = date('Y-m-d H:i:s', $subscription['current_period_end'] ?? strtotime('+1 month'));
                
                // Update company
                $db->query("UPDATE companies SET 
                    stripe_subscription_id = ?,
                    subscription_status = 'active',
                    plan_id = ?,
                    current_period_start = ?,
                    current_period_end = ?,
                    trial_ends_at = NULL,
                    status = 'active'
                    WHERE company_id = ?", [
                    $subscriptionId,
                    $planKey,
                    $periodStart,
                    $periodEnd,
                    $companyId,
                ]);
            }
            break;
            
        case 'invoice.payment_succeeded':
            $invoice = $event['data']['object'] ?? [];
            $subscriptionId = $invoice['subscription'] ?? null;
            
            if ($subscriptionId) {
                // Update period end
                $ch = curl_init('https://api.stripe.com/v1/subscriptions/' . $subscriptionId);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $stripeSecretKey,
                ]);
                $subResponse = curl_exec($ch);
                curl_close($ch);
                
                $subscription = json_decode($subResponse, true);
                $periodEnd = date('Y-m-d H:i:s', $subscription['current_period_end'] ?? strtotime('+1 month'));
                
                $db->query("UPDATE companies SET 
                    subscription_status = 'active',
                    current_period_end = ?
                    WHERE stripe_subscription_id = ?", [
                    $periodEnd,
                    $subscriptionId,
                ]);
            }
            break;
            
        case 'invoice.payment_failed':
            $invoice = $event['data']['object'] ?? [];
            $subscriptionId = $invoice['subscription'] ?? null;
            
            if ($subscriptionId) {
                $db->query("UPDATE companies SET 
                    subscription_status = 'past_due'
                    WHERE stripe_subscription_id = ?", [$subscriptionId]);
            }
            break;
            
        case 'customer.subscription.deleted':
            $subscription = $event['data']['object'] ?? [];
            $subscriptionId = $subscription['id'] ?? null;
            
            if ($subscriptionId) {
                $db->query("UPDATE companies SET 
                    subscription_status = 'cancelled',
                    stripe_subscription_id = NULL
                    WHERE stripe_subscription_id = ?", [$subscriptionId]);
            }
            break;
    }
    
    echo json_encode(['success' => true, 'event_type' => $eventType]);
    
} catch (Exception $e) {
    error_log("Stripe webhook error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Webhook processing failed']);
}
