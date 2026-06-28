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

// Rejection reason is mandatory when rejecting
if ($status === 'REJECTED' && $rejectionReason === '') {
    echo json_encode(['success' => false, 'error' => 'Please enter a rejection reason.']);
    exit;
}

// Confirm file exists
$stmt = $conn->prepare(
    "SELECT file_id, file_status FROM print_files WHERE file_id = ? LIMIT 1"
);
$stmt->bind_param('s', $fileId);
$stmt->execute();
$file = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$file) {
    echo json_encode(['success' => false, 'error' => 'Print file not found.']);
    exit;
}

// Build update query depending on status and optional final_price
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

echo json_encode([
    'success' => true,
    'status'  => $status,
    'message' => 'Print file marked as ' . ucfirst(strtolower($status)) . '.',
]);