<?php
/**
 * White Label CRM - Production Setup Script (v3)
 * Only creates what's missing - idempotent
 */
require_once __DIR__ . '/config/database.php';

$allowedHosts = ['localhost', '127.0.0.1', '::1', 'crm.pinpoint.online', 'www.crm.pinpoint.online'];
$isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', $allowedHosts) || php_sapi_name() === 'cli';
if (!$isLocal) { die('Access denied'); }

echo "=== Production Setup v3 ===\n\n";

$db = Database::getInstance();
$pdo = $db->getConnection();

// Check existing tables
$existingTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$existingCols = [];
foreach (['users', 'leads', 'interactions', 'settings', 'activity_log'] as $t) {
    try {
        $existingCols[$t] = $pdo->query("SHOW COLUMNS FROM $t")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $existingCols[$t] = [];
    }
}

echo "1. Adding company_id to users...\n";
if (!in_array('company_id', $existingCols['users'])) {
    $pdo->exec("ALTER TABLE users ADD COLUMN company_id INT DEFAULT NULL AFTER status");
    echo "   ✓ company_id added\n";
} else {
    echo "   - company_id exists\n";
}

echo "\n2. Adding is_super_admin to users...\n";
if (!in_array('is_super_admin', $existingCols['users'])) {
    $pdo->exec("ALTER TABLE users ADD COLUMN is_super_admin TINYINT(1) DEFAULT 0 AFTER company_id");
    echo "   ✓ is_super_admin added\n";
} else {
    echo "   - is_super_admin exists\n";
}

echo "\n3. Adding company_id to leads...\n";
if (!in_array('company_id', $existingCols['leads'])) {
    $pdo->exec("ALTER TABLE leads ADD COLUMN company_id INT DEFAULT NULL AFTER lead_id");
    echo "   ✓ company_id added\n";
} else {
    echo "   - company_id exists\n";
}

echo "\n4. Adding company_id to interactions...\n";
if (!in_array('company_id', $existingCols['interactions'])) {
    $pdo->exec("ALTER TABLE interactions ADD COLUMN company_id INT DEFAULT NULL AFTER interaction_id");
    echo "   ✓ company_id added\n";
} else {
    echo "   - company_id exists\n";
}

echo "\n5. Adding company_id to settings...\n";
if (!in_array('company_id', $existingCols['settings'])) {
    $pdo->exec("ALTER TABLE settings ADD COLUMN company_id INT DEFAULT NULL AFTER setting_id");
    echo "   ✓ company_id added\n";
} else {
    echo "   - company_id exists\n";
}

echo "\n6. Adding company_id to activity_log...\n";
if (!in_array('company_id', $existingCols['activity_log'])) {
    $pdo->exec("ALTER TABLE activity_log ADD COLUMN company_id INT DEFAULT NULL AFTER log_id");
    echo "   ✓ company_id added\n";
} else {
    echo "   - company_id exists\n";
}

// Create new SaaS tables
echo "\n7. Creating email_verifications...\n";
if (!in_array('email_verifications', $existingTables)) {
    $pdo->exec("CREATE TABLE email_verifications (
        id INT NOT NULL AUTO_INCREMENT,
        user_id INT NOT NULL,
        email VARCHAR(100) NOT NULL,
        token VARCHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        verified_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY token_idx (token),
        KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "   ✓ email_verifications created\n";
} else {
    echo "   - email_verifications exists\n";
}

echo "\n8. Creating password_resets...\n";
if (!in_array('password_resets', $existingTables)) {
    $pdo->exec("CREATE TABLE password_resets (
        id INT NOT NULL AUTO_INCREMENT,
        email VARCHAR(100) NOT NULL,
        token VARCHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY token_idx (token),
        KEY email_idx (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "   ✓ password_resets created\n";
} else {
    echo "   - password_resets exists\n";
}

echo "\n9. Creating company_documents...\n";
if (!in_array('company_documents', $existingTables)) {
    $pdo->exec("CREATE TABLE company_documents (
        document_id INT NOT NULL AUTO_INCREMENT,
        company_id INT NOT NULL,
        title VARCHAR(200) NOT NULL,
        description TEXT DEFAULT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_type VARCHAR(50) DEFAULT NULL,
        file_size INT DEFAULT NULL,
        category VARCHAR(50) DEFAULT 'General',
        uploaded_by INT DEFAULT NULL,
        download_count INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (document_id),
        KEY company_id (company_id),
        KEY category (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "   ✓ company_documents created\n";
} else {
    echo "   - company_documents exists\n";
}

echo "\n10. Creating company_invites...\n";
if (!in_array('company_invites', $existingTables)) {
    $pdo->exec("CREATE TABLE company_invites (
        invite_id INT NOT NULL AUTO_INCREMENT,
        company_id INT NOT NULL,
        email VARCHAR(100) NOT NULL,
        role ENUM('Admin','Sales Manager','Sales Rep','Viewer') NOT NULL DEFAULT 'Sales Rep',
        token VARCHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        accepted_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (invite_id),
        KEY token_idx (token),
        KEY company_id (company_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "   ✓ company_invites created\n";
} else {
    echo "   - company_invites exists\n";
}

echo "\n11. Creating stripe_events...\n";
if (!in_array('stripe_events', $existingTables)) {
    $pdo->exec("CREATE TABLE stripe_events (
        id INT NOT NULL AUTO_INCREMENT,
        stripe_event_id VARCHAR(100) DEFAULT NULL,
        event_type VARCHAR(100) NOT NULL,
        company_id INT DEFAULT NULL,
        data TEXT DEFAULT NULL,
        processed_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY stripe_event_id (stripe_event_id),
        KEY event_type (event_type),
        KEY company_id (company_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "   ✓ stripe_events created\n";
} else {
    echo "   - stripe_events exists\n";
}

// Seed plans
echo "\n12. Seeding plans...\n";
$plans = [
    ['single', 'Single User', 'Perfect for solo', 1, 10.00, 100.00, 0],
    ['team', 'Team', 'Growing teams', 5, 40.00, 400.00, 8],
    ['enterprise', 'Enterprise', 'Large teams', 15, 90.00, 900.00, 6],
];
foreach ($plans as $p) {
    $pdo->prepare("INSERT IGNORE INTO plans (plan_key, plan_name, description, user_limit, monthly_price, yearly_price, extra_user_price, is_active, sort_order) VALUES (?,?,?,?,?,?,?,1,?)")->execute($p);
    echo "   ✓ {$p[1]}\n";
}

// Create super admin
echo "\n13. Creating super admin...\n";
$companyId = null;
try {
    $stmt = $pdo->prepare("INSERT IGNORE INTO companies (company_name, company_slug, email, status, trial_ends_at, subscription_status, plan_id, plan_name, plan_user_limit, plan_price_monthly, extra_user_price) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute(['Pinpoint', 'pinpoint', 'admin@pinpoint.online', 'active', date('Y-m-d H:i:s', strtotime('+365 days')), 'active', 'enterprise', 'Enterprise', 999, 0, 0]);
    $companyId = $pdo->lastInsertId() ?: $pdo->query("SELECT company_id FROM companies WHERE company_slug='pinpoint'")->fetchColumn();
} catch (Exception $e) {}

if (!$companyId) {
    $companyId = $pdo->query("SELECT company_id FROM companies WHERE company_slug='pinpoint'")->fetchColumn();
}

if ($companyId) {
    try {
        $hash = password_hash('Pinpoint2024!', PASSWORD_BCRYPT);
        $pdo->prepare("INSERT IGNORE INTO users (company_id, username, email, password_hash, full_name, role, status, is_super_admin) VALUES (?,?,?,?,?,?,?,1)")->execute([$companyId, 'admin', 'admin@pinpoint.online', $hash, 'Pinpoint Admin', 'Admin', 'Active']);
        echo "   ✓ Super admin: admin@pinpoint.online / Pinpoint2024!\n";
    } catch (Exception $e) {
        echo "   - Super admin: " . $e->getMessage() . "\n";
    }
}

// Set default logo
echo "\n14. Setting default logo...\n";
try {
    $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('company_logo', 'pinpoint_logo.svg')")->execute();
    echo "   ✓ Default logo set\n";
} catch (Exception $e) {}

echo "\n=== DONE ===\n";
echo "Login: https://crm.pinpoint.online/login.php\n";
echo "⚠️  DELETE setup.php & check_tables.php!\n";
?>