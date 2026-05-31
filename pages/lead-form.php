<?php
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$pageTitle = 'Lead';
$currentPage = 'leads';
$leadId = intval($_GET['id'] ?? 0);
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:28px; }
.page-header h1 { margin:0; font-size:22px; font-weight:600; letter-spacing:-0.3px; color: var(--color-text); }
.card { background: var(--color-surface); border:1px solid var(--color-border); border-radius: var(--radius-md); padding:24px; margin-bottom:16px; box-shadow: var(--shadow-xs); }
.card-title { font-size:15px; font-weight:600; color: var(--color-text); margin:0 0 20px; }
.form-label { display:block; font-size:13px; font-weight:500; color: var(--color-text); margin-bottom:6px; }
.form-control { width:100%; padding:10px 12px; border:1px solid var(--color-border); border-radius: var(--radius-sm); font-size:14px; color: var(--color-text); background: var(--color-surface); box-sizing:border-box; transition: border-color var(--transition); }
.form-control:focus { outline:none; border-color: var(--color-accent); box-shadow:0 0 0 3px rgba(0,113,227,0.15); }
textarea.form-control { min-height:80px; resize:vertical; }
.row-2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.row-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; }
.btn { padding:10px 18px; border-radius: var(--radius-sm); font-size:14px; font-weight:500; cursor:pointer; transition: all var(--transition); border:none; text-decoration:none; display:inline-block; }
.btn-primary { background: var(--color-accent); color:#fff; }
.btn-primary:hover { background: var(--color-accent-hover); }
.btn-outline { background: var(--color-surface); color: var(--color-accent); border:1px solid var(--color-border); }
.btn-outline:hover { background: var(--color-bg); }
</style>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;">
        <a href="/pages/leads.php" class="btn btn-outline" style="padding:8px 14px;">← <?php echo htmlspecialchars(__('Back to Leads')); ?></a>
        <h1><?= $leadId ? htmlspecialchars(__('Edit Lead')) : htmlspecialchars(__('New Lead')) ?></h1>
    </div>
    <div>
        <button type="button" class="btn btn-primary" onclick="saveLead()"><?php echo htmlspecialchars(__('Save Lead')); ?></button>
    </div>
</div>

<div style="max-width:1000px;">
    <div class="card">
        <h3 class="card-title"><?php echo htmlspecialchars(__('Company Information')); ?></h3>
        <div class="row-2">
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Company Name *')); ?></label>
                <input type="text" id="companyName" class="form-control" placeholder="<?php echo htmlspecialchars(__('e.g., Acme Corp')); ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Lead Type')); ?></label>
                <select id="leadType" class="form-control">
                    <option value="Business"><?php echo htmlspecialchars(__('Business')); ?></option>
                    <option value="Individual"><?php echo htmlspecialchars(__('Individual')); ?></option>
                    <option value="Partner"><?php echo htmlspecialchars(__('Partner')); ?></option>
                    <option value="Reseller"><?php echo htmlspecialchars(__('Reseller')); ?></option>
                    <option value="Other"><?php echo htmlspecialchars(__('Other')); ?></option>
                </select>
            </div>
        </div>
        <div class="row-2" style="margin-top:16px;">
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Industry')); ?></label>
                <input type="text" id="industry" class="form-control" placeholder="<?php echo htmlspecialchars(__('e.g., Technology')); ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Company Size')); ?></label>
                <select id="companySize" class="form-control">
                    <option value=""><?php echo htmlspecialchars(__('Select...')); ?></option>
                    <option value="1-10"><?php echo htmlspecialchars(__('1-10 employees')); ?></option>
                    <option value="11-50"><?php echo htmlspecialchars(__('11-50 employees')); ?></option>
                    <option value="51-200"><?php echo htmlspecialchars(__('51-200 employees')); ?></option>
                    <option value="201-500"><?php echo htmlspecialchars(__('201-500 employees')); ?></option>
                    <option value="501+"><?php echo htmlspecialchars(__('501+ employees')); ?></option>
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title"><?php echo htmlspecialchars(__('Contact Details')); ?></h3>
        <div class="row-2">
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Contact Person *')); ?></label>
                <input type="text" id="contactPerson" class="form-control" placeholder="<?php echo htmlspecialchars(__('e.g., John Doe')); ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Title / Position')); ?></label>
                <input type="text" id="titlePosition" class="form-control" placeholder="<?php echo htmlspecialchars(__('e.g., CEO')); ?>">
            </div>
        </div>
        <div class="row-3" style="margin-top:16px;">
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Email')); ?></label>
                <input type="email" id="email" class="form-control" placeholder="john@example.com">
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Phone')); ?></label>
                <input type="tel" id="phone" class="form-control" placeholder="+1 555-0100">
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Mobile')); ?></label>
                <input type="tel" id="mobile" class="form-control" placeholder="+1 555-0100">
            </div>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title"><?php echo htmlspecialchars(__('Location & Source')); ?></h3>
        <div class="row-3">
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Country')); ?></label>
                <input type="text" id="countryInput" class="form-control" placeholder="<?php echo htmlspecialchars(__('e.g., United States')); ?>" autocomplete="off">
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('City')); ?></label>
                <input type="text" id="city" class="form-control" placeholder="<?php echo htmlspecialchars(__('City name')); ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Lead Source')); ?></label>
                <select id="leadSource" class="form-control">
                    <option value="Website"><?php echo htmlspecialchars(__('Website')); ?></option>
                    <option value="Referral"><?php echo htmlspecialchars(__('Referral')); ?></option>
                    <option value="Social Media"><?php echo htmlspecialchars(__('Social Media')); ?></option>
                    <option value="Email Campaign"><?php echo htmlspecialchars(__('Email Campaign')); ?></option>
                    <option value="Cold Call"><?php echo htmlspecialchars(__('Cold Call')); ?></option>
                    <option value="Trade Show"><?php echo htmlspecialchars(__('Trade Show')); ?></option>
                    <option value="Other"><?php echo htmlspecialchars(__('Other')); ?></option>
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title"><?php echo htmlspecialchars(__('Status & Priority')); ?></h3>
        <div class="row-3">
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Status')); ?></label>
                <select id="leadStatus" class="form-control">
                    <option value="New Lead"><?php echo htmlspecialchars(__('New Lead')); ?></option>
                    <option value="Contacted"><?php echo htmlspecialchars(__('Contacted')); ?></option>
                    <option value="Interested"><?php echo htmlspecialchars(__('Interested')); ?></option>
                    <option value="Not Interested"><?php echo htmlspecialchars(__('Not Interested')); ?></option>
                    <option value="Call Scheduled"><?php echo htmlspecialchars(__('Call Scheduled')); ?></option>
                    <option value="Proposal Sent"><?php echo htmlspecialchars(__('Proposal Sent')); ?></option>
                    <option value="Negotiation"><?php echo htmlspecialchars(__('Negotiation')); ?></option>
                    <option value="Won"><?php echo htmlspecialchars(__('Won')); ?></option>
                    <option value="Lost"><?php echo htmlspecialchars(__('Lost')); ?></option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Priority')); ?></label>
                <select id="priority" class="form-control">
                    <option value="Low"><?php echo htmlspecialchars(__('Low')); ?></option>
                    <option value="Medium" selected><?php echo htmlspecialchars(__('Medium')); ?></option>
                    <option value="High"><?php echo htmlspecialchars(__('High')); ?></option>
                    <option value="Urgent"><?php echo htmlspecialchars(__('Urgent')); ?></option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Assigned To')); ?></label>
                <select id="assignedTo" class="form-control">
                    <option value=""><?php echo htmlspecialchars(__('Unassigned')); ?></option>
                </select>
            </div>
        </div>
        <div style="margin-top:16px;">
            <label class="form-label"><?php echo htmlspecialchars(__('Notes')); ?></label>
            <textarea id="notes" class="form-control" placeholder="<?php echo htmlspecialchars(__('Additional notes about this lead...')); ?>"></textarea>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = "<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>";
const LEAD_ID = <?= $leadId ?>;
let currentUsers = [];

document.addEventListener('DOMContentLoaded', function() {
    loadUsers();
    if (LEAD_ID) loadLead();
});

function loadUsers() {
    fetch('/api/users.php?action=list', { credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.users) {
            var select = document.getElementById('assignedTo');
            data.users.forEach(function(u) {
                select.innerHTML += '<option value="' + u.user_id + '">' + escapeHtml(u.full_name || u.email) + '</option>';
            });
        }
    });
}

function loadLead() {
    fetch('/api/leads.php?action=detail&id=' + LEAD_ID, { credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.lead) {
            var l = data.lead;
            var fields = ['companyName','leadType','industry','companySize','contactPerson','titlePosition','email','phone','mobile','country','city','leadSource','leadStatus','priority','notes'];
            fields.forEach(function(f) {
                var el = document.getElementById(f === 'country' ? 'countryInput' : f);
                if (el && l[f.replace(/([A-Z])/g,'_$1').toLowerCase()]) {
                    el.value = l[f.replace(/([A-Z])/g,'_$1').toLowerCase()];
                }
            });
            if (l.assigned_to) document.getElementById('assignedTo').value = l.assigned_to;
        }
    });
}

function saveLead() {
    var companyName = document.getElementById('companyName').value.trim();
    if (!companyName) { showNotification(__('Company name is required'), 'error'); return; }
    
    var payload = {
        csrf_token: CSRF_TOKEN,
        company_name: companyName,
        lead_type: document.getElementById('leadType').value,
        industry: document.getElementById('industry').value,
        company_size: document.getElementById('companySize').value,
        contact_person: document.getElementById('contactPerson').value,
        title_position: document.getElementById('titlePosition').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        mobile: document.getElementById('mobile').value,
        country: document.getElementById('countryInput').value,
        city: document.getElementById('city').value,
        lead_source: document.getElementById('leadSource').value,
        lead_status: document.getElementById('leadStatus').value,
        priority: document.getElementById('priority').value,
        notes: document.getElementById('notes').value,
        assigned_to: document.getElementById('assignedTo').value || null
    };
    
    if (LEAD_ID) {
        fetch('/api/leads.php?action=update&id=' + LEAD_ID, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            showNotification(data.message || (data.success ? __('Lead updated!') : __('Update failed')), data.success ? 'success' : 'error');
            if (data.success) window.location.href = '/pages/leads.php';
        });
    } else {
        fetch('/api/leads.php?action=create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            showNotification(data.message || (data.success ? __('Lead created!') : __('Create failed')), data.success ? 'success' : 'error');
            if (data.success) window.location.href = '/pages/leads.php?saved=1';
        });
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[<>&"]/g, function(c) {
        return c === '<' ? '<' : c === '>' ? '>' : c === '&' ? '&' : '"';
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
