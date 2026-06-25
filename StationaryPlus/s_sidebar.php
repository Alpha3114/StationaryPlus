<?php
// ============================================================
//  s_sidebar.php — Staff sidebar (include on every s_ page)
// ============================================================

$currentPage = basename($_SERVER['PHP_SELF']);

$navMapping = [
    's_dashboard.php'       => 'dashboard',
    's_ordermanagement.php' => 'orders',
    's_inv.php'             => 'inventory',
    's_payments.php'        => 'payments',
    's_upload_review.php'   => 'printfiles',
];

$activePage  = $navMapping[$currentPage] ?? '';
$userName    = $_SESSION['user_name']    ?? 'Staff';
$userInitial = strtoupper(mb_substr($userName, 0, 1));

// Branch name for display
$branchName = 'All Branches';
if (!empty($_SESSION['branch_id']) && isset($conn)) {
    $stmt = $conn->prepare("SELECT branch_name FROM branches WHERE branch_id = ? LIMIT 1");
    $stmt->bind_param('s', $_SESSION['branch_id']);
    $stmt->execute();
    $row        = $stmt->get_result()->fetch_assoc();
    $branchName = $row['branch_name'] ?? 'All Branches';
    $stmt->close();
}

// Pending counts for badges
$pendingPayments  = 0;
$pendingPrintFiles = 0;
if (isset($conn)) {
    $r = $conn->query("SELECT COUNT(*) AS cnt FROM payments WHERE verification_status = 'PENDING'");
    $pendingPayments = $r->fetch_assoc()['cnt'] ?? 0;

    $r = $conn->query("SELECT COUNT(*) AS cnt FROM print_files WHERE file_status = 'RECEIVED'");
    $pendingPrintFiles = $r->fetch_assoc()['cnt'] ?? 0;
}
?>

<nav class="sidebar">

    <div class="logo-area">
        <div class="logo-icon"><i class="fas fa-pen-nib"></i></div>
        <div class="logo-text">StationaryPlus</div>
    </div>

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

    <div class="nav-section">
        <div class="nav-title">Payments &amp; Printing</div>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="s_payments.php" class="nav-link <?= $activePage === 'payments' ? 'active' : '' ?>">
                    <div class="nav-icon"><i class="fas fa-receipt"></i></div>
                    <div class="nav-text">
                        Payments
                        <?php if ($pendingPayments > 0): ?>
                        <span style="margin-left:auto;background:#ef4444;color:white;font-size:10px;font-weight:700;
                                     padding:1px 6px;border-radius:10px;min-width:18px;text-align:center;">
                            <?= $pendingPayments ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </a>
            </li>
            <li class="nav-item">
                <a href="s_upload_review.php" class="nav-link <?= $activePage === 'printfiles' ? 'active' : '' ?>">
                    <div class="nav-icon"><i class="fas fa-print"></i></div>
                    <div class="nav-text">
                        Print Files
                        <?php if ($pendingPrintFiles > 0): ?>
                        <span style="margin-left:auto;background:#ef4444;color:white;font-size:10px;font-weight:700;
                                     padding:1px 6px;border-radius:10px;min-width:18px;text-align:center;">
                            <?= $pendingPrintFiles ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </a>
            </li>
        </ul>
    </div>

    <div class="user-section">
        <div class="user-info">
            <div class="user-avatar"><?= htmlspecialchars($userInitial) ?></div>
            <div class="user-details">
                <div class="user-name"><?= htmlspecialchars($userName) ?></div>
                <div class="user-role">Staff — <?= htmlspecialchars($branchName) ?></div>
            </div>
        </div>
        <a href="logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>

</nav>