<?php
/**
 * Move lead to contacts
 */
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Invalid request method');
}

$data = json_decode(file_get_contents('php://input'), true);
$csrfToken = $data['csrf_token'] ?? '';

if (!verifyCSRFToken($csrfToken)) {
    jsonError('Invalid CSRF token');
}

$leadId = intval($data['lead_id'] ?? 0);
if (!$leadId) {
    jsonError('Lead ID required');
}

$companyId = $_SESSION['company_id'] ?? null;
if (!$companyId) {
    jsonError('Company not found');
}

$db = Database::getInstance();

try {
    // Get lead data
    $lead = $db->query("SELECT * FROM leads WHERE lead_id = ? AND company_id = ?", [$leadId, $companyId])->fetch();
    if (!$lead) {
        jsonError('Lead not found');
    }
    
    // Check if already a contact
    $existing = $db->query("SELECT contact_id FROM contacts WHERE email = ? AND company_id = ?", [$lead['email'], $companyId])->fetch();
    if ($existing) {
        jsonError('A contact with this email already exists');
    }
    
    // Create account first if company_name exists
    $accountId = null;
    if (!empty($lead['company_name'])) {
        // Check if account exists
        $account = $db->query("SELECT account_id FROM accounts WHERE account_name = ? AND company_id = ?", [$lead['company_name'], $companyId])->fetch();
        if ($account) {
            $accountId = $account['account_id'];
        } else {
            // Create account
            $db->query("INSERT INTO accounts (company_id, account_name, industry, country, city, website, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'Active', NOW())", [
                $companyId,
                $lead['company_name'],
                $lead['industry'] ?? null,
                $lead['country'] ?? null,
                $lead['city'] ?? null,
                $lead['website'] ?? null
            ]);
            $accountId = $db->getConnection()->lastInsertId();
        }
    }
    
    // Create contact
    $db->query("INSERT INTO contacts (company_id, account_id, first_name, last_name, email, phone, mobile, title, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active', NOW())", [
        $companyId,
        $accountId,
        $lead['contact_person'] ? explode(' ', $lead['contact_person'])[0] : null,
        $lead['contact_person'] ? (strpos($lead['contact_person'], ' ') !== false ? substr($lead['contact_person'], strpos($lead['contact_person'], ' ') + 1) : null) : null,
        $lead['email'],
        $lead['phone'],
        $lead['phone_2'] ?? null,
        $lead['title_position'] ?? null
    ]);
    $contactId = $db->getConnection()->lastInsertId();
    
    // Update lead status to converted
    $db->query("UPDATE leads SET lead_status = 'Won', updated_at = NOW() WHERE lead_id = ?", [$leadId]);
    
    // Log activity
    logActivity($_SESSION['user_id'] ?? null, 'convert', 'Lead', $leadId, "Lead converted to contact #{$contactId}");
    
    jsonSuccess(['contact_id' => $contactId, 'message' => 'Lead successfully converted to contact']);
} catch (Exception $e) {
    jsonError('Failed to convert lead: ' . $e->getMessage());
}
