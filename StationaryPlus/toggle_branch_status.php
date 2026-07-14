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

$branch_id     = trim($_POST['branch_id'] ?? '');
$target_status = strtoupper(trim($_POST['target_status'] ?? ''));

$validStatuses = ['ACTIVE', 'INACTIVE', 'RENOVATION'];
if ($branch_id === '' || !in_array($target_status, $validStatuses)) {
    echo json_encode(['success' => false, 'error' => 'Missing or invalid branch ID / target status']);
    exit;
}

$check = $conn->prepare("SELECT 1 FROM branches WHERE branch_id = ?");
$check->bind_param('s', $branch_id);
$check->execute();
$check->store_result();
if ($check->num_rows === 0) {
    $check->close();
    echo json_encode(['success' => false, 'error' => 'Branch not found']);
    exit;
}
$check->close();

// Leaving ACTIVE (to INACTIVE or RENOVATION) is blocked while the branch
// still has active orders or assigned staff depending on it being open —
// same guard as save_branch.php's full-form save path.
if ($target_status !== 'ACTIVE') {
    $oStmt = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM orders
         WHERE branch_id = ? AND order_status NOT IN ('COLLECTED','CANCELLED')"
    );
    $oStmt->bind_param('s', $branch_id);
    $oStmt->execute();
    $activeOrders = (int)$oStmt->get_result()->fetch_assoc()['cnt'];
    $oStmt->close();

    $sStmt = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM users WHERE branch_id = ? AND user_role = 'STAFF'"
    );
    $sStmt->bind_param('s', $branch_id);
    $sStmt->execute();
    $assignedStaff = (int)$sStmt->get_result()->fetch_assoc()['cnt'];
    $sStmt->close();

    if ($activeOrders > 0 || $assignedStaff > 0) {
        echo json_encode([
            'success' => false,
            'error'   => "Cannot change status: $activeOrders active order(s) and $assignedStaff assigned staff member(s) — resolve orders and reassign staff via User Management first."
        ]);
        exit;
    }
}

$stmt = $conn->prepare("UPDATE branches SET status = ? WHERE branch_id = ?");
$stmt->bind_param('ss', $target_status, $branch_id);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'new_status' => $target_status]);
