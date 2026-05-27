<?php
/**
 * White Label CRM - Tasks & Follow-ups
 */
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$userId = getCurrentUserId();
$companyId = $_SESSION["company_id"] ?? null;

$pageTitle = 'Tasks';
require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$users = $db->query("SELECT user_id, full_name FROM users WHERE company_id = ? AND status = 'Active' ORDER BY full_name", [$companyId])->fetchAll();
$leads = $db->query("SELECT lead_id, company_name, contact_person FROM leads WHERE company_id = ? ORDER BY company_name LIMIT 100", [$companyId])->fetchAll();

try {
    $contacts = $db->query("SELECT contact_id, first_name, last_name FROM contacts WHERE company_id = ? ORDER BY last_name LIMIT 100", [$companyId])->fetchAll();
} catch (Exception $e) {
    $contacts = [];
}
?>

<style>
.tasks-page { max-width: 1400px; margin: 0 auto; padding: 0 20px 40px; }
.page-header { display: flex; justify-content: space-between; align-items: center; padding: 24px 0 20px; }
.page-header h1 { font-size: 22px; font-weight: 600; margin: 0; }

.btn-primary { background: #2563eb; color: white; border: none; padding: 8px 18px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; }
.btn-primary:hover { background: #1d4ed8; }
.btn-outline { background: white; border: 1px solid #d1d5db; color: #374151; padding: 8px 16px; border-radius: 6px; font-size: 13px; cursor: pointer; }
.btn-outline:hover { background: #f9fafb; }

.kanban-board { display: flex; gap: 16px; overflow-x: auto; padding-bottom: 16px; }
.kanban-col { flex: 1; min-width: 260px; max-width: 320px; background: #f9fafb; border-radius: 10px; border: 1px solid #e5e7eb; }
.kanban-col-header { padding: 14px 16px 10px; display: flex; justify-content: space-between; border-bottom: 1px solid #e5e7eb; }
.kanban-col-title { font-size: 13px; font-weight: 600; color: #6b7280; text-transform: uppercase; }
.kanban-count { font-size: 12px; background: #e5e7eb; padding: 2px 8px; border-radius: 10px; }
.kanban-col-body { padding: 10px 0; min-height: 100px; }

.task-card { background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px; margin: 0 10px 10px; cursor: pointer; transition: all 0.15s; }
.task-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,0.08); border-color: #d1d5db; }
.task-card .task-title { font-weight: 600; font-size: 14px; margin-bottom: 6px; color: #111827; }
.task-card .task-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.task-card .task-priority { font-size: 11px; padding: 2px 8px; border-radius: 4px; font-weight: 500; }
.task-card .task-priority.low { background: #ecfdf5; color: #059669; }
.task-card .task-priority.medium { background: #eff6ff; color: #2563eb; }
.task-card .task-priority.high { background: #fffbeb; color: #d97706; }
.task-card .task-priority.urgent { background: #fef2f2; color: #dc2626; }
.task-card .task-due { font-size: 11px; color: #6b7280; }
.task-card .task-link { font-size: 11px; color: #2563eb; }
.task-card .task-assignee { font-size: 11px; color: #6b7280; display: flex; align-items: center; gap: 4px; }

#taskModal.modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1000; }
#taskModal.modal.active { display: block; }
.modal-backdrop { position: absolute; inset: 0; background: rgba(0,0,0,0.5); }
.modal-content { position: relative; background: white; margin: 40px auto; border-radius: 12px; width: 640px; max-width: 95vw; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
.modal-content .modal-header { padding: 20px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
.modal-content .modal-header h3 { margin: 0; font-size: 18px; font-weight: 600; }
.modal-content .modal-body { padding: 24px; }
.modal-content .modal-footer { padding: 16px 24px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 10px; }
.btn-close { background: none; border: none; font-size: 22px; cursor: pointer; color: #9ca3af; line-height: 1; }
.btn-close:hover { color: #374151; }

.form-section-title { font-size: 13px; font-weight: 600; color: #6b7280; text-transform: uppercase; margin: 16px 0 12px; }
.form-section-title:first-child { margin-top: 0; }
.grid { display: grid; gap: 14px; }
.grid-2 { grid-template-columns: 1fr 1fr; }
.grid-3 { grid-template-columns: 1fr 1fr 1fr; }
.form-group { margin-bottom: 14px; }
.form-group:last-child { margin-bottom: 0; }
.form-label { display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 5px; }
.form-control { width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; transition: border-color 0.15s, box-shadow 0.15s; }
.form-control:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
select.form-control { height: 38px; }
textarea.form-control { resize: vertical; min-height: 80px; }
</style>

<div class="tasks-page">
    <div class="page-header">
        <h1>Tasks</h1>
        <button class="btn-primary" onclick="openTaskModal()">+ New Task</button>
    </div>
    <div class="kanban-board" id="kanban-board"></div>
</div>

<div id="taskModal" class="modal">
    <div class="modal-backdrop" onclick="closeTaskModal()"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="taskModalTitle">New Task</h3>
            <button type="button" class="btn-close" onclick="closeTaskModal()">&times;</button>
        </div>
        <form id="taskForm" onsubmit="saveTask(event)">
            <input type="hidden" id="task_id" value="">
            <div class="modal-body">
                <h4 class="form-section-title">Task Details</h4>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">Title <span style="color:#dc2626">*</span></label>
                        <input type="text" id="task_title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Assigned To</label>
                        <select id="task_assigned_to" class="form-control">
                            <option value="">Unassigned</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['user_id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid grid-3">
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select id="task_priority" class="form-control">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select id="task_status" class="form-control">
                            <option value="todo" selected>To Do</option>
                            <option value="in_progress">In Progress</option>
                            <option value="review">Review</option>
                            <option value="done">Done</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Due Date</label>
                        <input type="date" id="task_due_date" class="form-control">
                    </div>
                </div>

                <h4 class="form-section-title">Link To</h4>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">Related Lead</label>
                        <input type="text" id="task_lead_search" class="form-control" placeholder="Search leads..." list="lead-options" oninput="document.getElementById('task_lead_id').value = this.value ? Array.from(document.querySelectorAll('#lead-options option')).(find(o=>o.value===this.value) \u0026\u0026 find(o=>o.value===this.value).dataset).id || '' : ''">
                        <input type="hidden" id="task_lead_id" value="">
                        <datalist id="lead-options">
                            <?php foreach ($leads as $lead): ?>
                                <option data-id="<?php echo $lead['lead_id']; ?>" value="<?php echo htmlspecialchars($lead['company_name'] . ($lead['contact_person'] ? ' (' . $lead['contact_person'] . ')' : '')); ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Related Contact</label>
                        <input type="text" id="task_contact_search" class="form-control" placeholder="Search contacts..." list="contact-options" oninput="document.getElementById('task_contact_id').value = this.value ? Array.from(document.querySelectorAll('#contact-options option')).(find(o=>o.value===this.value) \u0026\u0026 find(o=>o.value===this.value).dataset).id || '' : ''">
                        <input type="hidden" id="task_contact_id" value="">
                        <datalist id="contact-options">
                            <?php foreach ($contacts as $contact): ?>
                                <option data-id="<?php echo $contact['contact_id']; ?>" value="<?php echo htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']); ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>

                <h4 class="form-section-title">Description</h4>
                <div class="form-group">
                    <textarea id="task_description" class="form-control" rows="4" placeholder="Add task details..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-outline" onclick="closeTaskModal()">Cancel</button>
                <button type="submit" class="btn-primary" id="taskSaveBtn">Create Task</button>
            </div>
        </form>
    </div>
</div>

<script>
const API = '/api/tasks.php';
const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
let tasks = [];
let usersMap = {};

<?php foreach ($users as $user): ?>
usersMap[<?php echo $user['user_id']; ?>] = '<?php echo htmlspecialchars(addslashes($user['full_name'])); ?>';
<?php endforeach; ?>

document.addEventListener('DOMContentLoaded', loadTasks);

function loadTasks() {
    fetch(`${API}?action=list`, { credentials: 'same-origin' })
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
    const cols = [
        { key: 'todo', label: 'To Do', color: '#6b7280' },
        { key: 'in_progress', label: 'In Progress', color: '#2563eb' },
        { key: 'review', label: 'Review', color: '#d97706' },
        { key: 'done', label: 'Done', color: '#059669' }
    ];
    
    board.innerHTML = cols.map(col => {
        const colTasks = tasks.filter(t => t.status === col.key);
        return `             <div class="kanban-col">                 <div class="kanban-col-header">                     <span class="kanban-col-title" style="color:${col.color}">${col.label}</span>                     <span class="kanban-count">${colTasks.length}</span>                 </div>                 <div class="kanban-col-body">                     ${colTasks.map(t => `
                        <div class="task-card" onclick="openTaskModal(${t.task_id})">
                            <div class="task-title">${escapeHtml(t.title)}</div>
                            <div class="task-meta">
                                <span class="task-priority ${t.priority}">${t.priority}</span>
                                ${t.due_date ? `<span class="task-due">${formatDate(t.due_date)}</span>` : ''}
                                ${t.assigned_to ? `<span class="task-assignee">${escapeHtml(usersMap[t.assigned_to] || 'Unknown')}</span>` : ''}
                            </div>
                            ${t.lead_id ? '<div class="task-link">Lead #' + t.lead_id + '</div>' : ''}
                            ${t.contact_id ? '<div class="task-link">Contact #' + t.contact_id + '</div>' : ''}
                        </div>
                    `).join('')}                 </div>             </div>`;
    }).join('');
}

function openTaskModal(taskId) {
    document.getElementById('taskModal').classList.add('active');
    document.getElementById('taskForm').reset();
    document.getElementById('task_id').value = '';
    document.getElementById('taskModalTitle').textContent = 'New Task';
    document.getElementById('taskSaveBtn').textContent = 'Create Task';
    document.getElementById('task_lead_search').value = '';
    document.getElementById('task_contact_search').value = '';
    
    if (taskId) {
        const t = tasks.find(x => x.task_id == taskId);
        if (t) {
            document.getElementById('task_id').value = t.task_id;
            document.getElementById('task_title').value = t.title || '';
            document.getElementById('task_priority').value = t.priority || 'medium';
            document.getElementById('task_status').value = t.status || 'todo';
            document.getElementById('task_assigned_to').value = t.assigned_to || '';
            document.getElementById('task_due_date').value = t.due_date || '';
            document.getElementById('task_lead_id').value = t.lead_id || '';
            document.getElementById('task_contact_id').value = t.contact_id || '';
            document.getElementById('task_description').value = t.description || '';
            document.getElementById('taskModalTitle').textContent = 'Edit Task';
            document.getElementById('taskSaveBtn').textContent = 'Save Changes';
            
            // Set search inputs from datalist
            if (t.lead_id) {
                const leadOpt = document.querySelector('#lead-options option[data-id="' + t.lead_id + '"]');
                if (leadOpt) document.getElementById('task_lead_search').value = leadOpt.value;
            }
            if (t.contact_id) {
                const contactOpt = document.querySelector('#contact-options option[data-id="' + t.contact_id + '"]');
                if (contactOpt) document.getElementById('task_contact_search').value = contactOpt.value;
            }
        }
    }
}

function closeTaskModal() {
    document.getElementById('taskModal').classList.remove('active');
}

function saveTask(e) {
    e.preventDefault();
    const data = {
        csrf_token: CSRF_TOKEN,
        title: document.getElementById('task_title').value,
        description: document.getElementById('task_description').value,
        priority: document.getElementById('task_priority').value,
        status: document.getElementById('task_status').value,
        assigned_to: document.getElementById('task_assigned_to').value || null,
        due_date: document.getElementById('task_due_date').value || null,
        lead_id: document.getElementById('task_lead_id').value || null,
        contact_id: document.getElementById('task_contact_id').value || null
    };
    const taskId = document.getElementById('task_id').value;
    if (taskId) data.task_id = taskId;
    
    fetch(`${API}?action=${taskId ? 'update' : 'create'}`, {
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
            showNotification(taskId ? 'Task updated successfully' : 'Task created successfully', 'success');
        } else {
            showNotification(resp.message || 'Failed to save task', 'error');
        }
    })
    .catch(function() {
        showNotification('An error occurred while saving', 'error');
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeTaskModal();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
