<?php
// ============================================================
//  c_payment.php — Customer Payment Submission
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role('CUSTOMER');
require_once 'db.php';
require_once 'loyalty.php';
require_once 'audit.php';

$userId  = $_SESSION['user_id'];
$message = '';
$msgType = '';

// Flash message surfaced after the post/redirect below
if (!empty($_SESSION['payment_flash'])) {
    $message = $_SESSION['payment_flash']['message'];
    $msgType = $_SESSION['payment_flash']['type'];
    unset($_SESSION['payment_flash']);
}

// ?order_id=PRE-xxx lets c_orderstatus.php send the customer here to
// resubmit/submit payment for a specific order, pre-selected below.
$linkOrderId = trim($_GET['order_id'] ?? '');

function generate_payment_id(): string {
    return 'PAY-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

// ── Load unpaid orders for dropdown ───────────────────────────
// Excludes:
//   • Orders with a PENDING or VALID payment already
//   • Orders with any print file still RECEIVED (price not confirmed)
// Amount is computed from source tables (items + print files) so it
// always reflects the true combined total, not just estimated_total.
$stmt = $conn->prepare(
    "SELECT
         o.order_id   AS id,
         o.order_type AS rec_type,
         COALESCE(
             (SELECT SUM(oi.quantity * oi.unit_price)
              FROM order_items oi WHERE oi.order_id = o.order_id), 0
         ) +
         COALESCE(
             (SELECT SUM(pf.estimated_price)
              FROM print_files pf
              WHERE pf.order_id = o.order_id AND pf.file_status != 'REJECTED'), 0
         ) AS amount
     FROM orders o
     WHERE o.user_id = ?
       AND o.order_status NOT IN ('CANCELLED','COLLECTED')
       AND o.order_id NOT IN (
           SELECT p.order_id FROM payments p
           WHERE p.verification_status IN ('VALID','PENDING')
       )
       AND o.order_id NOT IN (
           SELECT DISTINCT pf.order_id FROM print_files pf
           WHERE pf.file_status = 'RECEIVED'
             AND pf.order_id IS NOT NULL
       )
     ORDER BY o.order_type, o.order_date DESC"
);
$stmt->bind_param('s', $userId);
$stmt->execute();
$unpaidAll = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$unpaidOrders    = array_filter($unpaidAll, fn($r) => $r['rec_type'] === 'ORDER');
$unpaidPreorders = array_filter($unpaidAll, fn($r) => $r['rec_type'] === 'PREORDER');

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedId = trim($_POST['selected_id'] ?? '');
    $method     = trim($_POST['method']       ?? '');
    $amount     = trim($_POST['amount']       ?? '');
    $reference  = trim($_POST['reference']    ?? '') ?: null;
    $payDate    = trim($_POST['pay_date']     ?? '');

    $errors = [];

    if (!$selectedId)
        $errors[] = 'Please select an order or pre-order.';
    if (!in_array($method, ['CASH','TRANSFER','OTHER']))
        $errors[] = 'Please select a valid payment method.';
    if (!is_numeric($amount) || (float)$amount <= 0)
        $errors[] = 'Invalid amount.';
    if (!$payDate)
        $errors[] = 'Please enter a payment date.';

    // Verify order belongs to this user and fetch its type
    $orderType   = '';
    $amountKnown = false;
    if ($selectedId) {
        $stmt = $conn->prepare(
            "SELECT order_type FROM orders WHERE order_id = ? AND user_id = ? LIMIT 1"
        );
        $stmt->bind_param('ss', $selectedId, $userId);
        $stmt->execute();
        $orderRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$orderRow) {
            $errors[] = 'Invalid order selected.';
        } else {
            $orderType = $orderRow['order_type'];

            // ── Recompute the authoritative order amount server-side ──
            // Never trust the posted `amount` for anything requiring a real
            // dollar value (the readonly styling on the field is JS-only and
            // trivially bypassed). This mirrors the "unpaid orders" query
            // above so an order with a known, confirmed amount always uses
            // that figure — the posted amount is only relevant for the rare
            // "amount TBC by staff" pre-order case (see below), where loyalty
            // redemption is disabled since there's nothing to validate it against.
            $amtStmt = $conn->prepare(
                "SELECT
                     COALESCE((SELECT SUM(oi.quantity * oi.unit_price) FROM order_items oi WHERE oi.order_id = ?), 0)
                   + COALESCE((SELECT SUM(pf.estimated_price) FROM print_files pf WHERE pf.order_id = ? AND pf.file_status != 'REJECTED'), 0)
                     AS amount"
            );
            $amtStmt->bind_param('ss', $selectedId, $selectedId);
            $amtStmt->execute();
            $serverAmount = (float)($amtStmt->get_result()->fetch_assoc()['amount'] ?? 0);
            $amtStmt->close();

            if ($serverAmount > 0) {
                $amountKnown = true;
                $amount      = (string) $serverAmount;
            }
        }
    }

    // Cash is not accepted for pre-orders (no staff present to collect)
    if ($method === 'CASH' && $orderType === 'PREORDER') {
        $errors[] = 'Cash is not accepted for pre-orders. Please use Bank Transfer or E-Wallet.';
    }

    // Double-payment guard — belt AND suspenders (query already excludes these,
    // but a race condition or direct POST could bypass the dropdown)
    if ($selectedId && empty($errors)) {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS cnt FROM payments
             WHERE order_id = ? AND verification_status IN ('VALID','PENDING')"
        );
        $stmt->bind_param('s', $selectedId);
        $stmt->execute();
        $existing = (int)$stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        if ($existing > 0) {
            $errors[] = 'A payment is already pending or verified for this order.';
        }
    }

    // Handle proof upload — required for TRANSFER and OTHER
    $proofPath = null;
    if (in_array($method, ['TRANSFER','OTHER'])) {
        if (!isset($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Payment proof is required for ' . ($method === 'TRANSFER' ? 'Bank Transfer' : 'E-Wallet') . ' payments. Please upload a receipt or screenshot.';
        } else {
            $allowed = ['image/jpeg','image/png','application/pdf'];
            $mime    = mime_content_type($_FILES['proof']['tmp_name']);
            $size    = $_FILES['proof']['size'];

            if (!in_array($mime, $allowed)) {
                $errors[] = 'Proof file must be JPG, PNG, or PDF.';
            } elseif ($size > 5 * 1024 * 1024) {
                $errors[] = 'Proof file must be under 5MB.';
            } else {
                $uploadDir = 'uploads/payment_proofs/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $ext      = pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION);
                $filename = 'proof_' . uniqid() . '.' . $ext;
                move_uploaded_file($_FILES['proof']['tmp_name'], $uploadDir . $filename);
                $proofPath = $uploadDir . $filename;
            }
        }
    }

    if (empty($errors)) {
        $paymentId = generate_payment_id();

        // ── Loyalty point redemption ───────────────────────────
        // $amount is now the server-recomputed, authoritative order total
        // (see above) for every order except the rare "amount TBC by staff"
        // case — redemption is disabled there since there's no confirmed
        // figure to validate a redeemed-points discount against.
        $pointsRedeemedRaw = $amountKnown ? max(0, (int)($_POST['points_redeemed'] ?? 0)) : 0;
        $pointsRedeemed    = 0;
        $pointsDiscount    = 0.0;
        $finalAmount       = (float)$amount;

        if ($pointsRedeemedRaw > 0) {
            $userBalance    = get_loyalty_balance($conn, $userId);
            $maxRedeemable  = max_redeemable_points($userBalance, (float)$amount);
            $pointsRedeemed = min($pointsRedeemedRaw, $maxRedeemable);
            if ($pointsRedeemed > 0) {
                $pointsDiscount = round($pointsRedeemed / 100, 2);
                $finalAmount    = round((float)$amount - $pointsDiscount, 2);
            }
        }

        $conn->begin_transaction();
        $submitted = false;
        try {
            $stmt = $conn->prepare(
                "INSERT INTO payments
                    (payment_id, order_id, payment_method, amount,
                     record_date, verification_status, reference_number, proof_path,
                     points_redeemed, points_discount)
                 VALUES (?, ?, ?, ?, ?, 'PENDING', ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                'sssdsssid',
                $paymentId, $selectedId, $method, $finalAmount,
                $payDate, $reference, $proofPath, $pointsRedeemed, $pointsDiscount
            );
            $stmt->execute();
            $stmt->close();

            if ($pointsRedeemed > 0) {
                $ok = redeem_loyalty_points($conn, $userId, $pointsRedeemed, $selectedId, $paymentId, "Redeemed at payment $paymentId");
                if (!$ok) {
                    throw new Exception('Your points balance changed — please try again.');
                }
            }

            $conn->commit();
            $submitted = true;

            log_audit(
                $conn, 'PAYMENT_SUBMITTED', 'payment', $paymentId,
                "RM " . number_format($finalAmount, 2) . " ($method) for order $selectedId"
                . ($pointsRedeemed > 0 ? ", redeemed $pointsRedeemed points" : "")
            );
        } catch (Throwable $e) {
            $conn->rollback();
            $message = $e->getMessage() ?: 'Something went wrong submitting your payment. Please try again.';
            $msgType = 'error';
        }

        if ($submitted) {
            $_SESSION['payment_flash'] = [
                'message' => "Payment record <strong>$paymentId</strong> submitted successfully"
                           . ($pointsRedeemed > 0 ? " (redeemed $pointsRedeemed points for RM " . number_format($pointsDiscount, 2) . " off)" : "")
                           . ". Staff will verify your payment shortly.",
                'type'    => 'success',
            ];

            header('Location: c_payment.php');
            exit;
        }
    } else {
        $message = implode('<br>', $errors);
        $msgType = 'error';
    }
}

// ── Loyalty points balance (for the redeem checkbox) ───────────
$userPoints = get_loyalty_balance($conn, $userId);

// ── Payment history ───────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT
         p.payment_id, p.payment_method, p.amount,
         p.record_date, p.verification_status, p.rejection_reason,
         p.reference_number, p.order_id, o.order_type,
         p.points_redeemed, p.points_discount
     FROM payments p
     JOIN orders o ON p.order_id = o.order_id
     WHERE o.user_id = ?
     ORDER BY p.record_date DESC
     LIMIT 10"
);
$stmt->bind_param('s', $userId);
$stmt->execute();
$paymentHistory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function verificationBadge(string $status): string {
    $map = [
        'VALID'   => ['var(--success)','var(--success-bg)','Verified'],
        'INVALID' => ['var(--danger)','var(--danger-bg)','Rejected'],
        'PENDING' => ['var(--warning)','var(--warning-bg)','Pending Review'],
    ];
    [$color, $bg, $label] = $map[$status] ?? ['#888','#f3f4f6', $status];
    return "<span style='background:$bg;color:$color;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;'>$label</span>";
}

function methodLabel(string $method): string {
    return match($method) {
        'CASH'     => 'Cash',
        'TRANSFER' => 'Bank Transfer',
        'OTHER'    => 'E-Wallet',
        default    => $method,
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus - Payment Record</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/tokens.css">
    <script src="assets/js/theme.js"></script>
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <style>
        :root { --primary:#A83535;--secondary:#F4A261;--accent:#F1EDE8;--background:#FAFAFA;--text-primary:#2E2E2E;--text-secondary:#707070;--border:#E0E0E0;--white:#FFFFFF;--sidebar-width:260px;--card-shadow:0 4px 12px rgba(0,0,0,0.05); }
        * { margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',system-ui,sans-serif; }
        body { background-color:var(--background);color:var(--text-primary);min-height:100vh;display:flex; }
        .main-content { flex-grow:1;margin-left:var(--sidebar-width);min-height:100vh;display:flex;flex-direction:column; }
        .top-header { background-color:var(--white);padding:20px 30px;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:10; }
        .page-title { font-size:24px;font-weight:700; }
        .page-subtitle { font-size:14px;color:var(--text-secondary);margin-top:4px; }
        .content-container { padding:30px;flex-grow:1;display:flex;flex-direction:column;gap:28px; }
        .alert { padding:13px 18px;border-radius:8px;font-size:14px;display:flex;align-items:flex-start;gap:10px;line-height:1.6; }
        .alert-success { background:var(--success-bg);color:var(--success);border:1px solid var(--success-border); }
        .alert-error { background:var(--danger-bg);color:var(--danger);border:1px solid var(--danger); }
        .card { background-color:var(--white);border-radius:12px;overflow:hidden;box-shadow:var(--card-shadow);border:1px solid var(--border); }
        .card-header { padding:20px 28px;border-bottom:1px solid var(--border);background-color:var(--primary-tint-subtle); }
        .card-title { font-size:17px;font-weight:700;color:var(--primary);display:flex;align-items:center;gap:10px; }
        .card-desc { font-size:13px;color:var(--text-secondary);margin-top:6px; }
        .card-body { padding:28px; }
        .pay-grid { display:grid;grid-template-columns:repeat(2,1fr);gap:20px;margin-bottom:20px; }
        .full { grid-column:span 2; }
        .form-label { display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:var(--text-primary); }
        .form-label span { font-weight:400;color:var(--text-secondary);font-size:13px; }
        .form-label .req { color:var(--primary);font-weight:700; }
        .form-select,.form-input { width:100%;padding:12px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;background-color:var(--accent);color:var(--text-primary);transition:all 0.2s; }
        .form-select:focus,.form-input:focus { outline:none;border-color:var(--primary);box-shadow:0 0 0 3px var(--primary-tint-light);background:var(--white); }
        .form-input[readonly] { background:var(--primary-tint-subtle);color:var(--text-secondary);cursor:not-allowed; }
        .file-zone { border:2px dashed var(--border);border-radius:8px;padding:28px;text-align:center;background:var(--primary-tint-subtle);cursor:pointer;transition:all 0.2s;position:relative; }
        .file-zone:hover,.file-zone.drag { border-color:var(--primary);background:var(--primary-tint-subtle); }
        .file-zone.required-error { border-color:var(--danger);background:rgba(239,68,68,0.03); }
        .file-zone input[type=file] { position:absolute;inset:0;opacity:0;cursor:pointer; }
        .file-zone i { font-size:28px;color:var(--primary);opacity:0.6;margin-bottom:8px;display:block; }
        .file-zone p { font-size:14px;color:var(--text-secondary); }
        .file-zone small { font-size:12px;color:var(--text-secondary); }
        .file-selected { margin-top:12px;padding:12px 16px;background:var(--primary-tint-subtle);border-radius:8px;border:1px solid var(--border);display:none;align-items:center;justify-content:space-between; }
        .file-selected.show { display:flex; }
        .file-selected-name { font-size:13px;font-weight:600;color:var(--text-primary); }
        .file-clear { background:none;border:none;color:var(--text-secondary);cursor:pointer;font-size:16px;padding:2px 6px;border-radius:4px; }
        .file-clear:hover { color:var(--danger); }
        .proof-section { display:none; }
        .proof-section.show { display:block; }
        .proof-required-note { display:none;margin-top:6px;font-size:12px;color:var(--primary);font-weight:600; }
        .proof-required-note.show { display:flex;align-items:center;gap:5px; }
        /* Cash-not-available notice */
        .cash-notice { display:none;margin-top:6px;padding:9px 13px;background:var(--warning-bg);border:1px solid #fde68a;border-radius:8px;font-size:12px;color:var(--warning);align-items:center;gap:7px; }
        .cash-notice.show { display:flex; }
        .submit-btn { padding:13px 32px;background-color:var(--primary);color:var(--on-primary);border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;transition:background-color 0.2s;display:inline-flex;align-items:center;gap:10px; }
        .submit-btn:hover { background-color:var(--primary-dark); }
        .history-table { width:100%;border-collapse:collapse; }
        .history-table thead { background:var(--primary-tint-subtle);border-bottom:2px solid var(--border); }
        .history-table th { padding:12px 18px;text-align:left;font-size:12px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.4px;white-space:nowrap; }
        .history-table tbody tr { border-bottom:1px solid var(--border);transition:background 0.15s; }
        .history-table tbody tr:last-child { border-bottom:none; }
        .history-table tbody tr:hover { background:var(--primary-tint-subtle); }
        .history-table td { padding:14px 18px;font-size:13px;color:var(--text-primary);vertical-align:middle; }
        .pay-ref { font-weight:700;color:var(--primary);font-family:monospace;font-size:12px; }
        .empty-history { text-align:center;padding:30px;color:var(--text-secondary);font-size:14px; }
        .page-footer { text-align:center;padding:22px;color:var(--text-secondary);font-size:13px;border-top:1px solid var(--border);background-color:var(--white); }
        .footer-links { margin-top:8px; }
        .footer-links a { color:var(--primary);text-decoration:none;margin:0 10px; }
        @media (max-width:768px) {
            .pay-grid { grid-template-columns:1fr; }
            .full { grid-column:span 1; }
        }
    </style>
</head>
<body>

<?php include 'c_sidebar.php'; ?>

<main class="main-content">
    <header class="top-header">
        <h1 class="page-title">Payment Record</h1>
        <p class="page-subtitle">Submit payment proof for your orders and pre-orders</p>
    </header>

    <div class="content-container">

        <?php if ($message): ?>
        <div class="alert alert-<?= $msgType ?>">
            <i class="fas <?= $msgType==='success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>" style="flex-shrink:0;margin-top:2px;"></i>
            <div><?= $message ?></div>
        </div>
        <?php endif; ?>

        <!-- Submission Form -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-file-invoice-dollar"></i> Submit Payment Record</div>
                <div class="card-desc">Record your payment here. Staff will verify and update your order status.</div>
            </div>
            <div class="card-body">
                <form method="POST" action="c_payment.php" enctype="multipart/form-data" id="paymentForm">

                    <!-- Tracks the selected order type for JS method filtering -->
                    <input type="hidden" name="rec_type" id="recTypeInput" value="">

                    <div class="pay-grid">

                        <!-- Order / Pre-order selector -->
                        <div class="full">
                            <label class="form-label">Order / Pre-order <span>(select the one you are paying for)</span></label>
                            <select name="selected_id" class="form-select" id="recordSelect"
                                    required onchange="updateAmount(this)">
                                <option value="">-- Select record --</option>
                                <?php if (!empty($unpaidOrders)): ?>
                                <optgroup label="Orders">
                                    <?php foreach ($unpaidOrders as $r):
                                        $amt = isset($r['amount']) ? (float)$r['amount'] : null;
                                    ?>
                                    <option value="<?= htmlspecialchars($r['id']) ?>"
                                            data-type="order"
                                            data-amount="<?= $amt > 0 ? $amt : '' ?>"
                                            <?= ($linkOrderId && $linkOrderId === $r['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r['id']) ?>
                                        <?= $amt > 0 ? '— RM ' . number_format($amt, 2) : '(amount pending)' ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endif; ?>
                                <?php if (!empty($unpaidPreorders)): ?>
                                <optgroup label="Pre-orders / Reservations">
                                    <?php foreach ($unpaidPreorders as $r):
                                        $amt = isset($r['amount']) ? (float)$r['amount'] : null;
                                    ?>
                                    <option value="<?= htmlspecialchars($r['id']) ?>"
                                            data-type="preorder"
                                            data-amount="<?= $amt > 0 ? $amt : '' ?>"
                                            <?= ($linkOrderId && $linkOrderId === $r['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r['id']) ?>
                                        <?= $amt > 0 ? '— RM ' . number_format($amt, 2) : '(amount TBC by staff)' ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endif; ?>
                                <?php if (empty($unpaidAll)): ?>
                                <option disabled>No pending orders found</option>
                                <?php endif; ?>
                            </select>
                            <?php if ($linkOrderId): ?>
                            <div style="margin-top:7px;padding:8px 13px;background:var(--warning-bg);border:1px solid #fde68a;
                                        border-radius:7px;font-size:12px;color:var(--warning);display:flex;gap:7px;align-items:center;">
                                <i class="fas fa-info-circle"></i>
                                You're submitting payment for order
                                <strong><?= htmlspecialchars($linkOrderId) ?></strong>.
                            </div>
                            <?php endif; ?>
                            <div style="margin-top:8px;padding:8px 12px;background:var(--warning-bg);
                                        border:1px solid #fde68a;border-radius:7px;
                                        font-size:12px;color:var(--warning);display:flex;gap:7px;align-items:flex-start;">
                                <i class="fas fa-info-circle" style="flex-shrink:0;margin-top:1px;"></i>
                                <span>
                                    Orders with print files awaiting staff review won't appear here until
                                    the file is approved and the price is confirmed.
                                    <a href="c_orderstatus.php" style="color:var(--warning);font-weight:700;">
                                        Check order status &rarr;
                                    </a>
                                </span>
                            </div>
                        </div>

                        <!-- Payment method -->
                        <div>
                            <label class="form-label">Payment Method</label>
                            <select name="method" class="form-select" id="methodSelect"
                                    required onchange="toggleProof(this.value)">
                                <option value="">-- Select method --</option>
                                <option value="CASH"     id="optCash">Cash</option>
                                <option value="TRANSFER" id="optTransfer">Bank Transfer</option>
                                <option value="OTHER"    id="optOther">E-Wallet / Other</option>
                            </select>
                            <!-- Shown when pre-order is selected and Cash is chosen -->
                            <div class="cash-notice" id="cashNotice">
                                <i class="fas fa-exclamation-triangle"></i>
                                Cash is not accepted for pre-orders. Please use Bank Transfer or E-Wallet.
                            </div>
                        </div>

                        <!-- Amount -->
                        <div>
                            <label class="form-label">
                                Amount (RM)
                                <span id="amountHint">(auto-filled from order)</span>
                            </label>
                            <input type="number" name="amount" id="amountInput" class="form-input"
                                   placeholder="0.00" step="0.01" min="0.01" required>
                        </div>

                        <!-- Loyalty points redemption -->
                        <div class="full" id="loyaltySection" style="display:<?= $userPoints > 0 ? 'block' : 'none' ?>;">
                            <label style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:rgba(76,175,80,0.05);border:1.5px solid rgba(76,175,80,0.25);border-radius:8px;cursor:pointer;">
                                <input type="checkbox" id="redeemPointsCheckbox" style="width:17px;height:17px;accent-color:var(--success);">
                                <span style="font-size:13px;color:var(--text-primary);">
                                    <strong>Redeem loyalty points</strong>
                                    <span style="color:var(--text-secondary);">— you have <strong id="pointsBalanceLabel"><?= $userPoints ?></strong> point<?= $userPoints === 1 ? '' : 's' ?> (worth RM <?= number_format($userPoints / 100, 2) ?>)</span>
                                </span>
                            </label>
                            <div id="loyaltyDiscountNote" style="display:none;margin-top:7px;padding:8px 13px;background:var(--success-bg);border:1px solid var(--success-border);border-radius:7px;font-size:12px;color:var(--success);font-weight:600;"></div>
                            <input type="hidden" name="points_redeemed" id="pointsRedeemedInput" value="0">
                        </div>

                        <!-- Reference -->
                        <div>
                            <label class="form-label">
                                Reference Number
                                <span id="refHint">(optional)</span>
                            </label>
                            <input type="text" name="reference" id="referenceInput"
                                   class="form-input" placeholder="e.g. TRX-123456789">
                        </div>

                        <!-- Date -->
                        <div>
                            <label class="form-label">Payment Date</label>
                            <input type="date" name="pay_date" class="form-input"
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <!-- Proof upload — required for TRANSFER/OTHER -->
                        <div class="full proof-section" id="proofSection">
                            <label class="form-label">
                                Payment Proof
                                <span class="req" id="proofRequiredLabel">*</span>
                                <span id="proofHint">Required for Bank Transfer / E-Wallet</span>
                            </label>
                            <div class="file-zone" id="fileZone">
                                <input type="file" name="proof" id="proofFile"
                                       accept=".jpg,.jpeg,.png,.pdf"
                                       onchange="showFile(this)">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Click or drag to upload screenshot / receipt</p>
                                <small>JPG, PNG or PDF — max 5 MB</small>
                            </div>
                            <div class="proof-required-note" id="proofRequiredNote">
                                <i class="fas fa-exclamation-circle"></i>
                                Please upload proof before submitting.
                            </div>
                            <div class="file-selected" id="fileSelected">
                                <span class="file-selected-name" id="fileSelectedName"></span>
                                <button type="button" class="file-clear" onclick="clearProofFile()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>

                    </div><!-- /.pay-grid -->

                    <button type="submit" class="submit-btn" id="submitBtn">
                        <i class="fas fa-paper-plane"></i> Submit Payment Record
                    </button>

                </form>
            </div>
        </div>

        <!-- Payment History -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-history"></i> Recent Payment Records</div>
            </div>
            <div style="overflow-x:auto;">
                <?php if (empty($paymentHistory)): ?>
                    <div class="empty-history">No payment records yet.</div>
                <?php else: ?>
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Date</th>
                            <th>Linked To</th>
                            <th>Method</th>
                            <th>Amount</th>
                            <th>Points</th>
                            <th>Reference</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paymentHistory as $pay): ?>
                        <tr>
                            <td><span class="pay-ref"><?= htmlspecialchars($pay['payment_id']) ?></span></td>
                            <td><?= date('d M Y', strtotime($pay['record_date'])) ?></td>
                            <td style="font-family:monospace;font-size:12px;">
                                <?= htmlspecialchars($pay['order_id']) ?>
                                <span style="font-size:11px;color:var(--text-secondary);margin-left:4px;">
                                    (<?= $pay['order_type'] === 'PREORDER' ? 'Reservation' : 'Walk-in' ?>)
                                </span>
                            </td>
                            <td><?= methodLabel($pay['payment_method']) ?></td>
                            <td style="font-weight:600;">RM <?= number_format($pay['amount'],2) ?></td>
                            <td style="font-size:12px;">
                                <?php if ((int)$pay['points_redeemed'] > 0): ?>
                                    <span style="color:var(--success);font-weight:600;">−<?= (int)$pay['points_redeemed'] ?> pts</span>
                                    <div style="color:var(--text-secondary);">(RM <?= number_format($pay['points_discount'], 2) ?> off)</div>
                                <?php elseif ($pay['verification_status'] === 'VALID'): ?>
                                    <span style="color:var(--success);">+<?= (int)floor((float)$pay['amount']) ?> pts</span>
                                <?php else: ?>
                                    <span style="color:var(--text-secondary);">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="color:var(--text-secondary);"><?= htmlspecialchars($pay['reference_number'] ?? '—') ?></td>
                            <td>
                                <?= verificationBadge($pay['verification_status']) ?>
                                <?php if ($pay['verification_status'] === 'INVALID' && !empty($pay['rejection_reason'])): ?>
                                <div style="font-size:11px;color:var(--danger);margin-top:4px;max-width:220px;line-height:1.4;">
                                    <i class="fas fa-info-circle"></i> <?= htmlspecialchars($pay['rejection_reason']) ?>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /.content-container -->

    <footer class="page-footer">
        &copy; <?= date('Y') ?> StationaryPlus &mdash; Stationery &amp; Printing Management System
    </footer>
</main>

<script>
// If arriving via a resubmit/submit-payment link, the matching option is
// already marked `selected` server-side — run the same onchange logic once
// on load so the amount/method restrictions populate without a manual reselect.
<?php if ($linkOrderId): ?>
document.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('recordSelect');
    if (sel && sel.value) updateAmount(sel);
});
<?php endif; ?>

// ── Order selector: auto-fill amount, filter methods ─────────
function updateAmount(select) {
    const opt    = select.options[select.selectedIndex];
    const amount = opt.getAttribute('data-amount');
    const type   = opt.getAttribute('data-type') || '';
    const input  = document.getElementById('amountInput');

    // Store type so form submit validation can use it
    document.getElementById('recTypeInput').value = type;

    // Auto-fill amount if known
    if (amount !== null && amount !== '' && !isNaN(parseFloat(amount))) {
        input.value            = parseFloat(amount).toFixed(2);
        input.readOnly         = true;
        input.style.background = 'rgba(168,53,53,0.05)';
        input.style.color      = 'var(--text-secondary)';
        input.style.cursor     = 'not-allowed';
        document.getElementById('amountHint').textContent = '(auto-filled from order)';
    } else {
        input.value            = '';
        input.readOnly         = false;
        input.style.background = '';
        input.style.color      = '';
        input.style.cursor     = '';
        document.getElementById('amountHint').textContent =
            type === 'preorder' ? '(enter amount from your receipt)' : '(enter amount)';
    }

    // Restrict payment methods for pre-orders: no cash
    const cashOpt     = document.getElementById('optCash');
    const methodSelect = document.getElementById('methodSelect');

    if (type === 'preorder') {
        cashOpt.disabled = true;
        cashOpt.textContent = 'Cash (not available for pre-orders)';
        // If cash was already selected, reset
        if (methodSelect.value === 'CASH') {
            methodSelect.value = '';
            toggleProof('');
        }
    } else {
        cashOpt.disabled = false;
        cashOpt.textContent = 'Cash';
    }

    // Reset method if no order selected
    if (!type) {
        cashOpt.disabled = false;
        cashOpt.textContent = 'Cash';
    }

    computeRedemption();
}

// ── Loyalty points redemption ──────────────────────────────────
const USER_POINTS = <?= (int)$userPoints ?>;

function computeRedemption() {
    const checkbox   = document.getElementById('redeemPointsCheckbox');
    const hidden     = document.getElementById('pointsRedeemedInput');
    const note       = document.getElementById('loyaltyDiscountNote');
    const amountInput = document.getElementById('amountInput');
    if (!checkbox || !hidden || !note || !amountInput) return;

    // Redemption requires a server-confirmed order amount (auto-filled +
    // readonly) — the server independently enforces this, but disabling the
    // checkbox client-side too avoids a confusing "it didn't work" surprise
    // for the rare "amount TBC by staff" pre-order case.
    const amountConfirmed = amountInput.readOnly;
    checkbox.disabled = !amountConfirmed;

    const amount = parseFloat(amountInput.value) || 0;

    if (!checkbox.checked || !amountConfirmed || amount <= 0 || USER_POINTS <= 0) {
        hidden.value = 0;
        note.style.display = 'none';
        if (!amountConfirmed && checkbox.checked) checkbox.checked = false;
        return;
    }

    // Convert to integer cents before subtracting — avoids float imprecision
    // (e.g. (0.03 - 0.01) * 100 === 1.9999999999999998 in IEEE-754).
    const amountCents   = Math.round(amount * 100);
    const maxRedeemable = Math.max(0, Math.min(USER_POINTS, amountCents - 1));
    hidden.value = maxRedeemable;

    if (maxRedeemable > 0) {
        const discount = maxRedeemable / 100;
        const finalAmt = (amount - discount).toFixed(2);
        note.textContent = `Redeeming ${maxRedeemable} points = − RM ${discount.toFixed(2)}. You pay RM ${finalAmt}.`;
        note.style.display = 'block';
    } else {
        note.style.display = 'none';
    }
}

document.getElementById('amountInput').addEventListener('input', computeRedemption);
const redeemCheckboxEl = document.getElementById('redeemPointsCheckbox');
if (redeemCheckboxEl) redeemCheckboxEl.addEventListener('change', computeRedemption);
document.addEventListener('DOMContentLoaded', computeRedemption);

// ── Payment method: show/hide proof section ───────────────────
function toggleProof(method) {
    const section   = document.getElementById('proofSection');
    const notice    = document.getElementById('cashNotice');
    const refHint   = document.getElementById('refHint');
    const isPreorder = document.getElementById('recTypeInput').value === 'preorder';

    const needsProof = method === 'TRANSFER' || method === 'OTHER';
    section.classList.toggle('show', needsProof);

    // Cash + preorder warning
    notice.classList.toggle('show', method === 'CASH' && isPreorder);

    // Update reference hint
    if (method === 'TRANSFER') {
        refHint.textContent = '(transaction/reference number)';
    } else if (method === 'OTHER') {
        refHint.textContent = '(transaction ID)';
    } else {
        refHint.textContent = '(optional)';
    }

    // Clear any error state when method changes
    document.getElementById('fileZone').classList.remove('required-error');
    document.getElementById('proofRequiredNote').classList.remove('show');
}

// ── Proof file display ────────────────────────────────────────
function showFile(input) {
    if (input.files && input.files[0]) {
        document.getElementById('fileSelectedName').textContent = input.files[0].name;
        document.getElementById('fileSelected').classList.add('show');
        document.getElementById('fileZone').classList.remove('required-error');
        document.getElementById('proofRequiredNote').classList.remove('show');
    }
}

function clearProofFile() {
    document.getElementById('proofFile').value = '';
    document.getElementById('fileSelected').classList.remove('show');
}

// ── Form submit: client-side validation ───────────────────────
document.getElementById('paymentForm').addEventListener('submit', function (e) {
    const method = document.getElementById('methodSelect').value;
    const needsProof = method === 'TRANSFER' || method === 'OTHER';

    if (needsProof) {
        const proofFile = document.getElementById('proofFile');
        if (!proofFile.files || proofFile.files.length === 0) {
            e.preventDefault();
            document.getElementById('fileZone').classList.add('required-error');
            document.getElementById('proofRequiredNote').classList.add('show');
            document.getElementById('proofSection').scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
    }
});

// ── Drag and drop ─────────────────────────────────────────────
const zone = document.getElementById('fileZone');
if (zone) {
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag'));
    zone.addEventListener('drop', e => {
        e.preventDefault(); zone.classList.remove('drag');
        const input = document.getElementById('proofFile');
        input.files = e.dataTransfer.files;
        showFile(input);
    });
}
</script>

</body>
</html>