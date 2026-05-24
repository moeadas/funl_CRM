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
$pageTitle = 'Super Admin';
include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Platform Administration</h1>
        <p style="font-size:13px;color:var(--color-text-secondary);margin-top:4px;">Manage all tenants, subscriptions, and users.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="openCreateCompanyModal()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Company
    </button>
</div>

<!-- Stats Cards -->
<div class="grid grid-3" style="gap:16px;margin-bottom:24px;">
    <div class="card" style="text-align:center;padding:24px;">
        <div style="font-size:32px;font-weight:700;color:var(--color-primary);"><?php echo $stats['total_companies']; ?></div>
        <div style="font-size:13px;color:var(--color-text-muted);">Total Companies</div>
    </div>
    <div class="card" style="text-align:center;padding:24px;">
        <div style="font-size:32px;font-weight:700;color:var(--color-success);"><?php echo $stats['active_companies']; ?></div>
        <div style="font-size:13px;color:var(--color-text-muted);">Active</div>
    </div>
    <div class="card" style="text-align:center;padding:24px;">
        <div style="font-size:32px;font-weight:700;color:var(--color-warning);"><?php echo $stats['trial_companies']; ?></div>
        <div style="font-size:13px;color:var(--color-text-muted);">In Trial</div>
    </div>
    <div class="card" style="text-align:center;padding:24px;">
        <div style="font-size:32px;font-weight:700;color:var(--color-danger);"><?php echo $stats['past_due_companies']; ?></div>
        <div style="font-size:13px;color:var(--color-text-muted);">Past Due</div>
    </div>
    <div class="card" style="text-align:center;padding:24px;">
        <div style="font-size:32px;font-weight:700;"><?php echo $stats['total_users']; ?></div>
        <div style="font-size:13px;color:var(--color-text-muted);">Total Users</div>
    </div>
    <div class="card" style="text-align:center;padding:24px;">
        <div style="font-size:32px;font-weight:700;"><?php echo $stats['total_leads']; ?></div>
        <div style="font-size:13px;color:var(--color-text-muted);">Total Leads</div>
    </div>
</div>

<!-- Companies Table -->
<div class="card">
    <div class="card-header"><h3 class="card-title">All Companies</h3></div>
    <div class="card-body" style="padding:0;">
        <table class="table">
            <thead>
                <tr>
                    <th>Company</th>
                    <th>Plan</th>
                    <th>Status</th>
                    <th>Users</th>
                    <th>Leads</th>
                    <th>Created</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($companies as $company): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($company['company_name']); ?></strong>
                        <div style="font-size:12px;color:var(--color-text-muted);">
                            <?php echo htmlspecialchars($company['email']); ?>
                            <?php if ($company['phone']): ?> &middot; <?php echo htmlspecialchars($company['phone']); endif; ?>
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
                        <button type="button" class="btn btn-sm btn-outline" onclick="openAddUserModal(<?php echo $company['company_id']; ?>, '<?php echo htmlspecialchars($company['company_name']); ?>')">
                            Add User
                        </button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Suspend this company? Users will lose access.');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="update_company_status">
                            <input type="hidden" name="company_id" value="<?php echo $company['company_id']; ?>">
                            <input type="hidden" name="status" value="suspended">
                            <button type="submit" class="btn btn-sm btn-warning" title="Suspend">Suspend</button>
                        </form>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this company and ALL its data? This cannot be undone.');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="delete_company">
                            <input type="hidden" name="company_id" value="<?php echo $company['company_id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger" title="Delete">&times;</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Company Modal -->
<div id="createCompanyModal" class="modal" style="display:none;">
    <div class="modal-backdrop" onclick="closeCreateCompanyModal()"></div>
    <div class="modal-content" style="max-width:600px;">
        <div class="modal-header">
            <h3 class="modal-title">Create New Company</h3>
            <button type="button" class="btn-close" onclick="closeCreateCompanyModal()">&times;</button>
        </div>
        <form method="POST" class="modal-body">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="create_company">
            
            <h4 style="margin-bottom:16px;">Company Info</h4>
            <div class="grid grid-2" style="gap:16px;">
                <div class="form-group">
                    <label class="form-label">Company Name *</label>
                    <input type="text" name="company_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Company Email *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="tel" name="phone" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Plan *</label>
                    <select name="plan" class="form-control">
                        <?php foreach ($plans as $plan): ?>
                            <option value="<?php echo $plan['plan_key']; ?>">
                                <?php echo htmlspecialchars($plan['plan_name']); ?> - $<?php echo number_format($plan['monthly_price'], 0); ?>/mo
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <h4 style="margin:24px 0 16px;">Admin User</h4>
            <div class="grid grid-2" style="gap:16px;">
                <div class="form-group">
                    <label class="form-label">Admin Name *</label>
                    <input type="text" name="admin_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Admin Email *</label>
                    <input type="email" name="admin_email" class="form-control" required>
                </div>
                <div class="form-group" style="grid-column:1/-1;">
                    <label class="form-label">Password *</label>
                    <input type="password" name="admin_password" class="form-control" required>
                </div>
            </div>
            
            <div class="form-actions" style="display:flex;justify-content:flex-end;gap:8px;margin-top:24px;">
                <button type="button" class="btn btn-outline" onclick="closeCreateCompanyModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Company</button>
            </div>
        </form>
    </div>
</div>

<!-- Add User to Company Modal -->
<div id="addUserModal" class="modal" style="display:none;">
    <div class="modal-backdrop" onclick="closeAddUserModal()"></div>
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h3 class="modal-title">Add User to <span id="modalCompanyName"></span></h3>
            <button type="button" class="btn-close" onclick="closeAddUserModal()">&times;</button>
        </div>
        <form method="POST" class="modal-body">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="create_user_for_company">
            <input type="hidden" name="company_id" id="modalCompanyId">
            
            <div class="grid grid-2" style="gap:16px;">
                <div class="form-group">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Role *</label>
                    <select name="role" class="form-control">
                        <option value="Admin">Admin</option>
                        <option value="Sales Manager">Sales Manager</option>
                        <option value="Sales Rep" selected>Sales Rep</option>
                        <option value="Viewer">Viewer</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column:1/-1;">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
            </div>
            
            <div class="form-actions" style="display:flex;justify-content:flex-end;gap:8px;margin-top:24px;">
                <button type="button" class="btn btn-outline" onclick="closeAddUserModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Add User</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateCompanyModal() {
    document.getElementById('createCompanyModal').style.display = 'flex';
}
function closeCreateCompanyModal() {
    document.getElementById('createCompanyModal').style.display = 'none';
}
function openAddUserModal(companyId, companyName) {
    document.getElementById('modalCompanyId').value = companyId;
    document.getElementById('modalCompanyName').textContent = companyName;
    document.getElementById('addUserModal').style.display = 'flex';
}
function closeAddUserModal() {
    document.getElementById('addUserModal').style.display = 'none';
}
</script>

<?php include '../includes/footer.php'; ?>