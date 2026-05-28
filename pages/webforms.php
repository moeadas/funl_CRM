<?php
/**
* White Label CRM - Web Forms Page
* FIXED: Removed getCurrentCompanyId() call before auth.php include
*/
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

// Get company info AFTER auth is loaded
$db = Database::getInstance();
$companyId = getCurrentCompanyId();

$pageTitle = 'Web Forms';
$currentPage = 'webforms';
require_once __DIR__ . '/../includes/header.php';

$crmFields = [
    ['name' => 'company_name', 'label' => 'Company Name', 'type' => 'text'],
    ['name' => 'contact_name', 'label' => 'Contact Name', 'type' => 'text'],
    ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
    ['name' => 'phone', 'label' => 'Phone', 'type' => 'tel'],
    ['name' => 'source', 'label' => 'Lead Source', 'type' => 'text'],
    ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
    ['name' => 'address', 'label' => 'Address', 'type' => 'text'],
    ['name' => 'city', 'label' => 'City', 'type' => 'text'],
    ['name' => 'country', 'label' => 'Country', 'type' => 'text'],
    ['name' => 'industry', 'label' => 'Industry', 'type' => 'text'],
    ['name' => 'budget', 'label' => 'Budget', 'type' => 'number'],
    ['name' => 'website', 'label' => 'Website', 'type' => 'url'],
];
?>

<div class="page-header">
    <div>
        <h1>Web Forms</h1>
        <p class="text-muted">Create embedded forms that feed directly into your CRM</p>
    </div>
    <div class="header-actions">
        <button class="btn btn-primary btn-sm" onclick="openFormModal()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Form
        </button>
    </div>
</div>

<div class="card">
    <div id="formsList" style="padding:20px;">
        <div class="text-center text-muted" style="padding:40px;">Loading...</div>
    </div>
</div>

<!-- Form Modal -->
<div class="modal-overlay" id="form-modal">
    <div class="modal modal-md">
        <div class="modal-header">
            <h2 id="form-modal-title">New Web Form</h2>
            <button class="modal-close" onclick="closeModal('form-modal')">&times;</button>
        </div>
        <form id="form-builder" onsubmit="saveForm(event)">
            <div class="modal-body">
                <input type="hidden" id="form-id" value="">
                <div class="form-group">
                    <label class="form-label">Form Name *</label>
                    <input type="text" id="form-name" class="form-control" required placeholder="e.g., Contact Us Form">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <input type="text" id="form-description" class="form-control" placeholder="What this form is for">
                </div>
                <div style="margin:24px 0 16px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                        <h3 style="margin:0;font-size:15px;font-weight:600;">Form Fields</h3>
                        <button type="button" class="btn btn-outline btn-sm" onclick="addFieldRow()">+ Add Field</button>
                    </div>
                    <div id="fields-container" style="max-height:300px;overflow-y:auto;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('form-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="save-btn">Create Form</button>
            </div>
        </form>
    </div>
</div>

<script>
const CSRF_TOKEN = "<?= $_SESSION['csrf_token'] ?? '' ?>";
const CRM_FIELDS = <?= json_encode($crmFields) ?>;
let currentFields = [];
let fieldCount = 0;

function loadForms() {
    fetch('/api/webforms.php?action=list')
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('formsList').innerHTML = '<div class="text-center text-muted" style="padding:40px;">Error: ' + (data.message || 'Failed to load') + '</div>';
                return;
            }
            renderForms(data.forms || []);
        })
        .catch(() => {
            document.getElementById('formsList').innerHTML = '<div class="text-center text-muted" style="padding:40px;">Error loading forms</div>';
        });
}

function renderForms(forms) {
    const container = document.getElementById('formsList');
    if (!forms || forms.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted" style="padding:60px 40px;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:16px;opacity:.4;">
                    <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                    <line x1="8" y1="12" x2="16" y2="12"></line>
                    <line x1="12" y1="8" x2="12" y2="16"></line>
                </svg>
                <h3 style="margin:0 0 8px;font-size:18px;">No forms yet</h3>
                <p>Create your first web form to capture leads directly from your website.</p>
                <button class="btn btn-primary" onclick="openFormModal()" style="margin-top:16px;">Create Form</button>
            </div>`;
        return;
    }
    
    container.innerHTML = `
        <table class="table">
            <thead><tr><th>Form Name</th><th>Fields</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>${forms.map(f => `
                <tr>
                    <td><strong>${escapeHtml(f.form_name || '')}</strong><div class="text-muted" style="font-size:13px;">${escapeHtml(f.description || '')}</div></td>
                    <td>${f.field_count || 0}</td>
                    <td><span class="badge badge-${f.status === 'active' ? 'success' : 'secondary'}">${f.status || 'active'}</span></td>
                    <td>
                        <button class="btn btn-sm btn-outline" onclick="editForm(${f.form_id})">Edit</button>
                        <button class="btn btn-sm btn-danger-outline" onclick="deleteForm(${f.form_id})">Delete</button>
                    </td>
                </tr>`).join('')}</tbody>
        </table>`;
}

function addFieldRow(data) {
    const container = document.getElementById('fields-container');
    const idx = fieldCount++;
    
    const options = CRM_FIELDS.map(f => 
        `<option value="${f.name}" ${data && data.crm_field === f.name ? 'selected' : ''}>${f.label}</option>`
    ).join('');
    
    const div = document.createElement('div');
    div.className = 'field-row';
    div.style.cssText = 'display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:8px;align-items:end;padding:8px;border:1px solid #e5e7eb;border-radius:6px;margin-bottom:8px;';
    div.innerHTML = `
        <div class="form-group" style="margin:0;">
            <label style="font-size:11px;color:#6b7280;">Label *</label>
            <input type="text" class="form-control field-label" value="${data ? escapeHtml(data.field_label || '') : ''}" placeholder="Field label" required style="font-size:13px;">
        </div>
        <div class="form-group" style="margin:0;">
            <label style="font-size:11px;color:#6b7280;">CRM Field *</label>
            <select class="form-control field-crm" required style="font-size:13px;"><option value="">Select...</option>${options}</select>
        </div>
        <div class="form-group" style="margin:0;">
            <label style="font-size:11px;color:#6b7280;">Type</label>
            <select class="form-control field-type" style="font-size:13px;">
                <option value="text" ${data && data.field_type === 'text' ? 'selected' : ''}>Text</option>
                <option value="email" ${data && data.field_type === 'email' ? 'selected' : ''}>Email</option>
                <option value="tel" ${data && data.field_type === 'tel' ? 'selected' : ''}>Phone</option>
                <option value="textarea" ${data && data.field_type === 'textarea' ? 'selected' : ''}>Textarea</option>
                <option value="number" ${data && data.field_type === 'number' ? 'selected' : ''}>Number</option>
            </select>
        </div>
        <button type="button" class="btn btn-sm btn-danger-outline" onclick="this.closest('.field-row').remove()" style="margin-bottom:2px;">×</button>`;
    container.appendChild(div);
}

function openFormModal() {
    document.getElementById('form-builder').reset();
    document.getElementById('form-id').value = '';
    document.getElementById('form-modal-title').textContent = 'New Web Form';
    document.getElementById('save-btn').textContent = 'Create Form';
    document.getElementById('fields-container').innerHTML = '';
    fieldCount = 0;
    addFieldRow();
    addFieldRow();
    addFieldRow();
    openModal('form-modal');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
    document.body.style.overflow = '';
}

function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function saveForm(e) {
    e.preventDefault();
    
    const fields = [];
    document.querySelectorAll('#fields-container > div').forEach(row => {
        fields.push({
            label: row.querySelector('.field-label').value,
            crm_field: row.querySelector('.field-crm').value,
            type: row.querySelector('.field-type').value
        });
    });
    
    if (fields.length === 0) {
        showNotification('Add at least one field', 'error');
        return;
    }
    
    const payload = {
        csrf_token: CSRF_TOKEN,
        form_id: document.getElementById('form-id').value,
        form_name: document.getElementById('form-name').value,
        description: document.getElementById('form-description').value,
        fields: fields
    };
    
    fetch('/api/webforms.php?action=save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        showNotification(data.message || (data.success ? 'Saved' : 'Error'), data.success ? 'success' : 'error');
        if (data.success) {
            closeModal('form-modal');
            loadForms();
        }
    })
    .catch(() => showNotification('Network error', 'error'));
}

function editForm(id) {
    fetch('/api/webforms.php?action=get&id=' + id)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.form) {
                showNotification('Form not found', 'error');
                return;
            }
            const f = data.form;
            document.getElementById('form-id').value = f.form_id;
            document.getElementById('form-name').value = f.form_name || '';
            document.getElementById('form-description').value = f.description || '';
            document.getElementById('form-modal-title').textContent = 'Edit Form';
            document.getElementById('save-btn').textContent = 'Save Changes';
            
            document.getElementById('fields-container').innerHTML = '';
            fieldCount = 0;
            (f.fields || []).forEach(field => addFieldRow(field));
            if ((f.fields || []).length === 0) {
                addFieldRow();
                addFieldRow();
                addFieldRow();
            }
            openModal('form-modal');
        });
}

function deleteForm(id) {
    if (!confirm('Delete this form?')) return;
    fetch('/api/webforms.php?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ form_id: id, csrf_token: CSRF_TOKEN })
    })
    .then(r => r.json())
    .then(data => {
        showNotification(data.message || (data.success ? 'Deleted' : 'Error'), data.success ? 'success' : 'error');
        if (data.success) loadForms();
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

loadForms();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
