<?php
/**
 * FUNL CRM - Registration Landing Page
 * SaaS signup with plan selection - ForceManager-inspired design
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/company-functions.php';
require_once __DIR__ . '/includes/resend-email.php';

startSecureSession();

// Do not force redirect logged-in users so they can access/review the registration page

$error = '';
$csrf_token = generateCSRFToken();

// Registration handler
$post = $_POST ?? [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Bypass CSRF check for public signup form submissions (e.g. WordPress integrations)
    if ($_POST['action'] !== 'register_company') {
        requireCSRF();
    }

    // H-1 mitigation: rate-limit public signups by IP. 5 per hour per IP.
    if ($_POST['action'] === 'register_company') {
        // H-1 honeypot: real users don't see/fill the hidden website_url field.
        // If it's non-empty, treat as a bot and silently fake success.
        if (!empty($_POST['website_url'])) {
            error_log('register honeypot triggered from IP ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
            $_SESSION['success'] = 'Account created! Check your email to verify.';
            header('Location: /register.php');
            exit;
        }

        $rateDir = sys_get_temp_dir() . '/wlrm_rate';
        if (!is_dir($rateDir)) @mkdir($rateDir, 0755, true);
        $clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rateKey  = 'register_' . preg_replace('/[^a-f0-9.:]/i', '', $clientIp);
        $rateFile = $rateDir . '/' . $rateKey . '.json';
        $now = time();
        $hour = $now - 3600;
        $attempts = [];
        if (file_exists($rateFile)) {
            $attempts = json_decode(file_get_contents($rateFile), true) ?: [];
        }
        $attempts = array_filter($attempts, fn($t) => $t > $hour);
        if (count($attempts) >= 3) {
            $error = 'Too many signups from your IP. Please try again in an hour.';
        } else {
            $attempts[] = $now;
            @file_put_contents($rateFile, json_encode(array_values($attempts)));
        }
    }

    if ($_POST['action'] === 'register_company') {
        $companyName = sanitizeInput($_POST['company_name'] ?? '');
        $companySlug = preg_replace('/[^a-z0-9-]/', '', strtolower(str_replace(' ', '-', $companyName)));
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $fullName = sanitizeInput($_POST['full_name'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $planKey = sanitizeInput($_POST['plan'] ?? 'single');
        $signupMode = sanitizeInput($_POST['signup_mode'] ?? 'trial');

        if (empty($companyName) || empty($email) || empty($password) || empty($fullName)) {
            $error = __('All fields are required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = __('Please enter a valid email address.');
        } elseif (function_exists('isDisposableEmail') && isDisposableEmail($email)) {
            $error = __('Please use a permanent email address. Temporary email services are not allowed.');
        } elseif (strlen($password) < 8) {
            $error = __('Password must be at least 8 characters.');
        } elseif (function_exists('validatePasswordStrength')) {
            // validatePasswordStrength returns array of errors (empty if OK).
            // BUGFIX: previously used !validatePasswordStrength() which is inverted
            // (empty array is falsy, so !empty was true → wrong error always shown).
            $pwErrors = validatePasswordStrength($password);
            if (!empty($pwErrors)) {
                // Show the specific reason(s) the password failed validation.
                $error = implode(' ', $pwErrors);
            }
        }
        if (empty($error) && $password !== $confirmPassword) {
            $error = __('Passwords do not match.');
        }
        if (empty($error)) {
            try {
                $db = Database::getInstance();

                $existing = $db->query("SELECT 1 FROM companies WHERE email = ? OR company_slug = ?", [$email, $companySlug])->fetch();
                if ($existing) {
                    $error = __('A company with this email or name already exists.');
                    } else {
                        // Also check if email already exists in users table (could be from another company)
                        $existingUser = $db->query("SELECT 1 FROM users WHERE email = ?", [$email])->fetch();
                        if ($existingUser) {
                            $error = __('This email address is already registered. Please use a different email or sign in.');
                        } else {
                            $plan = getPlan($planKey);
                            if (!$plan) $plan = getPlan('single');

                    // signup_mode: 'trial' gives 14 days free; 'subscribe' requires immediate payment
                    $isTrialMode = ($signupMode !== 'subscribe');
                    $trialEnds = $isTrialMode ? date('Y-m-d H:i:s', strtotime('+14 days')) : null;
                    $initialSubStatus = $isTrialMode ? 'trial' : 'pending_payment';
                    $companyId = $db->insert('companies', [
                        'company_name' => $companyName,
                        'company_slug' => $companySlug,
                        'email' => $email,
                        'phone' => $phone,
                        'status' => 'active',
                        'trial_ends_at' => $trialEnds,
                        'subscription_status' => $initialSubStatus,
                        'plan_id' => $planKey,
                        'plan_name' => $plan['plan_name'] ?? 'Single User',
                        'plan_user_limit' => $plan['user_limit'] ?? 1,
                        'plan_price_monthly' => $plan['monthly_price'] ?? 10,
                        'extra_user_price' => $plan['extra_user_price'] ?? 0,
                    ]);

                    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                    $userId = $db->insert('users', [
                        'company_id' => $companyId,
                        'username' => $email,
                        'email' => $email,
                        'password_hash' => $passwordHash,
                        'full_name' => $fullName,
                        'role' => 'Admin',
                        'phone' => $phone,
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
                        $db->query(
                            "INSERT INTO settings (company_id, setting_key, setting_value, setting_type)
                             VALUES (?, ?, ?, 'text')
                             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                            [$companyId, $key, $value]
                        );
                    }

                    // Create a verification token and store it
                    $verifyToken = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                    $db->query(
                        "INSERT INTO email_verifications (user_id, email, token, expires_at)
                         VALUES (?, ?, ?, ?)",
                        [$userId, $email, $verifyToken, $expires]
                    );
                    $emailSent = sendVerificationEmail($email, $fullName, $verifyToken);
                    
                    // Set email as NOT verified (must verify before full access)
                    $db->query("UPDATE users SET email_verified = 0 WHERE user_id = ?", [$userId]);
                    
                    // Track if email was actually delivered
                    $_SESSION['verification_email_sent'] = ($emailSent !== false);

                    // Set session but mark as unverified
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['username'] = $email;
                    $_SESSION['email'] = $email;
                    $_SESSION['full_name'] = $fullName;
                    $_SESSION['role'] = 'Admin';
                    $_SESSION['company_id'] = $companyId;
                    $_SESSION['email_verified'] = false;
                    
                    // If subscribe mode, store pending subscription for after email verification
                    if ($signupMode === 'subscribe') {
                        $_SESSION['pending_subscription'] = [
                            'plan_key' => $planKey,
                            'company_id' => $companyId,
                            'user_id' => $userId,
                        ];
                    }
                    
                    // Redirect to verification page
                    header('Location: /verify-email.php');
                    exit;
                }
                }
            } catch (Exception $e) {
                $error = 'Registration failed: ' . $e->getMessage();
            }
        }
    }
}

$plans = getActivePlans();

// Determine default selected plan from GET or POST parameter
$defaultPlan = sanitizeInput($_POST['plan'] ?? $_GET['plan'] ?? 'single');
$validPlans = ['single', 'team', 'enterprise'];
if (!in_array($defaultPlan, $validPlans)) {
    $defaultPlan = 'single';
}

// Find details of the selected plan
$selectedPlan = null;
foreach ($plans as $plan) {
    if ($plan['plan_key'] === $defaultPlan) {
        $selectedPlan = $plan;
        break;
    }
}
if (!$selectedPlan && !empty($plans)) {
    $selectedPlan = $plans[0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('Create Your Account'); ?> - <?php echo htmlspecialchars(getAppName()); ?></title>
    <link rel="icon" type="image/png" href="<?php echo getCompanyFavicon(); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        /* ───────────────────────────────────────────────
           Modern Registration Page
           App design tokens (matches /assets/css/style.css)
           ─────────────────────────────────────────────── */
        body {
            background: #f5f5f7;
            font-family: 'Inter', var(--font-family);
            -webkit-font-smoothing: antialiased;
        }

        /* Top nav */
        .reg-nav {
            position: sticky;
            top: 0;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: saturate(180%) blur(20px);
            -webkit-backdrop-filter: saturate(180%) blur(20px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
            z-index: 50;
        }
        .reg-nav-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .reg-nav-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 17px;
            color: var(--color-text);
            text-decoration: none;
        }
        .reg-nav-logo img { height: 30px; width: auto; }
        .reg-nav-links { display: flex; align-items: center; gap: 20px; }
        .reg-nav-link {
            color: var(--color-text-secondary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color .2s;
        }
        .reg-nav-link:hover { color: var(--color-accent); }
        .reg-nav-signin {
            background: var(--color-accent);
            color: white !important;
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all .2s;
        }
        .reg-nav-signin:hover { background: var(--color-accent-hover); transform: translateY(-1px); }

        /* Layout */
        .reg-container {
            max-width: 1080px;
            margin: 0 auto;
            padding: 48px 24px 80px;
        }
        .reg-header { text-align: center; margin-bottom: 32px; }
        .reg-header h1 {
            font-size: 36px;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--color-text);
            margin-bottom: 8px;
        }
        .reg-header p {
            color: var(--color-text-secondary);
            font-size: 17px;
        }
        .reg-layout {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 32px;
            align-items: start;
        }
        @media (max-width: 900px) {
            .reg-layout { grid-template-columns: 1fr; gap: 24px; }
            .reg-summary { position: relative !important; top: 0 !important; }
        }

        /* Card base */
        .reg-card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: 28px;
            box-shadow: var(--shadow-xs);
        }

        /* ── Left: Plan Summary Card ─────────────────── */
        .reg-summary { position: sticky; top: 80px; }
        .reg-summary-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--color-accent);
            margin-bottom: 8px;
        }
        .reg-plan-name {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--color-text);
            line-height: 1.1;
            margin-bottom: 4px;
        }
        .reg-plan-desc {
            font-size: 14px;
            color: var(--color-text-secondary);
            line-height: 1.5;
            margin-bottom: 20px;
        }
        .reg-plan-price-row {
            display: flex;
            align-items: baseline;
            gap: 6px;
            margin-bottom: 4px;
        }
        .reg-plan-price {
            font-size: 48px;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: var(--color-text);
            line-height: 1;
        }
        .reg-plan-period {
            font-size: 15px;
            color: var(--color-text-secondary);
            font-weight: 500;
        }
        .reg-plan-billing-note {
            font-size: 13px;
            color: var(--color-text-secondary);
            margin-bottom: 20px;
        }
        .reg-plan-save {
            background: linear-gradient(135deg, #e8f4fd 0%, #f0e8fd 100%);
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 13px;
            color: #1d4d80;
            line-height: 1.4;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        .reg-plan-save strong { color: #0d3a6e; }

        .reg-divider {
            border: none;
            border-top: 1px solid var(--color-border);
            margin: 22px 0;
        }

        .reg-features { list-style: none; padding: 0; margin: 0; }
        .reg-features li {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 7px 0;
            font-size: 14px;
            color: var(--color-text);
        }
        .reg-features li svg {
            flex-shrink: 0;
            color: var(--color-success);
        }

        .reg-trust {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .reg-trust-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: var(--color-text-secondary);
        }
        .reg-trust-item svg { color: var(--color-text-tertiary); flex-shrink: 0; }

        /* ── Right: Form Card ───────────────────────── */
        .reg-form-card { padding: 36px 32px; }
        .reg-form-title {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.01em;
            margin-bottom: 4px;
        }
        .reg-form-subtitle {
            color: var(--color-text-secondary);
            font-size: 14px;
            margin-bottom: 24px;
        }

        /* Plan picker */
        .reg-section-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--color-text);
            margin: 0 0 10px;
        }
        .reg-plan-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 22px;
        }
        .reg-plan-card {
            display: block;
            cursor: pointer;
            position: relative;
        }
        .reg-plan-card input { position: absolute; opacity: 0; pointer-events: none; }
        .reg-plan-card-inner {
            border: 1.5px solid var(--color-border);
            border-radius: 10px;
            padding: 12px;
            text-align: center;
            transition: all .15s ease;
            background: var(--color-surface);
        }
        .reg-plan-card:hover .reg-plan-card-inner {
            border-color: #c4c4c8;
        }
        .reg-plan-card input:checked + .reg-plan-card-inner {
            border-color: var(--color-accent);
            background: var(--color-accent-bg);
            box-shadow: 0 0 0 3px rgba(0, 113, 227, 0.08);
        }
        .reg-plan-card input:checked + .reg-plan-card-inner .reg-plan-check {
            opacity: 1;
        }
        .reg-plan-card-name {
            font-size: 14px;
            font-weight: 700;
            color: var(--color-text);
            margin-bottom: 2px;
        }
        .reg-plan-card-meta {
            font-size: 12px;
            color: var(--color-text-secondary);
        }
        .reg-plan-check {
            position: absolute;
            top: 8px;
            right: 8px;
            opacity: 0;
            color: var(--color-accent);
            transition: opacity .15s;
        }

        /* Mode toggle */
        .reg-mode-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 26px;
        }
        .reg-mode-card {
            display: block;
            cursor: pointer;
            position: relative;
        }
        .reg-mode-card input { position: absolute; opacity: 0; pointer-events: none; }
        .reg-mode-card-inner {
            border: 1.5px solid var(--color-border);
            border-radius: 12px;
            padding: 16px;
            transition: all .15s ease;
            background: var(--color-surface);
        }
        .reg-mode-card:hover .reg-mode-card-inner {
            border-color: #c4c4c8;
        }
        .reg-mode-card input:checked + .reg-mode-card-inner {
            border-color: var(--color-accent);
            background: var(--color-accent-bg);
            box-shadow: 0 0 0 3px rgba(0, 113, 227, 0.08);
        }
        .reg-mode-card-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }
        .reg-mode-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--color-accent-bg);
            color: var(--color-accent);
        }
        .reg-mode-card input:checked + .reg-mode-card-inner .reg-mode-icon {
            background: var(--color-accent);
            color: white;
        }
        .reg-mode-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--color-text);
        }
        .reg-mode-desc {
            font-size: 12.5px;
            color: var(--color-text-secondary);
            line-height: 1.4;
        }

        /* Form fields */
        .reg-fields {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 600px) {
            .reg-fields { grid-template-columns: 1fr; }
        }
        .reg-field-full { grid-column: 1 / -1; }
        .reg-field label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--color-text);
            margin-bottom: 6px;
        }
        .reg-field input {
            width: 100%;
            height: 42px;
            padding: 0 14px;
            border: 1px solid var(--color-border);
            border-radius: 8px;
            font-size: 14.5px;
            font-family: inherit;
            color: var(--color-text);
            background: var(--color-surface);
            transition: all .15s ease;
        }
        .reg-field input::placeholder { color: var(--color-text-tertiary); }
        .reg-field input:focus {
            outline: none;
            border-color: var(--color-accent);
            box-shadow: 0 0 0 3px rgba(0, 113, 227, 0.12);
        }

        /* Submit button */
        .reg-submit {
            display: block;
            width: 100%;
            height: 50px;
            background: var(--color-accent);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15.5px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all .2s ease;
            margin-top: 28px;
            box-shadow: 0 4px 14px rgba(0, 113, 227, 0.25);
        }
        .reg-submit:hover {
            background: var(--color-accent-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(0, 113, 227, 0.32);
        }
        .reg-submit:active { transform: translateY(0); }
        .reg-submit:disabled { background: #a0a0a0; cursor: not-allowed; transform: none; box-shadow: none; }

        .reg-form-footer {
            text-align: center;
            margin-top: 18px;
            font-size: 12.5px;
            color: var(--color-text-secondary);
            line-height: 1.5;
        }
        .reg-form-footer a { color: var(--color-accent); text-decoration: none; }

        .reg-alert {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .reg-alert-info {
            background: var(--color-accent-bg);
            border: 1px solid rgba(0, 113, 227, 0.2);
            color: #0c4a8a;
            padding: 14px 18px;
            border-radius: 10px;
            font-size: 14px;
            margin-bottom: 24px;
            max-width: 1200px;
            margin: 24px auto 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .reg-alert-info a {
            background: var(--color-accent);
            color: white;
            padding: 6px 14px;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
            font-size: 13px;
            margin-left: auto;
            flex-shrink: 0;
        }

        /* Sign-in link for already-logged-in */
        .reg-redirect {
            background: var(--color-accent-bg);
            border: 1px solid rgba(0, 113, 227, 0.15);
            color: #0c4a8a;
            padding: 14px 18px;
            border-radius: 10px;
            font-size: 14px;
            margin-bottom: 24px;
            max-width: 1080px;
            margin: 0 auto 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .reg-redirect strong { font-weight: 600; }
        .reg-redirect a {
            background: var(--color-accent);
            color: white;
            padding: 7px 16px;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
            font-size: 13px;
            margin-left: auto;
            flex-shrink: 0;
        }
    </style>
    <?php echo getSetting('tracking_head_code'); ?>
</head>
<body>
<?php echo getSetting('tracking_body_code'); ?>
<?php require_once __DIR__ . '/includes/preloader.php'; ?>

<!-- Sticky Nav -->
<nav class="reg-nav">
    <div class="reg-nav-inner">
        <a href="https://funl.online" class="reg-nav-logo">
            <img src="<?php echo getCompanyLogo(); ?>" alt="<?php echo htmlspecialchars(getAppName()); ?>">
            <span><?php echo htmlspecialchars(getAppName()); ?></span>
        </a>
        <div class="reg-nav-links">
            <a href="/login.php" class="reg-nav-link"><?php echo __('Already have an account?'); ?></a>
            <a href="/login.php" class="reg-nav-signin"><?php echo __('Sign In'); ?></a>
        </div>
    </div>
</nav>

<?php if (isLoggedIn()): ?>
    <div class="reg-redirect">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9 12l2 2 4-4"/></svg>
        <span><?php echo sprintf(__('You are signed in as %s.'), '<strong>' . htmlspecialchars($_SESSION['full_name'] ?? 'Administrator') . '</strong>'); ?></span>
        <a href="/pages/dashboard.php"><?php echo __('Go to Dashboard'); ?></a>
    </div>
<?php endif; ?>

<div class="reg-container">
    <div class="reg-header">
        <h1><?php echo __('Create your account'); ?></h1>
        <p><?php echo __('Start your free trial in less than a minute. No credit card required.'); ?></p>
    </div>

    <div class="reg-layout">
        <!-- ── Left: Plan Summary ─────────────────── -->
        <aside class="reg-summary">
            <div class="reg-card">
                <div class="reg-summary-label"><?php echo __('Your plan'); ?></div>
                <div class="reg-plan-name" id="summary-name">
                    <?= htmlspecialchars($selectedPlan['plan_name']) ?>
                </div>
                <div class="reg-plan-desc" id="summary-desc">
                    <?= htmlspecialchars($selectedPlan['description'] ?? '') ?>
                </div>

                <div class="reg-plan-price-row">
                    <span class="reg-plan-price" id="summary-price">$<?= number_format($selectedPlan['monthly_price'], 0) ?></span>
                    <span class="reg-plan-period">/<?= __('month') ?></span>
                </div>
                <div class="reg-plan-billing-note" id="summary-billing-note">
                    <?= __('Billed monthly after your 14-day free trial') ?>
                </div>

                <div class="reg-plan-save" id="summary-save">
                    💡 <?= sprintf(__('Save with yearly billing — %s (2 months free)'), '<strong>$' . number_format($selectedPlan['yearly_price'] ?? $selectedPlan['monthly_price'] * 10, 0) . '/' . __('year') . '</strong>') ?>
                </div>

                <hr class="reg-divider">

                <ul class="reg-features" id="summary-features">
                    <li>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <span id="summary-users"><?= sprintf(__('Up to %d users'), (int)$selectedPlan['user_limit']) ?></span>
                    </li>
                    <li>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <?= __('Unlimited leads & contacts') ?>
                    </li>
                    <li>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <?= __('Lead pipeline & deal management') ?>
                    </li>
                    <li>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <?= __('Email campaigns & automation') ?>
                    </li>
                    <li>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <?= __('Tasks & interaction tracking') ?>
                    </li>
                    <li>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <?= __('Mobile-ready sales dashboard') ?>
                    </li>
                    <li id="summary-extra-user" <?= ($selectedPlan['extra_user_price'] <= 0) ? 'style="display:none"' : '' ?>>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <span id="summary-extra-text"><?= sprintf(__('Extra users at %s/user'), '$' . number_format($selectedPlan['extra_user_price'], 0)) ?></span>
                    </li>
                </ul>

                <hr class="reg-divider">

                <div class="reg-trust">
                    <div class="reg-trust-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        <?= __('Setup in 60 seconds') ?>
                    </div>
                    <div class="reg-trust-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M2 12h20"/></svg>
                        <?= __('14-day full-access free trial') ?>
                    </div>
                    <div class="reg-trust-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 10h20"/></svg>
                        <?= __('No credit card required for trial') ?>
                    </div>
                </div>
            </div>
        </aside>

        <!-- ── Right: Form ────────────────────────── -->
        <main class="reg-card reg-form-card">
            <h2 class="reg-form-title"><?= __('Sign up details') ?></h2>
            <p class="reg-form-subtitle"><?= __('Choose a plan, then tell us about your company.') ?></p>

            <?php if ($error): ?>
                <div class="reg-alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" id="signupForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="register_company">
                <input type="hidden" name="plan" id="selectedPlan" value="<?= htmlspecialchars($defaultPlan) ?>">
                <!-- H-1 honeypot -->
                <div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">
                    <label>Leave this field empty</label>
                    <input type="text" name="website_url" tabindex="-1" autocomplete="off" value="">
                </div>

                <!-- Plan Picker -->
                <h3 class="reg-section-label"><?= __('1. Choose your plan') ?></h3>
                <div class="reg-plan-grid">
                    <?php foreach ($plans as $plan): ?>
                    <label class="reg-plan-card" data-plan-key="<?= htmlspecialchars($plan['plan_key']) ?>"
                        data-name="<?= htmlspecialchars($plan['plan_name']) ?>"
                        data-desc="<?= htmlspecialchars($plan['description'] ?? '') ?>"
                        data-monthly="<?= number_format($plan['monthly_price'], 2, '.', '') ?>"
                        data-yearly="<?= number_format($plan['yearly_price'] ?? $plan['monthly_price'] * 10, 0) ?>"
                        data-users="<?= (int)$plan['user_limit'] ?>"
                        data-extra="<?= number_format($plan['extra_user_price'] ?? 0, 2, '.', '') ?>">
                        <input type="radio" name="plan_radio" value="<?= htmlspecialchars($plan['plan_key']) ?>"
                            <?= ($plan['plan_key'] === $defaultPlan) ? 'checked' : '' ?>>
                        <div class="reg-plan-card-inner">
                            <svg class="reg-plan-check" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            <div class="reg-plan-card-name"><?= htmlspecialchars($plan['plan_name']) ?></div>
                            <div class="reg-plan-card-meta"><?= (int)$plan['user_limit'] ?> users · $<?= number_format($plan['monthly_price'], 0) ?>/mo</div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>

                <!-- Subscription mode -->
                <h3 class="reg-section-label"><?= __('2. How do you want to start?') ?></h3>
                <div class="reg-mode-grid">
                    <label class="reg-mode-card" id="mode-trial-label">
                        <input type="radio" name="signup_mode" value="trial" checked>
                        <div class="reg-mode-card-inner">
                            <div class="reg-mode-card-header">
                                <div class="reg-mode-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>
                                </div>
                                <div class="reg-mode-title"><?= __('Free Trial') ?></div>
                            </div>
                            <div class="reg-mode-desc">14 days · <?= __('No credit card required') ?></div>
                        </div>
                    </label>
                    <label class="reg-mode-card" id="mode-subscribe-label">
                        <input type="radio" name="signup_mode" value="subscribe">
                        <div class="reg-mode-card-inner">
                            <div class="reg-mode-card-header">
                                <div class="reg-mode-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                                </div>
                                <div class="reg-mode-title"><?= __('Subscribe Now') ?></div>
                            </div>
                            <div class="reg-mode-desc"><?= __('Pay today, full access immediately') ?></div>
                        </div>
                    </label>
                </div>

                <!-- Account fields -->
                <h3 class="reg-section-label"><?= __('3. Your details') ?></h3>
                <div class="reg-fields">
                    <div class="reg-field reg-field-full">
                        <label for="company_name"><?= __('Company name') ?> *</label>
                        <input type="text" id="company_name" name="company_name" placeholder="<?= __('Acme Inc.') ?>" required value="<?= htmlspecialchars($companyName ?? '') ?>">
                    </div>
                    <div class="reg-field">
                        <label for="full_name"><?= __('Your full name') ?> *</label>
                        <input type="text" id="full_name" name="full_name" placeholder="<?= __('John Doe') ?>" required value="<?= htmlspecialchars($fullName ?? '') ?>">
                    </div>
                    <div class="reg-field">
                        <label for="email"><?= __('Work email') ?> *</label>
                        <input type="email" id="email" name="email" placeholder="<?= __('you@company.com') ?>" required value="<?= htmlspecialchars($email ?? '') ?>">
                    </div>
                    <div class="reg-field reg-field-full">
                        <label for="phone"><?= __('Phone') ?> <span style="color:var(--color-text-tertiary);"><?= __('(optional)') ?></span></label>
                        <input type="tel" id="phone" name="phone" placeholder="+1 234 567 890" value="<?= htmlspecialchars($post['phone'] ?? '') ?>">
                    </div>
                    <div class="reg-field">
                        <label for="password"><?= __('Password') ?> *</label>
                        <input type="password" id="password" name="password" placeholder="<?= __('Min 8 characters') ?>" required>
                    </div>
                    <div class="reg-field">
                        <label for="confirm_password"><?= __('Confirm password') ?> *</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="<?= __('Repeat password') ?>" required>
                    </div>
                </div>

                <button type="submit" class="reg-submit" id="submitBtn">
                    <?= __('Start 14-Day Free Trial') ?>
                </button>

                <p class="reg-form-footer">
                    <?= __('By signing up, you agree to our Terms of Service and Privacy Policy.') ?>
                </p>
            </form>
        </main>
    </div>
</div>

<script>
(function() {
    // Plan data for live summary sync
    const planData = {
        <?php foreach ($plans as $p): ?>
        '<?= $p['plan_key'] ?>': {
            name: '<?= addslashes($p['plan_name']) ?>',
            desc: '<?= addslashes($p['description'] ?? '') ?>',
            monthly: <?= (float)$p['monthly_price'] ?>,
            yearly: <?= (float)($p['yearly_price'] ?? $p['monthly_price'] * 10) ?>,
            users: <?= (int)$p['user_limit'] ?>,
            extra: <?= (float)($p['extra_user_price'] ?? 0) ?>,
        },
        <?php endforeach; ?>
    };

    const $name = document.getElementById('summary-name');
    const $desc = document.getElementById('summary-desc');
    const $price = document.getElementById('summary-price');
    const $billingNote = document.getElementById('summary-billing-note');
    const $save = document.getElementById('summary-save');
    const $users = document.getElementById('summary-users');
    const $extraUser = document.getElementById('summary-extra-user');
    const $extraText = document.getElementById('summary-extra-text');
    const $submit = document.getElementById('submitBtn');
    const $selectedPlan = document.getElementById('selectedPlan');
    const $signupModeInputs = document.querySelectorAll('input[name="signup_mode"]');

    function fmtMoney(n) { return '$' + Number(n).toFixed(0); }

    function updateSummary() {
        const checked = document.querySelector('input[name="plan_radio"]:checked');
        if (!checked) return;
        const key = checked.value;
        const data = planData[key];
        if (!data) return;

        $name.textContent = data.name;
        $desc.textContent = data.desc;
        $price.textContent = fmtMoney(data.monthly);
        $users.textContent = data.users === 1 ? 'Up to 1 user' : 'Up to ' + data.users + ' users';
        $save.innerHTML = '💡 Save with yearly billing — <strong>' + fmtMoney(data.yearly) + '/year</strong> (2 months free)';

        if (data.extra > 0) {
            $extraUser.style.display = '';
            $extraText.textContent = 'Extra users at $' + data.extra.toFixed(0) + '/user';
        } else {
            $extraUser.style.display = 'none';
        }

        // Sync the hidden field
        $selectedPlan.value = key;
    }

    // Live update on radio change
    document.querySelectorAll('input[name="plan_radio"]').forEach(input => {
        input.addEventListener('change', updateSummary);
    });

    // Update submit button text based on signup mode
    function updateSubmitButton() {
        const mode = document.querySelector('input[name="signup_mode"]:checked').value;
        $submit.textContent = mode === 'subscribe' ? 'Continue to Payment' : 'Start 14-Day Free Trial';
        $billingNote.textContent = mode === 'subscribe'
            ? 'You will be charged immediately after signup'
            : 'Billed monthly after your 14-day free trial';
    }
    $signupModeInputs.forEach(input => {
        input.addEventListener('change', updateSubmitButton);
    });

    // Init
    updateSummary();
    updateSubmitButton();
})();
</script>
</body>
</html>