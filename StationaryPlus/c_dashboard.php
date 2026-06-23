<?php
// ============================================================
//  c_dashboard.php — Customer Dashboard
//
//  Refactored: all order/preorder queries now hit the unified
//  orders table filtered by order_type. Two queries replaced
//  with one wherever possible.
// ============================================================
 
if (session_status() === PHP_SESSION_NONE) session_start();
 
require_once 'auth.php';
require_role('CUSTOMER');
require_once 'db.php';
 
$userId = $_SESSION['user_id'];
 
// Branch name (unchanged)
$currentBranch = null;
if (!empty($_SESSION['branch_id'])) {
    $stmt = $conn->prepare(
        "SELECT branch_name FROM branches WHERE branch_id = ? LIMIT 1"
    );
    $stmt->bind_param('s', $_SESSION['branch_id']);
    $stmt->execute();
    $currentBranch = $stmt->get_result()->fetch_assoc()['branch_name'] ?? null;
    $stmt->close();
}
 
// ── 1. Active confirmed orders ────────────────────────────────
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS cnt FROM orders
     WHERE user_id = ?
       AND order_type = 'ORDER'
       AND order_status NOT IN ('CANCELLED','COLLECTED')"
);
$stmt->bind_param('s', $userId);
$stmt->execute();
$activeOrders = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmt->close();
 
// ── 2. Pending pre-orders (customer waiting on staff) ─────────
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS cnt FROM orders
     WHERE user_id = ?
       AND order_type = 'PREORDER'
       AND order_status IN ('NEW','PROCESSING')"
);
$stmt->bind_param('s', $userId);
$stmt->execute();
$pendingPreorders = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmt->close();
 
// ── 3. Uploaded print files ───────────────────────────────────
//  print_files now references orders.order_id directly
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS cnt
     FROM print_files pf
     JOIN orders o ON pf.order_id = o.order_id
     WHERE o.user_id = ?"
);
$stmt->bind_param('s', $userId);
$stmt->execute();
$uploadedFiles = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmt->close();
 
// ── 4. Pending payments total ─────────────────────────────────
//  Single JOIN — payments.order_id is now the only FK
$stmt = $conn->prepare(
    "SELECT COALESCE(SUM(p.amount), 0) AS total
     FROM payments p
     JOIN orders o ON p.order_id = o.order_id
     WHERE p.verification_status = 'PENDING'
       AND o.user_id = ?"
);
$stmt->bind_param('s', $userId);
$stmt->execute();
$pendingPayment = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();
 
// ── 5. Recent confirmed orders (last 5) ───────────────────────
$stmt = $conn->prepare(
    "SELECT order_id, order_date, order_status, estimated_total
     FROM orders
     WHERE user_id = ?
       AND order_type = 'ORDER'
     ORDER BY order_date DESC
     LIMIT 5"
);
$stmt->bind_param('s', $userId);
$stmt->execute();
$recentOrders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
 
// ── 6. Recent pre-orders (last 3) ────────────────────────────
$stmt = $conn->prepare(
    "SELECT order_id AS preorder_id, order_date, order_status, notes, estimated_total
     FROM orders
     WHERE user_id = ?
       AND order_type = 'PREORDER'
     ORDER BY order_date DESC
     LIMIT 3"
);
$stmt->bind_param('s', $userId);
$stmt->execute();
$recentPreorders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
 
// ── Status badge helpers ──────────────────────────────────────
function orderStatusBadge(string $status): string {
    $map = [
        'NEW'        => ['#3b82f6', '#eff6ff', 'New'],
        'PROCESSING' => ['#f59e0b', '#fffbeb', 'Processing'],
        'READY'      => ['#10b981', '#ecfdf5', 'Ready'],
        'COLLECTED'  => ['#6b7280', '#f3f4f6', 'Collected'],
        'CANCELLED'  => ['#ef4444', '#fef2f2', 'Cancelled'],
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
    <title>StationaryPlus — Customer Dashboard</title>
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

        /* Icon fix: no ::before/::after */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        body {
            background-color: var(--background);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
        }

        /* ── Sidebar ── */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--white);
            border-right: 1px solid var(--border);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            display: flex;
            flex-direction: column;
            box-shadow: 2px 0 10px rgba(0,0,0,0.03);
            overflow-y: auto;
        }

        .logo-area {
            padding: 25px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            flex-shrink: 0;
        }

        .logo-icon {
            background-color: var(--primary);
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            color: white;
            font-size: 20px;
        }

        .logo-text {
            font-size: 22px;
            font-weight: 700;
            color: var(--primary);
        }

        .nav-section {
            padding: 25px 0;
            border-bottom: 1px solid var(--border);
        }

        .nav-title {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 0 25px 12px 25px;
        }

        .nav-menu { list-style: none; }

        .nav-item { margin-bottom: 2px; }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 13px 25px;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.2s ease;
            border-left: 4px solid transparent;
        }

        .nav-link:hover {
            background-color: rgba(168,53,53,0.05);
            color: var(--primary);
            border-left-color: rgba(168,53,53,0.3);
        }

        .nav-link.active {
            background-color: rgba(168,53,53,0.08);
            color: var(--primary);
            border-left-color: var(--primary);
            font-weight: 600;
        }

        .nav-icon {
            width: 22px;
            text-align: center;
            margin-right: 14px;
            font-size: 16px;
        }

        .nav-text { font-size: 15px; }

        .user-section {
            margin-top: auto;
            padding: 20px 25px;
            border-top: 1px solid var(--border);
        }

        .user-info {
            display: flex;
            align-items: center;
            margin-bottom: 14px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(168,53,53,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-weight: 700;
            font-size: 16px;
            margin-right: 12px;
            flex-shrink: 0;
        }

        .user-name {
            font-weight: 600;
            font-size: 15px;
            color: var(--text-primary);
        }

        .user-role {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 2px;
        }

        .logout-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            background-color: rgba(168,53,53,0.06);
            color: var(--primary);
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: background-color 0.2s ease;
        }

        .logout-link:hover { background-color: rgba(168,53,53,0.14); }

        /* ── Main content ── */
        .main-content {
            flex-grow: 1;
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .top-header {
            background-color: var(--white);
            padding: 20px 30px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .page-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .page-subtitle {
            font-size: 14px;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .dashboard-content {
            padding: 30px;
            flex-grow: 1;
        }

        /* Welcome card */
        .welcome-card {
            background: linear-gradient(135deg, rgba(168,53,53,0.06) 0%, rgba(244,162,97,0.06) 100%);
            border-radius: 12px;
            padding: 28px 30px;
            margin-bottom: 28px;
            border-left: 5px solid var(--primary);
        }

        .welcome-title {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .welcome-subtitle {
            font-size: 15px;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* Stats grid */
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 36px;
        }

        .stat-card {
            background-color: var(--white);
            border-radius: 10px;
            padding: 22px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            transition: transform 0.2s ease;
        }

        .stat-card:hover { transform: translateY(-3px); }

        .stat-title {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .stat-value {
            font-size: 30px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 4px;
        }

        .stat-detail { font-size: 13px; color: var(--text-secondary); }

        /* Section */
        .content-section { margin-bottom: 36px; }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i { color: var(--primary); }

        /* Quick actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 16px;
        }

        .action-card {
            background-color: var(--white);
            border-radius: 10px;
            padding: 22px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            text-align: center;
            text-decoration: none;
            transition: all 0.2s ease;
            display: block;
        }

        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border-color: var(--primary);
        }

        .action-icon {
            width: 54px;
            height: 54px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 22px;
        }

        .action-icon.red    { background-color: rgba(168,53,53,0.1);  color: var(--primary); }
        .action-icon.orange { background-color: rgba(244,162,97,0.1); color: var(--secondary); }
        .action-icon.green  { background-color: rgba(16,185,129,0.1); color: #10b981; }
        .action-icon.blue   { background-color: rgba(59,130,246,0.1); color: #3b82f6; }

        .action-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .action-desc { font-size: 13px; color: var(--text-secondary); }

        /* Orders table */
        .table-card {
            background-color: var(--white);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead {
            background-color: rgba(168,53,53,0.04);
            border-bottom: 2px solid var(--border);
        }

        .data-table th {
            padding: 14px 20px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .data-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background-color 0.15s ease;
        }

        .data-table tbody tr:last-child { border-bottom: none; }

        .data-table tbody tr:hover { background-color: rgba(168,53,53,0.02); }

        .data-table td {
            padding: 16px 20px;
            font-size: 14px;
            color: var(--text-primary);
            vertical-align: middle;
        }

        .order-id {
            font-weight: 600;
            color: var(--primary);
            font-family: monospace;
            font-size: 13px;
        }

        .empty-row td {
            text-align: center;
            padding: 30px;
            color: var(--text-secondary);
            font-style: italic;
        }

        /* Footer */
        .dashboard-footer {
            text-align: center;
            padding: 22px;
            color: var(--text-secondary);
            font-size: 13px;
            border-top: 1px solid var(--border);
            background-color: var(--white);
        }

        .footer-links { margin-top: 8px; }
        .footer-links a { color: var(--primary); text-decoration: none; margin: 0 10px; }
        .footer-links a:hover { text-decoration: underline; }

        /* Responsive */
        @media (max-width: 1024px) {
            :root { --sidebar-width: 70px; }
            .logo-text, .nav-text, .user-details, .nav-title, .logout-link span { display: none; }
            .logo-area, .nav-section, .user-section { padding: 18px 12px; }
            .nav-link { justify-content: center; padding: 14px; border-left: none; border-right: 4px solid transparent; }
            .nav-link:hover, .nav-link.active { border-left: none; border-right-color: var(--primary); }
            .nav-icon { margin-right: 0; font-size: 20px; }
            .logout-link { justify-content: center; padding: 10px; }
        }

        @media (max-width: 768px) {
            .dashboard-stats { grid-template-columns: repeat(2, 1fr); }
            .quick-actions   { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<?php include 'c_sidebar.php'; ?>

<main class="main-content">

    <!-- Top header -->
    <header class="top-header">
    <div>
        <h1 class="page-title">Customer Dashboard</h1>
        <p class="page-subtitle">Manage your stationery and printing needs</p>
    </div>
    <!-- Branch dropdown panel -->
<div style="position:relative;" id="branchDropdownWrapper">

    <!-- Trigger pill -->
    <button type="button" onclick="toggleBranchPanel()"
            style="display:flex;align-items:center;gap:8px;padding:8px 14px;
                   border:1.5px solid var(--border);border-radius:20px;font-size:13px;
                   font-weight:600;color:var(--primary);background:rgba(168,53,53,0.06);
                   cursor:pointer;outline:none;">
        <i class="fas fa-store"></i>
        <span><?= htmlspecialchars($currentBranch ?? 'Select Branch') ?></span>
        <?php if ($isPreferred): ?>
            <i class="fas fa-star" style="color:#f59e0b;font-size:11px;"></i>
        <?php endif; ?>
        <i class="fas fa-chevron-down" style="font-size:11px;opacity:0.6;"></i>
    </button>

    <!-- Dropdown panel -->
<div id="branchPanel"
     style="display:none;position:absolute;top:calc(100% + 8px);right:0;
            background:white;border:1.5px solid var(--border);border-radius:12px;
            box-shadow:0 8px 24px rgba(0,0,0,0.1);padding:16px;min-width:240px;z-index:100;
            max-height:360px;overflow-y:auto;">
        <form method="POST" action="c_dashboard.php">
            <input type="hidden" name="switch_branch" value="1">
             <input type="hidden" name="branch_id" value="<?= htmlspecialchars($activeBranchId ?? '') ?>">

            <p style="font-size:11px;font-weight:600;color:var(--text-secondary);
                      text-transform:uppercase;letter-spacing:0.6px;margin-bottom:10px;">
                Select Branch
            </p>

            <!-- Branch options as radio buttons styled as pills -->
            <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:14px;">
                <?php foreach ($branchList as $b): ?>
                <label style="display:flex;align-items:center;gap:10px;padding:9px 12px;
                              border-radius:8px;cursor:pointer;border:1.5px solid var(--border);
                              transition:all 0.15s;font-size:13px;font-weight:500;
                              <?= ($activeBranchId === $b['branch_id']) ? 'background:rgba(168,53,53,0.08);border-color:var(--primary);color:var(--primary);' : 'color:var(--text-primary);' ?>">
                    <input type="radio" name="branch_id" value="<?= htmlspecialchars($b['branch_id']) ?>"
                           <?= ($activeBranchId === $b['branch_id']) ? 'checked' : '' ?>
                           onchange="this.form.submit()"
                           style="accent-color:var(--primary);">
                    <?= htmlspecialchars($b['branch_name']) ?>
                </label>
                <?php endforeach; ?>
            </div>

            <hr style="border:none;border-top:1px solid var(--border);margin-bottom:12px;">

            <!-- Remember checkbox -->
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:var(--text-secondary);">
                <input type="checkbox" name="remember_branch" value="1"
                       <?= $isPreferred ? 'checked' : '' ?>
                       onchange="this.form.submit()"
                       style="accent-color:#f59e0b;width:15px;height:15px;">
                <i class="fas fa-star" style="color:#f59e0b;font-size:13px;"></i>
                Remember as preferred branch
            </label>

        </form>
    </div>
</div>

<script>
function toggleBranchPanel() {
    const panel = document.getElementById('branchPanel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}
// Close panel when clicking outside
document.addEventListener('click', function(e) {
    const wrapper = document.getElementById('branchDropdownWrapper');
    if (!wrapper.contains(e.target)) {
        document.getElementById('branchPanel').style.display = 'none';
    }
});
</script>
</header>

    <div class="dashboard-content">

        <!-- Welcome -->
        <div class="welcome-card">
            <h2 class="welcome-title">Welcome back, <?= htmlspecialchars($userName) ?>!</h2>
            <p class="welcome-subtitle">
                You have <strong><?= $activeOrders ?></strong> active order(s) and
                <strong><?= $pendingPreorders ?></strong> pending pre-order(s).
                Use the sidebar to browse products, place pre-orders, upload files, and track your orders.
            </p>
        </div>

        <!-- Stats -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-title"><i class="fas fa-clipboard-list"></i> Active Orders</div>
                <div class="stat-value"><?= $activeOrders ?></div>
                <div class="stat-detail">Orders in progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-title"><i class="fas fa-clock"></i> Pending Pre-orders</div>
                <div class="stat-value"><?= $pendingPreorders ?></div>
                <div class="stat-detail">Awaiting processing</div>
            </div>
            <div class="stat-card">
                <div class="stat-title"><i class="fas fa-file-upload"></i> Uploaded Files</div>
                <div class="stat-value"><?= $uploadedFiles ?></div>
                <div class="stat-detail">Print files submitted</div>
            </div>
            <div class="stat-card">
                <div class="stat-title"><i class="fas fa-receipt"></i> Payment Pending</div>
                <div class="stat-value">RM <?= number_format($pendingPayment, 2) ?></div>
                <div class="stat-detail">Awaiting verification</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="content-section">
            <h3 class="section-title"><i class="fas fa-bolt"></i> Quick Actions</h3>
            <div class="quick-actions">
                <a href="c_viewproducts.php" class="action-card">
                    <div class="action-icon red"><i class="fas fa-box-open"></i></div>
                    <div class="action-title">Browse Products</div>
                    <div class="action-desc">View available stationery items</div>
                </a>
                <a href="c_preorder.php" class="action-card">
                    <div class="action-icon orange"><i class="fas fa-clipboard-check"></i></div>
                    <div class="action-title">Pre-order</div>
                    <div class="action-desc">Reserve out-of-stock items</div>
                </a>
                <a href="c_upload.php" class="action-card">
                    <div class="action-icon green"><i class="fas fa-upload"></i></div>
                    <div class="action-title">Upload Files</div>
                    <div class="action-desc">Submit files for printing</div>
                </a>
                <a href="c_payment.php" class="action-card">
                    <div class="action-icon blue"><i class="fas fa-receipt"></i></div>
                    <div class="action-title">Payment Record</div>
                    <div class="action-desc">Submit payment proof</div>
                </a>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="content-section">
            <h3 class="section-title"><i class="fas fa-shopping-bag"></i> Recent Orders</h3>
            <div class="table-card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Total (RM)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentOrders)): ?>
                            <tr class="empty-row">
                                <td colspan="4">No orders yet. <a href="c_viewproducts.php" style="color:var(--primary);">Browse products</a> to get started.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td><span class="order-id"><?= htmlspecialchars($order['order_id']) ?></span></td>
                                    <td><?= date('d M Y', strtotime($order['order_date'])) ?></td>
                                    <td>RM <?= number_format($order['estimated_total'], 2) ?></td>
                                    <td><?= orderStatusBadge($order['order_status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Pre-orders -->
        <div class="content-section">
            <h3 class="section-title"><i class="fas fa-clock"></i> Recent Pre-orders</h3>
            <div class="table-card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Pre-order ID</th>
                            <th>Date</th>
                            <th>Notes</th>
                            <th>Status</th>
                            <th>Estimated Total (RM)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentPreorders)): ?>
                            <tr class="empty-row">
                                <td colspan="5">No pre-orders yet. <a href="c_preorder.php" style="color:var(--primary);">Place a pre-order</a>.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentPreorders as $po): ?>
                                <tr>
                                    <td><span class="order-id"><?= htmlspecialchars($po['preorder_id']) ?></span></td>
                                    <td><?= date('d M Y', strtotime($po['order_date'])) ?></td>
                                    <td><?= htmlspecialchars($po['notes'] ?? '—') ?></td>
                                    <td><?= orderStatusBadge($po['order_status']) ?></td>
                                    <td>RM <?= number_format($po['estimated_total'] ?? 0, 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /.dashboard-content -->

    <footer class="dashboard-footer">
        <div>&copy; <?= date('Y') ?> StationaryPlus — Stationery &amp; Printing Management System</div>
        <div class="footer-links">
            <a href="#">Help Center</a> |
            <a href="#">Contact Support</a> |
            <a href="#">Privacy Policy</a>
        </div>
    </footer>

</main>

<?php if ($showBranchPopup): ?>
<div id="branchPopup" style="position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:999;
     display:flex;align-items:center;justify-content:center;">
    <div style="background:white;border-radius:14px;padding:36px 32px;width:90%;max-width:420px;
                box-shadow:0 20px 60px rgba(0,0,0,0.2);text-align:center;">
        <div style="width:56px;height:56px;background:rgba(168,53,53,0.1);border-radius:12px;
                    display:flex;align-items:center;justify-content:center;margin:0 auto 16px;
                    font-size:24px;color:#A83535;">
            <i class="fas fa-store"></i>
        </div>
        <h2 style="font-size:20px;font-weight:700;color:#2E2E2E;margin-bottom:8px;">Select Your Branch</h2>
        <p style="font-size:14px;color:#707070;margin-bottom:24px;">
            Choose the branch you'd like to shop from. You can change this anytime from the dashboard.
        </p>
        
        <form method="POST" action="c_dashboard.php" style="display:flex;align-items:center;gap:8px;">
    <input type="hidden" name="switch_branch" value="1">
    <i class="fas fa-store" style="color:var(--primary);font-size:14px;"></i>
    <select name="branch_id" onchange="this.form.submit()"
            style="padding:8px 28px 8px 12px;border:1.5px solid var(--border);border-radius:20px;
                   font-size:13px;font-weight:600;color:var(--primary);
                   background:rgba(168,53,53,0.06);cursor:pointer;outline:none;appearance:none;
                   background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23A83535' d='M6 8L1 3h10z'/%3E%3C/svg%3E\");
                   background-repeat:no-repeat;background-position:right 10px center;">
        <?php foreach ($branchList as $b): ?>
            <option value="<?= htmlspecialchars($b['branch_id']) ?>"
                <?= ($activeBranchId === $b['branch_id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($b['branch_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <!-- Star toggles remember preference -->
    <button type="submit" name="remember_branch" value="1"
            title="<?= $isPreferred ? 'Click to unset preferred branch' : 'Save as preferred branch' ?>"
            style="background:none;border:none;cursor:pointer;padding:4px;
                   color:<?= $isPreferred ? '#f59e0b' : '#d1d5db' ?>;font-size:16px;
                   transition:color 0.2s;"
            onmouseover="this.style.color='#f59e0b'"
            onmouseout="this.style.color='<?= $isPreferred ? '#f59e0b' : '#d1d5db' ?>'">
        <i class="fas fa-star"></i>
    </button>
</form>
    </div>
</div>
<?php endif; ?>

</body>
</html>