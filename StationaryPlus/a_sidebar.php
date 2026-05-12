<?php
// Determine current page for active highlighting
$currentPage = basename($_SERVER['PHP_SELF']);
$navMapping = [
    'a_dashboard.php' => 'dashboard',
    'a_usermanagement.php' => 'users',
    'a_productmanagement.php' => 'products',
    'a_branch.php' => 'branches',
    'a_report.php' => 'sales'
];
$activePage = $navMapping[$currentPage] ?? '';
?>

<!-- Sidebar Navigation -->
<nav class="sidebar">
    <div class="logo-area">
        <div class="logo-icon">
            <i class="fas fa-pen-nib"></i>
        </div>
        <div>
            <div class="logo-text">StationaryPlus</div>
            <div class="admin-subtitle">Administration</div>
        </div>
    </div>
    
    <div class="nav-section">
        <div class="nav-title">Administration</div>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="a_dashboard.php" class="nav-link <?php echo ($activePage === 'dashboard') ? 'active' : ''; ?>">
                    <div class="nav-icon">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <div class="nav-text">Dashboard</div>
                </a>
            </li>
            <li class="nav-item">
                <a href="a_usermanagement.php" class="nav-link <?php echo ($activePage === 'users') ? 'active' : ''; ?>">
                    <div class="nav-icon">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <div class="nav-text">User Management</div>
                </a>
            </li>
            <li class="nav-item">
                <a href="a_productmanagement.php" class="nav-link <?php echo ($activePage === 'products') ? 'active' : ''; ?>">
                    <div class="nav-icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div class="nav-text">Product Management</div>
                </a>
            </li>
            <li class="nav-item">
                <a href="a_branch.php" class="nav-link <?php echo ($activePage === 'branches') ? 'active' : ''; ?>">
                    <div class="nav-icon">
                        <i class="fas fa-store"></i>
                    </div>
                    <div class="nav-text">Branch Management</div>
                </a>
            </li>
            <li class="nav-item">
                    <a href="a_report.php" class="nav-link <?php echo ($activePage === 'sales') ? 'active' : ''; ?>">
                        <div class="nav-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="nav-text">Sales Report</div>
                    </a>
                </li>
        </ul>
    </div>
    
    <div class="user-section">
        <div class="user-info">
            <div class="user-avatar">AD</div>
            <div class="user-details">
                <div class="user-name">Admin User</div>
            </div>
        </div>
        <button class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </button>
    </div>
</nav>
