<?php
/**
 * White Label CRM V2 — Populate Email List
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
startSecureSession();
requireLogin();
requireRole('Sales Manager');

$listId = intval($_GET['id'] ?? 0);
if (!$listId) {
    header('Location: email-lists.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT * FROM email_lists WHERE list_id = ?");
$stmt->execute([$listId]);
$list = $stmt->fetch();

if (!$list) {
    $_SESSION['error'] = 'Email list not found';
    header('Location: email-lists.php');
    exit;
}

$csrfToken = generateCSRFToken();
$pageTitle = __('add_leads_to');
include '../includes/header.php';
?>

<style>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 24px; box-shadow: var(--shadow-xs); }
.form-group { margin-bottom: 16px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } }
.form-label { display: block; font-size: 13px; font-weight: 500; color: var(--color-text); margin-bottom: 6px; }
.form-control { width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); font-size: 14px; color: var(--color-text); background: var(--color-surface); box-sizing: border-box; }
.form-control:focus { outline: none; border-color: var(--color-accent); }
.btn { padding: 10px 18px; border-radius: var(--radius-sm); font-size: 14px; font-weight: 500; cursor: pointer; transition: all var(--transition); border: none; text-decoration: none; display: inline-block; }
.btn-primary { background: var(--color-accent); color: #fff; }
.btn-primary:hover { background: var(--color-accent-hover); }
.btn-outline { background: var(--color-surface); color: var(--color-text); border: 1px solid var(--color-border); margin-right: 8px; }
.btn-outline:hover { background: var(--color-bg); }
</style>

<div class="page-container">
    <div class="page-header">
        <div style="display:flex;align-items:center;gap:16px;">
            <a href="/pages/email-lists.php" class="btn btn-outline" style="padding:8px 14px;">← <?php echo __('back'); ?></a>
            <h1 class="page-title"><?php echo __('add_leads_to'); ?> "<?php echo htmlspecialchars($list['name']); ?>"</h1>
        </div>
    </div>

    <div style="max-width: 700px;">
        <div class="card">
            <p class="text-muted" style="margin-bottom: 20px; font-size: 14px;"><?php echo __('populate_list_desc'); ?></p>
            <form id="populateForm">
                <input type="hidden" name="list_id" value="<?php echo $listId; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?php echo __('lead_status'); ?></label>
                        <select name="status" class="form-control">
                            <option value=""><?php echo __('all_status'); ?></option>
                            <option value="New Lead"><?php echo __('new_lead'); ?></option>
                            <option value="Contacted"><?php echo __('contacted'); ?></option>
                            <option value="Interested"><?php echo __('interested'); ?></option>
                            <option value="Won"><?php echo __('won'); ?></option>
                            <option value="On Hold"><?php echo __('on_hold'); ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo __('country'); ?></label>
                        <input type="text" name="country" class="form-control" placeholder="<?php echo __('e_g_countries'); ?>">
                    </div>
                </div>

                <div class="form-row" style="margin-top: 12px;">
                    <div class="form-group">
                        <label class="form-label"><?php echo __('lead_type'); ?></label>
                        <select name="lead_type" class="form-control">
                            <option value=""><?php echo __('all_types'); ?></option>
                            <option value="Stable"><?php echo __('Stable'); ?></option>
                            <option value="Owner"><?php echo __('Owner'); ?></option>
                            <option value="Breeder"><?php echo __('Breeder'); ?></option>
                            <option value="Trainer"><?php echo __('Trainer'); ?></option>
                            <option value="Veterinarian"><?php echo __('Veterinarian'); ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo __('priority'); ?></label>
                        <select name="priority" class="form-control">
                            <option value=""><?php echo __('all_priorities'); ?></option>
                            <option value="Urgent"><?php echo __('urgent'); ?></option>
                            <option value="High"><?php echo __('high'); ?></option>
                            <option value="Medium"><?php echo __('medium'); ?></option>
                            <option value="Low"><?php echo __('low'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 12px;">
                    <label class="form-label"><?php echo __('country_contains'); ?></label>
                    <input type="text" name="country_contains" class="form-control" placeholder="<?php echo __('e_g_united_states'); ?>">
                </div>
                
                <div style="margin-top: 28px; display: flex; justify-content: flex-end;">
                    <a href="/pages/email-lists.php" class="btn btn-outline"><?php echo __('cancel'); ?></a>
                    <button type="submit" class="btn btn-primary"><?php echo __('add_matching_leads'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const CSRF = '<?php echo $csrfToken; ?>';

document.getElementById('populateForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    const listId = fd.get('list_id');
    const filters = {};
    fd.forEach((v, k) => { if (k !== 'list_id' && v) filters[k] = v; });

    fetch('/api/email.php?action=list_populate', {
        method: 'POST', 
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ list_id: parseInt(listId), filters: filters, csrf_token: CSRF })
    }).then(r => r.json()).then(d => {
        if (d.success) {
            showNotification(d.message || 'Leads added successfully', 'success');
            setTimeout(() => {
                window.location.href = '/pages/email-lists.php';
            }, 1000);
        } else {
            showNotification(d.message, 'error');
        }
    }).catch(() => showNotification('Error populating list', 'error'));
});
</script>

<?php include '../includes/footer.php'; ?>
