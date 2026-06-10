<?php
/**
 * White Label CRM - WhatsApp New Message (standalone, no popup)
 */
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$csrfToken = generateCSRFToken();
$pageTitle = __('new_whatsapp_message');
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;">
        <a href="/pages/whatsapp-dashboard.php" class="btn btn-outline" style="padding:8px 14px;display:inline-flex;align-items:center;gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            <?php echo htmlspecialchars(__('Back to WhatsApp')); ?>
        </a>
        <h1><?php echo htmlspecialchars(__('New WhatsApp message')); ?></h1>
    </div>
</div>

<div style="max-width:600px;">
    <div class="card">
        <div class="card-header" style="padding:18px 24px;background:#f0fdf4;">
            <h3 class="card-title" style="margin:0;display:flex;align-items:center;gap:8px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                <?php echo htmlspecialchars(__('Compose message')); ?>
            </h3>
        </div>
        <form id="newMessageForm" style="padding:24px;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Phone number')); ?></label>
                <input type="tel" id="waMsgNumber" name="phone" class="form-control"
                       placeholder="+971 50 123 4567"
                       style="padding:10px 14px;" required>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label"><?php echo htmlspecialchars(__('Message')); ?></label>
                <textarea id="waMsgBody" name="message" class="form-control" rows="6"
                          placeholder="<?php echo htmlspecialchars(__('Type your message...')); ?>"
                          style="padding:10px 14px;min-height:140px;"></textarea>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px;">
                    <small class="text-muted"><?php echo htmlspecialchars(__('Recipient will see this message in their WhatsApp')); ?></small>
                    <small class="text-muted"><span id="charCount">0</span> / 4096</small>
                </div>
            </div>
        </form>
        <div style="padding:16px 24px;display:flex;justify-content:flex-end;gap:8px;border-top:1px solid #e5e7eb;">
            <a href="/pages/whatsapp-dashboard.php" class="btn btn-outline"><?php echo htmlspecialchars(__('Cancel')); ?></a>
            <button type="button" class="btn btn-primary" id="sendMsgBtn" style="background:#16a34a;border-color:#16a34a;" onclick="sendQuickMessage()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px;"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                <?php echo htmlspecialchars(__('Send')); ?>
            </button>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;

// Character counter
document.getElementById('waMsgBody').addEventListener('input', function() {
    document.getElementById('charCount').textContent = this.value.length;
});

function sendQuickMessage() {
    const phone = document.getElementById('waMsgNumber').value.trim();
    const body = document.getElementById('waMsgBody').value.trim();

    if (!phone) { showNotification('Phone number is required', 'error'); return; }
    if (!body) { showNotification('Message is required', 'error'); return; }

    const btn = document.getElementById('sendMsgBtn');
    btn.disabled = true;
    btn.textContent = 'Sending…';

    fetch('/api/whatsapp.php?action=send_message', {
        method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ phone, message: body, csrf_token: CSRF_TOKEN })
    }).then(r => r.json()).then(resp => {
        if (resp.success) {
            showNotification('Message sent', 'success');
            setTimeout(() => window.location.href = '/pages/whatsapp-dashboard.php', 700);
        } else {
            showNotification(resp.message || 'Failed to send', 'error');
            btn.disabled = false;
            btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px;"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Send';
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
