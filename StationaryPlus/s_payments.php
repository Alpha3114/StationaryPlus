<?php
// ============================================================
//  s_payments.php — Staff: Payment Verification
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role(['STAFF', 'ADMIN']);
require_once 'db.php';

$branchId     = $_SESSION['branch_id'] ?? null;
$branchActive = staff_branch_is_active($conn);

// ── Existing filters ──────────────────────────────────────────
$filterStatus  = $_GET['status'] ?? 'PENDING';
$search        = trim($_GET['search'] ?? '');
$validStatuses = ['ALL', 'PENDING', 'VALID', 'INVALID'];
if (!in_array($filterStatus, $validStatuses)) $filterStatus = 'PENDING';

// ── New filters ───────────────────────────────────────────────
$sortOrder    = in_array($_GET['sort']   ?? '', ['newest','oldest'])                    ? $_GET['sort']   : 'newest';
$methodFilter = in_array($_GET['method'] ?? '', ['all','CASH','TRANSFER','OTHER'])      ? $_GET['method'] : 'all';
$amountFilter = in_array($_GET['amount'] ?? '', ['all','under50','50to200','over200'])  ? $_GET['amount'] : 'all';
$dateFilter   = in_array($_GET['date']   ?? '', ['all','today','week','month'])         ? $_GET['date']   : 'all';
$dateFrom     = $_GET['date_from'] ?? '';
$dateTo       = $_GET['date_to']   ?? '';

$activeFilterCount = (int)($sortOrder!=='newest') + (int)($methodFilter!=='all')
                   + (int)($amountFilter!=='all') + (int)($dateFilter!=='all')
                   + (int)($dateFrom !== '')       + (int)($dateTo !== '');

// ── Status counts ─────────────────────────────────────────────
$counts = ['ALL' => 0, 'PENDING' => 0, 'VALID' => 0, 'INVALID' => 0];
$countSQL = "SELECT p.verification_status, COUNT(*) AS cnt FROM payments p JOIN orders o ON p.order_id = o.order_id";
if ($branchId) {
    $cStmt = $conn->prepare($countSQL . " WHERE o.branch_id = ? GROUP BY p.verification_status");
    $cStmt->bind_param('s', $branchId);
    $cStmt->execute();
    $res = $cStmt->get_result();
} else {
    $res = $conn->query($countSQL . " GROUP BY p.verification_status");
}
while ($r = $res->fetch_assoc()) {
    if (isset($counts[$r['verification_status']])) $counts[$r['verification_status']] = (int)$r['cnt'];
    $counts['ALL'] += (int)$r['cnt'];
}

// ── Build WHERE ───────────────────────────────────────────────
$where  = ['1=1'];
$params = [];
$types  = '';

if ($branchId) {
    $where[]  = 'o.branch_id = ?';
    $params[] = $branchId;
    $types   .= 's';
}
if ($filterStatus !== 'ALL') {
    $where[]  = 'p.verification_status = ?';
    $params[] = $filterStatus;
    $types   .= 's';
}
if ($search !== '') {
    $where[]  = '(p.payment_id LIKE ? OR u.name LIKE ? OR o.order_id LIKE ?)';
    $like     = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= 'sss';
}
if ($methodFilter !== 'all') {
    $where[]  = 'p.payment_method = ?';
    $params[] = $methodFilter;
    $types   .= 's';
}
if ($amountFilter === 'under50')  $where[] = 'p.amount < 50';
if ($amountFilter === '50to200')  $where[] = 'p.amount BETWEEN 50 AND 200';
if ($amountFilter === 'over200')  $where[] = 'p.amount > 200';

if ($dateFilter === 'today') {
    $where[] = 'DATE(p.record_date) = CURDATE()';
} elseif ($dateFilter === 'week') {
    $where[] = 'p.record_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
} elseif ($dateFilter === 'month') {
    $where[] = 'MONTH(p.record_date) = MONTH(NOW()) AND YEAR(p.record_date) = YEAR(NOW())';
}
if ($dateFrom !== '') {
    $where[]  = 'DATE(p.record_date) >= ?';
    $params[] = $dateFrom;
    $types   .= 's';
}
if ($dateTo !== '') {
    $where[]  = 'DATE(p.record_date) <= ?';
    $params[] = $dateTo;
    $types   .= 's';
}

$orderBy = $sortOrder === 'oldest' ? 'p.record_date ASC' : 'p.record_date DESC';

$stmt = $conn->prepare(
    "SELECT
         p.payment_id, p.payment_method, p.amount,
         p.record_date, p.verification_status, p.rejection_reason,
         p.reference_number, p.proof_path, p.order_id,
         o.order_type, o.estimated_total,
         u.name AS customer_name, u.phone_number AS customer_phone,
         u.email AS customer_email
     FROM payments p
     JOIN orders o ON p.order_id = o.order_id
     JOIN users  u ON o.user_id  = u.user_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY $orderBy
     LIMIT 150"
);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function payFilterUrl(array $over = []): string {
    global $filterStatus,$search,$sortOrder,$methodFilter,$amountFilter,$dateFilter,$dateFrom,$dateTo;
    $p = array_merge([
        'status'=>$filterStatus,'search'=>$search,'sort'=>$sortOrder,
        'method'=>$methodFilter,'amount'=>$amountFilter,
        'date'=>$dateFilter,'date_from'=>$dateFrom,'date_to'=>$dateTo,
    ], $over);
    return '?'.http_build_query(array_filter($p, fn($v)=>$v!==''&&$v!=='all'&&$v!=='newest'));
}

function methodBadge(string $m): string {
    return match($m) {
        'CASH'     => "<span style='background:#e8f5e9;color:#2e7d32;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:600;'>💵 Cash</span>",
        'TRANSFER' => "<span style='background:#e3f2fd;color:#1565c0;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:600;'>🏦 Transfer</span>",
        'OTHER'    => "<span style='background:#f3e5f5;color:#6a1b9a;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:600;'>📱 E-Wallet</span>",
        default    => "<span style='background:#f3f4f6;color:#6b7280;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:600;'>$m</span>",
    };
}

function statusBadge(string $s): string {
    return match($s) {
        'VALID'   => "<span style='background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;'>✓ Verified</span>",
        'INVALID' => "<span style='background:#fef2f2;color:#c62828;border:1px solid #ef9a9a;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;'>✗ Rejected</span>",
        'PENDING' => "<span style='background:#fffbeb;color:#92400e;border:1px solid #fde68a;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;'>⏳ Pending</span>",
        default   => "<span style='background:#f3f4f6;color:#6b7280;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;'>$s</span>",
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus — Payment Verification</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary:#A83535; --secondary:#F4A261; --accent:#F1EDE8;
            --background:#FAFAFA; --text-primary:#2E2E2E; --text-secondary:#707070;
            --border:#E0E0E0; --white:#FFFFFF;
            --sidebar-width:260px; --card-shadow:0 4px 12px rgba(0,0,0,0.05);
        }
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',system-ui,sans-serif;}

        /* ── Custom Dialog (replaces native alert/confirm/prompt) ── */
        .custom-dialog-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center; }
        .custom-dialog-overlay.show { display:flex; }
        .custom-dialog-box { background:white;border-radius:12px;width:90%;max-width:420px;padding:28px 26px 22px;box-shadow:0 20px 60px rgba(0,0,0,0.2);text-align:center;animation:dialogPop 0.15s ease; }
        @keyframes dialogPop { from{transform:scale(0.95);opacity:0;} to{transform:scale(1);opacity:1;} }
        .custom-dialog-icon { width:52px;height:52px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:22px; }
        .custom-dialog-icon.dialog-info { background:#eff6ff;color:#1d4ed8; }
        .custom-dialog-icon.dialog-success { background:#ecfdf5;color:#059669; }
        .custom-dialog-icon.dialog-error { background:#fef2f2;color:#dc2626; }
        .custom-dialog-icon.dialog-warning { background:#fffbeb;color:#d97706; }
        .custom-dialog-message { font-size:14px;color:#2E2E2E;line-height:1.6;margin-bottom:16px;white-space:pre-line; }
        .custom-dialog-input { display:none;width:100%;padding:10px 12px;border:1.5px solid #E0E0E0;border-radius:8px;font-size:13px;font-family:inherit;resize:none;margin-bottom:18px;transition:border-color 0.2s; }
        .custom-dialog-input:focus { outline:none;border-color:#A83535; }
        .custom-dialog-actions { display:flex;gap:10px; }
        .custom-dialog-btn { flex:1;padding:11px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:none;transition:background 0.2s ease; }
        .custom-dialog-cancel { background:#F1EDE8;color:#2E2E2E;border:1.5px solid #E0E0E0; }
        .custom-dialog-cancel:hover { background:#e8e2da; }
        .custom-dialog-confirm { background:#A83535;color:white; }
        .custom-dialog-confirm:hover { background:#8b2a2a; }
        .custom-dialog-danger { background:#dc2626;color:white; }
        .custom-dialog-danger:hover { background:#b91c1c; }
        body{background:var(--background);color:var(--text-primary);min-height:100vh;display:flex;}

        /* ── Sidebar ── */
        .sidebar{width:var(--sidebar-width);background-color:var(--white);border-right:1px solid var(--border);height:100vh;position:fixed;left:0;top:0;display:flex;flex-direction:column;box-shadow:2px 0 10px rgba(0,0,0,0.03);overflow-y:auto;}
        .logo-area{padding:25px;border-bottom:1px solid var(--border);display:flex;align-items:center;flex-shrink:0;}
        .logo-icon{background-color:var(--primary);width:40px;height:40px;border-radius:8px;display:flex;align-items:center;justify-content:center;margin-right:12px;color:white;font-size:20px;}
        .logo-text{font-size:22px;font-weight:700;color:var(--primary);}
        .nav-section{padding:20px 0;border-bottom:1px solid var(--border);}
        .nav-title{font-size:12px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.8px;padding:0 25px 10px 25px;}
        .nav-menu{list-style:none;}
        .nav-item{margin-bottom:2px;}
        .nav-link{display:flex;align-items:center;padding:13px 25px;color:var(--text-primary);text-decoration:none;transition:all 0.2s;border-left:4px solid transparent;}
        .nav-link:hover{background-color:rgba(168,53,53,0.05);color:var(--primary);border-left-color:rgba(168,53,53,0.3);}
        .nav-link.active{background-color:rgba(168,53,53,0.08);color:var(--primary);border-left-color:var(--primary);font-weight:600;}
        .nav-icon{width:22px;text-align:center;margin-right:14px;font-size:16px;}
        .nav-text{font-size:15px;}
        .user-section{margin-top:auto;padding:20px 25px;border-top:1px solid var(--border);}
        .user-info{display:flex;align-items:center;margin-bottom:14px;}
        .user-avatar{width:40px;height:40px;border-radius:50%;background-color:rgba(168,53,53,0.1);display:flex;align-items:center;justify-content:center;color:var(--primary);font-weight:700;font-size:16px;margin-right:12px;flex-shrink:0;}
        .user-name{font-weight:600;font-size:15px;color:var(--text-primary);}
        .user-role{font-size:12px;color:var(--text-secondary);margin-top:2px;}
        .user-details{overflow:hidden;}
        .logout-link{display:flex;align-items:center;gap:10px;padding:10px 14px;background-color:rgba(168,53,53,0.06);color:var(--primary);border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;}
        .logout-link:hover{background-color:rgba(168,53,53,0.14);}

        .main-content{flex-grow:1;margin-left:var(--sidebar-width);min-height:100vh;display:flex;flex-direction:column;}
        .top-header{background:var(--white);padding:20px 30px;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:10;display:flex;justify-content:space-between;align-items:center;}
        .page-title{font-size:24px;font-weight:700;}
        .page-subtitle{font-size:14px;color:var(--text-secondary);margin-top:4px;}
        .content-wrap{padding:28px 30px;flex-grow:1;}

        /* Filter bar */
        .filter-bar{display:flex;gap:10px;align-items:center;margin-bottom:20px;flex-wrap:wrap;}
        .tab-btn{padding:8px 18px;border-radius:20px;font-size:13px;font-weight:600;cursor:pointer;border:1.5px solid var(--border);background:var(--white);color:var(--text-secondary);text-decoration:none;transition:all 0.2s;display:flex;align-items:center;gap:6px;}
        .tab-btn:hover{border-color:var(--primary);color:var(--primary);}
        .tab-btn.active{background:var(--primary);color:white;border-color:var(--primary);}
        .tab-count{background:rgba(255,255,255,0.25);padding:1px 7px;border-radius:10px;font-size:11px;}
        .tab-btn:not(.active) .tab-count{background:rgba(168,53,53,0.08);color:var(--primary);}
        .search-input{padding:9px 14px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;background:var(--white);outline:none;transition:border-color 0.2s;min-width:240px;margin-left:auto;}
        .search-input:focus{border-color:var(--primary);}

        /* ── Filter panel ── */
        .filters-toggle{display:flex;align-items:center;gap:8px;padding:8px 16px;border:1.5px solid var(--border);border-radius:9px;background:var(--white);font-size:13px;font-weight:600;color:var(--text-primary);cursor:pointer;transition:all 0.2s;white-space:nowrap;}
        .filters-toggle:hover,.filters-toggle.active{border-color:var(--primary);color:var(--primary);background:rgba(168,53,53,0.04);}
        .filter-badge{background:var(--primary);color:white;font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px;min-width:18px;text-align:center;}
        .filter-panel{display:none;background:var(--white);border:1.5px solid var(--border);border-radius:12px;padding:20px 22px 16px;margin-bottom:14px;box-shadow:var(--card-shadow);}
        .filter-panel.open{display:block;}
        .filter-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:18px 24px;margin-bottom:16px;}
        .filter-group-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--text-secondary);margin-bottom:8px;}
        .chip-row{display:flex;flex-wrap:wrap;gap:6px;}
        .chip{padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600;cursor:pointer;border:1.5px solid var(--border);background:var(--white);color:var(--text-secondary);text-decoration:none;transition:all 0.15s;white-space:nowrap;display:inline-block;}
        .chip:hover{border-color:var(--primary);color:var(--primary);}
        .chip.on{background:var(--primary);color:white;border-color:var(--primary);}
        .date-range{display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
        .date-input{padding:6px 10px;border:1.5px solid var(--border);border-radius:7px;font-size:12px;outline:none;transition:border-color 0.2s;}
        .date-input:focus{border-color:var(--primary);}
        .filter-actions{display:flex;align-items:center;gap:10px;border-top:1px solid var(--border);padding-top:14px;}
        .filter-clear{font-size:13px;color:var(--text-secondary);text-decoration:none;padding:6px 12px;border-radius:7px;transition:all 0.15s;}
        .filter-clear:hover{background:rgba(168,53,53,0.06);color:var(--primary);}
        .active-pills{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;}
        .active-pill{display:inline-flex;align-items:center;gap:5px;padding:3px 10px 3px 12px;background:rgba(168,53,53,0.08);color:var(--primary);border:1px solid rgba(168,53,53,0.2);border-radius:20px;font-size:12px;font-weight:600;}
        .active-pill a{color:var(--primary);text-decoration:none;opacity:0.6;margin-left:2px;font-size:14px;line-height:1;}
        .active-pill a:hover{opacity:1;}

        /* Table card */
        .card{background:var(--white);border-radius:12px;border:1px solid var(--border);box-shadow:var(--card-shadow);overflow:hidden;}
        .data-table{width:100%;border-collapse:collapse;}
        .data-table thead{background:rgba(168,53,53,0.04);border-bottom:2px solid var(--border);}
        .data-table th{padding:13px 16px;text-align:left;font-size:11px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.4px;white-space:nowrap;}
        .data-table tbody tr{border-bottom:1px solid var(--border);transition:background 0.15s;cursor:pointer;}
        .data-table tbody tr:last-child{border-bottom:none;}
        .data-table tbody tr:hover{background:rgba(168,53,53,0.02);}
        .data-table td{padding:13px 16px;font-size:13px;vertical-align:middle;}
        .mono{font-family:monospace;font-size:12px;font-weight:700;color:var(--primary);}
        .empty-state{text-align:center;padding:48px;color:var(--text-secondary);}
        .empty-state i{font-size:40px;opacity:0.15;display:block;margin-bottom:12px;}

        /* Detail panel */
        .detail-row{display:none;}
        .detail-row td{padding:0 !important;}
        .detail-row.open{display:table-row;}
        .detail-inner{padding:18px 24px 20px;background:rgba(168,53,53,0.02);border-top:1px dashed var(--border);}
        .detail-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:18px;}
        .detail-block .label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-secondary);margin-bottom:4px;}
        .detail-block .value{font-size:14px;font-weight:600;color:var(--text-primary);}
        .action-row{display:flex;gap:10px;align-items:center;}
        .btn{padding:9px 20px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:7px;transition:all 0.2s;}
        .btn-valid{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;}
        .btn-valid:hover{background:#2e7d32;color:white;}
        .btn-invalid{background:#fef2f2;color:#c62828;border:1px solid #ef9a9a;}
        .btn-invalid:hover{background:#c62828;color:white;}
        .btn-done{background:#f3f4f6;color:#6b7280;border:1px solid #E0E0E0;cursor:not-allowed;}
        .toast{position:fixed;bottom:28px;right:28px;padding:14px 20px;background:#2e7d32;color:white;border-radius:10px;font-size:14px;font-weight:600;box-shadow:0 4px 20px rgba(0,0,0,0.15);z-index:999;transform:translateY(80px);opacity:0;transition:all 0.3s;display:flex;align-items:center;gap:8px;}
        .toast.show{transform:translateY(0);opacity:1;}
        .toast.error{background:#c62828;}

        /* ── Evidence viewer modal ── */
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:1000;align-items:center;justify-content:center;padding:20px;}
        .modal-overlay.open{display:flex;}
        .modal-box{background:var(--white);border-radius:14px;width:90%;max-width:860px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.3);}
        .modal-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;}
        .modal-title{font-size:15px;font-weight:700;color:var(--text-primary);}
        .modal-close{background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-secondary);padding:4px 8px;border-radius:6px;transition:background 0.2s;}
        .modal-close:hover{background:rgba(168,53,53,0.08);color:var(--primary);}
        .modal-body{flex-grow:1;overflow:auto;display:flex;align-items:center;justify-content:center;min-height:400px;background:#f8f8f8;}
        .modal-body iframe{width:100%;height:70vh;border:none;}
        .modal-body img{max-width:100%;max-height:75vh;object-fit:contain;padding:16px;}

        /* Evidence button in detail panel */
        .btn-evidence{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;padding:8px 16px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:background 0.2s;}
        .btn-evidence:hover{background:#1d4ed8;color:white;}
        .btn-download-proof{background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;padding:8px 16px;border-radius:7px;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;text-decoration:none;transition:background 0.2s;}
        .btn-download-proof:hover{background:#15803d;color:white;}

        /* Footer */
        .page-footer{text-align:center;padding:20px;color:var(--text-secondary);font-size:13px;border-top:1px solid var(--border);background:var(--white);}

        @media(max-width:1024px){
            :root{--sidebar-width:70px;}
        }
    </style>
</head>
<body>

<?php include 'smart_sidebar.php'; ?>

<main class="main-content">
    <header class="top-header">
        <div>
            <h1 class="page-title">Payment Verification</h1>
            <p class="page-subtitle">Review and verify customer payment submissions</p>
        </div>
        <?php if ($counts['PENDING'] > 0): ?>
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:9px;padding:10px 16px;font-size:13px;color:#92400e;font-weight:600;">
            <i class="fas fa-exclamation-circle"></i>
            <?= $counts['PENDING'] ?> payment<?= $counts['PENDING'] > 1 ? 's' : '' ?> awaiting verification
        </div>
        <?php endif; ?>
    </header>

    <?php if (!$branchActive): ?>
    <div style="margin:16px 30px 0;background:#fef2f2;border:1.5px solid #fecaca;border-radius:8px;
                padding:12px 18px;font-size:13px;color:#991b1b;
                display:flex;align-items:center;gap:10px;">
        <i class="fas fa-triangle-exclamation" style="font-size:16px;"></i>
        <span>Your assigned branch is temporarily unavailable (inactive/under renovation). Payment verification is disabled — contact an admin to be reassigned.</span>
    </div>
    <?php endif; ?>

    <div class="content-wrap">

        <!-- Filter bar -->
        <div class="filter-bar">
            <?php
            $tabs = ['ALL'=>'All','PENDING'=>'Pending','VALID'=>'Verified','INVALID'=>'Rejected'];
            foreach ($tabs as $key => $label):
                $url = payFilterUrl(['status'=>$key]);
            ?>
            <a href="<?= $url ?>" class="tab-btn <?= $filterStatus===$key?'active':'' ?>">
                <?= $label ?><span class="tab-count"><?= $counts[$key] ?></span>
            </a>
            <?php endforeach; ?>

            <button type="button" class="filters-toggle <?= $activeFilterCount>0?'active':'' ?>" onclick="togglePanel()">
                <i class="fas fa-sliders-h"></i> Filters
                <?php if($activeFilterCount>0): ?><span class="filter-badge"><?= $activeFilterCount ?></span><?php endif; ?>
            </button>

            <form method="GET" style="margin-left:auto;display:flex;gap:6px;">
                <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
                <input type="hidden" name="sort"   value="<?= htmlspecialchars($sortOrder) ?>">
                <input type="hidden" name="method" value="<?= htmlspecialchars($methodFilter) ?>">
                <input type="hidden" name="amount" value="<?= htmlspecialchars($amountFilter) ?>">
                <input type="hidden" name="date"   value="<?= htmlspecialchars($dateFilter) ?>">
                <input type="text" name="search" class="search-input"
                    placeholder="Search payment ID, customer, order…"
                    value="<?= htmlspecialchars($search) ?>"
                    oninput="this.form.submit()">
            </form>
        </div>

        <!-- ── Filter panel ── -->
        <div class="filter-panel <?= $activeFilterCount>0?'open':'' ?>" id="filterPanel">
            <form method="GET" id="filterForm">
                <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">

                <div class="filter-grid">

                    <div>
                        <div class="filter-group-label"><i class="fas fa-sort-amount-down"></i> Sort Order</div>
                        <div class="chip-row">
                            <?php foreach(['newest'=>'⬇ Latest First','oldest'=>'⬆ Oldest First'] as $v=>$l): ?>
                            <label class="chip <?= $sortOrder===$v?'on':'' ?>">
                                <input type="radio" name="sort" value="<?= $v ?>" <?= $sortOrder===$v?'checked':'' ?> onchange="this.form.submit()" style="display:none;">
                                <?= $l ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div>
                        <div class="filter-group-label"><i class="fas fa-credit-card"></i> Payment Method</div>
                        <div class="chip-row">
                            <?php foreach(['all'=>'All','CASH'=>'💵 Cash','TRANSFER'=>'🏦 Transfer','OTHER'=>'📱 E-Wallet'] as $v=>$l): ?>
                            <label class="chip <?= $methodFilter===$v?'on':'' ?>">
                                <input type="radio" name="method" value="<?= $v ?>" <?= $methodFilter===$v?'checked':'' ?> onchange="this.form.submit()" style="display:none;">
                                <?= $l ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div>
                        <div class="filter-group-label"><i class="fas fa-money-bill-wave"></i> Amount Range</div>
                        <div class="chip-row">
                            <?php foreach(['all'=>'Any','under50'=>'< RM 50','50to200'=>'RM 50–200','over200'=>'> RM 200'] as $v=>$l): ?>
                            <label class="chip <?= $amountFilter===$v?'on':'' ?>">
                                <input type="radio" name="amount" value="<?= $v ?>" <?= $amountFilter===$v?'checked':'' ?> onchange="this.form.submit()" style="display:none;">
                                <?= $l ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div>
                        <div class="filter-group-label"><i class="fas fa-calendar-alt"></i> Date Range</div>
                        <div class="chip-row" style="margin-bottom:8px;">
                            <?php foreach(['all'=>'Any','today'=>'Today','week'=>'Last 7 days','month'=>'This month'] as $v=>$l): ?>
                            <label class="chip <?= $dateFilter===$v?'on':'' ?>">
                                <input type="radio" name="date" value="<?= $v ?>" <?= $dateFilter===$v?'checked':'' ?> onchange="this.form.submit()" style="display:none;">
                                <?= $l ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="date-range">
                            <input type="date" name="date_from" class="date-input"
                                value="<?= htmlspecialchars($dateFrom) ?>"
                                placeholder="From" onchange="this.form.submit()">
                            <span style="font-size:12px;color:var(--text-secondary);">to</span>
                            <input type="date" name="date_to" class="date-input"
                                value="<?= htmlspecialchars($dateTo) ?>"
                                placeholder="To" onchange="this.form.submit()">
                        </div>
                    </div>

                </div>

                <div class="filter-actions">
                    <span style="font-size:13px;color:var(--text-secondary);">
                        <?= count($payments) ?> result<?= count($payments)!==1?'s':'' ?> found
                    </span>
                    <?php if($activeFilterCount>0): ?>
                    <a href="?status=<?= urlencode($filterStatus) ?><?= $search?'&search='.urlencode($search):'' ?>" class="filter-clear">
                        <i class="fas fa-times"></i> Clear <?= $activeFilterCount ?> filter<?= $activeFilterCount>1?'s':'' ?>
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- ── Active filter pills ── -->
        <?php if($activeFilterCount > 0): ?>
        <div class="active-pills">
            <?php
            if($sortOrder!=='newest')   echo "<span class='active-pill'>Oldest First<a href='".payFilterUrl(['sort'=>''])."'>×</a></span>";
            if($methodFilter!=='all')   echo "<span class='active-pill'>".['CASH'=>'💵 Cash','TRANSFER'=>'🏦 Transfer','OTHER'=>'📱 E-Wallet'][$methodFilter]."<a href='".payFilterUrl(['method'=>''])."'>×</a></span>";
            if($amountFilter!=='all')   echo "<span class='active-pill'>".['under50'=>'< RM50','50to200'=>'RM50–200','over200'=>'> RM200'][$amountFilter]."<a href='".payFilterUrl(['amount'=>''])."'>×</a></span>";
            if($dateFilter!=='all')     echo "<span class='active-pill'>".ucfirst($dateFilter)."<a href='".payFilterUrl(['date'=>''])."'>×</a></span>";
            if($dateFrom!=='')          echo "<span class='active-pill'>From: $dateFrom<a href='".payFilterUrl(['date_from'=>''])."'>×</a></span>";
            if($dateTo!=='')            echo "<span class='active-pill'>To: $dateTo<a href='".payFilterUrl(['date_to'=>''])."'>×</a></span>";
            ?>
        </div>
        <?php endif; ?>

        <!-- Payments table -->
        <div class="card">
            <div style="overflow-x:auto;">
                <table class="data-table" id="paymentsTable">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Payment ID</th>
                            <th>Customer</th>
                            <th>Order ID</th>
                            <th>Type</th>
                            <th>Method</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="10">
                                <div class="empty-state">
                                    <i class="fas fa-receipt"></i>
                                    <p>No payments found<?= $filterStatus !== 'ALL' ? ' with status <strong>' . strtolower($filterStatus) . '</strong>' : '' ?>.</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $i => $p): ?>
                            <!-- Main row -->
                            <tr onclick="toggleDetail(<?= $i ?>)"
                                id="row-<?= $i ?>"
                                style="<?= $p['verification_status'] === 'PENDING' ? 'background:rgba(245,158,11,0.03);' : '' ?>">
                                <td style="width:36px;">
                                    <i class="fas fa-chevron-right" id="chevron-<?= $i ?>"
                                       style="color:var(--text-secondary);font-size:11px;transition:transform 0.2s;"></i>
                                </td>
                                <td><span class="mono"><?= htmlspecialchars($p['payment_id']) ?></span></td>
                                <td>
                                    <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($p['customer_name']) ?></div>
                                    <div style="font-size:11px;color:var(--text-secondary);"><?= htmlspecialchars($p['customer_email']) ?></div>
                                </td>
                                <td><span class="mono" style="color:var(--text-secondary);"><?= htmlspecialchars($p['order_id']) ?></span></td>
                                <td>
                                    <span style="font-size:12px;font-weight:600;
                                        <?= $p['order_type'] === 'PREORDER'
                                            ? 'color:#8b5cf6;background:#f5f3ff;padding:2px 9px;border-radius:20px;'
                                            : 'color:#3b82f6;background:#eff6ff;padding:2px 9px;border-radius:20px;' ?>">
                                        <?= $p['order_type'] === 'PREORDER' ? 'Pre-order' : 'Order' ?>
                                    </span>
                                </td>
                                <td><?= methodBadge($p['payment_method']) ?></td>
                                <td style="font-weight:700;color:var(--primary);">RM <?= number_format($p['amount'], 2) ?></td>
                                <td style="font-size:12px;color:var(--text-secondary);"><?= date('d M Y', strtotime($p['record_date'])) ?></td>
                                <td><?= statusBadge($p['verification_status']) ?></td>
                                <td onclick="event.stopPropagation()">
                                    <?php if ($p['verification_status'] === 'PENDING' && $branchActive): ?>
                                    <div style="display:flex;gap:6px;">
                                        <button class="btn btn-valid"
                                            onclick="verifyPayment('<?= $p['payment_id'] ?>', 'VALID', <?= $i ?>)">
                                            <i class="fas fa-check"></i> Verify
                                        </button>
                                        <button class="btn btn-invalid"
                                            onclick="verifyPayment('<?= $p['payment_id'] ?>', 'INVALID', <?= $i ?>)">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </div>
                                    <?php else: ?>
                                    <span class="btn btn-done">
                                        <i class="fas fa-lock"></i> <?= ucfirst(strtolower($p['verification_status'])) ?>
                                    </span>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <!-- Detail row (expandable) -->
                            <tr class="detail-row" id="detail-<?= $i ?>">
                                <td colspan="10">
                                    <div class="detail-inner">
                                        <div class="detail-grid">
                                            <div class="detail-block">
                                                <div class="label">Customer Contact</div>
                                                <div class="value"><?= htmlspecialchars($p['customer_phone'] ?? '—') ?></div>
                                                <div style="font-size:12px;color:var(--text-secondary);margin-top:2px;"><?= htmlspecialchars($p['customer_email']) ?></div>
                                            </div>
                                            <div class="detail-block">
                                                <div class="label">Reference Number</div>
                                                <div class="value"><?= htmlspecialchars($p['reference_number'] ?? '—') ?></div>
                                            </div>
                                            <?php if ($p['verification_status'] === 'INVALID' && !empty($p['rejection_reason'])): ?>
                                            <div class="detail-block" style="grid-column:1/-1;background:#fef2f2;border:1px solid #ef9a9a;border-radius:8px;padding:10px 14px;">
                                                <div class="label" style="color:#c62828;"><i class="fas fa-times-circle"></i> Rejection Reason</div>
                                                <div class="value" style="font-weight:400;"><?= htmlspecialchars($p['rejection_reason']) ?></div>
                                            </div>
                                            <?php endif; ?>
                                            <div class="detail-block">
                                                <div class="label">Order Total vs Paid</div>
                                                <div class="value">
                                                    RM <?= number_format($p['estimated_total'] ?? 0, 2) ?> ordered
                                                    &nbsp;/&nbsp;
                                                    <span style="color:var(--primary);">RM <?= number_format($p['amount'], 2) ?> paid</span>
                                                </div>
                                            </div>
                                            <div class="detail-block">
                                                <div class="label">Payment Evidence</div>
                                                <?php
                                                $proof    = $p['proof_path'] ?? null;
                                                $proofExt = $proof ? strtolower(pathinfo($proof, PATHINFO_EXTENSION)) : '';
                                                $isImg    = in_array($proofExt, ['jpg','jpeg','png']);
                                                $isPdf    = $proofExt === 'pdf';
                                                ?>
                                                <?php if ($proof && ($isImg || $isPdf)): ?>
                                                <div style="display:flex;gap:8px;margin-top:6px;flex-wrap:wrap;">
                                                    <button class="btn-evidence"
                                                        onclick="openEvidence('<?= htmlspecialchars($proof) ?>','<?= $isImg ? 'image' : 'pdf' ?>')">
                                                        <i class="fas fa-eye"></i> View Evidence
                                                    </button>
                                                    <a class="btn-download-proof"
                                                       href="<?= htmlspecialchars($proof) ?>" download>
                                                        <i class="fas fa-download"></i> Download
                                                    </a>
                                                </div>
                                                <?php elseif ($proof): ?>
                                                <a class="btn-download-proof" style="margin-top:6px;display:inline-flex;"
                                                   href="<?= htmlspecialchars($proof) ?>" download>
                                                    <i class="fas fa-download"></i> Download Proof
                                                </a>
                                                <?php else: ?>
                                                <div class="value" style="color:var(--text-secondary);font-weight:400;margin-top:4px;">No proof uploaded</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if ($p['verification_status'] === 'PENDING' && $branchActive): ?>
                                        <div class="action-row">
                                            <button class="btn btn-valid"
                                                onclick="verifyPayment('<?= $p['payment_id'] ?>', 'VALID', <?= $i ?>)">
                                                <i class="fas fa-check-circle"></i> Mark as Verified
                                            </button>
                                            <button class="btn btn-invalid"
                                                onclick="verifyPayment('<?= $p['payment_id'] ?>', 'INVALID', <?= $i ?>)">
                                                <i class="fas fa-times-circle"></i> Reject Payment
                                            </button>
                                            <span style="font-size:12px;color:var(--text-secondary);margin-left:6px;">
                                                <i class="fas fa-info-circle"></i>
                                                Verifying will move the linked order to Processing status.
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <footer class="page-footer">
        &copy; <?= date('Y') ?> StationaryPlus — Stationery &amp; Printing Management System
    </footer>
</main>

<!-- Evidence viewer modal -->
<div class="modal-overlay" id="evidenceModal" onclick="closeEvidence(event)">
    <div class="modal-box">
        <div class="modal-header">
            <span class="modal-title">Payment Evidence</span>
            <div style="display:flex;gap:8px;">
                <a id="evidenceDownload" href="#" download
                   style="background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;padding:6px 14px;border-radius:7px;font-size:13px;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:6px;">
                    <i class="fas fa-download"></i> Download
                </a>
                <button class="modal-close" onclick="closeEvidence()"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <div class="modal-body" id="evidenceBody"></div>
    </div>
</div>

<div class="toast" id="toast"></div>

<!-- Custom Dialog -->
<div id="customDialogOverlay" class="custom-dialog-overlay">
    <div class="custom-dialog-box">
        <div class="custom-dialog-icon" id="customDialogIcon"><i class="fas fa-info-circle"></i></div>
        <p class="custom-dialog-message" id="customDialogMessage"></p>
        <textarea class="custom-dialog-input" id="customDialogInput" rows="3"></textarea>
        <div class="custom-dialog-actions">
            <button class="custom-dialog-btn custom-dialog-cancel" id="customDialogCancelBtn" style="display:none;">Cancel</button>
            <button class="custom-dialog-btn custom-dialog-confirm" id="customDialogConfirmBtn">OK</button>
        </div>
    </div>
</div>

<script>
// ── Custom Dialog System (replaces native alert()/confirm()/prompt()) ──
const ICONS = {
    info:    '<i class="fas fa-info-circle"></i>',
    success: '<i class="fas fa-check-circle"></i>',
    error:   '<i class="fas fa-exclamation-circle"></i>',
    warning: '<i class="fas fa-exclamation-triangle"></i>',
};
function customAlert(message, type = 'info') {
    return new Promise(resolve => {
        const overlay = document.getElementById('customDialogOverlay');
        const icon = document.getElementById('customDialogIcon');
        const msgEl = document.getElementById('customDialogMessage');
        const cancelBtn = document.getElementById('customDialogCancelBtn');
        const confirmBtn = document.getElementById('customDialogConfirmBtn');
        msgEl.textContent = message;
        icon.className = 'custom-dialog-icon dialog-' + type;
        icon.innerHTML = ICONS[type] || ICONS.info;
        cancelBtn.style.display = 'none';
        confirmBtn.textContent = 'OK';
        confirmBtn.className = 'custom-dialog-btn custom-dialog-confirm';
        overlay.classList.add('show');
        const onOk = () => { overlay.classList.remove('show'); confirmBtn.removeEventListener('click', onOk); resolve(); };
        confirmBtn.addEventListener('click', onOk);
    });
}
function customConfirm(message, options = {}) {
    return new Promise(resolve => {
        const overlay = document.getElementById('customDialogOverlay');
        const icon = document.getElementById('customDialogIcon');
        const msgEl = document.getElementById('customDialogMessage');
        const cancelBtn = document.getElementById('customDialogCancelBtn');
        const confirmBtn = document.getElementById('customDialogConfirmBtn');
        const type = options.danger ? 'warning' : 'info';
        msgEl.textContent = message;
        icon.className = 'custom-dialog-icon dialog-' + type;
        icon.innerHTML = options.danger ? ICONS.warning : '<i class="fas fa-question-circle"></i>';
        cancelBtn.style.display = 'inline-flex';
        cancelBtn.textContent = options.cancelText || 'Cancel';
        confirmBtn.textContent = options.confirmText || 'Confirm';
        confirmBtn.className = 'custom-dialog-btn ' + (options.danger ? 'custom-dialog-danger' : 'custom-dialog-confirm');
        overlay.classList.add('show');
        const cleanup = (result) => {
            overlay.classList.remove('show');
            confirmBtn.removeEventListener('click', onYes);
            cancelBtn.removeEventListener('click', onNo);
            resolve(result);
        };
        const onYes = () => cleanup(true);
        const onNo  = () => cleanup(false);
        confirmBtn.addEventListener('click', onYes);
        cancelBtn.addEventListener('click', onNo);
    });
}
function customPrompt(message, options = {}) {
    return new Promise(resolve => {
        const overlay = document.getElementById('customDialogOverlay');
        const icon = document.getElementById('customDialogIcon');
        const msgEl = document.getElementById('customDialogMessage');
        const inputEl = document.getElementById('customDialogInput');
        const cancelBtn = document.getElementById('customDialogCancelBtn');
        const confirmBtn = document.getElementById('customDialogConfirmBtn');

        msgEl.textContent = message;
        icon.className = 'custom-dialog-icon dialog-warning';
        icon.innerHTML = ICONS.warning;

        inputEl.style.display = 'block';
        inputEl.value = options.defaultValue || '';
        inputEl.placeholder = options.placeholder || '';
        inputEl.style.borderColor = '#E0E0E0';

        cancelBtn.style.display = 'inline-flex';
        cancelBtn.textContent = options.cancelText || 'Cancel';
        confirmBtn.textContent = options.confirmText || 'Submit';
        confirmBtn.className = 'custom-dialog-btn custom-dialog-danger';

        overlay.classList.add('show');
        setTimeout(() => inputEl.focus(), 60);

        const cleanup = (result) => {
            overlay.classList.remove('show');
            inputEl.style.display = 'none';
            confirmBtn.removeEventListener('click', onYes);
            cancelBtn.removeEventListener('click', onNo);
            resolve(result);
        };
        const onYes = () => {
            const val = inputEl.value.trim();
            if (options.required && val === '') {
                inputEl.style.borderColor = '#dc2626';
                inputEl.focus();
                return;
            }
            cleanup(val);
        };
        const onNo = () => cleanup(null);
        confirmBtn.addEventListener('click', onYes);
        cancelBtn.addEventListener('click', onNo);
    });
}
</script>

<script>
// ── Filter panel toggle ───────────────────────────────────────
function togglePanel() {
    const panel  = document.getElementById('filterPanel');
    const toggle = document.querySelector('.filters-toggle');
    const isOpen = panel.classList.toggle('open');
    toggle.classList.toggle('active', isOpen);
}

// ── Evidence viewer ───────────────────────────────────────────
function openEvidence(filePath, type) {
    document.getElementById('evidenceDownload').href     = filePath;
    document.getElementById('evidenceDownload').download = filePath.split('/').pop();
    const body = document.getElementById('evidenceBody');
    if (type === 'pdf') {
        body.innerHTML = `<iframe src="${filePath}" style="width:100%;height:70vh;border:none;"></iframe>`;
    } else {
        body.innerHTML = `<img src="${filePath}" style="max-width:100%;max-height:75vh;object-fit:contain;padding:16px;">`;
    }
    document.getElementById('evidenceModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeEvidence(e) {
    if (e && e.target !== document.getElementById('evidenceModal') && !e.target.closest('.modal-close')) return;
    document.getElementById('evidenceModal').classList.remove('open');
    document.getElementById('evidenceBody').innerHTML = '';
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeEvidence(); });

// ── Expandable rows ───────────────────────────────────────────
function toggleDetail(i) {
    const detail  = document.getElementById('detail-' + i);
    const chevron = document.getElementById('chevron-' + i);
    const isOpen  = detail.classList.contains('open');
    detail.classList.toggle('open', !isOpen);
    chevron.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(90deg)';
}

// ── Payment verification ──────────────────────────────────────
async function verifyPayment(paymentId, action, rowIndex) {
    let rejectionReason = '';

    if (action === 'INVALID') {
        // Prompt staff for a rejection reason before sending
        rejectionReason = await customPrompt(
            `Rejection reason for ${paymentId}:`,
            {
                placeholder: 'This message will be shown to the customer…',
                required: true,
                confirmText: 'Reject Payment'
            }
        );
        // null = staff cancelled the dialog
        if (rejectionReason === null) return;
    } else {
        const ok = await customConfirm(`Verify payment ${paymentId}?`, { confirmText: 'Verify' });
        if (!ok) return;
    }

    const form = new FormData();
    form.append('payment_id', paymentId);
    form.append('action',     action);
    if (action === 'INVALID') {
        form.append('rejection_reason', rejectionReason);
    }

    try {
        const res  = await fetch('verify_payment.php', { method: 'POST', body: form });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, action === 'VALID' ? 'success' : 'error');
            // Reload after short delay to reflect new status
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast(data.error || 'Something went wrong.', 'error');
        }
    } catch (e) {
        showToast('Network error. Please try again.', 'error');
    }
}

// ── Toast ─────────────────────────────────────────────────────
function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className   = 'toast show' + (type === 'error' ? ' error' : '');
    setTimeout(() => t.classList.remove('show'), 3000);
}
</script>
</body>
</html>