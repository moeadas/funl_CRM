<?php
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$pageTitle = 'Contact';
$currentPage = 'contacts';
$contactId = intval($_GET['id'] ?? 0);
require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$companyId = $_SESSION["company_id"] ?? null;

// Fetch options in PHP to render directly
$accounts = $db->query("SELECT account_id, account_name FROM accounts WHERE company_id = ? ORDER BY account_name", [$companyId])->fetchAll();
$users = $db->query("SELECT user_id, full_name FROM users WHERE company_id = ? AND status = 'Active' ORDER BY full_name", [$companyId])->fetchAll();
$tags = $db->query("SELECT * FROM contact_tags WHERE company_id = ? ORDER BY tag_name", [$companyId])->fetchAll();
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

/* Tag Selector */
.tag-selector { display: flex; flex-wrap: wrap; gap: 8px; }
.tag-option { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 20px; font-size: 13px; cursor: pointer; transition: opacity 0.2s; user-select: none; border: 1px solid transparent; }
.tag-option input { display: none; }
.tag-option:hover { opacity: 0.85; }
.tag-option.active { border-color: var(--color-text) !important; font-weight: 600; }
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
                <input type="text" id="firstName" class="form-control" placeholder="John" required>
            </div>
            <div class="form-group">
                <label class="form-label">Last Name *</label>
                <input type="text" id="lastName" class="form-control" placeholder="Doe" required>
            </div>
        </div>
        <div class="row-2" style="margin-top:16px;">
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" id="email" class="form-control" placeholder="john@example.com">
            </div>
            <div class="form-group">
                <label class="form-label">Phone</label>
                <input type="tel" id="phone" class="form-control" placeholder="+1 555-0100">
            </div>
        </div>
        <div class="row-2" style="margin-top:16px;">
            <div class="form-group">
                <label class="form-label">Mobile</label>
                <input type="tel" id="mobile" class="form-control" placeholder="+1 555-0100">
            </div>
            <div class="form-group">
                <label class="form-label">Job Title / Position</label>
                <input type="text" id="title" class="form-control" placeholder="CEO">
            </div>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title">Address & Location</h3>
        <div class="row-3">
            <div class="form-group">
                <label class="form-label">Country</label>
                <input type="text" id="country" class="form-control" placeholder="e.g., United States">
            </div>
            <div class="form-group">
                <label class="form-label">City</label>
                <input type="text" id="city" class="form-control" placeholder="e.g., San Francisco">
            </div>
            <div class="form-group">
                <label class="form-label">Address</label>
                <input type="text" id="address" class="form-control" placeholder="123 Main St">
            </div>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title">Classification & Relationship</h3>
        <div class="row-3">
            <div class="form-group">
                <label class="form-label">Account</label>
                <select id="accountId" class="form-control">
                    <option value="">No Account</option>
                    <?php foreach ($accounts as $a): ?>
                        <option value="<?= $a['account_id'] ?>"><?= htmlspecialchars($a['account_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select id="contactStatus" class="form-control">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                    <option value="Do Not Contact">Do Not Contact</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Assigned To</label>
                <select id="assignedTo" class="form-control">
                    <option value="">Unassigned</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title">Tags & Additional Details</h3>
        <div class="form-group">
            <label class="form-label">Tags</label>
            <div class="tag-selector" id="tagSelector">
                <?php foreach ($tags as $t): ?>
                    <label class="tag-option" style="background:<?= htmlspecialchars($t['tag_color']) ?>15; color:<?= htmlspecialchars($t['tag_color']) ?>; border: 1px solid <?= htmlspecialchars($t['tag_color']) ?>40;" onclick="toggleTagOption(this)">
                        <input type="checkbox" value="<?= $t['tag_id'] ?>" name="tags">
                        <?= htmlspecialchars($t['tag_name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div style="margin-top:16px;">
            <label class="form-label">Notes</label>
            <textarea id="notes" class="form-control" placeholder="Additional notes about this contact..."></textarea>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = "<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>";
const CONTACT_ID = <?= $contactId ?>;

document.addEventListener('DOMContentLoaded', function() {
    if (CONTACT_ID) loadContact();
});

function toggleTagOption(labelEl) {
    const input = labelEl.querySelector('input');
    // Because clicking labels naturally triggers standard browser behavior on inputs,
    // we manage styling toggle here.
    setTimeout(() => {
        if (input.checked) {
            labelEl.classList.add('active');
        } else {
            labelEl.classList.remove('active');
        }
    }, 10);
}

function loadContact() {
    fetch('/api/contacts.php?action=get_contact&contact_id=' + CONTACT_ID, { credentials: 'same-origin' })
    .then(r => r.json())
    .then(resp => {
        if (resp.success && resp.data) {
            var c = resp.data;
            document.getElementById('firstName').value = c.first_name || '';
            document.getElementById('lastName').value = c.last_name || '';
            document.getElementById('email').value = c.email || '';
            document.getElementById('phone').value = c.phone || '';
            document.getElementById('mobile').value = c.mobile || '';
            document.getElementById('title').value = c.title || '';
            document.getElementById('country').value = c.country || '';
            document.getElementById('city').value = c.city || '';
            document.getElementById('address').value = c.address || '';
            document.getElementById('accountId').value = c.account_id || '';
            document.getElementById('contactStatus').value = c.contact_status || 'Active';
            document.getElementById('assignedTo').value = c.assigned_to || '';
            document.getElementById('notes').value = c.notes || '';
            
            if (c.tags) {
                c.tags.forEach(t => {
                    const input = document.querySelector(`#tagSelector input[value="${t.tag_id}"]`);
                    if (input) {
                        input.checked = true;
                        input.closest('.tag-option').classList.add('active');
                    }
                });
            }
        } else {
            showNotification(resp.message || 'Failed to load contact', 'error');
        }
    });
}

function saveContact() {
    var firstName = document.getElementById('firstName').value.trim();
    var lastName = document.getElementById('lastName').value.trim();
    if (!firstName || !lastName) { showNotification('First and last name are required', 'error'); return; }
    
    var tagIds = [...document.querySelectorAll('#tagSelector input:checked')].map(i => parseInt(i.value));
    
    var payload = {
        csrf_token: CSRF_TOKEN,
        first_name: firstName,
        last_name: lastName,
        email: document.getElementById('email').value.trim(),
        phone: document.getElementById('phone').value.trim(),
        mobile: document.getElementById('mobile').value.trim(),
        title: document.getElementById('title').value.trim(),
        country: document.getElementById('country').value.trim(),
        city: document.getElementById('city').value.trim(),
        address: document.getElementById('address').value.trim(),
        account_id: document.getElementById('accountId').value || null,
        contact_status: document.getElementById('contactStatus').value,
        assigned_to: document.getElementById('assignedTo').value || null,
        notes: document.getElementById('notes').value,
        tag_ids: tagIds
    };
    
    var url = '/api/contacts.php?action=' + (CONTACT_ID ? 'update_contact' : 'create_contact');
    if (CONTACT_ID) {
        payload.contact_id = CONTACT_ID;
    }
    
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(function(data) {
        showNotification(data.message || (data.success ? 'Contact saved!' : 'Save failed'), data.success ? 'success' : 'error');
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
