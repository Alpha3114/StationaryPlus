<?php
// ============================================================
//  verify_payment.php — AJAX: staff verifies a payment
//  POST: payment_id, action (VALID | INVALID)
//  Returns JSON
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role(['STAFF', 'ADMIN']);
require_once 'db.php';

header('Content-Type: application/json');

$paymentId = trim($_POST['payment_id'] ?? '');
$action    = trim($_POST['action']     ?? '');

if (!$paymentId || !in_array($action, ['VALID', 'INVALID'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

// Confirm payment exists and is still PENDING
$stmt = $conn->prepare(
    "SELECT payment_id, verification_status FROM payments WHERE payment_id = ? LIMIT 1"
);
$stmt->bind_param('s', $paymentId);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payment) {
    echo json_encode(['success' => false, 'error' => 'Payment not found.']);
    exit;
}

if ($payment['verification_status'] !== 'PENDING') {
    echo json_encode([
        'success' => false,
        'error'   => 'Payment has already been ' . strtolower($payment['verification_status']) . '.',
    ]);
    exit;
}

// Update verification status
$stmt = $conn->prepare(
    "UPDATE payments SET verification_status = ? WHERE payment_id = ?"
);
$stmt->bind_param('ss', $action, $paymentId);
$stmt->execute();
$stmt->close();

// If VALID — automatically move linked order to PROCESSING (if still NEW)
if ($action === 'VALID') {
    $stmt = $conn->prepare(
        "UPDATE orders o
         JOIN payments p ON p.order_id = o.order_id
         SET o.order_status = 'PROCESSING'
         WHERE p.payment_id = ?
           AND o.order_status = 'NEW'"
    );
    $stmt->bind_param('s', $paymentId);
    $stmt->execute();
    $stmt->close();
}

echo json_encode([
    'success' => true,
    'action'  => $action,
    'message' => 'Payment marked as ' . ($action === 'VALID' ? 'Verified' : 'Rejected') . '.',
]);