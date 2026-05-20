<?php
/**
 * White Label CRM - Sandbox Configuration
 * SQLite mode for quick local testing without MySQL
 * 
 * Usage: Set USE_SQLITE=true in config/.env or define it before including this file
 */

if (!defined('USE_SQLITE')) {
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                if (trim($key) === 'USE_SQLITE' && strtolower(trim($value)) === 'true') {
                    define('USE_SQLITE', true);
                    break;
                }
            }
        }
    }
}

if (!defined('USE_SQLITE')) {
    define('USE_SQLITE', false);
}

if (USE_SQLITE) {
    // SQLite Configuration
    define('DB_HOST', '');
    define('DB_NAME', __DIR__ . '/../database/sandbox.db');
    define('DB_USER', '');
    define('DB_PASS', '');
    define('DB_CHARSET', 'utf8');
    
    // Override session name for sandbox
    define('SESSION_NAME', 'WL_CRM_SANDBOX');
}
