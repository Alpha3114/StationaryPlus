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

if ($branch_name === '') {
    echo json_encode(['success' => false, 'error' => 'Branch name is required']);
    exit;
}

try {
    if ($branch_id !== '') {
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

            echo json_encode(['success' => true, 'action' => 'updated', 'branch_id' => $branch_id]);
            exit;
        } else {
            $check->close();
            $stmt = $conn->prepare("INSERT INTO branches (branch_id, branch_name, address, phone_number, status) VALUES (?,?,?,?,?)");
            $stmt->bind_param('sssss', $branch_id, $branch_name, $address, $phone_number, $status);
            $stmt->execute();
            $stmt->close();

            echo json_encode(['success' => true, 'action' => 'inserted', 'branch_id' => $branch_id]);
            exit;
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO branches (branch_name, address, phone_number, status) VALUES (?,?,?,?)");
        $stmt->bind_param('ssss', $branch_name, $address, $phone_number, $status);
        $stmt->execute();
        $newId = $conn->insert_id;
        $stmt->close();

        echo json_encode(['success' => true, 'action' => 'inserted', 'insert_id' => $newId]);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>