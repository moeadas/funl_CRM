<?php
/**
 * White Label CRM - Contacts & Accounts API
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

startSecureSession();
requireLogin();
requireEmailVerified();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$db = Database::getInstance();
$userId = getCurrentUser()['user_id'] ?? 0;
$companyId = $_SESSION["company_id"] ?? null;

// If company_id is missing from session (stale session), look it up from DB
if (!$companyId && !empty($userId)) {
    try {
        $stmt = $db->prepare("SELECT company_id FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $dbCompanyId = $stmt->fetchColumn();
        if ($dbCompanyId) {
            $companyId = (int)$dbCompanyId;
            $_SESSION['company_id'] = $companyId;
        }
    } catch (Exception $e) { /* ignore — will fail gracefully below */ }
}

if (!$companyId) {
    jsonError('No company is associated with your account. Please contact your administrator.', 400);
}

// ────────────────────────────────────────────────────────────────
// CONTACTS
// ────────────────────────────────────────────────────────────────

if ($action === 'list_contacts' && $method === 'GET') {
    $search = $_GET['search'] ?? '';
    $accountId = $_GET['account_id'] ?? '';
    $status = $_GET['status'] ?? '';
    $assignedTo = $_GET['assigned_to'] ?? '';
    $tagId = $_GET['tag_id'] ?? '';
    
    $where = ["c.company_id = ?"];
    $params = [$companyId];
    
    if ($search) {
        $where[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
        $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
    }
    if ($accountId) { $where[] = "c.account_id = ?"; $params[] = (int)$accountId; }
    if ($status) { $where[] = "c.contact_status = ?"; $params[] = $status; }
    if ($assignedTo) { $where[] = "c.assigned_to = ?"; $params[] = (int)$assignedTo; }
    if ($tagId) {
        $where[] = "EXISTS (SELECT 1 FROM contact_tag_map m WHERE m.contact_id = c.contact_id AND m.tag_id = ?)";
        $params[] = (int)$tagId;
    }
    
    $sql = "SELECT c.*, 
            a.account_name,
            u.full_name as assigned_name
            FROM contacts c
            LEFT JOIN accounts a ON c.account_id = a.account_id
            LEFT JOIN users u ON c.assigned_to = u.user_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY c.last_name, c.first_name";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonSuccess('Contacts loaded', $stmt->fetchAll());
}

if ($action === 'create_contact' && $method === 'POST') {
    requireCSRF();
    // REMOVED: requireRole(['Admin','Sales Manager']); — all authenticated users can create contacts
    
    $input = json_decode(file_get_contents('php://input'), true);
    $firstName = sanitizeInput($input['first_name'] ?? '');
    $lastName = sanitizeInput($input['last_name'] ?? '');
    
    if (empty($firstName) || empty($lastName)) jsonError('First and last name are required');
    
    $contactId = $db->insert('contacts', [
        'company_id'     => $companyId,
        'account_id'     => !empty($input['account_id']) ? (int)$input['account_id'] : null,
        'first_name'     => $firstName,
        'last_name'      => $lastName,
        'title'          => sanitizeInput($input['title'] ?? ''),
        'email'          => sanitizeInput($input['email'] ?? ''),
        'phone'          => sanitizeInput($input['phone'] ?? ''),
        'mobile'         => sanitizeInput($input['mobile'] ?? ''),
        'address'        => sanitizeInput($input['address'] ?? ''),
        'city'           => sanitizeInput($input['city'] ?? ''),
        'country'        => sanitizeInput($input['country'] ?? ''),
        'notes'          => sanitizeInput($input['notes'] ?? ''),
        'contact_status' => sanitizeInput($input['contact_status'] ?? 'Active'),
        'lead_source'    => sanitizeInput($input['lead_source'] ?? ''),
        'assigned_to'    => !empty($input['assigned_to']) ? (int)$input['assigned_to'] : null,
        'created_by'     => $userId,
    ]);
    
    // Handle tags
    if (!empty($input['tag_ids']) && is_array($input['tag_ids'])) {
        foreach ($input['tag_ids'] as $tagId) {
            $db->insert('contact_tag_map', ['contact_id' => $contactId, 'tag_id' => (int)$tagId]);
        }
    }
    
    logActivity($userId, 'Create Contact', 'Contact', $contactId, "Created contact: $firstName $lastName");
    jsonSuccess('Contact created', ['contact_id' => $contactId]);
}

if ($action === 'update_contact' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $contactId = (int)($input['contact_id'] ?? 0);
    
    $contact = $db->query("SELECT * FROM contacts WHERE contact_id = ? AND company_id = ?", [$contactId, $companyId])->fetch();
    if (!$contact) jsonError('Contact not found');
    
    $updates = [];
    $updatable = ['first_name','last_name','title','email','phone','mobile','address',
                   'city','state_province','country','postal_code','birthday','website',
                   'facebook_url','instagram_url','linkedin_url','twitter_url','notes',
                   'contact_status','lead_source','assigned_to','account_id'];
    foreach ($updatable as $field) {
        if (isset($input[$field])) {
            $updates[$field] = in_array($field, ['assigned_to','account_id']) 
                ? ($input[$field] ? (int)$input[$field] : null)
                : sanitizeInput($input[$field] ?? '');
        }
    }
    
    if (!empty($updates)) {
        $db->update('contacts', $updates, ['contact_id' => $contactId]);
    }
    
    // Update tags
    if (isset($input['tag_ids']) && is_array($input['tag_ids'])) {
        $db->query("DELETE FROM contact_tag_map WHERE contact_id = ?", [$contactId]);
        foreach ($input['tag_ids'] as $tagId) {
            $db->insert('contact_tag_map', ['contact_id' => $contactId, 'tag_id' => (int)$tagId]);
        }
    }
    
    logActivity($userId, 'Update Contact', 'Contact', $contactId, "Updated contact");
    jsonSuccess('Contact updated');
}

if ($action === 'delete_contact' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $contactId = (int)($input['contact_id'] ?? 0);
    
    $contact = $db->query("SELECT * FROM contacts WHERE contact_id = ? AND company_id = ?", [$contactId, $companyId])->fetch();
    if (!$contact) jsonError('Contact not found');
    
    $db->query("DELETE FROM contact_tag_map WHERE contact_id = ?", [$contactId]);
    $db->query("DELETE FROM contacts WHERE contact_id = ?", [$contactId]);
    
    logActivity($userId, 'Delete Contact', 'Contact', $contactId, "Deleted contact: {$contact['first_name']} {$contact['last_name']}");
    jsonSuccess('Contact deleted');
}

if ($action === 'get_contact' && $method === 'GET') {
    $contactId = (int)($_GET['contact_id'] ?? 0);
    $contact = $db->query("
        SELECT c.*, a.account_name, u.full_name as assigned_name
        FROM contacts c
        LEFT JOIN accounts a ON c.account_id = a.account_id
        LEFT JOIN users u ON c.assigned_to = u.user_id
        WHERE c.contact_id = ? AND c.company_id = ?", [$contactId, $companyId])->fetch();
    
    if (!$contact) jsonError('Contact not found');
    
    $tags = $db->query("
        SELECT t.tag_id, t.tag_name, t.tag_color
        FROM contact_tags t
        JOIN contact_tag_map m ON t.tag_id = m.tag_id
        WHERE m.contact_id = ?", [$contactId])->fetchAll();
    
    $contact['tags'] = $tags;
    jsonSuccess('Contact loaded', $contact);
}

// ────────────────────────────────────────────────────────────────
// ACCOUNTS
// ────────────────────────────────────────────────────────────────

if ($action === 'list_accounts' && $method === 'GET') {
    $search = $_GET['search'] ?? '';
    $type = $_GET['type'] ?? '';
    $status = $_GET['status'] ?? '';
    
    $where = ["a.company_id = ?"];
    $params = [$companyId];
    
    if ($search) {
        $where[] = "(a.account_name LIKE ? OR a.industry LIKE ? OR a.phone LIKE ? OR a.website LIKE ?)";
        $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
    }
    if ($type) { $where[] = "a.account_type = ?"; $params[] = $type; }
    if ($status) { $where[] = "a.status = ?"; $params[] = $status; }
    
    $sql = "SELECT a.*, 
            COUNT(c.contact_id) as contact_count,
            u.full_name as assigned_name
            FROM accounts a
            LEFT JOIN contacts c ON a.account_id = c.account_id
            LEFT JOIN users u ON a.assigned_to = u.user_id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY a.account_id
            ORDER BY a.account_name";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonSuccess('Accounts loaded', $stmt->fetchAll());
}

if ($action === 'create_account' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $accountName = sanitizeInput($input['account_name'] ?? '');
    
    if (empty($accountName)) jsonError('Account name is required');
    
    $accountId = $db->insert('accounts', [
        'company_id'       => $companyId,
        'account_name'     => $accountName,
        'account_type'     => sanitizeInput($input['account_type'] ?? 'Customer'),
        'industry'         => sanitizeInput($input['industry'] ?? ''),
        'website'          => sanitizeInput($input['website'] ?? ''),
        'phone'            => sanitizeInput($input['phone'] ?? ''),
        'address'          => sanitizeInput($input['address'] ?? ''),
        'city'             => sanitizeInput($input['city'] ?? ''),
        'country'          => sanitizeInput($input['country'] ?? ''),
        'annual_revenue'   => !empty($input['annual_revenue']) ? (float)$input['annual_revenue'] : null,
        'employee_count'   => !empty($input['employee_count']) ? (int)$input['employee_count'] : null,
        'description'      => sanitizeInput($input['description'] ?? ''),
        'lead_source'      => sanitizeInput($input['lead_source'] ?? ''),
        'assigned_to'      => !empty($input['assigned_to']) ? (int)$input['assigned_to'] : null,
        'created_by'       => $userId,
    ]);
    
    logActivity($userId, 'Create Account', 'Account', $accountId, "Created account: $accountName");
    jsonSuccess('Account created', ['account_id' => $accountId]);
}

if ($action === 'update_account' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $accountId = (int)($input['account_id'] ?? 0);
    
    $account = $db->query("SELECT * FROM accounts WHERE account_id = ? AND company_id = ?", [$accountId, $companyId])->fetch();
    if (!$account) jsonError('Account not found');
    
    $updates = [];
    $updatable = ['account_name','account_type','industry','website','phone','address',
                   'city','state_province','country','postal_code','billing_address',
                   'tax_id','annual_revenue','employee_count','description',
                   'facebook_url','instagram_url','linkedin_url','twitter_url',
                   'status','lead_source','assigned_to','lead_id'];
    foreach ($updatable as $field) {
        if (isset($input[$field])) {
            $updates[$field] = in_array($field, ['assigned_to','lead_id','employee_count']) 
                ? ($input[$field] ? (int)$input[$field] : null)
                : (in_array($field, ['annual_revenue']) 
                    ? ($input[$field] ? (float)$input[$field] : null)
                    : sanitizeInput($input[$field] ?? ''));
        }
    }
    
    if (!empty($updates)) {
        $db->update('accounts', $updates, ['account_id' => $accountId]);
    }
    
    logActivity($userId, 'Update Account', 'Account', $accountId, "Updated account");
    jsonSuccess('Account updated');
}

if ($action === 'delete_account' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $accountId = (int)($input['account_id'] ?? 0);
    
    $account = $db->query("SELECT * FROM accounts WHERE account_id = ? AND company_id = ?", [$accountId, $companyId])->fetch();
    if (!$account) jsonError('Account not found');
    
    // Unlink contacts
    $db->query("UPDATE contacts SET account_id = NULL WHERE account_id = ?", [$accountId]);
    $db->query("DELETE FROM accounts WHERE account_id = ?", [$accountId]);
    
    logActivity($userId, 'Delete Account', 'Account', $accountId, "Deleted account: {$account['account_name']}");
    jsonSuccess('Account deleted');
}

if ($action === 'get_account' && $method === 'GET') {
    $accountId = (int)($_GET['account_id'] ?? 0);
    $account = $db->query("
        SELECT a.*, u.full_name as assigned_name
        FROM accounts a
        LEFT JOIN users u ON a.assigned_to = u.user_id
        WHERE a.account_id = ? AND a.company_id = ?", [$accountId, $companyId])->fetch();
    
    if (!$account) jsonError('Account not found');
    
    $contacts = $db->query("
        SELECT contact_id, first_name, last_name, title, email, phone, contact_status
        FROM contacts WHERE account_id = ? ORDER BY last_name, first_name", [$accountId])->fetchAll();
    
    $account['contacts'] = $contacts;
    jsonSuccess('Account loaded', $account);
}

// ────────────────────────────────────────────────────────────────
// TAGS
// ────────────────────────────────────────────────────────────────

if ($action === 'list_tags' && $method === 'GET') {
    $tags = $db->query("SELECT * FROM contact_tags WHERE company_id = ? ORDER BY tag_name", [$companyId])->fetchAll();
    jsonSuccess('Tags loaded', $tags);
}

if ($action === 'create_tag' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $tagName = sanitizeInput($input['tag_name'] ?? '');
    $tagColor = sanitizeInput($input['tag_color'] ?? '#6b7280');
    
    if (empty($tagName)) jsonError('Tag name required');
    
    try {
        $tagId = $db->insert('contact_tags', [
            'company_id' => $companyId,
            'tag_name'   => $tagName,
            'tag_color'  => $tagColor,
        ]);
        jsonSuccess('Tag created', ['tag_id' => $tagId]);
    } catch (Exception $e) {
        jsonError('Tag already exists');
    }
}

if ($action === 'delete_tag' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $tagId = (int)($input['tag_id'] ?? 0);
    $db->query("DELETE FROM contact_tag_map WHERE tag_id = ?", [$tagId]);
    $db->query("DELETE FROM contact_tags WHERE tag_id = ? AND company_id = ?", [$tagId, $companyId]);
    jsonSuccess('Tag deleted');
}

jsonError('Unknown action');
?>
