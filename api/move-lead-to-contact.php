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
            $db->query("INSERT INTO accounts (company_id, account_name, industry, country, city, website, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, 'Active', ?, NOW())", [
                $companyId,
                $lead['company_name'],
                $lead['industry'] ?? null,
                $lead['country'] ?? null,
                $lead['city'] ?? null,
                $lead['website'] ?? null,
                $_SESSION['user_id'] ?? 1
            ]);
            $accountId = $db->getConnection()->lastInsertId();
        }
    }
    
    // Parse first name and last name safely
    $firstName = '';
    $lastName = '';
    if (!empty($lead['contact_person'])) {
        $parts = explode(' ', trim($lead['contact_person']));
        $firstName = array_shift($parts);
        $lastName = implode(' ', $parts);
    }
    if (empty($firstName)) {
        $firstName = $lead['company_name'] ?: 'Contact';
    }
    
    // Create contact
    $db->query("INSERT INTO contacts (company_id, account_id, first_name, last_name, email, phone, mobile, title, status, contact_status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active', 'Active', ?, NOW())", [
        $companyId,
        $accountId,
        $firstName,
        $lastName,
        $lead['email'] ?? null,
        $lead['phone'] ?? null,
        $lead['mobile'] ?? null,
        $lead['title_position'] ?? null,
        $_SESSION['user_id'] ?? 1
    ]);
    $contactId = $db->getConnection()->lastInsertId();
    
    // Update lead status to converted
    $db->query("UPDATE leads SET lead_status = 'Won', updated_at = NOW() WHERE lead_id = ?", [$leadId]);
    
    // Log activity
    logActivity($_SESSION['user_id'] ?? 1, 'convert', 'Lead', $leadId, "Lead converted to contact #{$contactId}");
    
    jsonSuccess('Lead successfully converted to contact', ['contact_id' => $contactId]);
} catch (Exception $e) {
    jsonError('Failed to convert lead: ' . $e->getMessage());
}
