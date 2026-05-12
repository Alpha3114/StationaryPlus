<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus - Inventory Monitoring</title>
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
            --warning-bg: rgba(168, 53, 53, 0.08);
            --warning-border: rgba(168, 53, 53, 0.2);
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
        
        /* Inventory Content */
        .inventory-content {
            flex-grow: 1;
            padding: 25px;
            display: flex;
            flex-direction: column;
            gap: 30px;
            overflow: hidden;
        }
        
        /* Inventory Table Section */
        .inventory-section {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            height: 50%;
            overflow: hidden;
        }
        
        .section-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--border);
            background-color: rgba(168, 53, 53, 0.03);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-header h2 {
            font-size: 20px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-header h2 i {
            font-size: 20px;
        }
        
        .section-stats {
            display: flex;
            gap: 20px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-label {
            font-size: 13px;
            color: var(--light-text);
        }
        
        .table-container {
            flex-grow: 1;
            overflow: auto;
        }
        
        .inventory-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .inventory-table thead {
            background-color: rgba(168, 53, 53, 0.03);
            position: sticky;
            top: 0;
        }
        
        .inventory-table th {
            padding: 18px 20px;
            text-align: left;
            font-weight: 600;
            color: var(--text);
            font-size: 14px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        
        .inventory-table td {
            padding: 18px 20px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
            color: var(--text);
        }
        
        .inventory-table tbody tr:hover {
            background-color: rgba(168, 53, 53, 0.02);
        }
        
        .product-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .product-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }
        
        .icon-paper {
            background-color: #4A6FA5; /* Blue */
        }
        
        .icon-ink {
            background-color: #9C27B0; /* Purple */
        }
        
        .icon-pen {
            background-color: #FF9800; /* Orange */
        }
        
        .icon-folder {
            background-color: #4CAF50; /* Green */
        }
        
        .icon-binding {
            background-color: #795548; /* Brown */
        }
        
        .product-name {
            font-weight: 600;
            font-size: 15px;
        }
        
        .product-sku {
            font-size: 13px;
            color: var(--light-text);
        }
        
        .branch-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .branch-icon {
            color: var(--primary);
            font-size: 16px;
        }
        
        .stock-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stock-bar {
            width: 100px;
            height: 8px;
            background-color: rgba(168, 53, 53, 0.1);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .stock-fill {
            height: 100%;
            border-radius: 4px;
        }
        
        .stock-high {
            background-color: #4CAF50; /* Green */
            width: 80%;
        }
        
        .stock-medium {
            background-color: #FF9800; /* Orange */
            width: 50%;
        }
        
        .stock-low {
            background-color: var(--secondary);
            width: 20%;
        }
        
        .stock-critical {
            background-color: var(--primary);
            width: 5%;
        }
        
        .stock-numbers {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .current-stock {
            font-weight: 600;
            font-size: 15px;
        }
        
        .minimum-stock {
            font-size: 13px;
            color: var(--light-text);
        }
        
        .update-action {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stock-input {
            width: 80px;
            padding: 8px 12px;
            border: 1.5px solid var(--border);
            border-radius: 5px;
            font-size: 14px;
            text-align: center;
        }
        
        .stock-input:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .update-btn {
            padding: 8px 16px;
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
        
        .update-btn:hover {
            background-color: rgba(168, 53, 53, 0.2);
        }
        
        /* Low Stock Alerts Section */
        .alerts-section {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--warning-border);
            display: flex;
            flex-direction: column;
            height: 50%;
            overflow: hidden;
            background-color: rgba(168, 53, 53, 0.01);
        }
        
        .alerts-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--warning-border);
            background-color: var(--warning-bg);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .alerts-header h2 {
            font-size: 20px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alerts-header h2 i {
            font-size: 20px;
            color: var(--primary);
        }
        
        .alert-count {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-badge {
            background-color: var(--primary);
            color: white;
            font-weight: 600;
            font-size: 14px;
            padding: 5px 12px;
            border-radius: 20px;
        }
        
        .alerts-container {
            flex-grow: 1;
            overflow: auto;
            padding: 0;
        }
        
        .alerts-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .alerts-table thead {
            background-color: rgba(244, 162, 97, 0.1);
            position: sticky;
            top: 0;
        }
        
        .alerts-table th {
            padding: 18px 20px;
            text-align: left;
            font-weight: 600;
            color: var(--text);
            font-size: 14px;
            border-bottom: 1px solid var(--warning-border);
            white-space: nowrap;
        }
        
        .alerts-table td {
            padding: 18px 20px;
            border-bottom: 1px solid var(--warning-border);
            font-size: 14px;
            color: var(--text);
        }
        
        .alerts-table tbody tr {
            background-color: rgba(244, 162, 97, 0.03);
        }
        
        .alerts-table tbody tr:hover {
            background-color: rgba(244, 162, 97, 0.08);
        }
        
        .alert-level {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
        }
        
        .alert-critical {
            background-color: rgba(168, 53, 53, 0.15);
            color: var(--primary);
        }
        
        .alert-warning {
            background-color: rgba(244, 162, 97, 0.2);
            color: var(--secondary);
        }
        
        .stock-difference {
            font-weight: 600;
            color: var(--primary);
        }
        
        .reorder-action {
            display: flex;
            gap: 10px;
        }
        
        .reorder-btn {
            padding: 8px 16px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .reorder-btn:hover {
            background-color: #8b2a2a;
        }
        
        .mark-seen-btn {
            padding: 8px 16px;
            background-color: transparent;
            color: var(--light-text);
            border: 1px solid var(--border);
            border-radius: 5px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .mark-seen-btn:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .inventory-content {
                gap: 20px;
            }
        }
        
        @media (max-width: 1024px) {
            .header-right {
                flex-direction: column;
            }
            
            .search-input {
                width: 200px;
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
                width: 180px;
            }
        }
        
        /* Scrollbar styling */
        .table-container::-webkit-scrollbar,
        .alerts-container::-webkit-scrollbar {
            width: 6px;
        }
        
        .table-container::-webkit-scrollbar-track,
        .alerts-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .table-container::-webkit-scrollbar-thumb,
        .alerts-container::-webkit-scrollbar-thumb {
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
                    <a href="#" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="nav-text">Manage Orders</div>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link active">
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
                <div class="user-avatar">LS</div>
                <div class="user-details">
                    <div class="user-name">Lisa Smith</div>
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
            <div class="header-left">
                <h1>Stock Management</h1>
                <p>Update stock quantities and view low stock alerts.</p>
            </div>
            <div class="header-right">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" class="search-input" placeholder="Search inventory...">
                </div>
            </div>
        </header>
        
        <!-- Inventory Content -->
        <div class="inventory-content">
            <!-- Section 1: Inventory Table -->
            <section class="inventory-section">
                <div class="section-header">
                    <h2><i class="fas fa-boxes"></i> Stock Overview</h2>
                    <div class="section-stats">
                        <div class="stat-item">
                            <div class="stat-value">142</div>
                            <div class="stat-label">Total Products</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">28</div>
                            <div class="stat-label">Categories</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">3</div>
                            <div class="stat-label">Branches</div>
                        </div>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Branch</th>
                                <th>Current Stock</th>
                                <th>Minimum Stock</th>
                                <th>Update Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div class="product-info">
                                        <div class="product-icon icon-paper">
                                            <i class="fas fa-file-alt"></i>
                                        </div>
                                        <div>
                                            <div class="product-name">A4 Paper (80gsm)</div>
                                            <div class="product-sku">SKU: PAP-A4-80</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="branch-info">
                                        <i class="fas fa-store branch-icon"></i>
                                        <span>Main Branch</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="stock-info">
                                        <div class="stock-bar">
                                            <div class="stock-fill stock-high"></div>
                                        </div>
                                        <div class="stock-numbers">
                                            <div class="current-stock">245 reams</div>
                                            <div class="minimum-stock">Min: 50 reams</div>
                                        </div>
                                    </div>
                                </td>
                                <td>50 reams</td>
                                <td>
                                    <div class="update-action">
                                        <input type="text" class="stock-input" value="245">
                                        <button class="update-btn">Update</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="product-info">
                                        <div class="product-icon icon-ink">
                                            <i class="fas fa-fill-drip"></i>
                                        </div>
                                        <div>
                                            <div class="product-name">Color Ink Cartridge</div>
                                            <div class="product-sku">SKU: INK-COL-C1</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="branch-info">
                                        <i class="fas fa-store branch-icon"></i>
                                        <span>Downtown Branch</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="stock-info">
                                        <div class="stock-bar">
                                            <div class="stock-fill stock-medium"></div>
                                        </div>
                                        <div class="stock-numbers">
                                            <div class="current-stock">42 units</div>
                                            <div class="minimum-stock">Min: 30 units</div>
                                        </div>
                                    </div>
                                </td>
                                <td>30 units</td>
                                <td>
                                    <div class="update-action">
                                        <input type="text" class="stock-input" value="42">
                                        <button class="update-btn">Update</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="product-info">
                                        <div class="product-icon icon-pen">
                                            <i class="fas fa-pen"></i>
                                        </div>
                                        <div>
                                            <div class="product-name">Premium Ballpoint Pens</div>
                                            <div class="product-sku">SKU: PEN-BP-PRM</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="branch-info">
                                        <i class="fas fa-store branch-icon"></i>
                                        <span>Main Branch</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="stock-info">
                                        <div class="stock-bar">
                                            <div class="stock-fill stock-low"></div>
                                        </div>
                                        <div class="stock-numbers">
                                            <div class="current-stock">18 packs</div>
                                            <div class="minimum-stock">Min: 25 packs</div>
                                        </div>
                                    </div>
                                </td>
                                <td>25 packs</td>
                                <td>
                                    <div class="update-action">
                                        <input type="text" class="stock-input" value="18">
                                        <button class="update-btn">Update</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="product-info">
                                        <div class="product-icon icon-folder">
                                            <i class="fas fa-folder"></i>
                                        </div>
                                        <div>
                                            <div class="product-name">Report Folders (A4)</div>
                                            <div class="product-sku">SKU: FOL-A4-RPT</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="branch-info">
                                        <i class="fas fa-store branch-icon"></i>
                                        <span>Westside Branch</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="stock-info">
                                        <div class="stock-bar">
                                            <div class="stock-fill stock-high"></div>
                                        </div>
                                        <div class="stock-numbers">
                                            <div class="current-stock">87 units</div>
                                            <div class="minimum-stock">Min: 20 units</div>
                                        </div>
                                    </div>
                                </td>
                                <td>20 units</td>
                                <td>
                                    <div class="update-action">
                                        <input type="text" class="stock-input" value="87">
                                        <button class="update-btn">Update</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="product-info">
                                        <div class="product-icon icon-binding">
                                            <i class="fas fa-book"></i>
                                        </div>
                                        <div>
                                            <div class="product-name">Spiral Binding (30mm)</div>
                                            <div class="product-sku">SKU: BND-SPL-30</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="branch-info">
                                        <i class="fas fa-store branch-icon"></i>
                                        <span>Main Branch</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="stock-info">
                                        <div class="stock-bar">
                                            <div class="stock-fill stock-critical"></div>
                                        </div>
                                        <div class="stock-numbers">
                                            <div class="current-stock">3 units</div>
                                            <div class="minimum-stock">Min: 15 units</div>
                                        </div>
                                    </div>
                                </td>
                                <td>15 units</td>
                                <td>
                                    <div class="update-action">
                                        <input type="text" class="stock-input" value="3">
                                        <button class="update-btn">Update</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            
            <!-- Section 2: Low Stock Alerts -->
            <section class="alerts-section">
                <div class="alerts-header">
                    <h2><i class="fas fa-exclamation-triangle"></i> Low Stock Alerts</h2>
                    <div class="alert-count">
                        <div class="alert-badge">8 Alerts</div>
                        <span style="font-size: 14px; color: var(--light-text);">Products below minimum stock</span>
                    </div>
                </div>
                
                <div class="alerts-container">
                    <table class="alerts-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Branch</th>
                                <th>Current Stock</th>
                                <th>Minimum Required</th>
                                <th>Alert Level</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div class="product-info">
                                        <div class="product-icon icon-binding">
                                            <i class="fas fa-book"></i>
                                        </div>
                                        <div>
                                            <div class="product-name">Spiral Binding (30mm)</div>
                                            <div class="product-sku">SKU: BND-SPL-30</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="branch-info">
                                        <i class="fas fa-store branch-icon"></i>
                                        <span>Main Branch</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="stock-info">
                                        <div class="stock-numbers">
                                            <div class="current-stock">3 units</div>
                                            <div class="stock-difference">-12 units below minimum</div>
                                        </div>
                                    </div>
                                </td>
                                <td>15 units</td>
                                <td><span class="alert-level alert-critical">Critical</span></td>
                                <td>
                                    <div class="reorder-action">
                                        <button class="reorder-btn">Request Restock</button>
                                        <button class="mark-seen-btn">Mark as Seen</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="product-info">
                                        <div class="product-icon icon-pen">
                                            <i class="fas fa-pen"></i>
                                        </div>
                                        <div>
                                            <div class="product-name">Premium Ballpoint Pens</div>
                                            <div class="product-sku">SKU: PEN-BP-PRM</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="branch-info">
                                        <i class="fas fa-store branch-icon"></i>
                                        <span>Main Branch</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="stock-info">
                                        <div class="stock-numbers">
                                            <div class="current-stock">18 packs</div>
                                            <div class="stock-difference">-7 packs below minimum</div>
                                        </div>
                                    </div>
                                </td>
                                <td>25 packs</td>
                                <td><span class="alert-level alert-warning">Warning</span></td>
                                <td>
                                    <div class="reorder-action">
                                        <button class="reorder-btn">Request Restock</button>
                                        <button class="mark-seen-btn">Mark as Seen</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="product-info">
                                        <div class="product-icon icon-ink">
                                            <i class="fas fa-fill-drip"></i>
                                        </div>
                                        <div>
                                            <div class="product-name">Black Toner Cartridge</div>
                                            <div class="product-sku">SKU: INK-BLK-T1</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="branch-info">
                                        <i class="fas fa-store branch-icon"></i>
                                        <span>Downtown Branch</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="stock-info">
                                        <div class="stock-numbers">
                                            <div class="current-stock">5 units</div>
                                            <div class="stock-difference">-10 units below minimum</div>
                                        </div>
                                    </div>
                                </td>
                                <td>15 units</td>
                                <td><span class="alert-level alert-critical">Critical</span></td>
                                <td>
                                    <div class="reorder-action">
                                        <button class="reorder-btn">Request Restock</button>
                                        <button class="mark-seen-btn">Mark as Seen</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="product-info">
                                        <div class="product-icon" style="background-color: #607D8B;">
                                            <i class="fas fa-stapler"></i>
                                        </div>
                                        <div>
                                            <div class="product-name">Heavy-Duty Staples</div>
                                            <div class="product-sku">SKU: STP-HD-24</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="branch-info">
                                        <i class="fas fa-store branch-icon"></i>
                                        <span>Westside Branch</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="stock-info">
                                        <div class="stock-numbers">
                                            <div class="current-stock">8 boxes</div>
                                            <div class="stock-difference">-2 boxes below minimum</div>
                                        </div>
                                    </div>
                                </td>
                                <td>10 boxes</td>
                                <td><span class="alert-level alert-warning">Warning</span></td>
                                <td>
                                    <div class="reorder-action">
                                        <button class="reorder-btn">Request Restock</button>
                                        <button class="mark-seen-btn">Mark as Seen</button>
                                    </div>
                                </td>
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
        
        // Update stock button interactions
        document.querySelectorAll('.update-btn').forEach(button => {
            button.addEventListener('click', function() {
                const input = this.parentElement.querySelector('.stock-input');
                const productRow = this.closest('tr');
                const productName = productRow.querySelector('.product-name').textContent;
                
                alert(`Stock quantity for "${productName}" updated to: ${input.value} (UI mockup only)`);
            });
        });
        
        // Request Restock button interactions
        document.querySelectorAll('.reorder-btn').forEach(button => {
            button.addEventListener('click', function() {
                const productRow = this.closest('tr');
                const productName = productRow.querySelector('.product-name').textContent;
                
                alert(`Restock request sent to admin for: "${productName}" (UI mockup only)`);
            });
        });
        
        // Mark as Seen button interactions
        document.querySelectorAll('.mark-seen-btn').forEach(button => {
            button.addEventListener('click', function() {
                const productRow = this.closest('tr');
                const productName = productRow.querySelector('.product-name').textContent;
                
                alert(`Alert marked as seen for: "${productName}" (UI mockup only)`);
            });
        });
        
        // Search input
        const searchInput = document.querySelector('.search-input');
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                alert(`Searching inventory for: "${this.value}" (UI mockup only)`);
            }
        });
        
        // Logout button
        document.querySelector('.logout-btn').addEventListener('click', function() {
            alert('Logout functionality would be implemented here (UI mockup only)');
        });
        
        // Update stock input validation (UI only)
        document.querySelectorAll('.stock-input').forEach(input => {
            input.addEventListener('input', function() {
                // Only allow numbers
                this.value = this.value.replace(/[^0-9]/g, '');
                
                // Add visual feedback if value is below minimum
                const row = this.closest('tr');
                const minStockText = row.querySelector('.minimum-stock').textContent;
                const minStock = parseInt(minStockText.replace(/[^0-9]/g, ''));
                const currentValue = parseInt(this.value) || 0;
                
                if (currentValue < minStock) {
                    this.style.borderColor = 'var(--primary)';
                    this.style.backgroundColor = 'rgba(168, 53, 53, 0.05)';
                } else {
                    this.style.borderColor = 'var(--border)';
                    this.style.backgroundColor = 'var(--white)';
                }
            });
        });
    </script>
</body>
</html>