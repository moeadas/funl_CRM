<?php
/**
 * White Label CRM - Helper Functions
 */

// Load branding from settings
function getSetting($key, $default = '') {
    static $settings = null;
    if ($settings === null) {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $e) {
            $settings = [];
        }
    }
    return $settings[$key] ?? $default;
}

function getAppName() {
    return getSetting('app_name', 'White Label CRM');
}

function getCompanyLogo() {
    $logo = getSetting('company_logo', '');
    if ($logo && file_exists(__DIR__ . '/../uploads/' . $logo)) {
        return '/uploads/' . htmlspecialchars($logo);
    }
    return '/assets/images/logo-default.svg';
}

function getCompanyFavicon() {
    $favicon = getSetting('company_favicon', '');
    if ($favicon && file_exists(__DIR__ . '/../uploads/' . $favicon)) {
        return '/uploads/' . htmlspecialchars($favicon);
    }
    return '/assets/images/favicon.svg';
}

// Custom Fields Helpers
function getActiveCustomFields() {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT * FROM custom_fields WHERE is_active = 1 ORDER BY sort_order ASC, field_id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function getCustomFieldValue($leadId, $fieldId) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT field_value FROM lead_custom_values WHERE lead_id = ? AND field_id = ?");
        $stmt->execute([$leadId, $fieldId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['field_value'] : '';
    } catch (Exception $e) {
        return '';
    }
}

function getAllCustomFieldValues($leadId) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT cf.field_name, cf.field_label, cf.field_type, cf.field_options, lcv.field_value
            FROM custom_fields cf
            LEFT JOIN lead_custom_values lcv ON cf.field_id = lcv.field_id AND lcv.lead_id = ?
            WHERE cf.is_active = 1
            ORDER BY cf.sort_order ASC
        ");
        $stmt->execute([$leadId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function renderCustomFieldInput($field, $value = '') {
    $name = 'custom_' . $field['field_name'];
    $label = htmlspecialchars($field['field_label']);
    $required = $field['is_required'] ? ' required' : '';
    $value = htmlspecialchars($value ?? '');
    $options = $field['field_options'] ? json_decode($field['field_options'], true) : [];

    $html = '<div class="form-group">';
    $html .= '<label class="form-label">' . $label . ($field['is_required'] ? ' <span class="required">*</span>' : '') . '</label>';

    switch ($field['field_type']) {
        case 'textarea':
            $html .= '<textarea name="' . $name . '" class="form-control" rows="3"' . $required . '>' . $value . '</textarea>';
            break;
        case 'select':
            $html .= '<select name="' . $name . '" class="form-control"' . $required . '>';
            $html .= '<option value="">Select ' . $label . '</option>';
            foreach ($options as $opt) {
                $selected = $value === $opt ? ' selected' : '';
                $html .= '<option value="' . htmlspecialchars($opt) . '"' . $selected . '>' . htmlspecialchars($opt) . '</option>';
            }
            $html .= '</select>';
            break;
        case 'checkbox':
            $checked = $value ? ' checked' : '';
            $html .= '<label class="form-check"><input type="checkbox" name="' . $name . '" value="1"' . $checked . '><span class="form-check-label">' . $label . '</span></label>';
            break;
        case 'number':
            $html .= '<input type="number" name="' . $name . '" class="form-control" value="' . $value . '"' . $required . '>';
            break;
        case 'date':
            $html .= '<input type="date" name="' . $name . '" class="form-control" value="' . $value . '"' . $required . '>';
            break;
        case 'email':
            $html .= '<input type="email" name="' . $name . '" class="form-control" value="' . $value . '" placeholder="email@example.com"' . $required . '>';
            break;
        case 'url':
            $html .= '<input type="url" name="' . $name . '" class="form-control" value="' . $value . '" placeholder="https://"' . $required . '>';
            break;
        case 'tel':
            $html .= '<input type="tel" name="' . $name . '" class="form-control" value="' . $value . '"' . $required . '>';
            break;
        default: // text
            $html .= '<input type="text" name="' . $name . '" class="form-control" value="' . $value . '"' . $required . '>';
    }

    $html .= '</div>';
    return $html;
}

function saveCustomFieldValues($leadId, $postData) {
    try {
        $db = Database::getInstance()->getConnection();
        $customFields = getActiveCustomFields();

        foreach ($customFields as $field) {
            $fieldName = 'custom_' . $field['field_name'];
            $fieldValue = $postData[$fieldName] ?? '';

            // For checkboxes, handle unchecked state
            if ($field['field_type'] === 'checkbox' && !isset($postData[$fieldName])) {
                $fieldValue = '0';
            }

            // Delete existing value
            $stmt = $db->prepare("DELETE FROM lead_custom_values WHERE lead_id = ? AND field_id = ?");
            $stmt->execute([$leadId, $field['field_id']]);

            // Insert new value if not empty
            if ($fieldValue !== '' && $fieldValue !== null) {
                $stmt = $db->prepare("INSERT INTO lead_custom_values (lead_id, field_id, field_value) VALUES (?, ?, ?)");
                $stmt->execute([$leadId, $field['field_id'], $fieldValue]);
            }
        }
    } catch (Exception $e) {
        error_log("Error saving custom fields: " . $e->getMessage());
    }
}

/**
 * Format date
 */
function formatDate($date, $format = 'M d, Y') {
    if (!$date) return '-';
    return date($format, strtotime($date));
}

/**
 * Format datetime
 */
function formatDateTime($datetime, $format = 'M d, Y h:i A') {
    if (!$datetime) return '-';
    return date($format, strtotime($datetime));
}

/**
 * Get time ago
 */
function timeAgo($datetime) {
    if (!$datetime) return '-';
    
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    if ($diff < 2592000) return floor($diff / 604800) . ' weeks ago';
    
    return formatDate($datetime);
}

/**
 * Get lead status badge class
 */
function getStatusBadgeClass($status) {
    $classes = [
        'New Lead' => 'bg-blue-100 text-blue-800',
        'Contacted' => 'bg-indigo-100 text-indigo-800',
        'Interested' => 'bg-green-100 text-green-800',
        'Not Interested' => 'bg-red-100 text-red-800',
        'Schedule Call' => 'bg-yellow-100 text-yellow-800',
        'Call Scheduled' => 'bg-purple-100 text-purple-800',
        'Demo Scheduled' => 'bg-pink-100 text-pink-800',
        'Proposal Sent' => 'bg-orange-100 text-orange-800',
        'Negotiation' => 'bg-teal-100 text-teal-800',
        'Won' => 'bg-green-500 text-white',
        'Lost' => 'bg-gray-500 text-white',
        'On Hold' => 'bg-gray-300 text-gray-800'
    ];
    
    return $classes[$status] ?? 'bg-gray-100 text-gray-800';
}

/**
 * Get priority badge class
 */
function getPriorityBadgeClass($priority) {
    $classes = [
        'Low' => 'bg-gray-100 text-gray-800',
        'Medium' => 'bg-blue-100 text-blue-800',
        'High' => 'bg-orange-100 text-orange-800',
        'Urgent' => 'bg-red-500 text-white'
    ];
    
    return $classes[$priority] ?? 'bg-gray-100 text-gray-800';
}

/**
 * Truncate text
 */
function truncate($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Get file size formatted
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}

/**
 * Generate random string
 */
function generateRandomString($length = 16) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Get initials from name
 */
function getInitials($name) {
    $words = explode(' ', $name);
    $initials = '';
    
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper($word[0]);
        }
        if (strlen($initials) >= 2) break;
    }
    
    return $initials ?: '??';
}

/**
 * Get avatar color based on name
 */
function getAvatarColor($name) {
    $colors = [
        'bg-red-500', 'bg-orange-500', 'bg-yellow-500', 'bg-green-500',
        'bg-teal-500', 'bg-blue-500', 'bg-indigo-500', 'bg-purple-500', 'bg-pink-500'
    ];
    
    $hash = 0;
    for ($i = 0; $i < strlen($name); $i++) {
        $hash = ord($name[$i]) + (($hash << 5) - $hash);
    }
    
    return $colors[abs($hash) % count($colors)];
}

/**
 * Success response JSON
 */
function jsonSuccess($message, $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

/**
 * Error response JSON
 */
function jsonError($message, $code = 400) {
    header('Content-Type: application/json');
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
    exit;
}

/**
 * Validate required fields
 */
function validateRequired($data, $requiredFields) {
    $errors = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    return $errors;
}

/**
 * Get all users for dropdown
 */
function getAllUsers() {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT user_id, full_name, role FROM users WHERE status = 'Active' ORDER BY full_name");
    return $stmt->fetchAll();
}

/**
 * Get user name by ID
 */
function getUserNameById($userId) {
    if (!$userId) return '-';
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT full_name FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    return $user ? $user['full_name'] : '-';
}

/**
 * Convert array to CSV
 */
function arrayToCSV($data, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    if (!empty($data)) {
        // Header row
        fputcsv($output, array_keys($data[0]));
        
        // Data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}

/**
 * Format phone number
 */
function formatPhone($phone) {
    if (!$phone) return '-';
    
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Format based on length
    $length = strlen($phone);
    
    if ($length == 10) {
        return preg_replace('/(\d{3})(\d{3})(\d{4})/', '($1) $2-$3', $phone);
    } elseif ($length == 11) {
        return preg_replace('/(\d{1})(\d{3})(\d{3})(\d{4})/', '+$1 ($2) $3-$4', $phone);
    }
    
    return $phone;
}
?>

// Encryption helpers for sensitive data (OAuth tokens, SMTP passwords)
// Uses libsodium if available, falls back to OpenSSL AES-256-GCM
function encryptToken($plaintext, $key = null) {
    if ($key === null) {
        $key = getenv('APP_ENCRYPTION_KEY');
    }
    if (empty($key)) {
        error_log('encryptToken: No encryption key configured');
        return base64_encode($plaintext); // Fallback (not encrypted!)
    }
    
    if (extension_loaded('sodium')) {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, hash('sha256', $key, true));
        return base64_encode($nonce . $ciphertext);
    } else {
        // OpenSSL fallback
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($plaintext, 'AES-256-GCM', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        return base64_encode($iv . $tag . $encrypted);
    }
}

function decryptToken($encrypted, $key = null) {
    if ($key === null) {
        $key = getenv('APP_ENCRYPTION_KEY');
    }
    if (empty($key)) {
        // Try base64 decode as fallback
        $decoded = base64_decode($encrypted, true);
        return $decoded !== false ? $decoded : '';
    }
    
    $data = base64_decode($encrypted, true);
    if ($data === false) return '';
    
    if (extension_loaded('sodium')) {
        $nonceLen = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        if (strlen($data) < $nonceLen) return '';
        $nonce = substr($data, 0, $nonceLen);
        $ciphertext = substr($data, $nonceLen);
        $decrypted = sodium_crypto_secretbox_open($ciphertext, $nonce, hash('sha256', $key, true));
        return $decrypted !== false ? $decrypted : '';
    } else {
        // OpenSSL fallback
        $iv = substr($data, 0, 16);
        $tag = substr($data, 16, 16);
        $ciphertext = substr($data, 32);
        $decrypted = openssl_decrypt($ciphertext, 'AES-256-GCM', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv, $tag);
        return $decrypted !== false ? $decrypted : '';
    }
}
