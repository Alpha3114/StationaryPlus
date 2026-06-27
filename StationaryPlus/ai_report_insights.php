<?php
// ============================================================
//  ai_report_insights.php — AI narrative for a_report.php
//
//  POST: period, date_from, date_to
//  Returns JSON: { success, insights: { summary, highlights[],
//                  concerns[], recommendation } }
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role('ADMIN');
require_once 'db.php';
require_once 'config.php';
require_once 'ai_helper.php';

header('Content-Type: application/json');

// ── Resolve period → SQL range ─────────────────────────────────
$period   = trim($_POST['period']    ?? 'month');
$dateFrom = trim($_POST['date_from'] ?? '');
$dateTo   = trim($_POST['date_to']   ?? '');

if ($period === 'custom' && $dateFrom && $dateTo) {
    $rangeSQL = "AND DATE(o.order_date) BETWEEN '$dateFrom' AND '$dateTo'";
    $prevSQL  = '';
    $label    = "$dateFrom to $dateTo";
} else {
    [$rangeSQL, $label] = match($period) {
        'today'     => ["AND DATE(o.order_date)=CURDATE()", 'Today'],
        'week'      => ["AND o.order_date>=DATE_SUB(NOW(),INTERVAL 7 DAY)", 'Last 7 Days'],
        'lastmonth' => ["AND MONTH(o.order_date)=MONTH(DATE_SUB(NOW(),INTERVAL 1 MONTH)) AND YEAR(o.order_date)=YEAR(DATE_SUB(NOW(),INTERVAL 1 MONTH))", 'Last Month'],
        'quarter'   => ["AND o.order_date>=DATE_SUB(NOW(),INTERVAL 3 MONTH)", 'This Quarter'],
        'year'      => ["AND YEAR(o.order_date)=YEAR(NOW())", 'This Year'],
        'alltime'   => ['', 'All Time'],
        default     => ["AND MONTH(o.order_date)=MONTH(NOW()) AND YEAR(o.order_date)=YEAR(NOW())", 'This Month'],
    };

    $prevSQL = match($period) {
        'today'     => "AND DATE(o.order_date)=DATE_SUB(CURDATE(),INTERVAL 1 DAY)",
        'week'      => "AND o.order_date>=DATE_SUB(NOW(),INTERVAL 14 DAY) AND o.order_date<DATE_SUB(NOW(),INTERVAL 7 DAY)",
        'quarter'   => "AND o.order_date>=DATE_SUB(NOW(),INTERVAL 6 MONTH) AND o.order_date<DATE_SUB(NOW(),INTERVAL 3 MONTH)",
        'year'      => "AND YEAR(o.order_date)=YEAR(NOW())-1",
        default     => "AND MONTH(o.order_date)=MONTH(DATE_SUB(NOW(),INTERVAL 1 MONTH)) AND YEAR(o.order_date)=YEAR(DATE_SUB(NOW(),INTERVAL 1 MONTH))",
    };
}

// ── Fetch key stats ────────────────────────────────────────────

// Current period revenue & orders
$r = $conn->query("SELECT COALESCE(SUM(p.amount),0) AS rev, COUNT(DISTINCT p.order_id) AS cnt
    FROM payments p JOIN orders o ON p.order_id=o.order_id
    WHERE p.verification_status='VALID' $rangeSQL");
$cur = $r->fetch_assoc();
$revenue = (float)$cur['rev'];
$orders  = (int)$cur['cnt'];
$avgOrder = $orders > 0 ? round($revenue / $orders, 2) : 0;

// Previous period (for comparison)
$prevRevenue = 0; $prevOrders = 0;
if ($prevSQL) {
    $r = $conn->query("SELECT COALESCE(SUM(p.amount),0) AS rev, COUNT(DISTINCT p.order_id) AS cnt
        FROM payments p JOIN orders o ON p.order_id=o.order_id
        WHERE p.verification_status='VALID' $prevSQL");
    $prev = $r->fetch_assoc();
    $prevRevenue = (float)$prev['rev'];
    $prevOrders  = (int)$prev['cnt'];
}
$revChange = $prevRevenue > 0
    ? round((($revenue - $prevRevenue) / $prevRevenue) * 100, 1)
    : ($revenue > 0 ? 100 : 0);

// Top 3 products
$r = $conn->query(
    "SELECT p.product_name, p.category, SUM(oi.quantity) AS qty, SUM(oi.quantity*oi.unit_price) AS rev
     FROM order_items oi
     JOIN orders o ON oi.order_id=o.order_id
     JOIN products p ON oi.product_id=p.product_id
     WHERE 1=1 $rangeSQL
     GROUP BY oi.product_id ORDER BY rev DESC LIMIT 3"
);
$topProducts = $r->fetch_all(MYSQLI_ASSOC);

// Top branch
$r = $conn->query(
    "SELECT b.branch_name, COALESCE(SUM(p.amount),0) AS rev
     FROM payments p
     JOIN orders o ON p.order_id=o.order_id
     JOIN users u ON o.user_id=u.user_id
     JOIN branches b ON u.branch_id=b.branch_id
     WHERE p.verification_status='VALID' $rangeSQL
     GROUP BY b.branch_id ORDER BY rev DESC LIMIT 1"
);
$topBranch = $r->fetch_assoc();

// Pending payments
$r = $conn->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM payments WHERE verification_status='PENDING'");
$pend = $r->fetch_assoc();

// Low stock
$r = $conn->query("SELECT COUNT(*) AS cnt FROM inventory WHERE stock_quantity <= minimum_level AND stock_quantity > 0");
$lowStock = (int)$r->fetch_assoc()['cnt'];
$r = $conn->query("SELECT COUNT(*) AS cnt FROM inventory WHERE stock_quantity = 0");
$outOfStock = (int)$r->fetch_assoc()['cnt'];

// ── Build prompt ───────────────────────────────────────────────
$topProdText = empty($topProducts)
    ? 'No sales data available.'
    : implode(', ', array_map(fn($p) => "{$p['product_name']} (RM " . number_format($p['rev'], 2) . ")", $topProducts));

$branchText   = $topBranch ? "{$topBranch['branch_name']} (RM " . number_format($topBranch['rev'], 2) . ")" : 'No branch data';
$revChangeText = ($revChange >= 0 ? '+' : '') . $revChange . '% vs previous period';

$prompt = "You are a business analyst for StationaryPlus, a Malaysian stationery retail chain.

Here are the sales figures for: $label

REVENUE METRICS:
- Total revenue: RM " . number_format($revenue, 2) . "
- Revenue change: $revChangeText
- Total orders: $orders (previously: $prevOrders)
- Average order value: RM $avgOrder

TOP PERFORMERS:
- Best-selling products: $topProdText
- Top revenue branch: $branchText

ALERTS:
- Pending unverified payments: {$pend['cnt']} (RM " . number_format($pend['total'], 2) . ")
- Low stock items: $lowStock
- Out of stock items: $outOfStock

Based on this data, provide a concise business analysis. Reply ONLY with this JSON, no markdown, no extra text:
{
  \"summary\": \"One sentence executive summary of performance this period.\",
  \"highlights\": [
    \"Specific positive observation 1.\",
    \"Specific positive observation 2.\"
  ],
  \"concerns\": [
    \"Specific concern or risk based on the data.\"
  ],
  \"recommendation\": \"One concrete, actionable recommendation for the store manager.\"
}

Be specific and data-driven. Reference actual figures from the data above.";

// ── Call Gemini ────────────────────────────────────────────────
$aiRaw = callAI($prompt, 1500);

// ── Parse response ─────────────────────────────────────────────
$clean  = preg_replace('/```(?:json)?\s*([\s\S]*?)```/', '$1', trim($aiRaw));
$start  = strpos($clean, '{');
$end    = strrpos($clean, '}');
$parsed = null;

if ($start !== false && $end !== false) {
    $parsed = json_decode(substr($clean, $start, $end - $start + 1), true);
}

if (!$parsed || !isset($parsed['summary'])) {
    // Fallback — extract whatever useful text came back
    echo json_encode([
        'success'  => false,
        'error'    => 'AI response could not be parsed. Raw: ' . substr($aiRaw, 0, 200),
    ]);
    exit;
}

echo json_encode([
    'success'  => true,
    'period'   => $label,
    'insights' => $parsed,
]);