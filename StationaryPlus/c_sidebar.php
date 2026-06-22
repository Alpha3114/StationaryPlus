<?php
// ============================================================
//  c_sidebar.php — Customer sidebar (include on every c_ page)
// ============================================================

// Get current page for active nav highlighting
$currentPage = basename($_SERVER['PHP_SELF']);

$navMapping = [
    'c_dashboard.php'    => 'dashboard',
    'c_viewproducts.php' => 'products',
    'c_preorder.php'     => 'preorder',
    'c_upload.php'       => 'upload',
    'c_orderstatus.php'  => 'orderstatus',
    'c_payment.php'      => 'payment',
];

$activePage = $navMapping[$currentPage] ?? '';

// Pull name from session — set in login.php
$userName   = $_SESSION['user_name'] ?? 'Customer';
// Avatar initial — first letter of name
$userInitial = strtoupper(mb_substr($userName, 0, 1));
?>

<nav class="sidebar">

    <!-- Logo -->
    <div class="logo-area">
        <div class="logo-icon">
            <i class="fas fa-pen-nib"></i>
        </div>
        <div class="logo-text">StationaryPlus</div>
    </div>

    <!-- Main Menu -->
    <div class="nav-section">
        <div class="nav-title">Main Menu</div>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="c_dashboard.php" class="nav-link <?= $activePage === 'dashboard'  ? 'active' : '' ?>">
                    <div class="nav-icon"><i class="fas fa-home"></i></div>
                    <div class="nav-text">Dashboard</div>
                </a>
            </li>
            <li class="nav-item">
                <a href="c_viewproducts.php" class="nav-link <?= $activePage === 'products'  ? 'active' : '' ?>">
                    <div class="nav-icon"><i class="fas fa-box-open"></i></div>
                    <div class="nav-text">View Products</div>
                </a>
            </li>
            <li class="nav-item">
                <a href="c_preorder.php" class="nav-link <?= $activePage === 'preorder'   ? 'active' : '' ?>">
                    <div class="nav-icon"><i class="fas fa-clipboard-check"></i></div>
                    <div class="nav-text">Pre-order</div>
                </a>
            </li>
            <li class="nav-item">
                <a href="c_upload.php" class="nav-link <?= $activePage === 'upload'     ? 'active' : '' ?>">
                    <div class="nav-icon"><i class="fas fa-upload"></i></div>
                    <div class="nav-text">Upload Files</div>
                </a>
            </li>
        </ul>
    </div>

    <!-- Orders & Payments -->
    <div class="nav-section">
        <div class="nav-title">Orders &amp; Payments</div>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="c_orderstatus.php" class="nav-link <?= $activePage === 'orderstatus' ? 'active' : '' ?>">
                    <div class="nav-icon"><i class="fas fa-search"></i></div>
                    <div class="nav-text">Order Status</div>
                </a>
            </li>
            <li class="nav-item">
                <a href="c_payment.php" class="nav-link <?= $activePage === 'payment'    ? 'active' : '' ?>">
                    <div class="nav-icon"><i class="fas fa-receipt"></i></div>
                    <div class="nav-text">Payment Record</div>
                </a>
            </li>
        </ul>
    </div>

    <!-- User info + logout -->
    <div class="user-section">
        <div class="user-info">
            <div class="user-avatar"><?= htmlspecialchars($userInitial) ?></div>
            <div class="user-details">
                <div class="user-name"><?= htmlspecialchars($userName) ?></div>
                <div class="user-role">Customer Account</div>
            </div>
        </div>
        <a href="logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>

</nav>