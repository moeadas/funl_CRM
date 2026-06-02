<?php
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$pageTitle = 'Web Form Builder';
$currentPage = 'webforms';
$formId = intval($_GET['id'] ?? 0);
require_once __DIR__ . '/../includes/header.php';

$crmFields = [
    ['name' => 'company_name', 'label' => 'Company Name'],
    ['name' => 'contact_name', 'label' => 'Contact Person Name'],
    ['name' => 'email', 'label' => 'Email Address'],
    ['name' => 'phone', 'label' => 'Phone Number'],
    ['name' => 'mobile', 'label' => 'Mobile Number'],
    ['name' => 'address', 'label' => 'Street Address'],
    ['name' => 'city', 'label' => 'City'],
    ['name' => 'country', 'label' => 'Country'],
    ['name' => 'industry', 'label' => 'Industry'],
    ['name' => 'website', 'label' => 'Website URL'],
    ['name' => 'notes', 'label' => 'Notes / Messages'],
];
?><div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;">
        <a href="/pages/webforms.php" class="btn btn-outline" style="padding:8px 14px;">← <?php echo __('Back to Web Forms'); ?></a>
        <h1><?php echo $formId ? __('Edit Web Form') : __('New Web Form'); ?></h1>
    </div>
    <div style="display:flex; gap:10px;">
        <?php if ($formId): ?>
            <button type="button" class="btn btn-danger" onclick="deleteForm()"><?php echo __('Delete Form'); ?></button>
        <?php endif; ?>
        <button type="button" class="btn btn-primary" onclick="saveForm()"><?php echo __('Save Form'); ?></button>
    </div>
</div>

<div style="max-width:1000px;">
    <div class="card">
        <h3 class="card-title"><?php echo __('Form Profile'); ?></h3>
        <div class="row-2">
            <div class="form-group">
                <label class="form-label"><?php echo __('Form Name'); ?> *</label>
                <input type="text" id="formName" class="form-control" placeholder="<?php echo __('e.g. Contact Us Form'); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo __('Status'); ?></label>
                <select id="formStatus" class="form-control">
                    <option value="active"><?php echo __('Active'); ?></option>
                    <option value="inactive"><?php echo __('Inactive'); ?></option>
                </select>
            </div>
        </div>
        <div style="margin-top:16px;">
            <label class="form-label"><?php echo __('Description / Helper Text'); ?></label>
            <input type="text" id="formDescription" class="form-control" placeholder="<?php echo __('What this form is for, or instructions for users'); ?>">
        </div>
    </div>

    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 class="card-title" style="margin:0;"><?php echo __('Form Fields & Field Mapping'); ?></h3>
            <button type="button" class="btn btn-outline" style="padding: 6px 12px; font-size: 13px;" onclick="addFieldRow()">+ <?php echo __('Add Field'); ?></button>
        </div>
        
        <div id="fieldsContainer">
            <!-- Dynamic fields go here -->
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = "<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>";
const FORM_ID = <?= $formId ?>;
const CRM_FIELDS = <?= json_encode($crmFields) ?>;

document.addEventListener('DOMContentLoaded', function() {
    if (FORM_ID) {
        loadForm();
    } else {
        // Add default fields for a new form
        addFieldRow({ field_label: 'Full Name', crm_field: 'contact_name', field_type: 'text', required: 1 });
        addFieldRow({ field_label: 'Email Address', crm_field: 'email', field_type: 'email', required: 1 });
        addFieldRow({ field_label: 'Company Name', crm_field: 'company_name', field_type: 'text', required: 0 });
        addFieldRow({ field_label: 'Message / Notes', crm_field: 'notes', field_type: 'textarea', required: 0 });
    }
});

function addFieldRow(data = null) {
    const container = document.getElementById('fieldsContainer');
    const row = document.createElement('div');
    row.className = 'field-row';
    
    // Build options
    const options = CRM_FIELDS.map(f => 
        `<option value="${f.name}" ${data && data.crm_field === f.name ? 'selected' : ''}>${f.label}</option>`
    ).join('');
    
    row.innerHTML = `
        <div class="form-group">
            <label class="form-label" style="font-size:11px;color:var(--color-text-muted);">${window.__('Field Label')} *</label>
            <input type="text" class="form-control field-label" value="${data ? escapeHtml(data.field_label || '') : ''}" placeholder="${window.__('e.g. Your Name')}" required>
        </div>
        <div class="form-group">
            <label class="form-label" style="font-size:11px;color:var(--color-text-muted);">${window.__('Map to CRM Field')} *</label>
            <select class="form-control field-crm" required>
                <option value="">${window.__('Select Field...')}</option>
                ${options}
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" style="font-size:11px;color:var(--color-text-muted);">${window.__('Field Type')}</label>
            <select class="form-control field-type">
                <option value="text" ${data && data.field_type === 'text' ? 'selected' : ''}>${window.__('Text Input')}</option>
                <option value="email" ${data && data.field_type === 'email' ? 'selected' : ''}>${window.__('Email Address')}</option>
                <option value="tel" ${data && data.field_type === 'tel' ? 'selected' : ''}>${window.__('Phone Number')}</option>
                <option value="textarea" ${data && data.field_type === 'textarea' ? 'selected' : ''}>${window.__('Paragraph Text')}</option>
                <option value="number" ${data && data.field_type === 'number' ? 'selected' : ''}>${window.__('Number')}</option>
                <option value="url" ${data && data.field_type === 'url' ? 'selected' : ''}>${window.__('Website URL')}</option>
            </select>
        </div>
        <div class="form-group" style="text-align:center;">
            <label class="form-label" style="font-size:11px;color:var(--color-text-muted);">${window.__('Required?')}</label>
            <div style="height:38px; display:flex; align-items:center; justify-content:center;">
                <input type="checkbox" class="field-required" ${data && data.required == 1 ? 'checked' : ''} style="width:18px; height:18px;">
            </div>
        </div>
        <div>
            <button type="button" class="remove-field-btn" onclick="this.closest('.field-row').remove()">&times;</button>
        </div>
    `;
    
    container.appendChild(row);
}

function loadForm() {
    fetch('/api/webforms.php?action=get&id=' + FORM_ID, { credentials: 'same-origin' })
    .then(r => r.json())
    .then(resp => {
        if (resp.success && resp.form) {
            const f = resp.form;
            document.getElementById('formName').value = f.form_name || '';
            document.getElementById('formStatus').value = f.status || 'active';
            document.getElementById('formDescription').value = f.description || '';
            
            const container = document.getElementById('fieldsContainer');
            container.innerHTML = '';
            
            if (f.fields && f.fields.length > 0) {
                f.fields.forEach(field => addFieldRow(field));
            } else {
                addFieldRow();
            }
        } else {
            showNotification(resp.message || 'Failed to load form builder', 'error');
        }
    });
}

function saveForm() {
    const formName = document.getElementById('formName').value.trim();
    if (!formName) { showNotification('Form Name is required', 'error'); return; }
    
    const fields = [];
    let valid = true;
    
    document.querySelectorAll('#fieldsContainer .field-row').forEach(row => {
        const label = row.querySelector('.field-label').value.trim();
        const crmField = row.querySelector('.field-crm').value;
        const type = row.querySelector('.field-type').value;
        const required = row.querySelector('.field-required').checked ? 1 : 0;
        
        if (!label || !crmField) {
            valid = false;
            return;
        }
        
        fields.push({
            label: label,
            crm_field: crmField,
            type: type,
            required: required
        });
    });
    
    if (!valid) {
        showNotification('Please fill in labels and CRM mapping for all fields', 'error');
        return;
    }
    
    if (fields.length === 0) {
        showNotification('Please add at least one field to the form', 'error');
        return;
    }
    
    const payload = {
        csrf_token: CSRF_TOKEN,
        form_name: formName,
        description: document.getElementById('formDescription').value.trim(),
        status: document.getElementById('formStatus').value,
        fields: fields
    };
    
    if (FORM_ID) {
        payload.form_id = FORM_ID;
    }
    
    fetch('/api/webforms.php?action=save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(resp => {
        showNotification(resp.message || (resp.success ? 'Form saved!' : 'Failed to save form'), resp.success ? 'success' : 'error');
        if (resp.success) {
            setTimeout(() => {
                window.location.href = '/pages/webforms.php';
            }, 500);
        }
    })
    .catch(() => showNotification('Network error saving form', 'error'));
}

function deleteForm() {
    showConfirm('Are you sure you want to delete this web form? All submission history for this form will be deleted.', function() {
        fetch('/api/webforms.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ form_id: FORM_ID, csrf_token: CSRF_TOKEN })
        })
        .then(r => r.json())
        .then(resp => {
            showNotification(resp.message || (resp.success ? 'Form deleted' : 'Delete failed'), resp.success ? 'success' : 'error');
            if (resp.success) {
                setTimeout(() => {
                    window.location.href = '/pages/webforms.php';
                }, 500);
            }
        });
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
