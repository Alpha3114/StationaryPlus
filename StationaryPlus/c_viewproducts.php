<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus - Product Catalog</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #A83535;      /* Brick Red */
            --secondary: #F4A261;    /* Muted Orange */
            --accent: #F1EDE8;       /* Warm Beige */
            --background: #FAFAFA;   /* Very Light Grey */
            --text-primary: #2E2E2E; /* Charcoal */
            --text-secondary: #707070; /* Grey */
            --border: #E0E0E0;       /* Soft Grey */
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
            color: var(--text-secondary);
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
            color: var(--text-primary);
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
            background-color: var(--accent);
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
            color: var(--text-primary);
            margin-bottom: 3px;
        }
        
        .user-role {
            font-size: 13px;
            color: var(--text-secondary);
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-title {
            font-size: 24px;
            color: var(--text-primary);
        }
        
        .page-subtitle {
            font-size: 15px;
            color: var(--text-secondary);
            margin-top: 5px;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .search-container {
            position: relative;
        }
        
        .search-input {
            padding: 12px 15px 12px 45px;
            border: 1px solid var(--border);
            border-radius: 8px;
            width: 300px;
            font-size: 15px;
            background-color: var(--accent);
            transition: all 0.2s ease;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(168, 53, 53, 0.1);
            background-color: var(--white);
        }
        
        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 18px;
        }
        
        .notification-btn {
            position: relative;
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 20px;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .notification-btn:hover {
            background-color: var(--accent);
            color: var(--primary);
        }
        
        /* Product Listing Content */
        .product-content {
            padding: 30px;
            flex-grow: 1;
        }
        
        .page-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .filter-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .filter-btn {
            padding: 10px 20px;
            background-color: var(--white);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        
        .filter-btn:hover {
            background-color: var(--accent);
            border-color: var(--primary);
        }
        
        .results-info {
            color: var(--text-secondary);
            font-size: 15px;
        }
        
        /* Product Grid */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .product-card {
            background-color: var(--white);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
        }
        
        .product-image {
            height: 180px;
            background-color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            font-size: 60px;
            opacity: 0.7;
        }
        
        .category-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background-color: rgba(168, 53, 53, 0.9);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .product-info {
            padding: 25px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .product-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 10px;
            line-height: 1.4;
        }
        
        .product-description {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.5;
            margin-bottom: 20px;
            flex-grow: 1;
        }
        
        .product-price {
            font-size: 22px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 20px;
        }
        
        .product-actions {
            display: flex;
            gap: 10px;
        }
        
        .preorder-btn {
            flex-grow: 1;
            padding: 12px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: background-color 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .preorder-btn:hover {
            background-color: #8b2a2a;
        }
        
        .details-btn {
            padding: 12px 20px;
            background-color: var(--accent);
            color: var(--text-primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .details-btn:hover {
            background-color: rgba(168, 53, 53, 0.05);
            border-color: var(--primary);
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 40px;
        }
        
        .pagination-btn {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background-color: var(--white);
            border: 1px solid var(--border);
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .pagination-btn:hover {
            background-color: var(--accent);
            border-color: var(--primary);
        }
        
        .pagination-btn.active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .pagination-ellipsis {
            color: var(--text-secondary);
            padding: 0 10px;
        }
        
        /* Footer */
        .product-footer {
            text-align: center;
            padding: 25px;
            color: var(--text-secondary);
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
        
        /* Category Sidebar (if we want filters) */
        .category-sidebar {
            width: 220px;
            background-color: var(--white);
            border-right: 1px solid var(--border);
            padding: 25px 0;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
            
            .search-input {
                width: 250px;
            }
        }
        
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
            
            .search-input {
                width: 200px;
            }
        }
        
        @media (max-width: 768px) {
            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            }
            
            .page-controls {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .search-input {
                width: 100%;
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
            <div>
                <h1 class="page-title">Product Catalog</h1>
                <p class="page-subtitle">Browse stationery products and printing services</p>
            </div>
            <div class="header-actions">
                <div class="search-container">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" class="search-input" placeholder="Search products...">
                </div>
                <button class="notification-btn">
                    <i class="fas fa-bell"></i>
                </button>
            </div>
        </header>
        
        <!-- Product Listing Content -->
        <div class="product-content">
            <!-- Page Controls -->
            <div class="page-controls">
                <div class="filter-section">
                    <button class="filter-btn">
                        <i class="fas fa-filter"></i> Filter by Category
                    </button>
                    <button class="filter-btn">
                        <i class="fas fa-sort-amount-down"></i> Sort by Price
                    </button>
                </div>
                <div class="results-info">
                    Showing 12 of 48 products
                </div>
            </div>
            
            <!-- Product Grid -->
            <div class="product-grid">
                <!-- Product Card 1 -->
                <div class="product-card">
                    <div class="product-image">
                        <div class="category-badge">Writing</div>
                        <div class="image-placeholder">
                            <i class="fas fa-pen"></i>
                        </div>
                    </div>
                    <div class="product-info">
                        <h3 class="product-name">Premium Ballpoint Pens (Set of 5)</h3>
                        <p class="product-description">Smooth writing ballpoint pens with comfortable grip, available in blue, black, and red ink.</p>
                        <div class="product-price">RM 12.90</div>
                        <div class="product-actions">
                            <button class="preorder-btn">
                                <i class="fas fa-cart-plus"></i> Pre-order
                            </button>
                            <button class="details-btn">Details</button>
                        </div>
                    </div>
                </div>
                
                <!-- Product Card 2 -->
                <div class="product-card">
                    <div class="product-image">
                        <div class="category-badge">Paper</div>
                        <div class="image-placeholder">
                            <i class="fas fa-file"></i>
                        </div>
                    </div>
                    <div class="product-info">
                        <h3 class="product-name">A4 Printing Paper (5 Reams)</h3>
                        <p class="product-description">High-quality A4 paper, 80gsm, suitable for all types of printing and copying.</p>
                        <div class="product-price">RM 45.00</div>
                        <div class="product-actions">
                            <button class="preorder-btn">
                                <i class="fas fa-cart-plus"></i> Pre-order
                            </button>
                            <button class="details-btn">Details</button>
                        </div>
                    </div>
                </div>
                
                <!-- Product Card 5 -->
                <div class="product-card">
                    <div class="product-image">
                        <div class="category-badge">Notebooks</div>
                        <div class="image-placeholder">
                            <i class="fas fa-book-open"></i>
                        </div>
                    </div>
                    <div class="product-info">
                        <h3 class="product-name">Hardcover Notebook (A5)</h3>
                        <p class="product-description">Premium hardcover notebook with 200 pages of ruled paper. Available in 3 colors.</p>
                        <div class="product-price">RM 18.50</div>
                        <div class="product-actions">
                            <button class="preorder-btn">
                                <i class="fas fa-cart-plus"></i> Pre-order
                            </button>
                            <button class="details-btn">Details</button>
                        </div>
                    </div>
                </div>
                
                <!-- Product Card 6 -->
                <div class="product-card">
                    <div class="product-image">
                        <div class="category-badge">Stationery Set</div>
                        <div class="image-placeholder">
                            <i class="fas fa-gift"></i>
                        </div>
                    </div>
                    <div class="product-info">
                        <h3 class="product-name">Executive Stationery Set</h3>
                        <p class="product-description">Complete set including pen, notebook, sticky notes, and paper clips in matching case.</p>
                        <div class="product-price">RM 35.00</div>
                        <div class="product-actions">
                            <button class="preorder-btn">
                                <i class="fas fa-cart-plus"></i> Pre-order
                            </button>
                            <button class="details-btn">Details</button>
                        </div>
                    </div>
                </div>
                
                
                <!-- Product Card 8 -->
                <div class="product-card">
                    <div class="product-image">
                        <div class="category-badge">Writing</div>
                        <div class="image-placeholder">
                            <i class="fas fa-highlighter"></i>
                        </div>
                    </div>
                    <div class="product-info">
                        <h3 class="product-name">Assorted Highlighters (Set of 6)</h3>
                        <p class="product-description">Vibrant color highlighters with chisel tips for effective text marking and note-taking.</p>
                        <div class="product-price">RM 15.90</div>
                        <div class="product-actions">
                            <button class="preorder-btn">
                                <i class="fas fa-cart-plus"></i> Pre-order
                            </button>
                            <button class="details-btn">Details</button>
                        </div>
                    </div>
                </div>
                
                <!-- Product Card 9 -->
                <div class="product-card">
                    <div class="product-image">
                        <div class="category-badge">Paper</div>
                        <div class="image-placeholder">
                            <i class="fas fa-sticky-note"></i>
                        </div>
                    </div>
                    <div class="product-info">
                        <h3 class="product-name">Assorted Sticky Notes</h3>
                        <p class="product-description">Colorful sticky notes in various sizes and shapes. 10 pads per set.</p>
                        <div class="product-price">RM 22.50</div>
                        <div class="product-actions">
                            <button class="preorder-btn">
                                <i class="fas fa-cart-plus"></i> Pre-order
                            </button>
                            <button class="details-btn">Details</button>
                        </div>
                    </div>
                </div>
                
                <!-- Product Card 10 -->
                <div class="product-card">
                    <div class="product-image">
                        <div class="category-badge">Printing</div>
                        <div class="image-placeholder">
                            <i class="fas fa-t-shirt"></i>
                        </div>
                    </div>
                    <div class="product-info">
                        <h3 class="product-name">T-Shirt Printing Service</h3>
                        <p class="product-description">Custom design printing on cotton t-shirts. Minimum order of 5 pieces.</p>
                        <div class="product-price">RM 25.00/pc</div>
                        <div class="product-actions">
                            <button class="preorder-btn">
                                <i class="fas fa-cart-plus"></i> Select
                            </button>
                            <button class="details-btn">Details</button>
                        </div>
                    </div>
                </div>
                
                <!-- Product Card 11 -->
                <div class="product-card">
                    <div class="product-image">
                        <div class="category-badge">Organizers</div>
                        <div class="image-placeholder">
                            <i class="fas fa-folder"></i>
                        </div>
                    </div>
                    <div class="product-info">
                        <h3 class="product-name">Document Folder (Set of 10)</h3>
                        <p class="product-description">Plastic document folders with button closure. Assorted colors available.</p>
                        <div class="product-price">RM 28.00</div>
                        <div class="product-actions">
                            <button class="preorder-btn">
                                <i class="fas fa-cart-plus"></i> Pre-order
                            </button>
                            <button class="details-btn">Details</button>
                        </div>
                    </div>
                </div>
                
                <!-- Product Card 12 -->
                <div class="product-card">
                    <div class="product-image">
                        <div class="category-badge">Printing</div>
                        <div class="image-placeholder">
                            <i class="fas fa-id-card"></i>
                        </div>
                    </div>
                    <div class="product-info">
                        <h3 class="product-name">Business Card Printing</h3>
                        <p class="product-description">Premium business card printing on 350gsm card stock. Gloss or matte finish available.</p>
                        <div class="product-price">RM 65.00/100pcs</div>
                        <div class="product-actions">
                            <button class="preorder-btn">
                                <i class="fas fa-cart-plus"></i> Select
                            </button>
                            <button class="details-btn">Details</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pagination -->
            <div class="pagination">
                <button class="pagination-btn">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="pagination-btn active">1</button>
                <button class="pagination-btn">2</button>
                <button class="pagination-btn">3</button>
                <div class="pagination-ellipsis">...</div>
                <button class="pagination-btn">8</button>
                <button class="pagination-btn">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        
        <!-- Footer -->
        <footer class="product-footer">
            <div>© 2023 StationaryPlus - Stationery & Printing Management System</div>
            <div class="footer-links">
                <a href="#">Help Center</a> | 
                <a href="#">Contact Support</a> | 
                <a href="#">Privacy Policy</a>
            </div>
        </footer>
    </main>
    
    <script>
        // Simple script for UI interactions
        // Navigation is handled by PHP sidebar - no preventDefault needed
        // Links will now navigate normally while PHP sets active state
        
        // Pre-order button interactions
        document.querySelectorAll('.preorder-btn').forEach(button => {
            button.addEventListener('click', function() {
                const productName = this.closest('.product-card').querySelector('.product-name').textContent;
                alert(`"${productName}" has been added to your pre-order list.`);
            });
        });
        
        // Details button interactions
        document.querySelectorAll('.details-btn').forEach(button => {
            button.addEventListener('click', function() {
                const productName = this.closest('.product-card').querySelector('.product-name').textContent;
                alert(`Viewing details for: ${productName}`);
            });
        });
        
        // Filter button interactions
        document.querySelectorAll('.filter-btn').forEach(button => {
            button.addEventListener('click', function() {
                const filterType = this.textContent.trim();
                alert(`Filtering by: ${filterType}`);
            });
        });
        
        // Search input placeholder interaction
        const searchInput = document.querySelector('.search-input');
        searchInput.addEventListener('focus', function() {
            this.placeholder = 'Search by product name, category, or description...';
        });
        
        searchInput.addEventListener('blur', function() {
            this.placeholder = 'Search products...';
        });
        
        // Pagination button interactions
        document.querySelectorAll('.pagination-btn').forEach(button => {
            button.addEventListener('click', function() {
                if (this.classList.contains('active')) return;
                
                // Remove active class from all pagination buttons
                document.querySelectorAll('.pagination-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                
                // Add active class to clicked button if it's a number button
                if (!this.querySelector('i')) {
                    this.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>