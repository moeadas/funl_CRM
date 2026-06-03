<?php
/**
 * White Label CRM - Deals / Pipeline API
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

startSecureSession();
requireLogin();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$db = Database::getInstance();
$userId = getCurrentUser()['user_id'] ?? 0;
$companyId = $_SESSION["company_id"] ?? null;

// ── Deals ─────────────────────────────────────────────────────

if ($action === 'list' && $method === 'GET') {
    $stage = $_GET['stage'] ?? '';
    $assignedTo = $_GET['assigned_to'] ?? '';
    $search = $_GET['search'] ?? '';
    $minValue = $_GET['min_value'] ?? '';
    $maxValue = $_GET['max_value'] ?? '';
    
    $where = ["d.company_id = ?"];
    $params = [$companyId];
    
    if ($stage) { $where[] = "d.stage = ?"; $params[] = $stage; }
    if ($assignedTo) { $where[] = "d.assigned_to = ?"; $params[] = (int)$assignedTo; }
    if ($minValue !== '') { $where[] = "d.deal_value >= ?"; $params[] = (float)$minValue; }
    if ($maxValue !== '') { $where[] = "d.deal_value <= ?"; $params[] = (float)$maxValue; }
    if ($search) {
        $where[] = "(d.deal_name LIKE ? OR d.description LIKE ? OR a.account_name LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ?)";
        $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
    }
    
    $sql = "SELECT d.*, 
            a.account_name,
            CONCAT(c.first_name, ' ', c.last_name) as contact_name,
            u.full_name as assigned_name,
            s.stage_label,
            s.color as stage_color,
            s.probability as stage_probability
            FROM deals d
            LEFT JOIN accounts a ON d.account_id = a.account_id
            LEFT JOIN contacts c ON d.contact_id = c.contact_id
            LEFT JOIN users u ON d.assigned_to = u.user_id
            LEFT JOIN deal_stages s ON d.stage = s.stage_name AND (s.company_id = d.company_id OR s.company_id = 0)
            WHERE " . implode(' AND ', $where) . "
            ORDER BY d.expected_close ASC, d.deal_value DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonSuccess('Deals loaded', $stmt->fetchAll());
}

if ($action === 'create' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $dealName = sanitizeInput($input['deal_name'] ?? '');
    
    if (empty($dealName)) jsonError('Deal name is required');
    
    $dealId = $db->insert('deals', [
        'company_id'     => $companyId,
        'deal_name'      => $dealName,
        'deal_value'     => !empty($input['deal_value']) ? (float)$input['deal_value'] : 0,
        'currency'       => sanitizeInput($input['currency'] ?? 'USD'),
        'stage'          => sanitizeInput($input['stage'] ?? 'prospecting'),
        'probability'    => !empty($input['probability']) ? (int)$input['probability'] : 10,
        'expected_close' => sanitizeInput($input['expected_close'] ?? '') ?: null,
        'lead_id'        => !empty($input['lead_id']) ? (int)$input['lead_id'] : null,
        'contact_id'     => !empty($input['contact_id']) ? (int)$input['contact_id'] : null,
        'account_id'     => !empty($input['account_id']) ? (int)$input['account_id'] : null,
        'source'         => sanitizeInput($input['source'] ?? ''),
        'type'           => sanitizeInput($input['type'] ?? 'New Business'),
        'description'    => sanitizeInput($input['description'] ?? ''),
        'assigned_to'    => !empty($input['assigned_to']) ? (int)$input['assigned_to'] : null,
        'created_by'     => $userId,
    ]);
    
    logActivity($userId, 'Create Deal', 'Deal', $dealId, "Created deal: $dealName");
    jsonSuccess('Deal created', ['deal_id' => $dealId]);
}

if ($action === 'update' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $dealId = (int)($input['deal_id'] ?? 0);
    
    $deal = $db->query("SELECT * FROM deals WHERE deal_id = ? AND company_id = ?", [$dealId, $companyId])->fetch();
    if (!$deal) jsonError('Deal not found');
    
    $oldStage = $deal['stage'];
    $updates = [];
    $updatable = ['deal_name','deal_value','currency','stage','probability','expected_close',
                   'lead_id','contact_id','account_id','source','type','description','notes','assigned_to'];
    
    foreach ($updatable as $field) {
        if (isset($input[$field])) {
            if (in_array($field, ['deal_value'])) {
                $updates[$field] = (float)$input[$field];
            } elseif (in_array($field, ['lead_id','contact_id','account_id','assigned_to','probability'])) {
                $updates[$field] = $input[$field] ? (int)$input[$field] : null;
            } else {
                $updates[$field] = sanitizeInput($input[$field] ?? '');
            }
        }
    }
    
    if (!empty($updates)) {
        $db->update('deals', $updates, ['deal_id' => $dealId]);
        
        // Log stage change
        if (isset($updates['stage']) && $updates['stage'] !== $oldStage) {
            $db->insert('deal_activities', [
                'deal_id'    => $dealId,
                'user_id'    => $userId,
                'type'       => 'stage_change',
                'from_stage' => $oldStage,
                'to_stage'   => $updates['stage'],
                'company_id' => getCurrentCompanyId(),
            ]);
            
            if ($updates['stage'] === 'closed_won') {
                $updates['actual_close'] = date('Y-m-d');
            }
        }
    }
    
    logActivity($userId, 'Update Deal', 'Deal', $dealId, "Updated deal");
    jsonSuccess('Deal updated');
}

if ($action === 'delete' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $dealId = (int)($input['deal_id'] ?? 0);
    
    $deal = $db->query("SELECT * FROM deals WHERE deal_id = ? AND company_id = ?", [$dealId, $companyId])->fetch();
    if (!$deal) jsonError('Deal not found');
    
    $db->query("DELETE FROM deal_activities WHERE deal_id = ? AND company_id = ?", [$dealId, $companyId]);
    $db->query("DELETE FROM deals WHERE deal_id = ? AND company_id = ?", [$dealId, $companyId]);
    
    logActivity($userId, 'Delete Deal', 'Deal', $dealId, "Deleted deal: {$deal['deal_name']}");
    jsonSuccess('Deal deleted');
}

if ($action === 'get' && $method === 'GET') {
    $dealId = (int)($_GET['deal_id'] ?? 0);
    $deal = $db->query("
        SELECT d.*, a.account_name, 
               CONCAT(c.first_name, ' ', c.last_name) as contact_name,
               u.full_name as assigned_name
        FROM deals d
        LEFT JOIN accounts a ON d.account_id = a.account_id
        LEFT JOIN contacts c ON d.contact_id = c.contact_id
        LEFT JOIN users u ON d.assigned_to = u.user_id
        WHERE d.deal_id = ? AND d.company_id = ?", [$dealId, $companyId])->fetch();
    
    if (!$deal) jsonError('Deal not found');
    
    $activities = $db->query("
        SELECT da.*, u.full_name as user_name
        FROM deal_activities da
        JOIN users u ON da.user_id = u.user_id
        WHERE da.deal_id = ?
        ORDER BY da.created_at DESC", [$dealId])->fetchAll();
    
    $deal['activities'] = $activities;
    jsonSuccess('Deal loaded', $deal);
}

if ($action === 'move' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $dealId = (int)($input['deal_id'] ?? 0);
    $newStage = sanitizeInput($input['stage'] ?? '');
    
    $deal = $db->query("SELECT * FROM deals WHERE deal_id = ? AND company_id = ?", [$dealId, $companyId])->fetch();
    if (!$deal) jsonError('Deal not found');
    
    $oldStage = $deal['stage'];
    if ($oldStage === $newStage) jsonSuccess('No change');
    
    $updates = ['stage' => $newStage];
    if ($newStage === 'closed_won') {
        $updates['actual_close'] = date('Y-m-d');
        $updates['probability'] = 100;
    } elseif ($newStage === 'closed_lost') {
        $updates['probability'] = 0;
    }
    
    $db->update('deals', $updates, ['deal_id' => $dealId]);
    
    // Log activity
    $db->insert('deal_activities', [
        'deal_id'       => $dealId,
        'user_id'       => $userId,
        'activity_type' => 'stage_change',
        'old_value'     => $oldStage,
        'new_value'     => $newStage,
    ]);
    
    logActivity($userId, 'Move Deal', 'Deal', $dealId, "Moved to $newStage");
    jsonSuccess('Deal moved');
}

if ($action === 'stages' && $method === 'GET') {
    $stages = $db->query("
        SELECT * FROM deal_stages 
        WHERE company_id = ? OR company_id = 0 
        ORDER BY position", [$companyId])->fetchAll();
    jsonSuccess('Stages loaded', $stages);
}

if ($action === 'summary' && $method === 'GET') {
    // Pipeline summary stats
    $totalDeals = $db->query("SELECT COUNT(*) as cnt, SUM(deal_value) as total FROM deals WHERE company_id = ? AND stage NOT IN ('closed_won','closed_lost')", [$companyId])->fetch();
    $won = $db->query("SELECT COUNT(*) as cnt, SUM(deal_value) as total FROM deals WHERE company_id = ? AND stage = 'closed_won'", [$companyId])->fetch();
    $lost = $db->query("SELECT COUNT(*) as cnt, SUM(deal_value) as total FROM deals WHERE company_id = ? AND stage = 'closed_lost'", [$companyId])->fetch();
    
    jsonSuccess('Summary loaded', [
        'open_deals'    => $totalDeals['cnt'] ?? 0,
        'pipeline_value' => $totalDeals['total'] ?? 0,
        'won_deals'     => $won['cnt'] ?? 0,
        'won_value'     => $won['total'] ?? 0,
        'lost_deals'    => $lost['cnt'] ?? 0,
        'lost_value'    => $lost['total'] ?? 0,
    ]);
}

jsonError('Unknown action');
?>
