<?php
// ============================================================
//  confirm_print.php — STEP 2: commit a pending print job to DB
//
//  Called by the customer clicking "Confirm & Submit".
//  Reads the pending data stored in $_SESSION by upload_print.php,
//  creates an order if needed, inserts into print_files, then
//  clears the session entry.
//
//  POST: file_id
//  Returns JSON { success, file_id, order_id }
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role('CUSTOMER');
require_once 'db.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];
$fileId = trim($_POST['file_id'] ?? '');

if (!$fileId) {
    echo json_encode(['success' => false, 'error' => 'No file ID provided.']);
    exit;
}

// ── 1. Retrieve & validate the pending session entry ──────────
$pending = $_SESSION['pending_prints'][$fileId] ?? null;

if (!$pending) {
    echo json_encode([
        'success' => false,
        'error'   => 'Session expired or file not found. Please re-upload your file.',
    ]);
    exit;
}

// Security: ensure this entry belongs to the logged-in user
if ($pending['user_id'] !== $userId) {
    echo json_encode(['success' => false, 'error' => 'Unauthorised.']);
    exit;
}

// Expiry check: pending entries older than 2 hours are invalid
if (time() - ($pending['created_at'] ?? 0) > 7200) {
    unset($_SESSION['pending_prints'][$fileId]);
    echo json_encode([
        'success' => false,
        'error'   => 'This upload has expired. Please re-upload your file.',
    ]);
    exit;
}

// ── 2. Create an order if no pre-order was linked ────────────
$orderId     = $pending['linked_order_id'];
$orderBranch = $_SESSION['branch_id'] ?? null;
$isNewOrder  = ($orderId === null);

$conn->begin_transaction();

if ($orderId === null) {
    $autoOrderId = 'PRE-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
    $printNote   = 'Print job: ' . $pending['file_name'];

    $stmt = $conn->prepare(
        "INSERT INTO orders
            (order_id, user_id, order_type, order_status, estimated_total, notes, branch_id)
         VALUES (?, ?, 'PREORDER', 'NEW', ?, ?, ?)"
    );
    $stmt->bind_param('ssdss', $autoOrderId, $userId, $pending['estimated_price'], $printNote, $orderBranch);
    $stmt->execute();
    $stmt->close();

    $orderId = $autoOrderId;
}

// ── 3. Insert into print_files ────────────────────────────────
$stmt = $conn->prepare(
    "INSERT INTO print_files
        (file_id, order_id, user_id, file_name, file_path, file_type,
         print_type, paper_size, paper_type, binding_type, copies,
         total_pages, color_pages, bw_pages,
         estimated_price, duration_min, ai_analysis, file_status)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'RECEIVED')"
);
$stmt->bind_param(
    'ssssssssssiiiidis',
    $fileId,
    $orderId,
    $userId,
    $pending['file_name'],
    $pending['file_path'],
    $pending['file_type'],
    $pending['print_type'],
    $pending['paper_size'],
    $pending['paper_type'],
    $pending['binding_type'],
    $pending['copies'],
    $pending['total_pages'],
    $pending['color_pages'],
    $pending['bw_pages'],
    $pending['estimated_price'],
    $pending['duration_min'],
    $pending['analysis_json']
);

if (!$stmt->execute()) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Failed to save print job. Please try again.']);
    exit;
}
$stmt->close();

$conn->commit();

// ── Order confirmation email — only for a newly auto-created order;
// a print job attached to an existing pre-order was already confirmed.
if ($isNewOrder) {
    $cStmt = $conn->prepare("SELECT email, name FROM users WHERE user_id = ? LIMIT 1");
    $cStmt->bind_param('s', $userId);
    $cStmt->execute();
    $customer = $cStmt->get_result()->fetch_assoc();
    $cStmt->close();

    if ($customer && !empty($customer['email'])) {
        require_once 'mailer.php';
        $subject = "StationaryPlus — Pre-order Confirmed ($orderId)";
        $body    = "Hi {$customer['name']},\n\n"
                 . "Your print job has been received and is now being processed.\n\n"
                 . "Order ID: $orderId\n"
                 . "File: {$pending['file_name']}\n"
                 . "Estimated Total: RM " . number_format($pending['estimated_price'], 2) . "\n\n"
                 . "Track your order status: c_orderstatus.php\n\n"
                 . "— StationaryPlus Team";
        sendAppEmail($customer['email'], $subject, $body);
    }
}

// ── 4. Clear the session entry ────────────────────────────────
unset($_SESSION['pending_prints'][$fileId]);

// ── 5. Return success ─────────────────────────────────────────
echo json_encode([
    'success'  => true,
    'file_id'  => $fileId,
    'order_id' => $orderId,
]);