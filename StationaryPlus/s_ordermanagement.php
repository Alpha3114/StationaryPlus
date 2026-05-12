<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus - Order Management</title>
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
        }
        
        .top-header {
            background-color: var(--white);
            padding: 18px 30px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-left h1 {
            font-size: 24px;
            color: var(--text);
            margin-bottom: 4px;
        }
        
        .header-left p {
            font-size: 14px;
            color: var(--light-text);
        }
        
        .header-right {
            display: flex;
            gap: 15px;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-input {
            padding: 10px 15px 10px 40px;
            border: 1px solid var(--border);
            border-radius: 6px;
            width: 250px;
            font-size: 14px;
        }
        
        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--light-text);
        }
        
        .filter-btn {
            padding: 10px 20px;
            background-color: var(--white);
            border: 1px solid var(--border);
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            color: var(--text);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-btn:hover {
            background-color: rgba(168, 53, 53, 0.05);
            border-color: var(--primary);
        }
        
        /* Order Management Content */
        .order-management {
            display: flex;
            height: calc(100vh - 110px);
            overflow: hidden;
        }
        
        /* Orders Table Section */
        .orders-table-section {
            flex: 1;
            padding: 25px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .table-header h2 {
            font-size: 20px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .table-header h2 i {
            font-size: 20px;
        }
        
        .total-orders {
            font-size: 15px;
            color: var(--light-text);
            background-color: rgba(168, 53, 53, 0.05);
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .table-container {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            flex-grow: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            flex-grow: 1;
        }
        
        .orders-table thead {
            background-color: rgba(168, 53, 53, 0.03);
            position: sticky;
            top: 0;
        }
        
        .orders-table th {
            padding: 18px 20px;
            text-align: left;
            font-weight: 600;
            color: var(--text);
            font-size: 14px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        
        .orders-table td {
            padding: 18px 20px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
            color: var(--text);
        }
        
        .orders-table tbody tr:hover {
            background-color: rgba(168, 53, 53, 0.02);
            cursor: pointer;
        }
        
        .orders-table tbody tr.selected {
            background-color: rgba(168, 53, 53, 0.05);
            border-left: 3px solid var(--primary);
        }
        
        .order-id {
            font-weight: 600;
            color: var(--primary);
            font-size: 14px;
        }
        
        .customer-name {
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .customer-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: rgba(168, 53, 53, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-weight: 600;
            font-size: 13px;
        }
        
        .order-date {
            color: var(--light-text);
        }
        
        .order-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
            min-width: 100px;
        }
        
        .status-pending {
            background-color: rgba(244, 162, 97, 0.15);
            color: var(--secondary);
        }
        
        .status-processing {
            background-color: rgba(33, 150, 243, 0.15);
            color: #2196F3;
        }
        
        .status-ready {
            background-color: rgba(76, 175, 80, 0.15);
            color: #4CAF50;
        }
        
        .status-completed {
            background-color: rgba(158, 158, 158, 0.15);
            color: #757575;
        }
        
        .status-cancelled {
            background-color: rgba(244, 67, 54, 0.15);
            color: #F44336;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
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
            white-space: nowrap;
        }
        
        .view-btn:hover {
            background-color: rgba(168, 53, 53, 0.2);
        }
        
        /* Order Details Panel */
        .order-details-panel {
            width: 380px;
            background-color: var(--white);
            border-left: 1px solid var(--border);
            padding: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .panel-header {
            padding: 25px;
            border-bottom: 1px solid var(--border);
            background-color: rgba(168, 53, 53, 0.03);
        }
        
        .panel-header h3 {
            font-size: 18px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .panel-header h3 i {
            font-size: 18px;
        }
        
        .no-selection {
            padding: 40px 25px;
            text-align: center;
            color: var(--light-text);
        }
        
        .no-selection i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .no-selection p {
            font-size: 15px;
        }
        
        .order-details-content {
            flex-grow: 1;
            overflow-y: auto;
            padding: 25px;
            display: none;
        }
        
        .order-details-content.active {
            display: block;
        }
        
        .order-info-section {
            margin-bottom: 30px;
        }
        
        .order-info-header {
            font-size: 16px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .order-info-header i {
            color: var(--primary);
            font-size: 16px;
        }
        
        .order-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .info-item {
            margin-bottom: 12px;
        }
        
        .info-label {
            font-size: 13px;
            color: var(--light-text);
            margin-bottom: 4px;
        }
        
        .info-value {
            font-size: 15px;
            font-weight: 600;
            color: var(--text);
        }
        
        .order-items {
            margin-top: 25px;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .item-name {
            font-weight: 500;
            font-size: 14px;
        }
        
        .item-qty {
            color: var(--light-text);
            font-size: 14px;
        }
        
        .item-price {
            font-weight: 600;
            font-size: 14px;
        }
        
        .order-total {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid var(--border);
            display: flex;
            justify-content: space-between;
            font-weight: 600;
            font-size: 16px;
        }
        
        .total-amount {
            color: var(--primary);
            font-size: 18px;
        }
        
        /* Status Update Section */
        .status-update-section {
            background-color: rgba(168, 53, 53, 0.02);
            border-radius: 10px;
            padding: 20px;
            margin-top: 25px;
            border: 1px solid var(--border);
        }
        
        .status-update-section h4 {
            font-size: 16px;
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .status-update-section h4 i {
            font-size: 16px;
        }
        
        .status-select {
            width: 100%;
            padding: 12px 15px;
            border: 1.5px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 15px;
            background-color: var(--white);
            color: var(--text);
        }
        
        .status-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(168, 53, 53, 0.1);
        }
        
        .update-btn {
            width: 100%;
            padding: 12px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: background-color 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .update-btn:hover {
            background-color: #8b2a2a;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .order-details-panel {
                width: 350px;
            }
        }
        
        @media (max-width: 768px) {
            :root {
                --sidebar-width: 70px;
            }
            
            .logo-text, .nav-text, .user-details, .nav-title {
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
            
            .search-input {
                width: 200px;
            }
        }
        
        /* Scrollbar styling */
        .order-details-content::-webkit-scrollbar {
            width: 6px;
        }
        
        .order-details-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .order-details-content::-webkit-scrollbar-thumb {
            background: rgba(168, 53, 53, 0.3);
            border-radius: 3px;
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
                    <a href="#" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-tachometer-alt"></i>
                        </div>
                        <div class="nav-text">Dashboard</div>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link active">
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
                    <div class="user-role">Order Manager</div>
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
            <div class="header-left">
                <h1>Order Management</h1>
                <p>Review, process, and update customer orders</p>
            </div>
            <div class="header-right">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" class="search-input" placeholder="Search orders...">
                </div>
                <button class="filter-btn">
                    <i class="fas fa-filter"></i> Filter
                </button>
            </div>
        </header>
        
        <!-- Order Management Content -->
        <div class="order-management">
            <!-- Orders Table Section -->
            <section class="orders-table-section">
                <div class="table-header">
                    <h2><i class="fas fa-clipboard-list"></i> Recent Orders</h2>
                    <div class="total-orders">24 Orders</div>
                </div>
                
                <div class="table-container">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Current Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="selected">
                                <td class="order-id">#SP-2025-156</td>
                                <td class="customer-name">
                                    <div class="customer-avatar">AF</div>
                                    Ahmad Faris
                                </td>
                                <td class="order-date">2025-11-15</td>
                                <td><span class="order-status status-pending">Pending</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="view-btn">View Details</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="order-id">#SP-2025-155</td>
                                <td class="customer-name">
                                    <div class="customer-avatar">SJ</div>
                                    Sarah Johnson
                                </td>
                                <td class="order-date">2025-11-14</td>
                                <td><span class="order-status status-processing">Processing</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="view-btn">View Details</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="order-id">#SP-2025-154</td>
                                <td class="customer-name">
                                    <div class="customer-avatar">GS</div>
                                    Global Solutions
                                </td>
                                <td class="order-date">2025-11-14</td>
                                <td><span class="order-status status-ready">Ready</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="view-btn">View Details</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="order-id">#SP-2025-153</td>
                                <td class="customer-name">
                                    <div class="customer-avatar">MC</div>
                                    Michael Chen
                                </td>
                                <td class="order-date">2025-11-13</td>
                                <td><span class="order-status status-pending">Pending</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="view-btn">View Details</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="order-id">#SP-2025-152</td>
                                <td class="customer-name">
                                    <div class="customer-avatar">UP</div>
                                    University Print Lab
                                </td>
                                <td class="order-date">2025-11-13</td>
                                <td><span class="order-status status-processing">Processing</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="view-btn">View Details</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="order-id">#SP-2025-151</td>
                                <td class="customer-name">
                                    <div class="customer-avatar">DC</div>
                                    Design Co.
                                </td>
                                <td class="order-date">2025-11-12</td>
                                <td><span class="order-status status-completed">Completed</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="view-btn">View Details</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="order-id">#SP-2025-150</td>
                                <td class="customer-name">
                                    <div class="customer-avatar">RK</div>
                                    Robert Kim
                                </td>
                                <td class="order-date">2025-11-12</td>
                                <td><span class="order-status status-cancelled">Cancelled</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="view-btn">View Details</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            
            <!-- Order Details Panel -->
            <section class="order-details-panel">
                <div class="panel-header">
                    <h3><i class="fas fa-info-circle"></i> Order Details</h3>
                </div>
                
                <!-- No Selection State -->
                <div class="no-selection" id="noSelection">
                    <i class="fas fa-clipboard-check"></i>
                    <p>Select an order to view details</p>
                </div>
                
                <!-- Order Details Content -->
                <div class="order-details-content active" id="orderDetails">
                    <!-- Order Information -->
                    <div class="order-info-section">
                        <h4 class="order-info-header">
                            <i class="fas fa-file-alt"></i> Order Information
                        </h4>
                        <div class="order-info-grid">
                            <div class="info-item">
                                <div class="info-label">Order ID</div>
                                <div class="info-value">#SP-2025-156</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Date & Time</div>
                                <div class="info-value">2025-11-15, 14:30</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Customer</div>
                                <div class="info-value">Ahmad Faris</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Contact</div>
                                <div class="info-value">ahmad@email.com</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Payment Method</div>
                                <div class="info-value">Credit Card</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Payment Status</div>
                                <div class="info-value" style="color: #4CAF50;">Paid</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Items -->
                    <div class="order-info-section">
                        <h4 class="order-info-header">
                            <i class="fas fa-box-open"></i> Order Items
                        </h4>
                        <div class="order-items">
                            <div class="order-item">
                                <div>
                                    <div class="item-name">A4 Paper Trim (80gsm)</div>
                                    <div class="item-qty">Quantity: 2</div>
                                </div>
                                <div class="item-price">RM48.00</div>
                            </div>
                            <div class="order-item">
                                <div>
                                    <div class="item-name">Premium Color Printing (A4)</div>
                                    <div class="item-qty">Quantity: 50 pages</div>
                                </div>
                                <div class="item-price">RM60.00</div>
                            </div>
                            <div class="order-item">
                                <div>
                                    <div class="item-name">Document Binding (Spiral)</div>
                                    <div class="item-qty">Quantity: 1</div>
                                </div>
                                <div class="item-price">RM8.50</div>
                            </div>
                            <div class="order-item">
                                <div>
                                    <div class="item-name">Service Fee</div>
                                </div>
                                <div class="item-price">RM5.00</div>
                            </div>
                        </div>
                        <div class="order-total">
                            <span>Total Amount</span>
                            <span class="total-amount">RM121.50</span>
                        </div>
                    </div>
                    
                    <!-- Special Instructions -->
                    <div class="order-info-section">
                        <h4 class="order-info-header">
                            <i class="fas fa-edit"></i> Special Instructions
                        </h4>
                        <p style="font-size: 14px; color: var(--text); line-height: 1.5;">
                            Please print the color pages on glossy paper. Spiral binding with clear plastic cover.
                        </p>
                    </div>
                    
                    <!-- Status Update Section -->
                    <div class="status-update-section">
                        <h4><i class="fas fa-sync-alt"></i> Update Order Status</h4>
                        <select class="status-select">
                            <option value="pending" selected>Pending</option>
                            <option value="processing">Processing</option>
                            <option value="ready">Ready for Pickup</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        <button class="update-btn">
                            <i class="fas fa-check-circle"></i> Update Status
                        </button>
                    </div>
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
        
        // Table row selection
        document.querySelectorAll('.orders-table tbody tr').forEach(row => {
            row.addEventListener('click', function() {
                // Remove selected class from all rows
                document.querySelectorAll('.orders-table tbody tr').forEach(r => {
                    r.classList.remove('selected');
                });
                
                // Add selected class to clicked row
                this.classList.add('selected');
                
                // Show order details panel
                document.getElementById('noSelection').style.display = 'none';
                document.getElementById('orderDetails').classList.add('active');
            });
        });
        
        // View details button interactions
        document.querySelectorAll('.view-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation();
                const row = this.closest('tr');
                
                // Remove selected class from all rows
                document.querySelectorAll('.orders-table tbody tr').forEach(r => {
                    r.classList.remove('selected');
                });
                
                // Add selected class to clicked row
                row.classList.add('selected');
                
                // Show order details panel
                document.getElementById('noSelection').style.display = 'none';
                document.getElementById('orderDetails').classList.add('active');
            });
        });
        
        // Update status button
        document.querySelector('.update-btn').addEventListener('click', function() {
            const statusSelect = document.querySelector('.status-select');
            const selectedStatus = statusSelect.options[statusSelect.selectedIndex].text;
            
            alert(`Order status would be updated to: ${selectedStatus} (UI mockup only)`);
        });
        
        // Filter button
        document.querySelector('.filter-btn').addEventListener('click', function() {
            alert('Filter options would appear here (UI mockup only)');
        });
        
        // Search input
        const searchInput = document.querySelector('.search-input');
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                alert(`Searching for: "${this.value}" (UI mockup only)`);
            }
        });
        
        // Logout button
        document.querySelector('.logout-btn').addEventListener('click', function() {
            alert('Logout functionality would be implemented here (UI mockup only)');
        });
    </script>
</body>
</html>