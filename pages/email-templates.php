<?php
/**
 * White Label CRM V2 — Email Templates
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
startSecureSession();
requireLogin();
requireRole('Sales Manager');

$companyId = $_SESSION['company_id'] ?? null;

$csrfToken = generateCSRFToken();
$db = Database::getInstance()->getConnection();

$templates = $db->prepare("SELECT t.*, u.full_name as creator FROM email_templates t LEFT JOIN users u ON t.created_by = u.user_id WHERE t.company_id = ? ORDER BY t.updated_at DESC");
$templates->execute([$companyId]);
$templates = $templates->fetchAll();

$pageTitle = __('email_templates');
include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?php echo __('email_templates'); ?></h1>
        <p class="text-muted"><?php echo __('email_templates_subtitle'); ?></p>
    </div>
    <a href="email-template-new.php" class="btn btn-primary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <?php echo __('new_template'); ?>
    </a>
</div>

<?php if (empty($templates)): ?>
    <div class="card">
        <div class="empty-state">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-tertiary)" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
            <h3><?php echo __('no_templates_yet'); ?></h3>
            <p><?php echo __('create_first_template_desc'); ?></p>
        </div>
    </div>
<?php else: ?>
    <div class="template-grid">
        <?php foreach ($templates as $t): ?>
            <div class="card template-card">
                <div class="card-body">
                    <div class="template-card-header">
                        <h3><?php echo htmlspecialchars($t['name']); ?></h3>
                        <span class="badge badge-default"><?php echo htmlspecialchars(__($t['category'])); ?></span>
                    </div>
                    <?php if ($t['subject']): ?>
                        <p class="text-muted fs-13"><?php echo htmlspecialchars($t['subject']); ?></p>
                    <?php endif; ?>
                    <div class="template-meta">
                        <span><?php echo __('by'); ?> <?php echo htmlspecialchars($t['creator'] ?? __('unknown')); ?></span>
                        <span><?php echo timeAgo($t['updated_at']); ?></span>
                    </div>
                </div>
                <div class="card-footer template-actions">
                    <a href="email-builder.php?mode=template&id=<?php echo $t['template_id']; ?>" class="btn btn-sm btn-outline"><?php echo __('edit_design'); ?></a>
                    <button class="btn btn-sm btn-outline btn-danger-outline" onclick="deleteTemplate(<?php echo $t['template_id']; ?>)"><?php echo __('delete'); ?></button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
function deleteTemplate(id) {
    showConfirm('<?php echo htmlspecialchars(__('are_you_sure_delete_template'), ENT_QUOTES); ?>', function() {
        fetch('/api/email.php?action=template_delete', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ template_id: id, csrf_token: '<?php echo $csrfToken; ?>' })
        }).then(r => r.json()).then(d => {
            if (d.success) location.reload();
            else showNotification(d.message, 'error');
        });
    });
}
</script>

<?php include '../includes/footer.php'; ?>
