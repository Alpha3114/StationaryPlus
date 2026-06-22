<?php
// ============================================================
//  a_sidebar.php — Admin sidebar (include on every a_ page)
// ============================================================

// Ensure session is started and user is authenticated


$currentPage = basename($_SERVER['PHP_SELF']);

$navMapping = [
    'a_dashboard.php'      => 'dashboard',
    'a_usermanagement.php' => 'users',
    'a_productmanagement.php' => 'products',
    'a_branch.php'         => 'branches',
    'a_report.php'         => 'sales',
];

$activePage  = $navMapping[$currentPage] ?? '';
$userName    = $_SESSION['user_name']   ?? 'Admin';
$userInitial = strtoupper(mb_substr($userName, 0, 1));
?>

<!-- Sidebar Navigation -->
<nav class="sidebar">

    <!-- Logo -->
    <div class="logo-area">
        <div class="logo-icon"><i class="fas fa-pen-nib"></i></div>
        <div class="logo-text">StationaryPlus</div>
    </div>

    <!-- Main Menu -->
    <div class="nav-section">
        <div class="nav-title">Administration</div>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="a_dashboard.php" class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
                    <div class="nav-icon"><i class="fas fa-tachometer-alt"></i></div>
                    <div class="nav-text">Dashboard</div>
                </a>
            </li>
            <li class="nav-item">
                <a href="a_usermanagement.php" class="nav-link <?= $activePage === 'users' ? 'active' : '' ?>">
                    <div class="nav-icon"><i class="fas fa-users-cog"></i></div>
                    <div class="nav-text">User Management</div>
                </a>
            </li>
            <li class="nav-item">
                <a href="a_productmanagement.php" class="nav-link <?= $activePage === 'products' ? 'active' : '' ?>">
                    <div class="nav-icon"><i class="fas fa-boxes"></i></div>
                    <div class="nav-text">Product Management</div>
                </a>
            </li>
            <li class="nav-item">
                <a href="a_branch.php" class="nav-link <?= $activePage === 'branches' ? 'active' : '' ?>">
                    <div class="nav-icon"><i class="fas fa-store"></i></div>
                    <div class="nav-text">Branch Management</div>
                </a>
            </li>
            <li class="nav-item">
                <a href="a_report.php" class="nav-link <?= $activePage === 'sales' ? 'active' : '' ?>">
                    <div class="nav-icon"><i class="fas fa-chart-bar"></i></div>
                    <div class="nav-text">Sales Report</div>
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
                <div class="user-role">Administrator</div>
            </div>
        </div>
        <a href="logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>

</nav>
