<?php
/**
 * api/ni-checkout-helpers.php
 * 
 * Shared helpers for NI Gateway checkout + webhook handlers.
 */

if (!function_exists('getNIGatewaySettings')) {
    function getNIGatewaySettings() {
        $db = Database::getInstance();
        $rows = $db->query(
            "SELECT setting_key, setting_value FROM settings WHERE company_id = 0 AND setting_key LIKE 'ni_%'"
        )->fetchAll(PDO::FETCH_KEY_PAIR);
        return $rows;
    }
}

if (!function_exists('dbLastInsertId')) {
    function dbLastInsertId() {
        $db = Database::getInstance();
        return $db->getConnection()->lastInsertId();
    }
}
