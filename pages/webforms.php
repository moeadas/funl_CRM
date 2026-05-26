<?php
/**
 * White Label CRM - Web Forms Manager
 */
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();
requireCompany();

$userId = getCurrentUserId();
$companyId = getCurrentCompanyId();

$pageTitle = 'Web Forms';
$js = ['webforms'];

require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$users = $db->query("SELECT user_id, full_name FROM users WHERE company_id = ? AND status = 'Active' ORDER BY full_name", [$companyId])->fetchAll();
?>

<style>
.webforms-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px 40px;
}
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 24px 0 20px;
}
.page-header h1 {
    font-size: 22px;
    font-weight: 600;
    margin: 0;
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
.btn-outline { background: white; border: 1px solid var(--border, #d1d5db); color: var(--text-primary, #374151); }

/* Forms Grid */
.forms-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
}
.form-card {
    background: white;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 10px;
    padding: 20px;
    position: relative;
}
.form-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}
.form-name {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-primary, #1f2937);
}
.form-status {
    font-size: 11px;
    padding: 3px 8px;
    border-radius: 4px;
    font-weight: 500;
}
.form-status.active { background: #dcfce7; color: #15803d; }
.form-status.inactive { background: #f3f4f6; color: #6b7280; }
.form-slug {
    font-size: 12px;
    color: var(--text-secondary, #9ca3af);
    font-family: monospace;
    margin-bottom: 12px;
}
.form-stats {
    display: flex;
    gap: 16px;
    font-size: 13px;
    color: var(--text-secondary, #6b7280);
    margin-bottom: 16px;
}
.form-actions {
    display: flex;
    gap: 8px;
}
.form-actions .btn { flex: 1; justify-content: center; }

/* Embed Code */
.embed-code {
    background: var(--bg-secondary, #f9fafb);
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 6px;
    padding: 12px;
    font-size: 12px;
    font-family: monospace;
    color: var(--text-secondary, #6b7280);
    overflow-x: auto;
    white-space: pre-wrap;
    word-break: break-all;
    margin-top: 12px;
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
    width: 600px;
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
.modal-header h2 { font-size: 17px; font-weight: 600; margin: 0; }
.modal-close { background: none; border: none; font-size: 20px; cursor: pointer; color: var(--text-secondary, #9ca3af); }
.modal-body { padding: 20px 24px; }
.form-group { margin-bottom: 14px; }
.form-label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 5px; color: var(--text-primary, #374151); }
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
.form-control:focus { outline: none; border-color: var(--primary, #2563eb); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
textarea.form-control { min-height: 70px; resize: vertical; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-actions {
    padding: 16px 24px;
    border-top: 1px solid var(--border, #e5e7eb);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Field Builder */
.field-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: var(--bg-secondary, #f9fafb);
    border-radius: 6px;
    margin-bottom: 8px;
}
.field-drag { cursor: grab; color: var(--text-secondary, #9ca3af); }
.field-type { font-size: 12px; color: var(--text-secondary, #6b7280); min-width: 80px; }
.field-name { flex: 1; font-size: 13px; }
.field-required { font-size: 11px; color: #dc2626; }
.field-actions { display: flex; gap: 4px; }
.field-actions button { background: none; border: none; padding: 4px; cursor: pointer; font-size: 13px; }
</style>

<div class="webforms-page">
    <div class="page-header">
        <h1>Web Forms</h1>
        <button class="btn btn-primary" onclick="openFormModal()">+ New Form</button>
    </div>

    <div class="forms-grid" id="forms-grid">
        <div style="text-align:center;padding:40px;color:#9ca3af">Loading...</div>
    </div>
</div>

<!-- Form Modal -->
<div class="modal-overlay" id="form-modal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="form-modal-title">New Web Form</h2>
            <button class="modal-close" onclick="closeFormModal()">&times;</button>
        </div>
        <form id="webform-form" onsubmit="saveForm(event)">
            <div class="modal-body">
                <input type="hidden" id="form-id" value="">
                
                <div class="form-group">
                    <label class="form-label">Form Name *</label>
                    <input type="text" id="form-name" class="form-control" required placeholder="e.g. Contact Us">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Title (shown on form)</label>
                        <input type="text" id="form-title" class="form-control" placeholder="Get in Touch">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Success Message</label>
                        <input type="text" id="form-success" class="form-control" value="Thank you! We will contact you soon.">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea id="form-description" class="form-control" placeholder="Optional description shown above the form"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Auto-assign leads to</label>
                        <select id="form-assign" class="form-control">
                            <option value="">Do not auto-assign</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Lead Source</label>
                        <input type="text" id="form-source" class="form-control" value="Web Form">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notify Emails (comma-separated)</label>
                    <input type="text" id="form-notify" class="form-control" placeholder="sales@company.com, manager@company.com">
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeFormModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="form-save-btn">Create Form</button>
            </div>
        </form>
    </div>
</div>

<!-- Embed Modal -->
<div class="modal-overlay" id="embed-modal">
    <div class="modal">
        <div class="modal-header">
            <h2>Embed Form</h2>
            <button class="modal-close" onclick="closeEmbedModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size:13px;color:#6b7280;margin-bottom:12px">Copy this code and paste it on your website:</p>
            <div class="embed-code" id="embed-code"></div>
            <p style="font-size:13px;color:#6b7280;margin-top:16px">Or share this direct link:</p>
            <input type="text" id="embed-url" class="form-control" readonly onclick="this.select()">
        </div>
    </div>
</div>

<script>
const COMPANY_ID = <?= json_encode($companyId) ?>;
const CSRF_TOKEN = *** json_encode($_SESSION['csrf_token'] ?? '') ?>;
const API = '/api/webforms.php';

let forms = [];

document.addEventListener('DOMContentLoaded', loadForms);

function loadForms() {
    fetch(`${API}?action=list`, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(resp => {
            if (resp.success) {
                forms = resp.data || [];
                renderForms();
            }
        });
}

function renderForms() {
    const grid = document.getElementById('forms-grid');
    if (!forms.length) {
        grid.innerHTML = `
            <div style="grid-column:1/-1;text-align:center;padding:60px 20px;color:#9ca3af">
                <h3 style="font-size:16px;margin:0 0 8px;color:#374151">No web forms yet</h3>
                Create embeddable forms to capture leads from your website.
            </div>`;
        return;
    }
    
    grid.innerHTML = forms.map(f => `
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-name">${escapeHtml(f.form_name)}</div>
                <div class="form-status ${f.is_active ? 'active' : 'inactive'}">${f.is_active ? 'Active' : 'Inactive'}</div>
            </div>
            <div class="form-slug">${escapeHtml(f.form_slug)}</div>
            <div class="form-stats">
                <span>📥 ${f.submit_count || 0} submissions</span>
                <span>👁️ ${f.is_active ? 'Live' : 'Hidden'}</span>
            </div>
            <div class="form-actions">
                <button class="btn btn-outline" onclick="showEmbed(${f.form_id})">Embed</button>
                <button class="btn btn-outline" onclick="editForm(${f.form_id})">Edit</button>
                <button class="btn btn-outline" onclick="deleteForm(${f.form_id})">Delete</button>
            </div>
        </div>
    `).join('');
}

function openFormModal() {
    document.getElementById('webform-form').reset();
    document.getElementById('form-id').value = '';
    document.getElementById('form-modal-title').textContent = 'New Web Form';
    document.getElementById('form-save-btn').textContent = 'Create Form';
    document.getElementById('form-modal').classList.add('active');
}

function closeFormModal() {
    document.getElementById('form-modal').classList.remove('active');
}

function editForm(formId) {
    const form = forms.find(f => f.form_id == formId);
    if (!form) return;
    
    document.getElementById('form-id').value = form.form_id;
    document.getElementById('form-name').value = form.form_name || '';
    document.getElementById('form-title').value = form.title || '';
    document.getElementById('form-success').value = form.success_message || '';
    document.getElementById('form-description').value = form.description || '';
    document.getElementById('form-assign').value = form.auto_assign_to || '';
    document.getElementById('form-source').value = form.lead_source || 'Web Form';
    document.getElementById('form-notify').value = form.notify_emails || '';
    
    document.getElementById('form-modal-title').textContent = 'Edit Web Form';
    document.getElementById('form-save-btn').textContent = 'Save Changes';
    document.getElementById('form-modal').classList.add('active');
}

function saveForm(e) {
    e.preventDefault();
    const formId = document.getElementById('form-id').value;
    const action = formId ? 'update' : 'create';
    
    const data = {
        csrf_token: CSRF_TOKEN,
        form_name: document.getElementById('form-name').value,
        title: document.getElementById('form-title').value,
        success_message: document.getElementById('form-success').value,
        description: document.getElementById('form-description').value,
        auto_assign_to: document.getElementById('form-assign').value || null,
        lead_source: document.getElementById('form-source').value,
        notify_emails: document.getElementById('form-notify').value,
    };
    if (formId) data.form_id = formId;
    
    fetch(`${API}?action=${action}`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    }).then(r => r.json()).then(resp => {
        if (resp.success) {
            closeFormModal();
            loadForms();
            showNotification(formId ? 'Form updated' : 'Form created', 'success');
        } else {
            showNotification(resp.message || 'Failed to save', 'error');
        }
    });
}

function deleteForm(formId) {
    if (!confirm('Delete this form and all its submissions?')) return;
    fetch(`${API}?action=delete`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ form_id: formId, csrf_token: CSRF_TOKEN })
    }).then(r => r.json()).then(resp => {
        if (resp.success) {
            loadForms();
            showNotification('Form deleted', 'success');
        } else {
            showNotification(resp.message || 'Failed to delete', 'error');
        }
    });
}

function showEmbed(formId) {
    const form = forms.find(f => f.form_id == formId);
    if (!form) return;
    
    const baseUrl = window.location.origin;
    const embedCode = `<!-- Embed this form on your website -->
<iframe 
    src="${baseUrl}/pages/form-embed.php?slug=${form.form_slug}" 
    width="100%" 
    height="600" 
    style="border:none;border-radius:8px;"></iframe>`;
    
    document.getElementById('embed-code').textContent = embedCode;
    document.getElementById('embed-url').value = `${baseUrl}/pages/form-embed.php?slug=${form.form_slug}`;
    document.getElementById('embed-modal').classList.add('active');
}

function closeEmbedModal() {
    document.getElementById('embed-modal').classList.remove('active');
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

document.addEventListener('keydown', e => { 
    if (e.key === 'Escape') { closeFormModal(); closeEmbedModal(); } 
});
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) { closeFormModal(); closeEmbedModal(); } });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
