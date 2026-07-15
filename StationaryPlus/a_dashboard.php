<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'auth.php';
require_role(['STAFF', 'ADMIN']);

require_once 'db.php';

$userName = $_SESSION['user_name'];

// ========================================
// DASHBOARD STATISTICS — one combined query via scalar subqueries
// instead of 8 separate round trips.
// ========================================

$statsRes = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM users) AS total_users,
        (SELECT COUNT(*) FROM users
         WHERE MONTH(registration_date) = MONTH(CURRENT_DATE())
           AND YEAR(registration_date) = YEAR(CURRENT_DATE())) AS new_users,
        (SELECT COUNT(*) FROM products WHERE product_status = 'ACTIVE') AS total_products,
        (SELECT COUNT(*) FROM products
         WHERE MONTH(last_updated) = MONTH(CURRENT_DATE())
           AND YEAR(last_updated) = YEAR(CURRENT_DATE())) AS new_products,
        (SELECT COUNT(*) FROM branches WHERE status = 'ACTIVE') AS total_branches,
        (SELECT COUNT(*) FROM branches
         WHERE MONTH(last_updated) = MONTH(CURRENT_DATE())
           AND YEAR(last_updated) = YEAR(CURRENT_DATE())) AS updated_branches,
        (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE verification_status = 'VALID') AS total_sales,
        (SELECT COALESCE(SUM(amount), 0) FROM payments
         WHERE verification_status = 'VALID'
           AND MONTH(record_date) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH)
           AND YEAR(record_date) = YEAR(CURRENT_DATE - INTERVAL 1 MONTH)) AS last_month_sales,
        (SELECT COUNT(*) FROM inventory WHERE stock_quantity = 0) AS out_of_stock,
        (SELECT COUNT(*) FROM inventory WHERE stock_quantity > 0 AND stock_quantity <= minimum_level) AS low_stock
");
$stats = $statsRes->fetch_assoc();

$totalUsers      = (int)$stats['total_users'];
$newUsers        = (int)$stats['new_users'];
$totalProducts   = (int)$stats['total_products'];
$newProducts     = (int)$stats['new_products'];
$totalBranches   = (int)$stats['total_branches'];
$updatedBranches = (int)$stats['updated_branches'];
$totalSales      = (float)$stats['total_sales'];
$lastMonthSales  = (float)$stats['last_month_sales'];
$outOfStock      = (int)$stats['out_of_stock'];
$lowStock        = (int)$stats['low_stock'];

// ========================================
// SALES TREND PERCENTAGE
// ========================================

$salesTrend = 0;

if ($lastMonthSales > 0) {
    $salesTrend = (($totalSales - $lastMonthSales) / $lastMonthSales) * 100;
}

$salesTrendDown = $salesTrend < 0;
$salesTrend = number_format($salesTrend, 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus - Administrator Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/tokens.css">
    <script src="assets/js/theme.js"></script>
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <style>
        :root {
            --primary: #A83535;      /* Brick Red */
            --secondary: #F4A261;    /* Muted Orange */
            --background: #FAFAFA;   /* Light Grey */
            --accent: #F1EDE8;
            --text-primary: #2E2E2E;         /* Dark Charcoal */
            --text-secondary: #707070;   /* Secondary Text */
            --border: #E0E0E0;       /* Border Grey */
            --white: #FFFFFF;
            --sidebar-width: 260px;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        
        body {
            background-color: var(--background);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
        }
        
        /* Main Content Area */
        .main-content {
            flex-grow: 1;
            margin-left: var(--sidebar-width);
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .admin-top-header {
            background-color: var(--white);
            padding: 22px 35px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
        }
        
        .admin-header-left h1 {
            font-size: 28px;
            color: var(--text-primary);
            margin-bottom: 6px;
            font-weight: 700;
        }
        
        .admin-header-left p {
            font-size: 15px;
            color: var(--text-secondary);
        }
        
        .admin-header-right {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        
        .admin-date {
            font-size: 15px;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        /* Admin Dashboard Content */
        .admin-dashboard {
            flex-grow: 1;
            padding: 35px;
            display: flex;
            flex-direction: column;
            gap: 35px;
            overflow: hidden;
        }
        
        /* Overview Cards Section */
        .overview-section {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
        }
        
        .overview-card {
            background-color: var(--white);
            border-radius: 12px;
            padding: 28px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }
        
        .overview-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        }
        
        .overview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .overview-title {
            font-size: 17px;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .overview-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: var(--on-primary);
        }
        
        .users-icon {
            background-color: var(--primary);
        }
        
        .products-icon {
            background-color: #4A6FA5; /* Blue */
        }
        
        .branches-icon {
            background-color: #4CAF50; /* Green */
        }
        
        .sales-icon {
            background-color: var(--secondary);
        }
        
        .overview-value {
            font-size: 36px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .overview-trend {
            font-size: 14px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: auto;
        }
        
        .trend-up {
            color: var(--success);
        }
        
        .trend-down {
            color: var(--primary);
        }

        /* Needs Your Attention Section */
        .attention-section {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
        }

        .attention-card {
            background-color: var(--white);
            border-radius: 12px;
            padding: 22px 24px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 16px;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s ease;
        }

        .attention-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        }

        .attention-card.has-alert {
            border-color: var(--primary-tint-active);
        }

        .attention-icon {
            width: 46px;
            height: 46px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
            flex-shrink: 0;
            background-color: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }

        .attention-card.has-alert .attention-icon {
            background-color: var(--primary-tint-medium);
            color: var(--primary);
        }

        .attention-value {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.2;
        }

        .attention-card.has-alert .attention-value {
            color: var(--primary);
        }

        .attention-label {
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 600;
            margin-top: 2px;
        }

        .attention-sub {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 2px;
        }

        /* Navigation Cards Section */
        .admin-navigation-section {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .section-title {
            font-size: 22px;
            color: var(--primary);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
        }
        
        .section-title i {
            font-size: 24px;
        }
        
        .navigation-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            height: 100%;
        }
        
        .admin-nav-card {
            background-color: var(--white);
            border-radius: 12px;
            padding: 35px 28px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .admin-nav-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border-color: var(--primary);
        }
        
        .admin-nav-icon {
            width: 70px;
            height: 70px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 25px;
            font-size: 30px;
            color: var(--on-primary);
        }
        
        .nav-manage-users {
            background-color: var(--primary);
        }
        
        .nav-manage-products {
            background-color: #4A6FA5; /* Blue */
        }
        
        .nav-manage-branches {
            background-color: #4CAF50; /* Green */
        }
        
        .nav-sales-report {
            background-color: var(--secondary);
        }
        
        .admin-nav-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 12px;
        }
        
        .admin-nav-description {
            font-size: 15px;
            color: var(--text-secondary);
            line-height: 1.5;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1400px) {
            .overview-section, .navigation-grid, .attention-section {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 1024px) {
            .header-left h1 {
                font-size: 24px;
            }
        }

        @media (max-width: 768px) {
            .overview-section, .navigation-grid, .attention-section {
                grid-template-columns: 1fr;
            }
            
            .admin-dashboard {
                padding: 25px;
                gap: 25px;
            }
        }
    </style>
</head>
<body>
    <?php include 'a_sidebar.php'; ?>
    
    <!-- Main Content Area -->
    <main class="main-content">
        <!-- Top Header -->
        <header class="admin-top-header">
            <div class="admin-header-left">
                <h1>Administrator Dashboard</h1>
                <p>System overview and management controls</p>
            </div>
            <div class="admin-header-right">
                <div class="admin-date"><?= date('F j, Y') ?></div>
            </div>
        </header>
        
        <!-- Admin Dashboard Content -->
        <div class="admin-dashboard">
            <!-- Overview Cards Section -->
            <section class="overview-section">
                <div class="overview-card">
                    <div class="overview-header">
                        <h3 class="overview-title">Total Users</h3>
                        <div class="overview-icon users-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="overview-value">
    <?php echo number_format($totalUsers); ?>
</div>
                    <div class="overview-trend">
                        <i class="fas fa-arrow-up trend-up"></i>
                        <span>+<?php echo $newUsers; ?> this month</span>
                    </div>
                </div>
                
                <div class="overview-card">
                    <div class="overview-header">
                        <h3 class="overview-title">Products</h3>
                        <div class="overview-icon products-icon">
                            <i class="fas fa-box-open"></i>
                        </div>
                    </div>
                    <div class="overview-value">
    <?php echo number_format($totalProducts); ?>
</div>
                    <div class="overview-trend">
                        <i class="fas fa-arrow-up trend-up"></i>
                        <span>+<?php echo $newProducts; ?> updated this month</span>
                    </div>
                </div>
                
                <div class="overview-card">
                    <div class="overview-header">
                        <h3 class="overview-title">Branches</h3>
                        <div class="overview-icon branches-icon">
                            <i class="fas fa-store"></i>
                        </div>
                    </div>
                    <div class="overview-value">
    <?php echo number_format($totalBranches); ?>
</div>
                    <div class="overview-trend">
                        <i class="fas fa-arrow-up trend-up"></i>
                        <span>
    +<?php echo $updatedBranches; ?> updated this month
</span>
                    </div>
                </div>
                
                <div class="overview-card">
                    <div class="overview-header">
                        <h3 class="overview-title">Sales Summary</h3>
                        <div class="overview-icon sales-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="overview-value">
    RM <?php echo number_format($totalSales, 2); ?>
</div>
                    <div class="overview-trend">
                        <i class="fas <?php echo $salesTrendDown ? 'fa-arrow-down trend-down' : 'fa-arrow-up trend-up'; ?>"></i>
                        <span>
    <?php echo $salesTrend; ?>% from last month
</span>
                    </div>
                </div>
            </section>

            <!-- Needs Your Attention Section -->
            <section>
                <h2 class="section-title">
                    <i class="fas fa-bell"></i> Needs Your Attention
                </h2>
                <div class="attention-section">
                    <a href="s_payments.php?status=PENDING" class="attention-card <?= $pendingPayments > 0 ? 'has-alert' : '' ?>">
                        <div class="attention-icon"><i class="fas fa-hourglass-half"></i></div>
                        <div>
                            <div class="attention-value"><?= number_format($pendingPayments) ?></div>
                            <div class="attention-label">Pending Payments</div>
                            <div class="attention-sub">awaiting verification</div>
                        </div>
                    </a>

                    <a href="s_upload_review.php?status=RECEIVED" class="attention-card <?= $pendingPrintFiles > 0 ? 'has-alert' : '' ?>">
                        <div class="attention-icon"><i class="fas fa-print"></i></div>
                        <div>
                            <div class="attention-value"><?= number_format($pendingPrintFiles) ?></div>
                            <div class="attention-label">Print Files to Review</div>
                            <div class="attention-sub">awaiting review</div>
                        </div>
                    </a>

                    <a href="a_productmanagement.php" class="attention-card <?= $pendingRestock > 0 ? 'has-alert' : '' ?>">
                        <div class="attention-icon"><i class="fas fa-dolly"></i></div>
                        <div>
                            <div class="attention-value"><?= number_format($pendingRestock) ?></div>
                            <div class="attention-label">Restock Requests</div>
                            <div class="attention-sub">pending approval</div>
                        </div>
                    </a>

                    <a href="s_inv.php" class="attention-card <?= ($lowStock + $outOfStock) > 0 ? 'has-alert' : '' ?>">
                        <div class="attention-icon"><i class="fas fa-exclamation-triangle"></i></div>
                        <div>
                            <div class="attention-value"><?= number_format($lowStock + $outOfStock) ?></div>
                            <div class="attention-label">Stock Alerts</div>
                            <div class="attention-sub"><?= $outOfStock ?> out of stock</div>
                        </div>
                    </a>
                </div>
            </section>

            <!-- Navigation Cards Section -->
            <section class="admin-navigation-section">
                <h2 class="section-title">
                    <i class="fas fa-cogs"></i> Management Controls
                </h2>
                
                <div class="navigation-grid">
                    <a href="a_usermanagement.php" class="admin-nav-card">
                        <div class="admin-nav-icon nav-manage-users">
                            <i class="fas fa-users-cog"></i>
                        </div>
                        <h3 class="admin-nav-title">Manage Users</h3>
                        <p class="admin-nav-description">
                            Add, edit, or remove user accounts. Manage permissions and access levels.
                        </p>
</a>
                    
                    <a href="a_productmanagement.php" class="admin-nav-card">
                        <div class="admin-nav-icon nav-manage-products">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <h3 class="admin-nav-title">Manage Products</h3>
                        <p class="admin-nav-description">
                            Update product catalog, pricing, categories, and inventory settings.
                        </p>
                    </a>
                    
                    <a href="a_branch.php" class="admin-nav-card">
                        <div class="admin-nav-icon nav-manage-branches">
                            <i class="fas fa-store"></i>
                        </div>
                        <h3 class="admin-nav-title">Manage Branches</h3>
                        <p class="admin-nav-description">
                            Configure branch locations, staff assignments, and regional settings.
                        </p>
                    </a>
                    
                    <a href="a_report.php" class="admin-nav-card">
                        <div class="admin-nav-icon nav-sales-report">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <h3 class="admin-nav-title">Sales Report</h3>
                        <p class="admin-nav-description">
                            View detailed sales analytics, generate reports, and export data.
                        </p>
                    </a>
                </div>
            </section>
        </div>
    </main>
    
    <script>
        // Navigation interactions
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                document.querySelectorAll('.nav-link').forEach(item => {
                    item.classList.remove('active');
                });
                this.classList.add('active');
            });
        });
    </script>
</body>
</html>