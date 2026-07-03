<?php
// ============================================================
//  cart_recommend.php — "Frequently Bought Together"
//
//  Different algorithm from collab_recommend.php (which matches
//  on customers similar to YOU, across their whole history).
//  This one matches on ORDERS that contained the same product(s)
//  currently in your cart — classic basket-level co-purchase,
//  independent of who bought it or when.
//
//  GET endpoint — called by c_preorder.php when the cart is
//  non-empty.
//  Returns JSON: {
//    success, recommendations: [
//      { product_id, product_name, category, price,
//        frequency, freq_label }
//    ]
//  }
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role('CUSTOMER');
require_once 'db.php';

header('Content-Type: application/json');

$branchId = $_SESSION['branch_id'] ?? null;
$limit    = 4;

$cartProductIds = array_keys($_SESSION['cart'] ?? []);

if (empty($cartProductIds)) {
    echo json_encode(['success' => true, 'recommendations' => []]);
    exit;
}

// Same in-stock filter as collab_recommend.php — a co-purchase match is
// useless to show if there's nowhere to actually buy it right now.
$branchJoin = $branchId
    ? "JOIN inventory inv ON oi_co.product_id = inv.product_id
       AND inv.branch_id = ? AND inv.stock_quantity > 0"
    : "JOIN inventory inv ON oi_co.product_id = inv.product_id
       AND inv.stock_quantity > 0";

$cartPlaceholders = implode(',', array_fill(0, count($cartProductIds), '?'));

$sql = "
    SELECT
        p.product_id,
        p.product_name,
        p.category,
        p.price,
        COUNT(DISTINCT oi_co.order_id) AS frequency
    FROM order_items oi_co
    JOIN orders o_co ON oi_co.order_id = o_co.order_id
    JOIN products p  ON oi_co.product_id = p.product_id
    $branchJoin
    WHERE
        o_co.order_status != 'CANCELLED'

        -- the order also contained at least one item currently in the cart
        AND o_co.order_id IN (
            SELECT DISTINCT oi_cart.order_id
            FROM order_items oi_cart
            JOIN orders o_cart ON oi_cart.order_id = o_cart.order_id
            WHERE oi_cart.product_id IN ($cartPlaceholders)
              AND o_cart.order_status != 'CANCELLED'
        )

        -- don't recommend what's already in the cart
        AND oi_co.product_id NOT IN ($cartPlaceholders)

        AND p.product_status = 'ACTIVE'

    GROUP BY p.product_id
    ORDER BY frequency DESC, p.product_name ASC
    LIMIT ?
";

$stmt = $conn->prepare($sql);

// Param order: [branchId if used] + cartProductIds (subquery) + cartProductIds (exclusion) + limit
$params = [];
$types  = '';
if ($branchId) {
    $params[] = $branchId;
    $types   .= 's';
}
foreach ($cartProductIds as $pid) { $params[] = $pid; $types .= 's'; } // subquery IN
foreach ($cartProductIds as $pid) { $params[] = $pid; $types .= 's'; } // exclusion NOT IN
$params[] = $limit;
$types   .= 'i';

$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode([
    'success'         => true,
    'recommendations' => array_map(fn($r) => [
        'product_id'   => $r['product_id'],
        'product_name' => $r['product_name'],
        'category'     => $r['category'],
        'price'        => $r['price'],
        'frequency'    => (int)$r['frequency'],
        'freq_label'   => 'Bought together ' . (int)$r['frequency'] . ' time'
                          . ((int)$r['frequency'] !== 1 ? 's' : ''),
    ], $rows),
]);
