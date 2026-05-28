<?php
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$pageTitle = 'Task';
$currentPage = 'tasks';
$taskId = intval($_GET['id'] ?? 0);
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:28px; padding:0; }
.page-header h1 { margin:0; font-size:22px; font-weight:600; letter-spacing:-0.3px; }
.page-header .header-actions { display:flex; gap:10px; }
.card { background:#fff; border:1px solid #e5e5e7; border-radius:12px; padding:24px; margin-bottom:16px; }
.card-title { font-size:15px; font-weight:600; color:#1d1d1f; margin:0 0 20px; }
.form-label { display:block; font-size:13px; font-weight:500; color:#424245; margin-bottom:6px; }
.form-control { width:100%; padding:10px 12px; border:1px solid #d2d2d7; border-radius:8px; font-size:14px; color:#1d1d1f; background:#fff; box-sizing:border-box; transition:border-color 0.2s; }
.form-control:focus { outline:none; border-color:#0071e3; box-shadow:0 0 0 3px rgba(0,113,227,0.15); }
textarea.form-control { min-height:80px; resize:vertical; }
.row-2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.row-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; }
.btn { padding:10px 18px; border-radius:8px; font-size:14px; font-weight:500; cursor:pointer; transition:all 0.2s; border:none; }
.btn-primary { background:#0071e3; color:#fff; }
.btn-primary:hover { background:#0077ed; }
.btn-primary:active { background:#006bd4; }
.btn-outline { background:#fff; color:#0071e3; border:1px solid #0071e3; }
.btn-outline:hover { background:#f5f5f7; }
.btn-danger { background:#fff; color:#ff3b30; border:1px solid #ff3b30; }
.btn-danger:hover { background:#fff5f5; }
.help-text { font-size:12px; color:#86868b; margin-top:4px; }
.status-option { padding:8px 12px; border-radius:6px; margin-bottom:6px; cursor:pointer; border:1px solid transparent; }
.status-option:hover { background:#f5f5f7; }
.status-option.selected { border-color:#0071e3; background:rgba(0,113,227,0.05); }
</style>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;">
        <a href="/pages/tasks.php" class="btn btn-outline" style="padding:8px 14px;">← Back</a>
        <h1><?= $taskId ? 'Edit Task' : 'New Task' ?></h1>
    </div>
    <div class="header-actions">
        <a href="/pages/tasks.php" class="btn btn-outline" onclick="return confirm('Discard changes?')">Cancel</a>
        <button type="button" class="btn btn-primary" onclick="saveTask()">Save Task</button>
    </div>
</div>

<div style="max-width:900px;">
    <div class="card">
        <h3 class="card-title">Task Information</h3>
        <div class="row-2">
            <div class="form-group">
                <label class="form-label">Task Title *</label>
                <input type="text" id="taskTitle" class="form-control" placeholder="What needs to be done?">
            </div>
            <div class="form-group">
                <label class="form-labelStatus">Status</label>
                <select id="taskStatus" class="form-control">
                    <option value="todo">To Do</option>
                    <option value="in_progress">In Progress</option>
                    <option value="review">Review</option>
                    <option value="done">Done</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
        </div>
        <div style="margin-top:16px;">
            <label class="form-label">Description</label>
            <textarea id="taskDescription" class="form-control" placeholder="Add details about this task..."></textarea>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title">Assignment& Due Date</h3>
        <div class="row-3">
            <div class="form-group">
                <label class="form-label">Priority</label>
                <select id="taskPriority" class="form-control">
                    <option value="low">Low</option>
                    <option value="medium" selected>Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Assigned To</label>
                <select id="taskAssignedTo" class="form-control">
                    <option value="">Unassigned</option>
                    <option value="1">Pinpoint Admin</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Due Date</label>
                <input type="date" id="taskDueDate" class="form-control">
            </div>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = "<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>";
const TASK_ID = <?= $taskId ?>;
let currentUsers = [];

document.addEventListener('DOMContentLoaded', function() {
    loadUsers();
    if (TASK_ID) {
        loadTask();
    } else {
        document.getElementById('taskDueDate').value = new Date(Date.now() + 7*24*60*60*1000).toISOString().split('T')[0];
    }
});

function loadUsers() {
    fetch('/api/users.php?action=list', { credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.users) {
            currentUsers = data.users;
            var select = document.getElementById('taskAssignedTo');
            select.innerHTML = '<option value="">Unassigned</option>';
            currentUsers.forEach(function(u) {
                select.innerHTML += '<option value="' + u.user_id + '">' + escapeHtml(u.full_name || u.email) + '</option>';
            });
        }
    });
}

function loadTask() {
    fetch('/api/tasks.php?id=' + TASK_ID, { credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.task) {
            var t = data.task;
            document.getElementById('taskTitle').value = t.title || '';
            document.getElementById('taskStatus').value = t.status || 'todo';
            document.getElementById('taskPriority').value = t.priority || 'medium';
            document.getElementById('taskDescription').value = t.description || '';
            document.getElementById('taskDueDate').value = t.due_date || '';
            if (t.assigned_to) {
                document.getElementById('taskAssignedTo').value = t.assigned_to;
            }
        } else {
            showNotification('Failed to load task', 'error');
        }
    });
}

function saveTask() {
    var title = document.getElementById('taskTitle').value.trim();
    if (!title) {
        showNotification('Title is required', 'error');
        return;
    }
    
    var payload = {
        csrf_token: CSRF_TOKEN,
        title: title,
        status: document.getElementById('taskStatus').value,
        priority: document.getElementById('taskPriority').value,
        description: document.getElementById('taskDescription').value,
        due_date: document.getElementById('taskDueDate').value,
        assigned_to: document.getElementById('taskAssignedTo').value || null
    };
    
    if (TASK_ID) {
        payload.task_id = TASK_ID;
    }
    
    fetch('/api/tasks.php?action=save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        showNotification(data.message || (data.success ? 'Task saved!' : 'Save failed'), data.success ? 'success' : 'error');
        if (data.success && !TASK_ID) {
            window.location.href = '/pages/tasks.php?saved=1';
        } else if (data.success) {
            window.location.href = '/pages/tasks.php';
        }
    })
    .catch(() => showNotification('Network error', 'error'));
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[<&]/g, function(c) { return c === '<' ? '<' : '&'; });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
