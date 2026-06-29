<?php
/**
 * White Label CRM - Public Form Submission Handler
 * No authentication required - accessible from any website
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

/**
 * Extract a UTM parameter from the request's referer URL.
 * Used as a server-side fallback when the JS capture didn't run
 * (e.g. forms on the user's own site that don't include our script).
 */
function extractUtmFromServerVar($key) {
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if (!$ref) return null;
    $parts = parse_url($ref);
    if (empty($parts['query'])) return null;
    parse_str($parts['query'], $params);
    return !empty($params[$key]) ? $params[$key] : null;
}

/**
 * Track a lead source value for the company.
 */
function trackLeadSource($db, $companyId, $sourceValue) {
    $sourceValue = trim((string)$sourceValue);
    if ($sourceValue === '' || !$companyId) return;
    if (mb_strlen($sourceValue) > 255) $sourceValue = mb_substr($sourceValue, 0, 255);
    try {
        $db->query("
            INSERT INTO company_lead_sources (company_id, source_value, use_count, first_used_at, last_used_at)
            VALUES (?, ?, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE use_count = use_count + 1, last_used_at = NOW()
        ", [$companyId, $sourceValue]);
    } catch (Exception $e) {
        // Non-fatal
    }
}

// H-3 fix: rate limit public form submissions to prevent spam/DB flooding.
// 10 submissions per IP per hour. This endpoint is intentionally public (CORS:*)
// so we rely on IP-based limiting since there is no user/session.
$rateDir = sys_get_temp_dir() . '/wlrm_rate';
if (!is_dir($rateDir)) @mkdir($rateDir, 0755, true);
$clientIp = getClientIP(); // L-3: use trusted-proxy-aware IP, not spoofable raw header
$rateKey  = 'formsubmit_' . preg_replace('/[^a-f0-9.:]/i', '', $clientIp);
$rateFile = $rateDir . '/' . $rateKey . '.json';
$now = time();
$hour = $now - 3600;
$attempts = [];
if (file_exists($rateFile)) {
    $attempts = json_decode(file_get_contents($rateFile), true) ?: [];
}
$attempts = array_filter($attempts, fn($t) => $t > $hour);
if (count($attempts) >= 10) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many submissions. Please try again later.']);
    exit;
}
$attempts[] = $now;
@file_put_contents($rateFile, json_encode(array_values($attempts)));

// H-3 honeypot: legitimate form owners add a hidden <input name="website_url">.
// If it's filled in, the request is from a bot - silently fake success.
if (!empty($_POST['website_url'])) {
    error_log('form-submit honeypot triggered from IP ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Thank you! Your submission has been received.',
    ]);
    exit;
}

$db = Database::getInstance();

$input = json_decode(file_get_contents('php://input'), true);
$formId = intval($input['form_id'] ?? 0);

if (empty($formId)) {
    echo json_encode(['success' => false, 'message' => 'Form ID is required']);
    exit;
}

// Find form by ID
$form = $db->query("SELECT * FROM webforms WHERE form_id = ? AND status = 'active'", [$formId])->fetch();
if (!$form) {
    echo json_encode(['success' => false, 'message' => 'Form not found or inactive']);
    exit;
}

$companyId = $form['company_id'];

// Get fields schema
$fields = $db->query("SELECT * FROM webform_fields WHERE form_id = ? ORDER BY position ASC", [$formId])->fetchAll();

$submittedData = $input['data'] ?? [];
$errors = [];

// Validate required fields
foreach ($fields as $field) {
    if (!empty($field['required']) && empty($submittedData[$field['crm_field']])) {
        $errors[] = $field['field_label'] . ' is required';
    }
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $errors]);
    exit;
}

// Extract and map fields to Leads table schema
$companyName = '';
$contactPerson = '';
$email = '';
$phone = '';
$mobile = '';
$country = '';
$city = '';
$address = '';
$website = '';
$industry = '';
$notes = '';

foreach ($fields as $field) {
    $val = trim($submittedData[$field['crm_field']] ?? '');
    switch ($field['crm_field']) {
        case 'company_name': $companyName = $val; break;
        case 'contact_name': $contactPerson = $val; break;
        case 'email': $email = $val; break;
        case 'phone': $phone = $val; break;
        case 'mobile': $mobile = $val; break;
        case 'country': $country = $val; break;
        case 'city': $city = $val; break;
        case 'address': $address = $val; break;
        case 'website': $website = $val; break;
        case 'industry': $industry = $val; break;
        case 'notes': $notes = $val; break;
    }
}

if (empty($contactPerson)) $contactPerson = 'Web submission';
if (empty($companyName)) $companyName = 'Unknown Company';

// Create lead
$leadData = [
    'company_id'     => $companyId,
    'company_name'   => sanitizeInput($companyName),
    'contact_person' => sanitizeInput($contactPerson),
    'email'          => sanitizeInput($email),
    'phone'          => sanitizeInput($phone),
    'mobile'         => sanitizeInput($mobile),
    'country'        => sanitizeInput($country ?: 'Unknown'),
    'city'           => sanitizeInput($city),
    'address'        => sanitizeInput($address),
    'website'        => sanitizeInput($website),
    'industry'       => sanitizeInput($industry),
    'notes'          => sanitizeInput($notes),
    'lead_source'    => sanitizeInput($input['lead_source'] ?? 'Website'),
    'lead_status'    => 'New Lead',
    'created_by'     => !empty($form['created_by']) ? (int)$form['created_by'] : 1,
    // UTM tracking (from form payload OR fallback to query string / referrer)
    'utm_source'     => sanitizeInput($input['utm_source'] ?? extractUtmFromServerVar('utm_source')),
    'utm_campaign'   => sanitizeInput($input['utm_campaign'] ?? extractUtmFromServerVar('utm_campaign')),
    'utm_medium'     => sanitizeInput($input['utm_medium'] ?? extractUtmFromServerVar('utm_medium')),
    'utm_content'    => sanitizeInput($input['utm_content'] ?? extractUtmFromServerVar('utm_content')),
    'utm_term'       => sanitizeInput($input['utm_term'] ?? extractUtmFromServerVar('utm_term')),
    'landing_page'   => sanitizeInput($input['landing_page'] ?? ($_SERVER['HTTP_REFERER'] ?? null)),
    'referrer'       => sanitizeInput($input['referrer'] ?? ($_SERVER['HTTP_REFERER'] ?? null)),
];

// If utm_source is present but lead_source wasn't supplied, derive a friendly source
if (empty($input['lead_source']) && !empty($leadData['utm_source'])) {
    $derived = $leadData['utm_source'];
    if (!empty($leadData['utm_campaign'])) $derived .= ' (' . $leadData['utm_campaign'] . ')';
    $leadData['lead_source'] = $derived;
}

try {
    $leadId = $db->insert('leads', $leadData);

    // Track the new source for the company
    if (!empty($leadData['lead_source'])) {
        trackLeadSource($db, $companyId, $leadData['lead_source']);
    }
    
    // Log submission in webform_submissions
    $db->insert('webform_submissions', [
        'form_id'        => $formId,
        'company_id'     => $companyId,
        'lead_id'        => $leadId,
        'submitted_data' => json_encode($submittedData),
        'ip_address'     => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent'     => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
    
    // Run automation rules
    $rules = $db->query("SELECT * FROM automation_rules WHERE company_id = ? AND is_active = 1 AND trigger_type = 'lead_created'", [$companyId])->fetchAll();
    foreach ($rules as $rule) {
        $actionConfig = json_decode($rule['action_config'] ?? '{}', true);
        if ($rule['action_type'] === 'assign_user' && !empty($actionConfig['user_id'])) {
            $db->query("UPDATE leads SET assigned_to = ? WHERE lead_id = ?", [$actionConfig['user_id'], $leadId]);
        }
        if ($rule['action_type'] === 'create_task') {
            $dueDate = date('Y-m-d', strtotime('+' . ($actionConfig['due_days'] ?? 1) . ' days'));
            // B-8 fix: use the lead's assigned_to user, falling back to the form's
            // default creator, then to a system user (1). Avoids orphan FK when
            // user_id=0 doesn't exist.
            $taskOwner = (int)($form['created_by'] ?? 0);
            if (!$taskOwner) {
                // L-4 fix: $lead was undefined here. Read the lead's current
                // assigned_to (an earlier assign_user rule may have set it).
                $assignedTo = $db->query(
                    "SELECT assigned_to FROM leads WHERE lead_id = ?",
                    [$leadId]
                )->fetchColumn();
                $taskOwner = (int)($assignedTo ?: 0);
            }
            if (!$taskOwner) {
                $taskOwner = 1; // System user as last resort
            }
            $db->insert('tasks', [
                'company_id' => $companyId,
                'title'      => $actionConfig['title'] ?? 'Follow up webform submission',
                'status'     => 'todo',
                'priority'   => $actionConfig['priority'] ?? 'medium',
                'due_date'   => $dueDate,
                'lead_id'    => $leadId,
                'created_by' => $taskOwner,
            ]);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Thank you! Your submission has been received.',
    ]);
    
} catch (Exception $e) {
    safeJsonError($e, 'Failed to process submission: ', 500);
}
?>
