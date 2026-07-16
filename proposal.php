<?php
/**
 * Public, token-scoped proposal view.
 *
 * pages/proposal-view.php requires a login, so a lead/contact could never open
 * a link to it. This page is the recipient-facing counterpart: reachable with no
 * session, and the ONLY way in is an unguessable share_token minted by
 * api/proposals.php?action=send.
 *
 * Security notes:
 *   - No company_id comes from the request; it is derived from the token's row,
 *     so a token can never be used to read another tenant's data.
 *   - Branding is resolved directly for that company (getSetting() reads the
 *     session, which does not exist here).
 *   - Read-only, fully output-escaped, no actions.
 */
require_once __DIR__ . '/config/database.php';

function proposal_not_found(): void {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
       . '<title>Proposal not available</title></head>'
       . '<body style="font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:#f4f6f9;margin:0;padding:60px 20px;text-align:center;color:#374151;">'
       . '<h1 style="font-size:20px;">This proposal link is not valid</h1>'
       . '<p style="color:#6b7280;">It may have been withdrawn. Please contact the sender for a new link.</p>'
       . '</body></html>';
    exit;
}
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$token = trim($_GET['t'] ?? '');
if ($token === '' || !preg_match('/^[a-f0-9]{16,64}$/i', $token)) { proposal_not_found(); }

try {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare("SELECT * FROM proposals WHERE share_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $proposal = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
    error_log('public proposal view failed: ' . $ex->getMessage());
    proposal_not_found();
}
if (!$proposal) { proposal_not_found(); }

$companyId = $proposal['company_id'] ?? null;
$brand = [];
try {
    $bs = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE company_id = ? AND setting_key IN ('app_name','company_name','company_logo','company_email','company_phone')");
    $bs->execute([$companyId]);
    $brand = $bs->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $ex) { $brand = []; }

$brandName = $brand['company_name'] ?? ($brand['app_name'] ?? 'Proposal');
$logoFile  = $brand['company_logo'] ?? '';
$logoUrl   = '';
if ($logoFile && strpos($logoFile, '..') === false && is_file(__DIR__ . '/uploads/' . $logoFile) && filesize(__DIR__ . '/uploads/' . $logoFile) >= 100) {
    $logoUrl = '/uploads/' . rawurlencode($logoFile);
}

$items = json_decode($proposal['line_items'] ?? '[]', true);
if (!is_array($items)) { $items = []; }
$subtotal = 0.0;
foreach ($items as $it) { $subtotal += (float)($it['qty'] ?? 0) * (float)($it['rate'] ?? 0); }
$taxAmount = (float)($proposal['tax_amount'] ?? 0);
$total     = (float)($proposal['total'] ?? ($subtotal + $taxAmount));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($brandName) ?> &mdash; Proposal <?= e($proposal['estimate_number'] ?? '') ?></title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f4f6f9; color: #1f2937; line-height: 1.6; padding: 24px 16px; }
    .sheet { max-width: 820px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 40px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
    .top { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; flex-wrap: wrap; margin-bottom: 32px; }
    .logo { max-height: 56px; max-width: 220px; }
    .brand-name { font-size: 20px; font-weight: 700; }
    .meta { text-align: right; font-size: 13px; color: #6b7280; }
    .meta .num { font-size: 22px; font-weight: 700; color: #1f2937; }
    .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: #d1ecf1; color: #0c5460; }
    h2 { font-size: 12px; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; margin-bottom: 6px; }
    .to { margin-bottom: 28px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    th { text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: .4px; color: #6b7280; border-bottom: 2px solid #e5e7eb; padding: 10px 8px; }
    td { padding: 12px 8px; border-bottom: 1px solid #f1f3f5; font-size: 14px; vertical-align: top; }
    .num-col { text-align: right; white-space: nowrap; }
    .totals { margin-left: auto; width: 280px; font-size: 14px; }
    .totals div { display: flex; justify-content: space-between; padding: 6px 0; }
    .totals .grand { border-top: 2px solid #e5e7eb; margin-top: 6px; padding-top: 12px; font-size: 18px; font-weight: 700; }
    .notes { margin-top: 28px; padding-top: 20px; border-top: 1px solid #f1f3f5; font-size: 14px; white-space: pre-wrap; }
    .foot { text-align: center; color: #9ca3af; font-size: 12px; margin-top: 28px; }
    @media print { body { background: #fff; padding: 0; } .sheet { box-shadow: none; border-radius: 0; padding: 0; } }
    @media (max-width: 600px) { .sheet { padding: 24px 18px; } .meta { text-align: left; } }
</style>
</head>
<body>
<div class="sheet">
    <div class="top">
        <div>
            <?php if ($logoUrl): ?>
                <img src="<?= e($logoUrl) ?>" alt="<?= e($brandName) ?>" class="logo">
            <?php else: ?>
                <div class="brand-name"><?= e($brandName) ?></div>
            <?php endif; ?>
            <?php if (!empty($brand['company_email'])): ?>
                <div style="font-size:13px;color:#6b7280;margin-top:8px;"><?= e($brand['company_email']) ?></div>
            <?php endif; ?>
            <?php if (!empty($brand['company_phone'])): ?>
                <div style="font-size:13px;color:#6b7280;"><?= e($brand['company_phone']) ?></div>
            <?php endif; ?>
        </div>
        <div class="meta">
            <div class="num"><?= e($proposal['estimate_number'] ?? '') ?></div>
            <?php if (!empty($proposal['proposal_date'])): ?>
                <div><?= e(date('F j, Y', strtotime($proposal['proposal_date']))) ?></div>
            <?php endif; ?>
            <div style="margin-top:8px;"><span class="badge"><?= e($proposal['status'] ?? '') ?></span></div>
        </div>
    </div>

    <div class="to">
        <h2>Prepared for</h2>
        <?php if (!empty($proposal['customer_company'])): ?>
            <div style="font-weight:600;"><?= e($proposal['customer_company']) ?></div>
        <?php endif; ?>
        <?php if (!empty($proposal['contact_name'])): ?>
            <div><?= e($proposal['contact_name']) ?></div>
        <?php endif; ?>
        <?php if (!empty($proposal['customer_address'])): ?>
            <div style="color:#6b7280;font-size:14px;white-space:pre-wrap;"><?= e($proposal['customer_address']) ?></div>
        <?php endif; ?>
    </div>

    <table>
        <thead>
            <tr><th>Description</th><th class="num-col">Qty</th><th class="num-col">Rate</th><th class="num-col">Amount</th></tr>
        </thead>
        <tbody>
        <?php if (empty($items)): ?>
            <tr><td colspan="4" style="color:#9ca3af;">No line items.</td></tr>
        <?php else: ?>
            <?php foreach ($items as $it): ?>
                <?php $amt = (float)($it['qty'] ?? 0) * (float)($it['rate'] ?? 0); ?>
                <tr>
                    <td><?= e($it['description'] ?? '') ?></td>
                    <td class="num-col"><?= e((string)($it['qty'] ?? 0)) ?></td>
                    <td class="num-col"><?= e(number_format((float)($it['rate'] ?? 0), 2)) ?></td>
                    <td class="num-col"><?= e(number_format($amt, 2)) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <div class="totals">
        <div><span>Subtotal</span><span><?= e(number_format($subtotal, 2)) ?></span></div>
        <?php if ($taxAmount > 0): ?>
            <div><span>Tax<?= !empty($proposal['tax_rate']) ? ' (' . e($proposal['tax_rate']) . '%)' : '' ?></span><span><?= e(number_format($taxAmount, 2)) ?></span></div>
        <?php endif; ?>
        <div class="grand"><span>Total</span><span><?= e(number_format($total, 2)) ?></span></div>
    </div>

    <?php if (!empty($proposal['notes'])): ?>
        <div class="notes"><h2>Notes</h2><?= e($proposal['notes']) ?></div>
    <?php endif; ?>

    <div class="foot">Sent via <?= e($brandName) ?></div>
</div>
</body>
</html>
