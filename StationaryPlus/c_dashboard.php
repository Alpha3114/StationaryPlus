<?php
// ============================================================
//  c_dashboard.php — Customer Dashboard
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role('CUSTOMER');
require_once 'db.php';

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
$showBranchPopup = empty($_SESSION['branch_id']);
$currentBranch   = null;

if (!empty($_SESSION['branch_id'])) {
    $stmt = $conn->prepare(
        "SELECT branch_name FROM branches WHERE branch_id = ? LIMIT 1"
    );
    $stmt->bind_param('s', $_SESSION['branch_id']);
    $stmt->execute();
    $currentBranch = $stmt->get_result()->fetch_assoc()['branch_name'] ?? null;
    $stmt->close();
}

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
    $map = [
        'NEW'        => ['#3b82f6','#eff6ff','New'],
        'PROCESSING' => ['#f59e0b','#fffbeb','Processing'],
        'READY'      => ['#10b981','#ecfdf5','Ready'],
        'COLLECTED'  => ['#6b7280','#f3f4f6','Collected'],
        'CANCELLED'  => ['#ef4444','#fef2f2','Cancelled'],
        'SUBMITTED'  => ['#8b5cf6','#f5f3ff','Submitted'],
    ];
    [$c,$bg,$l] = $map[$status] ?? ['#6b7280','#f3f4f6',$status];
    return "<span style='background:$bg;color:$c;border:1px solid {$c}44;
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
    <style>
        :root {
            --primary:#A83535; --secondary:#F4A261; --accent:#F1EDE8;
            --background:#FAFAFA; --text-primary:#2E2E2E; --text-secondary:#707070;
            --border:#E0E0E0; --white:#FFFFFF;
            --sidebar-width:260px; --card-shadow:0 4px 12px rgba(0,0,0,0.05);
        }
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',system-ui,sans-serif;}
        body{background:var(--background);color:var(--text-primary);min-height:100vh;display:flex;}

        /* Sidebar */
        .sidebar{width:var(--sidebar-width);background:var(--white);border-right:1px solid var(--border);height:100vh;position:fixed;left:0;top:0;display:flex;flex-direction:column;box-shadow:2px 0 10px rgba(0,0,0,0.03);overflow-y:auto;}
        .logo-area{padding:25px;border-bottom:1px solid var(--border);display:flex;align-items:center;flex-shrink:0;}
        .logo-icon{background:var(--primary);width:40px;height:40px;border-radius:8px;display:flex;align-items:center;justify-content:center;margin-right:12px;color:white;font-size:20px;}
        .logo-text{font-size:22px;font-weight:700;color:var(--primary);}
        .nav-section{padding:25px 0;border-bottom:1px solid var(--border);}
        .nav-title{font-size:12px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.8px;padding:0 25px 12px;}
        .nav-menu{list-style:none;}
        .nav-link{display:flex;align-items:center;padding:13px 25px;color:var(--text-primary);text-decoration:none;transition:all 0.2s;border-left:4px solid transparent;}
        .nav-link:hover{background:rgba(168,53,53,0.05);color:var(--primary);border-left-color:rgba(168,53,53,0.3);}
        .nav-link.active{background:rgba(168,53,53,0.08);color:var(--primary);border-left-color:var(--primary);font-weight:600;}
        .nav-icon{width:22px;text-align:center;margin-right:14px;font-size:16px;}
        .nav-text{font-size:15px;}
        .user-section{margin-top:auto;padding:20px 25px;border-top:1px solid var(--border);}
        .user-info{display:flex;align-items:center;margin-bottom:14px;cursor:pointer;}
        .user-avatar{width:40px;height:40px;border-radius:50%;background:rgba(168,53,53,0.1);display:flex;align-items:center;justify-content:center;color:var(--primary);font-weight:700;font-size:16px;margin-right:12px;flex-shrink:0;}
        .user-name{font-weight:600;font-size:15px;}
        .user-role{font-size:12px;color:var(--text-secondary);margin-top:2px;}
        .logout-link{display:flex;align-items:center;gap:10px;padding:10px 14px;background:rgba(168,53,53,0.06);color:var(--primary);border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;}
        .logout-link:hover{background:rgba(168,53,53,0.14);}

        /* Main */
        .main-content{flex-grow:1;margin-left:var(--sidebar-width);min-height:100vh;display:flex;flex-direction:column;}
        .top-header{background:var(--white);padding:20px 30px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:10;}
        .page-title{font-size:24px;font-weight:700;}
        .page-subtitle{font-size:14px;color:var(--text-secondary);margin-top:4px;}
        .dashboard-content{padding:30px;flex-grow:1;}

        /* Welcome */
        .welcome-card{background:linear-gradient(135deg,rgba(168,53,53,0.06),rgba(244,162,97,0.06));border-radius:12px;padding:28px 30px;margin-bottom:28px;border-left:5px solid var(--primary);}
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
        .action-icon.red   {background:rgba(168,53,53,0.1);color:var(--primary);}
        .action-icon.orange{background:rgba(244,162,97,0.1);color:var(--secondary);}
        .action-icon.green {background:rgba(16,185,129,0.1);color:#10b981;}
        .action-icon.blue  {background:rgba(59,130,246,0.1);color:#3b82f6;}
        .action-icon.purple{background:rgba(139,92,246,0.1);color:#8b5cf6;}
        .action-title{font-size:15px;font-weight:600;color:var(--text-primary);margin-bottom:6px;}
        .action-desc{font-size:13px;color:var(--text-secondary);}

        /* Tables */
        .table-card{background:var(--white);border-radius:10px;overflow:hidden;box-shadow:var(--card-shadow);border:1px solid var(--border);}
        .data-table{width:100%;border-collapse:collapse;}
        .data-table thead{background:rgba(168,53,53,0.04);border-bottom:2px solid var(--border);}
        .data-table th{padding:14px 20px;text-align:left;font-size:12px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.4px;}
        .data-table tbody tr{border-bottom:1px solid var(--border);transition:background 0.15s;}
        .data-table tbody tr:last-child{border-bottom:none;}
        .data-table tbody tr:hover{background:rgba(168,53,53,0.02);}
        .data-table td{padding:14px 20px;font-size:14px;vertical-align:middle;}
        .order-id{font-weight:600;color:var(--primary);font-family:monospace;font-size:13px;}
        .empty-row td{text-align:center;padding:28px;color:var(--text-secondary);font-style:italic;}

        /* AI Advisor */
        .advisor-wrap{background:var(--white);border-radius:12px;border:1px solid var(--border);box-shadow:var(--card-shadow);overflow:hidden;}
        .advisor-input-area{padding:24px;}
        .advisor-hint{font-size:13px;color:var(--text-secondary);margin-bottom:12px;line-height:1.6;}
        .advisor-row{display:flex;gap:10px;align-items:flex-start;}
        .advisor-textarea{flex:1;padding:12px 14px;border:1.5px solid var(--border);border-radius:9px;font-size:14px;font-family:inherit;resize:none;background:var(--accent);color:var(--text-primary);transition:border-color 0.2s,box-shadow 0.2s;outline:none;min-height:52px;}
        .advisor-textarea:focus{border-color:var(--primary);background:var(--white);box-shadow:0 0 0 3px rgba(168,53,53,0.08);}
        .advisor-btn{padding:0 20px;height:52px;background:var(--primary);color:white;border:none;border-radius:9px;font-size:14px;font-weight:600;cursor:pointer;white-space:nowrap;transition:background 0.2s;flex-shrink:0;}
        .advisor-btn:hover:not(:disabled){background:#8b2a2a;}
        .advisor-btn:disabled{background:#d1d5db;cursor:not-allowed;}
        .advisor-error{display:none;margin-top:10px;padding:10px 14px;background:#fff0f0;color:#c62828;border-radius:7px;font-size:13px;border:1px solid #ef9a9a;}
        .advisor-results{display:none;border-top:1px solid var(--border);}
        .advisor-loading{display:none;padding:32px;text-align:center;color:var(--text-secondary);}
        .advisor-spinner{width:32px;height:32px;border:3px solid var(--border);border-top-color:var(--primary);border-radius:50%;animation:spin 0.7s linear infinite;margin:0 auto 12px;}
        .rec-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;padding:20px;}
        .rec-card{background:var(--white);border-radius:10px;padding:18px;border:1px solid var(--border);transition:transform 0.2s,box-shadow 0.2s,border-color 0.2s;}
        .rec-card:hover{transform:translateY(-3px);box-shadow:0 8px 20px rgba(0,0,0,0.08);border-color:var(--primary);}
        .rec-category{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--primary);background:rgba(168,53,53,0.08);padding:2px 8px;border-radius:20px;display:inline-block;margin-bottom:10px;}
        .rec-name{font-size:15px;font-weight:700;color:var(--text-primary);margin-bottom:5px;}
        .rec-price{font-size:17px;font-weight:700;color:var(--primary);margin-bottom:10px;}
        .rec-reason{font-size:12px;color:var(--text-secondary);line-height:1.6;margin-bottom:14px;}
        .rec-btn{display:block;width:100%;padding:9px;text-align:center;background:rgba(168,53,53,0.06);color:var(--primary);border-radius:7px;text-decoration:none;font-size:13px;font-weight:600;transition:background 0.2s;border:none;cursor:pointer;}
        .rec-btn:hover{background:rgba(168,53,53,0.14);}

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

        @media(max-width:1024px){
            :root{--sidebar-width:70px;}
            .logo-text,.nav-text,.user-name,.user-role,.nav-title,.logout-link span{display:none;}
            .logo-area,.nav-section,.user-section{padding:18px 12px;}
            .nav-link{justify-content:center;padding:14px;border-left:none;border-right:4px solid transparent;}
            .nav-link:hover,.nav-link.active{border-left:none;border-right-color:var(--primary);}
            .nav-icon{margin-right:0;font-size:20px;}
            .logout-link{justify-content:center;}
        }
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

        <!-- Branch selector -->
        <div style="position:relative;" id="branchDropdownWrapper">
            <button type="button" onclick="toggleBranchPanel()"
                style="display:flex;align-items:center;gap:8px;padding:8px 16px;
                       border:1.5px solid var(--border);border-radius:20px;font-size:13px;
                       font-weight:600;color:var(--primary);background:rgba(168,53,53,0.06);
                       cursor:pointer;outline:none;transition:background 0.2s;">
                <i class="fas fa-store"></i>
                <span><?= htmlspecialchars($currentBranch ?? 'Select Branch') ?></span>
                <i class="fas fa-chevron-down" style="font-size:11px;opacity:0.6;"></i>
            </button>

            <div id="branchPanel"
                 style="display:none;position:absolute;top:calc(100% + 8px);right:0;
                        background:white;border:1.5px solid var(--border);border-radius:12px;
                        box-shadow:0 8px 24px rgba(0,0,0,0.1);padding:18px;min-width:260px;z-index:100;">
                <form method="POST" action="c_dashboard.php">
                    <input type="hidden" name="switch_branch" value="1">

                    <p style="font-size:11px;font-weight:700;color:var(--text-secondary);
                              text-transform:uppercase;letter-spacing:0.6px;margin-bottom:4px;">
                        <i class="fas fa-star" style="color:#f59e0b;"></i>
                        Your Preferred Branch
                    </p>
                    <p style="font-size:12px;color:var(--text-secondary);margin-bottom:12px;line-height:1.5;">
                        Saved permanently to your account.<br>
                        To browse a different branch without changing your preference,
                        use the selector on the <a href="c_viewproducts.php"
                        style="color:var(--primary);font-weight:600;">products page</a>.
                    </p>

                    <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:14px;">
                        <?php foreach ($branchList as $b): ?>
                        <label style="display:flex;align-items:center;gap:10px;padding:9px 12px;
                                      border-radius:8px;cursor:pointer;transition:all 0.15s;font-size:13px;
                                      font-weight:500;border:1.5px solid var(--border);
                                      <?= ($activeBranchId === $b['branch_id'])
                                          ? 'background:rgba(168,53,53,0.08);border-color:var(--primary);color:var(--primary);'
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
                            style="width:100%;padding:10px;background:var(--primary);color:white;
                                   border:none;border-radius:8px;font-size:13px;font-weight:700;
                                   cursor:pointer;display:flex;align-items:center;justify-content:center;
                                   gap:8px;transition:background 0.2s;">
                        <i class="fas fa-star"></i> Save as My Branch
                    </button>
                </form>
            </div>
        </div>
    </header>

    <div class="dashboard-content">

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
                <i class="fas fa-users" style="color:#d97706;"></i>
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
                            <th>Pre-order ID</th><th>Date</th><th>Notes</th><th>Status</th><th>Est. Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentPreorders)): ?>
                            <tr class="empty-row">
                                <td colspan="5">No pre-orders yet.
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
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /.dashboard-content -->

    <footer class="dashboard-footer">
        &copy; <?= date('Y') ?> StationaryPlus — Stationery &amp; Printing Management System
        <div class="footer-links">
            <a href="#">Help Center</a> | <a href="#">Contact Support</a> | <a href="#">Privacy Policy</a>
        </div>
    </footer>

</main>

<!-- Branch popup (first login — no preferred branch set yet) -->
<?php if ($showBranchPopup): ?>
<div id="branchPopup"
     style="position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:999;
            display:flex;align-items:center;justify-content:center;">
    <div style="background:white;border-radius:14px;padding:36px 32px;width:90%;max-width:420px;
                box-shadow:0 20px 60px rgba(0,0,0,0.2);text-align:center;">
        <div style="width:56px;height:56px;background:rgba(168,53,53,0.1);border-radius:12px;
                    display:flex;align-items:center;justify-content:center;margin:0 auto 16px;
                    font-size:24px;color:var(--primary);">
            <i class="fas fa-store"></i>
        </div>
        <h2 style="font-size:20px;font-weight:700;margin-bottom:8px;">Welcome! Choose Your Branch</h2>
        <p style="font-size:14px;color:var(--text-secondary);margin-bottom:6px;">
            Which branch will you be collecting from?
        </p>
        <p style="font-size:12px;color:var(--text-secondary);margin-bottom:24px;
                  background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:8px 12px;">
            <i class="fas fa-star" style="color:#f59e0b;"></i>
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
                       style="color:#d97706;font-size:11px;flex-shrink:0;"></i>
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
                <i class="fas fa-check-circle" style="color:#10b981;margin-right:4px;font-size:11px;"></i>
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

// Enter key in advisor textarea
document.getElementById('advisorInput').addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); getRecommendations(); }
});
</script>
</body>
</html>