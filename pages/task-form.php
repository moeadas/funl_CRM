<?php
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$pageTitle = __('Task');
$currentPage = 'tasks';
$taskId = intval($_GET['id'] ?? 0);
require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$companyId = $_SESSION["company_id"] ?? null;

// Fetch users & leads directly in PHP to render options
$users = $db->query("SELECT user_id, full_name FROM users WHERE company_id = ? AND status = 'Active' ORDER BY full_name", [$companyId])->fetchAll();
$leads = $db->query("SELECT lead_id, company_name FROM leads WHERE company_id = ? ORDER BY company_name", [$companyId])->fetchAll();
?>

<style>
.page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:28px; padding:0; }
.page-header h1 { margin:0; font-size:22px; font-weight:600; letter-spacing:-0.3px; color: var(--color-text); }
.page-header .header-actions { display:flex; gap:10px; }
.card { background: var(--color-surface); border:1px solid var(--color-border); border-radius: var(--radius-md); padding:24px; margin-bottom:16px; box-shadow: var(--shadow-xs); }
.card-title { font-size:15px; font-weight:600; color: var(--color-text); margin:0 0 20px; }
.form-label { display:block; font-size:13px; font-weight:500; color: var(--color-text); margin-bottom:6px; }
.form-control { width:100%; padding:10px 12px; border:1px solid var(--color-border); border-radius: var(--radius-sm); font-size:14px; color: var(--color-text); background: var(--color-surface); box-sizing:border-box; transition: border-color var(--transition); }
.form-control:focus { outline:none; border-color: var(--color-accent); box-shadow:0 0 0 3px rgba(0,113,227,0.15); }
textarea.form-control { min-height:80px; resize:vertical; }
.row-2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.row-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; }
.btn { padding:10px 18px; border-radius: var(--radius-sm); font-size:14px; font-weight:500; cursor:pointer; transition: all var(--transition); border:none; text-decoration:none; display:inline-block; }
.btn-primary { background: var(--color-accent); color:#fff; }
.btn-primary:hover { background: var(--color-accent-hover); }
.btn-outline { background: var(--color-surface); color: var(--color-accent); border:1px solid var(--color-border); }
.btn-outline:hover { background: var(--color-bg); }
.btn-danger { background: var(--color-surface); color: #dc2626; border:1px solid #fca5a5; }
.btn-danger:hover { background: #fef2f2; }
</style>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;">
        <a href="/pages/tasks.php" class="btn btn-outline" style="padding:8px 14px;">← <?php echo htmlspecialchars(__('Back to Board')); ?></a>
        <h1><?= $taskId ? htmlspecialchars(__('Edit Task')) : htmlspecialchars(__('New Task')) ?></h1>
    </div>
    <div class="header-actions">
        <?php if ($taskId): ?>
            <button type="button" class="btn btn-danger" onclick="deleteTask()"><?php echo htmlspecialchars(__('Delete Task')); ?></button>
        <?php endif; ?>
        <button type="button" class="btn btn-primary" onclick="saveTask()"><?php echo htmlspecialchars(__('Save Task')); ?></button>
    </div>
</div>

<div style="max-width:900px;">
    <div class="card">
        <h3 class="card-title"><?php echo htmlspecialchars(__('Task Details')); ?></h3>
        <div class="row-2">
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Task Title *')); ?></label>
                <input type="text" id="taskTitle" class="form-control" placeholder="<?php echo htmlspecialchars(__('What needs to be done?')); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Status')); ?></label>
                <select id="taskStatus" class="form-control">
                    <option value="todo"><?php echo htmlspecialchars(__('To Do')); ?></option>
                    <option value="in_progress"><?php echo htmlspecialchars(__('In Progress')); ?></option>
                    <option value="review"><?php echo htmlspecialchars(__('Review')); ?></option>
                    <option value="done"><?php echo htmlspecialchars(__('Done')); ?></option>
                    <option value="cancelled"><?php echo htmlspecialchars(__('Cancelled')); ?></option>
                </select>
            </div>
        </div>
        <div style="margin-top:16px;">
            <label class="form-label"><?php echo htmlspecialchars(__('Description')); ?></label>
            <textarea id="taskDescription" class="form-control" placeholder="<?php echo htmlspecialchars(__('Add details about this task...')); ?>"></textarea>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title"><?php echo htmlspecialchars(__('Assignment & Due Date')); ?></h3>
        <div class="row-3">
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Priority')); ?></label>
                <select id="taskPriority" class="form-control">
                    <option value="low"><?php echo htmlspecialchars(__('Low')); ?></option>
                    <option value="medium" selected><?php echo htmlspecialchars(__('Medium')); ?></option>
                    <option value="high"><?php echo htmlspecialchars(__('High')); ?></option>
                    <option value="urgent"><?php echo htmlspecialchars(__('Urgent')); ?></option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Assigned To')); ?></label>
                <select id="taskAssignedTo" class="form-control">
                    <option value=""><?php echo htmlspecialchars(__('Unassigned')); ?></option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Due Date')); ?></label>
                <input type="date" id="taskDueDate" class="form-control">
            </div>
        </div>
        <div class="row-2" style="margin-top: 16px;">
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Linked Lead')); ?></label>
                <select id="taskLeadId" class="form-control">
                    <option value=""><?php echo htmlspecialchars(__('None')); ?></option>
                    <?php foreach ($leads as $l): ?>
                        <option value="<?= $l['lead_id'] ?>"><?= htmlspecialchars($l['company_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Follow-up Date')); ?></label>
                <input type="date" id="taskFollowUp" class="form-control">
            </div>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = "<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>";
const TASK_ID = <?= $taskId ?>;

document.addEventListener('DOMContentLoaded', function() {
    if (TASK_ID) {
        loadTask();
    } else {
        // Set default due date to 7 days from now for new tasks
        document.getElementById('taskDueDate').value = new Date(Date.now() + 7*24*60*60*1000).toISOString().split('T')[0];
    }
});

function loadTask() {
    fetch('/api/tasks.php?action=detail&id=' + TASK_ID, { credentials: 'same-origin' })
    .then(r => r.json())
    .then(resp => {
        if (resp.success && resp.data && resp.data.task) {
            var t = resp.data.task;
            document.getElementById('taskTitle').value = t.title || '';
            document.getElementById('taskStatus').value = t.status || 'todo';
            document.getElementById('taskPriority').value = t.priority || 'medium';
            document.getElementById('taskDescription').value = t.description || '';
            document.getElementById('taskDueDate').value = t.due_date || '';
            document.getElementById('taskFollowUp').value = t.follow_up_date || '';
            document.getElementById('taskAssignedTo').value = t.assigned_to || '';
            document.getElementById('taskLeadId').value = t.lead_id || '';
        } else {
            showNotification(resp.message || __('Failed to load task'), 'error');
        }
    });
}

function saveTask() {
    var title = document.getElementById('taskTitle').value.trim();
    if (!title) {
        showNotification(__('title_is_required'), 'error');
        return;
    }
    
    var payload = {
        csrf_token: CSRF_TOKEN,
        title: title,
        status: document.getElementById('taskStatus').value,
        priority: document.getElementById('taskPriority').value,
        description: document.getElementById('taskDescription').value,
        due_date: document.getElementById('taskDueDate').value || null,
        follow_up_date: document.getElementById('taskFollowUp').value || null,
        assigned_to: document.getElementById('taskAssignedTo').value || null,
        lead_id: document.getElementById('taskLeadId').value || null
    };
    
    var url = '/api/tasks.php?action=' + (TASK_ID ? 'update' : 'create');
    if (TASK_ID) {
        payload.task_id = TASK_ID;
    }
    
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        showNotification(data.message || (data.success ? __('Task saved!') : __('Save failed')), data.success ? 'success' : 'error');
        if (data.success) {
            setTimeout(() => {
                window.location.href = '/pages/tasks.php';
            }, 500);
        }
    })
    .catch(() => showNotification(__('Network error'), 'error'));
}

function deleteTask() {
    showConfirm(__('are_you_sure_you_want_to_delete_this_task'), function() {
        fetch('/api/tasks.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ task_id: TASK_ID, csrf_token: CSRF_TOKEN })
        })
        .then(r => r.json())
        .then(resp => {
            showNotification(resp.message || (resp.success ? __('Task deleted') : __('Failed to delete')), resp.success ? 'success' : 'error');
            if (resp.success) {
                setTimeout(() => {
                    window.location.href = '/pages/tasks.php';
                }, 500);
            }
        });
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
