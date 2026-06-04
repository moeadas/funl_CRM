<?php
/**
 * White Label CRM V2 — Automation Rule Form
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
startSecureSession();
requireLogin();
requireRole('Sales Manager');

$db = Database::getInstance()->getConnection();
$companyId = getCurrentCompanyId();
$ruleId = intval($_GET['id'] ?? 0);

$rule = null;
if ($ruleId) {
    $stmt = $db->prepare("SELECT * FROM automation_rules WHERE rule_id = ? AND company_id = ?");
    $stmt->execute([$ruleId, $companyId]);
    $rule = $stmt->fetch();
    if (!$rule) {
        $_SESSION['error'] = 'Rule not found';
        header('Location: automation.php');
        exit;
    }
}

// Fetch active users for the dropdown
$users = $db->query("SELECT user_id, full_name FROM users WHERE company_id = $companyId AND status = 'Active'")->fetchAll(PDO::FETCH_ASSOC);

$csrfToken = generateCSRFToken();
$pageTitle = $ruleId ? __('Edit Automation Rule') : __('New Automation Rule');
include '../includes/header.php';
?>

<style>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 24px; box-shadow: var(--shadow-xs); }
.form-group { margin-bottom: 16px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } }
.form-label { display: block; font-size: 13px; font-weight: 500; color: var(--color-text); margin-bottom: 6px; }
.form-control { width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); font-size: 14px; color: var(--color-text); background: var(--color-surface); box-sizing: border-box; }
.form-control:focus { outline: none; border-color: var(--color-accent); }
.btn { padding: 10px 18px; border-radius: var(--radius-sm); font-size: 14px; font-weight: 500; cursor: pointer; transition: all var(--transition); border: none; text-decoration: none; display: inline-block; }
.btn-primary { background: var(--color-accent); color: #fff; }
.btn-primary:hover { background: var(--color-accent-hover); }
.btn-outline { background: var(--color-surface); color: var(--color-text); border: 1px solid var(--color-border); margin-right: 8px; }
.btn-outline:hover { background: var(--color-bg); }
.rule-builder-step { padding: 16px; background: #fafafa; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 16px; }
.step-label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #6b7280; letter-spacing: 0.5px; margin-bottom: 12px; display: inline-block; padding: 2px 6px; border-radius: 4px; }
.step-label.when { background: #fee2e2; color: #dc2626; }
.step-label.then { background: #dcfce7; color: #16a34a; }
.arrow-down { text-align: center; font-size: 20px; color: #9ca3af; margin: 8px 0; }
</style>

<div class="page-container">
    <div class="page-header">
        <div style="display:flex;align-items:center;gap:16px;">
            <a href="/pages/automation.php" class="btn btn-outline" style="padding:8px 14px;">← <?php echo __('back'); ?></a>
            <h1 class="page-title"><?php echo $ruleId ? __('Edit Automation Rule') : __('New Automation Rule'); ?></h1>
        </div>
    </div>

    <div style="max-width: 650px;">
        <div class="card">
            <form id="rule-form">
                <input type="hidden" id="rule-id" value="<?php echo $ruleId; ?>">
                
                <div class="form-group">
                    <label class="form-label"><?php echo __('Rule Name'); ?> *</label>
                    <input type="text" id="rule-name" class="form-control" required placeholder="<?php echo __('e.g. Auto-assign Dubai leads'); ?>" value="<?php echo htmlspecialchars($rule['rule_name'] ?? ''); ?>">
                </div>

                <!-- WHEN -->
                <div class="rule-builder-step">
                    <div class="step-label when"><?php echo __('When this happens...'); ?></div>
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label"><?php echo __('Trigger'); ?></label>
                        <select id="rule-trigger" class="form-control">
                            <option value="lead_created" <?php echo ($rule && $rule['trigger_type'] === 'lead_created') ? 'selected' : ''; ?>><?php echo __('New lead is created'); ?></option>
                            <option value="lead_status_changed" <?php echo ($rule && $rule['trigger_type'] === 'lead_status_changed') ? 'selected' : ''; ?>><?php echo __('Lead status changes'); ?></option>
                            <option value="deal_stage_changed" <?php echo ($rule && $rule['trigger_type'] === 'deal_stage_changed') ? 'selected' : ''; ?>><?php echo __('Deal moves to stage'); ?></option>
                            <option value="task_overdue" <?php echo ($rule && $rule['trigger_type'] === 'task_overdue') ? 'selected' : ''; ?>><?php echo __('Task becomes overdue'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="arrow-down">↓</div>

                <!-- THEN -->
                <div class="rule-builder-step">
                    <div class="step-label then"><?php echo __('Then do this...'); ?></div>
                    <div class="form-group">
                        <label class="form-label"><?php echo __('Action'); ?></label>
                        <select id="rule-action" class="form-control" onchange="updateActionOptions()">
                            <option value="assign_user" <?php echo ($rule && $rule['action_type'] === 'assign_user') ? 'selected' : ''; ?>><?php echo __('Assign to user'); ?></option>
                            <option value="create_task" <?php echo ($rule && $rule['action_type'] === 'create_task') ? 'selected' : ''; ?>><?php echo __('Create a task'); ?></option>
                            <option value="send_email" <?php echo ($rule && $rule['action_type'] === 'send_email') ? 'selected' : ''; ?>><?php echo __('Send email'); ?></option>
                            <option value="move_deal" <?php echo ($rule && $rule['action_type'] === 'move_deal') ? 'selected' : ''; ?>><?php echo __('Move deal to stage'); ?></option>
                            <option value="notify_user" <?php echo ($rule && $rule['action_type'] === 'notify_user') ? 'selected' : ''; ?>><?php echo __('Send notification'); ?></option>
                        </select>
                    </div>
                    
                    <div id="action-options" style="margin-top: 16px;">
                        <!-- Custom options injected here by JS -->
                    </div>
                </div>
                
                <div style="margin-top: 24px; display: flex; justify-content: flex-end;">
                    <a href="/pages/automation.php" class="btn btn-outline"><?php echo __('Cancel'); ?></a>
                    <button type="submit" class="btn btn-primary" id="rule-save-btn"><?php echo $ruleId ? __('Save Changes') : __('Create Rule'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?php echo $csrfToken; ?>';
const USERS = <?php echo json_encode($users); ?>;
const RULE_CONFIG = <?php echo $rule ? $rule['action_config'] : 'null'; ?>;
const API = '/api/automation.php';

document.addEventListener('DOMContentLoaded', function() {
    updateActionOptions();
    if (RULE_CONFIG) {
        populateActionConfig();
    }
});

document.getElementById('rule-form').addEventListener('submit', saveRule);

function updateActionOptions() {
    const action = document.getElementById('rule-action').value;
    const container = document.getElementById('action-options');
    
    const userOptions = USERS.map(u => `<option value="${u.user_id}">${escapeHtml(u.full_name)}</option>`).join('');
    
    switch (action) {
        case 'assign_user':
            container.innerHTML = `
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label"><?php echo __('Assign to'); ?></label>
                    <select id="action-user-id" class="form-control">${userOptions}</select>
                </div>`;
            break;
        case 'create_task':
            container.innerHTML = `
                <div class="form-group">
                    <label class="form-label"><?php echo __('Task Title'); ?></label>
                    <input type="text" id="action-task-title" class="form-control" value="Follow up">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?php echo __('Due in (days)'); ?></label>
                        <input type="number" id="action-due-days" class="form-control" value="2" min="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo __('Priority'); ?></label>
                        <select id="action-priority" class="form-control">
                            <option value="low"><?php echo __('Low'); ?></option>
                            <option value="medium" selected><?php echo __('Medium'); ?></option>
                            <option value="high"><?php echo __('High'); ?></option>
                        </select>
                    </div>
                </div>`;
            break;
        case 'send_email':
            container.innerHTML = `
                <div class="form-group">
                    <label class="form-label"><?php echo __('To'); ?></label>
                    <input type="email" id="action-email-to" class="form-control" placeholder="admin@company.com">
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('Subject'); ?></label>
                    <input type="text" id="action-email-subject" class="form-control" value="New lead notification">
                </div>`;
            break;
        case 'move_deal':
            container.innerHTML = `
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label"><?php echo __('Move to stage'); ?></label>
                    <select id="action-target-stage" class="form-control">
                        <option value="prospecting"><?php echo __('Prospecting'); ?></option>
                        <option value="qualification"><?php echo __('Qualification'); ?></option>
                        <option value="proposal"><?php echo __('Proposal'); ?></option>
                        <option value="negotiation"><?php echo __('Negotiation'); ?></option>
                        <option value="closed_won"><?php echo __('Closed Won'); ?></option>
                        <option value="closed_lost"><?php echo __('Closed Lost'); ?></option>
                    </select>
                </div>`;
            break;
        case 'notify_user':
            container.innerHTML = `
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label"><?php echo __('Message'); ?></label>
                    <input type="text" id="action-message" class="form-control" value="Action required!">
                </div>`;
            break;
    }
}

function populateActionConfig() {
    const action = document.getElementById('rule-action').value;
    if (!RULE_CONFIG) return;
    
    setTimeout(() => {
        switch (action) {
            case 'assign_user':
                if (RULE_CONFIG.user_id) document.getElementById('action-user-id').value = RULE_CONFIG.user_id;
                break;
            case 'create_task':
                if (RULE_CONFIG.title) document.getElementById('action-task-title').value = RULE_CONFIG.title;
                if (RULE_CONFIG.due_days) document.getElementById('action-due-days').value = RULE_CONFIG.due_days;
                if (RULE_CONFIG.priority) document.getElementById('action-priority').value = RULE_CONFIG.priority;
                break;
            case 'send_email':
                if (RULE_CONFIG.to) document.getElementById('action-email-to').value = RULE_CONFIG.to;
                if (RULE_CONFIG.subject) document.getElementById('action-email-subject').value = RULE_CONFIG.subject;
                break;
            case 'move_deal':
                if (RULE_CONFIG.stage) document.getElementById('action-target-stage').value = RULE_CONFIG.stage;
                break;
            case 'notify_user':
                if (RULE_CONFIG.message) document.getElementById('action-message').value = RULE_CONFIG.message;
                break;
        }
    }, 10);
}

function saveRule(e) {
    e.preventDefault();
    const ruleId = document.getElementById('rule-id').value;
    const action = ruleId ? 'update' : 'create';
    
    const actionType = document.getElementById('rule-action').value;
    let actionConfig = {};
    
    switch (actionType) {
        case 'assign_user':
            actionConfig = { user_id: parseInt(document.getElementById('action-user-id')?.value) || 0 };
            break;
        case 'create_task':
            actionConfig = {
                title: document.getElementById('action-task-title')?.value || 'Follow up',
                due_days: parseInt(document.getElementById('action-due-days')?.value) || 2,
                priority: document.getElementById('action-priority')?.value || 'medium',
            };
            break;
        case 'send_email':
            actionConfig = {
                to: document.getElementById('action-email-to')?.value || '',
                subject: document.getElementById('action-email-subject')?.value || '',
            };
            break;
        case 'move_deal':
            actionConfig = { stage: document.getElementById('action-target-stage')?.value || 'prospecting' };
            break;
        case 'notify_user':
            actionConfig = { message: document.getElementById('action-message')?.value || '' };
            break;
    }
    
    const data = {
        csrf_token: CSRF_TOKEN,
        rule_name: document.getElementById('rule-name').value,
        trigger_type: document.getElementById('rule-trigger').value,
        action_type: actionType,
        action_config: actionConfig,
    };
    if (ruleId) data.rule_id = ruleId;
    
    fetch(`${API}?action=${action}`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    }).then(r => r.json()).then(resp => {
        if (resp.success) {
            showNotification(ruleId ? 'Rule updated successfully' : 'Rule created successfully', 'success');
            setTimeout(() => {
                window.location.href = '/pages/automation.php';
            }, 1000);
        } else {
            showNotification(resp.message || 'Failed to save', 'error');
        }
    }).catch(() => showNotification('Error saving rule', 'error'));
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>

<?php include '../includes/footer.php'; ?>
