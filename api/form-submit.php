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
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();
$conn = $db->getConnection();

$input = json_decode(file_get_contents('php://input'), true);
$slug = sanitizeInput($input['form_slug'] ?? '');

if (empty($slug)) {
    echo json_encode(['success' => false, 'message' => 'Form not found']);
    exit;
}

// Find form by slug
$form = $db->query("SELECT * FROM web_forms WHERE form_slug = ? AND is_active = 1", [$slug])->fetch();
if (!$form) {
    echo json_encode(['success' => false, 'message' => 'Form not found or inactive']);
    exit;
}

$companyId = $form['company_id'];
$formId = $form['form_id'];
$fieldsConfig = json_decode($form['fields_config'] ?? '[]', true);

// Validate required fields
$submittedData = $input['data'] ?? [];
$errors = [];
foreach ($fieldsConfig as $field) {
    if (!empty($field['required']) && empty($submittedData[$field['name']])) {
        $errors[] = $field['label'] . ' is required';
    }
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $errors]);
    exit;
}

// Create lead from form data
$leadData = [
    'company_id'     => $companyId,
    'company_name'   => sanitizeInput($submittedData['company_name'] ?? 'Unknown'),
    'contact_person' => sanitizeInput($submittedData['contact_person'] ?? ''),
    'email'          => sanitizeInput($submittedData['email'] ?? ''),
    'phone'          => sanitizeInput($submittedData['phone'] ?? ''),
    'mobile'         => sanitizeInput($submittedData['mobile'] ?? ''),
    'country'        => sanitizeInput($submittedData['country'] ?? 'Other'),
    'city'           => sanitizeInput($submittedData['city'] ?? ''),
    'address'        => sanitizeInput($submittedData['address'] ?? ''),
    'specialization' => sanitizeInput($submittedData['message'] ?? ''),
    'lead_source'    => $form['lead_source'] ?? 'Web Form',
    'lead_status'    => 'New Lead',
    'assigned_to'    => $form['auto_assign_to'] ?? null,
    'created_by'     => 0, // System
];

try {
    $leadId = $db->insert('leads', $leadData);
    
    // Log submission
    $db->insert('web_form_submissions', [
        'form_id'     => $formId,
        'company_id'  => $companyId,
        'lead_id'     => $leadId,
        'data'        => json_encode($submittedData),
        'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'referrer'    => $_SERVER['HTTP_REFERER'] ?? null,
    ]);
    
    // Update form submit count
    $db->query("UPDATE web_forms SET submit_count = submit_count + 1 WHERE form_id = ?", [$formId]);
    
    // Send notifications
    if (!empty($form['notify_emails'])) {
        $emails = array_map('trim', explode(',', $form['notify_emails']));
        $subject = "New form submission: {$form['form_name']}";
        $body = "<h2>New Lead from {$form['form_name']}</h2>";
        foreach ($submittedData as $key => $value) {
            $body .= "<p><strong>" . ucfirst(str_replace('_', ' ', $key)) . ":</strong> " . htmlspecialchars($value) . "</p>";
        }
        
        if (function_exists('sendEmailViaSMTP')) {
            foreach ($emails as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    sendEmailViaSMTP($email, $subject, $body);
                }
            }
        }
    }
    
    // Run automation rules
    $rules = $db->query("SELECT * FROM automation_rules WHERE company_id = ? AND is_active = 1 AND trigger_type = 'lead_created'", [$companyId])->fetchAll();
    foreach ($rules as $rule) {
        $actionConfig = json_decode($rule['action_config'] ?? '{}', true);
        if ($rule['action_type'] === 'assign_user' && !empty($actionConfig['user_id'])) {
            $db->query("UPDATE leads SET assigned_to = ? WHERE lead_id = ?", [$actionConfig['user_id'], $leadId]);
        }
        if ($rule['action_type'] === 'create_task') {
            $dueDate = date('Y-m-d', strtotime('+' . ($actionConfig['due_days'] ?? 1) . ' days'));
            $db->insert('tasks', [
                'company_id' => $companyId,
                'title'      => $actionConfig['title'] ?? 'Follow up',
                'status'     => 'todo',
                'priority'   => $actionConfig['priority'] ?? 'medium',
                'due_date'   => $dueDate,
                'lead_id'    => $leadId,
                'created_by' => 0,
            ]);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => $form['success_message'] ?? 'Thank you!',
        'redirect_url' => $form['redirect_url'] ?? null,
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to process submission']);
}
?>
