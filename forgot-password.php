<?php
/**
 * Forgot Password Page
 * User enters email → generates token → sends reset link via email
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = __('Invalid request. Please try again.');
    } else {
        $email = sanitizeInput($_POST['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = __('Please enter a valid email address.');
        } else {
            $db = Database::getInstance();

            // Check if user exists with this email
            $user = $db->query(
                "SELECT user_id, full_name, email, status FROM users WHERE email = ? AND status = 'Active'",
                [$email]
            )->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Generate secure token
                $token = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

                // Delete any existing tokens for this email
                $db->query("DELETE FROM password_resets WHERE email = ?", [$email]);

                // Insert new token
                $db->insert('password_resets', [
                    'email'      => $email,
                    'token'      => $token,
                    'expires_at' => $expiresAt,
                ]);

                // Build reset link
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'app.funl.online';
                $resetLink = $scheme . '://' . $host . '/reset-password.php?token=' . $token;

                // Send email
                $siteName = getAppName();
                $subject = "Password Reset — {$siteName}";
                $body = "Hi {$user['full_name']},\n\n";
                $body .= "We received a request to reset your password for your {$siteName} account.\n\n";
                $body .= "Click the link below to set a new password:\n";
                $body .= $resetLink . "\n\n";
                $body .= "This link will expire in 1 hour.\n\n";
                $body .= "If you didn't request a password reset, you can safely ignore this email.\n\n";
                $body .= "Thanks,\nThe {$siteName} Team";

                $fromEmail = getSetting('platform_support_email') ?: 'noreply@funl.online';
                $headers = 'From: ' . $siteName . ' <' . $fromEmail . ">\r\n";
                $headers .= 'Content-Type: text/plain; charset=UTF-8';

                @mail($email, $subject, $body, $headers);
            }

            // Always show success (don't reveal whether email exists)
            $success = __('If an account exists with that email, a password reset link has been sent. Please check your inbox.');
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
    <title><?php echo __('Forgot Password'); ?> &mdash; <?php echo htmlspecialchars(getAppName()); ?></title>
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
                <p class="login-subtitle"><?php echo __('Reset your password'); ?></p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
                <div style="text-align:center;margin-top:20px;">
                    <a href="/login.php" class="btn btn-outline btn-block"><?php echo __('Back to Login'); ?></a>
                </div>
            <?php else: ?>
                <p style="color:var(--color-text-secondary);font-size:14px;margin-bottom:20px;">
                    <?php echo __('Enter your email address and we\'ll send you a link to reset your password.'); ?>
                </p>

                <form method="POST" action="">
                    <?php echo csrfField(); ?>

                    <div class="form-group">
                        <label class="form-label" for="email"><?php echo __('Email Address'); ?></label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="<?php echo __('Enter your email'); ?>" required autofocus autocomplete="email">
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        <?php echo __('Send Reset Link'); ?>
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