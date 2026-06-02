<?php
/**
 * White Label CRM - Profile Page
 * Premium design, user details updates, password changes, and Microsoft OAuth email integration
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
requireLogin();

$pageTitle = 'My Profile';
$db = Database::getInstance();
$pdo = $db->getConnection();
$userId = getCurrentUserId();

$success = '';
$error = '';

// Load user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found");
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $fullName = sanitizeInput($_POST['full_name'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $language = sanitizeInput($_POST['language'] ?? 'en');
        
        if (empty($fullName) || empty($email)) {
            $error = 'Name and Email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } else {
            try {
                // Check if email is unique
                $stmtCheck = $pdo->prepare("SELECT 1 FROM users WHERE email = ? AND user_id != ?");
                $stmtCheck->execute([$email, $userId]);
                if ($stmtCheck->fetch()) {
                    $error = 'Email is already in use by another user.';
                } else {
                    $db->update('users', [
                        'full_name' => $fullName,
                        'phone' => $phone,
                        'email' => $email,
                        'username' => $email, // Keep username matching email in SaaS context
                        'language' => $language,
                    ], ['user_id' => $userId]);
                    
                    // Update session
                    $_SESSION['full_name'] = $fullName;
                    $_SESSION['username'] = $email;
                    $_SESSION['email'] = $email;
                    $_SESSION['language'] = $language;
                    
                    // Reload user data
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $success = 'Profile details updated successfully!';
                    logActivity($userId, 'Update Profile', 'User', $userId, 'Updated profile details');
                }
            } catch (Exception $e) {
                $error = 'Failed to update profile: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
            $error = 'All password fields are required.';
        } elseif (strlen($newPass) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($newPass !== $confirmPass) {
            $error = 'New passwords do not match.';
        } elseif (!password_verify($currentPass, $user['password_hash'])) {
            $error = 'Current password is incorrect.';
        } else {
            try {
                $newHash = password_hash($newPass, PASSWORD_BCRYPT);
                $db->update('users', ['password_hash' => $newHash], ['user_id' => $userId]);
                $success = 'Password changed successfully!';
                logActivity($userId, 'Change Password', 'User', $userId, 'Changed account password');
            } catch (Exception $e) {
                $error = 'Failed to change password: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'disconnect_microsoft') {
        try {
            $db->update('users', [
                'ms_access_token'    => null,
                'ms_refresh_token'   => null,
                'ms_token_expires'   => null,
                'ms_connected_email' => null,
            ], ['user_id' => $userId]);
            
            // Reload user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $success = 'Microsoft account disconnected successfully.';
            logActivity($userId, 'Disconnected Microsoft Email', 'User', $userId, 'Disconnected Office 365 OAuth');
        } catch (Exception $e) {
            $error = 'Failed to disconnect: ' . $e->getMessage();
        }
    }
}

// Generate Microsoft OAuth Authorization URL
$msConnected = !empty($user['ms_connected_email']);
$msAuthUrl = '';
if (defined('MS_CLIENT_ID') && MS_CLIENT_ID !== '') {
    $state = bin2hex(random_bytes(16));
    $_SESSION['ms_oauth_state'] = $state;
    $msAuthUrl = "https://login.microsoftonline.com/" . MS_TENANT_ID . "/oauth2/v2.0/authorize?" . http_build_query([
        'client_id'     => MS_CLIENT_ID,
        'response_type' => 'code',
        'redirect_uri'  => MS_REDIRECT_URI,
        'response_mode' => 'query',
        'scope'         => 'https://graph.microsoft.com/Mail.Send https://graph.microsoft.com/User.Read offline_access',
        'state'         => $state,
    ]);
}

// Get Oauth status message if redirected back
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

$csrfToken = generateCSRFToken();
include __DIR__ . '/../includes/header.php';
?>

<style>
.profile-container { max-width: 1000px; margin: 0 auto; padding: 0 32px 48px; }
.profile-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
@media (max-width: 800px) { .profile-grid { grid-template-columns: 1fr; } }
.badge-role { display: inline-block; padding: 4px 10px; border-radius: 50px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
.badge-role.admin { background: #fee2e2; color: #dc2626; }
.badge-role.manager { background: #f3e8ff; color: #7c3aed; }
.badge-role.rep { background: #dbeafe; color: #2563eb; }
.microsoft-box { display: flex; align-items: center; justify-content: space-between; padding: 16px; border: 1.5px solid #e5e7eb; border-radius: 12px; background: #fafafa; margin-bottom: 24px; }
.microsoft-icon { font-size: 24px; margin-right: 12px; }
.microsoft-status { display: flex; flex-direction: column; }
.microsoft-status h4 { margin: 0; font-size: 14px; font-weight: 700; color: #1f2937; }
.microsoft-status p { margin: 2px 0 0; font-size: 12px; color: #6b7280; }
</style>

<div class="profile-container">
    <div class="page-header">
        <h1 class="page-title"><?php echo __('my_profile'); ?></h1>
        <p class="page-subtitle"><?php echo __('profile_subtitle'); ?></p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success" style="margin-bottom:20px;"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom:20px;"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="profile-grid">
        <!-- Main Panel: Profile Details & Password -->
        <div style="display:flex;flex-direction:column;gap:24px;">
            <!-- Profile Card -->
            <div class="card">
                <div class="card-header"><h3 class="card-title"><?php echo __('profile_information'); ?></h3></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label class="form-label"><?php echo __('full_name'); ?> *</label>
                                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php echo __('email_address'); ?> *</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php echo __('phone_number'); ?></label>
                                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php echo __('username_readonly'); ?></label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php echo __('preferred_language'); ?></label>
                                <select name="language" class="form-control">
                                    <option value="en" <?php echo ($user['language'] ?? 'en') === 'en' ? 'selected' : ''; ?>><?php echo __('english'); ?></option>
                                    <option value="ar" <?php echo ($user['language'] ?? 'en') === 'ar' ? 'selected' : ''; ?>><?php echo __('arabic'); ?></option>
                                </select>
                            </div>
                        </div>

                        <div style="margin-top:20px;display:flex;justify-content:flex-end;">
                            <button type="submit" class="btn btn-primary"><?php echo __('save_changes'); ?></button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Password Card -->
            <div class="card">
                <div class="card-header"><h3 class="card-title"><?php echo __('security_password'); ?></h3></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label class="form-label"><?php echo __('current_password'); ?> *</label>
                            <input type="password" name="current_password" class="form-control" placeholder="••••••••" required>
                        </div>
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label class="form-label"><?php echo __('new_password'); ?> *</label>
                                <input type="password" name="new_password" class="form-control" placeholder="<?php echo __('min_8_characters'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?php echo __('confirm_new_password'); ?> *</label>
                                <input type="password" name="confirm_password" class="form-control" placeholder="<?php echo __('repeat_new_password'); ?>" required>
                            </div>
                        </div>

                        <div style="margin-top:20px;display:flex;justify-content:flex-end;">
                            <button type="submit" class="btn btn-primary"><?php echo __('change_password'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar Panel: Status & Integrations -->
        <div style="display:flex;flex-direction:column;gap:24px;">
            <!-- Account Status Card -->
            <div class="card">
                <div class="card-body" style="text-align:center;padding:32px 24px;">
                    <div style="width:72px;height:72px;border-radius:50%;background:#dd2d4a;color:white;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;margin:0 auto 16px;">
                        <?php 
                        $parts = explode(' ', $user['full_name']);
                        echo strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                        ?>
                    </div>
                    <h3 style="font-size:16px;font-weight:700;color:#1f2937;margin-bottom:4px;"><?php echo htmlspecialchars($user['full_name']); ?></h3>
                    <p style="font-size:13px;color:#6b7280;margin-bottom:16px;"><?php echo htmlspecialchars($user['email']); ?></p>
                    
                    <div style="display:flex;flex-direction:column;gap:10px;align-items:center;">
                        <div>
                            <?php 
                            $roleClass = 'rep';
                            if ($user['role'] === 'Admin') $roleClass = 'admin';
                            elseif ($user['role'] === 'Sales Manager') $roleClass = 'manager';
                            ?>
                            <span class="badge-role <?php echo $roleClass; ?>"><?php echo htmlspecialchars($user['role']); ?></span>
                        </div>
                        <div style="font-size:12px;color:#9ca3af;"><?php echo __('joined'); ?> <?php echo date('M j, Y', strtotime($user['created_at'])); ?></div>
                    </div>
                </div>
            </div>

            <!-- Microsoft Integration Card -->
            <div class="card">
                <div class="card-header"><h3 class="card-title"><?php echo __('email_integration'); ?></h3></div>
                <div class="card-body">
                    <p style="font-size:12px;color:#6b7280;margin-bottom:16px;"><?php echo __('connect_microsoft_desc'); ?></p>
                    
                    <?php if ($msConnected): ?>
                        <div class="microsoft-box">
                            <div style="display:flex;align-items:center;">
                                <span class="microsoft-icon">🌐</span>
                                <div class="microsoft-status">
                                    <h4><?php echo __('office_365_connected'); ?></h4>
                                    <p><?php echo htmlspecialchars($user['ms_connected_email']); ?></p>
                                </div>
                            </div>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="action" value="disconnect_microsoft">
                            <button type="submit" class="btn btn-outline btn-error btn-block"><?php echo __('disconnect_account'); ?></button>
                        </form>
                    <?php else: ?>
                        <div class="microsoft-box">
                            <div style="display:flex;align-items:center;">
                                <span class="microsoft-icon" style="opacity:0.5;">🌐</span>
                                <div class="microsoft-status">
                                    <h4><?php echo __('not_connected'); ?></h4>
                                    <p><?php echo __('connect_to_get_started'); ?></p>
                                </div>
                            </div>
                        </div>
                        <?php if ($msAuthUrl): ?>
                            <a href="<?php echo $msAuthUrl; ?>" class="btn btn-primary btn-block"><?php echo __('connect_office_365'); ?></a>
                        <?php else: ?>
                            <button class="btn btn-primary btn-block" disabled style="opacity:0.6;cursor:not-allowed;"><?php echo __('connect_office_365'); ?></button>
                            <div style="font-size:11px;color:#ef4444;margin-top:8px;text-align:center;"><?php echo __('client_id_not_configured'); ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
