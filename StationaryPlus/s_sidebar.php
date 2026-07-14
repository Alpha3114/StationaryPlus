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
    's_pos.php'             => 'pos',
];

$activePage  = $navMapping[$currentPage] ?? '';
$userName    = $_SESSION['user_name']    ?? 'Staff';
$userInitial = strtoupper(mb_substr($userName, 0, 1));

// Branch name + pending counts for badges — scoped to branch when
// staff has one assigned; company-wide totals otherwise. Combined into
// a single query (per case) instead of separate round trips.
$branchName        = 'All Branches';
$sidebarBranch     = $_SESSION['branch_id'] ?? null;
$pendingPayments   = 0;
$pendingPrintFiles = 0;

if (isset($conn)) {
    if ($sidebarBranch) {
        $stmt = $conn->prepare(
            "SELECT b.branch_name,
                    (SELECT COUNT(*) FROM payments p
                     JOIN orders o ON p.order_id = o.order_id
                     WHERE p.verification_status = 'PENDING' AND o.branch_id = b.branch_id) AS pending_payments,
                    (SELECT COUNT(*) FROM print_files pf
                     LEFT JOIN orders o ON pf.order_id = o.order_id
                     WHERE pf.file_status = 'RECEIVED' AND o.branch_id = b.branch_id) AS pending_print_files
             FROM branches b WHERE b.branch_id = ? LIMIT 1"
        );
        $stmt->bind_param('s', $sidebarBranch);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $branchName        = $row['branch_name'] ?? 'All Branches';
            $pendingPayments   = (int)$row['pending_payments'];
            $pendingPrintFiles = (int)$row['pending_print_files'];
        }
    } else {
        $r = $conn->query(
            "SELECT
                (SELECT COUNT(*) FROM payments WHERE verification_status = 'PENDING') AS pending_payments,
                (SELECT COUNT(*) FROM print_files WHERE file_status = 'RECEIVED') AS pending_print_files"
        );
        if ($r && ($row = $r->fetch_assoc())) {
            $pendingPayments   = (int)$row['pending_payments'];
            $pendingPrintFiles = (int)$row['pending_print_files'];
        }
    }
}
?>

<style>
.ops-alert {
    margin-left: auto;
    background: #ef4444; color: white;
    font-size: 10px; font-weight: 700;
    padding: 1px 6px; border-radius: 10px;
    min-width: 18px; text-align: center;
}
</style>

<nav class="sidebar">

    <a href="s_dashboard.php" class="logo-area" style="text-decoration:none;">
        <div class="logo-icon"><i class="fas fa-pen-nib"></i></div>
        <div class="logo-text">StationaryPlus</div>
    </a>

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
                <a href="s_pos.php" class="nav-link <?= $activePage === 'pos' ? 'active' : '' ?>">
                    <div class="nav-icon"><i class="fas fa-cash-register"></i></div>
                    <div class="nav-text">Point of Sale</div>
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
        <ul class="nav-menu ops">
            <li class="nav-item">
                <a href="s_payments.php" class="nav-link ops <?= $activePage === 'payments' ? 'active' : '' ?>">
                    <div class="nav-icon"><i class="fas fa-receipt"></i></div>
                    <div class="nav-text">
                        Payments
                        <?php if ($pendingPayments > 0): ?>
                        <span class="ops-alert"><?= $pendingPayments ?></span>
                        <?php endif; ?>
                    </div>
                </a>
            </li>
            <li class="nav-item">
                <a href="s_upload_review.php" class="nav-link ops <?= $activePage === 'printfiles' ? 'active' : '' ?>">
                    <div class="nav-icon"><i class="fas fa-print"></i></div>
                    <div class="nav-text">
                        Print Files
                        <?php if ($pendingPrintFiles > 0): ?>
                        <span class="ops-alert"><?= $pendingPrintFiles ?></span>
                        <?php endif; ?>
                    </div>
                </a>
            </li>
        </ul>
    </div>

    <div class="user-section">
        <div style="font-size:11px;font-weight:600;color:var(--text-secondary);
                    text-transform:uppercase;letter-spacing:0.6px;margin-bottom:8px;">
            <i class="fas fa-store" style="margin-right:5px;"></i>
            <?= htmlspecialchars($branchName) ?>
        </div>
        <div class="user-info">
            <div class="user-avatar"><?= htmlspecialchars($userInitial) ?></div>
            <div>
                <div class="user-name"><?= htmlspecialchars($userName) ?></div>
                <div class="user-role"><?= htmlspecialchars($_SESSION['user_role'] ?? 'Staff') ?></div>
            </div>
        </div>
        <a href="logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>

</nav>