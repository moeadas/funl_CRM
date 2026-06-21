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

        <!-- Automated Trigger Section -->
        <div class="card mt-2">
            <div class="card-header" style="padding:18px 24px;display:flex;justify-content:space-between;align-items:center;">
                <h3 class="card-title" style="margin:0;"><?php echo __('Automation Trigger'); ?></h3>
                <label style="font-size:13px;display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="is_automated" value="1" id="isAutomated" <?php echo !empty($campaign['is_automated']) ? 'checked' : ''; ?> onchange="toggleAutomation()">
                    <?php echo __('Enable automated sending'); ?>
                </label>
            </div>
            <div class="card-body" id="automationConfig" style="padding:24px;display:<?php echo !empty($campaign['is_automated']) ? 'block' : 'none'; ?>">
                <div class="form-group" style="margin-bottom:16px;">
                    <label class="form-label"><?php echo __('When should this campaign send?'); ?> *</label>
                    <select name="trigger_type" class="form-control" id="triggerType">
                        <option value="">— <?php echo __('Select a trigger'); ?> —</option>
                        <option value="new_lead" <?php echo ($campaign['trigger_type'] ?? '') === 'new_lead' ? 'selected' : ''; ?><?php echo __('When a new lead is created'); ?></option>
                        <option value="lead_status_change" <?php echo ($campaign['trigger_type'] ?? '') === 'lead_status_change' ? 'selected' : ''; ?><?php echo __('When lead status changes to...'); ?></option>
                        <option value="contact_created" <?php echo ($campaign['trigger_type'] ?? '') === 'contact_created' ? 'selected' : ''; ?><?php echo __('When a new contact is created'); ?></option>
                        <option value="scheduled" <?php echo ($campaign['trigger_type'] ?? '') === 'scheduled' ? 'selected' : ''; ?><?php echo __('Recurring / Scheduled interval'); ?></option>
                        <option value="deal_stage_change" <?php echo ($campaign['trigger_type'] ?? '') === 'deal_stage_change' ? 'selected' : ''; ?><?php echo __('When deal moves to stage...'); ?></option>
                    </select>
                </div>

                <!-- Conditional fields based on trigger type -->
                <div id="triggerConfigFields" style="margin-top:12px;">
                    <!-- For lead_status_change -->
                    <div class="form-group trigger-config trigger-status" style="display:none;margin-bottom:12px;">
                        <label class="form-label"><?php echo __('Send when lead status becomes'); ?></label>
                        <select name="trigger_status" class="form-control">
                            <option value="Contacted">Contacted</option>
                            <option value="Interested">Interested</option>
                            <option value="Demo Scheduled">Demo Scheduled</option>
                            <option value="Proposal Sent">Proposal Sent</option>
                            <option value="Negotiation">Negotiation</option>
                            <option value="Won">Won</option>
                        </select>
                    </div>

                    <!-- For scheduled/recurring -->
                    <div class="form-group trigger-config trigger-scheduled" style="display:none;margin-bottom:12px;">
                        <label class="form-label"><?php echo __('Send every'); ?></label>
                        <select name="trigger_interval" class="form-control" style="max-width:200px;display:inline-block;">
                            <option value="daily"><?php echo __('Day'); ?></option>
                            <option value="weekly"><?php echo __('Week'); ?></option>
                            <option value="monthly"><?php echo __('Month'); ?></option>
                        </select>
                        <span style="font-size:13px;color:var(--color-text-secondary);margin-left:8px;"><?php echo __('to the selected list'); ?></span>
                    </div>

                    <!-- For deal_stage_change -->
                    <div class="form-group trigger-config trigger-deal" style="display:none;margin-bottom:12px;">
                        <label class="form-label"><?php echo __('Send when deal moves to'); ?></label>
                        <input type="text" name="trigger_deal_stage" class="form-control" placeholder="e.g. Negotiation, Won" >
                    </div>

                    <!-- Delay option (for all trigger types) -->
                    <div class="form-group" style="margin-bottom:0;padding-top:12px;border-top:1px solid var(--color-border-light);">
                        <label class="form-label"><?php echo __('Delay before sending'); ?></label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="number" name="delay_value" class="form-control" style="max-width:100px;" min="0" value="0">
                            <select name="delay_unit" class="form-control" style="max-width:120px;">
                                <option value="minutes"><?php echo __('minutes'); ?></option>
                                <option value="hours"><?php echo __('hours'); ?></option>
                                <option value="days"><?php echo __('days'); ?></option>
                            </select>
                            <span style="font-size:13px;color:var(--color-text-secondary);"><?php echo __('after trigger fires'); ?></span>
                        </div>
                    </div>
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


function toggleAutomation() {
    var cb = document.getElementById("isAutomated");
    document.getElementById("automationConfig").style.display = cb.checked ? "block" : "none";
}

document.getElementById("triggerType").addEventListener("change", function() {
    var val = this.value;
    document.querySelectorAll(".trigger-config").forEach(function(el) { el.style.display = "none"; });
    if (val === "lead_status_change") { document.querySelector(".trigger-status").style.display = "block"; }
    if (val === "scheduled") { document.querySelector(".trigger-scheduled").style.display = "block"; }
    if (val === "deal_stage_change") { document.querySelector(".trigger-deal").style.display = "block"; }
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
