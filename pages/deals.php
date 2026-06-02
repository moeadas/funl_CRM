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

$pageTitle = __('Pipeline');
$js = ['deals'];

require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$accounts = $db->query("SELECT account_id, account_name FROM accounts WHERE company_id = ? ORDER BY account_name", [$companyId])->fetchAll();
$contacts = $db->query("SELECT contact_id, first_name, last_name FROM contacts WHERE company_id = ? ORDER BY last_name, first_name LIMIT 100", [$companyId])->fetchAll();
$users = $db->query("SELECT user_id, full_name FROM users WHERE company_id = ? AND status = 'Active' ORDER BY full_name", [$companyId])->fetchAll();
$stages = $db->query("SELECT stage_name, stage_label, probability, color FROM deal_stages WHERE company_id = 0 OR company_id = ? ORDER BY position", [$companyId])->fetchAll();
?><div class="deals-page">
    <div class="page-header">
        <h1><?php echo htmlspecialchars(__('Pipeline')); ?></h1>
        <div class="header-actions">
            <a href="/pages/deal-form.php" class="btn btn-primary" style="text-decoration:none;">+ <?php echo htmlspecialchars(__('New Deal')); ?></a>
        </div>
    </div>

    <!-- Stats -->
    <div class="pipeline-stats" id="pipeline-stats">
        <div class="stat-card">
            <div class="stat-label"><?php echo htmlspecialchars(__('Open Deals')); ?></div>
            <div class="stat-value" id="stat-open-count">-</div>
        </div>
        <div class="stat-card">
            <div class="stat-label"><?php echo htmlspecialchars(__('Pipeline Value')); ?></div>
            <div class="stat-value blue" id="stat-pipeline-value">-</div>
        </div>
        <div class="stat-card">
            <div class="stat-label"><?php echo htmlspecialchars(__('Won (All Time)')); ?></div>
            <div class="stat-value green" id="stat-won-value">-</div>
        </div>
        <div class="stat-card">
            <div class="stat-label"><?php echo htmlspecialchars(__('Lost (All Time)')); ?></div>
            <div class="stat-value red" id="stat-lost-value">-</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
        <input type="text" id="deal-search" class="search-input" placeholder="<?php echo htmlspecialchars(__('Search deals...')); ?>" oninput="loadDeals()">
        <select id="deal-assigned-filter" class="filter-select" onchange="loadDeals()">
            <option value=""><?php echo htmlspecialchars(__('All Owners')); ?></option>
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

<script>
const COMPANY_ID = <?= json_encode($companyId) ?>;
const USER_ROLE = <?= json_encode($userRole) ?>;
const CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
const API = '/api/deals.php';

const STAGES = [
    <?php foreach ($stages as $s): ?>
        { name: '<?= $s['stage_name'] ?>', label: '<?= htmlspecialchars(__($s['stage_label'])) ?>', probability: <?= $s['probability'] ?>, color: '<?= $s['color'] ?>' },
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
                ${stageDeals.length ? stageDeals.map(d => renderDealCard(d, stage, cardClass)).join('') : '<div class="pipeline-empty">' + escapeHtml(__('Drop deals here')) + '</div>'}
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
            closeDateHtml = `<span class="deal-close-date overdue">${escapeHtml(__('Overdue'))}: ${formatDate(closeDate)}</span>`;
        } else if (closeDate <= new Date(Date.now() + 7 * 86400000).toISOString().split('T')[0]) {
            closeDateHtml = `<span class="deal-close-date soon">${escapeHtml(__('Close'))}: ${formatDate(closeDate)}</span>`;
        } else {
            closeDateHtml = `<span class="deal-close-date">${escapeHtml(__('Close'))}: ${formatDate(closeDate)}</span>`;
        }
    }
    
    return `
    <div class="deal-card ${cardClass}" draggable="true" data-id="${deal.deal_id}"
         ondragstart="onDragStart(event)" ondragend="onDragEnd(event)"
         onclick="window.location.href='/pages/deal-form.php?id=${deal.deal_id}'">
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
            if (deal) {
                deal.stage = newStage;
                if (newStage === 'closed_won') {
                    deal.probability = 100;
                } else if (newStage === 'closed_lost') {
                    deal.probability = 0;
                }
            }
            renderBoard();
            loadSummary();
            showNotification(__('Deal moved'), 'success');
        } else {
            showNotification(resp.message || __('Failed to move'), 'error');
        }
    });
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    try {
        return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    } catch (e) { return dateStr; }
}
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
