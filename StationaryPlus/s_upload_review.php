<?php
// ============================================================
//  s_upload_review.php — Staff: Print File Review
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role(['STAFF', 'ADMIN']);
require_once 'db.php';

// ── Existing filters ──────────────────────────────────────────
$filterStatus = $_GET['status'] ?? 'RECEIVED';
$search       = trim($_GET['search'] ?? '');
$validStatuses = ['ALL', 'RECEIVED', 'REVIEWED', 'REJECTED'];
if (!in_array($filterStatus, $validStatuses)) $filterStatus = 'RECEIVED';

// ── New filters ───────────────────────────────────────────────
$sortOrder     = in_array($_GET['sort']       ?? '', ['newest','oldest'])              ? $_GET['sort']       : 'newest';
$printType     = in_array($_GET['print_type'] ?? '', ['all','color','bw','mixed'])     ? $_GET['print_type'] : 'all';
$paperSize     = in_array($_GET['paper_size'] ?? '', ['all','A0','A1','A2','A3','A4','A5']) ? $_GET['paper_size'] : 'all';
$bindingFilter = in_array($_GET['binding']    ?? '', ['all','NONE','STAPLE','SPIRAL']) ? $_GET['binding']    : 'all';
$copiesFilter  = in_array($_GET['copies']     ?? '', ['all','single','multiple'])      ? $_GET['copies']     : 'all';

$activeFilterCount = (int)($sortOrder!=='newest') + (int)($printType!=='all')
                   + (int)($paperSize!=='all')    + (int)($bindingFilter!=='all')
                   + (int)($copiesFilter!=='all');

// ── Status counts ─────────────────────────────────────────────
$branchId = $_SESSION['branch_id'] ?? null;

$counts = ['ALL' => 0, 'RECEIVED' => 0, 'REVIEWED' => 0, 'REJECTED' => 0];
$countSQL = "SELECT pf.file_status, COUNT(*) AS cnt FROM print_files pf LEFT JOIN orders o ON pf.order_id = o.order_id";
if ($branchId) {
    $cStmt = $conn->prepare($countSQL . " WHERE o.branch_id = ? GROUP BY pf.file_status");
    $cStmt->bind_param('s', $branchId);
    $cStmt->execute();
    $res = $cStmt->get_result();
} else {
    $res = $conn->query($countSQL . " GROUP BY pf.file_status");
}
while ($r = $res->fetch_assoc()) {
    if (isset($counts[$r['file_status']])) $counts[$r['file_status']] = (int)$r['cnt'];
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
    $where[]  = 'pf.file_status = ?';
    $params[] = $filterStatus;
    $types   .= 's';
}
if ($search !== '') {
    $where[]  = '(pf.file_id LIKE ? OR pf.file_name LIKE ? OR u.name LIKE ?)';
    $like     = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= 'sss';
}
if ($printType === 'color')  $where[] = '(pf.color_pages > 0 AND pf.bw_pages = 0)';
if ($printType === 'bw')     $where[] = '(pf.color_pages = 0)';
if ($printType === 'mixed')  $where[] = '(pf.color_pages > 0 AND pf.bw_pages > 0)';
if ($paperSize !== 'all')   { $where[] = 'pf.paper_size = ?';    $params[] = $paperSize;     $types .= 's'; }
if ($bindingFilter !== 'all'){ $where[] = 'pf.binding_type = ?'; $params[] = $bindingFilter; $types .= 's'; }
if ($copiesFilter === 'single')   $where[] = 'pf.copies = 1';
if ($copiesFilter === 'multiple') $where[] = 'pf.copies > 1';

$orderBy = $sortOrder === 'oldest' ? 'pf.upload_date ASC' : 'pf.upload_date DESC';

$stmt = $conn->prepare(
    "SELECT pf.file_id, pf.file_name, pf.file_type, pf.file_path,
            pf.file_status, pf.print_type, pf.paper_size, pf.paper_type,
            pf.binding_type, pf.copies, pf.total_pages,
            pf.color_pages, pf.bw_pages, pf.estimated_price,
            pf.ai_analysis, pf.upload_date, pf.order_id,
            u.name AS customer_name, u.phone_number AS customer_phone,
            u.user_id AS customer_id
     FROM print_files pf
     LEFT JOIN users u ON pf.user_id = u.user_id
     LEFT JOIN orders o ON pf.order_id = o.order_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY $orderBy
     LIMIT 150"
);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Build a URL keeping all current filters, with selective overrides
function filterUrl(array $over = []): string {
    global $filterStatus,$search,$sortOrder,$printType,$paperSize,$bindingFilter,$copiesFilter;
    $p = array_merge([
        'status'=>$filterStatus,'search'=>$search,'sort'=>$sortOrder,
        'print_type'=>$printType,'paper_size'=>$paperSize,
        'binding'=>$bindingFilter,'copies'=>$copiesFilter,
    ], $over);
    return '?'.http_build_query(array_filter($p, fn($v)=>$v!==''&&$v!=='all'&&$v!=='newest'));
}

function fileStatusBadge(string $s): string {
    return match($s) {
        'RECEIVED' => "<span style='background:#fffbeb;color:#92400e;border:1px solid #fde68a;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;'>⏳ Received</span>",
        'REVIEWED' => "<span style='background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;'>✓ Reviewed</span>",
        'REJECTED' => "<span style='background:#fef2f2;color:#c62828;border:1px solid #ef9a9a;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;'>✗ Rejected</span>",
        default    => "<span style='background:#f3f4f6;color:#6b7280;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;'>$s</span>",
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus — Print File Review</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary:#A83535; --secondary:#F4A261; --accent:#F1EDE8;
            --background:#FAFAFA; --text-primary:#2E2E2E; --text-secondary:#707070;
            --border:#E0E0E0; --white:#FFFFFF;
            --sidebar-width:260px; --card-shadow:0 4px 12px rgba(0,0,0,0.05);
        }
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',system-ui,sans-serif;}
        body{background:var(--background);color:var(--text-primary);min-height:100vh;display:flex;}

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

        /* ── Main ── */
        .main-content{flex-grow:1;margin-left:var(--sidebar-width);min-height:100vh;display:flex;flex-direction:column;}
        .top-header{background:var(--white);padding:20px 30px;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:10;display:flex;justify-content:space-between;align-items:center;}
        .page-title{font-size:24px;font-weight:700;}
        .page-subtitle{font-size:14px;color:var(--text-secondary);margin-top:4px;}
        .content-wrap{padding:28px 30px;flex-grow:1;}

        /* ── Filters ── */
        .filter-bar{display:flex;gap:10px;align-items:center;margin-bottom:20px;flex-wrap:wrap;}
        .tab-btn{padding:8px 18px;border-radius:20px;font-size:13px;font-weight:600;cursor:pointer;border:1.5px solid var(--border);background:var(--white);color:var(--text-secondary);text-decoration:none;transition:all 0.2s;display:flex;align-items:center;gap:6px;}
        .tab-btn:hover{border-color:var(--primary);color:var(--primary);}
        .tab-btn.active{background:var(--primary);color:white;border-color:var(--primary);}
        .tab-count{background:rgba(168,53,53,0.08);color:var(--primary);padding:1px 7px;border-radius:10px;font-size:11px;}
        .tab-btn.active .tab-count{background:rgba(255,255,255,0.25);color:white;}
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
        .chip.color-chip.on{background:#A83535;color:white;border-color:#A83535;}
        .chip.bw-chip.on{background:#4b5563;color:white;border-color:#4b5563;}
        .chip.mixed-chip.on{background:#7c3aed;color:white;border-color:#7c3aed;}
        .filter-actions{display:flex;align-items:center;gap:10px;border-top:1px solid var(--border);padding-top:14px;}
        .filter-clear{font-size:13px;color:var(--text-secondary);text-decoration:none;padding:6px 12px;border-radius:7px;transition:all 0.15s;}
        .filter-clear:hover{background:rgba(168,53,53,0.06);color:var(--primary);}

        /* ── Active filter pills ── */
        .active-pills{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;}
        .active-pill{display:inline-flex;align-items:center;gap:5px;padding:3px 10px 3px 12px;background:rgba(168,53,53,0.08);color:var(--primary);border:1px solid rgba(168,53,53,0.2);border-radius:20px;font-size:12px;font-weight:600;}
        .active-pill a{color:var(--primary);text-decoration:none;opacity:0.6;margin-left:2px;font-size:14px;line-height:1;}
        .active-pill a:hover{opacity:1;}

        /* ── Table ── */
        .card{background:var(--white);border-radius:12px;border:1px solid var(--border);box-shadow:var(--card-shadow);overflow:hidden;}
        .data-table{width:100%;border-collapse:collapse;}
        .data-table thead{background:rgba(168,53,53,0.04);border-bottom:2px solid var(--border);}
        .data-table th{padding:13px 16px;text-align:left;font-size:11px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.4px;white-space:nowrap;}
        .data-table tbody tr.main-row{border-bottom:1px solid var(--border);transition:background 0.15s;cursor:pointer;}
        .data-table tbody tr.main-row:hover{background:rgba(168,53,53,0.02);}
        .data-table td{padding:13px 16px;font-size:13px;vertical-align:middle;}
        .mono{font-family:monospace;font-size:12px;font-weight:700;color:var(--primary);}
        .empty-state{text-align:center;padding:48px;color:var(--text-secondary);}
        .empty-state i{font-size:40px;opacity:0.15;display:block;margin-bottom:12px;}

        /* ── Detail panel ── */
        .detail-row{display:none;}
        .detail-row.open{display:table-row;}
        .detail-row td{padding:0 !important;border-bottom:2px solid var(--border);}
        .detail-inner{padding:20px 24px 22px;background:#fafafa;}

        /* ── Page colour map ── */
        .page-map{display:flex;flex-wrap:wrap;gap:4px;margin-top:8px;}
        .page-dot{width:28px;height:28px;border-radius:5px;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;}
        .page-dot.colour{background:#fdf2f2;color:#A83535;border:1px solid #fca5a5;}
        .page-dot.bw{background:#f3f4f6;color:#6b7280;border:1px solid #E0E0E0;}

        /* ── Info grid ── */
        .info-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:16px;}
        .info-block .ilabel{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.4px;color:var(--text-secondary);margin-bottom:3px;}
        .info-block .ivalue{font-size:14px;font-weight:600;color:var(--text-primary);}

        /* ── File viewer buttons ── */
        .file-actions{display:flex;gap:8px;align-items:center;margin-bottom:16px;padding:12px 16px;background:var(--white);border-radius:9px;border:1px solid var(--border);}
        .btn-view{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;padding:8px 16px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;text-decoration:none;transition:background 0.2s;}
        .btn-view:hover{background:#1d4ed8;color:white;}
        .btn-download{background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;padding:8px 16px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;text-decoration:none;transition:background 0.2s;}
        .btn-download:hover{background:#15803d;color:white;}

        /* ── Price + action row ── */
        .price-override{display:flex;align-items:center;gap:10px;margin-top:14px;padding:14px 16px;background:var(--white);border-radius:9px;border:1px solid var(--border);flex-wrap:wrap;}
        .price-label{font-size:13px;font-weight:600;color:var(--text-primary);white-space:nowrap;}
        .price-input{padding:8px 12px;border:1.5px solid var(--border);border-radius:7px;font-size:14px;font-weight:700;width:120px;color:var(--primary);outline:none;}
        .price-input:focus{border-color:var(--primary);}
        .btn{padding:9px 18px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:7px;transition:all 0.2s;}
        .btn-reviewed{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;}
        .btn-reviewed:hover{background:#2e7d32;color:white;}
        .btn-reject{background:#fef2f2;color:#c62828;border:1px solid #ef9a9a;}
        .btn-reject:hover{background:#c62828;color:white;}
        .btn-done{background:#f3f4f6;color:#6b7280;border:1px solid #E0E0E0;cursor:not-allowed;}

        /* ── File viewer modal ── */
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:1000;align-items:center;justify-content:center;padding:20px;}
        .modal-overlay.open{display:flex;}
        .modal-box{background:var(--white);border-radius:14px;width:90%;max-width:900px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.3);}
        .modal-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;}
        .modal-title{font-size:15px;font-weight:700;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:calc(100% - 80px);}
        .modal-close{background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-secondary);padding:4px 8px;border-radius:6px;transition:background 0.2s;}
        .modal-close:hover{background:rgba(168,53,53,0.08);color:var(--primary);}
        .modal-body{flex-grow:1;overflow:auto;padding:0;display:flex;align-items:center;justify-content:center;min-height:400px;background:#f8f8f8;}
        .modal-body iframe{width:100%;height:100%;min-height:500px;border:none;}
        .modal-body img{max-width:100%;max-height:80vh;object-fit:contain;padding:16px;}

        .toast{position:fixed;bottom:28px;right:28px;padding:14px 20px;background:#2e7d32;color:white;border-radius:10px;font-size:14px;font-weight:600;box-shadow:0 4px 20px rgba(0,0,0,0.15);z-index:1100;transform:translateY(80px);opacity:0;transition:all 0.3s;display:flex;align-items:center;gap:8px;}
        .toast.show{transform:translateY(0);opacity:1;}
        .toast.error{background:#c62828;}
        .page-footer{text-align:center;padding:20px;color:var(--text-secondary);font-size:13px;border-top:1px solid var(--border);background:var(--white);}

        @media(max-width:1024px){
            :root{--sidebar-width:70px;}
            .logo-text,.nav-text,.user-details,.nav-title,.logout-link span{display:none;}
            .logo-area,.nav-section,.user-section{padding:18px 12px;}
            .nav-link{justify-content:center;padding:14px;border-left:none;border-right:4px solid transparent;}
            .nav-link:hover,.nav-link.active{border-left:none;border-right-color:var(--primary);}
            .nav-icon{margin-right:0;font-size:20px;}
            .logout-link{justify-content:center;}
        }
        @media(max-width:900px){.info-grid{grid-template-columns:1fr 1fr;}}
    </style>
</head>
<body>

<?php include 'smart_sidebar.php'; ?>

<main class="main-content">
    <header class="top-header">
        <div>
            <h1 class="page-title">Print File Review</h1>
            <p class="page-subtitle">Review AI colour analysis, confirm pricing, and process print jobs</p>
        </div>
        <?php if ($counts['RECEIVED'] > 0): ?>
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:9px;padding:10px 16px;font-size:13px;color:#92400e;font-weight:600;">
            <i class="fas fa-print"></i>
            <?= $counts['RECEIVED'] ?> file<?= $counts['RECEIVED'] > 1 ? 's' : '' ?> awaiting review
        </div>
        <?php endif; ?>
    </header>

    <div class="content-wrap">

        <div class="filter-bar">
            <?php
            $tabs = ['ALL'=>'All','RECEIVED'=>'Received','REVIEWED'=>'Reviewed','REJECTED'=>'Rejected'];
            foreach ($tabs as $key => $label):
                $url = filterUrl(['status'=>$key]);
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
                <?php foreach(['status','sort','print_type','paper_size','binding','copies'] as $k): ?>
                <input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars($$k === 'filterStatus' ? $filterStatus : $$k) ?>">
                <?php endforeach; ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
                <input type="text" name="search" class="search-input"
                    placeholder="Search file ID, filename, customer…"
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
                        <div class="filter-group-label"><i class="fas fa-palette"></i> Print Type</div>
                        <div class="chip-row">
                            <?php $ptMap = ['all'=>'All','color'=>'🎨 Colour Only','bw'=>'⬛ B&W Only','mixed'=>'🎭 Mixed']; ?>
                            <?php foreach($ptMap as $v=>$l): ?>
                            <label class="chip <?= $printType===$v?'on':'' ?> <?= $v.'-chip' ?>">
                                <input type="radio" name="print_type" value="<?= $v ?>" <?= $printType===$v?'checked':'' ?> onchange="this.form.submit()" style="display:none;">
                                <?= $l ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div>
                        <div class="filter-group-label"><i class="fas fa-ruler"></i> Paper Size</div>
                        <div class="chip-row">
                            <?php foreach(['all'=>'All','A4'=>'A4','A3'=>'A3','A5'=>'A5','A2'=>'A2','A1'=>'A1','A0'=>'A0'] as $v=>$l): ?>
                            <label class="chip <?= $paperSize===$v?'on':'' ?>">
                                <input type="radio" name="paper_size" value="<?= $v ?>" <?= $paperSize===$v?'checked':'' ?> onchange="this.form.submit()" style="display:none;">
                                <?= $l ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div>
                        <div class="filter-group-label"><i class="fas fa-paperclip"></i> Binding</div>
                        <div class="chip-row">
                            <?php foreach(['all'=>'All','NONE'=>'No Binding','STAPLE'=>'Staple','SPIRAL'=>'Spiral'] as $v=>$l): ?>
                            <label class="chip <?= $bindingFilter===$v?'on':'' ?>">
                                <input type="radio" name="binding" value="<?= $v ?>" <?= $bindingFilter===$v?'checked':'' ?> onchange="this.form.submit()" style="display:none;">
                                <?= $l ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div>
                        <div class="filter-group-label"><i class="fas fa-copy"></i> Copies</div>
                        <div class="chip-row">
                            <?php foreach(['all'=>'Any','single'=>'Single (1)','multiple'=>'Multiple (2+)'] as $v=>$l): ?>
                            <label class="chip <?= $copiesFilter===$v?'on':'' ?>">
                                <input type="radio" name="copies" value="<?= $v ?>" <?= $copiesFilter===$v?'checked':'' ?> onchange="this.form.submit()" style="display:none;">
                                <?= $l ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>

                <div class="filter-actions">
                    <span style="font-size:13px;color:var(--text-secondary);">
                        <?= count($files) ?> result<?= count($files)!==1?'s':'' ?> found
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
            if($sortOrder!=='newest')     echo "<span class='active-pill'>".($sortOrder==='oldest'?'Oldest First':'Sort')."<a href='".filterUrl(['sort'=>''])."'>×</a></span>";
            if($printType!=='all')        echo "<span class='active-pill'>".['color'=>'🎨 Colour Only','bw'=>'⬛ B&W Only','mixed'=>'🎭 Mixed'][$printType]."<a href='".filterUrl(['print_type'=>''])."'>×</a></span>";
            if($paperSize!=='all')        echo "<span class='active-pill'>Paper: $paperSize<a href='".filterUrl(['paper_size'=>''])."'>×</a></span>";
            if($bindingFilter!=='all')    echo "<span class='active-pill'>Binding: ".ucfirst(strtolower($bindingFilter))."<a href='".filterUrl(['binding'=>''])."'>×</a></span>";
            if($copiesFilter!=='all')     echo "<span class='active-pill'>".ucfirst($copiesFilter)." copy<a href='".filterUrl(['copies'=>''])."'>×</a></span>";
            ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>File ID</th>
                            <th>Customer</th>
                            <th>Filename</th>
                            <th>Pages</th>
                            <th>Colour / B&W</th>
                            <th>Print Type</th>
                            <th>Est. Price</th>
                            <th>Uploaded</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($files)): ?>
                        <tr><td colspan="10">
                            <div class="empty-state">
                                <i class="fas fa-file-upload"></i>
                                <p>No print files found<?= $filterStatus !== 'ALL' ? ' with status <strong>' . strtolower($filterStatus) . '</strong>' : '' ?>.</p>
                            </div>
                        </td></tr>
                        <?php else: foreach ($files as $i => $f):
                            $analysis = json_decode($f['ai_analysis'] ?? '{}', true);
                            $pages    = $analysis['pages'] ?? [];
                            $isImage  = in_array(strtoupper($f['file_type'] ?? ''), ['JPG','JPEG','PNG']);
                            $isPdf    = strtoupper($f['file_type'] ?? '') === 'PDF';
                        ?>
                        <tr class="main-row" onclick="toggleDetail(<?= $i ?>)">
                            <td style="width:36px;">
                                <i class="fas fa-chevron-right" id="chev-<?= $i ?>"
                                   style="color:var(--text-secondary);font-size:11px;transition:transform 0.2s;"></i>
                            </td>
                            <td><span class="mono"><?= htmlspecialchars($f['file_id']) ?></span></td>
                            <td>
                                <div style="font-weight:600;"><?= htmlspecialchars($f['customer_name'] ?? '—') ?></div>
                                <div style="font-size:11px;color:var(--text-secondary);"><?= htmlspecialchars($f['customer_id'] ?? '') ?></div>
                            </td>
                            <td>
                                <div style="font-weight:600;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($f['file_name']) ?>">
                                    <?= htmlspecialchars($f['file_name']) ?>
                                </div>
                                <div style="font-size:11px;color:var(--text-secondary);"><?= htmlspecialchars($f['file_type'] ?? '') ?></div>
                            </td>
                            <td style="font-weight:600;"><?= $f['total_pages'] ?></td>
                            <td>
                                <?php if ($f['color_pages'] > 0): ?><span style="color:#A83535;font-weight:600;"><?= $f['color_pages'] ?>C</span><?php endif; ?>
                                <?php if ($f['bw_pages']    > 0): ?><span style="color:#6b7280;"> / <?= $f['bw_pages'] ?>B&W</span><?php endif; ?>
                            </td>
                            <td>
                                <span style="font-size:12px;font-weight:600;padding:2px 9px;border-radius:20px;
                                    <?= $f['print_type'] === 'COLOR' ? 'background:#fdf2f2;color:#A83535;' : 'background:#f3f4f6;color:#6b7280;' ?>">
                                    <?= $f['print_type'] === 'COLOR' ? '🎨 Colour' : '⬛ B&W' ?>
                                </span>
                            </td>
                            <td style="font-weight:700;color:var(--primary);">RM <?= number_format($f['estimated_price'], 2) ?></td>
                            <td style="font-size:12px;color:var(--text-secondary);"><?= date('d M Y', strtotime($f['upload_date'])) ?></td>
                            <td><?= fileStatusBadge($f['file_status']) ?></td>
                        </tr>

                        <!-- Detail row -->
                        <tr class="detail-row" id="det-<?= $i ?>">
                            <td colspan="10">
                                <div class="detail-inner">

                                    <!-- File viewer buttons -->
                                    <div class="file-actions">
                                        <i class="fas fa-file" style="color:var(--primary);font-size:16px;"></i>
                                        <span style="font-size:13px;font-weight:600;color:var(--text-primary);margin-right:4px;">
                                            <?= htmlspecialchars($f['file_name']) ?>
                                        </span>

                                        <?php if ($isPdf || $isImage): ?>
                                        <button class="btn-view" onclick="openViewer('<?= htmlspecialchars($f['file_path']) ?>', '<?= htmlspecialchars($f['file_name']) ?>', '<?= $isPdf ? 'pdf' : 'image' ?>')">
                                            <i class="fas fa-eye"></i> View File
                                        </button>
                                        <?php endif; ?>

                                        <a class="btn-download"
                                           href="<?= htmlspecialchars($f['file_path']) ?>"
                                           download="<?= htmlspecialchars($f['file_name']) ?>">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>

                                    <!-- Info grid -->
                                    <div class="info-grid">
                                        <div class="info-block">
                                            <div class="ilabel">Paper</div>
                                            <div class="ivalue"><?= htmlspecialchars($f['paper_size']) ?> — <?= htmlspecialchars($f['paper_type']) ?></div>
                                        </div>
                                        <div class="info-block">
                                            <div class="ilabel">Binding</div>
                                            <div class="ivalue"><?= htmlspecialchars($f['binding_type']) ?></div>
                                        </div>
                                        <div class="info-block">
                                            <div class="ilabel">Copies</div>
                                            <div class="ivalue"><?= $f['copies'] ?></div>
                                        </div>
                                        <div class="info-block">
                                            <div class="ilabel">Linked Order</div>
                                            <div class="ivalue mono" style="font-size:12px;"><?= $f['order_id'] ? htmlspecialchars($f['order_id']) : '—' ?></div>
                                        </div>
                                    </div>

                                    <!-- AI page colour map -->
                                    <?php if (!empty($pages)): ?>
                                    <div style="margin-bottom:16px;">
                                        <div style="font-size:12px;font-weight:600;color:var(--text-secondary);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.4px;">
                                            AI Page Colour Map
                                            <?php if (!($analysis['parse_ok'] ?? true)): ?>
                                            <span style="color:#f59e0b;font-weight:400;margin-left:6px;">(AI parse was uncertain — verify manually)</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="page-map">
                                            <?php foreach ($pages as $pg): ?>
                                            <div class="page-dot <?= $pg['is_color'] ? 'colour' : 'bw' ?>"
                                                 title="Page <?= $pg['page'] ?>: <?= $pg['is_color'] ? 'Colour' : 'B&W' ?> (<?= $pg['confidence'] ?? '' ?> confidence)">
                                                <?= $pg['page'] ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div style="display:flex;gap:14px;margin-top:6px;">
                                            <span style="font-size:12px;color:var(--text-secondary);display:flex;align-items:center;gap:5px;">
                                                <span style="width:12px;height:12px;border-radius:3px;background:#fdf2f2;border:1px solid #fca5a5;display:inline-block;"></span> Colour
                                            </span>
                                            <span style="font-size:12px;color:var(--text-secondary);display:flex;align-items:center;gap:5px;">
                                                <span style="width:12px;height:12px;border-radius:3px;background:#f3f4f6;border:1px solid #E0E0E0;display:inline-block;"></span> B&W
                                            </span>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Price override + actions -->
                                    <?php if ($f['file_status'] === 'RECEIVED'): ?>
                                    <div class="price-override">
                                        <span class="price-label">Final Price (RM):</span>
                                        <input type="number" class="price-input" id="price-<?= $i ?>"
                                            value="<?= number_format($f['estimated_price'], 2, '.', '') ?>"
                                            step="0.10" min="0">
                                        <span style="font-size:12px;color:var(--text-secondary);">
                                            AI estimate: RM <?= number_format($f['estimated_price'], 2) ?> — override if needed
                                        </span>
                                    </div>
                                    <div style="display:flex;gap:10px;margin-top:12px;align-items:center;">
                                        <button class="btn btn-reviewed" onclick="updateStatus('<?= $f['file_id'] ?>', 'REVIEWED', <?= $i ?>)">
                                            <i class="fas fa-check-circle"></i> Approve &amp; Mark Reviewed
                                        </button>
                                        <button class="btn btn-reject" onclick="updateStatus('<?= $f['file_id'] ?>', 'REJECTED', <?= $i ?>)">
                                            <i class="fas fa-times-circle"></i> Reject File
                                        </button>
                                    </div>
                                    <?php else: ?>
                                    <div style="font-size:13px;color:var(--text-secondary);margin-top:4px;">
                                        <i class="fas fa-lock" style="margin-right:6px;"></i>
                                        This file has been <?= strtolower($f['file_status']) ?>.
                                        Final price: <strong>RM <?= number_format($f['estimated_price'], 2) ?></strong>
                                    </div>
                                    <?php endif; ?>

                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <footer class="page-footer">
        &copy; <?= date('Y') ?> StationaryPlus — Stationery &amp; Printing Management System
    </footer>
</main>

<!-- File viewer modal -->
<div class="modal-overlay" id="fileModal" onclick="closeModal(event)">
    <div class="modal-box">
        <div class="modal-header">
            <span class="modal-title" id="modalTitle">File Viewer</span>
            <div style="display:flex;gap:8px;">
                <a id="modalDownload" href="#" download
                   style="background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;padding:6px 14px;border-radius:7px;font-size:13px;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:6px;">
                    <i class="fas fa-download"></i> Download
                </a>
                <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <div class="modal-body" id="modalBody"></div>
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

// ── Row toggle ────────────────────────────────────────────────
function toggleDetail(i) {
    const det  = document.getElementById('det-' + i);
    const chev = document.getElementById('chev-' + i);
    const open = det.classList.contains('open');
    det.classList.toggle('open', !open);
    chev.style.transform = open ? 'rotate(0deg)' : 'rotate(90deg)';
}

// ── File viewer ───────────────────────────────────────────────
function openViewer(filePath, fileName, type) {
    document.getElementById('modalTitle').textContent    = fileName;
    document.getElementById('modalDownload').href        = filePath;
    document.getElementById('modalDownload').download    = fileName;

    const body = document.getElementById('modalBody');
    if (type === 'pdf') {
        body.innerHTML = `<iframe src="${filePath}" style="width:100%;height:70vh;border:none;"></iframe>`;
    } else {
        body.innerHTML = `<img src="${filePath}" alt="${fileName}" style="max-width:100%;max-height:75vh;object-fit:contain;padding:16px;">`;
    }

    document.getElementById('fileModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeModal(e) {
    if (e && e.target !== document.getElementById('fileModal') && !e.target.closest('.modal-close')) return;
    document.getElementById('fileModal').classList.remove('open');
    document.getElementById('modalBody').innerHTML = '';
    document.body.style.overflow = '';
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeModal();
});

// ── Status update ─────────────────────────────────────────────
async function updateStatus(fileId, status, rowIndex) {
    let rejectionReason = '';

    if (status === 'REJECTED') {
        // Prompt staff for a rejection reason before sending
        rejectionReason = await customPrompt(
            `Rejection reason for ${fileId}:`,
            {
                placeholder: 'This message will be shown to the customer…',
                required: true,
                confirmText: 'Reject File'
            }
        );
        // null = staff cancelled the dialog
        if (rejectionReason === null) return;
    } else {
        // Approve path
        const ok = await customConfirm(`Approve print file ${fileId}?`, { confirmText: 'Approve' });
        if (!ok) return;
    }
 
    const form = new FormData();
    form.append('file_id', fileId);
    form.append('status',  status);
    if (status === 'REVIEWED') {
        const p = document.getElementById('price-' + rowIndex);
        if (p) form.append('final_price', p.value);
    }
    if (status === 'REJECTED') {
        form.append('rejection_reason', rejectionReason);
    }
 
    try {
        const res  = await fetch('update_print_status.php', { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, status === 'REVIEWED' ? 'success' : 'error');
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
    const t   = document.getElementById('toast');
    t.innerHTML = (type === 'success' ? '<i class="fas fa-check-circle"></i> ' : '<i class="fas fa-times-circle"></i> ') + msg;
    t.className = 'toast show' + (type === 'error' ? ' error' : '');
    setTimeout(() => t.classList.remove('show'), 3000);
}
</script>
</body>
</html>