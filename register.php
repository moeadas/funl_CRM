<?php
/**
 * Pinpoint CRM - Registration Landing Page
 * SaaS signup with plan selection - ForceManager-inspired design
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/company-functions.php';

startSecureSession();

// Do not force redirect logged-in users so they can access/review the registration page

$error = '';
$csrf_token = generateCSRFToken();

// Registration handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCSRF();

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
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $db = Database::getInstance();

                $existing = $db->query("SELECT 1 FROM companies WHERE email = ? OR company_slug = ?", [$email, $companySlug])->fetch();
                if ($existing) {
                    $error = 'A company with this email or name already exists.';
                    } else {
                        // Also check if email already exists in users table (could be from another company)
                        $existingUser = $db->query("SELECT 1 FROM users WHERE email = ?", [$email])->fetch();
                        if ($existingUser) {
                            $error = 'This email address is already registered. Please use a different email or sign in.';
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
                        $db->insert('settings', [
                            'company_id' => $companyId,
                            'setting_key' => $key,
                            'setting_value' => $value,
                            'setting_type' => 'text',
                        ]);
                    }

                    sendVerificationEmail($userId, $email);
                    
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(getAppName()); ?> - Start Your Free Trial</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo getCompanyFavicon(); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        :root { --color-primary: #dd2d4a; --color-primary-dark: #9d1830; --color-primary-light: #ff627d; --color-grey: #999999; }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'SF Pro Display', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #fff; color: #1a1a2e; line-height: 1.6; }

        /* Navigation */
        .nav { position: fixed; top: 0; left: 0; right: 0; background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(0,0,0,0.06); z-index: 100; padding: 0 24px; }
        .nav-inner { max-width: 1200px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; height: 64px; }
        .nav-logo { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 18px; color: var(--color-primary); text-decoration: none; }
        .nav-logo img { height: 32px; }
        .nav-links { display: flex; align-items: center; gap: 32px; }
        .nav-links a { color: var(--color-grey); text-decoration: none; font-size: 14px; font-weight: 500; transition: color 0.2s; }
        .nav-links a:hover { color: var(--color-primary); }
        .nav-cta { background: var(--color-primary); color: #fff; padding: 10px 20px; border-radius: 8px; font-weight: 600; text-decoration: none; font-size: 14px; transition: all 0.2s; }
        .nav-cta:hover { background: var(--color-primary-dark); transform: translateY(-1px); }
        .nav-signin { color: var(--color-primary); font-weight: 600; text-decoration: none; font-size: 14px; }

        /* Hero */
        .hero { padding: 140px 24px 80px; text-align: center; max-width: 900px; margin: 0 auto; }
        .hero-badge { display: inline-block; background: rgba(221, 45, 74,0.08); color: var(--color-primary); padding: 6px 16px; border-radius: 50px; font-size: 13px; font-weight: 600; margin-bottom: 24px; }
        .hero h1 { font-size: 52px; font-weight: 800; line-height: 1.1; margin-bottom: 20px; letter-spacing: -0.02em; }
        .hero h1 span { color: var(--color-primary); }
        .hero p { font-size: 20px; color: var(--color-grey); max-width: 600px; margin: 0 auto 32px; }
        .hero-cta { display: inline-flex; align-items: center; gap: 16px; }
        .btn-hero { background: var(--color-primary); color: #fff; padding: 16px 36px; border-radius: 12px; font-size: 16px; font-weight: 700; text-decoration: none; border: none; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 20px rgba(221, 45, 74,0.3); }
        .btn-hero:hover { background: var(--color-primary-dark); transform: translateY(-2px); box-shadow: 0 8px 30px rgba(221, 45, 74,0.4); }
        .btn-hero-outline { background: transparent; color: var(--color-primary); padding: 16px 36px; border-radius: 12px; font-size: 16px; font-weight: 700; text-decoration: none; border: 2px solid var(--color-primary); transition: all 0.2s; }
        .btn-hero-outline:hover { background: rgba(221, 45, 74,0.05); }
        .hero-trust { margin-top: 48px; display: flex; align-items: center; justify-content: center; gap: 8px; color: #8a8a9a; font-size: 14px; }
        .hero-trust .stars { color: #FFB800; font-size: 16px; }

        /* Section headers */
        .section { padding: 80px 24px; }
        .section-header { text-align: center; max-width: 700px; margin: 0 auto 64px; }
        .section-header h2 { font-size: 36px; font-weight: 700; margin-bottom: 12px; }
        .section-header p { font-size: 18px; color: var(--color-grey); }

        /* Pricing */
        .pricing-section { background: #f8f9fb; }
        .pricing-cards { max-width: 1000px; margin: 0 auto; display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
        .pricing-card { background: #fff; border-radius: 16px; padding: 36px 28px; border: 1px solid #e8e8ef; transition: all 0.3s; position: relative; }
        .pricing-card:hover { transform: translateY(-4px); box-shadow: 0 20px 40px rgba(0,0,0,0.08); }
        .pricing-card.featured { border-color: var(--color-primary); box-shadow: 0 8px 30px rgba(221, 45, 74,0.12); }
        .pricing-card .badge { position: absolute; top: -12px; right: 24px; background: var(--color-primary); color: #fff; padding: 6px 14px; border-radius: 50px; font-size: 12px; font-weight: 700; }
        .pricing-card h3 { font-size: 20px; font-weight: 700; margin-bottom: 4px; }
        .pricing-card .desc { font-size: 14px; color: var(--color-grey); margin-bottom: 20px; }
        .pricing-card .price { font-size: 40px; font-weight: 800; color: var(--color-primary); margin-bottom: 4px; }
        .pricing-card .price span { font-size: 15px; font-weight: 400; color: var(--color-grey); }
        .pricing-card .price-note { font-size: 13px; color: var(--color-grey); margin-bottom: 24px; }
        .pricing-card .btn-select { width: 100%; padding: 14px; border-radius: 10px; border: 2px solid var(--color-primary); background: #fff; color: var(--color-primary); font-weight: 700; font-size: 15px; cursor: pointer; transition: all 0.2s; margin-bottom: 24px; }
        .pricing-card .btn-select:hover, .pricing-card .btn-select.active { background: var(--color-primary); color: #fff; }
        .pricing-card ul { list-style: none; }
        .pricing-card ul li { padding: 8px 0; font-size: 14px; color: #4a4a5a; display: flex; align-items: center; gap: 10px; }
        .pricing-card ul li::before { content: "✓"; color: var(--color-primary); font-weight: bold; flex-shrink: 0; }

        /* Registration Form */
        .form-section { max-width: 520px; margin: 0 auto; padding: 60px 24px; }
        .form-card { background: #fff; border-radius: 16px; padding: 40px; border: 1px solid #e8e8ef; box-shadow: 0 4px 24px rgba(0,0,0,0.06); }
        .form-card h2 { font-size: 28px; font-weight: 700; margin-bottom: 8px; text-align: center; }
        .form-card .subtitle { text-align: center; color: var(--color-grey); font-size: 15px; margin-bottom: 28px; }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; color: #3a3a4a; margin-bottom: 6px; }
        .form-control { width: 100%; padding: 12px 14px; border: 1.5px solid #e0e0e8; border-radius: 10px; font-size: 15px; transition: all 0.2s; font-family: inherit; }
        .form-control:focus { outline: none; border-color: var(--color-primary); box-shadow: 0 0 0 3px rgba(221, 45, 74,0.1); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .btn-submit { width: 100%; padding: 16px; background: var(--color-primary); color: #fff; border: none; border-radius: 12px; font-size: 16px; font-weight: 700; cursor: pointer; transition: all 0.2s; margin-top: 8px; }
        .btn-submit:hover { background: var(--color-primary-dark); transform: translateY(-1px); }
        .form-footer { text-align: center; margin-top: 20px; font-size: 13px; color: var(--color-grey); }
        .form-footer a { color: var(--color-primary); font-weight: 600; text-decoration: none; }

        /* Features */
        .features-grid { max-width: 1000px; margin: 0 auto; display: grid; grid-template-columns: repeat(3, 1fr); gap: 40px; }
        .feature { text-align: center; padding: 20px; }
        .feature-icon { width: 56px; height: 56px; background: rgba(221, 45, 74,0.08); border-radius: 14px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 24px; }
        .feature h3 { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
        .feature p { font-size: 15px; color: var(--color-grey); }

        /* Comparison */
        .comparison-section { background: #f8f9fb; }
        .comparison-table { max-width: 800px; margin: 0 auto; background: #fff; border-radius: 16px; overflow: hidden; border: 1px solid #e8e8ef; }
        .comparison-row { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; padding: 14px 24px; border-bottom: 1px solid #f0f0f5; align-items: center; }
        .comparison-row.header { background: #f8f9fb; font-weight: 700; font-size: 14px; }
        .comparison-row .check { color: var(--color-primary); font-weight: bold; text-align: center; }
        .comparison-row .uncheck { color: #ccc; text-align: center; }
        .comparison-row .feature-name { font-size: 14px; color: #3a3a4a; }
        .comparison-row .plan-name { text-align: center; font-weight: 700; font-size: 14px; }

        /* Trust */
        .trust-section { text-align: center; padding: 60px 24px; }
        .trust-badges { display: flex; justify-content: center; gap: 48px; margin-top: 32px; flex-wrap: wrap; }
        .trust-badge { display: flex; flex-direction: column; align-items: center; gap: 8px; color: var(--color-grey); font-size: 14px; }
        .trust-badge .icon { width: 48px; height: 48px; background: #f0f0f5; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; }

        /* Footer */
        .footer { background: #1a1a2e; color: var(--color-grey); padding: 40px 24px; text-align: center; font-size: 14px; }
        .footer a { color: #ccc; text-decoration: none; }

        @media (max-width: 768px) {
            .hero h1 { font-size: 36px; }
            .hero p { font-size: 17px; }
            .pricing-cards, .features-grid { grid-template-columns: 1fr; }
            .form-grid { grid-template-columns: 1fr; }
            .comparison-row { grid-template-columns: 2fr 1fr; }
            .comparison-row .plan-name:nth-child(3), .comparison-row .plan-name:nth-child(4) { display: none; }
            .nav-links { display: none; }
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/preloader.php'; ?>

<!-- Navigation -->
<nav class="nav">
    <div class="nav-inner">
        <a href="/" class="nav-logo">
            <img src="<?php echo getCompanyLogo(); ?>" alt="<?php echo htmlspecialchars(getAppName()); ?>">
            <span><?php echo htmlspecialchars(getAppName()); ?></span>
        </a>
        <div class="nav-links">
            <a href="#pricing">Pricing</a>
            <a href="#features">Features</a>
            <a href="#compare">Compare</a>
            <?php if (isLoggedIn()): ?>
                <a href="/pages/dashboard.php" class="nav-cta">Dashboard</a>
            <?php else: ?>
                <a href="/login.php" class="nav-signin">Sign In</a>
                <a href="#signup" class="nav-cta">Try it free</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<?php if (isLoggedIn()): ?>
    <div style="max-width: 1000px; margin: 90px auto -50px; padding: 14px 20px; background: #fff8eb; border: 1px solid #ffe8cc; border-radius: 10px; color: #b45309; font-size: 14px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 8px rgba(0,0,0,0.03); z-index: 50; position: relative;">
        <span>👋 You are currently signed in as <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Administrator'); ?></strong>. You can view this page for testing, or return to your dashboard.</span>
        <a href="/pages/dashboard.php" style="background: #d97706; color: #fff; padding: 8px 16px; border-radius: 6px; font-weight: 600; text-decoration: none; font-size: 12px; margin-left: 16px;">Go to Dashboard</a>
    </div>
<?php endif; ?>

<!-- Hero -->
<section class="hero">
    <div class="hero-badge">🚀 14-Day Free Trial - No Credit Card</div>
    <h1>The CRM that <span>supercharges</span> your sales team</h1>
    <p><?php echo htmlspecialchars(getAppName()); ?> gives your team the tools to manage leads, close deals faster, and grow revenue - all in one intuitive platform.</p>
    <div class="hero-cta">
        <?php if (isLoggedIn()): ?>
            <a href="/pages/dashboard.php" class="btn-hero">Go to Dashboard</a>
        <?php else: ?>
            <a href="#signup" class="btn-hero">Start Free Trial</a>
            <a href="#pricing" class="btn-hero-outline">See Plans</a>
        <?php endif; ?>
    </div>
    <div class="hero-trust">
        <span class="stars">★★★★★</span>
        <span>Trusted by growing sales teams worldwide</span>
    </div>
</section>

<!-- Pricing -->
<section class="section pricing-section" id="pricing">
    <div class="section-header">
        <h2>Simple, transparent pricing</h2>
        <p>Start free. Scale as you grow. No hidden fees.</p>
    </div>
    <div class="pricing-cards">
        <?php foreach ($plans as $plan): ?>
        <div class="pricing-card <?php echo ($plan['plan_key'] === 'team') ? 'featured' : ''; ?>">
            <?php if ($plan['plan_key'] === 'team'): ?><div class="badge">Most Popular</div><?php endif; ?>
            <h3><?php echo htmlspecialchars($plan['plan_name']); ?></h3>
            <p class="desc"><?php echo htmlspecialchars($plan['description'] ?? ''); ?></p>
            <div class="price">$<?php echo number_format($plan['monthly_price'], 0); ?><span>/month</span></div>
            <p class="price-note">or $<?php echo number_format($plan['yearly_price'] ?? $plan['monthly_price'] * 10, 0); ?>/year (2 months free)</p>
            <button type="button" class="btn-select <?php echo ($plan['plan_key'] === 'single') ? 'active' : ''; ?>" onclick="selectPlan('<?php echo $plan['plan_key']; ?>', this)">
                <?php echo ($plan['plan_key'] === 'single') ? 'Selected' : 'Select Plan'; ?>
            </button>
            <ul>
                <li>Up to <?php echo $plan['user_limit']; ?> <?php echo $plan['user_limit'] > 1 ? 'users' : 'user'; ?></li>
                <li>Unlimited leads & contacts</li>
                <li>Lead pipeline management</li>
                <li>Email campaigns</li>
                <li>Task & interaction tracking</li>
                <li>Document library</li>
                <li>Mobile-friendly dashboard</li>
                <?php if ($plan['extra_user_price'] > 0): ?>
                <li>Extra users at $<?php echo number_format($plan['extra_user_price'], 0); ?>/user</li>
                <?php endif; ?>
                <?php if ($plan['plan_key'] === 'enterprise'): ?>
                <li>Priority support</li>
                <li>Custom integrations</li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Features -->
<section class="section" id="features">
    <div class="section-header">
        <h2>Everything you need to sell smarter</h2>
        <p>Powerful features designed for modern sales teams</p>
    </div>
    <div class="features-grid">
        <div class="feature">
            <div class="feature-icon">📊</div>
            <h3>Lead Management</h3>
            <p>Track every lead from first contact to closed deal. Never lose an opportunity again.</p>
        </div>
        <div class="feature">
            <div class="feature-icon">📧</div>
            <h3>Email Campaigns</h3>
            <p>Build, send, and track email campaigns directly from your CRM. Nurture leads at scale.</p>
        </div>
        <div class="feature">
            <div class="feature-icon">📱</div>
            <h3>Mobile Ready</h3>
            <p>Access your CRM from any device. Your pipeline goes wherever your team goes.</p>
        </div>
        <div class="feature">
            <div class="feature-icon">📄</div>
            <h3>Document Library</h3>
            <p>Store, share, and manage sales collateral. Keep your team aligned with the right content.</p>
        </div>
        <div class="feature">
            <div class="feature-icon">🤝</div>
            <h3>Interaction Tracking</h3>
            <p>Log calls, emails, meetings, and notes. Build complete customer histories effortlessly.</p>
        </div>
        <div class="feature">
            <div class="feature-icon">🔒</div>
            <h3>Enterprise Security</h3>
            <p>SSL encryption, secure sessions, and role-based access. Your data stays protected.</p>
        </div>
    </div>
</section>

<!-- Comparison -->
<section class="section comparison-section" id="compare">
    <div class="section-header">
        <h2>Compare plans</h2>
        <p>Find the perfect fit for your team size</p>
    </div>
    <div class="comparison-table">
        <div class="comparison-row header">
            <div>Feature</div>
            <div class="plan-name">Single</div>
            <div class="plan-name">Team</div>
            <div class="plan-name">Enterprise</div>
        </div>
        <div class="comparison-row">
            <div class="feature-name">Users</div>
            <div class="check">1</div>
            <div class="check">5</div>
            <div class="check">15</div>
        </div>
        <div class="comparison-row">
            <div class="feature-name">Unlimited Leads</div>
            <div class="check">✓</div>
            <div class="check">✓</div>
            <div class="check">✓</div>
        </div>
        <div class="comparison-row">
            <div class="feature-name">Lead Pipeline</div>
            <div class="check">✓</div>
            <div class="check">✓</div>
            <div class="check">✓</div>
        </div>
        <div class="comparison-row">
            <div class="feature-name">Email Campaigns</div>
            <div class="check">✓</div>
            <div class="check">✓</div>
            <div class="check">✓</div>
        </div>
        <div class="comparison-row">
            <div class="feature-name">Document Library</div>
            <div class="check">✓</div>
            <div class="check">✓</div>
            <div class="check">✓</div>
        </div>
        <div class="comparison-row">
            <div class="feature-name">Interaction History</div>
            <div class="check">✓</div>
            <div class="check">✓</div>
            <div class="check">✓</div>
        </div>
        <div class="comparison-row">
            <div class="feature-name">Extra User Pricing</div>
            <div class="uncheck">-</div>
            <div class="check">$8/mo</div>
            <div class="check">$6/mo</div>
        </div>
        <div class="comparison-row">
            <div class="feature-name">Priority Support</div>
            <div class="uncheck">-</div>
            <div class="check">✓</div>
            <div class="check">✓</div>
        </div>
        <div class="comparison-row">
            <div class="feature-name">Custom Integrations</div>
            <div class="uncheck">-</div>
            <div class="uncheck">-</div>
            <div class="check">✓</div>
        </div>
    </div>
</section>

<!-- Trust -->
<section class="trust-section">
    <h2 style="font-size: 28px; font-weight: 700; margin-bottom: 8px;">Why teams choose <?php echo htmlspecialchars(getAppName()); ?></h2>
    <p style="color: #6a6a7a; font-size: 16px;">Everything you need, nothing you don't</p>
    <div class="trust-badges">
        <div class="trust-badge">
            <div class="icon">⚡</div>
            <span>Setup in minutes</span>
        </div>
        <div class="trust-badge">
            <div class="icon">🎁</div>
            <span>14-day free trial</span>
        </div>
        <div class="trust-badge">
            <div class="icon">💳</div>
            <span>No credit card</span>
        </div>
        <div class="trust-badge">
            <div class="icon">🔄</div>
            <span>Cancel anytime</span>
        </div>
    </div>
</section>

<!-- Registration Form -->
<section class="form-section" id="signup">
    <div class="form-card">
        <h2>Start your free trial</h2>
        <p class="subtitle">Create your account in 60 seconds. No credit card required.</p>

        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom:20px;padding:12px 16px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;color:#dc2626;font-size:14px;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="register_company">
            <input type="hidden" name="plan" id="selectedPlan" value="single">

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Company Name *</label>
                    <input type="text" name="company_name" class="form-control" placeholder="Acme Inc." required>
                </div>
                <div class="form-group">
                    <label class="form-label">Your Full Name *</label>
                    <input type="text" name="full_name" class="form-control" placeholder="John Doe" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Work Email *</label>
                    <input type="email" name="email" class="form-control" placeholder="you@company.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone (optional)</label>
                    <input type="tel" name="phone" class="form-control" placeholder="+1 234 567 890">
                </div>
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" placeholder="Min 8 characters" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password *</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
                </div>
            </div>

            <div style="margin-top:8px;">
                <label class="form-label">Selected Plan</label>
                <div id="planDisplay" style="padding:10px 14px;background:#f8f9fb;border-radius:8px;font-size:14px;font-weight:600;color:var(--color-primary);">Single User - $10/month</div>
            </div>

            <button type="submit" class="btn-submit">Create Account & Start Free Trial</button>
        </form>

        <p class="form-footer">
            Already have an account? <a href="/login.php">Sign in</a>
        </p>
        <p class="form-footer" style="margin-top:8px;">
            By signing up, you agree to our Terms of Service and Privacy Policy.
        </p>
    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <p>© 2026 <?php echo htmlspecialchars(getAppName()); ?>. All rights reserved. | <a href="/login.php">Sign In</a></p>
</footer>

<script>
function selectPlan(key, btn) {
    document.getElementById('selectedPlan').value = key;

    // Update button states
    document.querySelectorAll('.pricing-card .btn-select').forEach(b => {
        b.textContent = 'Select Plan';
        b.classList.remove('active');
    });
    btn.textContent = 'Selected';
    btn.classList.add('active');

    // Update plan display in form
    const plans = {
        'single': 'Single User - $10/month',
        'team': 'Team - $40/month',
        'enterprise': 'Enterprise - $90/month'
    };
    document.getElementById('planDisplay').textContent = plans[key] || key;

    // Scroll to form
    document.getElementById('signup').scrollIntoView({ behavior: 'smooth' });
}

// Auto-select first plan button
window.addEventListener('load', function() {
    const firstBtn = document.querySelector('.pricing-card .btn-select');
    if (firstBtn) firstBtn.classList.add('active');
});
</script>

</body>
</html>