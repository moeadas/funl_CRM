<?php
/**
 * White Label CRM - Custom Fields API
 * CRUD operations for custom lead fields
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

startSecureSession();
requireLogin();
requireRole(['Admin']);

$action = $_GET['action'] ?? '';
$db = Database::getInstance()->getConnection();
$currentUser = getCurrentUser();
$companyId = $currentUser['company_id'] ?? null;

switch ($action) {
    case 'list':
        try {
            if ($companyId) {
                $stmt = $db->prepare("SELECT * FROM custom_fields WHERE company_id = ? OR company_id IS NULL ORDER BY sort_order ASC, field_id ASC");
                $stmt->execute([$companyId]);
            } else {
                $stmt = $db->query("SELECT * FROM custom_fields ORDER BY sort_order ASC, field_id ASC");
            }
            $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
            jsonSuccess('Custom fields loaded', $fields);
        } catch (Exception $e) {
            jsonError($e->getMessage());
        }
        break;

    case 'create':
        requireCSRF();
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        try {
            $stmt = $db->prepare("
                INSERT INTO custom_fields (company_id, field_name, field_label, field_type, field_options, is_required, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $companyId,
                $data['field_name'],
                $data['field_label'],
                $data['field_type'] ?? 'text',
                $data['field_options'] ?? null,
                ($data['is_required'] ?? false) ? 1 : 0,
                $data['sort_order'] ?? 0
            ]);
            logActivity(getCurrentUserId(), 'Create Custom Field', 'CustomField', $db->getConnection()->lastInsertId(), "Created field: {$data['field_label']}");
            jsonSuccess('Custom field created');
        } catch (Exception $e) {
            jsonError($e->getMessage());
        }
        break;

    case 'update':
        requireCSRF();
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        try {
            $where = 'WHERE field_id = ?';
            $params = [
                $data['field_name'],
                $data['field_label'],
                $data['field_type'],
                $data['field_options'] ?? null,
                ($data['is_required'] ?? false) ? 1 : 0,
                $data['sort_order'] ?? 0,
                ($data['is_active'] ?? true) ? 1 : 0,
                $data['field_id']
            ];
            
            if ($companyId) {
                $where = 'WHERE field_id = ? AND (company_id = ? OR company_id IS NULL)';
                $params[] = $companyId;
            }
            
            $stmt = $db->prepare("
                UPDATE custom_fields 
                SET field_name = ?, field_label = ?, field_type = ?, field_options = ?, is_required = ?, sort_order = ?, is_active = ?
                $where
            ");
            $stmt->execute($params);
            logActivity(getCurrentUserId(), 'Update Custom Field', 'CustomField', $data['field_id'], "Updated field: {$data['field_label']}");
            jsonSuccess('Custom field updated');
        } catch (Exception $e) {
            jsonError($e->getMessage());
        }
        break;

    case 'delete':
        requireCSRF();
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        try {
            $where = 'WHERE field_id = ?';
            $params = [$data['field_id']];
            
            if ($companyId) {
                $where = 'WHERE field_id = ? AND (company_id = ? OR company_id IS NULL)';
                $params[] = $companyId;
            }
            
            // Delete values first (FK constraint)
            $stmt = $db->prepare("DELETE FROM lead_custom_values WHERE field_id = ?");
            $stmt->execute([$data['field_id']]);
            
            // Delete field
            $stmt = $db->prepare("DELETE FROM custom_fields $where");
            $stmt->execute($params);
            
            logActivity(getCurrentUserId(), 'Delete Custom Field', 'CustomField', $data['field_id'], "Deleted field #{$data['field_id']}");
            jsonSuccess('Custom field deleted');
        } catch (Exception $e) {
            jsonError($e->getMessage());
        }
        break;

    case 'reorder':
        requireCSRF();
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        try {
            $db->beginTransaction();
            foreach ($data['fields'] as $field) {
                $stmt = $db->prepare("UPDATE custom_fields SET sort_order = ? WHERE field_id = ?");
                $stmt->execute([$field['sort_order'], $field['field_id']]);
            }
            $db->commit();
            jsonSuccess('Fields reordered');
        } catch (Exception $e) {
            $db->rollBack();
            jsonError($e->getMessage());
        }
        break;

    default:
        jsonError('Unknown action', 400);
}
