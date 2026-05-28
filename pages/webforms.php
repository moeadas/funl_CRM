<?php
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

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
?\u003e

<div class="page-header">
    <div>
        <h1>Web Forms</h1>
        <p class="text-muted">Create embedded forms that feed directly into your CRM</p>
    </div>
    <div class="header-actions">
        <button class="btn btn-primary btn-sm" onclick="openFormModal()">+ New Form</button>
    </div>
</div>

<div class="card">
    <div id="formsList" style="padding:20px;">Loading...</div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="form-modal">
    <div class="modal" style="max-width:800px;">
        <div class="modal-header">
            <h2 id="modal-title">New Web Form</h2>
            <button class="modal-close" onclick="closeFormModal()">&times;</button>
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
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeFormModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="save-btn">Create Form</button>
            </div>
        </form>
    </div>
</div>

<script>
const CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
const CRM_FIELDS = <?= json_encode($crmFields) ?>;

function loadForms() {
    fetch('/api/webforms.php?action=list')
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('formsList').innerHTML = '<div class="text-center text-muted" style="padding:40px;">Error loading forms</div>';
                return;
            }
            renderForms(data.forms);
        });
}

function renderForms(forms) {
    if (!forms || forms.length === 0) {
        document.getElementById('formsList').innerHTML = `
            <div class="text-center text-muted" style="padding:60px 40px;">
                <h3 style="margin:0 0 8px;font-size:18px;">No forms yet</h3>
                <p>Create your first web form to capture leads directly from your website.</p>
                <button class="btn btn-primary" onclick="openFormModal()" style="margin-top:16px;">Create Form</button>
            </div>`;
        return;
    }
    
    document.getElementById('formsList').innerHTML = `
        <div class="table-container">
            <table class="table">
                <thead><tr><th>Form Name</th><th>Fields</th><th>Submissions</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>${forms.map(f => `
                    <tr>
                        <td><strong>${escapeHtml(f.form_name)}</strong><div class="text-muted" style="font-size:13px;">${escapeHtml(f.description || '')}</div></td>
                        <td>${f.field_count || 0} fields</td>
                        <td>${f.submission_count || 0}</td>
                        <td><span class="badge badge-${f.status === 'active' ? 'success' : 'secondary'}">${f.status || 'active'}</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline" onclick="editForm(${f.form_id})">Edit</button>
                            <button class="btn btn-sm btn-outline" onclick="getEmbedCode(${f.form_id})">Embed</button>
                            <button class="btn btn-sm btn-danger-outline" onclick="deleteForm(${f.form_id})">Delete</button>
                        </td>
                    </tr>`).join('')}
                </tbody>
            </table>
        </div>`;
}

let fieldCount = 0;

function addFieldRow(data) {
    const container = document.getElementById('fields-container');
    const idx = fieldCount++;
    
    const fieldOptions = CRM_FIELDS.map(f => `<option value="${f.name}" ${data && data.crm_field === f.name ? 'selected' : ''}>${f.label}</option>`).join('');
    
    const div = document.createElement('div');
    div.style.cssText = 'display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:8px;align-items:end;padding:8px;border:1px solid #e5e7eb;border-radius:6px;margin-bottom:8px;';
    div.innerHTML = `
        <div class="form-group" style="margin:0;"><label style="font-size:11px;color:#6b7280;">Label *</label><input type="text" class="form-control field-label" value="${data ? escapeHtml(data.label) : ''}" placeholder="Field label" required style="font-size:13px;"></div>
        <div class="form-group" style="margin:0;"><label style="font-size:11px;color:#6b7280;">Map to CRM Field *</label><select class="form-control field-crm" required style="font-size:13px;"><option value="">Select field...</option>${fieldOptions}</select></div>
        <div class="form-group" style="margin:0;"><label style="font-size:11px;color:#6b7280;">Type</label><select class="form-control field-type" style="font-size:13px;">
            <option value="text" ${data && data.type === 'text' ? 'selected' : ''}>Text</option>
            <option value="email" ${data && data.type === 'email' ? 'selected' : ''}>Email</option>
            <option value="tel" ${data && data.type === 'tel' ? 'selected' : ''}>Phone</option>
            <option value="textarea" ${data && data.type === 'textarea' ? 'selected' : ''}>Textarea</option>
            <option value="number" ${data && data.type === 'number' ? 'selected' : ''}>Number</option>
            <option value="select" ${data && data.type === 'select' ? 'selected' : ''}>Dropdown</option>
        </select></div>
        <button type="button" class="btn btn-sm btn-danger-outline" onclick="this.closest('.field-row').remove()" style="margin-bottom:2px;">×</button>`;
    container.appendChild(div);
}

function openFormModal() {
    document.getElementById('form-builder').reset();
    document.getElementById('form-id').value = '';
    document.getElementById('modal-title').textContent = 'New Web Form';
    document.getElementById('save-btn').textContent = 'Create Form';
    document.getElementById('fields-container').innerHTML = '';
    fieldCount = 0;
    addFieldRow({ label: 'Company Name', crm_field: 'company_name', type: 'text' });
    addFieldRow({ label: 'Contact Name', crm_field: 'contact_name', type: 'text' });
    addFieldRow({ label: 'Email', crm_field: 'email', type: 'email' });
    document.getElementById('form-modal').classList.add('active');
}

function closeFormModal() {
    document.getElementById('form-modal').classList.remove('active');
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
    
    if (fields.length === 0) { showNotification('Add at least one field', 'error'); return; }
    
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
        showNotification(data.message, data.success ? 'success' : 'error');
        if (data.success) { closeFormModal(); loadForms(); }
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
        showNotification(data.message, data.success ? 'success' : 'error');
        if (data.success) loadForms();
    });
}

function getEmbedCode(id) {
    const code = `\u003c!-- Pinpoint CRM Web Form --\u003e\n\u003cdiv id="pinpoint-form-${id}">\u003c/div\u003e\n\u003cscript src="https://crm.pinpoint.online/embed/form.js?id=${id}">\u003c/script\u003e\n\u003c!-- End Pinpoint CRM Web Form --\u003e`;
    navigator.clipboard.writeText(code).then(() => showNotification('Embed code copied!', 'success'));
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

loadForms();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?\u003e