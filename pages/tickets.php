<?php
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();
$pageTitle = __('Support Tickets');
$js = ['tickets'];
require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$contacts = $db->query("SELECT contact_id, first_name, last_name FROM contacts WHERE company_id = ? ORDER BY last_name LIMIT 50", [$_SESSION["company_id"] ?? null])->fetchAll();
$accounts = $db->query("SELECT account_id, account_name FROM accounts WHERE company_id = ? ORDER BY account_name", [$_SESSION["company_id"] ?? null])->fetchAll();
?>
<style>
.tickets-page { max-width: 1200px; margin: 0 auto; padding: 0 20px 40px; }
.page-header { display: flex; justify-content: space-between; align-items: center; padding: 24px 0 20px; }
.page-header h1 { font-size: 22px; font-weight: 600; margin: 0; }
.btn { padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; }
.btn-primary { background: #2563eb; color: white; }
.data-table-wrap { background: white; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; }
table.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
table.data-table th { background: #f9fafb; padding: 12px 16px; text-align: left; font-weight: 600; color: #6b7280; font-size: 12px; text-transform: uppercase; border-bottom: 1px solid #e5e7eb; }
table.data-table td { padding: 14px 16px; border-bottom: 1px solid #e5e7eb; color: #1f2937; }
table.data-table tr:last-child td { border-bottom: none; }
table.data-table tr:hover { background: #f9fafb; }
.priority-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
.priority-urgent { background: #fee2e2; color: #dc2626; }
.priority-high { background: #fef3c7; color: #d97706; }
.priority-medium { background: #dbeafe; color: #2563eb; }
.priority-low { background: #dcfce7; color: #16a34a; }
.status-badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
.status-open { background: #fee2e2; color: #dc2626; }
.status-in_progress { background: #fef3c7; color: #d97706; }
.status-resolved { background: #dcfce7; color: #15803d; }
.status-closed { background: #f3f4f6; color: #6b7280; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: none; align-items: center; justify-content: center; z-index: 1000; }
.modal-overlay.active { display: flex; }
.modal { background: white; border-radius: 12px; width: 520px; max-width: 95vw; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
.modal-header { padding: 20px 24px 16px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
.modal-header h2 { font-size: 17px; font-weight: 600; margin: 0; }
.modal-close { background: none; border: none; font-size: 20px; cursor: pointer; color: #9ca3af; }
.modal-body { padding: 20px 24px; }
.form-group { margin-bottom: 14px; }
.form-label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 5px; color: #374151; }
.form-control { width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; box-sizing: border-box; }
.form-control:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-actions { padding: 16px 24px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 10px; }
</style>

<div class="tickets-page">
    <div class="page-header">
        <h1><?php echo htmlspecialchars(__('Support Tickets')); ?></h1>
        <a href="/pages/ticket-form.php" class="btn btn-primary" style="text-decoration:none;">+ <?php echo htmlspecialchars(__('New Ticket')); ?></a>
    </div>
    
    <div class="data-table-wrap">
        <table class="data-table">
            <thead><tr>
                <th><?php echo htmlspecialchars(__('Ticket #')); ?></th>
                <th><?php echo htmlspecialchars(__('Subject')); ?></th>
                <th><?php echo htmlspecialchars(__('Contact')); ?></th>
                <th><?php echo htmlspecialchars(__('Priority')); ?></th>
                <th><?php echo htmlspecialchars(__('Status')); ?></th>
                <th><?php echo htmlspecialchars(__('Assigned')); ?></th>
                <th><?php echo htmlspecialchars(__('Actions')); ?></th>
            </tr></thead>
            <tbody id="tickets-tbody"><tr><td colspan="7" style="text-align:center;padding:40px;color:#9ca3af"><?php echo htmlspecialchars(__('Loading')); ?>...</td></tr></tbody>
        </table>
    </div>
</div>

<script>
const API = '/api/tickets.php';
const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
let tickets = [];

document.addEventListener('DOMContentLoaded', loadTickets);

function loadTickets() {
    fetch(`${API}?action=list`, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(resp => {
            if (resp.success) {
                tickets = resp.data || [];
                renderTickets();
            }
        });
}

function renderTickets() {
    const tbody = document.getElementById('tickets-tbody');
    if (!tickets.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;color:#9ca3af">' + escapeHtml(__('No tickets yet')) + '</td></tr>';
        return;
    }
    tbody.innerHTML = tickets.map(t => {
        const contact = t.first_name ? `${t.first_name} ${t.last_name}` : (t.account_name || '-') ;
        return `
        <tr>
            <td><strong>${escapeHtml(t.ticket_number)}</strong></td>
            <td>${escapeHtml(t.subject)}</td>
            <td>${escapeHtml(contact)}</td>
            <td><span class="priority-badge priority-${t.priority}">${escapeHtml(__(t.priority))}</span></td>
            <td><span class="status-badge status-${t.status}">${escapeHtml(__(t.status))}</span></td>
            <td>${escapeHtml(t.assigned_name || __('Unassigned'))}</td>
            <td>
                <a href="/pages/ticket-form.php?id=${t.ticket_id}" class="btn btn-xs btn-outline" style="text-decoration:none;margin-right:5px;padding:4px 8px;">${escapeHtml(__('Edit'))}</a>
                <button onclick="deleteTicket(${t.ticket_id})" class="btn btn-xs btn-outline" style="color:#dc2626;padding:4px 8px;">${escapeHtml(__('Delete'))}</button>
            </td>
        </tr>`;
    }).join('');
}

function deleteTicket(id) {
    showConfirm(__('are_you_sure_you_want_to_delete_this_ticket_this_action_cannot_be_undone'), () => {
        fetch(`${API}?action=delete`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ticket_id: id, csrf_token: CSRF_TOKEN })
        }).then(r => r.json()).then(resp => {
            if (resp.success) {
                loadTickets();
                showNotification(__('ticket_deleted_successfully'), 'success');
            } else {
                showNotification(resp.message || __('failed_to_delete_ticket'), 'error');
            }
        });
    });
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
