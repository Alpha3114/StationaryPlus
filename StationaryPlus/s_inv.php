<?php
// ============================================================
//  s_inv.php — Staff Inventory Management
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role(['STAFF', 'ADMIN']);
require_once 'db.php';

$userId   = $_SESSION['user_id'];
$branchId = $_SESSION['branch_id'] ?? null;

// ── Handle stock update (POST) ────────────────────────────────
$successMsg = '';
$isWarning  = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    $inventoryId = trim($_POST['inventory_id'] ?? '');
    $newQty      = (int)($_POST['new_qty'] ?? -1);

    if ($inventoryId !== '' && $newQty >= 0) {

        // Fetch current state before changing anything
        $stmt = $conn->prepare(
            "SELECT stock_quantity, reserved_quantity, product_id, branch_id
             FROM inventory WHERE inventory_id = ? LIMIT 1"
        );
        $stmt->bind_param('s', $inventoryId);
        $stmt->execute();
        $current = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($current) {
            $oldQty    = (int)$current['stock_quantity'];
            $reserved  = (int)$current['reserved_quantity'];
            $productId = $current['product_id'];
            $invBranch = $current['branch_id'];
            $changeQty = $newQty - $oldQty;

            // Update the stock level
            $stmt = $conn->prepare(
                "UPDATE inventory SET stock_quantity = ?, last_updated = NOW() WHERE inventory_id = ?"
            );
            $stmt->bind_param('is', $newQty, $inventoryId);
            $stmt->execute();
            $stmt->close();

            // Log the movement (only if quantity actually changed)
            if ($changeQty !== 0) {
                $stmt = $conn->prepare(
                    "INSERT INTO inventory_log
                        (inventory_id, product_id, branch_id, change_qty, old_qty, new_qty,
                         reason, changed_by)
                     VALUES (?, ?, ?, ?, ?, ?, 'MANUAL_UPDATE', ?)"
                );
                $stmt->bind_param('sssiiis',
                    $inventoryId, $productId, $invBranch,
                    $changeQty, $oldQty, $newQty,
                    $userId
                );
                $stmt->execute();
                $stmt->close();
            }

            // Warn (don't block) if the new stock level is now below what's reserved
            // for pending pre-orders — staff may be writing off damaged stock, but
            // should be aware some reservations may no longer be fulfillable.
            // Passed via query string since the redirect below loses in-memory state.
            $reservedWarning = ($reserved > 0 && $newQty < $reserved) ? "$reserved:$newQty" : '';
        }
    }

    $qs = http_build_query(array_filter([
        'search' => $_GET['search'] ?? '',
        'filter' => $_GET['filter'] ?? '',
        'branch' => $_GET['branch'] ?? '',
        'warn'   => $reservedWarning ?? '',
    ]));
    header('Location: s_inv.php' . ($qs ? "?$qs&updated=1" : '?updated=1'));
    exit;
}

if (isset($_GET['updated'])) {
    if (!empty($_GET['warn']) && str_contains($_GET['warn'], ':')) {
        [$resv, $nq] = explode(':', $_GET['warn']);
        $resv = (int)$resv; $nq = (int)$nq;
        $successMsg = "Stock updated, but note: $resv unit(s) are reserved for pending pre-orders "
                    . "and only $nq are now in stock. Some reservations may not be fulfillable.";
        $isWarning = true;
    } else {
        $successMsg = "Stock updated successfully.";
    }
}

// ── Filters ───────────────────────────────────────────────────
$search       = trim($_GET['search'] ?? '');
$filterView   = $_GET['filter']      ?? 'all';
$filterBranch = $_GET['branch']      ?? 'all';

// ── Stats ─────────────────────────────────────────────────────
$res = $conn->query("SELECT COUNT(*) AS cnt FROM products WHERE product_status = 'ACTIVE'");
$totalProducts = $res->fetch_assoc()['cnt'] ?? 0;

$res = $conn->query("SELECT COUNT(*) AS cnt FROM branches WHERE status = 'ACTIVE'");
$totalBranches = $res->fetch_assoc()['cnt'] ?? 0;

if ($branchId) {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM inventory WHERE stock_quantity <= minimum_level AND branch_id = ?"
    );
    $stmt->bind_param('s', $branchId);
} else {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM inventory WHERE stock_quantity <= minimum_level"
    );
}
$stmt->execute();
$lowStockCount = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmt->close();

// ── Branch list for admin filter dropdown ─────────────────────
$branches = $conn->query(
    "SELECT branch_id, branch_name FROM branches WHERE status = 'ACTIVE' ORDER BY branch_name"
)->fetch_all(MYSQLI_ASSOC);

// ── Main inventory query ──────────────────────────────────────
$where  = ["p.product_status = 'ACTIVE'"];
$params = [];
$types  = '';

if ($branchId) {
    $where[]  = "i.branch_id = ?";
    $params[] = $branchId;
    $types   .= 's';
} elseif ($filterBranch !== 'all') {
    $where[]  = "i.branch_id = ?";
    $params[] = $filterBranch;
    $types   .= 's';
}

if ($search !== '') {
    $like     = "%$search%";
    $where[]  = "(p.product_name LIKE ? OR p.category LIKE ?)";
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

if ($filterView === 'low') {
    $where[] = "i.stock_quantity <= i.minimum_level";
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$stmt = $conn->prepare(
    "SELECT i.inventory_id, i.stock_quantity, i.reserved_quantity, i.minimum_level,
            p.product_name, p.category, b.branch_name
     FROM inventory i
     JOIN products p ON i.product_id = p.product_id
     JOIN branches b ON i.branch_id  = b.branch_id
     $whereSQL
     ORDER BY i.stock_quantity ASC, p.product_name ASC
     LIMIT 100"
);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$inventory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Low stock alerts ──────────────────────────────────────────
$alertWhere  = ["i.stock_quantity <= i.minimum_level", "p.product_status = 'ACTIVE'"];
$alertParams = [];
$alertTypes  = '';

if ($branchId) {
    $alertWhere[]  = "i.branch_id = ?";
    $alertParams[] = $branchId;
    $alertTypes   .= 's';
}

$alertWhereSQL = 'WHERE ' . implode(' AND ', $alertWhere);

$stmt = $conn->prepare(
    "SELECT i.inventory_id, i.stock_quantity, i.minimum_level,
            p.product_name, p.category, b.branch_name
     FROM inventory i
     JOIN products p ON i.product_id = p.product_id
     JOIN branches b ON i.branch_id  = b.branch_id
     $alertWhereSQL
     ORDER BY i.stock_quantity ASC
     LIMIT 50"
);
if ($alertTypes) $stmt->bind_param($alertTypes, ...$alertParams);
$stmt->execute();
$lowStockItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Movement history ──────────────────────────────────────────
$histWhere  = ['1=1'];
$histParams = [];
$histTypes  = '';

if ($branchId) {
    $histWhere[]  = 'il.branch_id = ?';
    $histParams[] = $branchId;
    $histTypes   .= 's';
} elseif ($filterBranch !== 'all') {
    $histWhere[]  = 'il.branch_id = ?';
    $histParams[] = $filterBranch;
    $histTypes   .= 's';
}

$histWhereSQL = implode(' AND ', $histWhere);

$stmt = $conn->prepare(
    "SELECT il.log_id, il.change_qty, il.old_qty, il.new_qty,
            il.reason, il.reference_id, il.changed_at,
            p.product_name, b.branch_name,
            u.name AS changed_by_name
     FROM inventory_log il
     JOIN products  p ON il.product_id = p.product_id
     LEFT JOIN branches b ON il.branch_id  = b.branch_id
     LEFT JOIN users    u ON il.changed_by = u.user_id
     WHERE $histWhereSQL
     ORDER BY il.changed_at DESC
     LIMIT 100"
);
if ($histTypes) $stmt->bind_param($histTypes, ...$histParams);
$stmt->execute();
$movementHistory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Category → icon/colour map ────────────────────────────────
function categoryIcon(string $cat): array {
    $cat = strtolower($cat);
    $map = [
        'paper'    => ['fa-file-alt',   '#4A6FA5'],
        'writing'  => ['fa-pen',        '#FF9800'],
        'notebook' => ['fa-book-open',  '#795548'],
        'printing' => ['fa-print',      '#9C27B0'],
        'organizer'=> ['fa-folder',     '#4CAF50'],
        'binding'  => ['fa-book',       '#795548'],
        'ink'      => ['fa-fill-drip',  '#9C27B0'],
        'art'      => ['fa-paint-brush','#E91E63'],
    ];
    foreach ($map as $key => $val) {
        if (str_contains($cat, $key)) return $val;
    }
    return ['fa-box', '#607D8B'];
}

function reasonBadge(string $reason): string {
    $map = [
        'MANUAL_UPDATE'   => ['#6b7280','#f3f4f6','Manual Update'],
        'POS_SALE'        => ['#A83535','#fdf2f2','POS Sale'],
        'ORDER_COLLECTED' => ['#d97706','#fffbeb','Order Collected'],
    ];
    [$color, $bg, $label] = $map[$reason] ?? ['#6b7280','#f3f4f6', $reason];
    return "<span style='background:$bg;color:$color;border:1px solid {$color}44;
                padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;
                white-space:nowrap;'>$label</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus — Inventory</title>
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

        .top-header { background-color:var(--white);padding:16px 28px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;position:sticky;top:0;z-index:10; }
        .page-title { font-size:22px;font-weight:700;color:var(--text-primary); }
        .page-subtitle { font-size:13px;color:var(--text-secondary);margin-top:3px; }
        .header-right { display:flex;gap:10px;align-items:center;flex-wrap:wrap; }

        .search-wrap { position:relative; }
        .search-icon-pos { position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-secondary);font-size:14px; }
        .search-input { padding:9px 12px 9px 36px;border:1.5px solid var(--border);border-radius:8px;width:220px;font-size:13px;background:var(--accent);transition:all 0.2s; }
        .search-input:focus { outline:none;border-color:var(--primary);background:var(--white);box-shadow:0 0 0 3px rgba(168,53,53,0.08); }

        .filter-select { padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;background:var(--white);color:var(--text-primary);cursor:pointer; }
        .filter-select:focus { outline:none;border-color:var(--primary); }

        .tab { padding:8px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-weight:600;color:var(--text-secondary);background:var(--white);cursor:pointer;text-decoration:none;transition:all 0.2s; }
        .tab:hover { border-color:var(--primary);color:var(--primary); }
        .tab.active { background:var(--primary);color:white;border-color:var(--primary); }

        /* Success banner */
        .success-banner { margin:16px 28px 0;padding:12px 18px;background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;border-radius:8px;font-size:14px;display:flex;align-items:center;gap:10px; }
        .success-banner.warn { background:#fffbeb;color:#92400e;border-color:#fde68a; }

        /* Content */
        .inv-content { padding:24px 28px;flex-grow:1;display:flex;flex-direction:column;gap:24px; }

        /* Stats row */
        .stats-row { display:grid;grid-template-columns:repeat(3,1fr);gap:16px; }
        .stat-card { background:var(--white);border-radius:10px;padding:18px 20px;box-shadow:var(--card-shadow);border:1px solid var(--border);display:flex;align-items:center;gap:14px; }
        .stat-icon { width:42px;height:42px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0; }
        .stat-value { font-size:26px;font-weight:700;color:var(--primary); }
        .stat-label { font-size:12px;color:var(--text-secondary);font-weight:600;text-transform:uppercase;letter-spacing:0.4px; }

        /* Section card */
        .section-card { background:var(--white);border-radius:10px;box-shadow:var(--card-shadow);border:1px solid var(--border);overflow:hidden; }
        .section-card.alert-card { border-color:rgba(168,53,53,0.2); }
        .card-header { padding:16px 22px;border-bottom:1px solid var(--border);background:rgba(168,53,53,0.03);display:flex;justify-content:space-between;align-items:center; }
        .alert-card .card-header { background:rgba(168,53,53,0.06); }
        .card-title { font-size:16px;font-weight:700;color:var(--primary);display:flex;align-items:center;gap:8px; }
        .card-count { background:var(--primary);color:white;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700; }

        /* Table */
        .table-wrap { overflow-x:auto; }
        .inv-table { width:100%;border-collapse:collapse; }
        .inv-table thead { background:rgba(168,53,53,0.03);border-bottom:2px solid var(--border); }
        .inv-table th { padding:12px 18px;text-align:left;font-size:11px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.4px;white-space:nowrap; }
        .inv-table tbody tr { border-bottom:1px solid var(--border);transition:background 0.15s; }
        .inv-table tbody tr:last-child { border-bottom:none; }
        .inv-table tbody tr:hover { background:rgba(168,53,53,0.02); }
        .inv-table td { padding:14px 18px;font-size:13px;color:var(--text-primary);vertical-align:middle; }

        /* Product cell */
        .prod-cell { display:flex;align-items:center;gap:12px; }
        .prod-icon { width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:white;font-size:15px;flex-shrink:0; }
        .prod-name { font-weight:600;font-size:13px; }
        .prod-cat { font-size:11px;color:var(--text-secondary);margin-top:2px; }

        /* Stock bar */
        .stock-cell { display:flex;align-items:center;gap:10px; }
        .bar-wrap { width:80px;height:7px;background:var(--border);border-radius:4px;overflow:hidden;flex-shrink:0; }
        .bar-fill { height:100%;border-radius:4px; }
        .bar-ok       { background:#10b981; }
        .bar-warning  { background:#f59e0b; }
        .bar-critical { background:#ef4444; }
        .stock-num { font-weight:700;font-size:14px; }
        .stock-num.ok       { color:#10b981; }
        .stock-num.warning  { color:#f59e0b; }
        .stock-num.critical { color:#ef4444; }

        /* Inline update form */
        .update-form { display:flex;align-items:center;gap:8px; }
        .qty-input { width:72px;padding:7px 10px;border:1.5px solid var(--border);border-radius:7px;font-size:13px;text-align:center;background:var(--accent);transition:border-color 0.2s,background 0.2s; }
        .qty-input:focus { outline:none;border-color:var(--primary);background:var(--white); }
        .qty-input.below { border-color:#f59e0b;background:#fffbeb; }
        .save-btn { padding:7px 14px;background:var(--primary);color:white;border:none;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer;transition:background 0.2s;white-space:nowrap; }
        .save-btn:hover { background:#8b2a2a; }

        /* Alert badges */
        .level-critical { background:rgba(168,53,53,0.12);color:var(--primary);border:1px solid rgba(168,53,53,0.3);padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600; }
        .level-warning  { background:rgba(245,158,11,0.12);color:#d97706;border:1px solid rgba(245,158,11,0.3);padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600; }
        .shortage-text  { font-size:12px;color:#ef4444;font-weight:600; }

        /* Movement history — change qty chip */
        .change-chip { font-size:12px;font-weight:700;padding:3px 9px;border-radius:20px;font-family:monospace;white-space:nowrap; }
        .change-pos { background:#ecfdf5;color:#059669;border:1px solid #a7f3d0; }
        .change-neg { background:#fef2f2;color:#dc2626;border:1px solid #fca5a5; }
        .qty-flow { font-size:12px;color:var(--text-secondary);white-space:nowrap; }
        .ref-link { font-family:monospace;font-size:11px;color:var(--primary);font-weight:600; }

        /* Empty state */
        .empty-state { padding:40px 20px;text-align:center;color:var(--text-secondary); }
        .empty-state i { font-size:36px;opacity:0.2;margin-bottom:10px;display:block; }
        .empty-state p { font-size:14px; }

        /* ── AI Restock panel ── */
        .ai-restock-card{background:linear-gradient(135deg,rgba(37,99,235,0.04),rgba(16,185,129,0.04));border:1.5px solid rgba(37,99,235,0.15);border-radius:12px;padding:20px 22px;}
        .ai-restock-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;}
        .ai-restock-title{font-size:15px;font-weight:700;color:#1d4ed8;display:flex;align-items:center;gap:9px;}
        .ai-restock-sub{font-size:12px;color:var(--text-secondary);margin-top:3px;}
        .ai-restock-btn{padding:8px 18px;background:#1d4ed8;color:white;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:7px;transition:background 0.2s;white-space:nowrap;}
        .ai-restock-btn:hover:not(:disabled){background:#1e40af;}
        .ai-restock-btn:disabled{background:#d1d5db;cursor:not-allowed;}
        .ai-restock-body{display:none;margin-top:16px;}
        .ai-restock-body.show{display:block;}
        .ai-loading-r{text-align:center;padding:24px;color:var(--text-secondary);display:flex;flex-direction:column;align-items:center;gap:10px;}
        .ai-spinner-r{width:26px;height:26px;border:3px solid var(--border);border-top-color:#1d4ed8;border-radius:50%;animation:rspin .7s linear infinite;}
        @keyframes rspin{to{transform:rotate(360deg);}}
        .rec-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;}
        .rec-card{background:var(--white);border-radius:10px;padding:16px;border:1.5px solid var(--border);transition:box-shadow 0.2s;}
        .rec-card.critical{border-color:#fca5a5;background:rgba(239,68,68,0.02);}
        .rec-card.high{border-color:#fde68a;background:rgba(245,158,11,0.02);}
        .rec-card.medium{border-color:#bfdbfe;background:rgba(59,130,246,0.02);}
        .rec-priority{font-size:11px;font-weight:700;padding:2px 9px;border-radius:20px;display:inline-block;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px;}
        .priority-critical{background:#fef2f2;color:#c62828;border:1px solid #ef9a9a;}
        .priority-high{background:#fffbeb;color:#92400e;border:1px solid #fde68a;}
        .priority-medium{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;}
        .rec-product{font-size:14px;font-weight:700;color:var(--text-primary);margin-bottom:3px;}
        .rec-branch{font-size:12px;color:var(--text-secondary);margin-bottom:10px;}
        .rec-stats{display:flex;gap:10px;margin-bottom:10px;flex-wrap:wrap;}
        .rec-stat{font-size:12px;padding:4px 10px;background:var(--accent);border-radius:6px;border:1px solid var(--border);}
        .rec-stat strong{color:var(--text-primary);}
        .rec-order{display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:rgba(37,99,235,0.06);border-radius:8px;margin-bottom:10px;}
        .rec-order-label{font-size:12px;font-weight:600;color:#1d4ed8;}
        .rec-order-qty{font-size:20px;font-weight:700;color:#1d4ed8;}
        .rec-reason{font-size:12px;color:var(--text-secondary);line-height:1.5;font-style:italic;}
        .ai-all-clear{padding:16px 18px;background:rgba(16,185,129,0.06);border:1px solid #a7f3d0;border-radius:9px;font-size:13px;color:#065f46;display:flex;align-items:center;gap:10px;}
        .ai-err-r{padding:12px 16px;background:#fff0f0;border:1px solid #ef9a9a;border-radius:9px;font-size:13px;color:#c62828;}

        @media (max-width:1024px) {
            :root { --sidebar-width:70px; }
            .logo-text,.nav-text,.user-details,.nav-title,.logout-link span { display:none; }
            .logo-area,.nav-section,.user-section { padding:18px 12px; }
            .nav-link { justify-content:center;padding:14px;border-left:none;border-right:4px solid transparent; }
            .nav-link:hover,.nav-link.active { border-left:none;border-right-color:var(--primary); }
            .nav-icon { margin-right:0;font-size:20px; }
            .logout-link { justify-content:center;padding:10px; }
        }
        @media (max-width:768px) {
            .stats-row { grid-template-columns:1fr 1fr; }
            .search-input { width:160px; }
        }
    </style>
</head>
<body>

<?php include 'smart_sidebar.php'; ?>

<main class="main-content">

    <!-- Header -->
    <header class="top-header">
        <div>
            <div class="page-title">Inventory Management</div>
            <div class="page-subtitle">Monitor stock levels, update quantities, and track every movement</div>
        </div>

        <form method="GET" action="s_inv.php" class="header-right">
            <div class="search-wrap">
                <i class="fas fa-search search-icon-pos"></i>
                <input type="text" name="search" class="search-input"
                       placeholder="Search products…"
                       value="<?= htmlspecialchars($search) ?>">
            </div>

            <?php if (!$branchId): ?>
            <select name="branch" class="filter-select" onchange="this.form.submit()">
                <option value="all" <?= $filterBranch==='all' ? 'selected':'' ?>>All Branches</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= htmlspecialchars($b['branch_id']) ?>"
                            <?= $filterBranch===$b['branch_id'] ? 'selected':'' ?>>
                        <?= htmlspecialchars($b['branch_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <input type="hidden" name="filter" value="<?= htmlspecialchars($filterView) ?>">

            <a href="?filter=all&search=<?= urlencode($search) ?>&branch=<?= urlencode($filterBranch) ?>"
               class="tab <?= $filterView==='all' ? 'active':'' ?>">All Stock</a>
            <a href="?filter=low&search=<?= urlencode($search) ?>&branch=<?= urlencode($filterBranch) ?>"
               class="tab <?= $filterView==='low' ? 'active':'' ?>">
                Low Stock
                <?php if ($lowStockCount > 0): ?>
                    <span style="background:white;color:var(--primary);border-radius:10px;padding:1px 6px;font-size:11px;margin-left:4px;font-weight:700;"><?= $lowStockCount ?></span>
                <?php endif; ?>
            </a>
        </form>
    </header>

    <?php if ($successMsg): ?>
    <div class="success-banner<?= $isWarning ? ' warn' : '' ?>">
        <i class="fas <?= $isWarning ? 'fa-exclamation-triangle' : 'fa-check-circle' ?>"></i> <?= htmlspecialchars($successMsg) ?>
    </div>
    <?php endif; ?>

    <div class="inv-content">

        <!-- ── AI Restock Recommendations ── -->
        <?php if ($lowStockCount > 0): ?>
        <div class="ai-restock-card">
            <div class="ai-restock-head">
                <div>
                    <div class="ai-restock-title">
                        <i class="fas fa-robot"></i> AI Restock Recommendations
                        <span style="font-size:11px;font-weight:400;color:#6b7280;margin-left:4px;">Powered by Gemini</span>
                    </div>
                    <div class="ai-restock-sub">
                        <?= $lowStockCount ?> item<?= $lowStockCount > 1 ? 's' : '' ?> below minimum level — click to get AI-suggested reorder quantities
                    </div>
                </div>
                <button class="ai-restock-btn" id="restockBtn" onclick="getRestockRecs()">
                    <i class="fas fa-magic"></i> Get Recommendations
                </button>
            </div>
            <div class="ai-restock-body" id="restockBody">
                <div class="ai-loading-r" id="restockLoading" style="display:none;">
                    <div class="ai-spinner-r"></div>
                    <div>Analysing stock levels and sales velocity…</div>
                </div>
                <div id="restockResults"></div>
                <div class="ai-err-r" id="restockErr" style="display:none;"></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(168,53,53,0.1);color:var(--primary);">
                    <i class="fas fa-boxes"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $totalProducts ?></div>
                    <div class="stat-label">Active Products</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(245,158,11,0.1);color:#d97706;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $lowStockCount ?></div>
                    <div class="stat-label">Low Stock Alerts</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(59,130,246,0.1);color:#3b82f6;">
                    <i class="fas fa-store"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $totalBranches ?></div>
                    <div class="stat-label">Active Branches</div>
                </div>
            </div>
        </div>

        <!-- ── Stock Overview Table ── -->
        <div class="section-card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-boxes"></i> Stock Overview</div>
                <span style="font-size:13px;color:var(--text-secondary);">
                    <?= count($inventory) ?> record<?= count($inventory)!==1?'s':'' ?>
                </span>
            </div>
            <div class="table-wrap">
                <?php if (empty($inventory)): ?>
                    <div class="empty-state">
                        <i class="fas fa-boxes"></i>
                        <p>No inventory records found for the selected filters.</p>
                    </div>
                <?php else: ?>
                <table class="inv-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Branch</th>
                            <th>Stock Level</th>
                            <th>Reserved</th>
                            <th>Min. Level</th>
                            <th>Update Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory as $item):
                            [$icon, $color] = categoryIcon($item['category'] ?? '');
                            $qty = (int)$item['stock_quantity'];
                            $min = (int)$item['minimum_level'];
                            $pct = $min > 0 ? min(100, (int)round(($qty / ($min * 2)) * 100)) : 100;

                            if ($qty === 0)       { $barClass = 'bar-critical'; $numClass = 'critical'; }
                            elseif ($qty <= $min) { $barClass = 'bar-warning';  $numClass = 'warning';  }
                            else                  { $barClass = 'bar-ok';       $numClass = 'ok';       }
                        ?>
                        <tr>
                            <td>
                                <div class="prod-cell">
                                    <div class="prod-icon" style="background:<?= $color ?>;">
                                        <i class="fas <?= $icon ?>"></i>
                                    </div>
                                    <div>
                                        <div class="prod-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                        <div class="prod-cat"><?= htmlspecialchars($item['category'] ?? '—') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <i class="fas fa-store" style="color:var(--primary);margin-right:6px;font-size:12px;"></i>
                                <?= htmlspecialchars($item['branch_name']) ?>
                            </td>
                            <td>
                                <div class="stock-cell">
                                    <div class="bar-wrap">
                                        <div class="bar-fill <?= $barClass ?>" style="width:<?= $pct ?>%"></div>
                                    </div>
                                    <span class="stock-num <?= $numClass ?>"><?= $qty ?></span>
                                </div>
                            </td>
                            <td>
                                <?php $reserved = (int)($item['reserved_quantity'] ?? 0); ?>
                                <?php if ($reserved > 0): ?>
                                    <span style="background:#fffbeb;color:#d97706;border:1px solid #fde68a;
                                                 padding:2px 9px;border-radius:20px;font-size:12px;font-weight:600;">
                                        <i class="fas fa-lock" style="font-size:10px;"></i> <?= $reserved ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:#9ca3af;font-size:12px;">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $min ?></td>
                            <td>
                                <form method="POST" action="s_inv.php?<?= http_build_query(array_filter(['search'=>$search,'filter'=>$filterView,'branch'=>$filterBranch])) ?>" class="update-form">
                                    <input type="hidden" name="inventory_id" value="<?= htmlspecialchars($item['inventory_id']) ?>">
                                    <input type="number" name="new_qty"
                                           class="qty-input <?= $qty <= $min ? 'below' : '' ?>"
                                           value="<?= $qty ?>" min="0"
                                           oninput="highlightQty(this, <?= $min ?>)">
                                    <button type="submit" name="update_stock" class="save-btn">
                                        <i class="fas fa-save"></i> Save
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Low Stock Alerts ── -->
        <div class="section-card alert-card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-exclamation-triangle"></i> Low Stock Alerts
                </div>
                <span class="card-count">
                    <?= count($lowStockItems) ?> alert<?= count($lowStockItems)!==1?'s':'' ?>
                </span>
            </div>
            <div class="table-wrap">
                <?php if (empty($lowStockItems)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>All stock levels are healthy. No alerts at this time.</p>
                    </div>
                <?php else: ?>
                <table class="inv-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Branch</th>
                            <th>Current Stock</th>
                            <th>Min. Required</th>
                            <th>Shortage</th>
                            <th>Level</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowStockItems as $item):
                            [$icon, $color] = categoryIcon($item['category'] ?? '');
                            $qty        = (int)$item['stock_quantity'];
                            $min        = (int)$item['minimum_level'];
                            $shortage   = $min - $qty;
                            $isCritical = ($qty === 0 || $shortage >= (int)($min * 0.5));
                        ?>
                        <tr>
                            <td>
                                <div class="prod-cell">
                                    <div class="prod-icon" style="background:<?= $color ?>;">
                                        <i class="fas <?= $icon ?>"></i>
                                    </div>
                                    <div>
                                        <div class="prod-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                        <div class="prod-cat"><?= htmlspecialchars($item['category'] ?? '—') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <i class="fas fa-store" style="color:var(--primary);margin-right:6px;font-size:12px;"></i>
                                <?= htmlspecialchars($item['branch_name']) ?>
                            </td>
                            <td><span class="stock-num <?= $qty===0 ? 'critical':'warning' ?>"><?= $qty ?></span></td>
                            <td><?= $min ?></td>
                            <td><span class="shortage-text">−<?= $shortage ?></span></td>
                            <td>
                                <?php if ($isCritical): ?>
                                    <span class="level-critical">Critical</span>
                                <?php else: ?>
                                    <span class="level-warning">Warning</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Movement History ── -->
        <div class="section-card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-history"></i> Movement History
                </div>
                <span style="font-size:13px;color:var(--text-secondary);">
                    Last <?= count($movementHistory) ?> entries
                </span>
            </div>
            <div class="table-wrap">
                <?php if (empty($movementHistory)): ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <p>No inventory movements recorded yet. Changes will appear here automatically.</p>
                    </div>
                <?php else: ?>
                <table class="inv-table">
                    <thead>
                        <tr>
                            <th>Date &amp; Time</th>
                            <th>Product</th>
                            <th>Branch</th>
                            <th>Change</th>
                            <th>Before → After</th>
                            <th>Reason</th>
                            <th>Reference</th>
                            <th>Staff</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movementHistory as $log):
                            $isPositive = (int)$log['change_qty'] >= 0;
                        ?>
                        <tr>
                            <td style="font-size:12px;color:var(--text-secondary);white-space:nowrap;">
                                <?= date('d M Y', strtotime($log['changed_at'])) ?><br>
                                <span style="font-size:11px;"><?= date('H:i:s', strtotime($log['changed_at'])) ?></span>
                            </td>
                            <td>
                                <div class="prod-name"><?= htmlspecialchars($log['product_name']) ?></div>
                            </td>
                            <td style="font-size:12px;">
                                <?= htmlspecialchars($log['branch_name'] ?? '—') ?>
                            </td>
                            <td>
                                <span class="change-chip <?= $isPositive ? 'change-pos' : 'change-neg' ?>">
                                    <?= $isPositive ? '+' : '' ?><?= (int)$log['change_qty'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="qty-flow">
                                    <?= (int)$log['old_qty'] ?>
                                    <i class="fas fa-arrow-right" style="font-size:10px;color:var(--text-secondary);margin:0 4px;"></i>
                                    <?= (int)$log['new_qty'] ?>
                                </span>
                            </td>
                            <td><?= reasonBadge($log['reason']) ?></td>
                            <td>
                                <?php if (!empty($log['reference_id'])): ?>
                                    <span class="ref-link"><?= htmlspecialchars($log['reference_id']) ?></span>
                                <?php else: ?>
                                    <span style="color:var(--text-secondary);font-size:12px;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:12px;">
                                <?= htmlspecialchars($log['changed_by_name'] ?? '—') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /.inv-content -->

</main>

<script>
function highlightQty(input, min) {
    const val = parseInt(input.value) || 0;
    input.classList.toggle('below', val <= min);
    input.value = input.value.replace(/[^0-9]/g, '');
}

async function getRestockRecs() {
    const btn  = document.getElementById('restockBtn');
    const body = document.getElementById('restockBody');
    const load = document.getElementById('restockLoading');
    const res  = document.getElementById('restockResults');
    const err  = document.getElementById('restockErr');

    btn.disabled  = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analysing…';
    body.classList.add('show');
    load.style.display = 'flex';
    res.innerHTML      = '';
    err.style.display  = 'none';

    try {
        const fd = new FormData();
        fd.append('branch_id', '<?= addslashes($branchId ?? '') ?>');

        const r    = await fetch('ai_restock.php', { method: 'POST', body: fd });
        const data = await r.json();
        load.style.display = 'none';

        if (!data.success) {
            err.textContent   = data.error || 'Failed to get recommendations.';
            err.style.display = 'block';
            return;
        }

        if (!data.recommendations || data.recommendations.length === 0) {
            res.innerHTML = `<div class="ai-all-clear">
                <i class="fas fa-check-circle" style="font-size:18px;flex-shrink:0;"></i>
                <div><strong>All clear!</strong> ${data.message || 'Stock levels are healthy.'}</div>
            </div>`;
            return;
        }

        res.innerHTML = `<div class="rec-grid">${
            data.recommendations.map(r => `
                <div class="rec-card ${esc(r.priority)}">
                    <span class="rec-priority priority-${esc(r.priority)}">${priorityIcon(r.priority)} ${esc(r.priority)}</span>
                    <div class="rec-product">${esc(r.product_name)}</div>
                    <div class="rec-branch"><i class="fas fa-store" style="margin-right:4px;font-size:11px;"></i>${esc(r.branch_name)}</div>
                    <div class="rec-stats">
                        <div class="rec-stat">Stock: <strong>${r.current_stock}</strong></div>
                        <div class="rec-stat">Min: <strong>${r.minimum_level}</strong></div>
                        <div class="rec-stat">Sold/30d: <strong>${r.sold_30d}</strong></div>
                    </div>
                    <div class="rec-order">
                        <span class="rec-order-label"><i class="fas fa-cart-plus"></i> Recommended Order</span>
                        <span class="rec-order-qty">${r.reorder_qty} units</span>
                    </div>
                    <div class="rec-reason">"${esc(r.reason)}"</div>
                </div>`
            ).join('')
        }</div>`;

    } catch (e) {
        load.style.display = 'none';
        err.textContent   = 'Network error: ' + e.message;
        err.style.display = 'block';
    } finally {
        btn.disabled  = false;
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> Regenerate';
    }
}

function priorityIcon(p) {
    return p === 'critical' ? '🔴' : p === 'high' ? '🟡' : '🔵';
}
function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

</body>
</html>