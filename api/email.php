<?php
/**
 * White Label CRM V2 — Email Marketing API
 * Handles: templates, lists, campaigns, sending, tracking, unsubscribe
 * Webhook: resend-webhook.php handles delivery events from Resend
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/resend-email.php';

// ─── PUBLIC TRACKING ACTIONS ───
$publicActions = ['track_open', 'track_click', 'unsubscribe', 'unsubscribe_confirm'];
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if (in_array($action, $publicActions)) {
    $db = Database::getInstance()->getConnection();

    // ─── PUBLIC: Open tracking ───
    if ($action === 'track_open' && isset($_GET['token'])) {
        $stmt = $db->prepare("UPDATE email_campaign_log SET status = 'Opened', opened_at = NOW() WHERE tracking_token = ? AND status IN ('Sent','Opened')");
        $stmt->execute([$_GET['token']]);
        // Update campaign total_opened
        $stmt2 = $db->prepare("UPDATE email_campaigns c SET c.total_opened = (SELECT COUNT(*) FROM email_campaign_log WHERE campaign_id = c.campaign_id AND status IN ('Opened','Clicked')) WHERE c.campaign_id = (SELECT campaign_id FROM email_campaign_log WHERE tracking_token = ? LIMIT 1)");
        $stmt2->execute([$_GET['token']]);
        // Return transparent pixel
        header('Content-Type: image/gif');
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        exit;
    }

    // ─── PUBLIC: Click tracking ───
    if ($action === 'track_click' && isset($_GET['token']) && isset($_GET['url'])) {
        $stmt = $db->prepare("UPDATE email_campaign_log SET status = 'Clicked', clicked_at = NOW() WHERE tracking_token = ? AND status IN ('Sent','Opened','Clicked')");
        $stmt->execute([$_GET['token']]);
        $stmt2 = $db->prepare("UPDATE email_campaigns c SET c.total_clicked = (SELECT COUNT(*) FROM email_campaign_log WHERE campaign_id = c.campaign_id AND status = 'Clicked') WHERE c.campaign_id = (SELECT campaign_id FROM email_campaign_log WHERE tracking_token = ? LIMIT 1)");
        $stmt2->execute([$_GET['token']]);
        // Redirect
        header('Location: ' . urldecode($_GET['url']));
        exit;
    }

    // ─── PUBLIC: Unsubscribe ───
    if ($action === 'unsubscribe' && isset($_GET['token'])) {
        $stmt = $db->prepare("SELECT ecl.*, ec.name as campaign_name FROM email_campaign_log ecl JOIN email_campaigns ec ON ecl.campaign_id = ec.campaign_id WHERE ecl.tracking_token = ?");
        $stmt->execute([$_GET['token']]);
        $log = $stmt->fetch();
        if (!$log) { echo "<p>Invalid link.</p>"; exit; }
        ?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Unsubscribe</title><style>body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;background:#f4f6f8;}.box{background:#fff;padding:40px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.08);max-width:400px;width:100%;text-align:center;}h1{margin:0 0 16px;font-size:24px;}p{color:#666;margin:0 0 24px;}button{background:#dc3545;color:#fff;border:none;padding:12px 24px;border-radius:8px;font-size:16px;cursor:pointer;}button:hover{background:#c82333;}a{color:#007bff;text-decoration:none;}</style></head>
<body><div class="box"><h1>Unsubscribe</h1><p>You received emails from "<?php echo htmlspecialchars($log['campaign_name']); ?>".</p><p><strong><?php echo htmlspecialchars($log['email']); ?></strong></p><form method="POST" action="email.php?action=unsubscribe_confirm"><input type="hidden" name="token" value="<?php echo htmlspecialchars($log['tracking_token']); ?>"><button type="submit">Unsubscribe Me</button></form></div></body></html>
        <?php exit;
    }

    if ($action === 'unsubscribe_confirm' && $method === 'POST' && isset($_POST['token'])) {
        $stmt = $db->prepare("SELECT lead_id, email FROM email_campaign_log WHERE tracking_token = ?");
        $stmt->execute([$_POST['token']]);
        $log = $stmt->fetch();
        if ($log) {
            $db->prepare("UPDATE email_list_members SET status = 'Unsubscribed', unsubscribed_at = NOW() WHERE email = ?")->execute([$log['email']]);
            echo "<p>You have been unsubscribed. You will no longer receive emails from us.</p>";
        } else {
            echo "<p>Invalid token.</p>";
        }
        exit;
    }
}

// ─── AUTHENTICATED API ───
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api-security.php';

startSecureSession();
requireLogin();
requireRole(['Admin', 'Sales Manager']);

$db = Database::getInstance()->getConnection();
$currentUser = getCurrentUser();
$companyId = $currentUser['company_id'] ?? null;

function jsonResponse($success, $message = '', $data = null, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}



$action = $_GET['action'] ?? '';

// ─── LIST ALL ───
if ($action === 'list') {
    $companyId = getCurrentCompanyId(); $stmt = $db->prepare("SELECT el.*, u.full_name as creator, (SELECT COUNT(*) FROM email_list_members WHERE list_id = el.list_id AND status = 'Active') as active_members FROM email_lists el LEFT JOIN users u ON el.created_by = u.user_id WHERE el.company_id = ? ORDER BY el.updated_at DESC");
    jsonSuccess('Lists loaded', $stmt->fetchAll());
}

// ─── CREATE LIST (also handles list_save alias) ───
if (($action === 'create_list' || $action === 'list_save') && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['name'])) jsonError('List name is required');
    $stmt = $db->prepare("INSERT INTO email_lists (name, description, created_by) VALUES (?, ?, ?)");
    $stmt->execute([$input['name'], $input['description'] ?? '', $currentUser['user_id']]);
    jsonSuccess('List created', ['list_id' => $db->lastInsertId()]);
}

// ─── UPDATE LIST ───
if ($action === 'update_list' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $listId = (int)($input['list_id'] ?? 0);
    if (!$listId || empty($input['name'])) jsonError('List ID and name are required');
    $stmt = $db->prepare("UPDATE email_lists SET name = ?, description = ? WHERE list_id = ?");
    $stmt->execute([$input['name'], $input['description'] ?? '', $listId]);
    jsonSuccess('List updated');
}

// ─── DELETE LIST (also handles list_delete alias) ───
if (($action === 'delete_list' || $action === 'list_delete') && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $listId = (int)($input['list_id'] ?? 0);
    if (!$listId) jsonError('List ID is required');
    $db->prepare("DELETE FROM email_lists WHERE list_id = ?")->execute([$listId]);
    jsonSuccess('List deleted');
}

// ─── ADD LEADS TO LIST (from filter) (also handles list_populate alias) ───
if (($action === 'add_to_list' || $action === 'list_populate') && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $listId = (int)($input['list_id'] ?? 0);
    $filters = $input['filters'] ?? [];
    if (!$listId) jsonError('List ID is required');

    $where = ["email IS NOT NULL", "email != ''"];
    $params = [];

    if (!empty($filters['lead_type'])) { $where[] = "lead_type = ?"; $params[] = $filters['lead_type']; }
    if (!empty($filters['country'])) { $where[] = "country = ?"; $params[] = $filters['country']; }
    if (!empty($filters['region'])) { $where[] = "region = ?"; $params[] = $filters['region']; }
    if (!empty($filters['lead_status'])) { $where[] = "lead_status = ?"; $params[] = $filters['lead_status']; }
    if (!empty($filters['industry'])) { $where[] = "industry = ?"; $params[] = $filters['industry']; }
    if (!empty($filters['priority'])) { $where[] = "priority = ?"; $params[] = $filters['priority']; }
    if (!empty($filters['assigned_to'])) { $where[] = "assigned_to = ?"; $params[] = (int)$filters['assigned_to']; }

    $whereSQL = implode(' AND ', $where);
    $leads = $db->prepare("SELECT lead_id, email FROM leads WHERE $whereSQL");
    $leads->execute($params);
    $toAdd = $leads->fetchAll();

    if (empty($toAdd)) jsonError('No leads match the selected filters');

    $unsubscribed = $db->query("SELECT DISTINCT email FROM email_list_members WHERE status = 'Unsubscribed'")->fetchAll(PDO::FETCH_COLUMN);
    $unsubSet = array_flip($unsubscribed);

    $insertStmt = $db->prepare("INSERT IGNORE INTO email_list_members (list_id, lead_id, email, status) VALUES (?, ?, ?, 'Active')");
    $added = 0;
    foreach ($toAdd as $lead) {
        if (isset($unsubSet[$lead['email']])) continue;
        $insertStmt->execute([$listId, $lead['lead_id'], $lead['email']]);
        if ($insertStmt->rowCount() > 0) $added++;
    }

    $count = $db->prepare("SELECT COUNT(*) FROM email_list_members WHERE list_id = ? AND status = 'Active'");
    $count->execute([$listId]);
    $total = $count->fetchColumn();

    jsonSuccess("Added $added leads to list. Total active: $total", ['added' => $added, 'total_active' => $total]);
}

// ─── ADD SINGLE LEAD TO LIST ───
if ($action === 'add_single_to_list' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $listId = (int)($input['list_id'] ?? 0);
    $leadId = (int)($input['lead_id'] ?? 0);
    if (!$listId || !$leadId) jsonError('List ID and Lead ID required');
    $lead = $db->prepare("SELECT email FROM leads WHERE lead_id = ?");
    $lead->execute([$leadId]);
    $l = $lead->fetch();
    if (!$l || !$l['email']) jsonError('Lead has no email');
    $stmt = $db->prepare("INSERT IGNORE INTO email_list_members (list_id, lead_id, email, status) VALUES (?, ?, ?, 'Active')");
    $stmt->execute([$listId, $leadId, $l['email']]);
    jsonSuccess('Lead added to list');
}

// ─── LIST MEMBERS ───
if ($action === 'list_members' && isset($_GET['list_id'])) {
    $listId = (int)$_GET['list_id'];
    $stmt = $db->prepare("SELECT elm.*, l.company_name, l.contact_person FROM email_list_members elm LEFT JOIN leads l ON elm.lead_id = l.lead_id WHERE elm.list_id = ? ORDER BY elm.added_at DESC");
    $stmt->execute([$listId]);
    jsonSuccess('Members loaded', $stmt->fetchAll());
}

// ─── REMOVE MEMBER ───
if ($action === 'remove_member' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $db->prepare("DELETE FROM email_list_members WHERE member_id = ?")->execute([$input['member_id']]);
    jsonSuccess('Member removed');
}

// ─── CAMPAIGN LIST ───
if ($action === 'campaign_list') {
    $stmt = $db->query("SELECT c.*, el.name as list_name FROM email_campaigns c LEFT JOIN email_lists el ON c.list_id = el.list_id WHERE c.company_id = ? ORDER BY c.created_at DESC");
    jsonSuccess('Campaigns loaded', $stmt->fetchAll());
}

// ─── CAMPAIGN DETAIL ───
if ($action === 'campaign_detail' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $campaign = $db->prepare("SELECT c.*, el.name as list_name FROM email_campaigns c LEFT JOIN email_lists el ON c.list_id = el.list_id WHERE c.campaign_id = ?");
    $campaign->execute([$id]);
    jsonSuccess('Campaign loaded', $campaign->fetch());
}

// ─── CREATE CAMPAIGN ───
if ($action === 'create_campaign' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $stmt = $db->prepare("INSERT INTO email_campaigns (name, subject, from_name, from_email, reply_to, template_id, list_id, content_json, content_html, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft', ?)");
    $stmt->execute([
        $input['name'], $input['subject'], $input['from_name'] ?? '', $input['from_email'] ?? '',
        $input['reply_to'] ?? '', $input['template_id'] ?? null, $input['list_id'] ?? null,
        json_encode($input['content_json'] ?? []), $input['content_html'] ?? '', $currentUser['user_id']
    ]);
    jsonSuccess('Campaign created', ['campaign_id' => $db->lastInsertId()]);
}

// ─── UPDATE CAMPAIGN ───
if ($action === 'update_campaign' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['campaign_id'] ?? 0);
    $db->prepare("UPDATE email_campaigns SET name=?, subject=?, from_name=?, from_email=?, reply_to=?, template_id=?, list_id=?, content_json=?, content_html=?, scheduled_at=?, updated_at=NOW() WHERE campaign_id=? AND status IN ('Draft','Scheduled')")
       ->execute([$input['name'], $input['subject'], $input['from_name'] ?? '', $input['from_email'] ?? '', $input['reply_to'] ?? '', $input['template_id'] ?? null, $input['list_id'] ?? null, json_encode($input['content_json'] ?? []), $input['content_html'] ?? '', $input['scheduled_at'] ?? null, $id]);
    jsonSuccess('Campaign updated');
}

// ─── DELETE CAMPAIGN (also handles campaign_delete alias) ───
if (($action === 'delete_campaign' || $action === 'campaign_delete') && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['campaign_id'] ?? 0);
    $db->prepare("DELETE FROM email_campaigns WHERE campaign_id = ? AND status = 'Draft'")->execute([$id]);
    jsonSuccess('Campaign deleted');
}

// ─── SEND TEST ───
if ($action === 'send_test' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $testEmail = $input['test_email'] ?? $input['to'] ?? '';
    $subject = $input['subject'] ?? 'Test Email';
    $html = $input['content_html'] ?? '<p>Test</p>';
    if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) jsonError('Invalid test email');

    $result = sendEmailViaSMTP($testEmail, $subject . ' [TEST]', $html);
    if ($result['success']) {
        jsonSuccess('Test email sent successfully');
    } else {
        jsonError('Failed to send: ' . $result['error']);
    }
}

// ─── CAMPAIGN SEND (BATCH) ───
if ($action === 'campaign_send' && $method === 'POST') {
    requireCSRF();
    requireRole(['Admin', 'Sales Manager']);
    $input = json_decode(file_get_contents('php://input'), true);
    $campaignId = (int)($input['campaign_id'] ?? 0);

    $stmt = $db->prepare("SELECT * FROM email_campaigns WHERE campaign_id = ? AND status IN ('Draft','Scheduled')");
    $stmt->execute([$campaignId]);
    $campaign = $stmt->fetch();
    if (!$campaign) jsonError('Campaign not found or already sent');
    if (!$campaign['list_id']) jsonError('No audience list selected');
    if (empty($campaign['content_html'])) jsonError('Campaign has no email content');

    // Get active list members
    $members = $db->prepare("SELECT elm.lead_id, elm.email, l.company_name, l.contact_person FROM email_list_members elm LEFT JOIN leads l ON elm.lead_id = l.lead_id WHERE elm.list_id = ? AND elm.status = 'Active'");
    $members->execute([$campaign['list_id']]);
    $recipients = $members->fetchAll();

    if (empty($recipients)) jsonError('No active recipients in the selected list');

    // Mark campaign as sending
    $db->prepare("UPDATE email_campaigns SET status = 'Sending', total_recipients = ? WHERE campaign_id = ?")->execute([count($recipients), $campaignId]);

    // Queue all recipients
    $queueStmt = $db->prepare("INSERT INTO email_campaign_log (campaign_id, lead_id, email, status, tracking_token) VALUES (?, ?, ?, 'Queued', ?)");
    foreach ($recipients as $r) {
        $token = bin2hex(random_bytes(32));
        $queueStmt->execute([$campaignId, $r['lead_id'], $r['email'], $token]);
    }

    // Process queue in batches
    $batchSize = (int)(getSettingValue('email_batch_size') ?: 50);
    $batchDelay = (int)(getSettingValue('email_batch_delay') ?: 2);

    $queued = $db->prepare("SELECT ecl.*, l.company_name, l.contact_person FROM email_campaign_log ecl LEFT JOIN leads l ON ecl.lead_id = l.lead_id WHERE ecl.campaign_id = ? AND ecl.status = 'Queued' LIMIT ?");
    $queued->bindValue(1, $campaignId, PDO::PARAM_INT);
    $queued->bindValue(2, $batchSize, PDO::PARAM_INT);
    $queued->execute();
    $batch = $queued->fetchAll();

    $sent = 0;
    $failed = 0;
    $appUrl = rtrim(APP_URL, '/');
    $resendEnabled = (new ResendEmailService())->isEnabled();

    foreach ($batch as $item) {
        $html = $campaign['content_html'];

        // Merge tags
        $html = str_replace('{{company_name}}', htmlspecialchars($item['company_name'] ?? ''), $html);
        $html = str_replace('{{contact_person}}', htmlspecialchars($item['contact_person'] ?? ''), $html);
        $html = str_replace('{{email}}', htmlspecialchars($item['email']), $html);

        // Tracking pixel
        $trackPixel = '<img src="' . $appUrl . '/api/email.php?action=track_open&token=' . $item['tracking_token'] . '" width="1" height="1" style="display:none;" alt="">';
        $html .= $trackPixel;

        // Rewrite links for click tracking
        $html = preg_replace_callback('/href="(https?:\/\/[^"]+)"/', function($m) use ($appUrl, $item) {
            $encodedUrl = urlencode($m[1]);
            return 'href="' . $appUrl . '/api/email.php?action=track_click&token=' . $item['tracking_token'] . '&url=' . $encodedUrl . '"';
        }, $html);

        // Add unsubscribe link
        $unsubLink = $appUrl . '/api/email.php?action=unsubscribe&token=' . $item['tracking_token'];
        $html = str_replace('{{unsubscribe_url}}', $unsubLink, $html);

        // Try Resend first if enabled, fallback to SMTP
        $emailResult = null;
        $resendId = null;

        if ($resendEnabled) {
            $resend = new ResendEmailService();
            $emailResult = $resend->sendEmail($item['email'], $campaign['subject'], $html);
            if ($emailResult && isset($emailResult['email_id'])) {
                $resendId = $emailResult['email_id'];
                $emailResult = ['success' => true];
            } elseif ($emailResult === false) {
                $emailResult = ['success' => false, 'error' => 'Resend API failed'];
            } else {
                $emailResult = ['success' => true];
            }
        }

        // Fallback to SMTP if Resend not enabled or failed
        if (!$emailResult || (!$emailResult['success'] && !$resendEnabled)) {
            $emailResult = sendEmailViaSMTP($item['email'], $campaign['subject'], $html, $campaign['from_name'], $campaign['from_email'], $campaign['reply_to']);
        }

        if ($emailResult['success']) {
            $db->prepare("UPDATE email_campaign_log SET status = 'Sent', sent_at = NOW(), resend_email_id = ? WHERE log_id = ?")->execute([$resendId, $item['log_id']]);
            $sent++;
        } else {
            $db->prepare("UPDATE email_campaign_log SET status = 'Failed', error_message = ? WHERE log_id = ?")->execute([$emailResult['error'] ?? 'Unknown error', $item['log_id']]);
            $failed++;
        }
    }

    // Check if all done
    $remaining = $db->prepare("SELECT COUNT(*) FROM email_campaign_log WHERE campaign_id = ? AND status = 'Queued'");
    $remaining->execute([$campaignId]);
    $left = $remaining->fetchColumn();

    $finalStatus = ($left == 0) ? 'Sent' : 'Sending';
    $db->prepare("UPDATE email_campaigns SET status = ?, total_sent = total_sent + ?, total_failed = total_failed + ?, sent_at = IF(? = 'Sent', NOW(), sent_at) WHERE campaign_id = ?")
       ->execute([$finalStatus, $sent, $failed, $finalStatus, $campaignId]);

    logActivity($currentUser['user_id'], 'Send Campaign', 'Campaign', $campaignId, "Sent batch: $sent sent, $failed failed, $left remaining");
    jsonSuccess("Batch complete: $sent sent, $failed failed" . ($left > 0 ? ", $left remaining — click Send again to continue" : " — campaign complete!"), [
        'sent' => $sent, 'failed' => $failed, 'remaining' => $left, 'status' => $finalStatus
    ]);
}

// ─── CAMPAIGN REPORT ───
if ($action === 'campaign_report' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $campaign = $db->prepare("SELECT c.*, el.name as list_name FROM email_campaigns c LEFT JOIN email_lists el ON c.list_id = el.list_id WHERE c.campaign_id = ?");
    $campaign->execute([$id]);
    $data = $campaign->fetch();
    if (!$data) jsonError('Campaign not found', 404);

    $logs = $db->prepare("SELECT ecl.*, l.company_name, l.contact_person FROM email_campaign_log ecl LEFT JOIN leads l ON ecl.lead_id = l.lead_id WHERE ecl.campaign_id = ? ORDER BY ecl.sent_at DESC");
    $logs->execute([$id]);
    $data['logs'] = $logs->fetchAll();

    jsonSuccess('Report loaded', $data);
}

// ─── TEMPLATES ───
if ($action === 'template_list') {
    $stmt = $db->query("SELECT t.*, u.full_name as creator FROM email_templates t LEFT JOIN users u ON t.created_by = u.user_id ORDER BY t.updated_at DESC");
    jsonSuccess('Templates loaded', $stmt->fetchAll());
}

if ($action === 'template_detail' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT t.*, u.full_name as creator FROM email_templates t LEFT JOIN users u ON t.created_by = u.user_id WHERE t.template_id = ?");
    $stmt->execute([$id]);
    jsonSuccess('Template loaded', $stmt->fetch());
}

if (($action === 'create_template' || $action === 'template_save') && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $stmt = $db->prepare("INSERT INTO email_templates (name, subject, content_html, created_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$input['name'], $input['subject'] ?? '', $input['content_html'] ?? '', $currentUser['user_id']]);
    jsonSuccess('Template created', ['template_id' => $db->lastInsertId()]);
}

if ($action === 'update_template' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['template_id'] ?? 0);
    $db->prepare("UPDATE email_templates SET name=?, subject=?, content_html=?, updated_at=NOW() WHERE template_id=?")->execute([$input['name'], $input['subject'] ?? '', $input['content_html'] ?? '', $id]);
    jsonSuccess('Template updated');
}

if (($action === 'delete_template' || $action === 'template_delete') && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['template_id'] ?? 0);
    $db->prepare("DELETE FROM email_templates WHERE template_id = ?")->execute([$id]);
    jsonSuccess('Template deleted');
}

// ─── CAMPAIGN SAVE (create or update, also handles campaign_save alias) ───
if (($action === 'campaign_save') && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $campaignId = (int)($input['campaign_id'] ?? 0);

    if ($campaignId > 0) {
        // Update existing campaign
        $stmt = $db->prepare("UPDATE email_campaigns SET name=?, subject=?, from_name=?, from_email=?, reply_to=?, template_id=?, list_id=?, scheduled_at=?, is_automated=?, trigger_type=?, trigger_status=?, trigger_interval=?, trigger_deal_stage=?, delay_value=?, delay_unit=?, updated_at=NOW() WHERE campaign_id=? AND company_id=? AND status IN ('Draft','Scheduled')");
        $stmt->execute([
            $input['name'] ?? 'New Campaign',
            $input['subject'] ?? '',
            $input['from_name'] ?? '',
            $input['from_email'] ?? '',
            $input['reply_to'] ?? '',
            !empty($input['template_id']) ? (int)$input['template_id'] : null,
            !empty($input['list_id']) ? (int)$input['list_id'] : null,
            !empty($input['scheduled_at']) ? $input['scheduled_at'] : null,
            !empty($input['is_automated']) ? 1 : 0,
            $input['trigger_type'] ?? null,
            $input['trigger_status'] ?? null,
            $input['trigger_interval'] ?? null,
            $input['trigger_deal_stage'] ?? null,
            (int)($input['delay_value'] ?? 0),
            $input['delay_unit'] ?? 'minutes',
            $campaignId,
            $companyId
        ]);
        jsonSuccess('Campaign updated', ['campaign_id' => $campaignId]);
    } else {
        // Create new campaign
        $stmt = $db->prepare("INSERT INTO email_campaigns (company_id, name, subject, from_name, from_email, reply_to, template_id, list_id, scheduled_at, is_automated, trigger_type, trigger_status, trigger_interval, trigger_deal_stage, delay_value, delay_unit, content_json, content_html, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '{}', '', 'Draft', ?, NOW())");
        $stmt->execute([
            $companyId,
            $input['name'] ?? 'New Campaign',
            $input['subject'] ?? '',
            $input['from_name'] ?? '',
            $input['from_email'] ?? '',
            $input['reply_to'] ?? '',
            !empty($input['template_id']) ? (int)$input['template_id'] : null,
            !empty($input['list_id']) ? (int)$input['list_id'] : null,
            !empty($input['scheduled_at']) ? $input['scheduled_at'] : null,
            !empty($input['is_automated']) ? 1 : 0,
            $input['trigger_type'] ?? null,
            $input['trigger_status'] ?? null,
            $input['trigger_interval'] ?? null,
            $input['trigger_deal_stage'] ?? null,
            (int)($input['delay_value'] ?? 0),
            $input['delay_unit'] ?? 'minutes',
            $currentUser['user_id']
        ]);
        jsonSuccess('Campaign created', ['campaign_id' => $db->lastInsertId()]);
    }
}

// ─── CAMPAIGN DUPLICATE ───
if ($action === 'campaign_duplicate' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $campaignId = (int)($input['campaign_id'] ?? 0);
    if (!$campaignId) jsonError('Campaign ID required');

    $stmt = $db->prepare("SELECT * FROM email_campaigns WHERE campaign_id = ? AND company_id = ?");
    $stmt->execute([$campaignId, $companyId]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) jsonError('Campaign not found');

    $stmt = $db->prepare("INSERT INTO email_campaigns (company_id, name, subject, from_name, from_email, reply_to, template_id, list_id, content_json, content_html, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft', ?, NOW())");
    $stmt->execute([
        $companyId,
        $campaign['name'] . ' (Copy)',
        $campaign['subject'],
        $campaign['from_name'],
        $campaign['from_email'],
        $campaign['reply_to'],
        $campaign['template_id'],
        $campaign['list_id'],
        $campaign['content_json'],
        $campaign['content_html'],
        $currentUser['user_id']
    ]);
    jsonSuccess('Campaign duplicated', ['campaign_id' => $db->lastInsertId()]);
}

// ─── LEADS WITH EMAIL (for list building) ───
if ($action === 'leads_with_email') {
    $companyId = getCurrentCompanyId(); $stmt = $db->prepare("SELECT lead_id, company_name, contact_person, email, lead_status, country, lead_type, priority FROM leads WHERE company_id = ? AND email IS NOT NULL AND email != '' ORDER BY company_name LIMIT 5000"); $stmt->execute([$companyId]);
    jsonSuccess('Leads loaded', $stmt->fetchAll());
}

// ─── DEFAULT ───
jsonError('Unknown action: ' . $action);

// ─── HELPER FUNCTIONS ───
function sendEmailViaSMTP($to, $subject, $html, $fromName = null, $fromEmail = null, $replyTo = null) {
    $smtpHost = getSettingValue('smtp_host');
    $smtpPort = getSettingValue('smtp_port') ?: 465;
    $smtpUser = getSettingValue('smtp_username');
    $smtpPass = getSettingValue('smtp_password');
    $smtpEnc  = getSettingValue('smtp_encryption') ?: 'ssl';
    $defaultFrom = getSettingValue('email_from_address');
    $defaultName = getSettingValue('email_from_name') ?: 'Your Company';

    $fromEmail = $fromEmail ?: $defaultFrom;
    $fromName  = $fromName  ?: $defaultName;

    // Try PHPMailer if available
    $phpmailerPath = __DIR__ . '/../includes/PHPMailer.php';
    if (file_exists($phpmailerPath) && !empty($smtpHost)) {
        require_once $phpmailerPath;
        require_once __DIR__ . '/../includes/SMTP.php';
        require_once __DIR__ . '/../includes/Exception.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUser;
            $mail->Password = $smtpPass;
            $mail->SMTPSecure = $smtpEnc;
            $mail->Port = (int)$smtpPort;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($fromEmail, $fromName);
            if ($replyTo) $mail->addReplyTo($replyTo);
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $html));

            $mail->send();
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $mail->ErrorInfo];
        }
    }

    // Fallback to PHP mail()
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    if ($fromEmail) $headers .= "From: $fromName <$fromEmail>\r\n";
    if ($replyTo)   $headers .= "Reply-To: $replyTo\r\n";

    $result = @mail($to, $subject, $html, $headers);
    return $result ? ['success' => true] : ['success' => false, 'error' => 'PHP mail() failed'];
}
