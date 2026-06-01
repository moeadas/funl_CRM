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
</style>

<div class="products-page">
    <div class="page-header">
        <h1><?php echo __('Products'); ?></h1>
        <a href="/pages/product-form.php" class="btn btn-primary" style="text-decoration:none;">+ New Product</a>
    </div>
    <div class="data-table-wrap">
        <table class="data-table">
            <thead><tr><th><?php echo __('Product'); ?></th><th><?php echo __('SKU'); ?></th><th><?php echo __('Category'); ?></th><th><?php echo __('Price'); ?></th><th><?php echo __('Stock'); ?></th><th></th></tr></thead>
            <tbody id="products-tbody"><tr><td colspan="6" style="text-align:center;padding:40px;color:#9ca3af"><?php echo __('Loading...'); ?></td></tr></tbody>
        </table>
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
            } else {
                showNotification(resp.message || 'Failed to load products', 'error');
            }
        })
        .catch(() => showNotification('Network error loading products', 'error'));
}

function renderProducts() {
    const tbody = document.getElementById('products-tbody');
    if (!products.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;color:#9ca3af">No products yet. Click "+ New Product" to add one.</td></tr>';
        return;
    }
    tbody.innerHTML = products.map(p => `
        <tr onclick="window.location.href='/pages/product-form.php?id=${p.product_id}'" style="cursor:pointer">
            <td><strong>${escapeHtml(p.product_name)}</strong>
                ${p.description ? `<div style="font-size:12px;color:#9ca3af;margin-top:2px">${escapeHtml(p.description.substring(0,60))}${p.description.length>60?'…':''}</div>` : ''}
            </td>
            <td>${escapeHtml(p.sku || '—')}</td>
            <td>${escapeHtml(p.category || '—')}</td>
            <td class="price">$${Number(p.price || 0).toFixed(2)}</td>
            <td>${p.quantity_in_stock != null ? p.quantity_in_stock : '—'}</td>
            <td>
                <div style="display:flex;gap:6px" onclick="event.stopPropagation()">
                    <button onclick="window.location.href='/pages/product-form.php?id=${p.product_id}'"
                        style="background:#f3f4f6;border:1px solid #e5e7eb;border-radius:5px;padding:5px 10px;font-size:12px;cursor:pointer;color:#374151">✏️ Edit</button>
                    <button onclick="deleteProduct(${p.product_id})"
                        style="background:#fef2f2;border:1px solid #fecaca;border-radius:5px;padding:5px 10px;font-size:12px;cursor:pointer;color:#dc2626">🗑️ Delete</button>
                </div>
            </td>
        </tr>
    `).join('');
}

function deleteProduct(id) {
    showConfirm('Delete this product?', function() {
        fetch(`${API}?action=delete`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: id, csrf_token: '<?php echo $_SESSION['csrf_token'] ?? ''; ?>' })
        }).then(r => r.json()).then(resp => {
            if (resp.success) {
                loadProducts();
                showNotification('Product deleted successfully', 'success');
            } else {
                showNotification(resp.message || 'Failed to delete product', 'error');
            }
        });
    });
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
