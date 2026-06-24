<?php
/**
 * White Label CRM - Web Forms Page
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
?>



<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo __('Web Forms'); ?></h1>
        <p class="text-muted"><?php echo __('Create embedded forms that feed directly into your CRM leads pipeline'); ?></p>
    </div>
    <div class="header-actions">
        <a href="/pages/webform-form.php" class="btn btn-primary" style="text-decoration:none;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <?php echo __('New Form'); ?>
            </a>
        </div>
    </div>
</div>

<div class="card">
    <div id="formsList" style="padding:0;">
        <div class="text-center" style="padding:40px; color:var(--text-secondary, #6b7280);"><?php echo __('Loading...'); ?></div>
    </div>
</div>

<script>
const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;

document.addEventListener('DOMContentLoaded', loadForms);

function loadForms() {
    fetch('/api/webforms.php?action=list')
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('formsList').innerHTML = '<div style="padding:40px; text-align:center; color:#6b7280;">Error: ' + (data.message || 'Failed to load') + '</div>';
                return;
            }
            renderForms(data.forms || []);
        })
        .catch(() => {
            document.getElementById('formsList').innerHTML = '<div style="padding:40px; text-align:center; color:#6b7280;">' + window.__('Error loading forms') + '</div>';
        });
}

function renderForms(forms) {
    const container = document.getElementById('formsList');
    if (!forms || forms.length === 0) {
        container.innerHTML = `
            <div style="text-align:center; padding:60px 40px; color:#6b7280;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom:16px; opacity:.4; display:block; margin-left:auto; margin-right:auto;">
                    <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                    <line x1="8" y1="12" x2="16" y2="12"></line>
                    <line x1="12" y1="8" x2="12" y2="16"></line>
                </svg>
                <h3 style="margin:0 0 8px; font-size:18px; color:#1f2937;">${window.__('No web forms yet')}</h3>
                <p style="margin-bottom:20px;">${window.__('Create your first web form to capture leads directly from your website.')}</p>
            <a href="/pages/webform-form.php" class="btn btn-primary" style="text-decoration:none;">${window.__('Create Form')}</a>
            </div>`;
        return;
    }
    
    container.innerHTML = `
        <table class="table">
            <thead>
                <tr>
                    <th>${window.__('Form Name')}</th>
                    <th>${window.__('Fields')}</th>
                    <th>${window.__('Submissions')}</th>
                    <th>${window.__('Status')}</th>
                    <th>${window.__('Actions')}</th>
                </tr>
            </thead>
            <tbody>
                ${forms.map(f => {
                    const statusClass = f.status === 'active' ? 'badge-success' : 'badge-secondary';
                    return `
                    <tr>
                        <td>
                            <strong style="font-size:14px; color:#1f2937;">${escapeHtml(f.form_name || '')}</strong>
                            <div style="font-size:12px; color:#6b7280; margin-top:2px;">${escapeHtml(f.description || '')}</div>
                        </td>
                        <td>${f.field_count || 0} fields</td>
                        <td>${f.submission_count || 0} leads</td>
                        <td><span class="badge ${statusClass}">${(f.status || 'active').toUpperCase()}</span></td>
                        <td>
                            <div style="display:flex; gap:8px;">
                                <button class="btn btn-sm btn-outline" onclick="copyEmbedCode(${f.form_id})" title="${window.__('Copy Iframe HTML Embed Code')}">🔗 ${window.__('Embed Code')}</button>
                                <button class="btn btn-sm btn-outline" onclick="copyUtmSnippet()" title="${window.__('Copy the UTM capture snippet to use with your own forms')}">🧷 ${window.__('UTM Snippet')}</button>
                                <a href="/pages/webform-form.php?id=${f.form_id}" class="btn btn-sm btn-outline" style="text-decoration:none;">✏️ Edit</a>
                                <button class="btn btn-sm btn-danger-outline" onclick="deleteForm(${f.form_id})">🗑️ Delete</button>
                            </div>
                        </td>
                    </tr>`;
                }).join('')}
            </tbody>
        </table>`;
}

function copyEmbedCode(formId) {
    const embedUrl = `${window.location.origin}/pages/form-embed.php?id=${formId}`;
    const iframeCode = `<iframe src="${embedUrl}" width="100%" height="600" style="border:none; background:transparent;"></iframe>`;

    navigator.clipboard.writeText(iframeCode)
        .then(() => {
            showNotification('HTML Embed Code copied to clipboard!', 'success');        })
        .catch(() => {
            showNotification('Failed to copy embed code. Copy manually: ' + iframeCode, 'error');
        });
}

function copyUtmSnippet() {
    const snippet = `<script src="${window.location.origin}/assets/js/funl_utm.js"><\/script>`;
    navigator.clipboard.writeText(snippet)
        .then(() => {
            showNotification('UTM Snippet copied! Paste it before </body> on any page with a form.', 'success');
        })
        .catch(() => {
            showNotification('Copy manually: ' + snippet, 'error');
        });
}

function deleteForm(id) {
    showConfirm('Are you sure you want to delete this web form? All submission history will be deleted.', function() {
        fetch('/api/webforms.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ form_id: id, csrf_token: CSRF_TOKEN })
        })
        .then(r => r.json())
        .then(data => {
            showNotification(data.message || (data.success ? 'Form deleted' : 'Error deleting form'), data.success ? 'success' : 'error');
            if (data.success) loadForms();
        })
        .catch(() => showNotification('Network error deleting form', 'error'));
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
