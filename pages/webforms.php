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

<style>
.webforms-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px 40px;
}
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px 0 20px;
}
.page-header h1 {
    font-size: 22px;
    font-weight: 600;
    margin: 0;
}
.text-muted { color: var(--color-text-muted, #6b7280); font-size: 14px; margin-top: 4px; }
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
.btn-outline:hover { background: var(--bg-secondary, #f3f4f6); }
.btn-danger-outline { background: white; border: 1px solid #fca5a5; color: #dc2626; }
.btn-danger-outline:hover { background: #fef2f2; }

.card {
    background: white;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 10px;
    overflow: hidden;
    margin-top: 16px;
}
table.table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
table.table th {
    background: var(--bg-secondary, #f9fafb);
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    color: var(--text-secondary, #6b7280);
    font-size: 12px;
    text-transform: uppercase;
    border-bottom: 1px solid var(--border, #e5e7eb);
}
table.table td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border, #e5e7eb);
    color: var(--text-primary, #1f2937);
}
table.table tr:last-child td { border-bottom: none; }
table.table tr:hover { background: var(--bg-secondary, #f9fafb); }

.badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}
.badge-success { background: #dcfce7; color: #15803d; }
.badge-secondary { background: #f3f4f6; color: #6b7280; }
</style>

<div class="webforms-page">
    <div class="page-header">
        <div>
            <h1>Web Forms</h1>
            <p class="text-muted">Create embedded forms that feed directly into your CRM leads pipeline</p>
        </div>
        <div class="header-actions">
            <a href="/pages/webform-form.php" class="btn btn-primary" style="text-decoration:none;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Form
            </a>
        </div>
    </div>

    <div class="card">
        <div id="formsList" style="padding:0;">
            <div class="text-center" style="padding:40px; color:var(--text-secondary, #6b7280);">Loading...</div>
        </div>
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
            document.getElementById('formsList').innerHTML = '<div style="padding:40px; text-align:center; color:#6b7280;">Error loading forms</div>';
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
                <h3 style="margin:0 0 8px; font-size:18px; color:#1f2937;">No web forms yet</h3>
                <p style="margin-bottom:20px;">Create your first web form to capture leads directly from your website.</p>
                <a href="/pages/webform-form.php" class="btn btn-primary" style="text-decoration:none;">Create Form</a>
            </div>`;
        return;
    }
    
    container.innerHTML = `
        <table class="table">
            <thead>
                <tr>
                    <th>Form Name</th>
                    <th>Fields</th>
                    <th>Submissions</th>
                    <th>Status</th>
                    <th>Actions</th>
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
                                <button class="btn btn-sm btn-outline" onclick="copyEmbedCode(${f.form_id})" title="Copy Iframe HTML Embed Code">🔗 Embed Code</button>
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
    const iframeCode = `<iframe src="${embedUrl}" width="100%" height="600" style="border:none; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.08);"></iframe>`;
    
    navigator.clipboard.writeText(iframeCode)
        .then(() => {
            showNotification('HTML Embed Code copied to clipboard!', 'success');
        })
        .catch(() => {
            showNotification('Failed to copy embed code. Copy manually: ' + iframeCode, 'error');
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
