<?php
// ============================================================
//  c_dashboard.php — Customer Dashboard
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role('CUSTOMER');
require_once 'db.php';
require_once 'banner_slot.php';

$userId         = $_SESSION['user_id'];
$userName       = $_SESSION['user_name'] ?? 'Customer';
$activeBranchId = $_SESSION['branch_id'] ?? null;

// ── Branch list ───────────────────────────────────────────────
$branchList = $conn->query(
    "SELECT branch_id, branch_name FROM branches WHERE status = 'ACTIVE' ORDER BY branch_name"
)->fetch_all(MYSQLI_ASSOC);

// ── Handle branch switch ──────────────────────────────────────
// Dashboard always saves the selected branch as the customer's
// permanent preferred branch AND updates the session.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['switch_branch'])) {
    $newBranch = trim($_POST['branch_id'] ?? '');

    if ($newBranch !== '') {
        $chk = $conn->prepare(
            "SELECT branch_id FROM branches WHERE branch_id = ? AND status = 'ACTIVE' LIMIT 1"
        );
        $chk->bind_param('s', $newBranch);
        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows > 0) {
            // Update session
            $_SESSION['branch_id'] = $newBranch;
            // Save permanently to DB
            $stmt = $conn->prepare(
                "UPDATE users SET preferred_branch_id = ? WHERE user_id = ?"
            );
            $stmt->bind_param('ss', $newBranch, $userId);
            $stmt->execute();
            $stmt->close();
        }
        $chk->close();
    }

    header('Location: c_dashboard.php');
    exit;
}

// ── Show branch popup if none selected ───────────────────────
$currentBranch          = null;
$branchBecameUnavailable = false;

if (!empty($_SESSION['branch_id'])) {
    // Only resolve against currently-ACTIVE branches — a branch saved
    // earlier may have since gone INACTIVE/RENOVATION.
    $stmt = $conn->prepare(
        "SELECT branch_name FROM branches WHERE branch_id = ? AND status = 'ACTIVE' LIMIT 1"
    );
    $stmt->bind_param('s', $_SESSION['branch_id']);
    $stmt->execute();
    $currentBranch = $stmt->get_result()->fetch_assoc()['branch_name'] ?? null;
    $stmt->close();

    if ($currentBranch === null) {
        // Stale selection — the branch is no longer active. Clear it so
        // the customer isn't silently filtered against an unavailable branch.
        unset($_SESSION['branch_id']);
        $branchBecameUnavailable = true;
    }
}

$showBranchPopup = empty($_SESSION['branch_id']);

// ── 1. Active reservations ────────────────────────────────────
// Customers only ever create PREORDERs (walk-in ORDERs come
// from staff via POS). Count all active pre-orders.
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS cnt FROM orders
     WHERE user_id = ?
       AND order_type = 'PREORDER'
       AND order_status NOT IN ('CANCELLED','COLLECTED')"
);
$stmt->bind_param('s', $userId);
$stmt->execute();
$activeOrders = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmt->close();

// ── 2. Pending pre-orders ─────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS cnt FROM orders
     WHERE user_id = ?
       AND order_type = 'PREORDER'
       AND order_status IN ('NEW','PROCESSING')"
);
$stmt->bind_param('s', $userId);
$stmt->execute();
$pendingPreorders = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmt->close();

// ── 3. Uploaded print files ───────────────────────────────────
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS cnt FROM print_files WHERE user_id = ?"
);
$stmt->bind_param('s', $userId);
$stmt->execute();
$uploadedFiles = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmt->close();

// ── 4. Pending payments ───────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT COALESCE(SUM(p.amount), 0) AS total
     FROM payments p
     JOIN orders o ON p.order_id = o.order_id
     WHERE p.verification_status = 'PENDING'
       AND o.user_id = ?"
);
$stmt->bind_param('s', $userId);
$stmt->execute();
$pendingPayment = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// ── Loyalty points balance ────────────────────────────────────
require_once 'loyalty.php';
$loyaltyPoints = get_loyalty_balance($conn, $userId);

// ── 5. Recent pre-orders (last 5) ────────────────────────────
$stmt = $conn->prepare(
    "SELECT order_id AS preorder_id, order_date, order_status, notes, estimated_total
     FROM orders
     WHERE user_id = ?
       AND order_type = 'PREORDER'
     ORDER BY order_date DESC
     LIMIT 5"
);
$stmt->bind_param('s', $userId);
$stmt->execute();
$recentPreorders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Status badge ──────────────────────────────────────────────
function orderStatusBadge(string $status): string {
    // Border uses a themed soft-alpha token (--*-border-soft) when the status
    // maps to a semantic status color; statuses outside that set (blue/purple/
    // grey) fall back to the literal hex + hand alpha suffix since they aren't
    // part of the token system.
    $map = [
        'NEW'        => ['#3b82f6','#eff6ff','New'],
        'PROCESSING' => ['#f59e0b','var(--warning-bg)','Processing'],
        'READY'      => ['#10b981','var(--success-bg)','Ready'],
        'COLLECTED'  => ['#6b7280','#f3f4f6','Collected'],
        'CANCELLED'  => ['#ef4444','var(--danger-bg)','Cancelled'],
        'SUBMITTED'  => ['#8b5cf6','#f5f3ff','Submitted'],
    ];
    $varMap = [
        'PROCESSING' => 'var(--warning)',
        'READY'      => 'var(--success)',
        'CANCELLED'  => 'var(--danger)',
    ];
    $borderVarMap = [
        'PROCESSING' => 'var(--warning-border-soft)',
        'READY'      => 'var(--success-border-soft)',
        'CANCELLED'  => 'var(--danger-border-soft)',
    ];
    [$c,$bg,$l] = $map[$status] ?? ['#6b7280','#f3f4f6',$status];
    $textColor = $varMap[$status] ?? $c;
    $borderColor = $borderVarMap[$status] ?? "{$c}44";
    return "<span style='background:$bg;color:$textColor;border:1px solid $borderColor;
                padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;
                white-space:nowrap;'>$l</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus — Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/tokens.css?v=<?= @filemtime(__DIR__.'/assets/css/tokens.css') ?>">
    <script src="assets/js/theme.js?v=<?= @filemtime(__DIR__.'/assets/js/theme.js') ?>"></script>
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?= @filemtime(__DIR__.'/assets/css/sidebar.css') ?>">
    <style>
        :root {
            --primary:#A83535; --secondary:#F4A261; --accent:#F1EDE8;
            --background:#FAFAFA; --text-primary:#2E2E2E; --text-secondary:#707070;
            --border:#E0E0E0; --white:#FFFFFF;
            --sidebar-width:260px; --card-shadow:0 4px 12px rgba(0,0,0,0.05);
        }
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',system-ui,sans-serif;}
        body{background:var(--background);color:var(--text-primary);min-height:100vh;display:flex;}

        /* Main */
        .main-content{flex-grow:1;margin-left:var(--sidebar-width);min-height:100vh;display:flex;flex-direction:column;}
        .top-header{background:var(--white);padding:20px 30px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:10;}
        .page-title{font-size:24px;font-weight:700;}
        .page-subtitle{font-size:14px;color:var(--text-secondary);margin-top:4px;}
        .dashboard-content{padding:30px;flex-grow:1;}

        /* Welcome */
        .welcome-card{background:linear-gradient(135deg,var(--primary-tint-light),rgba(244,162,97,0.06));border-radius:12px;padding:28px 30px;margin-bottom:28px;border-left:5px solid var(--primary);}
        .welcome-title{font-size:24px;font-weight:700;margin-bottom:8px;}
        .welcome-subtitle{font-size:14px;color:var(--text-secondary);line-height:1.6;}

        /* Stats */
        .dashboard-stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:18px;margin-bottom:28px;}
        .stat-card{background:var(--white);border-radius:10px;padding:22px;box-shadow:var(--card-shadow);border:1px solid var(--border);transition:transform 0.2s;}
        .stat-card:hover{transform:translateY(-3px);}
        .stat-title{font-size:13px;color:var(--text-secondary);margin-bottom:10px;display:flex;align-items:center;gap:8px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;}
        .stat-value{font-size:30px;font-weight:700;color:var(--primary);margin-bottom:4px;}
        .stat-detail{font-size:13px;color:var(--text-secondary);}

        /* Section */
        .content-section{margin-bottom:32px;}
        .section-title{font-size:18px;font-weight:600;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;}
        .section-title i{color:var(--primary);}

        /* Quick actions */
        .quick-actions{display:grid;grid-template-columns:repeat(auto-fill,minmax(175px,1fr));gap:16px;}
        .action-card{background:var(--white);border-radius:10px;padding:22px;box-shadow:var(--card-shadow);border:1px solid var(--border);text-align:center;text-decoration:none;transition:all 0.2s;display:block;}
        .action-card:hover{transform:translateY(-4px);box-shadow:0 8px 20px rgba(0,0,0,0.08);border-color:var(--primary);}
        .action-icon{width:54px;height:54px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:22px;}
        .action-icon.red   {background:var(--primary-tint-medium);color:var(--primary);}
        .action-icon.orange{background:rgba(244,162,97,0.1);color:var(--secondary);}
        .action-icon.green {background:var(--success-bg);color:var(--success);}
        .action-icon.blue  {background:rgba(59,130,246,0.1);color:#3b82f6;}
        .action-icon.purple{background:rgba(139,92,246,0.1);color:#8b5cf6;}
        .action-title{font-size:15px;font-weight:600;color:var(--text-primary);margin-bottom:6px;}
        .action-desc{font-size:13px;color:var(--text-secondary);}

        /* Tables */
        .table-card{background:var(--white);border-radius:10px;overflow:hidden;box-shadow:var(--card-shadow);border:1px solid var(--border);}
        .data-table{width:100%;border-collapse:collapse;}
        .data-table thead{background:var(--primary-tint-subtle);border-bottom:2px solid var(--border);}
        .data-table th{padding:14px 20px;text-align:left;font-size:12px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.4px;}
        .data-table tbody tr{border-bottom:1px solid var(--border);transition:background 0.15s;}
        .data-table tbody tr:last-child{border-bottom:none;}
        .data-table tbody tr:hover{background:var(--primary-tint-subtle);}
        .data-table td{padding:14px 20px;font-size:14px;vertical-align:middle;}
        .order-id{font-weight:600;color:var(--primary);font-family:monospace;font-size:13px;}
        .empty-row td{text-align:center;padding:28px;color:var(--text-secondary);font-style:italic;}

        /* AI Advisor */
        .advisor-wrap{background:var(--white);border-radius:12px;border:1px solid var(--border);box-shadow:var(--card-shadow);overflow:hidden;}
        .advisor-input-area{padding:24px;}
        .advisor-hint{font-size:13px;color:var(--text-secondary);margin-bottom:12px;line-height:1.6;}
        .advisor-row{display:flex;gap:10px;align-items:flex-start;}
        .advisor-textarea{flex:1;padding:12px 14px;border:1.5px solid var(--border);border-radius:9px;font-size:14px;font-family:inherit;resize:none;background:var(--accent);color:var(--text-primary);transition:border-color 0.2s,box-shadow 0.2s;outline:none;min-height:52px;}
        .advisor-textarea:focus{border-color:var(--primary);background:var(--white);box-shadow:0 0 0 3px var(--primary-tint-light);}
        .advisor-btn{padding:0 20px;height:52px;background:var(--primary);color:var(--on-primary);border:none;border-radius:9px;font-size:14px;font-weight:600;cursor:pointer;white-space:nowrap;transition:background 0.2s;flex-shrink:0;}
        .advisor-btn:hover:not(:disabled){background:var(--primary-dark);}
        .advisor-btn:disabled{background:#d1d5db;cursor:not-allowed;}
        .advisor-error{display:none;margin-top:10px;padding:10px 14px;background:var(--danger-bg);color:var(--danger);border-radius:7px;font-size:13px;border:1px solid var(--danger);}
        .advisor-results{display:none;border-top:1px solid var(--border);}
        .advisor-loading{display:none;padding:32px;text-align:center;color:var(--text-secondary);}
        .advisor-spinner{width:32px;height:32px;border:3px solid var(--border);border-top-color:var(--primary);border-radius:50%;animation:spin 0.7s linear infinite;margin:0 auto 12px;}
        .rec-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;padding:20px;}
        .rec-card{background:var(--white);border-radius:10px;padding:18px;border:1px solid var(--border);transition:transform 0.2s,box-shadow 0.2s,border-color 0.2s;}
        .rec-card:hover{transform:translateY(-3px);box-shadow:0 8px 20px rgba(0,0,0,0.08);border-color:var(--primary);}
        .rec-category{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--primary);background:var(--primary-tint-light);padding:2px 8px;border-radius:20px;display:inline-block;margin-bottom:10px;}
        .rec-name{font-size:15px;font-weight:700;color:var(--text-primary);margin-bottom:5px;}
        .rec-price{font-size:17px;font-weight:700;color:var(--primary);margin-bottom:10px;}
        .rec-reason{font-size:12px;color:var(--text-secondary);line-height:1.6;margin-bottom:14px;}
        .rec-btn{display:block;width:100%;padding:9px;text-align:center;background:var(--primary-tint-light);color:var(--primary);border-radius:7px;text-decoration:none;font-size:13px;font-weight:600;transition:background 0.2s;border:none;cursor:pointer;}
        .rec-btn:hover{background:var(--primary-tint-active);}

        /* Collaborative filtering */
        .collab-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:14px;}
        .collab-card{background:var(--white);border-radius:10px;padding:18px;border:1px solid var(--border);box-shadow:var(--card-shadow);transition:transform 0.2s,box-shadow 0.2s,border-color 0.2s;display:flex;flex-direction:column;}
        .collab-card:hover{transform:translateY(-3px);box-shadow:0 8px 20px rgba(0,0,0,0.08);border-color:var(--secondary);}
        .collab-category{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:#d97706;background:rgba(244,162,97,0.12);padding:2px 8px;border-radius:20px;display:inline-block;margin-bottom:10px;}
        .collab-name{font-size:14px;font-weight:700;color:var(--text-primary);margin-bottom:5px;}
        .collab-price{font-size:16px;font-weight:700;color:var(--primary);margin-bottom:10px;}
        .collab-freq{font-size:11px;color:var(--text-secondary);display:flex;align-items:center;gap:5px;margin-top:auto;padding-top:10px;border-top:1px solid var(--border);margin-bottom:12px;}
        .collab-btn{display:block;width:100%;padding:9px;text-align:center;background:rgba(244,162,97,0.08);color:#d97706;border-radius:7px;text-decoration:none;font-size:13px;font-weight:600;transition:background 0.2s;}
        .collab-btn:hover{background:rgba(244,162,97,0.2);}
        .collab-skeleton{background:var(--white);border-radius:10px;padding:18px;border:1px solid var(--border);animation:cpulse 1.5s ease-in-out infinite;}
        @keyframes cpulse{0%,100%{opacity:1}50%{opacity:0.45}}
        .skel-line{height:11px;background:#f0f0f0;border-radius:4px;margin-bottom:8px;}

        /* Footer */
        .dashboard-footer{text-align:center;padding:22px;color:var(--text-secondary);font-size:13px;border-top:1px solid var(--border);background:var(--white);}
        .footer-links a{color:var(--primary);text-decoration:none;margin:0 10px;}

        @keyframes spin{to{transform:rotate(360deg);}}

        @media(max-width:768px){
            .dashboard-stats{grid-template-columns:repeat(2,1fr);}
            .quick-actions{grid-template-columns:repeat(2,1fr);}
        }
    </style>
</head>
<body>

<?php include 'c_sidebar.php'; ?>

<main class="main-content">

    <!-- Header -->
    <header class="top-header">
        <div>
            <h1 class="page-title">Customer Dashboard</h1>
            <p class="page-subtitle">Manage your stationery and printing needs</p>
        </div>

        <div style="display:flex;align-items:center;gap:12px;">
        <!-- Preferred Branch selector (permanent — saved to your account; distinct from the
             session-only "Browsing Branch" selector shown on other pages) -->
        <div style="position:relative;" id="branchDropdownWrapper" title="Preferred Branch — saved permanently to your account.">
            <button type="button" onclick="toggleBranchPanel()"
                style="display:flex;align-items:center;gap:8px;padding:8px 16px;
                       border:1.5px solid var(--border);border-radius:20px;font-size:13px;
                       font-weight:600;color:var(--primary);background:rgba(168,53,53,0.06);
                       cursor:pointer;outline:none;transition:background 0.2s;">
                <i class="fas fa-star" style="font-size:11px;color:var(--warning);"></i>
                <span>Preferred: <?= htmlspecialchars($currentBranch ?? 'Select Branch') ?></span>
                <i class="fas fa-chevron-down" style="font-size:11px;opacity:0.6;"></i>
            </button>

            <div id="branchPanel"
                 style="display:none;position:absolute;top:calc(100% + 8px);right:0;
                        background:var(--white);border:1.5px solid var(--border);border-radius:12px;
                        box-shadow:0 8px 24px rgba(0,0,0,0.1);padding:18px;min-width:260px;
                        max-height:90vh;overflow-y:auto;z-index:100;">
                <form method="POST" action="c_dashboard.php">
                    <input type="hidden" name="switch_branch" value="1">

                    <p style="font-size:11px;font-weight:700;color:var(--text-secondary);
                              text-transform:uppercase;letter-spacing:0.6px;margin-bottom:4px;">
                        <i class="fas fa-star" style="color:var(--warning);"></i>
                        Your Preferred Branch
                    </p>
                    <p style="font-size:12px;color:var(--text-secondary);margin-bottom:12px;line-height:1.5;">
                        Saved permanently to your account.<br>
                        To browse a different branch without changing your preference,
                        use the selector on the <a href="c_viewproducts.php"
                        style="color:var(--primary);font-weight:600;">products page</a>.
                    </p>

                    <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:14px;
                                max-height:280px;overflow-y:auto;overscroll-behavior:contain;padding-right:4px;">
                        <?php foreach ($branchList as $b): ?>
                        <label style="display:flex;align-items:center;gap:10px;padding:9px 12px;
                                      border-radius:8px;cursor:pointer;transition:all 0.15s;font-size:13px;
                                      font-weight:500;border:1.5px solid var(--border);
                                      <?= ($activeBranchId === $b['branch_id'])
                                          ? 'background:var(--primary-tint-light);border-color:var(--primary);color:var(--primary);'
                                          : 'color:var(--text-primary);' ?>">
                            <input type="radio" name="branch_id"
                                   value="<?= htmlspecialchars($b['branch_id']) ?>"
                                   <?= ($activeBranchId === $b['branch_id']) ? 'checked' : '' ?>
                                   style="accent-color:var(--primary);">
                            <?= htmlspecialchars($b['branch_name']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <button type="submit"
                            style="width:100%;padding:10px;background:var(--primary);color:var(--on-primary);
                                   border:none;border-radius:8px;font-size:13px;font-weight:700;
                                   cursor:pointer;display:flex;align-items:center;justify-content:center;
                                   gap:8px;transition:background 0.2s;">
                        <i class="fas fa-star"></i> Save as My Branch
                    </button>
                </form>
            </div>
        </div>
        <button type="button" class="theme-toggle-header-btn" data-theme-cycle title="Theme" aria-label="Theme"><i class="fas fa-sun"></i></button>
        </div>
    </header>
    <script>if (window.initThemeToggle) initThemeToggle();</script>

    <div class="dashboard-content">

        <?php render_banner_slot($conn, 'C_DASHBOARD'); ?>

        <!-- Welcome -->
        <div class="welcome-card">
            <h2 class="welcome-title">Welcome back, <?= htmlspecialchars($userName) ?>!</h2>
            <p class="welcome-subtitle">
                You have <strong><?= $activeOrders ?></strong> active reservation<?= $activeOrders !== 1 ? 's' : '' ?> and
                <strong><?= $pendingPreorders ?></strong> awaiting processing.
                Use the sidebar or quick actions below to manage your stationery needs.
            </p>
        </div>

        <!-- Stats -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-title"><i class="fas fa-clipboard-list"></i> Active Reservations</div>
                <div class="stat-value"><?= $activeOrders ?></div>
                <div class="stat-detail">Pre-orders in progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-title"><i class="fas fa-clock"></i> Pending Pre-orders</div>
                <div class="stat-value"><?= $pendingPreorders ?></div>
                <div class="stat-detail">Awaiting processing</div>
            </div>
            <div class="stat-card">
                <div class="stat-title"><i class="fas fa-file-upload"></i> Uploaded Files</div>
                <div class="stat-value"><?= $uploadedFiles ?></div>
                <div class="stat-detail">Print jobs submitted</div>
            </div>
            <div class="stat-card">
                <div class="stat-title"><i class="fas fa-receipt"></i> Payment Pending</div>
                <div class="stat-value">RM <?= number_format($pendingPayment, 2) ?></div>
                <div class="stat-detail">Awaiting verification</div>
            </div>
            <div class="stat-card">
                <div class="stat-title"><i class="fas fa-coins"></i> Loyalty Points</div>
                <div class="stat-value"><?= $loyaltyPoints ?></div>
                <div class="stat-detail">Worth RM <?= number_format($loyaltyPoints / 100, 2) ?> &mdash; <a href="c_payment.php" style="color:var(--primary);font-weight:600;">redeem at payment</a></div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="content-section">
            <h3 class="section-title"><i class="fas fa-bolt"></i> Quick Actions</h3>
            <div class="quick-actions">
                <a href="c_viewproducts.php" class="action-card">
                    <div class="action-icon red"><i class="fas fa-box-open"></i></div>
                    <div class="action-title">Browse Products</div>
                    <div class="action-desc">View available stationery items</div>
                </a>
                <a href="c_preorder.php" class="action-card">
                    <div class="action-icon orange"><i class="fas fa-clipboard-check"></i></div>
                    <div class="action-title">Pre-order</div>
                    <div class="action-desc">Reserve out-of-stock items</div>
                </a>
                <a href="c_upload.php" class="action-card">
                    <div class="action-icon green"><i class="fas fa-upload"></i></div>
                    <div class="action-title">Upload Files</div>
                    <div class="action-desc">Submit files for printing</div>
                </a>
                <a href="c_payment.php" class="action-card">
                    <div class="action-icon blue"><i class="fas fa-receipt"></i></div>
                    <div class="action-title">Payment Record</div>
                    <div class="action-desc">Submit payment proof</div>
                </a>
                <a href="c_profile.php" class="action-card">
                    <div class="action-icon purple"><i class="fas fa-user-circle"></i></div>
                    <div class="action-title">My Profile</div>
                    <div class="action-desc">Update your details &amp; password</div>
                </a>
            </div>
        </div>

        <!-- Customers Also Bought -->
        <div class="content-section">
            <h3 class="section-title">
                <i class="fas fa-users" style="color:var(--warning);"></i>
                Customers Also Bought
                <span id="collabTypeLabel" style="font-size:12px;font-weight:400;color:var(--text-secondary);margin-left:6px;"></span>
            </h3>
            <div class="collab-grid" id="collabGrid">
                <!-- Skeletons while loading -->
                <?php for($i=0;$i<4;$i++): ?>
                <div class="collab-skeleton">
                    <div class="skel-line" style="width:40%;margin-bottom:12px;"></div>
                    <div class="skel-line" style="width:75%;"></div>
                    <div class="skel-line" style="width:30%;margin-bottom:18px;"></div>
                    <div class="skel-line" style="width:60%;"></div>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- AI Product Advisor -->
        <div class="content-section">
            <h3 class="section-title">
                <i class="fas fa-magic"></i> AI Product Advisor
                <span style="font-size:12px;font-weight:400;color:var(--text-secondary);margin-left:6px;">
                    Describe what you need — AI finds the best match from our catalog
                </span>
            </h3>
            <div class="advisor-wrap">
                <div class="advisor-input-area">
                    <p class="advisor-hint">
                        <i class="fas fa-lightbulb" style="color:var(--secondary);margin-right:5px;"></i>
                        Try: <em>"I'm starting university and need supplies"</em> &nbsp;·&nbsp;
                        <em>"I need to organise my home office"</em> &nbsp;·&nbsp;
                        <em>"My child is starting primary school"</em>
                    </p>
                    <div class="advisor-row">
                        <textarea id="advisorInput" class="advisor-textarea" rows="2"
                            placeholder="Describe what you're looking for or what problem you're trying to solve…"></textarea>
                        <button class="advisor-btn" id="advisorBtn" onclick="getRecommendations()">
                            <i class="fas fa-search"></i> Ask AI
                        </button>
                    </div>
                    <div class="advisor-error" id="advisorError"></div>
                </div>

                <div class="advisor-results" id="advisorResults">
                    <div class="advisor-loading" id="advisorLoading">
                        <div class="advisor-spinner"></div>
                        <div>Finding the best products for you…</div>
                    </div>
                    <div class="rec-grid" id="advisorCards" style="display:none;"></div>
                </div>
            </div>
        </div>

        <!-- Recent Pre-orders -->
        <div class="content-section">
            <h3 class="section-title"><i class="fas fa-clock"></i> Recent Pre-orders</h3>
            <div class="table-card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Pre-order ID</th><th>Date</th><th>Notes</th><th>Status</th><th>Est. Total</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentPreorders)): ?>
                            <tr class="empty-row">
                                <td colspan="6">No pre-orders yet.
                                    <a href="c_preorder.php" style="color:var(--primary);">Place a pre-order</a>.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentPreorders as $po): ?>
                            <tr>
                                <td><span class="order-id"><?= htmlspecialchars($po['preorder_id']) ?></span></td>
                                <td><?= date('d M Y', strtotime($po['order_date'])) ?></td>
                                <td><?= htmlspecialchars($po['notes'] ?? '—') ?></td>
                                <td><?= orderStatusBadge($po['order_status']) ?></td>
                                <td>RM <?= number_format($po['estimated_total'] ?? 0, 2) ?></td>
                                <td>
                                    <button type="button" onclick="openOrderModal('<?= htmlspecialchars($po['preorder_id'], ENT_QUOTES) ?>')"
                                            style="padding:6px 12px;background:var(--primary-tint-light);color:var(--primary);
                                                   border:1px solid rgba(168,53,53,0.3);border-radius:7px;font-size:12px;
                                                   font-weight:600;cursor:pointer;white-space:nowrap;">
                                        <i class="fas fa-arrow-right"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /.dashboard-content -->

    <footer class="dashboard-footer">
        &copy; <?= date('Y') ?> StationaryPlus &mdash; Stationery &amp; Printing Management System
    </footer>

</main>

<!-- Order details modal (reuses the same c_orderdetail.php endpoint as c_orderstatus.php) -->
<div class="modal-overlay" id="orderModalOverlay" onclick="if(event.target===this)closeOrderModal()"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:200;align-items:center;justify-content:center;">
    <div class="modal" style="background:var(--white);border-radius:12px;width:90%;max-width:520px;max-height:85vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="padding:18px 22px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
            <div style="font-size:16px;font-weight:700;color:var(--primary);">Order Details</div>
            <button onclick="closeOrderModal()" style="background:none;border:none;font-size:18px;color:var(--text-secondary);cursor:pointer;padding:4px 8px;border-radius:6px;"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="orderModalBody" style="padding:22px;"></div>
    </div>
</div>

<!-- Branch popup (first login — no preferred branch set yet) -->
<?php if ($showBranchPopup): ?>
<div id="branchPopup"
     style="position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:999;
            display:flex;align-items:center;justify-content:center;">
    <div style="background:var(--white);border-radius:14px;padding:36px 32px;width:90%;max-width:420px;
                box-shadow:0 20px 60px rgba(0,0,0,0.2);text-align:center;">
        <div style="width:56px;height:56px;background:var(--primary-tint-medium);border-radius:12px;
                    display:flex;align-items:center;justify-content:center;margin:0 auto 16px;
                    font-size:24px;color:var(--primary);">
            <i class="fas fa-store"></i>
        </div>
        <?php if ($branchBecameUnavailable): ?>
        <h2 style="font-size:20px;font-weight:700;margin-bottom:8px;">Your Branch Is Unavailable</h2>
        <p style="font-size:14px;color:var(--text-secondary);margin-bottom:6px;">
            Your previously selected branch is temporarily closed (inactive or under renovation).
            Please choose another branch to continue.
        </p>
        <?php else: ?>
        <h2 style="font-size:20px;font-weight:700;margin-bottom:8px;">Welcome! Choose Your Branch</h2>
        <p style="font-size:14px;color:var(--text-secondary);margin-bottom:6px;">
            Which branch will you be collecting from?
        </p>
        <?php endif; ?>
        <p style="font-size:12px;color:var(--text-secondary);margin-bottom:24px;
                  background:var(--warning-bg);border:1px solid #fde68a;border-radius:8px;padding:8px 12px;">
            <i class="fas fa-star" style="color:var(--warning);"></i>
            This will be saved as your <strong>preferred branch</strong>.
            You can change it anytime from your dashboard.
        </p>
        <form method="POST" action="c_dashboard.php">
            <input type="hidden" name="switch_branch" value="1">
            <select name="branch_id" onchange="this.form.submit()"
                    style="width:100%;padding:12px 16px;border:1.5px solid var(--border);
                           border-radius:9px;font-size:14px;font-weight:600;color:var(--primary);
                           background:rgba(168,53,53,0.04);cursor:pointer;outline:none;
                           appearance:none;text-align:center;">
                <option value="">— Choose a branch —</option>
                <?php foreach ($branchList as $b): ?>
                    <option value="<?= htmlspecialchars($b['branch_id']) ?>">
                        <?= htmlspecialchars($b['branch_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// ── Branch panel ──────────────────────────────────────────────
function toggleBranchPanel() {
    const p = document.getElementById('branchPanel');
    p.style.display = p.style.display === 'none' ? 'block' : 'none';
}
document.addEventListener('click', e => {
    const w = document.getElementById('branchDropdownWrapper');
    if (w && !w.contains(e.target)) {
        document.getElementById('branchPanel').style.display = 'none';
    }
});

// ── Collaborative Filtering ───────────────────────────────────
async function loadCollabRecommendations() {
    try {
        const res  = await fetch('collab_recommend.php');
        const data = await res.json();
        const grid = document.getElementById('collabGrid');
        const lbl  = document.getElementById('collabTypeLabel');

        if (!data.success || !data.recommendations?.length) {
            grid.innerHTML = '<p style="color:var(--text-secondary);font-size:14px;grid-column:1/-1;padding:10px 0;">No recommendations available yet.</p>';
            return;
        }

        // Label changes based on whether it's collaborative or popular
        if (data.type === 'collaborative') {
            lbl.textContent = '— Based on similar customers\' purchases';
        } else {
            lbl.textContent = '— Most popular in our store';
        }

        grid.innerHTML = data.recommendations.map(p => `
            <div class="collab-card">
                <span class="collab-category">${escHtml(p.category)}</span>
                <div class="collab-name">${escHtml(p.product_name)}</div>
                <div class="collab-price">RM ${parseFloat(p.price).toFixed(2)}</div>
                <div class="collab-freq">
                    <i class="fas fa-${data.type === 'collaborative' ? 'users' : 'fire'}"
                       style="color:var(--warning);font-size:11px;flex-shrink:0;"></i>
                    ${escHtml(p.freq_label)}
                </div>
                <a href="c_viewproducts.php?search=${encodeURIComponent(p.product_name)}"
                   class="collab-btn">
                    <i class="fas fa-eye"></i> View Product
                </a>
            </div>`
        ).join('');

    } catch (e) {
        const grid = document.getElementById('collabGrid');
        if (grid) grid.innerHTML = '<p style="color:var(--text-secondary);font-size:14px;grid-column:1/-1;">Could not load recommendations.</p>';
    }
}

window.addEventListener('load', loadCollabRecommendations);
function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}


async function getRecommendations() {
    const input  = document.getElementById('advisorInput');
    const btn    = document.getElementById('advisorBtn');
    const errBox = document.getElementById('advisorError');
    const query  = input.value.trim();

    if (!query) { input.focus(); return; }

    errBox.style.display = 'none';
    btn.disabled         = true;
    btn.innerHTML        = '<i class="fas fa-spinner fa-spin"></i> Thinking…';

    const results = document.getElementById('advisorResults');
    const loading = document.getElementById('advisorLoading');
    const cards   = document.getElementById('advisorCards');

    results.style.display = 'block';
    loading.style.display = 'block';
    cards.style.display   = 'none';

    try {
        const form = new FormData();
        form.append('query', query);

        const res  = await fetch('ai_recommend.php', { method: 'POST', body: form });
        const data = await res.json();

        loading.style.display = 'none';

        if (!data.success) {
            errBox.textContent    = data.error || 'Something went wrong. Please try again.';
            errBox.style.display  = 'block';
            results.style.display = 'none';
            return;
        }

        renderCards(data.recommendations);

    } catch (e) {
        loading.style.display = 'none';
        errBox.textContent    = 'Network error. Please try again.';
        errBox.style.display  = 'block';
        results.style.display = 'none';
    } finally {
        btn.disabled  = false;
        btn.innerHTML = '<i class="fas fa-search"></i> Ask AI';
    }
}

function renderCards(recs) {
    const cards = document.getElementById('advisorCards');
    cards.innerHTML = recs.map(p => `
        <div class="rec-card">
            <span class="rec-category">${esc(p.category)}</span>
            <div class="rec-name">${esc(p.product_name)}</div>
            <div class="rec-price">RM ${parseFloat(p.price).toFixed(2)}</div>
            <div class="rec-reason">
                <i class="fas fa-check-circle" style="color:var(--success);margin-right:4px;font-size:11px;"></i>
                ${esc(p.reason)}
            </div>
            <a href="c_viewproducts.php?search=${encodeURIComponent(p.product_name)}" class="rec-btn">
                <i class="fas fa-eye"></i> View Product
            </a>
        </div>`).join('');

    cards.style.display = 'grid';
    cards.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function esc(s) {
    return String(s ?? '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Recent Pre-orders drill-in (same c_orderdetail.php endpoint used by c_orderstatus.php) ──
function openOrderModal(id) {
    document.getElementById('orderModalBody').innerHTML =
        '<div style="text-align:center;padding:30px;color:#707070"><i class="fas fa-spinner fa-spin" style="font-size:22px;margin-bottom:10px;display:block;"></i>Loading…</div>';
    document.getElementById('orderModalOverlay').style.display = 'flex';

    fetch('c_orderdetail.php?id=' + encodeURIComponent(id))
        .then(r => r.json())
        .then(d => renderOrderReceipt(d))
        .catch(() => {
            document.getElementById('orderModalBody').innerHTML =
                '<p style="text-align:center;color:var(--danger);padding:30px;">Failed to load details.</p>';
        });
}

function closeOrderModal() {
    document.getElementById('orderModalOverlay').style.display = 'none';
    document.getElementById('orderModalBody').innerHTML = '';
}

function renderOrderReceipt(d) {
    if (d.error) {
        document.getElementById('orderModalBody').innerHTML =
            '<p style="text-align:center;color:var(--danger);padding:30px;">' + esc(d.error) + '</p>';
        return;
    }

    const row = (label, value) => `
        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border);font-size:14px;">
            <span style="color:var(--text-secondary);">${label}</span>
            <span style="font-weight:600;color:var(--text-primary);">${value}</span>
        </div>`;

    let itemsHtml = '';
    if (d.items && d.items.length > 0) {
        itemsHtml = `
        <div style="margin:14px 0 4px;font-size:12px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.5px;">
            <i class="fas fa-box"></i> Stationery Items
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:13px;margin-bottom:10px;">
            <tbody>
                ${d.items.map(i => `
                <tr style="border-bottom:1px solid #f0f0f0;">
                    <td style="padding:9px 10px;font-weight:600;">${esc(i.name)}</td>
                    <td style="padding:9px 10px;text-align:center;">×${i.qty}</td>
                    <td style="padding:9px 10px;text-align:right;font-family:monospace;">RM ${(i.qty * i.unit_price).toFixed(2)}</td>
                </tr>`).join('')}
            </tbody>
        </table>`;
    }

    document.getElementById('orderModalBody').innerHTML = `
        ${row('Order ID', `<span style="font-family:monospace;">${esc(d.id)}</span>`)}
        ${row('Date', esc(d.date))}
        ${row('Order Status', esc(d.status))}
        ${itemsHtml}
        <div style="display:flex;justify-content:space-between;padding:14px 0 0;margin-top:8px;border-top:2px solid var(--primary);">
            <span style="font-size:15px;font-weight:700;">Estimated Total</span>
            <span style="font-size:20px;font-weight:800;color:var(--primary);">
                ${d.total !== null ? 'RM ' + parseFloat(d.total).toFixed(2) : '—'}
            </span>
        </div>
        <div style="margin-top:14px;text-align:center;">
            <a href="c_orderstatus.php" style="display:inline-flex;align-items:center;gap:8px;padding:9px 20px;
                   background:var(--primary-tint-light);color:var(--primary);border:1.5px solid rgba(168,53,53,0.3);
                   border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;">
                <i class="fas fa-list"></i> View all orders
            </a>
        </div>
    `;
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeOrderModal();
});

// Enter key in advisor textarea
document.getElementById('advisorInput').addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); getRecommendations(); }
});
</script>
</body>
</html>