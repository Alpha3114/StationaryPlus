<?php
// ============================================================
//  update_print_status.php — AJAX: staff updates print file status
//  POST: file_id, status (REVIEWED | REJECTED),
//        final_price (optional), rejection_reason (required if REJECTED)
//  Returns JSON
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role(['STAFF', 'ADMIN']);
require_once 'db.php';

header('Content-Type: application/json');

$fileId          = trim($_POST['file_id']          ?? '');
$status          = trim($_POST['status']           ?? '');
$finalPrice      = $_POST['final_price']           ?? null;
$rejectionReason = trim($_POST['rejection_reason'] ?? '');

if (!$fileId || !in_array($status, ['REVIEWED', 'REJECTED'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

if ($status === 'REJECTED' && $rejectionReason === '') {
    echo json_encode(['success' => false, 'error' => 'Please enter a rejection reason.']);
    exit;
}

// Fetch file + its linked order in one query
$stmt = $conn->prepare(
    "SELECT pf.file_id, pf.file_status, pf.order_id,
            o.order_status, o.order_type
     FROM print_files pf
     LEFT JOIN orders o ON pf.order_id = o.order_id
     WHERE pf.file_id = ? LIMIT 1"
);
$stmt->bind_param('s', $fileId);
$stmt->execute();
$file = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$file) {
    echo json_encode(['success' => false, 'error' => 'Print file not found.']);
    exit;
}

// ── Update the print file status ──────────────────────────────
if ($status === 'REJECTED') {
    $stmt = $conn->prepare(
        "UPDATE print_files SET file_status = ?, rejection_reason = ? WHERE file_id = ?"
    );
    $stmt->bind_param('sss', $status, $rejectionReason, $fileId);

} elseif ($finalPrice !== null && is_numeric($finalPrice) && $finalPrice >= 0) {
    $price = (float)$finalPrice;
    $stmt  = $conn->prepare(
        "UPDATE print_files
         SET file_status = ?, estimated_price = ?, rejection_reason = NULL
         WHERE file_id = ?"
    );
    $stmt->bind_param('sds', $status, $price, $fileId);

} else {
    $stmt = $conn->prepare(
        "UPDATE print_files SET file_status = ?, rejection_reason = NULL WHERE file_id = ?"
    );
    $stmt->bind_param('ss', $status, $fileId);
}

$stmt->execute();
$stmt->close();

// ── Check if order should move to PROCESSING ─────────────────
// All five conditions must be true:
//   1. File was just marked REVIEWED (not rejected)
//   2. Order exists and is still NEW
//   3. Order is a PREORDER (print job)
//   4. A VALID payment exists for this order
//   5. No remaining RECEIVED (unreviewed) files on this order

$orderTransitioned = false;
$orderId           = $file['order_id'] ?? null;

if ($status === 'REVIEWED'
    && $orderId
    && $file['order_status'] === 'NEW'
    && $file['order_type']   === 'PREORDER') {

    // Condition 4: valid payment exists
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM payments
         WHERE order_id = ? AND verification_status = 'VALID'"
    );
    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $hasValidPayment = (int)$stmt->get_result()->fetch_assoc()['cnt'] > 0;
    $stmt->close();

    if ($hasValidPayment) {
        // Condition 5: no more unreviewed files
        // The file we just updated is already REVIEWED in the DB at this point
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS cnt FROM print_files
             WHERE order_id = ? AND file_status = 'RECEIVED'"
        );
        $stmt->bind_param('s', $orderId);
        $stmt->execute();
        $remainingUnreviewed = (int)$stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        if ($remainingUnreviewed === 0) {
            $stmt = $conn->prepare(
                "UPDATE orders SET order_status = 'PROCESSING'
                 WHERE order_id = ? AND order_status = 'NEW'"
            );
            $stmt->bind_param('s', $orderId);
            $stmt->execute();
            $orderTransitioned = ($stmt->affected_rows > 0);
            $stmt->close();
        }
    }
}

$message = 'Print file marked as ' . ucfirst(strtolower($status)) . '.';
if ($orderTransitioned) {
    $message .= ' Order moved to Processing (payment was already verified).';
}

echo json_encode([
    'success'            => true,
    'status'             => $status,
    'order_transitioned' => $orderTransitioned,
    'message'            => $message,
]);