<?php
/**
 * White Label CRM - Document Library
 * Admin can upload/manage documents, all users can view and download
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/company-functions.php';
startSecureSession();
requireLogin();

$currentUser = getCurrentUser();
$companyId = getCurrentCompanyId();
$isAdmin = hasRole('Admin');
$csrf_token = generateCSRFToken();

$db = Database::getInstance();
$uploadDir = __DIR__ . '/../uploads/documents/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// Handle upload (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin && isset($_POST['action'])) {
    requireCSRF();
    
    if ($_POST['action'] === 'upload_document') {
        if (!empty($_FILES['document_file']['name'])) {
            $file = $_FILES['document_file'];
            $title = sanitizeInput($_POST['title'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            $category = sanitizeInput($_POST['category'] ?? 'general');
            
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'webp'];
            
            if (in_array($ext, $allowed)) {
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
                    $_SESSION['success'] = 'Document uploaded successfully.';
                } else {
                    $_SESSION['error'] = 'Failed to upload file.';
                }
            } else {
                $_SESSION['error'] = 'Invalid file type. Allowed: ' . implode(', ', $allowed);
            }
        }
    }
    
    if ($_POST['action'] === 'delete_document') {
        $docId = intval($_POST['document_id'] ?? 0);
        $doc = $db->findOne('company_documents', ['document_id' => $docId, 'company_id' => $companyId]);
        if ($doc) {
            $fullPath = $uploadDir . $doc['file_path'];
            if (file_exists($fullPath)) unlink($fullPath);
            $db->query("DELETE FROM company_documents WHERE document_id = ?", [$docId]);
            $_SESSION['success'] = 'Document deleted.';
        }
    }
    
    header('Location: documents.php');
    exit;
}

// Handle download
if (isset($_GET['download'])) {
    $docId = intval($_GET['download']);
    $doc = $db->findOne('company_documents', ['document_id' => $docId, 'company_id' => $companyId]);
    if ($doc) {
        $fullPath = $uploadDir . $doc['file_path'];
        if (file_exists($fullPath)) {
            // Update download count
            $db->query("UPDATE company_documents SET download_count = download_count + 1 WHERE document_id = ?", [$docId]);
            
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $doc['file_name'] . '"');
            header('Content-Length: ' . filesize($fullPath));
            readfile($fullPath);
            exit;
        }
    }
    header('Location: documents.php');
    exit;
}

// Fetch documents
$category = $_GET['category'] ?? 'all';
$documents = getCompanyDocuments($companyId, $category);
$categories = getDocumentCategories();

$pageTitle = 'Document Library';
include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Document Library</h1>
        <p style="font-size:13px;color:var(--color-text-secondary);margin-top:4px;">Company documents, sales materials, and training resources.</p>
    </div>
    <?php if ($isAdmin): ?>
    <button type="button" class="btn btn-primary" onclick="openUploadModal()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <line x1="12" y1="5" x2="12" y2="19"/>
            <line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Upload Document
    </button>
    <?php endif; ?>
</div>

<!-- Category Filters -->
<div class="card" style="margin-bottom:16px;">
    <div class="card-body" style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="?category=all" class="btn btn-sm <?php echo $category === 'all' ? 'btn-primary' : 'btn-outline'; ?>">All</a>
        <?php foreach ($categories as $key => $label): ?>
            <a href="?category=<?php echo $key; ?>" class="btn btn-sm <?php echo $category === $key ? 'btn-primary' : 'btn-outline'; ?>">
                <?php echo htmlspecialchars($label); ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Documents Grid -->
<?php if (empty($documents)): ?>
    <div class="card">
        <div class="card-body" style="text-align:center;padding:60px 20px;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="1.5" style="margin-bottom:16px;">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
                <polyline points="10 9 9 9 8 9"/>
            </svg>
            <h3 style="color:var(--color-text-secondary);margin-bottom:8px;">No documents yet</h3>
            <p style="color:var(--color-text-muted);max-width:400px;margin:0 auto;">
                <?php if ($isAdmin): ?>
                    Upload company documents, sales materials, or training resources for your team.
                <?php else: ?>
                    No documents have been uploaded yet. Contact your admin to add company resources.
                <?php endif; ?>
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="grid" style="grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:16px;">
        <?php foreach ($documents as $doc): ?>
            <div class="card" style="display:flex;flex-direction:column;">
                <div class="card-body" style="flex:1;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:12px;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="width:40px;height:40px;border-radius:8px;background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;font-size:12px;text-transform:uppercase;">
                                <?php echo htmlspecialchars($doc['file_type'] ?: 'FILE'); ?>
                            </div>
                            <div>
                                <h4 style="margin:0;font-size:15px;"><?php echo htmlspecialchars($doc['title']); ?></h4>
                                <p style="margin:2px 0 0;font-size:12px;color:var(--color-text-muted);">
                                    <?php echo htmlspecialchars($categories[$doc['category']] ?? 'General'); ?>
                                    &middot;
                                    <?php echo number_format($doc['file_size'] / 1024, 1); ?> KB
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php if ($doc['description']): ?>
                        <p style="font-size:13px;color:var(--color-text-secondary);margin-bottom:12px;">
                            <?php echo htmlspecialchars($doc['description']); ?>
                        </p>
                    <?php endif; ?>
                    
                    <div style="display:flex;align-items:center;gap:8px;font-size:12px;color:var(--color-text-muted);">
                        <span>Uploaded by <?php echo htmlspecialchars($doc['uploaded_by_name'] ?? 'Admin'); ?></span>
                        <span>&middot;</span>
                        <span><?php echo date('M j, Y', strtotime($doc['created_at'])); ?></span>
                        <?php if ($doc['download_count'] > 0): ?>
                            <span>&middot;</span>
                            <span><?php echo $doc['download_count']; ?> downloads</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card-footer" style="display:flex;gap:8px;padding-top:16px;border-top:1px solid var(--color-border-light);">
                    <a href="?download=<?php echo $doc['document_id']; ?>" class="btn btn-primary btn-sm" style="flex:1;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px;">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        Download
                    </a>
                    
                    <?php if ($isAdmin): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this document?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="delete_document">
                            <input type="hidden" name="document_id" value="<?php echo $doc['document_id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"/>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                </svg>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Upload Modal -->
<?php if ($isAdmin): ?>
<div id="uploadModal" class="modal" style="display:none;">
    <div class="modal-backdrop" onclick="closeUploadModal()"></div>
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h3 class="modal-title">Upload Document</h3>
            <button type="button" class="btn-close" onclick="closeUploadModal()">&times;</button>
        </div>
        
        <form method="POST" enctype="multipart/form-data" class="modal-body">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="upload_document">
            
            <div class="form-group">
                <label class="form-label">Document File *</label>
                <input type="file" name="document_file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.webp" required>
                <div class="form-hint">Max file size: 10MB. Allowed: PDF, DOC, XLS, PPT, TXT, Images</div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Title *</label>
                <input type="text" name="title" class="form-control" placeholder="e.g., Company Profile 2026" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Brief description..."></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Category</label>
                <select name="category" class="form-control">
                    <?php foreach ($categories as $key => $label): ?>
                        <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-actions" style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                <button type="button" class="btn btn-outline" onclick="closeUploadModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Upload</button>
            </div>
        </form>
    </div>
</div>

<script>
function openUploadModal() { document.getElementById('uploadModal').style.display = 'flex'; }
function closeUploadModal() { document.getElementById('uploadModal').style.display = 'none'; }
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
