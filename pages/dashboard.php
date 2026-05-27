<?php
/**
 * White Label CRM - KPI Dashboard
 * Visual home with key metrics
 */
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$userId = getCurrentUserId();
$companyId = $_SESSION["company_id"] ?? null;

$pageTitle = 'Dashboard';
$js = ['dashboard'];

require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();

// Quick stats
$stats = [
    'leads' => $db->query("SELECT COUNT(*) as cnt FROM leads WHERE company_id = ?", [$companyId])->fetch()['cnt'] ?? 0,
    'contacts' => $db->query("SELECT COUNT(*) as cnt FROM contacts WHERE company_id = ?", [$companyId])->fetch()['cnt'] ?? 0,
    'deals_open' => $db->query("SELECT COUNT(*) as cnt FROM deals WHERE company_id = ? AND stage NOT IN ('closed_won','closed_lost')", [$companyId])->fetch()['cnt'] ?? 0,
    'deals_value' => $db->query("SELECT SUM(deal_value) as total FROM deals WHERE company_id = ? AND stage NOT IN ('closed_won','closed_lost')", [$companyId])->fetch()['total'] ?? 0,
    'tasks_due' => $db->query("SELECT COUNT(*) as cnt FROM tasks WHERE company_id = ? AND status != 'done' AND (due_date IS NULL OR due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY))", [$companyId])->fetch()['cnt'] ?? 0,
    'tickets_open' => $db->query("SELECT COUNT(*) as cnt FROM support_tickets WHERE company_id = ? AND status IN ('open','in_progress')", [$companyId])->fetch()['cnt'] ?? 0,
];

// Recent activity
$activities = $db->query("
    SELECT a.*, u.full_name as user_name
    FROM activity_log a
    LEFT JOIN users u ON a.user_id = u.user_id
    WHERE a.company_id = ?
    ORDER BY a.created_at DESC
    LIMIT 10", [$companyId])->fetchAll();

// Pipeline breakdown
$pipeline = $db->query("
    SELECT stage, COUNT(*) as cnt, SUM(deal_value) as value
    FROM deals
    WHERE company_id = ? AND stage NOT IN ('closed_won','closed_lost')
    GROUP BY stage
    ORDER BY FIELD(stage, 'prospecting','qualification','proposal','negotiation')", [$companyId])->fetchAll();
?>

<style>
.dashboard-page { max-width: 1400px; margin: 0 auto; padding: 0 20px 40px; }
.welcome-header { padding: 24px 0 20px; }
.welcome-header h1 { font-size: 22px; font-weight: 600; margin: 0 0 4px; }
.welcome-header p { font-size: 14px; color: #6b7280; margin: 0; }

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 32px;
}
.stat-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 20px;
    transition: box-shadow 0.15s;
}
.stat-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
.stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    margin-bottom: 12px;
}
.stat-icon.blue { background: #dbeafe; }
.stat-icon.green { background: #dcfce7; }
.stat-icon.orange { background: #fef3c7; }
.stat-icon.purple { background: #f3e8ff; }
.stat-icon.red { background: #fee2e2; }
.stat-icon.gray { background: #f3f4f6; }
.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 4px;
}
.stat-value.currency { font-size: 22px; }
.stat-label {
    font-size: 13px;
    color: #6b7280;
}

/* Two Column Layout */
.dashboard-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
}
@media (max-width: 900px) {
    .dashboard-grid { grid-template-columns: 1fr; }
}

/* Pipeline Chart */
.pipeline-chart {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 20px;
}
.chart-title {
    font-size: 15px;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 20px;
}
.pipeline-bar {
    display: flex;
    height: 32px;
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 16px;
}
.pipeline-segment {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 600;
    color: white;
    min-width: 40px;
}
.pipeline-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}
.legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #6b7280;
}
.legend-color {
    width: 10px;
    height: 10px;
    border-radius: 2px;
}

/* Activity Feed */
.activity-feed {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 20px;
}
.activity-title {
    font-size: 15px;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 16px;
}
.activity-item {
    display: flex;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid #f3f4f6;
}
.activity-item:last-child { border-bottom: none; }
.activity-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #2563eb;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    flex-shrink: 0;
}
.activity-content { flex: 1; }
.activity-text {
    font-size: 13px;
    color: #374151;
    line-height: 1.4;
}
.activity-time {
    font-size: 11px;
    color: #9ca3af;
    margin-top: 2px;
}

/* Quick Actions */
.quick-actions {
    display: flex;
    gap: 10px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}
.quick-btn {
    padding: 10px 16px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    color: #374151;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s;
}
.quick-btn:hover {
    background: #f9fafb;
    border-color: #d1d5db;
    transform: translateY(-1px);
}
</style>

<div class="dashboard-page">
    <div class="welcome-header">
        <h1>Good <?php echo  date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening') ?>!</h1>
        <p>Here's what's happening in your business today.</p>
    </div>

    <div class="quick-actions">
        <a href="/pages/leads.php" class="quick-btn">+ New Lead</a>
        <a href="/pages/deals.php?action=new" class="quick-btn">+ New Deal</a>
        <a href="/pages/tasks.php?action=new" class="quick-btn">+ New Task</a>
        <a href="/pages/quotes.php?action=new" class="quick-btn">+ New Quote</a>
        <a href="/pages/tickets.php" class="quick-btn">+ New Ticket</a>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">📋</div>
            <div class="stat-value"><?php echo  number_format($stats['leads']) ?></div>
            <div class="stat-label">Total Leads</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple">👥</div>
            <div class="stat-value"><?php echo  number_format($stats['contacts']) ?></div>
            <div class="stat-label">Contacts</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange">🎯</div>
            <div class="stat-value"><?php echo  number_format($stats['deals_open']) ?></div>
            <div class="stat-label">Open Deals</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">💰</div>
            <div class="stat-value currency">$<?php echo  number_format($stats['deals_value']) ?></div>
            <div class="stat-label">Pipeline Value</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red">⚡</div>
            <div class="stat-value"><?php echo  number_format($stats['tasks_due']) ?></div>
            <div class="stat-label">Tasks Due Soon</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon gray">🎫</div>
            <div class="stat-value"><?php echo  number_format($stats['tickets_open']) ?></div>
            <div class="stat-label">Open Tickets</div>
        </div>
    </div>

    <div class="dashboard-grid">
        <!-- Pipeline -->
        <div class="pipeline-chart">
            <div class="chart-title">Pipeline Overview</div>
            <?php 
            $totalDeals = array_sum(array_column($pipeline, 'cnt'));
            $stageColors = ['prospecting' => '#9ca3af', 'qualification' => '#60a5fa', 'proposal' => '#a78bfa', 'negotiation' => '#fbbf24'];
            $stageLabels = ['prospecting' => 'Prospecting', 'qualification' => 'Qualification', 'proposal' => 'Proposal', 'negotiation' => 'Negotiation'];
            ?>
            
            <?php if ($totalDeals > 0): ?>
                <div class="pipeline-bar">
                    <?php foreach ($pipeline as $p): 
                        $width = ($p['cnt'] / $totalDeals) * 100;
                        $color = $stageColors[$p['stage']] ?? '#9ca3af';
                    ?>
                        <div class="pipeline-segment" style="width: <?php echo  $width ?>%; background: <?php echo  $color ?>">
                            <?php echo  $p['cnt'] ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="pipeline-legend">
                    <?php foreach ($pipeline as $p): 
                        $color = $stageColors[$p['stage']] ?? '#9ca3af';
                        $label = $stageLabels[$p['stage']] ?? $p['stage'];
                    ?>
                        <div class="legend-item">
                            <div class="legend-color" style="background: <?php echo  $color ?>"></div>
                            <span><?php echo  $label ?> (<?php echo  $p['cnt'] ?>)</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align:center;padding:40px;color:#9ca3af">No open deals. Create your first deal in the Pipeline.</div>
            <?php endif; ?>
        </div>

        <!-- Activity Feed -->
        <div class="activity-feed">
            <div class="activity-title">Recent Activity</div>
            <?php if (empty($activities)): ?>
                <div style="text-align:center;padding:20px;color:#9ca3af;font-size:13px">No recent activity.</div>
            <?php else: ?>
                <?php foreach ($activities as $a): 
                    $initials = '';
                    if ($a['user_name']) {
                        $parts = explode(' ', $a['user_name']);
                        $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                    }
                    $time = strtotime($a['created_at']);
                    $timeAgo = time() - $time < 60 ? 'Just now' : (time() - $time < 3600 ? floor((time() - $time) / 60) . 'm ago' : (time() - $time < 86400 ? floor((time() - $time) / 3600) . 'h ago' : date('M j', $time)));
                ?>
                    <div class="activity-item">
                        <div class="activity-avatar"><?php echo  $initials ?: '?' ?></div>
                        <div class="activity-content">
                            <div class="activity-text">
                                <strong><?php echo  htmlspecialchars($a['user_name'] ?? 'System') ?></strong> 
                                <?php echo  htmlspecialchars(strtolower($a['action'])) ?> 
                                <?php echo  htmlspecialchars($a['entity_type']) ?>
                            </div>
                            <div class="activity-time"><?php echo  $timeAgo ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
