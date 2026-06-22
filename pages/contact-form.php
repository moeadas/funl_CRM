<?php
require_once __DIR__ . "/../includes/countries.php";
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$pageTitle = __('Contact');
$currentPage = 'contacts';
$contactId = intval($_GET['id'] ?? 0);
require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$companyId = $_SESSION["company_id"] ?? null;

// Fetch options in PHP to render directly
$accounts = $db->query("SELECT account_id, account_name FROM accounts WHERE company_id = ? ORDER BY account_name", [$companyId])->fetchAll();
$users = $db->query("SELECT user_id, full_name FROM users WHERE company_id = ? AND status = 'Active' ORDER BY full_name", [$companyId])->fetchAll();
$tags = $db->query("SELECT * FROM contact_tags WHERE company_id = ? ORDER BY tag_name", [$companyId])->fetchAll();
?><div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;">
        <a href="/pages/contacts.php" class="btn btn-outline" style="padding:8px 14px;">← <?php echo htmlspecialchars(__('Back to Contacts')); ?></a>
        <h1><?= $contactId ? htmlspecialchars(__('Edit Contact')) : htmlspecialchars(__('New Contact')) ?></h1>
    </div>
    <div>
        <button type="button" class="btn btn-primary" onclick="saveContact()"><?php echo htmlspecialchars(__('Save Contact')); ?></button>
    </div>
</div>

<div style="max-width:1000px;">
    <div class="card">
        <h3 class="card-title"><?php echo htmlspecialchars(__('Personal Information')); ?></h3>
        <div class="row-2">
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('First Name *')); ?></label>
                <input type="text" id="firstName" class="form-control" placeholder="<?php echo htmlspecialchars(__('John')); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Last Name *')); ?></label>
                <input type="text" id="lastName" class="form-control" placeholder="<?php echo htmlspecialchars(__('Doe')); ?>" required>
            </div>
        </div>
        <div class="row-2" style="margin-top:16px;">
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Email')); ?></label>
                <input type="email" id="email" class="form-control" placeholder="<?php echo htmlspecialchars(__('john@example.com')); ?>">
            </div>
            <div class="form-group">
                <?php echo renderPhonePicker(['id' => 'phone', 'label' => __('Phone'), 'value' => '']); ?>
            </div>
        </div>
        <div class="row-2" style="margin-top:16px;">
            <div class="form-group">
                <?php echo renderPhonePicker(['id' => 'mobile', 'label' => __('Mobile'), 'value' => '']); ?>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Job Title / Position')); ?></label>
                <input type="text" id="title" class="form-control" placeholder="<?php echo htmlspecialchars(__('CEO')); ?>">
            </div>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title"><?php echo htmlspecialchars(__('Address & Location')); ?></h3>
        <div class="row-3">
            <div class="form-group">
                <?php echo renderCountrySelect(['id' => 'country', 'label' => __('Country'), 'value' => '']); ?>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('City')); ?></label>
                <input type="text" id="city" class="form-control" placeholder="<?php echo htmlspecialchars(__('e.g., San Francisco')); ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Address')); ?></label>
                <input type="text" id="address" class="form-control" placeholder="<?php echo htmlspecialchars(__('123 Main St')); ?>">
            </div>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title"><?php echo htmlspecialchars(__('Classification & Relationship')); ?></h3>
        <div class="row-3">
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Account')); ?></label>
                <select id="accountId" class="form-control">
                    <option value=""><?php echo htmlspecialchars(__('No Account')); ?></option>
                    <?php foreach ($accounts as $a): ?>
                        <option value="<?= $a['account_id'] ?>"><?= htmlspecialchars($a['account_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Status')); ?></label>
                <select id="contactStatus" class="form-control">
                    <option value="Active"><?php echo htmlspecialchars(__('Active')); ?></option>
                    <option value="Inactive"><?php echo htmlspecialchars(__('Inactive')); ?></option>
                    <option value="Do Not Contact"><?php echo htmlspecialchars(__('Do Not Contact')); ?></option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Assigned To')); ?></label>
                <select id="assignedTo" class="form-control">
                    <option value=""><?php echo htmlspecialchars(__('Unassigned')); ?></option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title"><?php echo htmlspecialchars(__('Tags & Additional Details')); ?></h3>
        <div class="form-group">
            <label class="form-label"><?php echo htmlspecialchars(__('Tags')); ?></label>
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
            <label class="form-label"><?php echo htmlspecialchars(__('Notes')); ?></label>
            <textarea id="notes" class="form-control" placeholder="<?php echo htmlspecialchars(__('Additional notes about this contact...')); ?>"></textarea>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = "<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>";
const CONTACT_ID = <?= $contactId ?>;

window.COUNTRY_DIAL_CODES = <?php $codes = []; foreach (getCountriesList() as $c) { $codes[] = ["code" => $c[0], "dial" => $c[2]]; } echo json_encode($codes); ?>;
function parsePhoneForPicker(phone) { if (!phone) return null; phone = String(phone).trim(); if (phone[0] !== "+") phone = "+" + phone; var cs = window.COUNTRY_DIAL_CODES || []; var sorted = cs.slice().sort(function(a,b){return b.dial.length-a.dial.length;}); for (var i=0; i<sorted.length; i++) { if (phone.indexOf(sorted[i].dial)===0) { var n = phone.substring(sorted[i].dial.length).replace(/^[\s\-()]+/, ""); return {code:sorted[i].code, dial_code:sorted[i].dial, national:n}; } } return null; }

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
            showNotification(resp.message || __('Failed to load contact'), 'error');
        }
    });
}

function saveContact() {
    var firstName = document.getElementById('firstName').value.trim();
    var lastName = document.getElementById('lastName').value.trim();
    if (!firstName || !lastName) { showNotification(__('First and last name are required'), 'error'); return; }
    
    var tagIds = [...document.querySelectorAll('#tagSelector input:checked')].map(i => parseInt(i.value));
    
    var payload = {
        csrf_token: CSRF_TOKEN,
        first_name: firstName,
        last_name: lastName,
        email: document.getElementById('email').value.trim(),
        phone: document.getElementById('phone_full')?.value || document.getElementById('phone').value.trim(),
        mobile: document.getElementById('mobile_full')?.value || document.getElementById('mobile').value.trim(),
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
        showNotification(data.message || (data.success ? __('Contact saved!') : __('Save failed')), data.success ? 'success' : 'error');
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
