<?php
// ============================================================
//  c_viewproducts.php — Product Catalog
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role('CUSTOMER');
require_once 'db.php';

$userId = $_SESSION['user_id'];  // FIX: was missing — needed for "remember branch"

// ── Branch context ────────────────────────────────────────────
$branchList = $conn->query(
    "SELECT branch_id, branch_name FROM branches WHERE status = 'ACTIVE' ORDER BY branch_name"
)->fetch_all(MYSQLI_ASSOC);

// Handle branch switch — session only, never touches preferred_branch_id
// Preferred branch is managed exclusively from the dashboard.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['switch_branch'])) {
    $newBranch = trim($_POST['branch_id'] ?? '');

    $chk = $conn->prepare("SELECT branch_id FROM branches WHERE branch_id = ? AND status = 'ACTIVE' LIMIT 1");
    $chk->bind_param('s', $newBranch);
    $chk->execute(); $chk->store_result();
    if ($chk->num_rows > 0) {
        $_SESSION['branch_id'] = $newBranch;
    }
    $chk->close();
    header('Location: c_viewproducts.php'); exit;
}

$activeBranchId = $_SESSION['branch_id'] ?? null;
$currentBranch  = null;
if ($activeBranchId) {
    $stmt = $conn->prepare("SELECT branch_name FROM branches WHERE branch_id = ? LIMIT 1");
    $stmt->bind_param('s', $activeBranchId);
    $stmt->execute();
    $currentBranch = $stmt->get_result()->fetch_assoc()['branch_name'] ?? null;
    $stmt->close();
}
// Cart item count for header badge
if (session_status() === PHP_SESSION_NONE) session_start();
$cartCount = array_sum($_SESSION['cart'] ?? []);

// ── Query parameters ─────────────────────────────────────────
$search   = trim($_GET['search']   ?? '');
$category = trim($_GET['category'] ?? '');
$sort     = $_GET['sort']          ?? 'name_asc';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 9;
$offset   = ($page - 1) * $perPage;

// ── Fetch distinct categories for filter dropdown ────────────
$categories = $conn->query(
    "SELECT DISTINCT category FROM products
     WHERE product_status = 'ACTIVE' AND category IS NOT NULL
     ORDER BY category ASC"
)->fetch_all(MYSQLI_ASSOC);

// ── Build dynamic query ──────────────────────────────────────
$where  = ["p.product_status = 'ACTIVE'"];
$params = [];
$types  = '';

if ($search !== '') {
    $where[]  = "(p.product_name LIKE ? OR p.category LIKE ?)";
    $like     = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

if ($category !== '') {
    $where[]  = "p.category = ?";
    $params[] = $category;
    $types   .= 's';
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$orderSQL = match($sort) {
    'price_asc'  => 'ORDER BY (total_stock > 0) DESC, p.price ASC',
    'price_desc' => 'ORDER BY (total_stock > 0) DESC, p.price DESC',
    'name_desc'  => 'ORDER BY (total_stock > 0) DESC, p.product_name DESC',
    default      => 'ORDER BY (total_stock > 0) DESC, p.product_name ASC',
};

// ── Count total for pagination ───────────────────────────────
if ($activeBranchId) {
    $countSQL = "SELECT COUNT(DISTINCT p.product_id) AS cnt 
                 FROM products p
                 LEFT JOIN inventory i ON p.product_id = i.product_id AND i.branch_id = ?
                 $whereSQL";
    $countStmt = $conn->prepare($countSQL);
    $countTypes = 's' . $types;
    $countParams = array_merge([$activeBranchId], $params);
    if ($countTypes) $countStmt->bind_param($countTypes, ...$countParams);
} else {
    $countSQL = "SELECT COUNT(*) AS cnt FROM products p $whereSQL";
    $countStmt = $conn->prepare($countSQL);
    if ($types) $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRows  = $countStmt->get_result()->fetch_assoc()['cnt'] ?? 0;
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$countStmt->close();

// ── Fetch products with stock info ────────────────────────────
// If branch selected: only products with stock > 0 at that branch
// If no branch: all active products across all branches
if ($activeBranchId) {
    $dataSQL = "
        SELECT p.product_id, p.product_name, p.category, p.price,
               COALESCE(i.stock_quantity, 0) AS total_stock
        FROM products p
        LEFT JOIN inventory i ON p.product_id = i.product_id AND i.branch_id = ?
        $whereSQL
        GROUP BY p.product_id
        $orderSQL
        LIMIT ? OFFSET ?
    ";
    $dataTypes  = 's' . $types . 'ii';
    $dataParams = array_merge([$activeBranchId], $params, [$perPage, $offset]);
} else {
    $dataSQL = "
        SELECT p.product_id, p.product_name, p.category, p.price,
               COALESCE(SUM(i.stock_quantity), 0) AS total_stock
        FROM products p
        LEFT JOIN inventory i ON p.product_id = i.product_id
        $whereSQL
        GROUP BY p.product_id
        $orderSQL
        LIMIT ? OFFSET ?
    ";
    $dataTypes  = $types . 'ii';
    $dataParams = array_merge($params, [$perPage, $offset]);
}

$stmt = $conn->prepare($dataSQL);
$stmt->bind_param($dataTypes, ...$dataParams);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Category → icon map ──────────────────────────────────────
function categoryIcon(string $cat): string {
    $cat = strtolower($cat);
    $map = [
        'writing'       => 'fa-pen',
        'paper'         => 'fa-file',
        'notebook'      => 'fa-book-open',
        'notebooks'     => 'fa-book-open',
        'printing'      => 'fa-print',
        'organizer'     => 'fa-folder',
        'organizers'    => 'fa-folder',
        'stationery'    => 'fa-pencil-ruler',
        'stationery set'=> 'fa-gift',
        'art'           => 'fa-paint-brush',
    ];
    foreach ($map as $key => $icon) {
        if (str_contains($cat, $key)) return $icon;
    }
    return 'fa-box';
}

// ── Build pagination URL helper ───────────────────────────────
function pageUrl(int $p): string {
    $q = $_GET;
    $q['page'] = $p;
    return '?' . http_build_query($q);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus — Product Catalog</title>
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
            left: 0; top: 0;
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
            width: 40px; height: 40px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            margin-right: 12px;
            color: white; font-size: 20px;
        }
        .logo-text { font-size: 22px; font-weight: 700; color: var(--primary); }
        .nav-section { padding: 25px 0; border-bottom: 1px solid var(--border); }
        .nav-title {
            font-size: 12px; font-weight: 600; color: var(--text-secondary);
            text-transform: uppercase; letter-spacing: 0.8px;
            padding: 0 25px 12px 25px;
        }
        .nav-menu { list-style: none; }
        .nav-item { margin-bottom: 2px; }
        .nav-link {
            display: flex; align-items: center;
            padding: 13px 25px;
            color: var(--text-primary); text-decoration: none;
            transition: all 0.2s ease;
            border-left: 4px solid transparent;
        }
        .nav-link:hover { background-color: rgba(168,53,53,0.05); color: var(--primary); border-left-color: rgba(168,53,53,0.3); }
        .nav-link.active { background-color: rgba(168,53,53,0.08); color: var(--primary); border-left-color: var(--primary); font-weight: 600; }
        .nav-icon { width: 22px; text-align: center; margin-right: 14px; font-size: 16px; }
        .nav-text { font-size: 15px; }
        .user-section { margin-top: auto; padding: 20px 25px; border-top: 1px solid var(--border); }
        .user-info { display: flex; align-items: center; margin-bottom: 14px; }
        .user-avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background-color: rgba(168,53,53,0.1);
            display: flex; align-items: center; justify-content: center;
            color: var(--primary); font-weight: 700; font-size: 16px;
            margin-right: 12px; flex-shrink: 0;
        }
        .user-name { font-weight: 600; font-size: 15px; color: var(--text-primary); }
        .user-role { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
        .logout-link {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 14px;
            background-color: rgba(168,53,53,0.06);
            color: var(--primary); border-radius: 8px;
            text-decoration: none; font-size: 14px; font-weight: 600;
            transition: background-color 0.2s ease;
        }
        .logout-link:hover { background-color: rgba(168,53,53,0.14); }

        /* ── Main ── */
        .main-content {
            flex-grow: 1;
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex; flex-direction: column;
        }
        .top-header {
            background-color: var(--white);
            padding: 20px 30px;
            border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; z-index: 10;
        }
        .page-title { font-size: 24px; font-weight: 700; color: var(--text-primary); }
        .page-subtitle { font-size: 14px; color: var(--text-secondary); margin-top: 4px; }

        .search-container { position: relative; }
        .search-input {
            padding: 11px 15px 11px 42px;
            border: 1.5px solid var(--border);
            border-radius: 8px; width: 280px; font-size: 14px;
            background-color: var(--accent);
            transition: all 0.2s ease;
        }
        .search-input:focus {
            outline: none; border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(168,53,53,0.1);
            background-color: var(--white);
        }
        .search-icon {
            position: absolute; left: 14px; top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary); font-size: 15px;
        }

        /* ── Content ── */
        .product-content { padding: 30px; flex-grow: 1; }

        /* Filter bar */
        .filter-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            gap: 12px;
            flex-wrap: wrap;
        }
        .filter-left { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }

        .filter-select {
            padding: 10px 14px;
            border: 1.5px solid var(--border);
            border-radius: 8px; font-size: 14px;
            background-color: var(--white);
            color: var(--text-primary);
            cursor: pointer;
            transition: border-color 0.2s;
        }
        .filter-select:focus { outline: none; border-color: var(--primary); }

        .results-info { font-size: 14px; color: var(--text-secondary); white-space: nowrap; }

        /* Active filters */
        .active-filters { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
        .filter-tag {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px;
            background-color: rgba(168,53,53,0.08);
            color: var(--primary);
            border-radius: 20px; font-size: 13px; font-weight: 600;
            text-decoration: none;
        }
        .filter-tag:hover { background-color: rgba(168,53,53,0.15); }

        /* Product grid */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
            gap: 22px;
            margin-bottom: 36px;
        }

        .product-card {
            background-color: var(--white);
            border-radius: 12px; overflow: hidden;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            display: flex; flex-direction: column;
        }
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 24px rgba(0,0,0,0.09);
        }

        .product-image {
            height: 160px;
            background-color: var(--accent);
            display: flex; align-items: center; justify-content: center;
            position: relative;
        }
        .image-placeholder { font-size: 56px; color: var(--primary); opacity: 0.35; }

        .category-badge {
            position: absolute; top: 12px; left: 12px;
            background-color: rgba(168,53,53,0.88);
            color: white; padding: 4px 11px;
            border-radius: 20px; font-size: 12px; font-weight: 600;
        }

        /* Stock badge */
        .stock-badge {
            position: absolute; top: 12px; right: 12px;
            padding: 4px 10px; border-radius: 20px;
            font-size: 12px; font-weight: 600;
        }
        .stock-in    { background: rgba(16,185,129,0.12); color: #059669; border: 1px solid #a7f3d0; }
        .stock-low   { background: rgba(245,158,11,0.12); color: #d97706; border: 1px solid #fde68a; }
        .stock-out   { background: rgba(239,68,68,0.12);  color: #dc2626; border: 1px solid #fecaca; }

        .product-info { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
        .product-name { font-size: 16px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; line-height: 1.4; }
        .product-category { font-size: 12px; color: var(--text-secondary); margin-bottom: 10px; }
        .product-price { font-size: 20px; font-weight: 700; color: var(--primary); margin-top: auto; margin-bottom: 16px; }

        .product-actions { display: flex; gap: 8px; }
        .preorder-btn {
            flex-grow: 1; padding: 11px;
            background-color: var(--primary); color: white;
            border: none; border-radius: 8px;
            font-weight: 600; font-size: 14px; cursor: pointer;
            transition: background-color 0.2s ease;
            display: flex; align-items: center; justify-content: center; gap: 7px;
            text-decoration: none;
        }
        .preorder-btn:hover { background-color: #8b2a2a; }
        .preorder-btn.disabled {
            background-color: #d1d5db; color: #9ca3af; cursor: not-allowed;
            pointer-events: none;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        .empty-state i { font-size: 48px; opacity: 0.3; margin-bottom: 16px; display: block; }
        .empty-state p { font-size: 16px; }

        /* Pagination */
        .pagination { display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 10px; }
        .pagination a, .pagination span {
            width: 38px; height: 38px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; font-weight: 600;
            border: 1px solid var(--border);
            text-decoration: none;
            color: var(--text-primary);
            background-color: var(--white);
            transition: all 0.2s ease;
        }
        .pagination a:hover { background-color: var(--accent); border-color: var(--primary); color: var(--primary); }
        .pagination .current { background-color: var(--primary); color: white; border-color: var(--primary); }
        .pagination .dots { border: none; background: none; color: var(--text-secondary); }

        /* Footer */
        .product-footer {
            text-align: center; padding: 22px;
            color: var(--text-secondary); font-size: 13px;
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
            .search-input { width: 200px; }
        }

        @media (max-width: 768px) {
            .product-grid { grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); }
            .filter-bar { flex-direction: column; align-items: flex-start; }
            .search-input { width: 100%; }
        }
    </style>
</head>
<body>

<?php include 'c_sidebar.php'; ?>

<main class="main-content">

    <header class="top-header">
        <div>
            <h1 class="page-title">Product Catalog</h1>
            <p class="page-subtitle">Browse stationery products and printing services</p>
        </div>
        <form method="GET" action="c_viewproducts.php" class="search-container">
            <?php if ($category): ?><input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>"><?php endif; ?>
            <?php if ($sort): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>"><?php endif; ?>
            <i class="fas fa-search search-icon"></i>
            <input
                type="text"
                name="search"
                class="search-input"
                placeholder="Search products..."
                value="<?= htmlspecialchars($search) ?>"
                autocomplete="off"
            >
        </form>
        <!-- Branch context bar -->
    <form method="POST" action="c_viewproducts.php" style="display:flex;align-items:center;gap:10px;">
        <input type="hidden" name="switch_branch" value="1">
        <label style="font-size:13px;color:var(--text-secondary);font-weight:600;white-space:nowrap;">
            <i class="fas fa-store" style="color:var(--primary);margin-right:4px;"></i> Branch:
        </label>
        <select name="branch_id" onchange="this.form.submit()"
                style="padding:8px 28px 8px 12px;border:1.5px solid var(--border);border-radius:20px;
                       font-size:13px;font-weight:600;color:var(--primary);
                       background:rgba(168,53,53,0.06);cursor:pointer;outline:none;
                       appearance:none;
                       background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23A83535' d='M6 8L1 3h10z'/%3E%3C/svg%3E\");
                       background-repeat:no-repeat;background-position:right 10px center;">
            <!-- Placeholder shown when no branch is selected -->
            <option value="" <?= !$activeBranchId ? 'selected' : '' ?>>
                <?= $activeBranchId ? '' : '— Select your branch —' ?>
            </option>
            <?php foreach ($branchList as $b): ?>
                <option value="<?= htmlspecialchars($b['branch_id']) ?>"
                    <?= $activeBranchId === $b['branch_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($b['branch_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p style="font-size:11px;color:var(--text-secondary);margin-top:5px;white-space:nowrap;">
            Browsing only —
            <a href="c_dashboard.php" style="color:var(--primary);font-weight:600;">set preferred branch</a>
        </p>
    </form>

    <!-- Cart link -->
        <a href="c_preorder.php" style="position:relative;display:flex;align-items:center;padding:9px 16px;background:rgba(168,53,53,0.08);border-radius:8px;color:var(--primary);text-decoration:none;font-weight:600;font-size:14px;gap:8px;white-space:nowrap;">
            <i class="fas fa-shopping-basket"></i> Cart
            <?php if ($cartCount > 0): ?><span style="background:var(--primary);color:white;border-radius:10px;padding:1px 7px;font-size:11px;font-weight:700;"><?= $cartCount ?></span><?php endif; ?>
        </a>
    </header>

    <?php if (isset($_GET['added'])): ?>
    <div style="margin:16px 30px 0;padding:13px 18px;background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;border-radius:8px;font-size:14px;display:flex;align-items:center;gap:10px;">
        <i class="fas fa-check-circle"></i> Item added to your pre-order cart. <a href="c_preorder.php" style="color:#2e7d32;font-weight:600;margin-left:auto;">View Cart &rarr;</a>
    </div>
    <?php endif; ?>

    <div class="product-content">

        <!-- Filter bar -->
        <form method="GET" action="c_viewproducts.php">
            <?php if ($search): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
            <div class="filter-bar">
                <div class="filter-left">
                    <select name="category" class="filter-select" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['category']) ?>"
                                <?= $category === $cat['category'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['category']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="sort" class="filter-select" onchange="this.form.submit()">
                        <option value="name_asc"  <?= $sort === 'name_asc'   ? 'selected' : '' ?>>Name (A–Z)</option>
                        <option value="name_desc" <?= $sort === 'name_desc'  ? 'selected' : '' ?>>Name (Z–A)</option>
                        <option value="price_asc" <?= $sort === 'price_asc'  ? 'selected' : '' ?>>Price (Low–High)</option>
                        <option value="price_desc"<?= $sort === 'price_desc' ? 'selected' : '' ?>>Price (High–Low)</option>
                    </select>
                </div>
                <div class="results-info">
                    Showing <?= count($products) ?> of <?= $totalRows ?> product<?= $totalRows !== 1 ? 's' : '' ?>
                </div>
            </div>
        </form>

        <!-- Active filter tags -->
        <?php if ($search || $category): ?>
        <div class="active-filters">
            <?php if ($search): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['search' => '', 'page' => 1])) ?>" class="filter-tag">
                    Search: "<?= htmlspecialchars($search) ?>" &times;
                </a>
            <?php endif; ?>
            <?php if ($category): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['category' => '', 'page' => 1])) ?>" class="filter-tag">
                    Category: <?= htmlspecialchars($category) ?> &times;
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Product grid -->
        <?php if (empty($products)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <p>No products found<?= $search ? " for \"" . htmlspecialchars($search) . "\"" : '' ?>.</p>
            </div>
        <?php else: ?>
        <div style="display:flex;align-items:center;justify-content:space-between;
            padding:10px 16px;background:rgba(168,53,53,0.04);
            border:1px solid rgba(168,53,53,0.12);border-radius:9px;margin-bottom:18px;
            font-size:13px;">
    <span style="color:var(--text-secondary);">
        <i class="fas fa-store" style="color:var(--primary);margin-right:6px;"></i>
        <?php if ($currentBranch): ?>
            Showing products available at <strong style="color:var(--primary)"><?= htmlspecialchars($currentBranch) ?></strong>
        <?php else: ?>
            Showing products from <strong style="color:var(--primary)">all branches</strong>
        <?php endif; ?>
    </span>
    <?php if ($currentBranch): ?>
    <span style="color:var(--text-secondary);font-size:12px;">
        Stock levels reflect this branch only
    </span>
    <?php endif; ?>
</div>
        <div class="product-grid">
            <?php foreach ($products as $p):
                $stock = (int)$p['total_stock'];
                if ($stock <= 0) {
                    $stockClass = 'stock-out'; $stockLabel = 'Out of Stock';
                } elseif ($stock <= 5) {
                    $stockClass = 'stock-low'; $stockLabel = 'Low Stock';
                } else {
                    $stockClass = 'stock-in'; $stockLabel = 'In Stock';
                }
                $icon = categoryIcon($p['category'] ?? '');
            ?>
            <div class="product-card">
                <div class="product-image">
                    <div class="image-placeholder"><i class="fas <?= $icon ?>"></i></div>
                    <?php if ($p['category']): ?>
                        <span class="category-badge"><?= htmlspecialchars($p['category']) ?></span>
                    <?php endif; ?>
                    <span class="stock-badge <?= $stockClass ?>"><?= $stockLabel ?></span>
                </div>
                <div class="product-info">
                    <h3 class="product-name"><?= htmlspecialchars($p['product_name']) ?></h3>
                    <?php if ($p['category']): ?>
                        <div class="product-category"><i class="fas fa-tag"></i> <?= htmlspecialchars($p['category']) ?></div>
                    <?php endif; ?>
                    <div class="product-price">RM <?= number_format($p['price'], 2) ?></div>
                    <div class="product-actions">
                        <?php
                        // Adds directly to session cart, then redirects back to products
                        $addUrl = 'c_preorder.php?action=add&product_id=' . urlencode($p['product_id']) . '&redirect=products';
                        ?>
                        <a href="<?= $addUrl ?>"
                           class="preorder-btn <?= $stock <= 0 ? 'disabled' : '' ?>"
                           <?= $stock <= 0 ? 'aria-disabled="true"' : '' ?>>
                            <i class="fas fa-cart-plus"></i>
                            <?= $stock <= 0 ? 'Unavailable' : 'Pre-order' ?>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="<?= pageUrl($page - 1) ?>"><i class="fas fa-chevron-left"></i></a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++):
                if ($i === 1 || $i === $totalPages || abs($i - $page) <= 1): ?>
                    <?php if ($i === $page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= pageUrl($i) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php elseif (abs($i - $page) === 2): ?>
                    <span class="dots">…</span>
                <?php endif;
            endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="<?= pageUrl($page + 1) ?>"><i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

    </div>

    <footer class="product-footer">
        <div>&copy; <?= date('Y') ?> StationaryPlus — Stationery &amp; Printing Management System</div>
        <div class="footer-links">
            <a href="#">Help Center</a> |
            <a href="#">Contact Support</a> |
            <a href="#">Privacy Policy</a>
        </div>
    </footer>

</main>

</body>
</html>