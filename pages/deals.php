<?php
/**
 * White Label CRM - Deal Pipeline (Kanban)
 */
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$userId = getCurrentUserId();
$companyId = $_SESSION["company_id"] ?? null;
$userRole = $_SESSION["role"] ?? "";

$pageTitle = 'Pipeline';
require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$accounts = $db->query("SELECT account_id, account_name FROM accounts WHERE company_id = ? ORDER BY account_name", [$companyId])->fetchAll();
$contacts = $db->query("SELECT contact_id, first_name, last_name FROM contacts WHERE company_id = ? ORDER BY last_name, first_name LIMIT 100", [$companyId])->fetchAll();
$users = $db->query("SELECT user_id, full_name FROM users WHERE company_id = ? AND status = 'Active' ORDER BY full_name", [$companyId])->fetchAll();
$stages = $db->query("SELECT stage_name, stage_label, probability, color FROM deal_stages WHERE company_id = 0 OR company_id = ? ORDER BY position", [$companyId])->fetchAll();
?>

<style>
.deals-page { max-width: 1600px; margin: 0 auto; padding: 0 20px 40px; }
.page-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 0 16px; gap: 16px; }
.page-header h1 { font-size: 20px; font-weight: 600; margin: 0; }
.header-actions { display: flex; gap: 10px; align-items: center; }

/* Stats Bar */
.pipeline-stats { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.stat-card { background: white; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px 18px; min-width: 140px; flex: 1; }
.stat-label { font-size: 12px; color: #6b7280; text-transform: uppercase; font-weight: 500; }
.stat-value { font-size: 20px; font-weight: 700; color: #111827; margin-top: 4px; }

/* Filters */
.filters-bar { display: flex; gap: 10px; margin-bottom: 16px; align-items: center; flex-wrap: wrap; }
.filters-bar input, .filters-bar select { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; }

/* Kanban */
.kanban-container { display: flex; gap: 16px; overflow-x: auto; padding-bottom: 20px; min-height: 400px; }
.kanban-column { flex: 1; min-width: 280px; max-width: 320px; background: #f9fafb; border-radius: 10px; border: 1px solid #e5e7eb; display: flex; flex-direction: column; }
.kanban-column-header { padding: 14px 16px 10px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; }
.kanban-column-title { font-size: 13px; font-weight: 600; text-transform: uppercase; }
.kanban-column-count { font-size: 12px; background: #e5e7eb; padding: 2px 10px; border-radius: 10px; }
.kanban-column-body { padding: 10px; min-height: 100px; flex: 1; }

/* Deal Card */
.deal-card { background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px; margin-bottom: 10px; cursor: grab; transition: all 0.15s; }
.deal-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,0.08); border-color: #d1d5db; }
.deal-card.dragging { opacity: 0.5; }
.deal-name { font-weight: 600; font-size: 14px; margin-bottom: 6px; color: #111827; }
.deal-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.deal-value { font-size: 13px; font-weight: 600; color: #059669; }
.deal-probability { font-size: 11px; color: #6b7280; }
.deal-company { font-size: 12px; color: #6b7280; }
.deal-assignee { font-size: 11px; color: #6b7280; }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 20px; }
.modal-overlay.active { display: flex; }
.modal { background: white; border-radius: 12px; width: 520px; max-width: 95vw; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
.modal-header { padding: 16px 20px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
.modal-header h2 { margin: 0; font-size: 18px; font-weight: 600; }
.modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #9ca3af; line-height: 1; }
.modal-close:hover { color: #374151; }
.modal-body { padding: 20px; }
.modal-footer { padding: 16px 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 10px; }

.form-group { margin-bottom: 14px; }
.form-label { display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 5px; }
.form-control { width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
.form-control:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
select.form-control { height: 38px; }
textarea.form-control { resize: vertical; min-height: 80px; }

.btn { padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 6px; }
.btn-primary { background: #2563eb; color: white; }
.btn-primary:hover { background: #1d4ed8; }
.btn-outline { background: white; border: 1px solid #d1d5db; color: #374151; }
.btn-outline:hover { background: #f9fafb; }

.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
</style>

<div class="deals-page">
    <div class="page-header">
        <h1>Pipeline</h1>
        <div class="header-actions">
            <button class="btn btn-primary" onclick="openDealModal()">+ New Deal</button>
        </div>
    </div>

    <div class="pipeline-stats">
        <div class="stat-card">
            <div class="stat-label">Open Deals</div>
            <div class="stat-value" id="stat-open-count">-</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Pipeline Value</div>
            <div class="stat-value" id="stat-pipeline-value">-</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Won Value</div>
            <div class="stat-value" id="stat-won-value">-</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Lost Value</div>
            <div class="stat-value" id="stat-lost-value">-</div>
        </div>
    </div>

    <div class="filters-bar">
        <input type="text" id="deal-search" placeholder="Search deals..." oninput="loadDeals()">
        <select id="deal-assigned-filter" onchange="loadDeals()">
            <option value="">All Assignees</option>
            <?php foreach ($users as $user): ?>
                <option value="<?php echo $user['user_id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="kanban-container" id="kanban-container">
        <?php foreach ($stages as $stage): ?>
            <div class="kanban-column" data-stage="<?php echo htmlspecialchars($stage['stage_name']); ?>"
                 ondragover="onDragOver(event)" ondrop="onDrop(event)">
                <div class="kanban-column-header">
                    <span class="kanban-column-title" style="color:<?php echo htmlspecialchars($stage['color']); ?>">
                        <?php echo htmlspecialchars($stage['stage_label']); ?>
                    </span>
                    <span class="kanban-column-count" id="count-<?php echo htmlspecialchars($stage['stage_name']); ?>">0</span>
                </div>
                <div class="kanban-column-body" id="stage-<?php echo htmlspecialchars($stage['stage_name']); ?>"></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Deal Modal -->
<div class="modal-overlay" id="deal-modal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="deal-modal-title">New Deal</h2>
            <button class="modal-close" onclick="closeDealModal()">&times;</button>
        </div>
        <form id="deal-form" onsubmit="saveDeal(event)">
            <input type="hidden" id="deal-id" value="">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Deal Name *</label>
                    <input type="text" id="deal-name" class="form-control" required>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Value</label>
                        <input type="number" id="deal-value" class="form-control" placeholder="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Currency</label>
                        <select id="deal-currency" class="form-control">
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="GBP">GBP</option>
                        </select>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Stage</label>
                        <select id="deal-stage" class="form-control">
                            <?php foreach ($stages as $stage): ?>
                                <option value="<?php echo htmlspecialchars($stage['stage_name']); ?>"><?php echo htmlspecialchars($stage['stage_label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Probability (%)</label>
                        <input type="number" id="deal-probability" class="form-control" value="10" min="0" max="100">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Expected Close</label>
                        <input type="date" id="deal-close-date" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Type</label>
                        <select id="deal-type" class="form-control">
                            <option value="New Business">New Business</option>
                            <option value="Existing Business">Existing Business</option>
                        </select>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Account</label>
                        <select id="deal-account-id" class="form-control">
                            <option value="">-- No Account --</option>
                            <?php foreach ($accounts as $account): ?>
                                <option value="<?php echo $account['account_id']; ?>"><?php echo htmlspecialchars($account['account_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact</label>
                        <select id="deal-contact-id" class="form-control">
                            <option value="">-- No Contact --</option>
                            <?php foreach ($contacts as $contact): ?>
                                <option value="<?php echo $contact['contact_id']; ?>"><?php echo htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Assigned To</label>
                    <select id="deal-assigned-to" class="form-control">
                        <option value="">-- Unassigned --</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['user_id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea id="deal-description" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeDealModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="deal-save-btn">Create Deal</button>
            </div>
        </form>
    </div>
</div>

<script>
const COMPANY_ID = <?php echo json_encode($companyId); ?>;
const USER_ROLE = <?php echo json_encode($userRole); ?>;
const CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
const API = '/api/deals.php';

const STAGES = [
    <?php foreach ($stages as $s): ?>
        { name: '<?php echo $s['stage_name']; ?>', label: '<?php echo htmlspecialchars($s['stage_label']); ?>', probability: <?php echo $s['probability']; ?>, color: '<?php echo $s['color']; ?>' },
    <?php endforeach; ?>
];

let deals = [];
let draggedCard = null;

document.addEventListener('DOMContentLoaded', () => {
    loadDeals();
    loadSummary();
});

function formatCurrency(value, currency) {
    if (!value) return '-';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: currency || 'USD', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(value);
}

function loadSummary() {
    fetch(API + '?action=summary', { credentials: 'same-origin' })
        .then(r => r.json())
        .then(resp => {
            if (!resp.success) return;
            const d = resp.data;
            document.getElementById('stat-open-count').textContent = d.open_deals || 0;
            document.getElementById('stat-pipeline-value').textContent = formatCurrency(d.pipeline_value);
            document.getElementById('stat-won-value').textContent = formatCurrency(d.won_value);
            document.getElementById('stat-lost-value').textContent = formatCurrency(d.lost_value);
        });
}

function loadDeals() {
    const search = (document.getElementById('deal-search') \u0026\u0026 document.getElementById('deal-search').value) || '';
    const assignedTo = (document.getElementById('deal-assigned-filter') \u0026\u0026 document.getElementById('deal-assigned-filter').value) || '';
    
    let url = API + '?action=list';
    if (search) url += '&search=' + encodeURIComponent(search);
    if (assignedTo) url += '&assigned_to=' + assignedTo;
    
    fetch(url, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(resp => {
            if (resp.success) {
                deals = resp.data || [];
                renderKanban();
            }
        })
        .catch(err => console.error('Error loading deals:', err));
}

function renderKanban() {
    // Clear all columns
    STAGES.forEach(stage => {
        document.getElementById('stage-' + stage.name).innerHTML = '';
        document.getElementById('count-' + stage.name).textContent = '0';
    });
    
    // Group deals by stage
    const dealsByStage = {};
    STAGES.forEach(stage => dealsByStage[stage.name] = []);
    deals.forEach(deal => {
        if (dealsByStage[deal.stage]) {
            dealsByStage[deal.stage].push(deal);
        }
    });
    
    // Render each column
    STAGES.forEach(stage => {
        const columnDeals = dealsByStage[stage.name] || [];
        document.getElementById('count-' + stage.name).textContent = columnDeals.length;
        
        const container = document.getElementById('stage-' + stage.name);
        container.innerHTML = columnDeals.map(deal => `             <div class="deal-card" draggable="true" data-id="${deal.deal_id}"                  ondragstart="onDragStart(event)" ondragend="onDragEnd(event)"                  onclick="openDealModal(${deal.deal_id})">                 <div class="deal-name">${escapeHtml(deal.deal_name)}</div>                 <div class="deal-meta">                     <span class="deal-value">${formatCurrency(deal.deal_value, deal.currency)}</span>                     <span class="deal-probability">${deal.probability || 0}%</span>                 </div>                 ${deal.company_name ? '<div class="deal-company">' + escapeHtml(deal.company_name) + '</div>' : ''}                 ${deal.assigned_name ? '<div class="deal-assignee">' + escapeHtml(deal.assigned_name) + '</div>' : ''}             </div>         `).join('');
    });
}

function onDragStart(e) {
    draggedCard = e.target;
    e.target.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', e.target.dataset.id);
}

function onDragEnd(e) {
    e.target.classList.remove('dragging');
    draggedCard = null;
}

function onDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
}

function onDrop(e) {
    e.preventDefault();
    const column = e.currentTarget;
    const newStage = column.dataset.stage;
    const dealId = e.dataTransfer.getData('text/plain');
    
    if (!dealId || !newStage) return;
    
    const deal = deals.find(d => d.deal_id == dealId);
    if (!deal || deal.stage === newStage) return;
    
    // Update UI immediately
    deal.stage = newStage;
    renderKanban();
    
    // Update server
    fetch(API + '?action=move_stage', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ deal_id: dealId, stage: newStage, csrf_token: CSRF_TOKEN })
    })
    .then(r => r.json())
    .then(resp => {
        if (!resp.success) {
            // Revert on failure
            loadDeals();
        }
    })
    .catch(() => loadDeals());
}

// Modal
function openDealModal(dealId) {
    const modal = document.getElementById('deal-modal');
    const form = document.getElementById('deal-form');
    form.reset();
    document.getElementById('deal-id').value = '';
    
    if (dealId) {
        const deal = deals.find(d => d.deal_id == dealId);
        if (!deal) return;
        document.getElementById('deal-id').value = deal.deal_id;
        document.getElementById('deal-name').value = deal.deal_name || '';
        document.getElementById('deal-currency').value = deal.currency || 'USD';
        document.getElementById('deal-value').value = deal.deal_value || '';
        document.getElementById('deal-stage').value = deal.stage || 'prospecting';
        document.getElementById('deal-probability').value = deal.probability || 10;
        document.getElementById('deal-close-date').value = deal.expected_close || '';
        document.getElementById('deal-type').value = deal.type || 'New Business';
        document.getElementById('deal-account-id').value = deal.account_id || '';
        document.getElementById('deal-contact-id').value = deal.contact_id || '';
        document.getElementById('deal-assigned-to').value = deal.assigned_to || '';
        document.getElementById('deal-description').value = deal.description || '';
        document.getElementById('deal-modal-title').textContent = 'Edit Deal';
        document.getElementById('deal-save-btn').textContent = 'Save Changes';
    } else {
        document.getElementById('deal-modal-title').textContent = 'New Deal';
        document.getElementById('deal-save-btn').textContent = 'Create Deal';
    }
    
    modal.classList.add('active');
}

function closeDealModal() {
    document.getElementById('deal-modal').classList.remove('active');
}

function saveDeal(e) {
    e.preventDefault();
    const dealId = document.getElementById('deal-id').value;
    const action = dealId ? 'update' : 'create';
    
    const data = {
        csrf_token: CSRF_TOKEN,
        deal_name: document.getElementById('deal-name').value,
        currency: document.getElementById('deal-currency').value,
        deal_value: document.getElementById('deal-value').value || 0,
        stage: document.getElementById('deal-stage').value,
        probability: document.getElementById('deal-probability').value || 10,
        expected_close: document.getElementById('deal-close-date').value || null,
        type: document.getElementById('deal-type').value,
        account_id: document.getElementById('deal-account-id').value || null,
        contact_id: document.getElementById('deal-contact-id').value || null,
        assigned_to: document.getElementById('deal-assigned-to').value || null,
        description: document.getElementById('deal-description').value,
    };
    if (dealId) data.deal_id = dealId;
    
    fetch(API + '?action=' + action, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(resp => {
        if (resp.success) {
            closeDealModal();
            loadDeals();
            loadSummary();
            showNotification(dealId ? 'Deal updated' : 'Deal created', 'success');
        } else {
            showNotification(resp.message || 'Failed to save deal', 'error');
        }
    })
    .catch(err => {
        showNotification('Error saving deal', 'error');
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDealModal(); });
document.querySelector('.modal-overlay').addEventListener('click', e => { 
    if (e.target === e.currentTarget) closeDealModal(); 
});

// Notification fallback
if (typeof showNotification !== "function") {
    window.showNotification = function(msg, type) {
        const div = document.createElement("div");
        div.className = "eb-toast eb-toast-" + (type || "info");
        div.style.cssText = "position:fixed;top:16px;right:16px;z-index:99999;padding:12px 20px;border-radius:8px;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,.15);color:#fff;background:" + (type === "error" ? "#dc2626" : type === "success" ? "#16a34a" : "#3b82f6") + ";animation:ebToastIn .25s";
        div.textContent = msg;
        document.body.appendChild(div);
        setTimeout(function() { div.style.opacity = "0"; setTimeout(function() { div.remove(); }, 300); }, 3000);
    };
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
