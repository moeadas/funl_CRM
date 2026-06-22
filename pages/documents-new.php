<?php
/**
 * White Label CRM - Knowledge Hub
 * Link-based resource cards (no file uploads to save server space)
 * Admin/Sales Manager can add/edit/delete cards
 * All users can view and click cards to open links in new tab
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
startSecureSession();
requireLogin();

$currentUser = getCurrentUser();
$companyId = getCurrentCompanyId();
$canManage = hasRole(['Admin', 'Sales Manager']);
$csrf_token = generateCSRFToken();

$db = Database::getInstance();

// Handle actions (Admin/Sales Manager only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage && isset($_POST['action'])) {
    requireCSRF();

    if ($_POST['action'] === 'save_card') {
        $cardId = intval($_POST['card_id'] ?? 0);
        $title = trim(sanitizeInput($_POST['title'] ?? ''));
        $description = trim(sanitizeInput($_POST['description'] ?? ''));
        $category = trim(sanitizeInput($_POST['category'] ?? 'general'));
        $link = trim($_POST['link'] ?? '');

        if ($title && $link) {
            // Ensure link has protocol
            if (!preg_match('/^https?:\/\//i', $link)) {
                $link = 'https://' . $link;
            }

            if ($cardId) {
                $db->query(
                    "UPDATE company_documents SET title=?, description=?, category=?, file_name=?, file_path=? WHERE document_id=? AND company_id=?",
                    [$title, $description, $category, $title, $link, $cardId, $companyId]
                );
                $_SESSION['success'] = 'Card updated successfully.';
            } else {
                $db->insert('company_documents', [
                    'company_id' => $companyId,
                    'uploaded_by' => $currentUser['user_id'],
                    'title' => $title,
                    'description' => $description,
                    'file_name' => $title,
                    'file_path' => $link,
                    'file_size' => 0,
                    'file_type' => 'link',
                    'category' => $category,
                ]);
                $_SESSION['success'] = 'Card created successfully.';
            }
        } else {
            $_SESSION['error'] = 'Title and link are required.';
        }
    } elseif ($_POST['action'] === 'delete_card') {
        $cardId = intval($_POST['card_id'] ?? 0);
        $db->query("DELETE FROM company_documents WHERE document_id=? AND company_id=?", [$cardId, $companyId]);
        $_SESSION['success'] = 'Card deleted.';
    }

    header('Location: documents.php');
    exit;
}

// Fetch cards
$category = $_GET['category'] ?? 'all';
if ($category !== 'all') {
    $cards = $db->query(
        "SELECT d.*, u.full_name as uploaded_by_name 
         FROM company_documents d 
         LEFT JOIN users u ON d.uploaded_by = u.user_id 
         WHERE d.company_id=? AND d.file_type='link' AND d.category=? 
         ORDER BY d.created_at DESC",
        [$companyId, $category]
    )->fetchAll(PDO::FETCH_ASSOC);
} else {
    $cards = $db->query(
        "SELECT d.*, u.full_name as uploaded_by_name 
         FROM company_documents d 
         LEFT JOIN users u ON d.uploaded_by = u.user_id 
         WHERE d.company_id=? AND d.file_type='link' 
         ORDER BY d.created_at DESC",
        [$companyId]
    )->fetchAll(PDO::FETCH_ASSOC);
}

$categories = [
    'general' => 'General',
    'sales' => 'Sales',
    'marketing' => 'Marketing',
    'training' => 'Training',
    'legal' => 'Legal',
    'other' => 'Other',
];

$pageTitle = 'Knowledge Hub';
include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo __('Knowledge Hub'); ?></h1>
        <p style="font-size:13px;color:var(--color-text-secondary);margin-top:4px;"><?php echo __('Shared resources, guides, and useful links for your team.'); ?></p>
    </div>
    <?php if ($canManage): ?>
    <button type="button" class="btn btn-primary" onclick="openCardForm()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <line x1="12" y1="5" x2="12" y2="19"/>
            <line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        <?php echo __('Add Card'); ?>
    </button>
    <?php endif; ?>
</div>

<!-- Category Filters -->
<div class="card" style="margin-bottom:16px;">
    <div class="card-body" style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="?category=all" class="btn btn-sm <?php echo $category === 'all' ? 'btn-primary' : 'btn-outline'; ?>"><?php echo __('All'); ?></a>
        <?php foreach ($categories as $key => $label): ?>
            <a href="?category=<?php echo $key; ?>" class="btn btn-sm <?php echo $category === $key ? 'btn-primary' : 'btn-outline'; ?>">
                <?php echo htmlspecialchars(__($label)); ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Cards Grid -->
<?php if (empty($cards)): ?>
    <div class="card">
        <div class="card-body" style="text-align:center;padding:60px 20px;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="1.5" style="margin-bottom:16px;">
                <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
            </svg>
            <h3 style="color:var(--color-text-secondary);margin-bottom:8px;"><?php echo __('No resources yet'); ?></h3>
            <p style="color:var(--color-text-muted);max-width:400px;margin:0 auto;">
                <?php if ($canManage): ?>
                    <?php echo __('Add link cards to share resources, guides, and useful links with your team.'); ?>
                <?php else: ?>
                    <?php echo __('No resources have been added yet. Ask your admin to add some.'); ?>
                <?php endif; ?>
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="grid" style="grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:16px;">
        <?php foreach ($cards as $card): ?>
            <div class="card" style="display:flex;flex-direction:column;">
                <div class="card-body" style="flex:1;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:12px;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="width:40px;height:40px;border-radius:8px;background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;font-size:18px;">
                                🔗
                            </div>
                            <div>
                                <h4 style="margin:0;font-size:15px;"><?php echo htmlspecialchars($card['title']); ?></h4>
                                <p style="margin:2px 0 0;font-size:12px;color:var(--color-text-muted);">
                                    <?php echo htmlspecialchars(__($categories[$card['category']] ?? 'General')); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php if ($card['description']): ?>
                        <p style="font-size:13px;color:var(--color-text-secondary);margin-bottom:12px;line-height:1.5;">
                            <?php echo htmlspecialchars($card['description']); ?>
                        </p>
                    <?php endif; ?>

                    <div style="display:flex;align-items:center;gap:8px;font-size:12px;color:var(--color-text-muted);">
                        <span><?php echo __('Added by'); ?> <?php echo htmlspecialchars($card['uploaded_by_name'] ?? 'Admin'); ?></span>
                        <span>&middot;</span>
                        <span><?php echo date('M j, Y', strtotime($card['created_at'])); ?></span>
                    </div>
                </div>

                <div class="card-footer" style="display:flex;gap:8px;padding-top:16px;border-top:1px solid var(--color-border-light);">
                    <a href="<?php echo htmlspecialchars($card['file_path']); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm" style="flex:1;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px;">
                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                            <polyline points="15 3 21 3 21 9"/>
                            <line x1="10" y1="14" x2="21" y2="3"/>
                        </svg>
                        <?php echo __('Open Link'); ?>
                    </a>

                    <?php if ($canManage): ?>
                        <button type="button" class="btn btn-sm btn-info" onclick='editCard(<?php echo json_encode($card); ?>)' title="<?php echo __('Edit'); ?>">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                        </button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this card?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="delete_card">
                            <input type="hidden" name="card_id" value="<?php echo $card['document_id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger" title="<?php echo __('Delete'); ?>">
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

<!-- Card Add/Edit Modal -->
<div id="cardModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:9999;align-items:center;justify-content:center;" onclick="if(event.target===this)closeCardForm()">
    <div style="background:white;border-radius:12px;padding:24px;width:90%;max-width:500px;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <h3 id="cardModalTitle" style="margin:0 0 16px;font-size:18px;"><?php echo __('Add New Card'); ?></h3>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="save_card">
            <input type="hidden" name="card_id" id="cf_card_id" value="0">
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label"><?php echo __('Title'); ?> *</label>
                <input type="text" name="title" id="cf_title" class="form-control" required placeholder="Sales Playbook">
            </div>
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label"><?php echo __('Description'); ?></label>
                <textarea name="description" id="cf_description" class="form-control" rows="2" placeholder="Brief description of this resource"></textarea>
            </div>
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label"><?php echo __('Category'); ?></label>
                <select name="category" id="cf_category" class="form-control">
                    <?php foreach ($categories as $key => $label): ?>
                        <option value="<?php echo $key; ?>"><?php echo htmlspecialchars(__($label)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:24px;">
                <label class="form-label"><?php echo __('Link URL'); ?> *</label>
                <input type="url" name="link" id="cf_link" class="form-control" required placeholder="https://docs.google.com/...">
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" class="btn btn-outline" onclick="closeCardForm()"><?php echo __('Cancel'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo __('Save Card'); ?></button>
            </div>
        </form>
    </div>
</div>

<script>
function openCardForm() {
    document.getElementById('cardModalTitle').textContent = '<?php echo __("Add New Card"); ?>';
    document.getElementById('cf_card_id').value = 0;
    document.getElementById('cf_title').value = '';
    document.getElementById('cf_description').value = '';
    document.getElementById('cf_category').value = 'general';
    document.getElementById('cf_link').value = '';
    document.getElementById('cardModal').style.display = 'flex';
}

function editCard(card) {
    document.getElementById('cardModalTitle').textContent = '<?php echo __("Edit Card"); ?>';
    document.getElementById('cf_card_id').value = card.document_id;
    document.getElementById('cf_title').value = card.title;
    document.getElementById('cf_description').value = card.description || '';
    document.getElementById('cf_category').value = card.category || 'general';
    document.getElementById('cf_link').value = card.file_path || '';
    document.getElementById('cardModal').style.display = 'flex';
}

function closeCardForm() {
    document.getElementById('cardModal').style.display = 'none';
}
</script>

<?php include '../includes/footer.php'; ?>
