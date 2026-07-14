<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role(['STAFF', 'ADMIN']);

include 'db.php';
header('Content-Type: application/json');

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$product_id = trim($_POST['product_id'] ?? '');

if ($product_id === '') {
    echo json_encode(['success' => false, 'error' => 'Missing product ID']);
    exit;
}

$stmt = $conn->prepare("SELECT product_status FROM products WHERE product_id = ? LIMIT 1");
$stmt->bind_param('s', $product_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$existing) {
    echo json_encode(['success' => false, 'error' => 'Product not found']);
    exit;
}

$newStatus = (strtoupper(trim($existing['product_status'])) === 'ACTIVE') ? 'INACTIVE' : 'ACTIVE';
$now = date('Y-m-d H:i:s');

$stmt = $conn->prepare("UPDATE products SET product_status = ?, last_updated = ? WHERE product_id = ?");
$stmt->bind_param('sss', $newStatus, $now, $product_id);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'new_status' => $newStatus]);
