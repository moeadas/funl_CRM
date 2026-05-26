<?php
/**
 * White Label CRM - Quotes & Proposals API
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
$companyId = $_SESSION["company_id"] ?? null;

if ($action === 'list' && $method === 'GET') {
    $status = $_GET['status'] ?? '';
    $where = ["q.company_id = ?"];
    $params = [$companyId];
    if ($status) { $where[] = "q.status = ?"; $params[] = $status; }
    
    $quotes = $db->query("
        SELECT q.*, d.deal_name, l.company_name as lead_name,
               CONCAT(c.first_name, ' ', c.last_name) as contact_name,
               a.account_name
        FROM quotes q
        LEFT JOIN deals d ON q.deal_id = d.deal_id
        LEFT JOIN leads l ON q.lead_id = l.lead_id
        LEFT JOIN contacts c ON q.contact_id = c.contact_id
        LEFT JOIN accounts a ON q.account_id = a.account_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY q.created_at DESC", $params)->fetchAll();
    jsonSuccess('Quotes loaded', $quotes);
}

if ($action === 'create' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    
    $quoteNumber = generateQuoteNumber($db, $companyId);
    $quoteId = $db->insert('quotes', [
        'company_id'   => $companyId,
        'quote_number' => $quoteNumber,
        'deal_id'      => !empty($input['deal_id']) ? (int)$input['deal_id'] : null,
        'lead_id'      => !empty($input['lead_id']) ? (int)$input['lead_id'] : null,
        'contact_id'   => !empty($input['contact_id']) ? (int)$input['contact_id'] : null,
        'account_id'   => !empty($input['account_id']) ? (int)$input['account_id'] : null,
        'quote_title'  => sanitizeInput($input['quote_title'] ?? 'Quote'),
        'issue_date'   => sanitizeInput($input['issue_date'] ?? date('Y-m-d')),
        'expiry_date'  => sanitizeInput($input['expiry_date'] ?? ''),
        'currency'     => sanitizeInput($input['currency'] ?? 'USD'),
        'notes'        => sanitizeInput($input['notes'] ?? ''),
        'terms'        => sanitizeInput($input['terms'] ?? ''),
        'footer_text'  => sanitizeInput($input['footer_text'] ?? ''),
        'created_by'   => $userId,
    ]);
    
    // Add items
    if (!empty($input['items']) && is_array($input['items'])) {
        foreach ($input['items'] as $i => $item) {
            $qty = (float)($item['quantity'] ?? 1);
            $price = (float)($item['unit_price'] ?? 0);
            $discount = (float)($item['discount_percent'] ?? 0);
            $lineTotal = $qty * $price * (1 - $discount / 100);
            
            $db->insert('quote_items', [
                'quote_id'         => $quoteId,
                'item_description' => sanitizeInput($item['description'] ?? ''),
                'quantity'         => $qty,
                'unit_price'       => $price,
                'discount_percent' => $discount,
                'line_total'       => $lineTotal,
                'sort_order'       => $i,
            ]);
        }
    }
    
    recalculateQuote($db, $quoteId);
    logActivity($userId, 'Create Quote', 'Quote', $quoteId, "Created quote: $quoteNumber");
    jsonSuccess('Quote created', ['quote_id' => $quoteId, 'quote_number' => $quoteNumber]);
}

if ($action === 'get' && $method === 'GET') {
    $quoteId = (int)($_GET['quote_id'] ?? 0);
    $quote = $db->query("
        SELECT q.*, d.deal_name, l.company_name as lead_name,
               CONCAT(c.first_name, ' ', c.last_name) as contact_name,
               a.account_name, u.full_name as creator_name
        FROM quotes q
        LEFT JOIN deals d ON q.deal_id = d.deal_id
        LEFT JOIN leads l ON q.lead_id = l.lead_id
        LEFT JOIN contacts c ON q.contact_id = c.contact_id
        LEFT JOIN accounts a ON q.account_id = a.account_id
        LEFT JOIN users u ON q.created_by = u.user_id
        WHERE q.quote_id = ? AND q.company_id = ?", [$quoteId, $companyId])->fetch();
    
    if (!$quote) jsonError('Quote not found');
    
    $items = $db->query("SELECT * FROM quote_items WHERE quote_id = ? ORDER BY sort_order", [$quoteId])->fetchAll();
    $quote['items'] = $items;
    jsonSuccess('Quote loaded', $quote);
}

if ($action === 'update_status' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $quoteId = (int)($input['quote_id'] ?? 0);
    $status = sanitizeInput($input['status'] ?? '');
    
    $updates = ['status' => $status];
    if ($status === 'sent') $updates['sent_at'] = date('Y-m-d H:i:s');
    if ($status === 'accepted') $updates['accepted_at'] = date('Y-m-d H:i:s');
    
    $db->update('quotes', $updates, ['quote_id' => $quoteId, 'company_id' => $companyId]);
    jsonSuccess('Status updated');
}

if ($action === 'delete' && $method === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $quoteId = (int)($input['quote_id'] ?? 0);
    
    $db->query("DELETE FROM quote_items WHERE quote_id = ?", [$quoteId]);
    $db->query("DELETE FROM quotes WHERE quote_id = ? AND company_id = ?", [$quoteId, $companyId]);
    jsonSuccess('Quote deleted');
}

// ── Helpers ───────────────────────────────────────────────────

function generateQuoteNumber($db, $companyId) {
    $prefix = 'Q-' . date('Y') . '-';
    $last = $db->query("SELECT quote_number FROM quotes WHERE company_id = ? AND quote_number LIKE ? ORDER BY quote_id DESC LIMIT 1", 
        [$companyId, $prefix . '%'])->fetch();
    
    if ($last) {
        $num = (int)substr($last['quote_number'], strlen($prefix)) + 1;
    } else {
        $num = 1;
    }
    return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
}

function recalculateQuote($db, $quoteId) {
    $items = $db->query("SELECT SUM(line_total) as subtotal FROM quote_items WHERE quote_id = ?", [$quoteId])->fetch();
    $subtotal = $items['subtotal'] ?? 0;
    
    $quote = $db->query("SELECT tax_rate FROM quotes WHERE quote_id = ?", [$quoteId])->fetch();
    $taxRate = $quote['tax_rate'] ?? 0;
    $taxAmount = $subtotal * ($taxRate / 100);
    $total = $subtotal + $taxAmount;
    
    $db->update('quotes', [
        'subtotal'   => $subtotal,
        'tax_amount' => $taxAmount,
        'total'      => $total,
    ], ['quote_id' => $quoteId]);
}

jsonError('Unknown action');
?>
