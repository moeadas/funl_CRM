<?php
/**
 * White Label CRM - VoIP Quick Dial (standalone, no popup)
 */
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$csrfToken = generateCSRFToken();
$pageTitle = __('Quick Dial');
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;">
        <a href="/pages/voip-dashboard.php" class="btn btn-outline" style="padding:8px 14px;display:inline-flex;align-items:center;gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            <?php echo htmlspecialchars(__('Back to VoIP')); ?>
        </a>
        <h1><?php echo htmlspecialchars(__('Quick Dial')); ?></h1>
    </div>
</div>

<div style="max-width:520px;">
    <div class="card">
        <div class="card-header" style="padding:18px 24px;background:#f0fdf4;">
            <h3 class="card-title" style="margin:0;display:flex;align-items:center;gap:8px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                <?php echo htmlspecialchars(__('Make a call')); ?>
            </h3>
        </div>
        <form id="dialForm" style="padding:24px;" onsubmit="event.preventDefault(); dialNumber();">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label"><?php echo htmlspecialchars(__('Phone number')); ?></label>
                <input type="tel" id="dialNumber" class="form-control"
                       placeholder="+1 858 358 5260"
                       autofocus
                       style="font-size:18px;letter-spacing:0.5px;padding:14px 16px;">
                <small class="text-muted"><?php echo htmlspecialchars(__('Enter a phone number with country code (e.g. +1 for US)')); ?></small>
            </div>
        </form>
        <div style="padding:16px 24px;display:flex;justify-content:flex-end;gap:8px;border-top:1px solid #e5e7eb;">
            <a href="/pages/voip-dashboard.php" class="btn btn-outline"><?php echo htmlspecialchars(__('Cancel')); ?></a>
            <button type="button" class="btn btn-primary" style="background:#16a34a;border-color:#16a34a;" onclick="dialNumber()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px;"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                <?php echo htmlspecialchars(__('Call')); ?>
            </button>
        </div>
    </div>
</div>

<script>
function dialNumber() {
    var num = document.getElementById('dialNumber').value.trim();
    if (!num) { showNotification('Enter a phone number', 'error'); return; }
    if (typeof VoIPPhone !== 'undefined' && VoIPPhone.call) {
        VoIPPhone.call(num, 0);
    } else {
        // Fallback: redirect to dashboard with call init
        window.location.href = '/pages/voip-dashboard.php?dial=' + encodeURIComponent(num);
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
