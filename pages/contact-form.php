<?php
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$pageTitle = 'Contact';
$currentPage = 'contacts';
$contactId = intval($_GET['id'] ?? 0);
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
        <a href="/pages/contacts.php" class="btn btn-outline" style="padding:8px 14px;">← Back to Contacts</a>
        <h1><?= $contactId ? 'Edit Contact' : 'New Contact' ?></h1>
    </div>
    <div>
        <button type="button" class="btn btn-primary" onclick="saveContact()">Save Contact</button>
    </div>
</div>

<div style="max-width:1000px;">
    <div class="card">
        <h3 class="card-title">Personal Information</h3>
        <div class="row-2">
            <div class="form-group">
                <label class="form-label">First Name *</label>
                <input type="text" id="firstName" class="form-control" placeholder="John">
            </div>
            <div class="form-group">
                <label class="form-label">Last Name *</label>
                <input type="text" id="lastName" class="form-control" placeholder="Doe">
            </div>
        </div>
        <div class="row-2" style="margin-top:16px;">
            <div class="form-group">
                <label class="form-label">Email *</label>
                <input type="email" id="email" class="form-control" placeholder="john@example.com">
            </div>
            <div class="form-group">
                <label class="form-label">Phone</label>
                <input type="tel" id="phone" class="form-control" placeholder="+1 555-0100">
            </div>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title">Company Details</h3>
        <div class="row-2">
            <div class="form-group">
                <label class="form-label">Company</label>
                <input type="text" id="company" class="form-control" placeholder="Acme Corp">
            </div>
            <div class="form-group">
                <label class="form-label">Job Title</label>
                <input type="text" id="jobTitle" class="form-control" placeholder="CEO">
            </div>
        </div>
        <div style="margin-top:16px;">
            <label class="form-label">Address</label>
            <textarea id="address" class="form-control" placeholder="123 Main St, City, Country"></textarea>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title">Classification</h3>
        <div class="row-3">
            <div class="form-group">
                <label class="form-label">Contact Type</label>
                <select id="contactType" class="form-control">
                    <option value="Customer">Customer</option>
                    <option value="Prospect">Prospect</option>
                    <option value="Partner">Partner</option>
                    <option value="Vendor">Vendor</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Assigned To</label>
                <select id="assignedTo" class="form-control">
                    <option value="">Unassigned</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Tags</label>
                <input type="text" id="tags" class="form-control" placeholder="vip, decision-maker">
            </div>
        </div>
        <div style="margin-top:16px;">
            <label class="form-label">Notes</label>
            <textarea id="notes" class="form-control" placeholder="Additional notes..."></textarea>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = "<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>";
const CONTACT_ID = <?= $contactId ?>;

document.addEventListener('DOMContentLoaded', function() {
    loadUsers();
    if (CONTACT_ID) loadContact();
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

function loadContact() {
    fetch('/api/contacts.php?id=' + CONTACT_ID, { credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.contact) {
            var c = data.contact;
            ['firstName','lastName','email','phone','company','jobTitle','address','contactType','tags','notes'].forEach(function(f) {
                var el = document.getElementById(f);
                if (el && c[f.replace(/([A-Z])/g,'_$1').toLowerCase()]) {
                    el.value = c[f.replace(/([A-Z])/g,'_$1').toLowerCase()];
                }
            });
        }
    });
}

function saveContact() {
    var firstName = document.getElementById('firstName').value.trim();
    var lastName = document.getElementById('lastName').value.trim();
    var email = document.getElementById('email').value.trim();
    if (!firstName || !lastName) { showNotification('First and last name are required', 'error'); return; }
    if (!email) { showNotification('Email is required', 'error'); return; }
    
    var payload = {
        csrf_token: CSRF_TOKEN,
        first_name: firstName,
        last_name: lastName,
        email: email,
        phone: document.getElementById('phone').value,
        company: document.getElementById('company').value,
        job_title: document.getElementById('jobTitle').value,
        address: document.getElementById('address').value,
        contact_type: document.getElementById('contactType').value,
        tags: document.getElementById('tags').value,
        notes: document.getElementById('notes').value,
        assigned_to: document.getElementById('assignedTo').value || null
    };
    
    var url = CONTACT_ID ? '/api/contacts.php?action=update&id=' + CONTACT_ID : '/api/contacts.php?action=create';
    var method = CONTACT_ID ? 'PUT' : 'POST';
    
    fetch(url, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(function(data) {
        showNotification(data.message || (data.success ? 'Contact saved!' : 'Save failed'), data.success ? 'success' : 'error');
        if (data.success) window.location.href = '/pages/contacts.php';
    })
    .catch(function() { showNotification('Network error', 'error'); });
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[<>&"]/g, function(c) {
        return { '<': '<', '>': '>', '&': '&', '"': '"' }[c];
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
