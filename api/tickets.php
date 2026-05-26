<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireLogin();

$action = $_GET['action'] ?? '';
$db = Database::getInstance()->getConnection();
$userId = getCurrentUser()['user_id'] ?? 0;
$companyId = $_SESSION["company_id"] ?? null;

if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = $_GET['status'] ?? '';
    $where = ["t.company_id = ?"]; $params = [$companyId];
    if ($status) { $where[] = "t.status = ?"; $params[] = $status; }
    
    $tickets = $db->query("
        SELECT t.*, c.first_name, c.last_name, a.account_name, u.full_name as assigned_name
        FROM support_tickets t
        LEFT JOIN contacts c ON t.contact_id = c.contact_id
        LEFT JOIN accounts a ON t.account_id = a.account_id
        LEFT JOIN users u ON t.assigned_to = u.user_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY 
            CASE t.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END,
            t.created_at DESC", $params)->fetchAll();
    jsonSuccess('Tickets loaded', $tickets);
}

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $ticketNumber = 'TKT-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    $ticketId = $db->insert('support_tickets', [
        'company_id'   => $companyId,
        'ticket_number' => $ticketNumber,
        'subject'      => sanitizeInput($input['subject'] ?? ''),
        'description'  => sanitizeInput($input['description'] ?? ''),
        'priority'     => sanitizeInput($input['priority'] ?? 'medium'),
        'category'     => sanitizeInput($input['category'] ?? ''),
        'contact_id'   => !empty($input['contact_id']) ? (int)$input['contact_id'] : null,
        'account_id'   => !empty($input['account_id']) ? (int)$input['account_id'] : null,
        'assigned_to'  => !empty($input['assigned_to']) ? (int)$input['assigned_to'] : null,
        'created_by'   => $userId,
    ]);
    jsonSuccess('Ticket created', ['ticket_id' => $ticketId, 'ticket_number' => $ticketNumber]);
}

if ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $ticketId = (int)($input['ticket_id'] ?? 0);
    $status = sanitizeInput($input['status'] ?? '');
    $updates = ['status' => $status];
    if ($status === 'resolved') $updates['resolved_at'] = date('Y-m-d H:i:s');
    $db->update('support_tickets', $updates, ['ticket_id' => $ticketId, 'company_id' => $companyId]);
    jsonSuccess('Status updated');
}

if ($action === 'reply' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $db->insert('ticket_replies', [
        'ticket_id'   => (int)($input['ticket_id'] ?? 0),
        'user_id'     => $userId,
        'message'     => sanitizeInput($input['message'] ?? ''),
        'is_internal' => (int)($input['is_internal'] ?? 0),
    ]);
    jsonSuccess('Reply added');
}

jsonError('Unknown action');
?>
