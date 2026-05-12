<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus - Order Status</title>
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
            font-size: 22px;
            font-weight: 700;
            color: var(--primary);
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
            padding: 0 25px 15px 25px;
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
            padding: 15px 25px;
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
            margin-right: 15px;
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
        }
        
        .user-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background-color: rgba(168, 53, 53, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-weight: 600;
            font-size: 18px;
            margin-right: 12px;
        }
        
        .user-details {
            flex-grow: 1;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 16px;
            color: var(--text);
            margin-bottom: 3px;
        }
        
        .user-role {
            font-size: 13px;
            color: var(--light-text);
        }
        
        /* Main Content Area */
        .main-content {
            flex-grow: 1;
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .top-header {
            background-color: var(--white);
            padding: 20px 30px;
            border-bottom: 1px solid var(--border);
        }
        
        .page-title {
            font-size: 28px;
            color: var(--text);
            margin-bottom: 8px;
        }
        
        .page-subtitle {
            font-size: 16px;
            color: var(--light-text);
        }
        
        /* Content Container */
        .content-container {
            padding: 30px;
            flex-grow: 1;
        }
        
        /* Section Card */
        .section-card {
            background-color: var(--white);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            height: 100%;
        }
        
        .section-header {
            padding: 25px 30px;
            border-bottom: 1px solid var(--border);
            background-color: rgba(168, 53, 53, 0.03);
        }
        
        .section-title {
            font-size: 22px;
            color: var(--primary);
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .section-title i {
            margin-right: 12px;
            font-size: 24px;
        }
        
        .section-description {
            font-size: 15px;
            color: var(--light-text);
            line-height: 1.5;
        }
        
        .section-content {
            padding: 30px;
        }
        
        /* Order Status Table */
        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .orders-table thead {
            background-color: rgba(168, 53, 53, 0.05);
            border-bottom: 2px solid var(--border);
        }
        
        .orders-table th {
            padding: 18px 20px;
            text-align: left;
            font-weight: 600;
            color: var(--text);
            font-size: 15px;
            white-space: nowrap;
        }
        
        .orders-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background-color 0.2s ease;
        }
        
        .orders-table tbody tr:hover {
            background-color: rgba(168, 53, 53, 0.02);
        }
        
        .orders-table td {
            padding: 20px;
            vertical-align: middle;
            color: var(--text);
            font-size: 15px;
        }
        
        .order-id {
            font-weight: 600;
            color: var(--primary);
        }
        
        .order-date {
            color: var(--light-text);
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            min-width: 140px;
            text-align: center;
        }
        
        /* Tempahan (Pre-order) Statuses */
        .status-dihantar {
            background-color: rgba(168, 53, 53, 0.1);
            color: var(--primary);
            border: 1px solid rgba(168, 53, 53, 0.3);
        }
        
        .status-disemak {
            background-color: rgba(33, 150, 243, 0.1);
            color: #2196F3;
            border: 1px solid rgba(33, 150, 243, 0.3);
        }
        
        .status-menunggu-pembayaran {
            background-color: rgba(244, 162, 97, 0.2);
            color: var(--secondary);
            border: 1px solid rgba(244, 162, 97, 0.3);
        }
        
        .status-ditolak {
            background-color: rgba(244, 67, 54, 0.1);
            color: #F44336;
            border: 1px solid rgba(244, 67, 54, 0.3);
        }
        
        .status-disahkan {
            background-color: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }
        
        /* Pesanan (Order) Statuses */
        .status-diproses {
            background-color: rgba(168, 53, 53, 0.1);
            color: var(--primary);
            border: 1px solid rgba(168, 53, 53, 0.3);
        }
        
        .status-siap {
            background-color: rgba(33, 150, 243, 0.1);
            color: #2196F3;
            border: 1px solid rgba(33, 150, 243, 0.3);
        }
        
        .status-sedia-diambil {
            background-color: rgba(244, 162, 97, 0.2);
            color: var(--secondary);
            border: 1px solid rgba(244, 162, 97, 0.3);
        }
        
        .status-selesai {
            background-color: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }
        
        .status-dibatalkan {
            background-color: rgba(244, 67, 54, 0.1);
            color: #F44336;
            border: 1px solid rgba(244, 67, 54, 0.3);
        }
        
        .view-details-btn {
            padding: 8px 20px;
            background-color: rgba(168, 53, 53, 0.1);
            color: var(--primary);
            border: 1px solid var(--primary);
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .view-details-btn:hover {
            background-color: rgba(168, 53, 53, 0.2);
        }
        
        /* Filter Controls */
        .filter-controls {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .filter-select {
            padding: 12px 16px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: 15px;
            background-color: var(--white);
            color: var(--text);
            min-width: 180px;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        /* Footer */
        .dashboard-footer {
            text-align: center;
            padding: 25px;
            color: var(--light-text);
            font-size: 14px;
            border-top: 1px solid var(--border);
            background-color: var(--white);
        }
        
        .footer-links {
            margin-top: 10px;
        }
        
        .footer-links a {
            color: var(--primary);
            text-decoration: none;
            margin: 0 10px;
        }
        
        .footer-links a:hover {
            text-decoration: underline;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            :root {
                --sidebar-width: 70px;
            }
            
            .logo-text, .nav-text, .user-details, .nav-title {
                display: none;
            }
            
            .logo-area, .nav-section, .user-section {
                padding: 20px 15px;
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
                font-size: 20px;
            }
            
            .orders-table {
                display: block;
                overflow-x: auto;
            }
        }
        
        @media (max-width: 768px) {
            .orders-table th, 
            .orders-table td {
                padding: 15px 10px;
                font-size: 14px;
            }
            
            .status-badge {
                min-width: 120px;
                font-size: 12px;
                padding: 5px 10px;
            }
            
            .filter-controls {
                flex-direction: column;
            }
            
            .filter-select {
                width: 100%;
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
            <div class="nav-title">Main Menu</div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-home"></i>
                        </div>
                        <div class="nav-text">Dashboard</div>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <div class="nav-text">View Products</div>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div class="nav-text">Pre-order</div>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-upload"></i>
                        </div>
                        <div class="nav-text">Upload Files</div>
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="nav-section">
            <div class="nav-title">Orders & Payments</div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="#" class="nav-link active">
                        <div class="nav-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <div class="nav-text">Order Status</div>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="payment-record.html" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="nav-text">Payment Record</div>
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="user-section">
            <div class="user-info">
                <div class="user-avatar">H</div>
                <div class="user-details">
                    <div class="user-name">Haresh</div>
                    <div class="user-role">Customer Account</div>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Main Content Area -->
    <main class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <h1 class="page-title">Order Status</h1>
            <p class="page-subtitle">Check the status of your pre-orders and orders</p>
        </header>
        
        <!-- Content Container -->
        <div class="content-container">
            <!-- Order Status Section -->
            <div class="section-card">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-clipboard-list"></i> Order Status Check
                    </h2>
                    <p class="section-description">
                        View the status of your pre-orders and orders. Click "View Details" for more information.
                    </p>
                </div>
                
                <div class="section-content">
                    <!-- Filter Controls -->
                    <div class="filter-controls">
                        <select class="filter-select">
                            <option>All Types</option>
                            <option>Tempahan (Pre-order)</option>
                            <option>Pesanan (Order)</option>
                        </select>
                        
                        <select class="filter-select">
                            <option>All Statuses</option>
                            <option>Dihantar (Submitted)</option>
                            <option>Disemak (Reviewed)</option>
                            <option>Menunggu Pembayaran (Awaiting Payment)</option>
                            <option>Disahkan (Confirmed)</option>
                            <option>Diproses (Processing)</option>
                            <option>Siap (Ready)</option>
                            <option>Sedia Diambil (Ready for Pickup)</option>
                            <option>Selesai (Completed)</option>
                        </select>
                        
                        <select class="filter-select">
                            <option>Last 30 Days</option>
                            <option>Last 7 Days</option>
                            <option>Last 3 Months</option>
                            <option>All Time</option>
                        </select>
                    </div>
                    
                    <!-- Orders Table -->
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Row 1: Tempahan with Dihantar status -->
                            <tr>
                                <td class="order-id">#SP-2023-089</td>
                                <td class="order-date">15 Nov 2023</td>
                                <td>Tempahan</td>
                                <td>
                                    <span class="status-badge status-dihantar">Submitted</span>
                                </td>
                                <td>
                                    <button class="view-details-btn">View Details</button>
                                </td>
                            </tr>
                            
                            <!-- Row 2: Pesanan with Diproses status -->
                            <tr>
                                <td class="order-id">#SP-2023-088</td>
                                <td class="order-date">14 Nov 2023</td>
                                <td>Pesanan</td>
                                <td>
                                    <span class="status-badge status-diproses">Processing</span>
                                </td>
                                <td>
                                    <button class="view-details-btn">View Details</button>
                                </td>
                            </tr>
                            
                            <!-- Row 4: Pesanan with Siap status -->
                            <tr>
                                <td class="order-id">#SP-2023-086</td>
                                <td class="order-date">12 Nov 2023</td>
                                <td>Pesanan</td>
                                <td>
                                    <span class="status-badge status-siap">Ready</span>
                                </td>
                                <td>
                                    <button class="view-details-btn">View Details</button>
                                </td>
                            </tr>
                            
                            <!-- Row 5: Tempahan with Menunggu Pembayaran status -->
                            <tr>
                                <td class="order-id">#SP-2023-085</td>
                                <td class="order-date">10 Nov 2023</td>
                                <td>Tempahan</td>
                                <td>
                                    <span class="status-badge status-menunggu-pembayaran">Awaiting Payment</span>
                                </td>
                                <td>
                                    <button class="view-details-btn">View Details</button>
                                </td>
                            </tr>
                            
                            <!-- Row 6: Pesanan with Sedia Diambil status -->
                            <tr>
                                <td class="order-id">#SP-2023-084</td>
                                <td class="order-date">08 Nov 2023</td>
                                <td>Pesanan</td>
                                <td>
                                    <span class="status-badge status-sedia-diambil">Ready for Pickup</span>
                                </td>
                                <td>
                                    <button class="view-details-btn">View Details</button>
                                </td>
                            </tr>
                            
                            <!-- Row 7: Tempahan with Ditolak status -->
                            <tr>
                                <td class="order-id">#SP-2023-083</td>
                                <td class="order-date">05 Nov 2023</td>
                                <td>Tempahan</td>
                                <td>
                                    <span class="status-badge status-ditolak">Rejected</span>
                                </td>
                                <td>
                                    <button class="view-details-btn">View Details</button>
                                </td>
                            </tr>
                            
                            <!-- Row 8: Pesanan with Selesai status -->
                            <tr>
                                <td class="order-id">#SP-2023-082</td>
                                <td class="order-date">03 Nov 2023</td>
                                <td>Pesanan</td>
                                <td>
                                    <span class="status-badge status-selesai">Completed</span>
                                </td>
                                <td>
                                    <button class="view-details-btn">View Details</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <footer class="dashboard-footer">
            <div>© 2023 StationaryPlus - Stationery & Printing Management System</div>
            <div class="footer-links">
                <a href="#">Help Center</a> | 
                <a href="#">Contact Support</a> | 
                <a href="#">Privacy Policy</a>
            </div>
        </footer>
    </main>
    
    <script>
        // Navigation interactions
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                document.querySelectorAll('.nav-link').forEach(item => {
                    item.classList.remove('active');
                });
                this.classList.add('active');
                
                // Only prevent default if it's not a link to another page
                if (!this.getAttribute('href') || this.getAttribute('href') === '#') {
                    e.preventDefault();
                }
            });
        });
        
        // View Details buttons
        document.querySelectorAll('.view-details-btn').forEach(button => {
            button.addEventListener('click', function() {
                const orderId = this.closest('tr').querySelector('.order-id').textContent;
                const orderType = this.closest('tr').querySelector('td:nth-child(3)').textContent;
                const orderStatus = this.closest('tr').querySelector('.status-badge').textContent;
                
                alert(`Order Details:\n\nOrder ID: ${orderId}\nType: ${orderType}\nStatus: ${orderStatus}\n\nThis is a UI mockup only. In a real system, this would show detailed order information.`);
            });
        });
        
        // Filter functionality
        document.querySelectorAll('.filter-select').forEach(select => {
            select.addEventListener('change', function() {
                alert(`Filter applied: ${this.value}\n\nThis is a UI mockup only. In a real system, this would filter the order list.`);
            });
        });
    </script>
</body>
</html>

<script>
(function fitDoc(){
    function applyScale(){
        document.documentElement.style.overflow = 'hidden';
        document.body.style.overflow = 'hidden';
        var el = document.body;
        var contentH = el.scrollHeight;
        var viewH = window.innerHeight;
        var scale = Math.min(1, viewH / contentH);
        el.style.transformOrigin = 'top left';
        el.style.transform = 'scale(' + scale + ')';
    }
    window.addEventListener('load', applyScale);
    window.addEventListener('resize', applyScale);
})();
</script>