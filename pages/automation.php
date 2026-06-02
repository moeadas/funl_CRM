<?php
/**
 * White Label CRM - Automation / Workflows
 * Simple rule-based automation
 */
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$userId = getCurrentUserId();
$companyId = $_SESSION["company_id"] ?? null;
$userRole = $_SESSION["role"] ?? "";

$pageTitle = __('Automation');
$js = ['automation'];

require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$users = $db->query("SELECT user_id, full_name FROM users WHERE company_id = ? AND status = 'Active' ORDER BY full_name", [$companyId])->fetchAll();
?>



<div class="automation-page">
    <div class="page-header">
        <h1 class="page-title"><?php echo __('Automation'); ?></h1>
        <button class="btn btn-primary" onclick="openRuleModal()">+ <?php echo __('New Rule'); ?></button>
    </div>

    <div class="rules-list" id="rules-list">
        <div class="empty-state">
            <h3><?php echo __('No automation rules yet'); ?></h3>
            <?php echo __('Create rules to automate repetitive tasks.'); ?>
        </div>
    </div>

    <!-- Execution Logs -->
    <div class="logs-section">
        <div class="logs-title"><?php echo __('Recent Activity'); ?></div>
        <div id="logs-list">
            <div class="empty-state" style="padding:20px 0;"><?php echo __('No recent activity.'); ?></div>
        </div>
    </div>
</div>

<!-- Rule Modal -->
<div class="modal-overlay" id="rule-modal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="rule-modal-title"><?php echo __('New Automation Rule'); ?></h2>
            <button class="modal-close" onclick="closeRuleModal()">&times;</button>
        </div>
        <form id="rule-form" onsubmit="saveRule(event)">
            <div class="modal-body">
                <input type="hidden" id="rule-id" value="">
                
                <div class="form-group">
                    <label class="form-label"><?php echo __('Rule Name'); ?> *</label>
                    <input type="text" id="rule-name" class="form-control" required placeholder="<?php echo __('e.g. Auto-assign Dubai leads'); ?>">
                </div>

                <!-- WHEN -->
                <div class="rule-builder-step">
                    <div class="step-label when"><?php echo __('When this happens...'); ?></div>
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label"><?php echo __('Trigger'); ?></label>
                        <select id="rule-trigger" class="form-control">
                            <option value="lead_created"><?php echo __('New lead is created'); ?></option>
                            <option value="lead_status_changed"><?php echo __('Lead status changes'); ?></option>
                            <option value="deal_stage_changed"><?php echo __('Deal moves to stage'); ?></option>
                            <option value="task_overdue"><?php echo __('Task becomes overdue'); ?></option>
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
                            <option value="assign_user"><?php echo __('Assign to user'); ?></option>
                            <option value="create_task"><?php echo __('Create a task'); ?></option>
                            <option value="send_email"><?php echo __('Send email'); ?></option>
                            <option value="move_deal"><?php echo __('Move deal to stage'); ?></option>
                            <option value="notify_user"><?php echo __('Send notification'); ?></option>
                        </select>
                    </div>
                    <div id="action-options">
                        <!-- Dynamic action options -->
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeRuleModal()"><?php echo __('Cancel'); ?></button>
                <button type="submit" class="btn btn-primary" id="rule-save-btn"><?php echo __('Create Rule'); ?></button>
            </div>
        </form>
    </div>
</div>

<script>
const COMPANY_ID = <?= json_encode($companyId) ?>;
const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
const API = '/api/automation.php';
const USERS = <?= json_encode($users) ?>;

let rules = [];

document.addEventListener('DOMContentLoaded', () => {
    loadRules();
    loadLogs();
});

function loadRules() {
    fetch(`${API}?action=list`, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(resp => {
            if (resp.success) {
                rules = resp.data || [];
                renderRules();
            }
        });
}

function renderRules() {
    const container = document.getElementById('rules-list');
    if (!rules.length) {
        container.innerHTML = `
            <div class="empty-state">
                <h3>${window.__('No automation rules yet')}</h3>
                ${window.__('Create rules to automate repetitive tasks like assigning leads or creating follow-up tasks.')}
            </div>`;
        return;
    }
    
    container.innerHTML = rules.map(r => {
        const triggerLabels = {
            'lead_created': window.__('New lead created'),
            'lead_status_changed': window.__('Lead status changes'),
            'deal_stage_changed': window.__('Deal moves to stage'),
            'task_overdue': window.__('Task becomes overdue'),
        };
        const actionLabels = {
            'assign_user': window.__('Assign to user'),
            'create_task': window.__('Create task'),
            'send_email': window.__('Send email'),
            'move_deal': window.__('Move deal'),
            'notify_user': window.__('Send notification'),
        };
        return `
        <div class="rule-card ${r.is_active ? '' : 'inactive'}">
            <div class="rule-info">
                <div class="rule-name">${escapeHtml(r.rule_name)}</div>
                <div class="rule-desc">${window.__('When')} <strong>${triggerLabels[r.trigger_type] || r.trigger_type}</strong> → ${window.__('Then')} <strong>${actionLabels[r.action_type] || r.action_type}</strong></div>
                <div class="rule-meta">${window.__('Run')} ${r.run_count || 0} ${window.__('times')} · ${window.__('Created by')} ${escapeHtml(r.creator_name || window.__('System'))}</div>
            </div>
            <div class="toggle-switch ${r.is_active ? 'active' : ''}" onclick="toggleRule(${r.rule_id}, this)"></div>
            <div class="rule-actions">
                <button onclick="editRule(${r.rule_id})" title="${window.__('Edit')}">✏️</button>
                <button onclick="deleteRule(${r.rule_id})" title="${window.__('Delete')}">🗑️</button>
            </div>
        </div>`;
    }).join('');
}

function loadLogs() {
    fetch(`${API}?action=logs&limit=20`, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(resp => {
            if (!resp.success) return;
            const logs = resp.data || [];
            const container = document.getElementById('logs-list');
            if (!logs.length) {
                container.innerHTML = '<div class="empty-state" style="padding:20px 0;">' + window.__('No recent activity.') + '</div>';
                return;
            }
            container.innerHTML = logs.map(l => `
                <div class="log-entry">
                    <div class="log-status ${l.status}"></div>
                    <div class="log-time">${formatTime(l.created_at)}</div>
                    <div class="log-action">${escapeHtml(l.action_taken)}</div>
                    <div class="log-rule">${escapeHtml(l.rule_name || '')}</div>
                </div>
            `).join('');
        });
}

function formatTime(ts) {
    if (!ts) return '';
    const d = new Date(ts + 'Z');
    const now = new Date();
    const diff = (now - d) / 1000;
    if (diff < 60) return window.__('Just now');
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

// Modal
function openRuleModal() {
    document.getElementById('rule-form').reset();
    document.getElementById('rule-id').value = '';
    document.getElementById('rule-modal-title').textContent = window.__('New Automation Rule');
    document.getElementById('rule-save-btn').textContent = window.__('Create Rule');
    updateActionOptions();
    document.getElementById('rule-modal').classList.add('active');
}

function closeRuleModal() {
    document.getElementById('rule-modal').classList.remove('active');
}

function editRule(ruleId) {
    const rule = rules.find(r => r.rule_id == ruleId);
    if (!rule) return;
    
    document.getElementById('rule-id').value = rule.rule_id;
    document.getElementById('rule-name').value = rule.rule_name || '';
    document.getElementById('rule-trigger').value = rule.trigger_type || 'lead_created';
    document.getElementById('rule-action').value = rule.action_type || 'assign_user';
    document.getElementById('rule-modal-title').textContent = window.__('Edit Rule');
    document.getElementById('rule-save-btn').textContent = window.__('Save Changes');
    
    updateActionOptions();
    document.getElementById('rule-modal').classList.add('active');
}

function updateActionOptions() {
    const action = document.getElementById('rule-action').value;
    const container = document.getElementById('action-options');
    
    const userOptions = USERS.map(u => `<option value="${u.user_id}">${escapeHtml(u.full_name)}</option>`).join('');
    
    switch (action) {
        case 'assign_user':
            container.innerHTML = `
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">${window.__('Assign to')}</label>
                    <select id="action-user-id" class="form-control">${userOptions}</select>
                </div>`;
            break;
        case 'create_task':
            container.innerHTML = `
                <div class="form-group">
                    <label class="form-label">${window.__('Task Title')}</label>
                    <input type="text" id="action-task-title" class="form-control" value="Follow up">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">${window.__('Due in (days)')}</label>
                        <input type="number" id="action-due-days" class="form-control" value="2" min="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">${window.__('Priority')}</label>
                        <select id="action-priority" class="form-control">
                            <option value="low">${window.__('Low')}</option>
                            <option value="medium" selected>${window.__('Medium')}</option>
                            <option value="high">${window.__('High')}</option>
                        </select>
                    </div>
                </div>`;
            break;
        case 'send_email':
            container.innerHTML = `
                <div class="form-group">
                    <label class="form-label">${window.__('To')}</label>
                    <input type="email" id="action-email-to" class="form-control" placeholder="admin@company.com">
                </div>
                <div class="form-group">
                    <label class="form-label">${window.__('Subject')}</label>
                    <input type="text" id="action-email-subject" class="form-control" value="New lead notification">
                </div>`;
            break;
        case 'move_deal':
            container.innerHTML = `
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">${window.__('Move to stage')}</label>
                    <select id="action-target-stage" class="form-control">
                        <option value="prospecting">${window.__('Prospecting')}</option>
                        <option value="qualification">${window.__('Qualification')}</option>
                        <option value="proposal">${window.__('Proposal')}</option>
                        <option value="negotiation">${window.__('Negotiation')}</option>
                        <option value="closed_won">${window.__('Closed Won')}</option>
                        <option value="closed_lost">${window.__('Closed Lost')}</option>
                    </select>
                </div>`;
            break;
        case 'notify_user':
            container.innerHTML = `
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">${window.__('Message')}</label>
                    <input type="text" id="action-message" class="form-control" value="Action required!">
                </div>`;
            break;
    }
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
            closeRuleModal();
            loadRules();
            showNotification(ruleId ? window.__('Rule updated') : window.__('Rule created'), 'success');
        } else {
            showNotification(resp.message || window.__('Failed to save'), 'error');
        }
    });
}

function toggleRule(ruleId, el) {
    fetch(`${API}?action=toggle`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ rule_id: ruleId, csrf_token: CSRF_TOKEN })
    }).then(r => r.json()).then(resp => {
        if (resp.success) {
            el.classList.toggle('active');
            const card = el.closest('.rule-card');
            card.classList.toggle('inactive');
            showNotification(el.classList.contains('active') ? window.__('Rule enabled') : window.__('Rule disabled'), 'success');
        }
    });
}

function deleteRule(ruleId) {
    showConfirm(window.__('Delete this automation rule?'), function() {
        fetch(`${API}?action=delete`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ rule_id: ruleId, csrf_token: CSRF_TOKEN })
        }).then(r => r.json()).then(resp => {
            if (resp.success) {
                loadRules();
                showNotification(window.__('Rule deleted'), 'success');
            } else {
                showNotification(resp.message || window.__('Failed to delete'), 'error');
            }
        });
    });
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeRuleModal(); });
document.querySelector('.modal-overlay').addEventListener('click', e => { 
    if (e.target === e.currentTarget) closeRuleModal(); 
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
