<?php
/**
 * White Label CRM - Public Web Forms API
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

startSecureSession();
requireLogin();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$db = Database::getInstance()->getConnection();
$userId = getCurrentUser()['user_id'] ?? 0;
$companyId = getCurrentCompanyId();

// ── CRUD ──────────────────────────────────────────────────────

if ($action === 'list' && $method === 'GET') {
    $forms = $db->query("
        SELECT f.*, u.full_name as creator_name
        FROM web_forms f
        LEFT JOIN users u ON f.created_by = u.user_id
        WHERE f.company_id = ?
        ORDER BY f.created_at DESC", [$companyId])->fetchAll();
    jsonSuccess('Forms loaded', $forms);
}

if ($action === 'create' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $formName = sanitizeInput($input['form_name'] ?? '');
    
    if (empty($formName)) jsonError('Form name is required');
    
    $slug = generateSlug($formName);
    // Ensure unique slug
    $existing = $db->query("SELECT 1 FROM web_forms WHERE company_id = ? AND form_slug = ?", [$companyId, $slug])->fetch();
    if ($existing) $slug .= '-' . time();
    
    $formId = $db->insert('web_forms', [
        'company_id'       => $companyId,
        'form_name'        => $formName,
        'form_slug'        => $slug,
        'title'            => sanitizeInput($input['title'] ?? $formName),
        'description'      => sanitizeInput($input['description'] ?? ''),
        'success_message'  => sanitizeInput($input['success_message'] ?? 'Thank you! We will contact you soon.'),
        'redirect_url'     => sanitizeInput($input['redirect_url'] ?? ''),
        'fields_config'    => json_encode($input['fields_config'] ?? defaultFields()),
        'styling'          => json_encode($input['styling'] ?? defaultStyling()),
        'thank_you_page'   => isset($input['thank_you_page']) ? (int)$input['thank_you_page'] : 1,
        'notify_emails'    => sanitizeInput($input['notify_emails'] ?? ''),
        'auto_assign_to'   => !empty($input['auto_assign_to']) ? (int)$input['auto_assign_to'] : null,
        'lead_source'      => sanitizeInput($input['lead_source'] ?? 'Web Form'),
        'created_by'       => $userId,
    ]);
    
    logActivity($userId, 'Create Web Form', 'WebForm', $formId, "Created form: $formName");
    jsonSuccess('Form created', ['form_id' => $formId, 'slug' => $slug]);
}

if ($action === 'update' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $formId = (int)($input['form_id'] ?? 0);
    
    $form = $db->query("SELECT * FROM web_forms WHERE form_id = ? AND company_id = ?", [$formId, $companyId])->fetch();
    if (!$form) jsonError('Form not found');
    
    $updates = [];
    if (isset($input['form_name'])) $updates['form_name'] = sanitizeInput($input['form_name']);
    if (isset($input['is_active'])) $updates['is_active'] = (int)$input['is_active'];
    if (isset($input['title'])) $updates['title'] = sanitizeInput($input['title']);
    if (isset($input['description'])) $updates['description'] = sanitizeInput($input['description']);
    if (isset($input['success_message'])) $updates['success_message'] = sanitizeInput($input['success_message']);
    if (isset($input['redirect_url'])) $updates['redirect_url'] = sanitizeInput($input['redirect_url']);
    if (isset($input['fields_config'])) $updates['fields_config'] = json_encode($input['fields_config']);
    if (isset($input['styling'])) $updates['styling'] = json_encode($input['styling']);
    if (isset($input['thank_you_page'])) $updates['thank_you_page'] = (int)$input['thank_you_page'];
    if (isset($input['notify_emails'])) $updates['notify_emails'] = sanitizeInput($input['notify_emails']);
    if (isset($input['auto_assign_to'])) $updates['auto_assign_to'] = $input['auto_assign_to'] ? (int)$input['auto_assign_to'] : null;
    if (isset($input['lead_source'])) $updates['lead_source'] = sanitizeInput($input['lead_source']);
    
    if (!empty($updates)) {
        $db->update('web_forms', $updates, ['form_id' => $formId]);
    }
    
    logActivity($userId, 'Update Web Form', 'WebForm', $formId, "Updated form");
    jsonSuccess('Form updated');
}

if ($action === 'delete' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $formId = (int)($input['form_id'] ?? 0);
    
    $form = $db->query("SELECT * FROM web_forms WHERE form_id = ? AND company_id = ?", [$formId, $companyId])->fetch();
    if (!$form) jsonError('Form not found');
    
    $db->query("DELETE FROM web_form_submissions WHERE form_id = ?", [$formId]);
    $db->query("DELETE FROM web_forms WHERE form_id = ?", [$formId]);
    
    logActivity($userId, 'Delete Web Form', 'WebForm', $formId, "Deleted form: {$form['form_name']}");
    jsonSuccess('Form deleted');
}

if ($action === 'get' && $method === 'GET') {
    $formId = (int)($_GET['form_id'] ?? 0);
    $form = $db->query("SELECT * FROM web_forms WHERE form_id = ? AND company_id = ?", [$formId, $companyId])->fetch();
    if (!$form) jsonError('Form not found');
    
    $form['fields_config'] = json_decode($form['fields_config'] ?? '[]', true);
    $form['styling'] = json_decode($form['styling'] ?? '{}', true);
    
    $submissions = $db->query("SELECT COUNT(*) as cnt FROM web_form_submissions WHERE form_id = ?", [$formId])->fetch();
    $form['submission_count'] = $submissions['cnt'] ?? 0;
    
    jsonSuccess('Form loaded', $form);
}

if ($action === 'submissions' && $method === 'GET') {
    $formId = (int)($_GET['form_id'] ?? 0);
    $limit = min((int)($_GET['limit'] ?? 50), 100);
    
    $subs = $db->query("
        SELECT s.*, l.company_name as lead_name
        FROM web_form_submissions s
        LEFT JOIN leads l ON s.lead_id = l.lead_id
        WHERE s.form_id = ? AND s.company_id = ?
        ORDER BY s.created_at DESC
        LIMIT $limit", [$formId, $companyId])->fetchAll();
    
    jsonSuccess('Submissions loaded', $subs);
}

// ── Default helpers ───────────────────────────────────────────

function defaultFields() {
    return [
        ['type' => 'text', 'name' => 'company_name', 'label' => 'Company Name', 'required' => true],
        ['type' => 'text', 'name' => 'contact_person', 'label' => 'Contact Person', 'required' => true],
        ['type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => true],
        ['type' => 'tel', 'name' => 'phone', 'label' => 'Phone', 'required' => false],
        ['type' => 'select', 'name' => 'country', 'label' => 'Country', 'required' => true, 'options' => ['United States','United Kingdom','Canada','Australia','Germany','France','UAE','Saudi Arabia','Other']],
        ['type' => 'textarea', 'name' => 'message', 'label' => 'Message', 'required' => false],
    ];
}

function defaultStyling() {
    return [
        'primary_color' => '#2563eb',
        'bg_color'      => '#ffffff',
        'text_color'    => '#1f2937',
        'border_radius' => '8',
        'font_family'   => 'system-ui',
    ];
}

function generateSlug($name) {
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return substr($slug, 0, 100);
}

jsonError('Unknown action');
?>
