<?php
/**
 * White Label CRM - Migration Guard (H-2 fix)
 *
 * Defense-in-depth for one-off migration / schema scripts.
 *
 * Previously these scripts were protected ONLY by .htaccess rules. On nginx,
 * on Apache with `AllowOverride None`, or if the .htaccess is lost during a
 * deploy, the scripts would become public, unauthenticated, schema-altering
 * HTTP endpoints. This guard closes that hole in code:
 *
 *   - CLI execution (php migrate_xyz.php) is always allowed.
 *   - Web execution requires a logged-in Super Admin.
 *   - As an alternative for automated/headless runs, a MIGRATION_TOKEN env var
 *     may be set and passed as ?token=... (compared in constant time).
 *
 * Include this immediately after config/database.php in every migration script.
 */

if (PHP_SAPI === 'cli') {
    // Running from the command line — trusted.
    return;
}

// Optional shared-secret bypass for headless/automated runs.
$migrationToken = getenv('MIGRATION_TOKEN') ?: '';
if ($migrationToken !== '') {
    $provided = $_GET['token'] ?? $_SERVER['HTTP_X_MIGRATION_TOKEN'] ?? '';
    if (is_string($provided) && $provided !== '' && hash_equals($migrationToken, $provided)) {
        return;
    }
}

// Otherwise require an authenticated Super Admin session.
require_once __DIR__ . '/auth.php';
startSecureSession();

if (!isSuperAdmin()) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    die("Access denied. Migration scripts require Super Admin access (or CLI execution).");
}
