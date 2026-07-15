<?php
/**
 * White Label CRM - Proposal View / Print
 * 
 * Displays a read-only, printable proposal with company branding.
 * Add ?print=1 to the URL to auto-trigger the print dialog (Save as PDF).
 * Data is scoped to the user's company_id.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireLogin();

$proposalId = intval($_GET['id'] ?? 0);
$printMode = isset($_GET['print']);

if (!$proposalId) {
    $_SESSION['error'] = 'Invalid proposal ID';
    header('Location: /pages/proposals.php');
    exit;
}

// Fetch proposal data — scoped to company_id for security
$db = Database::getInstance();
$companyId = $_SESSION['company_id'] ?? null;

// Auto-recover company_id if session is stale
if (!$companyId && !empty($_SESSION['user_id'])) {
    $companyId = $db->query("SELECT company_id FROM users WHERE user_id = ?", [$_SESSION['user_id']])->fetchColumn();
    if ($companyId) $_SESSION['company_id'] = (int)$companyId;
}

$proposal = $db->query(
    "SELECT * FROM proposals WHERE proposal_id = ? AND company_id = ?",
    [$proposalId, $companyId]
)->fetch(PDO::FETCH_ASSOC);

if (!$proposal) {
    $_SESSION['error'] = 'Proposal not found';
    header('Location: /pages/proposals.php');
    exit;
}

// Parse line items
$lineItems = json_decode($proposal['line_items'] ?? '[]', true);
if (!is_array($lineItems)) $lineItems = [];

// Get company branding info
$companyName = getSetting('company_name') ?: 'Our Company';
$companyEmail = getSetting('company_email') ?: '';
$companyPhone = getSetting('company_phone') ?: '';
$companyAddress = getSetting('company_address') ?: '';
// getSetting('company_logo') returns only the stored FILENAME (e.g.
// "logo_38_1783007642.png"). Emitting that straight into src="" resolved
// relative to /pages/, so the proposal logo always 404'd — hence "proposal not
// showing uploaded logos". getCompanyLogo() returns the correct /uploads/ URL.
//
// Only use it when this company actually has its own valid upload: a proposal
// goes to the tenant's customer, so falling back to the platform's default logo
// would brand their document with ours. With no logo we keep the original
// behaviour and show the company name instead.
$logoUrl = isValidUploadedAsset(getSetting('company_logo')) ? getCompanyLogo() : '';

// Calculate total
$total = 0;
foreach ($lineItems as $item) {
    $total += ($item['qty'] ?? 0) * ($item['rate'] ?? 0);
}
$proposalTotal = $proposal['total'] ?? $total;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proposal #<?php echo htmlspecialchars($proposal['estimate_number'] ?? $proposalId); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        /* Print-friendly styles for proposal view */
        body { background: #f5f5f7; margin: 0; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .proposal-doc {
            max-width: 800px; margin: 0 auto; background: white;
            padding: 48px; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        .proposal-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; }
        .company-info { display: flex; align-items: center; gap: 12px; }
        .company-info img { max-height: 48px; max-width: 160px; }
        .company-info .name { font-size: 20px; font-weight: 700; color: #1a1a2e; }
        .company-info .contact { font-size: 12px; color: #6b7280; margin-top: 2px; line-height: 1.5; }
        .proposal-meta { text-align: right; }
        .proposal-meta h1 { font-size: 28px; font-weight: 700; margin: 0; color: #1a1a2e; }
        .proposal-meta .estimate-num { font-size: 14px; color: #6b7280; margin-top: 4px; }
        .proposal-meta .date { font-size: 13px; color: #6b7280; margin-top: 2px; }
        .proposal-meta .status-badge {
            display: inline-block; padding: 4px 14px; border-radius: 20px;
            font-size: 12px; font-weight: 600; margin-top: 8px;
        }
        .status-Draft { background: #f3f4f6; color: #6b7280; }
        .status-Sent { background: #dbeafe; color: #2563eb; }
        .status-Accepted { background: #d1fae5; color: #059669; }
        .status-Declined { background: #fee2e2; color: #dc2626; }

        .section-title { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #9ca3af; margin-bottom: 6px; font-weight: 600; }
        .customer-block { margin-bottom: 32px; }
        .customer-block .name { font-size: 16px; font-weight: 600; color: #1a1a2e; }
        .customer-block .addr { font-size: 13px; color: #6b7280; white-space: pre-wrap; margin-top: 4px; line-height: 1.5; }

        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .items-table th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; padding: 10px 12px; border-bottom: 2px solid #e5e7eb; font-weight: 600; }
        .items-table td { padding: 12px; border-bottom: 1px solid #f3f4f6; font-size: 14px; color: #1f2937; }
        .items-table .num { text-align: right; font-variant-numeric: tabular-nums; }
        .items-table tfoot td { border-bottom: none; border-top: 2px solid #e5e7eb; font-weight: 700; font-size: 16px; padding-top: 16px; }

        .total-row { display: flex; justify-content: flex-end; margin-top: 8px; }
        .total-box { min-width: 240px; }
        .total-line { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; }
        .total-line.grand { border-top: 2px solid #1a1a2e; margin-top: 8px; padding-top: 12px; font-size: 20px; font-weight: 700; }

        .action-bar { max-width: 800px; margin: 16px auto 0; display: flex; gap: 8px; justify-content: center; }
        .action-bar .btn { padding: 8px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; text-decoration: none; cursor: pointer; border: 1px solid #e5e7eb; background: white; color: #1f2937; }
        .action-bar .btn:hover { border-color: #6366f1; color: #6366f1; }
        .action-bar .btn-primary { background: #6366f1; color: white; border-color: #6366f1; }

        @media print {
            body { background: white; padding: 0; }
            .proposal-doc { box-shadow: none; border-radius: 0; padding: 24px; max-width: 100%; }
            .action-bar { display: none !important; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="proposal-doc">
    <!-- Header: Company info on left, Proposal meta on right -->
    <div class="proposal-header">
        <div class="company-info">
            <?php if ($logoUrl): ?>
                <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Logo">
            <?php else: ?>
                <div class="name"><?php echo htmlspecialchars($companyName); ?></div>
            <?php endif; ?>
            <div>
                <?php if ($logoUrl): ?><div class="name"><?php echo htmlspecialchars($companyName); ?></div><?php endif; ?>
                <div class="contact">
                    <?php if ($companyEmail) echo htmlspecialchars($companyEmail) . '<br>'; ?>
                    <?php if ($companyPhone) echo htmlspecialchars($companyPhone) . '<br>'; ?>
                    <?php if ($companyAddress) echo htmlspecialchars($companyAddress); ?>
                </div>
            </div>
        </div>
        <div class="proposal-meta">
            <h1>PROPOSAL</h1>
            <div class="estimate-num">#<?php echo htmlspecialchars($proposal['estimate_number'] ?? $proposalId); ?></div>
            <div class="date"><?php echo htmlspecialchars($proposal['proposal_date'] ?? date('Y-m-d')); ?></div>
            <div class="status-badge status-<?php echo htmlspecialchars($proposal['status'] ?? 'Draft'); ?>">
                <?php echo htmlspecialchars($proposal['status'] ?? 'Draft'); ?>
            </div>
        </div>
    </div>

    <!-- Customer Information -->
    <div class="customer-block">
        <div class="section-title">Prepared For</div>
        <div class="name"><?php echo htmlspecialchars($proposal['customer_company'] ?? ''); ?></div>
        <?php if ($proposal['contact_name']): ?>
            <div style="font-size:14px;color:#4b5563;margin-top:2px;"><?php echo htmlspecialchars($proposal['contact_name']); ?></div>
        <?php endif; ?>
        <?php if ($proposal['customer_address']): ?>
            <div class="addr"><?php echo htmlspecialchars($proposal['customer_address']); ?></div>
        <?php endif; ?>
    </div>

    <!-- Line Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th class="num" style="width:80px;">Qty</th>
                <th class="num" style="width:120px;">Rate</th>
                <th class="num" style="width:120px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lineItems as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['description'] ?? ''); ?></td>
                <td class="num"><?php echo htmlspecialchars($item['qty'] ?? 1); ?></td>
                <td class="num">$<?php echo number_format($item['rate'] ?? 0, 2); ?></td>
                <td class="num">$<?php echo number_format(($item['qty'] ?? 0) * ($item['rate'] ?? 0), 2); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($lineItems)): ?>
            <tr><td colspan="4" style="text-align:center;color:#9ca3af;padding:24px;">No items</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Total -->
    <div class="total-row">
        <div class="total-box">
            <div class="total-line grand">
                <span>Total</span>
                <span>$<?php echo number_format($proposalTotal, 2); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Action Bar (hidden in print mode) -->
<div class="action-bar no-print">
    <a href="/pages/proposals.php" class="btn">← Back to Proposals</a>
    <button class="btn btn-primary" onclick="window.print()">⬇ Download PDF</button>
    <a href="/pages/proposal-form.php?id=<?php echo $proposalId; ?>" class="btn">✎ Edit</a>
</div>

<?php if ($printMode): ?>
<script>
// Auto-trigger print dialog for PDF download
window.addEventListener('load', function() {
    setTimeout(function() { window.print(); }, 300);
});
</script>
<?php endif; ?>

</body>
</html>
