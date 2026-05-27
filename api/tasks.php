<?php
/**
 * White Label CRM - Tasks API
 * CRUD for tasks with kanban support
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

startSecureSession();
requireLogin();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$db = Database::getInstance();
$conn = $db->getConnection();
$userId = getCurrentUser()['user_id'] ?? 0;
$companyId = $_SESSION["company_id"] ?? null;

// Helper: check if tasks table exists
function tasksTableExists($db) {
    try {
        $db->query("SELECT 1 FROM tasks LIMIT 1");
        return true;
    } catch (Exception $e) {
        return false;
    }
}

if (!tasksTableExists($db)) {
    jsonError('Tasks system not initialized. Run migrate_tasks.php first.');
}

if ($action === 'list' && $method === 'GET') {
    $status = $_GET['status'] ?? '';
    $assignedTo = $_GET['assigned_to'] ?? '';
    $leadId = $_GET['lead_id'] ?? '';
    $overdue = $_GET['overdue'] ?? '';
    $search = $_GET['search'] ?? '';
    
    $where = ["t.company_id = ?"];
    $params = [$companyId];
    
    if ($status) { $where[] = "t.status = ?"; $params[] = $status; }
    if ($assignedTo) { $where[] = "t.assigned_to = ?"; $params[] = (int)$assignedTo; }
    if ($leadId) { $where[] = "t.lead_id = ?"; $params[] = (int)$leadId; }
    if ($overdue === '1') { $where[] = "t.due_date < CURDATE() AND t.status NOT IN ('done','cancelled')"; }
    if ($search) { $where[] = "(t.title LIKE ? OR t.description LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
    
    $sql = "SELECT t.*, 
            u.full_name as assigned_name, 
            l.company_name as lead_name,
            CONCAT(c.first_name, ' ', c.last_name) as contact_name,
            cr.full_name as creator_name
            FROM tasks t
            LEFT JOIN users u ON t.assigned_to = u.user_id
            LEFT JOIN leads l ON t.lead_id = l.lead_id
            LEFT JOIN contacts c ON t.contact_id = c.contact_id
            LEFT JOIN users cr ON t.created_by = cr.user_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY 
              CASE t.status WHEN 'todo' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'review' THEN 3 WHEN 'done' THEN 4 ELSE 5 END,
              CASE t.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END,
              t.due_date IS NOT NULL ASC, t.due_date ASC,
              t.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonSuccess('Tasks loaded', $stmt->fetchAll());
}

if ($action === 'create' && $method === 'POST') {
    requireCSRF();
    requireRole(['Admin','Sales Manager']);
    
    $input = json_decode(file_get_contents('php://input'), true);
    $title = sanitizeInput($input['title'] ?? '');
    $description = sanitizeInput($input['description'] ?? '');
    $status = sanitizeInput($input['status'] ?? 'todo');
    $priority = sanitizeInput($input['priority'] ?? 'medium');
    $assignedTo = !empty($input['assigned_to']) ? (int)$input['assigned_to'] : null;
    $leadId = !empty($input['lead_id']) ? (int)$input['lead_id'] : null;
    $contactId = !empty($input['contact_id']) ? (int)$input['contact_id'] : null;
    $dueDate = sanitizeInput($input['due_date'] ?? '');
    $followUpDate = sanitizeInput($input['follow_up_date'] ?? '');
    
    if (empty($title)) jsonError('Task title is required');
    
    $taskId = $db->insert('tasks', [
        'company_id'     => $companyId,
        'title'          => $title,
        'description'    => $description,
        'status'         => $status,
        'priority'       => $priority,
        'assigned_to'    => $assignedTo,
        'lead_id'        => $leadId,
        'contact_id'     => $contactId,
        'due_date'       => $dueDate ?: null,
        'follow_up_date' => $followUpDate ?: null,
        'created_by'     => $userId,
    ]);
    
    logActivity($userId, 'Create Task', 'Task', $taskId, "Created task: $title");
    jsonSuccess('Task created', ['task_id' => $taskId]);
}

if ($action === 'update' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $taskId = (int)($input['task_id'] ?? 0);
    
    $task = $db->query("SELECT * FROM tasks WHERE task_id = ? AND company_id = ?", [$taskId, $companyId])->fetch();
    if (!$task) jsonError('Task not found');
    
    $updates = [];
    if (isset($input['title'])) $updates['title'] = sanitizeInput($input['title']);
    if (isset($input['description'])) $updates['description'] = sanitizeInput($input['description']);
    if (isset($input['status'])) $updates['status'] = sanitizeInput($input['status']);
    if (isset($input['priority'])) $updates['priority'] = sanitizeInput($input['priority']);
    if (isset($input['assigned_to'])) $updates['assigned_to'] = $input['assigned_to'] ? (int)$input['assigned_to'] : null;
    if (isset($input['lead_id'])) $updates['lead_id'] = $input['lead_id'] ? (int)$input['lead_id'] : null;
    if (isset($input['contact_id'])) $updates['contact_id'] = $input['contact_id'] ? (int)$input['contact_id'] : null;
    if (isset($input['due_date'])) $updates['due_date'] = sanitizeInput($input['due_date']) ?: null;
    if (isset($input['follow_up_date'])) $updates['follow_up_date'] = sanitizeInput($input['follow_up_date']) ?: null;
    
    if ($input['status'] ?? '' === 'done') {
        $updates['completed_at'] = date('Y-m-d H:i:s');
    } else if (($input['status'] ?? '') !== 'done' && $task['status'] === 'done') {
        $updates['completed_at'] = null;
    }
    
    if (!empty($updates)) {
        $db->update('tasks', $updates, ['task_id' => $taskId]);
        logActivity($userId, 'Update Task', 'Task', $taskId, "Updated task fields");
    }
    
    jsonSuccess('Task updated');
}

if ($action === 'delete' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $taskId = (int)($input['task_id'] ?? 0);
    
    $task = $db->query("SELECT * FROM tasks WHERE task_id = ? AND company_id = ?", [$taskId, $companyId])->fetch();
    if (!$task) jsonError('Task not found');
    
    $db->query("DELETE FROM tasks WHERE task_id = ?", [$taskId]);
    logActivity($userId, 'Delete Task', 'Task', $taskId, "Deleted task: {$task['title']}");
    jsonSuccess('Task deleted');
}

if ($action === 'move' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $taskId = (int)($input['task_id'] ?? 0);
    $newStatus = sanitizeInput($input['status'] ?? '');
    
    $validStatuses = ['todo','in_progress','review','done','cancelled'];
    if (!in_array($newStatus, $validStatuses)) jsonError('Invalid status');
    
    $task = $db->query("SELECT * FROM tasks WHERE task_id = ? AND company_id = ?", [$taskId, $companyId])->fetch();
    if (!$task) jsonError('Task not found');
    
    $updates = ['status' => $newStatus];
    if ($newStatus === 'done') {
        $updates['completed_at'] = date('Y-m-d H:i:s');
    } else {
        $updates['completed_at'] = null;
    }
    
    $db->update('tasks', $updates, ['task_id' => $taskId]);
    logActivity($userId, 'Move Task', 'Task', $taskId, "Moved to $newStatus");
    jsonSuccess('Task moved');
}

jsonError('Unknown action');
?>
