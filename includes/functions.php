<?php
/**
 * White Label CRM - Helper Functions
 */

// Load branding from settings
function getSetting($key, $default = '') {
    // M-8 fix: cache is keyed by the (company_id, impersonation state) tuple so
    // a switch-user mid-request (or any tenant change) gets a fresh load.
    // Previously the static was per-request, so an admin impersonating a tenant
    // would see the admin's settings for the rest of the request.
    $companyId = $_SESSION['company_id'] ?? null;
    $impersonating = !empty($_SESSION['impersonate_original_user_id']);
    $cacheKey = ($companyId ?? 'global') . '|' . ($impersonating ? '1' : '0');
    static $cache = [];   // [cacheKey => [key => value]]
    if (!isset($cache[$cacheKey])) {
        try {
            $db = Database::getInstance()->getConnection();
            if ($companyId) {
                $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE company_id = ? OR company_id IS NULL ORDER BY company_id ASC");
                $stmt->execute([$companyId]);
            } else {
                $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE company_id IS NULL");
            }
            $cache[$cacheKey] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $e) {
            $cache[$cacheKey] = [];
        }
    }
    return $cache[$cacheKey][$key] ?? $default;
}

// Alias for backward compatibility
function getSettingValue($key, $default = '') {
    return getSetting($key, $default);
}

function getAppName() {
    return getSetting('app_name', 'White Label CRM');
}

function getCompanyLogo() {
    $logo = getSetting('company_logo', '');
    if ($logo && file_exists(__DIR__ . '/../uploads/' . $logo)) {
        return '/uploads/' . htmlspecialchars($logo);
    }
    return '/assets/images/logo-default.png';
}

function getCompanyFavicon() {
    $favicon = getSetting('company_favicon', '');
    if ($favicon && file_exists(__DIR__ . '/../uploads/' . $favicon)) {
        return '/uploads/' . htmlspecialchars($favicon);
    }
    return '/assets/images/favicon.png';
}

// Custom Fields Helpers
function getActiveCustomFields() {
    try {
        $db = Database::getInstance()->getConnection();
        $companyId = getCurrentCompanyId();
        if ($companyId) {
            $stmt = $db->prepare("SELECT * FROM custom_fields WHERE is_active = 1 AND (company_id = ? OR company_id IS NULL) ORDER BY sort_order ASC, field_id ASC");
            $stmt->execute([$companyId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $stmt = $db->query("SELECT * FROM custom_fields WHERE is_active = 1 ORDER BY sort_order ASC, field_id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function getCustomFieldValue($leadId, $fieldId) {
    try {
        $db = Database::getInstance()->getConnection();
        $companyId = getCurrentCompanyId();
        // Tenant scope: field must belong to current company
        $stmt = $db->prepare("SELECT lcv.field_value FROM lead_custom_values lcv INNER JOIN custom_fields cf ON lcv.field_id = cf.field_id WHERE lcv.lead_id = ? AND lcv.field_id = ? AND (cf.company_id = ? OR cf.company_id IS NULL)");
        $stmt->execute([$leadId, $fieldId, $companyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['field_value'] : '';
    } catch (Exception $e) {
        return '';
    }
}

function getAllCustomFieldValues($leadId) {
    try {
        $db = Database::getInstance()->getConnection();
        $companyId = getCurrentCompanyId();
        $stmt = $db->prepare("
            SELECT cf.field_name, cf.field_label, cf.field_type, cf.field_options, lcv.field_value
            FROM custom_fields cf
            LEFT JOIN lead_custom_values lcv ON cf.field_id = lcv.field_id AND lcv.lead_id = ?
            WHERE cf.is_active = 1 AND (cf.company_id = ? OR cf.company_id IS NULL)
            ORDER BY cf.sort_order ASC
        ");
        $stmt->execute([$leadId, $companyId]);
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

            // Delete existing value (tenant scope: only delete values for fields owned by current company)
            $companyId = getCurrentCompanyId();
            $stmt = $db->prepare("DELETE lcv FROM lead_custom_values lcv INNER JOIN custom_fields cf ON lcv.field_id = cf.field_id WHERE lcv.lead_id = ? AND lcv.field_id = ? AND (cf.company_id = ? OR cf.company_id IS NULL)");
            $stmt->execute([$leadId, $field['field_id'], $companyId]);

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
    
    if ($diff < 60) return __('Just now');
    if ($diff < 3600) return sprintf(__('%d minutes ago'), floor($diff / 60));
    if ($diff < 86400) return sprintf(__('%d hours ago'), floor($diff / 3600));
    if ($diff < 604800) return sprintf(__('%d days ago'), floor($diff / 86400));
    if ($diff < 2592000) return sprintf(__('%d weeks ago'), floor($diff / 604800));
    
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
function jsonSuccess(string $message, ?array $data = null) {
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
 * M-6 fix: log the real exception details server-side and return a generic
 * message + an error_id the user can quote to support. Prevents SQL/schema
 * internals from leaking to API clients.
 *
 * Usage:
 *   try { ... } catch (Exception $e) { safeJsonError($e, 'Could not save lead'); }
 */
function safeJsonError(\Throwable $e, string $userMessage = 'An error occurred', int $code = 500) {
    $errorId = 'err_' . bin2hex(random_bytes(6));
    error_log("[$errorId] safeJsonError: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString());
    header('Content-Type: application/json');
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $userMessage . ' (ref: ' . $errorId . ')',
        'error_id' => $errorId
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
    $companyId = getCurrentCompanyId();
    if ($companyId) {
        $stmt = $db->prepare("SELECT user_id, full_name, role FROM users WHERE status = 'Active' AND (company_id = ? OR is_super_admin = 1) ORDER BY full_name");
        $stmt->execute([$companyId]);
    } else {
        $stmt = $db->query("SELECT user_id, full_name, role FROM users WHERE status = 'Active' ORDER BY full_name");
    }
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

// Encryption helpers for sensitive data (OAuth tokens, SMTP passwords)
// Uses libsodium if available, falls back to OpenSSL AES-256-GCM
function encryptToken(string $plaintext, ?string $key = null) {
    if ($key === null) {
        $key = getenv('APP_ENCRYPTION_KEY');
    }
    if (empty($key)) {
        // H-5: refuse to silently base64-encode secrets. Strict mode is now the
        // DEFAULT — the reversible base64 fallback is only allowed when explicitly
        // opted into for local dev (FUNL_STRICT_SECRETS=false OR USE_SQLITE sandbox).
        $strictEnv = getenv('FUNL_STRICT_SECRETS');
        $isSandbox = defined('USE_SQLITE') && USE_SQLITE;
        // Default to strict unless it's the SQLite sandbox or strict is explicitly disabled.
        $strict = ($strictEnv === false || $strictEnv === '')
            ? !$isSandbox
            : filter_var($strictEnv, FILTER_VALIDATE_BOOLEAN);
        if ($strict) {
            throw new \RuntimeException(
                'APP_ENCRYPTION_KEY is not set. Refusing to store secrets in reversible base64. ' .
                'Generate one with: php -r "echo bin2hex(random_bytes(32));" and set APP_ENCRYPTION_KEY in config/.env. ' .
                '(For local SQLite dev only, set FUNL_STRICT_SECRETS=false.)'
            );
        }
        error_log('encryptToken: No encryption key configured — using INSECURE base64 fallback (dev/sandbox only).');
        return base64_encode($plaintext); // Fallback (not encrypted!) — dev/sandbox only
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

function decryptToken(string $encrypted, ?string $key = null) {
    if ($key === null) {
        $key = getenv('APP_ENCRYPTION_KEY');
    }
    if (empty($key)) {
        // H-5: in strict mode, refuse to decode (the stored value would be base64 plaintext)
        $strict = filter_var(getenv('FUNL_STRICT_SECRETS'), FILTER_VALIDATE_BOOLEAN);
        if ($strict) {
            error_log('decryptToken: APP_ENCRYPTION_KEY unset + FUNL_STRICT_SECRETS=true; refusing to decode base64-stored secret');
            return '';
        }
        // Try base64 decode as fallback (legacy data written before APP_ENCRYPTION_KEY was set)
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

function __($key, $default = null) {
    if (empty($key)) return '';
    
    static $translations = [];
    
    // Get current language from session
    $lang = $_SESSION['language'] ?? 'en';
    if (!in_array($lang, ['en', 'ar'])) {
        $lang = 'en';
    }
    
    // Load language translation array if not already loaded
    if (!isset($translations[$lang])) {
        $langFile = __DIR__ . "/languages/{$lang}.php";
        if (file_exists($langFile)) {
            $translations[$lang] = include $langFile;
        } else {
            $translations[$lang] = [];
        }
    }
    
    // Normalize key: e.g. "Save Changes *" -> "save_changes"
    $cleanKey = trim(strtolower($key));
    $cleanKey = rtrim($cleanKey, '*:-?! ');
    $cleanKey = preg_replace('/[^a-z0-9]+/', '_', $cleanKey);
    $cleanKey = trim($cleanKey, '_');
    
    // 1. Try clean key in current language
    if (isset($translations[$lang][$cleanKey])) {
        return $translations[$lang][$cleanKey];
    }
    // 2. Try original key in current language (fallback)
    if (isset($translations[$lang][$key])) {
        return $translations[$lang][$key];
    }
    
    // Fallback to English dictionary if translation is missing in the chosen language
    if ($lang !== 'en') {
        if (!isset($translations['en'])) {
            $enFile = __DIR__ . "/languages/en.php";
            $translations['en'] = file_exists($enFile) ? include $enFile : [];
        }
        // 3. Try clean key in English
        if (isset($translations['en'][$cleanKey])) {
            return $translations['en'][$cleanKey];
        }
        // 4. Try original key in English
        if (isset($translations['en'][$key])) {
            return $translations['en'][$key];
        }
    }
    
    // If not found, return default, or formatted key (if it was a snake_case key to start with), or the original key
    if ($default !== null) {
        return $default;
    }
    
    if (strpos($key, ' ') === false && strpos($key, '_') !== false) {
        return str_replace('_', ' ', ucfirst($key));
    }
    
    return $key;
}