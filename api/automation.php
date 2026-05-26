<?php
/**
 * White Label CRM - Automation / Workflows API
 * Simple rule-based automation engine
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

startSecureSession();
requireLogin();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$db = Database::getInstance()->getConnection();
$userId = getCurrentUser()['user_id'] ?? 0;
$companyId = getCurrentCompanyId();

// ── CRUD ──────────────────────────────────────────────────────

if ($action === 'list' && $method === 'GET') {
    $rules = $db->query("
        SELECT r.*, u.full_name as creator_name
        FROM automation_rules r
        LEFT JOIN users u ON r.created_by = u.user_id
        WHERE r.company_id = ? OR r.company_id = 0
        ORDER BY r.is_active DESC, r.created_at DESC", [$companyId])->fetchAll();
    jsonSuccess('Rules loaded', $rules);
}

if ($action === 'create' && $method === 'POST') {
    requireCSRF();
    requireRole(['Admin','Sales Manager']);
    
    $input = json_decode(file_get_contents('php://input'), true);
    $ruleName = sanitizeInput($input['rule_name'] ?? '');
    $triggerType = sanitizeInput($input['trigger_type'] ?? '');
    $actionType = sanitizeInput($input['action_type'] ?? '');
    
    if (empty($ruleName)) jsonError('Rule name is required');
    if (empty($triggerType)) jsonError('Trigger type is required');
    if (empty($actionType)) jsonError('Action type is required');
    
    $ruleId = $db->insert('automation_rules', [
        'company_id'         => $companyId,
        'rule_name'          => $ruleName,
        'is_active'          => isset($input['is_active']) ? (int)$input['is_active'] : 1,
        'trigger_type'       => $triggerType,
        'trigger_conditions' => !empty($input['trigger_conditions']) ? json_encode($input['trigger_conditions']) : null,
        'action_type'        => $actionType,
        'action_config'      => !empty($input['action_config']) ? json_encode($input['action_config']) : '{}',
        'created_by'         => $userId,
    ]);
    
    logActivity($userId, 'Create Automation', 'Automation', $ruleId, "Created rule: $ruleName");
    jsonSuccess('Rule created', ['rule_id' => $ruleId]);
}

if ($action === 'update' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $ruleId = (int)($input['rule_id'] ?? 0);
    
    $rule = $db->query("SELECT * FROM automation_rules WHERE rule_id = ? AND (company_id = ? OR company_id = 0)", [$ruleId, $companyId])->fetch();
    if (!$rule) jsonError('Rule not found');
    
    $updates = [];
    if (isset($input['rule_name'])) $updates['rule_name'] = sanitizeInput($input['rule_name']);
    if (isset($input['is_active'])) $updates['is_active'] = (int)$input['is_active'];
    if (isset($input['trigger_type'])) $updates['trigger_type'] = sanitizeInput($input['trigger_type']);
    if (isset($input['trigger_conditions'])) $updates['trigger_conditions'] = json_encode($input['trigger_conditions']);
    if (isset($input['action_type'])) $updates['action_type'] = sanitizeInput($input['action_type']);
    if (isset($input['action_config'])) $updates['action_config'] = json_encode($input['action_config']);
    
    if (!empty($updates)) {
        $db->update('automation_rules', $updates, ['rule_id' => $ruleId]);
    }
    
    logActivity($userId, 'Update Automation', 'Automation', $ruleId, "Updated rule");
    jsonSuccess('Rule updated');
}

if ($action === 'delete' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $ruleId = (int)($input['rule_id'] ?? 0);
    
    $rule = $db->query("SELECT * FROM automation_rules WHERE rule_id = ? AND company_id = ?", [$ruleId, $companyId])->fetch();
    if (!$rule) jsonError('Rule not found');
    
    $db->query("DELETE FROM automation_logs WHERE rule_id = ?", [$ruleId]);
    $db->query("DELETE FROM automation_rules WHERE rule_id = ?", [$ruleId]);
    
    logActivity($userId, 'Delete Automation', 'Automation', $ruleId, "Deleted rule: {$rule['rule_name']}");
    jsonSuccess('Rule deleted');
}

if ($action === 'toggle' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $ruleId = (int)($input['rule_id'] ?? 0);
    
    $rule = $db->query("SELECT * FROM automation_rules WHERE rule_id = ? AND (company_id = ? OR company_id = 0)", [$ruleId, $companyId])->fetch();
    if (!$rule) jsonError('Rule not found');
    
    $newActive = $rule['is_active'] ? 0 : 1;
    $db->update('automation_rules', ['is_active' => $newActive], ['rule_id' => $ruleId]);
    
    jsonSuccess('Rule ' . ($newActive ? 'enabled' : 'disabled'));
}

if ($action === 'logs' && $method === 'GET') {
    $ruleId = $_GET['rule_id'] ?? '';
    $limit = min((int)($_GET['limit'] ?? 50), 100);
    
    $where = ["company_id = ?"];
    $params = [$companyId];
    if ($ruleId) { $where[] = "rule_id = ?"; $params[] = (int)$ruleId; }
    
    $logs = $db->query("
        SELECT l.*, r.rule_name
        FROM automation_logs l
        JOIN automation_rules r ON l.rule_id = r.rule_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY l.created_at DESC
        LIMIT $limit", $params)->fetchAll();
    
    jsonSuccess('Logs loaded', $logs);
}

// ── Execution Engine ────────────────────────────────────────────

if ($action === 'run' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $ruleId = (int)($input['rule_id'] ?? 0);
    $entityId = (int)($input['entity_id'] ?? 0);
    $entityType = sanitizeInput($input['entity_type'] ?? '');
    
    $rule = $db->query("SELECT * FROM automation_rules WHERE rule_id = ? AND (company_id = ? OR company_id = 0)", [$ruleId, $companyId])->fetch();
    if (!$rule || !$rule['is_active']) jsonError('Rule not found or inactive');
    
    $result = executeAutomationRule($db, $rule, $entityType, $entityId, $companyId, $userId);
    jsonSuccess('Rule executed', $result);
}

/**
 * Execute automation rule
 */
function executeAutomationRule($db, $rule, $entityType, $entityId, $companyId, $userId) {
    $actionConfig = json_decode($rule['action_config'] ?? '{}', true);
    $status = 'success';
    $errorMsg = null;
    $actionTaken = '';
    
    try {
        switch ($rule['action_type']) {
            case 'assign_user':
                $targetUser = $actionConfig['user_id'] ?? $userId;
                if ($entityType === 'lead') {
                    $db->query("UPDATE leads SET assigned_to = ? WHERE lead_id = ?", [$targetUser, $entityId]);
                    $actionTaken = "Assigned lead #$entityId to user #$targetUser";
                } elseif ($entityType === 'deal') {
                    $db->query("UPDATE deals SET assigned_to = ? WHERE deal_id = ?", [$targetUser, $entityId]);
                    $actionTaken = "Assigned deal #$entityId to user #$targetUser";
                }
                break;
                
            case 'create_task':
                $taskTitle = $actionConfig['title'] ?? 'Automated task';
                $dueDays = $actionConfig['due_days'] ?? 1;
                $dueDate = date('Y-m-d', strtotime("+$dueDays days"));
                $db->insert('tasks', [
                    'company_id' => $companyId,
                    'title'      => $taskTitle,
                    'status'     => 'todo',
                    'priority'   => $actionConfig['priority'] ?? 'medium',
                    'due_date'   => $dueDate,
                    'lead_id'    => $entityType === 'lead' ? $entityId : null,
                    'created_by' => $userId,
                ]);
                $actionTaken = "Created task: $taskTitle";
                break;
                
            case 'send_email':
                $to = $actionConfig['to'] ?? '';
                $subject = $actionConfig['subject'] ?? 'Automated notification';
                $body = $actionConfig['body'] ?? '';
                // Use existing email function
                if (function_exists('sendEmailViaSMTP')) {
                    sendEmailViaSMTP($to, $subject, $body);
                    $actionTaken = "Sent email to $to";
                } else {
                    $status = 'failed';
                    $errorMsg = 'Email function not available';
                }
                break;
                
            case 'move_deal':
                $targetStage = $actionConfig['stage'] ?? 'prospecting';
                $db->query("UPDATE deals SET stage = ? WHERE deal_id = ?", [$targetStage, $entityId]);
                $actionTaken = "Moved deal #$entityId to $targetStage";
                break;
                
            case 'notify_user':
                $message = $actionConfig['message'] ?? 'Automation triggered';
                // Store as system notification (simplified)
                $actionTaken = "Notification: $message";
                break;
                
            default:
                $status = 'skipped';
                $actionTaken = "Unknown action type: {$rule['action_type']}";
        }
    } catch (Exception $e) {
        $status = 'failed';
        $errorMsg = $e->getMessage();
        $actionTaken = "Failed: {$rule['action_type']}";
    }
    
    // Log execution
    $db->insert('automation_logs', [
        'rule_id'        => $rule['rule_id'],
        'company_id'     => $companyId,
        'trigger_entity' => $entityType,
        'entity_id'      => $entityId,
        'action_taken'   => $actionTaken,
        'status'         => $status,
        'error_message'  => $errorMsg,
    ]);
    
    // Update run count
    $db->query("UPDATE automation_rules SET run_count = run_count + 1, last_run_at = NOW() WHERE rule_id = ?", [$rule['rule_id']]);
    
    return ['status' => $status, 'action' => $actionTaken, 'error' => $errorMsg];
}

jsonError('Unknown action');
?>
