<?php
/**
 * Pinpoint CRM — Web Forms API with Field Mapping
 */
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

header('Content-Type: application/json; charset=utf-8');
$db = Database::getInstance();
$pdo = $db->getConnection();
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ══════════════════════════════════════════════════════════════
//  LIST forms
// ══════════════════════════════════════════════════════════════
if ($action === 'list') {
    $companyId = getCurrentCompanyId();
    
    $stmt = $pdo->prepare("
        SELECT f.*, 
            (SELECT COUNT(*) FROM webform_submissions WHERE form_id = f.form_id) as submission_count,
            (SELECT COUNT(*) FROM webform_fields WHERE form_id = f.form_id) as field_count
        FROM webforms f
        WHERE f.company_id = ?
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$companyId]);
    
    echo json_encode(['success' => true, 'forms' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ══════════════════════════════════════════════════════════════
//  GET single form with fields
// ══════════════════════════════════════════════════════════════
if ($action === 'get') {
    $formId = intval($_GET['id'] ?? 0);
    $companyId = getCurrentCompanyId();
    
    $stmt = $pdo->prepare("SELECT * FROM webforms WHERE form_id = ? AND company_id = ?");
    $stmt->execute([$formId, $companyId]);
    $form = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($form) {
        $fieldStmt = $pdo->prepare("SELECT * FROM webform_fields WHERE form_id = ? ORDER BY position ASC");
        $fieldStmt->execute([$formId]);
        $form['fields'] = $fieldStmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'form' => $form]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Form not found']);
    }
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
    $formId = intval($input['form_id'] ?? 0);
    
    $pdo->beginTransaction();
    
    try {
        if ($formId) {
            // Update form
            $stmt = $pdo->prepare("
                UPDATE webforms 
                SET form_name = ?, description = ?, status = ?, updated_at = NOW()
                WHERE form_id = ? AND company_id = ?
            ");
            $stmt->execute([
                $input['form_name'] ?? '',
                $input['description'] ?? '',
                $input['status'] ?? 'active',
                $formId,
                $companyId
            ]);
            
            // Delete old fields
            $pdo->prepare("DELETE FROM webform_fields WHERE form_id = ?")->execute([$formId]);
        } else {
            // Create form
            $stmt = $pdo->prepare("
                INSERT INTO webforms (company_id, form_name, description, status, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $companyId,
                $input['form_name'] ?? '',
                $input['description'] ?? '',
                'active',
                $userId
            ]);
            $formId = $pdo->lastInsertId();
        }
        
        // Insert fields
        if (!empty($input['fields']) && is_array($input['fields'])) {
            $fieldStmt = $pdo->prepare("
                INSERT INTO webform_fields (form_id, field_label, crm_field, field_type, position, required)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($input['fields'] as $idx => $field) {
                $fieldStmt->execute([
                    $formId,
                    $field['label'] ?? '',
                    $field['crm_field'] ?? '',
                    $field['type'] ?? 'text',
                    $idx,
                    $field['required'] ?? 0
                ]);
            }
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => $formId ? 'Form updated' : 'Form created', 'form_id' => $formId]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
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
    
    $formId = intval($input['form_id'] ?? 0);
    $companyId = getCurrentCompanyId();
    
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM webform_fields WHERE form_id = ?")->execute([$formId]);
        $pdo->prepare("DELETE FROM webform_submissions WHERE form_id = ?")->execute([$formId]);
        $pdo->prepare("DELETE FROM webforms WHERE form_id = ? AND company_id = ?")->execute([$formId, $companyId]);
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Form deleted']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
