<?php
/**
 * White Label CRM - Security Helpers
 * Rate limiting, headers, sanitization, and hardening utilities
 */

/**
 * Apply security headers to all responses
 */
function applySecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
}

/**
 * Get client IP address (proxy-aware)
 */
function getClientIP() {
    $ip = '';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = trim($_SERVER['HTTP_X_REAL_IP']);
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/**
 * Simple in-memory rate limiter
 * Returns true if allowed, false if rate limited
 * 
 * Usage: if (!rateLimit('login', 5, 900)) { die('Too many attempts'); }
 * 
 * @param string $key Unique identifier (e.g. 'login', 'api_leads', 'password_reset')
 * @param int $maxAttempts Max attempts in the window
 * @param int $windowSeconds Time window in seconds
 * @param string $ip Override IP (optional)
 * @return bool True if allowed
 */
function rateLimit($key, $maxAttempts, $windowSeconds, $ip = null) {
    $ip = $ip ?: getClientIP();
    $cacheDir = sys_get_temp_dir() . '/wlrm_rate';
    
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    
    $cacheFile = $cacheDir . '/' . md5($key . '_' . $ip) . '.json';
    $now = time();
    
    $data = [];
    if (file_exists($cacheFile)) {
        $content = @file_get_contents($cacheFile);
        if ($content) {
            $data = json_decode($content, true) ?: [];
        }
    }
    
    // Remove old entries outside the window
    $data = array_filter($data, function($ts) use ($now, $windowSeconds) {
        return ($now - $ts) < $windowSeconds;
    });
    
    // Check if rate limited
    if (count($data) >= $maxAttempts) {
        return false;
    }
    
    // Record this attempt
    $data[] = $now;
    
    // Prune again after adding
    $data = array_filter($data, function($ts) use ($now, $windowSeconds) {
        return ($now - $ts) < $windowSeconds;
    });
    
    @file_put_contents($cacheFile, json_encode(array_values($data)));
    return true;
}

/**
 * Get remaining attempts for a rate limit key
 */
function rateLimitRemaining($key, $maxAttempts, $windowSeconds, $ip = null) {
    $ip = $ip ?: getClientIP();
    $cacheDir = sys_get_temp_dir() . '/wlrm_rate';
    $cacheFile = $cacheDir . '/' . md5($key . '_' . $ip) . '.json';
    $now = time();
    
    if (!file_exists($cacheFile)) {
        return $maxAttempts;
    }
    
    $content = @file_get_contents($cacheFile);
    if (!$content) return $maxAttempts;
    
    $data = json_decode($content, true) ?: [];
    $data = array_filter($data, function($ts) use ($now, $windowSeconds) {
        return ($now - $ts) < $windowSeconds;
    });
    
    return max(0, $maxAttempts - count($data));
}

/**
 * Clear rate limit for a key
 */
function rateLimitClear($key, $ip = null) {
    $ip = $ip ?: getClientIP();
    $cacheDir = sys_get_temp_dir() . '/wlrm_rate';
    $cacheFile = $cacheDir . '/' . md5($key . '_' . $ip) . '.json';
    if (file_exists($cacheFile)) {
        @unlink($cacheFile);
    }
}

/**
 * Sanitize a string input
 */
function sanitizeInputAdvanced($value, $type = 'text') {
    if ($value === null) return '';
    
    $value = trim($value);
    
    switch ($type) {
        case 'email':
            $value = filter_var($value, FILTER_SANITIZE_EMAIL);
            break;
        case 'url':
            $value = filter_var($value, FILTER_SANITIZE_URL);
            break;
        case 'int':
        case 'integer':
            $value = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
            break;
        case 'float':
            $value = filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            break;
        case 'html':
            // Allow no HTML by default - strip all tags
            $value = strip_tags($value);
            break;
        case 'phone':
            $value = preg_replace('/[^0-9+\-\s()]/', '', $value);
            break;
        case 'username':
            $value = preg_replace('/[^a-zA-Z0-9_\-.@]/', '', $value);
            break;
        case 'alphanumeric':
            $value = preg_replace('/[^a-zA-Z0-9]/', '', $value);
            break;
        case 'slug':
            $value = preg_replace('/[^a-z0-9-]/', '', strtolower($value));
            break;
        case 'text':
        default:
            // For text, we escape HTML but don't strip - use htmlspecialchars when outputting
            $value = strip_tags($value);
            break;
    }
    
    return $value;
}

/**
 * Validate email format more strictly
 */
function isValidEmailAdvanced($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) && preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email);
}

/**
 * Validate URL format
 */
function isValidUrlAdvanced($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Log security event
 */
function logSecurityEvent($type, $description, $userId = null, $ip = null) {
    $ip = $ip ?: getClientIP();
    try {
        $db = Database::getInstance();
        $db->insert('activity_log', [
            'company_id' => getCurrentCompanyId() ?: 0,
            'user_id' => $userId ?: 0,
            'action' => 'Security: ' . $type,
            'entity_type' => 'security',
            'details' => $description . ' | IP: ' . $ip,
        ]);
    } catch (Exception $e) {
        error_log("Security log error: " . $e->getMessage());
    }
}

/**
 * Require HTTPS - redirect if not on HTTPS
 */
function requireHTTPS() {
    if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return; // Proxied HTTPS is OK
        }
        if (php_sapi_name() === 'cli') {
            return; // Allow CLI
        }
        // In production, uncomment this:
        // header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        // exit;
    }
}

/**
 * Check if request is an AJAX/API request
 */
function isApiRequestAdvanced() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        || !empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
        || in_array(pathinfo($_SERVER['PHP_SELF'], PATHINFO_FILENAME), ['api_leads', 'stripe-webhook', 'stripe-checkout']);
}

/**
 * Send JSON response (for API endpoints)
 */
function jsonResponseAdvanced($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Generate a cryptographically secure random token
 */
function secureTokenAdvanced($bytes = 32) {
    return bin2hex(random_bytes($bytes));
}

/**
 * Time-constant string comparison (prevents timing attacks)
 */
function secureCompare($a, $b) {
    return hash_equals($a, $b);
}

/**
 * Validate CSRF token with timing check
 * Tokens older than 2 hours are invalid
 */
function validateCSRFTokenWithExpiry($token, $maxAgeSeconds = 7200) {
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    
    if (!$sessionToken || !$token) {
        return false;
    }
    
    if (!secureCompare($sessionToken, $token)) {
        return false;
    }
    
    // Check token age if timestamp stored
    if (isset($_SESSION['csrf_token_time'])) {
        $age = time() - $_SESSION['csrf_token_time'];
        if ($age > $maxAgeSeconds) {
            return false;
        }
    }
    
    return true;
}

/**
 * Require API authentication for API routes
 */
function requireApiAuth() {
    header('Content-Type: application/json');
    
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
    $expectedKey = getenv('API_KEY') ?: '';
    
    if (!$expectedKey) {
        // No API key configured - fall back to session auth
        if (!isLoggedIn()) {
            jsonResponse(['error' => 'Unauthorized'], 401);
        }
        return;
    }
    
    if (!$apiKey || !secureCompare($apiKey, $expectedKey)) {
        jsonResponse(['error' => 'Invalid API key'], 401);
    }
}

/**
 * Password strength validator
 * Returns array of errors
 */
function validatePasswordStrength($password) {
    $errors = [];
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }
    return $errors;
}

/**
 * Hash a value for URL-safe storage
 */
function hashForUrlAdvanced($value, $salt = '') {
    $salt = $salt ?: getenv('APP_URL') ?: 'default-salt';
    return rtrim(strtr(base64_encode(hash('sha256', $value . $salt, true)), '=/'), '.');
}

/**
 * Verify URL-safe hash
 */
function verifyUrlHashAdvanced($value, $hash, $salt = '') {
    return hash_equals($hash, hashForUrl($value, $salt));
}

// Apply security headers on every request
applySecurityHeaders();