<?php
/**
 * White Label CRM - Network International Payment Page
 * 
 * Uses Mastercard's hosted checkout.min.js to collect card details.
 * The customer never enters card data on our server - it's collected by Mastercard's
 * hosted form and posted back via the session update.
 * 
 * Flow:
 *  1. Server creates a checkout session (ni-checkout.php?action=create_session)
 *  2. Client calls Checkout.configure() with session ID
 *  3. Client calls Checkout.showPaymentPage() or showEmbeddedPage()
 *  4. Customer enters card details on Mastercard's hosted page
 *  5. Mastercard redirects back to return_url with session ID
 *  6. Client verifies payment via ni-checkout.php?action=verify_payment
 * 
 * Docs: https://test-gateway.mastercard.com/api/documentation/apiDocumentation/checkout/version/100/api.html
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
requireLogin();

$companyId = $_SESSION['company_id'] ?? null;
if (!$companyId) {
    header('Location: /login.php');
    exit;
}

// Get the session details from query params
$sessionId = $_GET['session_id'] ?? '';
$orderId = $_GET['order_id'] ?? '';
$amount = $_GET['amount'] ?? '';
$currency = $_GET['currency'] ?? 'USD';
$description = $_GET['description'] ?? 'CRM Subscription';

// Load company info
$db = Database::getInstance();
$company = $db->query("SELECT company_name, email FROM companies WHERE company_id = ?", [$companyId])->fetch(PDO::FETCH_ASSOC);

// Load NI gateway settings
$settings = $db->query(
    "SELECT setting_key, setting_value FROM settings WHERE company_id IS NULL AND setting_key LIKE 'ni_%'"
)->fetchAll(PDO::FETCH_KEY_PAIR);
$niSettings = [];
foreach ($settings as $row) {
    $niSettings[$row['setting_key']] = $row['setting_value'];
}

$gatewayUrl = $niSettings['ni_gateway_url'] ?? 'https://test-network.mtf.gateway.mastercard.com';
$merchantId = $niSettings['ni_merchant_id'] ?? '';
$checkoutJsUrl = 'https://test-gateway.mastercard.com/static/checkout/checkout.min.js';
$appUrl = rtrim(getenv('APP_URL') ?: 'https://app.funl.online', '/');

// Return URLs
$successUrl = $appUrl . '/pages/settings.php?ni=success&order_id=' . urlencode($orderId);
$cancelUrl = $appUrl . '/pages/settings.php?ni=cancelled&order_id=' . urlencode($orderId);
$errorUrl = $appUrl . '/pages/settings.php?ni=error&order_id=' . urlencode($orderId);

$csrfToken = generateCSRFToken();

include __DIR__ . '/../includes/header.php';
?>

<style>
.ni-payment-container {
    max-width: 800px;
    margin: 40px auto;
    padding: 0 16px;
}
.ni-payment-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    overflow: hidden;
}
.ni-payment-header {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    color: white;
    padding: 32px 40px;
}
.ni-payment-header h1 {
    font-size: 24px;
    font-weight: 700;
    margin: 0 0 8px;
}
.ni-payment-header p {
    margin: 0;
    opacity: 0.8;
    font-size: 14px;
}
.ni-payment-body {
    padding: 40px;
}
.order-summary {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 32px;
}
.order-summary h3 {
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6c757d;
    margin: 0 0 16px;
}
.order-amount {
    font-size: 40px;
    font-weight: 700;
    color: #1a1a2e;
}
.order-amount small {
    font-size: 16px;
    font-weight: 400;
    color: #6c757d;
}
.order-description {
    margin-top: 8px;
    color: #6c757d;
    font-size: 14px;
}
.payment-placeholder {
    border: 2px dashed #dee2e6;
    border-radius: 12px;
    padding: 48px;
    text-align: center;
    color: #6c757d;
}
.payment-placeholder svg {
    width: 48px;
    height: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
}
.payment-placeholder h3 {
    margin: 0 0 8px;
    color: #495057;
}
.payment-placeholder p {
    margin: 0;
    font-size: 14px;
}
#pay-button {
    background: linear-gradient(135deg, #e63946 0%, #d62828 100%);
    color: white;
    border: none;
    padding: 16px 48px;
    font-size: 16px;
    font-weight: 600;
    border-radius: 8px;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    margin-top: 24px;
}
#pay-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(230, 57, 70, 0.4);
}
#pay-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}
#cancel-link {
    display: inline-block;
    margin-top: 16px;
    color: #6c757d;
    text-decoration: none;
    font-size: 14px;
}
#cancel-link:hover {
    text-decoration: underline;
}
.error-message, .success-message {
    padding: 16px 20px;
    border-radius: 8px;
    margin-bottom: 24px;
    display: none;
}
.error-message {
    background: #fee2e2;
    color: #dc2626;
    border: 1px solid #fecaca;
}
.success-message {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}
.secure-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 24px;
    color: #6c757d;
    font-size: 13px;
}
.secure-badge svg {
    width: 16px;
    height: 16px;
}
</style>

<div class="ni-payment-container">
    <div class="ni-payment-card">
        <div class="ni-payment-header">
            <h1><?php echo htmlspecialchars($niSettings['ni_gateway_name'] ?? 'Secure Payment'); ?></h1>
            <p>Powered by Network International &amp; Mastercard</p>
        </div>
        
        <div class="ni-payment-body">
            <?php if (isset($_GET['error'])): ?>
                <div class="error-message" id="error-message" style="display:block;">
                    Payment failed. Please try again or contact support.
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="success-message" id="success-message" style="display:block;">
                    Payment successful! Your subscription is now active.
                </div>
                <div style="text-align:center;padding:24px 0;">
                    <a href="/pages/settings.php" class="btn btn-primary">Return to Settings</a>
                </div>
            <?php else: ?>
            
                <div class="order-summary">
                    <h3>Order Summary</h3>
                    <div class="order-amount">
                        <?php echo htmlspecialchars($currency); ?> <?php echo number_format(floatval($amount), 2); ?>
                        <small>/ month</small>
                    </div>
                    <div class="order-description"><?php echo htmlspecialchars($description); ?></div>
                    <div style="margin-top:12px;font-size:13px;color:#6c757d;">
                        Company: <?php echo htmlspecialchars($company['company_name'] ?? ''); ?>
                    </div>
                </div>
                
                <div class="error-message" id="gateway-error"></div>
                
                <div class="payment-placeholder" id="payment-section">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="1" y="4" width="22" height="16" rx="2"/>
                        <line x1="1" y1="10" x2="23" y2="10"/>
                        <line x1="6" y1="15" x2="10" y2="15"/>
                    </svg>
                    <h3>Secure Card Payment</h3>
                    <p>Your card details are collected by Mastercard's secure hosted form.<br>
                    We never see or store your card information.</p>
                    
                    <div style="margin-top:24px;">
                        <div style="font-size:13px;color:#6c757d;margin-bottom:8px;">
                            Session: <?php echo htmlspecialchars($sessionId ?: 'Not created yet'); ?>
                        </div>
                        <button type="button" id="pay-button" onclick="startPayment()">
                            Pay Now — <?php echo htmlspecialchars($currency); ?> <?php echo number_format(floatval($amount), 2); ?>
                        </button>
                        <br>
                        <a href="<?php echo htmlspecialchars($cancelUrl); ?>" id="cancel-link">Cancel and return</a>
                    </div>
                </div>
                
                <div class="secure-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                    256-bit SSL encryption · PCI DSS compliant · Powered by Mastercard
                </div>
            
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Load Mastercard Checkout SDK -->
<script src="<?php echo htmlspecialchars($checkoutJsUrl); ?>"></script>

<script>
const CONFIG = {
    sessionId: '<?php echo htmlspecialchars($sessionId ?: ''); ?>',
    merchantId: '<?php echo htmlspecialchars($merchantId ?: ''); ?>',
    gatewayUrl: '<?php echo htmlspecialchars($gatewayUrl); ?>',
    csrfToken: '<?php echo $csrfToken; ?>',
    orderId: '<?php echo htmlspecialchars($orderId ?: ''); ?>',
    amount: '<?php echo htmlspecialchars($amount ?: ''); ?>',
    currency: '<?php echo htmlspecialchars($currency); ?>',
    successUrl: '<?php echo htmlspecialchars($successUrl); ?>',
    errorUrl: '<?php echo htmlspecialchars($errorUrl); ?>',
    companyId: '<?php echo intval($companyId); ?>',
};

let checkoutInstance = null;

function showError(msg) {
    const el = document.getElementById('gateway-error');
    if (el) {
        el.textContent = msg;
        el.style.display = 'block';
    }
}

function startPayment() {
    const btn = document.getElementById('pay-button');
    if (!CONFIG.sessionId) {
        showError('No payment session. Please go back and try again.');
        return;
    }
    
    btn.disabled = true;
    btn.textContent = 'Loading secure payment...';
    
    // Initialize Mastercard Checkout
    Checkout.configure({
        session: {
            id: CONFIG.sessionId,
        },
        merchantId: CONFIG.merchantId,
        order: {
            amount: CONFIG.amount,
            currency: CONFIG.currency,
            description: 'CRM Subscription',
            id: CONFIG.orderId,
        },
        interaction: {
            returnUrl: CONFIG.successUrl,
            cancelUrl: CONFIG.errorUrl,
        },
    });
    
    // Show the hosted payment page
    Checkout.showPaymentPage();
}

// If we already have a session ID, auto-load the checkout
if (CONFIG.sessionId && CONFIG.sessionId !== '') {
    // Pre-configure on page load so button is ready
    document.addEventListener('DOMContentLoaded', function() {
        // Don't auto-start, let user click the button
        // Checkout.configure can be called early for faster experience
        console.log('NI Payment session ready:', CONFIG.sessionId);
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
