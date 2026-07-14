<?php
// ============================================================
//  ai_restock.php — AI Smart Restock Recommendations
//
//  POST: branch_id (optional — scope to branch)
//  Returns JSON: { success, recommendations: [
//    { product_name, branch_name, current_stock, minimum_level,
//      reorder_qty, priority, reason }
//  ]}
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role(['STAFF', 'ADMIN']);
require_once 'db.php';
require_once 'config.php';
require_once 'ai_helper.php';

header('Content-Type: application/json');
installJsonErrorGuard();

$branchId = trim($_POST['branch_id'] ?? '') ?: ($_SESSION['branch_id'] ?? null);

// ── 1. Fetch all low / out-of-stock items ─────────────────────
$whereItems  = ["i.stock_quantity <= i.minimum_level", "p.product_status = 'ACTIVE'"];
$paramItems  = [];
$typesItems  = '';

if ($branchId) {
    $whereItems[] = "i.branch_id = ?";
    $paramItems[] = $branchId;
    $typesItems  .= 's';
}

$stmt = $conn->prepare(
    "SELECT i.inventory_id, i.stock_quantity, i.minimum_level,
            p.product_id, p.product_name, p.category, p.price,
            b.branch_name
     FROM inventory i
     JOIN products p ON i.product_id  = p.product_id
     JOIN branches b ON i.branch_id   = b.branch_id
     WHERE " . implode(' AND ', $whereItems) . "
     ORDER BY i.stock_quantity ASC
     LIMIT 20"
);
if ($typesItems) $stmt->bind_param($typesItems, ...$paramItems);
$stmt->execute();
$lowItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($lowItems)) {
    echo json_encode(['success' => true, 'recommendations' => [], 'message' => 'All stock levels are healthy — no restock needed.']);
    exit;
}

// ── 2. Get 30-day sales velocity for each low-stock product ───
$productIds  = array_unique(array_column($lowItems, 'product_id'));
$placeholders = implode(',', array_fill(0, count($productIds), '?'));

$stmt = $conn->prepare(
    "SELECT oi.product_id, SUM(oi.quantity) AS sold_30d
     FROM order_items oi
     JOIN orders o ON oi.order_id = o.order_id
     WHERE oi.product_id IN ($placeholders)
       AND o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
       AND o.order_status NOT IN ('CANCELLED')
     GROUP BY oi.product_id"
);
$stmt->bind_param(str_repeat('s', count($productIds)), ...$productIds);
$stmt->execute();
$velocityRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$velocity = array_column($velocityRows, 'sold_30d', 'product_id');

// ── 3. Build prompt ───────────────────────────────────────────
$itemLines = array_map(function($item) use ($velocity) {
    $sold    = (int)($velocity[$item['product_id']] ?? 0);
    $dailyRate = round($sold / 30, 2);
    return "{$item['product_name']} | {$item['branch_name']} | "
         . "Stock: {$item['stock_quantity']} | Min: {$item['minimum_level']} | "
         . "Sold last 30 days: $sold units (~$dailyRate/day)";
}, $lowItems);

$prompt = "You are a stationery shop inventory manager.

The following products need restocking (format: Product | Branch | Current Stock | Min Required | Recent Sales):
" . implode("\n", $itemLines) . "

For each item, recommend a reorder quantity that covers approximately 45 days of demand based on the sales velocity.
- If sold 0 in 30 days, recommend restocking to 1.5× the minimum level as a safety buffer.
- Round to sensible quantities (multiples of 5 or 10 where possible).
- Assign priority: 'critical' (stock=0), 'high' (stock < 50% of minimum), 'medium' (stock <= minimum).

Reply ONLY with this JSON array, no markdown, no extra text:
[
  {\"product_name\":\"...\",\"branch_name\":\"...\",\"reorder_qty\":50,\"priority\":\"critical\",\"reason\":\"One sentence.\"},
  {\"product_name\":\"...\",\"branch_name\":\"...\",\"reorder_qty\":30,\"priority\":\"high\",\"reason\":\"One sentence.\"}
]";

// ── 4. Call Gemini ────────────────────────────────────────────
$aiRaw = callAI($prompt, 1500);

// ── 5. Parse response ─────────────────────────────────────────
$clean  = preg_replace('/```(?:json)?\s*([\s\S]*?)```/', '$1', trim($aiRaw));
$start  = strpos($clean, '[');
$end    = strrpos($clean, ']');
$parsed = null;

if ($start !== false && $end !== false) {
    $parsed = json_decode(substr($clean, $start, $end - $start + 1), true);
}

// Fallback — use raw DB data with simple calculation
if (!is_array($parsed) || empty($parsed)) {
    error_log("ai_restock: parse failed. Raw: " . substr($aiRaw, 0, 300));
    $parsed = array_map(function($item) use ($velocity) {
        $sold       = (int)($velocity[$item['product_id']] ?? 0);
        $daily      = $sold / 30;
        $reorderQty = $daily > 0
            ? (int)ceil($daily * 45)
            : (int)ceil($item['minimum_level'] * 1.5);
        $reorderQty = max(5, (int)(ceil($reorderQty / 5) * 5));

        $qty = (int)$item['stock_quantity'];
        $min = (int)$item['minimum_level'];
        $priority = $qty === 0 ? 'critical' : ($qty < $min / 2 ? 'high' : 'medium');

        return [
            'product_name' => $item['product_name'],
            'branch_name'  => $item['branch_name'],
            'reorder_qty'  => $reorderQty,
            'priority'     => $priority,
            'reason'       => $sold > 0
                ? "Selling ~" . round($daily, 1) . " units/day; order covers ~45 days."
                : "No recent sales but stock is at/below minimum safety level.",
        ];
    }, $lowItems);
}

// ── 6. Enrich with live DB values ────────────────────────────
// Build a quick lookup from DB results
$dbLookup = [];
foreach ($lowItems as $item) {
    $key = strtolower(trim($item['product_name'])) . '|' . strtolower(trim($item['branch_name']));
    $dbLookup[$key] = $item;
}

$enriched = array_map(function($rec) use ($dbLookup, $velocity) {
    $key  = strtolower(trim($rec['product_name'] ?? '')) . '|' . strtolower(trim($rec['branch_name'] ?? ''));
    $db   = $dbLookup[$key] ?? null;
    return [
        'product_name'  => $rec['product_name']  ?? '—',
        'branch_name'   => $rec['branch_name']   ?? '—',
        'current_stock' => $db ? (int)$db['stock_quantity'] : '?',
        'minimum_level' => $db ? (int)$db['minimum_level']  : '?',
        'sold_30d'      => $db ? (int)($velocity[$db['product_id']] ?? 0) : 0,
        'reorder_qty'   => max(1, (int)($rec['reorder_qty'] ?? 1)),
        'priority'      => in_array($rec['priority'] ?? '', ['critical','high','medium'])
                           ? $rec['priority'] : 'medium',
        'reason'        => htmlspecialchars($rec['reason'] ?? ''),
    ];
}, $parsed);

echo json_encode([
    'success'         => true,
    'recommendations' => array_slice($enriched, 0, count($lowItems)),
    'total_low'       => count($lowItems),
]);