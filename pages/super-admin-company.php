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

// Load current super admin (used for invite emails + activity log)
$currentUser = getCurrentUser();

// Load platform settings (site_name, support email) for invite emails
$platformSettings = [];
try {
    $rows = $db->query("SELECT setting_key, setting_value FROM settings WHERE company_id = 0 AND setting_key IN ('site_name','platform_support_email','platform_super_admin_email','marketing_url')")->fetchAll(PDO::FETCH_KEY_PAIR);
    $platformSettings = $rows ?: [];
} catch (Exception $e) { /* ignore — fallback to defaults */ }

// Load users for this company (needed by POST handlers like add_user for limit checks)
$users = $db->query("SELECT * FROM users WHERE company_id = ? ORDER BY created_at ASC", [$companyId])->fetchAll(PDO::FETCH_ASSOC);

// Handle POST actions
$csrfToken = generateCSRFToken();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFTokenWithExpiry()) {
        $_SESSION['error'] = 'Invalid request token';
        header('Location: super-admin-company.php?id=' . $companyId);
        exit;
    }
    $action = $_POST['action'] ?? '';

    try {
    
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
            } else {
                $_SESSION['error'] = "Plan '{$newPlanKey}' not found.";
            }
            break;
        case 'add_user':
            $fullName = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = $_POST['role'] ?? 'Sales Rep';
            $password = $_POST['password'] ?? '';
            $sendInvite = !empty($_POST['send_invite']);

            $errors = [];
            if ($fullName === '') $errors[] = 'Full name is required';
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
            if (!in_array($role, ['Admin', 'Sales Manager', 'Sales Rep'])) $errors[] = 'Invalid role';
            if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters';

            // Check plan user limit
            $limit = (int)($company['plan_user_limit'] ?? 0);
            if ($limit > 0 && count($users) >= $limit) {
                $errors[] = "User limit reached ({$limit} on this plan). Upgrade or remove a user first.";
            }

            // Check email uniqueness across the platform
            $existing = $db->query("SELECT user_id FROM users WHERE email = ?", [$email])->fetchColumn();
            if ($existing) $errors[] = 'A user with that email already exists';

            if (!empty($errors)) {
                $_SESSION['error'] = implode('. ', $errors);
                $_SESSION['form_data'] = ['full_name' => $fullName, 'email' => $email, 'role' => $role];
            } else {
                try {
                    $userId = $db->insert('users', [
                        'company_id'  => $companyId,
                        'username'    => $email,
                        'email'       => $email,
                        'full_name'   => $fullName,
                        'role'        => $role,
                        'status'      => 'Active',
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'created_at'  => date('Y-m-d H:i:s'),
                        'email_verified' => 1, // Super admin created = pre-verified
                    ]);

                    if ($userId) {
                        $_SESSION['success'] = "User '{$fullName}' ({$email}) created as {$role}.";

                        // Optionally send invite email with credentials
                        if ($sendInvite) {
                            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                            $inviteLink = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'app.funl.online') . '/login.php';
                            $siteName = $platformSettings['site_name'] ?? 'FunL CRM';
                            $fromEmail = $platformSettings['platform_support_email'] ?? 'noreply@funl.online';
                            $subject = "You've been added to {$company['company_name']} on {$siteName}";
                            $body = "Hi {$fullName},\n\n{$currentUser['full_name']} has created an account for you at {$siteName}.\n\nLogin: {$email}\nPassword: {$password}\nLogin at: {$inviteLink}\n\nPlease change your password after first login.\n\nThanks,\nThe {$siteName} Team";
                            @mail($email, $subject, $body, 'From: ' . $fromEmail);
                            $_SESSION['success'] .= ' Invite email sent.';
                        }

                        logActivity($currentUser['user_id'], 'Created User', 'User', $userId, "Super admin created user: {$fullName} ({$email})");
                    }
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Failed to create user: ' . $e->getMessage();
                }
            }
            break;
        case 'delete_user':
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId) {
                $target = $db->query("SELECT full_name, email, is_super_admin FROM users WHERE user_id = ? AND company_id = ?", [$userId, $companyId])->fetch(PDO::FETCH_ASSOC);
                if (!$target) {
                    $_SESSION['error'] = 'User not found in this company.';
                } elseif ($target['is_super_admin'] && $target['user_id'] == ($currentUser['user_id'] ?? 0)) {
                    $_SESSION['error'] = "You cannot remove your own super admin account.";
                } else {
                    $db->query("DELETE FROM users WHERE user_id = ? AND company_id = ?", [$userId, $companyId]);
                    $_SESSION['success'] = "User '{$target['full_name']}' removed from this company.";
                    logActivity($currentUser['user_id'], 'Deleted User', 'User', $userId, "Super admin removed user: {$target['full_name']} ({$target['email']}) from company #{$companyId}");
                }
            }
            break;
        case 'reset_user_password':
            $userId = (int)($_POST['user_id'] ?? 0);
            $newPassword = $_POST['new_password'] ?? '';
            if ($userId && strlen($newPassword) >= 8) {
                $target = $db->query("SELECT full_name, email FROM users WHERE user_id = ? AND company_id = ?", [$userId, $companyId])->fetch(PDO::FETCH_ASSOC);
                if ($target) {
                    $db->query("UPDATE users SET password_hash = ? WHERE user_id = ?", [password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
                    $_SESSION['success'] = "Password reset for '{$target['full_name']}'. New password: {$newPassword}";
                }
            } else {
                $_SESSION['error'] = 'Password must be at least 8 characters.';
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
    } catch (Throwable $e) {
        error_log('super-admin-company.php action=' . $action . ' error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
        $_SESSION['error'] = 'Server error: ' . $e->getMessage();
    }
    header('Location: super-admin-company.php?id=' . $companyId);
    exit;
}

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
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:14px;">
                    <h2 style="margin:0;">Users (<?= count($users) ?><?= $limit > 0 ? ' / ' . $limit : '' ?>)</h2>
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('addUserForm').style.display = document.getElementById('addUserForm').style.display === 'none' ? 'block' : 'none';">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add User
                    </button>
                </div>

                <?php
                $formData = $_SESSION['form_data'] ?? [];
                unset($_SESSION['form_data']);
                $inviteAvailable = function_exists('mail') || function_exists('error_log');
                ?>

                <!-- Add User Form (collapsible) -->
                <div id="addUserForm" style="display:none;background:#f8fafc;border:1px solid var(--color-border);border-radius:8px;padding:16px;margin-bottom:18px;">
                    <h3 style="margin:0 0 12px;font-size:14px;color:var(--color-text);">New User for <?= htmlspecialchars($company['company_name']) ?></h3>
                    <form method="POST" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="add_user">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div class="form-group">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="full_name" class="form-control" required minlength="2" maxlength="100" value="<?= htmlspecialchars($formData['full_name'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($formData['email'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Role *</label>
                                <select name="role" class="form-control" required>
                                    <option value="Sales Rep" <?= ($formData['role'] ?? '') === 'Sales Rep' ? 'selected' : '' ?>>Sales Rep</option>
                                    <option value="Sales Manager" <?= ($formData['role'] ?? '') === 'Sales Manager' ? 'selected' : '' ?>>Sales Manager</option>
                                    <option value="Admin" <?= ($formData['role'] ?? '') === 'Admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Password *</label>
                                <div style="display:flex;gap:6px;">
                                    <input type="text" name="password" id="newUserPassword" class="form-control" required minlength="8" maxlength="128" style="font-family:monospace;">
                                    <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('newUserPassword').value = generatePassword(16);" title="Generate strong password">🎲</button>
                                </div>
                                <small style="color:var(--color-text-muted);font-size:11px;">Min 8 characters. User should change on first login.</small>
                            </div>
                        </div>
                        <div style="margin-top:8px;">
                            <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                                <input type="checkbox" name="send_invite" value="1" checked>
                                Send welcome email with login credentials
                            </label>
                        </div>
                        <div style="margin-top:14px;display:flex;gap:8px;justify-content:flex-end;">
                            <button type="button" class="btn btn-outline" onclick="document.getElementById('addUserForm').style.display = 'none';">Cancel</button>
                            <button type="submit" class="btn btn-primary">Create User</button>
                        </div>
                    </form>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                        <tr><td colspan="6" style="text-align:center;color:var(--color-text-muted);padding:30px;">No users yet. Click "Add User" above to create one.</td></tr>
                        <?php else: ?>
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
                            <td style="text-align:right;white-space:nowrap;">
                                <button type="button" class="btn btn-sm btn-outline" onclick="showResetPw(<?= (int)$u['user_id'] ?>, '<?= htmlspecialchars(addslashes($u['full_name'] ?? $u['username']), ENT_QUOTES) ?>')">Reset PW</button>
                                <?php if (empty($u['is_super_admin']) || $u['user_id'] != ($currentUser['user_id'] ?? 0)): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Remove <?= htmlspecialchars(addslashes($u['full_name'] ?? $u['username']), ENT_QUOTES) ?> from this company? They will lose access immediately.');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger-outline">Delete</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Reset Password Modal (inline) -->
                <div id="resetPwModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:1000;align-items:center;justify-content:center;">
                    <div style="background:#fff;border-radius:12px;padding:24px;max-width:400px;width:90%;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);">
                        <h3 style="margin:0 0 6px;font-size:16px;">Reset Password</h3>
                        <p style="margin:0 0 14px;color:var(--color-text-secondary);font-size:13px;">Set a new password for <strong id="resetPwName"></strong>.</p>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="action" value="reset_user_password">
                            <input type="hidden" name="user_id" id="resetPwUserId" value="">
                            <div class="form-group">
                                <label class="form-label">New Password *</label>
                                <input type="text" name="new_password" required minlength="8" maxlength="128" class="form-control" style="font-family:monospace;">
                                <small style="color:var(--color-text-muted);font-size:11px;">Min 8 characters. Communicate securely to the user.</small>
                            </div>
                            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px;">
                                <button type="button" class="btn btn-outline" onclick="document.getElementById('resetPwModal').style.display = 'none';">Cancel</button>
                                <button type="submit" class="btn btn-primary">Reset Password</button>
                            </div>
                        </form>
                    </div>
                </div>
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

<script>
function generatePassword(len) {
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*';
    var pwd = '';
    var arr = new Uint32Array(len);
    if (window.crypto && crypto.getRandomValues) {
        crypto.getRandomValues(arr);
        for (var i = 0; i < len; i++) pwd += chars[arr[i] % chars.length];
    } else {
        for (var i = 0; i < len; i++) pwd += chars[Math.floor(Math.random() * chars.length)];
    }
    return pwd;
}

function showResetPw(userId, userName) {
    document.getElementById('resetPwUserId').value = userId;
    document.getElementById('resetPwName').textContent = userName;
    // Suggest a random password
    var pwInput = document.querySelector('#resetPwModal input[name="new_password"]');
    if (pwInput) pwInput.value = generatePassword(14);
    document.getElementById('resetPwModal').style.display = 'flex';
}

// Close modal on backdrop click
document.getElementById('resetPwModal')?.addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});

// Close on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var m = document.getElementById('resetPwModal');
        if (m) m.style.display = 'none';
    }
});
</script>