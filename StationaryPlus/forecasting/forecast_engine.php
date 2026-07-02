<?php
// ============================================================
//  forecast_engine.php — Pure PHP Sales Forecasting Engine
//
//  Replaces forecast.py (sklearn LinearRegression + Polynomial
//  Pipeline) with hand-written closed-form regression. No
//  external process, no Python, no pip packages — runs inline
//  in the same PHP request as everything else on the site.
//
//  Methodology is IDENTICAL to the original Python version:
//    - Train/test split (75% train, 25% test, min 3 test months)
//    - Evaluate BOTH models on the held-out test set (MAE, RMSE, R²)
//    - Winning model (lower test RMSE) retrained on full data
//    - 3-month forward forecast from the winner
// ============================================================

/**
 * Simple Linear Regression: y = a + b*x
 * Closed-form Ordinary Least Squares (OLS).
 */
class LinearModel
{
    public float $a = 0.0; // intercept
    public float $b = 0.0; // slope

    public function fit(array $x, array $y): void
    {
        $n = count($x);
        $sumX  = array_sum($x);
        $sumY  = array_sum($y);
        $sumXY = 0.0;
        $sumX2 = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] * $x[$i];
        }

        $denominator = ($n * $sumX2) - ($sumX * $sumX);
        // Guard: all-identical x values would make this zero (shouldn't
        // happen with sequential month indices, but defend anyway)
        if (abs($denominator) < 1e-9) {
            $this->b = 0.0;
            $this->a = $n > 0 ? $sumY / $n : 0.0;
            return;
        }

        $this->b = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
        $this->a = ($sumY - ($this->b * $sumX)) / $n;
    }

    public function predict(float $x): float
    {
        return $this->a + ($this->b * $x);
    }

    public function predictAll(array $xs): array
    {
        return array_map(fn($x) => $this->predict($x), $xs);
    }
}

/**
 * Polynomial Regression, degree 2: y = a + b*x + c*x²
 * Solved via the normal equations (3x3 linear system, Cramer's rule).
 *
 * x is centered (mean-subtracted) before fitting to keep the matrix
 * well-conditioned — this mirrors what sklearn's internal scaling
 * effectively protects against, and prevents precision loss when
 * month indices grow large (e.g. 24 months of history).
 */
class PolynomialModel
{
    public float $a = 0.0;
    public float $b = 0.0;
    public float $c = 0.0;
    public float $xMean = 0.0;
    public bool $degenerate = false; // true if the fit was numerically unstable

    public function fit(array $x, array $y): void
    {
        $n = count($x);
        $this->xMean = array_sum($x) / $n;

        // Center x around zero for numerical stability
        $xc = array_map(fn($v) => $v - $this->xMean, $x);

        // Build normal equation sums
        // [ n      Σx     Σx²  ] [a]   [ Σy   ]
        // [ Σx     Σx²    Σx³  ] [b] = [ Σxy  ]
        // [ Σx²    Σx³    Σx⁴  ] [c]   [ Σx²y ]
        $Sx = $Sx2 = $Sx3 = $Sx4 = $Sy = $Sxy = $Sx2y = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $xi = $xc[$i];
            $yi = $y[$i];
            $Sx   += $xi;
            $Sx2  += $xi ** 2;
            $Sx3  += $xi ** 3;
            $Sx4  += $xi ** 4;
            $Sy   += $yi;
            $Sxy  += $xi * $yi;
            $Sx2y += ($xi ** 2) * $yi;
        }

        // 3x3 matrix, solved via Cramer's rule
        $M = [
            [$n,   $Sx,  $Sx2],
            [$Sx,  $Sx2, $Sx3],
            [$Sx2, $Sx3, $Sx4],
        ];
        $V = [$Sy, $Sxy, $Sx2y];

        $det = self::det3x3($M);

        // Guard against a near-singular matrix (extremely small/degenerate
        // datasets). If the determinant is too close to zero, solving would
        // amplify floating-point error into meaningless coefficients — so
        // we flag it and fall back to a flat/linear-like shape instead.
        if (abs($det) < 1e-6) {
            $this->degenerate = true;
            $this->a = $n > 0 ? $Sy / $n : 0.0;
            $this->b = 0.0;
            $this->c = 0.0;
            return;
        }

        $Ma = $M; $Ma[0][0] = $V[0]; $Ma[1][0] = $V[1]; $Ma[2][0] = $V[2];
        $Mb = $M; $Mb[0][1] = $V[0]; $Mb[1][1] = $V[1]; $Mb[2][1] = $V[2];
        $Mc = $M; $Mc[0][2] = $V[0]; $Mc[1][2] = $V[1]; $Mc[2][2] = $V[2];

        $this->a = self::det3x3($Ma) / $det;
        $this->b = self::det3x3($Mb) / $det;
        $this->c = self::det3x3($Mc) / $det;
    }

    public function predict(float $x): float
    {
        $xc = $x - $this->xMean;
        return $this->a + ($this->b * $xc) + ($this->c * $xc * $xc);
    }

    public function predictAll(array $xs): array
    {
        return array_map(fn($x) => $this->predict($x), $xs);
    }

    private static function det3x3(array $m): float
    {
        return $m[0][0] * ($m[1][1] * $m[2][2] - $m[1][2] * $m[2][1])
             - $m[0][1] * ($m[1][0] * $m[2][2] - $m[1][2] * $m[2][0])
             + $m[0][2] * ($m[1][0] * $m[2][1] - $m[1][1] * $m[2][0]);
    }
}

/**
 * Seasonal Regression: revenue = b0 + b1*monthIdx + b2*sin(2*pi*monthOfYear/12) + b3*cos(2*pi*monthOfYear/12)
 *
 * General multiple linear regression (Ordinary Least Squares), solved via
 * Gauss-Jordan elimination on the normal equations (XtX * beta = Xty).
 * Unlike order_count, both inputs here (a sequential month index, and the
 * calendar month 1-12) are always knowable in advance for future months —
 * which is what makes this model actually usable for forecasting, not just
 * for explaining historical variance.
 *
 * The time-index feature is standardized (z-scored) before fitting to keep
 * the normal-equation matrix well-conditioned across 36+ months of history;
 * sin/cos are already bounded in [-1, 1] and don't need it.
 */
class SeasonalModel
{
    public array $coef = [0.0, 0.0, 0.0, 0.0]; // [intercept, monthIdx, sin, cos]
    public bool $degenerate = false;

    private float $idxMean = 0.0;
    private float $idxStd  = 1.0;

    public function fit(array $monthIdx, array $monthOfYear, array $y): void
    {
        $n = count($monthIdx);

        $this->idxMean = array_sum($monthIdx) / $n;
        $variance = 0.0;
        foreach ($monthIdx as $v) $variance += ($v - $this->idxMean) ** 2;
        $this->idxStd = sqrt($variance / $n);
        if ($this->idxStd < 1e-9) $this->idxStd = 1.0;

        // Design matrix: [1, idx_z, sin, cos] per row
        $X = [];
        for ($i = 0; $i < $n; $i++) {
            $idxZ = ($monthIdx[$i] - $this->idxMean) / $this->idxStd;
            $angle = 2 * M_PI * $monthOfYear[$i] / 12.0;
            $X[] = [1.0, $idxZ, sin($angle), cos($angle)];
        }

        $p = 4; // number of coefficients
        $XtX = array_fill(0, $p, array_fill(0, $p, 0.0));
        $Xty = array_fill(0, $p, 0.0);
        for ($i = 0; $i < $n; $i++) {
            for ($a = 0; $a < $p; $a++) {
                $Xty[$a] += $X[$i][$a] * $y[$i];
                for ($b = 0; $b < $p; $b++) {
                    $XtX[$a][$b] += $X[$i][$a] * $X[$i][$b];
                }
            }
        }

        $solved = self::gaussJordanSolve($XtX, $Xty);
        if ($solved === null) {
            // Singular matrix — not enough variation to separate the four
            // effects reliably. Fall back to predicting the mean, same
            // philosophy as the degenerate guard in PolynomialModel.
            $this->degenerate = true;
            $this->coef = [$n > 0 ? array_sum($y) / $n : 0.0, 0.0, 0.0, 0.0];
            return;
        }
        $this->coef = $solved;
    }

    public function predict(float $monthIdx, int $monthOfYear): float
    {
        $idxZ  = ($monthIdx - $this->idxMean) / $this->idxStd;
        $angle = 2 * M_PI * $monthOfYear / 12.0;
        return $this->coef[0]
             + $this->coef[1] * $idxZ
             + $this->coef[2] * sin($angle)
             + $this->coef[3] * cos($angle);
    }

    public function predictAll(array $monthIdxArr, array $monthOfYearArr): array
    {
        $out = [];
        foreach ($monthIdxArr as $i => $idx) {
            $out[] = $this->predict($idx, $monthOfYearArr[$i]);
        }
        return $out;
    }

    /**
     * Solves A*x = b via Gauss-Jordan elimination with partial pivoting.
     * Returns null if A is (near-)singular — the multi-feature analogue
     * of PolynomialModel's det3x3 degeneracy guard.
     */
    private static function gaussJordanSolve(array $A, array $b): ?array
    {
        $n = count($A);
        $M = [];
        for ($i = 0; $i < $n; $i++) {
            $M[$i] = $A[$i];
            $M[$i][] = $b[$i];
        }

        for ($col = 0; $col < $n; $col++) {
            $maxRow = $col;
            $maxVal = abs($M[$col][$col]);
            for ($r = $col + 1; $r < $n; $r++) {
                if (abs($M[$r][$col]) > $maxVal) {
                    $maxVal = abs($M[$r][$col]);
                    $maxRow = $r;
                }
            }
            if ($maxVal < 1e-9) return null;

            if ($maxRow !== $col) {
                [$M[$col], $M[$maxRow]] = [$M[$maxRow], $M[$col]];
            }

            $pivot = $M[$col][$col];
            for ($k = $col; $k <= $n; $k++) $M[$col][$k] /= $pivot;

            for ($r = 0; $r < $n; $r++) {
                if ($r === $col) continue;
                $factor = $M[$r][$col];
                if (abs($factor) < 1e-12) continue;
                for ($k = $col; $k <= $n; $k++) $M[$r][$k] -= $factor * $M[$col][$k];
            }
        }

        $solution = [];
        for ($i = 0; $i < $n; $i++) $solution[] = $M[$i][$n];
        return $solution;
    }
}

/**
 * Metric helpers — identical formulas to sklearn's
 * mean_absolute_error, mean_squared_error (sqrt'd for RMSE), r2_score.
 */
function calcMetrics(array $yTrue, array $yPred): array
{
    $n = count($yTrue);
    if ($n === 0) {
        return ['mae' => 0.0, 'rmse' => 0.0, 'r_squared' => 0.0];
    }

    $absErrSum = 0.0;
    $sqErrSum  = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $err = $yTrue[$i] - $yPred[$i];
        $absErrSum += abs($err);
        $sqErrSum  += $err * $err;
    }
    $mae  = $absErrSum / $n;
    $rmse = sqrt($sqErrSum / $n);

    // R² = 1 - SS_res / SS_tot
    $yMean  = array_sum($yTrue) / $n;
    $ssTot  = 0.0;
    foreach ($yTrue as $v) $ssTot += ($v - $yMean) ** 2;
    $r2 = $ssTot > 0 ? 1 - ($sqErrSum / $ssTot) : 0.0;

    return [
        'mae'       => round($mae, 2),
        'rmse'      => round($rmse, 2),
        'r_squared' => round($r2, 4),
    ];
}