<?php
// ============================================================
//  pos_process.php — POS: process walk-in sale
//
//  POST fields:
//    items        — JSON array [{product_id, quantity}, ...]
//    customer_id  — user_id or empty (walk-in guest)
//    method       — CASH | TRANSFER | OTHER
//    amount_paid  — float (cash tendered; for change calculation)
//    reference    — optional reference number (transfer)
//    proof        — optional file upload (transfer/other)
//
//  Returns JSON { success, order_id, payment_id, total, change, items }
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role(['STAFF', 'ADMIN']);
require_once 'db.php';

header('Content-Type: application/json');

// ── 1. Parse inputs ───────────────────────────────────────────
$itemsRaw   = trim($_POST['items']       ?? '[]');
$customerId = trim($_POST['customer_id'] ?? '') ?: null;
$method     = trim($_POST['method']      ?? '');
$amountPaid = (float)($_POST['amount_paid'] ?? 0);
$reference  = trim($_POST['reference']   ?? '') ?: null;
$branchId   = $_SESSION['branch_id']     ?? null;

$items = json_decode($itemsRaw, true);

// ── 2. Basic validation ───────────────────────────────────────
if (empty($items) || !is_array($items)) {
    echo json_encode(['success' => false, 'error' => 'Cart is empty.']);
    exit;
}
if (!in_array($method, ['CASH', 'TRANSFER', 'OTHER'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid payment method.']);
    exit;
}
if ($method === 'TRANSFER' && empty($reference)) {
    echo json_encode(['success' => false, 'error' => 'Reference number is required for bank transfer.']);
    exit;
}

// ── 3. Handle proof upload ────────────────────────────────────
$proofPath = null;
if (in_array($method, ['TRANSFER', 'OTHER'])
    && isset($_FILES['proof'])
    && $_FILES['proof']['error'] === UPLOAD_ERR_OK) {

    $allowed = ['image/jpeg', 'image/png', 'application/pdf'];
    $mime    = mime_content_type($_FILES['proof']['tmp_name']);
    $size    = $_FILES['proof']['size'];

    if (in_array($mime, $allowed) && $size <= 5 * 1024 * 1024) {
        $dir = 'uploads/payment_proofs/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext      = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
        $filename = 'pos_proof_' . uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['proof']['tmp_name'], $dir . $filename);
        $proofPath = $dir . $filename;
    }
}

// ── 4. Fetch & validate products from DB ─────────────────────
//  Never trust client-side prices — re-fetch everything from DB.
$productIds   = array_unique(array_column($items, 'product_id'));
$placeholders = implode(',', array_fill(0, count($productIds), '?'));
$types        = str_repeat('s', count($productIds));

if ($branchId) {
    $stmt = $conn->prepare(
        "SELECT p.product_id, p.product_name, p.price,
                COALESCE(i.stock_quantity, 0) AS stock
         FROM products p
         LEFT JOIN inventory i
                ON p.product_id = i.product_id AND i.branch_id = ?
         WHERE p.product_id IN ($placeholders)
           AND p.product_status = 'ACTIVE'"
    );
    $stmt->bind_param('s' . $types, $branchId, ...$productIds);
} else {
    $stmt = $conn->prepare(
        "SELECT p.product_id, p.product_name, p.price,
                COALESCE(SUM(i.stock_quantity), 0) AS stock
         FROM products p
         LEFT JOIN inventory i ON p.product_id = i.product_id
         WHERE p.product_id IN ($placeholders)
           AND p.product_status = 'ACTIVE'
         GROUP BY p.product_id"
    );
    $stmt->bind_param($types, ...$productIds);
}

$stmt->execute();
$dbRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$productMap = array_column($dbRows, null, 'product_id');

// Build validated cart and compute total
$total          = 0.0;
$validatedItems = [];

foreach ($items as $item) {
    $pid = trim($item['product_id'] ?? '');
    $qty = max(1, (int)($item['quantity'] ?? 1));

    if (!isset($productMap[$pid])) continue;

    $prod  = $productMap[$pid];
    $stock = (int)$prod['stock'];

    if ($stock < $qty) {
        echo json_encode([
            'success' => false,
            'error'   => "Not enough stock for \"{$prod['product_name']}\". Available: $stock.",
        ]);
        exit;
    }

    $unitPrice = (float)$prod['price'];
    $subtotal  = round($unitPrice * $qty, 2);
    $total    += $subtotal;

    $validatedItems[] = [
        'product_id'   => $pid,
        'product_name' => $prod['product_name'],
        'quantity'     => $qty,
        'unit_price'   => $unitPrice,
        'subtotal'     => $subtotal,
    ];
}

$total = round($total, 2);

if (empty($validatedItems)) {
    echo json_encode(['success' => false, 'error' => 'No valid items could be processed.']);
    exit;
}

if ($method === 'CASH' && $amountPaid < $total) {
    echo json_encode(['success' => false, 'error' => sprintf('Amount paid (RM %.2f) is less than total (RM %.2f).', $amountPaid, $total)]);
    exit;
}

// ── 5. Run everything inside a DB transaction ─────────────────
$conn->begin_transaction();

try {
    // 5a. Create the order
    $orderId     = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
    $orderStatus = 'COLLECTED'; // Walk-in sale — already fulfilled
    $notes       = 'Walk-in sale (POS)';

    $stmt = $conn->prepare(
        "INSERT INTO orders
            (order_id, user_id, order_type, order_status, estimated_total, notes)
         VALUES (?, ?, 'ORDER', ?, ?, ?)"
    );
    $stmt->bind_param('sssds', $orderId, $customerId, $orderStatus, $total, $notes);
    $stmt->execute();
    $stmt->close();

    // 5b. Insert order items
    $stmt = $conn->prepare(
        "INSERT INTO order_items
            (order_item_id, order_id, product_id, quantity, unit_price)
         VALUES (?, ?, ?, ?, ?)"
    );
    foreach ($validatedItems as $vi) {
        $itemId = 'OI-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
        $stmt->bind_param('sssid', $itemId, $orderId, $vi['product_id'], $vi['quantity'], $vi['unit_price']);
        $stmt->execute();
    }
    $stmt->close();

    // 5c. Record payment
    $paymentId          = 'PAY-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
    $verificationStatus = $method === 'CASH' ? 'VALID' : 'PENDING';
    $payDate            = date('Y-m-d H:i:s');

    $stmt = $conn->prepare(
        "INSERT INTO payments
            (payment_id, order_id, payment_method, amount,
             record_date, verification_status, reference_number, proof_path)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        'sssdssss',
        $paymentId, $orderId, $method, $total,
        $payDate, $verificationStatus, $reference, $proofPath
    );
    $stmt->execute();
    $stmt->close();

    // 5d. Decrement inventory
    foreach ($validatedItems as $vi) {
        if ($branchId) {
            $stmt = $conn->prepare(
                "UPDATE inventory
                 SET stock_quantity = GREATEST(0, stock_quantity - ?),
                     last_updated   = NOW()
                 WHERE product_id = ? AND branch_id = ?"
            );
            $stmt->bind_param('iss', $vi['quantity'], $vi['product_id'], $branchId);
        } else {
            // No branch context — deduct from total pool (admin scenario)
            $stmt = $conn->prepare(
                "UPDATE inventory
                 SET stock_quantity = GREATEST(0, stock_quantity - ?),
                     last_updated   = NOW()
                 WHERE product_id = ?
                 LIMIT 1"
            );
            $stmt->bind_param('is', $vi['quantity'], $vi['product_id']);
        }
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();

    $change = $method === 'CASH' ? round($amountPaid - $total, 2) : 0.00;

    echo json_encode([
        'success'    => true,
        'order_id'   => $orderId,
        'payment_id' => $paymentId,
        'total'      => $total,
        'change'     => $change,
        'method'     => $method,
        'items'      => $validatedItems,
        'timestamp'  => date('d M Y, h:i A'),
    ]);

} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Transaction failed: ' . $e->getMessage()]);
}