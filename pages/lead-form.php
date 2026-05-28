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
.page-header h1 { margin:0; font-size:22px; font-weight:600; letter-spacing:-0.3px; }
.card { background:#fff; border:1px solid #e5e5e7; border-radius:12px; padding:24px; margin-bottom:16px; }
.card-title { font-size:15px; font-weight:600; color:#1d1d1f; margin:0 0 20px; }
.form-label { display:block; font-size:13px; font-weight:500; color:#424245; margin-bottom:6px; }
.form-control { width:100%; padding:10px 12px; border:1px solid #d2d2d7; border-radius:8px; font-size:14px; color:#1d1d1f; background:#fff; box-sizing:border-box; transition:border-color 0.2s; }
.form-control:focus { outline:none; border-color:#0071e3; box-shadow:0 0 0 3px rgba(0,113,227,0.15); }
textarea.form-control { min-height:80px; resize:vertical; }
.row-2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.row-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; }
.btn { padding:10px 18px; border-radius:8px; font-size:14px; font-weight:500; cursor:pointer; transition:all 0.2s; border:none; text-decoration:none; display:inline-block; }
.btn-primary { background:#0071e3; color:#fff; }
.btn-primary:hover { background:#0077ed; }
.btn-outline { background:#fff; color:#0071e3; border:1px solid #0071e3; }
.btn-outline:hover { background:#f5f5f7; }
</style>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;">
        <a href="/pages/leads.php" class="btn btn-outline" style="padding:8px 14px;">← Back to Leads</a>
        <h1><?= $leadId ? 'Edit Lead' : 'New Lead' ?></h1>
    </div>
    <div>
        <button type="button" class="btn btn-primary" onclick="saveLead()">Save Lead</button>
    </div>
</div>

<div style="max-width:1000px;">
    <div class="card">
        <h3 class="card-title">Company Information</h3>
        <div class="row-2">
            <div class="form-group">
                <label class="form-label">Company Name *</label>
                <input type="text" id="companyName" class="form-control" placeholder="e.g., Acme Corp">
            </div>
            <div class="form-group">
                <label class="form-label">Lead Type</label>
                <select id="leadType" class="form-control">
                    <option value="Prospect">Prospect</option>
                    <option value="Customer">Customer</option>
                    <option value="Partner">Partner</option>
                    <option value="Competitor">Competitor</option>
                    <option value="Other">Other</option>
                </select>
            </div>
        </div>
        <div class="row-2" style="margin-top:16px;">
            <div class="form-group">
                <label class="form-label">Industry</label>
                <input type="text" id="industry" class="form-control" placeholder="e.g., Technology">
            </div>
            <div class="form-group">
                <label class="form-label">Company Size</label>
                <select id="companySize" class="form-control">
                    <option value="">Select...</option>
                    <option value="1-10">1-10 employees</option>
                    <option value="11-50">11-50 employees</option>
                    <option value="51-200">51-200 employees</option>
                    <option value="201-500">201-500 employees</option>
                    <option value="501+">501+ employees</option>
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title">Contact Details</h3>
        <div class="row-2">
            <div class="form-group">
                <label class="form-label">Contact Person *</label>
                <input type="text" id="contactPerson" class="form-control" placeholder="e.g., John Doe">
            </div>
            <div class="form-group">
                <label class="form-label">Title / Position</label>
                <input type="text" id="titlePosition" class="form-control" placeholder="e.g., CEO">
            </div>
        </div>
        <div class="row-3" style="margin-top:16px;">
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" id="email" class="form-control" placeholder="john@example.com">
            </div>
            <div class="form-group">
                <label class="form-label">Phone</label>
                <input type="tel" id="phone" class="form-control" placeholder="+1 555-0100">
            </div>
            <div class="form-group">
                <label class="form-label">Mobile</label>
                <input type="tel" id="mobile" class="form-control" placeholder="+1 555-0100">
            </div>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title">Location & Source</h3>
        <div class="row-3">
            <div class="form-group">
                <label class="form-label">Country</label>
                <select id="country" class="form-control">
                    <option value="">Select Country...</option>
                    <option value="United States">United States</option>
                    <option value="United Kingdom">United Kingdom</option>
                    <option value="Germany">Germany</option>
                    <option value="France">France</option>
                    <option value="Spain">Spain</option>
                    <option value="Jordan">Jordan</option>
                    <option value="UAE">UAE</option>
                    <option value="Saudi Arabia">Saudi Arabia</option>
                    <option value="Egypt">Egypt</option>
                    <option value="India">India</option>
                    <option value="China">China</option>
                    <option value="Japan">Japan</option>
                    <option value="Australia">Australia</option>
                    <option value="Brazil">Brazil</option>
                    <option value="Mexico">Mexico</option>
                    <option value="Canada">Canada</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">City</label>
                <input type="text" id="city" class="form-control" placeholder="City name">
            </div>
            <div class="form-group">
                <label class="form-label">Lead Source</label>
                <select id="leadSource" class="form-control">
                    <option value="Website">Website</option>
                    <option value="Referral">Referral</option>
                    <option value="Social Media">Social Media</option>
                    <option value="Email Campaign">Email Campaign</option>
                    <option value="Cold Call">Cold Call</option>
                    <option value="Trade Show">Trade Show</option>
                    <option value="Other">Other</option>
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title">Status & Priority</h3>
        <div class="row-3">
            <div class="form-group">
                <label class="form-label">Status</label>
                <select id="leadStatus" class="form-control">
                    <option value="New Lead">New Lead</option>
                    <option value="Contacted">Contacted</option>
                    <option value="Interested">Interested</option>
                    <option value="Not Interested">Not Interested</option>
                    <option value="Call Scheduled">Call Scheduled</option>
                    <option value="Proposal Sent">Proposal Sent</option>
                    <option value="Negotiation">Negotiation</option>
                    <option value="Won">Won</option>
                    <option value="Lost">Lost</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Priority</label>
                <select id="priority" class="form-control">
                    <option value="Low">Low</option>
                    <option value="Medium" selected>Medium</option>
                    <option value="High">High</option>
                    <option value="Urgent">Urgent</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Assigned To</label>
                <select id="assignedTo" class="form-control">
                    <option value="">Unassigned</option>
                </select>
            </div>
        </div>
        <div style="margin-top:16px;">
            <label class="form-label">Notes</label>
            <textarea id="notes" class="form-control" placeholder="Additional notes about this lead..."></textarea>
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
                var el = document.getElementById(f);
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
    if (!companyName) { showNotification('Company name is required', 'error'); return; }
    
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
        country: document.getElementById('country').value,
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
            showNotification(data.message || (data.success ? 'Lead updated!' : 'Update failed'), data.success ? 'success' : 'error');
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
            showNotification(data.message || (data.success ? 'Lead created!' : 'Create failed'), data.success ? 'success' : 'error');
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
