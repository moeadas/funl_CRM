<?php
/**
* White Label CRM - Tasks API
* FIXED: removed role restriction on create so all logged-in users can create tasks.
* FIXED: replaced MySQL CURDATE() with PHP date for cross-DB compatibility.
*/
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
startSecureSession();
requireLogin();
header('Content-Type: application/json; charset=utf8');
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$db = Database::getInstance();
$userId = getCurrentUser()['user_id'] ?? 0;
$companyId = $_SESSION['company_id'] ?? null;
$today = date('Y-m-d');

// Guard: table must exist
try {
    $db->query("SELECT 1 FROM tasks LIMIT 1");
} catch (Exception $e) {
    jsonError('Tasks table not found. Run database/schema-v4-crm-tables.sql first.');
}

// ── LIST
if ($action === 'list' && $method === 'GET') {
    $status = sanitizeInput($_GET['status'] ?? '');
    $assignedTo = sanitizeInput($_GET['assigned_to'] ?? '');
    $leadId = sanitizeInput($_GET['lead_id'] ?? '');
    $overdue = $_GET['overdue'] ?? '';
    $search = sanitizeInput($_GET['search'] ?? '');
    
    $where = ['t.company_id = ?'];
    $params = [$companyId];
    
    if ($status) { $where[] = 't.status = ?'; $params[] = $status; }
    if ($assignedTo) { $where[] = 't.assigned_to = ?'; $params[] = (int)$assignedTo; }
    if ($leadId) { $where[] = 't.lead_id = ?'; $params[] = (int)$leadId; }
    if ($overdue === '1') {
        $where[] = "(t.due_date < ? AND t.status NOT IN ('done','cancelled'))";
        $params[] = $today;
    }
    if ($search) {
        $where[] = '(t.title LIKE ? OR t.description LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $sql = "SELECT t.*,
        u.full_name AS assigned_name,
        l.company_name AS lead_name,
        CONCAT(c.first_name,' ',c.last_name) AS contact_name,
        cr.full_name AS creator_name
        FROM tasks t
        LEFT JOIN users u ON t.assigned_to = u.user_id
        LEFT JOIN leads l ON t.lead_id = l.lead_id
        LEFT JOIN contacts c ON t.contact_id = c.contact_id
        LEFT JOIN users cr ON t.created_by = cr.user_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY
        CASE t.status WHEN 'todo' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'review' THEN 3 WHEN 'done' THEN 4 ELSE 5 END,
        CASE t.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END,
        t.due_date ASC,
        t.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonSuccess('Tasks loaded', $stmt->fetchAll(PDO::FETCH_ASSOC));
}

// ── CREATE
if ($action === 'create' && $method === 'POST') {
    requireCSRF();
    
    // FIX: all roles can create tasks (removed requireRole restriction)
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $title = sanitizeInput($input['title'] ?? '');
    $description = sanitizeInput($input['description'] ?? '');
    $status = sanitizeInput($input['status'] ?? 'todo');
    $priority = sanitizeInput($input['priority'] ?? 'medium');
    $assignedTo = !empty($input['assigned_to']) ? (int)$input['assigned_to'] : null;
    $leadId = !empty($input['lead_id']) ? (int)$input['lead_id'] : null;
    $contactId = !empty($input['contact_id']) ? (int)$input['contact_id'] : null;
    $dealId = !empty($input['deal_id']) ? (int)$input['deal_id'] : null;
    $dueDate = sanitizeInput($input['due_date'] ?? '') ?: null;
    $followUp = sanitizeInput($input['follow_up_date'] ?? '') ?: null;
    
    if (empty($title)) { jsonError('Task title is required'); }
    
    $validStatuses = ['todo','in_progress','review','done','cancelled'];
    $validPriorities = ['low','medium','high','urgent'];
    if (!in_array($status, $validStatuses)) { $status = 'todo'; }
    if (!in_array($priority, $validPriorities)) { $priority = 'medium'; }
    
    $taskId = $db->insert('tasks', [
        'company_id' => $companyId,
        'title' => $title,
        'description' => $description,
        'status' => $status,
        'priority' => $priority,
        'assigned_to' => $assignedTo,
        'lead_id' => $leadId,
        'contact_id' => $contactId,
        'deal_id' => $dealId,
        'due_date' => $dueDate,
        'follow_up_date' => $followUp,
        'created_by' => $userId,
    ]);
    
    if (!$taskId) { jsonError('Failed to create task'); }
    logActivity($userId, 'Create Task', 'System', $taskId, "Created task: $title");
    jsonSuccess('Task created', ['task_id' => $taskId]);
}

// ── UPDATE
if ($action === 'update' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $taskId = (int)($input['task_id'] ?? 0);
    if (!$taskId) { jsonError('Task ID required'); }
    
    $task = $db->query("SELECT * FROM tasks WHERE task_id = ? AND company_id = ?", [$taskId, $companyId])->fetch(PDO::FETCH_ASSOC);
    if (!$task) { jsonError('Task not found', 404); }
    
    $updates = [];
    if (isset($input['title'])) { $updates['title'] = sanitizeInput($input['title']); }
    if (isset($input['description'])) { $updates['description'] = sanitizeInput($input['description']); }
    if (isset($input['status'])) { $updates['status'] = sanitizeInput($input['status']); }
    if (isset($input['priority'])) { $updates['priority'] = sanitizeInput($input['priority']); }
    if (array_key_exists('assigned_to', $input)) { $updates['assigned_to'] = $input['assigned_to'] ? (int)$input['assigned_to'] : null; }
    if (array_key_exists('lead_id', $input)) { $updates['lead_id'] = $input['lead_id'] ? (int)$input['lead_id'] : null; }
    if (array_key_exists('contact_id', $input)) { $updates['contact_id'] = $input['contact_id'] ? (int)$input['contact_id'] : null; }
    if (array_key_exists('deal_id', $input)) { $updates['deal_id'] = $input['deal_id'] ? (int)$input['deal_id'] : null; }
    if (array_key_exists('due_date', $input)) { $updates['due_date'] = sanitizeInput($input['due_date']) ?: null; }
    if (array_key_exists('follow_up_date', $input)) { $updates['follow_up_date'] = sanitizeInput($input['follow_up_date']) ?: null; }
    
    // Set/clear completed_at based on status change
    if (isset($updates['status'])) {
        if ($updates['status'] === 'done' && $task['status'] !== 'done') {
            $updates['completed_at'] = date('Y-m-d H:i:s');
        } elseif ($updates['status'] !== 'done' && $task['status'] === 'done') {
            $updates['completed_at'] = null;
        }
    }
    
    if (!empty($updates)) {
        $db->update('tasks', $updates, ['task_id' => $taskId]);
        logActivity($userId, 'Update Task', 'System', $taskId, 'Updated task fields');
    }
    jsonSuccess('Task updated');
}

// ── DELETE
if ($action === 'delete' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $taskId = (int)($input['task_id'] ?? 0);
    if (!$taskId) { jsonError('Task ID required'); }
    
    $task = $db->query("SELECT * FROM tasks WHERE task_id = ? AND company_id = ?", [$taskId, $companyId])->fetch(PDO::FETCH_ASSOC);
    if (!$task) { jsonError('Task not found', 404); }
    
    $db->query("DELETE FROM tasks WHERE task_id = ?", [$taskId]);
    logActivity($userId, 'Delete Task', 'System', $taskId, "Deleted task: {$task['title']}");
    jsonSuccess('Task deleted');
}

// ── MOVE (drag & drop)
if ($action === 'move' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $taskId = (int)($input['task_id'] ?? 0);
    $newStatus = sanitizeInput($input['status'] ?? '');
    $valid = ['todo','in_progress','review','done','cancelled'];
    if (!in_array($newStatus, $valid)) { jsonError('Invalid status'); }
    
    $task = $db->query("SELECT * FROM tasks WHERE task_id = ? AND company_id = ?", [$taskId, $companyId])->fetch(PDO::FETCH_ASSOC);
    if (!$task) { jsonError('Task not found', 404); }
    
    $updates = ['status' => $newStatus];
    $updates['completed_at'] = ($newStatus === 'done') ? date('Y-m-d H:i:s') : null;
    $db->update('tasks', $updates, ['task_id' => $taskId]);
    logActivity($userId, 'Move Task', 'System', $taskId, "Moved to $newStatus");
    jsonSuccess('Task moved');
}

jsonError('Unknown action');
