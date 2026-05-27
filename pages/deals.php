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
$js = ['deals'];

require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$accounts = $db->query("SELECT account_id, account_name FROM accounts WHERE company_id = ? ORDER BY account_name", [$companyId])->fetchAll();
$contacts = $db->query("SELECT contact_id, first_name, last_name FROM contacts WHERE company_id = ? ORDER BY last_name, first_name LIMIT 100", [$companyId])->fetchAll();
$users = $db->query("SELECT user_id, full_name FROM users WHERE company_id = ? AND status = 'Active' ORDER BY full_name", [$companyId])->fetchAll();
$stages = $db->query("SELECT stage_name, stage_label, probability, color FROM deal_stages WHERE company_id = 0 OR company_id = ? ORDER BY position", [$companyId])->fetchAll();
?>

<style>
.deals-page {
    max-width: 1600px;
    margin: 0 auto;
    padding: 0 20px 40px;
}
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 0 16px;
    gap: 16px;
}
.page-header h1 {
    font-size: 20px;
    font-weight: 600;
    margin: 0;
}
.header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

/* Stats Bar */
.pipeline-stats {
    display: flex;
    gap: 16px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.stat-card {
    background: white;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 8px;
    padding: 14px 20px;
    min-width: 160px;
}
.stat-label {
    font-size: 11px;
    color: var(--text-secondary, #6b7280);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 4px;
}
.stat-value {
    font-size: 22px;
    font-weight: 700;
    color: var(--text-primary, #1f2937);
}
.stat-value.green { color: #16a34a; }
.stat-value.red { color: #dc2626; }
.stat-value.blue { color: #2563eb; }

/* Filters */
.filters-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    align-items: center;
}
.search-input {
    padding: 8px 12px;
    border: 1px solid var(--border, #d1d5db);
    border-radius: 6px;
    font-size: 13px;
    width: 200px;
    background: white;
}
select.filter-select {
    padding: 8px 12px;
    border: 1px solid var(--border, #d1d5db);
    border-radius: 6px;
    font-size: 13px;
    background: white;
}
.btn {
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-primary { background: var(--primary, #2563eb); color: white; }
.btn-primary:hover { background: var(--primary-dark, #1d4ed8); }
.btn-outline { background: white; border: 1px solid var(--border, #d1d5db); color: var(--text-primary, #374151); }

/* Kanban Board */
.pipeline-board {
    display: flex;
    gap: 14px;
    overflow-x: auto;
    padding-bottom: 16px;
    min-height: calc(100vh - 240px);
    align-items: flex-start;
}
.pipeline-col {
    flex: 0 0 280px;
    background: var(--bg-secondary, #f9fafb);
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 10px;
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 200px);
}
.pipeline-col-header {
    padding: 14px 16px 10px;
    border-bottom: 1px solid var(--border, #e5e7eb);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.col-title {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.col-count {
    font-size: 12px;
    font-weight: 600;
    padding: 2px 8px;
    background: white;
    border-radius: 10px;
    color: var(--text-secondary, #6b7280);
}
.pipeline-col-body {
    padding: 12px;
    flex: 1;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

/* Deal Card */
.deal-card {
    background: white;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 8px;
    padding: 14px;
    cursor: grab;
    transition: box-shadow 0.15s, transform 0.15s;
    position: relative;
}
.deal-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transform: translateY(-1px);
}
.deal-card.dragging { opacity: 0.5; cursor: grabbing; }
.deal-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
    padding-right: 24px;
}
.deal-name {
    font-size: 14px;
    font-weight: 500;
    color: var(--text-primary, #1f2937);
    line-height: 1.4;
    flex: 1;
}
.deal-value {
    font-size: 15px;
    font-weight: 700;
    color: var(--primary, #2563eb);
    margin-bottom: 8px;
}
.deal-meta {
    display: flex;
    flex-direction: column;
    gap: 5px;
    font-size: 12px;
    color: var(--text-secondary, #6b7280);
}
.deal-meta-row {
    display: flex;
    align-items: center;
    gap: 6px;
}
.deal-avatar {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: var(--primary, #2563eb);
    color: white;
    font-size: 10px;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
}
.deal-close-date {
    font-size: 11px;
    font-weight: 500;
}
.deal-close-date.overdue { color: #dc2626; }
.deal-close-date.soon { color: #ea580c; }

.probability-bar {
    height: 3px;
    background: var(--bg-secondary, #e5e7eb);
    border-radius: 2px;
    margin-top: 10px;
    overflow: hidden;
}
.probability-fill {
    height: 100%;
    border-radius: 2px;
    transition: width 0.3s;
}

.deal-won { border-left: 3px solid #22c55e; }
.deal-lost { border-left: 3px solid #ef4444; opacity: 0.7; }

/* Empty State */
.pipeline-empty {
    text-align: center;
    padding: 30px 16px;
    color: var(--text-secondary, #9ca3af);
    font-size: 13px;
}

/* Modal */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.4);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}
.modal-overlay.active { display: flex; }
.modal {
    background: white;
    border-radius: 12px;
    width: 580px;
    max-width: 95vw;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
}
.modal-header {
    padding: 20px 24px 16px;
    border-bottom: 1px solid var(--border, #e5e7eb);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.modal-header h2 {
    font-size: 17px;
    font-weight: 600;
    margin: 0;
}
.modal-close {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: var(--text-secondary, #9ca3af);
}
.modal-close:hover { color: var(--text-primary, #1f2937); }
.modal-body { padding: 20px 24px; }
.form-group { margin-bottom: 14px; }
.form-label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 5px;
    color: var(--text-primary, #374151);
}
.form-control {
    width: 100%;
    padding: 9px 12px;
    border: 1px solid var(--border, #d1d5db);
    border-radius: 6px;
    font-size: 13px;
    box-sizing: border-box;
    font-family: inherit;
    color: var(--text-primary, #1f2937);
    background: white;
}
.form-control:focus {
    outline: none;
    border-color: var(--primary, #2563eb);
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}
textarea.form-control { min-height: 70px; resize: vertical; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-actions {
    padding: 16px 24px;
    border-top: 1px solid var(--border, #e5e7eb);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
.form-actions .btn { padding: 9px 18px; }

.currency-input {
    display: flex;
    align-items: center;
    gap: 8px;
}
.currency-input select {
    width: 80px;
    padding: 9px 8px;
    border: 1px solid var(--border, #d1d5db);
    border-radius: 6px;
    font-size: 13px;
    background: white;
}
.currency-input input { flex: 1; }
</style>

<div class="deals-page">
    <div class="page-header">
        <h1>Pipeline</h1>
        <div class="header-actions">
            <button class="btn btn-primary" onclick="openDealModal()">+ New Deal</button>
        </div>
    </div>

    <!-- Stats -->
    <div class="pipeline-stats" id="pipeline-stats">
        <div class="stat-card">
            <div class="stat-label">Open Deals</div>
            <div class="stat-value" id="stat-open-count">-</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Pipeline Value</div>
            <div class="stat-value blue" id="stat-pipeline-value">-</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Won (All Time)</div>
            <div class="stat-value green" id="stat-won-value">-</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Lost (All Time)</div>
            <div class="stat-value red" id="stat-lost-value">-</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
        <input type="text" id="deal-search" class="search-input" placeholder="Search deals..." oninput="loadDeals()">
        <select id="deal-assigned-filter" class="filter-select" onchange="loadDeals()">
            <option value="">All Owners</option>
            <?php foreach ($users as $u): ?>
                <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Kanban Board -->
    <div class="pipeline-board" id="pipeline-board">
        <!-- Columns rendered by JS -->
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
            <div class="modal-body">
                <input type="hidden" id="deal-id" value="">
                <div class="form-group">
                    <label class="form-label">Deal Name *</label>
                    <input type="text" id="deal-name" class="form-control" required placeholder="e.g. Acme Corp - Enterprise License">
                </div>
                <div class="form-group">
                    <label class="form-label">Value</label>
                    <div class="currency-input">
                        <select id="deal-currency" class="form-control">
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="GBP">GBP</option>
                            <option value="AED">AED</option>
                            <option value="SAR">SAR</option>
                            <option value="CAD">CAD</option>
                            <option value="AUD">AUD</option>
                        </select>
                        <input type="number" id="deal-value" class="form-control" placeholder="0.00" min="0" step="0.01">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Stage</label>
                        <select id="deal-stage" class="form-control">
                            <?php foreach ($stages as $s): ?>
                                <option value="<?= $s['stage_name'] ?>"><?= htmlspecialchars($s['stage_label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Probability %</label>
                        <input type="number" id="deal-probability" class="form-control" min="0" max="100" value="10">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Expected Close</label>
                        <input type="date" id="deal-close-date" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Type</label>
                        <select id="deal-type" class="form-control">
                            <option value="New Business">New Business</option>
                            <option value="Renewal">Renewal</option>
                            <option value="Upsell">Upsell</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Account</label>
                        <select id="deal-account-id" class="form-control">
                            <option value="">None</option>
                            <?php foreach ($accounts as $a): ?>
                                <option value="<?= $a['account_id'] ?>"><?= htmlspecialchars($a['account_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Primary Contact</label>
                        <select id="deal-contact-id" class="form-control">
                            <option value="">None</option>
                            <?php foreach ($contacts as $c): ?>
                                <option value="<?= $c['contact_id'] ?>"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Assigned To</label>
                    <select id="deal-assigned-to" class="form-control">
                        <option value="">Unassigned</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea id="deal-description" class="form-control" placeholder="Deal details..."></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeDealModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="deal-save-btn">Create Deal</button>
            </div>
        </form>
    </div>
</div>

<script>
const COMPANY_ID = <?= json_encode($companyId) ?>;
const USER_ROLE = <?= json_encode($userRole) ?>;
const CSRF_TOKEN = u003c?php echo json_encode($_SESSION['csrf_token'] ?? '') ?>;
const API = '/api/deals.php';

const STAGES = [
    <?php foreach ($stages as $s): ?>
        { name: '<?= $s['stage_name'] ?>', label: '<?= htmlspecialchars($s['stage_label']) ?>', probability: <?= $s['probability'] ?>, color: '<?= $s['color'] ?>' },
    <?php endforeach; ?>
];

let deals = [];
let draggedCard = null;

document.addEventListener('DOMContentLoaded', () => {
    loadDeals();
    loadSummary();
});

function formatCurrency(value, currency = 'USD') {
    if (!value) return '-';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: currency, minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(value);
}

function loadSummary() {
    fetch(`${API}?action=summary`, { credentials: 'same-origin' })
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
    const search = document.getElementById('deal-search')?.value || '';
    const assignedTo = document.getElementById('deal-assigned-filter')?.value || '';
    
    let url = `${API}?action=list`;
    if (search) url += `&search=${encodeURIComponent(search)}`;
    if (assignedTo) url += `&assigned_to=${assignedTo}`;
    
    fetch(url, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(resp => {
            if (resp.success) {
                deals = resp.data || [];
                renderBoard();
            }
        });
}

function renderBoard() {
    const board = document.getElementById('pipeline-board');
    
    board.innerHTML = STAGES.map(stage => {
        const stageDeals = deals.filter(d => d.stage === stage.name);
        const stageColor = stage.color || '#6b7280';
        
        const isWon = stage.name === 'closed_won';
        const isLost = stage.name === 'closed_lost';
        const cardClass = isWon ? 'deal-won' : isLost ? 'deal-lost' : '';
        
        return `
        <div class="pipeline-col" data-stage="${stage.name}">
            <div class="pipeline-col-header">
                <span class="col-title" style="color:${stageColor}">${stage.label}</span>
                <span class="col-count">${stageDeals.length}</span>
            </div>
            <div class="pipeline-col-body" 
                 ondragover="onDragOver(event)" 
                 ondrop="onDrop(event, '${stage.name}')">
                ${stageDeals.length ? stageDeals.map(d => renderDealCard(d, stage, cardClass)).join('') : '<div class="pipeline-empty">Drop deals here</div>'}
            </div>
        </div>`;
    }).join('');
}

function renderDealCard(deal, stage, cardClass) {
    const initials = deal.assigned_name ? deal.assigned_name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2) : '?';
    const prob = deal.probability || stage.probability || 0;
    const probColor = prob >= 75 ? '#22c55e' : prob >= 50 ? '#2563eb' : prob >= 25 ? '#fbbf24' : '#9ca3af';
    
    let closeDateHtml = '';
    if (deal.expected_close) {
        const today = new Date().toISOString().split('T')[0];
        const closeDate = deal.expected_close;
        if (closeDate < today) {
            closeDateHtml = `<span class="deal-close-date overdue">Overdue: ${formatDate(closeDate)}</span>`;
        } else if (closeDate <= new Date(Date.now() + 7 * 86400000).toISOString().split('T')[0]) {
            closeDateHtml = `<span class="deal-close-date soon">Close: ${formatDate(closeDate)}</span>`;
        } else {
            closeDateHtml = `<span class="deal-close-date">Close: ${formatDate(closeDate)}</span>`;
        }
    }
    
    return `
    <div class="deal-card ${cardClass}" draggable="true" data-id="${deal.deal_id}"
         ondragstart="onDragStart(event)" ondragend="onDragEnd(event)"
         onclick="openDealModal(${deal.deal_id})">
        <div class="deal-card-header">
            <div class="deal-name">${escapeHtml(deal.deal_name)}</div>
        </div>
        <div class="deal-value">${formatCurrency(deal.deal_value, deal.currency)}</div>
        <div class="deal-meta">
            ${deal.account_name ? `<div class="deal-meta-row">🏢 ${escapeHtml(deal.account_name)}</div>` : ''}
            ${deal.contact_name ? `<div class="deal-meta-row">👤 ${escapeHtml(deal.contact_name)}</div>` : ''}
            ${closeDateHtml}
            ${deal.assigned_name ? `
            <div class="deal-meta-row">
                <div class="deal-avatar">${initials}</div>
                <span>${escapeHtml(deal.assigned_name)}</span>
            </div>` : ''}
        </div>
        <div class="probability-bar">
            <div class="probability-fill" style="width:${prob}%;background:${probColor}"></div>
        </div>
    </div>`;
}

);
}

// Drag & Drop
function onDragStart(e) {
    draggedCard = e.target;
    e.target.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
}
function onDragEnd(e) {
    e.target.classList.remove('dragging');
    draggedCard = null;
}
function onDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
}
function onDrop(e, newStage) {
    e.preventDefault();
    if (!draggedCard) return;
    const dealId = draggedCard.dataset.id;
    moveDeal(dealId, newStage);
}

function moveDeal(dealId, newStage) {
    fetch(`${API}?action=move`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ deal_id: dealId, stage: newStage, csrf_token: CSRF_TOKEN })
    })
    .then(r => r.json())
    .then(resp => {
        if (resp.success) {
            const deal = deals.find(d => d.deal_id == dealId);
            if (deal) deal.stage = newStage;
            renderBoard();
            loadSummary();
            showNotification('Deal moved', 'success');
        } else {
            showNotification(resp.message || 'Failed to move', 'error');
        }
    });
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
    
    fetch(`${API}?action=${action}`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    }).then(r => r.json()).then(resp => {
        if (resp.success) {
            closeDealModal();
            loadDeals();
            loadSummary();
            showNotification(dealId ? 'Deal updated' : 'Deal created', 'success');
        } else {
            showNotification(resp.message || 'Failed to save', 'error');
        }
    });
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDealModal(); });
document.querySelector('.modal-overlay').addEventListener('click', e => { 
    if (e.target === e.currentTarget) closeDealModal(); 
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
