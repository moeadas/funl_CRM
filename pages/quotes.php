<?php
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$userId = getCurrentUserId();
$companyId = $_SESSION["company_id"] ?? null;

$pageTitle = 'Quotes';
$js = ['quotes'];

require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$deals = $db->query("SELECT deal_id, deal_name FROM deals WHERE company_id = ? ORDER BY deal_name", [$companyId])->fetchAll();
$accounts = $db->query("SELECT account_id, account_name FROM accounts WHERE company_id = ? ORDER BY account_name", [$companyId])->fetchAll();
?>

<style>
.quotes-page { max-width: 1200px; margin: 0 auto; padding: 0 20px 40px; }
.page-header { display: flex; justify-content: space-between; align-items: center; padding: 24px 0 20px; }
.page-header h1 { font-size: 22px; font-weight: 600; margin: 0; }
.btn { padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; }
.btn-primary { background: #2563eb; color: white; }
.btn-outline { background: white; border: 1px solid #d1d5db; color: #374151; }

/* Table */
.data-table-wrap { background: white; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; }
table.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
table.data-table th { background: #f9fafb; padding: 12px 16px; text-align: left; font-weight: 600; color: #6b7280; font-size: 12px; text-transform: uppercase; border-bottom: 1px solid #e5e7eb; }
table.data-table td { padding: 14px 16px; border-bottom: 1px solid #e5e7eb; color: #1f2937; }
table.data-table tr:last-child td { border-bottom: none; }
table.data-table tr:hover { background: #f9fafb; }

.status-badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
.status-draft { background: #f3f4f6; color: #6b7280; }
.status-sent { background: #dbeafe; color: #2563eb; }
.status-accepted { background: #dcfce7; color: #15803d; }
.status-rejected { background: #fee2e2; color: #dc2626; }
.status-expired { background: #fef3c7; color: #d97706; }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: none; align-items: center; justify-content: center; z-index: 1000; }
.modal-overlay.active { display: flex; }
.modal { background: white; border-radius: 12px; width: 640px; max-width: 95vw; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
.modal-header { padding: 20px 24px 16px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
.modal-header h2 { font-size: 17px; font-weight: 600; margin: 0; }
.modal-close { background: none; border: none; font-size: 20px; cursor: pointer; color: #9ca3af; }
.modal-body { padding: 20px 24px; }
.form-group { margin-bottom: 14px; }
.form-label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 5px; color: #374151; }
.form-control { width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; box-sizing: border-box; }
.form-control:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
textarea.form-control { min-height: 70px; resize: vertical; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-actions { padding: 16px 24px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 10px; }

/* Items Table */
.items-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 12px; }
.items-table th { text-align: left; padding: 8px; font-size: 11px; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
.items-table td { padding: 8px; border-bottom: 1px solid #f3f4f6; }
.items-table input { width: 100%; padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 13px; }
.items-table .num-input { width: 80px; text-align: right; }
.items-table .action-btn { background: none; border: none; cursor: pointer; color: #dc2626; font-size: 16px; }
.add-item-btn { background: #f3f4f6; border: 1px dashed #d1d5db; padding: 8px; border-radius: 6px; cursor: pointer; font-size: 13px; color: #374151; width: 100%; }
.add-item-btn:hover { background: #e5e7eb; }

.totals { text-align: right; padding: 12px 0; font-size: 14px; }
.totals .total-row { display: flex; justify-content: flex-end; gap: 16px; margin-bottom: 6px; }
.totals .total-label { color: #6b7280; }
.totals .total-value { font-weight: 600; min-width: 100px; }
.totals .grand-total { font-size: 18px; font-weight: 700; color: #1f2937; border-top: 2px solid #e5e7eb; padding-top: 8px; margin-top: 8px; }
</style>

<div class="quotes-page">
    <div class="page-header">
        <h1>Quotes & Proposals</h1>
        <button class="btn btn-primary" onclick="openQuoteModal()">+ New Quote</button>
    </div>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Quote #</th>
                    <th>Title</th>
                    <th>Client</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="quotes-tbody">
                <tr><td colspan="7" style="text-align:center;padding:40px;color:#9ca3af">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Quote Modal -->
<div class="modal-overlay" id="quote-modal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="quote-modal-title">New Quote</h2>
            <button class="modal-close" onclick="closeQuoteModal()">&times;</button>
        </div>
        <form id="quote-form" onsubmit="saveQuote(event)">
            <div class="modal-body">
                <input type="hidden" id="quote-id" value="">
                
                <div class="form-group">
                    <label class="form-label">Quote Title *</label>
                    <input type="text" id="quote-title" class="form-control" required placeholder="e.g. Website Development Project">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Deal</label>
                        <select id="quote-deal" class="form-control">
                            <option value="">None</option>
                            <?php foreach ($deals as $d): ?>
                                <option value="<?= $d['deal_id'] ?>"><?= htmlspecialchars($d['deal_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Account</label>
                        <select id="quote-account" class="form-control">
                            <option value="">None</option>
                            <?php foreach ($accounts as $a): ?>
                                <option value="<?= $a['account_id'] ?>"><?= htmlspecialchars($a['account_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Issue Date</label>
                        <input type="date" id="quote-issue-date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" id="quote-expiry-date" class="form-control">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Currency</label>
                        <select id="quote-currency" class="form-control">
                            <option value="USD">USD ($)</option>
                            <option value="EUR">EUR (€)</option>
                            <option value="GBP">GBP (£)</option>
                            <option value="AED">AED (د.إ)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tax Rate %</label>
                        <input type="number" id="quote-tax" class="form-control" value="0" min="0" max="100" step="0.01" onchange="calculateTotals()">
                    </div>
                </div>
                
                <!-- Line Items -->
                <div style="margin:20px 0 12px">
                    <div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:10px">Line Items</div>
                    <table class="items-table" id="items-table">
                        <thead>
                            <tr>
                                <th style="width:40%">Description</th>
                                <th style="width:15%">Qty</th>
                                <th style="width:20%">Unit Price</th>
                                <th style="width:15%">Discount%</th>
                                <th style="width:10%"></th>
                            </tr>
                        </thead>
                        <tbody id="items-tbody">
                            <!-- Items added dynamically -->
                        </tbody>
                    </table>
                    <button type="button" class="add-item-btn" onclick="addItemRow()">+ Add Item</button>
                </div>
                
                <div class="totals">
                    <div class="total-row">
                        <span class="total-label">Subtotal:</span>
                        <span class="total-value" id="total-subtotal">$0.00</span>
                    </div>
                    <div class="total-row">
                        <span class="total-label">Tax:</span>
                        <span class="total-value" id="total-tax">$0.00</span>
                    </div>
                    <div class="total-row grand-total">
                        <span class="total-label">Total:</span>
                        <span class="total-value" id="total-grand">$0.00</span>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top:20px">
                    <label class="form-label">Notes</label>
                    <textarea id="quote-notes" class="form-control" placeholder="Additional notes for the client..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Terms & Conditions</label>
                    <textarea id="quote-terms" class="form-control" placeholder="Payment terms, delivery terms, etc."></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeQuoteModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="quote-save-btn">Create Quote</button>
            </div>
        </form>
    </div>
</div>

<script>
const COMPANY_ID = <?= json_encode($companyId) ?>;
const CSRF_TOKEN = 003c?php echo json_encode($_SESSION['csrf_token'] ?? '') ?>;
const API = '/api/quotes.php';

let quotes = [];
let itemCount = 0;

document.addEventListener('DOMContentLoaded', () => {
    loadQuotes();
    addItemRow(); // Add first empty row
});

function loadQuotes() {
    fetch(`${API}?action=list`, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(resp => {
            if (resp.success) {
                quotes = resp.data || [];
                renderQuotes();
            }
        });
}

function renderQuotes() {
    const tbody = document.getElementById('quotes-tbody');
    if (!quotes.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;color:#9ca3af">No quotes yet. Create your first quote!</td></tr>';
        return;
    }
    
    tbody.innerHTML = quotes.map(q => {
        const client = q.account_name || q.lead_name || q.contact_name || '-';
        const statusClass = 'status-' + (q.status || 'draft');
        const currency = q.currency || 'USD';
        const total = new Intl.NumberFormat('en-US', { style: 'currency', currency: currency }).format(q.total || 0);
        
        return `
        <tr onclick="viewQuote(${q.quote_id})" style="cursor:pointer">
            <td><strong>${escapeHtml(q.quote_number)}</strong></td>
            <td>${escapeHtml(q.quote_title)}</td>
            <td>${escapeHtml(client)}</td>
            <td style="font-weight:600">${total}</td>
            <td><span class="status-badge ${statusClass}">${q.status || 'Draft'}</span></td>
            <td>${formatDate(q.issue_date)}</td>
            <td><button type="button" onclick="event.stopPropagation();deleteQuote(${q.quote_id})" style="background:none;border:none;cursor:pointer;color:#dc2626">🗑️</button></td>
        </tr>`;
    }).join('');
}

function addItemRow() {
    itemCount++;
    const tbody = document.getElementById('items-tbody');
    const row = document.createElement('tr');
    row.dataset.itemId = itemCount;
    row.innerHTML = `
        <td><input type="text" class="item-desc" placeholder="Item description" onchange="calculateTotals()"></td>
        <td><input type="number" class="num-input item-qty" value="1" min="0.01" step="0.01" onchange="calculateTotals()"></td>
        <td><input type="number" class="num-input item-price" value="0" min="0" step="0.01" onchange="calculateTotals()"></td>
        <td><input type="number" class="num-input item-discount" value="0" min="0" max="100" step="0.01" onchange="calculateTotals()"></td>
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

function openQuoteModal() {
    document.getElementById('quote-form').reset();
    document.getElementById('quote-id').value = '';
    document.getElementById('quote-modal-title').textContent = 'New Quote';
    document.getElementById('quote-save-btn').textContent = 'Create Quote';
    document.getElementById('items-tbody').innerHTML = '';
    itemCount = 0;
    addItemRow();
    calculateTotals();
    document.getElementById('quote-modal').classList.add('active');
}

function closeQuoteModal() {
    document.getElementById('quote-modal').classList.remove('active');
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
    
    const data = {
        csrf_token: CSRF_TOKEN,
        quote_title: document.getElementById('quote-title').value,
        deal_id: document.getElementById('quote-deal').value || null,
        account_id: document.getElementById('quote-account').value || null,
        issue_date: document.getElementById('quote-issue-date').value,
        expiry_date: document.getElementById('quote-expiry-date').value || null,
        currency: document.getElementById('quote-currency').value,
        notes: document.getElementById('quote-notes').value,
        terms: document.getElementById('quote-terms').value,
        items: items,
    };
    
    fetch(`${API}?action=create`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    }).then(r => r.json()).then(resp => {
        if (resp.success) {
            closeQuoteModal();
            loadQuotes();
            showNotification('Quote created', 'success');
        } else {
            showNotification(resp.message || 'Failed to create', 'error');
        }
    });
}

function viewQuote(quoteId) {
    // For now just alert - PDF viewer would be next step
    window.open(`/pages/quote-view.php?quote_id=${quoteId}`, '_blank');
}

function deleteQuote(quoteId) {
    if (!confirm('Delete this quote?')) return;
    fetch(`${API}?action=delete`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ quote_id: quoteId, csrf_token: CSRF_TOKEN })
    }).then(r => r.json()).then(resp => {
        if (resp.success) {
            loadQuotes();
            showNotification('Quote deleted', 'success');
        }
    });
}

);
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeQuoteModal(); });
document.querySelector('.modal-overlay').addEventListener('click', e => { 
    if (e.target === e.currentTarget) closeQuoteModal(); 
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
