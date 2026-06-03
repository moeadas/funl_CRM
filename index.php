<?php
/**
 * White Label CRM - Index / Root Redirect
 */
require_once __DIR__ . '/includes/auth.php';
startSecureSession();

if (isLoggedIn()) {
    header('Location: /pages/dashboard.php');
    exit;
}

// Logged-out users: if a marketing URL is configured (Super Admin > Platform
// Settings), send them to that WordPress site. Otherwise fall back to /register.php
// for self-hosted marketing-less installs.
try {
    $db = Database::getInstance()->getConnection();
    $row = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'marketing_url' AND company_id IS NULL")->fetch();
    $marketingUrl = $row['setting_value'] ?? '';
    if (!empty($marketingUrl) && filter_var($marketingUrl, FILTER_VALIDATE_URL)) {
        header('Location: ' . $marketingUrl);
        exit;
    }
} catch (Exception $e) {
    // ignore
}

header('Location: /register.php');
exit;
