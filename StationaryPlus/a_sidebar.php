<?php
// ============================================================
//  a_sidebar.php — Admin sidebar
//  Administration section  → brick red accent (#A83535)
//  Operations section      → blue accent (#2563eb)
// ============================================================

$currentPage = basename($_SERVER['PHP_SELF']);

$navMapping = [
    // Admin pages
    'a_dashboard.php'       => 'dashboard',
    'a_usermanagement.php'  => 'users',
    'a_productmanagement.php'=> 'products',
    'a_branch.php'          => 'branches',
    'a_report.php'          => 'sales',
    'a_banners.php'         => 'banners',
    'a_auditlog.php'        => 'audit',
    // Staff/Ops pages accessed by admin
    's_ordermanagement.php' => 'ops_orders',
    's_payments.php'        => 'ops_payments',
    's_upload_review.php'   => 'ops_printfiles',
    's_inv.php'             => 'ops_inventory',
    's_pos.php' => 'ops_pos'
];

$activePage  = $navMapping[$currentPage] ?? '';
$userName    = $_SESSION['user_name']   ?? 'Admin';
$userInitial = strtoupper(mb_substr($userName, 0, 1));
$isOpsPage   = str_starts_with($activePage, 'ops_');

// ── Live counts for ops badges ────────────────────────────────
$pendingPayments  = 0;
$pendingPrintFiles = 0;
$pendingRestock    = 0;
if (isset($conn)) {
    $r = $conn->query(
        "SELECT
            (SELECT COUNT(*) FROM payments WHERE verification_status = 'PENDING') AS pending_payments,
            (SELECT COUNT(*) FROM print_files WHERE file_status = 'RECEIVED') AS pending_print_files,
            (SELECT COUNT(*) FROM restock_requests WHERE status = 'PENDING') AS pending_restock"
    );
    if ($r && ($row = $r->fetch_assoc())) {
        $pendingPayments   = (int)$row['pending_payments'];
        $pendingPrintFiles = (int)$row['pending_print_files'];
        $pendingRestock    = (int)$row['pending_restock'];
    }
}
?>

<style>
/* ── Ops section overrides (blue theme) ── */
.ops-section { background: rgba(37,99,235,0.03); border-top: 2px solid rgba(37,99,235,0.15); }
.ops-title-wrap {
    display: flex; align-items: center; gap: 7px;
    padding: 14px 22px 10px;
}
.ops-title-icon {
    width: 22px; height: 22px; border-radius: 5px;
    background: rgba(37,99,235,0.1); color: #2563eb;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; flex-shrink: 0;
}
.ops-title-text {
    font-size: 11px; font-weight: 700; color: #2563eb;
    text-transform: uppercase; letter-spacing: 0.7px;
}
.ops-badge-wrap {
    margin-left: auto;
    background: #2563eb; color: var(--on-primary);
    font-size: 9px; font-weight: 700;
    padding: 1px 6px; border-radius: 10px;
}
.nav-link.ops:hover {
    background: rgba(37,99,235,0.06);
    color: #2563eb;
    border-left-color: rgba(37,99,235,0.3);
}
.nav-link.ops.active {
    background: rgba(37,99,235,0.1);
    color: #2563eb;
    border-left-color: #2563eb;
    font-weight: 600;
}
.ops-alert {
    margin-left: auto;
    background: var(--danger); color: var(--on-primary);
    font-size: 10px; font-weight: 700;
    padding: 1px 6px; border-radius: 10px;
    min-width: 18px; text-align: center;
}
.ops-divider {
    margin: 0 18px 8px;
    border: none; border-top: 1px dashed rgba(37,99,235,0.2);
}
</style>

<nav class="sidebar">

    <!-- Logo -->
    <a href="a_dashboard.php" class="logo-area" style="text-decoration:none;">
        <div class="logo-icon"><i class="fas fa-pen-nib"></i></div>
        <div class="logo-text">StationaryPlus</div>
    </a>

    <!-- ── Administration ── -->
    <div class="nav-section">
        <div class="nav-title">Administration</div>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="a_dashboard.php" class="nav-link <?= $activePage==='dashboard'?'active':'' ?>">
                    <div class="nav-icon"><i class="fas fa-tachometer-alt"></i></div>
                    <div class="nav-text">Dashboard</div>
                </a>
            </li>
            <li class="nav-item">
                <a href="a_usermanagement.php" class="nav-link <?= $activePage==='users'?'active':'' ?>">
                    <div class="nav-icon"><i class="fas fa-users-cog"></i></div>
                    <div class="nav-text">User Management</div>
                </a>
            </li>
            <li class="nav-item">
                <a href="a_productmanagement.php" class="nav-link <?= $activePage==='products'?'active':'' ?>">
                    <div class="nav-icon"><i class="fas fa-boxes"></i></div>
                    <div class="nav-text">
                        Product Management
                        <?php if ($pendingRestock > 0): ?>
                        <span class="ops-alert"><?= $pendingRestock ?></span>
                        <?php endif; ?>
                    </div>
                </a>
            </li>
            <li class="nav-item">
                <a href="a_branch.php" class="nav-link <?= $activePage==='branches'?'active':'' ?>">
                    <div class="nav-icon"><i class="fas fa-store"></i></div>
                    <div class="nav-text">Branch Management</div>
                </a>
            </li>
            <li class="nav-item">
                <a href="a_report.php" class="nav-link <?= $activePage==='sales'?'active':'' ?>">
                    <div class="nav-icon"><i class="fas fa-chart-bar"></i></div>
                    <div class="nav-text">Sales Report</div>
                </a>
            </li>
            <li class="nav-item">
                <a href="a_banners.php" class="nav-link <?= $activePage==='banners'?'active':'' ?>">
                    <div class="nav-icon"><i class="fas fa-images"></i></div>
                    <div class="nav-text">Banners</div>
                </a>
            </li>
            <li class="nav-item">
                <a href="a_auditlog.php" class="nav-link <?= $activePage==='audit'?'active':'' ?>">
                    <div class="nav-icon"><i class="fas fa-clipboard-list"></i></div>
                    <div class="nav-text">Audit Log</div>
                </a>
            </li>
        </ul>
    </div>

    <!-- ── Operations (blue accent) ── -->
    <div class="nav-section ops-section">
        <div class="ops-title-wrap">
            <div class="ops-title-icon"><i class="fas fa-bolt"></i></div>
            <span class="ops-title-text">Operations</span>
            <?php $totalOpsAlerts = $pendingPayments + $pendingPrintFiles;
            if ($totalOpsAlerts > 0): ?>
            <span class="ops-badge-wrap"><?= $totalOpsAlerts ?></span>
            <?php endif; ?>
        </div>
        <hr class="ops-divider">
        <ul class="nav-menu">
            <li class="nav-item">
    <a href="s_pos.php" class="nav-link ops <?= $activePage === 'ops_pos' ? 'active' : '' ?>">
        <div class="nav-icon"><i class="fas fa-cash-register"></i></div>
        <div class="nav-text">Point of Sale</div>
    </a>
</li>
            <li class="nav-item">
                <a href="s_ordermanagement.php" class="nav-link ops <?= $activePage==='ops_orders'?'active':'' ?>">
                    <div class="nav-icon"><i class="fas fa-tasks"></i></div>
                    <div class="nav-text">Manage Orders</div>
                </a>
            </li>
            <li class="nav-item">
                <a href="s_payments.php" class="nav-link ops <?= $activePage==='ops_payments'?'active':'' ?>">
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
                <a href="s_upload_review.php" class="nav-link ops <?= $activePage==='ops_printfiles'?'active':'' ?>">
                    <div class="nav-icon"><i class="fas fa-print"></i></div>
                    <div class="nav-text">
                        Print Files
                        <?php if ($pendingPrintFiles > 0): ?>
                        <span class="ops-alert"><?= $pendingPrintFiles ?></span>
                        <?php endif; ?>
                    </div>
                </a>
            </li>
            <li class="nav-item">
                <a href="s_inv.php" class="nav-link ops <?= $activePage==='ops_inventory'?'active':'' ?>">
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
                <div class="user-role" style="display:flex;align-items:center;gap:5px;">
                    Administrator
                    <?php if ($isOpsPage): ?>
                    <span style="font-size:9px;background:rgba(37,99,235,0.1);color:#2563eb;padding:1px 5px;border-radius:4px;font-weight:700;">OPS</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="theme-toggle" role="group" aria-label="Theme">
            <button type="button" class="theme-toggle-btn" data-theme-option="light" title="Light theme" aria-label="Light theme"><i class="fas fa-sun"></i></button>
            <button type="button" class="theme-toggle-btn" data-theme-option="dark" title="Dark theme" aria-label="Dark theme"><i class="fas fa-moon"></i></button>
            <button type="button" class="theme-toggle-btn" data-theme-option="high-contrast" title="High contrast" aria-label="High contrast theme"><i class="fas fa-adjust"></i></button>
        </div>
        <script>if (window.initThemeToggle) initThemeToggle();</script>
        <a href="logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>

</nav>