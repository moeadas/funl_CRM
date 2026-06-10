<?php
/**
 * White Label CRM - Automation Rule (Create / Edit)
 * Standalone page — no popups.
 */
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$ruleId = intval($_GET['id'] ?? 0);
$companyId = $_SESSION['company_id'] ?? null;

$db = Database::getInstance();
$users = $db->query("SELECT user_id, full_name FROM users WHERE company_id = ? AND status = 'Active' ORDER BY full_name", [$companyId])->fetchAll();

// If editing, load the existing rule
$rule = null;
if ($ruleId) {
    $stmt = $db->prepare("SELECT * FROM automation_rules WHERE rule_id = ? AND company_id = ?");
    $stmt->execute([$ruleId, $companyId]);
    $rule = $stmt->fetch(PDO::FETCH_ASSOC);
}

$pageTitle = $ruleId ? __('Edit Rule') : __('New Automation Rule');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <a href="/pages/automation.php" class="btn btn-outline" style="padding:8px 14px;display:inline-flex;align-items:center;gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            <?php echo htmlspecialchars(__('Back to Automation')); ?>
        </a>
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
    </div>
    <div class="header-actions">
        <button type="button" class="btn btn-outline" onclick="window.location='/pages/automation.php'"><?php echo htmlspecialchars(__('Cancel')); ?></button>
        <button type="button" class="btn btn-primary" id="btnSaveRule" style="display:inline-flex;align-items:center;gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            <?php echo htmlspecialchars($ruleId ? __('Save Changes') : __('Create Rule')); ?>
        </button>
    </div>
</div>

<div style="max-width:780px;">
    <form id="ruleForm" onsubmit="return false;">
        <input type="hidden" id="ruleId" value="<?php echo (int)$ruleId; ?>">

        <div class="card">
            <div class="card-header" style="padding:18px 24px;">
                <h3 class="card-title" style="margin:0;"><?php echo htmlspecialchars(__('Rule details')); ?></h3>
            </div>
            <div class="card-body" style="padding:24px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Rule Name')); ?> *</label>
                    <input type="text" id="ruleName" class="form-control" required
                           placeholder="<?php echo htmlspecialchars(__('e.g. Auto-assign Dubai leads')); ?>"
                           value="<?php echo htmlspecialchars($rule['rule_name'] ?? ''); ?>"
                           style="padding:10px 14px;">
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:16px;">
            <div class="card-header" style="padding:18px 24px;background:#fff7ed;">
                <h3 class="card-title" style="margin:0;display:flex;align-items:center;gap:8px;">
                    <span style="background:#f97316;color:#fff;width:24px;height:24px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:13px;font-weight:600;">1</span>
                    <?php echo htmlspecialchars(__('When this happens...')); ?>
                </h3>
            </div>
            <div class="card-body" style="padding:24px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Trigger')); ?></label>
                    <select id="ruleTrigger" class="form-control" style="padding:10px 14px;">
                        <option value="lead_created"><?php echo htmlspecialchars(__('New lead is created')); ?></option>
                        <option value="lead_status_changed"><?php echo htmlspecialchars(__('Lead status changes')); ?></option>
                        <option value="deal_stage_changed"><?php echo htmlspecialchars(__('Deal moves to stage')); ?></option>
                        <option value="task_overdue"><?php echo htmlspecialchars(__('Task becomes overdue')); ?></option>
                    </select>
                </div>
            </div>
        </div>

        <div style="text-align:center;padding:12px 0;color:#9ca3af;font-size:18px;">↓</div>

        <div class="card">
            <div class="card-header" style="padding:18px 24px;background:#f0fdf4;">
                <h3 class="card-title" style="margin:0;display:flex;align-items:center;gap:8px;">
                    <span style="background:#16a34a;color:#fff;width:24px;height:24px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:13px;font-weight:600;">2</span>
                    <?php echo htmlspecialchars(__('Then do this...')); ?>
                </h3>
            </div>
            <div class="card-body" style="padding:24px;">
                <div class="form-group">
                    <label class="form-label"><?php echo htmlspecialchars(__('Action')); ?></label>
                    <select id="ruleAction" class="form-control" style="padding:10px 14px;">
                        <option value="assign_user"><?php echo htmlspecialchars(__('Assign to user')); ?></option>
                        <option value="create_task"><?php echo htmlspecialchars(__('Create a task')); ?></option>
                        <option value="send_email"><?php echo htmlspecialchars(__('Send email')); ?></option>
                        <option value="move_deal"><?php echo htmlspecialchars(__('Move deal to stage')); ?></option>
                        <option value="notify_user"><?php echo htmlspecialchars(__('Send notification')); ?></option>
                    </select>
                </div>
                <div id="actionOptions" style="margin-top:16px;"></div>
            </div>
        </div>
    </form>
</div>

<script>
const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
const API = '/api/automation.php';
const USERS = <?= json_encode($users) ?>;
const EXISTING_RULE = <?= json_encode($rule ?: null) ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Pre-fill values if editing
    if (EXISTING_RULE) {
        document.getElementById('ruleName').value = EXISTING_RULE.rule_name || '';
        document.getElementById('ruleTrigger').value = EXISTING_RULE.trigger_type || 'lead_created';
        document.getElementById('ruleAction').value = EXISTING_RULE.action_type || 'assign_user';
    }
    updateActionOptions();
    document.getElementById('ruleAction').addEventListener('change', updateActionOptions);
    document.getElementById('btnSaveRule').addEventListener('click', saveRule);
});

function updateActionOptions() {
    const action = document.getElementById('ruleAction').value;
    const container = document.getElementById('actionOptions');
    const userOptions = USERS.map(u => `<option value="${u.user_id}">${escapeHtml(u.full_name)}</option>`).join('');
    const cfg = (EXISTING_RULE && EXISTING_RULE.action_config) ? safeParse(EXISTING_RULE.action_config) : {};

    let html = '';
    switch (action) {
        case 'assign_user':
            html = `
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">${window.__('Assign to')}</label>
                    <select id="actionUserId" class="form-control" style="padding:10px 14px;">${userOptions}</select>
                </div>`;
            break;
        case 'create_task':
            html = `
                <div class="form-group">
                    <label class="form-label">${window.__('Task Title')}</label>
                    <input type="text" id="actionTaskTitle" class="form-control" value="${escapeAttr(cfg.title || 'Follow up')}" style="padding:10px 14px;">
                </div>
                <div class="row-2" style="margin-top:16px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">${window.__('Due in (days)')}</label>
                        <input type="number" id="actionDueDays" class="form-control" value="${escapeAttr(cfg.due_days || 2)}" min="1" style="padding:10px 14px;">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">${window.__('Priority')}</label>
                        <select id="actionPriority" class="form-control" style="padding:10px 14px;">
                            <option value="low" ${cfg.priority === 'low' ? 'selected' : ''}>${window.__('Low')}</option>
                            <option value="medium" ${(!cfg.priority || cfg.priority === 'medium') ? 'selected' : ''}>${window.__('Medium')}</option>
                            <option value="high" ${cfg.priority === 'high' ? 'selected' : ''}>${window.__('High')}</option>
                        </select>
                    </div>
                </div>`;
            break;
        case 'send_email':
            html = `
                <div class="form-group">
                    <label class="form-label">${window.__('To')}</label>
                    <input type="email" id="actionEmailTo" class="form-control" placeholder="admin@company.com" value="${escapeAttr(cfg.to || '')}" style="padding:10px 14px;">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">${window.__('Subject')}</label>
                    <input type="text" id="actionEmailSubject" class="form-control" value="${escapeAttr(cfg.subject || 'New lead notification')}" style="padding:10px 14px;">
                </div>`;
            break;
        case 'move_deal':
            html = `
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">${window.__('Move to stage')}</label>
                    <select id="actionTargetStage" class="form-control" style="padding:10px 14px;">
                        <option value="prospecting" ${cfg.stage === 'prospecting' ? 'selected' : ''}>${window.__('Prospecting')}</option>
                        <option value="qualification" ${cfg.stage === 'qualification' ? 'selected' : ''}>${window.__('Qualification')}</option>
                        <option value="proposal" ${cfg.stage === 'proposal' ? 'selected' : ''}>${window.__('Proposal')}</option>
                        <option value="negotiation" ${cfg.stage === 'negotiation' ? 'selected' : ''}>${window.__('Negotiation')}</option>
                        <option value="closed_won" ${cfg.stage === 'closed_won' ? 'selected' : ''}>${window.__('Closed Won')}</option>
                        <option value="closed_lost" ${cfg.stage === 'closed_lost' ? 'selected' : ''}>${window.__('Closed Lost')}</option>
                    </select>
                </div>`;
            break;
        case 'notify_user':
            html = `
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">${window.__('Message')}</label>
                    <input type="text" id="actionMessage" class="form-control" value="${escapeAttr(cfg.message || 'Action required!')}" style="padding:10px 14px;">
                </div>`;
            break;
    }
    container.innerHTML = html;

    // Restore selected user for assign_user
    if (action === 'assign_user' && cfg.user_id) {
        const sel = document.getElementById('actionUserId');
        if (sel) sel.value = cfg.user_id;
    }
}

function safeParse(v) {
    if (!v) return {};
    if (typeof v === 'object') return v;
    try { return JSON.parse(v); } catch (e) { return {}; }
}

function saveRule() {
    const ruleId = document.getElementById('ruleId').value;
    const name = document.getElementById('ruleName').value.trim();
    if (!name) { showNotification('Rule name is required', 'error'); return; }

    const actionType = document.getElementById('ruleAction').value;
    let actionConfig = {};

    switch (actionType) {
        case 'assign_user':
            actionConfig = { user_id: document.getElementById('actionUserId')?.value || '' };
            break;
        case 'create_task':
            actionConfig = {
                title: document.getElementById('actionTaskTitle')?.value || 'Follow up',
                due_days: parseInt(document.getElementById('actionDueDays')?.value || 2),
                priority: document.getElementById('actionPriority')?.value || 'medium',
            };
            break;
        case 'send_email':
            actionConfig = {
                to: document.getElementById('actionEmailTo')?.value || '',
                subject: document.getElementById('actionEmailSubject')?.value || '',
            };
            break;
        case 'move_deal':
            actionConfig = { stage: document.getElementById('actionTargetStage')?.value || 'prospecting' };
            break;
        case 'notify_user':
            actionConfig = { message: document.getElementById('actionMessage')?.value || '' };
            break;
    }

    const payload = {
        csrf_token: CSRF_TOKEN,
        rule_name: name,
        trigger_type: document.getElementById('ruleTrigger').value,
        action_type: actionType,
        action_config: actionConfig,
    };
    if (ruleId) payload.rule_id = ruleId;
    const action = ruleId ? 'update' : 'create';

    const btn = document.getElementById('btnSaveRule');
    btn.disabled = true;
    const origHTML = btn.innerHTML;
    btn.textContent = 'Saving…';

    fetch(`${API}?action=${action}`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    }).then(r => r.json()).then(resp => {
        if (resp.success) {
            showNotification(ruleId ? 'Rule updated' : 'Rule created', 'success');
            setTimeout(() => window.location.href = '/pages/automation.php', 600);
        } else {
            showNotification(resp.message || 'Failed to save', 'error');
            btn.disabled = false;
            btn.innerHTML = origHTML;
        }
    }).catch(err => {
        showNotification('Network error: ' + err.message, 'error');
        btn.disabled = false;
        btn.innerHTML = origHTML;
    });
}

function escapeHtml(str) {
    if (str == null) return '';
    return String(str).replace(/[<>&"']/g, c => c === '<' ? '<' : c === '>' ? '>' : c === '&' ? '&' : '"');
}
function escapeAttr(str) {
    return escapeHtml(str);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
