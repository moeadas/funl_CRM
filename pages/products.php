<?php
/**
 * White Label CRM - Products Catalog
 * Clean e-commerce style product management
 */
require_once __DIR__ . '/../includes/auth.php';
startSecureSession();
requireLogin();

$userId = getCurrentUserId();
$companyId = $_SESSION["company_id"] ?? null;
$userRole = $_SESSION["role"] ?? "";

$pageTitle = 'Products';
require_once __DIR__ . '/../includes/header.php';

$db = Database::getInstance();

// Fetch products
$products = $db->query("SELECT * FROM products WHERE company_id = ? ORDER BY product_name", [$companyId])->fetchAll();
$categories = $db->query("SELECT DISTINCT category FROM products WHERE company_id = ? AND category IS NOT NULL AND category != '' ORDER BY category", [$companyId])->fetchAll();
?>

<style>
.products-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px 40px;
}
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px 0 20px;
}
.page-header h1 {
    font-size: 22px;
    font-weight: 600;
    margin: 0;
}
.btn {
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s;
}
.btn-primary {
    background: #2563eb;
    color: white;
}
.btn-primary:hover {
    background: #1d4ed8;
}
.btn-outline {
    background: white;
    border: 1px solid #d1d5db;
    color: #374151;
}
.btn-outline:hover {
    background: #f9fafb;
}

/* Search & Filters */
.filters-bar {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.filters-bar input,
.filters-bar select {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 13px;
    background: white;
    color: #374151;
}
.filters-bar input {
    width: 240px;
}

/* Products Grid */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}
.product-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 20px;
    transition: all 0.15s;
    position: relative;
}
.product-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    border-color: #d1d5db;
}
.product-icon {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 12px;
}
.product-name {
    font-size: 15px;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 4px;
}
.product-sku {
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 8px;
}
.product-price {
    font-size: 18px;
    font-weight: 700;
    color: #2563eb;
    margin-bottom: 8px;
}
.product-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 12px;
}
.product-tag {
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 4px;
    font-weight: 500;
}
.product-tag.category {
    background: #f3e8ff;
    color: #7c3aed;
}
.product-tag.in-stock {
    background: #dcfce7;
    color: #15803d;
}
.product-tag.out-of-stock {
    background: #fee2e2;
    color: #dc2626;
}
.product-actions {
    display: flex;
    gap: 8px;
    border-top: 1px solid #f3f4f6;
    padding-top: 12px;
}
.product-actions button {
    flex: 1;
    padding: 6px 10px;
    border-radius: 4px;
    border: none;
    font-size: 12px;
    cursor: pointer;
    font-weight: 500;
}
.product-actions .btn-edit {
    background: #f9fafb;
    color: #374151;
}
.product-actions .btn-edit:hover {
    background: #f3f4f6;
}
.product-actions .btn-delete {
    background: #fee2e2;
    color: #dc2626;
}
.product-actions .btn-delete:hover {
    background: #fecaca;
}

/* Modal */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.4);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    overflow-y: auto;
    padding: 40px 20px;
}
.modal-overlay.active {
    display: flex;
}
.modal {
    background: white;
    border-radius: 12px;
    width: 520px;
    max-width: 95vw;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
    overflow: hidden;
}
.modal-header {
    padding: 20px 24px 16px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-header h2 {
    font-size: 17px;
    font-weight: 600;
    margin: 0;
}
.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #9ca3af;
    line-height: 1;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: background 0.15s;
}
.modal-close:hover {
    background: #f3f4f6;
    color: #374151;
}
.modal-body {
    padding: 24px;
}
.form-group {
    margin-bottom: 16px;
}
.form-label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 6px;
    color: #374151;
}
.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    box-sizing: border-box;
    font-family: inherit;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.form-control:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}
textarea.form-control {
    min-height: 80px;
    resize: vertical;
}
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}
.form-actions {
    padding: 16px 24px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    background: #f9fafb;
}
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #9ca3af;
}
.empty-state-icon {
    font-size: 48px;
    margin-bottom: 16px;
}
.empty-state h3 {
    font-size: 16px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
}
</style>

<div class="products-page">
    <div class="page-header">
        <h1>🛍️ Products</h1>
        <button class="btn btn-primary" onclick="openProductModal()">+ Add Product</button>
    </div>

    <div class="filters-bar">
        <input type="text" id="product-search" placeholder="Search products..." oninput="renderProducts()">
        <select id="product-category-filter" onchange="renderProducts()">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo  htmlspecialchars($cat['category']) ?>"><?php echo  htmlspecialchars($cat['category']) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="product-stock-filter" onchange="renderProducts()">
            <option value="">All Stock</option>
            <option value="in_stock">In Stock</option>
            <option value="out_of_stock">Out of Stock</option>
        </select>
    </div>

    <div class="products-grid" id="products-grid">
        <!-- Products rendered by JS -->
    </div>
</div>

<!-- Product Modal -->
<div class="modal-overlay" id="product-modal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="product-modal-title">➕ Add Product</h2>
            <button class="modal-close" onclick="closeProductModal()">&times;</button>
        </div>
        <form id="product-form" onsubmit="saveProduct(event)">
            <div class="modal-body">
                <input type="hidden" id="product-id" value="">

                <div class="form-group">
                    <label class="form-label">Product Name *</label>
                    <input type="text" id="product-name" class="form-control" required
                           placeholder="e.g. Premium CRM License">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">SKU *</label>
                        <input type="text" id="product-sku" class="form-control" required
                               placeholder="e.g. CRM-PRO-001">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Price *</label>
                        <input type="number" id="product-price" class="form-control" required
                               step="0.01" min="0" placeholder="0.00">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <input type="text" id="product-category" class="form-control"
                               placeholder="e.g. Software, Services" list="category-list">
                        <datalist id="category-list">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo  htmlspecialchars($cat['category']) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Stock Quantity</label>
                        <input type="number" id="product-stock" class="form-control"
                               min="0" step="1" value="0">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea id="product-desc" class="form-control"
                              placeholder="Product description, features, etc."></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeProductModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">💾 Save Product</button>
            </div>
        </form>
    </div>
</div>

<script>
const API = '/api/products.php';
const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';

let products = <?php echo  json_encode($products) ?>;

// Initialize
document.addEventListener('DOMContentLoaded', renderProducts);

function renderProducts() {
    const grid = document.getElementById('products-grid');
    const search = (document.getElementById('product-search') \u0026\u0026 document.getElementById('product-search').value).toLowerCase() || '';
    const category = (document.getElementById('product-category-filter') \u0026\u0026 document.getElementById('product-category-filter').value) || '';
    const stockFilter = (document.getElementById('product-stock-filter') \u0026\u0026 document.getElementById('product-stock-filter').value) || '';

    let filtered = products.filter(p => {
        if (search && !p.(product_name && product_name.toLowerCase)().includes(search) && !p.(sku && sku.toLowerCase)().includes(search)) return false;
        if (category && p.category !== category) return false;
        if (stockFilter === 'in_stock' && (p.stock_qty || 0) <= 0) return false;
        if (stockFilter === 'out_of_stock' && (p.stock_qty || 0) > 0) return false;
        return true;
    });

    if (!filtered.length) {
        grid.innerHTML = `             <div class="empty-state" style="grid-column: 1 / -1;">                 <div class="empty-state-icon">🛍️</div>                 <h3>No products yet</h3>                 <p>Add your first product to get started.</p>             </div>`;
        return;
    }

    grid.innerHTML = filtered.map(p => {
        const inStock = (p.stock_qty || 0) > 0;
        return `         <div class="product-card" data-id="${p.product_id}">             <div class="product-icon">📦</div>             <div class="product-name">${escapeHtml(p.product_name)}</div>             <div class="product-sku">SKU: ${escapeHtml(p.sku || '-')}</div>             <div class="product-price">$${parseFloat(p.price || 0).toFixed(2)}</div>             <div class="product-meta">                 ${p.category ? `<span class="product-tag category">${escapeHtml(p.category)}</span>` : ''}                 <span class="product-tag ${inStock ? 'in-stock' : 'out-of-stock'}">                     ${inStock ? '✅ In Stock' : '❌ Out of Stock'}                 </span>                 ${p.stock_qty ? `<span class="product-tag" style="background:#f3f4f6;color:#6b7280">${p.stock_qty} units</span>` : ''}             </div>             <div class="product-actions">                 <button class="btn-edit" onclick="editProduct(${p.product_id})">✏️ Edit</button>                 <button class="btn-delete" onclick="deleteProduct(${p.product_id})">🗑️ Delete</button>             </div>         </div>`;
    }).join('');
}

function openProductModal() {
    document.getElementById('product-form').reset();
    document.getElementById('product-id').value = '';
    document.getElementById('product-modal-title').textContent = '➕ Add Product';
    document.getElementById('product-modal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeProductModal() {
    document.getElementById('product-modal').classList.remove('active');
    document.body.style.overflow = '';
}

function editProduct(id) {
    const p = products.find(x => x.product_id == id);
    if (!p) return;
    document.getElementById('product-id').value = p.product_id;
    document.getElementById('product-name').value = p.product_name || '';
    document.getElementById('product-sku').value = p.sku || '';
    document.getElementById('product-price').value = p.price || '';
    document.getElementById('product-category').value = p.category || '';
    document.getElementById('product-stock').value = p.stock_qty || 0;
    document.getElementById('product-desc').value = p.description || '';
    document.getElementById('product-modal-title').textContent = '✏️ Edit Product';
    document.getElementById('product-modal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function saveProduct(e) {
    e.preventDefault();
    const id = document.getElementById('product-id').value;
    const data = {
        csrf_token: CSRF_TOKEN,
        product_name: document.getElementById('product-name').value,
        sku: document.getElementById('product-sku').value,
        price: parseFloat(document.getElementById('product-price').value) || 0,
        category: document.getElementById('product-category').value,
        stock_qty: parseInt(document.getElementById('product-stock').value) || 0,
        description: document.getElementById('product-desc').value
    };
    if (id) data.product_id = id;

    fetch(`${API}?action=${id ? 'update' : 'create'}`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(resp => {
        if (resp.success) {
            closeProductModal();
            loadProducts();
            showNotification(id ? 'Product updated' : 'Product created', 'success');
        } else {
            showNotification(resp.message || 'Failed', 'error');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        showNotification('Network error', 'error');
    });
}

function deleteProduct(id) {
    
    fetch(`${API}?action=delete`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: id, csrf_token: CSRF_TOKEN })
    })
    .then(r => r.json())
    .then(resp => {
        if (resp.success) {
            loadProducts();
            showNotification('Deleted', 'success');
        }
    });
}

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

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Close modal on escape / overlay click
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeProductModal(); });
document.querySelector('.modal-overlay').addEventListener('click', e => { if (e.target === e.currentTarget) closeProductModal(); });

// Notification fallback
if (typeof showNotification !== 'function') {
    window.showNotification = function(msg, type) {
        const div = document.createElement('div');
        div.className = 'eb-toast eb-toast-' + (type || 'info');
        div.style.cssText = 'position:fixed;top:16px;right:16px;z-index:99999;padding:12px 20px;border-radius:8px;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,.15);color:#fff;background:' + (type === 'error' ? '#dc2626' : type === 'success' ? '#16a34a' : '#3b82f6') + ';animation:ebToastIn .25s';
        div.textContent = msg;
        document.body.appendChild(div);
        setTimeout(function() { div.style.opacity = '0'; setTimeout(function() { div.remove(); }, 300); }, 3000);
    };
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
