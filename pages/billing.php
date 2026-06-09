<?php
/**
 * pages/billing.php
 * 
 * Manage Subscription tab — NI Gateway / Network International payments.
 * Shows current plan, trial status, and allows upgrading via hosted Mastercard checkout.
 * 
 * 3DS flow:
 *   1. User selects plan → create_order (initiates 3DS auth)
 *   2. 3DS challenge happens on Mastercard side
 *   3. Gateway sends async webhook (INBOUND_AUTH) with 3DS result
 *   4. We update session with 3DS result, create checkout session
 *   5. User completes checkout on hosted page → returns to return_url
 *   6. verify_payment activates subscription
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireLogin();
require_once __DIR__ . '/../includes/company-functions.php';

$currentUser = getCurrentUser();
$companyId = getCurrentCompanyId();
$company = getCompany($companyId);

// Get available plans
$db = Database::getInstance();
$plans = $db->query("SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);

// Handle return from checkout.js
$returnStatus = $_GET['status'] ?? null;
$sessionId = $_GET['session_id'] ?? null;
$errorCode = $_GET['error_code'] ?? null;
$errorMsg = $_GET['error_message'] ?? null;
$suspended = $_GET['suspended'] ?? null;
$expired = $_GET['expired'] ?? null;

// Auto-detect expired trial
if (!$expired && isset($company['subscription_status']) && $company['subscription_status'] === 'trial' 
    && isset($company['trial_ends_at']) && strtotime($company['trial_ends_at']) < time()) {
    $expired = '1';
}

// CSRF token for forms
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subscription — FunL CRM</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f4f6f9; color: #333; }
        .container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
        h1 { font-size: 24px; margin-bottom: 8px; }
        .subtitle { color: #666; margin-bottom: 30px; }
        
        /* Alerts */
        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 24px; font-size: 14px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        
        /* Current plan card */
        .current-plan { background: white; border-radius: 12px; padding: 28px; margin-bottom: 28px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .current-plan h2 { font-size: 16px; color: #666; margin-bottom: 16px; text-transform: uppercase; letter-spacing: .5px; }
        .plan-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .plan-name { font-size: 28px; font-weight: 700; }
        .plan-price { font-size: 20px; color: #666; }
        .plan-price strong { font-size: 32px; color: #333; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-trial { background: #e8f4fd; color: #1976d2; }
        .badge-active { background: #d4edda; color: #155724; }
        .badge-past_due { background: #fff3cd; color: #856404; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }
        .plan-meta { display: flex; gap: 32px; flex-wrap: wrap; font-size: 14px; color: #666; margin-top: 8px; }
        .plan-meta-item { display: flex; flex-direction: column; }
        .plan-meta-label { font-size: 11px; text-transform: uppercase; color: #aaa; margin-bottom: 2px; }
        
        /* Trial countdown */
        .trial-countdown { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 10px; padding: 20px 24px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; }
        .trial-countdown-text { font-size: 15px; }
        .trial-countdown-text strong { font-size: 18px; }
        .trial-days-left { font-size: 36px; font-weight: 700; }
        .trial-days-label { font-size: 12px; opacity: .8; text-align: right; }
        
        /* Suspended banner */
        .suspended-banner { background: #f8d7da; color: #721c24; border-radius: 8px; padding: 16px 20px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
        .suspended-banner .icon { font-size: 24px; }
        
        /* Plans grid */
        .plans-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 28px; }
        @media (max-width: 700px) { .plans-grid { grid-template-columns: 1fr; } }
        
        .plan-card { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.08); transition: box-shadow .2s; }
        .plan-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.12); }
        .plan-card.current { border: 2px solid #667eea; }
        .plan-card-title { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
        .plan-card-price { font-size: 28px; font-weight: 700; color: #333; margin-bottom: 4px; }
        .plan-card-price span { font-size: 14px; color: #999; font-weight: 400; }
        .plan-card-desc { font-size: 13px; color: #888; margin-bottom: 16px; }
        .plan-card-features { list-style: none; margin-bottom: 20px; }
        .plan-card-features li { font-size: 13px; color: #555; padding: 5px 0; border-bottom: 1px solid #f0f0f0; }
        .plan-card-features li:last-child { border: none; }
        .plan-card-features li::before { content: '✓ '; color: #667eea; font-weight: 700; }
        
        /* Buttons */
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: all .2s; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; }
        .btn-primary:disabled { background: #a0a0a0; cursor: not-allowed; }
        .btn-secondary { background: #f0f0f0; color: #333; }
        .btn-secondary:hover { background: #e0e0e0; }
        .btn-block { width: 100%; justify-content: center; }
        
        /* Hidden form */
        .hidden { display: none; }
        
        /* Loading overlay */
        #checkout-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 9999; align-items: center; justify-content: center; flex-direction: column; gap: 16px; }
        #checkout-overlay.show { display: flex; }
        .spinner { width: 48px; height: 48px; border: 4px solid rgba(255,255,255,.3); border-top-color: white; border-radius: 50%; animation: spin .8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .checkout-msg { color: white; font-size: 16px; font-weight: 600; }
        
        /* 3DS iframe */
        #three-ds-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.7); z-index: 9998; align-items: center; justify-content: center; }
        #three-ds-overlay.show { display: flex; }
        #three-ds-iframe { width: 500px; height: 600px; border: none; border-radius: 12px; background: white; }
        
        /* Payment result */
        .payment-result { text-align: center; padding: 40px; }
        .payment-result .icon { font-size: 64px; margin-bottom: 16px; }
        .payment-result h2 { margin-bottom: 8px; }
        .payment-result p { color: #666; }
    </style>
</head>
<body>
<?php if ($suspended): ?>
    <div class="container" style="margin-top: 60px;">
        <div class="suspended-banner">
            <span class="icon">⚠️</span>
            <div>
                <strong>Account Suspended</strong><br>
                Your account has been suspended. Please contact support or upgrade your plan to restore access.
            </div>
        </div>
        <a href="?" class="btn btn-secondary">← Back to Billing</a>
    </div>
<?php elseif ($returnStatus === 'success' && $sessionId): ?>
    <?php
    // Returned from checkout.js — verify payment and activate
    $verifyResult = null;
    $verifyError = null;
    try {
        $ch = curl_init("https://app.funl.online/api/ni-checkout.php?action=verify_payment");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'csrf_token' => $csrfToken,
            'session_id' => $sessionId,
            'company_id' => $companyId,
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode === 200 && $resp) {
            $verifyResult = json_decode($resp, true);
        }
    } catch (Exception $e) {
        $verifyError = $e->getMessage();
    }
    ?>
    <div class="container">
        <div class="alert <?= ($verifyResult['success'] ?? false) ? 'alert-success' : 'alert-error' ?>">
            <?php if (($verifyResult['success'] ?? false)): ?>
                <strong>✓ Payment Successful!</strong> Your subscription has been activated.
            <?php else: ?>
                <strong>Payment Verification Failed</strong><br>
                <?= htmlspecialchars($verifyResult['message'] ?? $verifyError ?? 'Please contact support.') ?>
            <?php endif; ?>
        </div>
        <div style="text-align:center; margin-top: 24px;">
            <a href="billing.php" class="btn btn-primary">← Back to Billing</a>
        </div>
    </div>
<?php elseif ($returnStatus === 'cancelled'): ?>
    <div class="container">
        <div class="alert alert-warning">
            <strong>Payment Cancelled</strong> — You can try again anytime from your billing page.
        </div>
        <div style="text-align:center; margin-top: 24px;">
            <a href="billing.php" class="btn btn-secondary">← Back to Billing</a>
        </div>
    </div>
<?php elseif ($returnStatus === 'error'): ?>
    <div class="container">
        <div class="alert alert-error">
            <strong>Payment Error<?= $errorCode ? " ($errorCode)" : '' ?></strong><br>
            <?= htmlspecialchars($errorMsg ?: 'An error occurred during payment. Please try again.') ?>
        </div>
        <div style="text-align:center; margin-top: 24px;">
            <a href="billing.php" class="btn btn-secondary">← Back to Billing</a>
        </div>
    </div>
<?php else: ?>
    <div class="container">
        <h1>Manage Subscription</h1>
        <p class="subtitle">Choose a plan that fits your team. Upgrading takes less than a minute.</p>
        
        <?php if ($expired): ?>
        <div class="alert alert-warning">
            <strong>⏰ Trial Expired</strong> — Your trial period has ended. Please subscribe to continue using FunL CRM.
        </div>
        <?php endif; ?>
        
        <?php if ($company['subscription_status'] === 'trial'): ?>
        <?php
        $trialEnd = strtotime($company['trial_ends_at']);
        $daysLeft = max(0, ceil(($trialEnd - time()) / 86400));
        ?>
        <div class="trial-countdown">
            <div class="trial-countdown-text">
                <?php if ($daysLeft > 0): ?>
                    Your trial ends in <strong><?= $daysLeft ?> day<?= $daysLeft !== 1 ? 's' : '' ?></strong>
                <?php else: ?>
                    <strong>Trial has expired</strong> — Subscribe now to keep your data
                <?php endif; ?>
            </div>
            <div>
                <div class="trial-days-left"><?= $daysLeft ?></div>
                <div class="trial-days-label">day<?= $daysLeft !== 1 ? 's' : '' ?> left</div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($company['subscription_status'] === 'active'): ?>
        <div class="current-plan">
            <h2>Current Plan</h2>
            <div class="plan-header">
                <div>
                    <div class="plan-name"><?= htmlspecialchars($company['plan_name'] ?: 'No Plan') ?></div>
                    <div class="plan-meta">
                        <?php if ($company['current_period_end']): ?>
                        <div class="plan-meta-item">
                            <span class="plan-meta-label">Next billing date</span>
                            <span><?= date('M j, Y', strtotime($company['current_period_end'])) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($company['plan_user_limit']): ?>
                        <div class="plan-meta-item">
                            <span class="plan-meta-label">User limit</span>
                            <span><?= (int)$company['plan_user_limit'] ?> users</span>
                        </div>
                        <?php endif; ?>
                        <?php if ($company['cancel_at_period_end']): ?>
                        <div class="plan-meta-item">
                            <span class="plan-meta-label">Status</span>
                            <span style="color:#dc3545;">Cancels at period end</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <span class="badge badge-<?= $company['subscription_status'] ?>">
                    <?= ucfirst($company['subscription_status']) ?>
                </span>
            </div>
        </div>
        <?php elseif ($company['subscription_status'] === 'past_due'): ?>
        <div class="alert alert-warning">
            <strong>⚠️ Payment Failed</strong> — We couldn't charge your card. Please update your payment method to avoid service interruption.
        </div>
        <?php elseif ($company['subscription_status'] === 'cancelled'): ?>
        <div class="alert alert-error">
            <strong>Subscription Cancelled</strong> — Your access is limited. Resubscribe to restore full features.
        </div>
        <?php endif; ?>
        
        <div class="plans-grid">
            <?php foreach ($plans as $plan): 
                $isCurrent = ($company['plan_id'] === $plan['plan_key']);
            ?>
            <div class="plan-card <?= $isCurrent ? 'current' : '' ?>">
                <div class="plan-card-title"><?= htmlspecialchars($plan['plan_name']) ?></div>
                <div class="plan-card-price">
                    $<?= number_format($plan['monthly_price'], 2) ?><span>/mo</span>
                </div>
                <div class="plan-card-desc">
                    $<?= number_format($plan['yearly_price'], 0) ?>/year (save ~17%)<br>
                    Up to <?= (int)$plan['user_limit'] ?> users
                </div>
                <ul class="plan-card-features">
                    <li>CRM with all features</li>
                    <li><?= (int)$plan['user_limit'] ?> user<?= $plan['user_limit'] > 1 ? 's' : '' ?></li>
                    <li>Lead &amp; deal pipeline</li>
                    <li>Automation &amp; web forms</li>
                    <li>Priority support</li>
                </ul>
                <?php if ($isCurrent): ?>
                    <button class="btn btn-secondary btn-block" disabled>Current Plan</button>
                <?php elseif ($company['subscription_status'] === 'active'): ?>
                    <button class="btn btn-primary btn-block" onclick="subscribeTo('<?= $plan['plan_key'] ?>', 'monthly')">
                        Switch to <?= htmlspecialchars($plan['plan_name']) ?>
                    </button>
                <?php else: ?>
                    <button class="btn btn-primary btn-block" onclick="subscribeTo('<?= $plan['plan_key'] ?>', 'monthly')">
                        Subscribe — $<?= number_format($plan['monthly_price'], 2) ?>/mo
                    </button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- NI Gateway: form that creates order and redirects to hosted checkout -->
        <form id="ni-checkout-form" method="POST" action="/api/ni-checkout.php?action=create_order" style="display:none;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="plan_key" id="form-plan-key">
            <input type="hidden" name="billing_cycle" id="form-billing-cycle">
            <input type="hidden" name="company_id" value="<?= (int)$companyId ?>">
        </form>
    </div>
    
    <!-- 3DS challenge overlay -->
    <div id="three-ds-overlay">
        <iframe id="three-ds-iframe" name="three-ds-iframe"></iframe>
    </div>
    
    <!-- Loading overlay -->
    <div id="checkout-overlay">
        <div class="spinner"></div>
        <div class="checkout-msg" id="checkout-msg">Preparing secure checkout...</div>
    </div>
    
    <script>
    // Show/hide overlays
    function showOverlay(msg) {
        document.getElementById('checkout-msg').textContent = msg || 'Please wait...';
        document.getElementById('checkout-overlay').classList.add('show');
    }
    function hideOverlay() {
        document.getElementById('checkout-overlay').classList.remove('show');
    }
    function show3DS(iframeTarget) {
        document.getElementById('three-ds-overlay').classList.add('show');
    }
    function hide3DS() {
        document.getElementById('three-ds-overlay').classList.remove('show');
    }
    
    // Subscribe to a plan — creates NI order and redirects to hosted checkout
    function subscribeTo(planKey, billingCycle) {
        const form = document.getElementById('ni-checkout-form');
        document.getElementById('form-plan-key').value = planKey;
        document.getElementById('form-billing-cycle').value = billingCycle;
        
        showOverlay('Preparing secure checkout...');
        form.submit();
    }
    
    // Listen for 3DS messages from iframe
    window.addEventListener('message', function(event) {
        if (event.data && event.data.type === '3dsComplete') {
            hide3DS();
            if (event.data.success) {
                showOverlay('Completing payment...');
                // The checkout form auto-submited, just wait for redirect
            } else {
                hideOverlay();
                alert('3D Secure authentication failed: ' + (event.data.message || 'Please try again.'));
            }
        }
    });
    
    // Auto-submit checkout when iframe signals ready
    window.addEventListener('message', function(event) {
        if (event.data && event.data.type === 'checkoutReady') {
            // Form is ready in the iframe, auto-submit
            const form = document.getElementById('ni-checkout-form');
            form.target = 'three-ds-iframe';
        }
    });
    </script>
<?php endif; ?>
</body>
</html>