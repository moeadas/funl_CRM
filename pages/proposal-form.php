<?php
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$pageTitle = 'Proposal';
$currentPage = 'proposals';
$proposalId = intval($_GET['id'] ?? 0);
// Allow arriving pre-linked from a lead/contact detail page:
//   proposal-form.php?lead_id=12  /  ?contact_id=7
$prefillLeadId    = intval($_GET['lead_id'] ?? 0);
$prefillContactId = intval($_GET['contact_id'] ?? 0);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <a href="/pages/proposals.php" class="btn btn-outline btn-sm">&larr; Back</a>
        <h1><?= $proposalId ? 'Edit Proposal' : 'New Proposal' ?></h1>
    </div>
    <div class="header-actions">
        <button type="button" class="btn btn-primary btn-sm" onclick="saveProposal()"><?php echo __('Save'); ?></button>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
    <div>
        <div class="card" style="padding:20px;">
            <h3 style="margin:0 0 16px;font-size:15px;font-weight:600;"><?php echo __('Proposal Details'); ?></h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label class="form-label">Estimate #</label>
                    <input type="text" id="estimateNumber" class="form-control" readonly style="background:#f5f5f7;">
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('Date'); ?></label>
                    <input type="date" id="proposalDate" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo __('Status'); ?></label>
                    <select id="proposalStatus" class="form-control">
                        <option value="Draft"><?php echo __('Draft'); ?></option>
                        <option value="Sent"><?php echo __('Sent'); ?></option>
                        <option value="Accepted"><?php echo __('Accepted'); ?></option>
                        <option value="Declined"><?php echo __('Declined'); ?></option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card" style="padding:20px;margin-top:16px;">
            <h3 style="margin:0 0 16px;font-size:15px;font-weight:600;"><?php echo __('Customer Information'); ?></h3>
            <div class="form-group">
                <label class="form-label"><?php echo __('Linked to Lead / Contact'); ?></label>
                <select id="linkedTo" class="form-control" onchange="applyLinkedTo()">
                    <option value=""><?php echo __('Not linked — enter details manually'); ?></option>
                </select>
                <p class="text-muted" style="font-size:12px;margin-top:4px;">
                    <?php echo __('Linking lets this proposal show up on that lead/contact and be sent to them directly.'); ?>
                </p>
            </div>
            <div class="form-group">
                <label class="form-label">Company Name *</label>
                <input type="text" id="customerCompany" class="form-control" placeholder="e.g., Acme Corp">
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo __('Contact Name'); ?></label>
                <input type="text" id="contactName" class="form-control" placeholder="e.g., John Doe">
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo __('Address'); ?></label>
                <textarea id="customerAddress" class="form-control" rows="3"></textarea>
            </div>
        </div>
    </div>

    <div>
        <div class="card" style="padding:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <h3 style="margin:0;font-size:15px;font-weight:600;"><?php echo __('Line Items'); ?></h3>
                <button type="button" class="btn btn-outline btn-sm" onclick="addLineItem()">+ Add Item</button>
            </div>
            <div id="lineItemsContainer"></div>
            <div style="display:flex;justify-content:flex-end;margin-top:16px;padding-top:12px;border-top:2px solid #e8e8ed;">
                <div style="text-align:right;">
                    <span class="text-muted" style="font-size:13px;"><?php echo __('TOTAL'); ?></span>
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
    // The picker must be populated BEFORE loadProposal() tries to re-select the
    // saved linkage, otherwise the <option> does not exist yet and the value is
    // silently dropped.
    loadLinkables().then(function () {
        if (PROPOSAL_ID) loadProposal();
    });
    if (PROPOSAL_ID) {
        // handled above once the picker is ready
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

const PREFILL_LEAD_ID    = <?php echo (int)$prefillLeadId; ?>;
const PREFILL_CONTACT_ID = <?php echo (int)$prefillContactId; ?>;
let LINKABLES = [];

// Populate the lead/contact picker. Runs before loadProposal() resolves so the
// saved linkage can be re-selected once options exist.
function loadLinkables() {
    return fetch('/api/proposals.php?action=linkables')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            LINKABLES = data.linkables || [];
            const sel = document.getElementById('linkedTo');
            const groups = { lead: [], contact: [] };
            LINKABLES.forEach(function (x) { (groups[x.type] || []).push(x); });
            [['lead', <?php echo json_encode(__('Leads')); ?>], ['contact', <?php echo json_encode(__('Contacts')); ?>]].forEach(function (g) {
                if (!groups[g[0]].length) return;
                const og = document.createElement('optgroup');
                og.label = g[1];
                groups[g[0]].forEach(function (x) {
                    const o = document.createElement('option');
                    o.value = x.type + ':' + x.id;
                    o.textContent = x.label + (x.email ? ' — ' + x.email : '');
                    og.appendChild(o);
                });
                sel.appendChild(og);
            });
            if (!PROPOSAL_ID) {
                if (PREFILL_LEAD_ID)    { sel.value = 'lead:' + PREFILL_LEAD_ID; applyLinkedTo(); }
                if (PREFILL_CONTACT_ID) { sel.value = 'contact:' + PREFILL_CONTACT_ID; applyLinkedTo(); }
            }
        });
}

// Copy the linked record's details into the free-text fields so the proposal
// still prints correctly, while keeping the real FK in the dropdown value.
function applyLinkedTo() {
    const v = document.getElementById('linkedTo').value;
    if (!v) return;
    const [type, id] = v.split(':');
    const x = LINKABLES.find(function (l) { return l.type === type && String(l.id) === id; });
    if (!x) return;
    const comp = document.getElementById('customerCompany');
    const cont = document.getElementById('contactName');
    if (!comp.value && x.company) comp.value = x.company;
    if (!comp.value && !x.company) comp.value = x.label;
    if (!cont.value && x.contact_name) cont.value = x.contact_name;
}

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
        if (p.lead_id)    document.getElementById('linkedTo').value = 'lead:' + p.lead_id;
        if (p.contact_id) document.getElementById('linkedTo').value = 'contact:' + p.contact_id;
        
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
    if (field === 'qty' || field === 'rate') {
        const amt = (lineItems[idx].qty || 0) * (lineItems[idx].rate || 0);
        const amtElem = document.getElementById(`line-item-amount-${idx}`);
        if (amtElem) {
            amtElem.textContent = '$' + amt.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    }
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
                        <label style="font-size:11px;color:#6b7280;">${window.__('Description')}</label>
                        <input type="text" class="form-control" value="${(item.description || '').replace(/"/g, '&quot;')}" onchange="updateLineItem(${i},'description',this.value)" placeholder="${window.__('Item description...')}" style="font-size:13px;">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="font-size:11px;color:#6b7280;">${window.__('Qty')}</label>
                        <input type="number" class="form-control" value="${item.qty || 1}" min="1" step="1" oninput="updateLineItem(${i},'qty',this.value)" style="font-size:13px;">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="font-size:11px;color:#6b7280;">${window.__('Rate')}</label>
                        <input type="number" class="form-control" value="${item.rate || 0}" min="0" step="0.01" oninput="updateLineItem(${i},'rate',this.value)" style="font-size:13px;">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="font-size:11px;color:#6b7280;">${window.__('Amount')}</label>
                        <div id="line-item-amount-${i}" class="form-control" style="background:#f0f0f5;font-weight:600;font-size:13px;">$${parseFloat(amt).toLocaleString('en-US', { minimumFractionDigits: 2 })}</div>
                    </div>
                    <button type="button" class="btn btn-outline btn-xs" onclick="removeLineItem(${i})" title="${window.__('Remove')}" style="margin-bottom:2px;${lineItems.length <= 1 ? 'visibility:hidden;' : ''}">×</button>
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
        lead_id: (document.getElementById('linkedTo').value.split(':')[0] === 'lead') ? document.getElementById('linkedTo').value.split(':')[1] : null,
        contact_id: (document.getElementById('linkedTo').value.split(':')[0] === 'contact') ? document.getElementById('linkedTo').value.split(':')[1] : null,
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
