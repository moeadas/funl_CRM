<?php
/**
 * White Label CRM - Admin Manual Email Verification
 * POST /api/admin-verify-user.php
 * Body: { csrf_token, user_id }
 *
 * Super admin can manually verify a user's email (for cases where
 * email service is not configured or email delivery fails).
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isLoggedIn() || !isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Super admin access required.']);
    exit;
}

if (!verifyCSRFToken()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
    exit;
}

$userId = (int)($_POST['user_id'] ?? 0);

if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
    exit;
}

try {
    $db = Database::getInstance();

    $user = $db->query("SELECT user_id, email, full_name, email_verified FROM users WHERE user_id = ?", [$userId])->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    if (!empty($user['email_verified'])) {
        echo json_encode(['success' => true, 'message' => 'User is already verified.']);
        exit;
    }

    // Mark email as verified
    $db->query("UPDATE users SET email_verified = 1 WHERE user_id = ?", [$userId]);

    // Mark any pending verification tokens as used
    $db->query(
        "UPDATE email_verifications SET verified_at = NOW() WHERE user_id = ? AND verified_at IS NULL",
        [$userId]
    );

    error_log("Admin manually verified user_id={$userId} email={$user['email']}");

    echo json_encode([
        'success' => true,
        'message' => "Email verified for {$user['full_name']} ({$user['email']})"
    ]);
} catch (Exception $e) {
    error_log('admin-verify-user error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}