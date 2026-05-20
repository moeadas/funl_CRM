<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance();
$pdo = $db->getConnection();

echo "=== Finishing Setup ===\n\n";

// Seed plans
echo "1. Seeding plans...\n";
$plans = [
    ['single', 'Single User', 'Perfect for solo', 1, 10.00, 100.00, 0],
    ['team', 'Team', 'Growing teams', 5, 40.00, 400.00, 8],
    ['enterprise', 'Enterprise', 'Large teams', 15, 90.00, 900.00, 6],
];
foreach ($plans as $p) {
    $pdo->prepare("INSERT IGNORE INTO plans (plan_key, plan_name, description, user_limit, monthly_price, yearly_price, extra_user_price, is_active, sort_order) VALUES (?,?,?,?,?,?,?,1,?)")->execute($p);
    echo "   ✓ {$p[1]}\n";
}

// Verify plans
$count = $pdo->query("SELECT COUNT(*) FROM plans")->fetchColumn();
echo "   Plans count: $count\n";

// Create Pinpoint company
echo "\n2. Creating Pinpoint company...\n";
$companyId = null;
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT IGNORE INTO companies (company_name, company_slug, email, status, trial_ends_at, subscription_status, plan_id, plan_name, plan_user_limit, plan_price_monthly, extra_user_price) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute(['Pinpoint', 'pinpoint', 'admin@pinpoint.online', 'active', date('Y-m-d H:i:s', strtotime('+365 days')), 'active', 'enterprise', 'Enterprise', 999, 0, 0]);
    $companyId = $pdo->lastInsertId();
    $pdo->commit();
    echo "   ✓ Company created (ID: $companyId)\n";
} catch (Exception $e) {
    $pdo->rollBack();
    // Try to get existing
    try {
        $companyId = $pdo->query("SELECT company_id FROM companies WHERE company_slug='pinpoint'")->fetchColumn();
        echo "   - Company exists (ID: $companyId)\n";
    } catch (Exception $e2) {
        echo "   ERROR: " . $e2->getMessage() . "\n";
    }
}

if (!$companyId) {
    $companyId = $pdo->query("SELECT company_id FROM companies WHERE company_slug='pinpoint'")->fetchColumn();
}

echo "   Company ID: $companyId\n";

// Create super admin
echo "\n3. Creating super admin...\n";
if ($companyId) {
    try {
        $hash = password_hash('Pinpoint2024!', PASSWORD_BCRYPT);
        $pdo->prepare("INSERT IGNORE INTO users (company_id, username, email, password_hash, full_name, role, status, is_super_admin) VALUES (?,?,?,?,?,?,?,1)")->execute([$companyId, 'admin', 'admin@pinpoint.online', $hash, 'Pinpoint Admin', 'Admin', 'Active']);
        echo "   ✓ Super admin created\n";
        echo "   Email: admin@pinpoint.online\n";
        echo "   Pass:  Pinpoint2024!\n";
    } catch (Exception $e) {
        echo "   ERROR: " . $e->getMessage() . "\n";
    }
}

// Set default logo
echo "\n4. Setting default logo...\n";
try {
    $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('company_logo', 'pinpoint_logo.svg')")->execute();
    echo "   ✓ Default logo set\n";
} catch (Exception $e) {}

echo "\n=== DONE ===\n";
echo "Login: https://crm.pinpoint.online/login.php\n";
echo "Email: admin@pinpoint.online\n";
echo "Pass: Pinpoint2024!\n";
?>