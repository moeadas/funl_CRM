<?php
/**
 * pages/billing.php
 * 
 * Manage Subscription — full tenant billing portal.
 * 
 * Features:
 *   - Current plan + trial countdown
 *   - Plan upgrade/downgrade (with proration)
 *   - Billing history / invoices (download as PDF)
 *   - Payment method management (NI stored cards)
 *   - Cancel subscription
 *   - Renew / resume after cancel
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../api/ni-checkout-helpers.php';
startSecureSession();
requireLogin();
require_once __DIR__ . '/../includes/company-functions.php';

$currentUser = getCurrentUser();
$companyId = getCurrentCompanyId();
$company = getCompany($companyId);

$db = Database::getInstance();
$plans = $db->query("SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);

// Get recent transactions for invoices
$transactions = $db->query("
    SELECT * FROM payment_transactions
    WHERE company_id = ?
    ORDER BY created_at DESC
    LIMIT 20
", [$companyId])->fetchAll(PDO::FETCH_ASSOC);

// Get stored payment methods (NI Gateway stored cards)
$cards = $db->query("
    SELECT * FROM payment_methods
    WHERE company_id = ?
    ORDER BY created_at DESC
", [$companyId])->fetchAll(PDO::FETCH_ASSOC);

// Handle POST actions
$success = $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFTokenWithExpiry()) {
        $error = 'Invalid request token. Please refresh the page.';
    } else {
        $action = $_POST['action'] ?? '';
        switch ($action) {
            case 'cancel_subscription':
                $db->query("UPDATE companies SET cancel_at_period_end = 1, updated_at = NOW() WHERE company_id = ?", [$companyId]);
                $success = 'Subscription will be cancelled at the end of your current billing period.';
                $company = getCompany($companyId); // refresh
                break;
            case 'resume_subscription':
                $db->query("UPDATE companies SET cancel_at_period_end = 0, updated_at = NOW() WHERE company_id = ?", [$companyId]);
                $success = 'Subscription resumed — you will be charged at the end of this period.';
                $company = getCompany($companyId);
                break;
            case 'switch_to_yearly':
                $db->query("UPDATE companies SET billing_cycle = 'yearly', updated_at = NOW() WHERE company_id = ?", [$companyId]);
                // This only records the preference -- no charge is taken and no
                // invoice is raised here. Say that plainly rather than implying
                // billing changed.
                $success = 'Billing cycle set to yearly. Nothing has been charged now — the yearly rate applies from your next renewal.';
                $company = getCompany($companyId);
                break;
            case 'switch_to_monthly':
                $db->query("UPDATE companies SET billing_cycle = 'monthly', updated_at = NOW() WHERE company_id = ?", [$companyId]);
                $success = 'Billing cycle set to monthly. Nothing has been charged now — the monthly rate applies from your next renewal.';
                $company = getCompany($companyId);
                break;
        }
    }
}

// Must be generated BEFORE the start_subscription auto-submit block below, which
// embeds it in a form. It used to be defined further down, so $csrfToken was
// undefined there and the auto-checkout form posted an EMPTY token -- create_order
// then rejected it as "Invalid CSRF token" and subscribing from registration
// could never work.
$csrfToken = generateCSRFToken();

$returnStatus = $_GET['status'] ?? null;
$sessionId = $_GET['session_id'] ?? null;
$suspended = $_GET['suspended'] ?? null;
$expired = $_GET['expired'] ?? null;
$startSubscription = $_GET['start_subscription'] ?? null;

// If start_subscription=1 and we have a pending subscription, auto-trigger create_order
if ($startSubscription === '1' && !empty($_SESSION['pending_subscription'])) {
    $pending = $_SESSION['pending_subscription'];
    if (($pending['company_id'] ?? 0) === (int)$companyId) {
        // Build the create_order form and submit it
        $planKey = htmlspecialchars($pending['plan_key'] ?? 'single');
        echo '<form id="auto-pending" method="POST" action="/api/ni-checkout.php?action=create_order">';
        echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '">';
        echo '<input type="hidden" name="plan_key" value="' . $planKey . '">';
        echo '<input type="hidden" name="billing_cycle" value="monthly">';
        echo '<input type="hidden" name="company_id" value="' . (int)$companyId . '">';
        echo '</form>';
        echo '<script>document.getElementById("auto-pending").submit();</script>';
        echo '<p style="text-align:center;padding:40px;">Redirecting to secure checkout...</p>';
        exit;
    }
}

// Resolve the price for the CURRENT billing cycle.
// `companies` only denormalises plan_price_monthly and has no yearly column, so
// the card always printed the MONTHLY figure next to a "/yearly" label. Switching
// cycle therefore looked like it did nothing -- the number never moved. Read the
// real price from `plans` instead.
$currentCycle   = $company['billing_cycle'] ?? 'monthly';
$currentPlanRow = null;
foreach ($plans as $__p) {
    if (($__p['plan_key'] ?? '') === ($company['plan_id'] ?? '')) { $currentPlanRow = $__p; break; }
}
$currentPrice = $currentPlanRow
    ? (float)($currentCycle === 'yearly' ? $currentPlanRow['yearly_price'] : $currentPlanRow['monthly_price'])
    : (float)($company['plan_price_monthly'] ?? 0);
$cycleLabel = ($currentCycle === 'yearly') ? 'year' : 'month';

// Is the payment gateway actually usable? Without credentials every plan button
// dies with "Failed to create checkout session", so say so up front rather than
// letting the user discover it by clicking.
$niSettings   = function_exists('getNIGatewaySettings') ? getNIGatewaySettings() : [];
// ni_payment.php refuses unless ni_enabled === '1'. Checking only the credentials
// here meant billing said "ready", sent the user to checkout, and only the final
// page revealed payments were switched off. Check the same condition it does.
$gatewayHasCreds = !empty($niSettings['ni_api_username']) && !empty($niSettings['ni_merchant_id']);
$gatewayEnabled  = (($niSettings['ni_enabled'] ?? '0') === '1');
$gatewayReady    = $gatewayHasCreds && $gatewayEnabled;

// Surface an error passed back by the checkout API redirect.
$checkoutError = trim($_GET['error'] ?? '');

// This page used to be a standalone HTML document with its own <head>, its own
// CSS reset and its own font stack. That is why it looked nothing like the rest
// of the app and dropped the user out of it entirely -- there was no sidebar and
// no way back except the browser Back button. It now renders inside the normal
// app chrome like every other page.
$pageTitle   = 'Manage Subscription';
$currentPage = 'billing';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
        /* Page-scoped styles. The global `* { }` reset and the `body { }` font /
           background rules that used to live here were removed: they would now
           fight the app theme instead of styling a standalone page. */
        .container { max-width: 1100px; margin: 0 auto; padding: 0; }
        h1 { font-size: 24px; margin-bottom: 4px; }
        h2 { font-size: 16px; color: #666; margin-bottom: 16px; text-transform: uppercase; letter-spacing: .5px; }
        .subtitle { color: #666; margin-bottom: 30px; }
        
        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 24px; font-size: 14px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        
        /* Current plan */
        .current-plan { background: white; border-radius: 12px; padding: 28px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .plan-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
        .plan-name { font-size: 28px; font-weight: 700; }
        .plan-price { font-size: 16px; color: #666; }
        .plan-price strong { font-size: 32px; color: #333; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-trial { background: #e8f4fd; color: #1976d2; }
        .badge-active { background: #d4edda; color: #155724; }
        .badge-past_due { background: #fff3cd; color: #856404; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }
        .badge-suspended { background: #dc3545; color: white; }
        .plan-meta { display: flex; gap: 32px; flex-wrap: wrap; font-size: 14px; color: #666; margin: 16px 0; padding: 16px 0; border-top: 1px solid #f0f0f0; border-bottom: 1px solid #f0f0f0; }
        .plan-meta-item { display: flex; flex-direction: column; }
        .plan-meta-label { font-size: 11px; text-transform: uppercase; color: #aaa; margin-bottom: 2px; }
        
        /* Trial countdown */
        .trial-countdown { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 10px; padding: 20px 24px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; }
        .trial-countdown-text { font-size: 15px; }
        .trial-countdown-text strong { font-size: 18px; }
        .trial-days-left { font-size: 36px; font-weight: 700; }
        .trial-days-label { font-size: 12px; opacity: .8; text-align: right; }
        
        /* Cancel banner */
        .cancel-banner { background: #fff3cd; color: #856404; border-radius: 8px; padding: 16px 20px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
        .cancel-banner .icon { font-size: 24px; }
        
        /* Tabs */
        .tabs { background: white; border-radius: 12px 12px 0 0; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .tab-links { display: flex; border-bottom: 1px solid #e0e0e0; }
        .tab-link { padding: 14px 24px; cursor: pointer; font-size: 14px; font-weight: 500; color: #666; border-bottom: 2px solid transparent; }
        .tab-link.active { color: #667eea; border-bottom-color: #667eea; }
        .tab-link:hover { color: #667eea; }
        
        .tab-pane { display: none; padding: 28px; }
        .tab-pane.active { display: block; }
        
        /* Plans grid */
        .plans-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 28px; }
        @media (max-width: 800px) { .plans-grid { grid-template-columns: 1fr; } }
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
        
        /* Billing history */
        .invoice-table { width: 100%; border-collapse: collapse; }
        .invoice-table th { text-align: left; font-size: 12px; text-transform: uppercase; color: #999; padding: 10px 12px; background: #f9fafb; font-weight: 600; }
        .invoice-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .invoice-table tr:last-child td { border: none; }
        .empty-state { text-align: center; padding: 40px; color: #999; }
        
        /* Payment method */
        .card-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
        .card { background: #f9fafb; border-radius: 10px; padding: 16px; border: 1px solid #e0e0e0; }
        .card .brand { font-size: 18px; font-weight: 700; }
        .card .number { font-family: monospace; font-size: 16px; margin: 8px 0; }
        .card .meta { font-size: 12px; color: #666; }
        .card .default-badge { background: #d4edda; color: #155724; font-size: 10px; padding: 2px 8px; border-radius: 10px; }
        
        /* Buttons */
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; }
        .btn-primary:disabled { background: #a0a0a0; cursor: not-allowed; }
        .btn-secondary { background: #f0f0f0; color: #333; }
        .btn-secondary:hover { background: #e0e0e0; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-block { width: 100%; justify-content: center; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        
        /* Hidden form */
        .hidden { display: none; }
        
        /* Loading overlay */
        #checkout-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 9999; align-items: center; justify-content: center; flex-direction: column; gap: 16px; }
        #checkout-overlay.show { display: flex; }
        .spinner { width: 48px; height: 48px; border: 4px solid rgba(255,255,255,.3); border-top-color: white; border-radius: 50%; animation: spin .8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .checkout-msg { color: white; font-size: 16px; font-weight: 600; }
    </style>
<?php if ($checkoutError): ?>
    <div class="alert alert-error">
        <strong>Checkout could not start</strong> &mdash; <?= htmlspecialchars($checkoutError) ?>
    </div>
<?php endif; ?>
<?php if (!$gatewayReady): ?>
    <div class="alert alert-warning">
        <?php if (!$gatewayHasCreds): ?>
            <strong>Payments are not set up yet</strong> &mdash; the payment gateway has no credentials configured,
            so subscribing and plan changes cannot be completed.
            <?php if (isSuperAdmin()): ?>
                Add the merchant ID, API username and password under
                <a href="/pages/super-admin.php">Super Admin &rsaquo; Platform Settings</a>.
            <?php else: ?>
                Please contact support.
            <?php endif; ?>
        <?php else: ?>
            <strong>Payments are switched off</strong> &mdash; credentials are configured, but the gateway is disabled,
            so checkout will not complete.
            <?php if (isSuperAdmin()): ?>
                Tick <em>Enable NI Gateway</em> under
                <a href="/pages/super-admin.php">Super Admin &rsaquo; Platform Settings</a> and save.
            <?php else: ?>
                Please contact support.
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php if ($suspended): ?>
    <div class="container" style="margin-top: 60px;">
        <div class="alert alert-error">
            <strong>⚠️ Account Suspended</strong> — Your account has been suspended by the platform admin. Please contact support to restore access.
        </div>
        <a href="?" class="btn btn-secondary">← Back to Billing</a>
    </div>
<?php elseif ($returnStatus === 'success' && $sessionId): ?>
    <?php
    // Verify payment server-side via the API
    $verifyResult = null;
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
    } catch (Exception $e) {}
    ?>
    <div class="container">
        <div class="alert <?= ($verifyResult['success'] ?? false) ? 'alert-success' : 'alert-error' ?>">
            <?php if (($verifyResult['success'] ?? false)): ?>
                <strong>✓ Payment Successful!</strong> Your subscription has been activated. Welcome to <?= htmlspecialchars($verifyResult['plan_name'] ?? 'your new plan') ?>!
            <?php else: ?>
                <strong>Payment Verification Failed</strong><br>
                <?= htmlspecialchars($verifyResult['message'] ?? 'Please contact support.') ?>
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
<?php else: ?>
    <div class="container">
        <h1>Manage Subscription</h1>
        <p class="subtitle">Manage your plan, payment methods, and view invoices.</p>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
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
        
        <?php if (!empty($company['cancel_at_period_end'])): ?>
        <div class="cancel-banner">
            <span class="icon">⚠️</span>
            <div style="flex:1;">
                <strong>Subscription will cancel</strong> on <?= $company['current_period_end'] ? date('M j, Y', strtotime($company['current_period_end'])) : 'period end' ?>
                <br><small>You can resume anytime before then.</small>
            </div>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="resume_subscription">
                <button class="btn btn-primary btn-sm">Resume</button>
            </form>
        </div>
        <?php endif; ?>
        
        <?php if (in_array($company['subscription_status'], ['active', 'trial'])): ?>
        <!-- Current plan card -->
        <div class="current-plan">
            <h2>Current Plan</h2>
            <div class="plan-header">
                <div>
                    <div class="plan-name"><?= htmlspecialchars($company['plan_name'] ?: 'No Plan') ?></div>
                    <div class="plan-price">
                        <strong>$<?= number_format($currentPrice, 2) ?></strong>
                        <span>/<?= htmlspecialchars($cycleLabel) ?></span>
                    </div>
                </div>
                <div>
                    <span class="badge badge-<?= $company['subscription_status'] ?: 'trial' ?>">
                        <?= ucfirst($company['subscription_status'] ?: 'trial') ?>
                    </span>
                </div>
            </div>
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
                <div class="plan-meta-item">
                    <span class="plan-meta-label">Billing cycle</span>
                    <span><?= ucfirst($company['billing_cycle'] ?? 'monthly') ?></span>
                </div>
            </div>
            
            <!-- Quick actions -->
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px;">
                <?php if (($company['billing_cycle'] ?? 'monthly') === 'monthly'): ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="switch_to_yearly">
                    <button class="btn btn-secondary btn-sm">💰 Switch to Yearly (Save 17%)</button>
                </form>
                <?php else: ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="switch_to_monthly">
                    <button class="btn btn-secondary btn-sm">Switch to Monthly</button>
                </form>
                <?php endif; ?>
                <?php if (empty($company['cancel_at_period_end'])): ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="cancel_subscription">
                    <button class="btn btn-danger btn-sm" type="submit" data-confirm="Cancel subscription? You will keep access until the end of your current billing period.">Cancel Subscription</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php elseif ($company['subscription_status'] === 'past_due'): ?>
        <div class="alert alert-warning">
            <strong>⚠️ Payment Failed</strong> — We couldn't charge your card. Please update your payment method or subscribe to restore access.
        </div>
        <?php elseif ($company['subscription_status'] === 'cancelled'): ?>
        <div class="alert alert-error">
            <strong>Subscription Cancelled</strong> — Your access is limited. Resubscribe below to restore full features.
        </div>
        <?php endif; ?>
        
        <!-- Tabbed content -->
        <div class="tabs">
            <div class="tab-links">
                <div class="tab-link active" data-tab="plans" onclick="switchTab('plans')">Plans</div>
                <div class="tab-link" data-tab="billing" onclick="switchTab('billing')">Billing History</div>
                <div class="tab-link" data-tab="payment" onclick="switchTab('payment')">Payment Methods</div>
            </div>
            
            <!-- Plans tab -->
            <div class="tab-pane active" id="pane-plans">
                <h2 style="margin-top:0;">Choose Your Plan</h2>
                <div class="plans-grid">
                    <?php foreach ($plans as $plan): 
                        $isCurrent = ($company['plan_id'] === $plan['plan_key']);
                        $yearlySave = ($plan['monthly_price'] * 12) - $plan['yearly_price'];
                    ?>
                    <div class="plan-card <?= $isCurrent ? 'current' : '' ?>">
                        <div class="plan-card-title"><?= htmlspecialchars($plan['plan_name']) ?></div>
                        <div class="plan-card-price">
                            $<?= number_format($plan['monthly_price'], 2) ?><span>/mo</span>
                        </div>
                        <div class="plan-card-desc">
                            $<?= number_format($plan['yearly_price'], 0) ?>/year (save $<?= number_format($yearlySave, 0) ?>)<br>
                            Up to <?= (int)$plan['user_limit'] ?> users
                        </div>
                        <ul class="plan-card-features">
                            <li>Full CRM with all features</li>
                            <li><?= (int)$plan['user_limit'] ?> user<?= $plan['user_limit'] > 1 ? 's' : '' ?> included</li>
                            <li>Lead &amp; deal pipeline</li>
                            <li>Automation &amp; web forms</li>
                            <li>Email campaigns</li>
                            <li>Priority support</li>
                        </ul>
                        <?php if ($isCurrent): ?>
                            <button class="btn btn-secondary btn-block" disabled>Current Plan</button>
                        <?php else: ?>
                            <button class="btn btn-primary btn-block" onclick="subscribeTo('<?= $plan['plan_key'] ?>')">
                                <?= $company['subscription_status'] === 'active' ? 'Switch to' : 'Subscribe to' ?> <?= htmlspecialchars($plan['plan_name']) ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Billing History tab -->
            <div class="tab-pane" id="pane-billing">
                <h2 style="margin-top:0;">Billing History & Invoices</h2>
                <?php if (empty($transactions)): ?>
                    <div class="empty-state">
                        <div style="font-size:48px;margin-bottom:12px;">📄</div>
                        <p>No billing history yet.</p>
                        <p style="font-size:13px;margin-top:4px;">Once you subscribe, your invoices will appear here.</p>
                    </div>
                <?php else: ?>
                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Invoice</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $t): ?>
                        <tr>
                            <td><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($t['plan_name'] ?? $t['plan_key'] ?? 'Subscription') ?></strong>
                                <br><code style="font-size:11px;color:#999;"><?= htmlspecialchars($t['order_id']) ?></code>
                            </td>
                            <td>$<?= number_format((float)$t['amount'], 2) ?> <?= $t['currency'] ?></td>
                            <td>
                                <span class="badge badge-<?= $t['status'] === 'completed' ? 'active' : ($t['status'] === 'failed' ? 'cancelled' : 'trial') ?>">
                                    <?= htmlspecialchars($t['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($t['status'] === 'completed'): ?>
                                    <a href="invoice.php?id=<?= $t['id'] ?>" target="_blank" class="btn btn-secondary btn-sm">Download PDF</a>
                                <?php else: ?>
                                    <span style="color:#999;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            
            <!-- Payment Methods tab -->
            <div class="tab-pane" id="pane-payment">
                <h2 style="margin-top:0;">Payment Methods</h2>
                <p style="color:#666;margin-bottom:20px;font-size:14px;">
                    Your card information is securely stored with our payment processor (Network International). We never see or store your card number.
                </p>
                
                <?php if (empty($cards)): ?>
                    <div class="empty-state">
                        <div style="font-size:48px;margin-bottom:12px;">💳</div>
                        <p>No payment methods on file.</p>
                        <p style="font-size:13px;margin-top:4px;">Your card will be added automatically when you complete a payment.</p>
                    </div>
                <?php else: ?>
                <div class="card-list">
                    <?php foreach ($cards as $c): ?>
                    <div class="card">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <div class="brand">
                                <?= htmlspecialchars($c['brand'] ?? 'Card') ?>
                            </div>
                            <?php if (!empty($c['is_default'])): ?>
                                <span class="default-badge">Default</span>
                            <?php endif; ?>
                        </div>
                        <div class="number">•••• •••• •••• <?= htmlspecialchars($c['last4'] ?? '****') ?></div>
                        <div class="meta">
                            Expires <?= htmlspecialchars($c['exp_month'] ?? '??') ?>/<?= htmlspecialchars($c['exp_year'] ?? '??') ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div style="margin-top:24px;padding:16px;background:#f0f9ff;border-radius:8px;font-size:13px;color:#0369a1;">
                    <strong>How to add a new card:</strong> Subscribe to a plan above and your card will be securely stored with our payment processor. Your card details never touch our servers.
                </div>
            </div>
        </div>
        
        <!-- NI Gateway: form that creates order and redirects to hosted checkout -->
        <form id="ni-checkout-form" method="POST" action="/api/ni-checkout.php?action=create_order" style="display:none;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="plan_key" id="form-plan-key">
            <input type="hidden" name="billing_cycle" id="form-billing-cycle" value="monthly">
            <input type="hidden" name="company_id" value="<?= (int)$companyId ?>">
        </form>
    </div>
    
    <!-- Loading overlay -->
    <div id="checkout-overlay">
        <div class="spinner"></div>
        <div class="checkout-msg" id="checkout-msg">Preparing secure checkout...</div>
    </div>
    
    <script>
    function switchTab(tabId) {
        document.querySelectorAll('.tab-link').forEach(link => {
            link.classList.toggle('active', link.getAttribute('data-tab') === tabId);
        });
        document.querySelectorAll('.tab-pane').forEach(pane => {
            pane.classList.toggle('active', pane.id === 'pane-' + tabId);
        });
        // Update hash for shareable URLs
        history.replaceState(null, '', '#' + tabId);
    }
    
    // Read tab from URL hash on load
    if (location.hash) {
        const t = location.hash.substring(1);
        if (['plans', 'billing', 'payment'].includes(t)) switchTab(t);
    }
    
    function showOverlay(msg) {
        document.getElementById('checkout-msg').textContent = msg || 'Please wait...';
        document.getElementById('checkout-overlay').classList.add('show');
    }
    function hideOverlay() {
        document.getElementById('checkout-overlay').classList.remove('show');
    }
    
    function subscribeTo(planKey) {
        const form = document.getElementById('ni-checkout-form');
        document.getElementById('form-plan-key').value = planKey;
        showOverlay('Preparing secure checkout...');
        form.submit();
    }
    </script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
