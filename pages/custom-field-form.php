<?php
/**
 * White Label CRM V2 — Custom Lead Field Form
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
startSecureSession();
requireLogin();
requireRole('Admin');

$db = Database::getInstance()->getConnection();
$companyId = getCurrentCompanyId();
$fieldId = intval($_GET['id'] ?? 0);

$field = null;
if ($fieldId) {
    // Admins can manage company custom fields
    $stmt = $db->prepare("SELECT * FROM custom_fields WHERE field_id = ? AND (company_id = ? OR company_id IS NULL)");
    $stmt->execute([$fieldId, $companyId]);
    $field = $stmt->fetch();
    if (!$field) {
        $_SESSION['error'] = __('Custom field not found');
        header('Location: settings.php?tab=custom_fields');
        exit;
    }
}

$csrfToken = generateCSRFToken();
$pageTitle = $fieldId ? __('Edit Custom Field') : __('Add Custom Field');
include '../includes/header.php';
?>

<style>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 24px; box-shadow: var(--shadow-xs); }
.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 13px; font-weight: 500; color: var(--color-text); margin-bottom: 6px; }
.form-control { width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); font-size: 14px; color: var(--color-text); background: var(--color-surface); box-sizing: border-box; }
.form-control:focus { outline: none; border-color: var(--color-accent); }
.form-control:disabled { background: #f3f4f6; color: var(--color-text-secondary); cursor: not-allowed; }
.btn { padding: 10px 18px; border-radius: var(--radius-sm); font-size: 14px; font-weight: 500; cursor: pointer; transition: all var(--transition); border: none; text-decoration: none; display: inline-block; }
.btn-primary { background: var(--color-accent); color: #fff; }
.btn-primary:hover { background: var(--color-accent-hover); }
.btn-outline { background: var(--color-surface); color: var(--color-text); border: 1px solid var(--color-border); margin-right: 8px; }
.btn-outline:hover { background: var(--color-bg); }
</style>

<div class="page-container">
    <div class="page-header">
        <div style="display:flex;align-items:center;gap:16px;">
            <a href="/pages/settings.php?tab=custom_fields" class="btn btn-outline" style="padding:8px 14px;">← <?php echo __('back'); ?></a>
            <h1 class="page-title"><?php echo $fieldId ? __('Edit Custom Field') : __('Add Custom Field'); ?></h1>
        </div>
    </div>

    <div style="max-width: 580px;">
        <div class="card">
            <form id="custom-field-form" onsubmit="saveCustomField(event)">
                <input type="hidden" id="field-id" value="<?php echo $fieldId; ?>">
                
                <div class="form-group">
                    <label class="form-label"><?php echo __('Field Label'); ?> *</label>
                    <input type="text" id="field-label" class="form-control" required placeholder="e.g. Lead Temperature" value="<?php echo htmlspecialchars($field['field_label'] ?? ''); ?>" oninput="generateFieldName(this.value)">
                </div>

                <div class="form-group">
                    <label class="form-label"><?php echo __('Field Code Name'); ?> * (<?php echo __('Alphanumeric/Underscore'); ?>)</label>
                    <input type="text" id="field-name" class="form-control" required placeholder="e.g. lead_temp" pattern="^[a-zA-Z0-9_]+$" value="<?php echo htmlspecialchars($field['field_name'] ?? ''); ?>" <?php echo $fieldId ? 'disabled' : ''; ?>>
                </div>

                <div class="form-group">
                    <label class="form-label"><?php echo __('Field Type'); ?></label>
                    <select id="field-type" class="form-control">
                        <option value="text" <?php echo ($field && $field['field_type'] === 'text') ? 'selected' : ''; ?>><?php echo __('Text Input'); ?></option>
                        <option value="textarea" <?php echo ($field && $field['field_type'] === 'textarea') ? 'selected' : ''; ?>><?php echo __('Textarea (Multiline)'); ?></option>
                        <option value="number" <?php echo ($field && $field['field_type'] === 'number') ? 'selected' : ''; ?>><?php echo __('Number'); ?></option>
                        <option value="select" <?php echo ($field && $field['field_type'] === 'select') ? 'selected' : ''; ?>><?php echo __('Dropdown List'); ?></option>
                        <option value="date" <?php echo ($field && $field['field_type'] === 'date') ? 'selected' : ''; ?>><?php echo __('Date'); ?></option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label"><?php echo __('Required Field'); ?></label>
                    <select id="field-required" class="form-control">
                        <option value="0" <?php echo ($field && $field['is_required'] == 0) ? 'selected' : ''; ?>><?php echo __('Optional'); ?></option>
                        <option value="1" <?php echo ($field && $field['is_required'] == 1) ? 'selected' : ''; ?>><?php echo __('Required'); ?></option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label"><?php echo __('Sort Order (Lower shows first)'); ?></label>
                    <input type="number" id="field-sort" class="form-control" value="<?php echo intval($field['sort_order'] ?? 0); ?>">
                </div>

                <div style="margin-top: 24px; display: flex; justify-content: flex-end;">
                    <a href="/pages/settings.php?tab=custom_fields" class="btn btn-outline"><?php echo __('Cancel'); ?></a>
                    <button type="submit" class="btn btn-primary"><?php echo $fieldId ? __('Save Changes') : __('Save Field'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?php echo $csrfToken; ?>';
const isEdit = <?php echo $fieldId ? 'true' : 'false'; ?>;

function generateFieldName(val) {
    if (isEdit) return; // Don't auto-modify on edit
    const slug = val.toLowerCase()
        .replace(/[^a-z0-9\s]/g, '') // remove special
        .replace(/\s+/g, '_') // spaces to underscores
        .substring(0, 50);
    document.getElementById('field-name').value = slug;
}

function saveCustomField(e) {
    e.preventDefault();
    const id = document.getElementById('field-id').value;
    const action = id ? 'update' : 'create';
    
    const bodyData = {
        field_label: document.getElementById('field-label').value,
        field_name: document.getElementById('field-name').value,
        field_type: document.getElementById('field-type').value,
        is_required: parseInt(document.getElementById('field-required').value),
        sort_order: parseInt(document.getElementById('field-sort').value),
        csrf_token: CSRF_TOKEN
    };
    
    if (id) {
        bodyData.field_id = parseInt(id);
        bodyData.is_active = 1;
    }
    
    fetch('/api/custom-fields.php?action=' + action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(bodyData)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showNotification(id ? __('Custom field updated successfully!') : __('Custom field saved successfully!'), 'success');
            setTimeout(() => {
                window.location.href = '/pages/settings.php?tab=custom_fields';
            }, 1000);
        } else {
            showNotification(data.message || __('Failed to save custom field'), 'error');
        }
    })
    .catch(() => {
        showNotification(__('An error occurred while saving custom field.'), 'error');
    });
}
</script>

<?php include '../includes/footer.php'; ?>
