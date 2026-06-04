<?php
/**
 * White Label CRM V2 — Email List Form
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
startSecureSession();
requireLogin();
requireRole('Sales Manager');

$csrfToken = generateCSRFToken();
$pageTitle = __('new_email_list');
include '../includes/header.php';
?>

<style>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 24px; box-shadow: var(--shadow-xs); }
.form-group { margin-bottom: 16px; }
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
            <a href="/pages/email-lists.php" class="btn btn-outline" style="padding:8px 14px;">← <?php echo __('back'); ?></a>
            <h1 class="page-title"><?php echo __('new_email_list'); ?></h1>
        </div>
    </div>

    <div style="max-width: 600px;">
        <div class="card">
            <form id="newListForm">
                <div class="form-group">
                    <label class="form-label"><?php echo __('list_name'); ?> *</label>
                    <input type="text" name="name" class="form-control" required placeholder="<?php echo __('e_g_list_name'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('description'); ?></label>
                    <textarea name="description" class="form-control" rows="4" placeholder="<?php echo __('optional_description'); ?>"></textarea>
                </div>
                
                <div style="margin-top: 24px; display: flex; justify-content: flex-end;">
                    <a href="/pages/email-lists.php" class="btn btn-outline"><?php echo __('cancel'); ?></a>
                    <button type="submit" class="btn btn-primary"><?php echo __('create_list'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const CSRF = '<?php echo $csrfToken; ?>';

document.getElementById('newListForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    const data = { csrf_token: CSRF };
    fd.forEach((v, k) => data[k] = v);

    fetch('/api/email.php?action=list_save', {
        method: 'POST', 
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    }).then(r => r.json()).then(d => {
        if (d.success) {
            showNotification(d.message || 'List created successfully', 'success');
            setTimeout(() => {
                window.location.href = '/pages/email-lists.php';
            }, 1000);
        } else {
            showNotification(d.message, 'error');
        }
    }).catch(() => showNotification('Error creating list', 'error'));
});
</script>

<?php include '../includes/footer.php'; ?>
