<?php
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$pageTitle = __('Deal');
$currentPage = 'deals';
$dealId = intval($_GET['id'] ?? 0);
require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();
$companyId = $_SESSION["company_id"] ?? null;

// Fetch options directly in PHP to populate lists
$accounts = $db->query("SELECT account_id, account_name FROM accounts WHERE company_id = ? ORDER BY account_name", [$companyId])->fetchAll();
$contacts = $db->query("SELECT contact_id, first_name, last_name FROM contacts WHERE company_id = ? ORDER BY last_name, first_name", [$companyId])->fetchAll();
$users = $db->query("SELECT user_id, full_name FROM users WHERE company_id = ? AND status = 'Active' ORDER BY full_name", [$companyId])->fetchAll();
$stages = $db->query("SELECT stage_name, stage_label, probability FROM deal_stages WHERE company_id = 0 OR company_id = ? ORDER BY position", [$companyId])->fetchAll();
?>

<style>
.currency-input { display: flex; align-items: center; gap: 8px; }
.currency-input select { width: 90px; }
.currency-input input { flex: 1; }
</style>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;">
        <a href="/pages/deals.php" class="btn btn-outline" style="padding:8px 14px;">← <?php echo htmlspecialchars(__('Back to Pipeline')); ?></a>
        <h1><?= $dealId ? htmlspecialchars(__('Edit Deal')) : htmlspecialchars(__('New Deal')) ?></h1>
    </div>
    <div style="display:flex; gap:10px;">
        <?php if ($dealId): ?>
            <button type="button" class="btn btn-danger" onclick="deleteDeal()"><?php echo htmlspecialchars(__('Delete Deal')); ?></button>
        <?php endif; ?>
        <button type="button" class="btn btn-primary" onclick="saveDeal()"><?php echo htmlspecialchars(__('Save Deal')); ?></button>
    </div>
</div>

<div style="max-width:1000px;">
    <div class="card">
        <div class="card-header" style="padding: 18px 24px;">
            <h3 class="card-title" style="margin:0;"><?php echo htmlspecialchars(__('Deal Information')); ?></h3>
        </div>
        <div class="card-body" style="padding: 24px;">
        <div class="form-group">
            <label class="form-label"><?php echo htmlspecialchars(__('Deal Name *')); ?></label>
            <input type="text" id="dealName" class="form-control" placeholder="<?php echo htmlspecialchars(__('Deal Name')); ?>" required>
        </div>
        <div class="row-2" style="margin-top:16px;">
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Value')); ?></label>
                <div class="currency-input">
                    <select id="dealCurrency" class="form-control">
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                        <option value="GBP">GBP</option>
                        <option value="AED">AED</option>
                        <option value="SAR">SAR</option>
                        <option value="CAD">CAD</option>
                        <option value="AUD">AUD</option>
                    </select>
                    <input type="number" id="dealValue" class="form-control" placeholder="0.00" min="0" step="0.01">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Deal Type')); ?></label>
                <select id="dealType" class="form-control">
                    <option value="New Business"><?php echo htmlspecialchars(__('New Business')); ?></option>
                    <option value="Renewal"><?php echo htmlspecialchars(__('Renewal')); ?></option>
                    <option value="Upsell"><?php echo htmlspecialchars(__('Upsell')); ?></option>
                </select>
            </div>
        </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="padding: 18px 24px;">
            <h3 class="card-title" style="margin:0;"><?php echo htmlspecialchars(__('Pipeline Stage & Probability')); ?></h3>
        </div>
        <div class="card-body" style="padding: 24px;">
        <div class="row-3">
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Stage')); ?></label>
                <select id="dealStage" class="form-control" onchange="updateDefaultProbability()">
                    <?php foreach ($stages as $s): ?>
                        <option value="<?= $s['stage_name'] ?>" data-prob="<?= $s['probability'] ?>"><?= htmlspecialchars(__($s['stage_label'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Probability %')); ?></label>
                <input type="number" id="dealProbability" class="form-control" min="0" max="100" value="10">
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Expected Close Date')); ?></label>
                <input type="date" id="dealCloseDate" class="form-control">
            </div>
        </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="padding: 18px 24px;">
            <h3 class="card-title" style="margin:0;"><?php echo htmlspecialchars(__('CRM Links & Ownership')); ?></h3>
        </div>
        <div class="card-body" style="padding: 24px;">
        <div class="row-3">
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Account Link')); ?></label>
                <select id="dealAccountId" class="form-control">
                    <option value=""><?php echo htmlspecialchars(__('None')); ?></option>
                    <?php foreach ($accounts as $a): ?>
                        <option value="<?= $a['account_id'] ?>"><?= htmlspecialchars($a['account_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Primary Contact')); ?></label>
                <select id="dealContactId" class="form-control">
                    <option value=""><?php echo htmlspecialchars(__('None')); ?></option>
                    <?php foreach ($contacts as $c): ?>
                        <option value="<?= $c['contact_id'] ?>"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars(__('Assigned Owner')); ?></label>
                <select id="dealAssignedTo" class="form-control">
                    <option value=""><?php echo htmlspecialchars(__('Unassigned')); ?></option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div style="margin-top:16px;">
            <label class="form-label"><?php echo htmlspecialchars(__('Deal Description')); ?></label>
            <textarea id="dealDescription" class="form-control" placeholder="<?php echo htmlspecialchars(__('Deal Description')); ?>"></textarea>
        </div>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = "<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>";
const DEAL_ID = <?= $dealId ?>;

document.addEventListener('DOMContentLoaded', function() {
    if (DEAL_ID) {
        loadDeal();
    } else {
        updateDefaultProbability();
    }
});

function updateDefaultProbability() {
    if (DEAL_ID) return; // Do not overwrite existing probability
    const select = document.getElementById('dealStage');
    const selectedOption = select.options[select.selectedIndex];
    if (selectedOption) {
        const prob = selectedOption.getAttribute('data-prob');
        if (prob !== null) {
            document.getElementById('dealProbability').value = prob;
        }
    }
}

function loadDeal() {
    fetch('/api/deals.php?action=get&deal_id=' + DEAL_ID, { credentials: 'same-origin' })
    .then(r => r.json())
    .then(resp => {
        if (resp.success && resp.data) {
            var d = resp.data;
            document.getElementById('dealName').value = d.deal_name || '';
            document.getElementById('dealCurrency').value = d.currency || 'USD';
            document.getElementById('dealValue').value = d.deal_value || '';
            document.getElementById('dealStage').value = d.stage || 'prospecting';
            document.getElementById('dealProbability').value = d.probability || 10;
            document.getElementById('dealCloseDate').value = d.expected_close || '';
            document.getElementById('dealType').value = d.type || 'New Business';
            document.getElementById('dealAccountId').value = d.account_id || '';
            document.getElementById('dealContactId').value = d.contact_id || '';
            document.getElementById('dealAssignedTo').value = d.assigned_to || '';
            document.getElementById('dealDescription').value = d.description || '';
        } else {
            showNotification(resp.message || __('Failed to load deal'), 'error');
        }
    });
}

function saveDeal() {
    var dealName = document.getElementById('dealName').value.trim();
    if (!dealName) { showNotification(__('deal_name_is_required'), 'error'); return; }
    
    var payload = {
        csrf_token: CSRF_TOKEN,
        deal_name: dealName,
        currency: document.getElementById('dealCurrency').value,
        deal_value: parseFloat(document.getElementById('dealValue').value) || 0,
        stage: document.getElementById('dealStage').value,
        probability: parseInt(document.getElementById('dealProbability').value) || 0,
        expected_close: document.getElementById('dealCloseDate').value || null,
        type: document.getElementById('dealType').value,
        account_id: document.getElementById('dealAccountId').value || null,
        contact_id: document.getElementById('dealContactId').value || null,
        assigned_to: document.getElementById('dealAssignedTo').value || null,
        description: document.getElementById('dealDescription').value.trim()
    };
    
    var url = '/api/deals.php?action=' + (DEAL_ID ? 'update' : 'create');
    if (DEAL_ID) {
        payload.deal_id = DEAL_ID;
    }
    
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(function(data) {
        showNotification(data.message || (data.success ? __('Deal saved!') : __('Save failed')), data.success ? 'success' : 'error');
        if (data.success) {
            setTimeout(() => {
                window.location.href = '/pages/deals.php';
            }, 500);
        }
    })
    .catch(function() { showNotification(__('Network error'), 'error'); });
}

function deleteDeal() {
    showConfirm(__('are_you_sure_you_want_to_delete_this_deal'), function() {
        fetch('/api/deals.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ deal_id: DEAL_ID, csrf_token: CSRF_TOKEN })
        })
        .then(r => r.json())
        .then(resp => {
            showNotification(resp.message || (resp.success ? __('Deal deleted') : __('Delete failed')), resp.success ? 'success' : 'error');
            if (resp.success) {
                setTimeout(() => {
                    window.location.href = '/pages/deals.php';
                }, 500);
            }
        });
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
