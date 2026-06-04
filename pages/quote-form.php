<?php
/**
 * White Label CRM V2 — Quote Creation/Editing Form
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
startSecureSession();
requireLogin();
requireRole('Sales Manager');

$db = Database::getInstance()->getConnection();
$companyId = getCurrentCompanyId();
$quoteId = intval($_GET['id'] ?? 0);

$quote = null;
$quoteItems = [];

if ($quoteId) {
    $stmt = $db->prepare("SELECT * FROM quotes WHERE quote_id = ? AND company_id = ?");
    $stmt->execute([$quoteId, $companyId]);
    $quote = $stmt->fetch();
    if ($quote) {
        $stmtItems = $db->prepare("SELECT * FROM quote_items WHERE quote_id = ? ORDER BY sort_order");
        $stmtItems->execute([$quoteId]);
        $quoteItems = $stmtItems->fetchAll();
    } else {
        $_SESSION['error'] = __('Quote not found');
        header('Location: quotes.php');
        exit;
    }
}

// Load dropdown data
$stmtDeals = $db->prepare("SELECT deal_id, deal_name FROM deals WHERE company_id = ? ORDER BY deal_name");
$stmtDeals->execute([$companyId]);
$deals = $stmtDeals->fetchAll();

$stmtAccounts = $db->prepare("SELECT account_id, account_name FROM accounts WHERE company_id = ? ORDER BY account_name");
$stmtAccounts->execute([$companyId]);
$accounts = $stmtAccounts->fetchAll();

$csrfToken = generateCSRFToken();
$pageTitle = $quoteId ? __('Edit Quote') : __('New Quote');
include '../includes/header.php';
?>

<style>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 24px; box-shadow: var(--shadow-xs); }
.form-group { margin-bottom: 16px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } }
.form-label { display: block; font-size: 13px; font-weight: 500; color: var(--color-text); margin-bottom: 6px; }
.form-control { width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); font-size: 14px; color: var(--color-text); background: var(--color-surface); box-sizing: border-box; }
.form-control:focus { outline: none; border-color: var(--color-accent); }
.btn { padding: 10px 18px; border-radius: var(--radius-sm); font-size: 14px; font-weight: 500; cursor: pointer; transition: all var(--transition); border: none; text-decoration: none; display: inline-block; }
.btn-primary { background: var(--color-accent); color: #fff; }
.btn-primary:hover { background: var(--color-accent-hover); }
.btn-outline { background: var(--color-surface); color: var(--color-text); border: 1px solid var(--color-border); margin-right: 8px; }
.btn-outline:hover { background: var(--color-bg); }

/* Items Table */
.items-table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 16px; }
.items-table th { text-align: left; font-size: 12px; font-weight: 600; color: var(--color-text-secondary); padding: 8px; border-bottom: 1px solid var(--color-border); }
.items-table td { padding: 8px; border-bottom: 1px solid var(--color-border); }
.items-table input { width: 100%; padding: 8px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); font-size: 13px; background: var(--color-surface); color: var(--color-text); }
.add-item-btn { background: none; border: 1px dashed var(--color-border); padding: 8px 16px; border-radius: var(--radius-sm); width: 100%; cursor: pointer; color: var(--color-text-secondary); transition: all 0.2s; }
.add-item-btn:hover { background: var(--color-bg); border-color: var(--color-text-secondary); }
.action-btn { background: none; border: none; font-size: 16px; color: var(--color-danger); cursor: pointer; }
.totals { margin-top: 16px; border-top: 1px solid var(--color-border); padding-top: 16px; display: flex; flex-direction: column; align-items: flex-end; gap: 8px; }
.total-row { display: flex; justify-content: space-between; width: 240px; font-size: 13px; color: var(--color-text-secondary); }
.total-row.grand-total { font-size: 16px; font-weight: 600; color: var(--color-text); margin-top: 4px; padding-top: 4px; border-top: 1px dashed var(--color-border); }
</style>

<div class="page-container">
    <div class="page-header">
        <div style="display:flex;align-items:center;gap:16px;">
            <a href="/pages/quotes.php" class="btn btn-outline" style="padding:8px 14px;">← <?php echo __('back'); ?></a>
            <h1 class="page-title"><?php echo $quoteId ? __('Edit Quote') : __('New Quote'); ?></h1>
        </div>
    </div>

    <div style="max-width: 800px;">
        <div class="card">
            <form id="quote-form" onsubmit="saveQuote(event)">
                <input type="hidden" id="quote-id" value="<?php echo $quoteId; ?>">
                
                <div class="form-group">
                    <label class="form-label"><?php echo __('Quote Title'); ?> *</label>
                    <input type="text" id="quote-title" class="form-control" required placeholder="<?php echo __('e.g. Website Development Project'); ?>" value="<?php echo htmlspecialchars($quote['quote_title'] ?? ''); ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?php echo __('Deal'); ?></label>
                        <select id="quote-deal" class="form-control">
                            <option value=""><?php echo __('None'); ?></option>
                            <?php foreach ($deals as $d): ?>
                                <option value="<?php echo $d['deal_id']; ?>" <?php echo ($quote && $quote['deal_id'] == $d['deal_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($d['deal_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo __('Account'); ?></label>
                        <select id="quote-account" class="form-control">
                            <option value=""><?php echo __('None'); ?></option>
                            <?php foreach ($accounts as $a): ?>
                                <option value="<?php echo $a['account_id']; ?>" <?php echo ($quote && $quote['account_id'] == $a['account_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($a['account_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?php echo __('Issue Date'); ?></label>
                        <input type="date" id="quote-issue-date" class="form-control" value="<?php echo htmlspecialchars($quote['issue_date'] ?? date('Y-m-d')); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo __('Expiry Date'); ?></label>
                        <input type="date" id="quote-expiry-date" class="form-control" value="<?php echo htmlspecialchars($quote['expiry_date'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?php echo __('Currency'); ?></label>
                        <select id="quote-currency" class="form-control" onchange="calculateTotals()">
                            <option value="USD" <?php echo ($quote && $quote['currency'] === 'USD') ? 'selected' : ''; ?>>USD ($)</option>
                            <option value="EUR" <?php echo ($quote && $quote['currency'] === 'EUR') ? 'selected' : ''; ?>>EUR (€)</option>
                            <option value="GBP" <?php echo ($quote && $quote['currency'] === 'GBP') ? 'selected' : ''; ?>>GBP (£)</option>
                            <option value="AED" <?php echo ($quote && $quote['currency'] === 'AED') ? 'selected' : ''; ?>>AED (د.إ)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo __('Tax Rate %'); ?></label>
                        <input type="number" id="quote-tax" class="form-control" value="<?php echo htmlspecialchars($quote['tax_rate'] ?? '0'); ?>" min="0" max="100" step="0.01" onchange="calculateTotals()">
                    </div>
                </div>
                
                <!-- Line Items -->
                <div style="margin:20px 0 12px">
                    <div style="font-size:13px;font-weight:600;color:var(--color-text);margin-bottom:10px"><?php echo __('Line Items'); ?></div>
                    <table class="items-table" id="items-table">
                        <thead>
                            <tr>
                                <th style="width:40%"><?php echo __('Description'); ?></th>
                                <th style="width:15%"><?php echo __('Qty'); ?></th>
                                <th style="width:20%"><?php echo __('Unit Price'); ?></th>
                                <th style="width:15%"><?php echo __('Discount%'); ?></th>
                                <th style="width:10%"></th>
                            </tr>
                        </thead>
                        <tbody id="items-tbody">
                            <!-- Items added dynamically -->
                        </tbody>
                    </table>
                    <button type="button" class="add-item-btn" onclick="addItemRow()">+ <?php echo __('Add Item'); ?></button>
                </div>
                
                <div class="totals">
                    <div class="total-row">
                        <span class="total-label"><?php echo __('Subtotal'); ?>:</span>
                        <span class="total-value" id="total-subtotal">$0.00</span>
                    </div>
                    <div class="total-row">
                        <span class="total-label"><?php echo __('Tax'); ?>:</span>
                        <span class="total-value" id="total-tax">$0.00</span>
                    </div>
                    <div class="total-row grand-total">
                        <span class="total-label"><?php echo __('Total'); ?>:</span>
                        <span class="total-value" id="total-grand">$0.00</span>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top:20px">
                    <label class="form-label"><?php echo __('Notes'); ?></label>
                    <textarea id="quote-notes" class="form-control" placeholder="<?php echo __('Additional notes for the client...'); ?>"><?php echo htmlspecialchars($quote['notes'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><?php echo __('Terms & Conditions'); ?></label>
                    <textarea id="quote-terms" class="form-control" placeholder="<?php echo __('Payment terms, delivery terms, etc.'); ?>"><?php echo htmlspecialchars($quote['terms'] ?? ''); ?></textarea>
                </div>

                <div style="margin-top: 28px; display: flex; justify-content: flex-end;">
                    <a href="/pages/quotes.php" class="btn btn-outline"><?php echo __('Cancel'); ?></a>
                    <button type="submit" class="btn btn-primary" id="quote-save-btn"><?php echo $quoteId ? __('Save Changes') : __('Create Quote'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?php echo $csrfToken; ?>';
const isEdit = <?php echo $quoteId ? 'true' : 'false'; ?>;
const existingItems = <?php echo json_encode($quoteItems); ?>;
let itemCount = 0;

document.addEventListener('DOMContentLoaded', () => {
    if (isEdit && existingItems.length > 0) {
        existingItems.forEach(item => {
            addItemRow(item.item_description, item.quantity, item.unit_price, item.discount_percent);
        });
    } else {
        addItemRow(); // Add first empty row
    }
    calculateTotals();
});

function addItemRow(desc = '', qty = 1, price = 0, discount = 0) {
    itemCount++;
    const tbody = document.getElementById('items-tbody');
    const row = document.createElement('tr');
    row.dataset.itemId = itemCount;
    row.innerHTML = `
        <td><input type="text" class="item-desc" placeholder="${window.__('Item description')}" value="${escapeHtml(desc)}" onchange="calculateTotals()"></td>
        <td><input type="number" class="num-input item-qty" value="${qty}" min="0.01" step="0.01" onchange="calculateTotals()"></td>
        <td><input type="number" class="num-input item-price" value="${price}" min="0" step="0.01" onchange="calculateTotals()"></td>
        <td><input type="number" class="num-input item-discount" value="${discount}" min="0" max="100" step="0.01" onchange="calculateTotals()"></td>
        <td><button type="button" class="action-btn" onclick="removeItemRow(this)">×</button></td>
    `;
    tbody.appendChild(row);
}

function removeItemRow(btn) {
    btn.closest('tr').remove();
    calculateTotals();
}

function calculateTotals() {
    let subtotal = 0;
    document.querySelectorAll('#items-tbody tr').forEach(row => {
        const qty = parseFloat(row.querySelector('.item-qty')?.value || 0);
        const price = parseFloat(row.querySelector('.item-price')?.value || 0);
        const discount = parseFloat(row.querySelector('.item-discount')?.value || 0);
        subtotal += qty * price * (1 - discount / 100);
    });
    
    const taxRate = parseFloat(document.getElementById('quote-tax')?.value || 0);
    const tax = subtotal * (taxRate / 100);
    const total = subtotal + tax;
    const currency = document.getElementById('quote-currency')?.value || 'USD';
    
    const fmt = new Intl.NumberFormat('en-US', { style: 'currency', currency: currency });
    document.getElementById('total-subtotal').textContent = fmt.format(subtotal);
    document.getElementById('total-tax').textContent = fmt.format(tax);
    document.getElementById('total-grand').textContent = fmt.format(total);
}

function saveQuote(e) {
    e.preventDefault();
    
    const items = [];
    document.querySelectorAll('#items-tbody tr').forEach(row => {
        const desc = row.querySelector('.item-desc')?.value;
        if (desc) {
            items.push({
                description: desc,
                quantity: parseFloat(row.querySelector('.item-qty')?.value || 1),
                unit_price: parseFloat(row.querySelector('.item-price')?.value || 0),
                discount_percent: parseFloat(row.querySelector('.item-discount')?.value || 0),
            });
        }
    });
    
    const id = document.getElementById('quote-id').value;
    const action = id ? 'update' : 'create';
    
    const data = {
        csrf_token: CSRF_TOKEN,
        quote_title: document.getElementById('quote-title').value,
        deal_id: document.getElementById('quote-deal').value || null,
        account_id: document.getElementById('quote-account').value || null,
        issue_date: document.getElementById('quote-issue-date').value,
        expiry_date: document.getElementById('quote-expiry-date').value || null,
        currency: document.getElementById('quote-currency').value,
        tax_rate: parseFloat(document.getElementById('quote-tax').value || 0),
        notes: document.getElementById('quote-notes').value,
        terms: document.getElementById('quote-terms').value,
        items: items,
    };
    
    if (id) {
        data.quote_id = parseInt(id);
    }
    
    fetch('/api/quotes.php?action=' + action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(resp => {
        if (resp.success) {
            showNotification(id ? __('Quote updated successfully!') : __('Quote created successfully!'), 'success');
            setTimeout(() => {
                window.location.href = '/pages/quotes.php';
            }, 1000);
        } else {
            showNotification(resp.message || __('Failed to save quote'), 'error');
        }
    })
    .catch(() => showNotification(__('Network error'), 'error'));
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>

<?php include '../includes/footer.php'; ?>
