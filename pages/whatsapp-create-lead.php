<?php
/**
 * White Label CRM - WhatsApp Create Lead from Number (standalone, no popup)
 */
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$fromNumber = preg_replace('/[^0-9+]/', '', $_GET['from'] ?? '');
if (!$fromNumber) {
    header('Location: /pages/whatsapp-dashboard.php');
    exit;
}

$csrfToken = generateCSRFToken();
$pageTitle = __('create_new_lead');
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;">
        <a href="/pages/whatsapp-dashboard.php" class="btn btn-outline" style="padding:8px 14px;display:inline-flex;align-items:center;gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            <?php echo htmlspecialchars(__('Back to WhatsApp')); ?>
        </a>
        <h1><?php echo htmlspecialchars(__('Create new lead')); ?></h1>
    </div>
</div>

<div style="max-width:560px;">
    <div class="card">
        <div class="card-header" style="padding:18px 24px;background:#f0fdf4;">
            <h3 class="card-title" style="margin:0;display:flex;align-items:center;gap:8px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                <?php echo htmlspecialchars(__('Create lead from WhatsApp')); ?>
            </h3>
        </div>
        <form id="createLeadForm" style="padding:24px;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" id="createLeadFromNumber" value="<?php echo htmlspecialchars($fromNumber); ?>">

            <p class="text-muted" style="margin-top:0;">
                <?php echo htmlspecialchars(__('This number is not yet linked to a lead. Create one so all future messages are auto-associated.')); ?>
            </p>

            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Phone number')); ?></label>
                <input type="text" id="createLeadPhone" class="form-control" readonly
                       value="<?php echo htmlspecialchars($fromNumber); ?>"
                       style="padding:10px 14px;background:#f3f4f6;">
            </div>

            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Contact person')); ?></label>
                <input type="text" id="createLeadName" class="form-control"
                       placeholder="<?php echo htmlspecialchars(__('e.g., John Smith')); ?>"
                       style="padding:10px 14px;">
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label"><?php echo htmlspecialchars(__('Company name')); ?></label>
                <input type="text" id="createLeadCompany" class="form-control"
                       placeholder="<?php echo htmlspecialchars(__('e.g., Acme Corp (optional)')); ?>"
                       style="padding:10px 14px;">
            </div>
        </form>
        <div style="padding:16px 24px;display:flex;justify-content:flex-end;gap:8px;border-top:1px solid #e5e7eb;">
            <a href="/pages/whatsapp-dashboard.php" class="btn btn-outline"><?php echo htmlspecialchars(__('Cancel')); ?></a>
            <button type="button" class="btn btn-primary" id="createLeadSubmitBtn" style="background:#16a34a;border-color:#16a34a;" onclick="submitCreateLead()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px;"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                <?php echo htmlspecialchars(__('Create lead')); ?>
            </button>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;

function submitCreateLead() {
    const phone = document.getElementById('createLeadPhone').value;
    const name = document.getElementById('createLeadName').value.trim();
    const company = document.getElementById('createLeadCompany').value.trim();

    const btn = document.getElementById('createLeadSubmitBtn');
    btn.disabled = true;
    btn.textContent = 'Creating…';

    fetch('/api/whatsapp.php?action=create_lead_from_message', {
        method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            phone: phone,
            contact_person: name,
            company_name: company,
            csrf_token: CSRF_TOKEN
        })
    }).then(r => r.json()).then(resp => {
        if (resp.success) {
            showNotification('Lead created', 'success');
            setTimeout(() => window.location.href = '/pages/whatsapp-dashboard.php', 600);
        } else {
            showNotification(resp.message || 'Failed', 'error');
            btn.disabled = false;
            btn.textContent = 'Create lead';
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
