<?php
// ============================================================
//  c_viewproducts.php — Product Catalog
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role('CUSTOMER');
require_once 'db.php';
require_once 'pricing.php';

$userId = $_SESSION['user_id'];  // FIX: was missing — needed for "remember branch"

// ── Branch context — session-only "Browsing Branch" (see branch_browse.php) ──
require_once 'branch_browse.php';

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
$categories = session_cache_get('active_categories', 60, fn() => $conn->query(
    "SELECT DISTINCT category FROM products
     WHERE product_status = 'ACTIVE' AND category IS NOT NULL
     ORDER BY category ASC"
)->fetch_all(MYSQLI_ASSOC));

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
// total_stock now reflects AVAILABLE stock (physical - reserved),
// so customers only see/order what isn't already promised to
// another pending pre-order.
if ($activeBranchId) {
    $dataSQL = "
        SELECT p.product_id, p.product_name, p.category, p.price, p.discount_percent, p.image_path,
               GREATEST(0, COALESCE(i.stock_quantity, 0) - COALESCE(i.reserved_quantity, 0)) AS total_stock
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
    // Wrapped in a derived table so total_stock (an aggregate expression
    // here) is a plain column by the time $orderSQL references it —
    // MySQL rejects re-wrapping a group-function alias directly in
    // ORDER BY (e.g. "(total_stock > 0)"), but that's fine once it's
    // just a column of an already-grouped subquery.
    $dataSQL = "
        SELECT * FROM (
            SELECT p.product_id, p.product_name, p.category, p.price, p.discount_percent, p.image_path,
                   GREATEST(0, COALESCE(SUM(i.stock_quantity), 0) - COALESCE(SUM(i.reserved_quantity), 0)) AS total_stock
            FROM products p
            LEFT JOIN inventory i ON p.product_id = i.product_id
            $whereSQL
            GROUP BY p.product_id
        ) t
        " . str_replace('p.', '', $orderSQL) . "
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
        'adhesive'   => 'fa-box-open',
        'packaging'  => 'fa-box-open',
        'art'        => 'fa-paint-brush',
        'book'       => 'fa-book-open',
        'filing'     => 'fa-folder',
        'organiz'    => 'fa-folder',
        'office'     => 'fa-briefcase',
        'paper'      => 'fa-file-alt',
        'technology' => 'fa-laptop',
        'writing'    => 'fa-pen',
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
    <link rel="stylesheet" href="assets/css/tokens.css?v=<?= @filemtime(__DIR__.'/assets/css/tokens.css') ?>">
    <script src="assets/js/theme.js?v=<?= @filemtime(__DIR__.'/assets/js/theme.js') ?>"></script>
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?= @filemtime(__DIR__.'/assets/css/sidebar.css') ?>">
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
            box-shadow: 0 0 0 3px var(--primary-tint-medium);
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
            background-color: var(--primary-tint-light);
            color: var(--primary);
            border-radius: 20px; font-size: 13px; font-weight: 600;
            text-decoration: none;
        }
        .filter-tag:hover { background-color: var(--primary-tint-active); }

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
            color: var(--on-primary); padding: 4px 11px;
            border-radius: 20px; font-size: 12px; font-weight: 600;
        }

        /* Stock badge */
        .stock-badge {
            position: absolute; top: 12px; right: 12px;
            padding: 4px 10px; border-radius: 20px;
            font-size: 12px; font-weight: 600;
        }
        .stock-in    { background: rgba(16,185,129,0.12); color: var(--success); border: 1px solid var(--success-border); }
        .stock-low   { background: rgba(245,158,11,0.12); color: var(--warning); border: 1px solid var(--warning); }
        .stock-out   { background: rgba(239,68,68,0.12);  color: var(--danger); border: 1px solid var(--danger); }

        .discount-corner-badge {
            position: absolute; bottom: 10px; left: 12px;
            background-color: var(--secondary);
            color: var(--on-primary); padding: 4px 10px;
            border-radius: 20px; font-size: 12px; font-weight: 700;
        }
        .price-strike {
            display: block;
            text-decoration: line-through;
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 500;
            opacity: 0.75;
        }

        .product-info { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
        .product-name { font-size: 16px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; line-height: 1.4; }
        .product-category { font-size: 12px; color: var(--text-secondary); margin-bottom: 10px; }
        .product-price { font-size: 20px; font-weight: 700; color: var(--primary); margin-top: auto; margin-bottom: 16px; }

        .product-actions { display: flex; gap: 8px; }
        .preorder-btn {
            flex-grow: 1; padding: 11px;
            background-color: var(--primary); color: var(--on-primary);
            border: none; border-radius: 8px;
            font-weight: 600; font-size: 14px; cursor: pointer;
            transition: background-color 0.2s ease;
            display: flex; align-items: center; justify-content: center; gap: 7px;
            text-decoration: none;
        }
        .preorder-btn:hover { background-color: var(--primary-dark); }
        .preorder-btn.disabled {
            background-color: #d1d5db; color: #9ca3af; cursor: not-allowed;
            pointer-events: none;
        }
        .qty-stepper { display: flex; align-items: center; flex-shrink: 0; }
        .qty-stepper button {
            width: 30px; height: 42px; border: 1.5px solid var(--border);
            background: var(--white); color: var(--text-primary); font-size: 15px;
            font-weight: 600; cursor: pointer; transition: all 0.15s ease;
        }
        .qty-stepper button:hover { background: var(--accent); border-color: var(--primary); color: var(--primary); }
        .qty-stepper button.dec { border-radius: 8px 0 0 8px; border-right: none; }
        .qty-stepper button.inc { border-radius: 0 8px 8px 0; border-left: none; }
        .qty-stepper input {
            width: 34px; height: 42px; text-align: center; border: 1.5px solid var(--border);
            border-left: none; border-right: none; font-size: 13px; font-weight: 600;
            background: var(--white); color: var(--text-primary);
        }
        .qty-stepper input:focus { outline: none; }

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
        .pagination .current { background-color: var(--primary); color: var(--on-primary); border-color: var(--primary); }
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
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <!-- Browsing Branch context bar (session-only view filter; see branch_browse.php) -->
        <div style="display:flex;flex-direction:column;gap:3px;">
            <?php render_browsing_branch_bar(); ?>
            <?php render_branch_unavailable_notice(); ?>
            <p style="font-size:11px;color:var(--text-secondary);white-space:nowrap;">
                Temporary for this session —
                <a href="c_dashboard.php" style="color:var(--primary);font-weight:600;">set your preferred branch</a>
            </p>
        </div>

    <!-- Cart link -->
        <a href="c_preorder.php" style="position:relative;display:flex;align-items:center;padding:9px 16px;background:var(--primary-tint-light);border-radius:8px;color:var(--primary);text-decoration:none;font-weight:600;font-size:14px;gap:8px;white-space:nowrap;">
            <i class="fas fa-shopping-basket"></i> Cart
            <?php if ($cartCount > 0): ?><span style="background:var(--primary);color:var(--on-primary);border-radius:10px;padding:1px 7px;font-size:11px;font-weight:700;"><?= $cartCount ?></span><?php endif; ?>
        </a>
        <button type="button" class="theme-toggle-header-btn" data-theme-cycle title="Theme" aria-label="Theme"><i class="fas fa-sun"></i></button>
        </div>
    </header>
    <script>if (window.initThemeToggle) initThemeToggle();</script>

    <?php if (isset($_GET['added'])): ?>
    <div style="margin:16px 30px 0;padding:13px 18px;background:var(--success-bg);color:var(--success);border:1px solid var(--success-border);border-radius:8px;font-size:14px;display:flex;align-items:center;gap:10px;">
        <i class="fas fa-check-circle"></i> Item added to your pre-order cart. <a href="c_preorder.php" style="color:var(--success);font-weight:600;margin-left:auto;">View Cart &rarr;</a>
    </div>
    <?php endif; ?>

    <div class="product-content">

        <!-- Search -->
        <form method="GET" action="c_viewproducts.php" class="search-container" style="margin-bottom:16px;">
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
            padding:10px 16px;background:var(--primary-tint-subtle);
            border:1px solid var(--primary-tint-medium);border-radius:9px;margin-bottom:18px;
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
                    $stockClass = 'stock-low'; $stockLabel = "Low Stock ($stock)";
                } else {
                    $stockClass = 'stock-in'; $stockLabel = "In Stock ($stock)";
                }
                $icon = categoryIcon($p['category'] ?? '');
            ?>
            <div class="product-card">
                <div class="product-image">
                    <?php if (!empty($p['image_path'])): ?>
                        <img src="<?= htmlspecialchars($p['image_path']) ?>" alt="<?= htmlspecialchars($p['product_name']) ?>" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                        <div class="image-placeholder"><i class="fas <?= $icon ?>"></i></div>
                    <?php endif; ?>
                    <?php if ($p['category']): ?>
                        <span class="category-badge"><?= htmlspecialchars($p['category']) ?></span>
                    <?php endif; ?>
                    <span class="stock-badge <?= $stockClass ?>"><?= $stockLabel ?></span>
                    <?php $pDiscount = (float)($p['discount_percent'] ?? 0); if ($pDiscount > 0): ?>
                        <span class="discount-corner-badge">-<?= rtrim(rtrim(number_format($pDiscount, 2), '0'), '.') ?>%</span>
                    <?php endif; ?>
                </div>
                <div class="product-info">
                    <h3 class="product-name"><?= htmlspecialchars($p['product_name']) ?></h3>
                    <?php if ($p['category']): ?>
                        <div class="product-category"><i class="fas fa-tag"></i> <?= htmlspecialchars($p['category']) ?></div>
                    <?php endif; ?>
                    <?php if ($pDiscount > 0): ?>
                    <div class="product-price">
                        <span class="price-strike">RM <?= number_format($p['price'], 2) ?></span>
                        RM <?= number_format(discounted_price((float)$p['price'], $pDiscount), 2) ?>
                    </div>
                    <?php else: ?>
                    <div class="product-price">RM <?= number_format($p['price'], 2) ?></div>
                    <?php endif; ?>
                    <div class="product-actions">
                        <?php
                        // Adds directly to session cart, then redirects back to products
                        $pid = htmlspecialchars($p['product_id'], ENT_QUOTES);
                        ?>
                        <?php if ($stock > 0): ?>
                        <div class="qty-stepper">
                            <button type="button" class="dec" onclick="stepQty('<?= $pid ?>', -1)">−</button>
                            <input type="number" id="qty_<?= $pid ?>" value="1" min="1" max="<?= $stock ?>" readonly>
                            <button type="button" class="inc" onclick="stepQty('<?= $pid ?>', 1, <?= $stock ?>)">+</button>
                        </div>
                        <a href="#" onclick="addToCart('<?= $pid ?>'); return false;" class="preorder-btn">
                            <i class="fas fa-cart-plus"></i> Pre-order
                        </a>
                        <?php else: ?>
                        <a href="#" class="preorder-btn disabled" aria-disabled="true">
                            <i class="fas fa-cart-plus"></i> Unavailable
                        </a>
                        <?php endif; ?>
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
        &copy; <?= date('Y') ?> StationaryPlus &mdash; Stationery &amp; Printing Management System
    </footer>

</main>

<script>
function stepQty(pid, delta, max) {
    const input = document.getElementById('qty_' + pid);
    const cap   = max || parseInt(input.max, 10) || 99;
    let val     = (parseInt(input.value, 10) || 1) + delta;
    val = Math.max(1, Math.min(cap, val));
    input.value = val;
}
function addToCart(pid) {
    const input = document.getElementById('qty_' + pid);
    const qty   = input ? (parseInt(input.value, 10) || 1) : 1;
    window.location.href = 'c_preorder.php?action=add&product_id=' + encodeURIComponent(pid)
        + '&qty=' + qty + '&redirect=products';
}
</script>

</body>
</html>