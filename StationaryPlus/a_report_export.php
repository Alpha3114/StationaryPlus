<?php
// ============================================================
//  a_report_export.php — Printable Accounting Report
//
//  Standalone letterhead-style report for the same period/tab
//  filters as a_report.php, meant to be saved as PDF via the
//  browser print dialog (window.print()) — same pattern as
//  receipt.php, since this codebase has no PDF library.
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role('ADMIN');
require_once 'db.php';
require_once 'report_helper.php';

// ── Period ────────────────────────────────────────────────────
$period   = $_GET['period']    ?? 'month';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';

$range       = reportPeriodRange($period, $dateFrom, $dateTo);
$rangeSQL    = reportRangeClause($range['start'], $range['end'], 'o.order_date');
$periodLabel = $range['label'];

$rangeSQLPay = reportRangeClause($range['start'], $range['end'], 'record_date');

// ── Generated-by ──────────────────────────────────────────────
$stmt = $conn->prepare("SELECT name FROM users WHERE user_id = ? LIMIT 1");
$stmt->bind_param('s', $_SESSION['user_id']);
$stmt->execute();
$generatedBy = $stmt->get_result()->fetch_assoc()['name'] ?? 'Admin';
$stmt->close();

// ── Revenue reconciliation (Verified / Pending / Rejected) ────
$res = $conn->query(
    "SELECT verification_status, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total
     FROM payments WHERE 1=1 $rangeSQLPay GROUP BY verification_status"
);
$reconRows = $res->fetch_all(MYSQLI_ASSOC);
$recon = ['VALID' => ['cnt'=>0,'total'=>0], 'PENDING' => ['cnt'=>0,'total'=>0], 'INVALID' => ['cnt'=>0,'total'=>0]];
foreach ($reconRows as $r) $recon[$r['verification_status']] = ['cnt' => (int)$r['cnt'], 'total' => (float)$r['total']];
$totalRevenue = $recon['VALID']['total'];

// ── Payment method breakdown (verified only) ──────────────────
$res = $conn->query(
    "SELECT payment_method, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total
     FROM payments p JOIN orders o ON p.order_id=o.order_id
     WHERE p.verification_status='VALID' $rangeSQL
     GROUP BY payment_method ORDER BY total DESC"
);
$paymentMethods = $res->fetch_all(MYSQLI_ASSOC);
$payMethodTotal = array_sum(array_column($paymentMethods, 'total')) ?: 1;
function methodLabel(string $m): string {
    return match($m) { 'TRANSFER' => 'Bank Transfer', 'OTHER' => 'E-Wallet / Other', default => 'Cash' };
}

// ── Order summary ─────────────────────────────────────────────
$res = $conn->query(
    "SELECT COALESCE(SUM(p.amount),0) AS revenue, COUNT(DISTINCT p.order_id) AS order_count
     FROM payments p JOIN orders o ON p.order_id=o.order_id
     WHERE p.verification_status='VALID' $rangeSQL"
);
$ov = $res->fetch_assoc();
$totalOrders = (int)$ov['order_count'];
$avgOrder    = $totalOrders > 0 ? ((float)$ov['revenue']) / $totalOrders : 0;

$res = $conn->query("SELECT order_status, COUNT(*) AS cnt FROM orders o WHERE 1=1 $rangeSQL GROUP BY order_status ORDER BY cnt DESC");
$orderStatuses = $res->fetch_all(MYSQLI_ASSOC);

// ── Revenue by branch ──────────────────────────────────────────
$res = $conn->query(
    "SELECT b.branch_name, COALESCE(SUM(p.amount),0) AS branch_revenue, COUNT(DISTINCT p.order_id) AS branch_orders
     FROM payments p JOIN orders o ON p.order_id=o.order_id
     JOIN users u ON o.user_id=u.user_id JOIN branches b ON u.branch_id=b.branch_id
     WHERE p.verification_status='VALID' $rangeSQL
     GROUP BY b.branch_id ORDER BY branch_revenue DESC"
);
$branchRows = $res->fetch_all(MYSQLI_ASSOC);

// ── Top products ────────────────────────────────────────────────
$res = $conn->query(
    "SELECT p.product_name, p.category, SUM(oi.quantity) AS total_qty, SUM(oi.quantity*oi.unit_price) AS total_revenue
     FROM order_items oi JOIN products p ON oi.product_id=p.product_id JOIN orders o ON oi.order_id=o.order_id
     WHERE 1=1 $rangeSQL GROUP BY p.product_id ORDER BY total_revenue DESC LIMIT 15"
);
$topProducts = $res->fetch_all(MYSQLI_ASSOC);

// ── Top customers ────────────────────────────────────────────────
$res = $conn->query(
    "SELECT u.name, u.email, COUNT(DISTINCT o.order_id) AS order_count, COALESCE(SUM(p.amount),0) AS total_spent
     FROM payments p JOIN orders o ON p.order_id=o.order_id JOIN users u ON o.user_id=u.user_id
     WHERE p.verification_status='VALID' $rangeSQL
     GROUP BY u.user_id ORDER BY total_spent DESC LIMIT 10"
);
$topCustomers = $res->fetch_all(MYSQLI_ASSOC);

// ── Transaction log (full period, capped) ─────────────────────
$TXN_CAP = 500;
$res = $conn->query("SELECT COUNT(*) AS cnt FROM payments p JOIN orders o ON p.order_id=o.order_id WHERE 1=1 $rangeSQL");
$txnTotalCount = (int)$res->fetch_assoc()['cnt'];

$res = $conn->query(
    "SELECT p.payment_id, p.record_date, p.amount, p.payment_method, p.verification_status,
            o.order_id, COALESCE(u.name,'Walk-in') AS customer_name
     FROM payments p JOIN orders o ON p.order_id=o.order_id
     LEFT JOIN users u ON o.user_id=u.user_id
     WHERE 1=1 $rangeSQL
     ORDER BY p.record_date DESC LIMIT $TXN_CAP"
);
$transactions = $res->fetch_all(MYSQLI_ASSOC);

function verifBadgeClass(string $s): string {
    return match($s) { 'VALID' => 'pay-valid', 'INVALID' => 'pay-invalid', default => 'pay-pending' };
}
function verifLabel(string $s): string {
    return match($s) { 'VALID' => 'Verified', 'INVALID' => 'Rejected', default => 'Pending' };
}

$generatedAt = date('d M Y, H:i');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Report — <?= htmlspecialchars($periodLabel) ?> | StationaryPlus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #A83535;
            --text: #2E2E2E;
            --muted: #707070;
            --border: #E0E0E0;
            --light: #F9F9F9;
        }
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', system-ui, sans-serif; }
        body { background:#f0f0f0; color:var(--text); min-height:100vh; padding:30px 20px; }

        .toolbar { max-width:920px; margin:0 auto 20px; display:flex; align-items:center; gap:10px; }
        .btn { padding:9px 20px; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer;
               display:inline-flex; align-items:center; gap:8px; text-decoration:none; transition:all .2s; border:none; }
        .btn-print { background:var(--primary); color:white; }
        .btn-print:hover { background:#8b2a2a; }
        .toolbar-note { margin-left:auto; font-size:12px; color:var(--muted); }

        .report { max-width:920px; margin:0 auto; background:white; border-radius:10px;
                  box-shadow:0 4px 20px rgba(0,0,0,.08); overflow:hidden; }

        .report-header { background:var(--primary); color:white; padding:28px 40px;
                          display:flex; justify-content:space-between; align-items:flex-start; }
        .brand-name { font-size:22px; font-weight:800; letter-spacing:.5px; }
        .brand-sub { font-size:13px; opacity:.85; margin-top:4px; line-height:1.6; }
        .report-title { text-align:right; }
        .report-title h2 { font-size:18px; font-weight:700; letter-spacing:1px; text-transform:uppercase; }
        .report-title p { font-size:12px; opacity:.85; margin-top:4px; }

        .report-body { padding:0 40px 32px; }
        .section { padding:22px 0; border-bottom:1px solid var(--border); }
        .section:last-child { border-bottom:none; }
        .section-title { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.7px;
                          color:var(--primary); margin-bottom:14px; display:flex; align-items:center; gap:7px; }

        .recon-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; }
        .recon-box { border:1.5px solid var(--border); border-radius:8px; padding:14px 16px; }
        .recon-box .label { font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; margin-bottom:6px; }
        .recon-box .amt { font-size:19px; font-weight:800; }
        .recon-box .sub { font-size:11px; color:var(--muted); margin-top:4px; }
        .recon-valid .amt { color:#059669; }
        .recon-pending .amt { color:#d97706; }
        .recon-invalid .amt { color:#dc2626; }

        table.data-table { width:100%; border-collapse:collapse; font-size:13px; }
        table.data-table thead { background:var(--light); border-bottom:2px solid var(--border); }
        table.data-table th { padding:9px 12px; text-align:left; font-size:11px; font-weight:700;
                               color:var(--muted); text-transform:uppercase; letter-spacing:.4px; }
        table.data-table th.num, table.data-table td.num { text-align:right; }
        table.data-table tbody tr { border-bottom:1px solid var(--border); }
        table.data-table tbody tr:last-child { border-bottom:none; }
        table.data-table td { padding:9px 12px; vertical-align:top; }
        table.data-table td.mono { font-family:monospace; font-size:12px; }

        .grand-total { display:flex; justify-content:space-between; align-items:baseline; padding:16px 14px;
                       background:var(--light); border-radius:8px; margin-top:12px; border:1.5px solid var(--border); }
        .grand-total .label { font-size:14px; font-weight:700; }
        .grand-total .value { font-size:22px; font-weight:800; color:var(--primary); }

        .pay-valid { color:#059669; font-weight:600; }
        .pay-invalid { color:#dc2626; font-weight:600; }
        .pay-pending { color:#d97706; font-weight:600; }

        .txn-note { font-size:12px; color:var(--muted); margin-top:10px; font-style:italic; }

        .sign-grid { display:grid; grid-template-columns:1fr 1fr; gap:40px; margin-top:8px; }
        .sign-line { border-top:1.5px solid var(--text); margin-top:40px; padding-top:6px; font-size:12px; color:var(--muted); }

        .report-footer { background:var(--light); border-top:1px solid var(--border); padding:18px 40px;
                          text-align:center; font-size:12px; color:var(--muted); line-height:1.8; }
        .report-footer strong { color:var(--text); }

        @media print {
            body { background:white; padding:0; }
            .toolbar { display:none !important; }
            .report { box-shadow:none; border-radius:0; max-width:100%; }
            .section { break-inside:avoid; }
        }

        @media (max-width:700px) {
            .report-header { flex-direction:column; gap:14px; }
            .report-title { text-align:left; }
            .recon-grid { grid-template-columns:1fr; }
            .sign-grid { grid-template-columns:1fr; }
            .report-body { padding:0 20px 24px; }
            .report-header { padding:22px 20px; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button class="btn btn-print" onclick="window.print()"><i class="fas fa-file-pdf"></i> Download PDF Report</button>
    <span class="toolbar-note">Tip: choose "Save as PDF" as the destination in the print dialog.</span>
</div>

<div class="report">

    <div class="report-header">
        <div>
            <div class="brand-name">StationaryPlus</div>
            <div class="brand-sub">Stationery &amp; Printing Management System</div>
        </div>
        <div class="report-title">
            <h2>Accounting Report</h2>
            <p><?= htmlspecialchars($periodLabel) ?></p>
            <p>Generated: <?= $generatedAt ?> by <?= htmlspecialchars($generatedBy) ?></p>
        </div>
    </div>

    <div class="report-body">

        <!-- Revenue Reconciliation -->
        <div class="section">
            <div class="section-title"><i class="fas fa-money-bill-wave"></i> Revenue Summary</div>
            <div class="recon-grid">
                <div class="recon-box recon-valid">
                    <div class="label">Verified Revenue</div>
                    <div class="amt">RM <?= number_format($recon['VALID']['total'], 2) ?></div>
                    <div class="sub"><?= $recon['VALID']['cnt'] ?> payment<?= $recon['VALID']['cnt']!=1?'s':'' ?></div>
                </div>
                <div class="recon-box recon-pending">
                    <div class="label">Pending Verification</div>
                    <div class="amt">RM <?= number_format($recon['PENDING']['total'], 2) ?></div>
                    <div class="sub"><?= $recon['PENDING']['cnt'] ?> payment<?= $recon['PENDING']['cnt']!=1?'s':'' ?></div>
                </div>
                <div class="recon-box recon-invalid">
                    <div class="label">Rejected</div>
                    <div class="amt">RM <?= number_format($recon['INVALID']['total'], 2) ?></div>
                    <div class="sub"><?= $recon['INVALID']['cnt'] ?> payment<?= $recon['INVALID']['cnt']!=1?'s':'' ?></div>
                </div>
            </div>
        </div>

        <!-- Payment Methods -->
        <div class="section">
            <div class="section-title"><i class="fas fa-wallet"></i> Payment Method Breakdown</div>
            <?php if (empty($paymentMethods)): ?>
            <p style="font-size:13px;color:var(--muted);">No verified payments in this period.</p>
            <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Method</th><th class="num">Transactions</th><th class="num">Total</th><th class="num">% of Revenue</th></tr></thead>
                <tbody>
                <?php foreach ($paymentMethods as $pm): ?>
                    <tr>
                        <td><?= methodLabel($pm['payment_method']) ?></td>
                        <td class="num"><?= (int)$pm['cnt'] ?></td>
                        <td class="num">RM <?= number_format($pm['total'], 2) ?></td>
                        <td class="num"><?= number_format(($pm['total']/$payMethodTotal)*100, 1) ?>%</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Order Summary -->
        <div class="section">
            <div class="section-title"><i class="fas fa-clipboard-list"></i> Order Summary</div>
            <div class="recon-grid" style="grid-template-columns:repeat(2,1fr);margin-bottom:14px;">
                <div class="recon-box">
                    <div class="label">Total Orders</div>
                    <div class="amt"><?= number_format($totalOrders) ?></div>
                </div>
                <div class="recon-box">
                    <div class="label">Average Order Value</div>
                    <div class="amt">RM <?= number_format($avgOrder, 2) ?></div>
                </div>
            </div>
            <table class="data-table">
                <thead><tr><th>Status</th><th class="num">Count</th></tr></thead>
                <tbody>
                <?php foreach ($orderStatuses as $os): ?>
                    <tr><td><?= ucfirst(strtolower($os['order_status'])) ?></td><td class="num"><?= (int)$os['cnt'] ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Revenue by Branch -->
        <?php if (!empty($branchRows)): ?>
        <div class="section">
            <div class="section-title"><i class="fas fa-store"></i> Revenue by Branch</div>
            <table class="data-table">
                <thead><tr><th>Branch</th><th class="num">Orders</th><th class="num">Revenue</th></tr></thead>
                <tbody>
                <?php foreach ($branchRows as $b): ?>
                    <tr>
                        <td><?= htmlspecialchars($b['branch_name']) ?></td>
                        <td class="num"><?= (int)$b['branch_orders'] ?></td>
                        <td class="num">RM <?= number_format($b['branch_revenue'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Top Products -->
        <?php if (!empty($topProducts)): ?>
        <div class="section">
            <div class="section-title"><i class="fas fa-box"></i> Top Products by Revenue</div>
            <table class="data-table">
                <thead><tr><th>Product</th><th>Category</th><th class="num">Units Sold</th><th class="num">Revenue</th></tr></thead>
                <tbody>
                <?php foreach ($topProducts as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['product_name']) ?></td>
                        <td><?= htmlspecialchars($p['category'] ?? 'Uncategorised') ?></td>
                        <td class="num"><?= (int)$p['total_qty'] ?></td>
                        <td class="num">RM <?= number_format($p['total_revenue'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Top Customers -->
        <?php if (!empty($topCustomers)): ?>
        <div class="section">
            <div class="section-title"><i class="fas fa-users"></i> Top Customers by Spend</div>
            <table class="data-table">
                <thead><tr><th>Customer</th><th>Email</th><th class="num">Orders</th><th class="num">Total Spent</th></tr></thead>
                <tbody>
                <?php foreach ($topCustomers as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['name']) ?></td>
                        <td style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($c['email']) ?></td>
                        <td class="num"><?= (int)$c['order_count'] ?></td>
                        <td class="num">RM <?= number_format($c['total_spent'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Transaction Log -->
        <div class="section">
            <div class="section-title"><i class="fas fa-receipt"></i> Transaction Log</div>
            <?php if (empty($transactions)): ?>
            <p style="font-size:13px;color:var(--muted);">No transactions in this period.</p>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr><th>Payment ID</th><th>Date</th><th>Customer</th><th>Order ID</th><th>Method</th><th class="num">Amount</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td class="mono"><?= htmlspecialchars($t['payment_id']) ?></td>
                        <td><?= date('d M Y', strtotime($t['record_date'])) ?></td>
                        <td><?= htmlspecialchars($t['customer_name']) ?></td>
                        <td class="mono"><?= htmlspecialchars($t['order_id']) ?></td>
                        <td><?= methodLabel($t['payment_method']) ?></td>
                        <td class="num">RM <?= number_format($t['amount'], 2) ?></td>
                        <td class="<?= verifBadgeClass($t['verification_status']) ?>"><?= verifLabel($t['verification_status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($txnTotalCount > $TXN_CAP): ?>
            <p class="txn-note">Showing latest <?= $TXN_CAP ?> of <?= number_format($txnTotalCount) ?> transactions in this period — narrow the date range on the Reports page for a complete listing.</p>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Grand Total -->
        <div class="grand-total">
            <span class="label">Total Verified Revenue (<?= htmlspecialchars($periodLabel) ?>)</span>
            <span class="value">RM <?= number_format($totalRevenue, 2) ?></span>
        </div>

        <!-- Signatures -->
        <div class="section">
            <div class="sign-grid">
                <div class="sign-line">Prepared by</div>
                <div class="sign-line">Reviewed / Approved by</div>
            </div>
        </div>

    </div>

    <div class="report-footer">
        <strong>Confidential — Internal Use Only</strong><br>
        This is a system-generated accounting report for internal record-keeping purposes.<br>
        Printed on <?= $generatedAt ?> &nbsp;·&nbsp; StationaryPlus Management System
    </div>

</div>

</body>
</html>
