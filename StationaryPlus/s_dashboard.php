<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus - Staff Dashboard</title>
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
            --sidebar-width: 260px;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            --alert-bg: rgba(244, 162, 97, 0.1);
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
            overflow: hidden;
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
            padding: 25px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
        }
        
        .logo-icon {
            background-color: var(--primary);
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            color: white;
            font-size: 20px;
        }
        
        .logo-text {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
        }
        
        .nav-section {
            padding: 20px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .nav-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--light-text);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0 25px 10px 25px;
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
            padding: 14px 25px;
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
            width: 20px;
            text-align: center;
            margin-right: 15px;
            font-size: 16px;
        }
        
        .nav-text {
            font-size: 15px;
        }
        
        .user-section {
            margin-top: auto;
            padding: 20px;
            border-top: 1px solid var(--border);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(168, 53, 53, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-weight: 600;
            font-size: 16px;
            margin-right: 12px;
        }
        
        .user-details {
            flex-grow: 1;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 15px;
            color: var(--text);
            margin-bottom: 2px;
        }
        
        .user-role {
            font-size: 13px;
            color: var(--light-text);
        }
        
        .logout-btn {
            width: 100%;
            padding: 10px;
            background-color: rgba(168, 53, 53, 0.1);
            color: var(--primary);
            border: 1.5px solid var(--primary);
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .logout-btn:hover {
            background-color: rgba(168, 53, 53, 0.2);
        }
        
        /* Main Content Area */
        .main-content {
            flex-grow: 1;
            margin-left: var(--sidebar-width);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .top-header {
            background-color: var(--white);
            padding: 18px 30px;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }
        
        .page-title {
            font-size: 24px;
            color: var(--text);
            margin-bottom: 4px;
        }
        
        .page-subtitle {
            font-size: 14px;
            color: var(--light-text);
        }
        
        /* Dashboard Content - Grid Layout */
        .dashboard-grid {
            flex-grow: 1;
            padding: 25px;
            display: grid;
            grid-template-columns: 1fr;
            grid-template-rows: auto auto 1fr;
            gap: 25px;
            height: calc(100vh - 110px); /* Adjust based on header height */
            overflow: hidden;
        }
        
        /* Summary Cards - Compact */
        .summary-section {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            flex-shrink: 0;
        }
        
        .summary-card {
            background-color: var(--white);
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
        }
        
        .summary-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .summary-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text);
        }
        
        .summary-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        .orders-icon {
            background-color: rgba(168, 53, 53, 0.1);
            color: var(--primary);
        }
        
        .alerts-icon {
            background-color: var(--alert-bg);
            color: var(--secondary);
        }
        
        .updates-icon {
            background-color: rgba(0, 0, 0, 0.05);
            color: var(--light-text);
        }
        
        .summary-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 6px;
        }
        
        .summary-subtitle {
            font-size: 13px;
            color: var(--light-text);
            margin-bottom: 10px;
        }
        
        .summary-footer {
            margin-top: auto;
            font-size: 12px;
            color: var(--light-text);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .summary-footer i {
            font-size: 11px;
        }
        
        /* Navigation Cards - Compact */
        .navigation-section {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            flex-shrink: 0;
        }
        
        .nav-card {
            background-color: var(--white);
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .nav-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.08);
            border-color: var(--primary);
        }
        
        .nav-icon-card {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 22px;
            color: var(--white);
        }
        
        .nav-orders {
            background-color: var(--primary);
        }
        
        .nav-update {
            background-color: #4A6FA5; /* Blue shade */
        }
        
        .nav-inventory {
            background-color: #4CAF50; /* Green shade */
        }
        
        .nav-alerts {
            background-color: var(--secondary);
        }
        
        .nav-title-card {
            font-size: 16px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
        }
        
        .nav-description {
            font-size: 13px;
            color: var(--light-text);
            line-height: 1.4;
        }
        
        /* Recent Orders Table - Compact */
        .orders-section {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            flex-grow: 1;
        }
        
        .orders-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            background-color: rgba(168, 53, 53, 0.03);
            flex-shrink: 0;
        }
        
        .orders-title {
            font-size: 18px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .orders-title i {
            font-size: 18px;
        }
        
        .table-container {
            flex-grow: 1;
            overflow: hidden;
            padding: 0;
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            height: 100%;
        }
        
        .orders-table thead {
            background-color: rgba(168, 53, 53, 0.03);
            position: sticky;
            top: 0;
        }
        
        .orders-table th {
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            color: var(--text);
            font-size: 14px;
            border-bottom: 1px solid var(--border);
        }
        
        .orders-table td {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
            color: var(--text);
        }
        
        .orders-table tr:last-child td {
            border-bottom: none;
        }
        
        .orders-table tr:hover {
            background-color: rgba(168, 53, 53, 0.02);
        }
        
        .order-id {
            font-weight: 600;
            color: var(--primary);
            font-size: 13px;
        }
        
        .customer-name {
            font-weight: 500;
        }
        
        .order-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-pending {
            background-color: rgba(244, 162, 97, 0.2);
            color: var(--secondary);
        }
        
        .status-processing {
            background-color: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }
        
        .status-ready {
            background-color: rgba(33, 150, 243, 0.1);
            color: #2196F3;
        }
        
        .order-total {
            font-weight: 600;
            font-size: 14px;
        }
        
        .view-btn {
            padding: 6px 12px;
            background-color: rgba(168, 53, 53, 0.1);
            color: var(--primary);
            border: 1px solid var(--primary);
            border-radius: 5px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .view-btn:hover {
            background-color: rgba(168, 53, 53, 0.2);
        }
        
        /* No Scrollbar Design */
        .table-container::-webkit-scrollbar {
            width: 6px;
        }
        
        .table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .table-container::-webkit-scrollbar-thumb {
            background: rgba(168, 53, 53, 0.3);
            border-radius: 3px;
        }
        
        .table-container::-webkit-scrollbar-thumb:hover {
            background: rgba(168, 53, 53, 0.5);
        }
        
        /* Responsive adjustments for documentation */
        @media (max-width: 1200px) {
            .summary-section, .navigation-section {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            :root {
                --sidebar-width: 70px;
            }
            
            .logo-text, .nav-text, .user-details, .nav-title, .nav-description {
                display: none;
            }
            
            .logo-area, .nav-section, .user-section {
                padding: 15px;
            }
            
            .nav-link {
                justify-content: center;
                padding: 15px;
                border-left: none;
                border-right: 4px solid transparent;
            }
            
            .nav-link:hover, .nav-link.active {
                border-left: none;
                border-right-color: var(--primary);
            }
            
            .nav-icon {
                margin-right: 0;
                font-size: 18px;
            }
            
            .logout-btn span {
                display: none;
            }
            
            .logout-btn {
                justify-content: center;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar Navigation -->
    <nav class="sidebar">
        <div class="logo-area">
            <div class="logo-icon">
                <i class="fas fa-pen-nib"></i>
            </div>
            <div class="logo-text">StationaryPlus</div>
        </div>
        
        <div class="nav-section">
            <div class="nav-title">Staff Dashboard</div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="#" class="nav-link active">
                        <div class="nav-icon">
                            <i class="fas fa-tachometer-alt"></i>
                        </div>
                        <div class="nav-text">Dashboard</div>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="nav-text">Manage Orders</div>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <div class="nav-text">Inventory</div>
                    </a>
                </li>
            </ul>
        </div>
        
        
        <div class="user-section">
            <div class="user-info">
                <div class="user-avatar">JS</div>
                <div class="user-details">
                    <div class="user-name">Jamie Smith</div>
                    <div class="user-role">Inventory Manager</div>
                </div>
            </div>
            <button class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </button>
        </div>
    </nav>
    
    <!-- Main Content Area -->
    <main class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <h1 class="page-title">Staff Dashboard</h1>
            <p class="page-subtitle">Manage orders, inventory, and monitor system alerts</p>
        </header>
        
        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Summary Cards -->
            <section class="summary-section">
                <div class="summary-card">
                    <div class="summary-header">
                        <h3 class="summary-title">Orders to Process</h3>
                        <div class="summary-icon orders-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                    </div>
                    <div class="summary-value">24</div>
                    <p class="summary-subtitle">Awaiting processing</p>
                    <div class="summary-footer">
                        <i class="fas fa-clock"></i> 8 new today
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-header">
                        <h3 class="summary-title">Low Stock Alerts</h3>
                        <div class="summary-icon alerts-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="summary-value">12</div>
                    <p class="summary-subtitle">Items below minimum</p>
                    <div class="summary-footer">
                        <i class="fas fa-arrow-down"></i> 3 critical
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-header">
                        <h3 class="summary-title">Recent Updates</h3>
                        <div class="summary-icon updates-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                    </div>
                    <div class="summary-value">7</div>
                    <p class="summary-subtitle">New notifications</p>
                    <div class="summary-footer">
                        <i class="fas fa-info-circle"></i> Updated 2h ago
                    </div>
                </div>
            </section>
            
            <!-- Navigation Cards -->
            <section class="navigation-section">
                <div class="nav-card">
                    <div class="nav-icon-card nav-orders">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <h3 class="nav-title-card">Manage Orders</h3>
                    <p class="nav-description">View, process, and fulfill customer orders</p>
                </div>
                
                <div class="nav-card">
                    <div class="nav-icon-card nav-update">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <h3 class="nav-title-card">Update Order Status</h3>
                    <p class="nav-description">Track and update order progress</p>
                </div>
                
                <div class="nav-card">
                    <div class="nav-icon-card nav-inventory">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <h3 class="nav-title-card">Manage Inventory</h3>
                    <p class="nav-description">Stock levels, reordering, and tracking</p>
                </div>
                
                <div class="nav-card">
                    <div class="nav-icon-card nav-alerts">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <h3 class="nav-title-card">Low Stock Alerts</h3>
                    <p class="nav-description">Review and manage stock warnings</p>
                </div>
            </section>
            
            <!-- Recent Orders Table -->
            <section class="orders-section">
                <div class="orders-header">
                    <h2 class="orders-title">
                        <i class="fas fa-history"></i> Recent Orders
                    </h2>
                </div>
                
                <div class="table-container">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="order-id">#SP-2023-156</td>
                                <td class="customer-name">Ahmad Faris</td>
                                <td>Nov 15</td>
                                <td><span class="order-status status-pending">Pending</span></td>
                                <td>3 items</td>
                                <td class="order-total">RM121.50</td>
                                <td><button class="view-btn">Process</button></td>
                            </tr>
                            <tr>
                                <td class="order-id">#SP-2023-155</td>
                                <td class="customer-name">Sarah Johnson</td>
                                <td>Nov 14</td>
                                <td><span class="order-status status-processing">Processing</span></td>
                                <td>5 items</td>
                                <td class="order-total">RM89.90</td>
                                <td><button class="view-btn">View</button></td>
                            </tr>
                            <tr>
                                <td class="order-id">#SP-2023-154</td>
                                <td class="customer-name">Global Solutions</td>
                                <td>Nov 14</td>
                                <td><span class="order-status status-ready">Ready</span></td>
                                <td>12 items</td>
                                <td class="order-total">RM450.25</td>
                                <td><button class="view-btn">Complete</button></td>
                            </tr>
                            <tr>
                                <td class="order-id">#SP-2023-153</td>
                                <td class="customer-name">Michael Chen</td>
                                <td>Nov 13</td>
                                <td><span class="order-status status-pending">Pending</span></td>
                                <td>2 items</td>
                                <td class="order-total">RM34.50</td>
                                <td><button class="view-btn">Process</button></td>
                            </tr>
                        </tbody>
                    </table>
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
                e.preventDefault();
            });
        });
        
        // Navigation card interactions
        document.querySelectorAll('.nav-card').forEach(card => {
            card.addEventListener('click', function() {
                const title = this.querySelector('.nav-title-card').textContent;
                alert(`This would navigate to: ${title} page (UI mockup only)`);
            });
        });
        
        // View button interactions
        document.querySelectorAll('.view-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation();
                const orderId = this.closest('tr').querySelector('.order-id').textContent;
                alert(`This would show details for: ${orderId} (UI mockup only)`);
            });
        });
        
        // Logout button
        document.querySelector('.logout-btn').addEventListener('click', function() {
            alert('Logout functionality would be implemented here (UI mockup only)');
        });
        
        // Summary card interactions
        document.querySelectorAll('.summary-card').forEach(card => {
            card.addEventListener('click', function() {
                const title = this.querySelector('.summary-title').textContent;
                alert(`This would show more details for: ${title} (UI mockup only)`);
            });
        });
        
        // Keep the sidebar active when clicking on content
        document.querySelector('.main-content').addEventListener('click', function() {
            // Keep the dashboard link active
            document.querySelectorAll('.nav-link').forEach(item => {
                if (item.querySelector('.nav-text').textContent === 'Dashboard') {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>