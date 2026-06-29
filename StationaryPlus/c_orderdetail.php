<?php
// ============================================================
//  c_orderdetail.php — Customer order detail AJAX endpoint
//  GET: ?id=ORDER_ID
//  Returns JSON with items, print_files, and payment status
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role('CUSTOMER');
require_once 'db.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];
$id     = trim($_GET['id'] ?? '');

if (!$id) {
    echo json_encode(['error' => 'No ID provided.']);
    exit;
}

// Verify order belongs to this customer
$stmt = $conn->prepare(
    "SELECT order_id, order_type, order_date, order_status, estimated_total, notes
     FROM orders WHERE order_id = ? AND user_id = ? LIMIT 1"
);
$stmt->bind_param('ss', $id, $userId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    echo json_encode(['error' => 'Order not found.']);
    exit;
}

// Stationery order items
$stmt = $conn->prepare(
    "SELECT p.product_name AS name, oi.quantity AS qty, oi.unit_price
     FROM order_items oi
     JOIN products p ON oi.product_id = p.product_id
     WHERE oi.order_id = ?"
);
$stmt->bind_param('s', $id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Print files linked to this order
$stmt = $conn->prepare(
    "SELECT file_id, file_name, total_pages, color_pages, bw_pages,
            paper_size, binding_type, copies, estimated_price, file_status
     FROM print_files WHERE order_id = ?"
);
$stmt->bind_param('s', $id);
$stmt->execute();
$printFiles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Most recent non-INVALID payment status
$stmt = $conn->prepare(
    "SELECT verification_status FROM payments
     WHERE order_id = ?
     ORDER BY record_date DESC LIMIT 1"
);
$stmt->bind_param('s', $id);
$stmt->execute();
$payRow    = $stmt->get_result()->fetch_assoc();
$payStatus = $payRow['verification_status'] ?? null;
$stmt->close();

// Always compute total directly from source tables so it reflects
// both stationery items AND print files regardless of orders.estimated_total.
// orders.estimated_total can lag behind (e.g. print file not yet reviewed)
// so we never rely on it for the receipt display.
$itemsTotal = array_reduce($items,      fn($s, $i) => $s + $i['qty'] * $i['unit_price'],     0.0);
$printTotal = array_reduce($printFiles, fn($s, $f) => $s + (float)$f['estimated_price'],      0.0);
$total      = $itemsTotal + $printTotal;

echo json_encode([
    'id'          => $order['order_id'],
    'type'        => $order['order_type'],
    'date'        => date('d M Y, H:i', strtotime($order['order_date'])),
    'status'      => $order['order_status'],
    'total'       => $total,
    'notes'       => $order['notes'],
    'items'       => $items,
    'print_files' => $printFiles,
    'pay_status'  => $payStatus,
]);