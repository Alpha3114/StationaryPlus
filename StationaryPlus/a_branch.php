<?php
require_once 'auth.php';
require_role(['STAFF', 'ADMIN']);
require_once 'db.php';

$userName = $_SESSION['user_name'];

$branches = [];
if ($conn) {
    $sql = "SELECT branch_id, branch_name, address, phone_number, status FROM branches ORDER BY branch_id";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $branches[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus - Branch Management</title>
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
            padding: 22px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
        }
        
        .logo-icon {
            background-color: var(--primary);
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            color: white;
            font-size: 18px;
        }
        
        .logo-text {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary);
        }
        
        .admin-subtitle {
            font-size: 12px;
            color: var(--light-text);
            margin-top: 2px;
        }
        
        .nav-section {
            padding: 18px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .nav-title {
            font-size: 12px;
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
            width: 18px;
            text-align: center;
            margin-right: 14px;
            font-size: 16px;
        }
        
        .nav-text {
            font-size: 14px;
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
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background-color: rgba(168, 53, 53, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-weight: 600;
            font-size: 15px;
            margin-right: 12px;
        }
        
        .user-details {
            flex-grow: 1;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--text);
            margin-bottom: 2px;
        }
        
        .user-role {
            font-size: 12px;
            color: var(--light-text);
        }
        
        .logout-btn {
            width: 100%;
            padding: 9px;
            background-color: rgba(168, 53, 53, 0.1);
            color: var(--primary);
            border: 1.5px solid var(--primary);
            border-radius: 5px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
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
            padding: 18px 28px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-left h1 {
            font-size: 22px;
            color: var(--text);
            margin-bottom: 4px;
            font-weight: 700;
        }
        
        .header-left p {
            font-size: 13px;
            color: var(--light-text);
        }
        
        .header-right {
            font-size: 13px;
            color: var(--light-text);
            background-color: rgba(168, 53, 53, 0.05);
            padding: 8px 15px;
            border-radius: 20px;
        }
        
        /* Branch Management Content - Two Column Layout */
        .branch-management {
            flex-grow: 1;
            padding: 25px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            height: calc(100vh - 100px);
            overflow: hidden;
        }
        
        /* Left Section: Branch Table */
        .table-section {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .section-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            background-color: rgba(168, 53, 53, 0.03);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-header h2 {
            font-size: 18px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-header h2 i {
            font-size: 18px;
        }
        
        .table-container {
            flex-grow: 1;
            overflow: hidden;
        }
        
        .branch-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        
        .branch-table thead {
            background-color: rgba(168, 53, 53, 0.03);
            position: sticky;
            top: 0;
        }
        
        .branch-table th {
            padding: 16px 18px;
            text-align: left;
            font-weight: 600;
            color: var(--text);
            font-size: 13px;
            border-bottom: 1px solid var(--border);
        }
        
        .branch-table td {
            padding: 16px 18px;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
            color: var(--text);
            vertical-align: middle;
        }
        
        .branch-table tbody tr:hover {
            background-color: rgba(168, 53, 53, 0.02);
            cursor: pointer;
        }
        
        .branch-table tbody tr.selected {
            background-color: rgba(168, 53, 53, 0.05);
        }
        
        .branch-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .branch-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background-color: rgba(168, 53, 53, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 16px;
            flex-shrink: 0;
        }
        
        .branch-name {
            font-weight: 600;
            font-size: 13px;
        }
        
        .branch-code {
            font-size: 11px;
            color: var(--light-text);
            margin-top: 2px;
        }
        
        .branch-address {
            font-size: 13px;
            color: var(--text);
            line-height: 1.4;
            max-height: 40px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .branch-contact {
            font-size: 13px;
            color: var(--text);
        }
        
        .branch-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            text-align: center;
        }
        
        .status-active {
            background-color: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }
        
        .status-inactive {
            background-color: rgba(158, 158, 158, 0.15);
            color: #757575;
        }
        
        .status-renovation {
            background-color: rgba(244, 162, 97, 0.15);
            color: var(--secondary);
        }
        
        /* Right Section: Branch Form */
        .form-section {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .form-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            background-color: rgba(168, 53, 53, 0.03);
        }
        
        .form-header h2 {
            font-size: 18px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-header h2 i {
            font-size: 18px;
        }
        
        .form-container {
            flex-grow: 1;
            padding: 25px;
            display: flex;
            flex-direction: column;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text);
            font-size: 13px;
        }
        
        .form-input {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid var(--border);
            border-radius: 6px;
            font-size: 13px;
            transition: all 0.2s ease;
            background-color: var(--white);
            color: var(--text);
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(168, 53, 53, 0.1);
        }
        
        .form-textarea {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid var(--border);
            border-radius: 6px;
            font-size: 13px;
            transition: all 0.2s ease;
            background-color: var(--white);
            color: var(--text);
            resize: vertical;
            min-height: 80px;
            font-family: inherit;
        }
        
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(168, 53, 53, 0.1);
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
            margin-right: 6px;
            accent-color: var(--primary);
        }
        
        .radio-label {
            color: var(--text);
            font-size: 13px;
        }
        
        /* Form Actions */
        .form-actions {
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 12px;
        }
        
        .primary-btn {
            flex: 1;
            padding: 12px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .primary-btn:hover {
            background-color: #8b2a2a;
        }
        
        .secondary-btn {
            flex: 1;
            padding: 12px;
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
        
        .secondary-btn:hover {
            background-color: rgba(168, 53, 53, 0.2);
        }
        
        /* Table column widths */
        .branch-table th:nth-child(1), .branch-table td:nth-child(1) {
            width: 25%;
        }
        
        .branch-table th:nth-child(2), .branch-table td:nth-child(2) {
            width: 35%;
        }
        
        .branch-table th:nth-child(3), .branch-table td:nth-child(3) {
            width: 20%;
        }
        
        .branch-table th:nth-child(4), .branch-table td:nth-child(4) {
            width: 20%;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .branch-management {
                grid-template-columns: 1fr;
                grid-template-rows: 1fr 1fr;
            }
        }
        
        @media (max-width: 1024px) {
            :root {
                --sidebar-width: 70px;
            }
            
            .logo-text, .admin-subtitle, .nav-text, .user-details, .nav-title {
                display: none;
            }
            
            .logo-area, .nav-section, .user-section {
                padding: 18px 15px;
            }
            
            .logo-area {
                justify-content: center;
            }
            
            .nav-link {
                justify-content: center;
                padding: 14px;
                border-left: none;
                border-right: 4px solid transparent;
            }
            
            .nav-link:hover, .nav-link.active {
                border-left: none;
                border-right-color: var(--primary);
            }
            
            .nav-icon {
                margin-right: 0;
                font-size: 17px;
            }
            
            .logout-btn span {
                display: none;
            }
            
            .logout-btn {
                justify-content: center;
                padding: 9px;
            }
        }
    </style>
</head>
<body>
    <?php include 'a_sidebar.php'; ?>
    
    <!-- Main Content Area -->
    <main class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="header-left">
                <h1>Branch Management</h1>
                <p>Manage branch locations and contact information</p>
            </div>
            <div class="header-right">
                Total Branches: <?php echo count($branches); ?>
            </div>
        </header>
        
        <!-- Branch Management Content -->
        <div class="branch-management">
            <!-- Left Section: Branch Table -->
            <section class="table-section">
                <div class="section-header">
                    <h2><i class="fas fa-store"></i> Branch Directory</h2>
                </div>
                
                <div class="table-container">
                    <table class="branch-table">
                        <thead>
                            <tr>
                                <th>Branch Name</th>
                                <th>Address</th>
                                <th>Contact Number</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($branches)): ?>
                                <tr><td colspan="4" style="color:var(--light-text); padding:18px;">No branches found in the database.</td></tr>
                            <?php else: ?>
                                <?php foreach ($branches as $branch): ?>
                                    <tr>
                                        <td>
                                            <div class="branch-info">
                                                <div class="branch-icon">
                                                    <i class="fas fa-building"></i>
                                                </div>
                                                <div>
                                                    <div class="branch-name"><?php echo htmlspecialchars($branch['branch_name']); ?></div>
                                                    <div class="branch-code">ID: <?php echo htmlspecialchars($branch['branch_id']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="branch-address"><?php echo htmlspecialchars($branch['address']); ?></td>
                                        <td class="branch-contact"><?php echo htmlspecialchars($branch['phone_number']); ?></td>
                                        <td><span class="branch-status <?php echo (strtolower($branch['status']) === 'active') ? 'status-active' : 'status-inactive'; ?>"><?php echo htmlspecialchars($branch['status']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            
            <!-- Right Section: Branch Form -->
            <section class="form-section">
                <div class="form-header">
                    <h2><i class="fas fa-edit"></i> Branch Details</h2>
                </div>
                
                <div class="form-container">
                    <div class="form-group">
                        <label class="form-label">Branch ID</label>
                        <input type="text" name="branch_id" class="form-input" placeholder="Auto-generated or enter ID">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Branch Name</label>
                        <input type="text" class="form-input" placeholder="Enter branch name">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <textarea class="form-textarea" placeholder="Enter branch address"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" class="form-input" placeholder="Enter phone number">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <div class="radio-group">
                            <div class="radio-option">
                                <input type="radio" id="active" name="status" checked>
                                <label class="radio-label" for="active">Active</label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" id="inactive" name="status">
                                <label class="radio-label" for="inactive">Inactive</label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" id="renovation" name="status">
                                <label class="radio-label" for="renovation">Renovation</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button class="primary-btn" id="saveBtn">
                            <i class="fas fa-save"></i> Save Branch
                        </button>
                        <button class="secondary-btn" id="newBtn">
                            <i class="fas fa-plus-circle"></i> New Branch
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </main>
    
    <script>
        // Navigation is handled dynamically - links work normally
        
        // Table row selection
        document.querySelectorAll('.branch-table tbody tr').forEach(row => {
            row.addEventListener('click', function() {
                // Remove selected class from all rows
                document.querySelectorAll('.branch-table tbody tr').forEach(r => {
                    r.classList.remove('selected');
                });
                
                // Add selected class to clicked row
                this.classList.add('selected');
                
                // Get branch data from the row
                const branchId = this.querySelector('.branch-code').textContent.replace('ID: ', '').trim();
                const branchName = this.querySelector('.branch-name').textContent;
                const branchAddress = this.querySelector('.branch-address').textContent;
                const branchPhone = this.querySelector('.branch-contact').textContent;
                const branchStatus = this.querySelector('.branch-status').textContent.trim();
                
                // Update form fields
                const idInput = document.querySelector('input[name="branch_id"]');
                idInput.value = branchId;
                idInput.setAttribute('readonly', 'readonly');
                document.querySelector('.form-input[placeholder="Enter branch name"]').value = branchName;
                document.querySelector('.form-textarea[placeholder="Enter branch address"]').value = branchAddress;
                document.querySelector('.form-input[placeholder="Enter phone number"]').value = branchPhone;
                
                // Set status
                document.querySelectorAll('input[name="status"]').forEach(radio => {
                    radio.checked = false;
                    if (radio.nextElementSibling.textContent.trim() === branchStatus) {
                        radio.checked = true;
                    }
                });
            });
        });
        
        // Save branch button (sends to save_branch.php)
        document.getElementById('saveBtn').addEventListener('click', function() {
            const id = document.querySelector('input[name="branch_id"]').value.trim();
            const name = document.querySelector('.form-input[placeholder="Enter branch name"]').value.trim();
            const address = document.querySelector('.form-textarea[placeholder="Enter branch address"]').value.trim();
            const phone = document.querySelector('.form-input[placeholder="Enter phone number"]').value.trim();
            const status = document.querySelector('input[name="status"]:checked').nextElementSibling.textContent.trim();

            const formData = new FormData();
            formData.append('branch_id', id);
            formData.append('branch_name', name);
            formData.append('address', address);
            formData.append('phone_number', phone);
            formData.append('status', status);

            fetch('save_branch.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('Branch saved successfully (' + (data.action || 'saved') + ').');
                        window.location.reload();
                    } else {
                        alert('Save failed: ' + (data.error || 'unknown error'));
                    }
                })
                .catch(err => {
                    alert('Request error: ' + err.message);
                });
        });
        
        // New branch button
        document.getElementById('newBtn').addEventListener('click', function() {
            const idInput = document.querySelector('input[name="branch_id"]');
            idInput.value = '';
            idInput.removeAttribute('readonly');
            document.querySelector('.form-input[placeholder="Enter branch name"]').value = '';
            document.querySelector('.form-textarea[placeholder="Enter branch address"]').value = '';
            document.querySelector('.form-input[placeholder="Enter phone number"]').value = '';
            document.querySelector('input[name="status"][id="active"]').checked = true;
            
            // Deselect all table rows
            document.querySelectorAll('.branch-table tbody tr').forEach(r => {
                r.classList.remove('selected');
            });
        });
        
        // Logout button
        document.querySelector('.logout-btn').addEventListener('click', function() {
            alert('Logout functionality would be implemented here (UI mockup only)');
        });
        
        // Phone number input validation (optional formatting)
        const contactInput = document.querySelector('.form-input[placeholder="Enter phone number"]');
        if (contactInput) {
            contactInput.addEventListener('input', function() {
                // Allow numbers, hyphens, spaces, and parentheses
                this.value = this.value.replace(/[^0-9\s\-()]/g, '');
            });
        }
        
        // Initialize form with empty fields
        function clearForm() {
            const idInput = document.querySelector('input[name="branch_id"]');
            idInput.value = '';
            idInput.removeAttribute('readonly');
            document.querySelector('.form-input[placeholder="Enter branch name"]').value = '';
            document.querySelector('.form-textarea[placeholder="Enter branch address"]').value = '';
            document.querySelector('.form-input[placeholder="Enter phone number"]').value = '';
            document.querySelector('input[name="status"][id="active"]').checked = true;
            document.querySelectorAll('.branch-table tbody tr').forEach(r => {
                r.classList.remove('selected');
            });
        }
        
        clearForm();
    </script>
</body>
</html>