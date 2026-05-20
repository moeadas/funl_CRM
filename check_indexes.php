<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance();
$pdo = $db->getConnection();

echo "=== Checking existing tables with indexes ===\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    echo "\nTable: $t\n";
    try {
        $indexes = $pdo->query("SHOW INDEX FROM $t")->fetchAll();
        foreach ($indexes as $idx) {
            echo "  - Key: {$idx['Key_name']} / Column: {$idx['Column_name']} / Unique: {$idx['Non_unique']}\n";
        }
    } catch (Exception $e) {
        echo "  Error: {$e->getMessage()}\n";
    }
}
?>