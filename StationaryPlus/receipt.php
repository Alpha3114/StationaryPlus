<?php
// ============================================================
//  receipt.php — Printable Order Receipt
//
//  Accessible by:
//    CUSTOMER — can only view their own orders
//    STAFF / ADMIN — can view any order
//
//  GET: ?id=ORDER_ID
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_once 'db.php';

$role    = $_SESSION['user_role'] ?? '';
$userId  = $_SESSION['user_id']   ?? '';
$orderId = trim($_GET['id']       ?? '');

if (!$orderId) {
    http_response_code(400);
    die('No order ID provided.');
}

// ── Auth & order fetch ────────────────────────────────────────
if (in_array($role, ['STAFF', 'ADMIN'])) {
    $stmt = $conn->prepare(
        "SELECT o.order_id, o.order_type, o.order_date, o.order_status,
                o.estimated_total, o.notes, o.cancellation_reason,
                COALESCE(u.name,         'Walk-in Guest')  AS customer_name,
                COALESCE(u.email,        '')               AS customer_email,
                COALESCE(u.phone_number, '')               AS customer_phone,
                b.branch_name, b.address AS branch_address, b.phone_number AS branch_phone
         FROM orders o
         LEFT JOIN users    u ON o.user_id   = u.user_id
         LEFT JOIN branches b ON o.branch_id = b.branch_id
         WHERE o.order_id = ? LIMIT 1"
    );
    $stmt->bind_param('s', $orderId);
} else {
    // Customer: must own the order
    require_role('CUSTOMER');
    $stmt = $conn->prepare(
        "SELECT o.order_id, o.order_type, o.order_date, o.order_status,
                o.estimated_total, o.notes, o.cancellation_reason,
                u.name AS customer_name, u.email AS customer_email,
                u.phone_number AS customer_phone,
                b.branch_name, b.address AS branch_address, b.phone_number AS branch_phone
         FROM orders o
         JOIN users    u ON o.user_id   = u.user_id
         LEFT JOIN branches b ON o.branch_id = b.branch_id
         WHERE o.order_id = ? AND o.user_id = ? LIMIT 1"
    );
    $stmt->bind_param('ss', $orderId, $userId);
}
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    http_response_code(404);
    die('Order not found or access denied.');
}

// ── Stationery items ──────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT p.product_name, oi.quantity, oi.unit_price,
            (oi.quantity * oi.unit_price) AS subtotal
     FROM order_items oi
     JOIN products p ON oi.product_id = p.product_id
     WHERE oi.order_id = ?"
);
$stmt->bind_param('s', $orderId);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Print files ───────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT file_name, file_type, total_pages, color_pages, bw_pages,
            paper_size, paper_type, binding_type, copies, estimated_price, file_status
     FROM print_files WHERE order_id = ? ORDER BY upload_date ASC"
);
$stmt->bind_param('s', $orderId);
$stmt->execute();
$printFiles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Payment ───────────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT payment_id, payment_method, amount, record_date,
            verification_status, reference_number
     FROM payments WHERE order_id = ?
     ORDER BY record_date DESC LIMIT 1"
);
$stmt->bind_param('s', $orderId);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Computed totals ───────────────────────────────────────────
$itemsTotal = array_reduce($items,      fn($s, $i) => $s + (float)$i['subtotal'],         0.0);
$printTotal = array_reduce($printFiles, fn($s, $f) => $s + ($f['file_status'] === 'REJECTED' ? 0 : (float)$f['estimated_price']), 0.0);
$grandTotal = $itemsTotal + $printTotal;

// ── Helpers ───────────────────────────────────────────────────
function esc(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function bindingLabel(string $b): string {
    return match($b) { 'STAPLE' => 'Staple', 'SPIRAL' => 'Spiral', default => 'None' };
}

function methodLabel(string $m): string {
    return match($m) { 'TRANSFER' => 'Bank Transfer', 'OTHER' => 'E-Wallet / Other', default => 'Cash' };
}

function payStatusLabel(string $s): string {
    return match($s) { 'VALID' => '✓ Verified', 'INVALID' => '✗ Rejected', default => '⏳ Pending Verification' };
}

$typeLabel   = $order['order_type'] === 'PREORDER' ? 'Reservation' : 'Walk-in Sale';
$printedAt   = date('d M Y, H:i');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt — <?= esc($orderId) ?> | StationaryPlus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/tokens.css">
    <style>
        :root {
            --primary: #A83535;
            --text: #2E2E2E;
            --muted: #707070;
            --border: #E0E0E0;
            --light: #F9F9F9;
        }

        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', system-ui, sans-serif; }

        body {
            background: #f0f0f0;
            color: var(--text);
            min-height: 100vh;
            padding: 30px 20px;
        }

        /* ── Screen: toolbar ── */
        .toolbar {
            max-width: 740px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .btn {
            padding: 9px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
        }
        .btn-print { background: var(--primary); color: white; }
        .btn-print:hover { background: #8b2a2a; }
        .btn-back  { background: white; color: var(--text); border: 1.5px solid var(--border); }
        .btn-back:hover { border-color: var(--primary); color: var(--primary); }
        .toolbar-note { margin-left: auto; font-size: 12px; color: var(--muted); }

        /* ── Receipt card ── */
        .receipt {
            max-width: 740px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        /* Header */
        .receipt-header {
            background: var(--primary);
            color: white;
            padding: 28px 36px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .brand-name { font-size: 22px; font-weight: 800; letter-spacing: 0.5px; }
        .brand-sub  { font-size: 13px; opacity: 0.85; margin-top: 4px; line-height: 1.6; }
        .receipt-title { text-align: right; }
        .receipt-title h2 { font-size: 18px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
        .receipt-title p  { font-size: 12px; opacity: 0.85; margin-top: 4px; }

        /* Body sections */
        .receipt-body { padding: 0 36px 32px; }

        .section {
            padding: 20px 0;
            border-bottom: 1px solid var(--border);
        }
        .section:last-child { border-bottom: none; }

        .section-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: var(--primary);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        /* Info grid */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 24px;
        }
        .info-item .label { font-size: 11px; color: var(--muted); margin-bottom: 2px; text-transform: uppercase; letter-spacing: 0.4px; }
        .info-item .value { font-size: 13px; font-weight: 600; color: var(--text); }
        .info-item .value.mono { font-family: monospace; font-size: 14px; }

        /* Order status badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        /* Items table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .items-table thead {
            background: var(--light);
            border-bottom: 2px solid var(--border);
        }
        .items-table th {
            padding: 9px 12px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .items-table th:last-child, .items-table td:last-child { text-align: right; }
        .items-table tbody tr { border-bottom: 1px solid var(--border); }
        .items-table tbody tr:last-child { border-bottom: none; }
        .items-table td { padding: 10px 12px; vertical-align: top; }
        .items-table td .sub { font-size: 11px; color: var(--muted); margin-top: 3px; line-height: 1.5; }

        /* Subtotals */
        .subtotal-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 12px;
            font-size: 13px;
            color: var(--muted);
        }

        /* Grand total */
        .grand-total {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding: 16px 12px;
            background: var(--light);
            border-radius: 8px;
            margin-top: 14px;
            border: 1.5px solid var(--border);
        }
        .grand-total .label { font-size: 15px; font-weight: 700; color: var(--text); }
        .grand-total .value { font-size: 24px; font-weight: 800; color: var(--primary); }

        /* Payment status */
        .pay-valid   { color: #059669; }
        .pay-invalid { color: #dc2626; }
        .pay-pending { color: #d97706; }

        /* Cancellation notice */
        .cancel-notice {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            border-radius: 8px;
            padding: 12px 16px;
            margin-top: 14px;
            font-size: 13px;
            color: #991b1b;
        }

        /* Footer */
        .receipt-footer {
            background: var(--light);
            border-top: 1px solid var(--border);
            padding: 18px 36px;
            text-align: center;
            font-size: 12px;
            color: var(--muted);
            line-height: 1.8;
        }
        .receipt-footer strong { color: var(--text); }

        /* ── Print styles ── */
        @media print {
            body { background: white; padding: 0; }
            .toolbar { display: none !important; }
            .receipt {
                box-shadow: none;
                border-radius: 0;
                max-width: 100%;
            }
        }

        @media (max-width: 600px) {
            .receipt-header { flex-direction: column; gap: 14px; }
            .receipt-title  { text-align: left; }
            .info-grid      { grid-template-columns: 1fr; }
            .receipt-body   { padding: 0 20px 24px; }
            .receipt-header { padding: 22px 20px; }
        }
    </style>
</head>
<body>

<!-- Screen-only toolbar -->
<div class="toolbar">
    <a href="javascript:history.back()" class="btn btn-back">
        <i class="fas fa-arrow-left"></i> Back
    </a>
    <button class="btn btn-print" onclick="window.print()">
        <i class="fas fa-print"></i> Print Receipt
    </button>
    <span class="toolbar-note">Printed: <?= $printedAt ?></span>
</div>

<!-- Receipt -->
<div class="receipt">

    <!-- Header -->
    <div class="receipt-header">
        <div>
            <div class="brand-name">StationaryPlus</div>
            <?php if (!empty($order['branch_name'])): ?>
            <div class="brand-sub">
                <?= esc($order['branch_name']) ?><br>
                <?php if (!empty($order['branch_address'])): ?>
                    <?= esc($order['branch_address']) ?><br>
                <?php endif; ?>
                <?php if (!empty($order['branch_phone'])): ?>
                    <?= esc($order['branch_phone']) ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="receipt-title">
            <h2><?= $typeLabel ?> Receipt</h2>
            <p><?= date('d M Y, H:i', strtotime($order['order_date'])) ?></p>
        </div>
    </div>

    <div class="receipt-body">

        <!-- Order Info -->
        <div class="section">
            <div class="section-title"><i class="fas fa-file-alt"></i> Order Information</div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="label">Order ID</div>
                    <div class="value mono"><?= esc($orderId) ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Status</div>
                    <div class="value">
                        <?php
                        $statusColors = [
                            'NEW'        => '#3b82f6',
                            'PROCESSING' => '#f59e0b',
                            'READY'      => '#10b981',
                            'COLLECTED'  => '#6b7280',
                            'CANCELLED'  => '#ef4444',
                        ];
                        $sc = $statusColors[$order['order_status']] ?? '#888';
                        $sl = ucfirst(strtolower($order['order_status']));
                        if ($order['order_status'] === 'READY') $sl = 'Ready for Collection';
                        ?>
                        <span class="status-badge" style="background:<?= $sc ?>22;color:<?= $sc ?>;border:1px solid <?= $sc ?>44;">
                            <?= $sl ?>
                        </span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="label">Type</div>
                    <div class="value"><?= $typeLabel ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Date</div>
                    <div class="value"><?= date('d M Y', strtotime($order['order_date'])) ?></div>
                </div>
                <?php if (!empty($order['notes'])): ?>
                <div class="info-item" style="grid-column:span 2;">
                    <div class="label">Notes</div>
                    <div class="value" style="font-weight:400;"><?= esc($order['notes']) ?></div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($order['order_status'] === 'CANCELLED' && !empty($order['cancellation_reason'])): ?>
            <div class="cancel-notice">
                <i class="fas fa-times-circle"></i>
                <strong>Cancellation reason:</strong> <?= esc($order['cancellation_reason']) ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Customer Info -->
        <?php if (!empty($order['customer_name'])): ?>
        <div class="section">
            <div class="section-title"><i class="fas fa-user"></i> Customer</div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="label">Name</div>
                    <div class="value"><?= esc($order['customer_name']) ?></div>
                </div>
                <?php if (!empty($order['customer_phone'])): ?>
                <div class="info-item">
                    <div class="label">Phone</div>
                    <div class="value"><?= esc($order['customer_phone']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($order['customer_email'])): ?>
                <div class="info-item" style="grid-column:span 2;">
                    <div class="label">Email</div>
                    <div class="value" style="font-weight:400;"><?= esc($order['customer_email']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stationery Items -->
        <?php if (!empty($items)): ?>
        <div class="section">
            <div class="section-title"><i class="fas fa-box"></i> Stationery Items</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th style="text-align:center;">Qty</th>
                        <th>Unit Price</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= esc($item['product_name']) ?></td>
                        <td style="text-align:center;"><?= $item['quantity'] ?></td>
                        <td>RM <?= number_format($item['unit_price'], 2) ?></td>
                        <td>RM <?= number_format($item['subtotal'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!empty($printFiles)): ?>
            <div class="subtotal-row" style="margin-top:8px;">
                <span>Items subtotal</span>
                <span>RM <?= number_format($itemsTotal, 2) ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Print Files -->
        <?php if (!empty($printFiles)): ?>
        <div class="section">
            <div class="section-title"><i class="fas fa-print"></i> Print Files</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Specs</th>
                        <th>Est. Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($printFiles as $f):
                        $colourText = '';
                        if ((int)$f['color_pages'] > 0 && (int)$f['bw_pages'] > 0) {
                            $colourText = (int)$f['color_pages'] . ' colour + ' . (int)$f['bw_pages'] . ' B&W';
                        } elseif ((int)$f['color_pages'] > 0) {
                            $colourText = 'Full colour';
                        } else {
                            $colourText = 'Black & White';
                        }
                        $pfStatusColors = ['RECEIVED'=>'#3b82f6','REVIEWED'=>'#10b981','REJECTED'=>'#ef4444'];
                        $pfLabels       = ['RECEIVED'=>'Under Review','REVIEWED'=>'Approved','REJECTED'=>'Rejected'];
                        $pfColor  = $pfStatusColors[$f['file_status']] ?? '#888';
                        $pfLabel  = $pfLabels[$f['file_status']]       ?? $f['file_status'];
                    ?>
                    <tr>
                        <td>
                            <strong><?= esc($f['file_name']) ?></strong>
                            <div class="sub">
                                <span class="status-badge" style="font-size:10px;background:<?= $pfColor ?>22;color:<?= $pfColor ?>;border:1px solid <?= $pfColor ?>44;">
                                    <?= $pfLabel ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <div><?= (int)$f['total_pages'] ?> page<?= $f['total_pages'] != 1 ? 's' : '' ?> · <?= $colourText ?></div>
                            <div class="sub">
                                <?= esc($f['paper_size']) ?> ·
                                <?= esc($f['paper_type']) ?> ·
                                <?= bindingLabel($f['binding_type']) ?> ·
                                <?= (int)$f['copies'] ?> cop<?= $f['copies'] != 1 ? 'ies' : 'y' ?>
                            </div>
                        </td>
                        <td>RM <?= number_format($f['estimated_price'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!empty($items)): ?>
            <div class="subtotal-row" style="margin-top:8px;">
                <span>Print files subtotal</span>
                <span>RM <?= number_format($printTotal, 2) ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Payment -->
        <div class="section">
            <div class="section-title"><i class="fas fa-receipt"></i> Payment</div>
            <?php if ($payment): ?>
            <div class="info-grid">
                <div class="info-item">
                    <div class="label">Payment ID</div>
                    <div class="value mono"><?= esc($payment['payment_id']) ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Method</div>
                    <div class="value"><?= methodLabel($payment['payment_method']) ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Amount Paid</div>
                    <div class="value">RM <?= number_format($payment['amount'], 2) ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Status</div>
                    <div class="value <?= match($payment['verification_status']) { 'VALID' => 'pay-valid', 'INVALID' => 'pay-invalid', default => 'pay-pending' } ?>">
                        <?= payStatusLabel($payment['verification_status']) ?>
                    </div>
                </div>
                <?php if (!empty($payment['reference_number'])): ?>
                <div class="info-item" style="grid-column:span 2;">
                    <div class="label">Reference</div>
                    <div class="value mono" style="font-weight:400;"><?= esc($payment['reference_number']) ?></div>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <div class="label">Payment Date</div>
                    <div class="value"><?= date('d M Y', strtotime($payment['record_date'])) ?></div>
                </div>
            </div>
            <?php else: ?>
            <p style="font-size:13px;color:var(--muted);">No payment submitted for this order yet.</p>
            <?php endif; ?>
        </div>

        <!-- Grand Total -->
        <div class="grand-total">
            <span class="label">Grand Total</span>
            <span class="value">RM <?= number_format($grandTotal, 2) ?></span>
        </div>

    </div><!-- /.receipt-body -->

    <!-- Footer -->
    <div class="receipt-footer">
        <strong>Thank you for choosing StationaryPlus!</strong><br>
        <?php if ($order['order_status'] === 'READY'): ?>
        Please bring this receipt and your <strong>Order ID (<?= esc($orderId) ?>)</strong> when collecting.<br>
        <?php elseif ($order['order_status'] === 'COLLECTED'): ?>
        This order has been collected. Thank you for your business.<br>
        <?php endif; ?>
        Printed on <?= $printedAt ?> &nbsp;·&nbsp; StationaryPlus Management System
    </div>

</div><!-- /.receipt -->

</body>
</html>