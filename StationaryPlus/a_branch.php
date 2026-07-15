<?php
require_once 'auth.php';
require_role(['STAFF', 'ADMIN']);
require_once 'db.php';

$userName = $_SESSION['user_name'];

// ── Filters ──────────────────────────────────────────────────
$search       = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status']      ?? 'all';
$validStatuses = ['ACTIVE', 'INACTIVE', 'RENOVATION'];
if (!in_array($filterStatus, $validStatuses)) $filterStatus = 'all';

$where  = ['1=1'];
$params = [];
$types  = '';

if ($search !== '') {
    $like     = "%$search%";
    $where[]  = "(branch_name LIKE ? OR branch_id LIKE ? OR address LIKE ? OR phone_number LIKE ?)";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= 'ssss';
}
if ($filterStatus !== 'all') {
    $where[]  = "status = ?";
    $params[] = $filterStatus;
    $types   .= 's';
}

$branches = [];
if ($conn) {
    $sql  = "SELECT branch_id, branch_name, address, phone_number, status
              FROM branches WHERE " . implode(' AND ', $where) . "
              ORDER BY FIELD(status, 'ACTIVE', 'RENOVATION', 'INACTIVE'), branch_id";
    $stmt = $conn->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $branches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus - Branch Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/tokens.css">
    <script src="assets/js/theme.js"></script>
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <style>
        :root {
            --primary: #A83535;      /* Brick Red */
            --secondary: #F4A261;    /* Muted Orange */
            --background: #FAFAFA;   /* Light Grey */
            --accent: #F1EDE8;
            --text-primary: #2E2E2E;         /* Dark Charcoal */
            --text-secondary: #707070;   /* Secondary Text */
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
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            overflow: hidden;
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
            color: var(--text-primary);
            margin-bottom: 4px;
            font-weight: 700;
        }
        
        .header-left p {
            font-size: 13px;
            color: var(--text-secondary);
        }
        
        .header-right {
            font-size: 13px;
            color: var(--text-secondary);
            background-color: var(--primary-tint-subtle);
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
            background-color: var(--primary-tint-subtle);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .filter-bar {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border);
            background: var(--primary-tint-subtle);
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-bar form { display:flex; gap:8px; flex-wrap:wrap; align-items:center; flex:1; }
        .search-wrap { position:relative; flex:1; min-width:160px; }
        .search-icon { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--text-secondary); font-size:13px; }
        .search-input { width:100%; padding:8px 10px 8px 32px; border:1.5px solid var(--border); border-radius:7px; font-size:13px; background:var(--white); }
        .search-input:focus { outline:none; border-color:var(--primary); }
        .filter-select { padding:8px 12px; border:1.5px solid var(--border); border-radius:7px; font-size:13px; background:var(--white); cursor:pointer; }
        .filter-select:focus { outline:none; border-color:var(--primary); }
        .filter-btn { padding:8px 16px; background:var(--primary); color:var(--on-primary); border:none; border-radius:7px; font-size:13px; font-weight:600; cursor:pointer; }
        .filter-btn:hover { background:var(--primary-dark); }
        .filter-clear { font-size:12px; color:var(--text-secondary); text-decoration:none; white-space:nowrap; }
        .filter-clear:hover { color:var(--primary); }

        
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
            overflow-y: auto;
        }
        
        .branch-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        
        .branch-table thead {
            background-color: var(--primary-tint-subtle);
            position: sticky;
            top: 0;
        }
        
        .branch-table th {
            padding: 16px 18px;
            text-align: left;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 13px;
            border-bottom: 1px solid var(--border);
        }
        
        .branch-table td {
            padding: 16px 18px;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .branch-table tbody tr:hover {
            background-color: var(--primary-tint-subtle);
            cursor: pointer;
        }

        .branch-table tbody tr.selected {
            background-color: var(--primary-tint-subtle);
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
            background-color: var(--primary-tint-medium);
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
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        .branch-address {
            font-size: 13px;
            color: var(--text-primary);
            line-height: 1.4;
            white-space: normal;
            word-wrap: break-word;
        }
        
        .branch-contact {
            font-size: 13px;
            color: var(--text-primary);
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
            background-color: var(--success-bg);
            color: var(--success);
        }
        
        .status-inactive {
            background-color: rgba(158, 158, 158, 0.15);
            color: #616161;
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
            background-color: var(--primary-tint-subtle);
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

        .form-mode-badge { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
        .badge-edit { background: var(--primary-tint-medium); color: var(--primary); }
        .badge-new  { background: var(--success-bg); color: var(--success); }

        .form-container {
            flex-grow: 1;
            padding: 25px;
            display: flex;
            flex-direction: column;
        }

        /* Placeholder state (shown before any selection) */
        .form-placeholder { display: flex; flex-direction: column; align-items: center; justify-content: center; flex-grow: 1; color: var(--text-secondary); text-align: center; padding: 30px; gap: 12px; }
        .form-placeholder i { font-size: 38px; opacity: 0.2; }
        .form-placeholder p { font-size: 14px; }

        /* Status toggle buttons (edit mode only) */
        .status-btn { flex: 1; padding: 12px; border-radius: 6px; font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .status-btn-activate { background-color: var(--success-bg); color: var(--success); border: 1.5px solid var(--success); }
        .status-btn-activate:hover { background-color: rgba(76,175,80,0.2); }
        .status-btn-deactivate { background-color: var(--danger-bg); color: var(--danger); border: 1.5px solid var(--danger); }
        .status-btn-deactivate:hover { background-color: rgba(239,68,68,0.16); }
        .renovation-link { font-size: 12px; color: var(--text-secondary); text-decoration: underline; cursor: pointer; background: none; border: none; padding: 0; }
        .renovation-link:hover { color: var(--secondary); }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-primary);
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
            color: var(--text-primary);
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary-tint-medium);
        }
        
        .form-textarea {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid var(--border);
            border-radius: 6px;
            font-size: 13px;
            transition: all 0.2s ease;
            background-color: var(--white);
            color: var(--text-primary);
            resize: vertical;
            min-height: 80px;
            font-family: inherit;
        }
        
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary-tint-medium);
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
            color: var(--text-primary);
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
            color: var(--on-primary);
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
            background-color: var(--primary-dark);
        }

        .secondary-btn {
            flex: 1;
            padding: 12px;
            background-color: var(--primary-tint-medium);
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
            background-color: var(--primary-tint-active);
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
        
        /* ── Custom Dialog (replaces native alert/confirm) ── */
        .custom-dialog-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center; }
        .custom-dialog-overlay.show { display:flex; }
        .custom-dialog-box { background:var(--white);border-radius:12px;width:90%;max-width:400px;padding:28px 26px 22px;box-shadow:0 20px 60px rgba(0,0,0,0.2);text-align:center;animation:dialogPop 0.15s ease; }
        @keyframes dialogPop { from{transform:scale(0.95);opacity:0;} to{transform:scale(1);opacity:1;} }
        .custom-dialog-icon { width:52px;height:52px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:22px; }
        .custom-dialog-icon.dialog-info { background:#eff6ff;color:#1d4ed8; }
        .custom-dialog-icon.dialog-success { background:var(--success-bg);color:var(--success); }
        .custom-dialog-icon.dialog-error { background:var(--danger-bg);color:var(--danger); }
        .custom-dialog-icon.dialog-warning { background:var(--warning-bg);color:var(--warning); }
        .custom-dialog-message { font-size:14px;color:#2E2E2E;line-height:1.6;margin-bottom:22px;white-space:pre-line; }
        .custom-dialog-actions { display:flex;gap:10px; }
        .custom-dialog-btn { flex:1;padding:11px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:none;transition:background 0.2s ease; }
        .custom-dialog-cancel { background:#F1EDE8;color:#2E2E2E;border:1.5px solid #E0E0E0; }
        .custom-dialog-cancel:hover { background:#e8e2da; }
        .custom-dialog-confirm { background:#A83535;color:var(--on-primary); }
        .custom-dialog-confirm:hover { background:var(--primary-dark); }
        .custom-dialog-danger { background:var(--danger);color:var(--on-primary); }
        .custom-dialog-danger:hover { background:#b91c1c; }
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

                <div class="filter-bar">
                    <form method="GET" action="a_branch.php" style="display:contents;">
                        <div class="search-wrap">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" name="search" class="search-input"
                                   placeholder="Search name, ID, address, phone…"
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <select name="status" class="filter-select">
                            <option value="all"        <?= $filterStatus==='all'        ? 'selected':'' ?>>All Statuses</option>
                            <option value="ACTIVE"     <?= $filterStatus==='ACTIVE'     ? 'selected':'' ?>>Active</option>
                            <option value="INACTIVE"   <?= $filterStatus==='INACTIVE'   ? 'selected':'' ?>>Inactive</option>
                            <option value="RENOVATION" <?= $filterStatus==='RENOVATION' ? 'selected':'' ?>>Renovation</option>
                        </select>
                        <button type="submit" class="filter-btn"><i class="fas fa-filter"></i> Filter</button>
                        <?php if ($search !== '' || $filterStatus !== 'all'): ?>
                        <a href="a_branch.php" class="filter-clear"><i class="fas fa-times"></i> Clear</a>
                        <?php endif; ?>
                    </form>
                    <button type="button" class="secondary-btn add-new-btn" id="newBtn">
                        <i class="fas fa-plus-circle"></i> Add New
                    </button>
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
                                <tr><td colspan="4" style="color:var(--text-secondary); padding:18px;">
                                    <?= ($search !== '' || $filterStatus !== 'all')
                                        ? 'No branches match your filters.'
                                        : 'No branches found in the database.' ?>
                                </td></tr>
                            <?php else: ?>
                                <?php foreach ($branches as $branch): ?>
                                    <tr data-id="<?= htmlspecialchars($branch['branch_id']) ?>"
                                        onclick="loadBranch(<?= htmlspecialchars(json_encode($branch), ENT_QUOTES) ?>)">
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
                                        <td><?php
                                            $statusVal = strtoupper(trim($branch['status']));
                                            $statusClass = match($statusVal) {
                                                'ACTIVE'     => 'status-active',
                                                'RENOVATION' => 'status-renovation',
                                                default      => 'status-inactive',
                                            };
                                        ?><span class="branch-status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($branch['status']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            
            <!-- Right Section: Branch Form -->
            <section class="form-section">
                <div class="form-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h2><i class="fas fa-edit"></i> <span id="formTitle">Branch Details</span></h2>
                    <span class="form-mode-badge badge-new" id="modeBadge" style="display:none;">New</span>
                </div>

                <div class="form-container" id="formContainer">

                    <!-- Placeholder (shown before any selection) -->
                    <div class="form-placeholder" id="formPlaceholder">
                        <i class="fas fa-store"></i>
                        <p>Select a branch from the table to edit,<br>or click <strong>Add New</strong> to create one.</p>
                    </div>

                    <div id="branchFormFields" style="display:none; flex-direction:column; flex-grow:1;">
                        <div class="form-group">
                            <label class="form-label">Branch ID</label>
                            <input type="text" name="branch_id" id="fieldBranchId" class="form-input" placeholder="Auto-generated or enter ID">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Branch Name</label>
                            <input type="text" class="form-input" id="fieldBranchName" placeholder="Enter branch name">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Address</label>
                            <textarea class="form-textarea" id="fieldAddress" placeholder="Enter branch address"></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="text" class="form-input" id="fieldPhone" placeholder="Enter phone number">
                        </div>

                        <!-- Status: hidden in Add mode (new branches are always Active) -->
                        <input type="hidden" id="fieldStatus" value="ACTIVE">
                        <div class="form-group" id="statusFieldWrap" style="display:none;">
                            <label class="form-label">Status</label>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <span class="branch-status" id="statusBadgeDisplay"></span>
                                <button type="button" class="renovation-link" id="renovationLink" onclick="toggleBranchStatus('RENOVATION')">
                                    Set to Renovation
                                </button>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button class="primary-btn" id="saveBtn">
                                <i class="fas fa-save"></i> <span id="submitLabel">Add Branch</span>
                            </button>
                            <button type="button" class="status-btn status-btn-activate" id="activateBtn" style="display:none;"
                                    onclick="toggleBranchStatus('ACTIVE')">
                                <i class="fas fa-check-circle"></i> Activate
                            </button>
                            <button type="button" class="status-btn status-btn-deactivate" id="deactivateBtn" style="display:none;"
                                    onclick="toggleBranchStatus('INACTIVE')">
                                <i class="fas fa-ban"></i> Deactivate
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>
    
    <!-- Custom Dialog -->
    <div id="customDialogOverlay" class="custom-dialog-overlay">
        <div class="custom-dialog-box">
            <div class="custom-dialog-icon" id="customDialogIcon"><i class="fas fa-info-circle"></i></div>
            <p class="custom-dialog-message" id="customDialogMessage"></p>
            <div class="custom-dialog-actions">
                <button class="custom-dialog-btn custom-dialog-cancel" id="customDialogCancelBtn" style="display:none;">Cancel</button>
                <button class="custom-dialog-btn custom-dialog-confirm" id="customDialogConfirmBtn">OK</button>
            </div>
        </div>
    </div>

    <script>
        // ── Custom Dialog System (replaces native alert()/confirm()) ──
        const ICONS = {
            info:    '<i class="fas fa-info-circle"></i>',
            success: '<i class="fas fa-check-circle"></i>',
            error:   '<i class="fas fa-exclamation-circle"></i>',
            warning: '<i class="fas fa-exclamation-triangle"></i>',
        };
        function customAlert(message, type = 'info') {
            return new Promise(resolve => {
                const overlay = document.getElementById('customDialogOverlay');
                const icon = document.getElementById('customDialogIcon');
                const msgEl = document.getElementById('customDialogMessage');
                const cancelBtn = document.getElementById('customDialogCancelBtn');
                const confirmBtn = document.getElementById('customDialogConfirmBtn');
                msgEl.textContent = message;
                icon.className = 'custom-dialog-icon dialog-' + type;
                icon.innerHTML = ICONS[type] || ICONS.info;
                cancelBtn.style.display = 'none';
                confirmBtn.textContent = 'OK';
                confirmBtn.className = 'custom-dialog-btn custom-dialog-confirm';
                overlay.classList.add('show');
                const onOk = () => { overlay.classList.remove('show'); confirmBtn.removeEventListener('click', onOk); resolve(); };
                confirmBtn.addEventListener('click', onOk);
            });
        }
        function customConfirm(message, options = {}) {
            return new Promise(resolve => {
                const overlay = document.getElementById('customDialogOverlay');
                const icon = document.getElementById('customDialogIcon');
                const msgEl = document.getElementById('customDialogMessage');
                const cancelBtn = document.getElementById('customDialogCancelBtn');
                const confirmBtn = document.getElementById('customDialogConfirmBtn');
                const type = options.danger ? 'warning' : 'info';
                msgEl.textContent = message;
                icon.className = 'custom-dialog-icon dialog-' + type;
                icon.innerHTML = options.danger ? ICONS.warning : '<i class="fas fa-question-circle"></i>';
                cancelBtn.style.display = 'inline-flex';
                cancelBtn.textContent = options.cancelText || 'Cancel';
                confirmBtn.textContent = options.confirmText || 'Confirm';
                confirmBtn.className = 'custom-dialog-btn ' + (options.danger ? 'custom-dialog-danger' : 'custom-dialog-confirm');
                overlay.classList.add('show');
                const cleanup = (result) => {
                    overlay.classList.remove('show');
                    confirmBtn.removeEventListener('click', onYes);
                    cancelBtn.removeEventListener('click', onNo);
                    resolve(result);
                };
                const onYes = () => cleanup(true);
                const onNo  = () => cleanup(false);
                confirmBtn.addEventListener('click', onYes);
                cancelBtn.addEventListener('click', onNo);
            });
        }
    </script>

    <script>
        // Navigation is handled dynamically - links work normally

        // Show/hide the Details form vs. the placeholder
        function showForm(show) {
            document.getElementById('formPlaceholder').style.display  = show ? 'none' : 'flex';
            document.getElementById('branchFormFields').style.display = show ? 'flex' : 'none';
        }

        function updateStatusControls(status) {
            document.getElementById('fieldStatus').value = status;
            document.getElementById('statusFieldWrap').style.display = 'block';

            const badge = document.getElementById('statusBadgeDisplay');
            const cls = status === 'ACTIVE' ? 'status-active' : (status === 'RENOVATION' ? 'status-renovation' : 'status-inactive');
            badge.textContent = status.charAt(0) + status.slice(1).toLowerCase();
            badge.className = 'branch-status ' + cls;

            document.getElementById('activateBtn').style.display   = status === 'ACTIVE'   ? 'none' : 'flex';
            document.getElementById('deactivateBtn').style.display = status === 'INACTIVE' ? 'none' : 'flex';
            document.getElementById('renovationLink').style.display = status === 'RENOVATION' ? 'none' : 'inline';
        }

        // Populate the form from a clicked table row's branch data
        function loadBranch(b) {
            showForm(true);

            const idInput = document.getElementById('fieldBranchId');
            idInput.value = b.branch_id;
            idInput.setAttribute('readonly', 'readonly');
            document.getElementById('fieldBranchName').value = b.branch_name;
            document.getElementById('fieldAddress').value = b.address;
            document.getElementById('fieldPhone').value = b.phone_number;

            updateStatusControls((b.status || 'ACTIVE').toUpperCase());

            document.getElementById('formTitle').textContent = 'Edit Branch';
            document.getElementById('modeBadge').textContent = 'Edit';
            document.getElementById('modeBadge').className   = 'form-mode-badge badge-edit';
            document.getElementById('modeBadge').style.display = 'inline-block';
            document.getElementById('submitLabel').textContent = 'Update Branch';

            document.querySelectorAll('.branch-table tbody tr').forEach(r => r.classList.remove('selected'));
            const row = document.querySelector(`.branch-table tbody tr[data-id="${b.branch_id}"]`);
            if (row) row.classList.add('selected');
        }

        // Instant status change (edit mode only) — Activate / Deactivate / Set to Renovation
        async function toggleBranchStatus(targetStatus) {
            const id   = document.getElementById('fieldBranchId').value;
            const name = document.getElementById('fieldBranchName').value;
            const labels = { ACTIVE: 'Activate', INACTIVE: 'Deactivate', RENOVATION: 'set to Renovation' };
            const verb = labels[targetStatus];

            const ok = await customConfirm(`${verb} "${name}"?`, { danger: targetStatus !== 'ACTIVE', confirmText: verb.charAt(0).toUpperCase() + verb.slice(1) });
            if (!ok) return;

            const formData = new FormData();
            formData.append('branch_id', id);
            formData.append('target_status', targetStatus);

            try {
                const res = await fetch('toggle_branch_status.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    await customAlert('Branch status updated successfully.', 'success');
                    window.location.reload();
                } else {
                    await customAlert('Status change failed: ' + (data.error || 'unknown error'), 'error');
                }
            } catch (err) {
                await customAlert('Request error: ' + err.message, 'error');
            }
        }

        // Save branch button (sends to save_branch.php)
        document.getElementById('saveBtn').addEventListener('click', async function() {
            const id = document.getElementById('fieldBranchId').value.trim();
            const name = document.getElementById('fieldBranchName').value.trim();
            const address = document.getElementById('fieldAddress').value.trim();
            const phone = document.getElementById('fieldPhone').value.trim();
            const status = document.getElementById('fieldStatus').value;

            const formData = new FormData();
            formData.append('branch_id', id);
            formData.append('branch_name', name);
            formData.append('address', address);
            formData.append('phone_number', phone);
            formData.append('status', status);

            try {
                const res = await fetch('save_branch.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    await customAlert('Branch saved successfully (' + (data.action || 'saved') + ').', 'success');
                    window.location.reload();
                } else {
                    await customAlert('Save failed: ' + (data.error || 'unknown error'), 'error');
                }
            } catch (err) {
                await customAlert('Request error: ' + err.message, 'error');
            }
        });

        // New branch button — reveal the form in "new" mode (status hidden, defaults to Active)
        document.getElementById('newBtn').addEventListener('click', clearForm);

        // Logout button (real link via a_sidebar.php — no JS interception needed)

        // Phone number input validation (optional formatting)
        const contactInput = document.getElementById('fieldPhone');
        if (contactInput) {
            contactInput.addEventListener('input', function() {
                // Allow numbers, hyphens, spaces, and parentheses
                this.value = this.value.replace(/[^0-9\s\-()]/g, '');
            });
        }

        // New branch mode: clear fields, hide status controls (new branches are always Active)
        function clearForm() {
            showForm(true);

            const idInput = document.getElementById('fieldBranchId');
            idInput.value = '';
            idInput.removeAttribute('readonly');
            document.getElementById('fieldBranchName').value = '';
            document.getElementById('fieldAddress').value = '';
            document.getElementById('fieldPhone').value = '';
            document.getElementById('fieldStatus').value = 'ACTIVE';
            document.getElementById('statusFieldWrap').style.display = 'none';
            document.getElementById('activateBtn').style.display = 'none';
            document.getElementById('deactivateBtn').style.display = 'none';

            document.getElementById('formTitle').textContent = 'Add New Branch';
            document.getElementById('modeBadge').textContent = 'New';
            document.getElementById('modeBadge').className   = 'form-mode-badge badge-new';
            document.getElementById('modeBadge').style.display = 'inline-block';
            document.getElementById('submitLabel').textContent = 'Add Branch';

            document.querySelectorAll('.branch-table tbody tr').forEach(r => {
                r.classList.remove('selected');
            });
        }
    </script>
</body>
</html>