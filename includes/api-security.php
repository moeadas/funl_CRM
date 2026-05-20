<?php
/**
 * White Label CRM - API Security Layer
 * Apply security, rate limiting, and input validation to all API routes
 */
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';

// Apply security headers for API
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Get client IP for rate limiting
$clientIp = getClientIP();

// API Rate limits: key = endpoint pattern, max = requests, window = seconds
$apiLimits = [
    'leads' => [100, 3600],      // 100 leads fetches per hour
    'leads_create' => [30, 3600], // 30 lead creates per hour
    'login' => [20, 3600],       // 20 logins per hour
    'register' => [10, 3600],    // 10 registrations per hour
    'password_reset' => [5, 3600], // 5 resets per hour
    'api_default' => [200, 3600], // 200 requests/hour default
];

// Determine rate limit key from request
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$rateKey = 'api_default';

// Map request to rate limit bucket
if (preg_match('#/api/leads#', $requestPath)) {
    $rateKey = $_SERVER['REQUEST_METHOD'] === 'POST' ? 'leads_create' : 'leads';
} elseif (preg_match('#/login#', $requestPath)) {
    $rateKey = 'login';
} elseif (preg_match('#/register#', $requestPath)) {
    $rateKey = 'register';
} elseif (preg_match('#/password_reset|forgot_password#', $requestPath)) {
    $rateKey = 'password_reset';
}

$limits = $apiLimits[$rateKey] ?? $apiLimits['api_default'];

// Apply rate limiting
if (!rateLimit('api_' . $rateKey . '_' . $clientIp, $limits[0], $limits[1], $clientIp)) {
    $remaining = rateLimitRemaining('api_' . $rateKey . '_' . $clientIp, $limits[0], $limits[1], $clientIp);
    header('X-RateLimit-Limit: ' . $limits[0]);
    header('X-RateLimit-Remaining: ' . max(0, $remaining));
    header('Retry-After: 3600');
    http_response_code(429);
    echo json_encode([
        'error' => 'Rate limit exceeded',
        'message' => 'Too many requests. Please try again later.',
        'retry_after' => 3600,
    ]);
    exit;
}

// Log API access
$userId = getCurrentUserId();
if ($userId) {
    logActivity($userId, 'API Access', 'api', null, 'Endpoint: ' . $requestPath);
}