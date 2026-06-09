<?php
/**
 * White Label CRM - Email Verification
 *
 * Supports three flows:
 *   1. ?token=***   → consume the verification token
 *   2. Logged in, not yet verified → friendly "check your email" + resend
 *   3. Logged out, no token        → "check your email" with a link to login
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/company-functions.php';

startSecureSession();

$token   = $_GET['token'] ?? '';
$message = '';
$success = false;
$state   = 'unknown'; // unknown | token_ok | token_bad | pending | not_logged_in

if ($token) {
    $result = verifyEmailToken($token);
    $success = $result['success'];
    $message = $result['message'];
    $state   = $success ? 'token_ok' : 'token_bad';
    
    // If successful verification and there's a pending subscription, redirect to billing
    if ($success && !empty($_SESSION['pending_subscription'])) {
        header('Location: /pages/billing.php?start_subscription=1');
        exit;
    }
} elseif (isLoggedIn()) {
    // Logged in. If already verified, send them to the dashboard.
    if (!empty($_SESSION['email_verified'])) {
        header('Location: /pages/dashboard.php');
        exit;
    }
    $state = 'pending';

    // Find the most recent pending verification row for this user
    try {
        $db = Database::getInstance();
        $row = $db->query(
            "SELECT email, created_at, expires_at,
                    (verified_at IS NULL AND expires_at > NOW()) AS is_active
               FROM email_verifications
              WHERE user_id = ?
           ORDER BY verification_id DESC
              LIMIT 1",
            [$_SESSION['user_id']]
        )->fetch(PDO::FETCH_ASSOC);
        $userEmail = $row['email'] ?? $_SESSION['email'] ?? '';
    } catch (Exception $e) {
        $userEmail = $_SESSION['email'] ?? '';
    }
} else {
    $state = 'not_logged_in';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width-scale=1.0">
    <title><?php echo __('Email Verification'); ?> &mdash; <?php echo htmlspecialchars(getAppName()); ?></title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .verify-container {
            max-width: 460px;
            margin: 80px auto;
            text-align: center;
            padding: 40px 32px;
            background: var(--color-surface, #fff);
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
        }
        .verify-icon { width: 64px; height: 64px; margin: 0 auto 20px; }
        .verify-icon svg { width: 100%; height: 100%; }
        .verify-email-line {
            font-size: 14px;
            color: var(--color-text-secondary, #6b7280);
            margin: -8px 0 24px;
            word-break: break-all;
        }
        .verify-actions { display: flex; flex-direction: column; gap: 10px; align-items: center; }
        .verify-resend-row { margin-top: 20px; }
        #resend-msg { font-size: 13px; min-height: 18px; margin-top: 10px; }
        #resend-msg.ok  { color: #16a34a; }
        #resend-msg.err { color: #dc2626; }
    </style>
</head>
<body>

<div class="verify-container">
    <div class="verify-icon">
        <?php if ($state === 'token_ok'): ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
        <?php elseif ($state === 'token_bad'): ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="15" y1="9" x2="9" y2="15"/>
                <line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
        <?php else: ?>
            <!-- Mail icon for pending / not_logged_in states -->
            <svg viewBox="0 0 24 24" fill="none" stroke="#007bff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                <polyline points="22,6 12,13 2,6"/>
            </svg>
        <?php endif; ?>
    </div>

    <?php if ($state === 'token_ok'): ?>
        <h1 style="margin-bottom: 12px;"><?php echo __('Email Verified!'); ?></h1>
        <p style="color: var(--color-text-muted, #6b7280); margin-bottom: 24px;">
            <?php echo htmlspecialchars($message); ?>
        </p>
        <a href="/pages/dashboard.php" class="btn btn-primary"><?php echo __('Go to Dashboard'); ?></a>

    <?php elseif ($state === 'token_bad'): ?>
        <h1 style="margin-bottom: 12px;"><?php echo __('Verification Failed'); ?></h1>
        <p style="color: var(--color-text-muted, #6b7280); margin-bottom: 24px;">
            <?php echo htmlspecialchars($message); ?>
        </p>
        <div class="verify-actions">
            <a href="/login.php" class="btn btn-primary"><?php echo __('Go to Login'); ?></a>
            <?php if (isLoggedIn()): ?>
                <a href="?resend=1" class="btn btn-outline" id="resend-link"><?php echo __('Resend verification email'); ?></a>
            <?php endif; ?>
        </div>

    <?php elseif ($state === 'pending'): ?>
        <h1 style="margin-bottom: 8px;"><?php echo __('Check your email'); ?></h1>
        <p style="color: var(--color-text-muted, #6b7280); margin-bottom: 8px;">
            <?php echo __('We sent a verification link to:'); ?>
        </p>
        <p class="verify-email-line"><strong><?php echo htmlspecialchars($userEmail); ?></strong></p>
        <p style="color: var(--color-text-muted, #6b7280); margin-bottom: 24px; font-size: 14px;">
            <?php echo __('Click the link in the email to activate your account. The link expires in 24 hours.'); ?>
        </p>
        <div class="verify-actions">
            <button type="button" class="btn btn-primary" id="resend-btn"><?php echo __('Resend verification email'); ?></button>
            <a href="/logout.php" class="btn btn-outline"><?php echo __('Sign out'); ?></a>
        </div>
        <p id="resend-msg"></p>

    <?php else: /* not_logged_in */ ?>
        <h1 style="margin-bottom: 12px;"><?php echo __('Check your email'); ?></h1>
        <p style="color: var(--color-text-muted, #6b7280); margin-bottom: 24px;">
            <?php echo __('To continue verifying your account, please sign in first.'); ?>
        </p>
        <div class="verify-actions">
            <a href="/login.php" class="btn btn-primary"><?php echo __('Go to Login'); ?></a>
        </div>
    <?php endif; ?>
</div>

<?php if ($state === 'pending'): ?>
<script>
(function () {
    var btn = document.getElementById('resend-btn');
    var msg = document.getElementById('resend-msg');
    if (!btn) return;

    // CSRF token: read from the <meta name="csrf-token"> tag.
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';

    btn.addEventListener('click', function () {
        btn.disabled = true;
        btn.textContent = '<?php echo __('Sending...'); ?>';
        msg.className = '';
        msg.textContent = '';

        fetch('/api/resend-verification.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: csrf })
        }).then(function (r) { return r.json(); })
          .then(function (j) {
              if (j && j.success) {
                  msg.className = 'ok';
                  msg.textContent = j.message || '<?php echo __('Verification email sent.'); ?>';
              } else {
                  msg.className = 'err';
                  msg.textContent = (j && j.message) || '<?php echo __('Could not send email. Please try again later.'); ?>';
              }
          }).catch(function () {
              msg.className = 'err';
              msg.textContent = '<?php echo __('Network error. Please try again.'); ?>';
          }).finally(function () {
              btn.disabled = false;
              btn.textContent = '<?php echo __('Resend verification email'); ?>';
          });
    });
})();
</script>
<?php endif; ?>

</body>
</html>
