<?php
// ============================================================
//  s_orderdetail.php — AJAX endpoint for staff order detail
//
//  Refactored: single query against the unified orders table.
//  The ?type= parameter is no longer needed but kept for
//  backwards compatibility with the existing JS caller.
//
//  GET params:
//    id   — order_id (works for both ORDER and PREORDER types)
//    type — ignored server-side; order_type comes from DB
// ============================================================
 
if (session_status() === PHP_SESSION_NONE) session_start();
 
require_once 'auth.php';
require_role(['STAFF','ADMIN']);
require_once 'db.php';
 
header('Content-Type: application/json');
 
$id = trim($_GET['id'] ?? '');
 
if (!$id) {
    echo json_encode(['error' => 'No ID provided']);
    exit;
}
 
// ── Single header query — works for both order types ──────────
$stmt = $conn->prepare(
    "SELECT
         o.order_id,
         o.order_type,
         o.order_date,
         o.order_status,
         o.estimated_total,
         o.notes,
         u.name         AS customer_name,
         u.email        AS customer_email,
         u.phone_number AS customer_phone
     FROM orders o
     JOIN users u ON o.user_id = u.user_id
     WHERE o.order_id = ?
     LIMIT 1"
);
$stmt->bind_param('s', $id);
$stmt->execute();
$header = $stmt->get_result()->fetch_assoc();
$stmt->close();
 
if (!$header) {
    echo json_encode(['error' => 'Order not found']);
    exit;
}
 
// ── Items — same table for both types ─────────────────────────
$stmt = $conn->prepare(
    "SELECT
         p.product_name AS name,
         oi.quantity    AS qty,
         oi.unit_price
     FROM order_items oi
     JOIN products p ON oi.product_id = p.product_id
     WHERE oi.order_id = ?"
);
$stmt->bind_param('s', $id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
 
// ── Print files (only relevant for PREORDER type) ─────────────
$printFiles = [];
if ($header['order_type'] === 'PREORDER') {
    $stmt = $conn->prepare(
        "SELECT file_id, file_name, file_type, upload_date,
                file_status, print_type, paper_size, copies
         FROM print_files
         WHERE order_id = ?
         ORDER BY upload_date DESC"
    );
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $printFiles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
 
// ── Compute total from items if not stored ────────────────────
$computedTotal = array_reduce($items, fn($sum, $i) => $sum + ($i['qty'] * $i['unit_price']), 0.0);
$displayTotal  = $header['estimated_total'] ?? $computedTotal;
 
echo json_encode([
    'id'             => $header['order_id'],
    'type'           => $header['order_type'],      // 'ORDER' | 'PREORDER'
    'date'           => date('d M Y, H:i', strtotime($header['order_date'])),
    'status'         => $header['order_status'],
    'customer_name'  => $header['customer_name'],
    'customer_email' => $header['customer_email'],
    'customer_phone' => $header['customer_phone'],
    'notes'          => $header['notes'],
    'amount'         => $displayTotal,
    'items'          => $items,
    'print_files'    => $printFiles,
]);