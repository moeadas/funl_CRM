<?php
/**
 * White Label CRM - Create Company (super admin) - standalone, no popup
 */
require_once __DIR__ . "/../includes/countries.php";
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();
requireRole('Super Admin');

$db = Database::getInstance();
$csrf_token = generateCSRFToken();

// Get plans list
$plans = $db->query("SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
if (!$plans) {
    // Fallback to default plans
    $plans = [
        ['plan_key' => 'single', 'plan_name' => 'Single User', 'monthly_price' => 10],
        ['plan_key' => 'team', 'plan_name' => 'Team', 'monthly_price' => 40],
        ['plan_key' => 'enterprise', 'plan_name' => 'Enterprise', 'monthly_price' => 90],
    ];
}

$pageTitle = __('Create New Company');
include __DIR__ . '/../includes/header.php';

?>
<script src="/assets/js/phone-picker.js?v=2"></script>


<div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;">
        <a href="/pages/super-admin.php" class="btn btn-outline" style="padding:8px 14px;display:inline-flex;align-items:center;gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            <?php echo htmlspecialchars(__('Back to Super Admin')); ?>
        </a>
        <h1><?php echo htmlspecialchars(__('Create New Company')); ?></h1>
    </div>
</div>

<?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-error" style="max-width:760px;margin-bottom:16px;padding:12px 16px;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;border-radius:8px;">
        <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<div style="max-width:760px;">
    <form method="POST" action="/pages/super-admin.php" onsubmit="document.getElementById("phone").value = document.getElementById("phone_full").value;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="action" value="create_company">

        <div class="card">
            <div class="card-header" style="padding:18px 24px;">
                <h3 class="card-title" style="margin:0;"><?php echo htmlspecialchars(__('Company info')); ?></h3>
            </div>
            <div class="card-body" style="padding:24px;">
                <div class="row-2">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label"><?php echo htmlspecialchars(__('Company name')); ?> *</label>
                        <input type="text" name="company_name" class="form-control" required style="padding:10px 14px;">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label"><?php echo htmlspecialchars(__('Company email')); ?> *</label>
                        <input type="email" name="email" class="form-control" required style="padding:10px 14px;">
                    </div>
                </div>
                <div class="row-2" style="margin-top:16px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <?php echo renderPhonePicker(['id' => 'phone', 'label' => __('Phone'), 'value' => '']); ?>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label"><?php echo htmlspecialchars(__('Plan')); ?> *</label>
                        <select name="plan" class="form-control" style="padding:10px 14px;">
                            <?php foreach ($plans as $plan): ?>
                                <option value="<?php echo htmlspecialchars($plan['plan_key']); ?>">
                                    <?php echo htmlspecialchars($plan['plan_name']); ?> - $<?php echo number_format($plan['monthly_price'], 0); ?>/mo
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:16px;">
            <div class="card-header" style="padding:18px 24px;">
                <h3 class="card-title" style="margin:0;"><?php echo htmlspecialchars(__('Admin user')); ?></h3>
            </div>
            <div class="card-body" style="padding:24px;">
                <div class="row-2">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label"><?php echo htmlspecialchars(__('Admin name')); ?> *</label>
                        <input type="text" name="admin_name" class="form-control" required style="padding:10px 14px;">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label"><?php echo htmlspecialchars(__('Admin email')); ?> *</label>
                        <input type="email" name="admin_email" class="form-control" required style="padding:10px 14px;">
                    </div>
                </div>
                <div class="form-group" style="margin-top:16px;margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Password')); ?> *</label>
                    <input type="password" name="admin_password" class="form-control" required style="padding:10px 14px;">
                </div>
            </div>
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:16px;">
            <a href="/pages/super-admin.php" class="btn btn-outline"><?php echo htmlspecialchars(__('Cancel')); ?></a>
            <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars(__('Create Company')); ?></button>
        </div>
    </form>
</div>


<?php include __DIR__ . '/../includes/footer.php'; ?>
