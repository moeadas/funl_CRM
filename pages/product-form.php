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
        <a href="/pages/products.php" class="btn btn-outline" style="padding:8px 14px;">← Back to Products</a>
        <h1><?= $productId ? 'Edit Product' : 'New Product' ?></h1>
    </div>
    <div style="display:flex; gap:10px;">
        <?php if ($productId): ?>
            <button type="button" class="btn btn-danger" onclick="deleteProduct()"><?php echo __('Delete Product'); ?></button>
        <?php endif; ?>
        <button type="button" class="btn btn-primary" onclick="saveProduct()"><?php echo __('Save Product'); ?></button>
    </div>
</div>

<div style="max-width:900px;">
    <div class="card">
        <h3 class="card-title"><?php echo __('Product Details'); ?></h3>
        <div class="form-group">
            <label class="form-label">Product Name *</label>
            <input type="text" id="productName" class="form-control" placeholder="e.g., Premium Plan" required>
        </div>
        <div class="row-2" style="margin-top:16px;">
            <div class="form-group">
                <label class="form-label"><?php echo __('SKU'); ?></label>
                <input type="text" id="sku" class="form-control" placeholder="e.g., SKU-001">
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo __('Category'); ?></label>
                <input type="text" id="category" class="form-control" placeholder="e.g., Software, Services">
            </div>
        </div>
        <div style="margin-top:16px;">
            <label class="form-label"><?php echo __('Description'); ?></label>
            <textarea id="description" class="form-control" placeholder="<?php echo __('Describe the product...'); ?>"></textarea>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title"><?php echo __('Pricing & Inventory'); ?></h3>
        <div class="row-3">
            <div class="form-group">
                <label class="form-label">Unit Price *</label>
                <input type="number" id="price" class="form-control" placeholder="0.00" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo __('Cost Price'); ?></label>
                <input type="number" id="cost" class="form-control" placeholder="0.00" step="0.01" min="0">
            </div>
            <div class="form-group">
                <label class="form-label"><?php echo __('Currency'); ?></label>
                <select id="currency" class="form-control">
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                    <option value="GBP">GBP</option>
                    <option value="JOD">JOD</option>
                    <option value="AED">AED</option>
                </select>
            </div>
        </div>
        <div class="row-2" style="margin-top:16px;">
            <div class="form-group">
                <label class="form-label"><?php echo __('Stock Quantity'); ?></label>
                <input type="number" id="stock" class="form-control" placeholder="e.g., 100" min="0">
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
    
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(function(data) {
        showNotification(data.message || (data.success ? 'Product saved!' : 'Save failed'), data.success ? 'success' : 'error');
        if (data.success) {
            setTimeout(() => {
                window.location.href = '/pages/products.php';
            }, 500);
        }
    })
    .catch(function() { showNotification('Network error', 'error'); });
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
            showNotification(resp.message || (resp.success ? 'Product deleted' : 'Delete failed'), resp.success ? 'success' : 'error');
            if (resp.success) {
                setTimeout(() => {
                    window.location.href = '/pages/products.php';
                }, 500);
            }
        });
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
