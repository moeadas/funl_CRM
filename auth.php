<?php
/**
 * White Label CRM - Authentication & Security Functions
 * SiteGround compatible (PHP 7.4+ / 8.x)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/company-functions.php';

/**
 * Start secure session with hardened settings
 */
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                   || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', $isHttps ? 1 : 0);
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
        session_name(SESSION_NAME);
        session_start();
    }
    // Send security headers on every request
    sendSecurityHeaders();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Require login — redirect to login page if not authenticated
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
    // H-8: enforce password change for flagged users (skips super admins)
    requireNoPasswordChange();
    // B-6: enforce active subscription (block tenants whose trial expired
    // or whose subscription was cancelled)
    requireActiveSubscription();
}

/**
 * Check user role using hierarchy
 */
function hasRole($requiredRole) {
    if (!isLoggedIn()) return false;

    $roleHierarchy = [
        'Viewer'        => 1,
        'Sales Rep'     => 2,
        'Sales Manager' => 3,
        'Admin'         => 4,
    ];

    $userRole = $_SESSION['role'] ?? 'Viewer';
    $userLevel = $roleHierarchy[$userRole] ?? 0;

    if (is_array($requiredRole)) {
        foreach ($requiredRole as $role) {
            $requiredLevel = $roleHierarchy[$role] ?? 0;
            if ($userLevel >= $requiredLevel) return true;
        }
        return false;
    }

    $requiredLevel = $roleHierarchy[$requiredRole] ?? 0;
    return $userLevel >= $requiredLevel;
}

/**
 * Require specific role — die with JSON or redirect
 */
function requireRole($requiredRole) {
    if (!hasRole($requiredRole)) {
        if (isApiRequest()) {
            http_response_code(403);
            die(json_encode(['success' => false, 'message' => 'Access denied']));
        }
        http_response_code(403);
        die('Access denied. You do not have permission to view this page.');
    }
}

/**
 * Check if current user is Super Admin (platform owner)
 */
function isSuperAdmin() {
    return isLoggedIn() && !empty($_SESSION['is_super_admin']);
}

/**
 * Require Super Admin access
 */
function requireSuperAdmin() {
    if (!isSuperAdmin()) {
        if (isApiRequest()) {
            http_response_code(403);
            die(json_encode(['success' => false, 'message' => 'Super Admin access required']));
        }
        http_response_code(403);
        die('Access denied. Super Admin privileges required.');
    }
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Assert that the current user has a company_id in their session.
 * Sends a 403 JSON response and exits if not. Use at the top of tenant-scoped pages.
 * Note: getCurrentCompanyId() is defined in includes/company-functions.php
 */
function requireCompanyContext(): void {
    if (isSuperAdmin()) return; // super admin can browse anything
    if (!getCurrentCompanyId()) {
        if (isApiRequest()) {
            http_response_code(403);
            die(json_encode(['success' => false, 'message' => 'No tenant context. Please contact support.']));
        }
        http_response_code(403);
        die('No tenant context. Please sign in again.');
    }
}

/**
 * Assert the current user has verified their email. M-11 fix: unverified users
 * can no longer hit the API or most pages; they are redirected to verify-email.php.
 * Super admins bypass this check (they are seeded pre-verified).
 */
function requireEmailVerified(): void {
    if (isSuperAdmin()) return;
    $verified = $_SESSION['email_verified'] ?? false;
    if ($verified) return;
    if (isApiRequest()) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Please verify your email address first.', 'redirect' => '/verify-email.php']));
    }
    header('Location: /verify-email.php');
    exit;
}

/**
 * H-8 fix: force a password change for users flagged with must_change_password.
 * Skips for super admins (they manage the system, not a tenant).
 * Sends the user to /pages/profile.php?must_change=1, where the change-password
 * form is the only thing allowed until the password is rotated.
 */
function requireNoPasswordChange(): void {
    if (isSuperAdmin()) return;
    if (empty($_SESSION['must_change_password'])) return;
    // Allow the change-password page itself + logout endpoints
    $allowed = [
        '/pages/profile.php',
        '/logout.php',
    ];
    $current = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($current, PHP_URL_PATH) ?? '';
    foreach ($allowed as $a) {
        if (strpos($path, $a) !== false) return;
    }
    if (isApiRequest()) {
        http_response_code(403);
        die(json_encode([
            'success' => false,
            'message' => 'You must change your password before continuing.',
            'redirect' => '/pages/profile.php?must_change=1',
        ]));
    }
    header('Location: /pages/profile.php?must_change=1');
    exit;
}

/**
 * B-6 / Trial Enforcement:
 *  Block tenant users when their trial has expired or subscription is
 *  cancelled/past_due. Super admins always pass.
 *
 *  Allows through:
 *   - 'active' subscription
 *   - 'trial' with trial_ends_at > now
 *   - access to /pages/billing.php or /logout.php (so they can pay)
 *   - access to /pages/profile.php (so they can change password)
 *   - access to any /api/ endpoint (so payment APIs can be called)
 */
function requireActiveSubscription(): void {
    if (isSuperAdmin()) return;
    if (empty($_SESSION['user_id'])) return;
    if (empty($_SESSION['company_id'])) return;
    
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT company_id, status, subscription_status, trial_ends_at,
                   current_period_end, cancel_at_period_end
            FROM companies WHERE company_id = ?
        ");
        $stmt->execute([(int)$_SESSION['company_id']]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company) return;
        
        // Company suspended by super admin
        if ($company['status'] !== 'active') {
            $current = $_SERVER['REQUEST_URI'] ?? '';
            $isApiRequest = (strpos($current, '/api/') !== false);
            if ($isApiRequest) {
                http_response_code(403);
                die(json_encode(['success' => false, 'message' => 'Account suspended. Contact support.']));
            }
            header('Location: /pages/billing.php?suspended=1');
            exit;
        }
        
        $subStatus = $company['subscription_status'] ?? 'trial';
        $now = time();
        
        // Trial check
        $trialExpired = false;
        if ($subStatus === 'trial' && $company['trial_ends_at']) {
            if (strtotime($company['trial_ends_at']) < $now) {
                $trialExpired = true;
            }
        }
        
        // Active subscription check
        $hasActiveSubscription = ($subStatus === 'active' && 
                                  (!$company['current_period_end'] || strtotime($company['current_period_end']) > $now));
        
        // Allow access if:
        //   1. Has active subscription, OR
        //   2. Still in trial (not expired), OR
        //   3. Past_due (grace period - allow access to fix payment)
        $isOk = $hasActiveSubscription || 
                ($subStatus === 'trial' && !$trialExpired) || 
                $subStatus === 'past_due';
        
        if ($isOk) return;
        
        // Subscription required - redirect to billing
        $current = $_SERVER['REQUEST_URI'] ?? '';
        $isApiRequest = (strpos($current, '/api/') !== false);
        $isBilling = strpos($current, '/pages/billing.php') !== false;
        $isLogout = strpos($current, '/logout.php') !== false;
        $isProfile = strpos($current, '/pages/profile.php') !== false;
        $isLogin = strpos($current, '/login.php') !== false;
        $isRegister = strpos($current, '/register.php') !== false;
        
        // Always allow: billing page, logout, profile, login, register
        if ($isBilling || $isLogout || $isProfile || $isLogin || $isRegister) {
            return;
        }
        
        if ($isApiRequest) {
            http_response_code(402);
            die(json_encode([
                'success' => false,
                'message' => $trialExpired ? 'Trial period expired. Please subscribe to continue.' : 'Subscription required.',
                'redirect' => '/pages/billing.php' . ($trialExpired ? '?expired=1' : ''),
            ]));
        }
        
        header('Location: /pages/billing.php' . ($trialExpired ? '?expired=1' : ''));
        exit;
        
    } catch (Exception $e) {
        // Never block login on a metadata check failure
        error_log('requireActiveSubscription check failed: ' . $e->getMessage());
    }
}

/**
 * Authenticate user with rate limiting
 */
function authenticateUser($username, $password) {
    // Use IP-based rate limiting (from security.php) instead of session-based
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if ($clientIp !== '127.0.0.1' && $clientIp !== '::1' && !rateLimit('login_' . $clientIp, 5, 900, $clientIp)) {
        return false; // Too many attempts
    }

    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("
        SELECT user_id, username, email, password_hash, full_name, role, status, company_id, is_super_admin, email_verified, language, must_change_password
        FROM users 
        WHERE (username = :username OR email = :email) AND status = 'Active'
    ");
    $stmt->execute(['username' => $username, 'email' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);

        // Update last login
        $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
        $updateStmt->execute([$user['user_id']]);

        // Set session variables
        $_SESSION['user_id']        = $user['user_id'];
        $_SESSION['username']       = $user['username'];
        $_SESSION['email']          = $user['email'];
        $_SESSION['full_name']      = $user['full_name'];
        $_SESSION['role']           = $user['role'];
        $_SESSION['is_super_admin']  = !empty($user['is_super_admin']);
        $_SESSION['email_verified'] = !empty($user['email_verified']);
        $_SESSION['language']       = $user['language'] ?? 'en';
        
        // Set company_id for multi-tenant support
        if (!empty($user['company_id'])) {
            $_SESSION['company_id'] = $user['company_id'];
        }

        // H-8: flag forced password change (set by admin or by seed default-cred migration)
        $_SESSION['must_change_password'] = !empty($user['must_change_password']);

        logActivity($user['user_id'], 'Login', 'System', null, 'User logged in');
        return true;
    }

    return false;
}

/**
 * Logout user
 */
function logoutUser() {
    if (isLoggedIn()) {
        logActivity($_SESSION['user_id'], 'Logout', 'System', null, 'User logged out');
    }
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header('Location: /login.php');
    exit;
}

/**
 * Hash password (bcrypt)
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Log activity
 */
function logActivity($userId, $action, string $entityType, ?int $entityId = null, ?string $details = null) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO activity_log (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $stmt->execute([
            $userId,
            $action,
            $entityType,
            $entityId,
            $details,
            $ipAddress,
            $userAgent ? substr($userAgent, 0, 255) : null,
        ]);
    } catch (Exception $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
}

/**
 * Get current user info from session
 */
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return [
        'user_id'    => $_SESSION['user_id'],
        'username'   => $_SESSION['username'],
        'email'      => $_SESSION['email'],
        'full_name'  => $_SESSION['full_name'],
        'role'       => $_SESSION['role'],
        'company_id' => $_SESSION['company_id'] ?? null,
        'is_super_admin' => !empty($_SESSION['is_super_admin']),
    ];
}

/**
 * Sanitize input
 *
 * M-5 fix: previously used strip_tags() which silently mangles legitimate
 * content like "price < 100" (becomes "price  100"). Output is already
 * escaped with htmlspecialchars() at render time, so storage only needs
 * to normalize whitespace.
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    // Trim + collapse runs of whitespace; never modify the characters.
    // htmlspecialchars() must be applied at OUTPUT time, not storage.
    if ($data === null) return '';
    return trim(preg_replace('/\s+/', ' ', (string)$data));
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Generate CSRF token (per-session, rotated on login)
 */
function generateCSRFToken() {
    // Rotate CSRF token every 2 hours or on every request
    $rotate = true;
    if (!empty($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time']) < 7200) {
        $rotate = false;
    }
    if (empty($_SESSION['csrf_token']) || $rotate) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token with expiry check (2 hour max)
 */
function verifyCSRFTokenWithExpiry(?string $token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? null;
        if ($token === null) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            $token = $data['csrf_token'] ?? null;
        }
    }
    if (!isset($_SESSION['csrf_token']) || $token === null) return false;
    
    // Check expiry
    if (!empty($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time']) > 7200) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Verify CSRF token — works for both POST forms and JSON API requests
 * (backward-compatible: does NOT check expiry)
 */
function verifyCSRFToken(?string $token = null) {
    if ($token === null) {
        // Try POST, then JSON body
        $token = $_POST['csrf_token'] ?? null;
        if ($token === null) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            $token = $data['csrf_token'] ?? null;
        }
    }
    return isset($_SESSION['csrf_token']) && $token !== null && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Require valid CSRF — die if invalid
 * Uses expiry-check version
 */
function requireCSRF() {
    if (!verifyCSRFTokenWithExpiry()) {
        if (isApiRequest()) {
            http_response_code(403);
            die(json_encode(['success' => false, 'message' => 'Invalid or expired request token. Please refresh the page.']));
        }
        http_response_code(403);
        die('Invalid request token. Please go back and try again.');
    }
}

/**
 * Output a hidden CSRF input field
 */
function csrfField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Check if current request is an API/JSON request
 */
function isApiRequest() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return (stripos($contentType, 'application/json') !== false)
        || (stripos($accept, 'application/json') !== false)
        || (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false);
}

/**
 * Check if admin is currently impersonating another user
 */
function isImpersonating() {
    return !empty($_SESSION['impersonate_original_user_id']);
}

/**
 * Get the original admin info when impersonating
 */
function getOriginalAdmin() {
    if (!isImpersonating()) return null;
    return [
        'user_id'   => $_SESSION['impersonate_original_user_id'],
        'username'  => $_SESSION['impersonate_original_username'],
        'full_name' => $_SESSION['impersonate_original_full_name'],
        'role'      => $_SESSION['impersonate_original_role'],
    ];
}

/**
 * Switch to another user (admin-only impersonation)
 */
function switchToUser($targetUserId) {
    if (!hasRole('Admin')) {
        return ['success' => false, 'message' => 'Only admins can switch users.'];
    }

    $db = Database::getInstance()->getConnection();
    // Super admins can switch to anyone; regular admins can only switch within their own company
    if (isSuperAdmin()) {
        $stmt = $db->prepare("SELECT user_id, username, email, full_name, role, company_id, is_super_admin, language FROM users WHERE user_id = ? AND status = 'Active'");
        $stmt->execute([$targetUserId]);
    } else {
        $myCompanyId = $_SESSION['company_id'] ?? null;
        if (!$myCompanyId) {
            return ['success' => false, 'message' => 'No tenant context for switch.'];
        }
        $stmt = $db->prepare("SELECT user_id, username, email, full_name, role, company_id, is_super_admin, language FROM users WHERE user_id = ? AND status = 'Active' AND company_id = ?");
        $stmt->execute([$targetUserId, $myCompanyId]);
    }
    $target = $stmt->fetch();

    if (!$target) {
        return ['success' => false, 'message' => 'User not found or inactive.'];
    }

    // Save original admin session (only if not already impersonating)
    if (!isImpersonating()) {
        $_SESSION['impersonate_original_user_id']   = $_SESSION['user_id'];
        $_SESSION['impersonate_original_username']   = $_SESSION['username'];
        $_SESSION['impersonate_original_email']      = $_SESSION['email'];
        $_SESSION['impersonate_original_full_name']  = $_SESSION['full_name'];
        $_SESSION['impersonate_original_role']       = $_SESSION['role'];
        $_SESSION['impersonate_original_company_id'] = $_SESSION['company_id'] ?? null;
        $_SESSION['impersonate_original_is_super_admin'] = $_SESSION['is_super_admin'] ?? false;
        $_SESSION['impersonate_original_language']   = $_SESSION['language'] ?? 'en';
    }

    // Switch session to target user
    $_SESSION['user_id']   = $target['user_id'];
    $_SESSION['username']  = $target['username'];
    $_SESSION['email']     = $target['email'];
    $_SESSION['full_name'] = $target['full_name'];
    $_SESSION['role']      = $target['role'];
    $_SESSION['company_id'] = $target['company_id'] ?? null;
    $_SESSION['language']   = $target['language'] ?? 'en';
    // Preserve super_admin status only for actual super admins
    if (empty($target['is_super_admin'])) {
        $_SESSION['is_super_admin'] = false;
    }

    logActivity($_SESSION['impersonate_original_user_id'], 'Switch User', 'User', $target['user_id'],
        "Admin switched to user: {$target['full_name']} ({$target['username']})");

    return ['success' => true, 'message' => "Switched to {$target['full_name']}"];
}

/**
 * Switch back to original admin account
 */
function switchBack() {
    if (!isImpersonating()) {
        return ['success' => false, 'message' => 'Not currently impersonating any user.'];
    }

    $targetName = $_SESSION['full_name'];

    // Restore original admin session
    $_SESSION['user_id']   = $_SESSION['impersonate_original_user_id'];
    $_SESSION['username']  = $_SESSION['impersonate_original_username'];
    $_SESSION['email']     = $_SESSION['impersonate_original_email'];
    $_SESSION['full_name'] = $_SESSION['impersonate_original_full_name'];
    $_SESSION['role']      = $_SESSION['impersonate_original_role'];
    $_SESSION['company_id'] = $_SESSION['impersonate_original_company_id'] ?? null;
    $_SESSION['is_super_admin'] = $_SESSION['impersonate_original_is_super_admin'] ?? false;
    $_SESSION['language']   = $_SESSION['impersonate_original_language'] ?? 'en';

    // Clear impersonation data
    unset(
        $_SESSION['impersonate_original_user_id'],
        $_SESSION['impersonate_original_username'],
        $_SESSION['impersonate_original_email'],
        $_SESSION['impersonate_original_full_name'],
        $_SESSION['impersonate_original_role'],
        $_SESSION['impersonate_original_company_id'],
        $_SESSION['impersonate_original_is_super_admin'],
        $_SESSION['impersonate_original_language']
    );

    logActivity($_SESSION['user_id'], 'Switch Back', 'User', null,
        "Admin switched back from user: $targetName");

    return ['success' => true, 'message' => "Switched back to {$_SESSION['full_name']}"];
}

/**
 * Scope a query by company_id for tenant isolation
 * @param string $table
 * @param int|null $companyId
 * @return string SQL snippet
 */
function scopeByCompany(string $table, ?int $companyId = null) {
    if ($companyId === null) {
        $companyId = $_SESSION['company_id'] ?? null;
    }
    if ($companyId === null) {
        return ''; // No scoping if not multi-tenant
    }
    return " AND {$table}.company_id = " . (int)$companyId;
}