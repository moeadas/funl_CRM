<?php
require_once __DIR__ . "/../includes/countries.php";
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$pageTitle = __('Account');
$currentPage = 'contacts';
$accountId = intval($_GET['id'] ?? 0);
require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$companyId = $_SESSION["company_id"] ?? null;

// Fetch active users for assignment
$users = $db->query("SELECT user_id, full_name FROM users WHERE company_id = ? AND status = 'Active' ORDER BY full_name", [$companyId])->fetchAll();
?>



<div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;">
        <a href="/pages/contacts.php" class="btn btn-outline" style="padding:8px 14px;">← <?php echo htmlspecialchars(__('Back to Contacts')); ?></a>
        <h1><?= $accountId ? htmlspecialchars(__('Edit Account')) : htmlspecialchars(__('New Account')) ?></h1>
    </div>
    <div>
        <button type="button" class="btn btn-primary" onclick="saveAccount()"><?php echo htmlspecialchars(__('Save Account')); ?></button>
    </div>
</div>

<div style="max-width:1000px;">
    <div class="card">
        <h3 class="card-title"><?php echo htmlspecialchars(__('Company Profile')); ?></h3>
        <div class="row-2">
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Account Name *')); ?></label>
                <input type="text" id="accountName" class="form-control" placeholder="<?php echo htmlspecialchars(__('e.g., Acme Corp')); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Type')); ?></label>
                <select id="accountType" class="form-control">
                    <option value="Customer"><?php echo htmlspecialchars(__('Customer')); ?></option>
                    <option value="Prospect"><?php echo htmlspecialchars(__('Prospect')); ?></option>
                    <option value="Partner"><?php echo htmlspecialchars(__('Partner')); ?></option>
                    <option value="Vendor"><?php echo htmlspecialchars(__('Vendor')); ?></option>
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
                <label class="form-label"><?php echo htmlspecialchars(__('Website')); ?></label>
                <input type="text" id="website" class="form-control" placeholder="https://...">
            </div>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title"><?php echo htmlspecialchars(__('Contact & Location')); ?></h3>
        <div class="row-3">
            <div class="form-group">
                <?php echo renderPhonePicker(['id' => 'phone', 'label' => __('Phone'), 'value' => '']); ?>
            </div>
            <div class="form-group">
                <?php echo renderCountrySelect(['id' => 'country', 'label' => __('Country'), 'value' => '']); ?>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('City')); ?></label>
                <input type="text" id="city" class="form-control" placeholder="<?php echo htmlspecialchars(__('e.g., Chicago')); ?>">
            </div>
        </div>
        <div style="margin-top:16px;">
            <label class="form-label"><?php echo htmlspecialchars(__('Address')); ?></label>
            <textarea id="address" class="form-control" placeholder="<?php echo htmlspecialchars(__('Address')); ?>"></textarea>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title"><?php echo htmlspecialchars(__('Metadata & Ownership')); ?></h3>
        <div class="row-3">
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Annual Revenue')); ?></label>
                <input type="number" step="0.01" id="annualRevenue" class="form-control" placeholder="<?php echo htmlspecialchars(__('e.g., 5000000')); ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Employees Count')); ?></label>
                <input type="number" id="employeeCount" class="form-control" placeholder="<?php echo htmlspecialchars(__('e.g., 150')); ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Assigned Owner')); ?></label>
                <select id="assignedTo" class="form-control">
                    <option value=""><?php echo htmlspecialchars(__('Unassigned')); ?></option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div style="margin-top:16px;">
            <label class="form-label"><?php echo htmlspecialchars(__('Description / Summary')); ?></label>
            <textarea id="description" class="form-control" placeholder="<?php echo htmlspecialchars(__('Description / Summary')); ?>"></textarea>
        </div>
    </div>
</div>

<script>
window.COUNTRY_DIAL_CODES = <?php $codes = []; foreach (getCountriesList() as $c) { $codes[] = ["code" => $c[0], "dial" => $c[2]]; } echo json_encode($codes); ?>;
function parsePhoneForPicker(phone) { if (!phone) return null; phone = String(phone).trim(); if (phone[0] !== "+") phone = "+" + phone; var cs = window.COUNTRY_DIAL_CODES || []; var sorted = cs.slice().sort(function(a,b){return b.dial.length-a.dial.length;}); for (var i=0; i<sorted.length; i++) { if (phone.indexOf(sorted[i].dial)===0) { var n = phone.substring(sorted[i].dial.length).replace(/^[\s\-()]+/, ""); return {code:sorted[i].code, dial_code:sorted[i].dial, national:n}; } } return null; }
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
            showNotification(resp.message || __('Failed to load account'), 'error');
        }
    });
}

function saveAccount() {
    var accountName = document.getElementById('accountName').value.trim();
    if (!accountName) { showNotification(__('account_name_is_required'), 'error'); return; }
    
    var payload = {
        csrf_token: CSRF_TOKEN,
        account_name: accountName,
        account_type: document.getElementById('accountType').value,
        industry: document.getElementById('industry').value.trim(),
        website: document.getElementById('website').value.trim(),
        phone: document.getElementById('phone_full')?.value || document.getElementById('phone').value.trim(),
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
        showNotification(data.message || (data.success ? __('Account saved!') : __('Save failed')), data.success ? 'success' : 'error');
        if (data.success) {
            setTimeout(() => {
                window.location.href = '/pages/contacts.php';
            }, 500);
        }
    })
    .catch(function() { showNotification(__('Network error'), 'error'); });
}
</script>
<script src="/assets/js/phone-picker.js"></script>


<?php include __DIR__ . '/../includes/footer.php'; ?>
