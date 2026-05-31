<?php
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$pageTitle = 'Account';
$currentPage = 'contacts';
$accountId = intval($_GET['id'] ?? 0);
require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$companyId = $_SESSION["company_id"] ?? null;

// Fetch active users for assignment
$users = $db->query("SELECT user_id, full_name FROM users WHERE company_id = ? AND status = 'Active' ORDER BY full_name", [$companyId])->fetchAll();
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
        <a href="/pages/contacts.php" class="btn btn-outline" style="padding:8px 14px;">← Back to Contacts</a>
        <h1><?= $accountId ? 'Edit Account' : 'New Account' ?></h1>
    </div>
    <div>
        <button type="button" class="btn btn-primary" onclick="saveAccount()">Save Account</button>
    </div>
</div>

<div style="max-width:1000px;">
    <div class="card">
        <h3 class="card-title">Company Profile</h3>
        <div class="row-2">
            <div class="form-group">
                <label class="form-label">Account Name *</label>
                <input type="text" id="accountName" class="form-control" placeholder="e.g., Acme Corp" required>
            </div>
            <div class="form-group">
                <label class="form-label">Type</label>
                <select id="accountType" class="form-control">
                    <option value="Customer">Customer</option>
                    <option value="Prospect">Prospect</option>
                    <option value="Partner">Partner</option>
                    <option value="Vendor">Vendor</option>
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
                <label class="form-label">Website</label>
                <input type="text" id="website" class="form-control" placeholder="https://...">
            </div>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title">Contact & Location</h3>
        <div class="row-3">
            <div class="form-group">
                <label class="form-label">Phone</label>
                <input type="tel" id="phone" class="form-control" placeholder="+1 555-0100">
            </div>
            <div class="form-group">
                <label class="form-label">Country</label>
                <input type="text" id="country" class="form-control" placeholder="e.g., United States">
            </div>
            <div class="form-group">
                <label class="form-label">City</label>
                <input type="text" id="city" class="form-control" placeholder="e.g., Chicago">
            </div>
        </div>
        <div style="margin-top:16px;">
            <label class="form-label">Address</label>
            <textarea id="address" class="form-control" placeholder="123 Corporate Blvd..."></textarea>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title">Metadata & Ownership</h3>
        <div class="row-3">
            <div class="form-group">
                <label class="form-label">Annual Revenue</label>
                <input type="number" step="0.01" id="annualRevenue" class="form-control" placeholder="e.g., 5000000">
            </div>
            <div class="form-group">
                <label class="form-label">Employees Count</label>
                <input type="number" id="employeeCount" class="form-control" placeholder="e.g., 150">
            </div>
            <div class="form-group">
                <label class="form-label">Assigned Owner</label>
                <select id="assignedTo" class="form-control">
                    <option value="">Unassigned</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div style="margin-top:16px;">
            <label class="form-label">Description / Summary</label>
            <textarea id="description" class="form-control" placeholder="Notes or summary about the account..."></textarea>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = "<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>";
const ACCOUNT_ID = <?= $accountId ?>;

document.addEventListener('DOMContentLoaded', function() {
    if (ACCOUNT_ID) loadAccount();
});

function loadAccount() {
    fetch('/api/contacts.php?action=get_account&account_id=' + ACCOUNT_ID, { credentials: 'same-origin' })
    .then(r => r.json())
    .then(resp => {
        if (resp.success && resp.data) {
            var a = resp.data;
            document.getElementById('accountName').value = a.account_name || '';
            document.getElementById('accountType').value = a.account_type || 'Customer';
            document.getElementById('industry').value = a.industry || '';
            document.getElementById('website').value = a.website || '';
            document.getElementById('phone').value = a.phone || '';
            document.getElementById('country').value = a.country || '';
            document.getElementById('city').value = a.city || '';
            document.getElementById('address').value = a.address || '';
            document.getElementById('annualRevenue').value = a.annual_revenue || '';
            document.getElementById('employeeCount').value = a.employee_count || '';
            document.getElementById('assignedTo').value = a.assigned_to || '';
            document.getElementById('description').value = a.description || '';
        } else {
            showNotification(resp.message || 'Failed to load account', 'error');
        }
    });
}

function saveAccount() {
    var accountName = document.getElementById('accountName').value.trim();
    if (!accountName) { showNotification('Account name is required', 'error'); return; }
    
    var payload = {
        csrf_token: CSRF_TOKEN,
        account_name: accountName,
        account_type: document.getElementById('accountType').value,
        industry: document.getElementById('industry').value.trim(),
        website: document.getElementById('website').value.trim(),
        phone: document.getElementById('phone').value.trim(),
        country: document.getElementById('country').value.trim(),
        city: document.getElementById('city').value.trim(),
        address: document.getElementById('address').value.trim(),
        annual_revenue: document.getElementById('annualRevenue').value || null,
        employee_count: document.getElementById('employeeCount').value || null,
        assigned_to: document.getElementById('assignedTo').value || null,
        description: document.getElementById('description').value.trim()
    };
    
    var url = '/api/contacts.php?action=' + (ACCOUNT_ID ? 'update_account' : 'create_account');
    if (ACCOUNT_ID) {
        payload.account_id = ACCOUNT_ID;
    }
    
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(function(data) {
        showNotification(data.message || (data.success ? 'Account saved!' : 'Save failed'), data.success ? 'success' : 'error');
        if (data.success) {
            setTimeout(() => {
                window.location.href = '/pages/contacts.php';
            }, 500);
        }
    })
    .catch(function() { showNotification('Network error', 'error'); });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
