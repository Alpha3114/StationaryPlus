<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus - User Management</title>
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
        
        /* User Management Content */
        .user-management {
            flex-grow: 1;
            padding: 25px;
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 25px;
            height: calc(100vh - 100px);
            overflow: hidden;
        }
        
        /* User Table Section */
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
        
        .user-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        
        .user-table thead {
            background-color: rgba(168, 53, 53, 0.03);
            position: sticky;
            top: 0;
        }
        
        .user-table th {
            padding: 16px 18px;
            text-align: left;
            font-weight: 600;
            color: var(--text);
            font-size: 13px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        
        .user-table td {
            padding: 16px 18px;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
            color: var(--text);
            vertical-align: middle;
        }
        
        .user-table tbody tr:hover {
            background-color: rgba(168, 53, 53, 0.02);
            cursor: pointer;
        }
        
        .user-table tbody tr.selected {
            background-color: rgba(168, 53, 53, 0.05);
        }
        
        .user-id {
            font-weight: 600;
            color: var(--primary);
            font-size: 12px;
        }
        
        .user-name-cell {
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar-small {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: rgba(168, 53, 53, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-weight: 600;
            font-size: 13px;
            flex-shrink: 0;
        }
        
        .user-email {
            font-size: 13px;
            color: var(--light-text);
        }
        
        .user-type {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            text-align: center;
        }
        
        .type-customer {
            background-color: rgba(33, 150, 243, 0.1);
            color: #2196F3;
        }
        
        .type-staff {
            background-color: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }
        
        .type-admin {
            background-color: rgba(168, 53, 53, 0.15);
            color: var(--primary);
        }
        
        .user-status {
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
        
        .status-pending {
            background-color: rgba(244, 162, 97, 0.15);
            color: var(--secondary);
        }
        
        /* User Form Section */
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
        
        .form-select {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid var(--border);
            border-radius: 6px;
            font-size: 13px;
            transition: all 0.2s ease;
            background-color: var(--white);
            color: var(--text);
        }
        
        .form-select:focus {
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
        
        .warning-btn {
            flex: 1;
            padding: 12px;
            background-color: rgba(244, 162, 97, 0.1);
            color: var(--secondary);
            border: 1.5px solid var(--secondary);
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
        
        .warning-btn:hover {
            background-color: rgba(244, 162, 97, 0.2);
        }
        
        /* Empty form state */
        .empty-form {
            text-align: center;
            color: var(--light-text);
            font-size: 13px;
            padding: 30px 20px;
        }
        
        .empty-form i {
            font-size: 40px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .user-management {
                grid-template-columns: 1fr 340px;
                gap: 20px;
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
        
        @media (max-width: 900px) {
            .user-management {
                grid-template-columns: 1fr;
                grid-template-rows: 1fr 1fr;
            }
        }
        
        /* Table column widths */
        .user-table th:nth-child(1), .user-table td:nth-child(1) {
            width: 15%;
        }
        
        .user-table th:nth-child(2), .user-table td:nth-child(2) {
            width: 25%;
        }
        
        .user-table th:nth-child(3), .user-table td:nth-child(3) {
            width: 30%;
        }
        
        .user-table th:nth-child(4), .user-table td:nth-child(4) {
            width: 15%;
        }
        
        .user-table th:nth-child(5), .user-table td:nth-child(5) {
            width: 15%;
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
            <div>
                <div class="logo-text">StationaryPlus</div>
                <div class="admin-subtitle">Administration</div>
            </div>
        </div>
        
        <div class="nav-section">
            <div class="nav-title">Administration</div>
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
                            <i class="fas fa-users-cog"></i>
                        </div>
                        <div class="nav-text">User Management</div>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <div class="nav-text">Product Management</div>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <div class="nav-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="nav-text">Reports</div>
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="user-section">
            <div class="user-info">
                <div class="user-avatar">AD</div>
                <div class="user-details">
                    <div class="user-name">Admin User</div>
                    <div class="user-role">System Administrator</div>
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
                <h1>User Management</h1>
                <p>Manage user accounts and permissions</p>
            </div>
            <div class="header-right">
                Total Users: 124
            </div>
        </header>
        
        <!-- User Management Content -->
        <div class="user-management">
            <!-- Left Section: User Table -->
            <section class="table-section">
                <div class="section-header">
                    <h2><i class="fas fa-users"></i> User Directory</h2>
                </div>
                
                <div class="table-container">
                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>User Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="selected">
                                <td class="user-id">USR-001</td>
                                <td>
                                    <div class="user-name-cell">
                                        <div class="user-avatar-small">AF</div>
                                        Ahmad Faris
                                    </div>
                                </td>
                                <td class="user-email">ahmad.faris@email.com</td>
                                <td><span class="user-type type-customer">Customer</span></td>
                                <td><span class="user-status status-active">Active</span></td>
                            </tr>
                            <tr>
                                <td class="user-id">USR-002</td>
                                <td>
                                    <div class="user-name-cell">
                                        <div class="user-avatar-small">SJ</div>
                                        Sarah Johnson
                                    </div>
                                </td>
                                <td class="user-email">sarah.j@email.com</td>
                                <td><span class="user-type type-staff">Staff</span></td>
                                <td><span class="user-status status-active">Active</span></td>
                            </tr>
                            <tr>
                                <td class="user-id">USR-003</td>
                                <td>
                                    <div class="user-name-cell">
                                        <div class="user-avatar-small">MC</div>
                                        Michael Chen
                                    </div>
                                </td>
                                <td class="user-email">michael.chen@email.com</td>
                                <td><span class="user-type type-customer">Customer</span></td>
                                <td><span class="user-status status-inactive">Inactive</span></td>
                            </tr>
                            <tr>
                                <td class="user-id">USR-004</td>
                                <td>
                                    <div class="user-name-cell">
                                        <div class="user-avatar-small">LS</div>
                                        Lisa Smith
                                    </div>
                                </td>
                                <td class="user-email">lisa.smith@email.com</td>
                                <td><span class="user-type type-admin">Admin</span></td>
                                <td><span class="user-status status-active">Active</span></td>
                            </tr>
                            <tr>
                                <td class="user-id">USR-005</td>
                                <td>
                                    <div class="user-name-cell">
                                        <div class="user-avatar-small">RK</div>
                                        Robert Kim
                                    </div>
                                </td>
                                <td class="user-email">robert.k@email.com</td>
                                <td><span class="user-type type-staff">Staff</span></td>
                                <td><span class="user-status status-active">Active</span></td>
                            </tr>
                            <tr>
                                <td class="user-id">USR-006</td>
                                <td>
                                    <div class="user-name-cell">
                                        <div class="user-avatar-small">DG</div>
                                        David Garcia
                                    </div>
                                </td>
                                <td class="user-email">david.g@email.com</td>
                                <td><span class="user-type type-customer">Customer</span></td>
                                <td><span class="user-status status-active">Active</span></td>
                            </tr>
                            <tr>
                                <td class="user-id">USR-007</td>
                                <td>
                                    <div class="user-name-cell">
                                        <div class="user-avatar-small">EJ</div>
                                        Emily Jones
                                    </div>
                                </td>
                                <td class="user-email">emily.j@email.com</td>
                                <td><span class="user-type type-customer">Customer</span></td>
                                <td><span class="user-status status-inactive">Inactive</span></td>
                            </tr>
                            <tr>
                                <td class="user-id">USR-008</td>
                                <td>
                                    <div class="user-name-cell">
                                        <div class="user-avatar-small">TP</div>
                                        Thomas Parker
                                    </div>
                                </td>
                                <td class="user-email">thomas.p@email.com</td>
                                <td><span class="user-type type-staff">Staff</span></td>
                                <td><span class="user-status status-active">Active</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            
            <!-- Right Section: User Form -->
            <section class="form-section">
                <div class="form-header">
                    <h2><i class="fas fa-user-edit"></i> Add / Update User</h2>
                </div>
                
                <div class="form-container">
                    <!-- Empty form state -->
                    <div class="empty-form" id="emptyForm">
                        <i class="fas fa-user-plus"></i>
                        <p>Select a user from the table to edit, or fill the form to add a new user.</p>
                    </div>
                    
                    <!-- User form -->
                    <div id="userForm" style="display: none;">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-input" placeholder="Enter full name" value="Ahmad Faris">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-input" placeholder="Enter email address" value="ahmad.faris@email.com">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">User Type</label>
                            <select class="form-select">
                                <option value="customer" selected>Customer</option>
                                <option value="staff">Staff</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Account Status</label>
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
                                    <input type="radio" id="pending" name="status">
                                    <label class="radio-label" for="pending">Pending</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button class="primary-btn" id="addBtn">
                                <i class="fas fa-plus-circle"></i> Add User
                            </button>
                            <button class="secondary-btn" id="updateBtn">
                                <i class="fas fa-save"></i> Update
                            </button>
                            <button class="warning-btn" id="deactivateBtn">
                                <i class="fas fa-user-slash"></i> Deactivate
                            </button>
                        </div>
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
        document.querySelectorAll('.user-table tbody tr').forEach(row => {
            row.addEventListener('click', function() {
                // Remove selected class from all rows
                document.querySelectorAll('.user-table tbody tr').forEach(r => {
                    r.classList.remove('selected');
                });
                
                // Add selected class to clicked row
                this.classList.add('selected');
                
                // Show user form
                document.getElementById('emptyForm').style.display = 'none';
                document.getElementById('userForm').style.display = 'block';
                
                // Update form with user data
                const userName = this.querySelector('.user-name-cell').textContent.trim();
                const userEmail = this.querySelector('.user-email').textContent.trim();
                const userType = this.querySelector('.user-type').textContent.trim();
                const userStatus = this.querySelector('.user-status').textContent.trim();
                
                // Update form fields
                document.querySelectorAll('.form-input')[0].value = userName;
                document.querySelectorAll('.form-input')[1].value = userEmail;
                
                // Set user type
                const typeSelect = document.querySelector('.form-select');
                if (userType === 'Customer') typeSelect.value = 'customer';
                if (userType === 'Staff') typeSelect.value = 'staff';
                if (userType === 'Admin') typeSelect.value = 'admin';
                
                // Set status
                document.querySelectorAll('input[name="status"]').forEach(radio => {
                    radio.checked = false;
                    if (radio.nextElementSibling.textContent === userStatus) {
                        radio.checked = true;
                    }
                });
            });
        });
        
        // Form buttons
        document.getElementById('addBtn').addEventListener('click', function() {
            const name = document.querySelectorAll('.form-input')[0].value;
            const email = document.querySelectorAll('.form-input')[1].value;
            const userType = document.querySelector('.form-select').value;
            const status = document.querySelector('input[name="status"]:checked').nextElementSibling.textContent;
            
            alert(`New user would be added:\nName: ${name}\nEmail: ${email}\nUser Type: ${userType}\nStatus: ${status}\n\n(UI mockup only)`);
        });
        
        document.getElementById('updateBtn').addEventListener('click', function() {
            const name = document.querySelectorAll('.form-input')[0].value;
            alert(`User "${name}" would be updated (UI mockup only)`);
        });
        
        document.getElementById('deactivateBtn').addEventListener('click', function() {
            const name = document.querySelectorAll('.form-input')[0].value;
            if (confirm(`Deactivate user "${name}"? (UI mockup only)`)) {
                alert(`User "${name}" would be deactivated (UI mockup only)`);
            }
        });
        
        // Logout button
        document.querySelector('.logout-btn').addEventListener('click', function() {
            alert('Logout functionality would be implemented here (UI mockup only)');
        });
        
        // Initialize with first row selected
        document.querySelector('.user-table tbody tr').click();
    </script>
</body>
</html>