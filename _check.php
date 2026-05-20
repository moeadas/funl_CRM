<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance();
$pdo = $db->getConnection();

echo "Plans table:\n";
$plans = $pdo->query("SELECT * FROM plans")->fetchAll(PDO::FETCH_ASSOC);
var_dump($plans);

echo "\nUsers:\n";
$users = $pdo->query("SELECT user_id, email, is_super_admin FROM users LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
var_dump($users);

echo "\nCompanies:\n";
$cos = $pdo->query("SELECT company_id, company_name, company_slug FROM companies LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
var_dump($cos);
?>