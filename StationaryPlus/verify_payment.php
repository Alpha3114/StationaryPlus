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

$paymentId       = trim($_POST['payment_id']       ?? '');
$action          = trim($_POST['action']           ?? '');
$rejectionReason = trim($_POST['rejection_reason'] ?? '');

if (!$paymentId || !in_array($action, ['VALID', 'INVALID'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

if ($action === 'INVALID' && $rejectionReason === '') {
    echo json_encode(['success' => false, 'error' => 'Please provide a rejection reason.']);
    exit;
}

// Confirm payment exists and is still PENDING
$stmt = $conn->prepare(
    "SELECT p.payment_id, p.verification_status, p.order_id,
            o.order_type, o.order_status
     FROM payments p
     JOIN orders o ON p.order_id = o.order_id
     WHERE p.payment_id = ? LIMIT 1"
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

// Update verification status (and store the rejection reason, if any)
if ($action === 'INVALID') {
    $stmt = $conn->prepare(
        "UPDATE payments SET verification_status = ?, rejection_reason = ? WHERE payment_id = ?"
    );
    $stmt->bind_param('sss', $action, $rejectionReason, $paymentId);
} else {
    $stmt = $conn->prepare(
        "UPDATE payments SET verification_status = ?, rejection_reason = NULL WHERE payment_id = ?"
    );
    $stmt->bind_param('ss', $action, $paymentId);
}
$stmt->execute();
$stmt->close();

// ── If VALID: conditionally move order to PROCESSING ─────────
$transitioned  = false;
$holdReason    = '';

if ($action === 'VALID') {
    $orderId   = $payment['order_id'];
    $orderType = $payment['order_type'];

    // For PREORDERs: check if there are any print files still awaiting review.
    // If so, hold the transition — the order will move to PROCESSING automatically
    // when the last file is marked REVIEWED in update_print_status.php.
    $blockedByFiles = false;

    if ($orderType === 'PREORDER') {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS cnt FROM print_files
             WHERE order_id = ? AND file_status = 'RECEIVED'"
        );
        $stmt->bind_param('s', $orderId);
        $stmt->execute();
        $unreviewedCount = (int)$stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        if ($unreviewedCount > 0) {
            $blockedByFiles = true;
            $holdReason = "Payment verified. Order will move to Processing once the $unreviewedCount pending print file(s) are reviewed by staff.";
        }
    }

    if (!$blockedByFiles) {
        // Safe to transition — no unreviewed files (or it's an ORDER type)
        $stmt = $conn->prepare(
            "UPDATE orders o
             JOIN payments p ON p.order_id = o.order_id
             SET o.order_status = 'PROCESSING'
             WHERE p.payment_id = ?
               AND o.order_status = 'NEW'"
        );
        $stmt->bind_param('s', $paymentId);
        $stmt->execute();
        $transitioned = ($stmt->affected_rows > 0);
        $stmt->close();
    }
}

$message = match(true) {
    $action === 'INVALID'  => 'Payment rejected.',
    $holdReason !== ''     => $holdReason,
    $transitioned          => 'Payment verified. Order moved to Processing.',
    default                => 'Payment verified.',
};

echo json_encode([
    'success'     => true,
    'action'      => $action,
    'transitioned'=> $transitioned,
    'message'     => $message,
]);