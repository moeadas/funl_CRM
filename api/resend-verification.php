<?php
/**
 * White Label CRM - Resend Verification Email API
 * POST /api/resend-verification.php
 * Body: { csrf_token }
 *
 * Generates a fresh verification token for the currently logged-in user
 * (rate-limited to 1/min, max 5/hour) and re-sends the verification email.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/resend-email.php';

startSecureSession();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not signed in.']);
    exit;
}

if (!verifyCSRFToken()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid request token. Please refresh the page and try again.']);
    exit;
}

$userId    = getCurrentUserId();
$userEmail = $_SESSION['email']    ?? '';
$fullName  = $_SESSION['full_name'] ?? '';

if (empty($userEmail) || empty($userId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing user information.']);
    exit;
}

try {
    $db = Database::getInstance();

    // Already verified? Nothing to do.
    $row = $db->query(
        "SELECT email_verified FROM users WHERE user_id = ?",
        [$userId]
    )->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }
    if (!empty($row['email_verified'])) {
        echo json_encode(['success' => true, 'message' => 'Your email is already verified.']);
        exit;
    }

    // Rate limit: at most 1 resend per 60 seconds, max 5 per hour
    $recent = $db->query(
        "SELECT COUNT(*) AS c
           FROM email_verifications
          WHERE user_id = ?
            AND created_at > (NOW() - INTERVAL 1 HOUR)",
        [$userId]
    )->fetch(PDO::FETCH_ASSOC);

    if (intval($recent['c'] ?? 0) >= 5) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Too many resend attempts. Please try again in an hour.'
        ]);
        exit;
    }

    $lastRow = $db->query(
        "SELECT created_at
           FROM email_verifications
          WHERE user_id = ?
       ORDER BY verification_id DESC
          LIMIT 1",
        [$userId]
    )->fetch(PDO::FETCH_ASSOC);

    if ($lastRow && strtotime($lastRow['created_at']) > (time() - 60)) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Please wait a minute before requesting another email.'
        ]);
        exit;
    }

    // Invalidate any previous unverified rows
    $db->query(
        "UPDATE email_verifications
            SET verified_at = NOW()
          WHERE user_id = ?
            AND verified_at IS NULL",
        [$userId]
    );

    // Generate a fresh token
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    $db->query(
        "INSERT INTO email_verifications (user_id, email, token, expires_at)
         VALUES (?, ?, ?, ?)",
        [$userId, $userEmail, $token, $expires]
    );

    // Send the email (ResendEmailService::sendVerificationEmail)
    $sent = sendVerificationEmail($userEmail, $fullName, $token);

    if (!$sent) {
        // Even if delivery failed, don't leak that the user exists.
        echo json_encode([
            'success' => true,
            'message' => 'If your account exists, a new verification email has been sent.'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'A new verification email has been sent to ' . $userEmail . '.'
    ]);
} catch (Exception $e) {
    error_log('resend-verification error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not process the request.']);
}
