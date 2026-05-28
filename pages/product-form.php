<?php
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$pageTitle = 'Product';
$currentPage = 'products';
$productId = intval($_GET['id'] ?? 0);
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:28px; }
.page-header h1 { margin:0; font-size:22px; font-weight:600; letter-spacing:-0.3px; }
.card { background:#fff; border:1px solid #e5e5e7; border-radius:12px; padding:24px; margin-bottom:16px; }
.card-title { font-size:15px; font-weight:600; color:#1d1d1f; margin:0 0 20px; }
.form-label { display:block; font-size:13px; font-weight:500; color:#424245; margin-bottom:6px; }
.form-control { width:100%; padding:10px 12px; border:1px solid #d2d2d7; border-radius:8px; font-size:14px; color:#1d1d1f; background:#fff; box-sizing:border-box; transition:border-color 0.2s; }
.form-control:focus { outline:none; border-color:#0071e3; box-shadow:0 0 0 3px rgba(0,113,227,0.15); }
textarea.form-control { min-height:80px; resize:vertical; }
.row-2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.row-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; }
.btn { padding:10px 18px; border-radius:8px; font-size:14px; font-weight:500; cursor:pointer; transition:all 0.2s; border:none; text-decoration:none; display:inline-block; }
.btn-primary { background:#0071e3; color:#fff; }
.btn-primary:hover { background:#0077ed; }
.btn-outline { background:#fff; color:#0071e3; border:1px solid #0071e3; }
.btn-outline:hover { background:#f5f5f7; }
</style>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:16px;">
        <a href="/pages/products.php" class="btn btn-outline" style="padding:8px 14px;">← Back to Products</a>
        <h1><?= $productId ? 'Edit Product' : 'New Product' ?></h1>
    </div>
    <div>
        <button type="button" class="btn btn-primary" onclick="saveProduct()">Save Product</button>
    </div>
</div>

<div style="max-width:900px;">
    <div class="card">
        <h3 class="card-title">Product Details</h3>
        <div class="form-group">
            <label class="form-label">Product Name *</label>
            <input type="text" id="productName" class="form-control" placeholder="e.g., Premium Plan">
        </div>
        <div class="row-2" style="margin-top:16px;">
            <div class="form-group">
                <label class="form-label">SKU</label>
                <input type="text" id="sku" class="form-control" placeholder="e.g., SKU-001">
            </div>
            <div class="form-group">
                <label class="form-label">Category</label>
                <input type="text" id="category" class="form-control" placeholder="e.g., Software">
            </div>
        </div>
        <div style="margin-top:16px;">
            <label class="form-label">Description</label>
            <textarea id="description" class="form-control" placeholder="Describe the product..."></textarea>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title">Pricing & Inventory</h3>
        <div class="row-3">
            <div class="form-group">
                <label class="form-label">Unit Price *</label>
                <input type="number" id="unitPrice" class="form-control" placeholder="0.00" step="0.01" min="0">
            </div>
            <div class="form-group">
                <label class="form-label">Cost Price</label>
                <input type="number" id="costPrice" class="form-control" placeholder="0.00" step="0.01" min="0">
            </div>
            <div class="form-group">
                <label class="form-label">Currency</label>
                <select id="currency" class="form-control">
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                    <option value="GBP">GBP</option>
                    <option value="JOD">JOD</option>
                    <option value="AED">AED</option>
                </select>
            </div>
        </div>
        <div class="row-3" style="margin-top:16px;">
            <div class="form-group">
                <label class="form-label">Stock Quantity</label>
                <input type="number" id="stockQuantity" class="form-control" placeholder="0" min="0">
            </div>
            <div class="form-group">
                <label class="form-label">Stock Status</label>
                <select id="stockStatus" class="form-control">
                    <option value="In Stock">In Stock</option>
                    <option value="Low Stock">Low Stock</option>
                    <option value="Out of Stock">Out of Stock</option>
                    <option value="Discontinued">Discontinued</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select id="status" class="form-control">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
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
    fetch('/api/products.php?id=' + PRODUCT_ID, { credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.product) {
            var p = data.product;
            ['productName','sku','category','description','unitPrice','costPrice','stockQuantity'].forEach(function(f) {
                var el = document.getElementById(f);
                if (el && p[f.replace(/([A-Z])/g,'_$1').toLowerCase()]) {
                    el.value = p[f.replace(/([A-Z])/g,'_$1').toLowerCase()];
                }
            });
            if (p.currency) document.getElementById('currency').value = p.currency;
            if (p.stock_status) document.getElementById('stockStatus').value = p.stock_status;
            if (p.status) document.getElementById('status').value = p.status;
        }
    });
}

function saveProduct() {
    var name = document.getElementById('productName').value.trim();
    if (!name) { showNotification('Product name is required', 'error'); return; }
    var price = parseFloat(document.getElementById('unitPrice').value) || 0;
    
    var payload = {
        csrf_token: CSRF_TOKEN,
        name: name,
        sku: document.getElementById('sku').value,
        category: document.getElementById('category').value,
        description: document.getElementById('description').value,
        unit_price: price,
        cost_price: parseFloat(document.getElementById('costPrice').value) || 0,
        currency: document.getElementById('currency').value,
        stock_quantity: parseInt(document.getElementById('stockQuantity').value) || 0,
        stock_status: document.getElementById('stockStatus').value,
        status: document.getElementById('status').value
    };
    
    var url = PRODUCT_ID ? '/api/products.php?action=update&id=' + PRODUCT_ID : '/api/products.php?action=create';
    var method = PRODUCT_ID ? 'PUT' : 'POST';
    
    fetch(url, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(function(data) {
        showNotification(data.message || (data.success ? 'Product saved!' : 'Save failed'), data.success ? 'success' : 'error');
        if (data.success) window.location.href = '/pages/products.php';
    })
    .catch(function() { showNotification('Network error', 'error'); });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
