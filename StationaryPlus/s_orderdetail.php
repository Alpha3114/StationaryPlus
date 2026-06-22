<?php
// ============================================================
//  s_order_detail.php — AJAX endpoint for order detail panel
//  Returns JSON: order info + items
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role(['STAFF','ADMIN']);
require_once 'db.php';

header('Content-Type: application/json');

$id   = trim($_GET['id']   ?? '');
$type = trim($_GET['type'] ?? 'order');

if (!$id) { echo json_encode(['error' => 'No ID']); exit; }

$result = [];

if ($type === 'preorder') {
    // ── Preorder header ───────────────────────────────────────
    $stmt = $conn->prepare(
        "SELECT po.preorder_id, po.order_date, po.order_status, po.notes,
                u.name AS customer_name, u.email AS customer_email, u.phone_number AS customer_phone
         FROM preorders po
         JOIN users u ON po.user_id = u.user_id
         WHERE po.preorder_id = ? LIMIT 1"
    );
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $header = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$header) { echo json_encode(['error' => 'Not found']); exit; }

    // ── Preorder items ────────────────────────────────────────
    $stmt = $conn->prepare(
        "SELECT p.product_name AS name, pi.quantity AS qty, pi.unit_price
         FROM preorder_items pi
         JOIN products p ON pi.product_id = p.product_id
         WHERE pi.preorder_id = ?"
    );
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $result = [
        'id'             => $header['preorder_id'],
        'date'           => date('d M Y, H:i', strtotime($header['order_date'])),
        'status'         => $header['order_status'],
        'customer_name'  => $header['customer_name'],
        'customer_email' => $header['customer_email'],
        'customer_phone' => $header['customer_phone'],
        'notes'          => $header['notes'],
        'amount'         => null,
        'items'          => $items,
    ];

} else {
    // ── Order header ──────────────────────────────────────────
    $stmt = $conn->prepare(
        "SELECT o.order_id, o.order_date, o.order_status, o.estimated_total,
                u.name AS customer_name, u.email AS customer_email, u.phone_number AS customer_phone
         FROM orders o
         JOIN users u ON o.user_id = u.user_id
         WHERE o.order_id = ? LIMIT 1"
    );
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $header = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$header) { echo json_encode(['error' => 'Not found']); exit; }

    // ── Order items ───────────────────────────────────────────
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

    $result = [
        'id'             => $header['order_id'],
        'date'           => date('d M Y, H:i', strtotime($header['order_date'])),
        'status'         => $header['order_status'],
        'customer_name'  => $header['customer_name'],
        'customer_email' => $header['customer_email'],
        'customer_phone' => $header['customer_phone'],
        'notes'          => null,
        'amount'         => $header['estimated_total'],
        'items'          => $items,
    ];
}

echo json_encode($result);