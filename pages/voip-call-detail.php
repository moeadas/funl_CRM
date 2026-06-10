<?php
/**
 * White Label CRM - VoIP Call Detail (standalone, no popup)
 */
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$callId = intval($_GET['id'] ?? 0);
if (!$callId) {
    header('Location: /pages/voip-dashboard.php');
    exit;
}

$db = Database::getInstance();
$companyId = $_SESSION['company_id'] ?? null;
$userId = $_SESSION['user_id'] ?? null;

$stmt = $db->prepare("SELECT v.*, u.full_name as user_name, l.contact_person, l.company_name
    FROM voip_calls v
    LEFT JOIN users u ON v.user_id = u.user_id
    LEFT JOIN leads l ON v.lead_id = l.lead_id
    WHERE v.call_id = ? AND (v.company_id = ? OR v.user_id = ?)");
$stmt->execute([$callId, $companyId, $userId]);
$call = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$call) {
    header('Location: /pages/voip-dashboard.php');
    exit;
}

$dur = (int)($call['duration_seconds'] ?? 0);
$durStr = $dur >= 3600
    ? floor($dur/3600) . ':' . str_pad(floor(($dur%3600)/60), 2, '0', STR_PAD_LEFT) . ':' . str_pad($dur%60, 2, '0', STR_PAD_LEFT)
    : floor($dur/60) . ':' . str_pad($dur%60, 2, '0', STR_PAD_LEFT);

$pageTitle = __('Call Details');
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;">
        <a href="/pages/voip-dashboard.php" class="btn btn-outline" style="padding:8px 14px;display:inline-flex;align-items:center;gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            <?php echo htmlspecialchars(__('Back to VoIP')); ?>
        </a>
        <h1><?php echo htmlspecialchars(__('Call Details')); ?></h1>
    </div>
    <?php if (!empty($call['recording_url'])): ?>
    <div class="header-actions">
        <button class="btn btn-primary" onclick="playRecording('<?php echo htmlspecialchars($call['recording_url'], ENT_QUOTES); ?>')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px;"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            <?php echo htmlspecialchars(__('Play recording')); ?>
        </button>
    </div>
    <?php endif; ?>
</div>

<div style="max-width:640px;">
    <div class="card">
        <div class="card-header" style="padding:18px 24px;">
            <h3 class="card-title" style="margin:0;display:flex;align-items:center;gap:8px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                <?php echo htmlspecialchars(__('Call summary')); ?>
            </h3>
        </div>
        <div class="card-body" style="padding:24px;">
            <dl style="display:grid;grid-template-columns:160px 1fr;gap:14px 20px;margin:0;font-size:14px;">
                <dt class="text-muted"><?php echo htmlspecialchars(__('Direction')); ?></dt>
                <dd style="margin:0;">
                    <span class="badge <?php echo $call['direction'] === 'Inbound' ? 'bg-green-100' : 'bg-blue-100'; ?>" style="padding:4px 10px;border-radius:6px;font-size:12px;font-weight:600;">
                        <?php echo htmlspecialchars($call['direction'] ?? '-'); ?>
                    </span>
                </dd>

                <dt class="text-muted"><?php echo htmlspecialchars(__('From')); ?></dt>
                <dd style="margin:0;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;"><?php echo htmlspecialchars($call['from_number'] ?? '-'); ?></dd>

                <dt class="text-muted"><?php echo htmlspecialchars(__('To')); ?></dt>
                <dd style="margin:0;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;"><?php echo htmlspecialchars($call['to_number'] ?? '-'); ?></dd>

                <dt class="text-muted"><?php echo htmlspecialchars(__('Status')); ?></dt>
                <dd style="margin:0;"><?php echo htmlspecialchars($call['status'] ?? '-'); ?></dd>

                <dt class="text-muted"><?php echo htmlspecialchars(__('Duration')); ?></dt>
                <dd style="margin:0;"><strong><?php echo htmlspecialchars($durStr); ?></strong></dd>

                <dt class="text-muted"><?php echo htmlspecialchars(__('Outcome')); ?></dt>
                <dd style="margin:0;"><?php echo htmlspecialchars($call['outcome'] ?? '-'); ?></dd>

                <?php if (!empty($call['notes'])): ?>
                <dt class="text-muted"><?php echo htmlspecialchars(__('Notes')); ?></dt>
                <dd style="margin:0;white-space:pre-wrap;"><?php echo htmlspecialchars($call['notes']); ?></dd>
                <?php endif; ?>

                <?php if (!empty($call['contact_person']) || !empty($call['company_name'])): ?>
                <dt class="text-muted"><?php echo htmlspecialchars(__('Lead')); ?></dt>
                <dd style="margin:0;">
                    <?php echo htmlspecialchars($call['contact_person'] ?? ''); ?>
                    <?php if (!empty($call['company_name'])): ?>
                        <span class="text-muted">(<?php echo htmlspecialchars($call['company_name']); ?>)</span>
                    <?php endif; ?>
                </dd>
                <?php endif; ?>

                <?php if (!empty($call['user_name'])): ?>
                <dt class="text-muted"><?php echo htmlspecialchars(__('Agent')); ?></dt>
                <dd style="margin:0;"><?php echo htmlspecialchars($call['user_name']); ?></dd>
                <?php endif; ?>

                <dt class="text-muted"><?php echo htmlspecialchars(__('Started')); ?></dt>
                <dd style="margin:0;"><?php echo htmlspecialchars($call['started_at'] ?? $call['created_at'] ?? '-'); ?></dd>

                <?php if (!empty($call['ended_at'])): ?>
                <dt class="text-muted"><?php echo htmlspecialchars(__('Ended')); ?></dt>
                <dd style="margin:0;"><?php echo htmlspecialchars($call['ended_at']); ?></dd>
                <?php endif; ?>

                <?php if (!empty($call['twilio_call_sid'])): ?>
                <dt class="text-muted">Twilio SID</dt>
                <dd style="margin:0;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;word-break:break-all;"><?php echo htmlspecialchars($call['twilio_call_sid']); ?></dd>
                <?php endif; ?>
            </dl>
        </div>
    </div>

    <?php if (!empty($call['recording_url'])): ?>
    <div class="card" id="recordingCard" style="margin-top:16px;display:none;">
        <div class="card-header" style="padding:18px 24px;">
            <h3 class="card-title" style="margin:0;"><?php echo htmlspecialchars(__('Recording')); ?></h3>
        </div>
        <div class="card-body" style="padding:24px;">
            <audio id="recordingAudio" controls style="width:100%;" preload="metadata">
                <source src="<?php echo htmlspecialchars($call['recording_url']); ?>">
            </audio>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function playRecording(url) {
    var card = document.getElementById('recordingCard');
    var audio = document.getElementById('recordingAudio');
    if (card) {
        card.style.display = 'block';
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    if (audio) {
        audio.play().catch(function() { /* autoplay may be blocked */ });
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
