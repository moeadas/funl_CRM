<?php
/**
 * White Label CRM - Stripe Checkout API
 * Creates checkout sessions for subscription payments
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/company-functions.php';

header('Content-Type: application/json');
startSecureSession();

if (!isLoggedIn() || !hasRole('Admin')) {
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

$companyId = getCurrentCompanyId();
if (!$companyId) {
    echo json_encode(['success' => false, 'message' => 'No company associated']);
    exit;
}

// Load Stripe keys from environment
$stripeSecretKey = getenv('STRIPE_SECRET_KEY') ?: '';
$stripePublishableKey = getenv('STRIPE_PUBLISHABLE_KEY') ?: '';

if (empty($stripeSecretKey)) {
    echo json_encode(['success' => false, 'message' => 'Stripe not configured. Add STRIPE_SECRET_KEY to config/.env']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// M-7 fix: require CSRF for all state-changing actions (create_checkout,
// cancel_subscription). Stripe billing changes were only role-gated before,
// which is insufficient if an attacker tricks an admin's browser.
if (in_array($action, ['create_checkout', 'cancel_subscription']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCSRFToken($token)) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
}

try {
    $db = Database::getInstance();
    $company = getCompany($companyId);
    
    if (!$company) {
        echo json_encode(['success' => false, 'message' => 'Company not found']);
        exit;
    }
    
    switch ($action) {
        case 'create_checkout':
            $planKey = sanitizeInput($_POST['plan'] ?? $company['plan_id'] ?? 'single');
            $billingCycle = sanitizeInput($_POST['billing_cycle'] ?? 'monthly');
            $extraUsers = intval($_POST['extra_users'] ?? 0);
            
            $plan = getPlan($planKey);
            if (!$plan) {
                echo json_encode(['success' => false, 'message' => 'Invalid plan']);
                exit;
            }
            
            // Calculate price
            $unitAmount = ($billingCycle === 'yearly') ? $plan['yearly_price'] : $plan['monthly_price'];
            $unitAmount = intval($unitAmount * 100); // Convert to cents
            
            if ($extraUsers > 0 && $plan['extra_user_price'] > 0) {
                $extraAmount = $extraUsers * $plan['extra_user_price'] * ($billingCycle === 'yearly' ? 12 : 1);
                $unitAmount += intval($extraAmount * 100);
            }
            
            // Create or get Stripe customer
            $stripeCustomerId = $company['stripe_customer_id'] ?? null;
            
            if (!$stripeCustomerId) {
                // Create customer via Stripe API
                $ch = curl_init('https://api.stripe.com/v1/customers');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                    'email' => $company['email'],
                    'name' => $company['company_name'],
                    'metadata[company_id]' => $companyId,
                ]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $stripeSecretKey,
                    'Content-Type: application/x-www-form-urlencoded',
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200) {
                    $customer = json_decode($response, true);
                    $stripeCustomerId = $customer['id'];
                    
                    // Save customer ID
                    $db->query(
                        "UPDATE companies SET stripe_customer_id = ? WHERE company_id = ?",
                        [$stripeCustomerId, $companyId]
                    );
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to create Stripe customer']);
                    exit;
                }
            }
            
            // Create checkout session
            $successUrl = APP_URL . '/pages/settings.php?stripe=success&session_id={CHECKOUT_SESSION_ID}';
            $cancelUrl = APP_URL . '/pages/settings.php?stripe=cancel';
            
            $checkoutData = [
                'customer' => $stripeCustomerId,
                'payment_method_types[]' => 'card',
                'line_items[0][price_data][currency]' => 'usd',
                'line_items[0][price_data][product_data][name]' => $plan['plan_name'] . ' - ' . $company['company_name'],
                'line_items[0][price_data][product_data][description]' => 'White Label CRM ' . ucfirst($billingCycle) . ' Subscription',
                'line_items[0][price_data][unit_amount]' => $unitAmount,
                'line_items[0][price_data][recurring][interval]' => $billingCycle === 'yearly' ? 'year' : 'month',
                'line_items[0][quantity]' => 1,
                'mode' => 'subscription',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'subscription_data[metadata][company_id]' => $companyId,
                'subscription_data[metadata][plan_key]' => $planKey,
                'subscription_data[metadata][extra_users]' => $extraUsers,
            ];
            
            $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($checkoutData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $stripeSecretKey,
                'Content-Type: application/x-www-form-urlencoded',
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $session = json_decode($response, true);
                echo json_encode([
                    'success' => true,
                    'checkout_url' => $session['url'],
                    'session_id' => $session['id'],
                ]);
            } else {
                $error = json_decode($response, true);
                echo json_encode([
                    'success' => false,
                    'message' => $error['error']['message'] ?? 'Failed to create checkout session',
                ]);
            }
            break;
            
        case 'get_subscription':
            $subscriptionId = $company['stripe_subscription_id'] ?? null;
            
            if (!$subscriptionId) {
                echo json_encode(['success' => false, 'message' => 'No active subscription']);
                exit;
            }
            
            $ch = curl_init('https://api.stripe.com/v1/subscriptions/' . $subscriptionId);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $stripeSecretKey,
            ]);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $subscription = json_decode($response, true);
            echo json_encode([
                'success' => true,
                'subscription' => $subscription,
            ]);
            break;
            
        case 'cancel_subscription':
            $subscriptionId = $company['stripe_subscription_id'] ?? null;
            
            if (!$subscriptionId) {
                echo json_encode(['success' => false, 'message' => 'No active subscription']);
                exit;
            }
            
            $ch = curl_init('https://api.stripe.com/v1/subscriptions/' . $subscriptionId);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $stripeSecretKey,
            ]);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            // Update company status
            $db->query(
                "UPDATE companies SET subscription_status = 'cancelled', cancel_at_period_end = 1 WHERE company_id = ?",
                [$companyId]
            );
            
            echo json_encode(['success' => true, 'message' => 'Subscription cancelled at end of period']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    safeJsonError($e, 'Error: ', 500);
}
