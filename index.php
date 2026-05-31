<?php
/**
 * White Label CRM - Index / Root Redirect
 */
require_once __DIR__ . '/includes/auth.php';
startSecureSession();

if (isLoggedIn()) {
    header('Location: /pages/dashboard.php');
} else {
    header('Location: /register.php');
}
exit;
