<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role(['STAFF', 'ADMIN']);

include 'db.php';
require_once 'audit.php';
header('Content-Type: application/json');

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$branch_id = trim($_POST['branch_id'] ?? '');
$branch_name = trim($_POST['branch_name'] ?? '');
$address = trim($_POST['address'] ?? '');
$phone_number = trim($_POST['phone_number'] ?? '');
$status = trim($_POST['status'] ?? 'ACTIVE');

// Normalize to the DB's uppercase enum convention regardless of what's posted
$status = strtoupper($status);
if (!in_array($status, ['ACTIVE', 'INACTIVE', 'RENOVATION'])) {
    $status = 'ACTIVE';
}

// branch_id is a varchar PK (no AUTO_INCREMENT) — generate one in the
// existing BRxxx format when the admin leaves the field blank, rather
// than relying on $conn->insert_id (always 0 for a non-autoincrement PK).
if ($branch_id === '') {
    $maxRow = $conn->query(
        "SELECT branch_id FROM branches WHERE branch_id REGEXP '^BR[0-9]+$'
         ORDER BY CAST(SUBSTRING(branch_id, 3) AS UNSIGNED) DESC LIMIT 1"
    )->fetch_assoc();
    $nextNum   = $maxRow ? ((int)substr($maxRow['branch_id'], 2) + 1) : 1;
    $branch_id = 'BR' . str_pad((string)$nextNum, 3, '0', STR_PAD_LEFT);
}

if ($branch_name === '') {
    echo json_encode(['success' => false, 'error' => 'Branch name is required']);
    exit;
}

try {
    $check = $conn->prepare("SELECT 1 FROM branches WHERE branch_id = ?");
    $check->bind_param('s', $branch_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $check->close();

        // Deactivating/renovating a branch is blocked while it still has
        // active orders or assigned staff depending on it being open.
        if ($status !== 'ACTIVE') {
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
                    'error'   => "Cannot deactivate: $activeOrders active order(s) and $assignedStaff assigned staff member(s) — resolve orders and reassign staff via User Management first."
                ]);
                exit;
            }
        }

        $stmt = $conn->prepare("UPDATE branches SET branch_name = ?, address = ?, phone_number = ?, status = ? WHERE branch_id = ?");
        $stmt->bind_param('sssss', $branch_name, $address, $phone_number, $status, $branch_id);
        $stmt->execute();
        $stmt->close();

        log_audit($conn, 'BRANCH_UPDATE', 'branch', $branch_id, "Updated \"$branch_name\" (status $status)");

        echo json_encode(['success' => true, 'action' => 'updated', 'branch_id' => $branch_id]);
        exit;
    } else {
        $check->close();
        $stmt = $conn->prepare("INSERT INTO branches (branch_id, branch_name, address, phone_number, status) VALUES (?,?,?,?,?)");
        $stmt->bind_param('sssss', $branch_id, $branch_name, $address, $phone_number, $status);
        $stmt->execute();
        $stmt->close();

        log_audit($conn, 'BRANCH_CREATE', 'branch', $branch_id, "Created \"$branch_name\" (status $status)");

        echo json_encode(['success' => true, 'action' => 'inserted', 'branch_id' => $branch_id]);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>