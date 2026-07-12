<?php
/**
 * White Label CRM - Upload Document (standalone, no popup)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/company-functions.php';
startSecureSession();
requireLogin();

$currentUser = getCurrentUser();
$companyId = getCurrentCompanyId();
$isAdmin = hasRole('Admin');

if (!$isAdmin) {
    header('Location: /pages/documents.php');
    exit;
}

$csrf_token = generateCSRFToken();
$db = Database::getInstance();
$uploadDir = __DIR__ . '/../uploads/documents/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// Get categories list (same as documents.php)
$categories = [
    'general' => 'General',
    'contracts' => 'Contracts',
    'sales' => 'Sales Materials',
    'training' => 'Training',
    'policies' => 'Policies',
    'marketing' => 'Marketing',
];

$success = null;
$error = null;

// Handle upload on this same page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_document') {
    requireCSRF();

    if (!empty($_FILES['document_file']['name'])) {
        $file = $_FILES['document_file'];
        $title = sanitizeInput($_POST['title'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $category = sanitizeInput($_POST['category'] ?? 'general');

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'webp'];

        // Validate real MIME type (don't trust the client-supplied extension alone)
        $allowedMime = [
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain', 'image/jpeg', 'image/png', 'image/webp',
        ];
        $realMime = '';
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $realMime = $finfo->file($file['tmp_name']) ?: '';
        }

        if ($realMime && !in_array($realMime, $allowedMime, true)) {
            $error = 'File content does not match an allowed type.';
        } elseif (in_array($ext, $allowed)) {
            $fileName = uniqid() . '_' . preg_replace('/[^a-z0-9._-]/i', '_', basename($file['name']));
            $filePath = $uploadDir . $fileName;

            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                $db->insert('company_documents', [
                    'company_id' => $companyId,
                    'uploaded_by' => $currentUser['user_id'],
                    'title' => $title ?: basename($file['name']),
                    'description' => $description,
                    'file_name' => basename($file['name']),
                    'file_path' => $fileName,
                    'file_size' => $file['size'],
                    'file_type' => $ext,
                    'category' => $category,
                ]);
                header('Location: /pages/documents.php?uploaded=1');
                exit;
            } else {
                $error = 'Failed to save file. Please try again.';
            }
        } else {
            $error = 'File type not allowed. Allowed: PDF, DOC, XLS, PPT, TXT, Images.';
        }
    } else {
        $error = 'Please choose a file to upload.';
    }
}

$pageTitle = __('Upload Document');
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;">
        <a href="/pages/documents.php" class="btn btn-outline" style="padding:8px 14px;display:inline-flex;align-items:center;gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            <?php echo htmlspecialchars(__('Back to Documents')); ?>
        </a>
        <h1><?php echo htmlspecialchars(__('Upload Document')); ?></h1>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-error" style="max-width:720px;margin-bottom:16px;padding:12px 16px;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;border-radius:8px;">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div style="max-width:720px;">
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="action" value="upload_document">

        <div class="card">
            <div class="card-header" style="padding:18px 24px;">
                <h3 class="card-title" style="margin:0;"><?php echo htmlspecialchars(__('File')); ?></h3>
            </div>
            <div class="card-body" style="padding:24px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Document File')); ?> *</label>
                    <input type="file" name="document_file" class="form-control"
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.webp"
                           required
                           style="padding:10px 14px;">
                    <small class="text-muted"><?php echo htmlspecialchars(__('Max file size: 10MB. Allowed: PDF, DOC, XLS, PPT, TXT, Images')); ?></small>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:16px;">
            <div class="card-header" style="padding:18px 24px;">
                <h3 class="card-title" style="margin:0;"><?php echo htmlspecialchars(__('Details')); ?></h3>
            </div>
            <div class="card-body" style="padding:24px;">
                <div class="form-group">
                    <label class="form-label"><?php echo htmlspecialchars(__('Title')); ?> *</label>
                    <input type="text" name="title" class="form-control"
                           placeholder="<?php echo htmlspecialchars(__('e.g., Company Profile 2026')); ?>"
                           required style="padding:10px 14px;">
                </div>
                <div class="form-group">
                    <label class="form-label"><?php echo htmlspecialchars(__('Description')); ?></label>
                    <textarea name="description" class="form-control" rows="2"
                              placeholder="<?php echo htmlspecialchars(__('Brief description...')); ?>"
                              style="padding:10px 14px;"></textarea>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo htmlspecialchars(__('Category')); ?></label>
                    <select name="category" class="form-control" style="padding:10px 14px;">
                        <?php foreach ($categories as $key => $label): ?>
                            <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars(__($label)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:16px;">
            <a href="/pages/documents.php" class="btn btn-outline"><?php echo htmlspecialchars(__('Cancel')); ?></a>
            <button type="submit" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <?php echo htmlspecialchars(__('Upload')); ?>
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
