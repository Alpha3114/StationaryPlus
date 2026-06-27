<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'auth.php';
require_role(['STAFF', 'ADMIN']);

require_once 'db.php';

$userName = $_SESSION['user_name'];

// ========================================
// DASHBOARD STATISTICS
// ========================================

// TOTAL USERS
$totalUsers = 0;

$userQuery = mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM users
");

if ($userQuery) {
    $userData = mysqli_fetch_assoc($userQuery);
    $totalUsers = $userData['total'];
}

// NEW USERS THIS MONTH
$newUsers = 0;

$newUsersQuery = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM users
    WHERE MONTH(registration_date) = MONTH(CURRENT_DATE())
    AND YEAR(registration_date) = YEAR(CURRENT_DATE())
");

if ($newUsersQuery) {
    $newUsersData = mysqli_fetch_assoc($newUsersQuery);
    $newUsers = $newUsersData['total'];
}

// ========================================
// TOTAL PRODUCTS
// ========================================

$totalProducts = 0;

$productQuery = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM products
    WHERE product_status = 'ACTIVE'
");

if ($productQuery) {
    $productData = mysqli_fetch_assoc($productQuery);
    $totalProducts = $productData['total'];
}

// PRODUCTS UPDATED THIS MONTH
$newProducts = 0;

$newProductsQuery = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM products
    WHERE MONTH(last_updated) = MONTH(CURRENT_DATE())
    AND YEAR(last_updated) = YEAR(CURRENT_DATE())
");

if ($newProductsQuery) {
    $newProductsData = mysqli_fetch_assoc($newProductsQuery);
    $newProducts = $newProductsData['total'];
}

// ========================================
// TOTAL BRANCHES
// ========================================

$totalBranches = 0;

$branchQuery = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM branches
    WHERE status = 'ACTIVE'
");

if ($branchQuery) {
    $branchData = mysqli_fetch_assoc($branchQuery);
    $totalBranches = $branchData['total'];
}
// ========================================
// BRANCHES UPDATED THIS MONTH
// ========================================

$updatedBranches = 0;

$updatedBranchesQuery = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM branches
    WHERE MONTH(last_updated) = MONTH(CURRENT_DATE())
    AND YEAR(last_updated) = YEAR(CURRENT_DATE())
");

if ($updatedBranchesQuery) {
    $updatedBranchesData = mysqli_fetch_assoc($updatedBranchesQuery);
    $updatedBranches = $updatedBranchesData['total'];
}
// ========================================
// TOTAL SALES
// ONLY VALID PAYMENTS
// ========================================

$totalSales = 0;

$salesQuery = mysqli_query($conn, "
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM payments
    WHERE verification_status = 'VALID'
");

if ($salesQuery) {
    $salesData = mysqli_fetch_assoc($salesQuery);
    $totalSales = $salesData['total'];
}

// ========================================
// SALES LAST MONTH
// ========================================

$lastMonthSales = 0;

$lastMonthQuery = mysqli_query($conn, "
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM payments
    WHERE verification_status = 'VALID'
    AND MONTH(record_date) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH)
    AND YEAR(record_date) = YEAR(CURRENT_DATE - INTERVAL 1 MONTH)
");

if ($lastMonthQuery) {
    $lastMonthData = mysqli_fetch_assoc($lastMonthQuery);
    $lastMonthSales = $lastMonthData['total'];
}

// ========================================
// SALES TREND PERCENTAGE
// ========================================

$salesTrend = 0;

if ($lastMonthSales > 0) {
    $salesTrend = (($totalSales - $lastMonthSales) / $lastMonthSales) * 100;
}

$salesTrend = number_format($salesTrend, 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus - Administrator Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #A83535;      /* Brick Red */
            --secondary: #F4A261;    /* Muted Orange */
            --background: #FAFAFA;   /* Light Grey */
            --text: #2E2E2E;         /* Dark Charcoal */
            --light-text: #707070;   /* Secondary Text */
            --border: #E0E0E0;       /* Border Grey */
            --white: #FFFFFF;
            --sidebar-width: 280px;
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
            color: var(--text);
            min-height: 100vh;
            display: flex;
        }
        
        /* Sidebar Navigation */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--white);
            border-right: 1px solid var(--border);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            display: flex;
            flex-direction: column;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.03);
        }
        
        .logo-area {
            padding: 22px;
            border-bottom: 1px solid var(--border);
            background-color: rgba(168, 53, 53, 0.03);
            display: flex;
            align-items: center;
        }
        
        .logo-icon {
            background-color: var(--primary);
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 22px;
        }
        
        .logo-text {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary);
        }
        
        .admin-subtitle {
            font-size: 13px;
            color: var(--light-text);
            margin-top: 3px;
        }
        
        .nav-section {
            padding: 25px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .nav-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--light-text);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0 22px 10px 22px;
        }
        
        .nav-menu {
            list-style: none;
        }
        
        .nav-item {
            margin-bottom: 2px;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 14px 22px;
            color: var(--text);
            text-decoration: none;
            transition: all 0.2s ease;
            border-left: 4px solid transparent;
        }
        
        .nav-link:hover {
            background-color: rgba(168, 53, 53, 0.05);
            color: var(--primary);
            border-left-color: rgba(168, 53, 53, 0.3);
        }
        
        .nav-link.active {
            background-color: rgba(168, 53, 53, 0.08);
            color: var(--primary);
            border-left-color: var(--primary);
            font-weight: 600;
        }
        
        .nav-icon {
            width: 22px;
            text-align: center;
            margin-right: 16px;
            font-size: 18px;
        }
        
        .nav-text {
            font-size: 16px;
        }
        
        .user-section {
            margin-top: auto;
            padding: 25px;
            border-top: 1px solid var(--border);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: rgba(168, 53, 53, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-weight: 700;
            font-size: 20px;
            margin-right: 15px;
        }
        
        .user-details {
            flex-grow: 1;
        }
        
        .user-name {
            font-weight: 700;
            font-size: 17px;
            color: var(--text);
            margin-bottom: 3px;
        }
        
        .user-role {
            font-size: 14px;
            color: var(--primary);
            font-weight: 600;
        }
        
        .logout-link {
             width: 100%;
    padding: 9px;
    background: rgba(168,53,53,0.1);
    color: var(--primary);
    border: 1.5px solid var(--primary);
    border-radius: 5px;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    text-decoration: none;
    transition: all 0.2s;
        }
        
        .logout-link:hover {
            background-color: rgba(168, 53, 53, 0.2);
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
            color: var(--text);
            margin-bottom: 6px;
            font-weight: 700;
        }
        
        .admin-header-left p {
            font-size: 15px;
            color: var(--light-text);
        }
        
        .admin-header-right {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        
        .admin-date {
            font-size: 15px;
            color: var(--light-text);
            font-weight: 500;
        }
        
        .admin-notifications {
            position: relative;
            cursor: pointer;
        }
        
        .notification-icon {
            font-size: 22px;
            color: var(--text);
            transition: color 0.2s ease;
        }
        
        .notification-icon:hover {
            color: var(--primary);
        }
        
        .notification-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background-color: var(--primary);
            color: white;
            font-size: 11px;
            font-weight: 700;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
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
            color: var(--text);
        }
        
        .overview-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
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
            color: var(--light-text);
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: auto;
        }
        
        .trend-up {
            color: #4CAF50;
        }
        
        .trend-down {
            color: var(--primary);
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
            color: white;
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
            color: var(--text);
            margin-bottom: 12px;
        }
        
        .admin-nav-description {
            font-size: 15px;
            color: var(--light-text);
            line-height: 1.5;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1400px) {
            .overview-section, .navigation-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 1024px) {
            :root {
                --sidebar-width: 70px;
            }
            
            .logo-text, .admin-subtitle, .nav-text, .user-details, .nav-title, .admin-nav-description {
                display: none;
            }
            
            .logo-area, .nav-section, .user-section {
                padding: 20px;
            }
            
            .logo-area {
                justify-content: center;
            }
            
            .nav-link {
                justify-content: center;
                padding: 20px;
                border-left: none;
                border-right: 4px solid transparent;
            }
            
            .nav-link:hover, .nav-link.active {
                border-left: none;
                border-right-color: var(--primary);
            }
            
            .nav-icon {
                margin-right: 0;
                font-size: 20px;
            }
            
            .logout-link span {
                display: none;
            }
            
            .logout-link {
                justify-content: center;
                padding: 12px;
            }
            
            .header-left h1 {
                font-size: 24px;
            }
        }
        
        @media (max-width: 768px) {
            .overview-section, .navigation-grid {
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
                <div class="admin-notifications">
                    <i class="fas fa-bell notification-icon"></i>
                    <div class="notification-badge">5</div>
                </div>
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
                        <i class="fas fa-arrow-up trend-up"></i>
                        <span>
    <?php echo $salesTrend; ?>% from last month
</span>
                    </div>
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
        
        // Update current date
        function updateCurrentDate() {
            const now = new Date();
            const options = { year: 'numeric', month: 'long', day: 'numeric' };
            const dateString = now.toLocaleDateString('en-US', options);
            document.querySelector('.admin-date').textContent = dateString;
        }
        
        // Initialize date
        updateCurrentDate();
    </script>
</body>
</html>