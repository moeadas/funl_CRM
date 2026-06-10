<?php
/**
 * White Label CRM - New Quote (standalone, no popup)
 */
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$quoteId = intval($_GET['id'] ?? 0);
$userId = getCurrentUserId();
$companyId = $_SESSION['company_id'] ?? null;

$db = Database::getInstance();
$deals = $db->query("SELECT deal_id, deal_name FROM deals WHERE company_id = ? ORDER BY deal_name", [$companyId])->fetchAll();
$accounts = $db->query("SELECT account_id, account_name FROM accounts WHERE company_id = ? ORDER BY account_name", [$companyId])->fetchAll();

$pageTitle = $quoteId ? __('Edit Quote') : __('New Quote');
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;">
        <a href="/pages/quotes.php" class="btn btn-outline" style="padding:8px 14px;display:inline-flex;align-items:center;gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            <?php echo htmlspecialchars(__('Back to Quotes')); ?>
        </a>
        <h1 id="quotePageTitle"><?php echo htmlspecialchars($pageTitle); ?></h1>
    </div>
    <div class="header-actions">
        <a href="/pages/quotes.php" class="btn btn-outline"><?php echo htmlspecialchars(__('Cancel')); ?></a>
        <button type="submit" form="quoteForm" class="btn btn-primary" id="quoteSaveBtn" style="display:inline-flex;align-items:center;gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            <span id="quoteSaveBtnText"><?php echo htmlspecialchars($quoteId ? __('Save Changes') : __('Create Quote')); ?></span>
        </button>
    </div>
</div>

<form id="quoteForm" onsubmit="saveQuote(event)" style="max-width:920px;">
    <input type="hidden" id="quoteId" value="<?php echo (int)$quoteId; ?>">

    <div class="card">
        <div class="card-header" style="padding:18px 24px;">
            <h3 class="card-title" style="margin:0;"><?php echo htmlspecialchars(__('Quote details')); ?></h3>
        </div>
        <div class="card-body" style="padding:24px;">
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Quote title')); ?> *</label>
                <input type="text" id="quoteTitle" class="form-control" required
                       placeholder="<?php echo htmlspecialchars(__('e.g. Website Development Project')); ?>"
                       style="padding:10px 14px;">
            </div>

            <div class="row-2" style="margin-top:16px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Deal')); ?></label>
                    <select id="quoteDeal" class="form-control" style="padding:10px 14px;">
                        <option value=""><?php echo htmlspecialchars(__('None')); ?></option>
                        <?php foreach ($deals as $d): ?>
                            <option value="<?= (int)$d['deal_id'] ?>"><?= htmlspecialchars($d['deal_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Account')); ?></label>
                    <select id="quoteAccount" class="form-control" style="padding:10px 14px;">
                        <option value=""><?php echo htmlspecialchars(__('None')); ?></option>
                        <?php foreach ($accounts as $a): ?>
                            <option value="<?= (int)$a['account_id'] ?>"><?= htmlspecialchars($a['account_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row-2" style="margin-top:16px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Issue date')); ?></label>
                    <input type="date" id="quoteIssueDate" class="form-control" value="<?= date('Y-m-d') ?>" style="padding:10px 14px;">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Expiry date')); ?></label>
                    <input type="date" id="quoteExpiryDate" class="form-control" style="padding:10px 14px;">
                </div>
            </div>

            <div class="row-2" style="margin-top:16px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Currency')); ?></label>
                    <select id="quoteCurrency" class="form-control" style="padding:10px 14px;">
                        <option value="USD">USD ($)</option>
                        <option value="EUR">EUR (€)</option>
                        <option value="GBP">GBP (£)</option>
                        <option value="AED">AED (د.إ)</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Tax rate %')); ?></label>
                    <input type="number" id="quoteTax" class="form-control" value="0" min="0" max="100" step="0.01" onchange="calculateTotals()" style="padding:10px 14px;">
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:16px;">
        <div class="card-header" style="padding:18px 24px;display:flex;justify-content:space-between;align-items:center;">
            <h3 class="card-title" style="margin:0;"><?php echo htmlspecialchars(__('Line items')); ?></h3>
            <button type="button" class="btn btn-sm btn-outline" onclick="addItemRow()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="vertical-align:-2px;margin-right:4px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <?php echo htmlspecialchars(__('Add item')); ?>
            </button>
        </div>
        <div class="card-body" style="padding:0;">
            <table class="items-table" id="itemsTable" style="width:100%;">
                <thead>
                    <tr style="background:#f9fafb;">
                        <th style="width:40%;padding:12px 16px;text-align:left;font-size:12px;font-weight:600;color:#6b7280;text-transform:uppercase;"><?php echo __('Description'); ?></th>
                        <th style="width:15%;padding:12px 16px;text-align:left;font-size:12px;font-weight:600;color:#6b7280;text-transform:uppercase;"><?php echo __('Qty'); ?></th>
                        <th style="width:20%;padding:12px 16px;text-align:left;font-size:12px;font-weight:600;color:#6b7280;text-transform:uppercase;"><?php echo __('Unit price'); ?></th>
                        <th style="width:15%;padding:12px 16px;text-align:left;font-size:12px;font-weight:600;color:#6b7280;text-transform:uppercase;"><?php echo __('Discount %'); ?></th>
                        <th style="width:10%;padding:12px 16px;"></th>
                    </tr>
                </thead>
                <tbody id="itemsTbody"></tbody>
            </table>
        </div>
    </div>

    <div class="card" style="margin-top:16px;">
        <div class="card-body" style="padding:24px;">
            <div style="display:flex;justify-content:flex-end;">
                <div style="min-width:280px;">
                    <div class="total-row" style="display:flex;justify-content:space-between;padding:8px 0;color:#6b7280;">
                        <span>Subtotal:</span>
                        <span id="totalSubtotal" style="font-weight:500;">$0.00</span>
                    </div>
                    <div class="total-row" style="display:flex;justify-content:space-between;padding:8px 0;color:#6b7280;">
                        <span>Tax:</span>
                        <span id="totalTax" style="font-weight:500;">$0.00</span>
                    </div>
                    <div class="total-row" style="display:flex;justify-content:space-between;padding:12px 0;border-top:2px solid #e5e7eb;font-size:18px;font-weight:600;color:#111827;">
                        <span>Total:</span>
                        <span id="totalGrand">$0.00</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:16px;">
        <div class="card-header" style="padding:18px 24px;">
            <h3 class="card-title" style="margin:0;"><?php echo htmlspecialchars(__('Notes & terms')); ?></h3>
        </div>
        <div class="card-body" style="padding:24px;">
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Notes')); ?></label>
                <textarea id="quoteNotes" class="form-control" rows="3" placeholder="<?php echo htmlspecialchars(__('Additional notes for the client...')); ?>" style="padding:10px 14px;"></textarea>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label"><?php echo htmlspecialchars(__('Terms & conditions')); ?></label>
                <textarea id="quoteTerms" class="form-control" rows="3" placeholder="Payment terms, delivery terms, etc." style="padding:10px 14px;"></textarea>
            </div>
        </div>
    </div>
</form>

<script>
const CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
const API = '/api/quotes.php';
const QUOTE_ID = <?= (int)$quoteId ?>;

let itemCount = 0;

document.addEventListener('DOMContentLoaded', () => {
    addItemRow();
    if (QUOTE_ID) loadQuote();
    calculateTotals();
});

function loadQuote() {
    fetch(`${API}?action=detail&id=${QUOTE_ID}`, { credentials: 'same-origin' })
    .then(r => r.json())
    .then(resp => {
        if (resp.success && resp.quote) {
            const q = resp.quote;
            document.getElementById('quoteTitle').value = q.quote_title || '';
            document.getElementById('quoteDeal').value = q.deal_id || '';
            document.getElementById('quoteAccount').value = q.account_id || '';
            document.getElementById('quoteIssueDate').value = q.issue_date || '';
            document.getElementById('quoteExpiryDate').value = q.expiry_date || '';
            document.getElementById('quoteCurrency').value = q.currency || 'USD';
            document.getElementById('quoteTax').value = q.tax_rate || 0;
            document.getElementById('quoteNotes').value = q.notes || '';
            document.getElementById('quoteTerms').value = q.terms || '';
            // Load items
            document.getElementById('itemsTbody').innerHTML = '';
            itemCount = 0;
            (q.items || []).forEach(item => {
                addItemRow(item);
            });
            if (!(q.items || []).length) addItemRow();
            calculateTotals();
        }
    });
}

function addItemRow(data) {
    itemCount++;
    const tbody = document.getElementById('itemsTbody');
    const row = document.createElement('tr');
    row.dataset.itemId = itemCount;
    row.style.borderTop = '1px solid #f3f4f6';
    row.innerHTML = `
        <td style="padding:8px 12px;"><input type="text" class="form-control item-desc" placeholder="${window.__('Item description')}" onchange="calculateTotals()" value="${escapeAttr(data?.description || '')}" style="padding:8px 10px;"></td>
        <td style="padding:8px 12px;"><input type="number" class="form-control item-qty" value="${escapeAttr(data?.quantity ?? 1)}" min="0.01" step="0.01" onchange="calculateTotals()" style="padding:8px 10px;"></td>
        <td style="padding:8px 12px;"><input type="number" class="form-control item-price" value="${escapeAttr(data?.unit_price ?? 0)}" min="0" step="0.01" onchange="calculateTotals()" style="padding:8px 10px;"></td>
        <td style="padding:8px 12px;"><input type="number" class="form-control item-discount" value="${escapeAttr(data?.discount_percent ?? 0)}" min="0" max="100" step="0.01" onchange="calculateTotals()" style="padding:8px 10px;"></td>
        <td style="padding:8px 12px;text-align:center;"><button type="button" class="btn btn-sm btn-outline" style="padding:4px 10px;color:#dc2626;border-color:#fecaca;background:#fef2f2;" onclick="removeItemRow(this)">×</button></td>
    `;
    tbody.appendChild(row);
}

function removeItemRow(btn) {
    btn.closest('tr').remove();
    calculateTotals();
}

function calculateTotals() {
    let subtotal = 0;
    document.querySelectorAll('#itemsTbody tr').forEach(row => {
        const qty = parseFloat(row.querySelector('.item-qty')?.value || 0);
        const price = parseFloat(row.querySelector('.item-price')?.value || 0);
        const discount = parseFloat(row.querySelector('.item-discount')?.value || 0);
        subtotal += qty * price * (1 - discount / 100);
    });
    const taxRate = parseFloat(document.getElementById('quoteTax')?.value || 0);
    const tax = subtotal * (taxRate / 100);
    const total = subtotal + tax;
    const currency = document.getElementById('quoteCurrency')?.value || 'USD';
    const fmt = new Intl.NumberFormat('en-US', { style: 'currency', currency: currency });
    document.getElementById('totalSubtotal').textContent = fmt.format(subtotal);
    document.getElementById('totalTax').textContent = fmt.format(tax);
    document.getElementById('totalGrand').textContent = fmt.format(total);
}

function saveQuote(e) {
    e.preventDefault();
    const items = [];
    document.querySelectorAll('#itemsTbody tr').forEach(row => {
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

    const data = {
        csrf_token: CSRF_TOKEN,
        quote_title: document.getElementById('quoteTitle').value,
        deal_id: document.getElementById('quoteDeal').value || null,
        account_id: document.getElementById('quoteAccount').value || null,
        issue_date: document.getElementById('quoteIssueDate').value,
        expiry_date: document.getElementById('quoteExpiryDate').value || null,
        currency: document.getElementById('quoteCurrency').value,
        tax_rate: parseFloat(document.getElementById('quoteTax').value || 0),
        notes: document.getElementById('quoteNotes').value,
        terms: document.getElementById('quoteTerms').value,
        items: items,
    };

    const action = QUOTE_ID ? 'update&id=' + QUOTE_ID : 'create';
    const btn = document.getElementById('quoteSaveBtn');
    btn.disabled = true;
    document.getElementById('quoteSaveBtnText').textContent = 'Saving…';

    fetch(`${API}?action=${action}`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    }).then(r => r.json()).then(resp => {
        if (resp.success) {
            showNotification(QUOTE_ID ? 'Quote updated' : 'Quote created', 'success');
            setTimeout(() => window.location.href = '/pages/quotes.php', 700);
        } else {
            showNotification(resp.message || 'Failed', 'error');
            btn.disabled = false;
            document.getElementById('quoteSaveBtnText').textContent = QUOTE_ID ? 'Save Changes' : 'Create Quote';
        }
    });
}

function escapeAttr(s) { return String(s == null ? '' : s).replace(/[<>"']/g, c => c === '<' ? '<' : c === '>' ? '>' : c === '&' ? '&' : '"'); }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
