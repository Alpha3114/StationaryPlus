<?php
// ============================================================
//  s_ordermanagement.php — Staff Order Management
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role(['STAFF','ADMIN']);
require_once 'db.php';

// ── Handle status update (POST) ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $orderId      = trim($_POST['order_id']   ?? '');
    $newStatus    = trim($_POST['new_status'] ?? '');
    $staffBranch  = $_SESSION['branch_id'] ?? null;

    $validStatuses = ['NEW','PROCESSING','READY','COLLECTED','CANCELLED'];

    if ($orderId && in_array($newStatus, $validStatuses)) {

        // Fetch current order state before making any changes
        $stmt = $conn->prepare(
            "SELECT order_status, order_type, branch_id FROM orders WHERE order_id = ? LIMIT 1"
        );
        $stmt->bind_param('s', $orderId);
        $stmt->execute();
        $currentOrder = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($currentOrder) {
            $prevStatus  = $currentOrder['order_status'];
            $orderType   = $currentOrder['order_type'];
            $orderBranch = $currentOrder['branch_id'] ?? $staffBranch;

            // ── Guard: PREORDER cannot be manually moved to PROCESSING ──────
            // without a verified payment. The transition should come through
            // verify_payment.php automatically. This prevents staff from
            // skipping the payment step.
            if ($newStatus === 'PROCESSING'
                && $orderType === 'PREORDER'
                && $prevStatus !== 'PROCESSING') {

                $pStmt = $conn->prepare(
                    "SELECT COUNT(*) AS cnt FROM payments
                     WHERE order_id = ? AND verification_status = 'VALID'"
                );
                $pStmt->bind_param('s', $orderId);
                $pStmt->execute();
                $hasValidPayment = (int)$pStmt->get_result()->fetch_assoc()['cnt'] > 0;
                $pStmt->close();

                if (!$hasValidPayment) {
                    $qs = http_build_query(array_filter([
                        'error'  => 'payment_required',
                        'search' => $_GET['search'] ?? '',
                        'status' => $_GET['status'] ?? '',
                        'filter' => $_GET['filter'] ?? '',
                    ]));
                    header('Location: s_ordermanagement.php?' . $qs);
                    exit;
                }
            }

            // ── Deduct inventory when a PREORDER is marked COLLECTED ──────
            // Guard: only fires once — skipped if order was already COLLECTED.
            // Walk-in ORDERs are excluded; their inventory is deducted at POS.
            if ($newStatus  === 'COLLECTED'
                && $prevStatus !== 'COLLECTED'
                && $orderType  === 'PREORDER') {

                $iStmt = $conn->prepare(
                    "SELECT product_id, quantity FROM order_items WHERE order_id = ?"
                );
                $iStmt->bind_param('s', $orderId);
                $iStmt->execute();
                $items = $iStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $iStmt->close();

                $staffId = $_SESSION['user_id'];

                foreach ($items as $item) {
                    // Fetch current inventory state before deducting (needed for log)
                    if ($orderBranch) {
                        $selStmt = $conn->prepare(
                            "SELECT inventory_id, stock_quantity FROM inventory
                             WHERE product_id = ? AND branch_id = ? LIMIT 1"
                        );
                        $selStmt->bind_param('ss', $item['product_id'], $orderBranch);
                    } else {
                        $selStmt = $conn->prepare(
                            "SELECT inventory_id, stock_quantity FROM inventory
                             WHERE product_id = ? ORDER BY stock_quantity DESC LIMIT 1"
                        );
                        $selStmt->bind_param('s', $item['product_id']);
                    }
                    $selStmt->execute();
                    $invRow = $selStmt->get_result()->fetch_assoc();
                    $selStmt->close();

                    if (!$invRow) continue;

                    $inventoryId = $invRow['inventory_id'];
                    $oldQty      = (int)$invRow['stock_quantity'];
                    $newQty      = $oldQty - (int)$item['quantity'];
                    $changeQty   = -(int)$item['quantity'];

                    // Deduct inventory
                    $dStmt = $conn->prepare(
                        "UPDATE inventory
                         SET stock_quantity = ?,
                             last_updated   = NOW()
                         WHERE inventory_id = ?"
                    );
                    $dStmt->bind_param('is', $newQty, $inventoryId);
                    $dStmt->execute();
                    $dStmt->close();

                    // Log the movement
                    $logStmt = $conn->prepare(
                        "INSERT INTO inventory_log
                            (inventory_id, product_id, branch_id, change_qty, old_qty, new_qty,
                             reason, reference_id, changed_by)
                         VALUES (?, ?, ?, ?, ?, ?, 'ORDER_COLLECTED', ?, ?)"
                    );
                    $logStmt->bind_param(
                        'sssiiiss',
                        $inventoryId, $item['product_id'], $orderBranch,
                        $changeQty, $oldQty, $newQty,
                        $orderId, $staffId
                    );
                    $logStmt->execute();
                    $logStmt->close();
                }
            }

            // ── Update the order status ───────────────────────────────────
            $stmt = $conn->prepare(
                "UPDATE orders SET order_status = ? WHERE order_id = ?"
            );
            $stmt->bind_param('ss', $newStatus, $orderId);
            $stmt->execute();
            $stmt->close();
        }
    }

    $redirect = '?';
    if (!empty($_GET['search'])) $redirect .= 'search='  . urlencode($_GET['search'])  . '&';
    if (!empty($_GET['status'])) $redirect .= 'status='  . urlencode($_GET['status'])  . '&';
    if (!empty($_GET['filter'])) $redirect .= 'filter='  . urlencode($_GET['filter'])  . '&';
    header('Location: s_ordermanagement.php' . rtrim($redirect, '&?'));
    exit;
}

// ── Filters ───────────────────────────────────────────────────
$branchId     = $_SESSION['branch_id'] ?? null;
$search       = trim($_GET['search'] ?? '');
$filterType   = $_GET['filter'] ?? 'all';   // all | order | preorder
$filterStatus = $_GET['status'] ?? 'all';

// ── Build WHERE clauses ───────────────────────────────────────
$where  = ['1=1'];
$params = [];
$types  = '';

if ($search !== '') {
    $where[]  = "(o.order_id LIKE ? OR u.name LIKE ?)";
    $like     = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

if ($filterType === 'order') {
    $where[] = "o.order_type = 'ORDER'";
} elseif ($filterType === 'preorder') {
    $where[] = "o.order_type = 'PREORDER'";
}

$validStatuses = ['NEW','PROCESSING','READY','COLLECTED','CANCELLED'];
if ($filterStatus !== 'all' && in_array($filterStatus, $validStatuses)) {
    $where[]  = "o.order_status = ?";
    $params[] = $filterStatus;
    $types   .= 's';
}

// Branch scoping — staff only see their own branch's orders
if ($branchId) {
    $where[]  = "o.branch_id = ?";
    $params[] = $branchId;
    $types   .= 's';
}

$whereSQL = implode(' AND ', $where);

// ── Main query ────────────────────────────────────────────────
// LEFT JOIN users because walk-in orders (created via POS) have NULL user_id
$sql = "SELECT
            o.order_id        AS id,
            o.order_type      AS type,
            o.order_date,
            o.order_status    AS status,
            o.estimated_total AS amount,
            o.notes,
            COALESCE(u.name,  'Walk-in') AS customer_name,
            COALESCE(u.email, '—')        AS customer_email,
            u.phone_number                AS customer_phone,
            COUNT(oi.order_item_id)       AS item_count
        FROM orders o
        LEFT JOIN users u      ON o.user_id  = u.user_id
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE $whereSQL
        GROUP BY o.order_id
        ORDER BY o.order_date DESC
        LIMIT 100";

$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$allRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Status badge helper ───────────────────────────────────────
function statusBadge(string $status): string {
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
    <title>StationaryPlus — Order Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary:#A83535;--secondary:#F4A261;--accent:#F1EDE8;--background:#FAFAFA;--text-primary:#2E2E2E;--text-secondary:#707070;--border:#E0E0E0;--white:#FFFFFF;--sidebar-width:260px;--card-shadow:0 4px 12px rgba(0,0,0,0.05); }
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

        /* Top header with search */
        .top-header { background-color:var(--white);padding:16px 28px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;position:sticky;top:0;z-index:10; }
        .page-title { font-size:22px;font-weight:700;color:var(--text-primary); }
        .page-subtitle { font-size:13px;color:var(--text-secondary);margin-top:3px; }
        .header-right { display:flex;gap:10px;align-items:center;flex-wrap:wrap; }

        /* Search form */
        .search-wrap { position:relative; }
        .search-icon { position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-secondary);font-size:14px; }
        .search-input { padding:9px 12px 9px 36px;border:1.5px solid var(--border);border-radius:8px;width:240px;font-size:13px;background:var(--accent);transition:all 0.2s; }
        .search-input:focus { outline:none;border-color:var(--primary);background:var(--white);box-shadow:0 0 0 3px rgba(168,53,53,0.08); }

        /* Filter tabs */
        .filter-tabs { display:flex;gap:6px; }
        .tab { padding:8px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-weight:600;color:var(--text-secondary);background:var(--white);cursor:pointer;text-decoration:none;transition:all 0.2s; }
        .tab:hover { border-color:var(--primary);color:var(--primary); }
        .tab.active { background:var(--primary);color:white;border-color:var(--primary); }

        /* Status filter */
        .status-select { padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;background:var(--white);color:var(--text-primary);cursor:pointer; }
        .status-select:focus { outline:none;border-color:var(--primary); }

        /* ── Split layout ── */
        .split-layout { display:flex;flex-grow:1;overflow:hidden;height:calc(100vh - 74px); }

        /* Orders list pane */
        .list-pane { flex:1;overflow-y:auto;padding:0; }
        .list-header { padding:14px 22px;border-bottom:1px solid var(--border);background:rgba(168,53,53,0.03);display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:5; }
        .list-title { font-size:15px;font-weight:700;color:var(--primary);display:flex;align-items:center;gap:8px; }
        .list-count { font-size:12px;color:var(--text-secondary);background:rgba(168,53,53,0.08);padding:3px 10px;border-radius:20px;font-weight:600; }

        /* Order rows */
        .order-row { padding:14px 22px;border-bottom:1px solid var(--border);cursor:pointer;transition:background 0.15s;display:flex;align-items:center;gap:14px; }
        .order-row:hover { background:rgba(168,53,53,0.03); }
        .order-row.active { background:rgba(168,53,53,0.07);border-left:3px solid var(--primary); }
        .order-row.active { padding-left:19px; }
        .avatar { width:36px;height:36px;border-radius:50%;background:rgba(168,53,53,0.1);color:var(--primary);font-weight:700;font-size:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
        .row-main { flex-grow:1;min-width:0; }
        .row-top { display:flex;justify-content:space-between;align-items:center;margin-bottom:4px; }
        .row-id { font-size:12px;font-weight:700;color:var(--primary);font-family:monospace; }
        .row-date { font-size:11px;color:var(--text-secondary); }
        .row-name { font-size:13px;font-weight:600;color:var(--text-primary);margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
        .row-meta { font-size:12px;color:var(--text-secondary); }
        .row-badges { display:flex;gap:4px;align-items:center;flex-shrink:0; }

        /* Detail pane */
        .detail-pane { width:400px;border-left:1px solid var(--border);background:var(--white);display:flex;flex-direction:column;overflow:hidden;flex-shrink:0; }
        .detail-header { padding:18px 22px;border-bottom:1px solid var(--border);background:rgba(168,53,53,0.03); }
        .detail-title { font-size:15px;font-weight:700;color:var(--primary);display:flex;align-items:center;gap:8px; }
        .detail-body { flex-grow:1;overflow-y:auto;padding:22px; }

        /* Empty/loading states */
        .empty-pane { display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:var(--text-secondary);text-align:center;padding:30px; }
        .empty-pane i { font-size:40px;opacity:0.2;margin-bottom:14px; }
        .empty-pane p { font-size:14px; }
        .spinner { width:28px;height:28px;border:3px solid var(--border);border-top-color:var(--primary);border-radius:50%;animation:spin 0.7s linear infinite;margin:0 auto 14px; }
        @keyframes spin { to { transform:rotate(360deg); } }

        /* Detail sections */
        .info-section { margin-bottom:22px; }
        .info-section-title { font-size:12px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.6px;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:6px; }
        .info-grid { display:grid;grid-template-columns:1fr 1fr;gap:12px; }
        .info-item { }
        .info-label { font-size:11px;color:var(--text-secondary);margin-bottom:3px;font-weight:600;text-transform:uppercase;letter-spacing:0.4px; }
        .info-value { font-size:13px;font-weight:600;color:var(--text-primary); }

        /* Items table inside detail */
        .items-table { width:100%;border-collapse:collapse;margin-top:4px; }
        .items-table thead { background:rgba(168,53,53,0.04); }
        .items-table th { padding:8px 10px;text-align:left;font-size:11px;font-weight:600;color:var(--text-secondary);text-transform:uppercase; }
        .items-table tbody tr { border-bottom:1px solid var(--border); }
        .items-table tbody tr:last-child { border-bottom:none; }
        .items-table td { padding:10px 10px;font-size:13px; }
        .items-total { display:flex;justify-content:space-between;padding:12px 10px 0;margin-top:6px;border-top:2px solid var(--border);font-weight:700;font-size:14px; }
        .items-total span:last-child { color:var(--primary); }

        /* Status update form */
        .update-form { background:rgba(168,53,53,0.03);border:1px solid var(--border);border-radius:10px;padding:16px; }
        .update-form-title { font-size:13px;font-weight:700;color:var(--primary);margin-bottom:12px;display:flex;align-items:center;gap:6px; }
        .status-select-form { width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;margin-bottom:12px;background:var(--white);color:var(--text-primary);transition:border-color 0.2s; }
        .status-select-form:focus { outline:none;border-color:var(--primary); }
        .update-btn { width:100%;padding:11px;background:var(--primary);color:white;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:background 0.2s; }
        .update-btn:hover { background:#8b2a2a; }

        /* Inventory notice shown below update form when COLLECTED is selected */
        .inv-notice { display:none;margin-top:10px;padding:9px 13px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px;font-size:12px;color:#065f46;align-items:center;gap:8px; }
        .inv-notice.show { display:flex; }

        /* Empty list */
        .empty-list { padding:50px 20px;text-align:center;color:var(--text-secondary); }
        .empty-list i { font-size:40px;opacity:0.2;margin-bottom:12px;display:block; }

        @media (max-width:1024px) {
            :root { --sidebar-width:70px; }
            .logo-text,.nav-text,.user-details,.nav-title,.logout-link span { display:none; }
            .logo-area,.nav-section,.user-section { padding:18px 12px; }
            .nav-link { justify-content:center;padding:14px;border-left:none;border-right:4px solid transparent; }
            .nav-link:hover,.nav-link.active { border-left:none;border-right-color:var(--primary); }
            .nav-icon { margin-right:0;font-size:20px; }
            .logout-link { justify-content:center;padding:10px; }
            .detail-pane { width:320px; }
        }
        @media (max-width:800px) {
            .detail-pane { display:none; }
            .split-layout { flex-direction:column; }
        }
    </style>
</head>
<body>

<?php include 'smart_sidebar.php'; ?>

<main class="main-content">

    <!-- Header + search/filters -->
    <header class="top-header">
        <div>
            <div class="page-title">Order Management</div>
            <div class="page-subtitle">Review, process, and update customer orders &amp; reservations</div>
        </div>
        <div class="header-right">
            <!-- Search -->
            <form method="GET" action="s_ordermanagement.php" style="display:flex;gap:8px;align-items:center;">
                <div class="search-wrap">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" name="search" class="search-input"
                           placeholder="Search ID or customer…"
                           value="<?= htmlspecialchars($search) ?>">
                </div>

                <!-- Status filter -->
                <select name="status" class="status-select" onchange="this.form.submit()">
                    <option value="all"        <?= $filterStatus==='all'        ? 'selected':'' ?>>All Statuses</option>
                    <option value="NEW"        <?= $filterStatus==='NEW'        ? 'selected':'' ?>>New</option>
                    <option value="PROCESSING" <?= $filterStatus==='PROCESSING' ? 'selected':'' ?>>Processing</option>
                    <option value="READY"      <?= $filterStatus==='READY'      ? 'selected':'' ?>>Ready</option>
                    <option value="COLLECTED"  <?= $filterStatus==='COLLECTED'  ? 'selected':'' ?>>Collected</option>
                    <option value="CANCELLED"  <?= $filterStatus==='CANCELLED'  ? 'selected':'' ?>>Cancelled</option>
                </select>
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filterType) ?>">
            </form>

            <!-- Type tabs -->
            <div class="filter-tabs">
                <a href="?filter=all&status=<?= urlencode($filterStatus) ?>&search=<?= urlencode($search) ?>"
                   class="tab <?= $filterType==='all'      ? 'active':'' ?>">All</a>
                <a href="?filter=order&status=<?= urlencode($filterStatus) ?>&search=<?= urlencode($search) ?>"
                   class="tab <?= $filterType==='order'    ? 'active':'' ?>">Walk-in Sales</a>
                <a href="?filter=preorder&status=<?= urlencode($filterStatus) ?>&search=<?= urlencode($search) ?>"
                   class="tab <?= $filterType==='preorder' ? 'active':'' ?>">Reservations</a>
            </div>
        </div>
    </header>

    <div class="split-layout">
        <?php if (isset($_GET['error']) && $_GET['error'] === 'payment_required'): ?>
        <div style="position:fixed;top:74px;left:var(--sidebar-width);right:0;z-index:20;
                    padding:12px 24px;background:#fff7ed;border-bottom:1px solid #fed7aa;
                    display:flex;align-items:center;gap:10px;font-size:13px;color:#92400e;">
            <i class="fas fa-exclamation-triangle" style="flex-shrink:0;"></i>
            <strong>Cannot set to Processing:</strong>
            This reservation has no verified payment yet. Payment must be verified by staff first.
        </div>
        <?php endif; ?>

        <!-- ── LEFT: order list ── -->
        <div class="list-pane" id="listPane">
            <div class="list-header">
                <div class="list-title"><i class="fas fa-clipboard-list"></i> Orders</div>
                <span class="list-count"><?= count($allRows) ?> record<?= count($allRows)!==1?'s':'' ?></span>
            </div>

            <?php if (empty($allRows)): ?>
                <div class="empty-list">
                    <i class="fas fa-clipboard-list"></i>
                    <p>No orders match the current filters.</p>
                </div>
            <?php else: ?>
                <?php foreach ($allRows as $row):
                    $initial = strtoupper(mb_substr($row['customer_name'], 0, 1));
                    $amount  = $row['amount'] !== null ? 'RM '.number_format($row['amount'],2) : 'Amount TBC';
                ?>
                <div class="order-row"
                     data-id="<?= htmlspecialchars($row['id']) ?>"
                     data-type="<?= $row['type'] ?>"
                     onclick="loadDetail('<?= htmlspecialchars($row['id'],ENT_QUOTES) ?>','<?= $row['type'] ?>')">
                    <div class="avatar"><?= htmlspecialchars($initial) ?></div>
                    <div class="row-main">
                        <div class="row-top">
                            <span class="row-id"><?= htmlspecialchars($row['id']) ?></span>
                            <span class="row-date"><?= date('d M Y', strtotime($row['order_date'])) ?></span>
                        </div>
                        <div class="row-name"><?= htmlspecialchars($row['customer_name']) ?></div>
                        <div class="row-meta"><?= $row['item_count'] ?> item<?= $row['item_count']!=1?'s':'' ?> &bull; <?= $amount ?></div>
                    </div>
                    <div class="row-badges">
                        <?= statusBadge($row['status']) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ── RIGHT: detail pane ── -->
        <div class="detail-pane" id="detailPane">
            <div class="detail-header">
                <div class="detail-title"><i class="fas fa-info-circle"></i> Order Details</div>
            </div>
            <div class="detail-body" id="detailBody">
                <div class="empty-pane">
                    <i class="fas fa-hand-pointer"></i>
                    <p>Click any order on the left to view its details</p>
                </div>
            </div>
        </div>

    </div><!-- /.split-layout -->

</main>

<script>
// ── AJAX detail loader ────────────────────────────────────────
function loadDetail(id, type) {
    document.querySelectorAll('.order-row').forEach(r => r.classList.remove('active'));
    const row = document.querySelector(`.order-row[data-id="${id}"]`);
    if (row) row.classList.add('active');

    document.getElementById('detailBody').innerHTML =
        '<div class="empty-pane"><div class="spinner"></div><p>Loading…</p></div>';

    fetch(`s_orderdetail.php?id=${encodeURIComponent(id)}&type=${type}`)
        .then(r => r.json())
        .then(data => renderDetail(data, id, type))
        .catch(() => {
            document.getElementById('detailBody').innerHTML =
                '<div class="empty-pane"><i class="fas fa-exclamation-circle"></i><p>Failed to load details.</p></div>';
        });
}

function renderDetail(d, id, type) {
    const isPreorder = type === 'preorder';

    const statusOptions = isPreorder
        ? ['NEW','PROCESSING','READY','COLLECTED','CANCELLED']
        : ['NEW','PROCESSING','READY','COLLECTED','CANCELLED'];

    const optionsHTML = statusOptions.map(s =>
        `<option value="${s}" ${s === d.status ? 'selected' : ''}>${s.charAt(0)+s.slice(1).toLowerCase()}</option>`
    ).join('');

    const itemsHTML = d.items.map(i =>
        `<tr>
            <td>${escHtml(i.name)}</td>
            <td style="text-align:center;">${i.qty}</td>
            <td style="text-align:right;">RM ${parseFloat(i.unit_price).toFixed(2)}</td>
            <td style="text-align:right;font-weight:600;">RM ${(i.qty * i.unit_price).toFixed(2)}</td>
         </tr>`
    ).join('');

    const total = d.items.reduce((s, i) => s + i.qty * i.unit_price, 0);
    const amountDisplay = d.amount !== null
        ? `RM ${parseFloat(d.amount).toFixed(2)}`
        : `RM ${total.toFixed(2)}`;

    document.getElementById('detailBody').innerHTML = `
        <div class="info-section">
            <div class="info-section-title">
                <i class="fas fa-file-alt"></i>
                ${isPreorder ? 'Reservation' : 'Walk-in Sale'} Info
            </div>
            <div class="info-grid">
                <div class="info-item"><div class="info-label">ID</div><div class="info-value" style="font-family:monospace;">${escHtml(id)}</div></div>
                <div class="info-item"><div class="info-label">Date</div><div class="info-value">${escHtml(d.date)}</div></div>
                <div class="info-item"><div class="info-label">Customer</div><div class="info-value">${escHtml(d.customer_name)}</div></div>
                <div class="info-item"><div class="info-label">Email</div><div class="info-value" style="font-size:12px;">${escHtml(d.customer_email)}</div></div>
                <div class="info-item"><div class="info-label">Phone</div><div class="info-value">${escHtml(d.customer_phone || '—')}</div></div>
                <div class="info-item"><div class="info-label">Total</div><div class="info-value" style="color:var(--primary);">${amountDisplay}</div></div>
            </div>
            ${d.notes ? `<div style="margin-top:12px;padding:10px 12px;background:var(--accent);border-radius:8px;font-size:13px;color:var(--text-primary);"><strong>Notes:</strong> ${escHtml(d.notes)}</div>` : ''}
        </div>

        <div class="info-section">
            <div class="info-section-title"><i class="fas fa-box-open"></i> Items</div>
            <table class="items-table">
                <thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead>
                <tbody>${itemsHTML || '<tr><td colspan="4" style="text-align:center;color:#707070;padding:14px;">No items recorded</td></tr>'}</tbody>
            </table>
            <div class="items-total"><span>Total</span><span>${amountDisplay}</span></div>
        </div>

        <div class="update-form">
            <div class="update-form-title"><i class="fas fa-sync-alt"></i> Update Status</div>
            <form method="POST" action="s_ordermanagement.php<?= '?' . http_build_query(array_filter(['search'=>$search,'filter'=>$filterType,'status'=>$filterStatus])) ?>">
                <input type="hidden" name="order_id"   value="${escHtml(id)}">
                <input type="hidden" name="order_type" value="${type}">
                <select name="new_status" class="status-select-form" id="statusSelectForm"
                        onchange="toggleInvNotice(this.value, '${type}', '${escHtml(d.status)}')">
                    ${optionsHTML}
                </select>
                <div class="inv-notice" id="invNotice">
                    <i class="fas fa-boxes"></i>
                    Stock will be deducted automatically when you save.
                </div>
                <button type="submit" name="update_status" class="update-btn">
                    <i class="fas fa-check-circle"></i> Update Status
                </button>
            </form>
        </div>
    `;

    // Show notice if COLLECTED is already selected on render
    toggleInvNotice(d.status, type, d.status);
}

// Show the inventory deduction notice when COLLECTED is chosen for a reservation
function toggleInvNotice(newStatus, type, currentStatus) {
    const notice = document.getElementById('invNotice');
    if (!notice) return;
    const willDeduct = newStatus === 'COLLECTED'
                    && currentStatus !== 'COLLECTED'
                    && type === 'preorder';
    notice.classList.toggle('show', willDeduct);
}

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

</body>
</html>