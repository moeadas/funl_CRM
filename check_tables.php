<?php
// Quick table checker - upload and run this
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance();
$pdo = $db->getConnection();

echo "=== Existing Tables ===\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) echo "- $t\n";

echo "\n=== companies columns ===\n";
try {
    $cols = $pdo->query("SHOW COLUMNS FROM companies")->fetchAll(PDO::FETCH_COLUMN);
    echo implode(", ", $cols) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== users columns ===\n";
$cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
echo implode(", ", $cols) . "\n";
?>