<?php
/**
 * White Label CRM V2 — Public Unsubscribe Page
 * Included by api/email.php (no direct access)
 * Variables available: $log, $token
 */
if (!isset($log) && !isset($token)) { header('Location: /login.php'); exit; }
if (!function_exists('__')) { require_once __DIR__ . '/../includes/functions.php'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('Unsubscribe'); ?> — Your Company</title>
    <link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="main-container">
        <div class="login-card unsub-card">
            <div class="login-header">
                <img src="/assets/images/VG%20logo.svg" alt="Your Company" class="login-logo">
                <h1 class="login-title"><?php echo __('Unsubscribe'); ?></h1>
            </div>

            <?php if ($log): ?>
                <div id="unsubContent">
                    <p class="unsub-text">
                        <?php echo sprintf(__('Are you sure you want to unsubscribe %s from our mailing list?'), '<strong>' . htmlspecialchars($log['email']) . '</strong>'); ?>
                    </p>
                    <button onclick="confirmUnsub()" class="btn btn-primary btn-block" id="unsubBtn"><?php echo __('Yes, Unsubscribe Me'); ?></button>
                    <p class="unsub-footer">
                        <?php echo __('You can also contact us at'); ?>
                        <?php
                        // Use platform-configured support email (set in Super Admin > Platform Settings),
                        // falling back to the legacy hardcoded address.
                        $platformSupport = '';
                        try {
                            $row = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'platform_support_email' AND company_id IS NULL")->fetch();
                            $platformSupport = $row['setting_value'] ?? '';
                        } catch (Exception $e) { /* ignore */ }
                        $supportEmail = $platformSupport ?: 'support@funl.online';
                        ?>
                        <a href="mailto:<?php echo htmlspecialchars($supportEmail); ?>"><?php echo htmlspecialchars($supportEmail); ?></a>
                    </p>
                </div>
                <div id="unsubDone" class="unsub-done" style="display:none;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--color-success)" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <h2><?php echo __('You\'ve been unsubscribed'); ?></h2>
                    <p><?php echo __('You will no longer receive marketing emails from us.'); ?></p>
                </div>
            <?php else: ?>
                <p class="unsub-text"><?php echo __('Invalid or expired unsubscribe link.'); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function confirmUnsub() {
        document.getElementById('unsubBtn').disabled = true;
        document.getElementById('unsubBtn').textContent = 'Processing...';
        fetch('/api/email.php?action=unsubscribe_confirm', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'token=<?php echo urlencode($token); ?>'
        }).then(r => r.json()).then(d => {
            document.getElementById('unsubContent').style.display = 'none';
            document.getElementById('unsubDone').style.display = '';
        }).catch(() => {
            document.getElementById('unsubContent').style.display = 'none';
            document.getElementById('unsubDone').style.display = '';
        });
    }
    </script>
</body>
</html>
