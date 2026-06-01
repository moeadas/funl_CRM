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

$lists = $db->query("SELECT el.*, u.full_name as creator, 
    (SELECT COUNT(*) FROM email_list_members WHERE list_id = el.list_id AND status = 'Active') as active_count,
    (SELECT COUNT(*) FROM email_list_members WHERE list_id = el.list_id AND status = 'Unsubscribed') as unsub_count
    FROM email_lists el 
    LEFT JOIN users u ON el.created_by = u.user_id 
    ORDER BY el.updated_at DESC")->fetchAll();

$totalMembers = array_sum(array_column($lists, 'active_count'));

$pageTitle = __('email_audiences');
include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?php echo __('email_audiences'); ?></h1>
        <p class="text-muted"><?php echo __('email_lists_subtitle'); ?></p>
    </div>
    <button class="btn btn-primary" onclick="showCreateModal()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <?php echo __('new_list'); ?>
    </button>
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
                                <button class="btn btn-sm btn-outline" onclick="showPopulateModal(<?php echo $l['list_id']; ?>, '<?php echo htmlspecialchars($l['name'], ENT_QUOTES); ?>')"><?php echo __('add_leads'); ?></button>
                                <button class="btn btn-sm btn-outline btn-danger-outline" onclick="deleteList(<?php echo $l['list_id']; ?>)"><?php echo __('delete'); ?></button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create List Modal -->
<div id="createListModal" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php echo __('new_email_list'); ?></h2>
            <button type="button" class="modal-close" onclick="hideModal('createListModal')">&times;</button>
        </div>
        <form id="newListForm">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label"><?php echo __('list_name'); ?> *</label>
                    <input type="text" name="name" class="form-control" required placeholder="<?php echo __('e_g_list_name'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('description'); ?></label>
                    <textarea name="description" class="form-control" rows="2" placeholder="<?php echo __('optional_description'); ?>"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="hideModal('createListModal')"><?php echo __('cancel'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo __('create_list'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Populate List Modal -->
<div id="populateModal" class="modal" style="display:none;">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h2><?php echo __('add_leads_to'); ?> <span id="populateListName"></span></h2>
            <button type="button" class="modal-close" onclick="hideModal('populateModal')">&times;</button>
        </div>
        <form id="populateForm">
            <input type="hidden" name="list_id" id="populateListId">
            <div class="modal-body">
                <p class="text-muted"><?php echo __('populate_list_desc'); ?></p>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?php echo __('lead_status'); ?></label>
                        <select name="status" class="form-control">
                            <option value=""><?php echo __('all_status'); ?></option>
                            <option value="New Lead"><?php echo __('new_lead'); ?></option>
                            <option value="Contacted"><?php echo __('contacted'); ?></option>
                            <option value="Interested"><?php echo __('interested'); ?></option>
                            <option value="Won"><?php echo __('won'); ?></option>
                            <option value="On Hold"><?php echo __('on_hold'); ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo __('country'); ?></label>
                        <input type="text" name="country" class="form-control" placeholder="<?php echo __('e_g_countries'); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?php echo __('lead_type'); ?></label>
                        <select name="lead_type" class="form-control">
                            <option value=""><?php echo __('all_types'); ?></option>
                            <option value="Stable"><?php echo __('Stable'); ?></option>
                            <option value="Owner"><?php echo __('Owner'); ?></option>
                            <option value="Breeder"><?php echo __('Breeder'); ?></option>
                            <option value="Trainer"><?php echo __('Trainer'); ?></option>
                            <option value="Veterinarian"><?php echo __('Veterinarian'); ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo __('priority'); ?></label>
                        <select name="priority" class="form-control">
                            <option value=""><?php echo __('all_priorities'); ?></option>
                            <option value="Urgent"><?php echo __('urgent'); ?></option>
                            <option value="High"><?php echo __('high'); ?></option>
                            <option value="Medium"><?php echo __('medium'); ?></option>
                            <option value="Low"><?php echo __('low'); ?></option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('country_contains'); ?></label>
                    <input type="text" name="country_contains" class="form-control" placeholder="<?php echo __('e_g_united_states'); ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="hideModal('populateModal')"><?php echo __('cancel'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo __('add_matching_leads'); ?></button>
            </div>
        </form>
    </div>
</div>

<script>
const CSRF = '<?php echo $csrfToken; ?>';

function showCreateModal() { document.getElementById('createListModal').style.display = 'flex'; }
function showPopulateModal(id, name) {
    document.getElementById('populateListId').value = id;
    document.getElementById('populateListName').textContent = name;
    document.getElementById('populateModal').style.display = 'flex';
}
function hideModal(id) { document.getElementById(id).style.display = 'none'; }

document.querySelectorAll('.modal').forEach(m => {
    m.addEventListener('click', function(e) { if (e.target === this) this.style.display = 'none'; });
});

document.getElementById('newListForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    const data = { csrf_token: CSRF };
    fd.forEach((v, k) => data[k] = v);

    fetch('/api/email.php?action=list_save', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
        else showNotification(d.message, 'error');
    });
});

document.getElementById('populateForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    const listId = fd.get('list_id');
    const filters = {};
    fd.forEach((v, k) => { if (k !== 'list_id' && v) filters[k] = v; });

    fetch('/api/email.php?action=list_populate', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ list_id: parseInt(listId), filters: filters, csrf_token: CSRF })
    }).then(r => r.json()).then(d => {
        if (d.success) {
            showNotification(d.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(d.message, 'error');
        }
    });
});

function deleteList(id) {
    
    fetch('/api/email.php?action=list_delete', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ list_id: id, csrf_token: CSRF })
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
        else showNotification(d.message, 'error');
    });
}
</script>

<?php include '../includes/footer.php'; ?>
