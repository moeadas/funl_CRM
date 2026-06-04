<?php
/**
 * Resend Webhook Handler
 * Receives delivery events from Resend: delivered, bounced, opened, clicked, complained, delivery_delayed
 * 
 * Setup in Resend Dashboard:
 * 1. Go to https://resend.com/domains → Webhooks
 * 2. Add webhook URL: https://app.funl.online/api/resend-webhook.php
 * 3. Select events: delivered, bounced, opened, clicked, complained, delivery_delayed
 */

require_once __DIR__ . '/../config/database.php';

// C-5 fix: verify Resend/Svix signature. Resend uses standard Svix headers
// (svix-id, svix-timestamp, svix-signature) with HMAC-SHA256 over
// "{svix-id}.{svix-timestamp}.{raw_payload}".
$webhookSecret = getenv('RESEND_WEBHOOK_SECRET') ?: '';
if (!empty($webhookSecret)) {
    $svixId = $_SERVER['HTTP_SVIX_ID'] ?? '';
    $svixTs = $_SERVER['HTTP_SVIX_TIMESTAMP'] ?? '';
    $svixSig = $_SERVER['HTTP_SVIX_SIGNATURE'] ?? '';
    $rawPayload = file_get_contents('php://input');

    if (empty($svixId) || empty($svixTs) || empty($svixSig)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing Svix signature headers']);
        exit;
    }
    // Reject timestamps more than 5 minutes old to prevent replay
    if (abs(time() - intval($svixTs)) > 300) {
        http_response_code(400);
        echo json_encode(['error' => 'Stale webhook timestamp']);
        exit;
    }
    // Svix secret format: "whsec_<base64>" - the part after "whsec_" is the actual secret
    $secretPart = str_starts_with($webhookSecret, 'whsec_') ? substr($webhookSecret, 6) : $webhookSecret;
    $secretBytes = base64_decode($secretPart);
    $signed = $svixId . '.' . $svixTs . '.' . $rawPayload;
    $expected = hash_hmac('sha256', $signed, $secretBytes);
    // svix-signature may contain "v1,<sig>" or multiple space-separated entries
    $expectedPrefixed = 'v1,' . $expected;
    $valid = false;
    foreach (explode(' ', $svixSig) as $entry) {
        if (hash_equals($expectedPrefixed, trim($entry))) { $valid = true; break; }
    }
    if (!$valid) {
        error_log('Resend webhook signature verification failed');
        http_response_code(401);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }
    $payload = $rawPayload;
} else {
    // No secret configured: log a warning so operators notice, but allow
    // the webhook through for now (uncomment http_response_code(401) + exit
    // to enforce strict verification in production).
    error_log('Resend webhook received with no RESEND_WEBHOOK_SECRET configured. Signature NOT verified.');
    $payload = file_get_contents('php://input');
}

$data = json_decode($payload, true);

if (!$data || !isset($data['type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$eventType = $data['type'];
$emailId = $data['data']['email']['id'] ?? null;
$toEmail = $data['data']['email']['to'] ?? null;

// Log the event for audit
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Find campaign log entry by resend_email_id
    $stmt = $pdo->prepare("SELECT log_id, campaign_id FROM email_campaign_log WHERE resend_email_id = ?");
    $stmt->execute([$emailId]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $logId = $log['log_id'] ?? null;
    $campaignId = $log['campaign_id'] ?? 0;
    
    // Log webhook event
    $pdo->prepare("INSERT INTO email_webhook_events (campaign_id, log_id, resend_email_id, event_type, email_address, payload) VALUES (?, ?, ?, ?, ?, ?)")->execute([
        $campaignId, $logId, $emailId, $eventType, $toEmail, $payload
    ]);
    
    if (!$log) {
        // Still log but can't update — email may not be from campaign
        http_response_code(200);
        echo json_encode(['status' => 'logged', 'note' => 'email not found in campaign log']);
        exit;
    }
    
    // Process by event type
    switch ($eventType) {
        case 'email.delivered':
            $pdo->prepare("UPDATE email_campaign_log SET delivery_status = 'delivered', delivered_at = NOW() WHERE log_id = ?")->execute([$logId]);
            $pdo->prepare("UPDATE email_campaigns SET total_delivered = total_delivered + 1 WHERE campaign_id = ?")->execute([$campaignId]);
            break;
            
        case 'email.bounced':
            $reason = $data['data']['bounce']['type'] ?? $data['data']['bounce']['message'] ?? 'Unknown';
            $pdo->prepare("UPDATE email_campaign_log SET delivery_status = 'bounced', bounced_at = NOW(), bounced_reason = ? WHERE log_id = ?")->execute([$reason, $logId]);
            $pdo->prepare("UPDATE email_campaigns SET total_bounced = total_bounced + 1 WHERE campaign_id = ?")->execute([$campaignId]);
            break;
            
        case 'email.complained':
            $complaintType = $data['data']['complaint']['type'] ?? 'unknown';
            $pdo->prepare("UPDATE email_campaign_log SET delivery_status = 'complained', complained_at = NOW(), complaint_type = ? WHERE log_id = ?")->execute([$complaintType, $logId]);
            $pdo->prepare("UPDATE email_campaigns SET total_complained = total_complained + 1 WHERE campaign_id = ?")->execute([$campaignId]);
            break;
            
        case 'email.opened':
            // Opened is tracked via pixel, but webhook confirms it
            $pdo->prepare("UPDATE email_campaign_log SET status = 'Opened', opened_at = COALESCE(opened_at, NOW()), delivery_status = 'opened' WHERE log_id = ?")->execute([$logId]);
            break;
            
        case 'email.clicked':
            // Clicked is tracked via link redirect
            $pdo->prepare("UPDATE email_campaign_log SET status = 'Clicked', clicked_at = COALESCE(clicked_at, NOW()), delivery_status = 'clicked' WHERE log_id = ?")->execute([$logId]);
            break;
            
        case 'email.delivery_delayed':
            $pdo->prepare("UPDATE email_campaign_log SET delivery_status = 'delayed' WHERE log_id = ?")->execute([$logId]);
            break;
    }
    
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'event' => $eventType]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'webhook processing failed']);
}
?>
