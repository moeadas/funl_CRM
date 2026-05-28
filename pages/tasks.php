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

$pageTitle = 'Tasks';
$css = ['tasks'];
$js = ['tasks'];

require_once __DIR__ . '/../includes/header.php';

// Helper: format due date relative to today



?>

<style>
.tasks-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px 40px;
}
.tasks-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 24px 0 20px;
    gap: 16px;
}
.tasks-header h1 {
    font-size: 22px;
    font-weight: 600;
    color: var(--text-primary, #1f2937);
    margin: 0;
}
.tasks-filters {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.tasks-filters select, .tasks-filters input {
    padding: 7px 12px;
    border: 1px solid var(--border, #d1d5db);
    border-radius: 6px;
    font-size: 13px;
    background: white;
    color: var(--text-primary, #1f2937);
}
.tasks-filters input {
    width: 160px;
}
.btn-new-task {
    background: var(--primary, #2563eb);
    color: white;
    border: none;
    padding: 8px 18px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}
.btn-new-task:hover { background: var(--primary-dark, #1d4ed8); }

/* Kanban Board */
.kanban-board {
    display: flex;
    gap: 16px;
    overflow-x: auto;
    padding-bottom: 16px;
    min-height: calc(100vh - 180px);
}
.kanban-col {
    flex: 1;
    min-width: 260px;
    max-width: 320px;
    display: flex;
    flex-direction: column;
    background: var(--bg-secondary, #f9fafb);
    border-radius: 10px;
    border: 1px solid var(--border, #e5e7eb);
}
.kanban-col-header {
    padding: 14px 16px 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--border, #e5e7eb);
}
.kanban-col-title {
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-secondary, #6b7280);
}
.kanban-count {
    background: var(--bg-tertiary, #e5e7eb);
    color: var(--text-secondary, #6b7280);
    font-size: 12px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 12px;
}
.kanban-col.todo .kanban-col-title { color: #6b7280; }
.kanban-col.in_progress .kanban-col-title { color: #2563eb; }
.kanban-col.review .kanban-col-title { color: #9333ea; }
.kanban-col.done .kanban-col-title { color: #16a34a; }
.kanban-col.cancelled .kanban-col-title { color: #dc2626; }

.kanban-col-body {
    padding: 12px;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 10px;
    overflow-y: auto;
    max-height: calc(100vh - 240px);
}

/* Task Card */
.task-card {
    background: white;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 8px;
    padding: 14px;
    cursor: grab;
    transition: box-shadow 0.15s, transform 0.15s;
}
.task-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transform: translateY(-1px);
}
.task-card.dragging { opacity: 0.5; cursor: grabbing; }
.task-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 8px;
}
.task-title {
    font-size: 14px;
    font-weight: 500;
    color: var(--text-primary, #1f2937);
    line-height: 1.4;
    flex: 1;
}
.task-actions {
    display: flex;
    gap: 4px;
    opacity: 0;
    transition: opacity 0.15s;
}
.task-card:hover .task-actions { opacity: 1; }
.task-actions button {
    background: none;
    border: none;
    padding: 4px;
    cursor: pointer;
    font-size: 14px;
    border-radius: 4px;
    color: var(--text-secondary, #6b7280);
}
.task-actions button:hover { background: var(--bg-secondary, #f3f4f6); }

.task-meta {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 12px;
    color: var(--text-secondary, #6b7280);
}
.task-meta-row {
    display: flex;
    align-items: center;
    gap: 6px;
}
.task-meta-icon { font-size: 11px; }
.task-priority {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}
.priority-urgent { background: #fef2f2; color: #dc2626; }
.priority-high { background: #fff7ed; color: #ea580c; }
.priority-medium { background: #f0fdf4; color: #16a34a; }
.priority-low { background: #f3f4f6; color: #6b7280; }
.due-label { font-weight: 500; }
.due-label.overdue { color: #dc2626; }
.due-label.today { color: #ea580c; }
.due-label.tomorrow { color: #2563eb; }
.due-label.muted { color: var(--text-secondary, #9ca3af); }
.due-label.scheduled { color: var(--text-secondary, #6b7280); }

.task-assignee {
    display: flex;
    align-items: center;
    gap: 6px;
}
.task-avatar {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: var(--primary, #2563eb);
    color: white;
    font-size: 10px;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
}
.task-lead-link {
    color: var(--primary, #2563eb);
    text-decoration: none;
    font-size: 12px;
}
.task-lead-link:hover { text-decoration: underline; }

/* Empty State */
.kanban-empty {
    text-align: center;
    padding: 24px 16px;
    color: var(--text-secondary, #9ca3af);
    font-size: 13px;
}

/* Modal */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    display: none;
}
.modal-overlay.active { display: flex; }
.modal {
    background: white;
    border-radius: 12px;
    width: 520px;
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
    color: var(--text-primary, #1f2937);
}
.modal-close {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    padding: 4px;
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
}
.form-control:focus {
    outline: none;
    border-color: var(--primary, #2563eb);
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}
textarea.form-control { min-height: 80px; resize: vertical; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-actions {
    padding: 16px 24px;
    border-top: 1px solid var(--border, #e5e7eb);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
.btn-secondary {
    background: white;
    border: 1px solid var(--border, #d1d5db);
    color: var(--text-primary, #374151);
    padding: 9px 18px;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
}
.btn-secondary:hover { background: var(--bg-secondary, #f9fafb); }
.btn-primary {
    background: var(--primary, #2563eb);
    color: white;
    border: none;
    padding: 9px 18px;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
}
.btn-primary:hover { background: var(--primary-dark, #1d4ed8); }

/* Overdue section */
.kanban-col.overdue-col { border-color: #fecaca; }
.kanban-col.overdue-col .kanban-col-header { background: #fef2f2; border-radius: 10px 10px 0 0; }

/* Drag placeholder */
.task-placeholder {
    border: 2px dashed var(--primary, #2563eb);
    border-radius: 8px;
    background: rgba(37,99,235,0.05);
    min-height: 80px;
}
</style>

<div class="tasks-page">
    <div class="tasks-header">
        <h1>Tasks</h1>
        <div class="tasks-filters">
            <input type="text" id="task-search" placeholder="Search tasks..." oninput="loadTasks()">
            <select id="task-assignee-filter" onchange="loadTasks()">
                <option value="">All Team Members</option>
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

<!-- Task Modal -->
<div class="modal-overlay" id="task-modal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="modal-title">New Task</h2>
            <button class="modal-close" onclick="closeTaskModal()">&times;</button>
        </div>
        <form id="task-form" onsubmit="saveTask(event)">
            <div class="modal-body">
                <input type="hidden" id="task-id" value="">
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" id="task-title" class="form-control" required placeholder="What needs to be done?">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea id="task-description" class="form-control" placeholder="Additional details..."></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select id="task-priority" class="form-control">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select id="task-status" class="form-control">
                            <option value="todo">To Do</option>
                            <option value="in_progress">In Progress</option>
                            <option value="review">Review</option>
                            <option value="done">Done</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Assigned To</label>
                        <select id="task-assigned-to" class="form-control">
                            <option value="">Unassigned</option>
                            <?php foreach ($db->query("SELECT user_id, full_name FROM users WHERE company_id = ? AND status = 'Active' ORDER BY full_name", [$companyId]) as $u): ?>
                                <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Linked Lead</label>
                        <select id="task-lead-id" class="form-control">
                            <option value="">None</option>
                            <?php foreach ($db->query("SELECT lead_id, company_name FROM leads WHERE company_id = ? ORDER BY company_name LIMIT 100", [$companyId]) as $l): ?>
                                <option value="<?= $l['lead_id'] ?>"><?= htmlspecialchars($l['company_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Due Date</label>
                        <input type="date" id="task-due-date" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Follow-up Date</label>
                        <input type="date" id="task-follow-up" class="form-control">
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeTaskModal()">Cancel</button>
                <button type="submit" class="btn-primary" id="task-save-btn">Create Task</button>
            </div>
        </form>
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
                <span class="kanban-col-title">${col.label}</span>
                <span class="kanban-count" id="count-${col.status}">${colTasks.length}</span>
            </div>
            <div class="kanban-col-body" ondragover="onDragOver(event)" ondrop="onDrop(event, '${col.status}')">
                ${colTasks.length ? colTasks.map(t => renderCard(t)).join('') : '<div class="kanban-empty">No tasks</div>'}
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
            <a href="/pages/task-form.php?id=${task.task_id}" class="task-title-link">${escapeHtml(task.title)}</a>
            <div class="task-actions" onclick="event.stopPropagation()">
                <button onclick="deleteTask(${task.task_id})" title="Delete">&times;</button>
            </div>
        </div>
        <div class="task-meta">
            <div class="task-meta-row">
                <span class="task-priority ${pClass}">${task.priority}</span>
                ${due ? `<span class="task-meta-icon">📅</span><span class="due-label ${due.class}">${due.label}</span>` : ''}
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

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ── CRITICAL FIX: dueLabel() was called but never defined ──
function dueLabel(dateStr) {
    if (!dateStr) return null;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);
    const due = new Date(dateStr + 'T00:00:00');
    if (isNaN(due)) return null;
    if (due < today) return { label: 'Overdue: ' + formatDate(due), class: 'overdue' };
    if (+due === +today) return { label: 'Due today', class: 'today' };
    if (+due === +tomorrow) return { label: 'Due tomorrow', class: 'tomorrow' };
    return { label: 'Due ' + formatDate(due), class: 'scheduled' };
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

function onDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
}

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
            showNotification('Task moved', 'success');
        } else {
            showNotification(resp.message || 'Failed to move task', 'error');
        }
    });
}

// Modal
function openTaskModal(taskId) {
    const modal = document.getElementById('task-modal');
    const form = document.getElementById('task-form');
    const title = document.getElementById('modal-title');
    const saveBtn = document.getElementById('task-save-btn');
    
    form.reset();
    document.getElementById('task-id').value = '';
    
    if (taskId) {
        const task = tasks.find(t => t.task_id == taskId);
        if (task) {
            title.textContent = 'Edit Task';
            saveBtn.textContent = 'Save Changes';
            document.getElementById('task-id').value = task.task_id;
            document.getElementById('task-title').value = task.title || '';
            document.getElementById('task-description').value = task.description || '';
            document.getElementById('task-priority').value = task.priority || 'medium';
            document.getElementById('task-status').value = task.status || 'todo';
            document.getElementById('task-assigned-to').value = task.assigned_to || '';
            document.getElementById('task-lead-id').value = task.lead_id || '';
            document.getElementById('task-due-date').value = task.due_date || '';
            document.getElementById('task-follow-up').value = task.follow_up_date || '';
        }
    } else {
        title.textContent = 'New Task';
        saveBtn.textContent = 'Create Task';
    }
    
    modal.classList.add('active');
}

function closeTaskModal() {
    document.getElementById('task-modal').classList.remove('active');
}

function saveTask(e) {
    e.preventDefault();
    const taskId = document.getElementById('task-id').value;
    const action = taskId ? 'update' : 'create';
    
    const data = {
        csrf_token: CSRF_TOKEN,
        title: document.getElementById('task-title').value,
        description: document.getElementById('task-description').value,
        priority: document.getElementById('task-priority').value,
        status: document.getElementById('task-status').value,
        assigned_to: document.getElementById('task-assigned-to').value || null,
        lead_id: document.getElementById('task-lead-id').value || null,
        due_date: document.getElementById('task-due-date').value || null,
        follow_up_date: document.getElementById('task-follow-up').value || null,
    };
    if (taskId) data.task_id = taskId;
    
    fetch(`${API}?action=${action}`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(resp => {
        if (resp.success) {
            closeTaskModal();
            loadTasks();
            showNotification(taskId ? 'Task updated' : 'Task created', 'success');
        } else {
            showNotification(resp.message || 'Failed to save task', 'error');
        }
    });
}

function deleteTask(taskId) {
    if (!confirm('Delete this task?')) return;
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
            showNotification('Task deleted', 'success');
        } else {
            showNotification(resp.message || 'Failed to delete', 'error');
        }
    });
}

// Close modal on escape / overlay click
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeTaskModal(); });
document.querySelector('.modal-overlay').addEventListener('click', e => { if (e.target === e.currentTarget) closeTaskModal(); });

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
