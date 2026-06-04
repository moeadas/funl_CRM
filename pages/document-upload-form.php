<?php
/**
 * White Label CRM V2 — Upload Document Form
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
startSecureSession();
requireLogin();

// Check if user is Admin or Sales Manager (since upload is Admin-only in original documents.php logic)
$isAdmin = hasRole('Admin');
if (!$isAdmin) {
    header('Location: documents.php');
    exit;
}

$categories = getDocumentCategories();
$csrf_token = generateCSRFToken();
$pageTitle = __('Upload Document');
include '../includes/header.php';
?>

<style>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 24px; box-shadow: var(--shadow-xs); }
.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 13px; font-weight: 500; color: var(--color-text); margin-bottom: 6px; }
.form-control { width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); font-size: 14px; color: var(--color-text); background: var(--color-surface); box-sizing: border-box; }
.form-control:focus { outline: none; border-color: var(--color-accent); }
.btn { padding: 10px 18px; border-radius: var(--radius-sm); font-size: 14px; font-weight: 500; cursor: pointer; transition: all var(--transition); border: none; text-decoration: none; display: inline-block; }
.btn-primary { background: var(--color-accent); color: #fff; }
.btn-primary:hover { background: var(--color-accent-hover); }
.btn-outline { background: var(--color-surface); color: var(--color-text); border: 1px solid var(--color-border); margin-right: 8px; }
.btn-outline:hover { background: var(--color-bg); }
.form-hint { font-size: 11px; color: var(--color-text-secondary); margin-top: 4px; }
</style>

<div class="page-container">
    <div class="page-header">
        <div style="display:flex;align-items:center;gap:16px;">
            <a href="/pages/documents.php" class="btn btn-outline" style="padding:8px 14px;">← <?php echo __('back'); ?></a>
            <h1 class="page-title"><?php echo __('Upload Document'); ?></h1>
        </div>
    </div>

    <div style="max-width: 600px;">
        <div class="card">
            <form method="POST" action="/pages/documents.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="upload_document">
                
                <div class="form-group">
                    <label class="form-label"><?php echo __('Document File'); ?> *</label>
                    <input type="file" name="document_file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.webp" required>
                    <div class="form-hint"><?php echo __('Max file size: 10MB. Allowed: PDF, DOC, XLS, PPT, TXT, Images'); ?></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><?php echo __('Title'); ?> *</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g., Company Profile 2026" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><?php echo __('Description'); ?></label>
                    <textarea name="description" class="form-control" rows="3" placeholder="<?php echo __('Brief description...'); ?>"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><?php echo __('Category'); ?></label>
                    <select name="category" class="form-control">
                        <?php foreach ($categories as $key => $label): ?>
                            <option value="<?php echo $key; ?>"><?php echo htmlspecialchars(__($label)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="margin-top: 24px; display: flex; justify-content: flex-end;">
                    <a href="/pages/documents.php" class="btn btn-outline"><?php echo __('Cancel'); ?></a>
                    <button type="submit" class="btn btn-primary"><?php echo __('Upload'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
