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

<div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;">
        <a href="/pages/tickets.php" class="btn btn-outline" style="padding:8px 14px;display:inline-flex;align-items:center;gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            <?php echo htmlspecialchars(__('Back to Tickets')); ?>
        </a>
        <h1><?= $ticketId ? htmlspecialchars(__('Edit Ticket')) : htmlspecialchars(__('New Ticket')) ?></h1>
    </div>
    <div class="header-actions">
        <?php if ($ticketId): ?>
            <button type="button" class="btn btn-outline" style="background:#fef2f2;border-color:#fecaca;color:#b91c1c;display:inline-flex;align-items:center;gap:6px;" onclick="deleteTicket()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                <?php echo htmlspecialchars(__('Delete Ticket')); ?>
            </button>
        <?php endif; ?>
        <button type="button" class="btn btn-primary" onclick="saveTicket()" style="display:inline-flex;align-items:center;gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            <?php echo htmlspecialchars(__('Save Ticket')); ?>
        </button>
    </div>
</div>

<div style="max-width:900px;">
    <div class="card">
        <div class="card-header" style="padding:18px 24px;">
            <h3 class="card-title" style="margin:0;"><?php echo htmlspecialchars(__('Ticket Information')); ?></h3>
        </div>
        <div class="card-body" style="padding:24px;">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label"><?php echo htmlspecialchars(__('Subject *')); ?></label>
                <input type="text" id="ticketSubject" class="form-control" placeholder="<?php echo htmlspecialchars(__('Brief summary of the issue')); ?>" required style="padding:10px 14px;">
            </div>
            <div class="row-2" style="margin-top:16px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Category')); ?></label>
                    <select id="ticketCategory" class="form-control" style="padding:10px 14px;">
                        <option value="Technical Support"><?php echo htmlspecialchars(__('Technical Support')); ?></option>
                        <option value="Billing"><?php echo htmlspecialchars(__('Billing')); ?></option>
                        <option value="Feature Request"><?php echo htmlspecialchars(__('Feature Request')); ?></option>
                        <option value="Bug Report"><?php echo htmlspecialchars(__('Bug Report')); ?></option>
                        <option value="Other"><?php echo htmlspecialchars(__('Other')); ?></option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Status')); ?></label>
                    <select id="ticketStatus" class="form-control" style="padding:10px 14px;">
                        <option value="open"><?php echo htmlspecialchars(__('Open')); ?></option>
                        <option value="in_progress"><?php echo htmlspecialchars(__('In Progress')); ?></option>
                        <option value="resolved"><?php echo htmlspecialchars(__('Resolved')); ?></option>
                        <option value="closed"><?php echo htmlspecialchars(__('Closed')); ?></option>
                    </select>
                </div>
            </div>
            <div class="form-group" style="margin-top:16px;margin-bottom:0;">
                <label class="form-label"><?php echo htmlspecialchars(__('Description *')); ?></label>
                <textarea id="ticketDescription" class="form-control" rows="5" placeholder="<?php echo htmlspecialchars(__('Detailed description of the issue or inquiry...')); ?>" required style="padding:10px 14px;"></textarea>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:16px;">
        <div class="card-header" style="padding:18px 24px;">
            <h3 class="card-title" style="margin:0;"><?php echo htmlspecialchars(__('Associations & Assignment')); ?></h3>
        </div>
        <div class="card-body" style="padding:24px;">
            <div class="row-3">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Contact')); ?></label>
                    <select id="ticketContactId" class="form-control" style="padding:10px 14px;">
                        <option value=""><?php echo htmlspecialchars(__('Select contact...')); ?></option>
                        <?php foreach ($contacts as $c): ?>
                            <option value="<?= $c['contact_id'] ?>"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Account')); ?></label>
                    <select id="ticketAccountId" class="form-control" style="padding:10px 14px;">
                        <option value=""><?php echo htmlspecialchars(__('Select account...')); ?></option>
                        <?php foreach ($accounts as $a): ?>
                            <option value="<?= $a['account_id'] ?>"><?= htmlspecialchars($a['account_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Assigned To')); ?></label>
                    <select id="ticketAssignedTo" class="form-control" style="padding:10px 14px;">
                        <option value=""><?php echo htmlspecialchars(__('Unassigned')); ?></option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group" style="margin-top:16px;margin-bottom:0;">
                <label class="form-label"><?php echo htmlspecialchars(__('Priority')); ?></label>
                <select id="ticketPriority" class="form-control" style="padding:10px 14px;max-width:300px;">
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
        showNotification(__('Subject is required'), 'error');
        return;
    }
    if (!description) {
        showNotification(__('Description is required'), 'error');
        return;
    }

    var data = {
        csrf_token: CSRF_TOKEN,
        subject: subject,
        status: document.getElementById('ticketStatus').value,
        category: document.getElementById('ticketCategory').value,
        description: description,
        contact_id: document.getElementById('ticketContactId').value || null,
        account_id: document.getElementById('ticketAccountId').value || null,
        assigned_to: document.getElementById('ticketAssignedTo').value || null,
        priority: document.getElementById('ticketPriority').value,
    };
    if (TICKET_ID) data.ticket_id = TICKET_ID;
    var action = TICKET_ID ? 'update' : 'create';

    var btn = document.querySelector('button[onclick="saveTicket()"]');
    btn.disabled = true;
    var origText = btn.innerHTML;
    btn.innerHTML = '<?php echo htmlspecialchars(__('Saving'), ENT_QUOTES); ?>…';

    fetch('/api/tickets.php?action=' + action, {
        method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    }).then(r => r.json()).then(resp => {
        if (resp.success) {
            showNotification(TICKET_ID ? '<?php echo htmlspecialchars(__('Ticket updated'), ENT_QUOTES); ?>' : '<?php echo htmlspecialchars(__('Ticket created'), ENT_QUOTES); ?>', 'success');
            setTimeout(() => window.location.href = '/pages/tickets.php', 700);
        } else {
            showNotification(resp.message || '<?php echo htmlspecialchars(__('Failed'), ENT_QUOTES); ?>', 'error');
            btn.disabled = false;
            btn.innerHTML = origText;
        }
    });
}

function deleteTicket() {
    if (!TICKET_ID) return;
    showConfirm('<?php echo htmlspecialchars(__('Delete this ticket?'), ENT_QUOTES); ?>', function() {
        fetch('/api/tickets.php?action=delete', {
            method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ ticket_id: TICKET_ID, csrf_token: CSRF_TOKEN })
        }).then(r => r.json()).then(resp => {
            if (resp.success) {
                showNotification('<?php echo htmlspecialchars(__('Ticket deleted'), ENT_QUOTES); ?>', 'success');
                setTimeout(() => window.location.href = '/pages/tickets.php', 700);
            } else {
                showNotification(resp.message || '<?php echo htmlspecialchars(__('Failed'), ENT_QUOTES); ?>', 'error');
            }
        });
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
