<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus - Pre-order</title>
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
        
        /* Page Navigation */
        .page-nav {
            padding: 20px 30px;
            background-color: var(--white);
            border-bottom: 1px solid var(--border);
            display: flex;
            gap: 15px;
        }
        
        .page-nav-btn {
            padding: 10px 20px;
            background-color: var(--white);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text);
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .page-nav-btn:hover {
            background-color: rgba(168, 53, 53, 0.05);
            border-color: var(--primary);
        }
        
        .page-nav-btn.active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        /* Content Container */
        .content-container {
            padding: 30px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .section-card {
            background-color: var(--white);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .section-header {
            padding: 25px;
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
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        /* Cart-Style Item List */
        .cart-container {
            background-color: var(--white);
            border-radius: 12px;
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 25px;
        }
        
        .cart-header {
            display: grid;
            grid-template-columns: 50px 2fr 1fr 1fr 1fr;
            gap: 20px;
            padding: 20px 25px;
            background-color: rgba(168, 53, 53, 0.03);
            border-bottom: 1px solid var(--border);
            font-weight: 600;
            color: var(--text);
            font-size: 15px;
        }
        
        .cart-items {
            padding: 0;
        }
        
        .cart-item {
            display: grid;
            grid-template-columns: 50px 2fr 1fr 1fr 1fr;
            gap: 20px;
            padding: 20px 25px;
            border-bottom: 1px solid var(--border);
            align-items: center;
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .item-number {
            font-weight: 600;
            color: var(--primary);
            font-size: 18px;
        }
        
        .item-name {
            font-weight: 600;
            font-size: 16px;
            color: var(--text);
        }
        
        .item-price {
            font-size: 16px;
            color: var(--light-text);
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            max-width: 130px;
        }
        
        .quantity-btn {
            width: 36px;
            height: 36px;
            background-color: var(--white);
            border: 1.5px solid var(--border);
            color: var(--text);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        
        .quantity-btn:hover {
            background-color: rgba(168, 53, 53, 0.05);
            border-color: var(--primary);
        }
        
        .quantity-btn.decrement {
            border-radius: 6px 0 0 6px;
            border-right: none;
        }
        
        .quantity-btn.increment {
            border-radius: 0 6px 6px 0;
            border-left: none;
        }
        
        .quantity-input {
            width: 58px;
            text-align: center;
            border: 1.5px solid var(--border);
            border-left: none;
            border-right: none;
            height: 36px;
            font-size: 16px;
            background-color: var(--white);
            color: var(--text);
        }
        
        .item-subtotal {
            font-weight: 600;
            color: var(--primary);
            font-size: 17px;
        }
        
        /* Add Item Button */
        .add-item-container {
            text-align: center;
            padding: 20px;
            background-color: var(--white);
            border-radius: 12px;
            border: 1px dashed var(--border);
            margin-bottom: 30px;
        }
        
        .add-item-btn {
            padding: 14px 30px;
            background-color: rgba(168, 53, 53, 0.1);
            color: var(--primary);
            border: 1.5px solid var(--primary);
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .add-item-btn:hover {
            background-color: rgba(168, 53, 53, 0.2);
        }
        
        /* Order Summary */
        .summary-container {
            background-color: var(--white);
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 25px;
            margin-top: auto;
        }
        
        .summary-title {
            font-size: 20px;
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border);
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .summary-label {
            color: var(--light-text);
        }
        
        .summary-value {
            font-weight: 600;
            color: var(--text);
        }
        
        .total-row {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid var(--border);
            font-size: 18px;
        }
        
        .total-label {
            color: var(--text);
            font-weight: 600;
        }
        
        .total-value {
            font-weight: 700;
            color: var(--primary);
            font-size: 22px;
        }
        
        /* Action Buttons */
        .action-section {
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid var(--border);
        }
        
        .primary-button {
            width: 100%;
            padding: 16px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 18px;
            cursor: pointer;
            transition: background-color 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        
        .primary-button:hover {
            background-color: #8b2a2a;
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
        }
        
        @media (max-width: 768px) {
            .cart-header, .cart-item {
                grid-template-columns: 40px 1fr;
                grid-template-rows: auto auto auto;
                gap: 15px;
            }
            
            .item-price, .quantity-control, .item-subtotal {
                grid-column: 2;
            }
            
            .page-nav {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <?php include 'c_sidebar.php'; ?>
    
    <!-- Main Content Area -->
    <main class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <h1 class="page-title">Pre-order Stationery</h1>
            <p class="page-subtitle">Select stationery items and place your pre-order</p>
        </header>
        
        <!-- Page Navigation -->
        <div class="page-nav">
            <a href="pre-order.html" class="page-nav-btn active">
                <i class="fas fa-clipboard-check"></i> Pre-order
            </a>
            <a href="upload-files.html" class="page-nav-btn">
                <i class="fas fa-upload"></i> Upload Files
            </a>
        </div>
        
        <!-- Content Container -->
        <div class="content-container">
            <div class="section-card">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-clipboard-check"></i> Pre-order Items
                    </h2>
                    <p class="section-description">
                        Review your stationery items and submit your pre-order
                    </p>
                </div>
                
                <div class="section-content">
                    <!-- Cart-Style Item List -->
                    <div class="cart-container">
                        <div class="cart-header">
                            <div>#</div>
                            <div>Item</div>
                            <div>Unit Price</div>
                            <div>Quantity</div>
                            <div>Subtotal</div>
                        </div>
                        
                        <div class="cart-items">
                            <!-- Item 1 -->
                            <div class="cart-item">
                                <div class="item-number">1</div>
                                <div class="item-name">A4 Paper Trim (80gsm)</div>
                                <div class="item-price">RM24.00</div>
                                <div class="quantity-control">
                                    <button class="quantity-btn decrement">-</button>
                                    <input type="text" class="quantity-input" value="2" readonly>
                                    <button class="quantity-btn increment">+</button>
                                </div>
                                <div class="item-subtotal">RM48.00</div>
                            </div>
                            
                            <!-- Item 2 -->
                            <div class="cart-item">
                                <div class="item-number">2</div>
                                <div class="item-name">Premium Color Paper (A4)</div>
                                <div class="item-price">RM1.20</div>
                                <div class="quantity-control">
                                    <button class="quantity-btn decrement">-</button>
                                    <input type="text" class="quantity-input" value="50" readonly>
                                    <button class="quantity-btn increment">+</button>
                                </div>
                                <div class="item-subtotal">RM60.00</div>
                            </div>
                            
                            <!-- Item 3 -->
                            <div class="cart-item">
                                <div class="item-number">3</div>
                                <div class="item-name">Document Binder (Spiral)</div>
                                <div class="item-price">RM8.50</div>
                                <div class="quantity-control">
                                    <button class="quantity-btn decrement">-</button>
                                    <input type="text" class="quantity-input" value="1" readonly>
                                    <button class="quantity-btn increment">+</button>
                                </div>
                                <div class="item-subtotal">RM8.50</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Add Item Button -->
                    <div class="add-item-container">
                        <button class="add-item-btn">
                            <i class="fas fa-plus-circle"></i> Browse Products to Add More Items
                        </button>
                    </div>
                    
                    <!-- Order Summary -->
                    <div class="summary-container">
                        <h3 class="summary-title">Order Summary</h3>
                        
                        <div class="summary-row">
                            <span class="summary-label">A4 Paper Trim (2x)</span>
                            <span class="summary-value">RM48.00</span>
                        </div>
                        
                        <div class="summary-row">
                            <span class="summary-label">Color Paper (50x)</span>
                            <span class="summary-value">RM60.00</span>
                        </div>
                        
                        <div class="summary-row">
                            <span class="summary-label">Document Binder (1x)</span>
                            <span class="summary-value">RM8.50</span>
                        </div>
                        
                        <div class="summary-row">
                            <span class="summary-label">Service Fee</span>
                            <span class="summary-value">RM5.00</span>
                        </div>
                        
                        <div class="summary-row total-row">
                            <span class="total-label">Total Amount</span>
                            <span class="total-value">RM121.50</span>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="action-section">
                            <button class="primary-button" id="submitPreOrder">
                                <i class="fas fa-paper-plane"></i> Submit Pre-order
                            </button>
                        </div>
                    </div>
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
        // Navigation is handled by PHP sidebar - active state set dynamically
        
        // Page navigation buttons
        document.querySelectorAll('.page-nav-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!this.href) {
                    e.preventDefault();
                    return;
                }
            });
        });
        
        // Quantity controls for cart items
        document.querySelectorAll('.quantity-btn').forEach(button => {
            button.addEventListener('click', function() {
                const input = this.parentElement.querySelector('.quantity-input');
                let value = parseInt(input.value);
                
                if (this.classList.contains('increment')) {
                    if (value < 99) {
                        input.value = value + 1;
                        updateSubtotal(this.closest('.cart-item'));
                    }
                } else if (this.classList.contains('decrement')) {
                    if (value > 1) {
                        input.value = value - 1;
                        updateSubtotal(this.closest('.cart-item'));
                    }
                }
                
                updateOrderSummary();
            });
        });
        
        // Update subtotal for a cart item
        function updateSubtotal(cartItem) {
            const priceText = cartItem.querySelector('.item-price').textContent;
            const price = parseFloat(priceText.replace('RM', ''));
            const quantity = parseInt(cartItem.querySelector('.quantity-input').value);
            const subtotal = price * quantity;
            
            cartItem.querySelector('.item-subtotal').textContent = 'RM' + subtotal.toFixed(2);
        }
        
        // Update order summary
        function updateOrderSummary() {
            let total = 0;
            
            document.querySelectorAll('.cart-item').forEach(item => {
                const subtotalText = item.querySelector('.item-subtotal').textContent;
                const subtotal = parseFloat(subtotalText.replace('RM', ''));
                total += subtotal;
            });
            
            // Add service fee
            total += 5.00;
            
            document.querySelector('.total-value').textContent = 'RM' + total.toFixed(2);
        }
        
        // Add item button
        document.querySelector('.add-item-btn').addEventListener('click', function() {
            alert('Browse products interface would open here. This is a UI mockup only.');
        });
        
        // Submit pre-order button
        document.getElementById('submitPreOrder').addEventListener('click', function() {
            alert('Pre-order submitted successfully! This is a UI mockup only.');
        });
        
        // Initialize order summary
        updateOrderSummary();
    </script>
</body>
</html>