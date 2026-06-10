<?php
/**
 * White Label CRM - WhatsApp Create Template (standalone, no popup)
 */
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();
requireRole('Sales Manager');

$csrfToken = generateCSRFToken();
$pageTitle = __('create_whatsapp_template');
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;">
        <a href="/pages/whatsapp-dashboard.php#tab-templates" class="btn btn-outline" style="padding:8px 14px;display:inline-flex;align-items:center;gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            <?php echo htmlspecialchars(__('Back to WhatsApp')); ?>
        </a>
        <h1><?php echo htmlspecialchars(__('Create WhatsApp template')); ?></h1>
    </div>
    <div class="header-actions">
        <a href="/pages/whatsapp-dashboard.php#tab-templates" class="btn btn-outline"><?php echo htmlspecialchars(__('Cancel')); ?></a>
        <button type="button" class="btn btn-primary" style="background:#16a34a;border-color:#16a34a;" onclick="submitCreateTemplate()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px;"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            <?php echo htmlspecialchars(__('Create & submit for approval')); ?>
        </button>
    </div>
</div>

<div style="max-width:920px;">
    <form id="createTplForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

        <div class="card">
            <div class="card-header" style="padding:18px 24px;background:#f0fdf4;">
                <h3 class="card-title" style="margin:0;display:flex;align-items:center;gap:8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                    <?php echo htmlspecialchars(__('Template basics')); ?>
                </h3>
            </div>
            <div class="card-body" style="padding:24px;">
                <div class="form-group">
                    <label class="form-label"><?php echo htmlspecialchars(__('Template name')); ?></label>
                    <input type="text" id="ctplName" class="form-control"
                           placeholder="<?php echo htmlspecialchars(__('e.g., appointment_reminder')); ?>"
                           style="padding:10px 14px;">
                    <small class="text-muted"><?php echo htmlspecialchars(__('Template name should be lowercase_with_underscores')); ?></small>
                </div>

                <div class="row-2" style="margin-top:16px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label"><?php echo htmlspecialchars(__('Category')); ?></label>
                        <select id="ctplCategory" class="form-control" style="padding:10px 14px;">
                            <option value="UTILITY"><?php echo htmlspecialchars(__('Utility / transactional')); ?></option>
                            <option value="MARKETING"><?php echo htmlspecialchars(__('Marketing / promotional')); ?></option>
                        </select>
                        <small class="text-muted"><?php echo htmlspecialchars(__('Utility templates usually have an easier approval')); ?></small>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label"><?php echo htmlspecialchars(__('Language')); ?></label>
                        <select id="ctplLanguage" class="form-control" style="padding:10px 14px;">
                            <option value="en">English (en)</option>
                            <option value="ar">Arabic (ar)</option>
                            <option value="es">Spanish (es)</option>
                            <option value="fr">French (fr)</option>
                            <option value="de">German (de)</option>
                            <option value="pt">Portuguese (pt)</option>
                            <option value="it">Italian (it)</option>
                            <option value="tr">Turkish (tr)</option>
                            <option value="nl">Dutch (nl)</option>
                            <option value="ja">Japanese (ja)</option>
                            <option value="zh_CN">Chinese Simplified (zh_CN)</option>
                        </select>
                        <small class="text-muted"><?php echo htmlspecialchars(__('Per Meta language guidelines')); ?></small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:16px;">
            <div class="card-header" style="padding:18px 24px;">
                <h3 class="card-title" style="margin:0;"><?php echo htmlspecialchars(__('Message body')); ?></h3>
            </div>
            <div class="card-body" style="padding:24px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Body')); ?></label>
                    <textarea id="ctplBody" class="form-control" rows="6"
                              placeholder="<?php echo htmlspecialchars(__('Hi {{1}}, this is a reminder about your appointment on {{2}}.')); ?>"
                              oninput="updateCreatePreview()"
                              style="padding:10px 14px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:14px;"></textarea>
                    <small class="text-muted"><?php echo htmlspecialchars(__('Use {{1}}, {{2}}, etc. for variables. Body must start and end with static text.')); ?></small>
                </div>

                <div id="ctplVarsContainer" style="display:none;margin-top:20px;padding-top:20px;border-top:1px solid #e5e7eb;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Variable descriptions')); ?></label>
                    <small class="text-muted" style="display:block;margin-bottom:10px;"><?php echo htmlspecialchars(__('Describe each variable so the template is clear to reviewers.')); ?></small>
                    <div id="ctplVarsList"></div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:16px;">
            <div class="card-header" style="padding:18px 24px;">
                <h3 class="card-title" style="margin:0;"><?php echo htmlspecialchars(__('Live preview')); ?></h3>
            </div>
            <div class="card-body" style="padding:24px;">
                <div id="ctplPreview" style="padding:16px 18px;background:#ECE5DD;border-radius:8px;min-height:60px;font-size:14px;line-height:1.5;">
                    <em class="text-muted"><?php echo htmlspecialchars(__('Start typing the body to see a preview.')); ?></em>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;

function updateCreatePreview() {
    const body = document.getElementById('ctplBody').value;
    const previewEl = document.getElementById('ctplPreview');
    const varsContainer = document.getElementById('ctplVarsContainer');
    const varsList = document.getElementById('ctplVarsList');

    if (!body.trim()) {
        previewEl.innerHTML = '<em class="text-muted"><?php echo htmlspecialchars(__('Start typing the body to see a preview.'), ENT_QUOTES); ?></em>';
        varsContainer.style.display = 'none';
        return;
    }

    // Find all {{N}} variables
    const matches = body.match(/\{\{(\d+)\}\}/g);
    const uniqueVars = [];
    if (matches) {
        matches.forEach(m => { if (uniqueVars.indexOf(m) === -1) uniqueVars.push(m); });
    }

    if (uniqueVars.length > 0) {
        varsContainer.style.display = 'block';
        const existingInputs = {};
        varsList.querySelectorAll('input').forEach(inp => {
            existingInputs[inp.getAttribute('data-var-key')] = inp.value;
        });
        let varsHtml = '';
        uniqueVars.forEach(v => {
            const key = v.replace(/[{}]/g, '');
            const existingVal = existingInputs[key] || '';
            varsHtml += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">' +
                '<code style="flex-shrink:0;font-size:12px;background:#f3f4f6;padding:3px 8px;border-radius:4px;">' + v + '</code>' +
                '<input type="text" class="form-control" data-var-key="' + key + '" value="' + escapeHtml(existingVal) + '" placeholder="Description (e.g. Contact name)" style="font-size:13px;padding:6px 10px;">' +
                '</div>';
        });
        varsList.innerHTML = varsHtml;
    } else {
        varsContainer.style.display = 'none';
    }

    let preview = escapeHtml(body);
    if (uniqueVars.length > 0) {
        uniqueVars.forEach(v => {
            const escaped = v.replace(/[{}]/g, m => '&#' + m.charCodeAt(0) + ';');
            preview = preview.replace(new RegExp(escaped, 'g'),
                '<span style="background:#16a34a;color:#fff;padding:1px 6px;border-radius:3px;font-size:11px;font-weight:600;">' + v + '</span>');
        });
    }
    previewEl.innerHTML = preview;
}

function submitCreateTemplate() {
    const name = document.getElementById('ctplName').value.trim();
    const category = document.getElementById('ctplCategory').value;
    const body = document.getElementById('ctplBody').value.trim();
    const language = document.getElementById('ctplLanguage').value;

    if (!name) { showNotification('Template name is required', 'error'); return; }
    if (!body) { showNotification('Template body is required', 'error'); return; }
    if (/^\{\{/.test(body)) { showNotification('Template body must start with static text, not a variable', 'error'); return; }
    if (/\}\}$/.test(body.trim())) { showNotification('Template body must end with static text, not a variable', 'error'); return; }

    // Collect variable descriptions
    const variables = {};
    const varInputs = document.querySelectorAll('#ctplVarsList input');
    let allVarsFilled = true;
    varInputs.forEach(inp => {
        const key = inp.getAttribute('data-var-key');
        const val = inp.value.trim();
        if (!val) allVarsFilled = false;
        variables[key] = val;
    });
    if (varInputs.length > 0 && !allVarsFilled) {
        showNotification('Please describe all variables', 'error');
        return;
    }

    const btn = document.querySelector('button[onclick="submitCreateTemplate()"]');
    btn.disabled = true;
    btn.textContent = 'Submitting…';

    fetch('/api/whatsapp.php?action=create_content_template', {
        method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            friendly_name: name, name: name, category: category,
            language: language, body: body, variables: variables,
            csrf_token: CSRF_TOKEN
        })
    }).then(r => r.json()).then(resp => {
        if (resp.success) {
            showNotification('Template created and submitted for approval', 'success');
            setTimeout(() => window.location.href = '/pages/whatsapp-dashboard.php#tab-templates', 800);
        } else {
            showNotification(resp.message || 'Failed', 'error');
            btn.disabled = false;
            btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px;"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Create & submit for approval';
        }
    });
}

function escapeHtml(s) { return String(s == null ? '' : s).replace(/[<>&"']/g, c => c === '<' ? '<' : c === '>' ? '>' : c === '&' ? '&' : '"'); }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
