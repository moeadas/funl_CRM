<?php
/**
 * pages/super-admin-company.php
 * 
 * Super Admin: Company detail view
 * Shows users, current plan, MRR, total revenue, lifetime value,
 * recent transactions, ability to change plan, suspend, etc.
 * 
 * URL: /pages/super-admin-company.php?id=29
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireLogin();
requireSuperAdmin();
require_once __DIR__ . '/../includes/company-functions.php';

$db = Database::getInstance();
$companyId = (int)($_GET['id'] ?? 0);
if (!$companyId) {
    header('Location: super-admin.php');
    exit;
}

// Load company
$company = $db->query("SELECT * FROM companies WHERE company_id = ?", [$companyId])->fetch(PDO::FETCH_ASSOC);
if (!$company) {
    $_SESSION['error'] = 'Company not found';
    header('Location: super-admin.php');
    exit;
}

// Handle POST actions
$csrfToken = generateCSRFToken();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFTokenWithExpiry()) {
        $_SESSION['error'] = 'Invalid request token';
        header('Location: super-admin-company.php?id=' . $companyId);
        exit;
    }
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_plan':
            $newPlanKey = $_POST['plan_key'] ?? '';
            $newStatus = $_POST['subscription_status'] ?? '';
            $periodEnd = !empty($_POST['current_period_end']) ? $_POST['current_period_end'] : null;
            $plan = $db->query("SELECT * FROM plans WHERE plan_key = ?", [$newPlanKey])->fetch(PDO::FETCH_ASSOC);
            if ($plan) {
                $db->query("UPDATE companies SET plan_id = ?, plan_name = ?, plan_user_limit = ?, plan_price_monthly = ?, subscription_status = ?, current_period_end = ?, updated_at = NOW() WHERE company_id = ?",
                    [$plan['plan_key'], $plan['plan_name'], $plan['user_limit'], $plan['monthly_price'], $newStatus, $periodEnd, $companyId]);
                $_SESSION['success'] = "Plan updated to {$plan['plan_name']} ({$newStatus})";
            }
            break;
        case 'extend_trial':
            $days = (int)($_POST['days'] ?? 0);
            if ($days > 0) {
                $currentEnd = $company['trial_ends_at'] ?: date('Y-m-d H:i:s');
                $newEnd = date('Y-m-d H:i:s', strtotime($currentEnd . " +$days days"));
                $db->query("UPDATE companies SET trial_ends_at = ?, subscription_status = 'trial' WHERE company_id = ?", [$newEnd, $companyId]);
                $_SESSION['success'] = "Extended trial by $days days. New end: $newEnd";
            }
            break;
        case 'suspend':
            $db->query("UPDATE companies SET status = 'suspended' WHERE company_id = ?", [$companyId]);
            $_SESSION['success'] = 'Company suspended';
            break;
        case 'reactivate':
            $db->query("UPDATE companies SET status = 'active' WHERE company_id = ?", [$companyId]);
            $_SESSION['success'] = 'Company reactivated';
            break;
    }
    header('Location: super-admin-company.php?id=' . $companyId);
    exit;
}

// Load users for this company
$users = $db->query("SELECT * FROM users WHERE company_id = ? ORDER BY created_at ASC", [$companyId])->fetchAll(PDO::FETCH_ASSOC);

// Load recent transactions
$transactions = $db->query("
    SELECT pt.*, 
           (SELECT plan_name FROM plans p WHERE p.plan_key = CONVERT(pt.plan_key USING utf8mb4) COLLATE utf8mb4_unicode_ci) as plan_name
    FROM payment_transactions pt
    WHERE company_id = ?
    ORDER BY created_at DESC
    LIMIT 20
", [$companyId])->fetchAll(PDO::FETCH_ASSOC);

// Revenue stats
$revStats = $db->query("
    SELECT 
        COUNT(*) as txn_count,
        COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), 0) as total_revenue,
        COALESCE(SUM(CASE WHEN status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN amount ELSE 0 END), 0) as mrr_30d,
        MAX(CASE WHEN status = 'completed' THEN completed_at END) as last_payment_at
    FROM payment_transactions 
    WHERE company_id = ?
", [$companyId])->fetch(PDO::FETCH_ASSOC);

// Plan info
$currentPlan = null;
if ($company['plan_id']) {
    $currentPlan = $db->query("SELECT * FROM plans WHERE plan_key = ?", [$company['plan_id']])->fetch(PDO::FETCH_ASSOC);
}
$allPlans = $db->query("SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);

// Recent activity
$activity = $db->query("
    SELECT COUNT(*) as lead_count FROM leads WHERE company_id = ?
    UNION ALL
    SELECT COUNT(*) FROM contacts WHERE company_id = ?
    UNION ALL
    SELECT COUNT(*) FROM deals WHERE company_id = ?
", [$companyId, $companyId, $companyId])->fetchAll(PDO::FETCH_COLUMN, 0);

$leadCount = $activity[0] ?? 0;
$contactCount = $activity[1] ?? 0;
$dealCount = $activity[2] ?? 0;

// Calculate MRR
$monthlyPrice = (float)($company['plan_price_monthly'] ?? 0);
$mrr = 0;
if ($company['subscription_status'] === 'active') {
    $mrr = $monthlyPrice;
}

$pageTitle = 'Company: ' . $company['company_name'];
include __DIR__ . '/../includes/header.php';
?>

<style>
.detail-container { max-width: 1200px; margin: 24px auto; padding: 0 24px; }
.detail-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
.detail-header h1 { font-size: 24px; margin: 0; }
.detail-header .back-link { color: #667eea; text-decoration: none; font-size: 14px; }
.detail-header .back-link:hover { text-decoration: underline; }

.badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.badge-active { background: #d4edda; color: #155724; }
.badge-trial { background: #e8f4fd; color: #1976d2; }
.badge-past_due { background: #fff3cd; color: #856404; }
.badge-cancelled { background: #f8d7da; color: #721c24; }
.badge-suspended { background: #dc3545; color: white; }

.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
@media (max-width: 900px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
.stat-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
.stat-label { font-size: 11px; text-transform: uppercase; color: #999; margin-bottom: 6px; letter-spacing: .5px; }
.stat-value { font-size: 24px; font-weight: 700; color: #333; }
.stat-value small { font-size: 14px; color: #999; font-weight: 400; }
.stat-sub { font-size: 12px; color: #999; margin-top: 4px; }

.detail-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
@media (max-width: 900px) { .detail-grid { grid-template-columns: 1fr; } }

.section { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.08); margin-bottom: 24px; }
.section h2 { font-size: 16px; margin: 0 0 16px; color: #666; text-transform: uppercase; letter-spacing: .5px; }
.section h3 { font-size: 14px; margin: 16px 0 8px; color: #333; }

.info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
.info-row:last-child { border: none; }
.info-row .label { color: #999; }
.info-row .value { color: #333; font-weight: 500; }

table { width: 100%; border-collapse: collapse; }
th { text-align: left; font-size: 12px; text-transform: uppercase; color: #999; padding: 8px 12px; background: #f9fafb; font-weight: 600; }
td { padding: 10px 12px; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
tr:last-child td { border: none; }

.btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; }
.btn-primary { background: #667eea; color: white; }
.btn-primary:hover { background: #5568d3; }
.btn-secondary { background: #f0f0f0; color: #333; }
.btn-secondary:hover { background: #e0e0e0; }
.btn-danger { background: #dc3545; color: white; }
.btn-danger:hover { background: #c82333; }
.btn-success { background: #28a745; color: white; }

.alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

.form-group { margin-bottom: 14px; }
.form-label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #555; }
.form-control { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
</style>

<div class="detail-container">
    <div class="detail-header">
        <div>
            <a href="super-admin.php" class="back-link">← All Companies</a>
            <h1 style="margin-top:8px;"><?= htmlspecialchars($company['company_name']) ?>
                <span class="badge badge-<?= $company['subscription_status'] ?: 'trial' ?>">
                    <?= ucfirst($company['subscription_status'] ?? 'trial') ?>
                </span>
                <?php if ($company['status'] === 'suspended'): ?>
                    <span class="badge badge-suspended">SUSPENDED</span>
                <?php endif; ?>
            </h1>
            <p style="color:#666;font-size:14px;margin-top:4px;">
                Company ID: <?= $companyId ?> · Slug: <code><?= htmlspecialchars($company['company_slug'] ?? 'n/a') ?></code> · Joined: <?= date('M j, Y', strtotime($company['created_at'] ?? 'now')) ?>
            </p>
        </div>
        <div>
            <?php if ($company['status'] === 'suspended'): ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="reactivate">
                    <button class="btn btn-success">Reactivate Company</button>
                </form>
            <?php else: ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="suspend">
                    <button class="btn btn-danger" onclick="return confirm('Suspend this company? All users will lose access.');">Suspend</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">MRR</div>
            <div class="stat-value">$<?= number_format($mrr, 2) ?></div>
            <div class="stat-sub">Monthly recurring revenue</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Lifetime Revenue</div>
            <div class="stat-value">$<?= number_format((float)$revStats['total_revenue'], 2) ?></div>
            <div class="stat-sub"><?= (int)$revStats['txn_count'] ?> transactions</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Users</div>
            <div class="stat-value"><?= count($users) ?><small> / <?= (int)($company['plan_user_limit'] ?? '∞') ?></small></div>
            <div class="stat-sub">Active in workspace</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Last Payment</div>
            <div class="stat-value" style="font-size:18px;">
                <?php if ($revStats['last_payment_at']): ?>
                    <?= date('M j, Y', strtotime($revStats['last_payment_at'])) ?>
                <?php else: ?>
                    <span style="color:#999;">Never</span>
                <?php endif; ?>
            </div>
            <div class="stat-sub">30-day: $<?= number_format((float)$revStats['mrr_30d'], 2) ?></div>
        </div>
    </div>

    <div class="detail-grid">
        <div>
            <!-- Plan Management -->
            <div class="section">
                <h2>Plan & Subscription</h2>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="update_plan">
                    <div class="form-group">
                        <label class="form-label">Plan</label>
                        <select name="plan_key" class="form-control">
                            <?php foreach ($allPlans as $p): ?>
                                <option value="<?= $p['plan_key'] ?>" <?= ($company['plan_id'] === $p['plan_key']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['plan_name']) ?> — $<?= number_format($p['monthly_price'], 2) ?>/mo · <?= (int)$p['user_limit'] ?> users
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Subscription Status</label>
                        <select name="subscription_status" class="form-control">
                            <option value="trial" <?= $company['subscription_status'] === 'trial' ? 'selected' : '' ?>>Trial</option>
                            <option value="active" <?= $company['subscription_status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="past_due" <?= $company['subscription_status'] === 'past_due' ? 'selected' : '' ?>>Past Due</option>
                            <option value="cancelled" <?= $company['subscription_status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Current Period End (or trial end)</label>
                        <input type="datetime-local" name="current_period_end" class="form-control" value="<?= $company['current_period_end'] ? date('Y-m-d\TH:i', strtotime($company['current_period_end'])) : '' ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Save Plan</button>
                </form>
                
                <h3>Extend Trial</h3>
                <form method="POST" style="display:flex;gap:8px;align-items:flex-end;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="extend_trial">
                    <div class="form-group" style="flex:1;margin:0;">
                        <label class="form-label">Days to extend</label>
                        <input type="number" name="days" min="1" max="365" value="7" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-secondary">Extend</button>
                </form>
            </div>

            <!-- Users -->
            <div class="section">
                <h2>Users (<?= count($users) ?>)</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['full_name'] ?? $u['username']) ?>
                                <?php if (!empty($u['is_super_admin'])): ?>
                                    <span style="color:#dc3545;font-size:11px;">[SUPER]</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($u['email'] ?? $u['username']) ?></td>
                            <td><?= htmlspecialchars($u['role'] ?? 'Sales Rep') ?></td>
                            <td><span class="badge badge-<?= ($u['status'] === 'Active') ? 'active' : 'cancelled' ?>"><?= htmlspecialchars($u['status']) ?></span></td>
                            <td><?= !empty($u['last_login']) ? date('M j, Y', strtotime($u['last_login'])) : '<span style="color:#999;">Never</span>' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Transactions -->
            <div class="section">
                <h2>Recent Transactions</h2>
                <?php if (empty($transactions)): ?>
                    <p style="color:#999;text-align:center;padding:20px;">No payment transactions yet.</p>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Order ID</th>
                            <th>Plan</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Gateway Ref</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $t): ?>
                        <tr>
                            <td><?= date('M j, Y H:i', strtotime($t['created_at'])) ?></td>
                            <td><code style="font-size:11px;"><?= htmlspecialchars($t['order_id']) ?></code></td>
                            <td><?= htmlspecialchars($t['plan_name'] ?? $t['plan_key'] ?? '—') ?></td>
                            <td>$<?= number_format((float)$t['amount'], 2) ?> <?= $t['currency'] ?></td>
                            <td><span class="badge badge-<?= $t['status'] === 'completed' ? 'active' : ($t['status'] === 'failed' ? 'cancelled' : 'trial') ?>"><?= htmlspecialchars($t['status']) ?></span></td>
                            <td><code style="font-size:11px;"><?= htmlspecialchars($t['gateway_reference'] ?? '—') ?></code></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <!-- Company info -->
            <div class="section">
                <h2>Company Info</h2>
                <div class="info-row"><span class="label">Plan</span><span class="value"><?= htmlspecialchars($company['plan_name'] ?? 'None') ?></span></div>
                <div class="info-row"><span class="label">User Limit</span><span class="value"><?= (int)($company['plan_user_limit'] ?? 0) ?></span></div>
                <div class="info-row"><span class="label">Price</span><span class="value">$<?= number_format((float)($company['plan_price_monthly'] ?? 0), 2) ?>/mo</span></div>
                <div class="info-row"><span class="label">Billing</span><span class="value"><?= htmlspecialchars($company['billing_cycle'] ?? 'monthly') ?></span></div>
                <div class="info-row"><span class="label">Trial Ends</span><span class="value"><?= $company['trial_ends_at'] ? date('M j, Y', strtotime($company['trial_ends_at'])) : '—' ?></span></div>
                <div class="info-row"><span class="label">Period End</span><span class="value"><?= $company['current_period_end'] ? date('M j, Y', strtotime($company['current_period_end'])) : '—' ?></span></div>
                <div class="info-row"><span class="label">Cancel @ Period End</span><span class="value"><?= $company['cancel_at_period_end'] ? 'Yes' : 'No' ?></span></div>
            </div>

            <!-- Activity -->
            <div class="section">
                <h2>Activity</h2>
                <div class="info-row"><span class="label">Leads</span><span class="value"><?= number_format($leadCount) ?></span></div>
                <div class="info-row"><span class="label">Contacts</span><span class="value"><?= number_format($contactCount) ?></span></div>
                <div class="info-row"><span class="label">Deals</span><span class="value"><?= number_format($dealCount) ?></span></div>
                <div class="info-row"><span class="label">Email</span><span class="value"><?= htmlspecialchars($company['email'] ?? '—') ?></span></div>
                <div class="info-row"><span class="label">Phone</span><span class="value"><?= htmlspecialchars($company['phone'] ?? '—') ?></span></div>
                <div class="info-row"><span class="label">Timezone</span><span class="value"><?= htmlspecialchars($company['timezone'] ?? 'UTC') ?></span></div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>