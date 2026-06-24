<?php
// ============================================================
//  ai_recommend.php — AI Product Advisor
//
//  POST: query (string) — customer's natural language request
//  Returns JSON: { success, recommendations: [
//    { product_id, product_name, category, price, reason }
//  ]}
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role('CUSTOMER');
require_once 'db.php';
require_once 'config.php';
require_once 'ai_helper.php';

header('Content-Type: application/json');

$query = trim($_POST['query'] ?? '');

if (strlen($query) < 3) {
    echo json_encode(['success' => false, 'error' => 'Please describe what you need in a bit more detail.']);
    exit;
}

if (strlen($query) > 500) {
    echo json_encode(['success' => false, 'error' => 'Please keep your request under 500 characters.']);
    exit;
}

$branchId = $_SESSION['branch_id'] ?? null;

// ── Load in-stock product catalog ────────────────────────────
if ($branchId) {
    $stmt = $conn->prepare(
        "SELECT p.product_id, p.product_name, p.category, p.price,
                i.stock_quantity AS stock
         FROM products p
         JOIN inventory i ON p.product_id = i.product_id AND i.branch_id = ?
         WHERE p.product_status = 'ACTIVE'
           AND i.stock_quantity > 0
         ORDER BY p.category, p.product_name
         LIMIT 60"
    );
    $stmt->bind_param('s', $branchId);
} else {
    $stmt = $conn->prepare(
        "SELECT p.product_id, p.product_name, p.category, p.price,
                SUM(i.stock_quantity) AS stock
         FROM products p
         JOIN inventory i ON p.product_id = i.product_id
         WHERE p.product_status = 'ACTIVE'
         GROUP BY p.product_id
         HAVING SUM(i.stock_quantity) > 0
         ORDER BY p.category, p.product_name
         LIMIT 60"
    );
}
$stmt->execute();
$catalog = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($catalog)) {
    echo json_encode(['success' => false, 'error' => 'No products are currently available.']);
    exit;
}

// ── Build catalog text for the prompt ────────────────────────
$catalogText = implode("\n", array_map(function($p) {
    return "{$p['product_id']} | {$p['product_name']} | {$p['category']} | RM {$p['price']}";
}, $catalog));

// ── Build prompt ──────────────────────────────────────────────
$prompt = "You are a helpful stationery shop assistant. A customer has described what they need.

Customer's request: \"{$query}\"

Available products (ID | Name | Category | Price | Description):
{$catalogText}

Your job: Recommend the 3 most suitable products from the list above that best match the customer's request.
Choose products that genuinely help with their situation. Mix categories if it makes sense.

Reply ONLY with this JSON — no explanation, no markdown, no extra text:
[
  {\"product_id\":\"PROD-001\",\"reason\":\"One clear sentence explaining why this suits their need.\"},
  {\"product_id\":\"PROD-002\",\"reason\":\"One clear sentence explaining why this suits their need.\"},
  {\"product_id\":\"PROD-003\",\"reason\":\"One clear sentence explaining why this suits their need.\"}
]

If fewer than 3 products are relevant, still return exactly 3 by picking the closest matches.";

// ── Call Gemini ───────────────────────────────────────────────
$aiRaw = callAI($prompt, 400);

// ── Parse response ────────────────────────────────────────────
$clean  = preg_replace('/```(?:json)?\s*([\s\S]*?)```/', '$1', trim($aiRaw));
$start  = strpos($clean, '[');
$end    = strrpos($clean, ']');
$parsed = null;

if ($start !== false && $end !== false) {
    $parsed = json_decode(substr($clean, $start, $end - $start + 1), true);
}

// Fallback — return first 3 products if parse fails
if (!is_array($parsed) || count($parsed) === 0) {
    error_log("ai_recommend.php: parse failed. Raw: " . substr($aiRaw, 0, 300));
    $parsed = array_map(fn($p) => [
        'product_id' => $p['product_id'],
        'reason'     => 'This is one of our popular products that may suit your needs.',
    ], array_slice($catalog, 0, 3));
}

// ── Enrich with real DB product data ─────────────────────────
$recommendedIds = array_slice(array_column($parsed, 'product_id'), 0, 3);
$reasonMap      = array_column($parsed, 'reason', 'product_id');

$placeholders = implode(',', array_fill(0, count($recommendedIds), '?'));
$stmt = $conn->prepare(
    "SELECT product_id, product_name, category, price
     FROM products WHERE product_id IN ($placeholders)"
);
$stmt->bind_param(str_repeat('s', count($recommendedIds)), ...$recommendedIds);
$stmt->execute();
$products   = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$productMap = array_column($products, null, 'product_id');
$stmt->close();

// Build result in AI's order
$result = [];
foreach ($recommendedIds as $pid) {
    if (isset($productMap[$pid])) {
        $p           = $productMap[$pid];
        $p['reason'] = $reasonMap[$pid] ?? '';
        $result[]    = $p;
    }
}

// Fill up to 3 if any IDs were invalid
if (count($result) < 3) {
    foreach ($catalog as $p) {
        if (count($result) >= 3) break;
        if (!in_array($p['product_id'], array_column($result, 'product_id'))) {
            $p['reason'] = 'This product may help with your needs.';
            $result[]    = $p;
        }
    }
}

echo json_encode([
    'success'         => true,
    'recommendations' => array_slice($result, 0, 3),
]);