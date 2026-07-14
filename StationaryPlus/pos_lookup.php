<?php
// ============================================================
//  pos_lookup.php — POS: product search / barcode lookup
//  GET ?q=PRODUCT_ID_OR_NAME
//  Returns JSON { success, products: [...] }
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role(['STAFF', 'ADMIN']);
require_once 'db.php';
require_once 'pricing.php';

header('Content-Type: application/json');

$q        = trim($_GET['q'] ?? '');
$branchId = $_SESSION['branch_id'] ?? null;

if (strlen($q) < 1) {
    echo json_encode(['success' => false, 'error' => 'Enter a product ID or name.']);
    exit;
}

// Exact product_id match first (barcode scan), then partial name search.
// Also returns branch stock so the UI can warn about low stock.
if ($branchId) {
    $stmt = $conn->prepare(
        "SELECT p.product_id, p.product_name, p.category, p.price, p.discount_percent,
                COALESCE(i.stock_quantity, 0) AS stock
         FROM products p
         LEFT JOIN inventory i
                ON p.product_id = i.product_id AND i.branch_id = ?
         WHERE p.product_status = 'ACTIVE'
           AND (p.product_id = ? OR p.product_name LIKE ?)
         ORDER BY (p.product_id = ?) DESC, p.product_name ASC
         LIMIT 8"
    );
    $like = "%$q%";
    $stmt->bind_param('ssss', $branchId, $q, $like, $q);
} else {
    $stmt = $conn->prepare(
        "SELECT p.product_id, p.product_name, p.category, p.price, p.discount_percent,
                COALESCE(SUM(i.stock_quantity), 0) AS stock
         FROM products p
         LEFT JOIN inventory i ON p.product_id = i.product_id
         WHERE p.product_status = 'ACTIVE'
           AND (p.product_id = ? OR p.product_name LIKE ?)
         GROUP BY p.product_id
         ORDER BY (p.product_id = ?) DESC, p.product_name ASC
         LIMIT 8"
    );
    $like = "%$q%";
    $stmt->bind_param('sss', $q, $like, $q);
}

$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($products)) {
    echo json_encode(['success' => false, 'error' => "No product found for \"$q\"."]);
    exit;
}

// Adds the actually-charged price alongside the base price, so the POS UI
// can show a discount the same way the customer catalog does.
foreach ($products as &$p) {
    $p['discount_percent']  = (float)$p['discount_percent'];
    $p['discounted_price']  = discounted_price((float)$p['price'], $p['discount_percent']);
}
unset($p);

echo json_encode(['success' => true, 'products' => $products]);