<?php
// ============================================================
//  c_sidebar.php — Customer sidebar (include on every c_ page)
// ============================================================

$currentPage = basename($_SERVER['PHP_SELF']);

$navMapping = [
    'c_dashboard.php'    => 'dashboard',
    'c_viewproducts.php' => 'products',
    'c_preorder.php'     => 'preorder',
    'c_upload.php'       => 'upload',
    'c_orderstatus.php'  => 'orderstatus',
    'c_payment.php'      => 'payment',
    'c_profile.php'      => 'profile',
];

$activePage  = $navMapping[$currentPage] ?? '';
$userName    = $_SESSION['user_name']   ?? 'Customer';
$userInitial = strtoupper(mb_substr($userName, 0, 1));

// ── Live counts for nav badges ─────────────────────────────────
// Combined into one query instead of two separate round trips.
// needsPayment mirrors c_payment.php's "unpaid orders" eligibility,
// as a COUNT — keeps the badge number consistent with what actually
// shows up there.
$readyForCollection = 0;
$needsPayment       = 0;
if (isset($conn) && !empty($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];

    $stmt = $conn->prepare(
        "SELECT
            (SELECT COUNT(*) FROM orders WHERE user_id = ? AND order_status = 'READY') AS ready_for_collection,
            (SELECT COUNT(*) FROM orders o
             WHERE o.user_id = ?
               AND o.order_status NOT IN ('CANCELLED','COLLECTED')
               AND o.order_id NOT IN (
                   SELECT p.order_id FROM payments p
                   WHERE p.verification_status IN ('VALID','PENDING')
               )
               AND o.order_id NOT IN (
                   SELECT DISTINCT pf.order_id FROM print_files pf
                   WHERE pf.file_status = 'RECEIVED'
                     AND pf.order_id IS NOT NULL
               )
            ) AS needs_payment"
    );
    $stmt->bind_param('ss', $uid, $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $readyForCollection = (int)$row['ready_for_collection'];
        $needsPayment       = (int)$row['needs_payment'];
    }
}
?>

<style>
.nav-alert {
    margin-left: auto;
    background: var(--danger); color: var(--on-primary);
    font-size: 10px; font-weight: 700;
    padding: 1px 6px; border-radius: 10px;
    min-width: 18px; text-align: center;
}
</style>

<nav class="sidebar">

    <!-- Logo -->
    <a href="c_dashboard.php" class="logo-area" style="text-decoration:none;">
        <div class="logo-icon"><i class="fas fa-pen-nib"></i></div>
        <div class="logo-text">StationaryPlus</div>
    </a>

    <!-- Main Menu -->
    <div class="nav-section">
        <div class="nav-title">Main Menu</div>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="c_dashboard.php" class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
                    <div class="nav-icon"><i class="fas fa-home"></i></div>
                    <div class="nav-text">Dashboard</div>
                </a>
            </li>
            <li class="nav-item">
                <a href="c_viewproducts.php" class="nav-link <?= $activePage === 'products' ? 'active' : '' ?>">
                    <div class="nav-icon"><i class="fas fa-box-open"></i></div>
                    <div class="nav-text">View Products</div>
                </a>
            </li>
            <li class="nav-item">
                <a href="c_preorder.php" class="nav-link <?= $activePage === 'preorder' ? 'active' : '' ?>">
                    <div class="nav-icon"><i class="fas fa-clipboard-check"></i></div>
                    <div class="nav-text">Pre-order</div>
                </a>
            </li>
            <li class="nav-item">
                <a href="c_upload.php" class="nav-link <?= $activePage === 'upload' ? 'active' : '' ?>">
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
                    <?php if ($readyForCollection > 0): ?>
                    <span class="nav-alert" title="Ready for collection"><?= $readyForCollection ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="c_payment.php" class="nav-link <?= $activePage === 'payment' ? 'active' : '' ?>">
                    <div class="nav-icon"><i class="fas fa-receipt"></i></div>
                    <div class="nav-text">Payment Record</div>
                    <?php if ($needsPayment > 0): ?>
                    <span class="nav-alert" title="Needs payment"><?= $needsPayment ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>
    </div>

    <!-- Account -->
    <div class="nav-section">
        <div class="nav-title">Account</div>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="c_profile.php" class="nav-link <?= $activePage === 'profile' ? 'active' : '' ?>">
                    <div class="nav-icon"><i class="fas fa-user-circle"></i></div>
                    <div class="nav-text">My Profile</div>
                </a>
            </li>
        </ul>
    </div>

    <!-- User info + logout -->
    <div class="user-section">
        <a href="c_profile.php" style="text-decoration:none;" title="Edit profile">
            <div class="user-info" style="cursor:pointer;">
                <div class="user-avatar"><?= htmlspecialchars($userInitial) ?></div>
                <div class="user-details">
                    <div class="user-name"><?= htmlspecialchars($userName) ?></div>
                    <div class="user-role">Customer Account</div>
                </div>
            </div>
        </a>
        <a href="logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>

</nav>