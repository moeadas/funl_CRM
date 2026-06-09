<?php
/**
 * pages/invoice.php?id=XX
 * 
 * PDF invoice generator for a payment transaction.
 * 
 * Uses the built-in PDF generation (no external libs required).
 * Outputs a clean, professional PDF with company + transaction details.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../api/ni-checkout-helpers.php';
startSecureSession();
requireLogin();

$db = Database::getInstance();
$txnId = (int)($_GET['id'] ?? 0);
$companyId = getCurrentCompanyId();

// Load transaction - tenant can only see their own
$txn = $db->query("SELECT * FROM payment_transactions WHERE id = ?", [$txnId])->fetch(PDO::FETCH_ASSOC);
if (!$txn) {
    http_response_code(404);
    die('Invoice not found');
}

// Authorize: tenant can see their own; super admin can see all
if ($txn['company_id'] != $companyId && !isSuperAdmin()) {
    http_response_code(403);
    die('Access denied');
}

$company = $db->query("SELECT * FROM companies WHERE company_id = ?", [$txn['company_id']])->fetch(PDO::FETCH_ASSOC);
$platform = [
    'name' => getAppName() ?: 'FunL CRM',
    'support_email' => getSetting('platform_support_email', '') ?: 'support@funl.online',
    'url' => 'https://app.funl.online',
];

// Status display
$statusLabels = [
    'completed' => 'PAID',
    'pending' => 'PENDING',
    'failed' => 'FAILED',
    'cancelled' => 'CANCELLED',
    'refunded' => 'REFUNDED',
];
$statusLabel = $statusLabels[$txn['status']] ?? strtoupper($txn['status']);

$invoiceNumber = 'INV-' . str_pad($txn['id'], 6, '0', STR_PAD_LEFT);
$invoiceDate = date('F j, Y', strtotime($txn['created_at']));
$periodStart = date('M j, Y', strtotime($txn['created_at']));
$periodEnd = $txn['completed_at'] ? date('M j, Y', strtotime($txn['completed_at'])) : date('M j, Y', strtotime($txn['created_at'] . ' +1 month'));

$planName = $txn['plan_key'] ?? 'Subscription';
// Look up plan name
$plan = $db->query("SELECT plan_name FROM plans WHERE plan_key = ?", [$txn['plan_key'] ?? ''])->fetch(PDO::FETCH_ASSOC);
if ($plan) $planName = $plan['plan_name'];

// Generate PDF
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $invoiceNumber . '.pdf"');

generateInvoicePDF($invoiceNumber, $invoiceDate, $periodStart, $periodEnd, $statusLabel, $company, $platform, $planName, $txn);
exit;

/**
 * Generate a PDF invoice using a minimal hand-rolled implementation.
 */
function generateInvoicePDF($invoiceNumber, $invoiceDate, $periodStart, $periodEnd, $statusLabel, $company, $platform, $planName, $txn) {
    $lines = [];
    
    // Header
    $lines[] = "%PDF-1.4";
    $lines[] = "%âãÏÓ";
    
    $objects = [];
    
    // Helper: add an object
    $objNum = 0;
    $objRefs = [];
    
    function addObj(&$objects, $content) {
        $objNum = count($objects) + 1;
        $objects[] = "{$objNum} 0 obj\n{$content}\nendobj\n";
        return $objNum;
    }
    
    // Object 1: Catalog
    $catalogNum = addObj($objects, "<< /Type /Catalog /Pages 2 0 R >>");
    
    // Object 2: Pages
    $pagesNum = addObj($objects, "<< /Type /Pages /Kids [3 0 R] /Count 1 >>");
    
    // Object 3: Page
    $pageNum = addObj($objects, "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R /F3 7 0 R >> >> >>");
    
    // Object 4: Page contents (stream)
    $content = buildInvoiceContent($invoiceNumber, $invoiceDate, $periodStart, $periodEnd, $statusLabel, $company, $platform, $planName, $txn);
    $contentNum = addObj($objects, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    
    // Object 5: Font Helvetica (F1)
    $f1Num = addObj($objects, "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>");
    
    // Object 6: Font Helvetica-Bold (F2)
    $f2Num = addObj($objects, "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>");
    
    // Object 7: Font Helvetica-Oblique (F3)
    $f3Num = addObj($objects, "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique >>");
    
    // Build PDF
    $pdf = "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";
    $offsets = [];
    foreach ($objects as $obj) {
        $offsets[] = strlen($pdf);
        $pdf .= $obj . "\n";
    }
    
    // Cross-reference table
    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n";
    $pdf .= "0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    foreach ($offsets as $off) {
        $pdf .= str_pad($off, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
    }
    
    // Trailer
    $pdf .= "trailer\n";
    $pdf .= "<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n";
    $pdf .= $xrefOffset . "\n";
    $pdf .= "%%EOF\n";
    
    echo $pdf;
}

/**
 * Build the page content stream (PDF operators).
 */
function buildInvoiceContent($invoiceNumber, $invoiceDate, $periodStart, $periodEnd, $statusLabel, $company, $platform, $planName, $txn) {
    $s = "";
    
    // Title
    $s .= "BT\n";
    $s .= "/F2 28 Tf\n";
    $s .= "50 740 Td\n";
    $s .= "(INVOICE) Tj\n";
    $s .= "ET\n";
    
    // Status badge (top right)
    $s .= "BT\n";
    $s .= "/F2 14 Tf\n";
    $s .= "450 740 Td\n";
    $s .= "({$statusLabel}) Tj\n";
    $s .= "ET\n";
    
    // Company name (top left, below title)
    $s .= "BT\n";
    $s .= "/F2 14 Tf\n";
    $s .= "50 710 Td\n";
    $s .= "(" . escapePdfString($platform['name']) . ") Tj\n";
    $s .= "ET\n";
    
    $s .= "BT\n";
    $s .= "/F1 9 Tf\n";
    $s .= "50 696 Td\n";
    $s .= "(" . escapePdfString($platform['url']) . ") Tj\n";
    $s .= "ET\n";
    
    $s .= "BT\n";
    $s .= "/F1 9 Tf\n";
    $s .= "50 684 Td\n";
    $s .= "(" . escapePdfString($platform['support_email']) . ") Tj\n";
    $s .= "ET\n";
    
    // Invoice details (right column)
    $s .= "BT\n";
    $s .= "/F2 10 Tf\n";
    $s .= "400 660 Td\n";
    $s .= "(Invoice #) Tj\n";
    $s .= "ET\n";
    $s .= "BT\n";
    $s .= "/F1 10 Tf\n";
    $s .= "500 660 Td\n";
    $s .= "({$invoiceNumber}) Tj\n";
    $s .= "ET\n";
    
    $s .= "BT\n";
    $s .= "/F2 10 Tf\n";
    $s .= "400 645 Td\n";
    $s .= "(Date) Tj\n";
    $s .= "ET\n";
    $s .= "BT\n";
    $s .= "/F1 10 Tf\n";
    $s .= "500 645 Td\n";
    $s .= "({$invoiceDate}) Tj\n";
    $s .= "ET\n";
    
    // Bill To section
    $s .= "BT\n";
    $s .= "/F2 11 Tf\n";
    $s .= "50 615 Td\n";
    $s .= "(BILL TO) Tj\n";
    $s .= "ET\n";
    
    $y = 600;
    $billLines = [
        $company['company_name'] ?? '',
        $company['email'] ?? '',
        $company['address'] ?? '',
    ];
    foreach ($billLines as $line) {
        if (!$line) continue;
        $s .= "BT\n";
        $s .= "/F1 10 Tf\n";
        $s .= "50 {$y} Td\n";
        $s .= "(" . escapePdfString($line) . ") Tj\n";
        $s .= "ET\n";
        $y -= 14;
    }
    
    // Horizontal line
    $s .= "0.8 w\n";
    $s .= "50 540 m\n";
    $s .= "560 540 l\n";
    $s .= "S\n";
    
    // Table header
    $s .= "BT\n";
    $s .= "/F2 10 Tf\n";
    $s .= "50 520 Td\n";
    $s .= "(Description) Tj\n";
    $s .= "ET\n";
    
    $s .= "BT\n";
    $s .= "/F2 10 Tf\n";
    $s .= "400 520 Td\n";
    $s .= "(Period) Tj\n";
    $s .= "ET\n";
    
    $s .= "BT\n";
    $s .= "/F2 10 Tf\n";
    $s .= "510 520 Td\n";
    $s .= "(Amount) Tj\n";
    $s .= "ET\n";
    
    // Line under header
    $s .= "0.5 w\n";
    $s .= "50 510 m\n";
    $s .= "560 510 l\n";
    $s .= "S\n";
    
    // Item row
    $desc = "{$planName} Plan - " . ucfirst($txn['billing_cycle'] ?? 'monthly') . " subscription";
    $s .= "BT\n";
    $s .= "/F1 10 Tf\n";
    $s .= "50 490 Td\n";
    $s .= "(" . escapePdfString($desc) . ") Tj\n";
    $s .= "ET\n";
    
    $period = "{$periodStart} - {$periodEnd}";
    $s .= "BT\n";
    $s .= "/F1 9 Tf\n";
    $s .= "400 490 Td\n";
    $s .= "(" . escapePdfString($period) . ") Tj\n";
    $s .= "ET\n";
    
    $amount = sprintf('$%.2f', (float)$txn['amount']);
    $s .= "BT\n";
    $s .= "/F1 10 Tf\n";
    $s .= "510 490 Td\n";
    $s .= "({$amount}) Tj\n";
    $s .= "ET\n";
    
    // Subtotal
    $s .= "BT\n";
    $s .= "/F1 10 Tf\n";
    $s .= "400 460 Td\n";
    $s .= "(Subtotal:) Tj\n";
    $s .= "ET\n";
    
    $s .= "BT\n";
    $s .= "/F1 10 Tf\n";
    $s .= "510 460 Td\n";
    $s .= "({$amount}) Tj\n";
    $s .= "ET\n";
    
    // Total
    $s .= "0.5 w\n";
    $s .= "380 450 m\n";
    $s .= "560 450 l\n";
    $s .= "S\n";
    
    $s .= "BT\n";
    $s .= "/F2 12 Tf\n";
    $s .= "400 435 Td\n";
    $s .= "(Total:) Tj\n";
    $s .= "ET\n";
    
    $s .= "BT\n";
    $s .= "/F2 12 Tf\n";
    $s .= "510 435 Td\n";
    $s .= "({$amount}) Tj\n";
    $s .= "ET\n";
    
    // Footer
    $s .= "BT\n";
    $s .= "/F3 9 Tf\n";
    $s .= "50 200 Td\n";
    $s .= "(Thank you for your business.) Tj\n";
    $s .= "ET\n";
    
    $s .= "BT\n";
    $s .= "/F1 8 Tf\n";
    $s .= "50 185 Td\n";
    $s .= "(Order ID: " . escapePdfString($txn['order_id'] ?? '') . ") Tj\n";
    $s .= "ET\n";
    
    if (!empty($txn['gateway_reference'])) {
        $s .= "BT\n";
        $s .= "/F1 8 Tf\n";
        $s .= "50 173 Td\n";
        $s .= "(Gateway Reference: " . escapePdfString($txn['gateway_reference']) . ") Tj\n";
        $s .= "ET\n";
    }
    
    $s .= "BT\n";
    $s .= "/F1 8 Tf\n";
    $s .= "50 50 Td\n";
    $s .= "(Questions? Contact " . escapePdfString($platform['support_email']) . ") Tj\n";
    $s .= "ET\n";
    
    return $s;
}

/**
 * Escape a string for use in a PDF text object.
 * Must escape: ( ) \ and special chars
 */
function escapePdfString($str) {
    $str = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $str);
    // Replace non-ASCII with closest equivalent or remove
    $str = preg_replace('/[^\x20-\x7E]/', '', $str);
    return substr($str, 0, 200); // PDF strings have a practical length limit
}