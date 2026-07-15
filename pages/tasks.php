<?php
/**
 * White Label CRM - Tasks & Follow-ups
 * Clean kanban-style task board
 */
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

// Initialize database
$db = Database::getInstance();

$userId = getCurrentUserId();
$companyId = $_SESSION["company_id"] ?? null;
$userRole = $_SESSION["role"] ?? "";

$pageTitle = __('Tasks');
$css = ['tasks'];
$js = ['tasks'];

require_once __DIR__ . '/../includes/header.php';

// Helper: format due date relative to today



?><div class="tasks-page">
    <div class="tasks-header">
        <h1 class="page-title"><?php echo htmlspecialchars(__('Tasks')); ?></h1>
        <div class="tasks-filters">
            <input type="text" id="task-search" placeholder="<?php echo htmlspecialchars(__('Search tasks...')); ?>" oninput="loadTasks()">
            <select id="task-assignee-filter" onchange="loadTasks()">
                <option value=""><?php echo htmlspecialchars(__('All Team Members')); ?></option>
                <?php foreach ($db->query("SELECT user_id, full_name FROM users WHERE company_id = ? AND status = 'Active' ORDER BY full_name", [$companyId]) as $u): ?>
                    <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <a href="/pages/task-form.php" class="btn-new-task" style="text-decoration:none">+ New Task</a>
        </div>
    </div>

    <div class="kanban-board" id="kanban-board">
        <!-- Columns rendered by JS -->
    </div>
</div>

<script>
const COMPANY_ID = <?= json_encode($companyId) ?>;
const USER_ID = <?= json_encode($userId) ?>;
const USER_ROLE = <?= json_encode($userRole) ?>;
const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
const API = '/api/tasks.php';

const COLUMNS = [
    { id: 'todo',        label: 'To Do',      status: 'todo' },
    { id: 'in_progress', label: 'In Progress', status: 'in_progress' },
    { id: 'review',      label: 'Review',     status: 'review' },
    { id: 'done',        label: 'Done',        status: 'done' },
    { id: 'cancelled',   label: 'Cancelled',  status: 'cancelled' },
];

let tasks = [];
let draggedCard = null;

// Init
document.addEventListener('DOMContentLoaded', loadTasks);

function loadTasks() {
    const search = document.getElementById('task-search')?.value || '';
    const assignee = document.getElementById('task-assignee-filter')?.value || '';
    
    let url = `${API}?action=list`;
    if (search) url += `&search=${encodeURIComponent(search)}`;
    if (assignee) url += `&assigned_to=${assignee}`;
    
    fetch(url, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(resp => {
            if (resp.success) {
                tasks = resp.data || [];
                renderBoard();
            }
        });
}

function renderBoard() {
    const board = document.getElementById('kanban-board');
    board.innerHTML = COLUMNS.map(col => {
        const colTasks = tasks.filter(t => t.status === col.status);
        const isOverdue = col.status === 'todo' && colTasks.some(t => t.due_date && t.due_date < new Date().toISOString().split('T')[0]);
        const colClass = isOverdue ? 'kanban-col overdue-col' : 'kanban-col';
        
        return `
        <div class="${colClass}" data-status="${col.status}">
            <div class="kanban-col-header">
                <span class="kanban-col-title">${escapeHtml(__(col.label))}</span>
                <span class="kanban-count" id="count-${col.status}">${colTasks.length}</span>
            </div>
            <div class="kanban-col-body" ondragover="onDragOver(event)" ondrop="onDrop(event, '${col.status}')">
                ${colTasks.length ? colTasks.map(t => renderCard(t)).join('') : '<div class="kanban-empty">' + escapeHtml(__('No tasks')) + '</div>'}
            </div>
        </div>`;
    }).join('');
}

function renderCard(task) {
    const pClass = `priority-${task.priority}`;
    const due = task.due_date ? dueLabel(task.due_date) : null;
    const initials = (task.assigned_name || '').split(' ').map(w => w[0]).join('').toUpperCase().slice(0,2) || '?';
    const leadLink = task.lead_id ? `<a href="/pages/leads.php?view=${task.lead_id}" class="task-lead-link">${task.lead_name || 'Lead #' + task.lead_id}</a>` : '';
    
    return `
    <div class="task-card" draggable="true" data-id="${task.task_id}"
         ondragstart="onDragStart(event)" ondragend="onDragEnd(event)">
        <div class="task-card-header">
            <a href="/pages/task-form.php?id=${task.task_id}" class="task-title" style="text-decoration:none;">${escapeHtml(task.title)}</a>
            <div class="task-actions" onclick="event.stopPropagation()">
                <!-- Editing was only reachable by clicking the task title, which
                     wasn't discoverable. Surface it as an explicit action. -->
                <button onclick="editTask(${task.task_id})" title="${escapeHtml(__('Edit'))}" aria-label="${escapeHtml(__('Edit'))}">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button onclick="deleteTask(${task.task_id})" title="${escapeHtml(__('Delete'))}" aria-label="${escapeHtml(__('Delete'))}">&times;</button>
            </div>
        </div>
        <div class="task-meta">
            <div class="task-meta-row">
                <span class="task-priority ${pClass}">${escapeHtml(__(task.priority))}</span>
                ${due ? `<span class="task-meta-icon">📅</span><span class="due-label ${due.class}">${escapeHtml(due.label)}</span>` : ''}
            </div>
            ${task.assigned_name ? `
            <div class="task-meta-row">
                <div class="task-avatar">${initials}</div>
                <span>${escapeHtml(task.assigned_name)}</span>
            </div>` : ''}
            ${leadLink ? `<div class="task-meta-row">${leadLink}</div>` : ''}
        </div>
    </div>`;
}

function editTask(taskId) {
    window.location.href = '/pages/task-form.php?id=' + taskId;
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function dueLabel(dateStr) {
    if (!dateStr) return null;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);
    const due = new Date(dateStr + 'T00:00:00');
    if (isNaN(due)) return null;
    if (due < today) return { label: __('Overdue') + ': ' + formatDate(due), class: 'overdue' };
    if (+due === +today) return { label: __('Due today'), class: 'today' };
    if (+due === +tomorrow) return { label: __('Due tomorrow'), class: 'tomorrow' };
    return { label: __('due') + ' ' + formatDate(due), class: 'scheduled' };
}

function formatDate(date) {
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

// Drag & Drop
function onDragStart(e) {
    draggedCard = e.target;
    e.target.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
}

function onDragEnd(e) {
    e.target.classList.remove('dragging');
    draggedCard = null;
}

// Drag over handler
function onDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
}

// Drop handler
function onDrop(e, newStatus) {
    e.preventDefault();
    if (!draggedCard) return;
    
    const taskId = draggedCard.dataset.id;
    moveTask(taskId, newStatus);
}

function moveTask(taskId, newStatus) {
    fetch(`${API}?action=move`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ task_id: taskId, status: newStatus, csrf_token: CSRF_TOKEN })
    })
    .then(r => r.json())
    .then(resp => {
        if (resp.success) {
            const task = tasks.find(t => t.task_id == taskId);
            if (task) task.status = newStatus;
            renderBoard();
            showNotification(__('Task moved'), 'success');
        } else {
            showNotification(resp.message || __('Failed to move task'), 'error');
        }
    });
}

function deleteTask(taskId) {
    showConfirm(__('are_you_sure_you_want_to_delete_this_task'), function() {
        fetch(`${API}?action=delete`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ task_id: taskId, csrf_token: CSRF_TOKEN })
        })
        .then(r => r.json())
        .then(resp => {
            if (resp.success) {
                tasks = tasks.filter(t => t.task_id != taskId);
                renderBoard();
                showNotification(__('Task deleted'), 'success');
            } else {
                showNotification(resp.message || __('Failed to delete task'), 'error');
            }
        });
    });
}

// Notification fallback
if (typeof showNotification !== 'function') {
    window.showNotification = (msg, type) => {
        const div = document.createElement('div');
        div.className = 'eb-toast eb-toast-' + (type || 'info');
        div.style.cssText = 'position:fixed;top:16px;right:16px;z-index:99999;padding:12px 20px;border-radius:8px;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,.15);color:#fff;background:' + (type === 'error' ? '#dc2626' : type === 'success' ? '#16a34a' : '#3b82f6') + ';animation:ebToastIn .25s';
        div.textContent = msg;
        document.body.appendChild(div);
        setTimeout(() => { div.style.opacity = '0'; setTimeout(() => div.remove(), 300); }, 3000);
    };
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
