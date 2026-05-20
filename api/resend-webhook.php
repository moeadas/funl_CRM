<?php
/**
 * Resend Webhook Handler
 * Receives delivery events from Resend: delivered, bounced, opened, clicked, complained, delivery_delayed
 * 
 * Setup in Resend Dashboard:
 * 1. Go to https://resend.com/domains → Webhooks
 * 2. Add webhook URL: https://crm.pinpoint.online/api/resend-webhook.php
 * 3. Select events: delivered, bounced, opened, clicked, complained, delivery_delayed
 */

require_once __DIR__ . '/../config/database.php';

// Get raw payload
$payload = file_get_contents('php://input');
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
    echo json_encode(['error' => $e->getMessage()]);
}
?>
