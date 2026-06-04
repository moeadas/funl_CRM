<?php
/**
 * Email Campaigns API
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireLogin();

header('Content-Type: application/json');

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$currentUser = getCurrentUser();
$companyId = $_SESSION['company_id'] ?? 0;

try {
    switch ($action) {
        case 'list':
            $stmt = $db->prepare("
                SELECT c.*, u.full_name as created_by_name
                FROM email_campaigns c
                LEFT JOIN users u ON c.created_by = u.user_id
                WHERE c.company_id = ?
                ORDER BY c.created_at DESC
            ");
            $stmt->execute([$companyId]);
            jsonSuccess('Campaigns loaded', ['campaigns' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'detail':
            $id = intval($_GET['id'] ?? 0);
            if (!$id) jsonError('Campaign ID required');
            $stmt = $db->prepare("
                SELECT c.*, u.full_name as created_by_name
                FROM email_campaigns c
                LEFT JOIN users u ON c.created_by = u.user_id
                WHERE c.campaign_id = ? AND c.company_id = ?
            ");
            $stmt->execute([$id, $companyId]);
            $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$campaign) jsonError('Campaign not found', 404);
            jsonSuccess('Campaign loaded', ['campaign' => $campaign]);
            break;

        case 'create':
            if ($method !== 'POST') jsonError('POST required');
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) $input = $_POST;
            
            $stmt = $db->prepare("
                INSERT INTO email_campaigns (company_id, name, subject, content_json, content_html, status, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, 'draft', ?, NOW())
            ");
            $stmt->execute([
                $companyId,
                $input['name'] ?? 'New Campaign',
                $input['subject'] ?? '',
                $input['content_json'] ?? '{}',
                $input['content_html'] ?? $input['html_content'] ?? '',
                $currentUser['user_id']
            ]);
            jsonSuccess('Campaign created', ['campaign_id' => $db->lastInsertId()]);
            break;

        case 'update':
            if ($method !== 'POST' && $method !== 'PUT') jsonError('POST/PUT required');
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) $input = $_POST;
            
            $id = intval($input['campaign_id'] ?? 0);
            if (!$id) jsonError('Campaign ID required');
            
            $stmt = $db->prepare("
                UPDATE email_campaigns SET
                    name = COALESCE(?, name),
                    subject = COALESCE(?, subject),
                    content_json = COALESCE(?, content_json),
                    content_html = COALESCE(?, content_html),
                    updated_at = NOW()
                WHERE campaign_id = ? AND company_id = ?
            ");
            $stmt->execute([
                $input['name'] ?? null,
                $input['subject'] ?? null,
                $input['content_json'] ?? null,
                $input['content_html'] ?? $input['html_content'] ?? null,
                $id, $companyId
            ]);
            jsonSuccess('Campaign updated');
            break;

        case 'update_content':
            if ($method !== 'POST') jsonError('POST required');
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) $input = $_POST;
            
            $id = intval($input['campaign_id'] ?? 0);
            if (!$id) jsonError('Campaign ID required');
            
            $stmt = $db->prepare("
                UPDATE email_campaigns SET
                    content_json = ?,
                    content_html = ?,
                    updated_at = NOW()
                WHERE campaign_id = ? AND company_id = ?
            ");
            $stmt->execute([
                $input['content_json'] ?? '{}',
                $input['html_content'] ?? '',
                $id, $companyId
            ]);
            jsonSuccess('Campaign content saved');
            break;

        case 'delete':
            $id = intval($_GET['id'] ?? 0);
            if (!$id) jsonError('Campaign ID required');
            $stmt = $db->prepare("DELETE FROM email_campaigns WHERE campaign_id = ? AND company_id = ?");
            $stmt->execute([$id, $companyId]);
            jsonSuccess('Campaign deleted');
            break;

        default:
            jsonError('Unknown action: ' . $action);
    }
} catch (Exception $e) {
    safeJsonError($e, 'Operation failed', 500);
}
?>
