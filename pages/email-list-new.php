<?php
/**
 * White Label CRM V2 - New Email List (standalone, no popup)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireLogin();
requireRole('Sales Manager');

$csrfToken = generateCSRFToken();
$pageTitle = __('new_email_list');
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;">
        <a href="/pages/email-lists.php" class="btn btn-outline" style="padding:8px 14px;display:inline-flex;align-items:center;gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            <?php echo htmlspecialchars(__('Back to Audiences')); ?>
        </a>
        <h1><?php echo htmlspecialchars(__('New Email List')); ?></h1>
    </div>
</div>

<div style="max-width:600px;">
    <form id="newListForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <div class="card">
            <div class="card-header" style="padding:18px 24px;">
                <h3 class="card-title" style="margin:0;"><?php echo htmlspecialchars(__('List details')); ?></h3>
            </div>
            <div class="card-body" style="padding:24px;">
                <div class="form-group">
                    <label class="form-label"><?php echo htmlspecialchars(__('List name')); ?> *</label>
                    <input type="text" name="name" class="form-control" required
                           placeholder="<?php echo htmlspecialchars(__('e.g., Newsletter subscribers')); ?>"
                           style="padding:10px 14px;">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Description')); ?></label>
                    <textarea name="description" class="form-control" rows="3"
                              placeholder="<?php echo htmlspecialchars(__('Optional description...')); ?>"
                              style="padding:10px 14px;"></textarea>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:16px;">
            <a href="/pages/email-lists.php" class="btn btn-outline"><?php echo htmlspecialchars(__('Cancel')); ?></a>
            <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars(__('Create list')); ?></button>
        </div>
    </form>
</div>

<script>
document.getElementById('newListForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    const data = {};
    fd.forEach((v, k) => data[k] = v);

    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = '<?php echo htmlspecialchars(__('Creating'), ENT_QUOTES); ?>…';

    fetch('/api/email.php?action=list_save', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    }).then(r => r.json()).then(d => {
        if (d.success) {
            window.location.href = '/pages/email-lists.php?created=1';
        } else {
            showNotification(d.message, 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = '<?php echo htmlspecialchars(__('Create list'), ENT_QUOTES); ?>';
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
