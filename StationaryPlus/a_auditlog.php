<?php
// ============================================================
//  a_auditlog.php — Admin Audit Log Viewer
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role('ADMIN');
require_once 'db.php';

// ── Filters ──────────────────────────────────────────────────
$search       = trim($_GET['search']  ?? '');
$filterAction = $_GET['action']       ?? 'all';
$filterEntity = $_GET['entity_type']  ?? 'all';
$dateFrom     = trim($_GET['date_from'] ?? '');
$dateTo       = trim($_GET['date_to']   ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 50;

$where  = ['1=1'];
$params = [];
$types  = '';

if ($search !== '') {
    $like     = "%$search%";
    $where[]  = "(actor_name LIKE ? OR actor_id LIKE ? OR entity_id LIKE ? OR details LIKE ?)";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= 'ssss';
}
if ($filterAction !== 'all') {
    $where[]  = "action = ?";
    $params[] = $filterAction;
    $types   .= 's';
}
if ($filterEntity !== 'all') {
    $where[]  = "entity_type = ?";
    $params[] = $filterEntity;
    $types   .= 's';
}
if ($dateFrom !== '') {
    $where[]  = "created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
    $types   .= 's';
}
if ($dateTo !== '') {
    $where[]  = "created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
    $types   .= 's';
}

$whereSQL = implode(' AND ', $where);

// Total count for pagination
$countSql = "SELECT COUNT(*) AS c FROM audit_log WHERE $whereSQL";
$stmt = $conn->prepare($countSql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$totalRows = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$sql = "SELECT log_id, actor_id, actor_name, actor_role, action, entity_type, entity_id, details, created_at
        FROM audit_log
        WHERE $whereSQL
        ORDER BY created_at DESC
        LIMIT $perPage OFFSET $offset";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Distinct values for filter dropdowns
$actionOptions = $conn->query("SELECT DISTINCT action FROM audit_log ORDER BY action")->fetch_all(MYSQLI_ASSOC);
$entityOptions = $conn->query("SELECT DISTINCT entity_type FROM audit_log ORDER BY entity_type")->fetch_all(MYSQLI_ASSOC);

function actionBadgeColor(string $action): array {
    if (str_contains($action, 'CREATE'))   return ['#2e7d32', 'rgba(76,175,80,0.1)'];
    if (str_contains($action, 'REJECT') || str_contains($action, 'DELETE')) return ['#c62828', 'rgba(239,68,68,0.1)'];
    if (str_contains($action, 'STATUS') || str_contains($action, 'TOGGLE')) return ['#e65100', 'rgba(244,162,97,0.15)'];
    return ['#A83535', 'rgba(168,53,53,0.1)'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus - Audit Log</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary:#A83535;--secondary:#F4A261;--accent:#F1EDE8;--background:#FAFAFA;--text-primary:#2E2E2E;--text-secondary:#707070;--border:#E0E0E0;--white:#FFFFFF;--sidebar-width:260px;--card-shadow:0 4px 12px rgba(0,0,0,0.05); }
        * { margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',system-ui,sans-serif; }
        body { background-color:var(--background);color:var(--text-primary);min-height:100vh;display:flex; }
        .main-content { flex-grow:1;margin-left:var(--sidebar-width);min-height:100vh;display:flex;flex-direction:column; }
        .top-header { background-color:var(--white);padding:20px 30px;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:10; }
        .page-title { font-size:24px;font-weight:700; }
        .page-subtitle { font-size:14px;color:var(--text-secondary);margin-top:4px; }
        .content-container { padding:30px;flex-grow:1;display:flex;flex-direction:column;gap:20px; }
        .card { background-color:var(--white);border-radius:12px;overflow:hidden;box-shadow:var(--card-shadow);border:1px solid var(--border); }
        .filter-bar { padding:16px 20px;border-bottom:1px solid var(--border);background:rgba(168,53,53,0.01);display:flex;gap:10px;flex-wrap:wrap;align-items:center; }
        .filter-bar form { display:flex;gap:8px;flex-wrap:wrap;align-items:center;flex:1; }
        .search-wrap { position:relative;flex:1;min-width:200px; }
        .search-icon { position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-secondary);font-size:13px; }
        .search-input { width:100%;padding:8px 12px 8px 32px;border:1.5px solid var(--border);border-radius:7px;font-size:13px; }
        .filter-select, .filter-date { padding:8px 12px;border:1.5px solid var(--border);border-radius:7px;font-size:13px;background:var(--white);cursor:pointer; }
        .filter-btn { padding:8px 16px;background:var(--primary);color:white;border:none;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer; }
        .filter-btn:hover { background:#8b2a2a; }
        .result-count { font-size:12px;color:var(--text-secondary);background:rgba(168,53,53,0.08);padding:3px 10px;border-radius:20px;font-weight:600;white-space:nowrap; }
        .audit-table { width:100%;border-collapse:collapse; }
        .audit-table thead { background:rgba(168,53,53,0.04);border-bottom:2px solid var(--border); }
        .audit-table th { padding:12px 16px;text-align:left;font-size:11px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.4px;white-space:nowrap; }
        .audit-table tbody tr { border-bottom:1px solid var(--border); }
        .audit-table tbody tr:hover { background:rgba(168,53,53,0.02); }
        .audit-table td { padding:12px 16px;font-size:13px;color:var(--text-primary);vertical-align:top; }
        .action-badge { display:inline-block;padding:3px 9px;border-radius:12px;font-size:11px;font-weight:600;white-space:nowrap; }
        .entity-tag { font-size:11px;color:var(--text-secondary);font-family:monospace; }
        .log-id { font-size:11px;color:var(--text-secondary);font-family:monospace; }
        .details-cell { max-width:360px;color:var(--text-secondary);font-size:12px;line-height:1.5; }
        .empty-state { text-align:center;padding:40px;color:var(--text-secondary);font-size:14px; }
        .pagination { display:flex;justify-content:center;align-items:center;gap:8px;padding:16px; }
        .page-link { padding:6px 12px;border:1.5px solid var(--border);border-radius:6px;font-size:13px;text-decoration:none;color:var(--text-primary); }
        .page-link.active { background:var(--primary);color:white;border-color:var(--primary); }
        .page-link.disabled { opacity:0.4;pointer-events:none; }
        @media (max-width:1024px) {
            :root { --sidebar-width:70px; }
        }
    </style>
</head>
<body>

<?php include 'a_sidebar.php'; ?>

<main class="main-content">
    <header class="top-header">
        <h1 class="page-title">Audit Log</h1>
        <p class="page-subtitle">Track of admin and staff actions across the system</p>
    </header>

    <div class="content-container">
        <div class="card">
            <div class="filter-bar">
                <form method="GET" action="a_auditlog.php" style="display:contents;">
                    <div class="search-wrap">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" name="search" class="search-input"
                               placeholder="Search actor, entity ID, details…"
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <select name="action" class="filter-select">
                        <option value="all">All Actions</option>
                        <?php foreach ($actionOptions as $a): ?>
                        <option value="<?= htmlspecialchars($a['action']) ?>" <?= $filterAction===$a['action']?'selected':'' ?>>
                            <?= htmlspecialchars($a['action']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="entity_type" class="filter-select">
                        <option value="all">All Entities</option>
                        <?php foreach ($entityOptions as $e): ?>
                        <option value="<?= htmlspecialchars($e['entity_type']) ?>" <?= $filterEntity===$e['entity_type']?'selected':'' ?>>
                            <?= htmlspecialchars(ucfirst($e['entity_type'])) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" name="date_from" class="filter-date" value="<?= htmlspecialchars($dateFrom) ?>">
                    <input type="date" name="date_to" class="filter-date" value="<?= htmlspecialchars($dateTo) ?>">
                    <button type="submit" class="filter-btn"><i class="fas fa-filter"></i> Filter</button>
                </form>
                <span class="result-count"><?= $totalRows ?> entr<?= $totalRows===1?'y':'ies' ?></span>
            </div>

            <div style="overflow-x:auto;">
                <?php if (empty($logs)): ?>
                    <div class="empty-state">No audit log entries match the selected filters.</div>
                <?php else: ?>
                <table class="audit-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Actor</th>
                            <th>Action</th>
                            <th>Entity</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): [$color, $bg] = actionBadgeColor($log['action']); ?>
                        <tr>
                            <td style="white-space:nowrap;color:var(--text-secondary);">
                                <?= date('d M Y, h:i A', strtotime($log['created_at'])) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($log['actor_name'] ?? 'System') ?>
                                <?php if ($log['actor_role']): ?>
                                <div class="entity-tag"><?= htmlspecialchars($log['actor_role']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="action-badge" style="color:<?= $color ?>;background:<?= $bg ?>;">
                                    <?= htmlspecialchars($log['action']) ?>
                                </span>
                            </td>
                            <td>
                                <?= htmlspecialchars(ucfirst($log['entity_type'])) ?>
                                <?php if ($log['entity_id']): ?>
                                <div class="entity-tag"><?= htmlspecialchars($log['entity_id']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="details-cell"><?= htmlspecialchars($log['details'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <?php if ($totalPages > 1):
                $qs = $_GET; unset($qs['page']);
                $baseQs = http_build_query($qs);
            ?>
            <div class="pagination">
                <a class="page-link <?= $page<=1?'disabled':'' ?>" href="?<?= $baseQs ?>&page=<?= $page-1 ?>">&laquo; Prev</a>
                <span style="font-size:13px;color:var(--text-secondary);">Page <?= $page ?> of <?= $totalPages ?></span>
                <a class="page-link <?= $page>=$totalPages?'disabled':'' ?>" href="?<?= $baseQs ?>&page=<?= $page+1 ?>">Next &raquo;</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

</body>
</html>
