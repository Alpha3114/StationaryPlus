<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus - Payment Record</title>
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
        
        /* Payment Record Form */
        .payment-form {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--text);
            font-size: 15px;
        }
        
        .form-label .optional {
            font-weight: normal;
            color: var(--light-text);
            font-size: 14px;
            margin-left: 5px;
        }
        
        .form-select, .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.2s ease;
            background-color: var(--white);
            color: var(--text);
        }
        
        .form-input.readonly {
            background-color: rgba(168, 53, 53, 0.05);
            color: var(--light-text);
            cursor: not-allowed;
        }
        
        .form-select:focus, .form-input:focus:not(.readonly) {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(168, 53, 53, 0.1);
        }
        
        .form-full-width {
            grid-column: span 2;
        }
        
        /* File Upload */
        .file-upload-container {
            grid-column: span 2;
            margin-top: 10px;
        }
        
        .file-upload-label {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .file-upload-box {
            border: 2px dashed var(--border);
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            background-color: rgba(168, 53, 53, 0.02);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .file-upload-box:hover {
            border-color: var(--primary);
            background-color: rgba(168, 53, 53, 0.05);
        }
        
        .file-upload-icon {
            font-size: 32px;
            color: var(--primary);
            margin-bottom: 10px;
            opacity: 0.7;
        }
        
        .file-upload-text {
            font-size: 15px;
            color: var(--light-text);
            margin-bottom: 5px;
        }
        
        .file-upload-hint {
            font-size: 13px;
            color: var(--light-text);
        }
        
        /* Selected File Display */
        .selected-file {
            margin-top: 15px;
            padding: 15px;
            background-color: rgba(168, 53, 53, 0.05);
            border-radius: 8px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .file-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .file-icon {
            color: var(--primary);
            font-size: 20px;
        }
        
        .file-name {
            font-weight: 600;
            color: var(--text);
            font-size: 14px;
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
            margin-top: 30px;
        }
        
        .primary-button {
            padding: 16px 40px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 12px;
        }
        
        .primary-button:hover {
            background-color: #8b2a2a;
        }
        
        /* Payment History */
        .payment-history {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid var(--border);
        }
        
        .history-title {
            font-size: 20px;
            color: var(--primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .history-title i {
            margin-right: 12px;
        }
        
        .history-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .history-table thead {
            background-color: rgba(168, 53, 53, 0.05);
            border-bottom: 2px solid var(--border);
        }
        
        .history-table th {
            padding: 16px 20px;
            text-align: left;
            font-weight: 600;
            color: var(--text);
            font-size: 14px;
        }
        
        .history-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background-color 0.2s ease;
        }
        
        .history-table tbody tr:hover {
            background-color: rgba(168, 53, 53, 0.02);
        }
        
        .history-table td {
            padding: 18px 20px;
            vertical-align: middle;
            color: var(--text);
            font-size: 14px;
        }
        
        .payment-ref {
            font-weight: 600;
            color: var(--primary);
        }
        
        .payment-status {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-verified {
            background-color: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }
        
        .status-pending {
            background-color: rgba(244, 162, 97, 0.1);
            color: var(--secondary);
            border: 1px solid rgba(244, 162, 97, 0.3);
        }
        
        .status-rejected {
            background-color: rgba(244, 67, 54, 0.1);
            color: #F44336;
            border: 1px solid rgba(244, 67, 54, 0.3);
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
        @media (max-width: 1100px) {
            .payment-form {
                grid-template-columns: 1fr;
            }
            
            .form-full-width, .file-upload-container {
                grid-column: span 1;
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
            
            .history-table {
                display: block;
                overflow-x: auto;
            }
        }
        
        @media (max-width: 768px) {
            .history-table th, 
            .history-table td {
                padding: 12px 10px;
                font-size: 13px;
            }
            
            .payment-status {
                min-width: 100px;
                font-size: 11px;
                padding: 5px 10px;
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
            <h1 class="page-title">Payment Record</h1>
            <p class="page-subtitle">Submit payment records for your pre-orders or orders</p>
        </header>
        
        <!-- Content Container -->
        <div class="content-container">
            <!-- Payment Record Section -->
            <div class="section-card">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-money-check-alt"></i> Payment Record Submission
                    </h2>
                    <p class="section-description">
                        Submit payment records for your pre-orders or orders. This is for record-keeping purposes only.
                    </p>
                </div>
                
                <div class="section-content">
                    <!-- Payment Record Form -->
                    <form class="payment-form">
                        <!-- Related Record Selection -->
                        <div class="form-group form-full-width">
                            <label class="form-label">Select Related Record (Tempahan / Pesanan)</label>
                            <select class="form-select">
                                <option value="">-- Select Tempahan or Pesanan --</option>
                                <option value="SP-2023-085" selected>#SP-2023-085 - Business Card Printing (Tempahan)</option>
                                <option value="SP-2023-089">#SP-2023-089 - A4 Paper & Printing Order (Tempahan)</option>
                                <option value="SP-2023-087">#SP-2023-087 - Thesis Printing & Binding (Pesanan)</option>
                                <option value="SP-2023-082">#SP-2023-082 - Stationery Set (Pesanan)</option>
                            </select>
                        </div>
                        
                        <!-- Payment Method -->
                        <div class="form-group">
                            <label class="form-label">Payment Method</label>
                            <select class="form-select">
                                <option value="">-- Select Payment Method --</option>
                                <option value="cash">Cash</option>
                                <option value="bank-transfer" selected>Bank Transfer</option>
                                <option value="ewallet">E-Wallet (Touch 'n Go, GrabPay, etc.)</option>
                                <option value="credit-card">Credit/Debit Card</option>
                            </select>
                        </div>
                        
                        <!-- Amount -->
                        <div class="form-group">
                            <label class="form-label">Amount (RM)</label>
                            <input type="text" class="form-input readonly" value="127.50" readonly>
                        </div>
                        
                        <!-- Reference Number -->
                        <div class="form-group">
                            <label class="form-label">Reference Number <span class="optional">(Optional)</span></label>
                            <input type="text" class="form-input" placeholder="e.g., TRX-123456789">
                        </div>
                        
                        <!-- Payment Date -->
                        <div class="form-group">
                            <label class="form-label">Payment Date</label>
                            <input type="date" class="form-input" value="2023-11-15">
                        </div>
                        
                        <!-- Proof Upload (only for e-wallet) -->
                        <div class="file-upload-container">
                            <div class="file-upload-label">
                                <label class="form-label">Proof Upload</label>
                                <span class="optional">(Required for E-Wallet payments only)</span>
                            </div>
                            <div class="file-upload-box" id="uploadBox">
                                <div class="file-upload-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <div class="file-upload-text">
                                    Click to upload payment screenshot/proof
                                </div>
                                <div class="file-upload-hint">
                                    Supported formats: JPG, PNG, PDF (Max 5MB)
                                </div>
                            </div>
                            
                            <!-- Selected File Display -->
                            <div class="selected-file" id="selectedFile" style="display: none;">
                                <div class="file-info">
                                    <div class="file-icon">
                                        <i class="fas fa-file-image"></i>
                                    </div>
                                    <div>
                                        <div class="file-name" id="fileName">payment_screenshot.png</div>
                                    </div>
                                </div>
                                <button type="button" class="file-remove" id="removeFile">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Submit Button -->
                    <div class="action-section">
                        <button class="primary-button" id="submitPayment">
                            <i class="fas fa-paper-plane"></i> Submit Payment Record
                        </button>
                    </div>
                    
                    <!-- Payment History -->
                    <div class="payment-history">
                        <h3 class="history-title">
                            <i class="fas fa-history"></i> Recent Payment Records
                        </h3>
                        
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Reference</th>
                                    <th>Order ID</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>14 Nov 2023</td>
                                    <td class="payment-ref">TRX-789012345</td>
                                    <td>#SP-2023-089</td>
                                    <td>RM 85.00</td>
                                    <td>Bank Transfer</td>
                                    <td>
                                        <span class="payment-status status-verified">Verified</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>12 Nov 2023</td>
                                    <td class="payment-ref">TRX-678901234</td>
                                    <td>#SP-2023-087</td>
                                    <td>RM 210.00</td>
                                    <td>E-Wallet</td>
                                    <td>
                                        <span class="payment-status status-verified">Verified</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>10 Nov 2023</td>
                                    <td class="payment-ref">TRX-567890123</td>
                                    <td>#SP-2023-085</td>
                                    <td>RM 127.50</td>
                                    <td>Cash</td>
                                    <td>
                                        <span class="payment-status status-pending">Pending</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>05 Nov 2023</td>
                                    <td class="payment-ref">TRX-456789012</td>
                                    <td>#SP-2023-082</td>
                                    <td>RM 45.90</td>
                                    <td>Credit Card</td>
                                    <td>
                                        <span class="payment-status status-verified">Verified</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>01 Nov 2023</td>
                                    <td class="payment-ref">TRX-345678901</td>
                                    <td>#SP-2023-079</td>
                                    <td>RM 68.00</td>
                                    <td>Bank Transfer</td>
                                    <td>
                                        <span class="payment-status status-rejected">Rejected</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
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
        
        // File Upload Functionality
        const uploadBox = document.getElementById('uploadBox');
        const selectedFile = document.getElementById('selectedFile');
        const fileName = document.getElementById('fileName');
        const removeFile = document.getElementById('removeFile');
        
        uploadBox.addEventListener('click', function() {
            // In a real application, this would trigger a file input dialog
            // For this UI mockup, we'll simulate file selection
            const simulatedFileName = "payment_proof_" + new Date().getTime() + ".png";
            fileName.textContent = simulatedFileName;
            selectedFile.style.display = 'flex';
            
            alert(`File "${simulatedFileName}" selected. This is a UI mockup only. In a real system, this would upload the file.`);
        });
        
        removeFile.addEventListener('click', function(e) {
            e.stopPropagation();
            selectedFile.style.display = 'none';
            alert("File removed. This is a UI mockup only.");
        });
        
        // Submit Payment Record button
        document.getElementById('submitPayment').addEventListener('click', function() {
            const selectedOrder = document.querySelector('.form-select').value;
            const paymentMethod = document.querySelectorAll('.form-select')[1].value;
            const amount = document.querySelector('.form-input.readonly').value;
            const reference = document.querySelectorAll('.form-input')[1].value || 'Not provided';
            const date = document.querySelectorAll('.form-input')[2].value;
            const hasProof = selectedFile.style.display !== 'none';
            
            // Validation (UI only)
            if (!selectedOrder) {
                alert("Please select a related record (Tempahan/Pesanan).");
                return;
            }
            
            if (!paymentMethod) {
                alert("Please select a payment method.");
                return;
            }
            
            if (paymentMethod === 'ewallet' && !hasProof) {
                alert("Please upload payment proof for E-Wallet payments.");
                return;
            }
            
            alert(`Payment Record Submitted:\n\nRelated Order: ${selectedOrder}\nPayment Method: ${paymentMethod}\nAmount: RM ${amount}\nReference: ${reference}\nPayment Date: ${date}\nProof Uploaded: ${hasProof ? 'Yes' : 'No'}\n\nThis is a UI mockup only. In a real system, this would submit data to a server.`);
            
            // Reset form after 1 second (UI simulation)
            setTimeout(() => {
                document.querySelectorAll('.form-select')[0].selectedIndex = 0;
                document.querySelectorAll('.form-select')[1].selectedIndex = 0;
                document.querySelectorAll('.form-input')[1].value = '';
                selectedFile.style.display = 'none';
                
                alert('Payment record submitted successfully! This is a UI mockup only.');
            }, 1000);
        });
        
        // Update amount based on selected order (UI simulation)
        const orderSelect = document.querySelectorAll('.form-select')[0];
        orderSelect.addEventListener('change', function() {
            if (this.value) {
                // Simulate different amounts based on selected order
                const amounts = {
                    'SP-2023-085': '127.50',
                    'SP-2023-089': '85.00',
                    'SP-2023-087': '210.00',
                    'SP-2023-082': '45.90'
                };
                
                const amountField = document.querySelector('.form-input.readonly');
                amountField.value = amounts[this.value] || '0.00';
            }
        });
        
        // Set initial amount for pre-selected order
        const amountField = document.querySelector('.form-input.readonly');
        amountField.value = '127.50';
        
        // Set today's date as default for payment date
        const today = new Date();
        const formattedDate = today.toISOString().split('T')[0];
        document.querySelector('input[type="date"]').value = formattedDate;
    </script>
</body>
</html>