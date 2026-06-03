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

// H-3 fix: rate limit public form submissions to prevent spam/DB flooding.
// 10 submissions per IP per hour. This endpoint is intentionally public (CORS:*)
// so we rely on IP-based limiting since there is no user/session.
$rateDir = sys_get_temp_dir() . '/wlrm_rate';
if (!is_dir($rateDir)) @mkdir($rateDir, 0755, true);
$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
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
    'lead_source'    => 'Website',
    'lead_status'    => 'New Lead',
    'created_by'     => !empty($form['created_by']) ? (int)$form['created_by'] : 1,
];

try {
    $leadId = $db->insert('leads', $leadData);
    
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
                $taskOwner = (int)($lead['assigned_to'] ?? 0);
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
    echo json_encode(['success' => false, 'message' => 'Failed to process submission: ' . $e->getMessage()]);
}
?>
