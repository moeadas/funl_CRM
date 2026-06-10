<?php
/**
 * White Label CRM - Custom Field (Create / Edit) — standalone, no popup
 */
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$fieldId = intval($_GET['id'] ?? 0);
$entityType = $_GET['entity'] ?? 'lead'; // lead / contact / deal / account

$db = Database::getInstance();
$field = null;
if ($fieldId) {
    $stmt = $db->prepare("SELECT * FROM custom_fields WHERE field_id = ?");
    $stmt->execute([$fieldId]);
    $field = $stmt->fetch(PDO::FETCH_ASSOC);
}

$pageTitle = $fieldId ? __('Edit Custom Field') : __('Add Custom Field');
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;">
        <a href="/pages/settings.php#custom-fields" class="btn btn-outline" style="padding:8px 14px;display:inline-flex;align-items:center;gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            <?php echo htmlspecialchars(__('Back to Settings')); ?>
        </a>
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
    </div>
    <div class="header-actions">
        <a href="/pages/settings.php#custom-fields" class="btn btn-outline"><?php echo htmlspecialchars(__('Cancel')); ?></a>
        <button type="submit" form="customFieldForm" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            <?php echo htmlspecialchars(__('Save Field')); ?>
        </button>
    </div>
</div>

<div style="max-width:560px;">
    <form id="customFieldForm" onsubmit="return saveCustomField(event)">
        <input type="hidden" id="fieldId" name="field_id" value="<?php echo (int)$fieldId; ?>">
        <input type="hidden" id="entityType" name="entity_type" value="<?php echo htmlspecialchars($entityType); ?>">

        <div class="card">
            <div class="card-header" style="padding:18px 24px;">
                <h3 class="card-title" style="margin:0;"><?php echo htmlspecialchars(__('Field details')); ?></h3>
            </div>
            <div class="card-body" style="padding:24px;">
                <div class="form-group">
                    <label class="form-label"><?php echo htmlspecialchars(__('Field label')); ?> *</label>
                    <input type="text" id="fieldLabel" class="form-control"
                           placeholder="<?php echo htmlspecialchars(__('e.g. Horse Age')); ?>"
                           required oninput="generateFieldName(this.value)"
                           value="<?php echo htmlspecialchars($field['field_label'] ?? ''); ?>"
                           style="padding:10px 14px;">
                </div>

                <div class="form-group">
                    <label class="form-label"><?php echo htmlspecialchars(__('Field code name')); ?> * <small class="text-muted"><?php echo htmlspecialchars(__('(alphanumeric / underscore)')); ?></small></label>
                    <input type="text" id="fieldName" name="field_name" class="form-control"
                           placeholder="<?php echo htmlspecialchars(__('e.g. horse_age')); ?>"
                           required pattern="^[a-zA-Z0-9_]+$"
                           value="<?php echo htmlspecialchars($field['field_name'] ?? ''); ?>"
                           style="padding:10px 14px;">
                    <small class="text-muted"><?php echo htmlspecialchars(__('Used internally — cannot be changed after creation')); ?></small>
                </div>

                <div class="form-group">
                    <label class="form-label"><?php echo htmlspecialchars(__('Field type')); ?></label>
                    <select id="fieldType" class="form-control" style="padding:10px 14px;">
                        <option value="text" <?php echo ($field['field_type'] ?? '') === 'text' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('Text input')); ?></option>
                        <option value="textarea" <?php echo ($field['field_type'] ?? '') === 'textarea' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('Textarea (multiline)')); ?></option>
                        <option value="number" <?php echo ($field['field_type'] ?? '') === 'number' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('Number')); ?></option>
                        <option value="select" <?php echo ($field['field_type'] ?? '') === 'select' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('Dropdown list')); ?></option>
                        <option value="date" <?php echo ($field['field_type'] ?? '') === 'date' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('Date')); ?></option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label"><?php echo htmlspecialchars(__('Required field')); ?></label>
                    <select id="fieldRequired" class="form-control" style="padding:10px 14px;">
                        <option value="0" <?php echo ($field['is_required'] ?? 0) == 0 ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('Optional')); ?></option>
                        <option value="1" <?php echo ($field['is_required'] ?? 0) == 1 ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('Required')); ?></option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Sort order')); ?> <small class="text-muted"><?php echo htmlspecialchars(__('(lower shows first)')); ?></small></label>
                    <input type="number" id="fieldSort" class="form-control"
                           value="<?php echo htmlspecialchars($field['sort_order'] ?? 0); ?>"
                           style="padding:10px 14px;">
                </div>
            </div>
        </div>
    </form>
</div>

<script>
const CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
const FIELD_ID = <?= (int)$fieldId ?>;

// Disable field name on edit (code name is a key)
if (FIELD_ID) {
    document.getElementById('fieldName').readOnly = true;
    document.getElementById('fieldName').style.background = '#f3f4f6';
}

function generateFieldName(val) {
    if (FIELD_ID) return; // don't auto-modify on edit
    const slug = String(val || '').toLowerCase()
        .replace(/[^a-z0-9\s]/g, '')
        .replace(/\s+/g, '_')
        .substring(0, 50);
    document.getElementById('fieldName').value = slug;
}

function saveCustomField(e) {
    e.preventDefault();
    const action = FIELD_ID ? 'update' : 'create';
    const data = {
        csrf_token: CSRF_TOKEN,
        field_id: FIELD_ID || undefined,
        entity_type: document.getElementById('entityType').value,
        field_label: document.getElementById('fieldLabel').value,
        field_name: document.getElementById('fieldName').value,
        field_type: document.getElementById('fieldType').value,
        is_required: parseInt(document.getElementById('fieldRequired').value || 0),
        sort_order: parseInt(document.getElementById('fieldSort').value || 0),
    };

    const submitBtn = document.querySelector('button[form="customFieldForm"]');
    submitBtn.disabled = true;
    const origText = submitBtn.textContent;
    submitBtn.textContent = 'Saving…';

    fetch(`/api/custom-fields.php?action=${action}`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    }).then(r => r.json()).then(resp => {
        if (resp.success) {
            showNotification(FIELD_ID ? 'Field updated' : 'Field created', 'success');
            setTimeout(() => window.location.href = '/pages/settings.php#custom-fields', 600);
        } else {
            showNotification(resp.message || 'Failed', 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = origText;
        }
    }).catch(err => {
        showNotification('Network error: ' + err.message, 'error');
        submitBtn.disabled = false;
        submitBtn.textContent = origText;
    });
    return false;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
