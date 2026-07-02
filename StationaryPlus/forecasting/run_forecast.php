<?php
// ============================================================
//  run_forecast.php — Sales Forecast (Pure PHP, no Python)
//  Called via AJAX from a_report.php
//  POST: (none required)
//  Returns JSON: { success, predictions, historical, comparison,
//                  evaluation, model, generated_at }
//
//  Methodology: train/test split, MAE/RMSE/R² evaluation on the
//  held-out test set, winner-takes-forecast. Three candidate
//  models are compared:
//    - Linear Regression            (time only)
//    - Polynomial Regression deg. 2 (time only)
//    - Seasonal Regression          (time + calendar seasonality)
//
//  order_count is NOT used as a model input — future order counts
//  aren't knowable in advance, so using it would make the forecast
//  impossible to actually run forward. It's still returned in the
//  historical/JSON output for display, since it's a useful
//  explanatory stat even though it isn't a forecasting input.
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../auth.php';
require_role('ADMIN');
require_once '../db.php';
require_once 'forecast_engine.php';

header('Content-Type: application/json');

try {
    // ── Pull monthly revenue (last 36 months) ───────────────────────
    $sql = "
        SELECT
            DATE_FORMAT(o.order_date, '%Y-%m-01') AS month_start,
            COALESCE(SUM(p.amount), 0)            AS revenue,
            COUNT(DISTINCT p.order_id)            AS order_count
        FROM payments p
        JOIN orders o ON p.order_id = o.order_id
        WHERE p.verification_status = 'VALID'
          AND o.order_date >= DATE_SUB(NOW(), INTERVAL 36 MONTH)
        GROUP BY month_start
        ORDER BY month_start ASC
    ";
    $result = $conn->query($sql);
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $n = count($rows);
    if ($n < 6) {
        echo json_encode([
            'success' => false,
            'error'   => "Need at least 6 months of data for a meaningful train/test split. Found $n month(s).",
        ]);
        exit;
    }

    // ── Prepare data arrays ─────────────────────────────────────────
    $monthStarts = array_map(fn($r) => $r['month_start'], $rows);
    $revenue     = array_map(fn($r) => (float)$r['revenue'], $rows);
    $orderCount  = array_map(fn($r) => (int)$r['order_count'], $rows);
    $monthIdx    = range(0, $n - 1);
    // Calendar month (1-12) for each row — needed by the seasonal model
    $monthOfYr   = array_map(fn($m) => (int)date('n', strtotime($m)), $monthStarts);

    // ── Train / test split (75% / 25%, min 3 test months) ──────────
    $split = max((int)($n * 0.75), $n - 3);

    $xTrain   = array_slice($monthIdx, 0, $split);
    $xTest    = array_slice($monthIdx, $split);
    $yTrain   = array_slice($revenue, 0, $split);
    $yTest    = array_slice($revenue, $split);
    $moyTrain = array_slice($monthOfYr, 0, $split);
    $moyTest  = array_slice($monthOfYr, $split);

    // ── Train all three models on the training set ──────────────────
    $lin  = new LinearModel();
    $poly = new PolynomialModel();
    $seas = new SeasonalModel();
    $lin->fit($xTrain, $yTrain);
    $poly->fit($xTrain, $yTrain);
    $seas->fit($xTrain, $moyTrain, $yTrain);

    // ── Evaluate on TEST set ─────────────────────────────────────────
    $linPredTest  = $lin->predictAll($xTest);
    $polyPredTest = $poly->predictAll($xTest);
    $seasPredTest = $seas->predictAll($xTest, $moyTest);

    $linMetrics  = calcMetrics($yTest, $linPredTest);
    $polyMetrics = calcMetrics($yTest, $polyPredTest);
    $seasMetrics = calcMetrics($yTest, $seasPredTest);

    // ── Pick winner: lowest test RMSE among non-degenerate models ───
    $candidates = [
        'linear'     => ['rmse' => $linMetrics['rmse'],  'degenerate' => false],
        'polynomial' => ['rmse' => $polyMetrics['rmse'], 'degenerate' => $poly->degenerate],
        'seasonal'   => ['rmse' => $seasMetrics['rmse'], 'degenerate' => $seas->degenerate],
    ];
    $winner = 'linear';
    $bestRmse = INF;
    foreach ($candidates as $key => $c) {
        if ($c['degenerate']) continue;
        if ($c['rmse'] < $bestRmse) {
            $bestRmse = $c['rmse'];
            $winner = $key;
        }
    }
    $winnerLabels = [
        'linear'     => 'Linear Regression',
        'polynomial' => 'Polynomial Regression (degree 2)',
        'seasonal'   => 'Seasonal Regression (time + calendar month)',
    ];
    $winnerLabel = $winnerLabels[$winner];

    // ── Test results (actual vs predicted per test month, all 3 models) ─
    $testResults = [];
    foreach ($xTest as $i => $idx) {
        $testResults[] = [
            'month_label' => date('M Y', strtotime($monthStarts[$idx])),
            'actual'      => round($yTest[$i], 2),
            'linear_pred' => round($linPredTest[$i], 2),
            'poly_pred'   => round($polyPredTest[$i], 2),
            'seasonal_pred' => round($seasPredTest[$i], 2),
        ];
    }

    // ── Retrain winner on FULL dataset for the future forecast ─────
    if ($winner === 'linear') {
        $finalModel = new LinearModel();
        $finalModel->fit($monthIdx, $revenue);
        $fullPred = $finalModel->predictAll($monthIdx);
    } elseif ($winner === 'polynomial') {
        $finalModel = new PolynomialModel();
        $finalModel->fit($monthIdx, $revenue);
        $fullPred = $finalModel->predictAll($monthIdx);
    } else {
        $finalModel = new SeasonalModel();
        $finalModel->fit($monthIdx, $monthOfYr, $revenue);
        $fullPred = $finalModel->predictAll($monthIdx, $monthOfYr);
    }

    // Full-data R² for winner (training quality indicator)
    $fullMetrics = calcMetrics($revenue, $fullPred);
    $fullR2 = $fullMetrics['r_squared'];

    $winnerMetrics = match($winner) {
        'linear'     => $linMetrics,
        'polynomial' => $polyMetrics,
        'seasonal'   => $seasMetrics,
    };

    // ── Predict next 3 months ────────────────────────────────────────
    $lastIdx   = $n;
    $lastMonth = new DateTime($monthStarts[$n - 1]);

    // "Today" for labeling purposes — compared against each predicted
    // month so the label reflects the real calendar, not just array
    // position. If sales data is up to date, predictions[0] usually IS
    // next month; but if the most recent complete month is older (e.g.
    // this month's sales aren't finalized yet), predictions[0] can
    // actually BE the current month — the label needs to say so.
    $todayFirst = new DateTime('first day of this month');

    $predictions = [];
    for ($i = 1; $i <= 3; $i++) {
        $futureIdx = $lastIdx + $i - 1;

        $futureMonth = clone $lastMonth;
        $futureMonth->modify("+$i month");
        $futureMoy = (int)$futureMonth->format('n');

        if ($winner === 'seasonal') {
            $predictedRev = $finalModel->predict($futureIdx, $futureMoy);
        } else {
            $predictedRev = $finalModel->predict($futureIdx);
        }
        $predictedRev = max(0.0, round($predictedRev, 2));

        $diffMonths = (((int)$futureMonth->format('Y') - (int)$todayFirst->format('Y')) * 12)
                    + ((int)$futureMonth->format('n') - (int)$todayFirst->format('n'));
        $relativeLabel = match(true) {
            $diffMonths <= 0 => 'This month',
            $diffMonths === 1 => 'Next month',
            default => "In $diffMonths months",
        };

        $predictions[] = [
            'month'             => $futureMonth->format('Y-m-01'),
            'month_label'       => $futureMonth->format('F Y'),
            'predicted_revenue' => $predictedRev,
            'relative_label'    => $relativeLabel,
        ];
    }

    // ── Historical summary (last 6 months for chart) ────────────────
    $historical = [];
    $tail = array_slice($rows, -6);
    foreach ($tail as $row) {
        $historical[] = [
            'month_label' => date('M Y', strtotime($row['month_start'])),
            'revenue'     => (float)$row['revenue'],
            'order_count' => (int)$row['order_count'],
        ];
    }

    // ── Persist predictions to DB ────────────────────────────────────
    $conn->query("DELETE FROM sales_forecasts");
    $insertStmt = $conn->prepare(
        "INSERT INTO sales_forecasts
            (forecast_month, predicted_revenue, model_type, r_squared, data_points)
         VALUES (?, ?, ?, ?, ?)"
    );
    foreach ($predictions as $pred) {
        $insertStmt->bind_param(
            'sdsdi',
            $pred['month'],
            $pred['predicted_revenue'],
            $winnerLabel,
            $winnerMetrics['r_squared'],
            $n
        );
        $insertStmt->execute();
    }
    $insertStmt->close();

    // ── Trend direction ──────────────────────────────────────────────
    $revChange = $predictions[2]['predicted_revenue'] - $predictions[0]['predicted_revenue'];
    $trend = $revChange > 0 ? 'upward' : ($revChange < 0 ? 'downward' : 'flat');

    // ── Output ────────────────────────────────────────────────────────
    echo json_encode([
        'success'     => true,
        'predictions' => $predictions,
        'historical'  => $historical,

        'comparison' => [
            'linear' => [
                'name'      => 'Linear Regression',
                'mae'       => $linMetrics['mae'],
                'rmse'      => $linMetrics['rmse'],
                'r_squared' => $linMetrics['r_squared'],
            ],
            'polynomial' => [
                'name'      => 'Polynomial Regression (degree 2)',
                'mae'       => $polyMetrics['mae'],
                'rmse'      => $polyMetrics['rmse'],
                'r_squared' => $polyMetrics['r_squared'],
            ],
            'seasonal' => [
                'name'      => 'Seasonal Regression (time + calendar month)',
                'mae'       => $seasMetrics['mae'],
                'rmse'      => $seasMetrics['rmse'],
                'r_squared' => $seasMetrics['r_squared'],
            ],
            'winner' => $winner,
        ],

        'evaluation' => [
            'total_months' => $n,
            'train_months' => $split,
            'test_months'  => $n - $split,
            'train_period' => date('M Y', strtotime($monthStarts[0])) . ' – ' . date('M Y', strtotime($monthStarts[$split - 1])),
            'test_period'  => date('M Y', strtotime($monthStarts[$split])) . ' – ' . date('M Y', strtotime($monthStarts[$n - 1])),
            'test_results' => $testResults,
        ],

        'model' => [
            'type'           => $winnerLabel,
            'winner'         => $winner,
            'mae'            => $winnerMetrics['mae'],
            'rmse'           => $winnerMetrics['rmse'],
            'r_squared'      => $winnerMetrics['r_squared'],
            'full_r_squared' => $fullR2,
            'data_points'    => $n,
            'trend'          => $trend,
        ],

        'generated_at' => date('Y-m-d H:i:s'),
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => 'Forecast error: ' . $e->getMessage(),
    ]);
}