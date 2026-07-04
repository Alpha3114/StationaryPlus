<?php
// ============================================================
//  c_orderstatus.php — Customer Order & Pre-order Status
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'auth.php';
require_role('CUSTOMER');
require_once 'db.php';
require_once 'branch_browse.php';

$userId = $_SESSION['user_id'];

// ── Handle customer order cancellation ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $cancelId = trim($_POST['order_id']     ?? '');
    $reason   = trim($_POST['cancel_reason'] ?? '') ?: null;

    if ($cancelId) {
        // Verify: belongs to customer, is a PREORDER, is still NEW,
        // and has no PENDING or VALID payment
        $stmt = $conn->prepare(
            "SELECT o.order_id, o.branch_id FROM orders o
             WHERE o.order_id   = ?
               AND o.user_id    = ?
               AND o.order_type = 'PREORDER'
               AND o.order_status = 'NEW'
               AND o.order_id NOT IN (
                   SELECT p.order_id FROM payments p
                   WHERE p.verification_status IN ('VALID','PENDING')
               )
             LIMIT 1"
        );
        $stmt->bind_param('ss', $cancelId, $userId);
        $stmt->execute();
        $ok = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($ok) {
            $stmt = $conn->prepare(
                "UPDATE orders
                 SET order_status = 'CANCELLED', cancellation_reason = ?
                 WHERE order_id = ?"
            );
            $stmt->bind_param('ss', $reason, $cancelId);
            $stmt->execute();
            $stmt->close();

            // Release reserved stock for every item on this order —
            // physical stock is untouched, only the hold is removed.
            $orderBranch = $ok['branch_id'];
            $iStmt = $conn->prepare(
                "SELECT product_id, quantity FROM order_items WHERE order_id = ?"
            );
            $iStmt->bind_param('s', $cancelId);
            $iStmt->execute();
            $cancelItems = $iStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $iStmt->close();

            foreach ($cancelItems as $item) {
                if ($orderBranch) {
                    $relStmt = $conn->prepare(
                        "UPDATE inventory
                         SET reserved_quantity = GREATEST(0, reserved_quantity - ?)
                         WHERE product_id = ? AND branch_id = ?"
                    );
                    $relStmt->bind_param('iss', $item['quantity'], $item['product_id'], $orderBranch);
                } else {
                    $relStmt = $conn->prepare(
                        "UPDATE inventory
                         SET reserved_quantity = GREATEST(0, reserved_quantity - ?)
                         WHERE product_id = ?
                         ORDER BY reserved_quantity DESC LIMIT 1"
                    );
                    $relStmt->bind_param('is', $item['quantity'], $item['product_id']);
                }
                $relStmt->execute();
                $relStmt->close();
            }
        }
    }

    header('Location: c_orderstatus.php');
    exit;
}

$filterType   = $_GET['type']   ?? 'all';
$filterStatus = $_GET['status'] ?? 'all';
$filterPeriod = $_GET['period'] ?? '30';

$periodSQL = match($filterPeriod) {
    '7'  => "AND o.order_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    '30' => "AND o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
    '90' => "AND o.order_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)",
    default => ''
};

$typeSQL = match($filterType) {
    'order'    => "AND o.order_type = 'ORDER'",
    'preorder' => "AND o.order_type = 'PREORDER'",
    default    => ''
};

$validStatuses = ['NEW','PROCESSING','READY','COLLECTED','CANCELLED'];
$statusSQL     = '';
$statusParam   = null;
if ($filterStatus !== 'all' && in_array($filterStatus, $validStatuses)) {
    $statusSQL   = "AND o.order_status = ?";
    $statusParam = $filterStatus;
}

// ── Main query ────────────────────────────────────────────────
// Correlated subqueries fetch the most recent payment status
// without fanning out rows (avoids duplicates from multi-file orders).
$sql = "SELECT
            o.order_id            AS id,
            o.order_date,
            o.order_status        AS status,
            o.estimated_total     AS amount,
            o.order_type          AS type,
            o.notes,
            o.cancellation_reason AS cancellation_reason,
            pf.file_id            AS pf_file_id,
            pf.file_name          AS pf_file_name,
            pf.file_status        AS pf_file_status,
            pf.rejection_reason   AS pf_rejection_reason,
            pf.duration_min       AS pf_duration_min,
            -- Most recent payment for this order
            (SELECT verification_status FROM payments
             WHERE order_id = o.order_id
             ORDER BY record_date DESC LIMIT 1) AS pay_status,
            (SELECT payment_id FROM payments
             WHERE order_id = o.order_id
             ORDER BY record_date DESC LIMIT 1) AS pay_id
        FROM orders o
        LEFT JOIN print_files pf ON pf.order_id = o.order_id
        WHERE o.user_id = ?
          $periodSQL
          $typeSQL
          $statusSQL
        ORDER BY o.order_date DESC";

$stmt = $conn->prepare($sql);
if ($statusParam) {
    $stmt->bind_param('ss', $userId, $statusParam);
} else {
    $stmt->bind_param('s', $userId);
}
$stmt->execute();
$allRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Badge helpers ─────────────────────────────────────────────
function statusBadge(string $status): string {
    $map = [
        'NEW'        => ['#3b82f6','#eff6ff','New'],
        'PROCESSING' => ['#f59e0b','#fffbeb','Processing'],
        'READY'      => ['#10b981','#ecfdf5','Ready for Collection'],
        'COLLECTED'  => ['#6b7280','#f3f4f6','Collected'],
        'CANCELLED'  => ['#ef4444','#fef2f2','Cancelled'],
    ];
    [$color, $bg, $label] = $map[$status] ?? ['#888','#f3f4f6', $status];
    return "<span style='background:$bg;color:$color;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;white-space:nowrap;'>$label</span>";
}

function typeBadge(string $type): string {
    if ($type === 'PREORDER') {
        return "<span style='background:#f3f0ff;color:#6d28d9;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;'>Pre-order</span>";
    }
    return "<span style='background:#eff6ff;color:#1d4ed8;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;'>Order</span>";
}

function paymentBadge(?string $status): string {
    if ($status === null) {
        return "<span style='background:#f3f4f6;color:#6b7280;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;'>Not submitted</span>";
    }
    $map = [
        'VALID'   => ['#10b981','#ecfdf5','✓ Verified'],
        'PENDING' => ['#f59e0b','#fffbeb','Pending Review'],
        'INVALID' => ['#ef4444','#fef2f2','✗ Rejected'],
    ];
    [$color, $bg, $label] = $map[$status] ?? ['#6b7280','#f3f4f6', $status];
    return "<span style='background:$bg;color:$color;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;white-space:nowrap;'>$label</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus - Order Status</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary:#A83535;--secondary:#F4A261;--accent:#F1EDE8;--background:#FAFAFA;--text-primary:#2E2E2E;--text-secondary:#707070;--border:#E0E0E0;--white:#FFFFFF;--sidebar-width:260px;--card-shadow:0 4px 12px rgba(0,0,0,0.05); }
        * { margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',system-ui,sans-serif; }
        body { background-color:var(--background);color:var(--text-primary);min-height:100vh;display:flex; }
        .sidebar { width:var(--sidebar-width);background-color:var(--white);border-right:1px solid var(--border);height:100vh;position:fixed;left:0;top:0;display:flex;flex-direction:column;box-shadow:2px 0 10px rgba(0,0,0,0.03);overflow-y:auto; }
        .logo-area { padding:25px;border-bottom:1px solid var(--border);display:flex;align-items:center;flex-shrink:0; }
        .logo-icon { background-color:var(--primary);width:40px;height:40px;border-radius:8px;display:flex;align-items:center;justify-content:center;margin-right:12px;color:white;font-size:20px; }
        .logo-text { font-size:22px;font-weight:700;color:var(--primary); }
        .nav-section { padding:25px 0;border-bottom:1px solid var(--border); }
        .nav-title { font-size:12px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.8px;padding:0 25px 12px 25px; }
        .nav-menu { list-style:none; }
        .nav-item { margin-bottom:2px; }
        .nav-link { display:flex;align-items:center;padding:13px 25px;color:var(--text-primary);text-decoration:none;transition:all 0.2s;border-left:4px solid transparent; }
        .nav-link:hover { background-color:rgba(168,53,53,0.05);color:var(--primary);border-left-color:rgba(168,53,53,0.3); }
        .nav-link.active { background-color:rgba(168,53,53,0.08);color:var(--primary);border-left-color:var(--primary);font-weight:600; }
        .nav-icon { width:22px;text-align:center;margin-right:14px;font-size:16px; }
        .nav-text { font-size:15px; }
        .user-section { margin-top:auto;padding:20px 25px;border-top:1px solid var(--border); }
        .user-info { display:flex;align-items:center;margin-bottom:14px; }
        .user-avatar { width:40px;height:40px;border-radius:50%;background-color:rgba(168,53,53,0.1);display:flex;align-items:center;justify-content:center;color:var(--primary);font-weight:700;font-size:16px;margin-right:12px;flex-shrink:0; }
        .user-name { font-weight:600;font-size:15px;color:var(--text-primary); }
        .user-role { font-size:12px;color:var(--text-secondary);margin-top:2px; }
        .logout-link { display:flex;align-items:center;gap:10px;padding:10px 14px;background-color:rgba(168,53,53,0.06);color:var(--primary);border-radius:8px;text-decoration:none;font-size:14px;font-weight:600; }
        .logout-link:hover { background-color:rgba(168,53,53,0.14); }
        .main-content { flex-grow:1;margin-left:var(--sidebar-width);min-height:100vh;display:flex;flex-direction:column; }
        .top-header { background-color:var(--white);padding:20px 30px;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:10;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap; }
        .page-title { font-size:24px;font-weight:700; }
        .page-subtitle { font-size:14px;color:var(--text-secondary);margin-top:4px; }
        .content-container { padding:30px;flex-grow:1;display:flex;flex-direction:column;gap:24px; }
        .section-card { background:var(--white);border-radius:12px;box-shadow:var(--card-shadow);border:1px solid var(--border);overflow:hidden; }
        .section-header { padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;background:rgba(168,53,53,0.02); }
        .section-title { font-size:16px;font-weight:700;color:var(--primary);display:flex;align-items:center;gap:8px; }
        .result-count { font-size:13px;color:var(--text-secondary);background:rgba(168,53,53,0.08);padding:4px 12px;border-radius:20px;font-weight:600; }
        .filter-bar { display:flex;gap:10px;align-items:center;padding:16px 24px;border-bottom:1px solid var(--border);flex-wrap:wrap;background:rgba(168,53,53,0.01); }
        .filter-select { padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;background:var(--white);color:var(--text-primary);cursor:pointer; }
        .filter-select:focus { outline:none;border-color:var(--primary); }
        .filter-btn { padding:8px 18px;background:var(--primary);color:white;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:7px; }
        .reset-link { font-size:13px;color:var(--text-secondary);text-decoration:none;padding:8px 12px;border-radius:8px;transition:all 0.2s; }
        .reset-link:hover { color:var(--primary);background:rgba(168,53,53,0.06); }
        .table-wrap { overflow-x:auto; }
        .orders-table { width:100%;border-collapse:collapse; }
        .orders-table thead { background:rgba(168,53,53,0.03);border-bottom:2px solid var(--border); }
        .orders-table th { padding:13px 20px;text-align:left;font-size:12px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.5px;white-space:nowrap; }
        .orders-table tbody tr { border-bottom:1px solid var(--border);transition:background-color 0.15s; }
        .orders-table tbody tr:last-child { border-bottom:none; }
        .orders-table tbody tr:hover { background-color:rgba(168,53,53,0.02); }
        .orders-table td { padding:14px 20px;font-size:14px;color:var(--text-primary);vertical-align:middle; }
        .order-id { font-weight:700;color:var(--primary);font-family:monospace;font-size:13px; }
        .order-date { color:var(--text-secondary);font-size:13px; }
        .detail-btn { padding:7px 14px;background-color:rgba(168,53,53,0.08);color:var(--primary);border:1px solid rgba(168,53,53,0.3);border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;transition:all 0.2s;white-space:nowrap; }
        .detail-btn:hover { background-color:rgba(168,53,53,0.16); }
        .cancel-order-btn { padding:7px 14px;background:rgba(239,68,68,0.08);color:#dc2626;border:1px solid rgba(239,68,68,0.3);border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;transition:all 0.2s;white-space:nowrap;margin-left:6px; }
        .cancel-order-btn:hover { background:#dc2626;color:white;border-color:#dc2626; }
        .resubmit-link { display:inline-flex;align-items:center;gap:5px;margin-top:5px;font-size:11px;font-weight:600;color:#dc2626;text-decoration:none;padding:3px 8px;background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;transition:all 0.15s; }
        .resubmit-link:hover { background:#dc2626;color:white; }
        .empty-state { padding:60px 20px;text-align:center;color:var(--text-secondary); }
        .empty-state i { font-size:44px;opacity:0.2;margin-bottom:14px;display:block; }
        /* Modal */
        .modal-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:200;align-items:center;justify-content:center; }
        .modal-overlay.open { display:flex; }
        .modal { background:var(--white);border-radius:12px;width:90%;max-width:520px;max-height:85vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.2); }
        .modal-header { padding:18px 22px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center; }
        .modal-title { font-size:16px;font-weight:700;color:var(--primary); }
        .modal-close { background:none;border:none;font-size:18px;color:var(--text-secondary);cursor:pointer;padding:4px 8px;border-radius:6px; }
        .modal-close:hover { background:var(--accent);color:var(--primary); }
        .modal-body { padding:22px; }
        .detail-row { display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border);font-size:14px; }
        .detail-row:last-child { border-bottom:none; }
        .detail-label { color:var(--text-secondary); }
        .detail-value { font-weight:600;color:var(--text-primary); }
        .page-footer { text-align:center;padding:22px;color:var(--text-secondary);font-size:13px;border-top:1px solid var(--border);background-color:var(--white); }
        .footer-links { margin-top:8px; }
        .footer-links a { color:var(--primary);text-decoration:none;margin:0 10px; }
        @media (max-width:1024px) {
            :root { --sidebar-width:70px; }
            .logo-text,.nav-text,.user-details,.nav-title,.logout-link span { display:none; }
            .logo-area,.nav-section,.user-section { padding:18px 12px; }
            .nav-link { justify-content:center;padding:14px;border-left:none;border-right:4px solid transparent; }
            .nav-link:hover,.nav-link.active { border-left:none;border-right-color:var(--primary); }
            .nav-icon { margin-right:0;font-size:20px; }
            .logout-link { justify-content:center;padding:10px; }
        }
    </style>
</head>
<body>

<?php include 'c_sidebar.php'; ?>

<main class="main-content">
    <header class="top-header">
        <div>
            <h1 class="page-title">Order Status</h1>
            <p class="page-subtitle">Track your pre-orders and orders in real time</p>
        </div>
        <?php render_browsing_branch_bar(); ?>
    </header>

    <div class="content-container">
        <div class="section-card">
            <div class="section-header">
                <div class="section-title"><i class="fas fa-clipboard-list"></i> My Orders &amp; Pre-orders</div>
                <span class="result-count"><?= count($allRows) ?> record<?= count($allRows) !== 1 ? 's' : '' ?> found</span>
            </div>

            <form method="GET" action="c_orderstatus.php">
                <div class="filter-bar">
                    <select name="type" class="filter-select">
                        <option value="all"      <?= $filterType==='all'      ? 'selected':'' ?>>All Types</option>
                        <option value="order"    <?= $filterType==='order'    ? 'selected':'' ?>>Orders only</option>
                        <option value="preorder" <?= $filterType==='preorder' ? 'selected':'' ?>>Pre-orders only</option>
                    </select>
                    <select name="status" class="filter-select">
                        <option value="all"        <?= $filterStatus==='all'        ? 'selected':'' ?>>All Statuses</option>
                        <option value="NEW"        <?= $filterStatus==='NEW'        ? 'selected':'' ?>>New</option>
                        <option value="PROCESSING" <?= $filterStatus==='PROCESSING' ? 'selected':'' ?>>Processing</option>
                        <option value="READY"      <?= $filterStatus==='READY'      ? 'selected':'' ?>>Ready</option>
                        <option value="COLLECTED"  <?= $filterStatus==='COLLECTED'  ? 'selected':'' ?>>Collected</option>
                        <option value="CANCELLED"  <?= $filterStatus==='CANCELLED'  ? 'selected':'' ?>>Cancelled</option>
                    </select>
                    <select name="period" class="filter-select">
                        <option value="7"   <?= $filterPeriod==='7'   ? 'selected':'' ?>>Last 7 days</option>
                        <option value="30"  <?= $filterPeriod==='30'  ? 'selected':'' ?>>Last 30 days</option>
                        <option value="90"  <?= $filterPeriod==='90'  ? 'selected':'' ?>>Last 3 months</option>
                        <option value="all" <?= $filterPeriod==='all' ? 'selected':'' ?>>All time</option>
                    </select>
                    <button type="submit" class="filter-btn"><i class="fas fa-filter"></i> Apply</button>
                    <a href="c_orderstatus.php" class="reset-link"><i class="fas fa-times"></i> Reset</a>
                </div>
            </form>

            <div class="table-wrap">
                <?php if (empty($allRows)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <p>No records found for the selected filters.</p>
                    </div>
                <?php else: ?>
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Order Status</th>
                            <th>Payment</th>
                            <th>File Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($allRows as $row): ?>
                    <tr>
                        <td><span class="order-id"><?= htmlspecialchars($row['id']) ?></span></td>
                        <td><?= typeBadge($row['type']) ?></td>
                        <td class="order-date"><?= date('d M Y', strtotime($row['order_date'])) ?></td>
                        <td><?= $row['amount'] !== null ? 'RM '.number_format($row['amount'],2) : '—' ?></td>
                        <td><?= statusBadge($row['status']) ?>
                            <?php if ($row['status'] === 'CANCELLED' && !empty($row['cancellation_reason'])): ?>
                            <div style="margin-top:6px;padding:7px 11px;
                                        background:#fef2f2;border:1px solid #fca5a5;
                                        border-radius:7px;font-size:12px;color:#991b1b;
                                        display:flex;align-items:flex-start;gap:6px;">
                                <i class="fas fa-times-circle" style="flex-shrink:0;margin-top:2px;"></i>
                                <div>
                                    <strong>Reason:</strong>
                                    <?= htmlspecialchars($row['cancellation_reason']) ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </td>

                        <!-- Payment status column -->
                        <td style="padding:10px 16px;">
                            <?= paymentBadge($row['pay_status'] ?? null) ?>
                            <?php if (($row['pay_status'] ?? null) === 'INVALID'): ?>
                            <div>
                                <a href="c_payment.php" class="resubmit-link">
                                    <i class="fas fa-redo"></i> Resubmit payment
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php
                            // Only nudge payment when:
                            // - No payment submitted yet AND order is NEW
                            // - AND either no print file, or the print file is already REVIEWED
                            $pfReviewed = empty($row['pf_file_id']) || ($row['pf_file_status'] ?? '') === 'REVIEWED';
                            if (($row['pay_status'] ?? null) === null && $row['status'] === 'NEW' && $pfReviewed):
                            ?>
                            <div style="margin-top:5px;">
                                <a href="c_payment.php" style="font-size:11px;color:var(--primary);text-decoration:none;font-weight:600;">
                                    <i class="fas fa-arrow-right"></i> Submit payment
                                </a>
                            </div>
                            <?php endif; ?>
                        </td>

                        <!-- Print file status column -->
                        <?php if (!empty($row['pf_file_id'])): ?>
                        <td style="padding:10px 16px;">

                            <?php if (!empty($row['pf_duration_min'])): ?>
                            <div style="margin-bottom:6px;display:inline-flex;align-items:center;gap:6px;
                                        padding:5px 11px;background:#eff6ff;border:1px solid #bfdbfe;
                                        border-radius:20px;font-size:12px;font-weight:600;color:#1d4ed8;">
                                <i class="fas fa-clock"></i>
                                <?php
                                $mins = (int)$row['pf_duration_min'];
                                if ($mins >= 60) {
                                    $h = intdiv($mins, 60);
                                    $m = $mins % 60;
                                    echo "Est. print time: {$h}h" . ($m > 0 ? " {$m}min" : "");
                                } else {
                                    echo "Est. print time: {$mins} min";
                                }
                                ?>
                            </div><br>
                            <?php endif; ?>

                            <?php
                            $pfStatus = $row['pf_file_status'] ?? '';
                            $pfColors = [
                                'RECEIVED' => ['#3b82f6','#eff6ff','Under Review'],
                                'REVIEWED' => ['#10b981','#ecfdf5','File Approved'],
                                'REJECTED' => ['#ef4444','#fef2f2','File Rejected'],
                            ];
                            [$pfColor, $pfBg, $pfLabel] = $pfColors[$pfStatus] ?? ['#6b7280','#f3f4f6', $pfStatus];
                            ?>
                            <span style="background:<?= $pfBg ?>;color:<?= $pfColor ?>;
                                         border:1px solid <?= $pfColor ?>55;padding:3px 10px;
                                         border-radius:20px;font-size:12px;font-weight:600;">
                                <?= htmlspecialchars($pfLabel) ?>
                            </span>

                            <?php if ($pfStatus === 'REJECTED' && !empty($row['pf_rejection_reason'])): ?>
                            <div style="margin-top:6px;padding:8px 12px;
                                        background:#fef2f2;border:1px solid #fca5a5;
                                        border-radius:7px;font-size:12px;color:#991b1b;
                                        display:flex;align-items:flex-start;gap:7px;">
                                <i class="fas fa-exclamation-circle" style="margin-top:2px;flex-shrink:0;"></i>
                                <div>
                                    <strong>File rejected:</strong>
                                    <?= htmlspecialchars($row['pf_rejection_reason']) ?>
                                    <div style="margin-top:6px;">
                                        <a href="c_upload.php?link_order=<?= urlencode($row['id']) ?>"
                                           style="display:inline-flex;align-items:center;gap:5px;
                                                  padding:4px 10px;background:#dc2626;color:white;
                                                  border-radius:6px;font-size:11px;font-weight:700;
                                                  text-decoration:none;">
                                            <i class="fas fa-upload"></i> Re-upload corrected file
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </td>
                        <?php else: ?>
                        <td style="padding:10px 16px;color:#9ca3af;font-size:13px;">—</td>
                        <?php endif; ?>

                        <td>
                            <button class="detail-btn"
                                    onclick="openModal('<?= htmlspecialchars($row['id'], ENT_QUOTES) ?>')">
                                <i class="fas fa-eye"></i> Details
                            </button>
                            <?php
                            $isCancellable = $row['type'] === 'PREORDER'
                                          && $row['status'] === 'NEW'
                                          && ($row['pay_status'] ?? null) === null;
                            ?>
                            <?php if ($isCancellable): ?>
                            <button class="cancel-order-btn"
                                    onclick="openCancelModal('<?= htmlspecialchars($row['id'], ENT_QUOTES) ?>')">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="page-footer">
        <div>&copy; <?= date('Y') ?> StationaryPlus &mdash; Stationery &amp; Printing Management System</div>
        <div class="footer-links">
            <a href="c_payment.php">Make a Payment</a> |
            <a href="c_dashboard.php">Dashboard</a>
        </div>
    </footer>
</main>

<div class="modal-overlay" id="modalOverlay" onclick="if(event.target===this)closeModal()">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="modalTitle">Order Details</div>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

<!-- Cancel order confirmation modal -->
<div class="modal-overlay" id="cancelModalOverlay" onclick="if(event.target===this)closeCancelModal()">
    <div class="modal" style="max-width:420px;">
        <div class="modal-header">
            <div class="modal-title" style="color:#dc2626;">
                <i class="fas fa-times-circle"></i> Cancel Order
            </div>
            <button class="modal-close" onclick="closeCancelModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <p style="font-size:14px;color:var(--text-secondary);margin-bottom:16px;line-height:1.6;">
                Are you sure you want to cancel order
                <strong id="cancelOrderLabel" style="color:var(--text-primary);font-family:monospace;"></strong>?
                This cannot be undone.
            </p>
            <form method="POST" action="c_orderstatus.php">
                <input type="hidden" name="cancel_order" value="1">
                <input type="hidden" name="order_id" id="cancelOrderInput" value="">
                <label style="display:block;font-size:13px;font-weight:600;
                              color:var(--text-primary);margin-bottom:7px;">
                    Reason <span style="font-weight:400;color:var(--text-secondary);">(optional — helps us improve)</span>
                </label>
                <textarea name="cancel_reason"
                          placeholder="e.g. Changed my mind, ordered by mistake…"
                          style="width:100%;padding:10px 12px;border:1.5px solid var(--border);
                                 border-radius:8px;font-size:13px;resize:none;min-height:80px;
                                 font-family:inherit;background:var(--accent);outline:none;
                                 transition:border-color 0.2s;color:var(--text-primary);"
                          onfocus="this.style.borderColor='var(--primary)'"
                          onblur="this.style.borderColor='var(--border)'"></textarea>
                <div style="display:flex;gap:10px;margin-top:16px;">
                    <button type="button" onclick="closeCancelModal()"
                            style="flex:1;padding:11px;background:var(--accent);
                                   color:var(--text-primary);border:1.5px solid var(--border);
                                   border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">
                        Keep Order
                    </button>
                    <button type="submit"
                            style="flex:1;padding:11px;background:#dc2626;color:white;
                                   border:none;border-radius:8px;font-size:14px;font-weight:600;
                                   cursor:pointer;transition:background 0.2s;"
                            onmouseover="this.style.background='#b91c1c'"
                            onmouseout="this.style.background='#dc2626'">
                        <i class="fas fa-times-circle"></i> Yes, Cancel Order
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById('modalTitle').textContent = 'Order Details';
    document.getElementById('modalBody').innerHTML =
        '<div style="text-align:center;padding:30px;color:#707070"><i class="fas fa-spinner fa-spin" style="font-size:22px;margin-bottom:10px;display:block;"></i>Loading…</div>';
    document.getElementById('modalOverlay').classList.add('open');

    fetch('c_orderdetail.php?id=' + encodeURIComponent(id))
        .then(r => r.json())
        .then(d => renderReceipt(d))
        .catch(() => {
            document.getElementById('modalBody').innerHTML =
                '<p style="text-align:center;color:#c62828;padding:30px;">Failed to load details.</p>';
        });
}

function renderReceipt(d) {
    if (d.error) {
        document.getElementById('modalBody').innerHTML =
            '<p style="text-align:center;color:#c62828;padding:30px;">' + d.error + '</p>';
        return;
    }

    const isPreorder = d.type === 'PREORDER';
    const typeLabel  = isPreorder ? 'Pre-order / Reservation' : 'Walk-in Sale';

    const payBadge = {
        'VALID':   "<span style='background:#ecfdf5;color:#059669;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600;'>✓ Verified</span>",
        'PENDING': "<span style='background:#fffbeb;color:#d97706;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600;'>Pending Review</span>",
        'INVALID': "<span style='background:#fef2f2;color:#dc2626;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600;'>✗ Rejected — please resubmit</span>",
    }[d.pay_status] || "<span style='background:#f3f4f6;color:#6b7280;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600;'>Not submitted</span>";

    // Order items rows
    let itemsHtml = '';
    if (d.items && d.items.length > 0) {
        itemsHtml = `
        <div style="margin:14px 0 4px;font-size:12px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.5px;">
            <i class="fas fa-box"></i> Stationery Items
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:13px;margin-bottom:10px;">
            <thead style="background:rgba(168,53,53,0.04);">
                <tr>
                    <th style="padding:8px 10px;text-align:left;font-size:11px;color:var(--text-secondary);">Item</th>
                    <th style="padding:8px 10px;text-align:center;font-size:11px;color:var(--text-secondary);">Qty</th>
                    <th style="padding:8px 10px;text-align:right;font-size:11px;color:var(--text-secondary);">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                ${d.items.map(i => `
                <tr style="border-bottom:1px solid #f0f0f0;">
                    <td style="padding:9px 10px;font-weight:600;">${esc(i.name)}</td>
                    <td style="padding:9px 10px;text-align:center;">${i.qty}</td>
                    <td style="padding:9px 10px;text-align:right;font-family:monospace;">RM ${(i.qty * i.unit_price).toFixed(2)}</td>
                </tr>`).join('')}
            </tbody>
        </table>`;
    }

    // Print files rows
    let filesHtml = '';
    if (d.print_files && d.print_files.length > 0) {
        filesHtml = `
        <div style="margin:14px 0 4px;font-size:12px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.5px;">
            <i class="fas fa-print"></i> Print Files
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:13px;margin-bottom:10px;">
            <thead style="background:rgba(168,53,53,0.04);">
                <tr>
                    <th style="padding:8px 10px;text-align:left;font-size:11px;color:var(--text-secondary);">File</th>
                    <th style="padding:8px 10px;text-align:left;font-size:11px;color:var(--text-secondary);">Details</th>
                    <th style="padding:8px 10px;text-align:right;font-size:11px;color:var(--text-secondary);">Est. Price</th>
                </tr>
            </thead>
            <tbody>
                ${d.print_files.map(f => `
                <tr style="border-bottom:1px solid #f0f0f0;">
                    <td style="padding:9px 10px;font-weight:600;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(f.file_name)}</td>
                    <td style="padding:9px 10px;font-size:12px;color:var(--text-secondary);">
                        ${f.total_pages} pg · ${f.color_pages > 0 ? f.color_pages + ' colour' : ''}${f.color_pages > 0 && f.bw_pages > 0 ? ' / ' : ''}${f.bw_pages > 0 ? f.bw_pages + ' B&W' : ''}
                        · ${f.paper_size} · ${f.copies} cop${f.copies !== 1 ? 'ies' : 'y'}
                    </td>
                    <td style="padding:9px 10px;text-align:right;font-family:monospace;">RM ${parseFloat(f.estimated_price).toFixed(2)}</td>
                </tr>`).join('')}
            </tbody>
        </table>`;
    }

    document.getElementById('modalBody').innerHTML = `
        <div class="detail-row"><span class="detail-label">Order ID</span><span class="detail-value" style="font-family:monospace;">${esc(d.id)}</span></div>
        <div class="detail-row"><span class="detail-label">Type</span><span class="detail-value">${typeLabel}</span></div>
        <div class="detail-row"><span class="detail-label">Date</span><span class="detail-value">${esc(d.date)}</span></div>
        <div class="detail-row"><span class="detail-label">Order Status</span><span class="detail-value">${esc(d.status)}</span></div>
        <div class="detail-row"><span class="detail-label">Payment</span><span class="detail-value">${payBadge}</span></div>
        ${d.status === 'CANCELLED' && d.cancellation_reason ? `
        <div class="detail-row" style="background:#fef2f2;border-radius:7px;padding:10px 12px;margin-top:4px;">
            <span class="detail-label" style="color:#dc2626;"><i class="fas fa-times-circle"></i> Cancellation Reason</span>
            <span class="detail-value" style="color:#991b1b;">${esc(d.cancellation_reason)}</span>
        </div>` : ''}
        ${d.notes ? `<div class="detail-row"><span class="detail-label">Notes</span><span class="detail-value">${esc(d.notes)}</span></div>` : ''}

        ${itemsHtml}
        ${filesHtml}

        <div style="display:flex;justify-content:space-between;padding:14px 0 0;margin-top:8px;border-top:2px solid var(--primary);">
            <span style="font-size:15px;font-weight:700;">Estimated Total</span>
            <span style="font-size:20px;font-weight:800;color:var(--primary);">
                ${d.total !== null ? 'RM ' + parseFloat(d.total).toFixed(2) : '—'}
            </span>
        </div>
        ${d.pay_status === 'INVALID' ? `
        <div style="margin-top:14px;padding:10px 14px;background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;font-size:13px;color:#991b1b;">
            <i class="fas fa-exclamation-circle"></i>
            Your payment was rejected. <a href="c_payment.php" style="color:#dc2626;font-weight:700;">Click here to resubmit.</a>
        </div>` : ''}
        ${d.pay_status === null && d.status === 'NEW' ? `
        <div style="margin-top:14px;text-align:center;">
            <a href="c_payment.php" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:var(--primary);color:white;border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;">
                <i class="fas fa-paper-plane"></i> Submit Payment
            </a>
        </div>` : ''}
        <div style="margin-top:14px;text-align:center;">
            <a href="receipt.php?id=${esc(d.id)}" target="_blank"
               style="display:inline-flex;align-items:center;gap:8px;padding:9px 20px;
                      background:#f3f4f6;color:#374151;border:1.5px solid #e5e7eb;
                      border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;
                      transition:all 0.2s;"
               onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)'"
               onmouseout="this.style.borderColor='#e5e7eb';this.style.color='#374151'">
                <i class="fas fa-print"></i> Print Receipt
            </a>
        </div>
    `;
}

function closeModal() {
    document.getElementById('modalOverlay').classList.remove('open');
    document.getElementById('modalBody').innerHTML = '';
}

function openCancelModal(orderId) {
    document.getElementById('cancelOrderLabel').textContent = orderId;
    document.getElementById('cancelOrderInput').value       = orderId;
    document.getElementById('cancelModalOverlay').classList.add('open');
}

function closeCancelModal() {
    document.getElementById('cancelModalOverlay').classList.remove('open');
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeModal(); closeCancelModal(); }
});

function esc(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

</body>
</html>