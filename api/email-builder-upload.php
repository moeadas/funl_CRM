<?php
/**
 * FUNL CRM — Email Builder image upload endpoint
 *
 * Accepts: multipart/form-data with fields:
 *   - image       (file)
 *   - csrf_token  (string)
 *
 * Returns JSON:
 *   { success: true,  url: "/uploads/email-builder/<company>/<file>" }
 *   { success: false, message: "..." }
 *
 * Security:
 *   - Auth required (Sales Manager+).
 *   - CSRF check on POST.
 *   - Validates real MIME type via finfo (not the trust-the-client name).
 *   - Limits to common image types and 8 MB.
 *   - Stores under uploads/email-builder/<company_id>/ for tenant isolation.
 *   - Renames to a random filename so callers can't overwrite each other.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireLogin();
requireRole('Sales Manager');
header('Content-Type: application/json');
function out($ok, $msg = '', $extra = []) {
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(false, 'POST required');
}
// CSRF — accept token from either POST field or X-CSRF-Token header
$token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
if (!verifyCSRFToken($token)) {
    http_response_code(403);
    out(false, 'Invalid or expired request token. Please refresh the page.');
}
if (!isset($_FILES['image'])) {
    http_response_code(400);
    out(false, 'No file uploaded (field name must be "image").');
}
$file = $_FILES['image'];
// Basic upload error check
if ($file['error'] !== UPLOAD_ERR_OK) {
    $codes = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit',
        UPLOAD_ERR_PARTIAL    => 'File only partially uploaded',
        UPLOAD_ERR_NO_FILE    => 'No file uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Server temp folder missing',
        UPLOAD_ERR_CANT_WRITE => 'Server failed to write file',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload',
    ];
    http_response_code(400);
    out(false, $codes[$file['error']] ?? 'Upload error');
}
// Size limit: 8 MB
$MAX_BYTES = 8 * 1024 * 1024;
if ($file['size'] > $MAX_BYTES) {
    http_response_code(400);
    out(false, 'File is too large (max 8 MB).');
}
if ($file['size'] <= 0) {
    http_response_code(400);
    out(false, 'File is empty.');
}
// MIME validation — never trust client-provided type
$allowedMime = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
    'image/svg+xml' => 'svg',
];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']) ?: '';
if (!isset($allowedMime[$mime])) {
    http_response_code(400);
    out(false, 'Unsupported file type. Allowed: JPG, PNG, GIF, WebP, SVG.');
}
$ext = $allowedMime[$mime];
// SVG — strip any <script> tags / on* handlers to prevent XSS in inboxes/preview
if ($mime === 'image/svg+xml') {
    $raw = @file_get_contents($file['tmp_name']);
    if ($raw === false) { http_response_code(500); out(false, 'Could not read uploaded file.'); }
    $clean = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $raw);
    $clean = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|\S+)#i', '', $clean);
    $clean = preg_replace('#xlink:href\s*=\s*("javascript:[^"]*"|\'javascript:[^\']*\')#i', '', $clean);
    if (file_put_contents($file['tmp_name'], $clean) === false) {
        http_response_code(500);
        out(false, 'Could not sanitize SVG.');
    }
}
// Destination folder: uploads/email-builder/<company_id>/
$companyId = (int)($_SESSION['company_id'] ?? 0);
if ($companyId <= 0) {
    // Super-admin without a company: bucket under 'shared'
    $bucket = 'shared';
} else {
    $bucket = 'co' . $companyId;
}
$relDir = '/uploads/email-builder/' . $bucket;
$absDir = realpath(__DIR__ . '/..') . $relDir;
if (!is_dir($absDir)) {
    if (!@mkdir($absDir, 0755, true) && !is_dir($absDir)) {
        http_response_code(500);
        out(false, 'Could not create upload folder. Check permissions on /uploads.');
    }
}
// Random filename to avoid clobbering and to obscure
try {
    $randomName = bin2hex(random_bytes(10));
} catch (Exception $e) {
    $randomName = substr(md5(uniqid('', true)), 0, 20);
}
$basename = date('Ymd') . '_' . $randomName . '.' . $ext;
$absPath  = $absDir . '/' . $basename;
$relPath  = $relDir . '/' . $basename;
if (!move_uploaded_file($file['tmp_name'], $absPath)) {
    http_response_code(500);
    out(false, 'Could not save uploaded file.');
}
@chmod($absPath, 0644);
// Log
if (function_exists('logActivity')) {
    try {
        logActivity(
            (int)($_SESSION['user_id'] ?? 0),
            'Upload',
            'EmailBuilderAsset',
            null,
            'Uploaded ' . $basename . ' (' . $file['size'] . ' bytes, ' . $mime . ')'
        );
    } catch (Throwable $e) { /* ignore logging errors */ }
}
// Build absolute URL for use in <img src=...> inside the exported email
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? parse_url(defined('APP_URL') ? APP_URL : '', PHP_URL_HOST);
$absUrl = $scheme . '://' . $host . $relPath;
out(true, 'Uploaded', [
    'url'        => $absUrl,
    'path'       => $relPath,
    'size'       => (int)$file['size'],
    'mime'       => $mime,
    'filename'   => $basename,
]);
