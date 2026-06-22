<?php
// ============================================================
//  c_preorder.php — Pre-order (Session Cart)
//
//  Actions via GET:
//    ?action=add&product_id=XXX   — add item to cart
//    ?action=remove&product_id=XXX — remove item from cart
//    ?action=clear                 — empty cart
//
//  Actions via POST:
//    qty update  — update quantities
//    submit      — insert preorder + preorder_items into DB
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role('CUSTOMER');
require_once 'db.php';

$userId = $_SESSION['user_id'];

// Initialise cart in session
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = []; // [product_id => qty]
}

$message = '';
$msgType = '';

// ── Helper: generate preorder ID ─────────────────────────────
function generate_preorder_id(): string {
    return 'PRE-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

// ── GET actions ───────────────────────────────────────────────
$action    = $_GET['action']     ?? '';
$productId = trim($_GET['product_id'] ?? '');

if ($action === 'add' && $productId !== '') {
    // Verify product exists and is active
    $stmt = $conn->prepare("SELECT product_id FROM products WHERE product_id = ? AND product_status = 'ACTIVE' LIMIT 1");
    $stmt->bind_param('s', $productId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        if (isset($_SESSION['cart'][$productId])) {
            $_SESSION['cart'][$productId]++;
        } else {
            $_SESSION['cart'][$productId] = 1;
        }
        // If coming from products page, redirect back there
        if (($_GET['redirect'] ?? '') === 'products') {
            header('Location: c_viewproducts.php?added=1');
            exit;
        }
        $message = 'Item added to your pre-order cart.';
        $msgType = 'success';
    }
    $stmt->close();
}

if ($action === 'remove' && $productId !== '') {
    unset($_SESSION['cart'][$productId]);
    $message = 'Item removed from cart.';
    $msgType = 'info';
}

if ($action === 'clear') {
    $_SESSION['cart'] = [];
    $message = 'Cart cleared.';
    $msgType = 'info';
}

// ── POST: update quantities ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_qty'])) {
    foreach ($_POST['qty'] as $pid => $qty) {
        $qty = max(1, min(99, (int)$qty));
        if (isset($_SESSION['cart'][$pid])) {
            $_SESSION['cart'][$pid] = $qty;
        }
    }
    $message = 'Quantities updated.';
    $msgType = 'success';
}

// ── POST: submit preorder ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_preorder'])) {
    $notes = trim($_POST['notes'] ?? '');

    if (empty($_SESSION['cart'])) {
        $message = 'Your cart is empty. Add items before submitting.';
        $msgType = 'error';
    } else {
        // Fetch current prices from DB (never trust client-side prices)
        $pids        = array_keys($_SESSION['cart']);
        $placeholders = implode(',', array_fill(0, count($pids), '?'));
        $types        = str_repeat('s', count($pids));

        $stmt = $conn->prepare(
            "SELECT product_id, price FROM products
             WHERE product_id IN ($placeholders) AND product_status = 'ACTIVE'"
        );
        $stmt->bind_param($types, ...$pids);
        $stmt->execute();
        $priceRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Build price map
        $priceMap = [];
        foreach ($priceRows as $row) {
            $priceMap[$row['product_id']] = (float)$row['price'];
        }

        // Insert preorder header
        $preorderId = generate_preorder_id();
        $notesVal   = $notes !== '' ? $notes : null;

        $stmt = $conn->prepare(
            "INSERT INTO preorders (preorder_id, user_id, order_status, notes)
             VALUES (?, ?, 'SUBMITTED', ?)"
        );
        $stmt->bind_param('sss', $preorderId, $userId, $notesVal);
        $stmt->execute();
        $stmt->close();

        // Insert preorder items
        $stmt = $conn->prepare(
            "INSERT INTO preorder_items (preorder_item_id, preorder_id, product_id, quantity, unit_price)
             VALUES (?, ?, ?, ?, ?)"
        );

        foreach ($_SESSION['cart'] as $pid => $qty) {
            if (!isset($priceMap[$pid])) continue; // skip if product was deactivated
            $itemId    = 'PRI-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
            $unitPrice = $priceMap[$pid];
            $stmt->bind_param('sssid', $itemId, $preorderId, $pid, $qty, $unitPrice);
            $stmt->execute();
        }
        $stmt->close();

        // Clear cart after successful submission
        $_SESSION['cart'] = [];

        $message = "Pre-order <strong>$preorderId</strong> submitted successfully! We'll notify you when it's ready.";
        $msgType = 'success';
    }
}

// ── Fetch cart product details for display ────────────────────
$cartItems   = [];
$orderTotal  = 0.0;

if (!empty($_SESSION['cart'])) {
    $pids         = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($pids), '?'));
    $types        = str_repeat('s', count($pids));

    $stmt = $conn->prepare(
        "SELECT product_id, product_name, price, category
         FROM products WHERE product_id IN ($placeholders)"
    );
    $stmt->bind_param($types, ...$pids);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        $qty              = $_SESSION['cart'][$row['product_id']];
        $subtotal         = $qty * (float)$row['price'];
        $orderTotal      += $subtotal;
        $cartItems[]      = array_merge($row, ['qty' => $qty, 'subtotal' => $subtotal]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus — Pre-order</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #A83535;
            --secondary: #F4A261;
            --accent: #F1EDE8;
            --background: #FAFAFA;
            --text-primary: #2E2E2E;
            --text-secondary: #707070;
            --border: #E0E0E0;
            --white: #FFFFFF;
            --sidebar-width: 260px;
            --card-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        * {
            margin: 0; padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        body { background-color: var(--background); color: var(--text-primary); min-height: 100vh; display: flex; }

        /* ── Sidebar ── */
        .sidebar {
            width: var(--sidebar-width); background-color: var(--white);
            border-right: 1px solid var(--border); height: 100vh;
            position: fixed; left: 0; top: 0;
            display: flex; flex-direction: column;
            box-shadow: 2px 0 10px rgba(0,0,0,0.03); overflow-y: auto;
        }
        .logo-area { padding: 25px; border-bottom: 1px solid var(--border); display: flex; align-items: center; flex-shrink: 0; }
        .logo-icon { background-color: var(--primary); width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px; color: white; font-size: 20px; }
        .logo-text { font-size: 22px; font-weight: 700; color: var(--primary); }
        .nav-section { padding: 25px 0; border-bottom: 1px solid var(--border); }
        .nav-title { font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.8px; padding: 0 25px 12px 25px; }
        .nav-menu { list-style: none; }
        .nav-item { margin-bottom: 2px; }
        .nav-link { display: flex; align-items: center; padding: 13px 25px; color: var(--text-primary); text-decoration: none; transition: all 0.2s ease; border-left: 4px solid transparent; }
        .nav-link:hover { background-color: rgba(168,53,53,0.05); color: var(--primary); border-left-color: rgba(168,53,53,0.3); }
        .nav-link.active { background-color: rgba(168,53,53,0.08); color: var(--primary); border-left-color: var(--primary); font-weight: 600; }
        .nav-icon { width: 22px; text-align: center; margin-right: 14px; font-size: 16px; }
        .nav-text { font-size: 15px; }
        .user-section { margin-top: auto; padding: 20px 25px; border-top: 1px solid var(--border); }
        .user-info { display: flex; align-items: center; margin-bottom: 14px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background-color: rgba(168,53,53,0.1); display: flex; align-items: center; justify-content: center; color: var(--primary); font-weight: 700; font-size: 16px; margin-right: 12px; flex-shrink: 0; }
        .user-name { font-weight: 600; font-size: 15px; color: var(--text-primary); }
        .user-role { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
        .logout-link { display: flex; align-items: center; gap: 10px; padding: 10px 14px; background-color: rgba(168,53,53,0.06); color: var(--primary); border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600; transition: background-color 0.2s ease; }
        .logout-link:hover { background-color: rgba(168,53,53,0.14); }

        /* ── Main ── */
        .main-content { flex-grow: 1; margin-left: var(--sidebar-width); min-height: 100vh; display: flex; flex-direction: column; }

        .top-header { background-color: var(--white); padding: 20px 30px; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 10; }
        .page-title { font-size: 24px; font-weight: 700; color: var(--text-primary); }
        .page-subtitle { font-size: 14px; color: var(--text-secondary); margin-top: 4px; }

        /* ── Alert ── */
        .alert {
            margin: 20px 30px 0;
            padding: 14px 18px; border-radius: 8px;
            font-size: 14px; display: flex; align-items: center; gap: 10px;
        }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .alert-error   { background: #fff0f0; color: #c62828; border: 1px solid #ef9a9a; }
        .alert-info    { background: #e3f2fd; color: #1565c0; border: 1px solid #90caf9; }

        /* ── Content ── */
        .content-container { padding: 24px 30px 30px; flex-grow: 1; display: grid; grid-template-columns: 1fr 340px; gap: 24px; align-items: start; }

        /* Cart panel */
        .cart-panel { background: var(--white); border-radius: 12px; border: 1px solid var(--border); box-shadow: var(--card-shadow); overflow: hidden; }
        .panel-header { padding: 20px 24px; border-bottom: 1px solid var(--border); background-color: rgba(168,53,53,0.03); display: flex; justify-content: space-between; align-items: center; }
        .panel-title { font-size: 18px; font-weight: 700; color: var(--primary); display: flex; align-items: center; gap: 10px; }
        .clear-link { font-size: 13px; color: var(--text-secondary); text-decoration: none; display: flex; align-items: center; gap: 5px; transition: color 0.2s; }
        .clear-link:hover { color: #c62828; }

        /* Cart table header */
        .cart-table-head {
            display: grid;
            grid-template-columns: 2fr 90px 110px 36px;
            gap: 12px;
            padding: 12px 24px;
            background: rgba(168,53,53,0.03);
            border-bottom: 1px solid var(--border);
            font-size: 12px; font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase; letter-spacing: 0.5px;
        }

        /* Cart rows (inside the update form) */
        .cart-row {
            display: grid;
            grid-template-columns: 2fr 90px 110px 36px;
            gap: 12px;
            padding: 16px 24px;
            border-bottom: 1px solid var(--border);
            align-items: center;
        }
        .cart-row:last-child { border-bottom: none; }

        .item-name  { font-weight: 600; font-size: 14px; color: var(--text-primary); }
        .item-cat   { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
        .item-price { font-size: 14px; color: var(--text-secondary); }
        .item-subtotal { font-weight: 700; font-size: 15px; color: var(--primary); }

        /* Quantity control */
        .qty-wrap { display: flex; align-items: center; }
        .qty-btn {
            width: 30px; height: 30px;
            background: var(--white); border: 1.5px solid var(--border);
            color: var(--text-primary); font-size: 16px; font-weight: 600;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: all 0.15s ease;
        }
        .qty-btn:hover { background: var(--accent); border-color: var(--primary); color: var(--primary); }
        .qty-btn.dec { border-radius: 6px 0 0 6px; border-right: none; }
        .qty-btn.inc { border-radius: 0 6px 6px 0; border-left: none; }
        .qty-input {
            width: 42px; height: 30px; text-align: center;
            border: 1.5px solid var(--border);
            border-left: none; border-right: none;
            font-size: 14px; font-weight: 600;
            background: var(--white); color: var(--text-primary);
        }
        .qty-input:focus { outline: none; }

        .remove-btn {
            background: none; border: none; color: var(--text-secondary);
            cursor: pointer; font-size: 15px; padding: 4px;
            border-radius: 5px; transition: all 0.15s ease;
            display: flex; align-items: center; justify-content: center;
        }
        .remove-btn:hover { color: #c62828; background: rgba(239,68,68,0.08); }

        /* Update button */
        .update-bar { padding: 14px 24px; border-top: 1px solid var(--border); background: rgba(168,53,53,0.02); }
        .update-btn {
            padding: 9px 20px; background: var(--white);
            border: 1.5px solid var(--primary); border-radius: 7px;
            color: var(--primary); font-size: 13px; font-weight: 600;
            cursor: pointer; transition: all 0.2s ease;
        }
        .update-btn:hover { background: rgba(168,53,53,0.06); }

        /* Browse more */
        .browse-bar { padding: 16px 24px; text-align: center; border-top: 1px solid var(--border); }
        .browse-link {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 22px;
            border: 1.5px dashed var(--primary);
            border-radius: 8px;
            color: var(--primary); text-decoration: none;
            font-size: 14px; font-weight: 600;
            transition: all 0.2s ease;
        }
        .browse-link:hover { background: rgba(168,53,53,0.05); }

        /* Empty cart */
        .empty-cart { padding: 50px 20px; text-align: center; color: var(--text-secondary); }
        .empty-cart i { font-size: 44px; opacity: 0.25; margin-bottom: 14px; display: block; }
        .empty-cart p { font-size: 15px; margin-bottom: 18px; }

        /* ── Summary panel ── */
        .summary-panel { background: var(--white); border-radius: 12px; border: 1px solid var(--border); box-shadow: var(--card-shadow); overflow: hidden; position: sticky; top: 90px; }
        .summary-header { padding: 20px 24px; border-bottom: 1px solid var(--border); background-color: rgba(168,53,53,0.03); }
        .summary-title { font-size: 17px; font-weight: 700; color: var(--primary); display: flex; align-items: center; gap: 8px; }
        .summary-body { padding: 20px 24px; }

        .summary-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 14px; }
        .summary-label { color: var(--text-secondary); }
        .summary-value { font-weight: 600; color: var(--text-primary); }
        .summary-divider { border: none; border-top: 1px solid var(--border); margin: 16px 0; }

        .total-row { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .total-label { font-size: 16px; font-weight: 700; color: var(--text-primary); }
        .total-value { font-size: 20px; font-weight: 700; color: var(--primary); }

        /* Notes textarea */
        .notes-label { font-size: 13px; font-weight: 600; color: var(--text-primary); margin-bottom: 8px; display: block; }
        .notes-textarea {
            width: 100%; padding: 10px 12px;
            border: 1.5px solid var(--border); border-radius: 8px;
            font-size: 13px; color: var(--text-primary);
            background: var(--accent); resize: vertical; min-height: 80px;
            transition: border-color 0.2s ease, background 0.2s ease;
            margin-bottom: 16px;
        }
        .notes-textarea:focus { outline: none; border-color: var(--primary); background: var(--white); box-shadow: 0 0 0 3px rgba(168,53,53,0.08); }

        /* Submit button */
        .submit-btn {
            width: 100%; padding: 14px;
            background-color: var(--primary); color: white;
            border: none; border-radius: 8px;
            font-weight: 700; font-size: 15px; cursor: pointer;
            transition: background-color 0.2s ease;
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .submit-btn:hover { background-color: #8b2a2a; }
        .submit-btn:disabled { background-color: #d1d5db; cursor: not-allowed; }

        /* Item count badge */
        .cart-count {
            background: var(--primary); color: white;
            border-radius: 12px; padding: 2px 8px;
            font-size: 12px; font-weight: 700;
        }

        /* Footer */
        .page-footer { text-align: center; padding: 22px; color: var(--text-secondary); font-size: 13px; border-top: 1px solid var(--border); background-color: var(--white); }
        .footer-links { margin-top: 8px; }
        .footer-links a { color: var(--primary); text-decoration: none; margin: 0 10px; }
        .footer-links a:hover { text-decoration: underline; }

        /* Responsive */
        @media (max-width: 1024px) {
            :root { --sidebar-width: 70px; }
            .logo-text, .nav-text, .user-details, .nav-title, .logout-link span { display: none; }
            .logo-area, .nav-section, .user-section { padding: 18px 12px; }
            .nav-link { justify-content: center; padding: 14px; border-left: none; border-right: 4px solid transparent; }
            .nav-link:hover, .nav-link.active { border-left: none; border-right-color: var(--primary); }
            .nav-icon { margin-right: 0; font-size: 20px; }
            .logout-link { justify-content: center; padding: 10px; }
        }

        @media (max-width: 900px) {
            .content-container { grid-template-columns: 1fr; }
            .summary-panel { position: static; }
        }

        @media (max-width: 600px) {
            .cart-table-head, .cart-row { grid-template-columns: 1fr 80px 30px; }
            .cart-table-head .col-price, .item-price { display: none; }
        }
    </style>
</head>
<body>

<?php include 'c_sidebar.php'; ?>

<main class="main-content">

    <header class="top-header">
        <div>
            <h1 class="page-title">Pre-order</h1>
            <p class="page-subtitle">Add items to your cart and submit a pre-order</p>
        </div>
    </header>

    <?php if ($message): ?>
        <div class="alert alert-<?= $msgType ?>">
            <i class="fas <?= $msgType === 'success' ? 'fa-check-circle' : ($msgType === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle') ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="content-container">

        <!-- ── LEFT: Cart panel ── -->
        <div class="cart-panel">
            <div class="panel-header">
                <div class="panel-title">
                    <i class="fas fa-shopping-basket"></i>
                    Your Cart
                    <?php if (!empty($cartItems)): ?>
                        <span class="cart-count"><?= count($cartItems) ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($cartItems)): ?>
                    <a href="c_preorder.php?action=clear" class="clear-link"
                       onclick="return confirm('Clear all items from your cart?')">
                        <i class="fas fa-trash"></i> Clear all
                    </a>
                <?php endif; ?>
            </div>

            <?php if (empty($cartItems)): ?>
                <div class="empty-cart">
                    <i class="fas fa-shopping-basket"></i>
                    <p>Your cart is empty.</p>
                    <a href="c_viewproducts.php" class="browse-link">
                        <i class="fas fa-box-open"></i> Browse Products
                    </a>
                </div>

            <?php else: ?>
                <!-- Column headers -->
                <div class="cart-table-head">
                    <div>Item</div>
                    <div class="col-price">Unit Price</div>
                    <div>Qty</div>
                    <div></div>
                </div>

                <!-- Qty update form -->
                <form method="POST" action="c_preorder.php" id="qtyForm">
                    <?php foreach ($cartItems as $item): ?>
                    <div class="cart-row">
                        <!-- Name + category -->
                        <div>
                            <div class="item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                            <?php if ($item['category']): ?>
                                <div class="item-cat"><?= htmlspecialchars($item['category']) ?></div>
                            <?php endif; ?>
                            <div class="item-subtotal">RM <?= number_format($item['subtotal'], 2) ?></div>
                        </div>

                        <!-- Unit price -->
                        <div class="item-price">RM <?= number_format($item['price'], 2) ?></div>

                        <!-- Quantity -->
                        <div class="qty-wrap">
                            <button type="button" class="qty-btn dec" data-pid="<?= htmlspecialchars($item['product_id']) ?>">−</button>
                            <input
                                type="number"
                                class="qty-input"
                                name="qty[<?= htmlspecialchars($item['product_id']) ?>]"
                                value="<?= $item['qty'] ?>"
                                min="1" max="99"
                                id="qty_<?= htmlspecialchars($item['product_id']) ?>"
                            >
                            <button type="button" class="qty-btn inc" data-pid="<?= htmlspecialchars($item['product_id']) ?>">+</button>
                        </div>

                        <!-- Remove -->
                        <a href="c_preorder.php?action=remove&product_id=<?= urlencode($item['product_id']) ?>"
                           class="remove-btn" title="Remove item">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                    <?php endforeach; ?>

                    <div class="update-bar">
                        <button type="submit" name="update_qty" class="update-btn">
                            <i class="fas fa-sync-alt"></i> Update quantities
                        </button>
                    </div>
                </form>

                <!-- Browse more -->
                <div class="browse-bar">
                    <a href="c_viewproducts.php" class="browse-link">
                        <i class="fas fa-plus"></i> Add more products
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── RIGHT: Summary + submit ── -->
        <div class="summary-panel">
            <div class="summary-header">
                <div class="summary-title"><i class="fas fa-receipt"></i> Order Summary</div>
            </div>
            <div class="summary-body">

                <?php if (!empty($cartItems)): ?>
                    <?php foreach ($cartItems as $item): ?>
                    <div class="summary-row">
                        <span class="summary-label"><?= htmlspecialchars($item['product_name']) ?> (×<?= $item['qty'] ?>)</span>
                        <span class="summary-value">RM <?= number_format($item['subtotal'], 2) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <hr class="summary-divider">
                    <div class="total-row">
                        <span class="total-label">Total</span>
                        <span class="total-value">RM <?= number_format($orderTotal, 2) ?></span>
                    </div>
                <?php else: ?>
                    <p style="color:var(--text-secondary);font-size:14px;text-align:center;padding:10px 0 20px;">
                        No items in cart yet.
                    </p>
                <?php endif; ?>

                <!-- Submit form -->
                <form method="POST" action="c_preorder.php">
                    <label class="notes-label" for="notes">
                        <i class="fas fa-sticky-note"></i> Special instructions <span style="font-weight:400;color:var(--text-secondary)">(optional)</span>
                    </label>
                    <textarea
                        id="notes"
                        name="notes"
                        class="notes-textarea"
                        placeholder="e.g. Please wrap items separately, deliver before Friday…"
                    ></textarea>

                    <button
                        type="submit"
                        name="submit_preorder"
                        class="submit-btn"
                        <?= empty($cartItems) ? 'disabled' : '' ?>
                        <?= !empty($cartItems) ? 'onclick="return confirm(\'Submit this pre-order?\')"' : '' ?>
                    >
                        <i class="fas fa-paper-plane"></i>
                        Submit Pre-order
                    </button>
                </form>

            </div>
        </div>

    </div><!-- /.content-container -->

    <footer class="page-footer">
        <div>&copy; <?= date('Y') ?> StationaryPlus — Stationery &amp; Printing Management System</div>
        <div class="footer-links">
            <a href="#">Help Center</a> |
            <a href="#">Contact Support</a> |
            <a href="#">Privacy Policy</a>
        </div>
    </footer>

</main>

<script>
    // +/- buttons update the qty input live and auto-submit the update form
    document.querySelectorAll('.qty-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const pid   = this.dataset.pid;
            const input = document.getElementById('qty_' + pid);
            let val     = parseInt(input.value) || 1;

            if (this.classList.contains('inc') && val < 99) val++;
            if (this.classList.contains('dec') && val > 1)  val--;

            input.value = val;
        });
    });
</script>

</body>
</html>