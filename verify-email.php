<?php
/**
 * White Label CRM - Email Verification
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/company-functions.php';

startSecureSession();

$token = $_GET['token'] ?? '';
$message = '';
$success = false;

if ($token) {
    $result = verifyEmailToken($token);
    $success = $result['success'];
    $message = $result['message'];
} else {
    $message = __('Invalid verification link.');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('Email Verification'); ?> &mdash; <?php echo htmlspecialchars(getAppName()); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .verify-container { max-width: 400px; margin: 100px auto; text-align: center; padding: 0 20px; }
        .verify-icon { width: 64px; height: 64px; margin: 0 auto 24px; }
        .verify-icon svg { width: 100%; height: 100%; }
    </style>
</head>
<body>

<div class="verify-container">
    <div class="verify-icon">
        <?php if ($success): ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
        <?php else: ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="15" y1="9" x2="9" y2="15"/>
                <line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
        <?php endif; ?>
    </div>
    
    <h1 style="margin-bottom: 12px;"><?php echo $success ? __('Email Verified!') : __('Verification Failed'); ?></h1>
    <p style="color: var(--color-text-muted); margin-bottom: 24px;"><?php echo htmlspecialchars($message); ?></p>
    
    <a href="/login.php" class="btn btn-primary"><?php echo __('Go to Login'); ?></a>
</div>

</body>
</html>
