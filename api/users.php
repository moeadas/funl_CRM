<?php
/**
 * White Label CRM - Users API
 * SiteGround MySQL compatible
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
requireLogin();

$action = $_GET['action'] ?? '';
$db = Database::getInstance();
$currentUser = getCurrentUser();
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
    if ($companyId) {
        $users = $db->query("SELECT user_id, username, email, full_name, role, status FROM users WHERE company_id = ? AND status = 'Active' ORDER BY full_name", [$companyId])->fetchAll();
    } else {
        $users = $db->query("SELECT user_id, username, email, full_name, role, status FROM users WHERE status = 'Active' ORDER BY full_name")->fetchAll();
    }
    jsonSuccess('Users loaded', ['users' => $users]);
}

jsonError('Unknown action');
?>
