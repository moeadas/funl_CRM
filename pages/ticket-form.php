<?php
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$pageTitle = __('Support Ticket');
$currentPage = 'tickets';
$ticketId = intval($_GET['id'] ?? 0);
require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$companyId = $_SESSION["company_id"] ?? null;

// Fetch contacts, accounts, users
$contacts = $db->query("SELECT contact_id, first_name, last_name FROM contacts WHERE company_id = ? ORDER BY last_name LIMIT 50", [$companyId])->fetchAll();
$accounts = $db->query("SELECT account_id, account_name FROM accounts WHERE company_id = ? ORDER BY account_name", [$companyId])->fetchAll();
$users = $db->query("SELECT user_id, full_name FROM users WHERE company_id = ? AND status = 'Active' ORDER BY full_name", [$companyId])->fetchAll();
?>

<style>
.page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:28px; padding:0; }
.page-header h1 { margin:0; font-size:22px; font-weight:600; letter-spacing:-0.3px; color: var(--color-text); }
.page-header .header-actions { display:flex; gap:10px; }
.card { background: var(--color-surface); border:1px solid var(--color-border); border-radius: var(--radius-md); padding:24px; margin-bottom:16px; box-shadow: var(--shadow-xs); }
.card-title { font-size:15px; font-weight:600; color: var(--color-text); margin:0 0 20px; }
.form-label { display:block; font-size:13px; font-weight:500; color: var(--color-text); margin-bottom:6px; }
.form-control { width:100%; padding:10px 12px; border:1px solid var(--color-border); border-radius: var(--radius-sm); font-size:14px; color: var(--color-text); background: var(--color-surface); box-sizing:border-box; transition: border-color var(--transition); }
.form-control:focus { outline:none; border-color: var(--color-accent); box-shadow:0 0 0 3px rgba(0,113,227,0.15); }
textarea.form-control { min-height:120px; resize:vertical; }
.row-2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.row-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; }
.btn { padding:10px 18px; border-radius: var(--radius-sm); font-size:14px; font-weight:500; cursor:pointer; transition: all var(--transition); border:none; text-decoration:none; display:inline-block; }
.btn-primary { background: var(--color-accent); color:#fff; }
.btn-primary:hover { background: var(--color-accent-hover); }
.btn-outline { background: var(--color-surface); color: var(--color-accent); border:1px solid var(--color-border); }
.btn-outline:hover { background: var(--color-bg); }
.btn-danger { background: var(--color-surface); color: #dc2626; border:1px solid #fca5a5; }
.btn-danger:hover { background: #fef2f2; }
</style>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;">
        <a href="/pages/tickets.php" class="btn btn-outline" style="padding:8px 14px;">← <?php echo htmlspecialchars(__('Back to Tickets')); ?></a>
        <h1><?= $ticketId ? htmlspecialchars(__('Edit Ticket')) : htmlspecialchars(__('New Ticket')) ?></h1>
    </div>
    <div class="header-actions">
        <?php if ($ticketId): ?>
            <button type="button" class="btn btn-danger" onclick="deleteTicket()"><?php echo htmlspecialchars(__('Delete Ticket')); ?></button>
        <?php endif; ?>
        <button type="button" class="btn btn-primary" onclick="saveTicket()"><?php echo htmlspecialchars(__('Save Ticket')); ?></button>
    </div>
</div>

<div style="max-width:900px;">
    <div class="card">
        <h3 class="card-title"><?php echo htmlspecialchars(__('Ticket Information')); ?></h3>
        <div class="form-group">
            <label class="form-label"><?php echo htmlspecialchars(__('Subject *')); ?></label>
            <input type="text" id="ticketSubject" class="form-control" placeholder="<?php echo htmlspecialchars(__('Brief summary of the issue')); ?>" required>
        </div>
        <div class="row-2" style="margin-top: 16px;">
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Category')); ?></label>
                <select id="ticketCategory" class="form-control">
                    <option value="Technical Support"><?php echo htmlspecialchars(__('Technical Support')); ?></option>
                    <option value="Billing"><?php echo htmlspecialchars(__('Billing')); ?></option>
                    <option value="Feature Request"><?php echo htmlspecialchars(__('Feature Request')); ?></option>
                    <option value="Bug Report"><?php echo htmlspecialchars(__('Bug Report')); ?></option>
                    <option value="Other"><?php echo htmlspecialchars(__('Other')); ?></option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Status')); ?></label>
                <select id="ticketStatus" class="form-control">
                    <option value="open"><?php echo htmlspecialchars(__('Open')); ?></option>
                    <option value="in_progress"><?php echo htmlspecialchars(__('In Progress')); ?></option>
                    <option value="resolved"><?php echo htmlspecialchars(__('Resolved')); ?></option>
                    <option value="closed"><?php echo htmlspecialchars(__('Closed')); ?></option>
                </select>
            </div>
        </div>
        <div style="margin-top:16px;">
            <label class="form-label"><?php echo htmlspecialchars(__('Description *')); ?></label>
            <textarea id="ticketDescription" class="form-control" placeholder="<?php echo htmlspecialchars(__('Detailed description of the issue or inquiry...')); ?>" required></textarea>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title"><?php echo htmlspecialchars(__('Associations & Assignment')); ?></h3>
        <div class="row-3">
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Contact')); ?></label>
                <select id="ticketContactId" class="form-control">
                    <option value=""><?php echo htmlspecialchars(__('Select contact...')); ?></option>
                    <?php foreach ($contacts as $c): ?>
                        <option value="<?= $c['contact_id'] ?>"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Account')); ?></label>
                <select id="ticketAccountId" class="form-control">
                    <option value=""><?php echo htmlspecialchars(__('Select account...')); ?></option>
                    <?php foreach ($accounts as $a): ?>
                        <option value="<?= $a['account_id'] ?>"><?= htmlspecialchars($a['account_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Assigned To')); ?></label>
                <select id="ticketAssignedTo" class="form-control">
                    <option value=""><?php echo htmlspecialchars(__('Unassigned')); ?></option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row-2" style="margin-top: 16px;">
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Priority')); ?></label>
                <select id="ticketPriority" class="form-control">
                    <option value="low"><?php echo htmlspecialchars(__('Low')); ?></option>
                    <option value="medium" selected><?php echo htmlspecialchars(__('Medium')); ?></option>
                    <option value="high"><?php echo htmlspecialchars(__('High')); ?></option>
                    <option value="urgent"><?php echo htmlspecialchars(__('Urgent')); ?></option>
                </select>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = "<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>";
const TICKET_ID = <?= $ticketId ?>;

document.addEventListener('DOMContentLoaded', function() {
    if (TICKET_ID) {
        loadTicket();
    }
});

function loadTicket() {
    fetch('/api/tickets.php?action=get&id=' + TICKET_ID, { credentials: 'same-origin' })
    .then(r => r.json())
    .then(resp => {
        if (resp.success && resp.data) {
            var t = resp.data;
            document.getElementById('ticketSubject').value = t.subject || '';
            document.getElementById('ticketStatus').value = t.status || 'open';
            document.getElementById('ticketCategory').value = t.category || 'Technical Support';
            document.getElementById('ticketDescription').value = t.description || '';
            document.getElementById('ticketContactId').value = t.contact_id || '';
            document.getElementById('ticketAccountId').value = t.account_id || '';
            document.getElementById('ticketAssignedTo').value = t.assigned_to || '';
            document.getElementById('ticketPriority').value = t.priority || 'medium';
        } else {
            showNotification(resp.message || __('Failed to load ticket'), 'error');
        }
    });
}

function saveTicket() {
    var subject = document.getElementById('ticketSubject').value.trim();
    var description = document.getElementById('ticketDescription').value.trim();
    if (!subject) {
        showNotification(__('subject_is_required'), 'error');
        return;
    }
    if (!description) {
        showNotification(__('description_is_required'), 'error');
        return;
    }
    
    var payload = {
        csrf_token: CSRF_TOKEN,
        subject: subject,
        description: description,
        status: document.getElementById('ticketStatus').value,
        category: document.getElementById('ticketCategory').value,
        contact_id: document.getElementById('ticketContactId').value || null,
        account_id: document.getElementById('ticketAccountId').value || null,
        assigned_to: document.getElementById('ticketAssignedTo').value || null,
        priority: document.getElementById('ticketPriority').value
    };
    
    var url = '/api/tickets.php?action=' + (TICKET_ID ? 'update' : 'create');
    if (TICKET_ID) {
        payload.ticket_id = TICKET_ID;
    }
    
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        showNotification(data.message || (data.success ? __('ticket_saved') : __('Save failed')), data.success ? 'success' : 'error');
        if (data.success) {
            setTimeout(() => {
                window.location.href = '/pages/tickets.php';
            }, 500);
        }
    })
    .catch(() => showNotification(__('Network error'), 'error'));
}

function deleteTicket() {
    showConfirm(__('are_you_sure_you_want_to_delete_this_ticket_this_action_cannot_be_undone'), () => {
        fetch('/api/tickets.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ ticket_id: TICKET_ID, csrf_token: CSRF_TOKEN })
        })
        .then(r => r.json())
        .then(resp => {
            showNotification(resp.message || (resp.success ? __('Ticket deleted') : __('Failed to delete')), resp.success ? 'success' : 'error');
            if (resp.success) {
                setTimeout(() => {
                    window.location.href = '/pages/tickets.php';
                }, 500);
            }
        });
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
