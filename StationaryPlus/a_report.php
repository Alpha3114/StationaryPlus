<?php
// ============================================================
//  a_report.php — Admin Analytics Dashboard (Full Live Data)
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role('ADMIN');
require_once 'db.php';

// ── Period & tab ──────────────────────────────────────────────
$period   = $_GET['period']    ?? 'month';
$tab      = $_GET['tab']       ?? 'overview';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';

// Build date range SQL (orders table alias = o, payments alias = p)
if ($period === 'custom' && $dateFrom && $dateTo) {
    $rangeSQL    = "AND DATE(o.order_date) BETWEEN '$dateFrom' AND '$dateTo'";
    $rangePaySQL = "AND DATE(p.record_date) BETWEEN '$dateFrom' AND '$dateTo'";
    $periodLabel = date('d M Y', strtotime($dateFrom)) . ' – ' . date('d M Y', strtotime($dateTo));
} else {
    [$rangeSQL, $rangePaySQL, $periodLabel] = match($period) {
        'today'     => [
            "AND DATE(o.order_date)=CURDATE()",
            "AND DATE(p.record_date)=CURDATE()",
            'Today',
        ],
        'week'      => [
            "AND o.order_date>=DATE_SUB(NOW(),INTERVAL 7 DAY)",
            "AND p.record_date>=DATE_SUB(NOW(),INTERVAL 7 DAY)",
            'Last 7 Days',
        ],
        'month'     => [
            "AND MONTH(o.order_date)=MONTH(NOW()) AND YEAR(o.order_date)=YEAR(NOW())",
            "AND MONTH(p.record_date)=MONTH(NOW()) AND YEAR(p.record_date)=YEAR(NOW())",
            'This Month',
        ],
        'lastmonth' => [
            "AND MONTH(o.order_date)=MONTH(DATE_SUB(NOW(),INTERVAL 1 MONTH)) AND YEAR(o.order_date)=YEAR(DATE_SUB(NOW(),INTERVAL 1 MONTH))",
            "AND MONTH(p.record_date)=MONTH(DATE_SUB(NOW(),INTERVAL 1 MONTH)) AND YEAR(p.record_date)=YEAR(DATE_SUB(NOW(),INTERVAL 1 MONTH))",
            'Last Month',
        ],
        'quarter'   => [
            "AND o.order_date>=DATE_SUB(NOW(),INTERVAL 3 MONTH)",
            "AND p.record_date>=DATE_SUB(NOW(),INTERVAL 3 MONTH)",
            'This Quarter',
        ],
        'year'      => [
            "AND YEAR(o.order_date)=YEAR(NOW())",
            "AND YEAR(p.record_date)=YEAR(NOW())",
            'This Year',
        ],
        'alltime'   => ["", "", 'All Time'],
        default     => [
            "AND MONTH(o.order_date)=MONTH(NOW()) AND YEAR(o.order_date)=YEAR(NOW())",
            "AND MONTH(p.record_date)=MONTH(NOW()) AND YEAR(p.record_date)=YEAR(NOW())",
            'This Month',
        ],
    };
}

// Previous period SQL (for growth %)
$prevSQL = match($period) {
    'today'     => "AND DATE(o.order_date)=DATE_SUB(CURDATE(),INTERVAL 1 DAY)",
    'week'      => "AND o.order_date>=DATE_SUB(NOW(),INTERVAL 14 DAY) AND o.order_date<DATE_SUB(NOW(),INTERVAL 7 DAY)",
    'month'     => "AND MONTH(o.order_date)=MONTH(DATE_SUB(NOW(),INTERVAL 1 MONTH)) AND YEAR(o.order_date)=YEAR(DATE_SUB(NOW(),INTERVAL 1 MONTH))",
    'lastmonth' => "AND MONTH(o.order_date)=MONTH(DATE_SUB(NOW(),INTERVAL 2 MONTH)) AND YEAR(o.order_date)=YEAR(DATE_SUB(NOW(),INTERVAL 2 MONTH))",
    'quarter'   => "AND o.order_date>=DATE_SUB(NOW(),INTERVAL 6 MONTH) AND o.order_date<DATE_SUB(NOW(),INTERVAL 3 MONTH)",
    'year'      => "AND YEAR(o.order_date)=YEAR(NOW())-1",
    default     => "",
};

function growthPct(float $cur, float $prev): array {
    if ($prev == 0) { $pct = $cur > 0 ? 100 : 0; $dir = $cur > 0 ? 'up' : 'flat'; }
    else { $pct = (($cur - $prev) / $prev) * 100; $dir = $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat'); }
    return ['pct' => ($dir==='up'?'+':'') . number_format($pct,1) . '%', 'dir' => $dir];
}

// ==============================================================
//  OVERVIEW STATS
// ==============================================================
$res = $conn->query("SELECT COALESCE(SUM(p.amount),0) AS revenue, COUNT(DISTINCT p.order_id) AS order_count FROM payments p JOIN orders o ON p.order_id=o.order_id WHERE p.verification_status='VALID' $rangeSQL");
$curr = $res->fetch_assoc();
$totalRevenue = (float)$curr['revenue'];
$totalOrders  = (int)$curr['order_count'];
$avgOrder     = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

$res = $conn->query("SELECT COALESCE(SUM(p.amount),0) AS revenue, COUNT(DISTINCT p.order_id) AS order_count FROM payments p JOIN orders o ON p.order_id=o.order_id WHERE p.verification_status='VALID' $prevSQL");
$prev = $res->fetch_assoc();
$prevRevenue = (float)$prev['revenue'];
$prevOrders  = (int)$prev['order_count'];
$prevAvg     = $prevOrders > 0 ? $prevRevenue / $prevOrders : 0;

$revGrowth = growthPct($totalRevenue, $prevRevenue);
$ordGrowth = growthPct($totalOrders,  $prevOrders);
$avgGrowth = growthPct($avgOrder,     $prevAvg);

$res = $conn->query("SELECT COUNT(DISTINCT o.user_id) AS cnt FROM orders o WHERE 1=1 $rangeSQL");
$activeCustomers = (int)$res->fetch_assoc()['cnt'];

$res = $conn->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM payments WHERE verification_status='PENDING'");
$pend = $res->fetch_assoc();
$pendingCount = (int)$pend['cnt']; $pendingAmt = (float)$pend['total'];

$res = $conn->query("SELECT COUNT(*) AS cnt FROM orders o WHERE o.order_status='CANCELLED' $rangeSQL");
$cancelledOrders = (int)$res->fetch_assoc()['cnt'];

$res = $conn->query("SELECT COUNT(*) AS cnt FROM orders WHERE order_type='PREORDER'");
$totalPreorders = (int)$res->fetch_assoc()['cnt'];

// ==============================================================
//  REVENUE TREND
// ==============================================================
$trendMonths = 12;
$monthSlots  = [];
for ($i = $trendMonths - 1; $i >= 0; $i--) {
    $ts = strtotime("-$i month");
    $monthSlots[] = ['key' => date('Y-m', $ts), 'label' => date('M', $ts)];
}

$res = $conn->query(
    "SELECT DATE_FORMAT(o.order_date,'%Y-%m') AS mkey,
            COALESCE(SUM(p.amount),0) AS revenue,
            COUNT(DISTINCT p.order_id) AS orders
     FROM payments p JOIN orders o ON p.order_id=o.order_id
     WHERE p.verification_status='VALID'
       AND o.order_date >= DATE_SUB(NOW(), INTERVAL {$trendMonths} MONTH)
     GROUP BY mkey ORDER BY mkey ASC"
);
$trendDB = [];
while ($row = $res->fetch_assoc()) $trendDB[$row['mkey']] = $row;

$trendFinal = [];
foreach ($monthSlots as $slot) {
    $trendFinal[] = [
        'label'   => $slot['label'],
        'revenue' => (float)($trendDB[$slot['key']]['revenue'] ?? 0),
        'orders'  => (int)($trendDB[$slot['key']]['orders']   ?? 0),
    ];
}
$maxTrend = max(array_column($trendFinal, 'revenue')) ?: 1;

$dailySlots = [];
for ($i = 6; $i >= 0; $i--) {
    $ts = strtotime("-$i day");
    $dailySlots[] = ['key' => date('Y-m-d', $ts), 'label' => date('D', $ts)];
}
$res = $conn->query(
    "SELECT DATE(o.order_date) AS dkey, COALESCE(SUM(p.amount),0) AS revenue, COUNT(DISTINCT p.order_id) AS orders
     FROM payments p JOIN orders o ON p.order_id=o.order_id
     WHERE p.verification_status='VALID' AND o.order_date>=DATE_SUB(NOW(),INTERVAL 7 DAY)
     GROUP BY dkey ORDER BY dkey ASC"
);
$dailyDB = [];
while ($row = $res->fetch_assoc()) $dailyDB[$row['dkey']] = $row;
$dailyFinal = [];
foreach ($dailySlots as $s) {
    $dailyFinal[] = ['label'=>$s['label'],'revenue'=>(float)($dailyDB[$s['key']]['revenue']??0),'orders'=>(int)($dailyDB[$s['key']]['orders']??0)];
}
$maxDaily = max(array_column($dailyFinal, 'revenue')) ?: 1;

$showDailyTrend = in_array($period, ['week','today']);
$activeTrend    = $showDailyTrend ? $dailyFinal : $trendFinal;
$activeMax      = $showDailyTrend ? $maxDaily   : $maxTrend;
$trendLabel     = $showDailyTrend ? 'Daily – Last 7 Days' : 'Monthly – Last 12 Months (all periods shown)';

// ==============================================================
//  CATEGORY, BRANCH, PRODUCTS, STATUS
// ==============================================================
$catColours = ['#A83535','#F4A261','#4CAF50','#2196F3','#9C27B0','#FF9800','#00BCD4','#E91E63'];

$res = $conn->query("SELECT COALESCE(p.category,'Uncategorised') AS category, SUM(oi.quantity) AS total_qty, SUM(oi.quantity*oi.unit_price) AS cat_revenue FROM order_items oi JOIN products p ON oi.product_id=p.product_id JOIN orders o ON oi.order_id=o.order_id WHERE 1=1 $rangeSQL GROUP BY category ORDER BY cat_revenue DESC LIMIT 8");
$categoryRows = $res->fetch_all(MYSQLI_ASSOC);
$catTotal = array_sum(array_column($categoryRows,'cat_revenue')) ?: 1;
$stops=[]; $cum=0;
foreach($categoryRows as $i=>$cat){ $pct=($cat['cat_revenue']/$catTotal)*100; $col=$catColours[$i%count($catColours)]; $stops[]="$col {$cum}% ".($cum+$pct)."%"; $cum+=$pct; }
$pieGradient = $stops ? implode(', ',$stops) : '#E0E0E0 0% 100%';

$res = $conn->query("SELECT p.product_id, p.product_name, p.category, SUM(oi.quantity) AS total_qty, SUM(oi.quantity*oi.unit_price) AS total_revenue, COUNT(DISTINCT oi.order_id) AS order_count FROM order_items oi JOIN products p ON oi.product_id=p.product_id JOIN orders o ON oi.order_id=o.order_id WHERE 1=1 $rangeSQL GROUP BY p.product_id ORDER BY total_revenue DESC LIMIT 10");
$topProducts = $res->fetch_all(MYSQLI_ASSOC);
$maxProdRev  = !empty($topProducts) ? (float)$topProducts[0]['total_revenue'] : 1;

$res = $conn->query("SELECT b.branch_name, COALESCE(SUM(p.amount),0) AS branch_revenue, COUNT(DISTINCT p.order_id) AS branch_orders FROM payments p JOIN orders o ON p.order_id=o.order_id JOIN users u ON o.user_id=u.user_id JOIN branches b ON u.branch_id=b.branch_id WHERE p.verification_status='VALID' $rangeSQL GROUP BY b.branch_id ORDER BY branch_revenue DESC");
$branchRows  = $res->fetch_all(MYSQLI_ASSOC);
if (empty($branchRows)) { $res2=$conn->query("SELECT branch_name,0 AS branch_revenue,0 AS branch_orders FROM branches WHERE status='ACTIVE' LIMIT 8"); $branchRows=$res2->fetch_all(MYSQLI_ASSOC); }
$branchTotal = array_sum(array_column($branchRows,'branch_revenue')) ?: 1;
$brMaxRev    = max(array_column($branchRows,'branch_revenue')) ?: 1;

$res = $conn->query("SELECT order_status, COUNT(*) AS cnt FROM orders o WHERE 1=1 $rangeSQL GROUP BY order_status ORDER BY cnt DESC");
$orderStatuses = $res->fetch_all(MYSQLI_ASSOC);
$totalStatusOrders = array_sum(array_column($orderStatuses,'cnt')) ?: 1;
$statusColours = ['NEW'=>'#3b82f6','PROCESSING'=>'#f59e0b','READY'=>'#10b981','COLLECTED'=>'#6b7280','CANCELLED'=>'#ef4444'];

$res=$conn->query("SELECT COUNT(*) AS cnt FROM inventory WHERE stock_quantity=0"); $outOfStock=(int)$res->fetch_assoc()['cnt'];
$res=$conn->query("SELECT COUNT(*) AS cnt FROM inventory WHERE stock_quantity>0 AND stock_quantity<=minimum_level"); $lowStock=(int)$res->fetch_assoc()['cnt'];
$res=$conn->query("SELECT COUNT(*) AS cnt FROM inventory WHERE stock_quantity>minimum_level"); $healthyStock=(int)$res->fetch_assoc()['cnt'];

$res=$conn->query("SELECT p.product_name, p.category, b.branch_name, i.stock_quantity, i.minimum_level, (i.minimum_level-i.stock_quantity) AS shortage FROM inventory i JOIN products p ON i.product_id=p.product_id JOIN branches b ON i.branch_id=b.branch_id WHERE i.stock_quantity<=i.minimum_level AND p.product_status='ACTIVE' ORDER BY i.stock_quantity ASC LIMIT 10");
$lowStockItems = $res->fetch_all(MYSQLI_ASSOC);

$res=$conn->query("SELECT COUNT(*) AS cnt FROM users WHERE user_role='CUSTOMER' AND account_status='ACTIVE'"); $totalCustomers=(int)$res->fetch_assoc()['cnt'];
$res=$conn->query("SELECT u.name, u.email, COUNT(DISTINCT o.order_id) AS order_count, COALESCE(SUM(p.amount),0) AS total_spent FROM payments p JOIN orders o ON p.order_id=o.order_id JOIN users u ON o.user_id=u.user_id WHERE p.verification_status='VALID' $rangeSQL GROUP BY u.user_id ORDER BY total_spent DESC LIMIT 8");
$topCustomers = $res->fetch_all(MYSQLI_ASSOC);

$res=$conn->query("SELECT payment_method, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM payments p JOIN orders o ON p.order_id=o.order_id WHERE p.verification_status='VALID' $rangeSQL GROUP BY payment_method ORDER BY total DESC");
$paymentMethods=$res->fetch_all(MYSQLI_ASSOC);
$payMethodTotal=array_sum(array_column($paymentMethods,'total')) ?: 1;

$res=$conn->query("SELECT verification_status, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM payments GROUP BY verification_status");
$payVerification=$res->fetch_all(MYSQLI_ASSOC);
$pvTotal=array_sum(array_column($payVerification,'cnt')) ?: 1;

$res=$conn->query("SELECT p.payment_id, p.record_date, p.amount, p.payment_method, p.verification_status, o.order_id, u.name AS customer_name FROM payments p JOIN orders o ON p.order_id=o.order_id JOIN users u ON o.user_id=u.user_id ORDER BY p.record_date DESC LIMIT 10");
$recentPayments=$res->fetch_all(MYSQLI_ASSOC);

$res=$conn->query("SELECT order_status, COUNT(*) AS cnt FROM orders WHERE order_type='PREORDER' GROUP BY order_status");
$preorderStatuses=$res->fetch_all(MYSQLI_ASSOC);
$totalPreorderRows=array_sum(array_column($preorderStatuses,'cnt')) ?: 1;

$res2=$conn->query("SELECT COUNT(*) AS cnt FROM products WHERE product_status='ACTIVE'");
$activeProducts=(int)$res2->fetch_assoc()['cnt'];

// ── Load existing sales forecasts from DB ─────────────────────
$existingForecasts = [];
$forecastMeta      = null;
$res = $conn->query(
    "SELECT forecast_month, predicted_revenue, model_type, r_squared,
            data_points, generated_at
     FROM sales_forecasts
     ORDER BY forecast_month ASC
     LIMIT 3"
);
if ($res && $res->num_rows > 0) {
    $existingForecasts = $res->fetch_all(MYSQLI_ASSOC);
    $forecastMeta = [
        'model_type'   => $existingForecasts[0]['model_type'],
        'r_squared'    => $existingForecasts[0]['r_squared'],
        'data_points'  => $existingForecasts[0]['data_points'],
        'generated_at' => $existingForecasts[0]['generated_at'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus – Analytics Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{--primary:#A83535;--secondary:#F4A261;--bg:#FAFAFA;--text:#2E2E2E;--muted:#707070;--border:#E0E0E0;--white:#FFFFFF;--sidebar:260px;--shadow:0 4px 12px rgba(0,0,0,0.05);}
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',system-ui,sans-serif;}
        body{background:var(--bg);color:var(--text);min-height:100vh;display:flex;}
        .sidebar{width:var(--sidebar);background:var(--white);border-right:1px solid var(--border);height:100vh;position:fixed;left:0;top:0;display:flex;flex-direction:column;box-shadow:2px 0 10px rgba(0,0,0,0.03);}
        .logo-area{padding:22px;border-bottom:1px solid var(--border);display:flex;align-items:center;}
        .logo-icon{background:var(--primary);width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;margin-right:12px;color:#fff;font-size:18px;}
        .logo-text{font-size:18px;font-weight:700;color:var(--primary);}
        .admin-subtitle{font-size:12px;color:var(--muted);margin-top:2px;}
        .nav-section{padding:18px 0;border-bottom:1px solid var(--border);}
        .nav-title{font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;padding:0 22px 10px;}
        .nav-menu{list-style:none;}
        .nav-item{margin-bottom:2px;}
        .nav-link{display:flex;align-items:center;padding:14px 22px;color:var(--text);text-decoration:none;transition:all .2s;border-left:4px solid transparent;}
        .nav-link:hover{background:rgba(168,53,53,.05);color:var(--primary);border-left-color:rgba(168,53,53,.3);}
        .nav-link.active{background:rgba(168,53,53,.08);color:var(--primary);border-left-color:var(--primary);font-weight:600;}
        .nav-icon{width:18px;text-align:center;margin-right:14px;font-size:16px;}
        .nav-text{font-size:14px;}
        .user-section{margin-top:auto;padding:20px;border-top:1px solid var(--border);}
        .user-info{display:flex;align-items:center;margin-bottom:15px;}
        .user-avatar{width:38px;height:38px;border-radius:50%;background:rgba(168,53,53,.1);display:flex;align-items:center;justify-content:center;color:var(--primary);font-weight:600;font-size:15px;margin-right:12px;}
        .user-name{font-weight:600;font-size:14px;}
        .logout-btn{width:100%;padding:9px;background:rgba(168,53,53,.1);color:var(--primary);border:1.5px solid var(--primary);border-radius:5px;font-weight:600;font-size:13px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;text-decoration:none;transition:all .2s;}
        .logout-btn:hover{background:rgba(168,53,53,.2);}
        .main-content{flex-grow:1;margin-left:var(--sidebar);min-height:100vh;display:flex;flex-direction:column;}
        .top-header{background:var(--white);padding:12px 22px;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:20;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;}
        .header-title{font-size:19px;font-weight:700;}
        .header-sub{font-size:12px;color:var(--muted);margin-top:2px;}
        .controls{display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
        .period-btn{padding:6px 11px;border:1.5px solid var(--border);border-radius:6px;font-size:12px;font-weight:600;color:var(--muted);background:var(--white);cursor:pointer;text-decoration:none;transition:all .2s;white-space:nowrap;}
        .period-btn:hover{border-color:var(--primary);color:var(--primary);}
        .period-btn.active{background:var(--primary);color:#fff;border-color:var(--primary);}
        .date-input{padding:6px 9px;border:1.5px solid var(--border);border-radius:6px;font-size:12px;color:var(--text);}
        .date-input:focus{outline:none;border-color:var(--primary);}
        .apply-btn{padding:6px 12px;background:var(--primary);color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;}
        .tab-bar{background:var(--white);border-bottom:1px solid var(--border);padding:0 22px;display:flex;gap:0;overflow-x:auto;}
        .tab-link{padding:12px 16px;font-size:13px;font-weight:600;color:var(--muted);text-decoration:none;border-bottom:3px solid transparent;white-space:nowrap;transition:all .2s;display:flex;align-items:center;gap:6px;}
        .tab-link:hover{color:var(--primary);}
        .tab-link.active{color:var(--primary);border-bottom-color:var(--primary);}
        .page-body{padding:20px 22px;flex-grow:1;display:flex;flex-direction:column;gap:18px;overflow-y:auto;}
        .stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;}
        .stat-card{background:var(--white);border-radius:10px;padding:18px;box-shadow:var(--shadow);border:1px solid var(--border);display:flex;flex-direction:column;transition:transform .2s;}
        .stat-card:hover{transform:translateY(-2px);}
        .stat-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;}
        .stat-label{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;}
        .stat-icon{width:38px;height:38px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:17px;}
        .ic-red   {background:rgba(168,53,53,.1);color:var(--primary);}
        .ic-orange{background:rgba(244,162,97,.15);color:#d97706;}
        .ic-green {background:rgba(16,185,129,.1);color:#10b981;}
        .ic-blue  {background:rgba(59,130,246,.1);color:#3b82f6;}
        .ic-purple{background:rgba(139,92,246,.1);color:#7c3aed;}
        .stat-value{font-size:24px;font-weight:700;color:var(--primary);margin-bottom:4px;}
        .stat-trend{font-size:11px;color:var(--muted);display:flex;align-items:center;gap:4px;margin-top:auto;}
        .t-up{color:#10b981;} .t-dn{color:#ef4444;}
        .two-col{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
        .three-col{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;}
        .card{background:var(--white);border-radius:10px;box-shadow:var(--shadow);border:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden;}
        .card-head{padding:14px 18px;border-bottom:1px solid var(--border);background:rgba(168,53,53,.025);display:flex;justify-content:space-between;align-items:center;}
        .card-title{font-size:13px;font-weight:700;color:var(--primary);display:flex;align-items:center;gap:7px;}
        .card-sub{font-size:11px;color:var(--muted);}
        .card-body{padding:16px 18px;flex-grow:1;}
        .bar-chart{display:flex;align-items:flex-end;justify-content:space-between;height:130px;gap:3px;padding:0 2px;}
        .bar-col{display:flex;flex-direction:column;align-items:center;gap:3px;flex:1;min-width:0;}
        .bar-wrap{display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100px;width:100%;}
        .bar{width:100%;max-width:28px;border-radius:3px 3px 0 0;background:var(--primary);transition:height .8s ease;cursor:pointer;position:relative;}
        .bar:hover{background:#8b2a2a;}
        .bar[data-tip]:hover::after{content:attr(data-tip);position:absolute;bottom:105%;left:50%;transform:translateX(-50%);background:rgba(0,0,0,.75);color:#fff;padding:4px 8px;border-radius:5px;font-size:10px;white-space:nowrap;margin-bottom:2px;pointer-events:none;z-index:99;}
        .bar-val{font-size:9px;font-weight:600;color:var(--muted);text-align:center;margin-bottom:2px;white-space:nowrap;overflow:hidden;width:100%;}
        .bar-lbl{font-size:9px;color:var(--muted);text-align:center;margin-top:3px;}
        .pie-wrap{display:flex;align-items:center;gap:18px;flex-wrap:wrap;}
        .pie{width:140px;height:140px;border-radius:50%;position:relative;flex-shrink:0;}
        .pie-hole{position:absolute;width:62px;height:62px;background:var(--white);border-radius:50%;top:50%;left:50%;transform:translate(-50%,-50%);display:flex;align-items:center;justify-content:center;flex-direction:column;}
        .pie-hole-val{font-size:13px;font-weight:700;color:var(--primary);}
        .pie-hole-lbl{font-size:9px;color:var(--muted);}
        .legend{display:flex;flex-direction:column;gap:6px;flex:1;}
        .legend-row{display:flex;align-items:center;gap:7px;}
        .legend-dot{width:9px;height:9px;border-radius:2px;flex-shrink:0;}
        .legend-name{font-size:11px;color:var(--text);flex:1;}
        .legend-pct{font-size:11px;font-weight:700;color:var(--primary);}
        .legend-amt{font-size:10px;color:var(--muted);}
        .h-bar-list{display:flex;flex-direction:column;gap:10px;}
        .h-bar-row{display:flex;align-items:center;gap:8px;}
        .h-bar-name{font-size:11px;color:var(--text);width:120px;min-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
        .h-bar-track{flex:1;height:18px;background:rgba(168,53,53,.08);border-radius:6px;overflow:hidden;}
        .h-bar-fill{height:100%;border-radius:6px;background:linear-gradient(90deg,var(--primary),rgba(168,53,53,.6));display:flex;align-items:center;padding-left:6px;font-size:9px;font-weight:700;color:#fff;transition:width 1s ease;}
        .h-bar-val{font-size:11px;font-weight:700;color:var(--primary);min-width:68px;text-align:right;}
        .status-grid{display:flex;flex-direction:column;gap:9px;}
        .status-row{display:flex;align-items:center;gap:8px;}
        .status-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0;}
        .status-name{font-size:12px;color:var(--text);flex:1;}
        .status-bar-track{flex:2;height:7px;background:var(--border);border-radius:3px;overflow:hidden;}
        .status-bar-fill{height:100%;border-radius:3px;}
        .status-count{font-size:11px;font-weight:700;min-width:24px;text-align:right;}
        .status-pct{font-size:10px;color:var(--muted);min-width:32px;text-align:right;}
        .data-table{width:100%;border-collapse:collapse;}
        .data-table thead{background:rgba(168,53,53,.03);border-bottom:2px solid var(--border);}
        .data-table th{padding:9px 13px;text-align:left;font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;white-space:nowrap;}
        .data-table tbody tr{border-bottom:1px solid var(--border);transition:background .15s;}
        .data-table tbody tr:last-child{border-bottom:none;}
        .data-table tbody tr:hover{background:rgba(168,53,53,.02);}
        .data-table td{padding:10px 13px;font-size:12px;color:var(--text);vertical-align:middle;}
        .mono{font-family:monospace;font-weight:700;color:var(--primary);font-size:11px;}
        .badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;}
        .b-green {background:rgba(16,185,129,.1);color:#059669;border:1px solid #a7f3d0;}
        .b-yellow{background:rgba(245,158,11,.1);color:#d97706;border:1px solid #fde68a;}
        .b-red   {background:rgba(239,68,68,.1);color:#dc2626;border:1px solid #fecaca;}
        .b-blue  {background:rgba(59,130,246,.1);color:#2563eb;border:1px solid #bfdbfe;}
        .b-gray  {background:rgba(107,114,128,.1);color:#4b5563;border:1px solid #d1d5db;}
        .inv-health{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;}
        .inv-hcard{padding:16px;border-radius:9px;text-align:center;}
        .inv-hcard .hval{font-size:28px;font-weight:700;margin-bottom:4px;}
        .inv-hcard .hlbl{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;}
        .inv-out{background:rgba(239,68,68,.08);border:1px solid #fecaca;} .inv-out .hval,.inv-out .hlbl{color:#dc2626;}
        .inv-low{background:rgba(245,158,11,.08);border:1px solid #fde68a;} .inv-low .hval,.inv-low .hlbl{color:#d97706;}
        .inv-ok {background:rgba(16,185,129,.08);border:1px solid #a7f3d0;} .inv-ok  .hval,.inv-ok  .hlbl{color:#059669;}
        .stock-mini{display:flex;align-items:center;gap:5px;}
        .stock-mini-bar{width:50px;height:5px;background:var(--border);border-radius:3px;overflow:hidden;}
        .stock-mini-fill{height:100%;border-radius:3px;}
        .method-grid{display:flex;flex-direction:column;gap:9px;}
        .method-row{display:flex;align-items:center;gap:8px;}
        .method-name{font-size:11px;font-weight:600;width:90px;}
        .method-bar-track{flex:1;height:16px;background:rgba(168,53,53,.07);border-radius:5px;overflow:hidden;}
        .method-bar-fill{height:100%;background:var(--primary);border-radius:5px;display:flex;align-items:center;padding-left:6px;font-size:9px;font-weight:700;color:#fff;}
        .method-total{font-size:11px;font-weight:700;color:var(--primary);min-width:68px;text-align:right;}
        .no-data{text-align:center;padding:30px 16px;color:var(--muted);}
        .no-data i{font-size:26px;opacity:.2;display:block;margin-bottom:8px;}
        .no-data p{font-size:12px;}

        /* ── Sales Forecast ── */
        .fc-card{background:linear-gradient(135deg,rgba(16,185,129,0.04),rgba(59,130,246,0.04));border:1.5px solid rgba(16,185,129,0.2);border-radius:12px;padding:22px 24px;margin-bottom:18px;}
        .fc-head{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px;gap:12px;flex-wrap:wrap;}
        .fc-title{font-size:15px;font-weight:700;color:#065f46;display:flex;align-items:center;gap:9px;}
        .fc-meta{font-size:11px;color:var(--muted);margin-top:4px;}
        .fc-run-btn{padding:8px 18px;background:#059669;color:white;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:7px;transition:background 0.2s;white-space:nowrap;flex-shrink:0;}
        .fc-run-btn:hover:not(:disabled){background:#047857;}
        .fc-run-btn:disabled{background:#d1d5db;cursor:not-allowed;}
        .fc-body{display:flex;flex-direction:column;gap:16px;}
        .fc-months{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;}
        .fc-month-card{background:var(--white);border-radius:10px;padding:16px 18px;border:1px solid var(--border);text-align:center;}
        .fc-month-label{font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:0.4px;margin-bottom:8px;}
        .fc-month-val{font-size:22px;font-weight:700;color:#059669;margin-bottom:6px;}
        .fc-month-bar-wrap{height:4px;background:rgba(5,150,105,0.15);border-radius:2px;overflow:hidden;}
        .fc-month-bar{height:100%;background:linear-gradient(90deg,#059669,#10b981);border-radius:2px;}
        .fc-winner-badge{display:inline-flex;align-items:center;gap:5px;padding:2px 9px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:20px;font-size:11px;font-weight:700;color:#065f46;}
        .fc-compare-wrap{background:var(--white);border-radius:10px;border:1px solid var(--border);overflow:hidden;}
        .fc-compare-head{padding:12px 16px;background:rgba(5,150,105,0.04);border-bottom:1px solid var(--border);font-size:13px;font-weight:700;color:#065f46;display:flex;align-items:center;gap:8px;}
        .fc-table{width:100%;border-collapse:collapse;font-size:13px;}
        .fc-table th{padding:10px 16px;text-align:left;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:0.4px;border-bottom:1px solid var(--border);background:rgba(5,150,105,0.02);}
        .fc-table td{padding:11px 16px;border-bottom:1px solid var(--border);color:var(--text);}
        .fc-table tr:last-child td{border-bottom:none;}
        .fc-table tr.winner-row td{background:rgba(5,150,105,0.04);font-weight:600;}
        .metric-good{color:#059669;font-weight:700;}
        .metric-bad{color:var(--muted);}
        .fc-avp-wrap{background:var(--white);border-radius:10px;border:1px solid var(--border);overflow:hidden;}
        .fc-avp-head{padding:12px 16px;background:rgba(59,130,246,0.03);border-bottom:1px solid var(--border);font-size:13px;font-weight:700;color:#1d4ed8;display:flex;align-items:center;gap:8px;}
        .fc-model-row{display:flex;gap:14px;flex-wrap:wrap;padding:12px 16px;background:rgba(5,150,105,0.05);border-radius:8px;border:1px solid rgba(5,150,105,0.15);}
        .fc-model-stat{font-size:12px;color:var(--text);display:flex;align-items:center;gap:5px;}
        .fc-model-stat strong{color:#065f46;}
        .fc-empty{padding:20px;text-align:center;color:var(--muted);font-size:13px;}
        .fc-loading{text-align:center;padding:28px;color:var(--muted);display:flex;flex-direction:column;align-items:center;gap:10px;}
        .fc-spinner{width:26px;height:26px;border:3px solid var(--border);border-top-color:#059669;border-radius:50%;animation:fcspin .7s linear infinite;}
        @keyframes fcspin{to{transform:rotate(360deg);}}
        .fc-err{padding:12px 16px;background:#fff0f0;border:1px solid #ef9a9a;border-radius:9px;font-size:13px;color:#c62828;margin-top:12px;}
        .fc-section-title{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--muted);margin:16px 0 8px;display:flex;align-items:center;gap:7px;}

        /* ── AI Insights ── */
        .ai-card{background:linear-gradient(135deg,rgba(168,53,53,0.04),rgba(244,162,97,0.04));border:1.5px solid rgba(168,53,53,0.15);border-radius:12px;padding:22px 24px;}
        .ai-card-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;}
        .ai-card-title{font-size:15px;font-weight:700;color:var(--primary);display:flex;align-items:center;gap:9px;}
        .ai-gen-btn{padding:8px 18px;background:var(--primary);color:white;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:7px;transition:background 0.2s;white-space:nowrap;}
        .ai-gen-btn:hover:not(:disabled){background:#8b2a2a;}
        .ai-gen-btn:disabled{background:#d1d5db;cursor:not-allowed;}
        .ai-body{display:none;margin-top:16px;}
        .ai-body.show{display:block;}
        .ai-loading{text-align:center;padding:24px;color:var(--muted);display:flex;flex-direction:column;align-items:center;gap:10px;}
        .ai-spinner{width:26px;height:26px;border:3px solid var(--border);border-top-color:var(--primary);border-radius:50%;animation:aispin .7s linear infinite;}
        @keyframes aispin{to{transform:rotate(360deg);}}
        .ai-summary{font-size:14px;line-height:1.7;padding:14px 18px;background:rgba(168,53,53,0.05);border-radius:9px;border-left:4px solid var(--primary);margin-bottom:16px;font-weight:500;color:var(--text);}
        .ai-cols{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
        .ai-sec{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;margin-bottom:8px;}
        .ai-sec.g{color:#2e7d32;} .ai-sec.r{color:#c62828;} .ai-sec.b{color:#1d4ed8;}
        .ai-item{display:flex;align-items:flex-start;gap:7px;font-size:13px;line-height:1.6;margin-bottom:5px;color:var(--text);}
        .ai-rec{padding:12px 16px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:9px;font-size:13px;color:#1d4ed8;line-height:1.6;}
        .ai-ts{font-size:11px;color:var(--muted);margin-top:10px;text-align:right;}
        .ai-err{padding:12px 16px;background:#fff0f0;border:1px solid #ef9a9a;border-radius:9px;font-size:13px;color:#c62828;}

        @media(max-width:1400px){.stat-grid{grid-template-columns:repeat(2,1fr);}}
        @media(max-width:1100px){.two-col,.three-col{grid-template-columns:1fr;}}
        @media(max-width:1024px){
            :root{--sidebar:70px;}
            .logo-text,.admin-subtitle,.nav-text,.user-details,.nav-title{display:none;}
            .logo-area,.nav-section,.user-section{padding:16px 12px;}
            .logo-area{justify-content:center;}
            .nav-link{justify-content:center;padding:14px;border-left:none;border-right:4px solid transparent;}
            .nav-link:hover,.nav-link.active{border-left:none;border-right-color:var(--primary);}
            .nav-icon{margin-right:0;}
            .logout-btn span{display:none;}
            .logout-btn{justify-content:center;padding:9px;}
            .ai-cols{grid-template-columns:1fr;}
        }
        @media(max-width:768px){.stat-grid{grid-template-columns:1fr 1fr;}}
    </style>
</head>
<body>

<?php include 'a_sidebar.php'; ?>

<main class="main-content">

<!-- Top header -->
<header class="top-header">
    <div>
        <div class="header-title"><i class="fas fa-chart-line" style="color:var(--primary);margin-right:8px;"></i>Analytics Dashboard</div>
        <div class="header-sub"><?= htmlspecialchars($periodLabel) ?> &nbsp;&bull;&nbsp; <?= date('d M Y, H:i') ?></div>
    </div>
    <form method="GET" action="a_report.php" class="controls">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
        <?php foreach(['today'=>'Today','week'=>'7 Days','month'=>'This Month','lastmonth'=>'Last Month','quarter'=>'Quarter','year'=>'This Year','alltime'=>'All Time'] as $k=>$v): ?>
        <a href="?tab=<?= $tab ?>&period=<?= $k ?>" class="period-btn <?= $period===$k?'active':'' ?>"><?= $v ?></a>
        <?php endforeach; ?>
        <input type="date" name="date_from" class="date-input" value="<?= htmlspecialchars($dateFrom) ?>">
        <input type="date" name="date_to"   class="date-input" value="<?= htmlspecialchars($dateTo) ?>">
        <input type="hidden" name="period" value="custom">
        <button type="submit" class="apply-btn"><i class="fas fa-filter"></i> Custom</button>
    </form>
</header>

<!-- Tabs -->
<nav class="tab-bar">
    <?php foreach(['overview'=>['fas fa-tachometer-alt','Overview'],'orders'=>['fas fa-clipboard-list','Orders'],'products'=>['fas fa-boxes','Products'],'inventory'=>['fas fa-warehouse','Inventory'],'customers'=>['fas fa-users','Customers'],'payments'=>['fas fa-receipt','Payments']] as $k=>[$icon,$label]): ?>
    <a href="?tab=<?= $k ?>&period=<?= $period ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>" class="tab-link <?= $tab===$k?'active':'' ?>">
        <i class="<?= $icon ?>"></i> <?= $label ?>
    </a>
    <?php endforeach; ?>
</nav>

<div class="page-body">

<?php if ($tab === 'overview'): ?>
<!-- ==================== OVERVIEW ==================== -->

<div class="stat-grid">
    <?php
    $cards=[
        ['Total Revenue','RM '.number_format($totalRevenue,2),$revGrowth,'fas fa-money-bill-wave','ic-red'],
        ['Total Orders',number_format($totalOrders),$ordGrowth,'fas fa-shopping-cart','ic-orange'],
        ['Avg Order Value','RM '.number_format($avgOrder,2),$avgGrowth,'fas fa-chart-pie','ic-green'],
        ['Active Customers',number_format($activeCustomers),['pct'=>'in period','dir'=>'flat'],'fas fa-users','ic-blue'],
    ];
    foreach($cards as [$lbl,$val,$g,$icon,$ic]):
    ?>
    <div class="stat-card">
        <div class="stat-header"><div class="stat-label"><?= $lbl ?></div><div class="stat-icon <?= $ic ?>"><i class="<?= $icon ?>"></i></div></div>
        <div class="stat-value"><?= $val ?></div>
        <div class="stat-trend">
            <?php if($g['dir']==='up'):?><i class="fas fa-arrow-up t-up"></i><span class="t-up"><?= $g['pct'] ?></span>
            <?php elseif($g['dir']==='down'):?><i class="fas fa-arrow-down t-dn"></i><span class="t-dn"><?= $g['pct'] ?></span>
            <?php else:?><i class="fas fa-minus"></i>
            <?php endif;?>
            <span>vs previous period</span>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-header"><div class="stat-label">Pending Payments</div><div class="stat-icon ic-orange"><i class="fas fa-hourglass-half"></i></div></div><div class="stat-value" style="font-size:20px;"><?= $pendingCount ?></div><div class="stat-trend"><i class="fas fa-exclamation-circle" style="color:#d97706;"></i> RM <?= number_format($pendingAmt,2) ?> unverified</div></div>
    <div class="stat-card"><div class="stat-header"><div class="stat-label">Cancelled Orders</div><div class="stat-icon ic-red"><i class="fas fa-times-circle"></i></div></div><div class="stat-value" style="font-size:20px;"><?= $cancelledOrders ?></div><div class="stat-trend"><i class="fas fa-info-circle"></i> This period</div></div>
    <div class="stat-card"><div class="stat-header"><div class="stat-label">Pre-orders</div><div class="stat-icon ic-purple"><i class="fas fa-clipboard-check"></i></div></div><div class="stat-value" style="font-size:20px;"><?= $totalPreorders ?></div><div class="stat-trend"><i class="fas fa-info-circle"></i> All time</div></div>
    <div class="stat-card"><div class="stat-header"><div class="stat-label">Low Stock Alerts</div><div class="stat-icon" style="background:rgba(239,68,68,.1);color:#dc2626;"><i class="fas fa-exclamation-triangle"></i></div></div><div class="stat-value" style="font-size:20px;"><?= $lowStock+$outOfStock ?></div><div class="stat-trend"><i class="fas fa-times-circle" style="color:#dc2626;"></i> <?= $outOfStock ?> out of stock</div></div>
</div>

<!-- ── Sales Forecast ── -->
<div class="fc-card">
    <div class="fc-head">
        <div>
            <div class="fc-title">
                <i class="fas fa-chart-line"></i> 3-Month Revenue Forecast
                <span style="font-size:11px;font-weight:400;color:var(--muted);margin-left:4px;">Linear, Polynomial &amp; Seasonal Regression</span>
            </div>
            <?php if ($forecastMeta): ?>
            <div class="fc-meta">
                Last run: <?= date('d M Y, H:i', strtotime($forecastMeta['generated_at'])) ?>
                &nbsp;·&nbsp; Model: <?= htmlspecialchars($forecastMeta['model_type']) ?>
                &nbsp;·&nbsp; Trained on <?= $forecastMeta['data_points'] ?> months of data
            </div>
            <?php endif; ?>
        </div>
        <button class="fc-run-btn" id="fcBtn" onclick="runForecast()">
            <i class="fas fa-play"></i>
            <?= $existingForecasts ? 'Refresh Forecast' : 'Run Forecast' ?>
        </button>
    </div>

    <div id="fcLoading" class="fc-loading" style="display:none;">
        <div class="fc-spinner"></div>
        <div>Training model on historical sales data…</div>
    </div>

    <div id="fcContent">
        <?php if (empty($existingForecasts)): ?>
        <div class="fc-empty">
            <i class="fas fa-chart-line" style="font-size:28px;opacity:0.15;display:block;margin-bottom:8px;"></i>
            No forecast generated yet. Click <strong>Run Forecast</strong> to train the model and predict the next 3 months.
        </div>
        <?php else: ?>
        <?php
            $fcMax = max(array_column($existingForecasts, 'predicted_revenue')) ?: 1;
        ?>
        <div class="fc-months">
            <?php foreach ($existingForecasts as $fc): ?>
            <div class="fc-month-card">
                <div class="fc-month-label"><?= date('M Y', strtotime($fc['forecast_month'])) ?></div>
                <div class="fc-month-val">RM <?= number_format($fc['predicted_revenue'], 0) ?></div>
                <div class="fc-month-bar-wrap">
                    <div class="fc-month-bar" style="width:<?= round(($fc['predicted_revenue'] / $fcMax) * 100) ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="fc-model-row">
            <div class="fc-model-stat">
                <i class="fas fa-bullseye" style="color:#059669;"></i>
                <?php
                // R² can legitimately go negative on a short/volatile test
                // window — that's expected with limited data, not a bug.
                // Showing a raw negative percentage reads as broken, so
                // it gets a clear label instead.
                $r2Val = $forecastMeta['r_squared'];
                ?>
                Model Accuracy (R²):
                <strong>
                    <?php if ($r2Val < 0): ?>
                        <span style="color:#dc2626;">Below average</span>
                    <?php else: ?>
                        <?= number_format($r2Val * 100, 1) ?>%
                    <?php endif; ?>
                </strong>
            </div>
            <div class="fc-model-stat" style="flex:1;">
                <div class="r2-bar-wrap">
                    <div class="r2-bar" style="width:<?= max(0, round($r2Val * 100)) ?>%"></div>
                </div>
            </div>
            <div class="fc-model-stat">
                <i class="fas fa-database" style="color:#059669;"></i>
                Training data: <strong><?= $forecastMeta['data_points'] ?> months</strong>
            </div>
            <div class="fc-model-stat">
                <i class="fas fa-info-circle" style="color:var(--muted);"></i>
                <span style="color:var(--muted);font-size:11px;">Higher R² = better fit. Forecast accuracy improves with more data.</span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="fc-err" id="fcErr" style="display:none;"></div>
</div>

<!-- ── AI Business Insights ── -->
<div class="ai-card">
    <div class="ai-card-head">
        <div class="ai-card-title">
            <i class="fas fa-robot"></i> AI Business Insights
            <span style="font-size:11px;font-weight:400;color:var(--muted);margin-left:4px;">Powered by Gemini</span>
        </div>
        <button class="ai-gen-btn" id="aiBtn" onclick="generateInsights()">
            <i class="fas fa-magic"></i> Generate Insights
        </button>
    </div>
    <div class="ai-body" id="aiBody">
        <div class="ai-loading" id="aiLoading" style="display:none;">
            <div class="ai-spinner"></div>
            <div>Analysing <?= htmlspecialchars($periodLabel) ?> data…</div>
        </div>
        <div id="aiResults" style="display:none;">
            <div class="ai-summary" id="aiSummary"></div>
            <div class="ai-cols">
                <div>
                    <div class="ai-sec g"><i class="fas fa-check-circle"></i> Highlights</div>
                    <div id="aiHL"></div>
                </div>
                <div>
                    <div class="ai-sec r"><i class="fas fa-exclamation-triangle"></i> Concerns</div>
                    <div id="aiCon"></div>
                    <div class="ai-sec b" style="margin-top:14px;"><i class="fas fa-lightbulb"></i> Recommendation</div>
                    <div class="ai-rec" id="aiRec"></div>
                </div>
            </div>
            <div class="ai-ts" id="aiTs"></div>
        </div>
        <div class="ai-err" id="aiErr" style="display:none;"></div>
    </div>
</div>

<!-- Trend + Category -->
<div class="two-col">
    <div class="card">
        <div class="card-head"><div class="card-title"><i class="fas fa-chart-bar"></i> Revenue Trend</div><span class="card-sub"><?= $trendLabel ?></span></div>
        <div class="card-body">
            <?php if(max(array_column($activeTrend,'revenue'))===0.0): ?>
                <div class="no-data"><i class="fas fa-chart-bar"></i><p>No revenue data yet.</p></div>
            <?php else: ?>
            <div class="bar-chart">
                <?php foreach($activeTrend as $t):
                    $h=$activeMax>0?max(3,($t['revenue']/$activeMax)*100):3;
                    $tip=$t['label'].': RM '.number_format($t['revenue'],2).' ('.$t['orders'].' orders)';
                ?>
                <div class="bar-col">
                    <div class="bar-wrap">
                        <div class="bar-val"><?= $t['revenue']>0?($t['revenue']>=1000?'RM'.number_format($t['revenue']/1000,1).'k':'RM'.number_format($t['revenue'],0)):'' ?></div>
                        <div class="bar" style="height:<?= $h ?>%;" data-tip="<?= htmlspecialchars($tip) ?>"></div>
                    </div>
                    <div class="bar-lbl"><?= htmlspecialchars($t['label']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><div class="card-title"><i class="fas fa-chart-pie"></i> Sales by Category</div><span class="card-sub"><?= htmlspecialchars($periodLabel) ?></span></div>
        <div class="card-body">
            <?php if(empty($categoryRows)): ?>
                <div class="no-data"><i class="fas fa-chart-pie"></i><p>No category data.</p></div>
            <?php else: ?>
            <div class="pie-wrap">
                <div class="pie" style="background:conic-gradient(<?= $pieGradient ?>);"><div class="pie-hole"><div class="pie-hole-val">RM<?= number_format($catTotal/1000,1) ?>k</div><div class="pie-hole-lbl">total</div></div></div>
                <div class="legend">
                    <?php foreach($categoryRows as $i=>$cat): $pct=round(($cat['cat_revenue']/$catTotal)*100,1); $col=$catColours[$i%count($catColours)]; ?>
                    <div class="legend-row"><div class="legend-dot" style="background:<?= $col ?>;"></div><span class="legend-name"><?= htmlspecialchars($cat['category']) ?></span><span class="legend-pct"><?= $pct ?>%</span><span class="legend-amt">RM<?= number_format($cat['cat_revenue'],0) ?></span></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Order status + Branch -->
<div class="two-col">
    <div class="card">
        <div class="card-head"><div class="card-title"><i class="fas fa-tasks"></i> Order Status</div><span class="card-sub"><?= htmlspecialchars($periodLabel) ?></span></div>
        <div class="card-body">
            <?php if(empty($orderStatuses)): ?>
                <div class="no-data"><i class="fas fa-tasks"></i><p>No orders this period.</p></div>
            <?php else: ?>
            <div class="status-grid">
                <?php foreach($orderStatuses as $s): $col=$statusColours[$s['order_status']]??'#607D8B'; $pct=round(($s['cnt']/$totalStatusOrders)*100,1); ?>
                <div class="status-row">
                    <div class="status-dot" style="background:<?= $col ?>;"></div>
                    <span class="status-name"><?= ucfirst(strtolower($s['order_status'])) ?></span>
                    <div class="status-bar-track"><div class="status-bar-fill" style="width:<?= $pct ?>%;background:<?= $col ?>;"></div></div>
                    <span class="status-count"><?= $s['cnt'] ?></span><span class="status-pct"><?= $pct ?>%</span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><div class="card-title"><i class="fas fa-store"></i> Revenue by Branch</div><span class="card-sub"><?= htmlspecialchars($periodLabel) ?></span></div>
        <div class="card-body">
            <?php if(empty($branchRows)): ?>
                <div class="no-data"><i class="fas fa-store"></i><p>No branch data.</p></div>
            <?php else: ?>
            <div class="h-bar-list">
                <?php foreach($branchRows as $br): $bPct=$brMaxRev>0?($br['branch_revenue']/$brMaxRev)*100:0; ?>
                <div class="h-bar-row">
                    <span class="h-bar-name" title="<?= htmlspecialchars($br['branch_name']) ?>"><?= htmlspecialchars($br['branch_name']) ?></span>
                    <div class="h-bar-track"><div class="h-bar-fill" style="width:<?= max(3,$bPct) ?>%;"><?php if($bPct>20):?>RM<?= number_format($br['branch_revenue']/1000,1) ?>k<?php endif;?></div></div>
                    <span class="h-bar-val">RM <?= number_format($br['branch_revenue'],0) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Top 5 products quick table -->
<div class="card">
    <div class="card-head"><div class="card-title"><i class="fas fa-star"></i> Top 5 Products</div><a href="?tab=products&period=<?= $period ?>" style="font-size:11px;color:var(--primary);text-decoration:none;font-weight:600;">View all &rarr;</a></div>
    <div style="overflow-x:auto;">
        <?php if(empty($topProducts)): ?>
            <div class="no-data"><i class="fas fa-boxes"></i><p>No product sales this period.</p></div>
        <?php else: ?>
        <table class="data-table">
            <thead><tr><th>#</th><th>Product</th><th>Category</th><th>Qty Sold</th><th>Revenue</th><th>Orders</th></tr></thead>
            <tbody>
            <?php foreach(array_slice($topProducts,0,5) as $i=>$p): ?>
            <tr><td style="font-weight:700;color:var(--muted);"><?= $i+1 ?></td><td style="font-weight:600;"><?= htmlspecialchars($p['product_name']) ?></td><td><span class="badge b-blue"><?= htmlspecialchars($p['category']?:'—') ?></span></td><td><?= number_format($p['total_qty']) ?></td><td style="font-weight:700;color:var(--primary);">RM <?= number_format($p['total_revenue'],2) ?></td><td><?= $p['order_count'] ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php elseif($tab==='orders'): ?>
<!-- ==================== ORDERS ==================== -->
<?php
$newOrd=0;$procOrd=0;$readyOrd=0;$collOrd=0;$cancOrd=0;
foreach($orderStatuses as $s){match($s['order_status']){'NEW'=>$newOrd=$s['cnt'],'PROCESSING'=>$procOrd=$s['cnt'],'READY'=>$readyOrd=$s['cnt'],'COLLECTED'=>$collOrd=$s['cnt'],'CANCELLED'=>$cancOrd=$s['cnt'],default=>null};}
?>
<div class="stat-grid">
    <div class="stat-card"><div class="stat-header"><div class="stat-label">Total Orders</div><div class="stat-icon ic-red"><i class="fas fa-clipboard-list"></i></div></div><div class="stat-value"><?= $totalOrders ?></div></div>
    <div class="stat-card"><div class="stat-header"><div class="stat-label">New</div><div class="stat-icon ic-blue"><i class="fas fa-plus-circle"></i></div></div><div class="stat-value"><?= $newOrd ?></div></div>
    <div class="stat-card"><div class="stat-header"><div class="stat-label">Processing</div><div class="stat-icon ic-orange"><i class="fas fa-cog"></i></div></div><div class="stat-value"><?= $procOrd ?></div></div>
    <div class="stat-card"><div class="stat-header"><div class="stat-label">Collected</div><div class="stat-icon ic-green"><i class="fas fa-check-circle"></i></div></div><div class="stat-value"><?= $collOrd ?></div></div>
</div>

<div class="two-col">
    <div class="card">
        <div class="card-head"><div class="card-title"><i class="fas fa-tasks"></i> Status Breakdown</div></div>
        <div class="card-body">
            <?php if(empty($orderStatuses)): ?><div class="no-data"><i class="fas fa-tasks"></i><p>No orders.</p></div><?php else: ?>
            <div class="status-grid">
                <?php foreach($orderStatuses as $s): $col=$statusColours[$s['order_status']]??'#607D8B'; $pct=round(($s['cnt']/$totalStatusOrders)*100,1); ?>
                <div class="status-row"><div class="status-dot" style="background:<?= $col ?>;"></div><span class="status-name"><?= ucfirst(strtolower($s['order_status'])) ?></span><div class="status-bar-track"><div class="status-bar-fill" style="width:<?= $pct ?>%;background:<?= $col ?>;"></div></div><span class="status-count"><?= $s['cnt'] ?></span><span class="status-pct"><?= $pct ?>%</span></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><div class="card-title"><i class="fas fa-chart-bar"></i> Order Volume Trend</div><span class="card-sub"><?= $trendLabel ?></span></div>
        <div class="card-body">
            <?php $maxOrdTrend=max(array_column($activeTrend,'orders'))?:1; ?>
            <div class="bar-chart">
                <?php foreach($activeTrend as $t): $h=$maxOrdTrend>0?max(3,($t['orders']/$maxOrdTrend)*100):3; ?>
                <div class="bar-col"><div class="bar-wrap"><div class="bar-val"><?= $t['orders']>0?$t['orders']:'' ?></div><div class="bar" style="height:<?= $h ?>%;background:#3b82f6;" data-tip="<?= htmlspecialchars($t['label'].': '.$t['orders'].' orders') ?>"></div></div><div class="bar-lbl"><?= htmlspecialchars($t['label']) ?></div></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Pre-orders -->
<div class="card">
    <div class="card-head"><div class="card-title"><i class="fas fa-clipboard-check"></i> Pre-order Status (All Time)</div></div>
    <div class="card-body">
        <?php if(empty($preorderStatuses)): ?><div class="no-data"><i class="fas fa-clipboard-check"></i><p>No pre-orders.</p></div><?php else: ?>
        <div class="status-grid">
            <?php $poCols=['SUBMITTED'=>'#A83535','PROCESSING'=>'#f59e0b','READY'=>'#10b981','CANCELLED'=>'#ef4444']; foreach($preorderStatuses as $ps): $col=$poCols[$ps['order_status']]??'#607D8B'; $pct=round(($ps['cnt']/$totalPreorderRows)*100,1); ?>
            <div class="status-row"><div class="status-dot" style="background:<?= $col ?>;"></div><span class="status-name"><?= ucfirst(strtolower($ps['order_status'])) ?></span><div class="status-bar-track"><div class="status-bar-fill" style="width:<?= $pct ?>%;background:<?= $col ?>;"></div></div><span class="status-count"><?= $ps['cnt'] ?></span><span class="status-pct"><?= $pct ?>%</span></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif($tab==='products'): ?>
<!-- ==================== PRODUCTS ==================== -->
<?php $totalProductRevenue=array_sum(array_column($topProducts,'total_revenue')); $totalQtySold=array_sum(array_column($topProducts,'total_qty')); ?>
<div class="stat-grid">
    <div class="stat-card"><div class="stat-header"><div class="stat-label">Active Products</div><div class="stat-icon ic-blue"><i class="fas fa-boxes"></i></div></div><div class="stat-value"><?= $activeProducts ?></div></div>
    <div class="stat-card"><div class="stat-header"><div class="stat-label">Product Revenue</div><div class="stat-icon ic-red"><i class="fas fa-dollar-sign"></i></div></div><div class="stat-value" style="font-size:18px;">RM <?= number_format($totalProductRevenue,2) ?></div></div>
    <div class="stat-card"><div class="stat-header"><div class="stat-label">Units Sold</div><div class="stat-icon ic-green"><i class="fas fa-box-open"></i></div></div><div class="stat-value"><?= number_format($totalQtySold) ?></div></div>
    <div class="stat-card"><div class="stat-header"><div class="stat-label">Categories</div><div class="stat-icon ic-orange"><i class="fas fa-tags"></i></div></div><div class="stat-value"><?= count($categoryRows) ?></div></div>
</div>

<div class="two-col">
    <div class="card">
        <div class="card-head"><div class="card-title"><i class="fas fa-chart-pie"></i> Revenue by Category</div></div>
        <div class="card-body">
            <?php if(empty($categoryRows)): ?><div class="no-data"><i class="fas fa-chart-pie"></i><p>No data.</p></div><?php else: ?>
            <div class="pie-wrap">
                <div class="pie" style="background:conic-gradient(<?= $pieGradient ?>);"><div class="pie-hole"><div class="pie-hole-val"><?= count($categoryRows) ?></div><div class="pie-hole-lbl">cats</div></div></div>
                <div class="legend"><?php foreach($categoryRows as $i=>$cat): $pct=round(($cat['cat_revenue']/$catTotal)*100,1); $col=$catColours[$i%count($catColours)]; ?>
                    <div class="legend-row"><div class="legend-dot" style="background:<?= $col ?>;"></div><span class="legend-name"><?= htmlspecialchars($cat['category']) ?></span><span class="legend-pct"><?= $pct ?>%</span><span class="legend-amt"><?= number_format($cat['total_qty']) ?> units</span></div>
                <?php endforeach; ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><div class="card-title"><i class="fas fa-chart-bar"></i> Top Products by Revenue</div></div>
        <div class="card-body">
            <?php if(empty($topProducts)): ?><div class="no-data"><i class="fas fa-boxes"></i><p>No sales.</p></div><?php else: ?>
            <div class="h-bar-list"><?php foreach(array_slice($topProducts,0,7) as $p): $bPct=$maxProdRev>0?($p['total_revenue']/$maxProdRev)*100:0; ?>
                <div class="h-bar-row"><span class="h-bar-name" title="<?= htmlspecialchars($p['product_name']) ?>"><?= htmlspecialchars($p['product_name']) ?></span><div class="h-bar-track"><div class="h-bar-fill" style="width:<?= max(3,$bPct) ?>%;"><?php if($bPct>20):?>RM<?= number_format($p['total_revenue'],0) ?><?php endif;?></div></div><span class="h-bar-val">RM <?= number_format($p['total_revenue'],0) ?></span></div>
            <?php endforeach; ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-head"><div class="card-title"><i class="fas fa-list"></i> Full Product Sales Table</div><span class="card-sub"><?= htmlspecialchars($periodLabel) ?></span></div>
    <div style="overflow-x:auto;">
        <?php if(empty($topProducts)): ?><div class="no-data"><i class="fas fa-boxes"></i><p>No product sales.</p></div><?php else: ?>
        <table class="data-table">
            <thead><tr><th>#</th><th>Product</th><th>Category</th><th>Qty Sold</th><th>Revenue</th><th>Avg Price</th><th>Orders</th></tr></thead>
            <tbody><?php foreach($topProducts as $i=>$p): ?>
            <tr><td style="font-weight:700;color:var(--muted);"><?= $i+1 ?></td><td style="font-weight:600;"><?= htmlspecialchars($p['product_name']) ?></td><td><span class="badge b-blue"><?= htmlspecialchars($p['category']?:'—') ?></span></td><td><?= number_format($p['total_qty']) ?></td><td style="font-weight:700;color:var(--primary);">RM <?= number_format($p['total_revenue'],2) ?></td><td>RM <?= $p['total_qty']>0?number_format($p['total_revenue']/$p['total_qty'],2):'—' ?></td><td><?= $p['order_count'] ?></td></tr>
            <?php endforeach; ?></tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php elseif($tab==='inventory'): ?>
<!-- ==================== INVENTORY ==================== -->
<div class="inv-health">
    <div class="inv-hcard inv-out"><div class="hval"><?= $outOfStock ?></div><div class="hlbl">Out of Stock</div></div>
    <div class="inv-hcard inv-low"><div class="hval"><?= $lowStock ?></div><div class="hlbl">Low Stock</div></div>
    <div class="inv-hcard inv-ok"><div class="hval"><?= $healthyStock ?></div><div class="hlbl">Healthy</div></div>
</div>

<div class="card">
    <div class="card-head"><div class="card-title"><i class="fas fa-exclamation-triangle"></i> Low &amp; Out-of-Stock Items</div><span class="card-sub">Across all branches</span></div>
    <div style="overflow-x:auto;">
        <?php if(empty($lowStockItems)): ?>
            <div class="no-data" style="padding:26px;"><i class="fas fa-check-circle" style="color:#10b981;"></i><p>All stock levels healthy!</p></div>
        <?php else: ?>
        <table class="data-table">
            <thead><tr><th>Product</th><th>Category</th><th>Branch</th><th>Stock</th><th>Min Level</th><th>Shortage</th><th>Health</th></tr></thead>
            <tbody><?php foreach($lowStockItems as $item): $pct2=$item['minimum_level']>0?min(100,round(($item['stock_quantity']/$item['minimum_level'])*100)):0; $barCol=$item['stock_quantity']==0?'#ef4444':'#f59e0b'; $badge=$item['stock_quantity']==0?'b-red':'b-yellow'; $label=$item['stock_quantity']==0?'Out of Stock':'Low'; ?>
            <tr><td style="font-weight:600;"><?= htmlspecialchars($item['product_name']) ?></td><td><span class="badge b-blue"><?= htmlspecialchars($item['category']?:'—') ?></span></td><td><?= htmlspecialchars($item['branch_name']) ?></td><td style="font-weight:700;color:<?= $barCol ?>;"><?= $item['stock_quantity'] ?></td><td><?= $item['minimum_level'] ?></td><td style="color:#ef4444;font-weight:700;">-<?= $item['shortage'] ?></td>
            <td><div class="stock-mini"><div class="stock-mini-bar"><div class="stock-mini-fill" style="width:<?= $pct2 ?>%;background:<?= $barCol ?>;"></div></div><span class="badge <?= $badge ?>"><?= $label ?></span></div></td></tr>
            <?php endforeach; ?></tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php elseif($tab==='customers'): ?>
<!-- ==================== CUSTOMERS ==================== -->
<div class="stat-grid">
    <div class="stat-card"><div class="stat-header"><div class="stat-label">Total Customers</div><div class="stat-icon ic-blue"><i class="fas fa-users"></i></div></div><div class="stat-value"><?= $totalCustomers ?></div></div>
    <div class="stat-card"><div class="stat-header"><div class="stat-label">Active This Period</div><div class="stat-icon ic-green"><i class="fas fa-user-check"></i></div></div><div class="stat-value"><?= $activeCustomers ?></div></div>
    <div class="stat-card"><div class="stat-header"><div class="stat-label">Top Spender</div><div class="stat-icon ic-red"><i class="fas fa-crown"></i></div></div><div class="stat-value" style="font-size:15px;"><?= !empty($topCustomers)?htmlspecialchars($topCustomers[0]['name']):'—' ?></div><div class="stat-trend"><?= !empty($topCustomers)?'RM '.number_format($topCustomers[0]['total_spent'],2):'' ?></div></div>
    <div class="stat-card"><div class="stat-header"><div class="stat-label">Avg Spend / Customer</div><div class="stat-icon ic-orange"><i class="fas fa-chart-line"></i></div></div><div class="stat-value" style="font-size:20px;">RM <?= $activeCustomers>0?number_format($totalRevenue/$activeCustomers,2):'0.00' ?></div></div>
</div>

<div class="card">
    <div class="card-head"><div class="card-title"><i class="fas fa-crown"></i> Top Customers by Spend</div><span class="card-sub"><?= htmlspecialchars($periodLabel) ?></span></div>
    <div style="overflow-x:auto;">
        <?php if(empty($topCustomers)): ?><div class="no-data"><i class="fas fa-users"></i><p>No activity this period.</p></div><?php else: $maxSpend=(float)$topCustomers[0]['total_spent']?:1; ?>
        <table class="data-table">
            <thead><tr><th>#</th><th>Customer</th><th>Email</th><th>Orders</th><th>Total Spent</th><th>Share</th></tr></thead>
            <tbody><?php foreach($topCustomers as $i=>$c): $share=round(($c['total_spent']/$maxSpend)*100); ?>
            <tr><td style="font-weight:700;color:var(--muted);"><?= $i+1 ?></td><td style="font-weight:600;"><?= htmlspecialchars($c['name']) ?></td><td style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($c['email']) ?></td><td><?= $c['order_count'] ?></td><td style="font-weight:700;color:var(--primary);">RM <?= number_format($c['total_spent'],2) ?></td>
            <td><div class="stock-mini"><div class="stock-mini-bar" style="width:70px;"><div class="stock-mini-fill" style="width:<?= $share ?>%;background:var(--primary);"></div></div><span style="font-size:10px;color:var(--muted);"><?= $share ?>%</span></div></td></tr>
            <?php endforeach; ?></tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php elseif($tab==='payments'): ?>
<!-- ==================== PAYMENTS ==================== -->
<?php $validTotal=0;$pendTotal=0;$invalidTotal=0; foreach($payVerification as $pv){match($pv['verification_status']){'VALID'=>$validTotal=(float)$pv['total'],'PENDING'=>$pendTotal=(float)$pv['total'],'INVALID'=>$invalidTotal=(float)$pv['total'],default=>null};} ?>
<div class="stat-grid">
    <div class="stat-card"><div class="stat-header"><div class="stat-label">Verified Revenue</div><div class="stat-icon ic-green"><i class="fas fa-check-circle"></i></div></div><div class="stat-value" style="font-size:18px;">RM <?= number_format($validTotal,2) ?></div></div>
    <div class="stat-card"><div class="stat-header"><div class="stat-label">Pending</div><div class="stat-icon ic-orange"><i class="fas fa-hourglass-half"></i></div></div><div class="stat-value" style="font-size:18px;">RM <?= number_format($pendTotal,2) ?></div><div class="stat-trend"><?= $pendingCount ?> payments</div></div>
    <div class="stat-card"><div class="stat-header"><div class="stat-label">Rejected</div><div class="stat-icon ic-red"><i class="fas fa-times-circle"></i></div></div><div class="stat-value" style="font-size:18px;">RM <?= number_format($invalidTotal,2) ?></div></div>
    <div class="stat-card"><div class="stat-header"><div class="stat-label">Top Method</div><div class="stat-icon ic-blue"><i class="fas fa-credit-card"></i></div></div><div class="stat-value" style="font-size:16px;"><?= !empty($paymentMethods)?htmlspecialchars(match($paymentMethods[0]['payment_method']){'CASH'=>'Cash','TRANSFER'=>'Bank Transfer','OTHER'=>'E-Wallet',default=>$paymentMethods[0]['payment_method']}):'—' ?></div></div>
</div>

<div class="two-col">
    <div class="card">
        <div class="card-head"><div class="card-title"><i class="fas fa-credit-card"></i> Payment Methods</div><span class="card-sub"><?= htmlspecialchars($periodLabel) ?></span></div>
        <div class="card-body">
            <?php if(empty($paymentMethods)): ?><div class="no-data"><i class="fas fa-credit-card"></i><p>No payments.</p></div><?php else: ?>
            <div class="method-grid"><?php foreach($paymentMethods as $pm): $mPct=$payMethodTotal>0?($pm['total']/$payMethodTotal)*100:0; $mLbl=match($pm['payment_method']){'CASH'=>'Cash','TRANSFER'=>'Bank Transfer','OTHER'=>'E-Wallet/Other',default=>$pm['payment_method']}; ?>
                <div class="method-row"><span class="method-name"><?= $mLbl ?></span><div class="method-bar-track"><div class="method-bar-fill" style="width:<?= max(3,$mPct) ?>%;"><?php if($mPct>15):?><?= round($mPct) ?>%<?php endif;?></div></div><span class="method-total">RM <?= number_format($pm['total'],0) ?></span></div>
            <?php endforeach; ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><div class="card-title"><i class="fas fa-shield-alt"></i> Verification Status</div></div>
        <div class="card-body">
            <?php if(empty($payVerification)): ?><div class="no-data"><i class="fas fa-receipt"></i><p>No records.</p></div><?php else: $pvCols=['VALID'=>'#10b981','PENDING'=>'#f59e0b','INVALID'=>'#ef4444']; ?>
            <div class="status-grid"><?php foreach($payVerification as $pv): $col=$pvCols[$pv['verification_status']]??'#607D8B'; $pct=round(($pv['cnt']/$pvTotal)*100,1); $lbl=match($pv['verification_status']){'VALID'=>'Verified','PENDING'=>'Pending','INVALID'=>'Rejected',default=>$pv['verification_status']}; ?>
                <div class="status-row"><div class="status-dot" style="background:<?= $col ?>;"></div><span class="status-name"><?= $lbl ?></span><div class="status-bar-track"><div class="status-bar-fill" style="width:<?= $pct ?>%;background:<?= $col ?>;"></div></div><span class="status-count"><?= $pv['cnt'] ?></span><span class="status-pct"><?= $pct ?>%</span></div>
            <?php endforeach; ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-head"><div class="card-title"><i class="fas fa-history"></i> Recent Transactions</div><span class="card-sub">Latest 10</span></div>
    <div style="overflow-x:auto;">
        <?php if(empty($recentPayments)): ?><div class="no-data"><i class="fas fa-receipt"></i><p>No payments found.</p></div><?php else: ?>
        <table class="data-table">
            <thead><tr><th>Payment ID</th><th>Date</th><th>Customer</th><th>Order</th><th>Method</th><th>Amount</th><th>Status</th></tr></thead>
            <tbody><?php foreach($recentPayments as $pay): $bC=match($pay['verification_status']){'VALID'=>'b-green','PENDING'=>'b-yellow','INVALID'=>'b-red',default=>'b-gray'}; $bL=match($pay['verification_status']){'VALID'=>'Verified','PENDING'=>'Pending','INVALID'=>'Rejected',default=>$pay['verification_status']}; $mL=match($pay['payment_method']){'CASH'=>'Cash','TRANSFER'=>'Transfer','OTHER'=>'E-Wallet',default=>$pay['payment_method']}; ?>
            <tr><td class="mono"><?= htmlspecialchars($pay['payment_id']) ?></td><td style="font-size:11px;color:var(--muted);"><?= date('d M Y H:i',strtotime($pay['record_date'])) ?></td><td><?= htmlspecialchars($pay['customer_name']) ?></td><td class="mono"><?= htmlspecialchars($pay['order_id']) ?></td><td><?= $mL ?></td><td style="font-weight:700;color:var(--primary);">RM <?= number_format($pay['amount'],2) ?></td><td><span class="badge <?= $bC ?>"><?= $bL ?></span></td></tr>
            <?php endforeach; ?></tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

</div><!-- /.page-body -->
</main>

<script>
// ── Sales Forecast ───────────────────────────────────────────
async function runForecast() {
    const btn  = document.getElementById('fcBtn');
    const load = document.getElementById('fcLoading');
    const cont = document.getElementById('fcContent');
    const err  = document.getElementById('fcErr');

    btn.disabled  = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running…';
    load.style.display = 'flex';
    cont.style.display = 'none';
    err.style.display  = 'none';

    try {
        const r    = await fetch('forecasting/run_forecast.php', { method: 'POST' });
        const data = await r.json();

        load.style.display = 'none';

        if (!data.success) {
            err.innerHTML     = '<i class="fas fa-exclamation-circle"></i> ' + esc(data.error);
            err.style.display = 'block';
            cont.style.display = 'block';
            return;
        }

        renderForecast(data);

    } catch (e) {
        load.style.display = 'none';
        err.innerHTML      = '<i class="fas fa-exclamation-circle"></i> Network error: ' + esc(e.message);
        err.style.display  = 'block';
        cont.style.display = 'block';
    } finally {
        btn.disabled  = false;
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh Forecast';
    }
}

function renderForecast(data) {
    const cont  = document.getElementById('fcContent');
    const preds = data.predictions || [];
    const model = data.model       || {};
    const cmp   = data.comparison  || {};
    const eval_ = data.evaluation  || {};

    if (!preds.length) {
        cont.innerHTML = '<div class="fc-empty">No predictions returned.</div>';
        cont.style.display = 'block'; return;
    }

    const maxRev = Math.max(...preds.map(p => p.predicted_revenue)) || 1;
    const winner = cmp.winner || 'linear';
    const linM   = cmp.linear     || {};
    const polyM  = cmp.polynomial || {};
    const seasM  = cmp.seasonal   || {};

    // Plain-English summary
    const trendWord = model.trend === 'upward'   ? 'trending upward 📈'
                    : model.trend === 'downward' ? 'trending downward 📉'
                    : 'holding steady ➡️';
    const nextMonth = preds[0];
    const plain = `Revenue is <strong>${trendWord}</strong>. Based on your last
        ${model.data_points||'?'} months of sales, we expect around
        <strong>RM ${fmtRM(nextMonth?.predicted_revenue||0)}</strong> next month.`;

    // Confidence badge is based on full-history R2 (all months), not the
    // 3-month test R2. The test R2 measures a fixed, tiny hold-out window
    // no matter how much data you have — it never stabilizes with more
    // history, so it's the wrong number to gate user-facing confidence on.
    // Full-history R2 genuinely improves as more months accumulate.
    const r2 = model.full_r_squared ?? model.r_squared ?? 0;
    const accuracyLabel = r2 >= 0.85 ? '🟢 High confidence'
                        : r2 >= 0.65 ? '🟡 Moderate confidence'
                        : '🔴 Low confidence — more data needed';
    const accuracyTip = r2 >= 0.85
        ? 'The model fits your full sales history well.'
        : r2 >= 0.65 ? 'Reasonable fit across your sales history. More data will improve accuracy further.'
        : 'Not enough data for a reliable forecast yet.';

    // Month cards
    const monthCards = preds.map((p,i) => {
        const pct = Math.round((p.predicted_revenue/maxRev)*100);
        const opacity = 1-(i*0.12);
        return `<div class="fc-month-card" style="opacity:${opacity}">
            <div class="fc-month-label">${esc(p.month_label)}</div>
            <div class="fc-month-val">RM ${fmtRM(p.predicted_revenue)}</div>
            <div style="font-size:11px;color:var(--muted);margin-bottom:8px;">
                ${esc(p.relative_label || (i===0?'Next month':i===1?'In 2 months':'In 3 months'))}</div>
            <div class="fc-month-bar-wrap">
                <div class="fc-month-bar" style="width:${pct}%"></div>
            </div></div>`;
    }).join('');

    // Model comparison rows (for technical section)
    const modelRows = [
        {key:'linear',     m:linM,  label:'Linear Regression'},
        {key:'polynomial', m:polyM, label:'Polynomial (curve fit)'},
        {key:'seasonal',   m:seasM, label:'Seasonal (time + calendar month)'},
    ].map(({key,m,label}) => {
        const isW = key===winner;
        return `<tr class="${isW?'winner-row':''}">
            <td>${label} ${isW?'<span class="fc-winner-badge">✓ Used for forecast</span>':''}</td>
            <td class="${isW?'metric-good':''}">RM ${fmtRM(m.mae||0)}<br>
                <span style="font-size:10px;color:var(--muted);">avg error/month</span></td>
            <td class="${isW?'metric-good':''}">RM ${fmtRM(m.rmse||0)}<br>
                <span style="font-size:10px;color:var(--muted);">penalises big errors</span></td>
            <td class="${isW?'metric-good':''}">${fmtFit(m.r_squared)}<br>
                <span style="font-size:10px;color:var(--muted);">how well it fits</span></td>
        </tr>`;
    }).join('');

    // Actual vs predicted rows
    const testResults = eval_.test_results || [];
    const maxTest = Math.max(...testResults.flatMap(r=>[r.actual,r.linear_pred,r.poly_pred,r.seasonal_pred]))||1;
    const avpRows = testResults.map(r => {
        const linErr  = Math.abs(r.actual-r.linear_pred);
        const polyErr = Math.abs(r.actual-r.poly_pred);
        const seasErr = Math.abs(r.actual-r.seasonal_pred);
        return `<div style="padding:12px 16px;border-bottom:1px solid var(--border);">
            <div style="font-size:12px;font-weight:700;margin-bottom:8px;">${esc(r.month_label)}</div>
            ${avpBar('Actual', r.actual, maxTest,'#374151')}
            ${avpBar('Linear', r.linear_pred, maxTest,'#3b82f6',`off by RM ${fmtRM(linErr)}`)}
            ${avpBar('Poly°2', r.poly_pred,   maxTest,'#8b5cf6',`off by RM ${fmtRM(polyErr)}`)}
            ${avpBar('Season', r.seasonal_pred, maxTest,'#059669',`off by RM ${fmtRM(seasErr)}`)}
        </div>`;
    }).join('');

    cont.innerHTML = `
        <!-- Plain English -->
        <div style="padding:14px 18px;background:rgba(5,150,105,0.06);border-radius:9px;
                    border-left:4px solid #059669;margin-bottom:16px;font-size:14px;
                    color:var(--text);line-height:1.7;">
            ${plain}
            <div style="margin-top:8px;font-size:13px;">
                ${accuracyLabel}
                <span style="color:var(--muted);margin-left:6px;">${accuracyTip}</span>
            </div>
        </div>

        <!-- 3-month cards -->
        <div class="fc-months" style="margin-bottom:12px;">${monthCards}</div>
        <div style="font-size:11px;color:var(--muted);text-align:center;margin-bottom:16px;">
            <i class="fas fa-info-circle"></i>
            Based on your sales history. Actual results may vary.
            Generated ${esc(data.generated_at||'')}
        </div>

        <!-- Collapsible technical details -->
        <div style="border:1px solid var(--border);border-radius:9px;overflow:hidden;">
            <button onclick="toggleFcDetails(this)"
                style="width:100%;padding:12px 16px;background:rgba(5,150,105,0.03);
                       border:none;cursor:pointer;display:flex;align-items:center;
                       justify-content:space-between;font-size:13px;font-weight:600;
                       color:#065f46;text-align:left;">
                <span><i class="fas fa-flask" style="margin-right:7px;"></i>
                    Technical Details</span>
                <i class="fas fa-chevron-down" id="fcDetailsChev"
                   style="transition:transform 0.2s;font-size:12px;"></i>
            </button>
            <div id="fcDetails" style="display:none;padding:16px;">
                <!-- How we tested -->
                <div style="padding:10px 14px;background:rgba(59,130,246,0.05);
                            border:1px solid #bfdbfe;border-radius:8px;font-size:12px;
                            color:#1d4ed8;margin-bottom:14px;">
                    <strong>How accuracy was tested:</strong>
                    Trained on <strong>${eval_.train_months||'?'} months</strong>
                    ${eval_.train_period?'('+esc(eval_.train_period)+')':''},
                    then tested on <strong>${eval_.test_months||'?'} months</strong>
                    ${eval_.test_period?'('+esc(eval_.test_period)+')':''}
                    that the model had never seen — giving a realistic accuracy estimate.
                    <div style="margin-top:8px;padding-top:8px;border-top:1px solid #bfdbfe;">
                        <strong>Full-history fit:</strong> ${fmtFit(model.full_r_squared)}
                        <span style="color:var(--muted);">
                            — measured across all ${model.data_points||'?'} months rather than
                            just the ${eval_.test_months||'?'}-month test window, so it isn't
                            as sensitive to a small, volatile hold-out sample.
                        </span>
                    </div>
                </div>
                <!-- Model table -->
                <div style="font-size:11px;font-weight:700;color:var(--muted);
                            text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">
                    Model Comparison (on test data)
                </div>
                <div class="fc-compare-wrap" style="margin-bottom:14px;">
                    <table class="fc-table">
                        <thead><tr>
                            <th>Model</th><th>Avg Monthly Error</th>
                            <th>Worst-case Error</th><th>Fit Quality</th>
                        </tr></thead>
                        <tbody>${modelRows}</tbody>
                    </table>
                </div>
                <!-- AVP -->
                ${testResults.length>0?`
                <div style="font-size:11px;font-weight:700;color:var(--muted);
                            text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">
                    How close were the predictions?</div>
                <div class="fc-avp-wrap" style="margin-bottom:8px;">
                    <div style="display:flex;gap:12px;padding:8px 16px;
                                border-bottom:1px solid var(--border);font-size:11px;">
                        <span><span style="display:inline-block;width:9px;height:9px;
                            border-radius:2px;background:#374151;margin-right:3px;"></span>Actual</span>
                        <span><span style="display:inline-block;width:9px;height:9px;
                            border-radius:2px;background:#3b82f6;margin-right:3px;"></span>Linear</span>
                        <span><span style="display:inline-block;width:9px;height:9px;
                            border-radius:2px;background:#8b5cf6;margin-right:3px;"></span>Polynomial</span>
                        <span><span style="display:inline-block;width:9px;height:9px;
                            border-radius:2px;background:#059669;margin-right:3px;"></span>Seasonal</span>
                    </div>
                    ${avpRows}
                </div>`:''}
            </div>
        </div>`;
    cont.style.display = 'block';
}

function toggleFcDetails(btn) {
    const panel = document.getElementById('fcDetails');
    const chev  = document.getElementById('fcDetailsChev');
    const open  = panel.style.display==='none';
    panel.style.display  = open?'block':'none';
    chev.style.transform = open?'rotate(180deg)':'rotate(0deg)';
    btn.style.background = open?'rgba(5,150,105,0.08)':'rgba(5,150,105,0.03)';
}

function avpBar(label, value, maxVal, color, subtitle) {
    const pct = Math.round((value/maxVal)*100);
    return `<div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;">
        <span style="font-size:11px;color:var(--muted);width:52px;flex-shrink:0;">${label}</span>
        <div style="flex:1;height:14px;background:var(--border);border-radius:4px;overflow:hidden;">
            <div style="height:100%;width:${pct}%;background:${color};border-radius:4px;"></div>
        </div>
        <span style="font-size:11px;font-weight:700;min-width:72px;text-align:right;color:${color};">
            RM ${fmtRM(value)}</span>
        ${subtitle?`<span style="font-size:10px;color:var(--muted);min-width:100px;">${subtitle}</span>`:''}
    </div>`;
}

function fmtRM(n) {
    return Number(n).toLocaleString('en-MY',{minimumFractionDigits:2,maximumFractionDigits:2});
}

// R² can legitimately go negative when a model tested on a short/volatile
// holdout period performs worse than just guessing the average — this is
// expected with limited data, not a bug. A raw "-31%" reads as broken to
// a non-technical viewer, so negative values get a clear label instead.
function fmtFit(r2) {
    const v = r2 || 0;
    if (v < 0) {
        return `<span style="color:#dc2626;">Below average</span>`;
    }
    return `${Math.round(v * 100)}%`;
}

async function generateInsights() {
    const btn  = document.getElementById('aiBtn');
    const body = document.getElementById('aiBody');
    const load = document.getElementById('aiLoading');
    const res  = document.getElementById('aiResults');
    const err  = document.getElementById('aiErr');

    btn.disabled  = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analysing…';

    body.classList.add('show');
    load.style.display = 'flex';
    res.style.display  = 'none';
    err.style.display  = 'none';

    try {
        const fd = new FormData();
        fd.append('period',    '<?= addslashes($period) ?>');
        fd.append('date_from', '<?= addslashes($dateFrom) ?>');
        fd.append('date_to',   '<?= addslashes($dateTo) ?>');

        const r    = await fetch('ai_report_insights.php', { method: 'POST', body: fd });
        const data = await r.json();

        load.style.display = 'none';

        if (!data.success) {
            err.textContent   = data.error || 'Failed to generate insights.';
            err.style.display = 'block';
            return;
        }

        const ins = data.insights;
        document.getElementById('aiSummary').textContent = ins.summary;
        document.getElementById('aiHL').innerHTML = (ins.highlights || []).map(h =>
            `<div class="ai-item"><i class="fas fa-check" style="color:#2e7d32;margin-top:3px;flex-shrink:0;font-size:11px;"></i>${esc(h)}</div>`
        ).join('');
        document.getElementById('aiCon').innerHTML = (ins.concerns || []).map(c =>
            `<div class="ai-item"><i class="fas fa-exclamation-circle" style="color:#c62828;margin-top:3px;flex-shrink:0;font-size:11px;"></i>${esc(c)}</div>`
        ).join('');
        document.getElementById('aiRec').innerHTML =
            `<i class="fas fa-arrow-right" style="margin-right:6px;"></i>${esc(ins.recommendation || '')}`;
        document.getElementById('aiTs').textContent =
            'Generated ' + new Date().toLocaleTimeString() + ' · ' + data.period;

        res.style.display = 'block';

    } catch (e) {
        load.style.display = 'none';
        err.textContent   = 'Network error: ' + e.message;
        err.style.display = 'block';
    } finally {
        btn.disabled  = false;
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> Regenerate';
    }
}

function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
</body>
</html>