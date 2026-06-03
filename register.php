<?php
/**
 * Pinpoint CRM - Registration Landing Page
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
        if (count($attempts) >= 5) {
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

        if (empty($companyName) || empty($email) || empty($password) || empty($fullName)) {
            $error = __('All fields are required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = __('Please enter a valid email address.');
        } elseif (strlen($password) < 8) {
            $error = __('Password must be at least 8 characters.');
        } elseif (function_exists('validatePasswordStrength') && !validatePasswordStrength($password)) {
            // M-10 fix: enforce the same strength policy used elsewhere
            // (upper + lower + number, min 8 chars). Earlier this only checked length.
            $error = __('Password must include uppercase, lowercase, and a number.');
        } elseif ($password !== $confirmPassword) {
            $error = __('Passwords do not match.');
        } else {
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
                    sendVerificationEmail($email, $fullName, $verifyToken);
                    
                    // Set email as NOT verified (must verify before full access)
                    $db->query("UPDATE users SET email_verified = 0 WHERE user_id = ?", [$userId]);

                    // Set session but mark as unverified
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['username'] = $email;
                    $_SESSION['email'] = $email;
                    $_SESSION['full_name'] = $fullName;
                    $_SESSION['role'] = 'Admin';
                    $_SESSION['company_id'] = $companyId;
                    $_SESSION['email_verified'] = false;
                    
                    // Redirect to verification page instead of dashboard
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
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        :root {
            --color-primary: #dd2d4a;
            --color-primary-dark: #9d1830;
            --color-primary-light: #ff627d;
            --color-grey: #999999;
            --color-bg-light: #f8f9fb;
            --color-border: #e8e8ef;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'SF Pro Display', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--color-bg-light);
            color: #1a1a2e;
            line-height: 1.6;
        }

        /* Minimal Header */
        .nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
            z-index: 100;
            padding: 0 24px;
        }
        .nav-inner {
            max-width: 1140px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 64px;
        }
        .nav-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 18px;
            color: var(--color-primary);
            text-decoration: none;
        }
        .nav-logo img { height: 32px; }
        .nav-signin {
            color: #4b5563;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.2s;
        }
        .nav-signin strong {
            color: var(--color-primary);
            font-weight: 600;
        }
        .nav-signin:hover {
            color: var(--color-primary-dark);
        }

        /* Split Layout Container */
        .checkout-container {
            max-width: 1140px;
            margin: 100px auto 60px;
            padding: 0 24px;
        }
        .checkout-split {
            display: flex;
            gap: 48px;
            align-items: flex-start;
        }

        /* Left Side: Summary */
        .checkout-summary {
            flex: 0 0 42%;
            position: sticky;
            top: 100px;
        }
        .summary-card {
            background: #ffffff;
            border: 1px solid var(--color-border);
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
        }
        .summary-badge {
            display: inline-block;
            background: rgba(221, 45, 74, 0.08);
            color: var(--color-primary);
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 16px;
        }
        .summary-card h2 {
            font-size: 24px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 6px;
            letter-spacing: -0.01em;
        }
        .summary-desc {
            font-size: 14px;
            color: var(--color-grey);
            margin-bottom: 24px;
            line-height: 1.4;
        }
        .summary-price {
            display: flex;
            align-items: baseline;
            margin-bottom: 4px;
        }
        .summary-price .currency {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
        }
        .summary-price .amount {
            font-size: 40px;
            font-weight: 800;
            color: var(--color-primary);
            letter-spacing: -0.02em;
        }
        .summary-price .period {
            font-size: 14px;
            font-weight: 500;
            color: var(--color-grey);
            margin-left: 4px;
        }
        .billing-note {
            font-size: 13px;
            color: var(--color-grey);
            margin-bottom: 24px;
            font-weight: 500;
        }
        .yearly-comparison {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 12px;
            font-size: 13px;
            color: #475569;
            line-height: 1.4;
        }
        .summary-divider {
            border: none;
            border-top: 1px solid var(--color-border);
            margin: 24px 0;
        }
        .summary-features {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .summary-features li {
            font-size: 13.5px;
            color: #4b5563;
            padding: 8px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .summary-features li::before {
            content: "✓";
            color: var(--color-primary);
            font-weight: bold;
            flex-shrink: 0;
        }
        .trust-list {
            margin-top: 8px;
        }
        .trust-item {
            font-size: 13px;
            color: #6b7280;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 0;
        }
        .trust-item .icon {
            font-size: 16px;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: var(--color-grey);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            transition: color 0.2s;
        }
        .back-link:hover {
            color: var(--color-primary);
        }

        /* Right Side: Form */
        .checkout-form-wrap {
            flex: 0 0 58%;
        }
        .form-card {
            background: #ffffff;
            border: 1px solid var(--color-border);
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
        }
        .form-card h2 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        .form-card .subtitle {
            color: var(--color-grey);
            font-size: 14.5px;
            margin-bottom: 32px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }
        .form-control {
            width: 100%;
            height: 44px;
            padding: 0 14px;
            border: 1.5px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14.5px;
            transition: all 0.2s;
            font-family: inherit;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(221, 45, 74, 0.1);
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .btn-submit {
            width: 100%;
            height: 48px;
            background: var(--color-primary);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 10px;
            box-shadow: 0 4px 14px rgba(221, 45, 74, 0.25);
        }
        .btn-submit:hover {
            background: var(--color-primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(221, 45, 74, 0.35);
        }
        .btn-submit:active {
            transform: translateY(0);
        }
        .form-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 13px;
            color: var(--color-grey);
            line-height: 1.4;
        }

        /* Footer minimal */
        .footer {
            border-top: 1px solid var(--color-border);
            padding: 30px 24px;
            text-align: center;
            font-size: 13px;
            color: var(--color-grey);
            background: #ffffff;
            margin-top: 60px;
        }

        @media (max-width: 900px) {
            .checkout-split {
                flex-direction: column;
                gap: 32px;
            }
            .checkout-summary {
                flex: 1 1 100%;
                width: 100%;
                position: relative;
                top: 0;
            }
            .checkout-form-wrap {
                flex: 1 1 100%;
                width: 100%;
            }
            .form-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
        }
    </style>
    <?php echo getSetting('tracking_head_code'); ?>
</head>
<body>
<?php echo getSetting('tracking_body_code'); ?>
<?php require_once __DIR__ . '/includes/preloader.php'; ?>

<!-- Navigation -->
<nav class="nav">
    <div class="nav-inner">
        <a href="https://funl.online" class="nav-logo">
            <img src="<?php echo getCompanyLogo(); ?>" alt="<?php echo htmlspecialchars(getAppName()); ?>">
            <span><?php echo htmlspecialchars(getAppName()); ?></span>
        </a>
        <a href="/login.php" class="nav-signin"><?php echo __('Already have an account?'); ?> <strong><?php echo __('Sign In'); ?></strong></a>
    </div>
</nav>

<?php if (isLoggedIn()): ?>
    <div style="max-width: 1140px; margin: 90px auto -60px; padding: 14px 20px; background: #fff8eb; border: 1px solid #ffe8cc; border-radius: 10px; color: #b45309; font-size: 14px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 8px rgba(0,0,0,0.03); z-index: 50; position: relative;">
        <span>👋 <?php echo sprintf(__('You are currently signed in as %s. You can view this page for testing, or return to your dashboard.'), '<strong>' . htmlspecialchars($_SESSION['full_name'] ?? 'Administrator') . '</strong>'); ?></span>
        <a href="/pages/dashboard.php" style="background: #d97706; color: #fff; padding: 8px 16px; border-radius: 6px; font-weight: 600; text-decoration: none; font-size: 12px; margin-left: 16px;"><?php echo __('Go to Dashboard'); ?></a>
    </div>
<?php endif; ?>

<div class="checkout-container">
    <div class="checkout-split">
        <!-- Left Column: Selected Plan Overview -->
        <div class="checkout-summary">
            <div class="summary-card">
                <div class="summary-badge"><?php echo __('Chosen Plan'); ?></div>
                <h2><?php echo sprintf(__('%s Plan'), htmlspecialchars($selectedPlan['plan_name'])); ?></h2>
                <p class="summary-desc"><?php echo htmlspecialchars($selectedPlan['description'] ?? ''); ?></p>
                
                <div class="summary-price">
                    <span class="currency">$</span>
                    <span class="amount"><?php echo number_format($selectedPlan['monthly_price'], 0); ?></span>
                    <span class="period"><?php echo __('/month'); ?></span>
                </div>
                <p class="billing-note"><?php echo __('Billed monthly after your 14-day free trial'); ?></p>
                
                <div class="yearly-comparison">
                    💡 <?php echo sprintf(__('Save with yearly billing at %s (2 months free!)'), '<strong>$' . number_format($selectedPlan['yearly_price'] ?? $selectedPlan['monthly_price'] * 10, 0) . '/' . __('year') . '</strong>'); ?>
                </div>
                
                <hr class="summary-divider">
                
                <ul class="summary-features">
                    <li><?php echo sprintf(__('Up to %s'), '<strong>' . $selectedPlan['user_limit'] . '</strong> ' . ($selectedPlan['user_limit'] > 1 ? __('users') : __('user'))); ?></li>
                    <li><?php echo __('Unlimited leads & contacts'); ?></li>
                    <li><?php echo __('Lead pipeline management'); ?></li>
                    <li><?php echo __('Email campaigns tool'); ?></li>
                    <li><?php echo __('Task & interaction tracking'); ?></li>
                    <li><?php echo __('Mobile-ready sales dashboard'); ?></li>
                    <?php if ($selectedPlan['extra_user_price'] > 0): ?>
                    <li><?php echo sprintf(__('Extra users at %s'), '$' . number_format($selectedPlan['extra_user_price'], 0) . '/' . __('user')); ?></li>
                    <?php endif; ?>
                </ul>
                
                <hr class="summary-divider">
                
                <div class="trust-list">
                    <div class="trust-item"><span class="icon">⚡</span> <?php echo __('Setup in 60 seconds'); ?></div>
                    <div class="trust-item"><span class="icon">🎁</span> <?php echo __('14-day full-access trial'); ?></div>
                    <div class="trust-item"><span class="icon">💳</span> <?php echo __('No credit card required'); ?></div>
                </div>
            </div>
            
            <a href="https://funl.online" class="back-link">← <?php echo __('Back to Marketing Site'); ?></a>
        </div>
        
        <!-- Right Column: Sign-Up Form -->
        <div class="checkout-form-wrap">
            <div class="form-card">
                <h2><?php echo __('Create your account'); ?></h2>
                <p class="subtitle"><?php echo __('Set up your sales CRM dashboard instantly.'); ?></p>

                <?php if ($error): ?>
                    <div class="alert alert-error" style="margin-bottom:20px;padding:12px 16px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;color:#dc2626;font-size:14px;"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="register_company">
                    <input type="hidden" name="plan" id="selectedPlan" value="<?php echo htmlspecialchars($defaultPlan); ?>">

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label"><?php echo __('Company Name'); ?> *</label>
                            <input type="text" name="company_name" class="form-control" placeholder="<?php echo __('Acme Inc.'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo __('Your Full Name'); ?> *</label>
                            <input type="text" name="full_name" class="form-control" placeholder="<?php echo __('John Doe'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo __('Work Email'); ?> *</label>
                            <input type="email" name="email" class="form-control" placeholder="<?php echo __('you@company.com'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo __('Phone (optional)'); ?></label>
                            <input type="tel" name="phone" class="form-control" placeholder="+1 234 567 890" value="<?php echo htmlspecialchars($post['phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo __('Password'); ?> *</label>
                            <input type="password" name="password" class="form-control" placeholder="<?php echo __('Min 8 characters'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?php echo __('Confirm Password'); ?> *</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="<?php echo __('Repeat password'); ?>" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit"><?php echo __('Start 14-Day Free Trial'); ?></button>
                </form>

                <p class="form-footer">
                    <?php echo __('By signing up, you agree to our Terms of Service and Privacy Policy.'); ?>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="footer">
    <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(getAppName()); ?><?php echo __('.&nbsp;All rights reserved.'); ?> | <a href="/login.php"><?php echo __('Sign In'); ?></a></p>
</footer>

</body>
</html>