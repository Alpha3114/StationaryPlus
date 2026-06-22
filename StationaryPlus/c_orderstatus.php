<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'auth.php';
require_role('CUSTOMER');
require_once 'db.php';

$userId = $_SESSION['user_id'];

$filterType   = $_GET['type']   ?? 'all';
$filterStatus = $_GET['status'] ?? 'all';
$filterPeriod = $_GET['period'] ?? '30';

$periodSQL = match($filterPeriod) {
    '7'  => "AND order_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    '30' => "AND order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
    '90' => "AND order_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)",
    default => ''
};

$orders = [];
if ($filterType === 'all' || $filterType === 'order') {
    $statusSQL = ($filterStatus !== 'all' && in_array($filterStatus, ['NEW','PROCESSING','READY','COLLECTED','CANCELLED'])) ? "AND order_status = ?" : '';
    $sql = "SELECT order_id AS id, order_date, order_status AS status, estimated_total AS amount, 'Order' AS type FROM orders WHERE user_id = ? $periodSQL $statusSQL ORDER BY order_date DESC";
    $stmt = $conn->prepare($sql);
    if ($statusSQL) { $stmt->bind_param('ss', $userId, $filterStatus); } else { $stmt->bind_param('s', $userId); }
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$preorders = [];
if ($filterType === 'all' || $filterType === 'preorder') {
    $statusSQL = ($filterStatus !== 'all' && in_array($filterStatus, ['SUBMITTED','PROCESSING','READY','CANCELLED'])) ? "AND order_status = ?" : '';
    $sql = "SELECT preorder_id AS id, order_date, order_status AS status, NULL AS amount, 'Pre-order' AS type FROM preorders WHERE user_id = ? $periodSQL $statusSQL ORDER BY order_date DESC";
    $stmt = $conn->prepare($sql);
    if ($statusSQL) { $stmt->bind_param('ss', $userId, $filterStatus); } else { $stmt->bind_param('s', $userId); }
    $stmt->execute();
    $preorders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$allRows = array_merge($orders, $preorders);
usort($allRows, fn($a, $b) => strtotime($b['order_date']) - strtotime($a['order_date']));

function statusBadge(string $status): string {
    $map = [
        'NEW'        => ['#3b82f6','#eff6ff','New'],
        'PROCESSING' => ['#f59e0b','#fffbeb','Processing'],
        'READY'      => ['#10b981','#ecfdf5','Ready'],
        'COLLECTED'  => ['#6b7280','#f3f4f6','Collected'],
        'CANCELLED'  => ['#ef4444','#fef2f2','Cancelled'],
        'SUBMITTED'  => ['#A83535','#fdf2f2','Submitted'],
    ];
    [$color,$bg,$label] = $map[$status] ?? ['#6b7280','#f3f4f6',$status];
    return "<span style='background:$bg;color:$color;border:1px solid {$color}55;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;white-space:nowrap;'>$label</span>";
}

function typeBadge(string $type): string {
    if ($type === 'Order') return "<span style='background:#eff6ff;color:#3b82f6;border:1px solid #bfdbfe;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;'><i class='fas fa-shopping-bag'></i> Order</span>";
    return "<span style='background:#fdf2f2;color:#A83535;border:1px solid #fca5a5;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;'><i class='fas fa-clipboard-check'></i> Pre-order</span>";
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
        .nav-link { display:flex;align-items:center;padding:13px 25px;color:var(--text-primary);text-decoration:none;transition:all 0.2s ease;border-left:4px solid transparent; }
        .nav-link:hover { background-color:rgba(168,53,53,0.05);color:var(--primary);border-left-color:rgba(168,53,53,0.3); }
        .nav-link.active { background-color:rgba(168,53,53,0.08);color:var(--primary);border-left-color:var(--primary);font-weight:600; }
        .nav-icon { width:22px;text-align:center;margin-right:14px;font-size:16px; }
        .nav-text { font-size:15px; }
        .user-section { margin-top:auto;padding:20px 25px;border-top:1px solid var(--border); }
        .user-info { display:flex;align-items:center;margin-bottom:14px; }
        .user-avatar { width:40px;height:40px;border-radius:50%;background-color:rgba(168,53,53,0.1);display:flex;align-items:center;justify-content:center;color:var(--primary);font-weight:700;font-size:16px;margin-right:12px;flex-shrink:0; }
        .user-name { font-weight:600;font-size:15px;color:var(--text-primary); }
        .user-role { font-size:12px;color:var(--text-secondary);margin-top:2px; }
        .logout-link { display:flex;align-items:center;gap:10px;padding:10px 14px;background-color:rgba(168,53,53,0.06);color:var(--primary);border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;transition:background-color 0.2s ease; }
        .logout-link:hover { background-color:rgba(168,53,53,0.14); }
        .main-content { flex-grow:1;margin-left:var(--sidebar-width);min-height:100vh;display:flex;flex-direction:column; }
        .top-header { background-color:var(--white);padding:20px 30px;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:10; }
        .page-title { font-size:24px;font-weight:700;color:var(--text-primary); }
        .page-subtitle { font-size:14px;color:var(--text-secondary);margin-top:4px; }
        .content-container { padding:30px;flex-grow:1; }
        .section-card { background-color:var(--white);border-radius:12px;overflow:hidden;box-shadow:var(--card-shadow);border:1px solid var(--border); }
        .section-header { padding:20px 28px;border-bottom:1px solid var(--border);background-color:rgba(168,53,53,0.03);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px; }
        .section-title { font-size:18px;font-weight:700;color:var(--primary);display:flex;align-items:center;gap:10px; }
        .result-count { font-size:13px;color:var(--text-secondary); }
        .filter-bar { padding:16px 28px;border-bottom:1px solid var(--border);display:flex;gap:10px;flex-wrap:wrap;background:rgba(168,53,53,0.01); }
        .filter-select { padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;background-color:var(--white);color:var(--text-primary);cursor:pointer; }
        .filter-select:focus { outline:none;border-color:var(--primary); }
        .filter-btn { padding:9px 18px;background-color:var(--primary);color:white;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer; }
        .filter-btn:hover { background-color:#8b2a2a; }
        .reset-link { padding:9px 12px;color:var(--text-secondary);font-size:13px;text-decoration:none;display:flex;align-items:center;gap:5px;border-radius:8px; }
        .reset-link:hover { color:var(--primary); }
        .table-wrap { overflow-x:auto; }
        .orders-table { width:100%;border-collapse:collapse; }
        .orders-table thead { background-color:rgba(168,53,53,0.04);border-bottom:2px solid var(--border); }
        .orders-table th { padding:13px 20px;text-align:left;font-size:12px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.5px;white-space:nowrap; }
        .orders-table tbody tr { border-bottom:1px solid var(--border);transition:background-color 0.15s; }
        .orders-table tbody tr:last-child { border-bottom:none; }
        .orders-table tbody tr:hover { background-color:rgba(168,53,53,0.02); }
        .orders-table td { padding:15px 20px;font-size:14px;color:var(--text-primary);vertical-align:middle; }
        .order-id { font-weight:700;color:var(--primary);font-family:monospace;font-size:13px; }
        .order-date { color:var(--text-secondary);font-size:13px; }
        .detail-btn { padding:7px 14px;background-color:rgba(168,53,53,0.08);color:var(--primary);border:1px solid rgba(168,53,53,0.3);border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;transition:all 0.2s; }
        .detail-btn:hover { background-color:rgba(168,53,53,0.16); }
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
        <h1 class="page-title">Order Status</h1>
        <p class="page-subtitle">Track your pre-orders and orders in real time</p>
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
                        <optgroup label="Orders">
                            <option value="NEW"        <?= $filterStatus==='NEW'        ? 'selected':'' ?>>New</option>
                            <option value="PROCESSING" <?= $filterStatus==='PROCESSING' ? 'selected':'' ?>>Processing</option>
                            <option value="READY"      <?= $filterStatus==='READY'      ? 'selected':'' ?>>Ready</option>
                            <option value="COLLECTED"  <?= $filterStatus==='COLLECTED'  ? 'selected':'' ?>>Collected</option>
                            <option value="CANCELLED"  <?= $filterStatus==='CANCELLED'  ? 'selected':'' ?>>Cancelled</option>
                        </optgroup>
                        <optgroup label="Pre-orders">
                            <option value="SUBMITTED"  <?= $filterStatus==='SUBMITTED'  ? 'selected':'' ?>>Submitted</option>
                        </optgroup>
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
                            <th>Status</th>
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
                            <td><?= statusBadge($row['status']) ?></td>
                            <td>
                                <button class="detail-btn" onclick="openModal(
                                    '<?= htmlspecialchars($row['id'],ENT_QUOTES) ?>',
                                    '<?= $row['type'] ?>',
                                    '<?= date('d M Y',strtotime($row['order_date'])) ?>',
                                    '<?= $row['amount']!==null ? 'RM '.number_format($row['amount'],2) : 'N/A' ?>',
                                    '<?= $row['status'] ?>'
                                )"><i class="fas fa-eye"></i> Details</button>
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
        <div class="footer-links"><a href="#">Help Center</a> | <a href="#">Contact Support</a> | <a href="#">Privacy Policy</a></div>
    </footer>
</main>

<div class="modal-overlay" id="modalOverlay" onclick="if(event.target===this)closeModal()">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="modalTitle">Details</div>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

<script>
function openModal(id, type, date, amount, status) {
    document.getElementById('modalTitle').textContent = type + ' Details';
    document.getElementById('modalBody').innerHTML =
        row('ID', '<span style="font-family:monospace;font-weight:700;color:#A83535">'+id+'</span>') +
        row('Type', type) + row('Date', date) + row('Amount', amount) + row('Status', status) +
        '<p style="margin-top:16px;font-size:13px;color:#707070"><i class="fas fa-info-circle"></i> For full item breakdown, visit your Payment Records page.</p>';
    document.getElementById('modalOverlay').classList.add('open');
}
function row(label, value) {
    return '<div class="detail-row"><span class="detail-label">'+label+'</span><span class="detail-value">'+value+'</span></div>';
}
function closeModal() { document.getElementById('modalOverlay').classList.remove('open'); }
document.addEventListener('keydown', e => { if (e.key==='Escape') closeModal(); });
</script>
</body>
</html>