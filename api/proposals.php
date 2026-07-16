<?php
/**
 * FUNL CRM — Proposals API
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireLogin();

header('Content-Type: application/json; charset=utf-8');
$db = Database::getInstance();
$pdo = $db->getConnection();
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ── Helper: get next estimate number ──────────────────────
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

// ═══════════════════════════════════════════════════════════
//  LIST proposals
// ═══════════════════════════════════════════════════════════
try {
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
    // Scope to a single lead/contact so their detail page can list only their
    // own proposals.
    if (!empty($_GET['lead_id'])) {
        $where[] = "p.lead_id = ?";
        $params[] = intval($_GET['lead_id']);
    }
    if (!empty($_GET['contact_id'])) {
        $where[] = "p.contact_id = ?";
        $params[] = intval($_GET['contact_id']);
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

// ═══════════════════════════════════════════════════════════
//  GET single proposal
// ═══════════════════════════════════════════════════════════
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

// ═══════════════════════════════════════════════════════════
//  LINKABLES - leads + contacts a proposal can be attached to
// ═══════════════════════════════════════════════════════════
if ($action === 'linkables') {
    $companyId = getCurrentCompanyId();
    $out = [];

    $stmt = $pdo->prepare("
        SELECT lead_id AS id, company_name, contact_person, email
        FROM leads WHERE company_id = ? ORDER BY company_name ASC LIMIT 500
    ");
    $stmt->execute([$companyId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $label = trim($r['company_name'] ?: $r['contact_person'] ?: 'Unnamed');
        $out[] = [
            'type' => 'lead',
            'id' => (int)$r['id'],
            'label' => $label,
            'contact_name' => $r['contact_person'] ?? '',
            'company' => $r['company_name'] ?? '',
            'email' => $r['email'] ?? '',
        ];
    }

    $stmt = $pdo->prepare("
        SELECT contact_id AS id, first_name, last_name, email
        FROM contacts WHERE company_id = ? ORDER BY first_name ASC LIMIT 500
    ");
    $stmt->execute([$companyId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
        $out[] = [
            'type' => 'contact',
            'id' => (int)$r['id'],
            'label' => $name !== '' ? $name : 'Unnamed',
            'contact_name' => $name,
            'company' => '',
            'email' => $r['email'] ?? '',
        ];
    }

    echo json_encode(['success' => true, 'linkables' => $out]);
    exit;
}

// ═══════════════════════════════════════════════════════════
//  NEXT NUMBER
// ═══════════════════════════════════════════════════════════
if ($action === 'next_number') {
    $companyId = getCurrentCompanyId();
    echo json_encode(['success' => true, 'next_number' => getNextEstimateNumber($pdo, $companyId)]);
    exit;
}

// ═══════════════════════════════════════════════════════════
//  SAVE (create/update)
// ═══════════════════════════════════════════════════════════
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
        // Optional linkage to the lead/contact this proposal is for. Stored as
        // real FKs rather than the free-text customer_company/contact_name that
        // were the only record of "who" before.
        'lead_id' => !empty($input['lead_id']) ? intval($input['lead_id']) : null,
        'contact_id' => !empty($input['contact_id']) ? intval($input['contact_id']) : null,
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

// ═══════════════════════════════════════════════════════════
//  SEND - email the proposal to its linked lead/contact
// ═══════════════════════════════════════════════════════════
if ($action === 'send' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['csrf_token']) || !verifyCSRFToken($input['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $companyId  = getCurrentCompanyId();
    $proposalId = intval($input['proposal_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT * FROM proposals WHERE proposal_id = ? AND company_id = ?");
    $stmt->execute([$proposalId, $companyId]);
    $proposal = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$proposal) {
        echo json_encode(['success' => false, 'message' => 'Proposal not found']);
        exit;
    }

    // Resolve the recipient from the linked record. An explicit override is
    // allowed so the sender can correct a missing/typo'd address.
    $to = trim($input['email'] ?? '');
    $recipientName = '';
    if ($to === '' && !empty($proposal['contact_id'])) {
        $r = $pdo->prepare("SELECT first_name, last_name, email FROM contacts WHERE contact_id = ? AND company_id = ?");
        $r->execute([$proposal['contact_id'], $companyId]);
        if ($row = $r->fetch(PDO::FETCH_ASSOC)) {
            $to = trim($row['email'] ?? '');
            $recipientName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        }
    }
    if ($to === '' && !empty($proposal['lead_id'])) {
        $r = $pdo->prepare("SELECT contact_person, email FROM leads WHERE lead_id = ? AND company_id = ?");
        $r->execute([$proposal['lead_id'], $companyId]);
        if ($row = $r->fetch(PDO::FETCH_ASSOC)) {
            $to = trim($row['email'] ?? '');
            $recipientName = trim($row['contact_person'] ?? '');
        }
    }
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'No valid email address for the linked lead/contact. Link one, or add an email to that record.']);
        exit;
    }

    // Mint a share token once. proposal-view.php requires a login, so a
    // recipient could never open it -- the emailed link points at the public,
    // token-scoped view instead.
    $token = $proposal['share_token'] ?? '';
    if (!$token) {
        $token = bin2hex(random_bytes(24));
        $u = $pdo->prepare("UPDATE proposals SET share_token = ? WHERE proposal_id = ? AND company_id = ?");
        $u->execute([$token, $proposalId, $companyId]);
    }

    $base = rtrim(getenv('APP_URL') ?: 'https://app.funl.online', '/');
    $link = $base . '/proposal.php?t=' . urlencode($token);

    $appName = getAppName();
    $subject = $appName . ' — Proposal ' . ($proposal['estimate_number'] ?: '');
    $safeName = htmlspecialchars($recipientName !== '' ? $recipientName : ($proposal['contact_name'] ?: 'there'), ENT_QUOTES, 'UTF-8');
    $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#1f2937;line-height:1.6;">'
          . '<p>Hi ' . $safeName . ',</p>'
          . '<p>Please find your proposal ' . htmlspecialchars($proposal['estimate_number'] ?: '', ENT_QUOTES, 'UTF-8')
          . ' from ' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . '.</p>'
          . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:12px 22px;background:#2563eb;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;">View your proposal</a></p>'
          . '<p style="font-size:13px;color:#6b7280;">Or paste this link into your browser:<br>'
          . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '</p>'
          . '</div>';

    $sent = false; $err = '';
    try {
        require_once __DIR__ . '/../includes/resend-email.php';
        $resend = new ResendEmailService();
        if ($resend->isEnabled()) {
            $res = $resend->sendEmail($to, $subject, $html);
            $sent = ($res !== false);
            if (!$sent) { $err = 'Resend rejected the message'; }
        } else {
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $fromAddr = getSettingValue('email_from_address') ?: ('no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $headers .= 'From: ' . ($appName ?: 'CRM') . ' <' . $fromAddr . ">\r\n";
            $sent = @mail($to, $subject, $html, $headers);
            if (!$sent) { $err = 'PHP mail() failed and Resend is not configured'; }
        }
    } catch (Exception $e) {
        $err = $e->getMessage();
    }

    if (!$sent) {
        echo json_encode(['success' => false, 'message' => 'Could not send: ' . $err, 'link' => $link]);
        exit;
    }

    $pdo->prepare("UPDATE proposals SET status = CASE WHEN status = 'Draft' THEN 'Sent' ELSE status END, sent_at = NOW() WHERE proposal_id = ? AND company_id = ?")
        ->execute([$proposalId, $companyId]);

    echo json_encode(['success' => true, 'message' => 'Proposal sent to ' . $to, 'link' => $link]);
    exit;
}

// ═══════════════════════════════════════════════════════════
//  DELETE
// ═══════════════════════════════════════════════════════════
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
} catch (Throwable $e) {
    safeJsonError($e, 'An error occurred', 500);
}
