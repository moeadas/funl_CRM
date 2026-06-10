<?php
/**
 * White Label CRM - WhatsApp Link to Lead (standalone, no popup)
 */
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$companyId = $_SESSION['company_id'] ?? null;
$userId = getCurrentUserId();
$fromNumber = preg_replace('/[^0-9+]/', '', $_GET['from'] ?? '');

if (!$fromNumber) {
    header('Location: /pages/whatsapp-dashboard.php');
    exit;
}

$db = Database::getInstance();
$csrfToken = generateCSRFToken();
$pageTitle = __('link_to_existing_lead');
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;">
        <a href="/pages/whatsapp-dashboard.php" class="btn btn-outline" style="padding:8px 14px;display:inline-flex;align-items:center;gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            <?php echo htmlspecialchars(__('Back to WhatsApp')); ?>
        </a>
        <h1><?php echo htmlspecialchars(__('Link to existing lead')); ?></h1>
    </div>
</div>

<div style="max-width:560px;">
    <div class="card">
        <div class="card-header" style="padding:18px 24px;background:#fff7ed;">
            <h3 class="card-title" style="margin:0;display:flex;align-items:center;gap:8px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                <?php echo htmlspecialchars(__('Link messages to lead')); ?>
            </h3>
        </div>
        <div class="card-body" style="padding:24px;">
            <p class="text-muted" style="margin-top:0;">
                <?php echo htmlspecialchars(__('Messages from')); ?> <strong id="linkFromNumber"><?php echo htmlspecialchars($fromNumber); ?></strong>
                <?php echo htmlspecialchars(__('will be linked to the selected lead.')); ?>
            </p>

            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Search lead')); ?></label>
                <input type="text" id="linkLeadSearch" class="form-control"
                       placeholder="<?php echo htmlspecialchars(__('Type lead name or company...')); ?>"
                       oninput="searchLeadsForLink(this.value)" autocomplete="off"
                       style="padding:10px 14px;">
            </div>

            <div id="linkLeadResults" style="max-height:240px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:8px;display:none;"></div>

            <input type="hidden" id="linkLeadId">
            <div id="linkLeadSelected" style="display:none;margin-top:12px;padding:12px 14px;background:#f3f4f6;border-radius:8px;font-size:14px;display:flex;align-items:center;justify-content:space-between;">
                <span id="linkLeadSelectedName"></span>
                <button type="button" onclick="clearLinkLead()" style="border:none;background:none;color:#dc2626;font-size:13px;cursor:pointer;font-weight:500;">Clear</button>
            </div>
        </div>
        <div style="padding:16px 24px;display:flex;justify-content:flex-end;gap:8px;border-top:1px solid #e5e7eb;">
            <a href="/pages/whatsapp-dashboard.php" class="btn btn-outline"><?php echo htmlspecialchars(__('Cancel')); ?></a>
            <button type="button" class="btn btn-primary" style="background:#f59e0b;border-color:#f59e0b;" id="linkSubmitBtn" onclick="submitLinkToLead('<?php echo htmlspecialchars($fromNumber, ENT_QUOTES); ?>')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                <?php echo htmlspecialchars(__('Link messages')); ?>
            </button>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
const FROM_NUMBER = <?= json_encode($fromNumber) ?>;
let searchTimeout = null;

function searchLeadsForLink(query) {
    clearTimeout(searchTimeout);
    if (!query || query.length < 2) {
        document.getElementById('linkLeadResults').style.display = 'none';
        return;
    }
    searchTimeout = setTimeout(function() {
        fetch('/api/leads.php?action=search&q=' + encodeURIComponent(query), { credentials: 'same-origin' })
        .then(r => r.json())
        .then(resp => {
            const container = document.getElementById('linkLeadResults');
            const leads = (resp && resp.data && resp.data.leads) || resp.leads || [];
            if (!leads.length) {
                container.innerHTML = '<div style="padding:14px;text-align:center;color:#9ca3af;font-size:13px;">No leads found</div>';
                container.style.display = 'block';
                return;
            }
            container.innerHTML = leads.map(l => `
                <div onclick="selectLinkLead(${l.lead_id}, '${(l.contact_person || l.company_name || '').replace(/'/g, "\\'")}')" style="padding:10px 12px;cursor:pointer;border-bottom:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <strong>${escapeHtml(l.contact_person || l.company_name || '')}</strong>
                        ${l.company_name && l.contact_person ? '<br><small style="color:#6b7280;">' + escapeHtml(l.company_name) + '</small>' : ''}
                    </div>
                    <small style="color:#6b7280;">${escapeHtml(l.phone || '')}</small>
                </div>
            `).join('');
            container.style.display = 'block';
        });
    }, 250);
}

function selectLinkLead(id, name) {
    document.getElementById('linkLeadId').value = id;
    document.getElementById('linkLeadSelectedName').textContent = name;
    document.getElementById('linkLeadSelected').style.display = 'flex';
    document.getElementById('linkLeadResults').style.display = 'none';
    document.getElementById('linkLeadSearch').value = name;
}

function clearLinkLead() {
    document.getElementById('linkLeadId').value = '';
    document.getElementById('linkLeadSelected').style.display = 'none';
    document.getElementById('linkLeadSearch').value = '';
    document.getElementById('linkLeadSearch').focus();
}

function submitLinkToLead() {
    const leadId = document.getElementById('linkLeadId').value;
    if (!leadId) {
        showNotification('Please select a lead first', 'error');
        return;
    }
    const btn = document.getElementById('linkSubmitBtn');
    btn.disabled = true;
    btn.textContent = 'Linking…';

    fetch('/api/whatsapp.php?action=link_to_lead', {
        method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ lead_id: parseInt(leadId), from_number: FROM_NUMBER, csrf_token: CSRF_TOKEN })
    }).then(r => r.json()).then(resp => {
        if (resp.success) {
            showNotification('Messages linked to lead', 'success');
            setTimeout(() => window.location.href = '/pages/whatsapp-dashboard.php', 600);
        } else {
            showNotification(resp.message || 'Failed', 'error');
            btn.disabled = false;
            btn.textContent = 'Link messages';
        }
    });
}

function escapeHtml(s) { return String(s == null ? '' : s).replace(/[<>&"']/g, c => c === '<' ? '<' : c === '>' ? '>' : c === '&' ? '&' : '"'); }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
