<?php
/**
 * White Label CRM - Leads API
 * Handles CRUD operations for leads
 * SiteGround MySQL compatible
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/twilio.php';

startSecureSession();
requireLogin();
requireEmailVerified();

header('Content-Type: application/json');

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$currentUser = getCurrentUser();

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($db, $action, $currentUser);
            break;
        case 'POST':
            handlePostRequest($db, $action, $currentUser);
            break;
        case 'PUT':
            handlePutRequest($db, $action, $currentUser);
            break;
        case 'DELETE':
            handleDeleteRequest($db, $action, $currentUser);
            break;
        default:
            jsonError('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Leads API Error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    jsonError('An error occurred: ' . $e->getMessage(), 500);
}

function handleGetRequest($db, $action, $currentUser) {
    switch ($action) {
        case 'list':   getLeadsList($db, $currentUser); break;
        case 'detail': getLeadDetail($db, $currentUser); break;
        case 'stats':  getLeadStats($db, $currentUser); break;
        case 'search': searchLeads($db, $currentUser); break;
        default:       getLeadsList($db, $currentUser);
    }
}

/**
 * Quick search leads by name, company, phone — used by link-to-lead in WhatsApp unmatched
 */
function searchLeads($db, $currentUser) {
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) {
        echo json_encode(['success' => true, 'data' => []]);
        return;
    }
    $like = '%' . $q . '%';
    $companyId = $currentUser['company_id'] ?? null;
    if ($companyId) {
        $stmt = $db->prepare("
            SELECT lead_id, contact_person, company_name, phone, mobile, email, lead_status
            FROM leads
            WHERE (contact_person LIKE ? OR company_name LIKE ? OR phone LIKE ? OR mobile LIKE ? OR email LIKE ?) AND company_id = ?
            ORDER BY contact_person ASC
            LIMIT 15
        ");
        $stmt->execute([$like, $like, $like, $like, $like, $companyId]);
    } else {
        $stmt = $db->prepare("
            SELECT lead_id, contact_person, company_name, phone, mobile, email, lead_status
            FROM leads
            WHERE (contact_person LIKE ? OR company_name LIKE ? OR phone LIKE ? OR mobile LIKE ? OR email LIKE ?)
            ORDER BY contact_person ASC
            LIMIT 15
        ");
        $stmt->execute([$like, $like, $like, $like, $like]);
    }
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $results]);
}

function handlePostRequest($db, $action, $currentUser) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) $data = $_POST;

    // Verify CSRF
    $token = $data['csrf_token'] ?? null;
    if (!verifyCSRFToken($token)) {
        jsonError('Invalid or expired request token. Please refresh the page.', 403);
    }

    switch ($action) {
        case 'create':       createLead($db, $data, $currentUser); break;
        case 'bulk_assign':  bulkAssignLeads($db, $data, $currentUser); break;
        case 'bulk_delete':  bulkDeleteLeads($db, $data, $currentUser); break;
        case 'move_to_contact': moveLeadToContact($db, $data, $currentUser); break;
        default:             jsonError('Unknown action', 400);
    }
}

function handlePutRequest($db, $action, $currentUser) {
    $data = json_decode(file_get_contents('php://input'), true);

    $token = $data['csrf_token'] ?? null;
    if (!verifyCSRFToken($token)) {
        jsonError('Invalid or expired request token. Please refresh the page.', 403);
    }

    switch ($action) {
        case 'update': updateLead($db, $data, $currentUser); break;
        case 'status': updateLeadStatusAPI($db, $data, $currentUser); break;
        default:       jsonError('Unknown action', 400);
    }
}

function handleDeleteRequest($db, $action, $currentUser) {
    if (!hasRole('Sales Manager')) {
        jsonError('Permission denied', 403);
    }

    // CSRF check for DELETE too
    $data = json_decode(file_get_contents('php://input'), true);
    $token = $data['csrf_token'] ?? $_GET['csrf_token'] ?? null;
    if (!verifyCSRFToken($token)) {
        jsonError('Invalid or expired request token.', 403);
    }

    $leadId = intval($_GET['id'] ?? 0);
    if (!$leadId) jsonError('Lead ID required', 400);

    deleteLead($db, $leadId, $currentUser);
}

// ─── GET Handlers ───────────────────────────────────────

function getLeadsList($db, $currentUser) {
    $page   = max(1, intval($_GET['page'] ?? 1));
    $limit  = RECORDS_PER_PAGE;
    $offset = ($page - 1) * $limit;

    $where  = ['1=1'];
    $params = [];

    // Tenant isolation: scope by company_id
    if (!empty($currentUser['company_id'])) {
        $where[]  = 'l.company_id = ?';
        $params[] = $currentUser['company_id'];
    }

    if (!empty($_GET['status'])) {
        $where[]  = 'l.lead_status = ?';
        $params[] = $_GET['status'];
    }
    if (!empty($_GET['country'])) {
        $where[]  = 'l.country = ?';
        $params[] = $_GET['country'];
    }
    if (!empty($_GET['assigned_to'])) {
        $where[]  = 'l.assigned_to = ?';
        $params[] = $_GET['assigned_to'];
    }
    if (!empty($_GET['search'])) {
        $where[] = '(l.company_name LIKE ? OR l.contact_person LIKE ? OR l.email LIKE ?)';
        $term = '%' . $_GET['search'] . '%';
        $params = array_merge($params, [$term, $term, $term]);
    }

    // Follow-up filter: leads that have at least one 'Follow-up' interaction
    $followUpFilter = !empty($_GET['follow_up']) && $_GET['follow_up'] == '1';
    if ($followUpFilter) {
        $where[] = "l.lead_id IN (
            SELECT DISTINCT lead_id FROM interactions WHERE interaction_type = 'Follow-up'
        )";
    }

    // Non-manager users see only their leads
    if (!hasRole('Sales Manager')) {
        $where[]  = '(l.assigned_to = ? OR l.created_by = ?)';
        $params[] = $currentUser['user_id'];
        $params[] = $currentUser['user_id'];
    }

    $whereClause = implode(' AND ', $where);

    // Count
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM leads l WHERE $whereClause");
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];

    // ── Sorting ──────────────────────────────────────────────
    $allowedSortColumns = [
        'updated_at'     => 'l.updated_at',
        'created_at'     => 'l.created_at',
        'company_name'   => 'l.company_name',
        'contact_person' => 'l.contact_person',
        'country'        => 'l.country',
        'lead_status'    => 'l.lead_status',
        'priority'       => 'l.priority',
        'lead_source'    => 'l.lead_source',
        'assigned_name'  => 'u1.full_name',
        'lead_type'      => 'l.lead_type',
    ];

    $sortBy  = $_GET['sort_by'] ?? 'updated_at';
    $sortDir = strtoupper($_GET['sort_dir'] ?? 'DESC');

    // Whitelist validation
    $sortColumn = $allowedSortColumns[$sortBy] ?? 'l.updated_at';
    $sortDir    = ($sortDir === 'ASC') ? 'ASC' : 'DESC';

    // Leads with LEFT JOIN for interaction count (no N+1)
    $sql = "
        SELECT l.*, 
               u1.full_name as assigned_name,
               u2.full_name as created_name,
               COALESCE(ic.cnt, 0) as interaction_count
        FROM leads l
        LEFT JOIN users u1 ON l.assigned_to = u1.user_id
        LEFT JOIN users u2 ON l.created_by = u2.user_id
        LEFT JOIN (
            SELECT lead_id, COUNT(*) as cnt FROM interactions GROUP BY lead_id
        ) ic ON ic.lead_id = l.lead_id
        WHERE $whereClause
        ORDER BY $sortColumn $sortDir
        LIMIT ? OFFSET ?
    ";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $leads = $stmt->fetchAll();

    jsonSuccess('Leads retrieved', [
        'leads'    => $leads,
        'total'    => $total,
        'page'     => $page,
        'pages'    => ceil($total / $limit),
        'sort_by'  => $sortBy,
        'sort_dir' => $sortDir,
    ]);
}

function getLeadDetail($db, $currentUser) {
    $leadId = intval($_GET['id'] ?? 0);
    if (!$leadId) jsonError('Lead ID required', 400);

    $companyId = $currentUser['company_id'] ?? null;
    
    if ($companyId) {
        $stmt = $db->prepare("
            SELECT l.*, 
                   u1.full_name as assigned_name,
                   u2.full_name as created_name
            FROM leads l
            LEFT JOIN users u1 ON l.assigned_to = u1.user_id
            LEFT JOIN users u2 ON l.created_by = u2.user_id
            WHERE l.lead_id = ? AND l.company_id = ?
        ");
        $stmt->execute([$leadId, $companyId]);
    } else {
        $stmt = $db->prepare("
            SELECT l.*, 
                   u1.full_name as assigned_name,
                   u2.full_name as created_name
            FROM leads l
            LEFT JOIN users u1 ON l.assigned_to = u1.user_id
            LEFT JOIN users u2 ON l.created_by = u2.user_id
            WHERE l.lead_id = ?
        ");
        $stmt->execute([$leadId]);
    }
    $lead = $stmt->fetch();
    if (!$lead) jsonError('Lead not found', 404);

    $intStmt = $db->prepare("
        SELECT i.*, u.full_name as user_name
        FROM interactions i LEFT JOIN users u ON i.user_id = u.user_id
        WHERE i.lead_id = ? ORDER BY i.interaction_date DESC
    ");
    $intStmt->execute([$leadId]);
    $interactions = $intStmt->fetchAll();

    $docStmt = $db->prepare("
        SELECT d.*, u.full_name as uploaded_by_name
        FROM documents d LEFT JOIN users u ON d.uploaded_by = u.user_id
        WHERE d.lead_id = ? ORDER BY d.uploaded_at DESC
    ");
    $docStmt->execute([$leadId]);
    $documents = $docStmt->fetchAll();

    jsonSuccess('Lead details retrieved', [
        'lead'         => $lead,
        'interactions' => $interactions,
        'documents'    => $documents,
        'custom_fields' => getAllCustomFieldValues($leadId),
    ]);
}

function getLeadStats($db, $currentUser) {
    $companyId = $currentUser['company_id'] ?? null;
    
    $where = [];
    $params = [];
    if ($companyId) {
        $where[] = 'company_id = ?';
        $params[] = $companyId;
    }
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $stats = [];
    
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM leads $whereClause");
    $stmt->execute($params);
    $stats['total'] = $stmt->fetch()['c'] ?? 0;
    
    $stmt = $db->prepare("SELECT lead_status, COUNT(*) as count FROM leads $whereClause GROUP BY lead_status");
    $stmt->execute($params);
    $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $countryWhere = $where ? $whereClause . " AND country IS NOT NULL AND country != ''" : "WHERE country IS NOT NULL AND country != ''";
    $stmt = $db->prepare("SELECT country, COUNT(*) as count FROM leads $countryWhere GROUP BY country ORDER BY count DESC");
    $stmt->execute($params);
    $stats['by_country'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    if ($db->isSQLite()) {
        $monthWhere = $where ? $whereClause . " AND strftime('%m', created_at) = strftime('%m', 'now') AND strftime('%Y', created_at) = strftime('%Y', 'now')" : "WHERE strftime('%m', created_at) = strftime('%m', 'now') AND strftime('%Y', created_at) = strftime('%Y', 'now')";
    } else {
        $monthWhere = $where ? $whereClause . " AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())" : "WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())";
    }
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM leads $monthWhere");
    $stmt->execute($params);
    $stats['this_month'] = $stmt->fetch()['c'] ?? 0;
    
    jsonSuccess('Stats retrieved', $stats);
}

// ─── POST / PUT Handlers ────────────────────────────────

function emptyToNull($value) {
    return (isset($value) && $value !== '') ? $value : null;
}

function createLead($db, $data, $currentUser) {
    // Only the contact person's name is required; everything else is optional
    $fieldErrors = [];
    $contactPerson = trim($data['contact_person'] ?? '');
    if ($contactPerson === '') {
        $fieldErrors['contact_person'] = 'Cannot be empty';
    }
    if (!empty($fieldErrors)) {
        echo json_encode(['success' => false, 'message' => 'Please fix the highlighted fields.', 'field_errors' => $fieldErrors]);
        return;
    }

    // Convert empty strings to null for nullable/enum/int fields
    $assignedTo = emptyToNull($data['assigned_to'] ?? null);
    if ($assignedTo === null) $assignedTo = $currentUser['user_id'];

    $stmt = $db->prepare("
        INSERT INTO leads (
            company_id, lead_type, company_name, contact_person, title_position, region, country, city,
            address, phone, mobile, email, website, facebook_url, instagram_url, linkedin_url,
            twitter_url, youtube_url, industry, company_size, annual_revenue, notes,
            lead_status, lead_source, priority, assigned_to, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $currentUser['company_id'] ?? null,
        $data['lead_type'] ?? 'Other', emptyToNull($data['company_name'] ?? null) ?: 'Unknown Company',
        $contactPerson, emptyToNull($data['title_position'] ?? null),
        $data['region'] ?? 'Other', emptyToNull($data['country'] ?? null) ?: 'Unknown', emptyToNull($data['city'] ?? null),
        emptyToNull($data['address'] ?? null), emptyToNull($data['phone'] ?? null), emptyToNull($data['mobile'] ?? null),
        emptyToNull($data['email'] ?? null), emptyToNull($data['website'] ?? null),
        emptyToNull($data['facebook_url'] ?? null), emptyToNull($data['instagram_url'] ?? null),
        emptyToNull($data['linkedin_url'] ?? null), emptyToNull($data['twitter_url'] ?? null),
        emptyToNull($data['youtube_url'] ?? null), emptyToNull($data['industry'] ?? null),
        emptyToNull($data['company_size'] ?? null), emptyToNull($data['annual_revenue'] ?? null),
        emptyToNull($data['notes'] ?? null), $data['lead_status'] ?? 'New Lead',
        $data['lead_source'] ?? 'Other', $data['priority'] ?? 'Medium',
        $assignedTo, $currentUser['user_id'],
    ]);

    $leadName = $data['contact_person'] ?? $data['company_name'] ?? 'New Lead';
    $leadId = $db->getConnection()->lastInsertId();

    // Save custom field values
    saveCustomFieldValues($leadId, $data);

    logActivity($currentUser['user_id'], 'Created', 'Lead', $leadId, 'Created lead: ' . $leadName);

    // Send WhatsApp notification if lead is assigned to someone other than creator
    if ($assignedTo && $assignedTo != $currentUser['user_id']) {
        TwilioHelper::notifyLeadAssignment($assignedTo, $leadName, $leadId, $currentUser['full_name'] ?? '');
    }

    jsonSuccess('Lead created successfully', ['lead_id' => $leadId]);
}

function updateLead($db, $data, $currentUser) {
    if (empty($data['lead_id'])) jsonError('Lead ID required', 400);

    // Only the contact person's name is required
    $fieldErrors = [];
    $contactPerson = trim($data['contact_person'] ?? '');
    if ($contactPerson === '') {
        $fieldErrors['contact_person'] = 'Cannot be empty';
    }
    if (!empty($fieldErrors)) {
        echo json_encode(['success' => false, 'message' => 'Please fix the highlighted fields.', 'field_errors' => $fieldErrors]);
        return;
    }

    // Get previous assigned_to before updating
    $prevStmt = $db->prepare("SELECT assigned_to FROM leads WHERE lead_id = ?");
    $prevStmt->execute([$data['lead_id']]);
    $prevLead = $prevStmt->fetch();
    $previousAssignedTo = $prevLead ? $prevLead['assigned_to'] : null;

    // Convert empty strings to null for nullable/enum/int fields
    $newAssignedTo = emptyToNull($data['assigned_to'] ?? null);

    $companyId = $currentUser['company_id'] ?? null;
    if ($companyId) {
        $stmt = $db->prepare("
            UPDATE leads SET
                lead_type = ?, company_name = ?, contact_person = ?, title_position = ?,
                region = ?, country = ?, city = ?, address = ?, phone = ?, mobile = ?,
                email = ?, website = ?, facebook_url = ?, instagram_url = ?, linkedin_url = ?,
                twitter_url = ?, youtube_url = ?, industry = ?, company_size = ?, annual_revenue = ?, notes = ?, lead_status = ?, lead_source = ?,
                priority = ?, assigned_to = ?
            WHERE lead_id = ? AND company_id = ?
        ");
    } else {
        $stmt = $db->prepare("
            UPDATE leads SET
                lead_type = ?, company_name = ?, contact_person = ?, title_position = ?,
                region = ?, country = ?, city = ?, address = ?, phone = ?, mobile = ?,
                email = ?, website = ?, facebook_url = ?, instagram_url = ?, linkedin_url = ?,
                twitter_url = ?, youtube_url = ?, industry = ?, company_size = ?, annual_revenue = ?, notes = ?, lead_status = ?, lead_source = ?,
                priority = ?, assigned_to = ?
            WHERE lead_id = ?
        ");
    }
    $params = [
        $data['lead_type'] ?? 'Other', emptyToNull($data['company_name'] ?? null) ?: 'Unknown Company',
        $contactPerson, emptyToNull($data['title_position'] ?? null),
        $data['region'] ?? 'Other', emptyToNull($data['country'] ?? null) ?: 'Unknown', emptyToNull($data['city'] ?? null),
        emptyToNull($data['address'] ?? null), emptyToNull($data['phone'] ?? null), emptyToNull($data['mobile'] ?? null),
        emptyToNull($data['email'] ?? null), emptyToNull($data['website'] ?? null),
        emptyToNull($data['facebook_url'] ?? null), emptyToNull($data['instagram_url'] ?? null),
        emptyToNull($data['linkedin_url'] ?? null), emptyToNull($data['twitter_url'] ?? null),
        emptyToNull($data['youtube_url'] ?? null), emptyToNull($data['industry'] ?? null),
        emptyToNull($data['company_size'] ?? null), emptyToNull($data['annual_revenue'] ?? null),
        emptyToNull($data['notes'] ?? null), $data['lead_status'] ?? 'New Lead', $data['lead_source'] ?? 'Other',
        $data['priority'] ?? 'Medium', $newAssignedTo, $data['lead_id'],
    ];
    if ($companyId) $params[] = $companyId;
    $stmt->execute($params);

    // Save custom field values
    saveCustomFieldValues($data['lead_id'], $data);

    $leadName = $data['contact_person'] ?? $data['company_name'] ?? 'Lead #' . $data['lead_id'];
    logActivity($currentUser['user_id'], 'Updated', 'Lead', $data['lead_id'], 'Updated lead: ' . $leadName);

    // Send WhatsApp notification if assignment changed
    if ($newAssignedTo && $newAssignedTo != $previousAssignedTo) {
        TwilioHelper::notifyLeadAssignment(
            intval($newAssignedTo),
            $leadName,
            intval($data['lead_id']),
            $currentUser['full_name'] ?? ''
        );
    }

    jsonSuccess('Lead updated successfully');
}

function updateLeadStatusAPI($db, $data, $currentUser) {
    $leadId = $data['lead_id'] ?? null;
    $status = $data['status'] ?? $data['lead_status'] ?? null;
    if (empty($leadId) || empty($status)) jsonError('Lead ID and status required', 400);

    $companyId = $currentUser['company_id'] ?? null;
    if ($companyId) {
        $stmt = $db->prepare("UPDATE leads SET lead_status = ? WHERE lead_id = ? AND company_id = ?");
        $stmt->execute([$status, $leadId, $companyId]);
    } else {
        $stmt = $db->prepare("UPDATE leads SET lead_status = ? WHERE lead_id = ?");
        $stmt->execute([$status, $leadId]);
    }
    if (!$stmt->rowCount()) jsonError('Lead not found or access denied', 404);
    logActivity($currentUser['user_id'], 'Status Change', 'Lead', $leadId, 'Changed status to: ' . $status);
    jsonSuccess('Status updated successfully');
}

function deleteLead($db, $leadId, $currentUser) {
    $companyId = $currentUser['company_id'] ?? null;
    if ($companyId) {
        $stmt = $db->prepare("SELECT contact_person, company_name FROM leads WHERE lead_id = ? AND company_id = ?");
        $stmt->execute([$leadId, $companyId]);
    } else {
        $stmt = $db->prepare("SELECT contact_person, company_name FROM leads WHERE lead_id = ?");
        $stmt->execute([$leadId]);
    }
    $lead = $stmt->fetch();
    if (!$lead) jsonError('Lead not found or access denied', 404);

    if ($companyId) {
        $stmt = $db->prepare("DELETE FROM leads WHERE lead_id = ? AND company_id = ?");
        $stmt->execute([$leadId, $companyId]);
    } else {
        $stmt = $db->prepare("DELETE FROM leads WHERE lead_id = ?");
        $stmt->execute([$leadId]);
    }
    $leadName = $lead['contact_person'] ?: $lead['company_name'] ?: 'Lead #' . $leadId;
    logActivity($currentUser['user_id'], 'Deleted', 'Lead', $leadId, 'Deleted lead: ' . $leadName);
    jsonSuccess('Lead deleted successfully');
}

// ─── Bulk Operations (Parameterized) ────────────────────

function bulkAssignLeads($db, $data, $currentUser) {
    if (!hasRole('Sales Manager')) jsonError('Permission denied', 403);
    if (empty($data['lead_ids']) || !is_array($data['lead_ids'])) jsonError('Lead IDs required', 400);
    if (empty($data['assigned_to'])) jsonError('Assigned user required', 400);

    $stmt = $db->prepare("SELECT full_name FROM users WHERE user_id = ?");
    $stmt->execute([$data['assigned_to']]);
    $user = $stmt->fetch();
    if (!$user) jsonError('Target user not found', 404);

    $companyId = $currentUser['company_id'] ?? null;
    $ids = array_map('intval', $data['lead_ids']);
    $in  = Database::buildInClause($ids);

    if ($companyId) {
        $sql  = "UPDATE leads SET assigned_to = ? WHERE lead_id IN ({$in['placeholders']}) AND company_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge([$data['assigned_to']], $in['params'], [$companyId]));
    } else {
        $sql  = "UPDATE leads SET assigned_to = ? WHERE lead_id IN ({$in['placeholders']})";
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge([$data['assigned_to']], $in['params']));
    }

    $count = count($ids);
    logActivity($currentUser['user_id'], 'Bulk Assign', 'Lead', 0, "Assigned $count leads to " . $user['full_name']);

    // Send WhatsApp notification for bulk assignment
    $assignedToId = intval($data['assigned_to']);
    if ($assignedToId != $currentUser['user_id']) {
        TwilioHelper::notifyLeadAssignment(
            $assignedToId,
            "$count lead(s) (bulk assignment)",
            0,
            $currentUser['full_name'] ?? ''
        );
    }

    jsonSuccess("Successfully assigned $count leads");
}

function bulkDeleteLeads($db, $data, $currentUser) {
    if (!hasRole('Sales Manager')) jsonError('Permission denied', 403);
    if (empty($data['lead_ids']) || !is_array($data['lead_ids'])) jsonError('Lead IDs required', 400);

    $companyId = $currentUser['company_id'] ?? null;
    $ids = array_map('intval', $data['lead_ids']);
    $in  = Database::buildInClause($ids);

    if ($companyId) {
        // Delete related records first (parameterized)
        $stmt = $db->prepare("DELETE FROM interactions WHERE lead_id IN ({$in['placeholders']}) AND EXISTS (SELECT 1 FROM leads WHERE leads.lead_id = interactions.lead_id AND company_id = ?)");
        $stmt->execute(array_merge($in['params'], [$companyId]));

        $stmt = $db->prepare("DELETE FROM documents WHERE lead_id IN ({$in['placeholders']}) AND EXISTS (SELECT 1 FROM leads WHERE leads.lead_id = documents.lead_id AND company_id = ?)");
        $stmt->execute(array_merge($in['params'], [$companyId]));

        $stmt = $db->prepare("DELETE FROM leads WHERE lead_id IN ({$in['placeholders']}) AND company_id = ?");
        $stmt->execute(array_merge($in['params'], [$companyId]));
    } else {
        $stmt = $db->prepare("DELETE FROM interactions WHERE lead_id IN ({$in['placeholders']})");
        $stmt->execute($in['params']);

        $stmt = $db->prepare("DELETE FROM documents WHERE lead_id IN ({$in['placeholders']})");
        $stmt->execute($in['params']);

        $stmt = $db->prepare("DELETE FROM leads WHERE lead_id IN ({$in['placeholders']})");
        $stmt->execute($in['params']);
    }

    $count = count($ids);
    logActivity($currentUser['user_id'], 'Bulk Delete', 'Lead', 0, "Deleted $count leads");
    jsonSuccess("Successfully deleted $count leads");
}

function moveLeadToContact($db, $data, $currentUser) {
    if (!hasRole('Sales Manager') && !hasRole('Admin')) {
        jsonError('Permission denied', 403);
    }
    
    $leadId = intval($data['lead_id'] ?? 0);
    if (!$leadId) jsonError('Lead ID required', 400);
    
    $companyId = $currentUser['company_id'] ?? null;
    if (!$companyId) jsonError('Company not found', 400);
    
    // Get the lead
    $stmt = $db->prepare("SELECT * FROM leads WHERE lead_id = ? AND company_id = ?");
    $stmt->execute([$leadId, $companyId]);
    $lead = $stmt->fetch();
    if (!$lead) jsonError('Lead not found', 404);
    
    // Create contact from lead data
    $stmt = $db->prepare("
        INSERT INTO contacts (company_id, first_name, last_name, email, phone, mobile, 
            title, address, city, country, notes, 
            assigned_to, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $nameParts = explode(' ', trim($lead['contact_person'] ?: 'Unknown'), 2);
    $firstName = $nameParts[0] ?? 'Unknown';
    $lastName = $nameParts[1] ?? '';
    
    $notes = $lead['notes'] ?: '';
    if ($lead['company_name']) {
        $notes = "Company: " . $lead['company_name'] . "\n" . $notes;
    }
    if ($lead['industry']) {
        $notes = "Industry: " . $lead['industry'] . "\n" . $notes;
    }
    
    $stmt->execute([
        $companyId,
        $firstName,
        $lastName,
        $lead['email'] ?: null,
        $lead['phone'] ?: null,
        $lead['mobile'] ?: null,
        $lead['title_position'] ?: null,
        $lead['address'] ?: null,
        $lead['city'] ?: null,
        $lead['country'] ?: null,
        $notes ?: null,
        $lead['assigned_to'],
        $currentUser['user_id']
    ]);
    
    $contactId = $db->getConnection()->lastInsertId();
    
    // Log activity
    $leadName = $lead['contact_person'] ?: $lead['company_name'] ?: 'Lead #' . $leadId;
    logActivity($currentUser['user_id'], 'Convert to Contact', 'Lead', $leadId, 
        "Converted lead '$leadName' to contact ID $contactId");
    
    jsonSuccess('Lead moved to contacts successfully', ['contact_id' => $contactId]);
}
?>
