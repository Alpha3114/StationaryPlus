<?php
// ============================================================
//  pos_process.php — POS: process walk-in sale
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role(['STAFF', 'ADMIN']);
require_once 'db.php';
require_once 'pricing.php';
require_once 'loyalty.php';
require_once 'audit.php';

header('Content-Type: application/json');

if (!staff_branch_is_active($conn)) {
    echo json_encode(['success' => false, 'error' => 'Your assigned branch is inactive — sales cannot be processed.']);
    exit;
}

// ── 1. Parse inputs ───────────────────────────────────────────
$itemsRaw   = trim($_POST['items']       ?? '[]');
$customerId = trim($_POST['customer_id'] ?? '') ?: null;
$method     = trim($_POST['method']      ?? '');
$amountPaid = (float)($_POST['amount_paid'] ?? 0);
$reference  = trim($_POST['reference']   ?? '') ?: null;
$pointsRedeemedRaw = max(0, (int)($_POST['points_redeemed'] ?? 0));
$branchId   = $_SESSION['branch_id']     ?? null;
$staffId    = $_SESSION['user_id'];

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
// Raw stock_quantity and reserved_quantity are fetched separately.
// Availability check uses (stock - reserved) so walk-in counter
// sales never take units already promised to an online pre-order
// customer, but the actual deduction always operates on the
// real physical stock_quantity.
$productIds   = array_unique(array_column($items, 'product_id'));
$placeholders = implode(',', array_fill(0, count($productIds), '?'));
$types        = str_repeat('s', count($productIds));

if ($branchId) {
    $stmt = $conn->prepare(
        "SELECT p.product_id, p.product_name, p.price, p.discount_percent,
                COALESCE(i.stock_quantity, 0)    AS raw_stock,
                COALESCE(i.reserved_quantity, 0) AS reserved,
                i.inventory_id
         FROM products p
         LEFT JOIN inventory i
                ON p.product_id = i.product_id AND i.branch_id = ?
         WHERE p.product_id IN ($placeholders)
           AND p.product_status = 'ACTIVE'"
    );
    $stmt->bind_param('s' . $types, $branchId, ...$productIds);
} else {
    $stmt = $conn->prepare(
        "SELECT p.product_id, p.product_name, p.price, p.discount_percent,
                COALESCE(SUM(i.stock_quantity), 0)    AS raw_stock,
                COALESCE(SUM(i.reserved_quantity), 0) AS reserved,
                MIN(i.inventory_id) AS inventory_id
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

$total          = 0.0;
$validatedItems = [];

foreach ($items as $item) {
    $pid = trim($item['product_id'] ?? '');
    $qty = max(1, (int)($item['quantity'] ?? 1));

    if (!isset($productMap[$pid])) continue;

    $prod      = $productMap[$pid];
    $rawStock  = (int)$prod['raw_stock'];
    $reserved  = (int)$prod['reserved'];
    $available = max(0, $rawStock - $reserved);

    if ($available < $qty) {
        echo json_encode([
            'success' => false,
            'error'   => "Not enough stock for \"{$prod['product_name']}\". Available: $available.",
        ]);
        exit;
    }

    // Same discounted price a customer would see/pay in the online catalog,
    // so a walk-in POS sale never charges more (or less) for the same product.
    $unitPrice = discounted_price((float)$prod['price'], (float)$prod['discount_percent']);
    $subtotal  = round($unitPrice * $qty, 2);
    $total    += $subtotal;

    $validatedItems[] = [
        'product_id'   => $pid,
        'product_name' => $prod['product_name'],
        'inventory_id' => $prod['inventory_id'],
        'quantity'     => $qty,
        'unit_price'   => $unitPrice,
        'subtotal'     => $subtotal,
        'old_stock'    => $rawStock, // physical stock, used for the actual deduction
    ];
}

$total = round($total, 2);

if (empty($validatedItems)) {
    echo json_encode(['success' => false, 'error' => 'No valid items could be processed.']);
    exit;
}

// ── Loyalty point redemption ────────────────────────────────
// Clamp against the customer's live balance and the server-computed cart
// total — the client's requested value is only a starting point.
$pointsRedeemed = 0;
$pointsDiscount = 0.0;
if ($pointsRedeemedRaw > 0 && $customerId) {
    $custBalance    = get_loyalty_balance($conn, $customerId);
    $maxRedeemable  = max_redeemable_points($custBalance, $total);
    $pointsRedeemed = min($pointsRedeemedRaw, $maxRedeemable);
    if ($pointsRedeemed > 0) {
        $pointsDiscount = round($pointsRedeemed / 100, 2);
    }
}
$finalTotal = round($total - $pointsDiscount, 2);

if ($method === 'CASH' && $amountPaid < $finalTotal) {
    echo json_encode(['success' => false, 'error' => sprintf('Amount paid (RM %.2f) is less than total (RM %.2f).', $amountPaid, $finalTotal)]);
    exit;
}

// ── 5. Run everything inside a DB transaction ─────────────────
$conn->begin_transaction();

try {
    $orderId     = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
    $orderStatus = 'COLLECTED';
    $notes       = 'Walk-in sale (POS)';
    $stmt = $conn->prepare(
        "INSERT INTO orders
            (order_id, user_id, order_type, order_status, estimated_total, notes, branch_id)
         VALUES (?, ?, 'ORDER', ?, ?, ?, ?)"
    );
    $stmt->bind_param('sssdss', $orderId, $customerId, $orderStatus, $finalTotal, $notes, $branchId);
    $stmt->execute();
    $stmt->close();

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

    $paymentId          = 'PAY-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
    // POS payments are witnessed in person by staff at checkout (cash counted,
    // transfer/e-wallet confirmation shown on the spot), so unlike online-order
    // proofs they don't need a separate staff verification pass.
    $verificationStatus = 'VALID';
    $payDate            = date('Y-m-d H:i:s');
    $stmt = $conn->prepare(
        "INSERT INTO payments
            (payment_id, order_id, payment_method, amount,
             record_date, verification_status, reference_number, proof_path,
             points_redeemed, points_discount)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        'sssdssssid',
        $paymentId, $orderId, $method, $finalTotal,
        $payDate, $verificationStatus, $reference, $proofPath,
        $pointsRedeemed, $pointsDiscount
    );
    $stmt->execute();
    $stmt->close();

    // ── Loyalty points: redeem what was applied, then earn on the final
    //    (post-discount) amount actually paid — same rules as online orders.
    $pointsEarned = 0;
    if ($customerId) {
        if ($pointsRedeemed > 0) {
            $redeemedOk = redeem_loyalty_points($conn, $customerId, $pointsRedeemed, $orderId, $paymentId, "Redeemed at POS sale $orderId");
            if (!$redeemedOk) {
                throw new Exception('Customer points balance changed — please retry the sale.');
            }
        }
        $pointsEarned = award_loyalty_points($conn, $customerId, $finalTotal, $orderId, $paymentId, "Earned from POS sale $orderId");
    }

    foreach ($validatedItems as $vi) {
        $oldQty    = $vi['old_stock'];
        $newQty    = $oldQty - $vi['quantity'];
        $changeQty = -$vi['quantity'];
        if ($branchId && $vi['inventory_id']) {
            $stmt = $conn->prepare(
                "UPDATE inventory
                 SET stock_quantity = ?,
                     last_updated   = NOW()
                 WHERE inventory_id = ?"
            );
            $stmt->bind_param('is', $newQty, $vi['inventory_id']);
        } else {
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

        $logStmt = $conn->prepare(
            "INSERT INTO inventory_log
                (inventory_id, product_id, branch_id, change_qty, old_qty, new_qty,
                 reason, reference_id, changed_by)
             VALUES (?, ?, ?, ?, ?, ?, 'POS_SALE', ?, ?)"
        );
        $logStmt->bind_param(
            'sssiiiss',
            $vi['inventory_id'], $vi['product_id'], $branchId,
            $changeQty, $oldQty, $newQty,
            $orderId, $staffId
        );
        $logStmt->execute();
        $logStmt->close();
    }

    $conn->commit();

    log_audit(
        $conn, 'POS_SALE', 'order', $orderId,
        "RM " . number_format($finalTotal, 2) . " ($method, " . count($validatedItems) . " item(s))"
        . ($customerId ? ", customer $customerId" : ", walk-in")
        . ($pointsRedeemed > 0 ? ", redeemed $pointsRedeemed points" : "")
    );

    $change = $method === 'CASH' ? round($amountPaid - $finalTotal, 2) : 0.00;

    echo json_encode([
        'success'         => true,
        'order_id'        => $orderId,
        'payment_id'      => $paymentId,
        'total'           => $finalTotal,
        'subtotal'        => $total,
        'points_redeemed' => $pointsRedeemed,
        'points_discount' => $pointsDiscount,
        'points_earned'   => $pointsEarned,
        'change'          => $change,
        'method'          => $method,
        'items'           => $validatedItems,
        'timestamp'       => date('d M Y, h:i A'),
    ]);

} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Transaction failed: ' . $e->getMessage()]);
}