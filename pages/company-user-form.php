<?php
/**
 * White Label CRM - Add User to Company Form
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
startSecureSession();
requireLogin();
requireSuperAdmin();

$companyId = intval($_GET['company_id'] ?? 0);
if (!$companyId) {
    header('Location: super-admin.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT * FROM companies WHERE company_id = ?");
$stmt->execute([$companyId]);
$company = $stmt->fetch();

if (!$company) {
    $_SESSION['error'] = 'Company not found';
    header('Location: super-admin.php');
    exit;
}

$csrf_token = generateCSRFToken();
$pageTitle = __('Add User to') . ' ' . $company['company_name'];
include '../includes/header.php';
?>

<style>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 24px; box-shadow: var(--shadow-xs); }
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
            <h1 class="page-title"><?php echo __('Add User to'); ?> "<?php echo htmlspecialchars($company['company_name']); ?>"</h1>
        </div>
    </div>

    <div style="max-width: 600px;">
        <div class="card">
            <form method="POST" action="/pages/super-admin.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="create_user_for_company">
                <input type="hidden" name="company_id" value="<?php echo $companyId; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?php echo __('Username'); ?> *</label>
                        <input type="text" name="username" class="form-control" required placeholder="e.g. john_doe">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo __('Email'); ?> *</label>
                        <input type="email" name="email" class="form-control" required placeholder="e.g. john@acme.com">
                    </div>
                </div>

                <div class="form-row" style="margin-top: 12px;">
                    <div class="form-group">
                        <label class="form-label"><?php echo __('Full Name'); ?> *</label>
                        <input type="text" name="full_name" class="form-control" required placeholder="e.g. John Doe">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo __('Role'); ?> *</label>
                        <select name="role" class="form-control">
                            <option value="Admin"><?php echo __('Admin'); ?></option>
                            <option value="Sales Manager"><?php echo __('Sales Manager'); ?></option>
                            <option value="Sales Rep" selected><?php echo __('Sales Rep'); ?></option>
                            <option value="Viewer"><?php echo __('Viewer'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 12px;">
                    <label class="form-label"><?php echo __('Password'); ?> *</label>
                    <input type="password" name="password" class="form-control" required placeholder="Enter secure password">
                </div>
                
                <div style="margin-top: 24px; display: flex; justify-content: flex-end;">
                    <a href="/pages/super-admin.php" class="btn btn-outline"><?php echo __('Cancel'); ?></a>
                    <button type="submit" class="btn btn-primary"><?php echo __('Add User'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
