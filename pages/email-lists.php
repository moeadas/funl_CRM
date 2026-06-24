<?php
/**
 * White Label CRM V2 — Email Lists (Audiences)
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
startSecureSession();
requireLogin();
requireRole('Sales Manager');

$csrfToken = generateCSRFToken();
$db = Database::getInstance()->getConnection();

$companyId = intval($_SESSION['company_id'] ?? 0);
$lists = $db->query("SELECT el.*, u.full_name as creator, 
    (SELECT COUNT(*) FROM email_list_members WHERE list_id = el.list_id AND status = 'Active') as active_count,
    (SELECT COUNT(*) FROM email_list_members WHERE list_id = el.list_id AND status = 'Unsubscribed') as unsub_count
    FROM email_lists el 
    LEFT JOIN users u ON el.created_by = u.user_id 
    WHERE el.company_id = ?
    ORDER BY el.updated_at DESC", [$companyId])->fetchAll();

$totalMembers = array_sum(array_column($lists, 'active_count'));

$pageTitle = __('email_audiences');
include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?php echo __('email_audiences'); ?></h1>
        <p class="text-muted"><?php echo __('email_lists_subtitle'); ?></p>
    </div>
    <a href="/pages/email-list-new.php" class="btn btn-primary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <?php echo __('new_list'); ?>
    </a>
</div>

<div class="stats-grid mb-2">
    <div class="stat-card">
        <div class="stat-icon icon-accent">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div>
            <div class="stat-label"><?php echo __('total_lists'); ?></div>
            <div class="stat-value"><?php echo count($lists); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-success">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/></svg>
        </div>
        <div>
            <div class="stat-label"><?php echo __('total_subscribers'); ?></div>
            <div class="stat-value"><?php echo number_format($totalMembers); ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th><?php echo __('list_name'); ?></th>
                    <th><?php echo __('active_members'); ?></th>
                    <th><?php echo __('unsubscribed'); ?></th>
                    <th><?php echo __('created'); ?></th>
                    <th><?php echo __('actions'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lists)): ?>
                    <tr><td colspan="5" class="text-center text-muted"><?php echo __('no_lists_yet'); ?></td></tr>
                <?php else: foreach ($lists as $l): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($l['name']); ?></strong>
                            <?php if ($l['description']): ?>
                                <div class="text-muted fs-12"><?php echo htmlspecialchars(truncate($l['description'], 80)); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-success"><?php echo number_format($l['active_count']); ?></span></td>
                        <td><?php echo $l['unsub_count']; ?></td>
                        <td><?php echo timeAgo($l['created_at']); ?></td>
                        <td>
                            <div class="action-btns">
                                <a href="/pages/email-list-populate.php?list_id=<?php echo (int)$l['list_id']; ?>" class="btn btn-sm btn-outline"><?php echo __('add_leads'); ?></a>
                                <a href="#" data-confirm-delete="<?php echo (int)$l['list_id']; ?>" class="btn btn-sm btn-outline btn-danger-outline"><?php echo __('delete'); ?></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const CSRF = '<?php echo $csrfToken; ?>';

document.querySelectorAll('a[data-confirm-delete]').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        var id = this.getAttribute('data-confirm-delete');
        showConfirm('<?php echo htmlspecialchars(__('Delete this list? Leads will not be removed.'), ENT_QUOTES); ?>', function() {
            fetch('/api/email.php?action=list_delete', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ list_id: id, csrf_token: CSRF })
            }).then(r => r.json()).then(d => {
                if (d.success) location.reload();
                else showNotification(d.message, 'error');
            });
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
