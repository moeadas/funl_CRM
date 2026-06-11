<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireLogin();
requireEmailVerified();

$action = $_GET['action'] ?? '';
$db = Database::getInstance();
$userId = getCurrentUser()['user_id'] ?? 0;
$companyId = $_SESSION["company_id"] ?? null;

// Auto-recover company_id if session is stale
if (!$companyId && !empty($userId)) {
    try {
        $stmt = $db->prepare("SELECT company_id FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $dbCompanyId = $stmt->fetchColumn();
        if ($dbCompanyId) {
            $companyId = (int)$dbCompanyId;
            $_SESSION['company_id'] = $companyId;
        }
    } catch (Exception $e) { /* ignore */ }
}
if (!$companyId) {
    jsonError('No company is associated with your account. Please contact your administrator.', 400);
}

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

if ($action === 'get' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $ticketId = (int)($_GET['id'] ?? 0);
    $ticket = $db->findOne('support_tickets', ['ticket_id' => $ticketId, 'company_id' => $companyId]);
    if ($ticket) {
        // Fetch replies too
        $replies = $db->query("
            SELECT r.*, u.full_name as user_name
            FROM ticket_replies r
            LEFT JOIN users u ON r.user_id = u.user_id
            WHERE r.ticket_id = ?
            ORDER BY r.created_at ASC", [$ticketId])->fetchAll();
        $ticket['replies'] = $replies;
        jsonSuccess('Ticket loaded', $ticket);
    } else {
        jsonError('Ticket not found');
    }
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $ticketId = (int)($input['ticket_id'] ?? 0);
    
    $ticket = $db->findOne('support_tickets', ['ticket_id' => $ticketId, 'company_id' => $companyId]);
    if (!$ticket) jsonError('Ticket not found');
    
    $status = sanitizeInput($input['status'] ?? 'open');
    $resolvedAt = ($status === 'resolved' && $ticket['status'] !== 'resolved') ? date('Y-m-d H:i:s') : $ticket['resolved_at'];
    
    $db->update('support_tickets', [
        'subject'     => sanitizeInput($input['subject'] ?? ''),
        'description' => sanitizeInput($input['description'] ?? ''),
        'priority'    => sanitizeInput($input['priority'] ?? 'medium'),
        'category'    => sanitizeInput($input['category'] ?? ''),
        'contact_id'  => !empty($input['contact_id']) ? (int)$input['contact_id'] : null,
        'account_id'  => !empty($input['account_id']) ? (int)$input['account_id'] : null,
        'assigned_to' => !empty($input['assigned_to']) ? (int)$input['assigned_to'] : null,
        'status'      => $status,
        'resolved_at' => $resolvedAt
    ], ['ticket_id' => $ticketId, 'company_id' => $companyId]);
    
    jsonSuccess('Ticket updated');
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $ticketId = (int)($input['ticket_id'] ?? 0);
    
    $ticket = $db->findOne('support_tickets', ['ticket_id' => $ticketId, 'company_id' => $companyId]);
    if (!$ticket) jsonError('Ticket not found');
    
    // Delete replies first
    $db->query("DELETE FROM ticket_replies WHERE ticket_id = ?", [$ticketId]);
    
    // Delete ticket
    $db->delete('support_tickets', ['ticket_id' => $ticketId, 'company_id' => $companyId]);
    
    jsonSuccess('Ticket deleted');
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
