<?php
/**
 * White Label CRM - Create Company Form
 */
require_once "../includes/countries.php";
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/company-functions.php';
startSecureSession();
requireLogin();
requireSuperAdmin();

$plans = getActivePlans();
$csrf_token = generateCSRFToken();
$pageTitle = __('Create New Company');
include '../includes/header.php';

?>
<script src="/assets/js/phone-picker.js?v=2"></script>


<style>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 24px; box-shadow: var(--shadow-xs); margin-bottom: 24px; }
.card-title { font-size: 16px; font-weight: 600; color: var(--color-text); margin: 0 0 16px 0; border-bottom: 1px solid var(--color-border-light); padding-bottom: 8px; }
.form-group { margin-bottom: 16px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } }
.form-label { display: block; font-size: 13px; font-weight: 500; color: var(--color-text); margin-bottom: 6px; }
.form-control { width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); font-size: 14px; color: var(--color-text); background: var(--color-surface); box-sizing: border-box; }
.form-control:focus { outline: none; border-color: var(--color-accent); }
.btn { padding: 10px 18px; border-radius: var(--radius-sm); font-size: 14px; font-weight: 500; cursor: pointer; transition: all var(--transition); border: none; text-decoration: none; display: inline-block; }
.btn-primary { background: var(--color-accent); color: #fff; }
.btn-primary:hover { background: var(--color-accent-hover); }
.btn-outline { background: var(--color-surface); color: var(--color-text); border: 1px solid var(--color-border); margin-right: 8px; }
.btn-outline:hover { background: var(--color-bg); }
</style>

<div class="page-container">
    <div class="page-header">
        <div style="display:flex;align-items:center;gap:16px;">
            <a href="/pages/super-admin.php" class="btn btn-outline" style="padding:8px 14px;">← <?php echo __('back'); ?></a>
            <h1 class="page-title"><?php echo __('Create New Company'); ?></h1>
        </div>
    </div>

    <form method="POST" action="/pages/super-admin.php" style="max-width: 800px;" onsubmit="var p=document.getElementById("phone_full");if(p)document.getElementById("phone").value=p.value;">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="action" value="create_company">

        <div class="card">
            <h3 class="card-title"><?php echo __('Company Info'); ?></h3>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><?php echo __('Company Name'); ?> *</label>
                    <input type="text" name="company_name" class="form-control" required placeholder="e.g. Acme Corp">
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('Company Email'); ?> *</label>
                    <input type="email" name="email" class="form-control" required placeholder="e.g. contact@acme.com">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <?php echo renderPhonePicker(['id' => 'phone', 'label' => __('Phone'), 'value' => '']); ?>
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('Plan'); ?> *</label>
                    <select name="plan" class="form-control">
                        <?php foreach ($plans as $plan): ?>
                            <option value="<?php echo $plan['plan_key']; ?>">
                                <?php echo htmlspecialchars($plan['plan_name']); ?> - $<?php echo number_format($plan['monthly_price'], 0); ?>/mo
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="card">
            <h3 class="card-title"><?php echo __('Admin User'); ?></h3>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><?php echo __('Admin Name'); ?> *</label>
                    <input type="text" name="admin_name" class="form-control" required placeholder="e.g. John Doe">
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('Admin Email'); ?> *</label>
                    <input type="email" name="admin_email" class="form-control" required placeholder="e.g. admin@acme.com">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo __('Password'); ?> *</label>
                <input type="password" name="admin_password" class="form-control" required placeholder="Enter secure password">
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 16px;">
            <a href="/pages/super-admin.php" class="btn btn-outline"><?php echo __('Cancel'); ?></a>
            <button type="submit" class="btn btn-primary"><?php echo __('Create Company'); ?></button>
        </div>
    </form>
</div>


<?php include '../includes/footer.php'; ?>
