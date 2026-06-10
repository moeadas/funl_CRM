<?php
/**
 * White Label CRM V2 - Populate Email List (standalone, no popup)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireLogin();
requireRole('Sales Manager');

$listId = intval($_GET['list_id'] ?? 0);
if (!$listId) {
    header('Location: /pages/email-lists.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT list_id, name FROM email_lists WHERE list_id = ?");
$stmt->execute([$listId]);
$list = $stmt->fetch();
if (!$list) {
    header('Location: /pages/email-lists.php');
    exit;
}

$csrfToken = generateCSRFToken();
$pageTitle = __('add_leads_to') . ' ' . $list['name'];
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;">
        <a href="/pages/email-lists.php" class="btn btn-outline" style="padding:8px 14px;display:inline-flex;align-items:center;gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            <?php echo htmlspecialchars(__('Back to Audiences')); ?>
        </a>
        <div>
            <h1><?php echo htmlspecialchars(__('Add leads to')); ?> <span style="color:#6366f1;"><?php echo htmlspecialchars($list['name']); ?></span></h1>
        </div>
    </div>
</div>

<div style="max-width:760px;">
    <form id="populateForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="list_id" value="<?php echo (int)$list['list_id']; ?>">

        <div class="card">
            <div class="card-header" style="padding:18px 24px;">
                <h3 class="card-title" style="margin:0;"><?php echo htmlspecialchars(__('Filter leads')); ?></h3>
            </div>
            <div class="card-body" style="padding:24px;">
                <p class="text-muted" style="margin-top:0;margin-bottom:20px;"><?php echo htmlspecialchars(__('Select the filters you want — matching leads will be added to this list.')); ?></p>

                <div class="row-2">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label"><?php echo htmlspecialchars(__('Lead status')); ?></label>
                        <select name="status" class="form-control" style="padding:10px 14px;">
                            <option value=""><?php echo htmlspecialchars(__('All statuses')); ?></option>
                            <option value="New Lead"><?php echo htmlspecialchars(__('New Lead')); ?></option>
                            <option value="Contacted"><?php echo htmlspecialchars(__('Contacted')); ?></option>
                            <option value="Interested"><?php echo htmlspecialchars(__('Interested')); ?></option>
                            <option value="Won"><?php echo htmlspecialchars(__('Won')); ?></option>
                            <option value="On Hold"><?php echo htmlspecialchars(__('On Hold')); ?></option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label"><?php echo htmlspecialchars(__('Country')); ?></label>
                        <input type="text" name="country" class="form-control"
                               placeholder="<?php echo htmlspecialchars(__('e.g., United States, Germany')); ?>"
                               style="padding:10px 14px;">
                    </div>
                </div>

                <div class="row-2" style="margin-top:20px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label"><?php echo htmlspecialchars(__('Lead type')); ?></label>
                        <select name="lead_type" class="form-control" style="padding:10px 14px;">
                            <option value=""><?php echo htmlspecialchars(__('All types')); ?></option>
                            <option value="Stable"><?php echo htmlspecialchars(__('Stable')); ?></option>
                            <option value="Owner"><?php echo htmlspecialchars(__('Owner')); ?></option>
                            <option value="Breeder"><?php echo htmlspecialchars(__('Breeder')); ?></option>
                            <option value="Trainer"><?php echo htmlspecialchars(__('Trainer')); ?></option>
                            <option value="Veterinarian"><?php echo htmlspecialchars(__('Veterinarian')); ?></option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label"><?php echo htmlspecialchars(__('Priority')); ?></label>
                        <select name="priority" class="form-control" style="padding:10px 14px;">
                            <option value=""><?php echo htmlspecialchars(__('All priorities')); ?></option>
                            <option value="Urgent"><?php echo htmlspecialchars(__('Urgent')); ?></option>
                            <option value="High"><?php echo htmlspecialchars(__('High')); ?></option>
                            <option value="Medium"><?php echo htmlspecialchars(__('Medium')); ?></option>
                            <option value="Low"><?php echo htmlspecialchars(__('Low')); ?></option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-top:20px;margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Country contains')); ?></label>
                    <input type="text" name="country_contains" class="form-control"
                           placeholder="<?php echo htmlspecialchars(__('e.g., united states')); ?>"
                           style="padding:10px 14px;">
                </div>
            </div>
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:16px;">
            <a href="/pages/email-lists.php" class="btn btn-outline"><?php echo htmlspecialchars(__('Cancel')); ?></a>
            <button type="submit" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <?php echo htmlspecialchars(__('Add matching leads')); ?>
            </button>
        </div>
    </form>
</div>

<script>
document.getElementById('populateForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    const listId = fd.get('list_id');
    const filters = {};
    fd.forEach((v, k) => { if (k !== 'list_id' && k !== 'csrf_token' && v) filters[k] = v; });

    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = '<?php echo htmlspecialchars(__('Adding'), ENT_QUOTES); ?>…';

    fetch('/api/email.php?action=list_populate', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ list_id: parseInt(listId), filters: filters, csrf_token: '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>' })
    }).then(r => r.json()).then(d => {
        if (d.success) {
            showNotification(d.message, 'success');
            setTimeout(() => window.location.href = '/pages/email-lists.php', 1500);
        } else {
            showNotification(d.message || 'Error', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> <?php echo htmlspecialchars(__('Add matching leads'), ENT_QUOTES); ?>';
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
