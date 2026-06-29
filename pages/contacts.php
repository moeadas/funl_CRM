<?php
/**
 * White Label CRM - Contacts & Accounts
 */
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$userId = getCurrentUserId();
$companyId = $_SESSION["company_id"] ?? null;
$userRole = $_SESSION["role"] ?? "";

$pageTitle = __('Contacts');
$js = ['contacts'];

require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$accounts = $db->query("SELECT account_id, account_name FROM accounts WHERE company_id = ? ORDER BY account_name", [$companyId])->fetchAll();
$users = $db->query("SELECT user_id, full_name FROM users WHERE company_id = ? AND status = 'Active' ORDER BY full_name", [$companyId])->fetchAll();
$tags = $db->query("SELECT * FROM contact_tags WHERE company_id = ? ORDER BY tag_name", [$companyId])->fetchAll();
?>

<div class="page-header">
    <h1 class="page-title"><?php echo htmlspecialchars(__('Contacts & Accounts')); ?></h1>
    <div class="header-actions">
        <a href="/pages/account-form.php" class="btn btn-outline">+ <?php echo htmlspecialchars(__('New Account')); ?></a>
        <a href="/pages/contact-form.php" class="btn btn-primary">+ <?php echo htmlspecialchars(__('New Contact')); ?></a>
    </div>
</div>

<div class="tab-nav">
    <button class="tab-btn active" data-tab="contacts" onclick="switchTab('contacts')"><?php echo htmlspecialchars(__('Contacts')); ?></button>
    <button class="tab-btn" data-tab="accounts" onclick="switchTab('accounts')"><?php echo htmlspecialchars(__('Accounts')); ?></button>
</div>

<!-- Contacts Tab -->
<div id="tab-contacts" class="tab-content">
    <div class="filters-bar">
        <input type="text" id="contact-search" class="search-input" placeholder="<?php echo htmlspecialchars(__('Search contacts...')); ?>" oninput="loadContacts()">
        <select id="contact-account-filter" class="filter-select" onchange="loadContacts()">
        <option value=""><?php echo htmlspecialchars(__('All Accounts')); ?></option>
        <?php foreach ($accounts as $a): ?>
        <option value="<?= $a['account_id'] ?>"><?= htmlspecialchars($a['account_name']) ?></option>
        <?php endforeach; ?>
        </select>
        <select id="contact-status-filter" class="filter-select" onchange="loadContacts()">
        <option value=""><?php echo htmlspecialchars(__('All Status')); ?></option>
        <option value="Active"><?php echo htmlspecialchars(__('Active')); ?></option>
        <option value="Inactive"><?php echo htmlspecialchars(__('Inactive')); ?></option>
        <option value="Do Not Contact"><?php echo htmlspecialchars(__('Do Not Contact')); ?></option>
        </select>
    </div>
    <div class="data-table-wrap">
        <table class="data-table">
        <thead>
        <tr>
        <th><?php echo htmlspecialchars(__('Name')); ?></th>
        <th><?php echo htmlspecialchars(__('Account')); ?></th>
        <th><?php echo htmlspecialchars(__('Email')); ?></th>
        <th><?php echo htmlspecialchars(__('Phone')); ?></th>
        <th><?php echo htmlspecialchars(__('Tags')); ?></th>
        <th><?php echo htmlspecialchars(__('Status')); ?></th>
        <th></th>
        </tr>
        </thead>
        <tbody id="contacts-tbody">
        <tr><td colspan="7" class="empty-state"><?php echo htmlspecialchars(__('Loading...')); ?></td></tr>
        </tbody>
        </table>
    </div>
</div>

<!-- Accounts Tab -->
<div id="tab-accounts" class="tab-content" style="display:none;">
    <div class="filters-bar">
        <input type="text" id="account-search" class="search-input" placeholder="<?php echo htmlspecialchars(__('Search accounts...')); ?>" oninput="loadAccounts()">
        <select id="account-type-filter" class="filter-select" onchange="loadAccounts()">
        <option value=""><?php echo htmlspecialchars(__('All Types')); ?></option>
        <option value="Customer"><?php echo htmlspecialchars(__('Customer')); ?></option>
        <option value="Prospect"><?php echo htmlspecialchars(__('Prospect')); ?></option>
        <option value="Partner"><?php echo htmlspecialchars(__('Partner')); ?></option>
        <option value="Vendor"><?php echo htmlspecialchars(__('Vendor')); ?></option>
        <option value="Other"><?php echo htmlspecialchars(__('Other')); ?></option>
        </select>
    </div>
    <div class="accounts-grid" id="accounts-grid">
        <div class="empty-state"><?php echo htmlspecialchars(__('Loading...')); ?></div>
    </div>
</div>

<script>
const COMPANY_ID = <?= json_encode($companyId) ?>;
const USER_ROLE = <?= json_encode($userRole) ?>;
const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
const API = '/api/contacts.php';

let contacts = [];
let accounts = [];

document.addEventListener('DOMContentLoaded', () => {
loadContacts();
loadAccounts();

// Check if URL has tab hash or parameter
const urlParams = new URLSearchParams(window.location.search);
const tab = urlParams.get('tab');
if (tab === 'accounts') {
    switchTab('accounts');
}
});

function switchTab(tab) {
document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
document.querySelectorAll('.tab-content').forEach(t => t.style.display = 'none');
document.querySelector(`.tab-btn[data-tab="${tab}"]`).classList.add('active');
document.getElementById(`tab-${tab}`).style.display = 'block';
}

// ── Contacts ────────────────────────────────────────────────

function loadContacts() {
const search = document.getElementById('contact-search')?.value || '';
const accountId = document.getElementById('contact-account-filter')?.value || '';
const status = document.getElementById('contact-status-filter')?.value || '';

let url = `${API}?action=list_contacts`;
if (search) url += `&search=${encodeURIComponent(search)}`;
if (accountId) url += `&account_id=${accountId}`;
if (status) url += `&status=${status}`;

fetch(url, { credentials: 'same-origin' })
    .then(r => r.json())
    .then(resp => {
        if (resp.success) {
        contacts = resp.data || [];
        renderContacts();
        }
    });
}

function renderContacts() {
const tbody = document.getElementById('contacts-tbody');
if (!contacts.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="empty-state"><h3>' + escapeHtml(__('No contacts yet')) + '</h3>' + escapeHtml(__('Click "+ New Contact" to add your first contact.')) + '</td></tr>';
    return;
}
tbody.innerHTML = contacts.map(c => {
    const tags = c.tags ? c.tags.map(t => {
        const col = safeColor(t.tag_color);
        return `<span class="tag" style="background:${col}20;color:${col}">${escapeHtml(t.tag_name)}</span>`;
    }).join('') : '';
    const statusClass = 'status-' + (c.contact_status || 'active').toLowerCase().replace(' ', '-');
    return `<tr onclick="window.location.href='/pages/contact-detail.php?id=${c.contact_id}'" style="cursor:pointer">
        <td>
        <div class="contact-name-cell">${escapeHtml(c.first_name)} ${escapeHtml(c.last_name)}</div>
        ${c.title ? `<div style="font-size:12px;color:#9ca3af">${escapeHtml(c.title)}</div>` : ''}
        </td>
        <td>${c.account_name ? `<span class="account-badge">${escapeHtml(c.account_name)}</span>` : '-'}</td>
        <td class="contact-email">${c.email ? `<a href="mailto:${escapeHtml(c.email)}" onclick="event.stopPropagation()">${escapeHtml(c.email)}</a>` : '-'}</td>
        <td>${c.phone || '-'}</td>
        <td>${tags || '-'}</td>
        <td><span class="status-badge ${statusClass}">${escapeHtml(__(c.contact_status || 'Active'))}</span></td>
        <td><div class="action-btns" onclick="event.stopPropagation()">
        <button onclick="window.location.href='/pages/contact-form.php?id=${c.contact_id}'" title="${escapeHtml(__('Edit'))}">✏️</button>
        <button onclick="deleteContact(${c.contact_id})" title="${escapeHtml(__('Delete'))}">🗑️</button>
        </div></td>
    </tr>`;
}).join('');
}

function deleteContact(contactId) {
showConfirm(__('Delete this contact?'), function() {
    fetch(`${API}?action=delete_contact`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ contact_id: contactId, csrf_token: CSRF_TOKEN })
    }).then(r => r.json()).then(resp => {
        if (resp.success) {
        loadContacts();
        showNotification(__('Contact deleted'), 'success');
        } else {
        showNotification(resp.message || __('Failed to delete contact'), 'error');
        }
    });
});
}

// ── Accounts ────────────────────────────────────────────────

function loadAccounts() {
const search = document.getElementById('account-search')?.value || '';
const type = document.getElementById('account-type-filter')?.value || '';

let url = `${API}?action=list_accounts`;
if (search) url += `&search=${encodeURIComponent(search)}`;
if (type) url += `&type=${type}`;

fetch(url, { credentials: 'same-origin' })
    .then(r => r.json())
    .then(resp => {
        if (resp.success) {
        accounts = resp.data || [];
        renderAccounts();
        }
    });
}

function renderAccounts() {
const grid = document.getElementById('accounts-grid');
if (!accounts.length) {
    grid.innerHTML = '<div class="empty-state"><h3>' + escapeHtml(__('No accounts yet')) + '</h3>' + escapeHtml(__('Click "+ New Account" to add your first account.')) + '</div>';
    return;
}
grid.innerHTML = accounts.map(a => `
    <div class="account-card" onclick="window.location.href='/pages/account-form.php?id=${a.account_id}'">
        <button class="card-delete-btn" onclick="event.stopPropagation(); deleteAccount(${a.account_id})" title="${escapeHtml(__('Delete Account'))}">🗑️</button>
        <div class="account-card-header">
        <div class="account-name">${escapeHtml(a.account_name)}</div>
        <div class="account-type">${escapeHtml(__(a.account_type || 'Customer'))}</div>
        </div>
        <div class="account-meta">
        ${a.industry ? `<div class="account-meta-row">🏢 ${escapeHtml(a.industry)}</div>` : ''}
        ${a.phone ? `<div class="account-meta-row">📞 ${escapeHtml(a.phone)}</div>` : ''}
        ${a.city || a.country ? `<div class="account-meta-row">📍 ${escapeHtml([a.city, a.country].filter(Boolean).join(', '))}</div>` : ''}
        <div class="account-meta-row">👥 ${a.contact_count || 0} ${escapeHtml(a.contact_count === 1 ? __('contact') : __('contacts'))}</div>
        </div>
    </div>
`).join('');
}

function deleteAccount(accountId) {
showConfirm(__('delete_account_confirm'), function() {
    fetch(`${API}?action=delete_account`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ account_id: accountId, csrf_token: CSRF_TOKEN })
    }).then(r => r.json()).then(resp => {
        if (resp.success) {
        loadAccounts();
        showNotification(__('Account deleted'), 'success');
        } else {
        showNotification(resp.message || __('Failed to delete account'), 'error');
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
// M-3 fix: only allow valid hex colors into inline style; neutralize anything
// else (defends against CSS/style injection from legacy/invalid stored values).
function safeColor(c) {
    return /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(c || '') ? c : '#6b7280';
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
