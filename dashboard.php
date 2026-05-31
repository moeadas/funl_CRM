<?php
/**
 * White Label CRM - Root Dashboard Redirect
 */
require_once __DIR__ . '/includes/auth.php';
startSecureSession();

header('Location: /pages/dashboard.php');
exit;
?>
