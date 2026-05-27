<?php
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();
$pageTitle = 'Support Tickets';
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
        <h1>Support Tickets</h1>
        <button class="btn btn-primary" onclick="openTicketModal()">+ New Ticket</button>
    </div>
    
    <div class="data-table-wrap">
        <table class="data-table">
            <thead><tr><th>Ticket #</th><th>Subject</th><th>Contact</th><th>Priority</th><th>Status</th><th>Assigned</th><th></th></tr></thead>
            <tbody id="tickets-tbody"><tr><td colspan="7" style="text-align:center;padding:40px;color:#9ca3af">Loading...</td></tr></tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="ticket-modal">
    <div class="modal">
        <div class="modal-header">
            <h2>New Support Ticket</h2>
            <button class="modal-close" onclick="closeTicketModal()">&times;</button>
        </div>
        <form id="ticket-form" onsubmit="saveTicket(event)">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Subject *</label>
                    <input type="text" id="ticket-subject" class="form-control" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Contact</label>
                        <select id="ticket-contact" class="form-control">
                            <option value="">Select contact...</option>
                            <?php foreach ($contacts as $c): ?>
                                <option value="<?php echo  $c['contact_id'] ?>"><?php echo  htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select id="ticket-priority" class="form-control">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea id="ticket-desc" class="form-control" rows="4" required></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeTicketModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Ticket</button>
            </div>
        </form>
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
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;color:#9ca3af">No tickets yet.</td></tr>';
        return;
    }
    tbody.innerHTML = tickets.map(t => {
        const contact = t.first_name ? `${t.first_name} ${t.last_name}` : (t.account_name || '-') ;
        return `         <tr>             <td><strong>${escapeHtml(t.ticket_number)}</strong></td>             <td>${escapeHtml(t.subject)}</td>             <td>${escapeHtml(contact)}</td>             <td><span class="priority-badge priority-${t.priority}">${t.priority}</span></td>             <td><span class="status-badge status-${t.status}">${t.status.replace('_', ' ')}</span></td>             <td>${escapeHtml(t.assigned_name || 'Unassigned')}</td>             <td><button onclick="deleteTicket(${t.ticket_id})" style="background:none;border:none;cursor:pointer;color:#dc2626">🗑️</button></td>         </tr>`;
    }).join('');
}

function openTicketModal() {
    document.getElementById('ticket-form').reset();
    document.getElementById('ticket-modal').classList.add('active');
}

function closeTicketModal() {
    document.getElementById('ticket-modal').classList.remove('active');
}

function saveTicket(e) {
    e.preventDefault();
    const data = {
        csrf_token: CSRF_TOKEN,
        subject: document.getElementById('ticket-subject').value,
        description: document.getElementById('ticket-desc').value,
        priority: document.getElementById('ticket-priority').value,
        contact_id: document.getElementById('ticket-contact').value || null,
    };
    
    fetch(`${API}?action=create`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    }).then(r => r.json()).then(resp => {
        if (resp.success) {
            closeTicketModal();
            loadTickets();
            showNotification('Ticket created', 'success');
        } else {
            showNotification(resp.message || 'Failed', 'error');
        }
    });
}

function deleteTicket(id) {
    fetch(`${API}?action=delete`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ticket_id: id, csrf_token: CSRF_TOKEN })
    }).then(r => r.json()).then(resp => {
        if (resp.success) { loadTickets(); showNotification('Deleted', 'success'); }
    });
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeTicketModal(); });
document.querySelector('.modal-overlay').addEventListener('click', e => { if (e.target === e.currentTarget) closeTicketModal(); });
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
