<?php
/**
 * White Label CRM - Contacts & Accounts
 */
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();
requireCompany();

$userId = getCurrentUserId();
$companyId = getCurrentCompanyId();
$userRole = getCurrentUserRole();

$pageTitle = 'Contacts';
$js = ['contacts'];

require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$accounts = $db->query("SELECT account_id, account_name FROM accounts WHERE company_id = ? ORDER BY account_name", [$companyId])->fetchAll();
$users = $db->query("SELECT user_id, full_name FROM users WHERE company_id = ? AND status = 'Active' ORDER BY full_name", [$companyId])->fetchAll();
$tags = $db->query("SELECT * FROM contact_tags WHERE company_id = ? ORDER BY tag_name", [$companyId])->fetchAll();
$companies = $db->query("SELECT DISTINCT country FROM leads WHERE company_id = ? AND country IS NOT NULL AND country != '' ORDER BY country LIMIT 50", [$companyId])->fetchAll();
?>

<style>
.contacts-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px 40px;
}
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 24px 0 20px;
    gap: 16px;
}
.page-header h1 {
    font-size: 22px;
    font-weight: 600;
    margin: 0;
}
.header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

/* Tab Navigation */
.tab-nav {
    display: flex;
    gap: 4px;
    border-bottom: 1px solid var(--border, #e5e7eb);
    margin-bottom: 20px;
}
.tab-btn {
    padding: 10px 20px;
    border: none;
    background: none;
    font-size: 14px;
    font-weight: 500;
    color: var(--text-secondary, #6b7280);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
    transition: color 0.15s;
}
.tab-btn:hover { color: var(--text-primary, #1f2937); }
.tab-btn.active {
    color: var(--primary, #2563eb);
    border-bottom-color: var(--primary, #2563eb);
}

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
.btn-outline {
    background: white;
    border: 1px solid var(--border, #d1d5db);
    color: var(--text-primary, #374151);
}
.btn-outline:hover { background: var(--bg-secondary, #f3f4f6); }

/* Table */
.data-table-wrap {
    background: white;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 10px;
    overflow: hidden;
}
table.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
table.data-table th {
    background: var(--bg-secondary, #f9fafb);
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    color: var(--text-secondary, #6b7280);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-bottom: 1px solid var(--border, #e5e7eb);
}
table.data-table td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border, #e5e7eb);
    color: var(--text-primary, #1f2937);
}
table.data-table tr:last-child td { border-bottom: none; }
table.data-table tr:hover { background: var(--bg-secondary, #f9fafb); }

.contact-name {
    font-weight: 500;
    color: var(--text-primary, #1f2937);
}
.contact-email {
    font-size: 12px;
    color: var(--text-secondary, #6b7280);
}
.account-badge {
    display: inline-block;
    padding: 2px 8px;
    background: var(--bg-secondary, #f3f4f6);
    border-radius: 4px;
    font-size: 12px;
    color: var(--text-secondary, #6b7280);
}
.status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}
.status-active { background: #dcfce7; color: #15803d; }
.status-inactive { background: #f3f4f6; color: #6b7280; }
.status-do-not-contact { background: #fee2e2; color: #dc2626; }
.status-prospect { background: #fef3c7; color: #d97706; }

.tag {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    margin: 1px;
}

.action-btns {
    display: flex;
    gap: 4px;
}
.action-btns button {
    background: none;
    border: none;
    padding: 6px 8px;
    cursor: pointer;
    font-size: 14px;
    border-radius: 4px;
    color: var(--text-secondary, #6b7280);
}
.action-btns button:hover { background: var(--bg-secondary, #f3f4f6); color: var(--text-primary, #1f2937); }

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary, #9ca3af);
}
.empty-state h3 { font-size: 16px; margin: 0 0 8px; color: var(--text-primary, #374151); }

/* Account Card Grid */
.accounts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
    margin-top: 20px;
}
.account-card {
    background: white;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 10px;
    padding: 20px;
    cursor: pointer;
    transition: box-shadow 0.15s, transform 0.15s;
}
.account-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transform: translateY(-1px);
}
.account-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}
.account-name {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-primary, #1f2937);
}
.account-type {
    font-size: 11px;
    padding: 3px 8px;
    background: var(--primary, #2563eb);
    color: white;
    border-radius: 4px;
    font-weight: 500;
}
.account-meta {
    font-size: 13px;
    color: var(--text-secondary, #6b7280);
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.account-meta-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Modal */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    display: none;
}
.modal-overlay.active { display: flex; }
.modal {
    background: white;
    border-radius: 12px;
    width: 560px;
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

/* Tag selector in modal */
.tag-selector {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 8px;
    border: 1px solid var(--border, #d1d5db);
    border-radius: 6px;
    min-height: 42px;
}
.tag-option {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    cursor: pointer;
    border: 1px solid transparent;
}
.tag-option:hover { background: var(--bg-secondary, #f3f4f6); }
.tag-option.selected { border-color: currentColor; opacity: 0.8; }
.tag-option input { display: none; }

/* Account Detail View */
.account-detail {
    display: none;
    background: white;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 10px;
    padding: 24px;
    margin-top: 16px;
}
.account-detail.active { display: block; }
.account-detail-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}
.account-detail-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--text-primary, #1f2937);
}
.account-detail-meta {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.meta-item { display: flex; flex-direction: column; gap: 4px; }
.meta-label { font-size: 11px; color: var(--text-secondary, #6b7280); text-transform: uppercase; letter-spacing: 0.04em; }
.meta-value { font-size: 14px; color: var(--text-primary, #1f2937); font-weight: 500; }
.account-contacts-list {
    border-top: 1px solid var(--border, #e5e7eb);
    padding-top: 16px;
}
.account-contacts-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-secondary, #6b7280);
    margin-bottom: 12px;
}
</style>

<div class="contacts-page">
    <div class="page-header">
        <h1>Contacts & Accounts</h1>
        <div class="header-actions">
            <button class="btn btn-outline" onclick="showAccountForm()">+ New Account</button>
            <button class="btn btn-primary" onclick="showContactForm()">+ New Contact</button>
        </div>
    </div>

    <div class="tab-nav">
        <button class="tab-btn active" data-tab="contacts" onclick="switchTab('contacts')">Contacts</button>
        <button class="tab-btn" data-tab="accounts" onclick="switchTab('accounts')">Accounts</button>
    </div>

    <!-- Contacts Tab -->
    <div id="tab-contacts" class="tab-content">
        <div class="filters-bar">
            <input type="text" id="contact-search" class="search-input" placeholder="Search contacts..." oninput="loadContacts()">
            <select id="contact-account-filter" class="filter-select" onchange="loadContacts()">
                <option value="">All Accounts</option>
                <?php foreach ($accounts as $a): ?>
                    <option value="<?= $a['account_id'] ?>"><?= htmlspecialchars($a['account_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="contact-status-filter" class="filter-select" onchange="loadContacts()">
                <option value="">All Status</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
                <option value="Do Not Contact">Do Not Contact</option>
            </select>
        </div>
        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Account</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Tags</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="contacts-tbody">
                    <tr><td colspan="7" class="empty-state">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Accounts Tab -->
    <div id="tab-accounts" class="tab-content" style="display:none;">
        <div class="filters-bar">
            <input type="text" id="account-search" class="search-input" placeholder="Search accounts..." oninput="loadAccounts()">
            <select id="account-type-filter" class="filter-select" onchange="loadAccounts()">
                <option value="">All Types</option>
                <option value="Customer">Customer</option>
                <option value="Prospect">Prospect</option>
                <option value="Partner">Partner</option>
                <option value="Vendor">Vendor</option>
                <option value="Other">Other</option>
            </select>
        </div>
        <div class="accounts-grid" id="accounts-grid">
            <div class="empty-state">Loading...</div>
        </div>
    </div>
</div>

<!-- Contact Form Modal -->
<div class="modal-overlay" id="contact-modal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="contact-modal-title">New Contact</h2>
            <button class="modal-close" onclick="closeContactModal()">&times;</button>
        </div>
        <form id="contact-form" onsubmit="saveContact(event)">
            <div class="modal-body">
                <input type="hidden" id="contact-id" value="">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" id="contact-first-name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" id="contact-last-name" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" id="contact-email" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" id="contact-phone" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Mobile</label>
                        <input type="text" id="contact-mobile" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Title / Position</label>
                        <input type="text" id="contact-title" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Account</label>
                        <select id="contact-account-id" class="form-control">
                            <option value="">No Account</option>
                            <?php foreach ($accounts as $a): ?>
                                <option value="<?= $a['account_id'] ?>"><?= htmlspecialchars($a['account_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select id="contact-status" class="form-control">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="Do Not Contact">Do Not Contact</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Tags</label>
                    <div class="tag-selector" id="tag-selector">
                        <?php foreach ($tags as $t): ?>
                            <label class="tag-option" style="background:<?= htmlspecialchars($t['tag_color']) ?>20;color:<?= htmlspecialchars($t['tag_color']) ?>">
                                <input type="checkbox" value="<?= $t['tag_id'] ?>" name="tags">
                                <?= htmlspecialchars($t['tag_name']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea id="contact-notes" class="form-control"></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeContactModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="contact-save-btn">Create Contact</button>
            </div>
        </form>
    </div>
</div>

<!-- Account Form Modal -->
<div class="modal-overlay" id="account-modal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="account-modal-title">New Account</h2>
            <button class="modal-close" onclick="closeAccountModal()">&times;</button>
        </div>
        <form id="account-form" onsubmit="saveAccount(event)">
            <div class="modal-body">
                <input type="hidden" id="account-id" value="">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Account Name *</label>
                        <input type="text" id="account-name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Type</label>
                        <select id="account-type" class="form-control">
                            <option value="Customer">Customer</option>
                            <option value="Prospect">Prospect</option>
                            <option value="Partner">Partner</option>
                            <option value="Vendor">Vendor</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Industry</label>
                        <input type="text" id="account-industry" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Website</label>
                        <input type="text" id="account-website" class="form-control" placeholder="https://...">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" id="account-phone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Country</label>
                        <select id="account-country" class="form-control">
                            <option value="">Select country</option>
                            <?php foreach ($companies as $c): ?>
                                <option value="<?= htmlspecialchars($c['country']) ?>"><?= htmlspecialchars($c['country']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea id="account-address" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea id="account-description" class="form-control"></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeAccountModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="account-save-btn">Create Account</button>
            </div>
        </form>
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
        tbody.innerHTML = '<tr><td colspan="7" class="empty-state"><h3>No contacts yet</h3>Click "+ New Contact" to add your first contact.</td></tr>';
        return;
    }
    tbody.innerHTML = contacts.map(c => {
        const tags = c.tags ? c.tags.map(t => 
            `<span class="tag" style="background:${t.tag_color}20;color:${t.tag_color}">${escapeHtml(t.tag_name)}</span>`
        ).join('') : '';
        const statusClass = 'status-' + (c.contact_status || 'active').toLowerCase().replace(' ', '-');
        return `<tr onclick="editContact(${c.contact_id})" style="cursor:pointer">
            <td>
                <div class="contact-name">${escapeHtml(c.first_name)} ${escapeHtml(c.last_name)}</div>
                ${c.title ? `<div style="font-size:12px;color:#9ca3af">${escapeHtml(c.title)}</div>` : ''}
            </td>
            <td>${c.account_name ? `<span class="account-badge">${escapeHtml(c.account_name)}</span>` : '-'}</td>
            <td class="contact-email">${c.email ? `<a href="mailto:${escapeHtml(c.email)}">${escapeHtml(c.email)}</a>` : '-'}</td>
            <td>${c.phone || '-'}</td>
            <td>${tags || '-'}</td>
            <td><span class="status-badge ${statusClass}">${c.contact_status || 'Active'}</span></td>
            <td><div class="action-btns" onclick="event.stopPropagation()">
                <button onclick="editContact(${c.contact_id})" title="Edit">✏️</button>
                <button onclick="deleteContact(${c.contact_id})" title="Delete">🗑️</button>
            </div></td>
        </tr>`;
    }).join('');
}

function showContactForm(contactId) {
    const form = document.getElementById('contact-form');
    form.reset();
    document.getElementById('contact-id').value = '';
    document.getElementById('contact-modal-title').textContent = 'New Contact';
    document.getElementById('contact-save-btn').textContent = 'Create Contact';
    document.getElementById('tag-selector').querySelectorAll('input').forEach(i => i.checked = false);
    if (!contactId) document.getElementById('contact-modal').classList.add('active');
}

function editContact(contactId) {
    fetch(`${API}?action=get_contact&contact_id=${contactId}`, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(resp => {
            if (!resp.success) { showNotification(resp.message, 'error'); return; }
            const c = resp.data;
            document.getElementById('contact-id').value = c.contact_id;
            document.getElementById('contact-first-name').value = c.first_name || '';
            document.getElementById('contact-last-name').value = c.last_name || '';
            document.getElementById('contact-email').value = c.email || '';
            document.getElementById('contact-phone').value = c.phone || '';
            document.getElementById('contact-mobile').value = c.mobile || '';
            document.getElementById('contact-title').value = c.title || '';
            document.getElementById('contact-account-id').value = c.account_id || '';
            document.getElementById('contact-status').value = c.contact_status || 'Active';
            document.getElementById('contact-notes').value = c.notes || '';
            
            document.querySelectorAll('#tag-selector input').forEach(i => i.checked = false);
            if (c.tags) c.tags.forEach(t => {
                const cb = document.querySelector(`#tag-selector input[value="${t.tag_id}"]`);
                if (cb) cb.checked = true;
            });
            
            document.getElementById('contact-modal-title').textContent = 'Edit Contact';
            document.getElementById('contact-save-btn').textContent = 'Save Changes';
            document.getElementById('contact-modal').classList.add('active');
        });
}

function closeContactModal() {
    document.getElementById('contact-modal').classList.remove('active');
}

function saveContact(e) {
    e.preventDefault();
    const contactId = document.getElementById('contact-id').value;
    const action = contactId ? 'update_contact' : 'create_contact';
    
    const tagIds = [...document.querySelectorAll('#tag-selector input:checked')].map(i => parseInt(i.value));
    
    const data = {
        csrf_token: CSRF_TOKEN,
        first_name: document.getElementById('contact-first-name').value,
        last_name: document.getElementById('contact-last-name').value,
        email: document.getElementById('contact-email').value,
        phone: document.getElementById('contact-phone').value,
        mobile: document.getElementById('contact-mobile').value,
        title: document.getElementById('contact-title').value,
        account_id: document.getElementById('contact-account-id').value || null,
        contact_status: document.getElementById('contact-status').value,
        notes: document.getElementById('contact-notes').value,
        tag_ids: tagIds,
    };
    if (contactId) data.contact_id = contactId;
    
    fetch(`${API}?action=${action}`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    }).then(r => r.json()).then(resp => {
        if (resp.success) {
            closeContactModal();
            loadContacts();
            showNotification(contactId ? 'Contact updated' : 'Contact created', 'success');
        } else {
            showNotification(resp.message || 'Failed to save', 'error');
        }
    });
}

function deleteContact(contactId) {
    if (!confirm('Delete this contact?')) return;
    fetch(`${API}?action=delete_contact`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ contact_id: contactId, csrf_token: CSRF_TOKEN })
    }).then(r => r.json()).then(resp => {
        if (resp.success) {
            loadContacts();
            showNotification('Contact deleted', 'success');
        } else {
            showNotification(resp.message || 'Failed to delete', 'error');
        }
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
        grid.innerHTML = '<div class="empty-state"><h3>No accounts yet</h3>Click "+ New Account" to add your first account.</div>';
        return;
    }
    grid.innerHTML = accounts.map(a => `
        <div class="account-card" onclick="showAccountDetail(${a.account_id})">
            <div class="account-card-header">
                <div class="account-name">${escapeHtml(a.account_name)}</div>
                <div class="account-type">${a.account_type || 'Customer'}</div>
            </div>
            <div class="account-meta">
                ${a.industry ? `<div class="account-meta-row">🏢 ${escapeHtml(a.industry)}</div>` : ''}
                ${a.phone ? `<div class="account-meta-row">📞 ${escapeHtml(a.phone)}</div>` : ''}
                ${a.city || a.country ? `<div class="account-meta-row">📍 ${escapeHtml([a.city, a.country].filter(Boolean).join(', '))}</div>` : ''}
                <div class="account-meta-row">👥 ${a.contact_count || 0} contact${a.contact_count !== '1' ? 's' : ''}</div>
            </div>
        </div>
    `).join('');
}

function showAccountForm(accountId) {
    const form = document.getElementById('account-form');
    form.reset();
    document.getElementById('account-id').value = '';
    document.getElementById('account-modal-title').textContent = 'New Account';
    document.getElementById('account-save-btn').textContent = 'Create Account';
    if (!accountId) document.getElementById('account-modal').classList.add('active');
}

function showAccountDetail(accountId) {
    fetch(`${API}?action=get_account&account_id=${accountId}`, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(resp => {
            if (!resp.success) { showNotification(resp.message, 'error'); return; }
            const a = resp.data;
            document.getElementById('account-id').value = a.account_id;
            document.getElementById('account-name').value = a.account_name || '';
            document.getElementById('account-type').value = a.account_type || 'Customer';
            document.getElementById('account-industry').value = a.industry || '';
            document.getElementById('account-website').value = a.website || '';
            document.getElementById('account-phone').value = a.phone || '';
            document.getElementById('account-country').value = a.country || '';
            document.getElementById('account-address').value = a.address || '';
            document.getElementById('account-description').value = a.description || '';
            document.getElementById('account-modal-title').textContent = 'Edit Account';
            document.getElementById('account-save-btn').textContent = 'Save Changes';
            document.getElementById('account-modal').classList.add('active');
        });
}

function closeAccountModal() {
    document.getElementById('account-modal').classList.remove('active');
}

function saveAccount(e) {
    e.preventDefault();
    const accountId = document.getElementById('account-id').value;
    const action = accountId ? 'update_account' : 'create_account';
    
    const data = {
        csrf_token: CSRF_TOKEN,
        account_name: document.getElementById('account-name').value,
        account_type: document.getElementById('account-type').value,
        industry: document.getElementById('account-industry').value,
        website: document.getElementById('account-website').value,
        phone: document.getElementById('account-phone').value,
        country: document.getElementById('account-country').value,
        address: document.getElementById('account-address').value,
        description: document.getElementById('account-description').value,
    };
    if (accountId) data.account_id = accountId;
    
    fetch(`${API}?action=${action}`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    }).then(r => r.json()).then(resp => {
        if (resp.success) {
            closeAccountModal();
            loadAccounts();
            showNotification(accountId ? 'Account updated' : 'Account created', 'success');
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

document.addEventListener('keydown', e => { 
    if (e.key === 'Escape') { 
        closeContactModal(); 
        closeAccountModal(); 
    } 
});
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) { closeContactModal(); closeAccountModal(); } });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
