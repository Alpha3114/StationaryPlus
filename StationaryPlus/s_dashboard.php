<?php
// ============================================================
//  s_dashboard.php — Staff Dashboard
//
//  Refactored: preorder stat now queries the unified orders
//  table with order_type='PREORDER'. All other queries unchanged.
// ============================================================
 
if (session_status() === PHP_SESSION_NONE) session_start();
 
require_once 'auth.php';
require_role(['STAFF','ADMIN']);
require_once 'db.php';
 
$userName = $_SESSION['user_name'];
$branchId = $_SESSION['branch_id'] ?? null;
 
// ── Stat 1: Confirmed orders to process (NEW or PROCESSING) ───
$stmt = $branchId
    ? $conn->prepare("SELECT COUNT(*) AS cnt FROM orders WHERE order_type = 'ORDER' AND order_status IN ('NEW','PROCESSING') AND branch_id = ?")
    : $conn->prepare("SELECT COUNT(*) AS cnt FROM orders WHERE order_type = 'ORDER' AND order_status IN ('NEW','PROCESSING')");
if ($branchId) $stmt->bind_param('s', $branchId);
$stmt->execute();
$ordersToProcess = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmt->close();

$stmt = $branchId
    ? $conn->prepare("SELECT COUNT(*) AS cnt FROM orders WHERE order_type = 'ORDER' AND order_status = 'NEW' AND DATE(order_date) = CURDATE() AND branch_id = ?")
    : $conn->prepare("SELECT COUNT(*) AS cnt FROM orders WHERE order_type = 'ORDER' AND order_status = 'NEW' AND DATE(order_date) = CURDATE()");
if ($branchId) $stmt->bind_param('s', $branchId);
$stmt->execute();
$newToday = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmt->close();
 
// ── Stat 2: Low stock alerts (unchanged) ──────────────────────
$branchSQL = $branchId ? "AND branch_id = ?" : '';
 
$stmt = $branchId
    ? $conn->prepare("SELECT COUNT(*) AS cnt FROM inventory WHERE stock_quantity <= minimum_level AND branch_id = ?")
    : $conn->prepare("SELECT COUNT(*) AS cnt FROM inventory WHERE stock_quantity <= minimum_level");
if ($branchId) $stmt->bind_param('s', $branchId);
$stmt->execute();
$lowStockCount = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmt->close();
 
$stmt = $branchId
    ? $conn->prepare("SELECT COUNT(*) AS cnt FROM inventory WHERE stock_quantity = 0 AND branch_id = ?")
    : $conn->prepare("SELECT COUNT(*) AS cnt FROM inventory WHERE stock_quantity = 0");
if ($branchId) $stmt->bind_param('s', $branchId);
$stmt->execute();
$criticalCount = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmt->close();
 
// ── Stat 3: Pending payments to verify (unchanged) ────────────
$stmt = $branchId
    ? $conn->prepare("SELECT COUNT(*) AS cnt FROM payments p JOIN orders o ON p.order_id = o.order_id WHERE p.verification_status = 'PENDING' AND o.branch_id = ?")
    : $conn->prepare("SELECT COUNT(*) AS cnt FROM payments WHERE verification_status = 'PENDING'");
if ($branchId) $stmt->bind_param('s', $branchId);
$stmt->execute();
$pendingPayments = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmt->close();
 
// ── Stat 4: Pre-orders awaiting staff action ──────────────────
//  Was: SELECT COUNT(*) FROM preorders WHERE order_status='SUBMITTED'
//  Now: unified table, order_type='PREORDER', initial status is 'NEW'
$stmt = $branchId
    ? $conn->prepare("SELECT COUNT(*) AS cnt FROM orders WHERE order_type = 'PREORDER' AND order_status = 'NEW' AND branch_id = ?")
    : $conn->prepare("SELECT COUNT(*) AS cnt FROM orders WHERE order_type = 'PREORDER' AND order_status = 'NEW'");
if ($branchId) $stmt->bind_param('s', $branchId);
$stmt->execute();
$pendingPreorders = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmt->close();
 
// ── Recent confirmed orders (last 8) ─────────────────────────
$stmt = $conn->prepare(
    "SELECT
         o.order_id,
         o.order_date,
         o.order_status,
         o.estimated_total,
         u.name AS customer_name,
         COUNT(oi.order_item_id) AS item_count
     FROM orders o
     JOIN users u ON o.user_id = u.user_id
     LEFT JOIN order_items oi ON o.order_id = oi.order_id
     WHERE o.order_type = 'ORDER'
       " . ($branchId ? "AND o.branch_id = ?" : "") . "
     GROUP BY o.order_id
     ORDER BY o.order_date DESC
     LIMIT 8"
);
if ($branchId) $stmt->bind_param('s', $branchId);
$stmt->execute();
$recentOrders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
 
// ── Low stock items (top 5) — unchanged ──────────────────────
if ($branchId) {
    $stmt = $conn->prepare(
        "SELECT p.product_name, p.category, i.stock_quantity, i.minimum_level, b.branch_name
         FROM inventory i
         JOIN products p ON i.product_id = p.product_id
         JOIN branches b ON i.branch_id  = b.branch_id
         WHERE i.stock_quantity <= i.minimum_level AND i.branch_id = ?
         ORDER BY i.stock_quantity ASC
         LIMIT 5"
    );
    $stmt->bind_param('s', $branchId);
} else {
    $stmt = $conn->prepare(
        "SELECT p.product_name, p.category, i.stock_quantity, i.minimum_level, b.branch_name
         FROM inventory i
         JOIN products p ON i.product_id = p.product_id
         JOIN branches b ON i.branch_id  = b.branch_id
         WHERE i.stock_quantity <= i.minimum_level
         ORDER BY i.stock_quantity ASC
         LIMIT 5"
    );
}
$stmt->execute();
$lowStockItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
 
// ── Status badge (unchanged) ──────────────────────────────────
function orderStatusBadge(string $status): string {
    $map = [
        'NEW'        => ['#3b82f6','#eff6ff','New'],
        'PROCESSING' => ['#f59e0b','#fffbeb','Processing'],
        'READY'      => ['#10b981','#ecfdf5','Ready'],
        'COLLECTED'  => ['#6b7280','#f3f4f6','Collected'],
        'CANCELLED'  => ['#ef4444','#fef2f2','Cancelled'],
    ];
    [$color, $bg, $label] = $map[$status] ?? ['#888','#f3f4f6', $status];
    return "<span style='background:$bg;color:$color;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;'>$label</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus — Staff Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #A83535;
            --secondary: #F4A261;
            --accent: #F1EDE8;
            --background: #FAFAFA;
            --text-primary: #2E2E2E;
            --text-secondary: #707070;
            --border: #E0E0E0;
            --white: #FFFFFF;
            --sidebar-width: 260px;
            --card-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        * { margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',system-ui,sans-serif; }

        body { background-color:var(--background);color:var(--text-primary);min-height:100vh;display:flex; }

        /* ── Sidebar ── */
        .sidebar { width:var(--sidebar-width);background-color:var(--white);border-right:1px solid var(--border);height:100vh;position:fixed;left:0;top:0;display:flex;flex-direction:column;box-shadow:2px 0 10px rgba(0,0,0,0.03);overflow-y:auto; }
        .logo-area { padding:25px;border-bottom:1px solid var(--border);display:flex;align-items:center;flex-shrink:0; }
        .logo-icon { background-color:var(--primary);width:40px;height:40px;border-radius:8px;display:flex;align-items:center;justify-content:center;margin-right:12px;color:white;font-size:20px; }
        .logo-text { font-size:22px;font-weight:700;color:var(--primary); }
        .nav-section { padding:20px 0;border-bottom:1px solid var(--border); }
        .nav-title { font-size:12px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.8px;padding:0 25px 10px 25px; }
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
        .logout-link { display:flex;align-items:center;gap:10px;padding:10px 14px;background-color:rgba(168,53,53,0.06);color:var(--primary);border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;transition:background-color 0.2s; }
        .logout-link:hover { background-color:rgba(168,53,53,0.14); }

        /* ── Main ── */
        .main-content { flex-grow:1;margin-left:var(--sidebar-width);min-height:100vh;display:flex;flex-direction:column; }
        .top-header { background-color:var(--white);padding:20px 30px;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:10; }
        .page-title { font-size:24px;font-weight:700;color:var(--text-primary); }
        .page-subtitle { font-size:14px;color:var(--text-secondary);margin-top:4px; }

        .dashboard-content { padding:28px 30px;flex-grow:1;display:flex;flex-direction:column;gap:26px; }

        /* ── Welcome ── */
        .welcome-card { background:linear-gradient(135deg,rgba(168,53,53,0.06) 0%,rgba(244,162,97,0.06) 100%);border-radius:12px;padding:22px 28px;border-left:5px solid var(--primary); }
        .welcome-title { font-size:22px;font-weight:700;color:var(--text-primary);margin-bottom:6px; }
        .welcome-sub { font-size:14px;color:var(--text-secondary);line-height:1.6; }

        /* ── Stat cards ── */
        .stats-grid { display:grid;grid-template-columns:repeat(4,1fr);gap:18px; }
        .stat-card { background-color:var(--white);border-radius:10px;padding:20px;box-shadow:var(--card-shadow);border:1px solid var(--border);transition:transform 0.2s; }
        .stat-card:hover { transform:translateY(-2px); }
        .stat-header { display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px; }
        .stat-label { font-size:13px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.4px; }
        .stat-icon { width:38px;height:38px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:17px; }
        .icon-red    { background:rgba(168,53,53,0.1);color:var(--primary); }
        .icon-orange { background:rgba(244,162,97,0.15);color:#d97706; }
        .icon-blue   { background:rgba(59,130,246,0.1);color:#3b82f6; }
        .icon-green  { background:rgba(16,185,129,0.1);color:#10b981; }
        .stat-value { font-size:30px;font-weight:700;color:var(--primary);margin-bottom:4px; }
        .stat-footer { font-size:12px;color:var(--text-secondary);display:flex;align-items:center;gap:5px; }

        /* ── Quick action cards ── */
        .actions-grid { display:grid;grid-template-columns:repeat(3,1fr);gap:18px; }
        .action-card { background-color:var(--white);border-radius:10px;padding:20px;box-shadow:var(--card-shadow);border:1px solid var(--border);text-align:center;text-decoration:none;transition:all 0.2s;display:block; }
        .action-card:hover { transform:translateY(-3px);box-shadow:0 8px 20px rgba(0,0,0,0.09);border-color:var(--primary); }
        .action-icon { width:52px;height:52px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:22px; }
        .action-title { font-size:15px;font-weight:700;color:var(--text-primary);margin-bottom:5px; }
        .action-desc { font-size:13px;color:var(--text-secondary); }

        /* ── Two-column bottom ── */
        .bottom-grid { display:grid;grid-template-columns:1fr 340px;gap:22px; }

        /* ── Table card ── */
        .table-card { background-color:var(--white);border-radius:10px;box-shadow:var(--card-shadow);border:1px solid var(--border);overflow:hidden; }
        .card-header { padding:18px 22px;border-bottom:1px solid var(--border);background:rgba(168,53,53,0.03);display:flex;justify-content:space-between;align-items:center; }
        .card-title { font-size:16px;font-weight:700;color:var(--primary);display:flex;align-items:center;gap:8px; }
        .card-link { font-size:13px;color:var(--primary);text-decoration:none;font-weight:600; }
        .card-link:hover { text-decoration:underline; }
        .data-table { width:100%;border-collapse:collapse; }
        .data-table thead { background:rgba(168,53,53,0.03);border-bottom:2px solid var(--border); }
        .data-table th { padding:11px 18px;text-align:left;font-size:11px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.4px;white-space:nowrap; }
        .data-table tbody tr { border-bottom:1px solid var(--border);transition:background 0.15s; }
        .data-table tbody tr:last-child { border-bottom:none; }
        .data-table tbody tr:hover { background:rgba(168,53,53,0.02); }
        .data-table td { padding:13px 18px;font-size:13px;color:var(--text-primary);vertical-align:middle; }
        .order-id { font-weight:700;color:var(--primary);font-family:monospace;font-size:12px; }
        .process-btn { padding:6px 14px;background:var(--primary);color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;transition:background 0.2s; }
        .process-btn:hover { background:#8b2a2a; }

        /* ── Low stock panel ── */
        .stock-panel { background-color:var(--white);border-radius:10px;box-shadow:var(--card-shadow);border:1px solid var(--border);overflow:hidden; }
        .stock-item { padding:14px 18px;border-bottom:1px solid var(--border);display:flex;flex-direction:column;gap:6px; }
        .stock-item:last-child { border-bottom:none; }
        .stock-name { font-size:13px;font-weight:600;color:var(--text-primary); }
        .stock-meta { font-size:12px;color:var(--text-secondary); }
        .stock-bar-wrap { height:6px;background:var(--border);border-radius:3px;overflow:hidden; }
        .stock-bar { height:100%;border-radius:3px; }
        .bar-critical { background:#ef4444; }
        .bar-low      { background:#f59e0b; }
        .empty-state { padding:40px 20px;text-align:center;color:var(--text-secondary);font-size:14px; }
        .empty-state i { font-size:36px;opacity:0.2;margin-bottom:10px;display:block; }

        /* Footer */
        .page-footer { text-align:center;padding:20px;color:var(--text-secondary);font-size:13px;border-top:1px solid var(--border);background-color:var(--white);margin-top:auto; }

        @media (max-width:1200px) { .stats-grid { grid-template-columns:repeat(2,1fr); } .bottom-grid { grid-template-columns:1fr; } }
        @media (max-width:1024px) {
            :root { --sidebar-width:70px; }
            .logo-text,.nav-text,.user-details,.nav-title,.logout-link span { display:none; }
            .logo-area,.nav-section,.user-section { padding:18px 12px; }
            .nav-link { justify-content:center;padding:14px;border-left:none;border-right:4px solid transparent; }
            .nav-link:hover,.nav-link.active { border-left:none;border-right-color:var(--primary); }
            .nav-icon { margin-right:0;font-size:20px; }
            .logout-link { justify-content:center;padding:10px; }
        }
        @media (max-width:768px) { .stats-grid { grid-template-columns:repeat(2,1fr); } .actions-grid { grid-template-columns:repeat(2,1fr); } }
    </style>
</head>
<body>

<?php include 'smart_sidebar.php'; ?>

<main class="main-content">

    <header class="top-header">
        <h1 class="page-title">Staff Dashboard</h1>
        <p class="page-subtitle">Manage orders, inventory and alerts for your branch</p>
    </header>

    <div class="dashboard-content">

        <!-- Welcome -->
        <div class="welcome-card">
            <div class="welcome-title">Welcome, <?= htmlspecialchars($userName) ?>!</div>
            <div class="welcome-sub">
                You have <strong><?= $ordersToProcess ?></strong> order<?= $ordersToProcess !== 1 ? 's' : '' ?> to process,
                <strong><?= $lowStockCount ?></strong> low stock alert<?= $lowStockCount !== 1 ? 's' : '' ?>,
                and <strong><?= $pendingPayments ?></strong> payment<?= $pendingPayments !== 1 ? 's' : '' ?> awaiting verification.
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-label">Orders to Process</div>
                    <div class="stat-icon icon-red"><i class="fas fa-clipboard-list"></i></div>
                </div>
                <div class="stat-value"><?= $ordersToProcess ?></div>
                <div class="stat-footer"><i class="fas fa-clock"></i> <?= $newToday ?> new today</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-label">Low Stock Alerts</div>
                    <div class="stat-icon icon-orange"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
                <div class="stat-value"><?= $lowStockCount ?></div>
                <div class="stat-footer"><i class="fas fa-times-circle"></i> <?= $criticalCount ?> out of stock</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-label">Pending Payments</div>
                    <div class="stat-icon icon-blue"><i class="fas fa-file-invoice-dollar"></i></div>
                </div>
                <div class="stat-value"><?= $pendingPayments ?></div>
                <div class="stat-footer"><i class="fas fa-hourglass-half"></i> Awaiting verification</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-label">Pending Pre-orders</div>
                    <div class="stat-icon icon-green"><i class="fas fa-clipboard-check"></i></div>
                </div>
                <div class="stat-value"><?= $pendingPreorders ?></div>
                <div class="stat-footer"><i class="fas fa-user-clock"></i> Submitted by customers</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="actions-grid">
            <a href="s_ordermanagement.php" class="action-card">
                <div class="action-icon" style="background:rgba(168,53,53,0.1);color:var(--primary);">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="action-title">Manage Orders</div>
                <div class="action-desc">View, process and update customer orders</div>
            </a>
            <a href="s_inv.php" class="action-card">
                <div class="action-icon" style="background:rgba(16,185,129,0.1);color:#10b981;">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="action-title">Inventory</div>
                <div class="action-desc">Check stock levels and update quantities</div>
            </a>
            <a href="s_ordermanagement.php?filter=preorder" class="action-card">
                <div class="action-icon" style="background:rgba(244,162,97,0.15);color:#d97706;">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div class="action-title">Pre-orders</div>
                <div class="action-desc">Review and action submitted pre-orders</div>
            </a>
        </div>

        <!-- Bottom: Orders table + Low stock -->
        <div class="bottom-grid">

            <!-- Recent Orders -->
            <div class="table-card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-history"></i> Recent Orders</div>
                    <a href="s_ordermanagement.php" class="card-link">View all &rarr;</a>
                </div>
                <?php if (empty($recentOrders)): ?>
                    <div class="empty-state"><i class="fas fa-clipboard-list"></i>No orders yet.</div>
                <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $o): ?>
                        <tr>
                            <td><span class="order-id"><?= htmlspecialchars($o['order_id']) ?></span></td>
                            <td><?= htmlspecialchars($o['customer_name']) ?></td>
                            <td style="color:var(--text-secondary);font-size:12px;"><?= date('d M', strtotime($o['order_date'])) ?></td>
                            <td style="color:var(--text-secondary);"><?= $o['item_count'] ?> item<?= $o['item_count'] != 1 ? 's' : '' ?></td>
                            <td style="font-weight:600;">RM <?= number_format($o['estimated_total'], 2) ?></td>
                            <td><?= orderStatusBadge($o['order_status']) ?></td>
                            <td>
                                <a href="s_ordermanagement.php?order_id=<?= urlencode($o['order_id']) ?>" class="process-btn">
                                    <?= $o['order_status'] === 'NEW' ? 'Process' : 'View' ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- Low Stock Panel -->
            <div class="stock-panel">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-exclamation-triangle"></i> Low Stock</div>
                    <a href="s_inv.php?filter=low" class="card-link">View all &rarr;</a>
                </div>
                <?php if (empty($lowStockItems)): ?>
                    <div class="empty-state"><i class="fas fa-check-circle"></i>All stock levels are healthy.</div>
                <?php else: ?>
                    <?php foreach ($lowStockItems as $item):
                        $pct = $item['minimum_level'] > 0
                            ? min(100, round(($item['stock_quantity'] / $item['minimum_level']) * 100))
                            : 0;
                        $barClass = $item['stock_quantity'] == 0 ? 'bar-critical' : 'bar-low';
                    ?>
                    <div class="stock-item">
                        <div class="stock-name"><?= htmlspecialchars($item['product_name']) ?></div>
                        <div class="stock-meta">
                            <?= htmlspecialchars($item['branch_name']) ?> &bull;
                            Stock: <strong><?= $item['stock_quantity'] ?></strong> /
                            Min: <?= $item['minimum_level'] ?>
                        </div>
                        <div class="stock-bar-wrap">
                            <div class="stock-bar <?= $barClass ?>" style="width:<?= $pct ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div><!-- /.bottom-grid -->

    </div><!-- /.dashboard-content -->

    <footer class="page-footer">
        &copy; <?= date('Y') ?> StationaryPlus &mdash; Stationery &amp; Printing Management System
    </footer>

</main>

</body>
</html>