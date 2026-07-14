<?php
// ============================================================
//  loyalty.php — Loyalty points (RM1 spent = 1 point = RM0.01)
//  Usage: require_once 'loyalty.php'; (after db.php)
//  Callers are responsible for wrapping these in a transaction
//  alongside whatever order/payment write they belong to.
// ============================================================

function get_loyalty_balance(mysqli $conn, string $userId): int {
    $stmt = $conn->prepare("SELECT loyalty_points FROM users WHERE user_id = ?");
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $bal = (int)($stmt->get_result()->fetch_assoc()['loyalty_points'] ?? 0);
    $stmt->close();
    return $bal;
}

function insert_loyalty_txn(mysqli $conn, string $userId, ?string $orderId, ?string $paymentId, string $type, int $points, int $balanceAfter, ?string $note): void {
    $txnId = 'LOY-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
    $now   = date('Y-m-d H:i:s');
    $stmt  = $conn->prepare(
        "INSERT INTO loyalty_transactions
            (txn_id, user_id, order_id, payment_id, txn_type, points, balance_after, note, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('sssssiiss', $txnId, $userId, $orderId, $paymentId, $type, $points, $balanceAfter, $note, $now);
    $stmt->execute();
    $stmt->close();
}

/**
 * Awards points for an amount actually paid (RM1 = 1 point, floored).
 * Returns the number of points awarded (0 if amount rounds down to nothing).
 */
function award_loyalty_points(mysqli $conn, string $userId, float $amountPaid, ?string $orderId, ?string $paymentId, ?string $note = null): int {
    $points = (int) floor($amountPaid);
    if ($points <= 0) return 0;

    $stmt = $conn->prepare("UPDATE users SET loyalty_points = loyalty_points + ? WHERE user_id = ?");
    $stmt->bind_param('is', $points, $userId);
    $stmt->execute();
    $stmt->close();

    $balance = get_loyalty_balance($conn, $userId);
    insert_loyalty_txn($conn, $userId, $orderId, $paymentId, 'EARN', $points, $balance, $note);
    return $points;
}

/**
 * Redeems points for a customer. Uses a conditional UPDATE (balance >= points)
 * so a race between two concurrent redemptions can't push the balance
 * negative. Returns false (no-op) if the balance was insufficient.
 */
function redeem_loyalty_points(mysqli $conn, string $userId, int $points, ?string $orderId, ?string $paymentId, ?string $note = null): bool {
    if ($points <= 0) return true;

    $stmt = $conn->prepare(
        "UPDATE users SET loyalty_points = loyalty_points - ? WHERE user_id = ? AND loyalty_points >= ?"
    );
    $stmt->bind_param('isi', $points, $userId, $points);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$ok) return false;

    $balance = get_loyalty_balance($conn, $userId);
    insert_loyalty_txn($conn, $userId, $orderId, $paymentId, 'REDEEM', -$points, $balance, $note);
    return true;
}

/**
 * Refunds previously redeemed points (e.g. a payment carrying a redemption
 * was later rejected). Always succeeds — there's no balance floor to hit
 * when adding points back.
 */
function refund_loyalty_points(mysqli $conn, string $userId, int $points, ?string $orderId, ?string $paymentId, ?string $note = null): void {
    if ($points <= 0) return;

    $stmt = $conn->prepare("UPDATE users SET loyalty_points = loyalty_points + ? WHERE user_id = ?");
    $stmt->bind_param('is', $points, $userId);
    $stmt->execute();
    $stmt->close();

    $balance = get_loyalty_balance($conn, $userId);
    insert_loyalty_txn($conn, $userId, $orderId, $paymentId, 'REVERSAL', $points, $balance, $note);
}
