<?php
// ============================================================
//  s_sidebar.php — Staff sidebar (include on every s_ page)
// ============================================================

$currentPage = basename($_SERVER['PHP_SELF']);

$navMapping = [
    's_dashboard.php'      => 'dashboard',
    's_ordermanagement.php'=> 'orders',
    's_inv.php'            => 'inventory',
];

$activePage  = $navMapping[$currentPage] ?? '';
$userName    = $_SESSION['user_name']   ?? 'Staff';
$userInitial = strtoupper(mb_substr($userName, 0, 1));

// Fetch staff branch name if branch_id is in session
$branchName = 'All Branches';
if (!empty($_SESSION['branch_id']) && isset($conn)) {
    $stmt = $conn->prepare("SELECT branch_name FROM branches WHERE branch_id = ? LIMIT 1");
    $stmt->bind_param('s', $_SESSION['branch_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $branchName = $row['branch_name'] ?? 'All Branches';
    $stmt->close();
}
?>

<nav class="sidebar">

    <!-- Logo -->
    <div class="logo-area">
        <div class="logo-icon"><i class="fas fa-pen-nib"></i></div>
        <div class="logo-text">StationaryPlus</div>
    </div>

    <!-- Main Menu -->
    <div class="nav-section">
        <div class="nav-title">Staff Menu</div>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="s_dashboard.php" class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
                    <div class="nav-icon"><i class="fas fa-tachometer-alt"></i></div>
                    <div class="nav-text">Dashboard</div>
                </a>
            </li>
            <li class="nav-item">
                <a href="s_ordermanagement.php" class="nav-link <?= $activePage === 'orders' ? 'active' : '' ?>">
                    <div class="nav-icon"><i class="fas fa-tasks"></i></div>
                    <div class="nav-text">Manage Orders</div>
                </a>
            </li>
            <li class="nav-item">
                <a href="s_inv.php" class="nav-link <?= $activePage === 'inventory' ? 'active' : '' ?>">
                    <div class="nav-icon"><i class="fas fa-boxes"></i></div>
                    <div class="nav-text">Inventory</div>
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
                <div class="user-role">Staff &mdash; <?= htmlspecialchars($branchName) ?></div>
            </div>
        </div>
        <a href="logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>

</nav>