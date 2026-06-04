<?php
/**
 * FUNL CRM — Proposals API
 */
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

header('Content-Type: application/json; charset=utf-8');
$db = Database::getInstance();
$pdo = $db->getConnection();
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ── Helper: get next estimate number ─────────────────────────
function getNextEstimateNumber($pdo, $companyId) {
    $year = date('Y');
    $db = Database::getInstance();
    if ($db->isSQLite()) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM proposals WHERE company_id = ? AND strftime('%Y', created_at) = ?");
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM proposals WHERE company_id = ? AND YEAR(created_at) = ?");
    }
    $stmt->execute([$companyId, $year]);
    $count = (int)$stmt->fetchColumn();
    return sprintf('EST-%s-%04d', $year, $count + 1);
}

// ══════════════════════════════════════════════════════════════
//  LIST proposals
// ══════════════════════════════════════════════════════════════
if ($action === 'list') {
    $companyId = getCurrentCompanyId();
    $search = trim($_GET['search'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    $where = ["p.company_id = ?"];
    $params = [$companyId];
    
    if ($search) {
        $where[] = "(p.customer_company LIKE ? OR p.contact_name LIKE ? OR p.estimate_number LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($status) {
        $where[] = "p.status = ?";
        $params[] = $status;
    }
    
    $whereSQL = 'WHERE ' . implode(' AND ', $where);
    
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM proposals p $whereSQL");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare("
        SELECT p.*, u.full_name AS creator_name
        FROM proposals p
        LEFT JOIN users u ON u.user_id = p.created_by
        $whereSQL
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute($params);
    
    echo json_encode([
        'success' => true,
        'proposals' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'total' => $total,
        'page' => $page,
        'pages' => ceil($total / $limit)
    ]);
    exit;
}

// ══════════════════════════════════════════════════════════════
//  GET single proposal
// ══════════════════════════════════════════════════════════════
if ($action === 'get') {
    $proposalId = intval($_GET['id'] ?? 0);
    $companyId = getCurrentCompanyId();
    
    $stmt = $pdo->prepare("
        SELECT p.*, u.full_name AS creator_name
        FROM proposals p
        LEFT JOIN users u ON u.user_id = p.created_by
        WHERE p.proposal_id = ? AND p.company_id = ?
    ");
    $stmt->execute([$proposalId, $companyId]);
    $proposal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($proposal) {
        echo json_encode(['success' => true, 'proposal' => $proposal]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Proposal not found']);
    }
    exit;
}

// ══════════════════════════════════════════════════════════════
//  NEXT NUMBER
// ══════════════════════════════════════════════════════════════
if ($action === 'next_number') {
    $companyId = getCurrentCompanyId();
    echo json_encode(['success' => true, 'next_number' => getNextEstimateNumber($pdo, $companyId)]);
    exit;
}

// ══════════════════════════════════════════════════════════════
//  SAVE (create/update)
// ══════════════════════════════════════════════════════════════
if ($action === 'save' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['csrf_token']) || !verifyCSRFToken($input['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $companyId = getCurrentCompanyId();
    $userId = getCurrentUserId();
    $proposalId = intval($input['proposal_id'] ?? 0);
    
    $data = [
        'company_id' => $companyId,
        'proposal_date' => $input['proposal_date'] ?? date('Y-m-d'),
        'status' => $input['status'] ?? 'Draft',
        'customer_company' => $input['customer_company'] ?? '',
        'contact_name' => $input['contact_name'] ?? '',
        'customer_address' => $input['customer_address'] ?? '',
        'line_items' => json_encode($input['line_items'] ?? []),
        'subtotal' => $input['subtotal'] ?? 0,
        'tax_rate' => $input['tax_rate'] ?? 0,
        'tax_amount' => $input['tax_amount'] ?? 0,
        'total' => $input['total'] ?? 0,
        'notes' => $input['notes'] ?? '',
        'accepted_by' => $input['accepted_by'] ?? '',
        'accepted_date' => $input['accepted_date'] ?? null,
        'created_by' => $userId
    ];
    
    if ($proposalId) {
        // Update
        $fields = [];
        $values = [];
        foreach ($data as $k => $v) {
            if ($k !== 'company_id' && $k !== 'created_by') {
                $fields[] = "$k = ?";
                $values[] = $v;
            }
        }
        $values[] = $proposalId;
        $values[] = $companyId;
        
        $stmt = $pdo->prepare("UPDATE proposals SET " . implode(', ', $fields) . " WHERE proposal_id = ? AND company_id = ?");
        $stmt->execute($values);
        
        echo json_encode(['success' => true, 'message' => 'Proposal updated', 'proposal_id' => $proposalId]);
    } else {
        // Create
        $data['estimate_number'] = getNextEstimateNumber($pdo, $companyId);
        
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        
        $stmt = $pdo->prepare("INSERT INTO proposals (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")");
        $stmt->execute(array_values($data));
        
        $newId = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'message' => 'Proposal created', 'proposal_id' => $newId]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════════
//  DELETE
// ══════════════════════════════════════════════════════════════
if ($action === 'delete' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['csrf_token']) || !verifyCSRFToken($input['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $proposalId = intval($input['proposal_id'] ?? 0);
    $companyId = getCurrentCompanyId();
    
    $stmt = $pdo->prepare("DELETE FROM proposals WHERE proposal_id = ? AND company_id = ?");
    $stmt->execute([$proposalId, $companyId]);
    
    echo json_encode(['success' => true, 'message' => 'Proposal deleted']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
