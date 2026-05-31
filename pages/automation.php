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

$pageTitle = 'Automation';
$js = ['automation'];

require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$users = $db->query("SELECT user_id, full_name FROM users WHERE company_id = ? AND status = 'Active' ORDER BY full_name", [$companyId])->fetchAll();
?>

<style>
.automation-page {
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
.btn-primary:hover { background: var(--primary-dark, #1d4ed8); }
.btn-outline { background: white; border: 1px solid var(--border, #d1d5db); color: var(--text-primary, #374151); }

/* Rules List */
.rules-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.rule-card {
    background: white;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 10px;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    transition: box-shadow 0.15s;
}
.rule-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.rule-card.inactive { opacity: 0.6; }

.rule-info { flex: 1; }
.rule-name {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-primary, #1f2937);
    margin-bottom: 4px;
}
.rule-desc {
    font-size: 13px;
    color: var(--text-secondary, #6b7280);
}
.rule-meta {
    font-size: 12px;
    color: var(--text-secondary, #9ca3af);
    margin-top: 4px;
}

/* Toggle Switch */
.toggle-switch {
    position: relative;
    width: 44px;
    height: 24px;
    background: var(--border, #d1d5db);
    border-radius: 12px;
    cursor: pointer;
    transition: background 0.2s;
    flex-shrink: 0;
}
.toggle-switch.active { background: var(--primary, #2563eb); }
.toggle-switch::after {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: 20px;
    height: 20px;
    background: white;
    border-radius: 50%;
    transition: transform 0.2s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.toggle-switch.active::after { transform: translateX(20px); }

.rule-actions {
    display: flex;
    gap: 6px;
}
.rule-actions button {
    background: none;
    border: none;
    padding: 6px 10px;
    cursor: pointer;
    font-size: 14px;
    border-radius: 4px;
    color: var(--text-secondary, #6b7280);
}
.rule-actions button:hover { background: var(--bg-secondary, #f3f4f6); color: var(--text-primary, #1f2937); }

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary, #9ca3af);
}
.empty-state h3 { font-size: 16px; margin: 0 0 8px; color: var(--text-primary, #374151); }

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
    width: 560px;
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
.modal-header h2 {
    font-size: 17px;
    font-weight: 600;
    margin: 0;
}
.modal-close {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: var(--text-secondary, #9ca3af);
}
.modal-close:hover { color: var(--text-primary, #1f2937); }
.modal-body { padding: 20px 24px; }
.form-group { margin-bottom: 16px; }
.form-label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 6px;
    color: var(--text-primary, #374151);
}
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
.form-control:focus {
    outline: none;
    border-color: var(--primary, #2563eb);
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-actions {
    padding: 16px 24px;
    border-top: 1px solid var(--border, #e5e7eb);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
.form-actions .btn { padding: 9px 18px; }

/* Rule Builder */
.rule-builder-step {
    background: var(--bg-secondary, #f9fafb);
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 12px;
}
.step-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-secondary, #6b7280);
    margin-bottom: 10px;
}
.step-label.when { color: #2563eb; }
.step-label.then { color: #16a34a; }

.arrow-down {
    text-align: center;
    font-size: 20px;
    color: var(--text-secondary, #9ca3af);
    margin: 4px 0;
}

/* Logs */
.logs-section {
    margin-top: 32px;
    border-top: 1px solid var(--border, #e5e7eb);
    padding-top: 24px;
}
.logs-title {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 16px;
}
.log-entry {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border, #f3f4f6);
    font-size: 13px;
}
.log-status {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.log-status.success { background: #22c55e; }
.log-status.failed { background: #dc2626; }
.log-status.skipped { background: #9ca3af; }
.log-time { color: var(--text-secondary, #9ca3af); font-size: 12px; min-width: 60px; }
.log-action { flex: 1; color: var(--text-primary, #374151); }
.log-rule { color: var(--text-secondary, #6b7280); font-size: 12px; }
</style>

<div class="automation-page">
    <div class="page-header">
        <h1>Automation</h1>
        <button class="btn btn-primary" onclick="openRuleModal()">+ New Rule</button>
    </div>

    <div class="rules-list" id="rules-list">
        <div class="empty-state">
            <h3>No automation rules yet</h3>
            Create rules to automate repetitive tasks.
        </div>
    </div>

    <!-- Execution Logs -->
    <div class="logs-section">
        <div class="logs-title">Recent Activity</div>
        <div id="logs-list">
            <div class="empty-state" style="padding:20px 0;">No recent activity.</div>
        </div>
    </div>
</div>

<!-- Rule Modal -->
<div class="modal-overlay" id="rule-modal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="rule-modal-title">New Automation Rule</h2>
            <button class="modal-close" onclick="closeRuleModal()">&times;</button>
        </div>
        <form id="rule-form" onsubmit="saveRule(event)">
            <div class="modal-body">
                <input type="hidden" id="rule-id" value="">
                
                <div class="form-group">
                    <label class="form-label">Rule Name *</label>
                    <input type="text" id="rule-name" class="form-control" required placeholder="e.g. Auto-assign Dubai leads">
                </div>

                <!-- WHEN -->
                <div class="rule-builder-step">
                    <div class="step-label when">When this happens...</div>
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">Trigger</label>
                        <select id="rule-trigger" class="form-control">
                            <option value="lead_created">New lead is created</option>
                            <option value="lead_status_changed">Lead status changes</option>
                            <option value="deal_stage_changed">Deal moves to stage</option>
                            <option value="task_overdue">Task becomes overdue</option>
                        </select>
                    </div>
                </div>

                <div class="arrow-down">↓</div>

                <!-- THEN -->
                <div class="rule-builder-step">
                    <div class="step-label then">Then do this...</div>
                    <div class="form-group">
                        <label class="form-label">Action</label>
                        <select id="rule-action" class="form-control" onchange="updateActionOptions()">
                            <option value="assign_user">Assign to user</option>
                            <option value="create_task">Create a task</option>
                            <option value="send_email">Send email</option>
                            <option value="move_deal">Move deal to stage</option>
                            <option value="notify_user">Send notification</option>
                        </select>
                    </div>
                    <div id="action-options">
                        <!-- Dynamic action options -->
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeRuleModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="rule-save-btn">Create Rule</button>
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
                <h3>No automation rules yet</h3>
                Create rules to automate repetitive tasks like assigning leads or creating follow-up tasks.
            </div>`;
        return;
    }
    
    container.innerHTML = rules.map(r => {
        const triggerLabels = {
            'lead_created': 'New lead created',
            'lead_status_changed': 'Lead status changes',
            'deal_stage_changed': 'Deal moves to stage',
            'task_overdue': 'Task becomes overdue',
        };
        const actionLabels = {
            'assign_user': 'Assign to user',
            'create_task': 'Create task',
            'send_email': 'Send email',
            'move_deal': 'Move deal',
            'notify_user': 'Send notification',
        };
        return `
        <div class="rule-card ${r.is_active ? '' : 'inactive'}">
            <div class="rule-info">
                <div class="rule-name">${escapeHtml(r.rule_name)}</div>
                <div class="rule-desc">When <strong>${triggerLabels[r.trigger_type] || r.trigger_type}</strong> → Then <strong>${actionLabels[r.action_type] || r.action_type}</strong></div>
                <div class="rule-meta">Run ${r.run_count || 0} times · Created by ${escapeHtml(r.creator_name || 'System')}</div>
            </div>
            <div class="toggle-switch ${r.is_active ? 'active' : ''}" onclick="toggleRule(${r.rule_id}, this)"></div>
            <div class="rule-actions">
                <button onclick="editRule(${r.rule_id})" title="Edit">✏️</button>
                <button onclick="deleteRule(${r.rule_id})" title="Delete">🗑️</button>
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
                container.innerHTML = '<div class="empty-state" style="padding:20px 0;">No recent activity.</div>';
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
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

// Modal
function openRuleModal() {
    document.getElementById('rule-form').reset();
    document.getElementById('rule-id').value = '';
    document.getElementById('rule-modal-title').textContent = 'New Automation Rule';
    document.getElementById('rule-save-btn').textContent = 'Create Rule';
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
    document.getElementById('rule-modal-title').textContent = 'Edit Rule';
    document.getElementById('rule-save-btn').textContent = 'Save Changes';
    
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
                    <label class="form-label">Assign to</label>
                    <select id="action-user-id" class="form-control">${userOptions}</select>
                </div>`;
            break;
        case 'create_task':
            container.innerHTML = `
                <div class="form-group">
                    <label class="form-label">Task Title</label>
                    <input type="text" id="action-task-title" class="form-control" value="Follow up">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Due in (days)</label>
                        <input type="number" id="action-due-days" class="form-control" value="2" min="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select id="action-priority" class="form-control">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                </div>`;
            break;
        case 'send_email':
            container.innerHTML = `
                <div class="form-group">
                    <label class="form-label">To</label>
                    <input type="email" id="action-email-to" class="form-control" placeholder="admin@company.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Subject</label>
                    <input type="text" id="action-email-subject" class="form-control" value="New lead notification">
                </div>`;
            break;
        case 'move_deal':
            container.innerHTML = `
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Move to stage</label>
                    <select id="action-target-stage" class="form-control">
                        <option value="prospecting">Prospecting</option>
                        <option value="qualification">Qualification</option>
                        <option value="proposal">Proposal</option>
                        <option value="negotiation">Negotiation</option>
                        <option value="closed_won">Closed Won</option>
                        <option value="closed_lost">Closed Lost</option>
                    </select>
                </div>`;
            break;
        case 'notify_user':
            container.innerHTML = `
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Message</label>
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
            showNotification(ruleId ? 'Rule updated' : 'Rule created', 'success');
        } else {
            showNotification(resp.message || 'Failed to save', 'error');
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
            showNotification(el.classList.contains('active') ? 'Rule enabled' : 'Rule disabled', 'success');
        }
    });
}

function deleteRule(ruleId) {
    showConfirm('Delete this automation rule?', function() {
        fetch(`${API}?action=delete`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ rule_id: ruleId, csrf_token: CSRF_TOKEN })
        }).then(r => r.json()).then(resp => {
            if (resp.success) {
                loadRules();
                showNotification('Rule deleted', 'success');
            } else {
                showNotification(resp.message || 'Failed to delete', 'error');
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
