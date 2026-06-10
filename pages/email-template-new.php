<?php
/**
 * White Label CRM V2 — Create Email Template (standalone page, no popup)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireLogin();
requireRole('Sales Manager');

$companyId = $_SESSION['company_id'] ?? null;
$csrfToken = generateCSRFToken();
$pageTitle = __('new_template');

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?php echo htmlspecialchars(__('new_template')); ?></h1>
        <p class="text-muted"><?php echo htmlspecialchars(__('create_first_template_desc')); ?></p>
    </div>
    <div class="header-actions">
        <a href="email-templates.php" class="btn btn-outline">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            <?php echo htmlspecialchars(__('back')); ?>
        </a>
    </div>
</div>

<div class="card" style="max-width: 720px;">
    <div class="card-header">
        <h2 class="card-title"><?php echo htmlspecialchars(__('template_details')); ?></h2>
    </div>
    <div class="card-body" style="padding: 24px;">
        <form id="newTemplateForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label" for="tplName"><?php echo htmlspecialchars(__('template_name')); ?> *</label>
                <input type="text" id="tplName" name="name" class="form-control" required
                       placeholder="<?php echo htmlspecialchars(__('e_g_monthly_newsletter')); ?>"
                       style="padding: 10px 14px;">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label" for="tplSubject"><?php echo htmlspecialchars(__('default_subject')); ?></label>
                <input type="text" id="tplSubject" name="subject" class="form-control"
                       placeholder="<?php echo htmlspecialchars(__('e_g_monthly_update')); ?>"
                       style="padding: 10px 14px;">
                <small class="text-muted"><?php echo htmlspecialchars(__('optional_used_as_default_when_sending')); ?></small>
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label class="form-label" for="tplCategory"><?php echo htmlspecialchars(__('category')); ?></label>
                <select id="tplCategory" name="category" class="form-control" style="padding: 10px 14px;">
                    <option value="Marketing"><?php echo htmlspecialchars(__('Marketing')); ?></option>
                    <option value="Newsletter"><?php echo htmlspecialchars(__('Newsletter')); ?></option>
                    <option value="Announcement"><?php echo htmlspecialchars(__('Announcement')); ?></option>
                    <option value="Follow-up"><?php echo htmlspecialchars(__('Follow-up')); ?></option>
                    <option value="Welcome"><?php echo htmlspecialchars(__('Welcome')); ?></option>
                    <option value="Custom" selected><?php echo htmlspecialchars(__('Custom')); ?></option>
                </select>
            </div>

            <div style="display:flex;gap:12px;justify-content:flex-end;padding-top:8px;border-top:1px solid var(--color-border);margin-top:8px;">
                <a href="email-templates.php" class="btn btn-outline"><?php echo htmlspecialchars(__('cancel')); ?></a>
                <button type="submit" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="9 18 15 12 9 6"/></svg>
                    <?php echo htmlspecialchars(__('create_open_builder')); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('newTemplateForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    const data = {};
    fd.forEach((v, k) => data[k] = v);

    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = '<?php echo htmlspecialchars(__('creating'), ENT_QUOTES); ?>…';

    fetch('/api/email.php?action=template_save', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            window.location.href = 'email-builder.php?mode=template&id=' + d.data.template_id;
        } else {
            showNotification(d.message || 'Error creating template', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="9 18 15 12 9 6"/></svg> <?php echo htmlspecialchars(__('create_open_builder'), ENT_QUOTES); ?>';
        }
    })
    .catch(err => {
        showNotification('Network error: ' + err.message, 'error');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="9 18 15 12 9 6"/></svg> <?php echo htmlspecialchars(__('create_open_builder'), ENT_QUOTES); ?>';
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
