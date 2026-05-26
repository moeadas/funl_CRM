<?php
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();
$pageTitle = 'Products';
$js = ['products'];
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.products-page { max-width: 1200px; margin: 0 auto; padding: 0 20px 40px; }
.page-header { display: flex; justify-content: space-between; align-items: center; padding: 24px 0 20px; }
.page-header h1 { font-size: 22px; font-weight: 600; margin: 0; }
.btn { padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; }
.btn-primary { background: #2563eb; color: white; }
.btn-outline { background: white; border: 1px solid #d1d5db; color: #374151; }
.data-table-wrap { background: white; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; }
table.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
table.data-table th { background: #f9fafb; padding: 12px 16px; text-align: left; font-weight: 600; color: #6b7280; font-size: 12px; text-transform: uppercase; border-bottom: 1px solid #e5e7eb; }
table.data-table td { padding: 14px 16px; border-bottom: 1px solid #e5e7eb; color: #1f2937; }
table.data-table tr:last-child td { border-bottom: none; }
table.data-table tr:hover { background: #f9fafb; }
.price { font-weight: 600; color: #2563eb; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: none; align-items: center; justify-content: center; z-index: 1000; }
.modal-overlay.active { display: flex; }
.modal { background: white; border-radius: 12px; width: 480px; max-width: 95vw; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
.modal-header { padding: 20px 24px 16px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
.modal-header h2 { font-size: 17px; font-weight: 600; margin: 0; }
.modal-close { background: none; border: none; font-size: 20px; cursor: pointer; color: #9ca3af; }
.modal-body { padding: 20px 24px; }
.form-group { margin-bottom: 14px; }
.form-label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 5px; color: #374151; }
.form-control { width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; box-sizing: border-box; }
.form-control:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-actions { padding: 16px 24px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 10px; }
</style>

<div class="products-page">
    <div class="page-header">
        <h1>Products</h1>
        <button class="btn btn-primary" onclick="openProductModal()">+ New Product</button>
    </div>
    <div class="data-table-wrap">
        <table class="data-table">
            <thead><tr><th>Product</th><th>SKU</th><th>Category</th><th>Price</th><th>Stock</th><th></th></tr></thead>
            <tbody id="products-tbody"><tr><td colspan="6" style="text-align:center;padding:40px;color:#9ca3af">Loading...</td></tr></tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="product-modal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="product-modal-title">New Product</h2>
            <button class="modal-close" onclick="closeProductModal()">&times;</button>
        </div>
        <form id="product-form" onsubmit="saveProduct(event)">
            <div class="modal-body">
                <input type="hidden" id="product-id" value="">
                <div class="form-group">
                    <label class="form-label">Product Name *</label>
                    <input type="text" id="product-name" class="form-control" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">SKU</label>
                        <input type="text" id="product-sku" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <input type="text" id="product-category" class="form-control" placeholder="e.g. Software, Services">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Price *</label>
                        <input type="number" id="product-price" class="form-control" required min="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Stock Qty</label>
                        <input type="number" id="product-stock" class="form-control" min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea id="product-desc" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeProductModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
const API = '/api/products.php';
let products = [];

document.addEventListener('DOMContentLoaded', loadProducts);

function loadProducts() {
    fetch(`${API}?action=list`, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(resp => {
            if (resp.success) {
                products = resp.data || [];
                renderProducts();
            }
        });
}

function renderProducts() {
    const tbody = document.getElementById('products-tbody');
    if (!products.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;color:#9ca3af">No products yet.</td></tr>';
        return;
    }
    tbody.innerHTML = products.map(p => `
        <tr>
            <td><strong>${escapeHtml(p.product_name)}</strong></td>
            <td>${escapeHtml(p.sku || '-')}</td>
            <td>${escapeHtml(p.category || '-')}</td>
            <td class="price">$${Number(p.price).toFixed(2)}</td>
            <td>${p.quantity_in_stock ?? '-'}</td>
            <td><button onclick="deleteProduct(${p.product_id})" style="background:none;border:none;cursor:pointer;color:#dc2626">🗑️</button></td>
        </tr>
    `).join('');
}

function openProductModal() {
    document.getElementById('product-form').reset();
    document.getElementById('product-id').value = '';
    document.getElementById('product-modal-title').textContent = 'New Product';
    document.getElementById('product-modal').classList.add('active');
}

function closeProductModal() {
    document.getElementById('product-modal').classList.remove('active');
}

function saveProduct(e) {
    e.preventDefault();
    const productId = document.getElementById('product-id').value;
    const action = productId ? 'update' : 'create';
    
    const data = {
        csrf_token: '<?php echo $_SESSION['csrf_token'] ?? ''; ?>',
        product_name: document.getElementById('product-name').value,
        sku: document.getElementById('product-sku').value,
        category: document.getElementById('product-category').value,
        price: document.getElementById('product-price').value,
        quantity_in_stock: document.getElementById('product-stock').value || null,
        description: document.getElementById('product-desc').value,
    };
    if (productId) data.product_id = productId;
    
    fetch(`${API}?action=${action}`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    }).then(r => r.json()).then(resp => {
        if (resp.success) {
            closeProductModal();
            loadProducts();
            showNotification(productId ? 'Updated' : 'Created', 'success');
        } else {
            showNotification(resp.message || 'Failed', 'error');
        }
    });
}

function deleteProduct(id) {
    if (!confirm('Delete this product?')) return;
    fetch(`${API}?action=delete`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: id, csrf_token: '<?php echo $_SESSION['csrf_token'] ?? ''; ?>' })
    }).then(r => r.json()).then(resp => {
        if (resp.success) { loadProducts(); showNotification('Deleted', 'success'); }
    });
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeProductModal(); });
document.querySelector('.modal-overlay').addEventListener('click', e => { if (e.target === e.currentTarget) closeProductModal(); });
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
