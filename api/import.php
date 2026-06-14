<?php
/**
 * White Label CRM — Data Import API
 * Accepts CSV / JSON uploads of leads
 *   POST /api/import.php?action=leads
 *   multipart/form-data with: file, csrf_token, duplicate_mode, default_status, default_source
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireLogin();

// Only Admin and Sales Manager can import
if (!hasRole('Admin') && !hasRole('Sales Manager')) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only Admin and Sales Manager can import data']);
    exit;
}

header('Content-Type: application/json');

try {
    $db = Database::getInstance();
    $userId = getCurrentUser()['user_id'] ?? 0;
    $companyId = $_SESSION['company_id'] ?? null;

    // Auto-recover company_id from DB if session is stale
    if (!$companyId && $userId) {
        $stmt = $db->prepare("SELECT company_id FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $companyId = $stmt->fetchColumn();
        if ($companyId) $_SESSION['company_id'] = $companyId;
    }
    if (!$companyId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No company associated with your account.']);
        exit;
    }

    requireCSRF();

    $action = $_GET['action'] ?? '';
    if ($action !== 'leads') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action. Use ?action=leads']);
        exit;
    }

    // Validate upload
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $err = $_FILES['file']['error'] ?? -1;
        $msg = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Server missing temp directory',
            UPLOAD_ERR_CANT_WRITE => 'Server cannot write file',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload',
        ][$err] ?? 'Unknown upload error';
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }

    $file = $_FILES['file'];
    if ($file['size'] > 10 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File too large (max 10 MB)']);
        exit;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'json'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Only .csv and .json files are accepted']);
        exit;
    }

    $duplicateMode = $_POST['duplicate_mode'] ?? 'skip';
    if (!in_array($duplicateMode, ['skip', 'update', 'create'])) $duplicateMode = 'skip';

    $defaultStatus = $_POST['default_status'] ?? 'New Lead';
    $defaultSource = trim($_POST['default_source'] ?? 'CSV Import') ?: 'CSV Import';

    $content = file_get_contents($file['tmp_name']);
    if ($content === false || $content === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Could not read uploaded file']);
        exit;
    }

    // Parse file
    if ($ext === 'json') {
        $rows = parseJsonImport($content);
    } else {
        $rows = parseCsvImport($content);
    }

    if (empty($rows)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No data rows found in file']);
        exit;
    }

    // Import
    $result = importLeads($db, $companyId, $userId, $rows, $duplicateMode, $defaultStatus, $defaultSource);

    echo json_encode([
        'success' => true,
        'message' => sprintf('Imported %d, updated %d, skipped %d',
            $result['imported'], $result['updated'], $result['skipped']),
        'data' => $result,
    ]);

} catch (Exception $e) {
    error_log('import.php error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

// ─────────────────────────────────────────────
// JSON PARSER
// ─────────────────────────────────────────────
function parseJsonImport($content) {
    $data = json_decode($content, true);
    if (!is_array($data)) {
        throw new Exception('JSON must be an array of objects');
    }
    // Allow top-level { "leads": [...] } envelope too
    if (!empty($data['leads']) && is_array($data['leads'])) {
        $data = $data['leads'];
    }
    // If it's an associative array (single object), wrap it
    if (!empty($data) && !isset($data[0])) {
        $data = [$data];
    }
    return $data;
}

// ─────────────────────────────────────────────
// CSV PARSER (handles quoted fields, commas in quotes, escaped quotes)
// ─────────────────────────────────────────────
function parseCsvImport($content) {
    // Strip BOM
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") $content = substr($content, 3);

    $rows = [];
    $fp = fopen('php://temp', 'r+');
    fwrite($fp, $content);
    rewind($fp);

    $headers = null;
    while (($row = fgetcsv($fp, 0, ',', '"', '\\')) !== false) {
        // Skip fully empty lines
        if (count($row) === 1 && trim((string)$row[0]) === '') continue;
        if ($headers === null) {
            $headers = array_map(function($h) { return strtolower(trim($h)); }, $row);
            continue;
        }
        // Pad to header length
        while (count($row) < count($headers)) $row[] = '';
        $row = array_slice($row, 0, count($headers));
        $assoc = [];
        foreach ($headers as $i => $h) {
            $assoc[$h] = $row[$i];
        }
        $rows[] = $assoc;
    }
    fclose($fp);
    return $rows;
}

// ─────────────────────────────────────────────
// LEAD FIELD MAPPING
// Recognizes many column-name variants (case-insensitive, snake/camel/space)
// ─────────────────────────────────────────────
function leadFieldMap() {
    return [
        // canonical             => [accepted variants]
        'company_name'   => ['company_name', 'company', 'companyname', 'organization', 'org', 'account', 'account_name'],
        'contact_person' => ['contact_person', 'contact', 'contactname', 'contact_name', 'name', 'full_name', 'fullname', 'lead_name', 'leadname'],
        'email'          => ['email', 'email_address', 'emailaddress', 'e-mail', 'mail'],
        'phone'          => ['phone', 'phone_number', 'phonenumber', 'tel', 'telephone', 'work_phone', 'workphone'],
        'mobile'         => ['mobile', 'mobile_number', 'mobilenumber', 'cell', 'cellphone', 'cell_phone'],
        'country'        => ['country', 'country_name', 'countryname'],
        'city'           => ['city', 'town', 'locality'],
        'address'        => ['address', 'street', 'street_address', 'streetaddress', 'addr'],
        'website'        => ['website', 'url', 'web', 'homepage'],
        'industry'       => ['industry', 'sector', 'vertical'],
        'lead_source'    => ['lead_source', 'source', 'leadsource', 'channel', 'origin'],
        'lead_status'    => ['lead_status', 'status', 'leadstatus', 'stage'],
        'priority'       => ['priority', 'prio'],
        'notes'          => ['notes', 'note', 'comment', 'comments', 'description', 'remarks'],
        'title_position' => ['title_position', 'title', 'position', 'job_title', 'jobtitle', 'role'],
        'company_size'   => ['company_size', 'companysize', 'size', 'employees'],
        'annual_revenue' => ['annual_revenue', 'annualrevenue', 'revenue', 'annual_income'],
        'region'         => ['region', 'territory'],
        'phone_country_code' => ['country_code', 'countrycode', 'phone_country_code', 'dial_code'],
    ];
}

function mapLeadRow($row) {
    static $map = null;
    if ($map === null) {
        $map = leadFieldMap();
        // Build a lookup: lowercased variant => canonical
        $lookup = [];
        foreach ($map as $canonical => $variants) {
            foreach ($variants as $v) $lookup[strtolower($v)] = $canonical;
        }
        $map = $lookup;
    }

    $out = [];
    foreach ($row as $key => $value) {
        $lk = strtolower(trim((string)$key));
        if (isset($map[$lk])) {
            $out[$map[$lk]] = is_string($value) ? trim($value) : $value;
        }
    }
    return $out;
}

// ─────────────────────────────────────────────
// LEAD IMPORTER
// ─────────────────────────────────────────────
function importLeads($db, $companyId, $userId, $rows, $duplicateMode, $defaultStatus, $defaultSource) {
    $imported = 0;
    $updated = 0;
    $skipped = 0;
    $errors = [];
    $rowNum = 0;

    $db->beginTransaction();
    try {
        foreach ($rows as $raw) {
            $rowNum++;
            $row = mapLeadRow($raw);

            // Skip empty rows
            if (empty($row)) continue;

            $companyName = trim((string)($row['company_name'] ?? ''));
            $contactName = trim((string)($row['contact_person'] ?? ''));

            // Fall back to "name" for company if missing
            if (!$companyName) $companyName = trim((string)($raw['Company'] ?? $raw['company'] ?? ''));
            if (!$contactName) $contactName = trim((string)($raw['Name'] ?? $raw['Contact'] ?? $raw['contact'] ?? ''));

            // Both required
            if (!$companyName || !$contactName) {
                // Don't count truly empty rows as errors
                if (!$companyName && !$contactName && empty($row['email']) && empty($row['phone']) && empty($row['mobile'])) {
                    continue;
                }
                $errors[] = ['row' => $rowNum, 'message' => 'Missing company_name or contact_person'];
                continue;
            }

            $email = strtolower(trim((string)($row['email'] ?? '')));
            $phone = trim((string)($row['phone'] ?? ''));
            $mobile = trim((string)($row['mobile'] ?? ''));

            // Check for existing lead (match on email or phone)
            $existingId = null;
            if ($email || $phone || $mobile) {
                $clauses = [];
                $params = [];
                if ($email)   { $clauses[] = 'LOWER(email) = ?';   $params[] = $email; }
                if ($phone)   { $clauses[] = 'phone = ?';           $params[] = $phone; }
                if ($mobile)  { $clauses[] = 'mobile = ?';          $params[] = $mobile; }
                $params[] = $companyId;
                $stmt = $db->prepare("SELECT lead_id FROM leads WHERE (" . implode(' OR ', $clauses) . ") AND company_id = ? LIMIT 1");
                $stmt->execute($params);
                $existingId = $stmt->fetchColumn();
            }

            if ($existingId) {
                if ($duplicateMode === 'skip') {
                    $skipped++;
                    continue;
                }
                if ($duplicateMode === 'update') {
                    $updateData = buildLeadData($row, $companyName, $contactName, $defaultStatus, $defaultSource, /*preserveSource=*/true);
                    $updateData['updated_at'] = date('Y-m-d H:i:s');
                    $db->update('leads', $updateData, ['lead_id' => $existingId, 'company_id' => $companyId]);
                    if (!empty($row['lead_source'])) {
                        trackLeadSource($db, $companyId, $row['lead_source']);
                    }
                    $updated++;
                    continue;
                }
                // 'create' → fall through and insert duplicate
            }

            $insertData = buildLeadData($row, $companyName, $contactName, $defaultStatus, $defaultSource, /*preserveSource=*/false);
            $insertData['company_id'] = $companyId;
            $insertData['created_by'] = $userId;
            $newId = $db->insert('leads', $insertData);
            if ($newId) {
                $imported++;
                if (!empty($insertData['lead_source'])) {
                    trackLeadSource($db, $companyId, $insertData['lead_source']);
                }
            } else {
                $errors[] = ['row' => $rowNum, 'message' => 'Insert failed'];
            }
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

    return compact('imported', 'updated', 'skipped', 'errors');
}

function buildLeadData($row, $companyName, $contactName, $defaultStatus, $defaultSource, $preserveSource) {
    $data = [
        'company_name'   => substr($companyName, 0, 200) ?: 'Unknown Company',
        'contact_person' => substr($contactName, 0, 100),
        'title_position' => !empty($row['title_position']) ? substr($row['title_position'], 0, 100) : null,
        'email'          => !empty($row['email']) ? substr($row['email'], 0, 100) : null,
        'phone'          => !empty($row['phone']) ? substr($row['phone'], 0, 20) : null,
        'mobile'         => !empty($row['mobile']) ? substr($row['mobile'], 0, 20) : null,
        'country'        => !empty($row['country']) ? substr($row['country'], 0, 100) : 'Unknown',
        'city'           => !empty($row['city']) ? substr($row['city'], 0, 100) : null,
        'address'        => !empty($row['address']) ? $row['address'] : null,
        'website'        => !empty($row['website']) ? substr($row['website'], 0, 255) : null,
        'industry'       => !empty($row['industry']) ? substr($row['industry'], 0, 100) : null,
        'company_size'   => !empty($row['company_size']) ? substr($row['company_size'], 0, 50) : null,
        'annual_revenue' => !empty($row['annual_revenue']) ? substr($row['annual_revenue'], 0, 50) : null,
        'region'         => !empty($row['region']) ? $row['region'] : 'Other',
        'notes'          => !empty($row['notes']) ? $row['notes'] : null,
        'lead_status'    => !empty($row['lead_status']) ? $row['lead_status'] : $defaultStatus,
        'lead_source'    => $preserveSource
            ? null  // don't overwrite on update unless we have new
            : (!empty($row['lead_source']) ? substr($row['lead_source'], 0, 255) : $defaultSource),
        'priority'       => !empty($row['priority']) ? $row['priority'] : 'Medium',
        'lead_type'      => 'Business',
    ];

    // For update mode: only overwrite source if new value is provided
    if ($preserveSource) {
        if (empty($row['lead_source'])) {
            unset($data['lead_source']);
        } else {
            $data['lead_source'] = substr($row['lead_source'], 0, 255);
        }
    }
    return $data;
}

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
