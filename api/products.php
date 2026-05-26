<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
requireLogin();

$action = $_GET['action'] ?? '';
$db = Database::getInstance()->getConnection();
$userId = getCurrentUser()['user_id'] ?? 0;
$companyId = $_SESSION["company_id"] ?? null;

if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $search = $_GET['search'] ?? '';
    $category = $_GET['category'] ?? '';
    $where = ["company_id = ?"]; $params = [$companyId];
    if ($search) { $where[] = "(product_name LIKE ? OR sku LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
    if ($category) { $where[] = "category = ?"; $params[] = $category; }
    
    $products = $db->query("SELECT * FROM products WHERE " . implode(' AND ', $where) . " ORDER BY product_name", $params)->fetchAll();
    jsonSuccess('Products loaded', $products);
}

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $productId = $db->insert('products', [
        'company_id'   => $companyId,
        'product_name' => sanitizeInput($input['product_name'] ?? ''),
        'sku'          => sanitizeInput($input['sku'] ?? ''),
        'description'  => sanitizeInput($input['description'] ?? ''),
        'category'     => sanitizeInput($input['category'] ?? ''),
        'price'        => (float)($input['price'] ?? 0),
        'cost'         => !empty($input['cost']) ? (float)$input['cost'] : null,
        'currency'     => sanitizeInput($input['currency'] ?? 'USD'),
        'quantity_in_stock' => !empty($input['quantity_in_stock']) ? (int)$input['quantity_in_stock'] : null,
        'created_by'   => $userId,
    ]);
    jsonSuccess('Product created', ['product_id' => $productId]);
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $productId = (int)($input['product_id'] ?? 0);
    $db->update('products', [
        'product_name' => sanitizeInput($input['product_name'] ?? ''),
        'sku'          => sanitizeInput($input['sku'] ?? ''),
        'description'  => sanitizeInput($input['description'] ?? ''),
        'category'     => sanitizeInput($input['category'] ?? ''),
        'price'        => (float)($input['price'] ?? 0),
        'quantity_in_stock' => !empty($input['quantity_in_stock']) ? (int)$input['quantity_in_stock'] : null,
    ], ['product_id' => $productId, 'company_id' => $companyId]);
    jsonSuccess('Product updated');
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);
    $db->query("DELETE FROM products WHERE product_id = ? AND company_id = ?", [(int)$input['product_id'], $companyId]);
    jsonSuccess('Product deleted');
}

jsonError('Unknown action');
?>
