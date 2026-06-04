<?php
/**
 * White Label CRM V2 — Campaign Create/Edit
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
startSecureSession();
requireLogin();
requireRole('Sales Manager');

$csrfToken = generateCSRFToken();
$db = Database::getInstance()->getConnection();
$campaignId = (int)($_GET['id'] ?? 0);
$campaign = null;
$isEdit = false;

if ($campaignId > 0) {
    $stmt = $db->prepare("SELECT * FROM email_campaigns WHERE campaign_id = ?");
    $stmt->execute([$campaignId]);
    $campaign = $stmt->fetch();
    if ($campaign) $isEdit = true;
}

// Load lists and templates for dropdowns
$lists = $db->query("SELECT list_id, name, member_count FROM email_lists ORDER BY name")->fetchAll();
$templates = $db->query("SELECT template_id, name, subject FROM email_templates ORDER BY name")->fetchAll();

// Default from settings
$defaultFromName = '';
$defaultFromEmail = '';
$defaultReplyTo = '';
$settings = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('email_from_name','email_from_address','email_reply_to')")->fetchAll(PDO::FETCH_KEY_PAIR);
$defaultFromName = $settings['email_from_name'] ?? '';
$defaultFromEmail = $settings['email_from_address'] ?? '';
$defaultReplyTo = $settings['email_reply_to'] ?? '';

$pageTitle = $isEdit ? __('Edit Campaign') : __('New Campaign');
include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <a href="email-campaigns.php" class="btn btn-outline btn-sm back-btn-margin">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            <?php echo __('Back to Campaigns'); ?>
        </a>
        <h1><?php echo $isEdit ? __('Edit Campaign') : __('Create Campaign'); ?></h1>
    </div>
</div>

<form id="campaignForm" class="form-grid-2col">
    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
    <input type="hidden" name="campaign_id" value="<?php echo $campaignId; ?>">

    <!-- Left: Campaign Details -->
    <div class="card">
        <div class="card-header"><h2 class="card-title"><?php echo __('Campaign Details'); ?></h2></div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label"><?php echo __('Campaign Name'); ?> *</label>
                <input type="text" name="name" class="form-control" required placeholder="<?php echo __('e.g., January Newsletter'); ?>" value="<?php echo htmlspecialchars($campaign['name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo __('Subject Line'); ?> *</label>
                <input type="text" name="subject" class="form-control" required placeholder="<?php echo __('e.g., Exciting news from Your Company!'); ?>" value="<?php echo htmlspecialchars($campaign['subject'] ?? ''); ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><?php echo __('From Name'); ?></label>
                    <input type="text" name="from_name" class="form-control" placeholder="<?php echo __('Your Company'); ?>" value="<?php echo htmlspecialchars($campaign['from_name'] ?? $defaultFromName); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('From Email'); ?></label>
                    <input type="email" name="from_email" class="form-control" placeholder="marketing@funl.online" value="<?php echo htmlspecialchars($campaign['from_email'] ?? $defaultFromEmail); ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo __('Reply-To Email'); ?></label>
                <input type="email" name="reply_to" class="form-control" placeholder="(same as from email if blank)" value="<?php echo htmlspecialchars($campaign['reply_to'] ?? $defaultReplyTo); ?>">
            </div>
        </div>
    </div>

    <!-- Right: Audience & Template -->
    <div>
        <div class="card">
            <div class="card-header"><h2 class="card-title"><?php echo __('Audience & Template'); ?></h2></div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label"><?php echo __('Audience List'); ?> *</label>
                    <select name="list_id" class="form-control" required>
                        <option value="">— <?php echo __('Select a list'); ?> —</option>
                        <?php foreach ($lists as $l): ?>
                            <option value="<?php echo $l['list_id']; ?>" <?php echo ($campaign['list_id'] ?? '') == $l['list_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($l['name']); ?> (<?php echo $l['member_count'] . ' ' . __('members'); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <a href="email-lists.php" class="text-muted fs-12"><?php echo __('Manage lists'); ?> →</a>
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('Start from Template'); ?></label>
                    <select name="template_id" class="form-control" id="templateSelect">
                        <option value="">— <?php echo __('Blank (build from scratch)'); ?> —</option>
                        <?php foreach ($templates as $t): ?>
                            <option value="<?php echo $t['template_id']; ?>" <?php echo ($campaign['template_id'] ?? '') == $t['template_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('Schedule (optional)'); ?></label>
                    <input type="datetime-local" name="scheduled_at" class="form-control" value="<?php echo !empty($campaign['scheduled_at']) ? date('Y-m-d\TH:i', strtotime($campaign['scheduled_at'])) : ''; ?>">
                    <small class="text-muted"><?php echo __('Leave blank to send manually'); ?></small>
                </div>
            </div>
        </div>

        <div class="card mt-2">
            <div class="card-body text-center-block">
                <button type="submit" class="btn btn-primary btn-block"><?php echo $isEdit ? __('Save Campaign') : __('Create Campaign'); ?></button>
                <?php if ($isEdit && $campaign['status'] === 'Draft'): ?>
                    <a href="email-builder.php?mode=campaign&id=<?php echo $campaignId; ?>" class="btn btn-outline btn-block mt-1">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        <?php echo __('Design Email'); ?>
                    </a>
                    <button type="button" class="btn btn-success btn-block mt-1" onclick="sendCampaign()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                        <?php echo __('Send Campaign Now'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>

<script>
document.getElementById('campaignForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    const data = {};
    fd.forEach((v, k) => data[k] = v);

    fetch('/api/email.php?action=campaign_save', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    }).then(r => r.json()).then(d => {
        if (d.success) {
            showNotification('Campaign saved!', 'success');
            if (!data.campaign_id || data.campaign_id === '0') {
                window.location.href = 'email-campaign-form.php?id=' + d.data.campaign_id;
            }
        } else {
            showNotification(d.message, 'error');
        }
    }).catch(() => showNotification('Network error', 'error'));
});

function sendCampaign() {
    
    fetch('/api/email.php?action=campaign_send', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ campaign_id: <?php echo $campaignId; ?>, csrf_token: '<?php echo $csrfToken; ?>' })
    }).then(r => r.json()).then(d => {
        showNotification(d.message, d.success ? 'success' : 'error');
        if (d.success) setTimeout(() => location.reload(), 2000);
    }).catch(() => showNotification('Network error', 'error'));
}
</script>

<?php include '../includes/footer.php'; ?>
