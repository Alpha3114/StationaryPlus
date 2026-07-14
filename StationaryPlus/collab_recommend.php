<?php
// ============================================================
//  collab_recommend.php — Item-Based Collaborative Filtering
//
//  Finds products frequently bought by customers who share
//  purchase history with the current user.
//  Falls back to store-wide popular products if no history.
//
//  GET endpoint — called by c_dashboard.php on page load
//  Returns JSON: {
//    success, type ("collaborative"|"popular"), recommendations: [
//      { product_id, product_name, category, price,
//        frequency, freq_label }
//    ]
//  }
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role('CUSTOMER');
require_once 'db.php';
require_once 'ai_helper.php';

header('Content-Type: application/json');
installJsonErrorGuard();

$userId   = $_SESSION['user_id'];
$branchId = $_SESSION['branch_id'] ?? null;
$limit    = 4;

// ── Step 1: Does this customer have any order history? ────────
$stmt = $conn->prepare(
    "SELECT COUNT(DISTINCT oi.product_id) AS cnt
     FROM order_items oi
     JOIN orders o ON oi.order_id = o.order_id
     WHERE o.user_id = ? AND o.order_status != 'CANCELLED'"
);
$stmt->bind_param('s', $userId);
$stmt->execute();
$historyCount = (int)$stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// ── Step 2a: Collaborative filtering (has history) ───────────
if ($historyCount > 0) {

    // Branch-aware JOIN if branch is selected
    $branchJoin  = $branchId
        ? "JOIN inventory inv ON oi_other.product_id = inv.product_id
           AND inv.branch_id = ? AND inv.stock_quantity > 0"
        : "JOIN inventory inv ON oi_other.product_id = inv.product_id
           AND inv.stock_quantity > 0";

    $stmt = $conn->prepare(
        "SELECT
             p.product_id,p.product_name,p.category,p.price,
             COUNT(DISTINCT o_other.user_id) AS frequency
         FROM order_items oi_other
         JOIN orders o_other ON oi_other.order_id = o_other.order_id
         JOIN products p     ON oi_other.product_id = p.product_id
         $branchJoin
         WHERE
             o_other.user_id != ?
             AND o_other.order_status != 'CANCELLED'
             AND o_other.user_id IN (
                 SELECT DISTINCT o2.user_id
                 FROM order_items oi2
                 JOIN orders o2 ON oi2.order_id = o2.order_id
                 WHERE oi2.product_id IN (
                     SELECT DISTINCT oi3.product_id
                     FROM order_items oi3
                     JOIN orders o3 ON oi3.order_id = o3.order_id
                     WHERE o3.user_id = ?
                       AND o3.order_status != 'CANCELLED'
                     LIMIT 500
                 )
                 AND o2.user_id != ?
                 LIMIT 500
             )
             AND oi_other.product_id NOT IN (
                 SELECT DISTINCT oi4.product_id
                 FROM order_items oi4
                 JOIN orders o4 ON oi4.order_id = o4.order_id
                 WHERE o4.user_id = ?
                 LIMIT 500
             )
             AND p.product_status = 'ACTIVE'
         GROUP BY p.product_id
         ORDER BY frequency DESC, p.product_name ASC
         LIMIT ?"
    );
    $params = $branchId ? [$branchId, $userId, $userId, $userId, $userId] : [$userId, $userId, $userId, $userId];
    $types  = $branchId ? 'sssss' : 'ssss';
    $params[] = $limit;
    $types   .= 'i';
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // If collaborative filtering found results, return them
    if (!empty($rows)) {
        echo json_encode([
            'success'         => true,
            'type'            => 'collaborative',
            'recommendations' => array_map(fn($r) => [
                'product_id'   => $r['product_id'],
                'product_name' => $r['product_name'],
                'category'     => $r['category'],
                'price'        => $r['price'],
                'frequency'    => (int)$r['frequency'],
                'freq_label'   => (int)$r['frequency'] . ' customer'
                                  . ((int)$r['frequency'] !== 1 ? 's' : '')
                                  . ' also bought this',
            ], $rows),
        ]);
        exit;
    }
}

// ── Step 2b: Fallback — store-wide popular products ───────────
// Shown when customer has no history OR collaborative returned nothing
$branchJoin = $branchId
    ? "JOIN inventory inv ON oi.product_id = inv.product_id
       AND inv.branch_id = ? AND inv.stock_quantity > 0"
    : "JOIN inventory inv ON oi.product_id = inv.product_id
       AND inv.stock_quantity > 0";

// Exclude products this customer already bought (if any)
$excludeClause = $historyCount > 0
    ? "AND oi.product_id NOT IN (
           SELECT DISTINCT oi_me.product_id
           FROM order_items oi_me
           JOIN orders o_me ON oi_me.order_id = o_me.order_id
           WHERE o_me.user_id = ?
           LIMIT 500
       )"
    : '';

$stmt = $conn->prepare(
    "SELECT
         p.product_id,
         p.product_name,
         p.category,
         p.price,
         COUNT(DISTINCT o.order_id) AS frequency
     FROM order_items oi
     JOIN orders   o ON oi.order_id   = o.order_id
     JOIN products p ON oi.product_id = p.product_id
     $branchJoin
     WHERE o.order_status != 'CANCELLED'
       AND p.product_status = 'ACTIVE'
       $excludeClause
     GROUP BY p.product_id
     ORDER BY frequency DESC, p.product_name ASC
     LIMIT ?"
);
$params = [];
$types  = '';
if ($branchId) { $params[] = $branchId; $types .= 's'; }
if ($historyCount > 0) { $params[] = $userId; $types .= 's'; }
$params[] = $limit;
$types   .= 'i';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($rows)) {
    echo json_encode(['success' => false, 'error' => 'No product data available yet.']);
    exit;
}

echo json_encode([
    'success'         => true,
    'type'            => 'popular',
    'recommendations' => array_map(fn($r) => [
        'product_id'   => $r['product_id'],
        'product_name' => $r['product_name'],
        'category'     => $r['category'],
        'price'        => $r['price'],
        'frequency'    => (int)$r['frequency'],
        'freq_label'   => 'Popular in store',
    ], $rows),
]);