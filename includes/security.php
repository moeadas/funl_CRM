<?php
/**
* White Label CRM - Security Helpers
* PATCHED: verifyUrlHashAdvanced, requireApiAuth, validatePasswordStrength, logSecurityEvent
*/
function applySecurityHeaders(): void {
if (headers_sent()) return;
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-crossorigin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}
/**
 * L-3 fix: only trust X-Forwarded-For / X-Real-IP when the direct peer
 * (REMOTE_ADDR) is a known/trusted proxy. Otherwise an attacker can spoof
 * these headers to forge their IP and bypass IP-based rate limiting on
 * login, registration, password reset, and public webhooks.
 *
 * Configure trusted proxies via the TRUSTED_PROXIES env var (comma-separated
 * IPs or CIDR ranges). When unset, ONLY REMOTE_ADDR is trusted (safe default).
 * Loopback/private ranges are trusted as proxies by default since a typical
 * Apache/nginx reverse proxy sits in front on the same host/LAN.
 */
function isTrustedProxy(string $remoteAddr): bool {
    $trusted = getenv('TRUSTED_PROXIES');
    if ($trusted !== false && trim($trusted) !== '') {
        foreach (array_map('trim', explode(',', $trusted)) as $entry) {
            if ($entry === '') continue;
            if (ipInCidr($remoteAddr, $entry)) return true;
        }
        return false;
    }
    // Default: trust loopback + RFC1918 private ranges (same-host/LAN proxy).
    $defaults = ['127.0.0.0/8', '::1/128', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'];
    foreach ($defaults as $cidr) {
        if (ipInCidr($remoteAddr, $cidr)) return true;
    }
    return false;
}

/**
 * Check whether an IP is inside a CIDR range (supports a bare IP as /32 //128).
 */
function ipInCidr(string $ip, string $cidr): bool {
    if (strpos($cidr, '/') === false) {
        return $ip === $cidr; // exact match
    }
    [$subnet, $maskLen] = explode('/', $cidr, 2);
    $maskLen = (int)$maskLen;
    $ipBin = @inet_pton($ip);
    $subnetBin = @inet_pton($subnet);
    if ($ipBin === false || $subnetBin === false) return false;
    if (strlen($ipBin) !== strlen($subnetBin)) return false; // mixed v4/v6
    $bytes = intdiv($maskLen, 8);
    $bits  = $maskLen % 8;
    if ($bytes > 0 && strncmp($ipBin, $subnetBin, $bytes) !== 0) return false;
    if ($bits === 0) return true;
    $mask = chr(0xff << (8 - $bits) & 0xff);
    return (($ipBin[$bytes] & $mask) === ($subnetBin[$bytes] & $mask));
}

function getClientIP(): string {
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    $ip = $remoteAddr;

    // Only consult forwarding headers if the direct peer is a trusted proxy.
    if ($remoteAddr !== '' && isTrustedProxy($remoteAddr)) {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Take the left-most (original client) entry.
            $candidate = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) $ip = $candidate;
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $candidate = trim($_SERVER['HTTP_X_REAL_IP']);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) $ip = $candidate;
        }
    }

    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}
/**
 * Simple file-based fixed-window rate limiter.
 *
 * L-8 NOTE: state is stored on the local filesystem (system temp dir), so it is
 * PER-APP-SERVER. Correct for single-host shared hosting (the intended target).
 * Behind a multi-node load balancer, replace the file store with a shared
 * Redis/DB backend (or use sticky sessions) so limits are enforced globally.
 * See TRUSTED_PROXIES / "Rate Limiting Backend" in config/.env.example.
 */
function rateLimit(string $key, int $maxAttempts, int $windowSeconds, ?string $ip = null): bool {
$ip = $ip ?: getClientIP();
$cacheDir = sys_get_temp_dir() . '/wlrm_rate';
if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0750, true); }
$cacheFile = $cacheDir . '/' . md5($key . '_' . $ip) . '.json';
$now = time();
$data = [];
if (is_readable($cacheFile)) {
$raw = @file_get_contents($cacheFile);
$data = $raw ? (json_decode($raw, true) ?: []) : [];
}
$data = array_values(array_filter($data, fn($ts) => ($now - $ts) < $windowSeconds));
if (count($data) >= $maxAttempts) { return false; }
$data[] = $now;
@file_put_contents($cacheFile, json_encode($data), LOCK_EX);
return true;
}
function rateLimitRemaining(string $key, int $maxAttempts, int $windowSeconds, ?string $ip = null): int {
$ip = $ip ?: getClientIP();
$cacheDir = sys_get_temp_dir() . '/wlrm_rate';
$cacheFile = $cacheDir . '/' . md5($key . '_' . $ip) . '.json';
$now = time();
if (!is_readable($cacheFile)) { return $maxAttempts; }
$raw = @file_get_contents($cacheFile);
$data = $raw ? (json_decode($raw, true) ?: []) : [];
$data = array_filter($data, fn($ts) => ($now - $ts) < $windowSeconds);
return max(0, $maxAttempts - count($data));
}
function rateLimitClear(string $key, ?string $ip = null): void {
$ip = $ip ?: getClientIP();
$cacheDir = sys_get_temp_dir() . '/wlrm_rate';
$cacheFile = $cacheDir . '/' . md5($key . '_' . $ip) . '.json';
if (file_exists($cacheFile)) { @unlink($cacheFile); }
}
function sanitizeInputAdvanced($value, string $type = 'text'): string {
if ($value === null) return '';
$value = trim((string)$value);
switch ($type) {
case 'email':
return (string)filter_var($value, FILTER_SANITIZE_EMAIL);
case 'url':
return (string)filter_var($value, FILTER_SANITIZE_URL);
case 'int':
case 'integer':
return (string)filter_var($value, FILTER_SANITIZE_NUMBER_INT);
case 'float':
return (string)filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
case 'html':
return strip_tags($value);
case 'phone':
return preg_replace('/[^0-9+\-\s()]/', '', $value);
case 'username':
return preg_replace('/[^a-zA-Z0-9_\-.@]/', '', $value);
case 'alphanumeric':
return preg_replace('/[^a-zA-Z0-9]/', '', $value);
case 'slug':
return preg_replace('/[^a-z0-9-]/', '', strtolower($value));
case 'text':
default:
return strip_tags($value);
}
}
function isValidEmailAdvanced(string $email): bool {
return (bool)(filter_var($email, FILTER_VALIDATE_EMAIL) && preg_match('/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/', $email));
}
function isValidUrlAdvanced(string $url): bool {
return filter_var($url, FILTER_VALIDATE_URL) !== false;
}
function logSecurityEvent(string $type, string $description, ?int $userId = null, ?string $ip = null): void {
$ip = $ip ?: getClientIP();
$companyId = 0;
if (function_exists('getCurrentCompanyId')) {
$companyId = (int)(getCurrentCompanyId() ?: 0);
} elseif (isset($_SESSION['company_id'])) {
$companyId = (int)$_SESSION['company_id'];
}
try {
$db = Database::getInstance();
$db->insert('activity_log', [
'company_id' => $companyId,
'user_id' => $userId ?: 0,
'action' => 'Security: ' . $type,
'entity_type' => 'System',
'details' => $description . ' | IP: ' . $ip,
]);
} catch (Throwable $e) {
error_log('[Security] Log error: ' . $e->getMessage());
}
}
function requireHTTPS(): void {
if (PHP_SAPI === 'cli') return;
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443 || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
if (!$isHttps && (getenv('APP_ENV') ?: 'production') !== 'development') {
header('Location: https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
exit;
}
}
function isApiRequestAdvanced(): bool {
$xrw = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
$script = pathinfo($_SERVER['PHP_SELF'] ?? '', PATHINFO_FILENAME);
return $xrw === 'xmlhttprequest' || str_contains($accept, 'application/json') || in_array($script, ['api_leads', 'stripe-webhook', 'stripe-checkout'], true);
}
function jsonResponseAdvanced(array $data, int $statusCode = 200): void {
if (!headers_sent()) {
http_response_code($statusCode);
header('Content-Type: application/json; charset=utf-8');
}
echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
}
function secureTokenAdvanced(int $bytes = 32): string {
return bin2hex(random_bytes($bytes));
}
function secureCompare(string $a, string $b): bool {
return hash_equals($a, $b);
}
function validateCSRFTokenWithExpiry(string $token, int $maxAgeSeconds = 7200): bool {
$sessionToken = $_SESSION['csrf_token'] ?? '';
if (!$sessionToken || !$token) { return false; }
if (!secureCompare($sessionToken, $token)) { return false; }
if (isset($_SESSION['csrf_token_time'])) {
if ((time() - (int)$_SESSION['csrf_token_time']) > $maxAgeSeconds) {
return false; }
}
return true;
}
function requireApiAuth(): void {
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
$expectedKey = (string)(getenv('API_KEY') ?: '');
if ($expectedKey === '') {
$loggedIn = function_exists('isLoggedIn') ? isLoggedIn() : !empty($_SESSION['user_id']);
if (!$loggedIn) {
jsonResponseAdvanced(['success' => false, 'error' => 'Unauthorized'], 401);
}
return;
}
if ($apiKey === '' || !secureCompare($apiKey, $expectedKey)) {
jsonResponseAdvanced(['success' => false, 'error' => 'Invalid API key'], 401);
}
}
if (!function_exists('validatePasswordStrength')) {
function validatePasswordStrength(string $password): array {
$errors = [];
if (strlen($password) < 8) { $errors[] = 'Password must be at least 8 characters'; }
if (!preg_match('/[A-Z]/', $password)) { $errors[] = 'Password must contain at least one uppercase letter'; }
if (!preg_match('/[a-z]/', $password)) { $errors[] = 'Password must contain at least one lowercase letter'; }
if (!preg_match('/[0-9]/', $password)) { $errors[] = 'Password must contain at least one number'; }
return $errors;
}
}
function hashForUrlAdvanced(string $value, string $salt = ''): string {
$salt = $salt ?: (string)(getenv('APP_URL') ?: 'default-salt');
return rtrim(strtr(base64_encode(hash('sha256', $value . $salt, true)), '+/', '-_'), '=');
}
function verifyUrlHashAdvanced(string $value, string $hash, string $salt = ''): bool {
return hash_equals($hash, hashForUrlAdvanced($value, $salt));
}

/**
 * Check if an email domain is a known disposable/temporary email provider.
 * Blocks bot signups from throwaway email services.
 */
function isDisposableEmail(string $email): bool {
    $domain = strtolower(substr(strrchr($email, '@'), 1));
    if (!$domain) return false;
    $disposable = [
        'mailinator.com', 'guerrillamail.com', 'tempmail.com', 'throwaway.email',
        '10minutemail.com', 'trashmail.com', 'fakeinbox.com', 'getnada.com',
        'yopmail.com', 'temp-mail.org', 'sharklasers.com', 'guerrillamail.net',
        'guerrillamailblock.com', 'armyspy.com', 'cuvox.de', 'dayrep.com',
        'dispostable.com', 'grr.la', 'ma1l.bij.pl', 'maildrop.cc', 'mailnesia.com',
        'mintemail.com', 'plexolan.de', 'spam4.me', 'trbvm.com', 'tmpmail.org',
        'tmpmail.net', 'boun.cr', 'bouncc.com', 'cock.li', 'dropmail.me',
        'emailondeck.com', 'fakebox.com', 'harakirimail.com', 'incognitomail.com',
        'jetable.com', 'keepmyemail.com', 'meltmail.com', 'mfsa.info',
        'muathe.org', 'my10minutemail.com', 'mytemp.email', 'nobugmail.com',
        'noclickemail.com', 'oneoffemail.com', 'popcheckz.com', 'privacy.net',
        'rmqkr.net', 'safetymail.info', 'safetypost.com', 'scratchmyback.com',
        'shortmail.net', 'sibmail.com', 'sneakemail.com', 'sneakmail.de',
        'sogetthis.com', 'spamgourmet.com', 'supergreatmail.com', 'te.xgpr.net',
        'tempinbox.com', 'tempmail.it', 'tempmail2.com', 'tempmaildemo.com',
        'tempmailer.com', 'temporarily.com', 'trashmail.ad', 'trashmail.io',
        'trashmail.me', 'tweekme.com', 'wuzup.net', 'yopmail.fr', 'yopmail.net',
        'anonbox.net', 'byb.us', 'cam4you.cc', 'chammy.info', 'clixser.com',
        'contbay.com', 'cool.fr', 'courrieltemporaire.com', 'deadaddress.com',
        'dodgit.com', 'edv.to', 'email-fake.com', 'filzmail.com', 'gishpuppy.com',
        'incognitomail.net', 'ip4mail.com', 'lawlita.com', 'letmeinonthis.com',
        'maboard.com', 'mailcatch.com', 'mailme.com', 'mailtempex.com',
        'moakt.com', 'moyamail.com', 'nepwk.com', 'objectmail.com', 'pookey.com',
        'proxymail.com', 'rcpt.at', 're-gister.com', 'rhyta.com', 'smellypotato.com',
        'solidsomething.com', 'spam.la', 'spambob.com', 'spamfree.eu', 'spamherelater.com',
        'trashymail.com', 'trbvn.com', 'tulis.gq', 'wespeakspam.com', 'xoxox.net',
    ];
    return in_array($domain, $disposable);
}
applySecurityHeaders();
