<?php
/**
 * Pinpoint CRM — Email Campaign Report (with Delivery Stats)
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
startSecureSession();
requireLogin();
requireRole('Sales Manager');

$db = Database::getInstance()->getConnection();
$campaignId = (int)($_GET['id'] ?? 0);

if (!$campaignId) { header('Location: email-campaigns.php'); exit; }

$stmt = $db->prepare("SELECT c.*, el.name as list_name, u.full_name as creator FROM email_campaigns c LEFT JOIN email_lists el ON c.list_id = el.list_id LEFT JOIN users u ON c.created_by = u.user_id WHERE c.campaign_id = ?");
$stmt->execute([$campaignId]);
$campaign = $stmt->fetch();

if (!$campaign) { $_SESSION['error'] = 'Campaign not found'; header('Location: email-campaigns.php'); exit; }

// Get send logs
$logs = $db->prepare("SELECT ecl.*, l.company_name, l.contact_person FROM email_campaign_log ecl LEFT JOIN leads l ON ecl.lead_id = l.lead_id WHERE ecl.campaign_id = ? ORDER BY ecl.sent_at DESC");
$logs->execute([$campaignId]);
$allLogs = $logs->fetchAll();

// Aggregate stats
$totalRecipients = $campaign['total_recipients'];
$totalSent       = $campaign['total_sent'];
$totalFailed     = $campaign['total_failed'];
$totalOpened     = $campaign['total_opened'];
$totalClicked    = $campaign['total_clicked'];
$totalBounced    = $campaign['total_bounced'] ?: 0;
$totalComplained = $campaign['total_complained'] ?: 0;
$totalDelivered  = $campaign['total_delivered'] ?: 0;
$totalJunk       = $campaign['total_junk'] ?: 0;
$totalSpam       = $campaign['total_spam'] ?: 0;
$totalRejected   = $campaign['total_rejected'] ?: 0;

// Calculate percentages
$deliveryRate   = $totalRecipients > 0 ? round(($totalSent / $totalRecipients) * 100, 1) : 0;
$openRate       = $totalSent > 0 ? round(($totalOpened / $totalSent) * 100, 1) : 0;
$clickRate      = $totalSent > 0 ? round(($totalClicked / $totalSent) * 100, 1) : 0;
$bounceRate     = $totalSent > 0 ? round(($totalBounced / $totalSent) * 100, 1) : 0;
$complaintRate  = $totalSent > 0 ? round(($totalComplained / $totalSent) * 100, 1) : 0;

// Count statuses from log (real-time)
$statusCounts = ['Queued' => 0, 'Sent' => 0, 'Failed' => 0, 'Opened' => 0, 'Clicked' => 0, 'Bounced' => 0];
foreach ($allLogs as $log) {
    $st = $log['status'];
    if (isset($statusCounts[$st])) $statusCounts[$st]++;
}

// Delivery status breakdown from delivery_status column
$delStatus = $db->prepare("SELECT delivery_status, COUNT(*) as cnt FROM email_campaign_log WHERE campaign_id = ? AND delivery_status IS NOT NULL GROUP BY delivery_status");
$delStatus->execute([$campaignId]);
$deliveryBreakdown = $delStatus->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'Campaign Report';
include '../includes/header.php';
?>

<style>
.stats-grid-6 { display: grid; grid-template-columns: repeat(6, 1fr); gap: 16px; margin-bottom: 24px; }
.stat-card { background: #fff; border: 1px solid #e8e8ef; border-radius: 12px; padding: 20px; display: flex; align-items: center; gap: 14px; }
.stat-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.icon-accent { background: rgba(221, 45, 74,0.08); color: #dd2d4a; }
.icon-success { background: rgba(34,197,94,0.08); color: #22c55e; }
.icon-info { background: rgba(59,130,246,0.08); color: #3b82f6; }
.icon-warn { background: rgba(245,158,11,0.08); color: #f59e0b; }
.icon-danger { background: rgba(239,68,68,0.08); color: #ef4444; }
.icon-purple { background: rgba(168,85,247,0.08); color: #a855f7; }
.stat-label { font-size: 12px; font-weight: 600; color: #8a8a9a; text-transform: uppercase; letter-spacing: 0.5px; }
.stat-value { font-size: 22px; font-weight: 700; color: #1a1a2e; }
.stat-value small { font-size: 13px; font-weight: 500; }

.delivery-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin: 16px 0; }
.delivery-stat { background: #f8f9fb; border-radius: 8px; padding: 14px 16px; text-align: center; }
.delivery-stat .num { font-size: 20px; font-weight: 700; color: #1a1a2e; }
.delivery-stat .lbl { font-size: 12px; color: #7a7a8a; margin-top: 2px; }
.delivery-stat.junk .num { color: #f59e0b; }
.delivery-stat.spam .num { color: #ef4444; }
.delivery-stat.rejected .num { color: #dc2626; }
.delivery-stat.delivered .num { color: #22c55e; }

@media (max-width: 900px) {
    .stats-grid-6 { grid-template-columns: repeat(3, 1fr); }
    .delivery-stats { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 500px) {
    .stats-grid-6 { grid-template-columns: 1fr 1fr; }
}
</style>

<div class="page-header">
    <div>
        <a href="email-campaigns.php" class="btn btn-outline btn-sm back-btn-margin">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            Back to Campaigns
        </a>
        <h1><?php echo htmlspecialchars($campaign['name']); ?></h1>
        <p class="text-muted">Subject: <?php echo htmlspecialchars($campaign['subject']); ?> · List: <?php echo htmlspecialchars($campaign['list_name'] ?? '—'); ?></p>
    </div>
    <span class="badge badge-<?php echo strtolower($campaign['status']); ?>"><?php echo $campaign['status']; ?></span>
</div>

<!-- Top Stats Row -->
<div class="stats-grid-6">
    <div class="stat-card">
        <div class="stat-icon icon-accent"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg></div>
        <div><div class="stat-label">Delivered</div><div class="stat-value"><?php echo $totalSent; ?> <small class="text-muted">(<?php echo $deliveryRate; ?>%)</small></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-success"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div>
        <div><div class="stat-label">Opened</div><div class="stat-value"><?php echo $totalOpened; ?> <small class="text-muted">(<?php echo $openRate; ?>%)</small></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-info"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg></div>
        <div><div class="stat-label">Clicked</div><div class="stat-value"><?php echo $totalClicked; ?> <small class="text-muted">(<?php echo $clickRate; ?>%)</small></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-danger"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
        <div><div class="stat-label">Failed</div><div class="stat-value"><?php echo $totalFailed; ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-warn"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
        <div><div class="stat-label">Bounced</div><div class="stat-value"><?php echo $totalBounced; ?> <small class="text-muted">(<?php echo $bounceRate; ?>%)</small></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-purple"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
        <div><div class="stat-label">Complained</div><div class="stat-value"><?php echo $totalComplained; ?> <small class="text-muted">(<?php echo $complaintRate; ?>%)</small></div></div>
    </div>
</div>

<!-- Delivery Status Breakdown (from webhooks) -->
<div class="card mt-2">
    <div class="card-header"><h2 class="card-title">📬 Delivery Status Breakdown</h2></div>
    <div class="card-body">
        <div class="delivery-stats">
            <div class="delivery-stat delivered">
                <div class="num"><?php echo $totalDelivered; ?></div>
                <div class="lbl">Delivered to Inbox</div>
            </div>
            <div class="delivery-stat junk">
                <div class="num"><?php echo $totalJunk; ?></div>
                <div class="lbl">Junk Folder</div>
            </div>
            <div class="delivery-stat spam">
                <div class="num"><?php echo $totalSpam; ?></div>
                <div class="lbl">Spam Folder</div>
            </div>
            <div class="delivery-stat rejected">
                <div class="num"><?php echo $totalRejected; ?></div>
                <div class="lbl">Rejected / Returned</div>
            </div>
        </div>
        <p style="color:#7a7a8a;font-size:13px;margin-top:12px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            These stats come from delivery webhooks. To enable: go to <a href="https://resend.com/domains" target="_blank" style="color:var(--color-primary);">Resend Dashboard → Webhooks</a> and add:
            <code style="background:#f0f0f5;padding:2px 6px;border-radius:4px;font-size:12px;"><?php echo htmlspecialchars(APP_URL); ?>/api/resend-webhook.php</code>
        </p>
    </div>
</div>

<!-- Visual bars -->
<div class="card mt-2">
    <div class="card-body">
        <div class="report-bar-group">
            <div class="report-bar-label">
                <span>Open Rate</span>
                <strong><?php echo $openRate; ?>%</strong>
            </div>
            <div class="progress-bar"><div class="progress-fill bg-success" style="width:<?php echo $openRate; ?>%"></div></div>
        </div>
        <div class="report-bar-group mt-1">
            <div class="report-bar-label">
                <span>Click Rate</span>
                <strong><?php echo $clickRate; ?>%</strong>
            </div>
            <div class="progress-bar"><div class="progress-fill bg-info" style="width:<?php echo $clickRate; ?>%"></div></div>
        </div>
        <div class="report-bar-group mt-1">
            <div class="report-bar-label">
                <span>Delivery Rate</span>
                <strong><?php echo $deliveryRate; ?>%</strong>
            </div>
            <div class="progress-bar"><div class="progress-fill" style="width:<?php echo $deliveryRate; ?>%"></div></div>
        </div>
        <div class="report-bar-group mt-1">
            <div class="report-bar-label">
                <span>Bounce Rate</span>
                <strong><?php echo $bounceRate; ?>%</strong>
            </div>
            <div class="progress-bar"><div class="progress-fill bg-danger" style="width:<?php echo $bounceRate; ?>%"></div></div>
        </div>
    </div>
</div>

<!-- Recipient Log -->
<div class="card mt-2">
    <div class="card-header">
        <h2 class="card-title">Recipient Details</h2>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Company</th>
                    <th>Status</th>
                    <th>Delivery</th>
                    <th>Sent</th>
                    <th>Opened</th>
                    <th>Clicked</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allLogs as $log): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($log['email']); ?></td>
                        <td><?php echo htmlspecialchars($log['company_name'] ?? '—'); ?></td>
                        <td><span class="badge badge-<?php echo strtolower($log['status']); ?>"><?php echo $log['status']; ?></span></td>
                        <td><?php echo $log['delivery_status'] ? htmlspecialchars($log['delivery_status']) : '—'; ?></td>
                        <td><?php echo $log['sent_at'] ? formatDateTime($log['sent_at']) : '—'; ?></td>
                        <td><?php echo $log['opened_at'] ? formatDateTime($log['opened_at']) : '—'; ?></td>
                        <td><?php echo $log['clicked_at'] ? formatDateTime($log['clicked_at']) : '—'; ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($allLogs)): ?
                    <tr><td colspan="7" class="text-center text-muted">No send log entries yet</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>