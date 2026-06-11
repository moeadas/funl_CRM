<?php
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$pageTitle = 'Product';
$currentPage = 'products';
$productId = intval($_GET['id'] ?? 0);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;">
        <a href="/pages/products.php" class="btn btn-outline" style="padding:8px 14px;display:inline-flex;align-items:center;gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            <?php echo __('Back to Products'); ?>
        </a>
        <h1><?= $productId ? htmlspecialchars(__('Edit Product')) : htmlspecialchars(__('New Product')) ?></h1>
    </div>
    <div class="header-actions">
        <?php if ($productId): ?>
            <button type="button" class="btn btn-outline" style="background:#fef2f2;border-color:#fecaca;color:#b91c1c;display:inline-flex;align-items:center;gap:6px;" onclick="deleteProduct()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                <?php echo __('Delete Product'); ?>
            </button>
        <?php endif; ?>
        <button type="button" class="btn btn-primary" onclick="saveProduct()" style="display:inline-flex;align-items:center;gap:6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            <?php echo __('Save Product'); ?>
        </button>
    </div>
</div>

<div style="max-width:900px;">
    <div class="card">
        <div class="card-header" style="padding:18px 24px;">
            <h3 class="card-title" style="margin:0;"><?php echo __('Product Details'); ?></h3>
        </div>
        <div class="card-body" style="padding:24px;">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Product Name *</label>
                <input type="text" id="productName" class="form-control" placeholder="e.g., Premium Plan" required style="padding:10px 14px;">
            </div>
            <div class="row-2" style="margin-top:16px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo __('SKU'); ?></label>
                    <input type="text" id="sku" class="form-control" placeholder="e.g., SKU-001" style="padding:10px 14px;">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo __('Category'); ?></label>
                    <input type="text" id="category" class="form-control" placeholder="e.g., Software, Services" style="padding:10px 14px;">
                </div>
            </div>
            <div class="form-group" style="margin-top:16px;margin-bottom:0;">
                <label class="form-label"><?php echo __('Description'); ?></label>
                <textarea id="description" class="form-control" rows="4" placeholder="<?php echo __('Describe the product...'); ?>" style="padding:10px 14px;"></textarea>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:16px;">
        <div class="card-header" style="padding:18px 24px;">
            <h3 class="card-title" style="margin:0;"><?php echo __('Pricing & Inventory'); ?></h3>
        </div>
        <div class="card-body" style="padding:24px;">
            <div class="row-3">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Unit Price *</label>
                    <input type="number" id="price" class="form-control" placeholder="0.00" step="0.01" min="0" required style="padding:10px 14px;">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo __('Cost Price'); ?></label>
                    <input type="number" id="cost" class="form-control" placeholder="0.00" step="0.01" min="0" style="padding:10px 14px;">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?php echo __('Currency'); ?></label>
                    <select id="currency" class="form-control" style="padding:10px 14px;">
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                        <option value="GBP">GBP</option>
                        <option value="JOD">JOD</option>
                        <option value="AED">AED</option>
                    </select>
                </div>
            </div>
            <div class="form-group" style="margin-top:16px;margin-bottom:0;">
                <label class="form-label"><?php echo __('Stock Quantity'); ?></label>
                <input type="number" id="stock" class="form-control" placeholder="e.g., 100" min="0" style="padding:10px 14px;max-width:300px;">
            </div>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = "<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>";
const PRODUCT_ID = <?= $productId ?>;

document.addEventListener('DOMContentLoaded', function() {
    if (PRODUCT_ID) loadProduct();
});

function loadProduct() {
    fetch('/api/products.php?action=detail&id=' + PRODUCT_ID, { credentials: 'same-origin' })
    .then(r => r.json())
    .then(resp => {
        if (resp.success && resp.data) {
            var p = resp.data;
            document.getElementById('productName').value = p.product_name || '';
            document.getElementById('sku').value = p.sku || '';
            document.getElementById('category').value = p.category || '';
            document.getElementById('description').value = p.description || '';
            document.getElementById('price').value = p.price || '';
            document.getElementById('cost').value = p.cost || '';
            document.getElementById('currency').value = p.currency || 'USD';
            document.getElementById('stock').value = p.quantity_in_stock || '';
        } else {
            showNotification(resp.message || 'Failed to load product', 'error');
        }
    });
}

function saveProduct() {
    var name = document.getElementById('productName').value.trim();
    if (!name) { showNotification('Product name is required', 'error'); return; }
    var priceVal = parseFloat(document.getElementById('price').value) || 0;

    var payload = {
        csrf_token: CSRF_TOKEN,
        product_name: name,
        sku: document.getElementById('sku').value.trim(),
        category: document.getElementById('category').value.trim(),
        description: document.getElementById('description').value.trim(),
        price: priceVal,
        cost: parseFloat(document.getElementById('cost').value) || null,
        currency: document.getElementById('currency').value,
        quantity_in_stock: document.getElementById('stock').value ? parseInt(document.getElementById('stock').value) : null
    };

    var url = '/api/products.php?action=' + (PRODUCT_ID ? 'update' : 'create');
    if (PRODUCT_ID) {
        payload.product_id = PRODUCT_ID;
    }

    var btn = document.querySelector('button[onclick="saveProduct()"]');
    btn.disabled = true;
    var origText = btn.innerHTML;
    btn.innerHTML = '<?php echo htmlspecialchars(__('Saving'), ENT_QUOTES); ?>…';

    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(function(data) {
        if (data.success) {
            showNotification('Product saved!', 'success');
            setTimeout(() => { window.location.href = '/pages/products.php'; }, 700);
        } else {
            showNotification(data.message || 'Save failed', 'error');
            btn.disabled = false;
            btn.innerHTML = origText;
        }
    })
    .catch(function() {
        showNotification('Network error', 'error');
        btn.disabled = false;
        btn.innerHTML = origText;
    });
}

function deleteProduct() {
    showConfirm('Are you sure you want to delete this product?', function() {
        fetch('/api/products.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ product_id: PRODUCT_ID, csrf_token: CSRF_TOKEN })
        })
        .then(r => r.json())
        .then(resp => {
            if (resp.success) {
                showNotification('Product deleted', 'success');
                setTimeout(() => { window.location.href = '/pages/products.php'; }, 700);
            } else {
                showNotification(resp.message || 'Delete failed', 'error');
            }
        });
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
