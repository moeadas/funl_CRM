<?php
/**
 * White Label CRM - Leads Management
 * List, search, filter, bulk actions, grid/list toggle
 * CSRF protected, Apple-style design, no FA icons
 * Region removed — Country used instead (dynamic filter)
 * Assign options hidden from Sales Reps
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
requireLogin();

$currentUser = getCurrentUser();
$csrf_token = generateCSRFToken();
$db = Database::getInstance()->getConnection();
$isSalesRep = !hasRole('Sales Manager');

$action = $_GET['action'] ?? 'list';

// Get filter options
$statuses = ['New Lead', 'Contacted', 'Interested', 'Not Interested', 'Schedule Call', 'Call Scheduled', 'Demo Scheduled', 'Proposal Sent', 'Negotiation', 'Won', 'Lost', 'On Hold'];
$leadTypes = ['Business','Individual','Partner','Reseller','Other'];
$priorities = ['Low', 'Medium', 'High', 'Urgent'];
$sources = ['Website', 'Facebook', 'Instagram', 'Google Ads', 'LinkedIn', 'Referral', 'Cold Outreach', 'Event', 'Import', 'Other'];

// Dynamic country list — scoped by company
$companyId = $_SESSION['company_id'] ?? null;
if ($companyId) {
    if ($isSalesRep) {
        $countryStmt = $db->prepare("SELECT DISTINCT country FROM leads WHERE country IS NOT NULL AND country != '' AND company_id = ? AND (assigned_to = ? OR created_by = ?) ORDER BY country ASC");
        $countryStmt->execute([$companyId, $currentUser['user_id'], $currentUser['user_id']]);
    } else {
        $countryStmt = $db->prepare("SELECT DISTINCT country FROM leads WHERE country IS NOT NULL AND country != '' AND company_id = ? ORDER BY country ASC");
        $countryStmt->execute([$companyId]);
    }
} else {
    if ($isSalesRep) {
        $countryStmt = $db->prepare("SELECT DISTINCT country FROM leads WHERE country IS NOT NULL AND country != '' AND company_id = ? AND (assigned_to = ? OR created_by = ?) ORDER BY country ASC");
        $countryStmt->execute([$companyId, $currentUser['user_id'], $currentUser['user_id']]);
    } else {
        $countryStmt = $db->prepare("SELECT DISTINCT country FROM leads WHERE country IS NOT NULL AND country != '' AND company_id = ? ORDER BY country ASC");
        $countryStmt->execute([$companyId]);
    }
}
$countries = $countryStmt->fetchAll(PDO::FETCH_COLUMN);

// Get users for assignment dropdown (only for managers)
$users = hasRole('Sales Manager') ? getAllUsers() : [];

$pageTitle = __('Leads');
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><?php echo htmlspecialchars(__('Leads Management')); ?></h1>
    <a href="/pages/lead-form.php" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px;text-decoration:none;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <?php echo htmlspecialchars(__('Add New Lead')); ?>
    </a>
</div>

<?php if (isset($_GET['follow_up']) && $_GET['follow_up'] == '1'): ?>
<div class="alert alert-warning" style="display:flex;align-items:center;justify-content:space-between;">
    <div style="display:flex;align-items:center;gap:8px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
        <strong><?php echo htmlspecialchars(__('Follow Up Leads')); ?></strong> &mdash; <?php echo htmlspecialchars(__('Showing leads that have a "Follow-up" interaction logged.')); ?>
    </div>
    <a href="/pages/leads.php" class="btn btn-sm btn-outline"><?php echo htmlspecialchars(__('Show All Leads')); ?></a>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="card filter-card">
    <div class="card-body">
        <form id="filterForm" class="filter-form">
            <div class="form-group filter-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Search')); ?></label>
                <input type="text" name="search" class="form-control" placeholder="<?php echo htmlspecialchars(__('Company, contact, email...')); ?>" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
            </div>
            <div class="form-group filter-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Status')); ?></label>
                <select name="status" class="form-control">
                    <option value=""><?php echo htmlspecialchars(__('All Statuses')); ?></option>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?php echo $status; ?>" <?php echo ($_GET['status'] ?? '') === $status ? 'selected' : ''; ?>><?php echo htmlspecialchars(__($status)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group filter-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Country')); ?></label>
                <select name="country" class="form-control">
                    <option value=""><?php echo htmlspecialchars(__('All Countries')); ?></option>
                    <?php foreach ($countries as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>" <?php echo ($_GET['country'] ?? '') === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars(__($c)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!$isSalesRep): ?>
            <div class="form-group filter-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Assigned To')); ?></label>
                <select name="assigned_to" class="form-control">
                    <option value=""><?php echo htmlspecialchars(__('All Users')); ?></option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['user_id']; ?>" <?php echo ($_GET['assigned_to'] ?? '') == $user['user_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <?php echo htmlspecialchars(__('Filter')); ?>
            </button>
            <a href="/pages/leads.php" class="btn btn-outline"><?php echo htmlspecialchars(__('Clear')); ?></a>
        </form>
    </div>
</div>

<!-- Leads Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><?php echo htmlspecialchars(__('All Leads')); ?></h2>
        <div class="card-header-actions">
            <div class="view-toggle">
                <button class="view-btn active" onclick="toggleView('list')" id="btn-list" title="<?php echo htmlspecialchars(__('List View')); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                </button>
                <button class="view-btn" onclick="toggleView('grid')" id="btn-grid" title="<?php echo htmlspecialchars(__('Grid View')); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                </button>
            </div>
            <?php if (hasRole('Sales Manager')): ?>
                <a href="/pages/export-leads.php" class="btn btn-sm btn-outline">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <?php echo htmlspecialchars(__('Export CSV')); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        
        <?php if (!$isSalesRep): ?>
        <!-- Bulk Actions Toolbar — managers only -->
        <div id="bulkActions" class="bulk-toolbar" style="display: none;">
            <div class="bulk-toolbar-left">
                <span class="bulk-count"><span id="selectedCount">0</span> <?php echo htmlspecialchars(__('selected')); ?></span>
                <div class="bulk-divider"></div>
                <select id="bulkAssignUser" class="form-control bulk-select">
                    <option value=""><?php echo htmlspecialchars(__('Assign to...')); ?></option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['user_id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary btn-sm" onclick="bulkAssign()"><?php echo htmlspecialchars(__('Apply')); ?></button>
            </div>
            <div class="bulk-toolbar-right">
                <?php if (hasRole('Sales Manager')): ?>
                <button class="btn btn-danger btn-sm" onclick="bulkDelete()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    <?php echo htmlspecialchars(__('Delete')); ?>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div id="leadsListContainer" class="table-container">
            <table class="table" id="leadsTable">
                <thead>
                    <tr>
                        <?php if (!$isSalesRep): ?><th class="th-checkbox" style="width:40px;min-width:40px;"><input type="checkbox" id="selectAll" onclick="toggleAll(this)"></th><?php endif; ?>
                        <th style="width:44px;min-width:44px;text-align:center;">#</th>
                        <th class="th-sortable th-resizable" data-sort="company_name"><?php echo htmlspecialchars(__('Company')); ?> <span class="sort-icon"></span></th>
                        <th class="th-sortable th-resizable" data-sort="contact_person"><?php echo htmlspecialchars(__('Contact')); ?> <span class="sort-icon"></span></th>
                        <th class="th-sortable th-resizable" data-sort="country"><?php echo htmlspecialchars(__('Country')); ?> <span class="sort-icon"></span></th>
                        <th class="th-sortable th-resizable" data-sort="lead_status"><?php echo htmlspecialchars(__('Status')); ?> <span class="sort-icon"></span></th>
                        <th class="th-sortable th-resizable" data-sort="priority"><?php echo htmlspecialchars(__('Priority')); ?> <span class="sort-icon"></span></th>
                        <?php if (!$isSalesRep): ?><th class="th-sortable th-resizable" data-sort="assigned_name"><?php echo htmlspecialchars(__('Assigned To')); ?> <span class="sort-icon"></span></th><?php endif; ?>
                        <th class="th-sortable th-resizable" data-sort="created_at"><?php echo htmlspecialchars(__('Date Created')); ?> <span class="sort-icon"></span></th>
                        <th class="th-sortable th-resizable" data-sort="updated_at"><?php echo htmlspecialchars(__('Updated')); ?> <span class="sort-icon"></span></th>
                        <th style="width:60px;min-width:60px;"><?php echo htmlspecialchars(__('Actions')); ?></th>
                    </tr>
                </thead>
                <tbody id="leadsTableBody">
                    <tr>
                        <td colspan="<?php echo $isSalesRep ? '9' : '11'; ?>" class="text-center text-muted"><?php echo htmlspecialchars(__('Loading...')); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <?php if (!$isSalesRep): ?>
        <div id="gridSelectAllContainer" class="grid-select-all" style="display: none;">
            <input type="checkbox" id="gridSelectAll" onclick="toggleAll(this)">
            <label for="gridSelectAll"><?php echo htmlspecialchars(__('Select All Leads on Page')); ?></label>
        </div>
        <?php endif; ?>
        
        <div id="leadsGridContainer" class="leads-grid" style="display: none;"></div>
        <div id="pagination" class="pagination"></div>
    </div>
</div>




<script>
const CSRF_TOKEN = "<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>";

// Escape HTML to prevent XSS
function escapeHtml(text) {
    if (text == null) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

var isSalesRep = <?php echo $isSalesRep ? 'true' : 'false'; ?>;
let currentLeads = [];
let currentPage = 1;
let currentView = localStorage.getItem('leadsView') || 'list';
let currentSortBy = localStorage.getItem('leadsSortBy') || 'created_at';
let currentSortDir = localStorage.getItem('leadsSortDir') || 'DESC';

document.addEventListener('DOMContentLoaded', function() {
    toggleView(currentView, false);
    initSortHeaders();
    initRowClicks();
    updateSortIndicators();
    loadLeads();
});

function initRowClicks() {
    document.addEventListener('click', function(e) {
        var row = e.target.closest && e.target.closest('tr.clickable-row');
        if (!row) return;
        // Don't navigate if click was inside a .no-row-click cell
        if (e.target.closest('.no-row-click')) return;
        // Don't navigate if click was on a link/button/input
        if (e.target.closest('a, button, input, select, textarea, label')) return;
        var href = row.getAttribute('data-href');
        if (href) window.location.href = href;
    });
}

function initSortHeaders() {
    var headers = document.querySelectorAll('.th-sortable');
    for (var i = 0; i < headers.length; i++) {
        headers[i].addEventListener('click', function() {
            var col = this.getAttribute('data-sort');
            if (currentSortBy === col) {
                currentSortDir = currentSortDir === 'ASC' ? 'DESC' : 'ASC';
            } else {
                currentSortBy = col;
                // Default direction: DESC for dates, ASC for text
                currentSortDir = (col === 'updated_at' || col === 'created_at') ? 'DESC' : 'ASC';
            }
            localStorage.setItem('leadsSortBy', currentSortBy);
            localStorage.setItem('leadsSortDir', currentSortDir);
            updateSortIndicators();
            loadLeads(1);
        });
    }
}

function updateSortIndicators() {
    var headers = document.querySelectorAll('.th-sortable');
    for (var i = 0; i < headers.length; i++) {
        var col = headers[i].getAttribute('data-sort');
        var icon = headers[i].querySelector('.sort-icon');
        headers[i].classList.remove('th-sort-active');
        icon.className = 'sort-icon';
        if (col === currentSortBy) {
            headers[i].classList.add('th-sort-active');
            icon.classList.add(currentSortDir === 'ASC' ? 'sort-asc' : 'sort-desc');
        }
    }
}

function toggleView(view, render) {
    if (render === undefined) render = true;
    currentView = view;
    localStorage.setItem('leadsView', view);
    
    document.getElementById('btn-list').className = 'view-btn ' + (view === 'list' ? 'active' : '');
    document.getElementById('btn-grid').className = 'view-btn ' + (view === 'grid' ? 'active' : '');
    
    document.getElementById('leadsListContainer').style.display = view === 'list' ? 'block' : 'none';
    document.getElementById('leadsGridContainer').style.display = view === 'grid' ? 'grid' : 'none';
    
    var gridSelectAll = document.getElementById('gridSelectAllContainer');
    if (gridSelectAll) {
        gridSelectAll.style.display = view === 'grid' ? 'flex' : 'none';
    }
    
    if (render && currentLeads.length) {
        if (view === 'list') {
            document.getElementById('leadsGridContainer').innerHTML = '';
            renderLeadsList(currentLeads);
        } else {
            document.getElementById('leadsTableBody').innerHTML = '';
            renderLeadsGrid(currentLeads);
        }
        if (!isSalesRep) updateBulkState();
        var selectAll = document.getElementById('selectAll');
        if (selectAll) selectAll.checked = false;
        var gridAll = document.getElementById('gridSelectAll');
        if (gridAll) gridAll.checked = false;
    }
}

function loadLeads(page) {
    if (page === undefined) page = 1;
    currentPage = page;
    var params = new URLSearchParams(window.location.search);
    params.set('page', page);
    params.set('sort_by', currentSortBy);
    params.set('sort_dir', currentSortDir);
    
    var colSpan = isSalesRep ? 9 : 11;
    if (currentView === 'list') {
        document.getElementById('leadsTableBody').innerHTML = '<tr><td colspan="' + colSpan + '" class="text-center text-muted">' + escapeHtml(__('Loading...')) + '</td></tr>';
    } else {
        document.getElementById('leadsGridContainer').innerHTML = '<div style="grid-column:1/-1;text-align:center;" class="text-muted">' + escapeHtml(__('Loading...')) + '</div>';
    }
    
    fetch('/api/leads.php?action=list&_cb=' + Date.now() + '&' + params.toString(), { credentials: 'same-origin' })
        .then(function(r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(function(data) {
            if (data.success) {
                currentLeads = data.data.leads;
                if (currentView === 'list') {
                    renderLeadsList(currentLeads);
                } else {
                    renderLeadsGrid(currentLeads);
                }
                renderPagination(data.data.page, data.data.pages);
            } else {
                // API returned success:false
                var errorMsg = data.message || 'Failed to load leads';
                var colSpan = isSalesRep ? 9 : 11;
                if (currentView === 'list') {
                    document.getElementById('leadsTableBody').innerHTML = '<tr><td colspan="' + colSpan + '" class="text-center text-muted">' + escapeHtml(__(errorMsg)) + '</td></tr>';
                } else {
                    document.getElementById('leadsGridContainer').innerHTML = '<div style="grid-column:1/-1;text-align:center;">' + escapeHtml(__(errorMsg)) + '</div>';
                }
            }
        })
        .catch(function(err) {
            console.error('Error loading leads:', err);
            var errorMsg = 'Failed to load leads';
            var colSpan = isSalesRep ? 9 : 11;
            if (currentView === 'list') {
                document.getElementById('leadsTableBody').innerHTML = '<tr><td colspan="' + colSpan + '" class="text-center text-muted">' + escapeHtml(__(errorMsg)) + '</td></tr>';
            } else {
                document.getElementById('leadsGridContainer').innerHTML = '<div style="grid-column:1/-1;text-align:center;">' + escapeHtml(__(errorMsg)) + '</div>';
            }
        });
}

function renderLeadsList(leads) {
    var tbody = document.getElementById('leadsTableBody');
    var colSpan = isSalesRep ? 9 : 11;
    
    if (!leads.length) {
        tbody.innerHTML = '<tr><td colspan="' + colSpan + '" class="text-center text-muted">' + escapeHtml(__('No leads found')) + '</td></tr>';
        return;
    }
    
    var startNum = ((currentPage - 1) * 25) + 1;
    tbody.innerHTML = leads.map(function(lead, idx) {
        var rowNum = startNum + idx;
        var row = '<tr class="clickable-row" data-href="/pages/lead-detail.php?id=' + lead.lead_id + '">';
        if (!isSalesRep) row += '<td class="no-row-click"><input type="checkbox" class="lead-checkbox" value="' + lead.lead_id + '" onchange="updateBulkState()"></td>';
        row += '<td style="text-align:center;color:var(--color-text-tertiary);font-size:13px;font-weight:500;">' + rowNum + '</td>' +
            '<td><strong><a href="/pages/lead-detail.php?id=' + lead.lead_id + '" style="color:inherit;text-decoration:none;border-bottom:1px dashed transparent;transition:border-color .15s" onmouseover="this.style.borderBottomColor=\'var(--color-primary)\'" onmouseout="this.style.borderBottomColor=\'transparent\'" title="' + escapeHtml(__('Open lead')) + '">' + escapeHtml(lead.company_name || lead.contact_person || __('Unnamed')) + ' <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2.5" stroke-linecap="round" style="vertical-align:-1px;opacity:.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg></a></strong><br><small class="text-muted">' + escapeHtml(__(lead.lead_type)) + '</small></td>' +
            '<td>' + escapeHtml(lead.contact_person || '-') + '<br><small class="text-muted">' + escapeHtml(lead.email || '-') + '</small></td>' +
            '<td>' + escapeHtml(__(lead.country) || '-') + '</td>' +
            '<td><span class="badge ' + getStatusClass(lead.lead_status) + '">' + escapeHtml(__(lead.lead_status)) + '</span></td>' +
            '<td><span class="badge ' + getPriorityClass(lead.priority) + '">' + escapeHtml(__(lead.priority)) + '</span></td>';
        if (!isSalesRep) row += '<td>' + escapeHtml(lead.assigned_name || __('Unassigned')) + '</td>';
        row += '<td><small class="text-muted">' + formatDate(lead.created_at) + '</small></td>' +
            '<td><small class="text-muted">' + formatDate(lead.updated_at) + '</small></td>' +
            '<td class="no-row-click"><div style="display:flex;gap:6px;justify-content:center">' +
            '<a href="/pages/lead-form.php?id=' + lead.lead_id + '" class="btn btn-sm btn-outline" title="' + escapeHtml(__('Edit')) + '"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>' +
            '<button onclick="moveLeadToContact(' + lead.lead_id + ', event)" title="' + escapeHtml(__('Convert to Contact')) + '" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:5px;padding:5px 8px;font-size:12px;cursor:pointer;color:#15803d;white-space:nowrap">→ ' + escapeHtml(__('Contact')) + '</button>' +
            '</div></td>' +
        '</tr>';
        return row;
    }).join('');
    
    var selectAll = document.getElementById('selectAll');
    if (selectAll) selectAll.checked = false;
    if (!isSalesRep) updateBulkState();
}

function renderLeadsGrid(leads) {
    var container = document.getElementById('leadsGridContainer');
    
    if (!leads.length) {
        container.innerHTML = '<div style="grid-column:1/-1;text-align:center;" class="text-muted">' + escapeHtml(__('No leads found')) + '</div>';
        return;
    }
    
    container.innerHTML = leads.map(function(lead) {
        return '<div class="lead-card">' +
            (!isSalesRep ? '<input type="checkbox" class="lead-checkbox card-checkbox" value="' + lead.lead_id + '" onchange="updateBulkState()">' : '') +
            '<div onclick="window.location=\'/pages/lead-detail.php?id=' + lead.lead_id + '\'" style="cursor:pointer;">' +
                '<div class="lead-card-header"><div>' +
                    '<h3 class="lead-card-title">' + escapeHtml(lead.company_name || lead.contact_person || __('Unnamed')) + '</h3>' +
                    '<div class="lead-card-subtitle">' + escapeHtml(__(lead.country) || '-') + '</div>' +
                '</div><span class="lead-tag purple">' + escapeHtml(__(lead.lead_type)) + '</span></div>' +
                '<div class="lead-desc">' + escapeHtml(lead.notes ? (lead.notes.length > 100 ? lead.notes.substring(0, 100) + '...' : lead.notes) : __('No description available')) + '</div>' +
                '<div class="lead-info-grid">' +
                    '<div class="lead-info-item"><span>' + escapeHtml(lead.phone || '-') + '</span></div>' +
                    '<div class="lead-info-item"><span>' + escapeHtml(lead.email || '-') + '</span></div>' +
                '</div>' +
                '<div class="lead-card-footer">' +
                    '<strong>' + escapeHtml(lead.contact_person || '-') + '</strong> ' +
                    '<span class="text-muted">(' + escapeHtml(lead.title_position || __('Contact')) + ')</span>' +
                '</div>' +
            '</div></div>';
    }).join('');
    
    var gridAll = document.getElementById('gridSelectAll');
    if (gridAll) gridAll.checked = false;
    if (!isSalesRep) updateBulkState();
}

function renderPagination(current, total) {
    var div = document.getElementById('pagination');
    if (total <= 1) { div.innerHTML = ''; return; }
    
    var html = '';
    html += '<a href="#" class="' + (current === 1 ? 'disabled' : '') + '" onclick="if(' + current + '>1)loadLeads(' + (current - 1) + ');return false;">&laquo;</a>';
    
    if (total <= 7) {
        for (var i = 1; i <= total; i++) {
            html += '<a href="#" class="' + (i === current ? 'active' : '') + '" onclick="loadLeads(' + i + ');return false;">' + i + '</a>';
        }
    } else {
        html += '<a href="#" class="' + (1 === current ? 'active' : '') + '" onclick="loadLeads(1);return false;">1</a>';
        if (current > 3) html += '<span>...</span>';
        var start = Math.max(2, current - 1);
        var end = Math.min(total - 1, current + 1);
        for (var i = start; i <= end; i++) {
            html += '<a href="#" class="' + (i === current ? 'active' : '') + '" onclick="loadLeads(' + i + ');return false;">' + i + '</a>';
        }
        if (current < total - 2) html += '<span>...</span>';
        html += '<a href="#" class="' + (total === current ? 'active' : '') + '" onclick="loadLeads(' + total + ');return false;">' + total + '</a>';
    }
    
    html += '<a href="#" class="' + (current === total ? 'disabled' : '') + '" onclick="if(' + current + '<' + total + ')loadLeads(' + (current + 1) + ');return false;">&raquo;</a>';
    div.innerHTML = html;
}

function toggleAll(source) {
    var checkboxes = document.getElementsByClassName('lead-checkbox');
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }
    updateBulkState();
}

function updateBulkState() {
    var toolbar = document.getElementById('bulkActions');
    if (!toolbar) return;
    var checkboxes = document.getElementsByClassName('lead-checkbox');
    var selected = 0;
    for (var i = 0; i < checkboxes.length; i++) {
        if (checkboxes[i].checked) selected++;
    }
    
    if (selected > 0) {
        toolbar.style.display = 'flex';
        document.getElementById('selectedCount').textContent = selected;
    } else {
        toolbar.style.display = 'none';
        var selectAll = document.getElementById('selectAll');
        if (selectAll) selectAll.checked = false;
    }
}

function getSelectedIds() {
    var checkboxes = document.getElementsByClassName('lead-checkbox');
    var ids = [];
    for (var i = 0; i < checkboxes.length; i++) {
        if (checkboxes[i].checked) ids.push(checkboxes[i].value);
    }
    return ids;
}

function bulkAssign() {
    var ids = getSelectedIds();
    var userId = document.getElementById('bulkAssignUser').value;
    if (ids.length === 0) return;
    if (!userId) { showNotification('Please select a user to assign to.', 'error'); return; }
    
    fetch('/api/leads.php?action=bulk_assign&_cb=' + Date.now(), {
        credentials: 'same-origin',
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: '<?php echo $csrf_token; ?>', lead_ids: ids, assigned_to: userId })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            showNotification(data.message || 'Leads assigned successfully', 'success');
            loadLeads(currentPage);
        } else {
            showNotification(data.message || 'Failed to assign leads', 'error');
        }
    })
    .catch(function() { showNotification('An error occurred', 'error'); });
}

function bulkDelete() {
    var ids = getSelectedIds();
    if (ids.length === 0) return;
    
    fetch('/api/leads.php?action=bulk_delete&_cb=' + Date.now(), {
        credentials: 'same-origin',
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf_token: '<?php echo $csrf_token; ?>', lead_ids: ids })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            showNotification(data.message || 'Leads deleted successfully', 'success');
            loadLeads(currentPage);
        } else {
            showNotification(data.message || 'Failed to delete leads', 'error');
        }
    })
    .catch(function() { showNotification('An error occurred', 'error'); });
}

function getStatusClass(status) {
    var classes = {
        'New Lead': 'bg-blue-100 text-blue-800',
        'Contacted': 'bg-indigo-100 text-indigo-800',
        'Interested': 'bg-green-100 text-green-800',
        'Won': 'bg-green-500 text-white',
        'Lost': 'bg-gray-500 text-white'
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
}

function getPriorityClass(priority) {
    var classes = {
        'Low': 'bg-gray-100 text-gray-800',
        'Medium': 'bg-blue-100 text-blue-800',
        'High': 'bg-orange-100 text-orange-800',
        'Urgent': 'bg-red-500 text-white'
    };
    return classes[priority] || 'bg-gray-100 text-gray-800';
}

// Form modal handlers removed in favor of standalone lead-form.php

// ── Move Lead to Contact ──
function moveLeadToContact(leadId, e) {
    if (e) e.stopPropagation();
    showConfirm('Convert this lead to a Contact? The lead status will be set to "Won".', function() {
        fetch('/api/leads.php?action=move_to_contact', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ lead_id: leadId, csrf_token: CSRF_TOKEN })
        })
        .then(function(r) { return r.json(); })
        .then(function(resp) {
            if (resp.success) {
                showNotification('Lead converted to contact successfully!', 'success');
                loadLeads();
            } else {
                showNotification(resp.message || 'Error converting lead', 'error');
            }
        })
        .catch(function() { showNotification('Network error', 'error'); });
    });
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    if (isNaN(date.getTime())) return dateStr;
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
