<?php
/**
 * White Label CRM - Super Admin Panel
 * Platform-level administration for all tenants
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/company-functions.php';
startSecureSession();
requireLogin();
requireSuperAdmin();

$currentUser = getCurrentUser();
$db = Database::getInstance();
$csrf_token = generateCSRFToken();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCSRF();
    
    switch ($_POST['action']) {
        case 'create_company':
            $companyName = sanitizeInput($_POST['company_name'] ?? '');
            $companySlug = preg_replace('/[^a-z0-9-]/', '', strtolower(str_replace(' ', '-', $companyName)));
            $email = sanitizeInput($_POST['email'] ?? '');
            $phone = sanitizeInput($_POST['phone'] ?? '');
            $planKey = sanitizeInput($_POST['plan'] ?? 'single');
            $adminName = sanitizeInput($_POST['admin_name'] ?? '');
            $adminEmail = sanitizeInput($_POST['admin_email'] ?? '');
            $password = $_POST['admin_password'] ?? '';
            
            if ($companyName && $email && $adminName && $adminEmail && $password) {
                $plan = getPlan($planKey) ?: getPlan('single');
                $trialEnds = date('Y-m-d H:i:s', strtotime('+14 days'));
                
                $companyId = $db->insert('companies', [
                    'company_name' => $companyName,
                    'company_slug' => $companySlug,
                    'email' => $email,
                    'phone' => $phone,
                    'status' => 'active',
                    'trial_ends_at' => $trialEnds,
                    'subscription_status' => 'trial',
                    'plan_id' => $planKey,
                    'plan_name' => $plan['plan_name'],
                    'plan_user_limit' => $plan['user_limit'],
                    'plan_price_monthly' => $plan['monthly_price'],
                    'extra_user_price' => $plan['extra_user_price'],
                ]);
                
                $db->insert('users', [
                    'company_id' => $companyId,
                    'username' => $adminEmail,
                    'email' => $adminEmail,
                    'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                    'full_name' => $adminName,
                    'role' => 'Admin',
                    'status' => 'Active',
                ]);
                
                $defaultSettings = [
                    'company_name' => $companyName,
                    'company_email' => $email,
                    'company_phone' => $phone,
                    'app_name' => 'White Label CRM',
                    'records_per_page' => '25',
                    'timezone' => 'UTC',
                    'email_from_name' => $companyName,
                ];
                foreach ($defaultSettings as $key => $value) {
                    $db->insert('settings', [
                        'company_id' => $companyId,
                        'setting_key' => $key,
                        'setting_value' => $value,
                        'setting_type' => 'text',
                    ]);
                }
                
                $_SESSION['success'] = "Company '$companyName' created successfully.";
            } else {
                $_SESSION['error'] = 'All fields are required.';
            }
            break;
            
        case 'update_company_status':
            $companyId = intval($_POST['company_id'] ?? 0);
            $status = sanitizeInput($_POST['status'] ?? '');
            if ($companyId && in_array($status, ['active', 'suspended', 'cancelled'])) {
                $db->query("UPDATE companies SET status = ? WHERE company_id = ?", [$status, $companyId]);
                $_SESSION['success'] = 'Company status updated.';
            }
            break;
            
        case 'create_user_for_company':
            $companyId = intval($_POST['company_id'] ?? 0);
            $username = sanitizeInput($_POST['username'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            $fullName = sanitizeInput($_POST['full_name'] ?? '');
            $role = sanitizeInput($_POST['role'] ?? 'Sales Rep');
            $password = $_POST['password'] ?? '';
            
            if ($companyId && $username && $email && $fullName && $password) {
                // Check for duplicate email across ALL users
                $existing = $db->query("SELECT user_id FROM users WHERE email = ?", [$email])->fetch();
                if ($existing) {
                    $_SESSION['error'] = "Email '$email' is already registered. Please use a different email address.";
                } else {
                    $db->insert('users', [
                        'company_id' => $companyId,
                        'username' => $username,
                        'email' => $email,
                        'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                        'full_name' => $fullName,
                        'role' => $role,
                        'status' => 'Active',
                    ]);
                    $_SESSION['success'] = "User '$fullName' created for company.";
                }
            } else {
                $_SESSION['error'] = 'All fields are required.';
            }
            break;
            
        case 'save_platform_settings':
            // Save platform-level settings: support email + super admin email.
            // Stored in settings table with company_id = NULL (global).
            $supportEmail = trim($_POST['support_email'] ?? '');
            $superAdminEmail = trim($_POST['super_admin_email'] ?? '');
            $marketingUrl = trim($_POST['marketing_url'] ?? '');
            $siteName = trim($_POST['site_name'] ?? '');
            // NI Gateway settings
            $niGatewayUrl = trim($_POST['ni_gateway_url'] ?? '');
            $niMerchantId = trim($_POST['ni_merchant_id'] ?? '');
            $niApiUsername = trim($_POST['ni_api_username'] ?? '');
            $niApiPasswordRaw = $_POST['ni_api_password'] ?? '';
            $niApiVersion = trim($_POST['ni_api_version'] ?? '100');
            $niEnabled = isset($_POST['ni_enabled']) ? '1' : '0';
            
            // H-4 fix: encrypt NI password if provided (never store plaintext).
            // If blank, preserve the existing stored value.
            $niApiPassword = '';
            if ($niApiPasswordRaw !== '') {
                $niApiPassword = encryptToken($niApiPasswordRaw);
            } else {
                // Keep existing
                $existingPw = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'ni_api_password' AND company_id = 0")->fetchColumn();
                if ($existingPw) $niApiPassword = $existingPw;
            }
            
            $updates = [
                'platform_support_email' => filter_var($supportEmail, FILTER_VALIDATE_EMAIL) ?: '',
                'platform_super_admin_email' => filter_var($superAdminEmail, FILTER_VALIDATE_EMAIL) ?: '',
                'marketing_url' => $marketingUrl,
                'site_name' => $siteName,
                // Network International Gateway settings
                'ni_gateway_url' => $niGatewayUrl,
                'ni_merchant_id' => $niMerchantId,
                'ni_api_username' => $niApiUsername,
                'ni_api_password' => $niApiPassword,
                'ni_api_version' => $niApiVersion ?: '100',
                'ni_enabled' => $niEnabled,
            ];
            foreach ($updates as $k => $v) {
                $existing = $db->query("SELECT setting_id FROM settings WHERE setting_key = ? AND company_id = 0", [$k])->fetch();
                if ($existing) {
                    $db->query("UPDATE settings SET setting_value = ? WHERE setting_key = ? AND company_id = 0", [$v, $k]);
                } else {
                    $db->query("INSERT INTO settings (setting_key, setting_value, setting_type, company_id) VALUES (?, ?, 'text', 0)", [$k, $v]);
                }
            }
            $_SESSION['success'] = 'Platform settings saved.';
            break;

        case 'create_super_admin':
            // Allow platform owner to add additional super admins.
            $username = sanitizeInput($_POST['username'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            $fullName = sanitizeInput($_POST['full_name'] ?? '');
            $password = $_POST['password'] ?? '';
            if ($username && $email && $fullName && strlen($password) >= 8) {
                $existing = $db->query("SELECT user_id FROM users WHERE email = ?", [$email])->fetch();
                if ($existing) {
                    $_SESSION['error'] = "Email '$email' is already registered.";
                } else {
                    $db->insert('users', [
                        'company_id' => null,
                        'username' => $username,
                        'email' => $email,
                        'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                        'full_name' => $fullName,
                        'role' => 'Admin',
                        'status' => 'Active',
                        'is_super_admin' => 1,
                        'email_verified' => 1,
                    ]);
                    $_SESSION['success'] = "Super admin '$fullName' created.";
                }
            } else {
                $_SESSION['error'] = 'All fields are required and password must be at least 8 characters.';
            }
            break;

        case 'delete_super_admin':
            $userId = intval($_POST['user_id'] ?? 0);
            if ($userId && $userId != getCurrentUserId()) {
                // Only allow removing other super admins, not yourself
                $db->query("UPDATE users SET is_super_admin = 0, status = 'Inactive' WHERE user_id = ? AND is_super_admin = 1 AND user_id != ?", [$userId, getCurrentUserId()]);
                $_SESSION['success'] = 'Super admin removed.';
            } else {
                $_SESSION['error'] = 'Cannot remove yourself.';
            }
            break;

        case 'delete_company':
            $companyId = intval($_POST['company_id'] ?? 0);
            if ($companyId) {
                try {
                    $db->beginTransaction();
                    
                    // Delete in dependency order (child tables first)
                    $tables = [
                        'activity_log',
                        'interactions',
                        'documents',
                        'email_campaign_log',
                        'email_campaigns',
                        'email_list_members',
                        'email_lists',
                        'email_templates',
                        'lead_custom_values',
                        'custom_fields',
                        'whatsapp_messages',
                        'voip_calls',
                        'webhook_log',
                        'webhook_endpoints',
                        'settings',
                        'leads',
                        'users',
                        'companies',
                    ];
                    
                    foreach ($tables as $table) {
                        try {
                            $db->query("DELETE FROM $table WHERE company_id = ?", [$companyId]);
                        } catch (Exception $e) {
                            // Some tables may not have company_id column, skip
                        }
                    }
                    
                    $db->commit();
                    $_SESSION['success'] = 'Company and all related data deleted.';
                } catch (Exception $e) {
                    $db->rollBack();
                    $_SESSION['error'] = 'Failed to delete company: ' . $e->getMessage();
                }
            }
            break;
    }
    
    header('Location: super-admin.php');
    exit;
}

// Get stats
$stats = [
    'total_companies' => $db->query("SELECT COUNT(*) FROM companies")->fetchColumn(),
    'active_companies' => $db->query("SELECT COUNT(*) FROM companies WHERE status = 'active'")->fetchColumn(),
    'trial_companies' => $db->query("SELECT COUNT(*) FROM companies WHERE subscription_status = 'trial'")->fetchColumn(),
    'past_due_companies' => $db->query("SELECT COUNT(*) FROM companies WHERE subscription_status = 'past_due'")->fetchColumn(),
    'total_users' => $db->query("SELECT COUNT(*) FROM users WHERE status = 'Active'")->fetchColumn(),
    'total_leads' => $db->query("SELECT COUNT(*) FROM leads")->fetchColumn(),
];

// Get all companies
$companies = $db->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM users u WHERE u.company_id = c.company_id AND u.status = 'Active') as user_count,
           (SELECT COUNT(*) FROM leads l WHERE l.company_id = c.company_id) as lead_count
    FROM companies c
    ORDER BY c.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$plans = getActivePlans();

// Load platform-level settings (support email, super admin email, etc.)
$platformSettings = [];
$psRows = $db->query("SELECT setting_key, setting_value FROM settings WHERE company_id = 0 AND setting_key IN ('platform_support_email','platform_super_admin_email','marketing_url','site_name','ni_gateway_url','ni_merchant_id','ni_api_username','ni_api_password','ni_api_version','ni_enabled','ni_webhook_secret')")->fetchAll(PDO::FETCH_KEY_PAIR);
foreach ($psRows as $k => $v) $platformSettings[$k] = $v;

// Load all super admins
$superAdmins = $db->query("SELECT user_id, username, email, full_name, status, created_at FROM users WHERE is_super_admin = 1 ORDER BY created_at ASC")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Super Admin';
include '../includes/header.php';
?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success" style="margin:16px auto;max-width:1200px;padding:12px 16px;background:#dcfce7;color:#166534;border-radius:8px;"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error" style="margin:16px auto;max-width:1200px;padding:12px 16px;background:#fee2e2;color:#dc2626;border-radius:8px;"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo __('Platform Administration'); ?></h1>
        <p style="font-size:13px;color:var(--color-text-secondary);margin-top:4px;"><?php echo __('Manage all tenants, subscriptions, and users.'); ?></p>
    </div>
    <a href="/pages/super-admin-company-new.php" class="btn btn-primary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <?php echo __('New Company'); ?>
    </button>
</div>

<!-- Stats Cards -->
<div class="grid grid-3" style="gap:16px;margin-bottom:24px;">
    <div class="card" style="text-align:center;padding:24px;">
        <div style="font-size:32px;font-weight:700;color:var(--color-primary);"><?php echo $stats['total_companies']; ?></div>
        <div style="font-size:13px;color:var(--color-text-muted);"><?php echo __('Total Companies'); ?></div>
    </div>
    <div class="card" style="text-align:center;padding:24px;">
        <div style="font-size:32px;font-weight:700;color:var(--color-success);"><?php echo $stats['active_companies']; ?></div>
        <div style="font-size:13px;color:var(--color-text-muted);"><?php echo __('Active'); ?></div>
    </div>
    <div class="card" style="text-align:center;padding:24px;">
        <div style="font-size:32px;font-weight:700;color:var(--color-warning);"><?php echo $stats['trial_companies']; ?></div>
        <div style="font-size:13px;color:var(--color-text-muted);"><?php echo __('In Trial'); ?></div>
    </div>
    <div class="card" style="text-align:center;padding:24px;">
        <div style="font-size:32px;font-weight:700;color:var(--color-danger);"><?php echo $stats['past_due_companies']; ?></div>
        <div style="font-size:13px;color:var(--color-text-muted);"><?php echo __('Past Due'); ?></div>
    </div>
    <div class="card" style="text-align:center;padding:24px;">
        <div style="font-size:32px;font-weight:700;"><?php echo $stats['total_users']; ?></div>
        <div style="font-size:13px;color:var(--color-text-muted);"><?php echo __('Total Users'); ?></div>
    </div>
    <div class="card" style="text-align:center;padding:24px;">
        <div style="font-size:32px;font-weight:700;"><?php echo $stats['total_leads']; ?></div>
        <div style="font-size:13px;color:var(--color-text-muted);"><?php echo __('Total Leads'); ?></div>
    </div>
</div>

<!-- Platform Settings & Super Admins -->
<div class="grid grid-2" style="gap:16px;margin-bottom:24px;">
    <!-- Platform Settings Card -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo __('Platform Settings'); ?></h3>
            <p style="font-size:12px;color:var(--color-text-muted);margin:4px 0 0;"><?php echo __('Global support email, super admin email, and marketing site URL.'); ?></p>
        </div>
        <div class="card-body">
            <form method="POST" action="super-admin.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="save_platform_settings">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label"><?php echo __('Site Name'); ?></label>
                        <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($platformSettings['site_name'] ?? 'White Label CRM'); ?>" placeholder="White Label CRM">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo __('Marketing Site URL'); ?></label>
                        <input type="url" name="marketing_url" class="form-control" value="<?php echo htmlspecialchars($platformSettings['marketing_url'] ?? ''); ?>" placeholder="https://funl.online">
                        <small style="color:var(--color-text-muted);font-size:11px;"><?php echo __('Logged-out users on the login page will be sent here instead of the register form.'); ?></small>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo __('Support Email'); ?></label>
                        <input type="email" name="support_email" class="form-control" value="<?php echo htmlspecialchars($platformSettings['platform_support_email'] ?? ''); ?>" placeholder="support@funl.online" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo __('Super Admin Email'); ?></label>
                        <input type="email" name="super_admin_email" class="form-control" value="<?php echo htmlspecialchars($platformSettings['platform_super_admin_email'] ?? ''); ?>" placeholder="admin@funl.online" required>
                    </div>
                </div>
                <div style="margin-top:16px;text-align:right;">
                    <button type="submit" class="btn btn-primary"><?php echo __('Save Platform Settings'); ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Super Admins Card -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?php echo __('Super Admins'); ?></h3>
            <p style="font-size:12px;color:var(--color-text-muted);margin:4px 0 0;"><?php echo __('Platform owners with full access to all tenants.'); ?></p>
        </div>
        <div class="card-body" style="padding:0;">
            <table class="data-table" style="width:100%;">
                <thead>
                    <tr>
                        <th><?php echo __('Name'); ?></th>
                        <th><?php echo __('Email'); ?></th>
                        <th style="text-align:right;"><?php echo __('Action'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($superAdmins as $sa): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($sa['full_name']); ?></strong>
                                <?php if ($sa['user_id'] == getCurrentUserId()): ?>
                                    <span class="badge badge-info" style="margin-left:6px;font-size:10px;"><?php echo __('You'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($sa['email']); ?></td>
                            <td style="text-align:right;">
                                <?php if ($sa['user_id'] != getCurrentUserId()): ?>
                                    <form method="POST" action="super-admin.php" style="display:inline;" onsubmit="return confirm('<?php echo __('Remove this super admin?'); ?>');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="action" value="delete_super_admin">
                                        <input type="hidden" name="user_id" value="<?php echo $sa['user_id']; ?>">
                                        <button type="submit" class="btn btn-xs btn-outline" style="color:#dc2626;"><?php echo __('Remove'); ?></button>
                                    </form>
                                <?php else: ?>
                                    <span style="color:var(--color-text-muted);font-size:12px;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="padding:16px;border-top:1px solid var(--color-border);">
                <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('addSuperAdminForm').style.display = document.getElementById('addSuperAdminForm').style.display === 'none' ? 'block' : 'none';">
                    + <?php echo __('Add Super Admin'); ?>
                </button>
                <form id="addSuperAdminForm" method="POST" action="super-admin.php" style="display:none;margin-top:16px;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="create_super_admin">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label"><?php echo __('Username'); ?></label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo __('Full Name'); ?></label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo __('Email'); ?></label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo __('Password (min 8 chars)'); ?></label>
                            <input type="password" name="password" class="form-control" minlength="8" required>
                        </div>
                    </div>
                    <div style="text-align:right;margin-top:8px;">
                        <button type="submit" class="btn btn-primary btn-sm"><?php echo __('Create Super Admin'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>



<!-- Network International / Mastercard Gateway Settings -->
<form method="POST" action="super-admin.php" style="margin-bottom:24px;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="save_platform_settings">
    <div class="card">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
            <div>
                <h3 class="card-title"><?php echo __('Payment Gateway (Network International)'); ?></h3>
                <p style="font-size:12px;color:var(--color-text-muted);margin:4px 0 0;"><?php echo __('Mastercard Hosted Checkout for subscription payments.'); ?></p>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
                <label style="font-size:13px;color:var(--color-text-secondary);display:flex;align-items:center;gap:6px;">
                    <input type="checkbox" name="ni_enabled" value="1" <?php echo ($platformSettings['ni_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
                    Enable NI Gateway
                </label>
            </div>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                <div class="form-group">
                    <label class="form-label"><?php echo __('Gateway URL'); ?></label>
                    <input type="url" name="ni_gateway_url" class="form-control" value="<?php echo htmlspecialchars($platformSettings['ni_gateway_url'] ?? 'https://test-network.mtf.gateway.mastercard.com/api'); ?>" placeholder="https://test-network.mtf.gateway.mastercard.com/api">
                    <small style="color:var(--color-text-muted);font-size:11px;">Test: test-network.mtf | Live: gateway.mastercard.com</small>
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('API Version'); ?></label>
                    <input type="text" name="ni_api_version" class="form-control" value="<?php echo htmlspecialchars($platformSettings['ni_api_version'] ?? '100'); ?>" placeholder="100">
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('Merchant ID'); ?></label>
                    <input type="text" name="ni_merchant_id" class="form-control" value="<?php echo htmlspecialchars($platformSettings['ni_merchant_id'] ?? ''); ?>" placeholder="test12122024">
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('API Username'); ?></label>
                    <input type="text" name="ni_api_username" class="form-control" value="<?php echo htmlspecialchars($platformSettings['ni_api_username'] ?? ''); ?>" placeholder="merchant.test12122024">
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('API Password'); ?></label>
                    <input type="password" name="ni_api_password" class="form-control" value="" placeholder="Leave blank to keep current">
                    <small style="color:var(--color-text-muted);font-size:11px;">Stored encrypted. Leave blank to keep current.</small>
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('Webhook Secret'); ?></label>
                    <input type="text" name="ni_webhook_secret" class="form-control" value="<?php echo htmlspecialchars($platformSettings['ni_webhook_secret'] ?? ''); ?>" placeholder="Optional — for webhook signature verification">
                    <small style="color:var(--color-text-muted);font-size:11px;">Used to verify NI Gateway webhook signatures. Get this from your NI dashboard.</small>
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;gap:8px;">
                    <button type="button" class="btn btn-outline" onclick="testNIGateway()" style="height:42px;">Test Connection</button>
                    <button type="submit" class="btn btn-primary" style="height:42px;">Save Gateway</button>
                </div>
            </div>
            <div style="margin-top:16px;padding:12px 16px;background:#f0f9ff;border-radius:8px;font-size:13px;color:#0369a1;">
                <strong>Test Credentials:</strong> Merchant: test12122024 | User: merchant.test12122024 | Pass: 0cb74bdcb05329641aa7bed1caff4e8a
            </div>
        </div>
    </div>
</form>


<!-- Companies Table -->
<div class="card">
    <div class="card-header"><h3 class="card-title"><?php echo __('All Companies'); ?></h3></div>
    <div class="card-body" style="padding:0;">
        <table class="table">
            <thead>
                <tr>
                    <th><?php echo __('Company'); ?></th>
                    <th><?php echo __('Plan'); ?></th>
                    <th><?php echo __('Status'); ?></th>
                    <th><?php echo __('Users'); ?></th>
                    <th><?php echo __('Leads'); ?></th>
                    <th><?php echo __('Created'); ?></th>
                    <th style="text-align:right;"><?php echo __('Actions'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($companies as $company): ?>
                <tr>
                    <td>
                        <a href="super-admin-company.php?id=<?= $company['company_id'] ?>" style="text-decoration:none;color:inherit;">
                            <strong style="color:var(--color-primary);"><?= htmlspecialchars($company['company_name']) ?> ↗</strong>
                        </a>
                        <div style="font-size:12px;color:var(--color-text-muted);">
                            <?= htmlspecialchars($company['email']) ?>
                            <?php if ($company['phone']): ?> &middot; <?= htmlspecialchars($company['phone']) ?><?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <span class="badge" style="background:var(--color-primary-light);color:var(--color-primary);">
                            <?php echo htmlspecialchars($company['plan_name'] ?? 'Unknown'); ?>
                        </span>
                        <div style="font-size:12px;color:var(--color-text-muted);">
                            $<?php echo number_format($company['plan_price_monthly'], 0); ?>/mo
                        </div>
                    </td>
                    <td>
                        <?php
                        $statusColors = [
                            'active' => ['bg' => '#d4edda', 'color' => '#155724'],
                            'trial' => ['bg' => '#fff3cd', 'color' => '#856404'],
                            'past_due' => ['bg' => '#f8d7da', 'color' => '#721c24'],
                            'cancelled' => ['bg' => '#e2e3e5', 'color' => '#383d41'],
                            'suspended' => ['bg' => '#f8d7da', 'color' => '#721c24'],
                        ];
                        $subStatus = $company['subscription_status'];
                        $colors = $statusColors[$subStatus] ?? $statusColors['active'];
                        ?>
                        <span class="badge" style="background:<?php echo $colors['bg']; ?>;color:<?php echo $colors['color']; ?>;">
                            <?php echo ucfirst($subStatus); ?>
                        </span>
                    </td>
                    <td><?php echo $company['user_count']; ?> / <?php echo $company['plan_user_limit']; ?></td>
                    <td><?php echo $company['lead_count']; ?></td>
                    <td><?php echo date('M j, Y', strtotime($company['created_at'])); ?></td>
                    <td style="text-align:right;">
                        <a href="/pages/super-admin-user-new.php?company_id=<?php echo $company['company_id']; ?>" class="btn btn-sm btn-outline">
                            <?php echo __('Add User'); ?>
                        </button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Suspend this company? Users will lose access.');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="update_company_status">
                            <input type="hidden" name="company_id" value="<?php echo $company['company_id']; ?>">
                            <input type="hidden" name="status" value="suspended">
                            <button type="submit" class="btn btn-sm btn-warning" title="<?php echo __('Suspend'); ?>"><?php echo __('Suspend'); ?></button>
                        </form>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this company and ALL its data? This cannot be undone.');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="delete_company">
                            <input type="hidden" name="company_id" value="<?php echo $company['company_id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger" title="<?php echo __('Delete'); ?>">&times;</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>


<script>
function testNIGateway() {
    const gatewayUrl = document.querySelector('[name="ni_gateway_url"]').value;
    const merchantId = document.querySelector('[name="ni_merchant_id"]').value;
    const apiUsername = document.querySelector('[name="ni_api_username"]').value;
    
    if (!merchantId || !apiUsername) {
        alert('Please fill in Merchant ID and API Username first.');
        return;
    }
    
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = 'Testing...';
    
    fetch('/api/ni-checkout.php?action=health')
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = 'Test Connection';
        if (data.configured) {
            alert('\u2713 NI Gateway is configured and reachable.');
        } else {
            alert('\u2717 NI Gateway not configured or not reachable: ' + (data.error || 'Check credentials'));
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.textContent = 'Test Connection';
        alert('Connection test failed: ' + err.message);
    });
}
</script>
<?php include '../includes/footer.php'; ?>
