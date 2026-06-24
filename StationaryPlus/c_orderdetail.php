<?php
// c_orderdetail.php — Customer-facing order detail AJAX endpoint
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'auth.php';
require_role('CUSTOMER');
require_once 'db.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];
$id     = trim($_GET['id'] ?? '');

if (!$id) { echo json_encode(['error' => 'No ID']); exit; }

// Verify the order belongs to this customer
$stmt = $conn->prepare(
    "SELECT order_id, order_type, order_date, order_status, estimated_total, notes
     FROM orders
     WHERE order_id = ? AND user_id = ?
     LIMIT 1"
);
$stmt->bind_param('ss', $id, $userId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) { echo json_encode(['error' => 'Not found']); exit; }

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

echo json_encode([
    'id'     => $order['order_id'],
    'type'   => $order['order_type'],
    'date'   => date('d M Y, H:i', strtotime($order['order_date'])),
    'status' => $order['order_status'],
    'amount' => $order['estimated_total'],
    'notes'  => $order['notes'],
    'items'  => $items,
]);