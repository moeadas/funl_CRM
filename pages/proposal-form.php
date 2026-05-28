<?php
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$pageTitle = 'Proposal';
$currentPage = 'proposals';
$proposalId = intval($_GET['id'] ?? 0);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <a href="/pages/proposals.php" class="btn btn-outline btn-sm">&larr; Back</a>
        <h1><?= $proposalId ? 'Edit Proposal' : 'New Proposal' ?></h1>
    </div>
    <div class="header-actions">
        <button type="button" class="btn btn-primary btn-sm" onclick="saveProposal()">Save</button>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
    <div>
        <div class="card" style="padding:20px;">
            <h3 style="margin:0 0 16px;font-size:15px;font-weight:600;">Proposal Details</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label class="form-label">Estimate #</label>
                    <input type="text" id="estimateNumber" class="form-control" readonly style="background:#f5f5f7;">
                </div>
                <div class="form-group">
                    <label class="form-label">Date</label>
                    <input type="date" id="proposalDate" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select id="proposalStatus" class="form-control">
                        <option value="Draft">Draft</option>
                        <option value="Sent">Sent</option>
                        <option value="Accepted">Accepted</option>
                        <option value="Declined">Declined</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card" style="padding:20px;margin-top:16px;">
            <h3 style="margin:0 0 16px;font-size:15px;font-weight:600;">Customer Information</h3>
            <div class="form-group">
                <label class="form-label">Company Name *</label>
                <input type="text" id="customerCompany" class="form-control" placeholder="e.g., Acme Corp">
            </div>
            <div class="form-group">
                <label class="form-label">Contact Name</label>
                <input type="text" id="contactName" class="form-control" placeholder="e.g., John Doe">
            </div>
            <div class="form-group">
                <label class="form-label">Address</label>
                <textarea id="customerAddress" class="form-control" rows="3"></textarea>
            </div>
        </div>
    </div>

    <div>
        <div class="card" style="padding:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <h3 style="margin:0;font-size:15px;font-weight:600;">Line Items</h3>
                <button type="button" class="btn btn-outline btn-sm" onclick="addLineItem()">+ Add Item</button>
            </div>
            <div id="lineItemsContainer"></div>
            <div style="display:flex;justify-content:flex-end;margin-top:16px;padding-top:12px;border-top:2px solid #e8e8ed;">
                <div style="text-align:right;">
                    <span class="text-muted" style="font-size:13px;">TOTAL</span>
                    <div style="font-size:22px;font-weight:700;" id="totalDisplay">$0.00</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = "<?= $_SESSION['csrf_token'] ?? '' ?>";
const PROPOSAL_ID = <?= $proposalId ?>;
let lineItems = [];

document.addEventListener('DOMContentLoaded', function() {
    if (PROPOSAL_ID) {
        loadProposal();
    } else {
        document.getElementById('proposalDate').value = new Date().toISOString().split('T')[0];
        fetch('/api/proposals.php?action=next_number')
            .then(r => r.json())
            .then(data => {
                if (data.success) document.getElementById('estimateNumber').value = data.next_number;
            });
        addLineItem();
    }
});

function loadProposal() {
    fetch('/api/proposals.php?action=get&id=' + PROPOSAL_ID)
    .then(r => r.json())
    .then(data => {
        if (!data.success) { showNotification(data.message, 'error'); return; }
        const p = data.proposal;
        document.getElementById('estimateNumber').value = p.estimate_number;
        document.getElementById('proposalDate').value = p.proposal_date || '';
        document.getElementById('proposalStatus').value = p.status || 'Draft';
        document.getElementById('customerCompany').value = p.customer_company || '';
        document.getElementById('contactName').value = p.contact_name || '';
        document.getElementById('customerAddress').value = p.customer_address || '';
        
        try {
            const items = typeof p.line_items === 'string' ? JSON.parse(p.line_items) : p.line_items;
            lineItems = Array.isArray(items) && items.length > 0 ? items : [{ description: '', qty: 1, rate: 0 }];
        } catch(e) { lineItems = [{ description: '', qty: 1, rate: 0 }]; }
        renderLineItems();
    });
}

function addLineItem() { lineItems.push({ description: '', qty: 1, rate: 0 }); renderLineItems(); }

function removeLineItem(idx) { if (lineItems.length <= 1) return; lineItems.splice(idx, 1); renderLineItems(); }

function updateLineItem(idx, field, value) {
    lineItems[idx][field] = field === 'qty' || field === 'rate' ? parseFloat(value) || 0 : value;
    updateTotals();
}

function updateTotals() {
    let total = 0;
    lineItems.forEach(item => { total += (item.qty || 0) * (item.rate || 0); });
    document.getElementById('totalDisplay').textContent = '$' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function renderLineItems() {
    const container = document.getElementById('lineItemsContainer');
    container.innerHTML = lineItems.map((item, i) => {
        const amt = ((item.qty || 0) * (item.rate || 0)).toFixed(2);
        return `
            <div style="border:1px solid #e8e8ed;border-radius:8px;padding:14px;margin-bottom:10px;background:#fafbfc;">
                <div style="display:grid;grid-template-columns:1fr 80px 120px 100px 40px;gap:10px;align-items:end;">
                    <div class="form-group" style="margin:0;">
                        <label style="font-size:11px;color:#6b7280;">Description</label>
                        <input type="text" class="form-control" value="${(item.description || '').replace(/"/g, '&quot;')}" onchange="updateLineItem(${i},'description',this.value)" placeholder="Item description..." style="font-size:13px;">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="font-size:11px;color:#6b7280;">Qty</label>
                        <input type="number" class="form-control" value="${item.qty || 1}" min="1" step="1" oninput="updateLineItem(${i},'qty',this.value)" style="font-size:13px;">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="font-size:11px;color:#6b7280;">Rate</label>
                        <input type="number" class="form-control" value="${item.rate || 0}" min="0" step="0.01" oninput="updateLineItem(${i},'rate',this.value)" style="font-size:13px;">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="font-size:11px;color:#6b7280;">Amount</label>
                        <div class="form-control" style="background:#f0f0f5;font-weight:600;font-size:13px;">$${parseFloat(amt).toLocaleString('en-US', { minimumFractionDigits: 2 })}</div>
                    </div>
                    <button type="button" class="btn btn-outline btn-xs" onclick="removeLineItem(${i})" title="Remove" style="margin-bottom:2px;${lineItems.length <= 1 ? 'visibility:hidden;' : ''}">×</button>
                </div>
            </div>`;
    }).join('');
    updateTotals();
}

function saveProposal() {
    let total = 0;
    lineItems.forEach(item => { total += (item.qty || 0) * (item.rate || 0); });
    
    const payload = {
        csrf_token: CSRF_TOKEN,
        proposal_id: PROPOSAL_ID,
        proposal_date: document.getElementById('proposalDate').value,
        customer_company: document.getElementById('customerCompany').value,
        contact_name: document.getElementById('contactName').value,
        customer_address: document.getElementById('customerAddress').value,
        line_items: lineItems,
        total: Math.round(total * 100) / 100,
        status: document.getElementById('proposalStatus').value,
    };

    fetch('/api/proposals.php?action=save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        showNotification(data.message, data.success ? 'success' : 'error');
        if (data.success && !PROPOSAL_ID) {
            window.location.href = '/pages/proposal-form.php?id=' + data.proposal_id;
        }
    })
    .catch(() => showNotification('Network error', 'error'));
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
