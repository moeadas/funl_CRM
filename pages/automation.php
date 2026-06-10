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
?>



<div class="automation-page">
    <div class="page-header">
        <h1 class="page-title"><?php echo __('Automation'); ?></h1>
        <a href="/pages/automation-rule.php" class="btn btn-primary">+ <?php echo __('New Rule'); ?></a>
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

<script>
const COMPANY_ID = <?= json_encode($companyId) ?>;
const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
const API = '/api/automation.php';

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

function editRule(ruleId) {
    window.location.href = '/pages/automation-rule.php?id=' + ruleId;
}

function updateActionOptions() {
    /* Moved to automation-rule.php — no longer used on this page */
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
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
