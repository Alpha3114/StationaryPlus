<?php
// ============================================================
//  s_pos.php — Staff: Point of Sale (Walk-in Sales)
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role(['STAFF', 'ADMIN']);
require_once 'db.php';

$staffName    = $_SESSION['user_name'] ?? 'Staff';
$branchId     = $_SESSION['branch_id'] ?? null;
$branchActive = staff_branch_is_active($conn);

// Branch name for receipt header
$branchName = 'StationaryPlus';
if ($branchId) {
    $stmt = $conn->prepare("SELECT branch_name, address, phone_number FROM branches WHERE branch_id = ? LIMIT 1");
    $stmt->bind_param('s', $branchId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $branchName    = $row['branch_name'];
        $branchAddress = $row['address']      ?? '';
        $branchPhone   = $row['phone_number'] ?? '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus — Point of Sale</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- html5-qrcode for camera barcode scanning -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        /* ── Design tokens (match existing staff pages) ── */
        :root {
            --primary:        #A83535;
            --primary-dark:   #8b2a2a;
            --primary-light:  rgba(168,53,53,0.08);
            --secondary:      #F4A261;
            --accent:         #F1EDE8;
            --background:     #FAFAFA;
            --text-primary:   #2E2E2E;
            --text-secondary: #707070;
            --border:         #E0E0E0;
            --white:          #FFFFFF;
            --sidebar-width:  260px;
            --card-shadow:    0 4px 12px rgba(0,0,0,0.05);
            --success:        #10b981;
            --warning:        #f59e0b;
            --danger:         #ef4444;
        }

        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--background);
            color: var(--text-primary);
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* ── Sidebar (matches s_sidebar.php styles) ── */
        .sidebar { width:var(--sidebar-width);background:var(--white);border-right:1px solid var(--border);height:100vh;position:fixed;left:0;top:0;display:flex;flex-direction:column;box-shadow:2px 0 10px rgba(0,0,0,0.03);overflow-y:auto; }
        .logo-area { padding:25px;border-bottom:1px solid var(--border);display:flex;align-items:center;flex-shrink:0; }
        .logo-icon { background:var(--primary);width:40px;height:40px;border-radius:8px;display:flex;align-items:center;justify-content:center;margin-right:12px;color:white;font-size:20px; }
        .logo-text { font-size:22px;font-weight:700;color:var(--primary); }
        .nav-section { padding:20px 0;border-bottom:1px solid var(--border); }
        .nav-title { font-size:12px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.8px;padding:0 25px 10px; }
        .nav-menu { list-style:none; }
        .nav-item { margin-bottom:2px; }
        .nav-link { display:flex;align-items:center;padding:13px 25px;color:var(--text-primary);text-decoration:none;transition:all 0.2s;border-left:4px solid transparent;gap:12px; }
        .nav-link:hover { background:var(--primary-light);color:var(--primary);border-left-color:rgba(168,53,53,0.3); }
        .nav-link.active { background:var(--primary-light);color:var(--primary);border-left-color:var(--primary);font-weight:600; }
        .nav-icon { width:18px;text-align:center;font-size:15px; }
        .badge-pill { margin-left:auto;background:#ef4444;color:white;font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px; }
        .user-section { margin-top:auto;padding:18px 25px;border-top:1px solid var(--border); }
        .user-info { display:flex;align-items:center;gap:10px;margin-bottom:12px; }
        .user-avatar { width:36px;height:36px;background:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:15px;flex-shrink:0; }
        .user-name { font-size:14px;font-weight:600; }
        .user-role { font-size:11px;color:var(--text-secondary); }
        .logout-link { display:flex;align-items:center;gap:8px;padding:8px 12px;color:var(--text-secondary);text-decoration:none;border-radius:7px;font-size:13px;transition:all 0.2s; }
        .logout-link:hover { background:rgba(239,68,68,0.08);color:#ef4444; }

        /* ── Main content ── */
        .main { margin-left:var(--sidebar-width); display:flex; flex-direction:column; height:100vh; overflow:hidden; flex:1; }

        /* ── Top bar ── */
        .topbar {
            padding: 0 28px;
            height: 62px;
            border-bottom: 1px solid var(--border);
            background: var(--white);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        .topbar-title { font-size:18px;font-weight:700;color:var(--text-primary);display:flex;align-items:center;gap:10px; }
        .topbar-title i { color:var(--primary); }
        .topbar-meta { font-size:13px;color:var(--text-secondary);display:flex;align-items:center;gap:18px; }
        .topbar-meta span { display:flex;align-items:center;gap:6px; }
        .live-clock { font-weight:600;color:var(--text-primary); }

        /* ── POS body: two columns ── */
        .pos-body {
            display: grid;
            grid-template-columns: 1fr 380px;
            flex: 1;
            overflow: hidden;
        }

        /* ── LEFT: scanner + cart ── */
        .left-panel {
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--border);
            overflow: hidden;
            min-width: 0; /* grid items default to min-width:auto and won't shrink below
                             their content's intrinsic width otherwise, which silently
                             clips the panel instead of letting it fit the grid track */
        }

        /* Scanner bar */
        .scanner-bar {
            padding: 16px 22px;
            background: var(--white);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .scan-wrap {
            position: relative;
            flex: 1;
        }
        .scan-wrap i.scan-icon {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 15px;
            pointer-events: none;
        }
        .scan-input {
            width: 100%;
            padding: 11px 14px 11px 40px;
            border: 2px solid var(--primary);
            border-radius: 9px;
            font-size: 15px;
            font-weight: 600;
            background: var(--white);
            color: var(--text-primary);
            outline: none;
            transition: box-shadow 0.2s;
        }
        .scan-input:focus { box-shadow: 0 0 0 3px rgba(168,53,53,0.15); }
        .scan-input::placeholder { font-weight: 400; color: var(--text-secondary); }
        .cam-btn {
            padding: 10px 16px;
            background: var(--accent);
            border: 1.5px solid var(--border);
            border-radius: 9px;
            cursor: pointer;
            font-size: 14px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 7px;
            font-weight: 600;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .cam-btn:hover { border-color:var(--primary);color:var(--primary); }
        .cam-btn.active { background:var(--primary);color:white;border-color:var(--primary); }

        /* Camera reader */
        #cameraReader {
            display: none;
            padding: 0 22px 14px;
            background: var(--white);
            border-bottom: 1px solid var(--border);
        }
        #cameraReader.open { display: block; }
        #reader { width: 100%; max-height: 200px; border-radius: 10px; overflow: hidden; }

        /* Search results dropdown */
        .search-results {
            display: none;
            position: absolute;
            top: calc(100% + 6px);
            left: 0; right: 0;
            background: var(--white);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            z-index: 100;
            max-height: 260px;
            overflow-y: auto;
        }
        .search-results.open { display: block; }
        .search-result-item {
            padding: 11px 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid var(--border);
            transition: background 0.15s;
        }
        .search-result-item:last-child { border-bottom: none; }
        .search-result-item:hover { background: var(--accent); }
        .sri-icon { width: 34px; height: 34px; background: var(--primary-light); border-radius: 8px; display:flex;align-items:center;justify-content:center;color:var(--primary);font-size:14px;flex-shrink:0; }
        .sri-name { font-size:13px;font-weight:600;color:var(--text-primary); }
        .sri-meta { font-size:11px;color:var(--text-secondary);margin-top:2px; }
        .sri-price { margin-left:auto;font-size:14px;font-weight:700;color:var(--primary);white-space:nowrap; }
        .sri-stock-low { color: var(--warning); font-size:11px; }
        .sri-stock-out { color: var(--danger); font-size:11px; }

        /* Cart area */
        .cart-area {
            flex: 1;
            overflow-y: auto;
            overflow-x: auto;
            padding: 0;
        }
        .cart-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-secondary);
            gap: 12px;
        }
        .cart-empty i { font-size: 48px; opacity: 0.2; }
        .cart-empty p { font-size: 14px; }

        /* Cart table */
        .cart-table { width:100%;border-collapse:collapse; }
        .cart-table thead { position:sticky;top:0;z-index:5;background:rgba(168,53,53,0.04);border-bottom:2px solid var(--border); }
        .cart-table th { padding:10px 18px;text-align:left;font-size:11px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.5px; }
        .cart-table th:last-child { text-align:right; }
        .cart-table tbody tr { border-bottom:1px solid var(--border);transition:background 0.15s; }
        .cart-table tbody tr:hover { background:rgba(168,53,53,0.02); }
        .cart-table td { padding:12px 18px;font-size:13px;vertical-align:middle; }
        .cart-prod-name { font-weight:600;color:var(--text-primary); }
        .cart-prod-id { font-size:11px;color:var(--text-secondary);font-family:monospace; }

        /* Qty stepper */
        .qty-stepper { display:flex;align-items:center;gap:0;width:100px; }
        .qty-btn { width:28px;height:28px;border:1.5px solid var(--border);background:var(--white);cursor:pointer;font-size:15px;font-weight:700;display:flex;align-items:center;justify-content:center;transition:all 0.15s;color:var(--text-primary); }
        .qty-btn.dec { border-radius:6px 0 0 6px;border-right:none; }
        .qty-btn.inc { border-radius:0 6px 6px 0;border-left:none; }
        .qty-btn:hover { background:var(--primary);color:white;border-color:var(--primary); }
        .qty-val { width:44px;height:28px;text-align:center;border:1.5px solid var(--border);border-left:none;border-right:none;font-size:13px;font-weight:700;background:var(--white);color:var(--text-primary); }
        .qty-val:focus { outline:none;border-color:var(--primary);z-index:1; }

        .cart-subtotal { text-align:right;font-weight:700;color:var(--text-primary); }
        .remove-btn { background:none;border:none;color:var(--text-secondary);cursor:pointer;padding:5px;border-radius:5px;transition:all 0.15s;font-size:13px; }
        .remove-btn:hover { color:var(--danger);background:rgba(239,68,68,0.08); }

        /* Cart footer (totals) */
        .cart-footer {
            border-top: 2px solid var(--border);
            padding: 16px 22px;
            background: var(--white);
            flex-shrink: 0;
        }
        .totals-row { display:flex;justify-content:space-between;font-size:13px;color:var(--text-secondary);margin-bottom:6px; }
        .totals-row.grand { font-size:20px;font-weight:800;color:var(--text-primary);margin-top:10px;padding-top:10px;border-top:1.5px dashed var(--border); }
        .totals-row.grand span:last-child { color:var(--primary); }
        .item-count-pill { background:var(--primary-light);color:var(--primary);font-size:12px;font-weight:700;padding:2px 9px;border-radius:20px;margin-left:6px; }

        /* ── RIGHT: checkout panel ── */
        .right-panel {
            display: flex;
            flex-direction: column;
            background: var(--white);
            overflow-y: auto;
        }

        .panel-section {
            padding: 20px 22px;
            border-bottom: 1px solid var(--border);
        }
        .section-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: var(--text-secondary);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .section-label i { color:var(--primary); }

        /* Customer picker */
        .customer-search-wrap { position:relative; }
        .customer-input {
            width: 100%;
            padding: 10px 14px 10px 38px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: 13px;
            background: var(--accent);
            transition: all 0.2s;
            outline: none;
        }
        .customer-input:focus { border-color:var(--primary);background:var(--white);box-shadow:0 0 0 3px rgba(168,53,53,0.08); }
        .customer-icon { position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-secondary);font-size:13px; }
        .customer-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 4px);
            left: 0; right: 0;
            background: var(--white);
            border: 1.5px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
            z-index: 50;
            max-height: 200px;
            overflow-y: auto;
        }
        .customer-dropdown.open { display: block; }
        .customer-option {
            padding: 10px 14px;
            cursor: pointer;
            border-bottom: 1px solid var(--border);
            transition: background 0.15s;
        }
        .customer-option:last-child { border-bottom:none; }
        .customer-option:hover { background:var(--accent); }
        .copt-name { font-size:13px;font-weight:600; }
        .copt-meta { font-size:11px;color:var(--text-secondary);margin-top:1px; }

        .selected-customer {
            display: none;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            background: #ecfdf5;
            border: 1.5px solid #a7f3d0;
            border-radius: 8px;
            margin-top: 8px;
        }
        .selected-customer.show { display:flex; }
        .selected-customer i { color:var(--success); }
        .sc-name { font-size:13px;font-weight:600;color:#065f46;flex:1; }
        .sc-clear { background:none;border:none;color:#6b7280;cursor:pointer;font-size:13px;padding:2px;border-radius:4px; }
        .sc-clear:hover { color:var(--danger); }

        .guest-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: #f0f9ff;
            border: 1.5px solid #bae6fd;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
            color: #0369a1;
            margin-top: 8px;
        }

        /* Payment section */
        .method-grid { display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:14px; }
        .method-btn {
            padding: 10px 6px;
            border: 2px solid var(--border);
            border-radius: 9px;
            background: var(--white);
            cursor: pointer;
            text-align: center;
            transition: all 0.2s;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
        }
        .method-btn i { display:block;font-size:18px;margin-bottom:5px;color:var(--text-secondary); }
        .method-btn:hover { border-color:var(--primary);color:var(--primary); }
        .method-btn:hover i { color:var(--primary); }
        .method-btn.selected { border-color:var(--primary);background:var(--primary-light);color:var(--primary); }
        .method-btn.selected i { color:var(--primary); }

        .field-label { font-size:12px;font-weight:600;color:var(--text-secondary);margin-bottom:5px;display:block; }
        .field-input {
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid var(--border);
            border-radius: 7px;
            font-size: 13px;
            background: var(--white);
            transition: border-color 0.2s;
            outline: none;
            margin-bottom: 10px;
        }
        .field-input:focus { border-color:var(--primary); }
        .field-input.large { font-size:20px;font-weight:700;color:var(--primary);padding:12px; }

        .change-display {
            padding: 12px 16px;
            background: #ecfdf5;
            border: 1.5px solid #a7f3d0;
            border-radius: 9px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .change-display span:first-child { font-size:13px;color:#065f46;font-weight:600; }
        .change-display span:last-child { font-size:18px;font-weight:800;color:#059669; }

        .proof-upload {
            border: 1.5px dashed var(--border);
            border-radius: 8px;
            padding: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            background: var(--accent);
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 10px;
        }
        .proof-upload:hover { border-color:var(--primary);color:var(--primary); }
        #proofFile { display:none; }

        /* Process button */
        .process-btn {
            margin: 18px 22px;
            padding: 15px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.2s;
            width: calc(100% - 44px);
        }
        .process-btn:hover:not(:disabled) { background: var(--primary-dark); transform:translateY(-1px);box-shadow:0 4px 14px rgba(168,53,53,0.3); }
        .process-btn:disabled { background:#ccc;cursor:not-allowed;transform:none;box-shadow:none; }

        /* ── Toast ── */
        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 12px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            color: white;
            background: #1f2937;
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 9px;
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.3s;
            z-index: 999;
            max-width: 360px;
        }
        .toast.show { opacity:1;transform:translateY(0); }
        .toast.success { background:#059669; }
        .toast.error   { background:#dc2626; }
        .toast.warn    { background:#d97706; }

        /* ── Receipt modal ── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            z-index: 200;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-overlay.open { display: flex; }
        .modal-box {
            background: var(--white);
            border-radius: 14px;
            width: 420px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .modal-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-header i { font-size:18px;color:var(--success); }
        .modal-header-text h3 { font-size:15px;font-weight:700; }
        .modal-header-text p { font-size:12px;color:var(--text-secondary);margin-top:2px; }

        /* Receipt */
        .receipt {
            padding: 24px;
            font-family: 'Courier New', monospace;
        }
        .receipt-header { text-align:center;margin-bottom:18px; }
        .receipt-header h2 { font-size:18px;font-weight:800;color:var(--primary);letter-spacing:1px; }
        .receipt-header p { font-size:12px;color:var(--text-secondary);margin-top:3px;line-height:1.6; }
        .receipt-divider { border:none;border-top:1.5px dashed var(--border);margin:14px 0; }
        .receipt-row { display:flex;justify-content:space-between;font-size:12px;margin-bottom:5px; }
        .receipt-row.bold { font-weight:700;font-size:13px; }
        .receipt-row.total { font-size:16px;font-weight:800;color:var(--primary);padding-top:6px;border-top:1.5px solid var(--border);margin-top:6px; }
        .receipt-row.change { color:var(--success);font-weight:700; }
        .receipt-items { margin:10px 0; }
        .receipt-item { display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px; }
        .receipt-item-name { flex:1;margin-right:8px; }
        .receipt-footer { text-align:center;margin-top:18px;font-size:11px;color:var(--text-secondary);line-height:1.8; }

        .modal-actions {
            padding: 16px 24px;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 10px;
        }
        .btn-print { flex:1;padding:11px;background:var(--primary);color:white;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;transition:background 0.2s; }
        .btn-print:hover { background:var(--primary-dark); }
        .btn-new-sale { flex:1;padding:11px;background:var(--accent);color:var(--primary);border:1.5px solid var(--primary);border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;transition:all 0.2s; }
        .btn-new-sale:hover { background:var(--primary-light); }

        /* ── Print styles ── */
        @media print {
            body > *:not(.modal-overlay) { display:none !important; }
            .modal-overlay { display:block !important; position:static; background:none; padding:0; }
            .modal-box { box-shadow:none; border:none; width:100%; max-height:none; }
            .modal-header, .modal-actions { display:none !important; }
            .receipt { padding: 0; }
        }

        /* ── Scrollbars ── */
        .cart-area::-webkit-scrollbar, .right-panel::-webkit-scrollbar { width:5px; }
        .cart-area::-webkit-scrollbar-track, .right-panel::-webkit-scrollbar-track { background:#f1f1f1;border-radius:3px; }
        .cart-area::-webkit-scrollbar-thumb, .right-panel::-webkit-scrollbar-thumb { background:rgba(168,53,53,0.25);border-radius:3px; }

        @media (max-width: 1024px) {
            :root { --sidebar-width: 70px; }
            .logo-text, .nav-text, .user-name, .user-role { display:none; }
            .logo-area, .nav-section, .user-section { padding:14px; }
            .logo-area { justify-content:center; }
            .nav-link { justify-content:center;padding:14px;border-left:none;border-right:4px solid transparent;gap:0; }
            .nav-link.active { border-left:none;border-right-color:var(--primary); }
            .user-info { justify-content:center; }
        }
    </style>
</head>
<body>

<?php include 'smart_sidebar.php'; ?>

<!-- ── Main ── -->
<div class="main">

    <!-- Top bar -->
    <div class="topbar">
        <div class="topbar-title">
            <i class="fas fa-cash-register"></i>
            Point of Sale
        </div>
        <div class="topbar-meta">
            <span><i class="fas fa-store"></i> <?= htmlspecialchars($branchName) ?></span>
            <span><i class="fas fa-user-tie"></i> <?= htmlspecialchars($staffName) ?></span>
            <span><i class="fas fa-clock"></i> <span class="live-clock" id="liveClock"></span></span>
        </div>
    </div>

    <?php if (!$branchActive): ?>
    <div style="background:#fef2f2;border:1.5px solid #fecaca;border-radius:8px;
                padding:12px 18px;margin:14px 20px 0;font-size:13px;color:#991b1b;
                display:flex;align-items:center;gap:10px;">
        <i class="fas fa-triangle-exclamation" style="font-size:16px;"></i>
        <span>Your assigned branch is temporarily unavailable (inactive/under renovation). Sales cannot be processed here — contact an admin to be reassigned.</span>
    </div>
    <?php endif; ?>

    <div class="pos-body">

        <!-- ══ LEFT PANEL ══ -->
        <div class="left-panel">

            <!-- Scanner bar -->
            <div class="scanner-bar">
                <div class="scan-wrap" id="scanWrap">
                    <i class="fas fa-barcode scan-icon"></i>
                    <input
                        type="text"
                        id="scanInput"
                        class="scan-input"
                        placeholder="Scan barcode or type product ID / name…"
                        autocomplete="off"
                        autofocus
                    >
                    <div class="search-results" id="searchResults"></div>
                </div>
                <button class="cam-btn" id="camToggle" title="Toggle camera scanner">
                    <i class="fas fa-camera"></i>
                    <span>Camera</span>
                </button>
            </div>

            <!-- Camera reader (hidden by default) -->
            <div id="cameraReader">
                <div id="reader"></div>
            </div>

            <!-- Cart -->
            <div class="cart-area" id="cartArea">
                <div class="cart-empty" id="cartEmpty">
                    <i class="fas fa-barcode"></i>
                    <p>Scan or search a product to begin</p>
                </div>
                <table class="cart-table" id="cartTable" style="display:none;">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th style="width:120px;">Qty</th>
                            <th style="width:100px;text-align:right;">Subtotal</th>
                            <th style="width:36px;"></th>
                        </tr>
                    </thead>
                    <tbody id="cartBody"></tbody>
                </table>
            </div>

            <!-- Totals footer -->
            <div class="cart-footer">
                <div class="totals-row">
                    <span>Items <span class="item-count-pill" id="itemCountPill">0</span></span>
                    <span id="subtotalDisplay">RM 0.00</span>
                </div>
                <div class="totals-row grand">
                    <span>Total</span>
                    <span id="grandTotalDisplay">RM 0.00</span>
                </div>
            </div>
        </div>

        <!-- ══ RIGHT PANEL ══ -->
        <div class="right-panel">

            <!-- Customer section -->
            <div class="panel-section">
                <div class="section-label"><i class="fas fa-user"></i> Customer</div>
                <div class="customer-search-wrap">
                    <i class="fas fa-search customer-icon"></i>
                    <input
                        type="text"
                        id="customerSearch"
                        class="customer-input"
                        placeholder="Search by name, phone, or email…"
                        autocomplete="off"
                    >
                    <div class="customer-dropdown" id="customerDropdown"></div>
                </div>
                <div class="selected-customer" id="selectedCustomerBox">
                    <i class="fas fa-user-check"></i>
                    <div class="sc-name" id="selectedCustomerName"></div>
                    <button class="sc-clear" onclick="clearCustomer()" title="Remove">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="guest-badge" id="guestBadge">
                    <i class="fas fa-walking"></i> Walk-in / Guest (no account)
                </div>
                <input type="hidden" id="selectedCustomerId" value="">
            </div>

            <!-- Payment section -->
            <div class="panel-section">
                <div class="section-label"><i class="fas fa-money-bill-wave"></i> Payment</div>

                <div class="method-grid">
                    <button class="method-btn selected" data-method="CASH" onclick="selectMethod('CASH')">
                        <i class="fas fa-money-bill"></i> Cash
                    </button>
                    <button class="method-btn" data-method="TRANSFER" onclick="selectMethod('TRANSFER')">
                        <i class="fas fa-university"></i> Transfer
                    </button>
                    <button class="method-btn" data-method="OTHER" onclick="selectMethod('OTHER')">
                        <i class="fas fa-wallet"></i> E-Wallet
                    </button>
                </div>

                <!-- Cash fields -->
                <div id="cashFields">
                    <label class="field-label">Amount Tendered (RM)</label>
                    <input type="number" id="amountPaid" class="field-input large" placeholder="0.00" min="0" step="0.10" oninput="updateChange()">
                    <div class="change-display" id="changeDisplay" style="display:none;">
                        <span><i class="fas fa-coins"></i> Change</span>
                        <span id="changeAmount">RM 0.00</span>
                    </div>
                </div>

                <!-- Transfer fields -->
                <div id="transferFields" style="display:none;">
                    <label class="field-label">Reference Number <span style="color:var(--danger);">*</span></label>
                    <input type="text" id="refNumber" class="field-input" placeholder="e.g. TT2026062900123">
                    <label class="field-label">Payment Proof (optional)</label>
                    <div class="proof-upload" onclick="document.getElementById('proofFile').click()">
                        <i class="fas fa-upload"></i>
                        <span id="proofLabel"> Upload receipt image or PDF</span>
                    </div>
                    <input type="file" id="proofFile" accept="image/jpeg,image/png,application/pdf" onchange="updateProofLabel(this)">
                </div>

                <!-- E-Wallet fields -->
                <div id="otherFields" style="display:none;">
                    <label class="field-label">Reference / Transaction ID (optional)</label>
                    <input type="text" id="otherRef" class="field-input" placeholder="e.g. TNG12345">
                    <label class="field-label">Payment Proof (optional)</label>
                    <div class="proof-upload" onclick="document.getElementById('proofFile').click()">
                        <i class="fas fa-upload"></i>
                        <span id="proofLabelOther"> Upload screenshot</span>
                    </div>
                </div>
            </div>

            <!-- Spacer -->
            <div style="flex:1;"></div>

            <!-- Process button -->
            <button class="process-btn" id="processBtn" onclick="processSale()" disabled>
                <i class="fas fa-check-circle"></i>
                Complete Sale
            </button>
        </div>
    </div><!-- /.pos-body -->
</div><!-- /.main -->

<!-- ── Toast ── -->
<div class="toast" id="toast"></div>

<!-- ── Receipt Modal ── -->
<div class="modal-overlay" id="receiptModal">
    <div class="modal-box">
        <div class="modal-header">
            <i class="fas fa-check-circle"></i>
            <div class="modal-header-text">
                <h3>Sale Complete</h3>
                <p id="receiptOrderId"></p>
            </div>
        </div>
        <div class="receipt" id="receiptBody"></div>
        <div class="modal-actions">
            <button class="btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> Print Receipt
            </button>
            <a class="btn-print" id="viewReceiptLink" href="#" target="_blank" rel="noopener" style="text-decoration:none;">
                <i class="fas fa-receipt"></i> View Receipt
            </a>
            <button class="btn-new-sale" onclick="newSale()">
                <i class="fas fa-plus"></i> New Sale
            </button>
        </div>
    </div>
</div>

<script>
// ═══════════════════════════════════════════════════════
//  POS — Client-side logic
// ═══════════════════════════════════════════════════════

// ── State ──────────────────────────────────────────────
const BRANCH_ACTIVE = <?= $branchActive ? 'true' : 'false' ?>;
const cart        = {};  // { product_id: { name, price, qty } }
let selectedMethod  = 'CASH';
let html5Scanner    = null;
let cameraOpen      = false;
let searchDebounce  = null;
let customerDebounce = null;

// ── Live clock ─────────────────────────────────────────
function updateClock() {
    const now = new Date();
    document.getElementById('liveClock').textContent =
        now.toLocaleTimeString('en-MY', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
}
updateClock();
setInterval(updateClock, 1000);

// ── Auto-refocus scanner input ─────────────────────────
function refocusScan() {
    const inp = document.getElementById('scanInput');
    if (inp && !document.querySelector('#customerSearch:focus, #amountPaid:focus, #refNumber:focus, #otherRef:focus'))
        inp.focus();
}
document.addEventListener('click', e => {
    if (!e.target.closest('.customer-search-wrap') &&
        !e.target.closest('#amountPaid') &&
        !e.target.closest('#refNumber') &&
        !e.target.closest('#otherRef') &&
        !e.target.closest('#receiptModal') &&
        !e.target.closest('.cam-btn') &&
        !e.target.closest('.qty-val'))
        refocusScan();
});

// ── Product search ─────────────────────────────────────
const scanInput = document.getElementById('scanInput');

scanInput.addEventListener('keydown', e => {
    if (e.key === 'Enter') {
        e.preventDefault();
        clearTimeout(searchDebounce);
        doSearch(scanInput.value.trim());
    }
});

scanInput.addEventListener('input', () => {
    clearTimeout(searchDebounce);
    const q = scanInput.value.trim();
    if (q.length === 0) { closeSearchResults(); return; }
    searchDebounce = setTimeout(() => doSearch(q), 380);
});

scanInput.addEventListener('blur', () => {
    setTimeout(closeSearchResults, 200);
});

async function doSearch(q) {
    if (!q) return;
    try {
        const res  = await fetch(`pos_lookup.php?q=${encodeURIComponent(q)}`);
        const data = await res.json();
        if (!data.success) { showToast(data.error, 'warn'); closeSearchResults(); return; }

        // If exact barcode match (single result with exact product_id), add immediately
        if (data.products.length === 1 && data.products[0].product_id === q) {
            addToCart(data.products[0]);
            scanInput.value = '';
            closeSearchResults();
            return;
        }
        renderSearchResults(data.products);
    } catch (err) {
        showToast('Lookup failed. Check connection.', 'error');
    }
}

function renderSearchResults(products) {
    const box = document.getElementById('searchResults');
    box.innerHTML = products.map(p => {
        const stockClass = p.stock <= 0 ? 'sri-stock-out' : (p.stock <= 5 ? 'sri-stock-low' : '');
        const stockLabel = p.stock <= 0
            ? '<span class="sri-stock-out"><i class="fas fa-exclamation-circle"></i> Out of stock</span>'
            : (p.stock <= 5
                ? `<span class="sri-stock-low"><i class="fas fa-exclamation-triangle"></i> Low: ${p.stock} left</span>`
                : `<span style="color:var(--success);font-size:11px;"><i class="fas fa-check-circle"></i> ${p.stock} in stock</span>`);
        const hasDiscount = parseFloat(p.discount_percent) > 0;
        const priceHtml = hasDiscount
            ? `<span style="text-decoration:line-through;color:var(--text-secondary);font-size:11px;opacity:0.75;">RM ${parseFloat(p.price).toFixed(2)}</span><br>RM ${parseFloat(p.discounted_price).toFixed(2)}`
            : `RM ${parseFloat(p.price).toFixed(2)}`;
        return `
            <div class="search-result-item" onclick="pickProduct(${JSON.stringify(p).replace(/"/g, '&quot;')})">
                <div class="sri-icon"><i class="fas fa-box"></i></div>
                <div style="flex:1;min-width:0;">
                    <div class="sri-name">${esc(p.product_name)} ${hasDiscount ? `<span style="font-size:10px;font-weight:700;padding:1px 6px;border-radius:8px;background:rgba(244,162,97,0.2);color:#b45309;">-${parseFloat(p.discount_percent)}%</span>` : ''}</div>
                    <div class="sri-meta">${esc(p.product_id)} &bull; ${esc(p.category)} &bull; ${stockLabel}</div>
                </div>
                <div class="sri-price">${priceHtml}</div>
            </div>`;
    }).join('');
    box.classList.add('open');
}

function pickProduct(p) {
    if (parseInt(p.stock) <= 0) { showToast(`"${p.product_name}" is out of stock.`, 'warn'); return; }
    addToCart(p);
    scanInput.value = '';
    closeSearchResults();
    refocusScan();
}

function closeSearchResults() {
    document.getElementById('searchResults').classList.remove('open');
}

// ── Cart logic ─────────────────────────────────────────
function addToCart(product) {
    const id = product.product_id;
    if (cart[id]) {
        cart[id].qty++;
    } else {
        // Cart carries the discounted (actually-charged) price — pos_process.php
        // recomputes and validates it server-side, but the on-screen total
        // must match what the customer is actually charged.
        cart[id] = {
            name:     product.product_name,
            price:    parseFloat(product.discounted_price ?? product.price),
            discount: parseFloat(product.discount_percent ?? 0),
            qty:      1,
            stock:    parseInt(product.stock),
        };
    }
    renderCart();
    showToast(`Added: ${product.product_name}`, 'success');
}

function changeQty(id, delta) {
    if (!cart[id]) return;
    const newQty = cart[id].qty + delta;
    if (newQty < 1) { removeFromCart(id); return; }
    if (newQty > cart[id].stock) { showToast('Quantity exceeds available stock.', 'warn'); return; }
    cart[id].qty = newQty;
    renderCart();
}

function setQty(id, val) {
    if (!cart[id]) return;
    const n = parseInt(val);
    if (isNaN(n) || n < 1) { cart[id].qty = 1; renderCart(); return; }
    if (n > cart[id].stock) { showToast('Quantity exceeds available stock.', 'warn'); cart[id].qty = cart[id].stock; renderCart(); return; }
    cart[id].qty = n;
    renderCart();
}

function removeFromCart(id) {
    delete cart[id];
    renderCart();
}

function renderCart() {
    const tbody     = document.getElementById('cartBody');
    const table     = document.getElementById('cartTable');
    const empty     = document.getElementById('cartEmpty');
    const keys      = Object.keys(cart);

    if (keys.length === 0) {
        table.style.display = 'none';
        empty.style.display = 'flex';
        updateTotals(0, 0);
        return;
    }

    table.style.display = 'table';
    empty.style.display = 'none';

    let itemCount = 0;
    let total = 0;

    tbody.innerHTML = keys.map(id => {
        const item     = cart[id];
        const subtotal = item.price * item.qty;
        itemCount += item.qty;
        total     += subtotal;

        // Stringify for inline handler — escape for HTML attribute
        const safeId = id.replace(/'/g, "\\'");

        return `
        <tr>
            <td>
                <div class="cart-prod-name">${esc(item.name)} ${item.discount > 0 ? `<span style="font-size:10px;font-weight:700;padding:1px 6px;border-radius:8px;background:rgba(244,162,97,0.2);color:#b45309;">-${item.discount}%</span>` : ''}</div>
                <div class="cart-prod-id">${esc(id)}</div>
            </td>
            <td>
                <div class="qty-stepper">
                    <button class="qty-btn dec" onclick="changeQty('${safeId}', -1)">−</button>
                    <input class="qty-val" type="number" value="${item.qty}" min="1" max="${item.stock}"
                           onchange="setQty('${safeId}', this.value)"
                           onblur="setQty('${safeId}', this.value)">
                    <button class="qty-btn inc" onclick="changeQty('${safeId}', 1)">+</button>
                </div>
            </td>
            <td class="cart-subtotal">RM ${subtotal.toFixed(2)}</td>
            <td>
                <button class="remove-btn" onclick="removeFromCart('${safeId}')" title="Remove">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </td>
        </tr>`;
    }).join('');

    updateTotals(itemCount, total);
    updateChange();
    updateProcessBtn();
}

function updateTotals(count, total) {
    document.getElementById('itemCountPill').textContent    = count;
    document.getElementById('subtotalDisplay').textContent  = `RM ${total.toFixed(2)}`;
    document.getElementById('grandTotalDisplay').textContent = `RM ${total.toFixed(2)}`;
}

function getCartTotal() {
    return Object.values(cart).reduce((sum, i) => sum + i.price * i.qty, 0);
}

function getCartItemCount() {
    return Object.keys(cart).length;
}

// ── Payment method ─────────────────────────────────────
function selectMethod(method) {
    selectedMethod = method;
    document.querySelectorAll('.method-btn').forEach(btn => {
        btn.classList.toggle('selected', btn.dataset.method === method);
    });
    document.getElementById('cashFields').style.display     = method === 'CASH'     ? 'block' : 'none';
    document.getElementById('transferFields').style.display = method === 'TRANSFER' ? 'block' : 'none';
    document.getElementById('otherFields').style.display    = method === 'OTHER'    ? 'block' : 'none';
    updateChange();
    updateProcessBtn();
}

function updateChange() {
    const total   = getCartTotal();
    const tendered = parseFloat(document.getElementById('amountPaid').value) || 0;
    const changeBox = document.getElementById('changeDisplay');

    if (selectedMethod !== 'CASH' || tendered <= 0) {
        changeBox.style.display = 'none';
        return;
    }

    const change = tendered - total;
    changeBox.style.display = 'flex';
    document.getElementById('changeAmount').textContent =
        change >= 0 ? `RM ${change.toFixed(2)}` : `− RM ${Math.abs(change).toFixed(2)}`;
    document.getElementById('changeAmount').style.color = change >= 0 ? 'var(--success)' : 'var(--danger)';
    updateProcessBtn();
}

function updateProofLabel(input) {
    const lbl = document.getElementById('proofLabel');
    if (lbl && input.files[0]) lbl.textContent = ' ' + input.files[0].name;
}

// ── Customer typeahead ─────────────────────────────────
const customerSearch = document.getElementById('customerSearch');

customerSearch.addEventListener('input', () => {
    clearTimeout(customerDebounce);
    const q = customerSearch.value.trim();
    if (q.length < 2) { closeCustomerDropdown(); return; }
    customerDebounce = setTimeout(() => searchCustomers(q), 350);
});

customerSearch.addEventListener('blur', () => {
    setTimeout(closeCustomerDropdown, 200);
});

async function searchCustomers(q) {
    try {
        const res  = await fetch(`pos_customer_search.php?q=${encodeURIComponent(q)}`);
        const data = await res.json();
        if (!data.success || !data.customers.length) { closeCustomerDropdown(); return; }
        renderCustomerDropdown(data.customers);
    } catch {}
}

function renderCustomerDropdown(customers) {
    const dd = document.getElementById('customerDropdown');
    dd.innerHTML = customers.map(c => `
        <div class="customer-option" onclick='selectCustomer(${JSON.stringify(c)})'>
            <div class="copt-name">${esc(c.name)}</div>
            <div class="copt-meta">${esc(c.phone_number || '')} ${c.email ? '· ' + esc(c.email) : ''}</div>
        </div>`).join('');
    dd.classList.add('open');
}

function selectCustomer(c) {
    document.getElementById('selectedCustomerId').value    = c.user_id;
    document.getElementById('selectedCustomerName').textContent = c.name + (c.phone_number ? ` · ${c.phone_number}` : '');
    document.getElementById('selectedCustomerBox').classList.add('show');
    document.getElementById('guestBadge').style.display   = 'none';
    document.getElementById('customerSearch').style.display = 'none';
    closeCustomerDropdown();
}

function clearCustomer() {
    document.getElementById('selectedCustomerId').value    = '';
    document.getElementById('selectedCustomerBox').classList.remove('show');
    document.getElementById('customerSearch').style.display = 'block';
    document.getElementById('customerSearch').value         = '';
    document.getElementById('guestBadge').style.display   = 'inline-flex';
    refocusScan();
}

function closeCustomerDropdown() {
    document.getElementById('customerDropdown').classList.remove('open');
}

// ── Process button state ───────────────────────────────
function updateProcessBtn() {
    const btn     = document.getElementById('processBtn');
    const hasCart = getCartItemCount() > 0;
    const total   = getCartTotal();
    let ready = hasCart;

    if (selectedMethod === 'CASH') {
        const tendered = parseFloat(document.getElementById('amountPaid').value) || 0;
        ready = hasCart && tendered >= total && total > 0;
    } else if (selectedMethod === 'TRANSFER') {
        ready = hasCart && total > 0;
    } else {
        ready = hasCart && total > 0;
    }

    btn.disabled = !ready || !BRANCH_ACTIVE;
}

// ── Process sale ───────────────────────────────────────
async function processSale() {
    if (!BRANCH_ACTIVE) { showToast('Your branch is inactive — sales cannot be processed.', 'warn'); return; }

    const btn    = document.getElementById('processBtn');
    const total  = getCartTotal();
    const items  = Object.entries(cart).map(([id, v]) => ({ product_id: id, quantity: v.qty }));
    const custId = document.getElementById('selectedCustomerId').value.trim() || '';

    if (!items.length) { showToast('Cart is empty.', 'warn'); return; }
    if (total <= 0) { showToast('Invalid total.', 'warn'); return; }

    btn.disabled  = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing…';

    const form = new FormData();
    form.append('items',       JSON.stringify(items));
    form.append('customer_id', custId);
    form.append('method',      selectedMethod);
    form.append('amount_paid', document.getElementById('amountPaid').value || '0');

    if (selectedMethod === 'TRANSFER') {
        form.append('reference', document.getElementById('refNumber').value.trim());
    } else if (selectedMethod === 'OTHER') {
        form.append('reference', document.getElementById('otherRef').value.trim());
    }

    const proofFile = document.getElementById('proofFile');
    if (proofFile && proofFile.files[0]) {
        form.append('proof', proofFile.files[0]);
    }

    try {
        const res  = await fetch('pos_process.php', { method: 'POST', body: form });
        const data = await res.json();

        if (data.success) {
            showReceipt(data, custId);
        } else {
            showToast(data.error || 'Something went wrong.', 'error');
            btn.disabled  = false;
            btn.innerHTML = '<i class="fas fa-check-circle"></i> Complete Sale';
        }
    } catch (err) {
        showToast('Network error. Please try again.', 'error');
        btn.disabled  = false;
        btn.innerHTML = '<i class="fas fa-check-circle"></i> Complete Sale';
    }
}

// ── Receipt ────────────────────────────────────────────
function showReceipt(data, custId) {
    document.getElementById('receiptOrderId').textContent = data.order_id;
    document.getElementById('viewReceiptLink').href = 'receipt.php?id=' + encodeURIComponent(data.order_id);

    const custName = custId
        ? (document.getElementById('selectedCustomerName').textContent || 'Customer')
        : 'Walk-in / Guest';

    const methodLabel = { CASH: 'Cash', TRANSFER: 'Bank Transfer', OTHER: 'E-Wallet' }[data.method] || data.method;

    const itemRows = data.items.map(i =>
        `<div class="receipt-item">
            <span class="receipt-item-name">${esc(i.product_name)} × ${i.quantity}</span>
            <span>RM ${parseFloat(i.subtotal).toFixed(2)}</span>
        </div>`
    ).join('');

    const changeRow = data.method === 'CASH' && data.change >= 0
        ? `<div class="receipt-row change"><span>Change</span><span>RM ${parseFloat(data.change).toFixed(2)}</span></div>`
        : '';

    document.getElementById('receiptBody').innerHTML = `
        <div class="receipt-header">
            <h2><?= htmlspecialchars($branchName) ?></h2>
            <p><?= htmlspecialchars($branchAddress ?? '') ?></p>
            <p><?= htmlspecialchars($branchPhone ?? '') ?></p>
        </div>
        <hr class="receipt-divider">
        <div class="receipt-row"><span>Date</span><span>${data.timestamp}</span></div>
        <div class="receipt-row"><span>Order</span><span style="font-family:monospace;">${esc(data.order_id)}</span></div>
        <div class="receipt-row"><span>Cashier</span><span><?= htmlspecialchars($staffName) ?></span></div>
        <div class="receipt-row"><span>Customer</span><span>${esc(custName)}</span></div>
        <hr class="receipt-divider">
        <div class="receipt-items">${itemRows}</div>
        <hr class="receipt-divider">
        <div class="receipt-row total"><span>TOTAL</span><span>RM ${parseFloat(data.total).toFixed(2)}</span></div>
        <div class="receipt-row bold"><span>Paid (${esc(methodLabel)})</span><span>RM ${parseFloat(data.total).toFixed(2)}</span></div>
        ${changeRow}
        <div class="receipt-footer">
            <p>Thank you for shopping at StationaryPlus!</p>
            <p>Please keep this receipt for your records.</p>
        </div>`;

    document.getElementById('receiptModal').classList.add('open');
}

function newSale() {
    // Clear cart
    Object.keys(cart).forEach(k => delete cart[k]);
    renderCart();

    // Reset customer
    clearCustomer();

    // Reset payment
    selectMethod('CASH');
    document.getElementById('amountPaid').value  = '';
    document.getElementById('refNumber').value   = '';
    document.getElementById('otherRef').value    = '';
    document.getElementById('proofFile').value   = '';

    // Reset process btn
    const btn = document.getElementById('processBtn');
    btn.disabled  = true;
    btn.innerHTML = '<i class="fas fa-check-circle"></i> Complete Sale';

    // Close modal, refocus
    document.getElementById('receiptModal').classList.remove('open');
    refocusScan();
}

// ── Camera scanner ─────────────────────────────────────
document.getElementById('camToggle').addEventListener('click', toggleCamera);

function toggleCamera() {
    const btn    = document.getElementById('camToggle');
    const reader = document.getElementById('cameraReader');

    if (!cameraOpen) {
        cameraOpen = true;
        btn.classList.add('active');
        btn.querySelector('span').textContent = 'Stop';
        reader.classList.add('open');

        html5Scanner = new Html5Qrcode('reader');
        html5Scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 300, height: 120 } },
            decodedText => {
                // Barcode detected — treat as product ID
                scanInput.value = decodedText;
                doSearch(decodedText);
                toggleCamera(); // Auto-close camera after scan
            },
            err => {} // silent scan errors
        ).catch(err => {
            showToast('Camera access denied or unavailable.', 'error');
            toggleCamera();
        });
    } else {
        cameraOpen = false;
        btn.classList.remove('active');
        btn.querySelector('span').textContent = 'Camera';
        reader.classList.remove('open');
        if (html5Scanner) {
            html5Scanner.stop().catch(() => {});
            html5Scanner = null;
        }
        refocusScan();
    }
}

// ── Toast ──────────────────────────────────────────────
let toastTimer;
function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    clearTimeout(toastTimer);
    t.className = `toast ${type} show`;
    const icons = { success:'fa-check-circle', error:'fa-times-circle', warn:'fa-exclamation-triangle' };
    t.innerHTML = `<i class="fas ${icons[type] || 'fa-info-circle'}"></i> ${msg}`;
    toastTimer = setTimeout(() => t.classList.remove('show'), 3500);
}

// ── Escape helper ──────────────────────────────────────
function esc(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Init ───────────────────────────────────────────────
selectMethod('CASH');
refocusScan();
</script>
</body>
</html>