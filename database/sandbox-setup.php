#!/usr/bin/env php
<?php
/**
 * White Label CRM - Sandbox Setup Script
 * Creates SQLite database, runs schema, seeds default data
 * Usage: php sandbox-setup.php
 */

require_once __DIR__ . '/../config/sandbox.php';

if (!USE_SQLITE) {
    echo "ERROR: Sandbox mode not enabled.\n";
    echo "Set USE_SQLITE=true in config/.env first.\n";
    exit(1);
}

$dbPath = DB_NAME;
$schemaFile = __DIR__ . '/sandbox-schema.sql';

echo "=== White Label CRM Sandbox Setup ===\n\n";

// Ensure database directory exists
$dir = dirname($dbPath);
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
    echo "Created directory: $dir\n";
}

// Check if database already exists
if (file_exists($dbPath)) {
    echo "Database exists: $dbPath\n";
    echo "Overwrite? (y/N): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    if (trim(strtolower($line)) !== 'y') {
        echo "Setup cancelled.\n";
        exit(0);
    }
    unlink($dbPath);
    echo "Removed existing database.\n";
}

// Create new database
echo "Creating SQLite database...\n";
try {
    $pdo = new PDO("sqlite:$dbPath", null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("PRAGMA foreign_keys = ON;");
    
    // Read and execute schema
    $sql = file_get_contents($schemaFile);
    if ($sql === false) {
        throw new Exception("Cannot read schema file: $schemaFile");
    }
    
    // Split by semicolons and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $stmt) {
        if (empty($stmt)) continue;
        $pdo->exec($stmt);
    }
    
    echo "✓ Schema applied successfully.\n";
    
    // Verify default admin user
    $admin = $pdo->query("SELECT user_id, username, full_name, role FROM users WHERE username = 'admin'")->fetch();
    if ($admin) {
        echo "✓ Default admin user created: {$admin['username']} ({$admin['full_name']})\n";
        echo "  Password: admin123\n";
    }
    
    // Count settings
    $settingsCount = $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
    echo "✓ Default settings created: $settingsCount\n";
    
    echo "\n=== Setup Complete ===\n";
    echo "Database: $dbPath\n";
    echo "Login: http://localhost:8000/login.php\n";
    echo "User: admin / admin123\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
