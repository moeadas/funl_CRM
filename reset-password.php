<?php
/**
 * Reset Password Page
 * User clicks link from email → sets new password
 */
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSecureSession();

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: /pages/dashboard.php');
    exit;
}

$error = '';
$success = '';
$csrf_token = generateCSRFToken();
$validToken = false;
$email = '';

// Check token from URL
$token = $_GET['token'] ?? '';
if (!empty($token)) {
    $db = Database::getInstance();
    $reset = $db->query(
        "SELECT * FROM password_resets WHERE token = ? AND used_at IS NULL AND expires_at > NOW()",
        [$token]
    )->fetch(PDO::FETCH_ASSOC);

    if ($reset) {
        $validToken = true;
        $email = $reset['email'];
    } else {
        $error = __('This password reset link is invalid or has expired. Please request a new one.');
    }
} else {
    $error = __('No reset token provided. Please request a password reset link.');
}

// Handle new password submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = __('Invalid request. Please try again.');
    } else {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $postToken = $_POST['token'] ?? '';

        // Verify token again from POST
        $db = Database::getInstance();
        $reset = $db->query(
            "SELECT * FROM password_resets WHERE token = ? AND used_at IS NULL AND expires_at > NOW()",
            [$postToken]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$reset) {
            $error = __('This reset link is no longer valid.');
            $validToken = false;
        } elseif (strlen($newPassword) < 8) {
            $error = __('Password must be at least 8 characters long.');
        } elseif ($newPassword !== $confirmPassword) {
            $error = __('Passwords do not match.');
        } else {
            // Update password
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $db->query("UPDATE users SET password_hash = ?, must_change_password = 0 WHERE email = ?", [$hash, $reset['email']]);

            // Mark token as used
            $db->query("UPDATE password_resets SET used_at = NOW() WHERE token = ?", [$postToken]);

            $success = __('Your password has been reset successfully. You can now log in with your new password.');
            $validToken = false; // Hide form after success
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#ffffff">
    <title><?php echo __('Reset Password'); ?> &mdash; <?php echo htmlspecialchars(getAppName()); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="icon" href="<?php echo getCompanyFavicon(); ?>">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php require_once __DIR__ . '/includes/preloader.php'; ?>
    <div class="main-container">
        <div class="login-card">
            <div class="login-header">
                <img src="<?php echo getCompanyLogo(); ?>" alt="<?php echo htmlspecialchars(getAppName()); ?>" class="login-logo" style="max-height:60px;max-width:200px;">
                <h1 class="login-title"><?php echo htmlspecialchars(getAppName()); ?></h1>
                <p class="login-subtitle"><?php echo __('Set a new password'); ?></p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
                <div style="text-align:center;margin-top:20px;">
                    <a href="/forgot-password.php" class="btn btn-outline btn-block"><?php echo __('Request New Link'); ?></a>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
                <div style="text-align:center;margin-top:20px;">
                    <a href="/login.php" class="btn btn-primary btn-block"><?php echo __('Sign In'); ?></a>
                </div>
            <?php endif; ?>

            <?php if ($validToken && !$success): ?>
                <p style="color:var(--color-text-secondary);font-size:14px;margin-bottom:20px;">
                    <?php echo __('Enter your new password below.'); ?>
                </p>

                <form method="POST" action="">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                    <div class="form-group">
                        <label class="form-label" for="new_password"><?php echo __('New Password'); ?></label>
                        <input type="password" id="new_password" name="new_password" class="form-control" placeholder="<?php echo __('Enter new password'); ?>" required autofocus autocomplete="new-password" minlength="8">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm_password"><?php echo __('Confirm Password'); ?></label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="<?php echo __('Re-enter new password'); ?>" required autocomplete="new-password" minlength="8">
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        <?php echo __('Reset Password'); ?>
                    </button>
                </form>
            <?php endif; ?>

            <div class="login-footer">
                <p style="text-align:center;margin-bottom:12px;">
                    <a href="/login.php"><?php echo __('Back to Login'); ?></a>
                </p>
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(getAppName()); ?><?php echo __('.&nbsp;All rights reserved.'); ?></p>
            </div>
        </div>
    </div>
</body>
</html>