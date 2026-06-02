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
?><div class="tickets-page">
    <div class="page-header">
        <h1 class="page-title"><?php echo htmlspecialchars(__('Support Tickets')); ?></h1>
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
