<?php
/**
 * White Label CRM V2 — Email Template Form
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
startSecureSession();
requireLogin();
requireRole('Sales Manager');

$csrfToken = generateCSRFToken();
$pageTitle = __('new_template');
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
            <a href="/pages/email-templates.php" class="btn btn-outline" style="padding:8px 14px;">← <?php echo __('back'); ?></a>
            <h1 class="page-title"><?php echo __('new_template'); ?></h1>
        </div>
    </div>

    <div style="max-width: 600px;">
        <div class="card">
            <form id="newTemplateForm">
                <div class="form-group">
                    <label class="form-label"><?php echo __('template_name'); ?> *</label>
                    <input type="text" name="name" class="form-control" required placeholder="<?php echo __('e_g_monthly_newsletter'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('default_subject'); ?></label>
                    <input type="text" name="subject" class="form-control" placeholder="<?php echo __('e_g_monthly_update'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('category'); ?></label>
                    <select name="category" class="form-control">
                        <option value="Marketing"><?php echo __('Marketing'); ?></option>
                        <option value="Newsletter"><?php echo __('Newsletter'); ?></option>
                        <option value="Announcement"><?php echo __('Announcement'); ?></option>
                        <option value="Follow-up"><?php echo __('Follow-up'); ?></option>
                        <option value="Welcome"><?php echo __('Welcome'); ?></option>
                        <option value="Custom" selected><?php echo __('Custom'); ?></option>
                    </select>
                </div>
                
                <div style="margin-top: 24px; display: flex; justify-content: flex-end;">
                    <a href="/pages/email-templates.php" class="btn btn-outline"><?php echo __('cancel'); ?></a>
                    <button type="submit" class="btn btn-primary"><?php echo __('create_open_builder'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('newTemplateForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    const data = { csrf_token: '<?php echo $csrfToken; ?>' };
    fd.forEach((v, k) => data[k] = v);

    fetch('/api/email.php?action=template_save', {
        method: 'POST', 
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    }).then(r => r.json()).then(d => {
        if (d.success) {
            showNotification('Template created successfully. Opening builder...', 'success');
            setTimeout(() => {
                window.location.href = 'email-builder.php?mode=template&id=' + d.data.template_id;
            }, 1000);
        } else {
            showNotification(d.message, 'error');
        }
    }).catch(() => showNotification('Error creating template', 'error'));
});
</script>

<?php include '../includes/footer.php'; ?>
