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

<div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;">
        <a href="/pages/tasks.php" class="btn btn-outline" style="padding:8px 14px;display:inline-flex;align-items:center;gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            <?php echo htmlspecialchars(__('Back to Board')); ?>
        </a>
        <h1><?= $taskId ? htmlspecialchars(__('Edit Task')) : htmlspecialchars(__('New Task')) ?></h1>
    </div>
    <div class="header-actions">
        <?php if ($taskId): ?>
            <button type="button" class="btn btn-outline" style="background:#fef2f2;border-color:#fecaca;color:#b91c1c;display:inline-flex;align-items:center;gap:6px;" onclick="deleteTask()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                <?php echo htmlspecialchars(__('Delete Task')); ?>
            </button>
        <?php endif; ?>
        <button type="button" class="btn btn-primary" onclick="saveTask()" style="display:inline-flex;align-items:center;gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            <?php echo htmlspecialchars(__('Save Task')); ?>
        </button>
    </div>
</div>

<div style="max-width:900px;">
    <div class="card">
        <div class="card-header" style="padding:18px 24px;">
            <h3 class="card-title" style="margin:0;"><?php echo htmlspecialchars(__('Task Details')); ?></h3>
        </div>
        <div class="card-body" style="padding:24px;">
            <div class="row-2">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Task Title *')); ?></label>
                    <input type="text" id="taskTitle" class="form-control" placeholder="<?php echo htmlspecialchars(__('What needs to be done?')); ?>" required style="padding:10px 14px;">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Status')); ?></label>
                    <select id="taskStatus" class="form-control" style="padding:10px 14px;">
                        <option value="todo"><?php echo htmlspecialchars(__('To Do')); ?></option>
                        <option value="in_progress"><?php echo htmlspecialchars(__('In Progress')); ?></option>
                        <option value="review"><?php echo htmlspecialchars(__('Review')); ?></option>
                        <option value="done"><?php echo htmlspecialchars(__('Done')); ?></option>
                        <option value="cancelled"><?php echo htmlspecialchars(__('Cancelled')); ?></option>
                    </select>
                </div>
            </div>
            <div class="form-group" style="margin-top:16px;margin-bottom:0;">
                <label class="form-label"><?php echo htmlspecialchars(__('Description')); ?></label>
                <textarea id="taskDescription" class="form-control" rows="4" placeholder="<?php echo htmlspecialchars(__('Add details about this task...')); ?>" style="padding:10px 14px;"></textarea>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:16px;">
        <div class="card-header" style="padding:18px 24px;">
            <h3 class="card-title" style="margin:0;"><?php echo htmlspecialchars(__('Assignment & Due Date')); ?></h3>
        </div>
        <div class="card-body" style="padding:24px;">
            <div class="row-3">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Priority')); ?></label>
                    <select id="taskPriority" class="form-control" style="padding:10px 14px;">
                        <option value="low"><?php echo htmlspecialchars(__('Low')); ?></option>
                        <option value="medium" selected><?php echo htmlspecialchars(__('Medium')); ?></option>
                        <option value="high"><?php echo htmlspecialchars(__('High')); ?></option>
                        <option value="urgent"><?php echo htmlspecialchars(__('Urgent')); ?></option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Assigned To')); ?></label>
                    <select id="taskAssignedTo" class="form-control" style="padding:10px 14px;">
                        <option value=""><?php echo htmlspecialchars(__('Unassigned')); ?></option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Due Date')); ?></label>
                    <input type="date" id="taskDueDate" class="form-control" style="padding:10px 14px;">
                </div>
            </div>
            <div class="row-2" style="margin-top:16px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Linked Lead')); ?></label>
                    <select id="taskLeadId" class="form-control" style="padding:10px 14px;">
                        <option value=""><?php echo htmlspecialchars(__('None')); ?></option>
                        <?php foreach ($leads as $l): ?>
                            <option value="<?= $l['lead_id'] ?>"><?= htmlspecialchars($l['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Follow-up Date')); ?></label>
                    <input type="date" id="taskFollowUp" class="form-control" style="padding:10px 14px;">
                </div>
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
            const t = resp.data.task;
            document.getElementById('taskTitle').value = t.title || '';
            document.getElementById('taskStatus').value = t.status || 'todo';
            document.getElementById('taskDescription').value = t.description || '';
            document.getElementById('taskPriority').value = t.priority || 'medium';
            document.getElementById('taskAssignedTo').value = t.assigned_to || '';
            document.getElementById('taskDueDate').value = t.due_date || '';
            document.getElementById('taskLeadId').value = t.lead_id || '';
            document.getElementById('taskFollowUp').value = t.follow_up_date || '';
        }
    });
}

function saveTask() {
    const data = {
        csrf_token: CSRF_TOKEN,
        title: document.getElementById('taskTitle').value,
        status: document.getElementById('taskStatus').value,
        description: document.getElementById('taskDescription').value,
        priority: document.getElementById('taskPriority').value,
        assigned_to: document.getElementById('taskAssignedTo').value || null,
        due_date: document.getElementById('taskDueDate').value || null,
        lead_id: document.getElementById('taskLeadId').value || null,
        follow_up_date: document.getElementById('taskFollowUp').value || null,
    };
    if (TASK_ID) data.task_id = TASK_ID;
    const action = TASK_ID ? 'update' : 'create';

    const btn = document.querySelector('button[onclick="saveTask()"]');
    btn.disabled = true;
    const origText = btn.innerHTML;
    btn.innerHTML = '<?php echo htmlspecialchars(__('Saving'), ENT_QUOTES); ?>…';

    fetch('/api/tasks.php?action=' + action, {
        method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    }).then(r => r.json()).then(resp => {
        if (resp.success) {
            showNotification(TASK_ID ? '<?php echo htmlspecialchars(__('Task updated'), ENT_QUOTES); ?>' : '<?php echo htmlspecialchars(__('Task created'), ENT_QUOTES); ?>', 'success');
            setTimeout(() => window.location.href = '/pages/tasks.php', 700);
        } else {
            showNotification(resp.message || '<?php echo htmlspecialchars(__('Failed'), ENT_QUOTES); ?>', 'error');
            btn.disabled = false;
            btn.innerHTML = origText;
        }
    });
}

function deleteTask() {
    if (!TASK_ID) return;
    showConfirm('<?php echo htmlspecialchars(__('Delete this task?'), ENT_QUOTES); ?>', function() {
        fetch('/api/tasks.php?action=delete', {
            method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ task_id: TASK_ID, csrf_token: CSRF_TOKEN })
        }).then(r => r.json()).then(resp => {
            if (resp.success) {
                showNotification('<?php echo htmlspecialchars(__('Task deleted'), ENT_QUOTES); ?>', 'success');
                setTimeout(() => window.location.href = '/pages/tasks.php', 700);
            } else {
                showNotification(resp.message || '<?php echo htmlspecialchars(__('Failed'), ENT_QUOTES); ?>', 'error');
            }
        });
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
