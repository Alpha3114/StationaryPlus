<?php
// ============================================================
//  c_preorder.php — Pre-order (Session Cart)
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role('CUSTOMER');
require_once 'db.php';
require_once 'pricing.php';
require_once 'branch_browse.php';

$userId = $_SESSION['user_id'];

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$message = '';
$msgType = '';

function generate_preorder_id(): string {
    return 'PRE-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

// Batch-fetches inventory rows for a set of product IDs in one query,
// mirroring the per-product "best branch by availability" fallback used
// when no branch is selected. Returns
// [product_id => ['inventory_id'=>, 'stock_quantity'=>, 'reserved_quantity'=>]].
function fetchInventoryForProducts(mysqli $conn, array $pids, ?string $branchId): array {
    if (empty($pids)) return [];
    $ph = implode(',', array_fill(0, count($pids), '?'));

    if ($branchId) {
        $stmt = $conn->prepare(
            "SELECT product_id, inventory_id, stock_quantity, reserved_quantity
             FROM inventory WHERE product_id IN ($ph) AND branch_id = ?"
        );
        $params = $pids;
        $params[] = $branchId;
        $stmt->bind_param(str_repeat('s', count($pids)) . 's', ...$params);
    } else {
        $stmt = $conn->prepare(
            "SELECT i.product_id, i.inventory_id, i.stock_quantity, i.reserved_quantity
             FROM inventory i
             WHERE i.product_id IN ($ph)
               AND i.inventory_id = (
                   SELECT i2.inventory_id FROM inventory i2
                   WHERE i2.product_id = i.product_id
                   ORDER BY (i2.stock_quantity - i2.reserved_quantity) DESC LIMIT 1
               )"
        );
        $stmt->bind_param(str_repeat('s', count($pids)), ...$pids);
    }
    $stmt->execute();
    $byProduct = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $byProduct[$row['product_id']] = $row;
    }
    $stmt->close();
    return $byProduct;
}

// ── GET actions ───────────────────────────────────────────────
$action    = $_GET['action']     ?? '';
$productId = trim($_GET['product_id'] ?? '');

if ($action === 'add' && $productId !== '') {
    $addQty = max(1, min(99, (int)($_GET['qty'] ?? 1)));

    $stmt = $conn->prepare(
        "SELECT product_id FROM products WHERE product_id = ? AND product_status = 'ACTIVE' LIMIT 1"
    );
    $stmt->bind_param('s', $productId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $_SESSION['cart'][$productId] = ($_SESSION['cart'][$productId] ?? 0) + $addQty;
        if (($_GET['redirect'] ?? '') === 'products') {
            header('Location: c_viewproducts.php?added=1'); exit;
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

// ── POST: submit pre-order ─────────────────────────────────────
// FIX #1: qty values from POST update the session cart BEFORE processing,
//          so the correct quantities are used when computing the total.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_preorder'])) {

    // Sync quantities from the submitted form into the session cart
    if (!empty($_POST['qty']) && is_array($_POST['qty'])) {
        foreach ($_POST['qty'] as $pid => $qty) {
            $qty = max(1, min(99, (int)$qty));
            if (isset($_SESSION['cart'][$pid])) {
                $_SESSION['cart'][$pid] = $qty;
            }
        }
    }

    $notes = trim($_POST['notes'] ?? '');

    if (empty($_SESSION['cart'])) {
        $message = 'Your cart is empty. Add items before submitting.';
        $msgType = 'error';
    } else {
        $pids         = array_keys($_SESSION['cart']);
        $placeholders = implode(',', array_fill(0, count($pids), '?'));
        $types        = str_repeat('s', count($pids));

        $stmt = $conn->prepare(
            "SELECT product_id, price, discount_percent, product_name FROM products
             WHERE product_id IN ($placeholders) AND product_status = 'ACTIVE'"
        );
        $stmt->bind_param($types, ...$pids);
        $stmt->execute();
        $priceRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // priceMap holds the discounted (actually-charged) unit price — the
        // customer catalog only ever displays and charges this price, never
        // the raw base price, so the two can't drift apart.
        $priceMap = [];
        $nameMap  = [];
        foreach ($priceRows as $row) {
            $priceMap[$row['product_id']] = discounted_price((float)$row['price'], (float)$row['discount_percent']);
            $nameMap[$row['product_id']]  = $row['product_name'];
        }

        $estimatedTotal = 0.0;
        foreach ($_SESSION['cart'] as $pid => $qty) {
            if (isset($priceMap[$pid])) {
                $estimatedTotal += $priceMap[$pid] * $qty;
            }
        }

        $branchId = $_SESSION['branch_id'] ?? null;

        // ── Stock reservation check ────────────────────────────────
        // Available stock = stock_quantity - reserved_quantity.
        // Validate every cart item has enough AVAILABLE stock before
        // creating the order, then reserve it inside the same transaction
        // so no other customer can claim the same units in the meantime.
        $stockErrors  = [];
        $invRowsToUse = []; // pid => inventory_id

        $invByProduct = fetchInventoryForProducts($conn, array_keys($_SESSION['cart']), $branchId);

        foreach ($_SESSION['cart'] as $pid => $qty) {
            if (!isset($priceMap[$pid])) continue;

            $invRow      = $invByProduct[$pid] ?? null;
            $available   = $invRow ? ((int)$invRow['stock_quantity'] - (int)$invRow['reserved_quantity']) : 0;
            $productName = $nameMap[$pid] ?? $pid;

            if (!$invRow || $available < $qty) {
                $stockErrors[] = $productName . " — only $available available (requested $qty)";
            } else {
                $invRowsToUse[$pid] = $invRow['inventory_id'];
            }
        }

        if (!empty($stockErrors)) {
            $message = "Some items don't have enough stock available:<br>• " . implode('<br>• ', array_map('htmlspecialchars', $stockErrors))
                      . "<br>Please adjust the quantities and try again.";
            $msgType = 'error';
        } else {

        // FIX #2: stamp branch_id on the order
        $orderId  = generate_preorder_id();
        $notesVal = $notes !== '' ? $notes : null;

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                "INSERT INTO orders
                    (order_id, user_id, order_type, order_status, estimated_total, notes, branch_id)
                 VALUES (?, ?, 'PREORDER', 'NEW', ?, ?, ?)"
            );
            $stmt->bind_param('ssdss', $orderId, $userId, $estimatedTotal, $notesVal, $branchId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare(
                "INSERT INTO order_items (order_item_id, order_id, product_id, quantity, unit_price)
                 VALUES (?, ?, ?, ?, ?)"
            );
            foreach ($_SESSION['cart'] as $pid => $qty) {
                if (!isset($priceMap[$pid])) continue;
                $itemId    = 'OI-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
                $unitPrice = $priceMap[$pid];
                $stmt->bind_param('sssid', $itemId, $orderId, $pid, $qty, $unitPrice);
                $stmt->execute();
            }
            $stmt->close();

            // Reserve stock for every item — physical stock is untouched
            // until the order is actually COLLECTED. Batched into one
            // UPDATE via CASE instead of one round trip per cart item.
            $caseParts  = [];
            $caseParams = [];
            $reserveIds = [];
            foreach ($_SESSION['cart'] as $pid => $qty) {
                if (!isset($invRowsToUse[$pid])) continue;
                $invId = $invRowsToUse[$pid];
                $caseParts[]  = "WHEN ? THEN reserved_quantity + ?";
                $caseParams[] = $invId;
                $caseParams[] = $qty;
                $reserveIds[] = $invId;
            }

            if (!empty($reserveIds)) {
                $idPh  = implode(',', array_fill(0, count($reserveIds), '?'));
                $rSql  = "UPDATE inventory SET reserved_quantity = CASE inventory_id "
                       . implode(' ', $caseParts)
                       . " END WHERE inventory_id IN ($idPh)";
                $rStmt  = $conn->prepare($rSql);
                $rTypes = str_repeat('si', count($reserveIds)) . str_repeat('s', count($reserveIds));
                $rStmt->bind_param($rTypes, ...array_merge($caseParams, $reserveIds));
                $rStmt->execute();
                $rStmt->close();
            }

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            $message = 'Something went wrong submitting your pre-order. Please try again.';
            $msgType = 'error';
        }

        if ($msgType !== 'error') {
            $itemCount = count(array_intersect_key($_SESSION['cart'], $priceMap));
            $_SESSION['cart'] = [];

            // ── Order confirmation email ───────────────────────────
            $cStmt = $conn->prepare("SELECT email, name FROM users WHERE user_id = ? LIMIT 1");
            $cStmt->bind_param('s', $userId);
            $cStmt->execute();
            $customer = $cStmt->get_result()->fetch_assoc();
            $cStmt->close();

            if ($customer && !empty($customer['email'])) {
                require_once 'mailer.php';
                $subject = "StationaryPlus — Pre-order Confirmed ($orderId)";
                $body    = "Hi {$customer['name']},\n\n"
                         . "Your pre-order has been received and is now being processed.\n\n"
                         . "Order ID: $orderId\n"
                         . "Items: $itemCount\n"
                         . "Estimated Total: RM " . number_format($estimatedTotal, 2) . "\n\n"
                         . "Track your order status: c_orderstatus.php\n\n"
                         . "— StationaryPlus Team";
                sendAppEmail($customer['email'], $subject, $body);
            }

            // FIX #3: remove the "We'll notify you" lie — link to order status instead
            $message = "Pre-order <strong>$orderId</strong> submitted! "
                     . "<a href='c_orderstatus.php' style='color:var(--success);font-weight:700;'>"
                     . "Track your order status &rarr;</a>";
            $msgType = 'success';
        }

        } // close: stock availability else-block
    }
}

// ── Fetch cart for display ────────────────────────────────────
$cartItems  = [];
$orderTotal = 0.0;

if (!empty($_SESSION['cart'])) {
    $pids         = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($pids), '?'));
    $types        = str_repeat('s', count($pids));

    $stmt = $conn->prepare(
        "SELECT product_id, product_name, price, discount_percent, category
         FROM products WHERE product_id IN ($placeholders) AND product_status = 'ACTIVE'"
    );
    $stmt->bind_param($types, ...$pids);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $cartBranchId = $_SESSION['branch_id'] ?? null;

    // Available stock, used only to cap the qty input in the UI —
    // the authoritative check still happens server-side at submit time.
    $invByProduct = fetchInventoryForProducts($conn, array_column($rows, 'product_id'), $cartBranchId);

    foreach ($rows as $row) {
        $qty                 = $_SESSION['cart'][$row['product_id']] ?? 1;
        $row['qty']          = $qty;
        $row['orig_price']   = (float)$row['price'];
        $row['discount_pct'] = (float)$row['discount_percent'];
        $row['price']        = discounted_price((float)$row['price'], (float)$row['discount_percent']);
        $row['subtotal']     = $row['price'] * $qty;

        $invRow = $invByProduct[$row['product_id']] ?? null;
        $row['available_stock'] = $invRow
            ? max(0, (int)$invRow['stock_quantity'] - (int)$invRow['reserved_quantity'])
            : 0;

        $cartItems[]     = $row;
        $orderTotal     += $row['subtotal'];
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
    <link rel="stylesheet" href="assets/css/tokens.css?v=<?= @filemtime(__DIR__.'/assets/css/tokens.css') ?>">
    <script src="assets/js/theme.js?v=<?= @filemtime(__DIR__.'/assets/js/theme.js') ?>"></script>
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?= @filemtime(__DIR__.'/assets/css/sidebar.css') ?>">
    <style>
        :root { --primary:#A83535;--secondary:#F4A261;--accent:#F1EDE8;--background:#FAFAFA;--text-primary:#2E2E2E;--text-secondary:#707070;--border:#E0E0E0;--white:#FFFFFF;--sidebar-width:260px;--card-shadow:0 4px 12px rgba(0,0,0,0.05); }
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',system-ui,sans-serif;}
        body{background-color:var(--background);color:var(--text-primary);min-height:100vh;display:flex;}
        .main-content{flex-grow:1;margin-left:var(--sidebar-width);min-height:100vh;display:flex;flex-direction:column;}
        .top-header{background-color:var(--white);padding:20px 30px;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:10;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;}
        .page-title{font-size:24px;font-weight:700;color:var(--text-primary);}
        .page-subtitle{font-size:14px;color:var(--text-secondary);margin-top:4px;}
        .alert{margin:20px 30px 0;padding:14px 18px;border-radius:8px;font-size:14px;display:flex;align-items:center;gap:10px;}
        .alert-success{background:var(--success-bg);color:var(--success);border:1px solid var(--success-border);}
        .alert-error{background:var(--danger-bg);color:var(--danger);border:1px solid var(--danger);}
        .alert-info{background:#e3f2fd;color:#1565c0;border:1px solid #90caf9;}
        .content-container{padding:24px 30px 30px;flex-grow:1;display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;}
        .cart-panel{background:var(--white);border-radius:12px;border:1px solid var(--border);box-shadow:var(--card-shadow);overflow:hidden;}
        .panel-header{padding:20px 24px;border-bottom:1px solid var(--border);background-color:var(--primary-tint-subtle);display:flex;justify-content:space-between;align-items:center;}
        .panel-title{font-size:18px;font-weight:700;color:var(--primary);display:flex;align-items:center;gap:10px;}
        .clear-link{font-size:13px;color:var(--text-secondary);text-decoration:none;display:flex;align-items:center;gap:5px;transition:color 0.2s;}
        .clear-link:hover{color:var(--danger);}
        .cart-table-head{display:grid;grid-template-columns:2fr 90px 110px 36px;gap:12px;padding:12px 24px;background:var(--primary-tint-subtle);border-bottom:1px solid var(--border);font-size:12px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.5px;}
        .cart-row{display:grid;grid-template-columns:2fr 90px 110px 36px;gap:12px;padding:16px 24px;border-bottom:1px solid var(--border);align-items:center;}
        .cart-row:last-child{border-bottom:none;}
        .item-name{font-weight:600;font-size:14px;color:var(--text-primary);}
        .item-cat{font-size:12px;color:var(--text-secondary);margin-top:2px;}
        .item-price{font-size:14px;color:var(--text-secondary);}
        .discount-tag{display:inline-block;margin-left:6px;padding:2px 7px;border-radius:10px;font-size:10px;font-weight:700;background:rgba(244,162,97,0.18);color:#b45309;vertical-align:middle;}
        .item-subtotal{font-weight:700;font-size:15px;color:var(--primary);}
        .qty-wrap{display:flex;align-items:center;}
        .qty-btn{width:30px;height:30px;background:var(--white);border:1.5px solid var(--border);color:var(--text-primary);font-size:16px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all 0.15s ease;}
        .qty-btn:hover{background:var(--accent);border-color:var(--primary);color:var(--primary);}
        .qty-btn.dec{border-radius:6px 0 0 6px;border-right:none;}
        .qty-btn.inc{border-radius:0 6px 6px 0;border-left:none;}
        .qty-input{width:42px;height:30px;text-align:center;border:1.5px solid var(--border);border-left:none;border-right:none;font-size:14px;font-weight:600;background:var(--white);color:var(--text-primary);}
        .qty-input:focus{outline:none;}
        .remove-btn{background:none;border:none;color:var(--text-secondary);cursor:pointer;font-size:15px;padding:4px;border-radius:5px;transition:all 0.15s ease;display:flex;align-items:center;justify-content:center;text-decoration:none;}
        .remove-btn:hover{color:var(--danger);background:rgba(239,68,68,0.08);}
        .browse-bar{padding:16px 24px;text-align:center;border-top:1px solid var(--border);}
        .browse-link{display:inline-flex;align-items:center;gap:8px;padding:10px 22px;border:1.5px dashed var(--primary);border-radius:8px;color:var(--primary);text-decoration:none;font-size:14px;font-weight:600;transition:all 0.2s ease;}
        .browse-link:hover{background:var(--primary-tint-subtle);}
        .empty-cart{padding:50px 20px;text-align:center;color:var(--text-secondary);}
        .empty-cart i{font-size:44px;opacity:0.25;margin-bottom:14px;display:block;}
        .empty-cart p{font-size:15px;margin-bottom:18px;}
        .summary-panel{background:var(--white);border-radius:12px;border:1px solid var(--border);box-shadow:var(--card-shadow);overflow:hidden;position:sticky;top:90px;}
        .summary-header{padding:20px 24px;border-bottom:1px solid var(--border);background-color:var(--primary-tint-subtle);}
        .summary-title{font-size:17px;font-weight:700;color:var(--primary);display:flex;align-items:center;gap:8px;}
        .summary-body{padding:20px 24px;}
        .summary-row{display:flex;justify-content:space-between;margin-bottom:12px;font-size:14px;}
        .summary-label{color:var(--text-secondary);}
        .summary-value{font-weight:600;color:var(--text-primary);}
        .summary-divider{border:none;border-top:1px solid var(--border);margin:16px 0;}
        .total-row{display:flex;justify-content:space-between;margin-bottom:20px;}
        .total-label{font-size:16px;font-weight:700;color:var(--text-primary);}
        .total-value{font-size:20px;font-weight:700;color:var(--primary);}
        .notes-label{font-size:13px;font-weight:600;color:var(--text-primary);margin-bottom:8px;display:block;}
        .notes-textarea{width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;color:var(--text-primary);background:var(--accent);resize:vertical;min-height:80px;transition:border-color 0.2s ease,background 0.2s ease;margin-bottom:16px;font-family:inherit;}
        .notes-textarea:focus{outline:none;border-color:var(--primary);background:var(--white);box-shadow:0 0 0 3px var(--primary-tint-light);}
        .submit-btn{width:100%;padding:14px;background-color:var(--primary);color:var(--on-primary);border:none;border-radius:8px;font-weight:700;font-size:15px;cursor:pointer;transition:background-color 0.2s ease;display:flex;align-items:center;justify-content:center;gap:10px;}
        .submit-btn:hover{background-color:var(--primary-dark);}
        .submit-btn:disabled{background-color:#d1d5db;cursor:not-allowed;}
        .cart-count{background:var(--primary);color:var(--on-primary);border-radius:12px;padding:2px 8px;font-size:12px;font-weight:700;}
        .page-footer{text-align:center;padding:22px;color:var(--text-secondary);font-size:13px;border-top:1px solid var(--border);background-color:var(--white);}
        .footer-links{margin-top:8px;}
        .footer-links a{color:var(--primary);text-decoration:none;margin:0 10px;}
        @media(max-width:900px){.content-container{grid-template-columns:1fr;}.summary-panel{position:static;}}
        @media(max-width:600px){.cart-table-head,.cart-row{grid-template-columns:1fr 80px 30px;}.cart-table-head .col-price,.item-price{display:none;}}
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

<?php include 'c_sidebar.php'; ?>

<main class="main-content">
    <header class="top-header">
        <div>
            <h1 class="page-title">Pre-order</h1>
            <p class="page-subtitle">Add items to your cart and submit a pre-order</p>
        </div>
        <div style="display:flex;align-items:center;gap:12px;">
        <div style="display:flex;flex-direction:column;gap:3px;">
            <?php render_browsing_branch_bar(); ?>
            <?php render_branch_unavailable_notice(); ?>
        </div>
        <button type="button" class="theme-toggle-header-btn" data-theme-cycle title="Theme" aria-label="Theme"><i class="fas fa-sun"></i></button>
        </div>
    </header>
    <script>if (window.initThemeToggle) initThemeToggle();</script>

    <?php if ($message): ?>
    <div class="alert alert-<?= $msgType ?>">
        <i class="fas <?= $msgType === 'success' ? 'fa-check-circle' : ($msgType === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle') ?>"></i>
        <?= $message ?>
    </div>
    <?php endif; ?>

    <div class="content-container">

        <!-- LEFT: Cart -->
        <div class="cart-panel">
            <div class="panel-header">
                <div class="panel-title">
                    <i class="fas fa-shopping-basket"></i> Your Cart
                    <?php if (!empty($cartItems)): ?>
                        <span class="cart-count"><?= count($cartItems) ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($cartItems)): ?>
                <a href="c_preorder.php?action=clear" class="clear-link"
                   onclick="return confirmClearCart(event, this)">
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
            <div class="cart-table-head">
                <div>Item</div>
                <div class="col-price">Unit Price</div>
                <div>Qty</div>
                <div></div>
            </div>

            <?php foreach ($cartItems as $item): ?>
            <div class="cart-row">
                <div>
                    <div class="item-name">
                        <?= htmlspecialchars($item['product_name']) ?>
                        <?php if ($item['discount_pct'] > 0): ?>
                        <span class="discount-tag">-<?= rtrim(rtrim(number_format($item['discount_pct'], 2), '0'), '.') ?>%</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($item['category']): ?>
                        <div class="item-cat"><?= htmlspecialchars($item['category']) ?></div>
                    <?php endif; ?>
                    <div class="item-subtotal" id="sub_<?= htmlspecialchars($item['product_id']) ?>">
                        RM <?= number_format($item['subtotal'], 2) ?>
                    </div>
                </div>
                <div class="item-price">
                    <?php if ($item['discount_pct'] > 0): ?>
                    <span style="text-decoration:line-through;color:var(--text-secondary);opacity:0.7;">RM <?= number_format($item['orig_price'], 2) ?></span><br>
                    <?php endif; ?>
                    RM <?= number_format($item['price'], 2) ?>
                </div>
                <div class="qty-wrap">
                    <?php $qtyCap = max(1, min(99, (int)$item['available_stock'])); ?>
                    <button type="button" class="qty-btn dec" data-pid="<?= htmlspecialchars($item['product_id']) ?>">−</button>
                    <input type="number" class="qty-input"
                           id="qty_<?= htmlspecialchars($item['product_id']) ?>"
                           value="<?= min($item['qty'], $qtyCap) ?>" min="1" max="<?= $qtyCap ?>"
                           data-price="<?= $item['price'] ?>"
                           data-pid="<?= htmlspecialchars($item['product_id']) ?>"
                           oninput="updateTotals()">
                    <button type="button" class="qty-btn inc" data-pid="<?= htmlspecialchars($item['product_id']) ?>">+</button>
                </div>
                <a href="c_preorder.php?action=remove&product_id=<?= urlencode($item['product_id']) ?>"
                   class="remove-btn" title="Remove">
                    <i class="fas fa-times"></i>
                </a>
            </div>
            <?php endforeach; ?>

            <!-- Frequently Bought Together — populated by JS, hidden if empty -->
            <div id="fbtSection" style="display:none;margin:18px 0 4px;padding-top:16px;border-top:1px solid var(--border);">
                <div style="font-size:13px;font-weight:700;color:var(--text-primary);margin-bottom:10px;display:flex;align-items:center;gap:7px;">
                    <i class="fas fa-layer-group" style="color:var(--primary);"></i>
                    Frequently Bought Together
                </div>
                <div id="fbtGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;"></div>
            </div>

            <div class="browse-bar">
                <a href="c_viewproducts.php" class="browse-link">
                    <i class="fas fa-plus"></i> Add more products
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT: Summary + submit -->
        <!-- FIX #1: Both qty inputs AND submit button are in ONE form -->
        <div class="summary-panel">
            <div class="summary-header">
                <div class="summary-title"><i class="fas fa-receipt"></i> Order Summary</div>
            </div>
            <div class="summary-body">

                <form method="POST" action="c_preorder.php" id="orderForm">

                    <?php if (!empty($cartItems)): ?>
                    <?php foreach ($cartItems as $item): ?>
                    <!-- Hidden qty mirrors — synced by JS before submit -->
                    <input type="hidden"
                           name="qty[<?= htmlspecialchars($item['product_id']) ?>]"
                           id="hqty_<?= htmlspecialchars($item['product_id']) ?>"
                           value="<?= $item['qty'] ?>">
                    <div class="summary-row">
                        <span class="summary-label" id="slabel_<?= htmlspecialchars($item['product_id']) ?>">
                            <?= htmlspecialchars($item['product_name']) ?> (×<?= $item['qty'] ?>)
                        </span>
                        <span class="summary-value" id="sval_<?= htmlspecialchars($item['product_id']) ?>">
                            RM <?= number_format($item['subtotal'], 2) ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                    <hr class="summary-divider">
                    <div class="total-row">
                        <span class="total-label">Total</span>
                        <span class="total-value" id="grand-total">RM <?= number_format($orderTotal, 2) ?></span>
                    </div>
                    <?php else: ?>
                    <p style="color:var(--text-secondary);font-size:14px;text-align:center;padding:10px 0 20px;">
                        No items in cart yet.
                    </p>
                    <?php endif; ?>

                    <label class="notes-label" for="notes">
                        <i class="fas fa-sticky-note"></i>
                        Special instructions <span style="font-weight:400;color:var(--text-secondary)">(optional)</span>
                    </label>
                    <textarea id="notes" name="notes" class="notes-textarea"
                              placeholder="e.g. Please wrap items separately…"></textarea>

                    <button type="submit" class="submit-btn"
                            <?= empty($cartItems) ? 'disabled' : '' ?>
                            <?= !empty($cartItems) ? 'onclick="return confirmSubmitPreorder(event, this)"' : '' ?>>
                        <i class="fas fa-paper-plane"></i> Submit Pre-order
                    </button>
                    <!-- Hidden fallback: form.submit() via JS does not include the
                         triggering button's name=value pair, so this ensures PHP
                         still sees submit_preorder in $_POST regardless. -->
                    <input type="hidden" name="submit_preorder" value="1">
                </form>

            </div>
        </div>

    </div>

    <footer class="page-footer">
        &copy; <?= date('Y') ?> StationaryPlus &mdash; Stationery &amp; Printing Management System
    </footer>
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

// ── Page-specific confirm helpers ──────────────────────────────
// confirm() is synchronous and can return false inline; our custom
// dialog is async, so these intercept the default action, await the
// dialog, then manually proceed (navigate / submit) if confirmed.
function confirmClearCart(e, link) {
    e.preventDefault();
    customConfirm('Clear all items from your cart?', { danger: true, confirmText: 'Clear Cart' })
        .then(ok => { if (ok) window.location.href = link.href; });
    return false;
}
function confirmSubmitPreorder(e, btn) {
    e.preventDefault();
    customConfirm('Submit this pre-order?', { confirmText: 'Submit' })
        .then(ok => { if (ok) btn.closest('form').submit(); });
    return false;
}

// +/- buttons update both the visible input AND the hidden mirror in the form
document.querySelectorAll('.qty-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        const pid   = this.dataset.pid;
        const input = document.getElementById('qty_' + pid);
        const cap   = parseInt(input.max, 10) || 99;
        let val     = parseInt(input.value) || 1;
        if (this.classList.contains('inc') && val < cap) val++;
        if (this.classList.contains('dec') && val > 1)   val--;
        input.value = val;
        syncHidden(pid, val);
        updateTotals();
    });
});

function syncHidden(pid, val) {
    const h = document.getElementById('hqty_' + pid);
    if (h) h.value = val;
}

function updateTotals() {
    let grandTotal = 0;
    document.querySelectorAll('.qty-input[data-price]').forEach(input => {
        const pid      = input.dataset.pid;
        const price    = parseFloat(input.dataset.price);
        const cap      = parseInt(input.max, 10) || 99;
        const qty      = Math.max(1, Math.min(cap, parseInt(input.value) || 1));
        input.value    = qty;
        const subtotal = price * qty;
        grandTotal    += subtotal;

        syncHidden(pid, qty);

        const subEl = document.getElementById('sub_' + pid);
        if (subEl) subEl.textContent = 'RM ' + subtotal.toFixed(2);

        const labelEl = document.getElementById('slabel_' + pid);
        if (labelEl) {
            const name = labelEl.textContent.split(' (×')[0];
            labelEl.textContent = name + ' (×' + qty + ')';
        }
        const valEl = document.getElementById('sval_' + pid);
        if (valEl) valEl.textContent = 'RM ' + subtotal.toFixed(2);
    });

    const totalEl = document.getElementById('grand-total');
    if (totalEl) totalEl.textContent = 'RM ' + grandTotal.toFixed(2);
}

// ── Frequently Bought Together ─────────────────────────────────
function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function loadFrequentlyBoughtTogether() {
    const section = document.getElementById('fbtSection');
    const grid    = document.getElementById('fbtGrid');
    if (!section || !grid) return; // cart is empty, section wasn't rendered at all

    try {
        const res  = await fetch('cart_recommend.php');
        const data = await res.json();

        if (!data.success || !data.recommendations?.length) {
            return; // stay hidden — no forced "popular" fallback here, dashboard already covers that
        }

        grid.innerHTML = data.recommendations.map(p => `
            <div style="border:1px solid var(--border);border-radius:8px;padding:10px;background:var(--white);">
                <div style="font-size:12px;font-weight:600;color:var(--text-primary);margin-bottom:3px;line-height:1.3;">
                    ${escHtml(p.product_name)}
                </div>
                <div style="font-size:11px;color:var(--text-secondary);margin-bottom:6px;">
                    ${escHtml(p.freq_label)}
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;gap:6px;">
                    <span style="font-size:13px;font-weight:700;color:var(--primary);">RM ${parseFloat(p.price).toFixed(2)}</span>
                    <a href="c_preorder.php?action=add&product_id=${encodeURIComponent(p.product_id)}"
                       style="font-size:11px;font-weight:600;color:var(--white);background:var(--primary);
                              padding:5px 9px;border-radius:6px;text-decoration:none;white-space:nowrap;">
                        <i class="fas fa-plus"></i> Add
                    </a>
                </div>
            </div>`
        ).join('');

        section.style.display = 'block';

    } catch (e) {
        // fail silently — this is a nice-to-have suggestion strip, not
        // core cart functionality, so a network hiccup shouldn't show
        // an error to the customer here
    }
}

window.addEventListener('load', loadFrequentlyBoughtTogether);
</script>

</body>
</html>