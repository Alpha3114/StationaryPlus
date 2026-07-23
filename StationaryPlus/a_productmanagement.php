<?php

require_once 'auth.php';
require_role(['STAFF', 'ADMIN']);
require_once 'db.php';
require_once 'audit.php';

$userName = $_SESSION['user_name'];
$userId   = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];
$isAdmin  = ($userRole === 'ADMIN');

// ── Handle restock request review (Accept / Reject) — ADMIN only ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_request']) && $isAdmin) {
    $requestId = trim($_POST['request_id'] ?? '');
    $action    = trim($_POST['action']     ?? ''); // 'accept' | 'reject'
    $adminNote = trim($_POST['admin_note'] ?? '') ?: null;

    if ($requestId && in_array($action, ['accept', 'reject'])) {
        $check = $conn->prepare(
            "SELECT request_id FROM restock_requests WHERE request_id = ? AND status = 'PENDING' LIMIT 1"
        );
        $check->bind_param('s', $requestId);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $newStatus = $action === 'accept' ? 'ORDERED' : 'REJECTED';
            $stmt = $conn->prepare(
                "UPDATE restock_requests
                 SET status = ?, reviewed_by = ?, admin_note = ?, reviewed_at = NOW()
                 WHERE request_id = ?"
            );
            $stmt->bind_param('ssss', $newStatus, $userId, $adminNote, $requestId);
            $stmt->execute();
            $stmt->close();
        }
        $check->close();
    }

    $tabParam = '?tab=restock' . (!empty($_GET['rstatus']) ? '&rstatus=' . urlencode($_GET['rstatus']) : '');
    header('Location: a_productmanagement.php' . $tabParam);
    exit;
}

// ── Create a follow-up restock request for a shortfall — ADMIN only ──
// One request can only ever spawn one follow-up (guarded by the
// followup_request_id IS NULL check inside the locked transaction).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_followup']) && $isAdmin) {
    $origId = trim($_POST['request_id'] ?? '');

    if ($origId) {
        $conn->begin_transaction();

        $stmt = $conn->prepare(
            "SELECT product_id, branch_id, requested_qty, received_qty, followup_request_id
             FROM restock_requests
             WHERE request_id = ? AND status = 'RECEIVED' AND has_issue = 1 LIMIT 1 FOR UPDATE"
        );
        $stmt->bind_param('s', $origId);
        $stmt->execute();
        $orig = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $followupOutcome = 'skipped'; // 'created' | 'skipped' — used to decide where to land after redirect

        if ($orig && $orig['followup_request_id'] === null) {
            $shortfall = (int)$orig['requested_qty'] - (int)$orig['received_qty'];

            if ($shortfall > 0) {
                $newId = 'RR-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
                $ins = $conn->prepare(
                    "INSERT INTO restock_requests
                        (request_id, product_id, branch_id, requested_qty, source, status,
                         requested_by, original_request_id)
                     VALUES (?, ?, ?, ?, 'MANUAL', 'PENDING', ?, ?)"
                );
                $ins->bind_param(
                    'sssiss', $newId, $orig['product_id'], $orig['branch_id'],
                    $shortfall, $userId, $origId
                );
                $ins->execute();
                $ins->close();

                $upd = $conn->prepare("UPDATE restock_requests SET followup_request_id = ? WHERE request_id = ?");
                $upd->bind_param('ss', $newId, $origId);
                $upd->execute();
                $upd->close();

                log_audit(
                    $conn, 'RESTOCK_FOLLOWUP_CREATED', 'restock_request', $newId,
                    "shortfall=$shortfall original=$origId"
                );
                $followupOutcome = 'created';
            }
            $conn->commit();
        } else {
            $conn->rollback();
        }
    }

    // A newly created follow-up lands as a PENDING request — send the admin
    // there instead of echoing back the RECEIVED tab they clicked from,
    // otherwise the new row is invisible and looks like nothing happened.
    if (($followupOutcome ?? 'skipped') === 'created') {
        $tabParam = '?tab=restock&rstatus=ALL&followup=created';
    } else {
        $tabParam = '?tab=restock'
            . (!empty($_GET['rstatus']) ? '&rstatus=' . urlencode($_GET['rstatus']) : '')
            . '&followup=skipped';
    }
    header('Location: a_productmanagement.php' . $tabParam);
    exit;
}

// ── Filters ──────────────────────────────────────────────────
$search         = trim($_GET['psearch']  ?? '');
$filterCategory = trim($_GET['category'] ?? 'all');
$filterStatus   = $_GET['pstatus']       ?? 'all';
if (!in_array($filterStatus, ['ACTIVE', 'INACTIVE'])) $filterStatus = 'all';

// True total, independent of filters (used in the header stat)
$totalProductsCount = 0;
if ($conn) {
    $totalRes = $conn->query("SELECT COUNT(*) AS cnt FROM products");
    $totalProductsCount = $totalRes ? (int)$totalRes->fetch_assoc()['cnt'] : 0;
}

// Distinct categories for the filter dropdown
$categoryList = [];
if ($conn) {
    $catRes = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category <> '' ORDER BY category");
    if ($catRes) {
        while ($row = $catRes->fetch_assoc()) $categoryList[] = $row['category'];
    }
}

$where  = ['1=1'];
$params = [];
$types  = '';

if ($search !== '') {
    $like     = "%$search%";
    $where[]  = "(product_name LIKE ? OR product_id LIKE ?)";
    $params[] = $like; $params[] = $like;
    $types   .= 'ss';
}
if ($filterCategory !== 'all') {
    $where[]  = "category = ?";
    $params[] = $filterCategory;
    $types   .= 's';
}
if ($filterStatus !== 'all') {
    $where[]  = "product_status = ?";
    $params[] = $filterStatus;
    $types   .= 's';
}

$products = [];
if ($conn) {
    $sql  = "SELECT product_id, product_name, category, price, product_status, last_updated, image_path, discount_percent
              FROM products WHERE " . implode(' AND ', $where) . "
              ORDER BY FIELD(product_status, 'ACTIVE', 'INACTIVE'), product_id";
    $stmt = $conn->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Both counts are scoped to requests whose product still exists, matching
// the INNER JOIN the requests list below already uses — otherwise a
// request left behind by a deleted product would inflate these counts
// while never actually appearing in the list.
$pendingCountRes = $conn->query(
    "SELECT COUNT(*) AS cnt FROM restock_requests rr
     JOIN products p ON rr.product_id = p.product_id
     WHERE rr.status = 'PENDING'"
);
$pendingRequestCount = $pendingCountRes ? (int)$pendingCountRes->fetch_assoc()['cnt'] : 0;

$totalRequestsRes = $conn->query(
    "SELECT COUNT(*) AS cnt FROM restock_requests rr
     JOIN products p ON rr.product_id = p.product_id"
);
$totalRequestCount = $totalRequestsRes ? (int)$totalRequestsRes->fetch_assoc()['cnt'] : 0;

// ── Restock requests list — filterable by status ──────────────
// Defaults to ALL (with PENDING requests surfaced at the top via the
// ORDER BY below) rather than hard-filtering to PENDING on first load,
// so admins see the full picture without an extra click.
$requestFilter = $_GET['rstatus'] ?? 'ALL';
$validRStatus  = ['PENDING', 'ORDERED', 'RECEIVED', 'REJECTED', 'ALL'];
if (!in_array($requestFilter, $validRStatus)) $requestFilter = 'ALL';

$rWhere = [];
$rParams = [];
$rTypes = '';
if ($requestFilter !== 'ALL') {
    $rWhere[] = 'rr.status = ?';
    $rParams[] = $requestFilter;
    $rTypes .= 's';
}
$rWhereSQL = $rWhere ? 'WHERE ' . implode(' AND ', $rWhere) : '';

$rStmt = $conn->prepare(
    "SELECT rr.request_id, rr.requested_qty, rr.received_qty, rr.damaged_qty, rr.has_issue,
            rr.followup_request_id, rr.original_request_id,
            rr.source, rr.status,
            rr.staff_note, rr.admin_note, rr.requested_at, rr.reviewed_at, rr.received_at,
            p.product_id, p.product_name,
            b.branch_name,
            ureq.name AS requested_by_name,
            urev.name AS reviewed_by_name,
            urec.name AS received_by_name
     FROM restock_requests rr
     JOIN products p ON rr.product_id = p.product_id
     JOIN branches b ON rr.branch_id  = b.branch_id
     LEFT JOIN users ureq ON rr.requested_by = ureq.user_id
     LEFT JOIN users urev ON rr.reviewed_by  = urev.user_id
     LEFT JOIN users urec ON rr.received_by  = urec.user_id
     $rWhereSQL
     ORDER BY (rr.status = 'PENDING') DESC, rr.requested_at DESC
     LIMIT 100"
);
if ($rTypes) $rStmt->bind_param($rTypes, ...$rParams);
$rStmt->execute();
$restockRequests = $rStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$rStmt->close();

function requestStatusBadge(string $status): string {
    $map = [
        'PENDING'  => ['var(--warning)', 'rgba(244,162,97,0.15)', 'Pending'],
        'ORDERED'  => ['#1d4ed8', 'rgba(37,99,235,0.1)',   'Ordered'],
        'RECEIVED' => ['var(--success)', 'rgba(76,175,80,0.1)',   'Received'],
        'REJECTED' => ['var(--primary)', 'var(--primary-tint-medium)',   'Rejected'],
    ];
    [$color, $bg, $label] = $map[$status] ?? ['#6b7280', '#f3f4f6', $status];
    return "<span class='request-status' style='color:$color;background:$bg;'>$label</span>";
}

function rrIssueBadge(array $req): string {
    if ((string)$req['status'] !== 'RECEIVED' || !$req['has_issue']) return '';
    $short = (int)$req['requested_qty'] - (int)$req['received_qty'];
    $parts = [];
    if ($short > 0) $parts[] = "$short short";
    if ((int)$req['damaged_qty'] > 0) $parts[] = $req['damaged_qty'] . ' damaged';
    $label = htmlspecialchars(implode(', ', $parts));
    return "<span style='display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;
        padding:2px 8px;border-radius:10px;background:var(--warning-bg);color:var(--warning);
        border:1px solid var(--warning-border-soft);margin-top:4px;'>
        <i class='fas fa-triangle-exclamation'></i> $label</span>";
}

function sourceBadge(string $source): string {
    return $source === 'AI'
        ? "<span style='font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;'><i class='fas fa-robot'></i> AI</span>"
        : "<span style='font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;background:#f3f4f6;color:#6b7280;border:1px solid #e5e7eb;'><i class='fas fa-pen'></i> Manual</span>";
}

// Initial tab — defaults to catalog unless ?tab=restock is passed (e.g. from sidebar badge)
$initialTab = ($_GET['tab'] ?? 'catalog') === 'restock' ? 'restock' : 'catalog';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus - Product Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/tokens.css?v=<?= @filemtime(__DIR__.'/assets/css/tokens.css') ?>">
    <script src="assets/js/theme.js?v=<?= @filemtime(__DIR__.'/assets/js/theme.js') ?>"></script>
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?= @filemtime(__DIR__.'/assets/css/sidebar.css') ?>">
    <style>
        :root {
            --primary: #A83535;      /* Brick Red */
            --secondary: #F4A261;    /* Muted Orange */
            --background: #FAFAFA;   /* Light Grey */
            --accent: #F1EDE8;
            --text-primary: #2E2E2E;         /* Dark Charcoal */
            --text-secondary: #707070;   /* Secondary Text */
            --border: #E0E0E0;       /* Border Grey */
            --white: #FFFFFF;
            --sidebar-width: 260px;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        
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
            overflow: auto;
        }
        
        /* Main Content Area */
        .main-content {
            flex-grow: 1;
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .top-header {
            background-color: var(--white);
            padding: 18px 28px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-left h1 {
            font-size: 22px;
            color: var(--text-primary);
            margin-bottom: 4px;
            font-weight: 700;
        }
        
        .header-left p {
            font-size: 13px;
            color: var(--text-secondary);
        }
        
        .header-right {
            font-size: 13px;
            color: var(--text-secondary);
            background-color: var(--primary-tint-subtle);
            padding: 8px 15px;
            border-radius: 20px;
        }

        /* Tab bar */
        .tab-bar {
            display: flex;
            gap: 4px;
            padding: 14px 28px 0;
            background-color: var(--white);
            border-bottom: 1px solid var(--border);
        }
        .tab-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 11px 20px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .tab-btn:hover {
            color: var(--primary);
            background: var(--primary-tint-subtle);
        }
        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        .tab-badge {
            background: #A83535;
            color: var(--on-primary);
            font-size: 10px;
            font-weight: 700;
            padding: 1px 7px;
            border-radius: 10px;
            min-width: 16px;
            text-align: center;
        }
        .tab-panel { display: none; }
        .tab-panel.active { display: flex; flex-direction: column; }

        /* Product Management Content - Two Column Layout */
        .product-management {
            flex-grow: 1;
            padding: 25px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            height: calc(100vh - 130px);
            overflow: hidden;
        }
        
        /* Left Section: Product Catalog + Low Stock Requests */
        .left-section {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }
        
        /* Product Catalog Table */
        .table-section {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            flex: 1;
        }
        
        .section-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            background-color: var(--primary-tint-subtle);
        }

        .filter-bar {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border);
            background: var(--primary-tint-subtle);
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-bar form { display:flex; gap:8px; flex-wrap:wrap; align-items:center; flex:1; }
        .search-wrap { position:relative; flex:1; min-width:160px; }
        .search-icon { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--text-secondary); font-size:13px; }
        .search-input { width:100%; padding:8px 28px 8px 32px; border:1.5px solid var(--border); border-radius:7px; font-size:13px; background:var(--white); }
        .search-input:focus { outline:none; border-color:var(--primary); }
        .search-clear { display:none; position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-secondary); cursor:pointer; font-size:13px; padding:2px 4px; }
        .search-clear:hover { color:var(--primary); }
        .search-clear.show { display:block; }
        .filter-select { padding:8px 12px; border:1.5px solid var(--border); border-radius:7px; font-size:13px; background:var(--white); color:var(--text-primary); cursor:pointer; }
        .filter-select:focus { outline:none; border-color:var(--primary); }
        .filter-btn { padding:8px 16px; background:var(--primary); color:var(--on-primary); border:none; border-radius:7px; font-size:13px; font-weight:600; cursor:pointer; }
        .filter-btn:hover { background:var(--primary-dark); }
        .filter-clear { font-size:12px; color:var(--text-secondary); text-decoration:none; white-space:nowrap; }
        .filter-clear:hover { color:var(--primary); }

        
        .section-header h2 {
            font-size: 18px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-header h2 i {
            font-size: 18px;
        }
        
        .table-container {
            flex-grow: 1;
            overflow: auto;
        }
        
        .product-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        
        .product-table thead {
            background-color: var(--primary-tint-subtle);
            position: sticky;
            top: 0;
        }
        
        .product-table th {
            padding: 16px 18px;
            text-align: left;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 13px;
            border-bottom: 1px solid var(--border);
        }
        
        .product-table td {
            padding: 16px 18px;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .product-table tbody tr:hover {
            background-color: var(--primary-tint-subtle);
            cursor: pointer;
        }

        .product-table tbody tr.selected {
            background-color: var(--primary-tint-subtle);
        }
        
        .product-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .product-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--on-primary);
            font-size: 16px;
            flex-shrink: 0;
        }

        .icon-paper {
            background-color: #4A6FA5;
        }
        
        .icon-pen {
            background-color: #FF9800;
        }
        
        .icon-folder {
            background-color: #4CAF50;
        }
        
        .icon-binding {
            background-color: #795548;
        }
        
        .icon-ink {
            background-color: #9C27B0;
        }
        
        .icon-stapler {
            background-color: #607D8B;
        }
        
        .product-name {
            font-weight: 600;
            font-size: 13px;
        }
        
        .product-sku {
            font-size: 11px;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        .product-category {
            font-size: 13px;
            color: var(--text-primary);
        }
        
        .product-price {
            font-weight: 600;
            color: var(--primary);
            font-size: 14px;
        }
        
        .product-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            text-align: center;
        }
        
        .status-active {
            background-color: var(--success-bg);
            color: var(--success);
        }

        .status-inactive {
            background-color: rgba(158, 158, 158, 0.15);
            color: #616161;
        }
        
        /* Low Stock Requests Section */
        .requests-section {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            flex: 1;
            min-height: 250px;
        }
        
        .requests-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            background-color: rgba(244, 162, 97, 0.05);
        }
        
        .requests-header h2 {
            font-size: 18px;
            color: var(--secondary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .requests-header h2 i {
            font-size: 18px;
        }
        
        .requests-container {
            flex-grow: 1;
            overflow: auto;
        }
        
        .requests-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .requests-table thead {
            background-color: rgba(244, 162, 97, 0.05);
            position: sticky;
            top: 0;
        }
        
        .requests-table th {
            padding: 14px 18px;
            text-align: left;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 12px;
            border-bottom: 1px solid var(--border);
        }
        
        .requests-table td {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border);
            font-size: 12px;
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .requests-table tbody tr:hover {
            background-color: rgba(244, 162, 97, 0.02);
        }
        
        .request-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            text-align: center;
        }
        
        .status-pending {
            background-color: rgba(244, 162, 97, 0.15);
            color: var(--secondary);
        }
        
        .status-approved {
            background-color: var(--success-bg);
            color: var(--success);
        }

        .status-rejected {
            background-color: var(--primary-tint-medium);
            color: var(--primary);
        }
        
        .request-action {
            display: flex;
            gap: 6px;
        }
        
        .action-btn {
            padding: 4px 10px;
            border: none;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .approve-btn {
            background-color: var(--success-bg);
            color: var(--success);
        }

        .approve-btn:hover {
            background-color: rgba(76, 175, 80, 0.2);
        }

        .reject-btn {
            background-color: var(--danger-bg);
            color: var(--danger);
        }

        .reject-btn:hover {
            background-color: var(--danger);
            color: var(--on-primary);
        }
        
        /* Right Section: Product Form */
        .form-section {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .form-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            background-color: var(--primary-tint-subtle);
        }
        
        .form-header h2 {
            font-size: 18px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-header h2 i {
            font-size: 18px;
        }

        .form-mode-badge { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
        .badge-edit { background: var(--primary-tint-medium); color: var(--primary); }
        .badge-new  { background: var(--success-bg); color: var(--success); }

        .form-container {
            flex-grow: 1;
            padding: 25px;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            min-height: 0;
        }

        /* Placeholder state (shown before any selection) */
        .form-placeholder { display: flex; flex-direction: column; align-items: center; justify-content: center; flex-grow: 1; color: var(--text-secondary); text-align: center; padding: 30px; gap: 12px; }
        .form-placeholder i { font-size: 38px; opacity: 0.2; }
        .form-placeholder p { font-size: 14px; }

        /* Status toggle buttons (edit mode only) */
        .status-btn { flex: 1; padding: 12px; border-radius: 6px; font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .status-btn-activate { background-color: var(--success-bg); color: var(--success); border: 1.5px solid var(--success); }
        .status-btn-activate:hover { background-color: rgba(76,175,80,0.2); }
        .status-btn-deactivate { background-color: var(--danger-bg); color: var(--danger); border: 1.5px solid var(--danger); }
        .status-btn-deactivate:hover { background-color: rgba(239,68,68,0.16); }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 13px;
        }
        
        .form-input {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid var(--border);
            border-radius: 6px;
            font-size: 13px;
            transition: all 0.2s ease;
            background-color: var(--white);
            color: var(--text-primary);
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary-tint-medium);
        }
        
        .form-select {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid var(--border);
            border-radius: 6px;
            font-size: 13px;
            transition: all 0.2s ease;
            background-color: var(--white);
            color: var(--text-primary);
        }
        
        .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary-tint-medium);
        }
        
        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 5px;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
        }
        
        .radio-option input {
            margin-right: 6px;
            accent-color: var(--primary);
        }
        
        .radio-label {
            color: var(--text-primary);
            font-size: 13px;
        }
        
        .price-input-container {
            position: relative;
        }
        
        .price-prefix {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 13px;
        }
        
        .price-input {
            padding-left: 35px;
        }
        
        /* Form Actions */
        .form-actions {
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 12px;
        }
        
        .primary-btn {
            flex: 1;
            padding: 12px;
            background-color: var(--primary);
            color: var(--on-primary);
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .primary-btn:hover {
            background-color: var(--primary-dark);
        }

        .secondary-btn {
            flex: 1;
            padding: 12px;
            background-color: var(--primary-tint-medium);
            color: var(--primary);
            border: 1.5px solid var(--primary);
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .secondary-btn:hover {
            background-color: var(--primary-tint-active);
        }
        
        /* Table column widths for product table */
        .product-table th:nth-child(1), .product-table td:nth-child(1) {
            width: 10%;
        }

        .product-table th:nth-child(2), .product-table td:nth-child(2) {
            width: 28%;
        }

        .product-table th:nth-child(3), .product-table td:nth-child(3) {
            width: 13%;
        }

        .product-table th:nth-child(4), .product-table td:nth-child(4) {
            width: 15%;
        }

        .product-table th:nth-child(5), .product-table td:nth-child(5) {
            width: 12%;
        }

        .product-table th:nth-child(6), .product-table td:nth-child(6) {
            width: 22%;
        }

        .discount-badge {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            background: rgba(244,162,97,0.18);
            color: #b45309;
        }
        
        /* Table column widths for requests table */
        .requests-table th:nth-child(1), .requests-table td:nth-child(1) {
            width: 40%;
        }
        
        .requests-table th:nth-child(2), .requests-table td:nth-child(2) {
            width: 25%;
        }
        
        .requests-table th:nth-child(3), .requests-table td:nth-child(3) {
            width: 20%;
        }
        
        .requests-table th:nth-child(4), .requests-table td:nth-child(4) {
            width: 15%;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .product-management {
                grid-template-columns: 1fr;
                grid-template-rows: 1fr 1fr;
            }
            
            .left-section {
                height: 100%;
            }
        }
        
        /* Scrollbar styling */
        .table-container::-webkit-scrollbar,
        .requests-container::-webkit-scrollbar {
            width: 6px;
        }
        
        .table-container::-webkit-scrollbar-track,
        .requests-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .table-container::-webkit-scrollbar-thumb,
        .requests-container::-webkit-scrollbar-thumb {
            background: var(--primary-tint-active);
            border-radius: 3px;
        }
        /* ── Custom Dialog (replaces native alert/confirm) ── */
        .custom-dialog-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center; }
        .custom-dialog-overlay.show { display:flex; }
        .custom-dialog-box { background:var(--white);border-radius:12px;width:90%;max-width:400px;padding:28px 26px 22px;box-shadow:0 20px 60px rgba(0,0,0,0.2);text-align:center;animation:dialogPop 0.15s ease; }
        @keyframes dialogPop { from{transform:scale(0.95);opacity:0;} to{transform:scale(1);opacity:1;} }
        .custom-dialog-icon { width:52px;height:52px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:22px; }
        .custom-dialog-icon.dialog-info { background:#eff6ff;color:#1d4ed8; }
        .custom-dialog-icon.dialog-success { background:var(--success-bg);color:var(--success); }
        .custom-dialog-icon.dialog-error { background:var(--danger-bg);color:var(--danger); }
        .custom-dialog-icon.dialog-warning { background:var(--warning-bg);color:var(--warning); }
        .custom-dialog-message { font-size:14px;color:#2E2E2E;line-height:1.6;margin-bottom:22px;white-space:pre-line; }
        .custom-dialog-actions { display:flex;gap:10px; }
        .custom-dialog-btn { flex:1;padding:11px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:none;transition:background 0.2s ease; }
        .custom-dialog-cancel { background:#F1EDE8;color:#2E2E2E;border:1.5px solid #E0E0E0; }
        .custom-dialog-cancel:hover { background:#e8e2da; }
        .custom-dialog-confirm { background:#A83535;color:var(--on-primary); }
        .custom-dialog-confirm:hover { background:var(--primary-dark); }
        .custom-dialog-danger { background:var(--danger);color:var(--on-primary); }
        .custom-dialog-danger:hover { background:#b91c1c; }
    </style>
</head>
<body>
    <?php include 'a_sidebar.php'; ?>
    
    <!-- Main Content Area -->
    <main class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="header-left">
                <h1>Product Management</h1>
                <p>Manage product catalog details and restock requests</p>
            </div>
            <div class="header-right" style="display:flex;align-items:center;gap:12px;">
                <span>Total Products: <?= $totalProductsCount ?></span>
                <button type="button" class="theme-toggle-header-btn" data-theme-cycle title="Theme" aria-label="Theme"><i class="fas fa-sun"></i></button>
            </div>
        </header>
        <script>if (window.initThemeToggle) initThemeToggle();</script>

        <!-- Tab bar -->
        <div class="tab-bar">
            <button type="button" class="tab-btn" id="tabBtnCatalog" onclick="switchTab('catalog')">
                <i class="fas fa-boxes"></i> Product Catalog
            </button>
            <button type="button" class="tab-btn" id="tabBtnRestock" onclick="switchTab('restock')">
                <i class="fas fa-truck-loading"></i> Restock Requests
                <?php if ($pendingRequestCount > 0): ?>
                <span class="tab-badge"><?= $pendingRequestCount ?></span>
                <?php endif; ?>
            </button>
        </div>
        
        <!-- Product Management Content -->
        <div class="tab-panel active" id="tabCatalog">
        <div class="product-management">
            <!-- Product Catalog -->
            <section class="table-section">
                <div class="section-header">
                    <h2><i class="fas fa-boxes"></i> Product Catalog</h2>
                </div>

                <div class="filter-bar">
                    <form method="GET" action="a_productmanagement.php" style="display:contents;">
                        <input type="hidden" name="tab" value="catalog">
                        <div class="search-wrap">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" name="psearch" id="productSearchInput" class="search-input"
                                   placeholder="Search name or ID…"
                                   value="<?= htmlspecialchars($search) ?>"
                                   oninput="document.getElementById('productSearchClear').classList.toggle('show', this.value.length > 0)">
                            <button type="button" id="productSearchClear" class="search-clear <?= $search !== '' ? 'show' : '' ?>"
                                    title="Clear search"
                                    onclick="document.getElementById('productSearchInput').value='';this.classList.remove('show');this.form.submit();">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <select name="category" class="filter-select">
                            <option value="all" <?= $filterCategory==='all' ? 'selected':'' ?>>All Categories</option>
                            <?php foreach ($categoryList as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= $filterCategory===$cat ? 'selected':'' ?>>
                                <?= htmlspecialchars($cat) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="pstatus" class="filter-select">
                            <option value="all"      <?= $filterStatus==='all'      ? 'selected':'' ?>>All Statuses</option>
                            <option value="ACTIVE"   <?= $filterStatus==='ACTIVE'   ? 'selected':'' ?>>Active</option>
                            <option value="INACTIVE" <?= $filterStatus==='INACTIVE' ? 'selected':'' ?>>Inactive</option>
                        </select>
                        <button type="submit" class="filter-btn"><i class="fas fa-filter"></i> Filter</button>
                        <?php if ($search !== '' || $filterCategory !== 'all' || $filterStatus !== 'all'): ?>
                        <a href="a_productmanagement.php?tab=catalog" class="filter-clear"><i class="fas fa-times"></i> Clear</a>
                        <?php endif; ?>
                    </form>
                    <button type="button" class="secondary-btn add-new-btn" id="addNewBtn">
                        <i class="fas fa-plus"></i> Add New
                    </button>
                </div>

                <div class="table-container">
                    <table class="product-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Discount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr><td colspan="6" style="color:var(--text-secondary); padding:18px;">
                                    <?= ($search !== '' || $filterCategory !== 'all' || $filterStatus !== 'all')
                                        ? 'No products match your filters.'
                                        : 'No products found in the database.' ?>
                                </td></tr>
                            <?php else: ?>
                                <?php foreach ($products as $p): ?>
                                    <tr data-id="<?= htmlspecialchars($p['product_id']) ?>"
                                        onclick="loadProduct(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)">
                                        <td><?php echo htmlspecialchars($p['product_id']); ?></td>
                                        <td>
                                            <div class="product-info">
                                                <div class="product-icon icon-paper">
                                                    <i class="fas fa-boxes"></i>
                                                </div>
                                                <div>
                                                    <div class="product-name"><?php echo htmlspecialchars($p['product_name']); ?></div>
                                                    <div class="product-sku">Last updated: <?php echo htmlspecialchars($p['last_updated']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="product-category"><?php echo htmlspecialchars($p['category']); ?></td>
                                        <td class="product-price">RM <?php echo number_format((float)$p['price'], 2); ?></td>
                                        <td><?php $pDiscount = (float)($p['discount_percent'] ?? 0); ?>
                                            <?php if ($pDiscount > 0): ?>
                                                <span class="discount-badge">-<?php echo rtrim(rtrim(number_format($pDiscount, 2), '0'), '.'); ?>%</span>
                                            <?php else: ?>
                                                <span style="color:var(--text-secondary);">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php
                                            $pStatusVal = strtoupper(trim($p['product_status']));
                                            $pStatusClass = ($pStatusVal === 'ACTIVE') ? 'status-active' : 'status-inactive';
                                        ?><span class="product-status <?php echo $pStatusClass; ?>"><?php echo htmlspecialchars($p['product_status']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            
            <!-- Right Section: Product Form -->
            <section class="form-section">
                <div class="form-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h2><i class="fas fa-edit"></i> <span id="formTitle">Product Details</span></h2>
                    <span class="form-mode-badge badge-new" id="modeBadge" style="display:none;">New</span>
                </div>

                <div class="form-container" id="formContainer">

                    <!-- Placeholder (shown before any selection) -->
                    <div class="form-placeholder" id="formPlaceholder">
                        <i class="fas fa-box-open"></i>
                        <p>Select a product from the table to edit,<br>or click <strong>Add New</strong> to create one.</p>
                    </div>

                    <div id="productFormFields" style="display:none; flex-direction:column; flex-grow:1;">
                        <div class="form-group">
                            <label class="form-label">Product ID</label>
                            <input type="text" class="form-input" id="fieldProductId" placeholder="Auto-generated or enter ID">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Product Name</label>
                            <input type="text" class="form-input" id="fieldProductName" placeholder="Enter product name">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select class="form-select" id="fieldCategory">
                                <?php foreach ($categoryList as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Product Image <span style="font-weight:400;color:var(--text-secondary);">(optional)</span></label>
                            <div style="display:flex;align-items:center;gap:14px;">
                                <div id="productImagePreviewWrap" style="width:64px;height:64px;border-radius:8px;background:var(--background);border:1.5px dashed var(--border);display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;">
                                    <i class="fas fa-image" style="color:var(--text-secondary);font-size:20px;" id="productImagePlaceholderIcon"></i>
                                    <img id="productImagePreview" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;">
                                </div>
                                <input type="file" id="productImageInput" accept="image/jpeg,image/png,image/webp" style="font-size:12px;">
                            </div>
                            <input type="hidden" id="productImageCurrent" value="">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Price (RM)</label>
                            <div class="price-input-container">
                                <span class="price-prefix">RM</span>
                                <input type="text" class="form-input price-input" id="fieldPrice" placeholder="0.00">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Discount % <span style="font-weight:400;color:var(--text-secondary);">(optional, 0–100)</span></label>
                            <input type="text" class="form-input" id="fieldDiscount" placeholder="0">
                        </div>

                        <!-- Status: hidden in Add mode (new products are always Active) -->
                        <input type="hidden" id="fieldStatus" value="ACTIVE">
                        <div class="form-group" id="statusFieldWrap" style="display:none;">
                            <label class="form-label">Status</label>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <span class="product-status" id="statusBadgeDisplay"></span>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button class="primary-btn" id="saveBtn">
                                <i class="fas fa-save"></i> <span id="submitLabel">Add Product</span>
                            </button>
                            <button type="button" class="status-btn status-btn-deactivate" id="statusToggleBtn" style="display:none;"
                                    onclick="toggleProductStatus()">
                                <i class="fas fa-ban" id="statusToggleIcon"></i> <span id="statusToggleLabel">Deactivate</span>
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        </div><!-- /#tabCatalog -->

        <!-- Restock Requests Tab Panel -->
        <div class="tab-panel" id="tabRestock" style="padding:25px;">

            <?php if (($_GET['followup'] ?? '') === 'created'): ?>
            <div style="margin-bottom:16px;padding:10px 14px;background:var(--success-bg);color:var(--success);
                        border:1px solid var(--success-border);border-radius:8px;font-size:13px;font-weight:600;
                        display:flex;align-items:center;gap:8px;">
                <i class="fas fa-check-circle"></i> Follow-up request created — it's now waiting for review below.
            </div>
            <?php elseif (($_GET['followup'] ?? '') === 'skipped'): ?>
            <div style="margin-bottom:16px;padding:10px 14px;background:var(--warning-bg);color:var(--warning);
                        border:1px solid var(--warning-border-soft);border-radius:8px;font-size:13px;font-weight:600;
                        display:flex;align-items:center;gap:8px;">
                <i class="fas fa-info-circle"></i> No follow-up was created — a follow-up may already exist for this request, or there's no shortfall to re-order.
            </div>
            <?php endif; ?>

            <div class="stats-row" style="display:grid;grid-template-columns:repeat(2,1fr);gap:18px;margin-bottom:20px;">
                <div class="stat-card" style="background-color:var(--white);border-radius:10px;padding:20px;box-shadow:var(--card-shadow);border:1px solid var(--border);display:flex;align-items:center;gap:14px;">
                    <div class="stat-icon" style="width:42px;height:42px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;background:rgba(244,162,97,0.15);color:var(--warning);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <div style="font-size:24px;font-weight:700;color:var(--primary);"><?= $pendingRequestCount ?></div>
                        <div style="font-size:12px;color:var(--text-secondary);font-weight:600;text-transform:uppercase;letter-spacing:0.4px;">Pending Review</div>
                    </div>
                </div>
                <div class="stat-card" style="background-color:var(--white);border-radius:10px;padding:20px;box-shadow:var(--card-shadow);border:1px solid var(--border);display:flex;align-items:center;gap:14px;">
                    <div class="stat-icon" style="width:42px;height:42px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;background:var(--primary-tint-medium);color:var(--primary);">
                        <i class="fas fa-list"></i>
                    </div>
                    <div>
                        <div style="font-size:24px;font-weight:700;color:var(--primary);"><?= $totalRequestCount ?></div>
                        <div style="font-size:12px;color:var(--text-secondary);font-weight:600;text-transform:uppercase;letter-spacing:0.4px;">Total Requests</div>
                    </div>
                </div>
            </div>

            <section class="requests-section">
                <div class="requests-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h2><i class="fas fa-truck-loading"></i> Restock Requests</h2>
                    <form method="GET" action="a_productmanagement.php" style="margin:0;">
                        <input type="hidden" name="tab" value="restock">
                        <select name="rstatus" onchange="this.form.submit()"
                                style="padding:7px 12px;border:1.5px solid var(--border);border-radius:6px;
                                       font-size:13px;font-weight:600;color:var(--text-primary);background:var(--white);cursor:pointer;">
                            <option value="PENDING"  <?= $requestFilter==='PENDING'  ? 'selected':'' ?>>Pending</option>
                            <option value="ORDERED"  <?= $requestFilter==='ORDERED'  ? 'selected':'' ?>>Ordered</option>
                            <option value="RECEIVED" <?= $requestFilter==='RECEIVED' ? 'selected':'' ?>>Received</option>
                            <option value="REJECTED" <?= $requestFilter==='REJECTED' ? 'selected':'' ?>>Rejected</option>
                            <option value="ALL"      <?= $requestFilter==='ALL'      ? 'selected':'' ?>>All</option>
                        </select>
                    </form>
                </div>

                <div class="requests-container">
                    <?php if (empty($restockRequests)): ?>
                        <div style="padding:48px 20px;text-align:center;color:var(--text-secondary);">
                            <i class="fas fa-truck-loading" style="font-size:38px;opacity:0.2;margin-bottom:12px;display:block;"></i>
                            <p>No <?= strtolower($requestFilter) === 'all' ? '' : strtolower($requestFilter) . ' ' ?>restock requests found.</p>
                        </div>
                    <?php else: ?>
                    <table class="requests-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Branch</th>
                                <th>Qty</th>
                                <th>Source</th>
                                <th>Status</th>
                                <?php if ($isAdmin): ?><th>Action</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($restockRequests as $req): ?>
                            <tr style="<?= $req['has_issue'] ? 'border-left:3px solid var(--warning);' : '' ?>">
                                <td>
                                    <div class="product-info">
                                        <div class="product-icon icon-paper" style="width: 28px; height: 28px; font-size: 14px;">
                                            <i class="fas fa-box"></i>
                                        </div>
                                        <div>
                                            <div class="product-name"><?= htmlspecialchars($req['product_name']) ?></div>
                                            <div class="product-sku">
                                                by <?= htmlspecialchars($req['requested_by_name'] ?? '—') ?>
                                                · <?= date('d M, H:i', strtotime($req['requested_at'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($req['branch_name']) ?></td>
                                <td style="font-weight:700;"><?= (int)$req['requested_qty'] ?></td>
                                <td><?= sourceBadge($req['source']) ?></td>
                                <td>
                                    <?= requestStatusBadge($req['status']) ?>
                                    <?php if ($req['status'] === 'REJECTED' && !empty($req['admin_note'])): ?>
                                    <div style="font-size:10px;color:var(--text-secondary);margin-top:3px;max-width:160px;">
                                        <?= htmlspecialchars($req['admin_note']) ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($req['status'] === 'RECEIVED'): ?>
                                    <div style="font-size:10px;color:var(--text-secondary);margin-top:3px;">
                                        by <?= htmlspecialchars($req['received_by_name'] ?? '—') ?>
                                    </div>
                                    <div><?= rrIssueBadge($req) ?></div>
                                    <?php endif; ?>
                                </td>
                                <?php if ($isAdmin): ?>
                                <td>
                                    <?php if ($req['status'] === 'PENDING'): ?>
                                    <div class="request-action">
                                        <button type="button" class="action-btn approve-btn"
                                                onclick="reviewRequest('<?= htmlspecialchars($req['request_id'], ENT_QUOTES) ?>','accept','<?= htmlspecialchars(addslashes($req['product_name'])) ?>')">
                                            Accept
                                        </button>
                                        <button type="button" class="action-btn reject-btn"
                                                onclick="reviewRequest('<?= htmlspecialchars($req['request_id'], ENT_QUOTES) ?>','reject','<?= htmlspecialchars(addslashes($req['product_name'])) ?>')">
                                            Reject
                                        </button>
                                    </div>
                                    <?php elseif ($req['status'] === 'RECEIVED' && $req['has_issue'] && $req['followup_request_id'] === null && (int)$req['requested_qty'] - (int)$req['received_qty'] > 0): ?>
                                    <button type="button" class="action-btn approve-btn"
                                            onclick="createFollowup('<?= htmlspecialchars($req['request_id'], ENT_QUOTES) ?>')">
                                        Create Follow-up (<?= (int)$req['requested_qty'] - (int)$req['received_qty'] ?> short)
                                    </button>
                                    <?php elseif ($req['status'] === 'RECEIVED' && $req['followup_request_id'] !== null): ?>
                                    <span style="font-size:11px;color:var(--text-secondary);">Follow-up created</span>
                                    <?php else: ?>
                                    <span style="font-size:11px;color:var(--text-secondary);">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </section>
        </div><!-- /#tabRestock -->
    </main>
    
    <!-- Hidden form used by reviewRequest() to submit accept/reject -->
    <form method="POST" action="a_productmanagement.php?tab=restock<?= !empty($_GET['rstatus']) ? '&rstatus=' . urlencode($_GET['rstatus']) : '' ?>" id="reviewForm" style="display:none;">
        <input type="hidden" name="review_request" value="1">
        <input type="hidden" name="request_id" id="reviewRequestId">
        <input type="hidden" name="action" id="reviewAction">
        <input type="hidden" name="admin_note" id="reviewNote">
    </form>

    <!-- Hidden form used by createFollowup() to submit a follow-up request -->
    <form method="POST" action="a_productmanagement.php?tab=restock<?= !empty($_GET['rstatus']) ? '&rstatus=' . urlencode($_GET['rstatus']) : '' ?>" id="followupForm" style="display:none;">
        <input type="hidden" name="create_followup" value="1">
        <input type="hidden" name="request_id" id="followupRequestId">
    </form>

    <!-- Reject reason modal -->
    <div id="rejectModalOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:300;align-items:center;justify-content:center;">
        <div style="background:var(--white);border-radius:12px;width:90%;max-width:420px;padding:24px;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
            <h3 style="font-size:16px;color:var(--primary);margin-bottom:14px;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-times-circle"></i> Reject Restock Request
            </h3>
            <p style="font-size:13px;color:var(--text-secondary);margin-bottom:14px;" id="rejectProductLabel"></p>
            <label style="font-size:12px;font-weight:600;color:var(--text-primary);margin-bottom:6px;display:block;">
                Reason <span style="font-weight:400;color:var(--text-secondary);">(shown to staff)</span>
            </label>
            <textarea id="rejectReasonInput" rows="3"
                      style="width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;
                             font-size:13px;font-family:inherit;resize:none;margin-bottom:16px;"
                      placeholder="e.g. Stock already on the way from another order"></textarea>
            <div style="display:flex;gap:10px;">
                <button type="button" onclick="closeRejectModal()"
                        style="flex:1;padding:10px;background:var(--background);border:1.5px solid var(--border);
                               border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
                    Cancel
                </button>
                <button type="button" onclick="confirmReject()"
                        style="flex:1;padding:10px;background:var(--primary);color:var(--on-primary);border:none;
                               border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
                    Confirm Reject
                </button>
            </div>
        </div>
    </div>
    
    <!-- Custom Dialog -->
    <div id="customDialogOverlay" class="custom-dialog-overlay">
        <div class="custom-dialog-box">
            <div class="custom-dialog-icon" id="customDialogIcon"><i class="fas fa-info-circle"></i></div>
            <p class="custom-dialog-message" id="customDialogMessage"></p>
            <div class="custom-dialog-actions">
                <button class="custom-dialog-btn custom-dialog-cancel" id="customDialogCancelBtn" style="display:none;">Cancel</button>
                <button class="custom-dialog-btn custom-dialog-confirm" id="customDialogConfirmBtn">OK</button>
            </div>
        </div>
    </div>

    <script>
        // ── Custom Dialog System (replaces native alert()/confirm()) ──
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
    </script>

    <script>
        // ── Tab switching ──────────────────────────────────────────────
        function switchTab(name) {
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));

            document.getElementById(name === 'restock' ? 'tabRestock' : 'tabCatalog').classList.add('active');
            document.getElementById(name === 'restock' ? 'tabBtnRestock' : 'tabBtnCatalog').classList.add('active');

            // Reflect in URL without a full reload, so refresh/back keeps the tab
            const url = new URL(window.location);
            url.searchParams.set('tab', name);
            window.history.replaceState({}, '', url);
        }

        // Initialize the correct tab on page load (from ?tab= or PHP default)
        switchTab('<?= $initialTab ?>');

        // ── Restock request review (Accept / Reject) ────────────────────
        let pendingRejectId = null;

        function reviewRequest(requestId, action, productName) {
            if (action === 'accept') {
                customConfirm(
                    `Mark "${productName}" as ORDERED?\n\nThis means you've contacted the supplier and placed the order. Stock will only update once the branch confirms it has been received.`,
                    { confirmText: 'Mark as Ordered' }
                ).then(ok => {
                    if (!ok) return;
                    document.getElementById('reviewRequestId').value = requestId;
                    document.getElementById('reviewAction').value    = 'accept';
                    document.getElementById('reviewNote').value      = '';
                    document.getElementById('reviewForm').submit();
                });
            } else {
                pendingRejectId = requestId;
                document.getElementById('rejectProductLabel').textContent = `Rejecting request for: ${productName}`;
                document.getElementById('rejectReasonInput').value = '';
                document.getElementById('rejectModalOverlay').style.display = 'flex';
            }
        }

        function closeRejectModal() {
            document.getElementById('rejectModalOverlay').style.display = 'none';
            pendingRejectId = null;
        }

        function createFollowup(requestId) {
            customConfirm(
                'Create a follow-up restock request for the shortfall? It will go through the normal review process.',
                { confirmText: 'Create Follow-up' }
            ).then(ok => {
                if (!ok) return;
                document.getElementById('followupRequestId').value = requestId;
                document.getElementById('followupForm').submit();
            });
        }

        function confirmReject() {
            if (!pendingRejectId) return;
            document.getElementById('reviewRequestId').value = pendingRejectId;
            document.getElementById('reviewAction').value    = 'reject';
            document.getElementById('reviewNote').value      = document.getElementById('rejectReasonInput').value.trim();
            document.getElementById('reviewForm').submit();
        }

        // Navigation interactions
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                document.querySelectorAll('.nav-link').forEach(item => {
                    item.classList.remove('active');
                });
                this.classList.add('active');
            });
        });
        
        // Show/hide the Details form vs. the placeholder
        function showForm(show) {
            document.getElementById('formPlaceholder').style.display   = show ? 'none'  : 'flex';
            document.getElementById('productFormFields').style.display = show ? 'flex'  : 'none';
        }

        // Populate the form from a clicked table row's product data
        function loadProduct(p) {
            showForm(true);

            const idInput = document.getElementById('fieldProductId');
            idInput.value = p.product_id;
            idInput.setAttribute('readonly', 'readonly');
            document.getElementById('fieldProductName').value = p.product_name;
            document.getElementById('fieldCategory').value = p.category;
            document.getElementById('fieldPrice').value = parseFloat(p.price).toFixed(2);
            document.getElementById('fieldDiscount').value = parseFloat(p.discount_percent || 0).toString();

            document.getElementById('productImageInput').value = '';
            document.getElementById('productImageCurrent').value = p.image_path || '';
            showProductImagePreview(p.image_path || '');

            const status = (p.product_status || 'ACTIVE').toUpperCase();
            document.getElementById('fieldStatus').value = status;
            document.getElementById('statusFieldWrap').style.display = 'block';
            const badge = document.getElementById('statusBadgeDisplay');
            badge.textContent = status === 'ACTIVE' ? 'Active' : 'Inactive';
            badge.className = 'product-status ' + (status === 'ACTIVE' ? 'status-active' : 'status-inactive');

            const isActive = status === 'ACTIVE';
            document.getElementById('statusToggleLabel').textContent = isActive ? 'Deactivate' : 'Activate';
            document.getElementById('statusToggleIcon').className    = isActive ? 'fas fa-ban' : 'fas fa-check-circle';
            document.getElementById('statusToggleBtn').className     = 'status-btn ' + (isActive ? 'status-btn-deactivate' : 'status-btn-activate');
            document.getElementById('statusToggleBtn').style.display = 'flex';

            document.getElementById('formTitle').textContent = 'Edit Product';
            document.getElementById('modeBadge').textContent = 'Edit';
            document.getElementById('modeBadge').className   = 'form-mode-badge badge-edit';
            document.getElementById('modeBadge').style.display = 'inline-block';
            document.getElementById('submitLabel').textContent = 'Update Product';

            document.querySelectorAll('.product-table tbody tr').forEach(r => r.classList.remove('selected'));
            const row = document.querySelector(`.product-table tbody tr[data-id="${p.product_id}"]`);
            if (row) row.classList.add('selected');
        }

        // Instant status toggle (edit mode only)
        async function toggleProductStatus() {
            const id   = document.getElementById('fieldProductId').value;
            const name = document.getElementById('fieldProductName').value;
            const activating = document.getElementById('statusToggleLabel').textContent === 'Activate';
            const verb = activating ? 'Activate' : 'Deactivate';

            const ok = await customConfirm(`${verb} "${name}"?`, { danger: !activating, confirmText: verb });
            if (!ok) return;

            const formData = new FormData();
            formData.append('product_id', id);

            try {
                const res = await fetch('toggle_product_status.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    await customAlert(`Product ${data.new_status === 'ACTIVE' ? 'activated' : 'deactivated'} successfully.`, 'success');
                    window.location.reload();
                } else {
                    await customAlert('Status change failed: ' + (data.error || 'unknown error'), 'error');
                }
            } catch (err) {
                await customAlert('Request error: ' + err.message, 'error');
            }
        }

        // Show/hide the image preview vs. placeholder icon
        function showProductImagePreview(src) {
            const img  = document.getElementById('productImagePreview');
            const icon = document.getElementById('productImagePlaceholderIcon');
            if (src) {
                img.src = src;
                img.style.display = 'block';
                icon.style.display = 'none';
            } else {
                img.src = '';
                img.style.display = 'none';
                icon.style.display = 'block';
            }
        }

        // Live preview when a new file is chosen
        document.getElementById('productImageInput').addEventListener('change', function() {
            const file = this.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = e => showProductImagePreview(e.target.result);
            reader.readAsDataURL(file);
        });
        
        // Save product button (sends to save_product.php)
        document.getElementById('saveBtn').addEventListener('click', async function() {
            const id = document.getElementById('fieldProductId').value.trim();
            const name = document.getElementById('fieldProductName').value.trim();
            const category = document.getElementById('fieldCategory').value;
            const price = document.getElementById('fieldPrice').value.trim();
            const discount = document.getElementById('fieldDiscount').value.trim();
            const status = document.getElementById('fieldStatus').value;

            const formData = new FormData();
            formData.append('product_id', id);
            formData.append('product_name', name);
            formData.append('category', category);
            formData.append('price', price);
            formData.append('discount_percent', discount);
            formData.append('product_status', status);

            const imageFile = document.getElementById('productImageInput').files[0];
            if (imageFile) formData.append('product_image', imageFile);

            try {
                const res = await fetch('save_product.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    await customAlert('Product saved successfully (' + (data.action || 'saved') + ').', 'success');
                    window.location.reload();
                } else {
                    await customAlert('Save failed: ' + (data.error || 'unknown error'), 'error');
                }
            } catch (err) {
                await customAlert('Request error: ' + err.message, 'error');
            }
        });

        // Add New button — reveal the form in "new" mode (status hidden, defaults to Active)
        document.getElementById('addNewBtn').addEventListener('click', clearForm);

        // Logout button (real link — no JS interception needed)

        // Price input validation (numbers only with decimal)
        document.getElementById('fieldPrice').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9.]/g, '');

            // Ensure only one decimal point
            const parts = this.value.split('.');
            if (parts.length > 2) {
                this.value = parts[0] + '.' + parts.slice(1).join('');
            }
        });

        // Discount % input validation (numbers only, clamped 0–100)
        document.getElementById('fieldDiscount').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9.]/g, '');
            const parts = this.value.split('.');
            if (parts.length > 2) {
                this.value = parts[0] + '.' + parts.slice(1).join('');
            }
        });
        document.getElementById('fieldDiscount').addEventListener('blur', function() {
            if (this.value === '') return;
            const v = Math.max(0, Math.min(100, parseFloat(this.value) || 0));
            this.value = v.toString();
        });

        // New product mode: clear fields, hide status controls (new products are always Active)
        function clearForm() {
            showForm(true);

            const idInput = document.getElementById('fieldProductId');
            idInput.value = '';
            idInput.removeAttribute('readonly');
            document.getElementById('fieldProductName').value = '';
            document.getElementById('fieldCategory').selectedIndex = 0;
            document.getElementById('fieldPrice').value = '';
            document.getElementById('fieldDiscount').value = '';
            document.getElementById('fieldStatus').value = 'ACTIVE';
            document.getElementById('statusFieldWrap').style.display = 'none';
            document.getElementById('statusToggleBtn').style.display = 'none';
            document.getElementById('productImageInput').value = '';
            document.getElementById('productImageCurrent').value = '';
            showProductImagePreview('');

            document.getElementById('formTitle').textContent = 'Add New Product';
            document.getElementById('modeBadge').textContent = 'New';
            document.getElementById('modeBadge').className   = 'form-mode-badge badge-new';
            document.getElementById('modeBadge').style.display = 'inline-block';
            document.getElementById('submitLabel').textContent = 'Add Product';

            document.querySelectorAll('.product-table tbody tr').forEach(r => {
                r.classList.remove('selected');
            });
        }
    </script>
</body>
</html>