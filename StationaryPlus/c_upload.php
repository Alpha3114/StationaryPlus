<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus - Upload Files</title>
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
        
        /* Related Pre-order Selection */
        .preorder-select-container {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--text);
            font-size: 15px;
        }
        
        .form-select, .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.2s ease;
            background-color: var(--white);
            color: var(--text);
        }
        
        .form-select:focus, .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(168, 53, 53, 0.1);
        }
        
        /* File Upload Area */
        .upload-area {
            border: 2px dashed var(--border);
            border-radius: 12px;
            padding: 50px 30px;
            text-align: center;
            background-color: rgba(168, 53, 53, 0.02);
            transition: all 0.3s ease;
            margin: 25px 0;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 200px;
        }
        
        .upload-area:hover {
            border-color: var(--primary);
            background-color: rgba(168, 53, 53, 0.05);
        }
        
        .upload-icon {
            font-size: 60px;
            color: var(--primary);
            margin-bottom: 20px;
            opacity: 0.7;
        }
        
        .upload-title {
            font-size: 20px;
            color: var(--text);
            margin-bottom: 10px;
        }
        
        .upload-description {
            font-size: 15px;
            color: var(--light-text);
            margin-bottom: 20px;
            max-width: 400px;
        }
        
        .upload-requirements {
            font-size: 14px;
            color: var(--light-text);
            margin-top: 15px;
        }
        
        /* Printing Specifications */
        .specs-container {
            margin: 30px 0;
            padding: 25px;
            background-color: rgba(168, 53, 53, 0.02);
            border-radius: 12px;
            border: 1px solid var(--border);
        }
        
        .specs-title {
            font-size: 18px;
            color: var(--primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .specs-title i {
            margin-right: 12px;
            font-size: 20px;
        }
        
        .specs-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 5px;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
        }
        
        .radio-option input {
            margin-right: 8px;
            accent-color: var(--primary);
        }
        
        .radio-label {
            color: var(--text);
            font-size: 15px;
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
        
        /* Special Instructions */
        .instructions-container {
            margin: 25px 0;
            padding: 25px;
            background-color: rgba(168, 53, 53, 0.02);
            border-radius: 12px;
            border: 1px solid var(--border);
        }
        
        .instructions-title {
            font-size: 18px;
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .instructions-title i {
            margin-right: 12px;
            font-size: 20px;
        }
        
        .instructions-input {
            width: 100%;
            padding: 16px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.2s ease;
            background-color: var(--white);
            color: var(--text);
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
        }
        
        .instructions-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(168, 53, 53, 0.1);
        }
        
        /* Uploaded Files List */
        .file-list {
            margin-top: 20px;
        }
        
        .file-list-title {
            font-size: 18px;
            color: var(--text);
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            padding: 18px;
            background-color: var(--white);
            border-radius: 8px;
            margin-bottom: 12px;
            border: 1px solid var(--border);
            justify-content: space-between;
        }
        
        .file-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .file-icon {
            color: var(--primary);
            font-size: 22px;
        }
        
        .file-name {
            font-weight: 600;
            color: var(--text);
            font-size: 16px;
        }
        
        .file-details {
            font-size: 13px;
            color: var(--light-text);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .file-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-ready {
            background-color: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }
        
        .status-pending {
            background-color: rgba(244, 162, 97, 0.2);
            color: var(--secondary);
        }
        
        .file-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .details-btn {
            padding: 8px 16px;
            background-color: rgba(168, 53, 53, 0.1);
            color: var(--primary);
            border: 1px solid var(--primary);
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .details-btn:hover {
            background-color: rgba(168, 53, 53, 0.2);
        }
        
        .file-remove {
            color: var(--light-text);
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            padding: 5px;
            border-radius: 5px;
            transition: all 0.2s ease;
        }
        
        .file-remove:hover {
            color: var(--primary);
            background-color: rgba(168, 53, 53, 0.1);
        }
        
        /* Action Buttons */
        .action-section {
            margin-top: auto;
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
            .specs-grid {
                grid-template-columns: 1fr;
            }
            
            .page-nav {
                flex-wrap: wrap;
            }
            
            .file-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .file-actions {
                align-self: flex-end;
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
            <h1 class="page-title">Upload Files for Printing</h1>
            <p class="page-subtitle">Upload files for printing and specify printing requirements</p>
        </header>
        
        <!-- Page Navigation -->
        <div class="page-nav">
            <a href="pre-order.html" class="page-nav-btn">
                <i class="fas fa-clipboard-check"></i> Pre-order
            </a>
            <a href="upload-files.html" class="page-nav-btn active">
                <i class="fas fa-upload"></i> Upload Files
            </a>
        </div>
        
        <!-- Content Container -->
        <div class="content-container">
            <div class="section-card">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-upload"></i> File Upload & Specifications
                    </h2>
                    <p class="section-description">
                        Upload files for printing and specify printing requirements
                    </p>
                </div>
                
                <div class="section-content">
                    <!-- Related Pre-order Selection -->
                    <div class="preorder-select-container">
                        <label class="form-label">Select Related Pre-order</label>
                        <select class="form-select">
                            <option selected>#SP-2023-089 - A4 Paper & Printing Order</option>
                            <option>#SP-2023-087 - Thesis Printing & Binding</option>
                            <option>#SP-2023-085 - Business Card Printing</option>
                            <option>No specific pre-order</option>
                        </select>
                    </div>
                    
                    <!-- Upload Area -->
                    <div class="upload-area" id="uploadArea">
                        <div class="upload-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <h3 class="upload-title">Drag & Drop Your Files Here</h3>
                        <p class="upload-description">
                            Click to browse or drag and drop your files. Supported formats: PDF, DOC, DOCX, JPG, PNG.
                        </p>
                        <p class="upload-requirements">
                            Maximum file size: 50MB per file
                        </p>
                    </div>
                    
                    <!-- Printing Specifications -->
                    <div class="specs-container">
                        <h3 class="specs-title">
                            <i class="fas fa-print"></i> Printing Specifications
                        </h3>
                        
                        <div class="specs-grid">
                            <!-- Print Type -->
                            <div class="form-group">
                                <label class="form-label">Print Type</label>
                                <div class="radio-group">
                                    <div class="radio-option">
                                        <input type="radio" id="bw" name="printType" checked>
                                        <label class="radio-label" for="bw">Black & White</label>
                                    </div>
                                    <div class="radio-option">
                                        <input type="radio" id="color" name="printType">
                                        <label class="radio-label" for="color">Color</label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Paper Size -->
                            <div class="form-group">
                                <label class="form-label">Paper Size</label>
                                <select class="form-select" id="paperSize">
                                    <option>A0</option>
                                    <option>A1</option>
                                    <option>A2</option>
                                    <option>A3</option>
                                    <option selected>A4</option>
                                    <option>A5</option>
                                </select>
                            </div>
                            
                            <!-- Paper Type -->
                            <div class="form-group">
                                <label class="form-label">Paper Type</label>
                                <select class="form-select" id="paperType">
                                    <option selected>80gsm Standard</option>
                                    <option>100gsm Premium</option>
                                    <option>Glossy Photo Paper</option>
                                    <option>Art Paper</option>
                                    <option>Recycled Paper</option>
                                </select>
                            </div>
                            
                            <!-- Binding Service -->
                            <div class="form-group">
                                <label class="form-label">Binding Service</label>
                                <select class="form-select" id="bindingService">
                                    <option selected>None</option>
                                    <option>Staple</option>
                                    <option>Spiral</option>
                                    <option>Thermal Binding</option>
                                    <option>Hardcover Binding</option>
                                </select>
                            </div>
                            
                            <!-- Number of Copies -->
                            <div class="form-group">
                                <label class="form-label">Number of Copies</label>
                                <div class="quantity-control" style="max-width: 150px;">
                                    <button class="quantity-btn decrement" id="copyDecrement">-</button>
                                    <input type="text" class="quantity-input" id="copyInput" value="1" readonly style="width: 78px;">
                                    <button class="quantity-btn increment" id="copyIncrement">+</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Special Instructions -->
                    <div class="instructions-container">
                        <h3 class="instructions-title">
                            <i class="fas fa-edit"></i> Special Instructions
                        </h3>
                        <textarea class="instructions-input" id="specialInstructions" placeholder="Add any special printing instructions, notes, or requirements for your files..."></textarea>
                    </div>
                    
                    <!-- Uploaded Files List -->
                    <div class="file-list">
                        <h4 class="file-list-title">Uploaded Files</h4>
                        
                        <div class="file-item">
                            <div class="file-info">
                                <div class="file-icon">
                                    <i class="fas fa-file-pdf"></i>
                                </div>
                                <div>
                                    <div class="file-name">Project_Report_Final.pdf</div>
                                    <div class="file-details">
                                        <span>2.4MB</span>
                                        <span>Uploaded: 2 days ago</span>
                                        <span class="file-status status-ready">Ready for Printing</span>
                                    </div>
                                </div>
                            </div>
                            <div class="file-actions">
                                <button class="details-btn">Details</button>
                                <button class="file-remove">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="file-item">
                            <div class="file-info">
                                <div class="file-icon">
                                    <i class="fas fa-file-image"></i>
                                </div>
                                <div>
                                    <div class="file-name">Company_Logo.png</div>
                                    <div class="file-details">
                                        <span>850KB</span>
                                        <span>Uploaded: 1 day ago</span>
                                        <span class="file-status status-pending">Pending Review</span>
                                    </div>
                                </div>
                            </div>
                            <div class="file-actions">
                                <button class="details-btn">Details</button>
                                <button class="file-remove">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="file-item">
                            <div class="file-info">
                                <div class="file-icon">
                                    <i class="fas fa-file-word"></i>
                                </div>
                                <div>
                                    <div class="file-name">Meeting_Minutes.docx</div>
                                    <div class="file-details">
                                        <span>1.2MB</span>
                                        <span>Uploaded: 2 days ago</span>
                                        <span class="file-status status-ready">Ready for Printing</span>
                                    </div>
                                </div>
                            </div>
                            <div class="file-actions">
                                <button class="details-btn">Details</button>
                                <button class="file-remove">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Upload Button -->
                    <div class="action-section">
                        <button class="primary-button" id="uploadFilesBtn">
                            <i class="fas fa-cloud-upload-alt"></i> Upload Files
                        </button>
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
        
        // Upload button
        document.getElementById('uploadFilesBtn').addEventListener('click', function() {
            const printType = document.querySelector('input[name="printType"]:checked').nextElementSibling.textContent;
            const paperSize = document.getElementById('paperSize').value;
            const paperType = document.getElementById('paperType').value;
            const bindingService = document.getElementById('bindingService').value;
            const copies = document.getElementById('copyInput').value;
            const instructions = document.getElementById('specialInstructions').value;
            
            alert(`Files uploaded with printing specifications:\nPrint Type: ${printType}\nPaper Size: ${paperSize}\nPaper Type: ${paperType}\nBinding Service: ${bindingService}\nCopies: ${copies}\nSpecial Instructions: ${instructions || 'None'}`);
        });
        
        // Upload area interaction
        const uploadArea = document.getElementById('uploadArea');
        uploadArea.addEventListener('click', function() {
            alert('File browser would open here. This is a UI mockup only.');
        });
        
        // Details buttons for uploaded files
        document.querySelectorAll('.details-btn').forEach(button => {
            button.addEventListener('click', function() {
                const fileName = this.closest('.file-item').querySelector('.file-name').textContent;
                const fileSize = this.closest('.file-item').querySelector('.file-details span:nth-child(1)').textContent;
                const uploadTime = this.closest('.file-item').querySelector('.file-details span:nth-child(2)').textContent;
                const status = this.closest('.file-item').querySelector('.file-status').textContent;
                
                alert(`File Details:\n\nName: ${fileName}\nSize: ${fileSize}\n${uploadTime}\nStatus: ${status}`);
            });
        });
        
        // File remove buttons
        document.querySelectorAll('.file-remove').forEach(button => {
            button.addEventListener('click', function() {
                const fileName = this.closest('.file-item').querySelector('.file-name').textContent;
                if (confirm(`Remove "${fileName}" from upload list?`)) {
                    this.closest('.file-item').remove();
                }
            });
        });
        
        // Number of copies control
        const copyDecrement = document.getElementById('copyDecrement');
        const copyIncrement = document.getElementById('copyIncrement');
        const copyInput = document.getElementById('copyInput');
        
        copyDecrement.addEventListener('click', function() {
            let value = parseInt(copyInput.value);
            if (value > 1) {
                copyInput.value = value - 1;
            }
        });
        
        copyIncrement.addEventListener('click', function() {
            let value = parseInt(copyInput.value);
            if (value < 20) {
                copyInput.value = value + 1;
            }
        });
    </script>
</body>
</html>