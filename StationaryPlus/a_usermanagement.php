<?php
// ============================================================
//  a_usermanagement.php — Admin User Management
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role('ADMIN');
require_once 'db.php';

// ── Helpers ───────────────────────────────────────────────────
function generate_user_id(): string {
    return 'USR-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

$message = '';
$msgType = '';

// ── Handle POST actions ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = trim($_POST['action']         ?? '');
    $userId     = trim($_POST['user_id']        ?? '');
    $name       = trim($_POST['name']           ?? '');
    $email      = trim($_POST['email']          ?? '');
    $phone      = trim($_POST['phone_number']   ?? '');
    $role       = trim($_POST['user_role']      ?? 'CUSTOMER');
    $status     = trim($_POST['account_status'] ?? 'ACTIVE');
    $password   = $_POST['password']            ?? '';
    $branchId   = trim($_POST['branch_id']      ?? '') ?: null;

    $validRoles = ['ADMIN', 'STAFF', 'CUSTOMER'];
    if (!in_array($role, $validRoles)) $role = 'CUSTOMER';

    // Branch only applies to STAFF — clear it for any other role
    if ($role !== 'STAFF') $branchId = null;

    // ── Add new user ──────────────────────────────────────────
    if ($action === 'add') {
        if ($name === '' || $email === '') {
            $message = 'Name and email are required.';
            $msgType = 'error';
        } elseif (strlen($password) < 8) {
            $message = 'Password must be at least 8 characters for new users.';
            $msgType = 'error';
        } elseif ($role === 'STAFF' && !$branchId) {
            $message = 'Please assign a branch for staff accounts.';
            $msgType = 'error';
        } else {
            // Check duplicate email
            $chk = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
            $chk->bind_param('s', $email);
            $chk->execute();
            $chk->store_result();
            if ($chk->num_rows > 0) {
                $message = 'A user with this email already exists.';
                $msgType = 'error';
            } else {
                $newId  = generate_user_id();
                $hash   = password_hash($password, PASSWORD_DEFAULT);
                // Accounts created directly by an admin are active immediately —
                // PENDING is only used for the customer self-registration/email-verification flow.
                $status = 'ACTIVE';
                $stmt   = $conn->prepare(
                    "INSERT INTO users (user_id, name, email, password_hash, phone_number, user_role, account_status, branch_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param('ssssssss', $newId, $name, $email, $hash, $phone, $role, $status, $branchId);
                $stmt->execute();
                $stmt->close();
                $message = "User <strong>$name</strong> added successfully (ID: $newId).";
                $msgType = 'success';
            }
            $chk->close();
        }

    // ── Update existing user ──────────────────────────────────
    // Name, email, and phone are locked from this form — they're set at
    // registration (and verified) or by the user themselves, and admin
    // edits here shouldn't be able to silently change them. Only role,
    // branch, and (for STAFF only) password can be changed here.
    } elseif ($action === 'update' && $userId !== '') {
        $exStmt = $conn->prepare("SELECT name, user_role FROM users WHERE user_id = ? LIMIT 1");
        $exStmt->bind_param('s', $userId);
        $exStmt->execute();
        $existing = $exStmt->get_result()->fetch_assoc();
        $exStmt->close();

        if (!$existing) {
            $message = 'User not found.';
            $msgType = 'error';
        } elseif ($role === 'STAFF' && !$branchId) {
            $message = 'Please assign a branch for staff accounts.';
            $msgType = 'error';
        } else {
            // Password may only be changed here for STAFF accounts —
            // staff don't self-register, so admin resetting their password directly is fine.
            $applyPassword = ($existing['user_role'] === 'STAFF' && $password !== '');

            if ($applyPassword) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare(
                    "UPDATE users SET user_role=?, password_hash=?, branch_id=? WHERE user_id=?"
                );
                $stmt->bind_param('ssss', $role, $hash, $branchId, $userId);
            } else {
                $stmt = $conn->prepare(
                    "UPDATE users SET user_role=?, branch_id=? WHERE user_id=?"
                );
                $stmt->bind_param('sss', $role, $branchId, $userId);
            }
            $stmt->execute();
            $stmt->close();
            $message = "User <strong>{$existing['name']}</strong> updated successfully.";
            $msgType = 'success';
        }

    // ── Activate / Deactivate toggle ──────────────────────────
    } elseif ($action === 'toggle_status' && $userId !== '') {
        $exStmt = $conn->prepare("SELECT account_status FROM users WHERE user_id = ? LIMIT 1");
        $exStmt->bind_param('s', $userId);
        $exStmt->execute();
        $existing = $exStmt->get_result()->fetch_assoc();
        $exStmt->close();

        if (!$existing) {
            $message = 'User not found.';
            $msgType = 'error';
        } else {
            $newStatus = ($existing['account_status'] === 'ACTIVE') ? 'INACTIVE' : 'ACTIVE';

            if ($newStatus === 'INACTIVE' && $userId === $_SESSION['user_id']) {
                $message = 'You cannot deactivate your own account.';
                $msgType = 'error';
            } else {
                $stmt = $conn->prepare("UPDATE users SET account_status=? WHERE user_id=?");
                $stmt->bind_param('ss', $newStatus, $userId);
                $stmt->execute();
                $stmt->close();
                $message = $newStatus === 'ACTIVE' ? 'User activated successfully.' : 'User deactivated successfully.';
                $msgType = 'success';
            }
        }
    }
}

// ── Fetch users with filters ──────────────────────────────────
$search      = trim($_GET['search']  ?? '');
$filterRole  = $_GET['role']         ?? 'all';
$filterStatus= $_GET['status']       ?? 'all';

$where  = ['1=1'];
$params = [];
$types  = '';

if ($search !== '') {
    $like     = "%$search%";
    $where[]  = "(name LIKE ? OR email LIKE ? OR user_id LIKE ?)";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= 'sss';
}
if ($filterRole !== 'all') {
    $where[]  = "user_role = ?";
    $params[] = $filterRole;
    $types   .= 's';
}
if ($filterStatus !== 'all') {
    $where[]  = "account_status = ?";
    $params[] = $filterStatus;
    $types   .= 's';
}

$whereSQL = implode(' AND ', $where);
$sql = "SELECT u.user_id, u.name, u.email, u.phone_number, u.user_role, u.account_status,
               u.branch_id, b.branch_name
        FROM users u
        LEFT JOIN branches b ON u.branch_id = b.branch_id
        WHERE $whereSQL ORDER BY u.name ASC LIMIT 200";

$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Branch list for the assignment dropdown ────────────────────
$branchList = $conn->query(
    "SELECT branch_id, branch_name FROM branches WHERE status = 'ACTIVE' ORDER BY branch_name"
)->fetch_all(MYSQLI_ASSOC);

// ── Counts for header ─────────────────────────────────────────
$totalUsers  = $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'] ?? 0;
$pendingCount= $conn->query("SELECT COUNT(*) AS c FROM users WHERE account_status='PENDING'")->fetch_assoc()['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus - User Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #A83535;
            --secondary: #F4A261;
            --background: #FAFAFA;
            --text: #2E2E2E;
            --light-text: #707070;
            --border: #E0E0E0;
            --white: #FFFFFF;
            --sidebar-width: 260px;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', system-ui, sans-serif; }

        body { background-color: var(--background); color: var(--text); min-height: 100vh; display: flex; }

        /* ── Sidebar (same tokens as a_sidebar.php) ── */
        .sidebar { width: var(--sidebar-width); background-color: var(--white); border-right: 1px solid var(--border); height: 100vh; position: fixed; left: 0; top: 0; display: flex; flex-direction: column; box-shadow: 2px 0 10px rgba(0,0,0,0.03); }
        .logo-area { padding: 22px; border-bottom: 1px solid var(--border); display: flex; align-items: center; }
        .logo-icon { background-color: var(--primary); width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px; color: white; font-size: 18px; }
        .logo-text { font-size: 18px; font-weight: 700; color: var(--primary); }
        .admin-subtitle { font-size: 12px; color: var(--light-text); margin-top: 2px; }
        .nav-section { padding: 18px 0; border-bottom: 1px solid var(--border); }
        .nav-title { font-size: 12px; font-weight: 600; color: var(--light-text); text-transform: uppercase; letter-spacing: 0.5px; padding: 0 22px 10px 22px; }
        .nav-menu { list-style: none; }
        .nav-item { margin-bottom: 2px; }
        .nav-link { display: flex; align-items: center; padding: 14px 22px; color: var(--text); text-decoration: none; transition: all 0.2s ease; border-left: 4px solid transparent; }
        .nav-link:hover { background-color: rgba(168,53,53,0.05); color: var(--primary); border-left-color: rgba(168,53,53,0.3); }
        .nav-link.active { background-color: rgba(168,53,53,0.08); color: var(--primary); border-left-color: var(--primary); font-weight: 600; }
        .nav-icon { width: 18px; text-align: center; margin-right: 14px; font-size: 16px; }
        .nav-text { font-size: 14px; }
        .user-section { margin-top: auto; padding: 20px; border-top: 1px solid var(--border); }
        .user-info { display: flex; align-items: center; margin-bottom: 15px; }
        .user-avatar { width: 38px; height: 38px; border-radius: 50%; background-color: rgba(168,53,53,0.1); display: flex; align-items: center; justify-content: center; color: var(--primary); font-weight: 600; font-size: 15px; margin-right: 12px; }
        .user-name { font-weight: 600; font-size: 14px; color: var(--text); }
        .user-role { font-size: 12px; color: var(--light-text); }
        .logout-link { display: flex; align-items: center; gap: 8px; padding: 10px 14px; background-color: rgba(168,53,53,0.08); color: var(--primary); border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; transition: background-color 0.2s; }
        .logout-link:hover { background-color: rgba(168,53,53,0.18); }

        /* ── Main ── */
        .main-content { flex-grow: 1; margin-left: var(--sidebar-width); min-height: 100vh; display: flex; flex-direction: column; overflow: hidden; }

        .top-header { background-color: var(--white); padding: 18px 28px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .header-left h1 { font-size: 22px; color: var(--text); margin-bottom: 4px; font-weight: 700; }
        .header-left p { font-size: 13px; color: var(--light-text); }
        .header-right { display: flex; gap: 10px; align-items: center; }
        .header-stat { font-size: 13px; color: var(--light-text); background-color: rgba(168,53,53,0.05); padding: 7px 14px; border-radius: 20px; }

        /* Alert */
        .alert { margin: 14px 25px 0; padding: 12px 16px; border-radius: 8px; font-size: 14px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .alert-error   { background: #fff0f0; color: #c62828; border: 1px solid #ef9a9a; }

        /* ── User Management Grid ── */
        .user-management { flex-grow: 1; padding: 20px 25px; display: grid; grid-template-columns: 1fr 380px; gap: 20px; height: calc(100vh - 100px); overflow: hidden; }

        /* ── Left: table section ── */
        .table-section { background-color: var(--white); border-radius: 10px; box-shadow: var(--card-shadow); border: 1px solid var(--border); display: flex; flex-direction: column; overflow: hidden; }

        /* Filter bar */
        .filter-bar { padding: 14px 18px; border-bottom: 1px solid var(--border); background: rgba(168,53,53,0.01); display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .filter-bar form { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; flex: 1; }
        .search-wrap { position: relative; flex: 1; min-width: 160px; }
        .search-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--light-text); font-size: 13px; }
        .search-input { width: 100%; padding: 8px 28px 8px 32px; border: 1.5px solid var(--border); border-radius: 7px; font-size: 13px; background: var(--white); }
        .search-input:focus { outline: none; border-color: var(--primary); }
        .search-clear { display: none; position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--light-text); cursor: pointer; font-size: 13px; padding: 2px 4px; }
        .search-clear:hover { color: var(--primary); }
        .search-clear.show { display: block; }
        .filter-select { padding: 8px 12px; border: 1.5px solid var(--border); border-radius: 7px; font-size: 13px; background: var(--white); cursor: pointer; }
        .filter-select:focus { outline: none; border-color: var(--primary); }
        .filter-btn { padding: 8px 16px; background: var(--primary); color: white; border: none; border-radius: 7px; font-size: 13px; font-weight: 600; cursor: pointer; }
        .filter-btn:hover { background: #8b2a2a; }
        .add-new-btn { padding: 8px 16px; background: rgba(168,53,53,0.08); color: var(--primary); border: 1.5px solid var(--primary); border-radius: 7px; font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap; display: flex; align-items: center; gap: 6px; }
        .add-new-btn:hover { background: rgba(168,53,53,0.16); }

        .section-header { padding: 14px 18px; border-bottom: 1px solid var(--border); background-color: rgba(168,53,53,0.03); display: flex; justify-content: space-between; align-items: center; }
        .section-header h2 { font-size: 16px; color: var(--primary); display: flex; align-items: center; gap: 10px; }
        .result-count { font-size: 12px; color: var(--light-text); background: rgba(168,53,53,0.08); padding: 3px 10px; border-radius: 20px; font-weight: 600; }

        .table-container { flex-grow: 1; overflow: auto; }

        .user-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .user-table thead { background-color: rgba(168,53,53,0.03); position: sticky; top: 0; z-index: 2; }
        .user-table th { padding: 13px 16px; text-align: left; font-weight: 600; color: var(--text); font-size: 12px; border-bottom: 1px solid var(--border); white-space: nowrap; }
        .user-table td { padding: 14px 16px; border-bottom: 1px solid var(--border); font-size: 13px; color: var(--text); vertical-align: middle; }
        .user-table tbody tr { transition: background 0.15s; cursor: pointer; }
        .user-table tbody tr:hover { background-color: rgba(168,53,53,0.03); }
        .user-table tbody tr.selected { background-color: rgba(168,53,53,0.07); }
        .user-table tbody tr:last-child td { border-bottom: none; }

        .user-id-cell { font-weight: 600; color: var(--primary); font-size: 11px; font-family: monospace; }
        .user-name-cell { display: flex; align-items: center; gap: 10px; }
        .user-avatar-sm { width: 30px; height: 30px; border-radius: 50%; background-color: rgba(168,53,53,0.1); display: flex; align-items: center; justify-content: center; color: var(--primary); font-weight: 700; font-size: 12px; flex-shrink: 0; }
        .user-email { font-size: 12px; color: var(--light-text); }

        .role-badge { display: inline-block; padding: 3px 9px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .role-CUSTOMER { background: rgba(33,150,243,0.1); color: #1565c0; }
        .role-STAFF    { background: rgba(76,175,80,0.1);  color: #2e7d32; }
        .role-ADMIN    { background: rgba(168,53,53,0.12); color: var(--primary); }

        .status-badge { display: inline-block; padding: 3px 9px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .status-ACTIVE   { background: rgba(76,175,80,0.1);  color: #2e7d32; }
        .status-INACTIVE { background: rgba(158,158,158,0.15); color: #616161; }
        .status-PENDING  { background: rgba(244,162,97,0.15); color: #e65100; }

        /* Column widths */
        .user-table th:nth-child(1), .user-table td:nth-child(1) { width: 14%; }
        .user-table th:nth-child(2), .user-table td:nth-child(2) { width: 22%; }
        .user-table th:nth-child(3), .user-table td:nth-child(3) { width: 28%; }
        .user-table th:nth-child(4), .user-table td:nth-child(4) { width: 14%; }
        .user-table th:nth-child(5), .user-table td:nth-child(5) { width: 12%; }
        .user-table th:nth-child(6), .user-table td:nth-child(6) { width: 10%; }

        /* Empty state */
        .empty-state { text-align: center; padding: 40px 20px; color: var(--light-text); font-size: 14px; }
        .empty-state i { font-size: 36px; opacity: 0.2; margin-bottom: 10px; display: block; }

        /* ── Right: form section ── */
        .form-section { background-color: var(--white); border-radius: 10px; box-shadow: var(--card-shadow); border: 1px solid var(--border); display: flex; flex-direction: column; overflow: hidden; }
        .form-header { padding: 16px 20px; border-bottom: 1px solid var(--border); background-color: rgba(168,53,53,0.03); display: flex; justify-content: space-between; align-items: center; }
        .form-header h2 { font-size: 16px; color: var(--primary); display: flex; align-items: center; gap: 8px; }
        .form-mode-badge { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
        .badge-edit   { background: rgba(168,53,53,0.1); color: var(--primary); }
        .badge-new    { background: rgba(76,175,80,0.1); color: #2e7d32; }

        .form-container { flex-grow: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; }

        .form-group { margin-bottom: 16px; }
        .form-label { display: block; margin-bottom: 6px; font-weight: 600; color: var(--text); font-size: 13px; }
        .form-label .optional { font-weight: 400; color: var(--light-text); font-size: 12px; }
        .form-input, .form-select { width: 100%; padding: 10px 13px; border: 1.5px solid var(--border); border-radius: 7px; font-size: 13px; transition: all 0.2s ease; background-color: var(--white); color: var(--text); }
        .form-input:focus, .form-select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 2px rgba(168,53,53,0.09); }
        .form-input[readonly] { background: #f5f5f5; color: var(--light-text); cursor: not-allowed; }
        .form-hint { font-size: 12px; color: var(--light-text); margin-top: 4px; }

        .radio-group { display: flex; gap: 18px; margin-top: 5px; flex-wrap: wrap; }
        .radio-option { display: flex; align-items: center; }
        .radio-option input { margin-right: 6px; accent-color: var(--primary); }
        .radio-label { color: var(--text); font-size: 13px; }

        .form-divider { border: none; border-top: 1px solid var(--border); margin: 16px 0; }

        .form-actions { margin-top: auto; padding-top: 16px; border-top: 1px solid var(--border); display: flex; gap: 10px; flex-wrap: wrap; }

        .btn-primary { flex: 1; padding: 11px; background-color: var(--primary); color: white; border: none; border-radius: 7px; font-weight: 600; font-size: 13px; cursor: pointer; transition: background-color 0.2s; display: flex; align-items: center; justify-content: center; gap: 7px; min-width: 100px; }
        .btn-primary:hover { background-color: #8b2a2a; }
        .btn-secondary { flex: 1; padding: 11px; background-color: rgba(168,53,53,0.08); color: var(--primary); border: 1.5px solid var(--primary); border-radius: 7px; font-weight: 600; font-size: 13px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 7px; min-width: 100px; }
        .btn-secondary:hover { background-color: rgba(168,53,53,0.16); }
        .btn-danger { flex: 1; padding: 11px; background-color: rgba(239,68,68,0.08); color: #c62828; border: 1.5px solid #ef9a9a; border-radius: 7px; font-weight: 600; font-size: 13px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 7px; min-width: 100px; }
        .btn-danger:hover { background-color: rgba(239,68,68,0.16); }

        /* Placeholder state */
        .form-placeholder { display: flex; flex-direction: column; align-items: center; justify-content: center; flex-grow: 1; color: var(--light-text); text-align: center; padding: 30px; gap: 12px; }
        .form-placeholder i { font-size: 38px; opacity: 0.2; }
        .form-placeholder p { font-size: 14px; }

        /* Responsive */
        @media (max-width: 1200px) {
            .user-management { grid-template-columns: 1fr; height: auto; overflow: visible; }
            .table-section { height: 50vh; }
        }
        @media (max-width: 1024px) {
            :root { --sidebar-width: 70px; }
            .logo-text, .admin-subtitle, .nav-text, .user-role, .user-name, .logout-link span, .nav-title { display: none; }
            .logo-area, .nav-section, .user-section { padding: 16px 12px; }
            .nav-link { justify-content: center; padding: 14px; border-left: none; border-right: 4px solid transparent; }
            .nav-link:hover, .nav-link.active { border-left: none; border-right-color: var(--primary); }
            .nav-icon { margin-right: 0; font-size: 18px; }
            .logout-link { justify-content: center; padding: 10px; }
            .user-info { justify-content: center; }
            .user-avatar { margin-right: 0; }
        }
        /* ── Custom Dialog (replaces native alert/confirm) ── */
        .custom-dialog-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center; }
        .custom-dialog-overlay.show { display:flex; }
        .custom-dialog-box { background:white;border-radius:12px;width:90%;max-width:400px;padding:28px 26px 22px;box-shadow:0 20px 60px rgba(0,0,0,0.2);text-align:center;animation:dialogPop 0.15s ease; }
        @keyframes dialogPop { from{transform:scale(0.95);opacity:0;} to{transform:scale(1);opacity:1;} }
        .custom-dialog-icon { width:52px;height:52px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:22px; }
        .custom-dialog-icon.dialog-info { background:#eff6ff;color:#1d4ed8; }
        .custom-dialog-icon.dialog-success { background:#ecfdf5;color:#059669; }
        .custom-dialog-icon.dialog-error { background:#fef2f2;color:#dc2626; }
        .custom-dialog-icon.dialog-warning { background:#fffbeb;color:#d97706; }
        .custom-dialog-message { font-size:14px;color:#2E2E2E;line-height:1.6;margin-bottom:22px;white-space:pre-line; }
        .custom-dialog-actions { display:flex;gap:10px; }
        .custom-dialog-btn { flex:1;padding:11px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:none;transition:background 0.2s ease; }
        .custom-dialog-cancel { background:#F1EDE8;color:#2E2E2E;border:1.5px solid #E0E0E0; }
        .custom-dialog-cancel:hover { background:#e8e2da; }
        .custom-dialog-confirm { background:#A83535;color:white; }
        .custom-dialog-confirm:hover { background:#8b2a2a; }
        .custom-dialog-danger { background:#dc2626;color:white; }
        .custom-dialog-danger:hover { background:#b91c1c; }
    </style>
</head>
<body>

<?php include 'a_sidebar.php'; ?>

<main class="main-content">
    <header class="top-header">
        <div class="header-left">
            <h1>User Management</h1>
            <p>Manage user accounts, roles, and access levels</p>
        </div>
        <div class="header-right">
            <?php if ($pendingCount > 0): ?>
                <div class="header-stat" style="background:rgba(244,162,97,0.12);color:#e65100;">
                    <i class="fas fa-clock"></i> <?= $pendingCount ?> Pending
                </div>
            <?php endif; ?>
            <div class="header-stat">Total Users: <?= $totalUsers ?></div>
        </div>
    </header>

    <?php if ($message): ?>
    <div class="alert alert-<?= $msgType ?>">
        <i class="fas <?= $msgType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
        <?= $message ?>
    </div>
    <?php endif; ?>

    <div class="user-management">

        <!-- ── LEFT: User Table ── -->
        <section class="table-section">

            <!-- Filter bar -->
            <div class="filter-bar">
                <form method="GET" action="a_usermanagement.php" style="display:contents;">
                    <div class="search-wrap">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" name="search" id="userSearchInput" class="search-input"
                               placeholder="Search name, email, ID…"
                               value="<?= htmlspecialchars($search) ?>"
                               oninput="document.getElementById('userSearchClear').classList.toggle('show', this.value.length > 0)">
                        <button type="button" id="userSearchClear" class="search-clear <?= $search !== '' ? 'show' : '' ?>"
                                title="Clear search"
                                onclick="document.getElementById('userSearchInput').value='';this.classList.remove('show');this.form.submit();">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <select name="role" class="filter-select">
                        <option value="all"     <?= $filterRole==='all'      ? 'selected':'' ?>>All Roles</option>
                        <option value="CUSTOMER"<?= $filterRole==='CUSTOMER' ? 'selected':'' ?>>Customer</option>
                        <option value="STAFF"   <?= $filterRole==='STAFF'    ? 'selected':'' ?>>Staff</option>
                        <option value="ADMIN"   <?= $filterRole==='ADMIN'    ? 'selected':'' ?>>Admin</option>
                    </select>
                    <select name="status" class="filter-select">
                        <option value="all"     <?= $filterStatus==='all'      ? 'selected':'' ?>>All Statuses</option>
                        <option value="ACTIVE"  <?= $filterStatus==='ACTIVE'   ? 'selected':'' ?>>Active</option>
                        <option value="INACTIVE"<?= $filterStatus==='INACTIVE' ? 'selected':'' ?>>Inactive</option>
                        <option value="PENDING" <?= $filterStatus==='PENDING'  ? 'selected':'' ?>>Pending</option>
                    </select>
                    <button type="submit" class="filter-btn"><i class="fas fa-filter"></i> Filter</button>
                </form>
                <button class="add-new-btn" id="addNewBtn" onclick="clearForm()">
                    <i class="fas fa-plus"></i> Add New
                </button>
            </div>

            <div class="section-header">
                <h2><i class="fas fa-users"></i> User Directory</h2>
                <span class="result-count"><?= count($users) ?> user<?= count($users)!==1?'s':'' ?></span>
            </div>

            <div class="table-container">
                <?php if (empty($users)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>No users match the selected filters.</p>
                    </div>
                <?php else: ?>
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Branch</th>
                            <th>Status</th>
                            <th>Phone</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u):
                            $initial = strtoupper(mb_substr($u['name'], 0, 1));
                        ?>
                        <tr onclick="loadUser(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)"
                            data-id="<?= htmlspecialchars($u['user_id']) ?>">
                            <td><span class="user-id-cell"><?= htmlspecialchars($u['user_id']) ?></span></td>
                            <td>
                                <div class="user-name-cell">
                                    <div class="user-avatar-sm"><?= htmlspecialchars($initial) ?></div>
                                    <?= htmlspecialchars($u['name']) ?>
                                </div>
                            </td>
                            <td class="user-email"><?= htmlspecialchars($u['email']) ?></td>
                            <td><span class="role-badge role-<?= $u['user_role'] ?>"><?= ucfirst(strtolower($u['user_role'])) ?></span></td>
                            <td style="font-size:12px;color:var(--light-text);">
                                <?php if ($u['user_role'] === 'STAFF'): ?>
                                    <?= $u['branch_name'] ? '<i class="fas fa-store" style="color:var(--primary);margin-right:4px;"></i>' . htmlspecialchars($u['branch_name']) : '<span style="color:#d97706;">Unassigned</span>' ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><span class="status-badge status-<?= $u['account_status'] ?>"><?= ucfirst(strtolower($u['account_status'])) ?></span></td>
                            <td style="color:var(--light-text);font-size:12px;"><?= htmlspecialchars($u['phone_number'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </section>

        <!-- ── RIGHT: User Form ── -->
        <section class="form-section">
            <div class="form-header">
                <h2><i class="fas fa-user-edit"></i> <span id="formTitle">User Details</span></h2>
                <span class="form-mode-badge badge-new" id="modeBadge">New</span>
            </div>

            <div class="form-container" id="formContainer">

                <!-- Placeholder (shown before any selection) -->
                <div class="form-placeholder" id="formPlaceholder">
                    <i class="fas fa-user-plus"></i>
                    <p>Select a user from the table to edit,<br>or click <strong>Add New</strong> to create one.</p>
                </div>

                <!-- The actual form (hidden until selection or Add New) -->
                <form method="POST" action="a_usermanagement.php?search=<?= urlencode($search) ?>&role=<?= urlencode($filterRole) ?>&status=<?= urlencode($filterStatus) ?>"
                      id="userForm" style="display:none; flex-direction:column; flex-grow:1;">

                    <input type="hidden" name="action" id="formAction" value="add">

                    <div class="form-group">
                        <label class="form-label">User ID <span class="optional">(auto-generated for new)</span></label>
                        <input type="text" name="user_id" id="fieldUserId" class="form-input" readonly placeholder="Auto-generated">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" id="fieldName" class="form-input" placeholder="Enter full name" required>
                        <div class="form-hint" id="nameLockHint" style="display:none;">Name is set at registration and can't be changed here.</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" id="fieldEmail" class="form-input" placeholder="email@domain.com" required>
                        <div class="form-hint" id="emailLockHint" style="display:none;">Email is verified at registration and can't be changed here.</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Phone Number <span class="optional">(optional)</span></label>
                        <input type="text" name="phone_number" id="fieldPhone" class="form-input" placeholder="e.g. 0123456789">
                        <div class="form-hint" id="phoneLockHint" style="display:none;">Phone is set by the user and can't be changed here.</div>
                    </div>

                    <div class="form-group" id="passwordFieldWrap">
                        <label class="form-label">Password <span class="optional" id="pwHint">(required for new user)</span></label>
                        <input type="password" name="password" id="fieldPassword" class="form-input" placeholder="Min. 8 characters" autocomplete="new-password">
                        <div class="form-hint" id="pwEditHint" style="display:none;">Only staff passwords can be reset from here. Leave blank to keep current password.</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">User Role</label>
                        <select name="user_role" id="fieldRole" class="form-select" onchange="toggleBranchField(); togglePasswordField();">
                            <option value="CUSTOMER">Customer</option>
                            <option value="STAFF">Staff</option>
                            <option value="ADMIN">Admin</option>
                        </select>
                    </div>

                    <div class="form-group" id="branchFieldWrap" style="display:none;">
                        <label class="form-label">
                            Assigned Branch
                            <span class="optional">(required for staff)</span>
                        </label>
                        <select name="branch_id" id="fieldBranch" class="form-select">
                            <option value="">— Select branch —</option>
                            <?php foreach ($branchList as $b): ?>
                            <option value="<?= htmlspecialchars($b['branch_id']) ?>">
                                <?= htmlspecialchars($b['branch_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-hint">Staff only see and manage data for their assigned branch.</div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary" id="submitBtn">
                            <i class="fas fa-save"></i> <span id="submitLabel">Add User</span>
                        </button>
                        <!-- Activate/Deactivate toggle — only shown in edit mode -->
                        <button type="button" class="btn-danger" id="statusToggleBtn" style="display:none;"
                                onclick="toggleUserStatus()">
                            <i class="fas fa-user-slash" id="statusToggleIcon"></i> <span id="statusToggleLabel">Deactivate</span>
                        </button>
                    </div>

                </form>

                <!-- Hidden status-toggle form — must be OUTSIDE the main form -->
                <form method="POST" id="statusToggleForm" style="display:none;"
                      action="a_usermanagement.php?search=<?= urlencode($search) ?>&role=<?= urlencode($filterRole) ?>&status=<?= urlencode($filterStatus) ?>">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="user_id" id="statusToggleUserId">
                </form>
            </div>
        </section>

    </div><!-- /.user-management -->

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
let editingUserId   = null;
let editingIsSelf   = false;

// Populate form from clicked row
function loadUser(u) {
    showForm(true);
    editingUserId = u.user_id;

    document.getElementById('formAction').value   = 'update';
    document.getElementById('fieldUserId').value  = u.user_id;
    document.getElementById('fieldName').value    = u.name;
    document.getElementById('fieldEmail').value   = u.email;
    document.getElementById('fieldPhone').value   = u.phone_number || '';
    document.getElementById('fieldPassword').value = '';
    document.getElementById('fieldRole').value    = u.user_role;
    document.getElementById('fieldBranch').value  = u.branch_id || '';
    toggleBranchField();

    // Name/email/phone are locked in edit mode — set at registration,
    // shouldn't be silently changed by an admin.
    ['fieldName', 'fieldEmail', 'fieldPhone'].forEach(id => {
        document.getElementById(id).readOnly = true;
    });
    document.getElementById('nameLockHint').style.display  = 'block';
    document.getElementById('emailLockHint').style.display = 'block';
    document.getElementById('phoneLockHint').style.display = 'block';

    togglePasswordField();

    // UI tweaks for edit mode
    document.getElementById('formTitle').textContent = 'Edit User';
    document.getElementById('modeBadge').textContent = 'Edit';
    document.getElementById('modeBadge').className   = 'form-mode-badge badge-edit';
    document.getElementById('submitLabel').textContent = 'Update User';

    // Activate/Deactivate toggle reflects the user's current status
    const isActive = u.account_status === 'ACTIVE';
    document.getElementById('statusToggleLabel').textContent = isActive ? 'Deactivate' : 'Activate';
    document.getElementById('statusToggleIcon').className    = isActive ? 'fas fa-user-slash' : 'fas fa-user-check';
    document.getElementById('statusToggleBtn').className     = 'btn-danger';
    document.getElementById('statusToggleBtn').style.display = 'flex';
    document.getElementById('statusToggleUserId').value      = u.user_id;

    // Highlight selected row
    document.querySelectorAll('.user-table tbody tr').forEach(r => r.classList.remove('selected'));
    const row = document.querySelector(`.user-table tbody tr[data-id="${u.user_id}"]`);
    if (row) row.classList.add('selected');
}

// Clear / new user mode
function clearForm() {
    showForm(true);
    editingUserId = null;

    document.getElementById('formAction').value   = 'add';
    document.getElementById('fieldUserId').value  = '';
    document.getElementById('fieldName').value    = '';
    document.getElementById('fieldEmail').value   = '';
    document.getElementById('fieldPhone').value   = '';
    document.getElementById('fieldPassword').value = '';
    document.getElementById('fieldRole').value    = 'CUSTOMER';
    document.getElementById('fieldBranch').value  = '';
    toggleBranchField();

    // New users are fully editable
    ['fieldName', 'fieldEmail', 'fieldPhone'].forEach(id => {
        document.getElementById(id).readOnly = false;
    });
    document.getElementById('nameLockHint').style.display  = 'none';
    document.getElementById('emailLockHint').style.display = 'none';
    document.getElementById('phoneLockHint').style.display = 'none';

    togglePasswordField();

    document.getElementById('formTitle').textContent = 'Add New User';
    document.getElementById('modeBadge').textContent = 'New';
    document.getElementById('modeBadge').className   = 'form-mode-badge badge-new';
    document.getElementById('submitLabel').textContent = 'Add User';
    document.getElementById('statusToggleBtn').style.display = 'none';

    document.querySelectorAll('.user-table tbody tr').forEach(r => r.classList.remove('selected'));
}

function showForm(show) {
    document.getElementById('formPlaceholder').style.display = show ? 'none' : 'flex';
    document.getElementById('userForm').style.display        = show ? 'flex' : 'none';
}

// Show/hide the branch dropdown based on selected role
function toggleBranchField() {
    const role = document.getElementById('fieldRole').value;
    const wrap = document.getElementById('branchFieldWrap');
    wrap.style.display = (role === 'STAFF') ? 'block' : 'none';
    if (role !== 'STAFF') {
        document.getElementById('fieldBranch').value = '';
    }
}

// Password is always available when adding a new user, but in edit mode
// it's only editable for STAFF accounts (customers/admins don't get their
// password reset from this generic form).
function togglePasswordField() {
    const isEditMode = (document.getElementById('formAction').value === 'update');
    const role        = document.getElementById('fieldRole').value;
    const wrap        = document.getElementById('passwordFieldWrap');
    const input       = document.getElementById('fieldPassword');
    const showField   = !isEditMode || role === 'STAFF';

    wrap.style.display = showField ? 'block' : 'none';
    if (!showField) input.value = '';

    document.getElementById('pwHint').textContent = !isEditMode
        ? '(required for new user)'
        : '(optional — leave blank to keep current password)';
    document.getElementById('pwEditHint').style.display = isEditMode ? 'block' : 'none';
}

// Require a branch when saving a STAFF account
document.getElementById('userForm').addEventListener('submit', function(e) {
    const role   = document.getElementById('fieldRole').value;
    const branch = document.getElementById('fieldBranch').value;
    if (role === 'STAFF' && !branch) {
        e.preventDefault();
        customAlert('Please assign a branch for this staff member before saving.', 'warning');
        document.getElementById('fieldBranch').focus();
    }
});

function toggleUserStatus() {
    const name        = document.getElementById('fieldName').value;
    const activating  = document.getElementById('statusToggleLabel').textContent === 'Activate';
    const verb        = activating ? 'Activate' : 'Deactivate';
    customConfirm(`${verb} "${name}"?`, {
        danger: !activating, confirmText: verb
    }).then(ok => {
        if (ok) document.getElementById('statusToggleForm').submit();
    });
}

// If a message was shown after POST (meaning the form was submitted), stay in the right mode
<?php if ($message && $msgType === 'success'): ?>
// After successful action, reset to placeholder
document.addEventListener('DOMContentLoaded', () => showForm(false));
<?php endif; ?>
</script>

</body>
</html>