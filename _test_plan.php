<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance();
$pdo = $db->getConnection();

echo "Testing plan insert...\n";
try {
    $stmt = $pdo->prepare("INSERT INTO plans (plan_key, plan_name, description, user_limit, monthly_price, yearly_price, extra_user_price, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)");
    $stmt->execute(['test99', 'Test Plan', 'Test', 1, 10, 100, 0, 1]);
    echo "Insert OK, ID: " . $pdo->lastInsertId() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nPlans count: " . $pdo->query("SELECT COUNT(*) FROM plans")->fetchColumn() . "\n";

$plans = $pdo->query("SELECT * FROM plans")->fetchAll(PDO::FETCH_ASSOC);
foreach ($plans as $p) {
    echo "- {$p['plan_key']}: {$p['plan_name']}\n";
}
?>