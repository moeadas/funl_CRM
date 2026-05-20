<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance();
$pdo = $db->getConnection();

echo "Plans table structure:\n";
$cols = $pdo->query("DESCRIBE plans")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo "- {$c['Field']}: {$c['Type']} (null: {$c['Null']}, key: {$c['Key']}, default: {$c['Default']})\n";
}

echo "\nInsert test with explicit columns:\n";
try {
    $pdo->exec("DELETE FROM plans WHERE plan_key IN ('single','team','enterprise')");
    $pdo->exec("INSERT INTO plans (plan_key, plan_name, description, user_limit, monthly_price, yearly_price, extra_user_price, is_active, sort_order) VALUES ('single', 'Single User', 'Perfect for solo', 1, 10.00, 100.00, 0, 1, 1)");
    echo "✓ single\n";
    $pdo->exec("INSERT INTO plans (plan_key, plan_name, description, user_limit, monthly_price, yearly_price, extra_user_price, is_active, sort_order) VALUES ('team', 'Team', 'Growing teams', 5, 40.00, 400.00, 8, 1, 2)");
    echo "✓ team\n";
    $pdo->exec("INSERT INTO plans (plan_key, plan_name, description, user_limit, monthly_price, yearly_price, extra_user_price, is_active, sort_order) VALUES ('enterprise', 'Enterprise', 'Large teams', 15, 90.00, 900.00, 6, 1, 3)");
    echo "✓ enterprise\n";
} catch (Exception $e) {
    echo "ERROR: {$e->getMessage()}\n";
}

echo "\nPlans:\n";
$plans = $pdo->query("SELECT * FROM plans")->fetchAll(PDO::FETCH_ASSOC);
foreach ($plans as $p) {
    echo "- {$p['plan_key']}: {$p['plan_name']} (\${$p['monthly_price']}/mo)\n";
}
?>