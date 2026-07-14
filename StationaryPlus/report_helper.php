<?php
// ============================================================
//  report_helper.php — Shared date-range helpers for admin
//  reporting pages (a_report.php, ai_report_insights.php).
//
//  Computes sargable date-range boundaries in PHP instead of
//  wrapping the order_date/record_date columns in DATE()/
//  MONTH()/YEAR() in SQL, so range filters can use a plain
//  B-tree index on the date column instead of forcing a full
//  table scan.
// ============================================================

const REPORT_PERIOD_LABELS = [
    'today'     => 'Today',
    'week'      => 'Last 7 Days',
    'month'     => 'This Month',
    'lastmonth' => 'Last Month',
    'quarter'   => 'This Quarter',
    'year'      => 'This Year',
    'alltime'   => 'All Time',
];

// Returns ['start' => ?string, 'end' => ?string, 'label' => string].
// start/end are 'Y-m-d H:i:s' boundaries, or null for "unbounded".
function reportPeriodRange(string $period, string $dateFrom = '', string $dateTo = ''): array {
    if ($period === 'custom') {
        $validFrom = (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) && strtotime($dateFrom) !== false;
        $validTo   = (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)   && strtotime($dateTo)   !== false;

        if ($validFrom && $validTo) {
            return [
                'start' => $dateFrom . ' 00:00:00',
                'end'   => date('Y-m-d 00:00:00', strtotime($dateTo . ' +1 day')),
                'label' => date('d M Y', strtotime($dateFrom)) . ' – ' . date('d M Y', strtotime($dateTo)),
            ];
        }
        // Invalid/missing custom dates — fall back to 'month' below.
        $period = 'month';
    }

    $now = time();

    switch ($period) {
        case 'today':
            return [
                'start' => date('Y-m-d 00:00:00', $now),
                'end'   => date('Y-m-d 00:00:00', strtotime('+1 day', $now)),
                'label' => REPORT_PERIOD_LABELS['today'],
            ];
        case 'week':
            return [
                'start' => date('Y-m-d H:i:s', strtotime('-7 days', $now)),
                'end'   => null,
                'label' => REPORT_PERIOD_LABELS['week'],
            ];
        case 'lastmonth':
            return [
                'start' => date('Y-m-01 00:00:00', strtotime('-1 month', $now)),
                'end'   => date('Y-m-01 00:00:00', $now),
                'label' => REPORT_PERIOD_LABELS['lastmonth'],
            ];
        case 'quarter':
            return [
                'start' => date('Y-m-d H:i:s', strtotime('-3 months', $now)),
                'end'   => null,
                'label' => REPORT_PERIOD_LABELS['quarter'],
            ];
        case 'year':
            return [
                'start' => date('Y-01-01 00:00:00', $now),
                'end'   => date('Y-01-01 00:00:00', strtotime('+1 year', $now)),
                'label' => REPORT_PERIOD_LABELS['year'],
            ];
        case 'alltime':
            return ['start' => null, 'end' => null, 'label' => REPORT_PERIOD_LABELS['alltime']];
        case 'month':
        default:
            return [
                'start' => date('Y-m-01 00:00:00', $now),
                'end'   => date('Y-m-01 00:00:00', strtotime('+1 month', $now)),
                'label' => REPORT_PERIOD_LABELS['month'],
            ];
    }
}

// Returns ['start' => ?string, 'end' => ?string] for the period immediately
// preceding $period, used for period-over-period growth comparisons.
// 'custom'/'alltime' have no well-defined "previous period", so both
// boundaries come back null (reportRangeClause then omits the filter
// entirely, matching the pre-existing behavior for those two periods).
function reportPrevPeriodRange(string $period): array {
    $now = time();

    switch ($period) {
        case 'today':
            return [
                'start' => date('Y-m-d 00:00:00', strtotime('-1 day', $now)),
                'end'   => date('Y-m-d 00:00:00', $now),
            ];
        case 'week':
            return [
                'start' => date('Y-m-d H:i:s', strtotime('-14 days', $now)),
                'end'   => date('Y-m-d H:i:s', strtotime('-7 days', $now)),
            ];
        case 'month':
            return [
                'start' => date('Y-m-01 00:00:00', strtotime('-1 month', $now)),
                'end'   => date('Y-m-01 00:00:00', $now),
            ];
        case 'lastmonth':
            return [
                'start' => date('Y-m-01 00:00:00', strtotime('-2 months', $now)),
                'end'   => date('Y-m-01 00:00:00', strtotime('-1 month', $now)),
            ];
        case 'quarter':
            return [
                'start' => date('Y-m-d H:i:s', strtotime('-6 months', $now)),
                'end'   => date('Y-m-d H:i:s', strtotime('-3 months', $now)),
            ];
        case 'year':
            return [
                'start' => date('Y-01-01 00:00:00', strtotime('-1 year', $now)),
                'end'   => date('Y-01-01 00:00:00', $now),
            ];
        default: // 'alltime', 'custom'
            return ['start' => null, 'end' => null];
    }
}

// Builds a sargable " AND column >= 'x' AND column < 'y'" clause.
// $start/$end are always PHP-computed (either strtotime()-derived or
// strictly format-validated custom input), so plain interpolation here
// is safe — no user input reaches this function unvalidated.
function reportRangeClause(?string $start, ?string $end, string $column): string {
    $parts = [];
    if ($start !== null) $parts[] = "$column >= '$start'";
    if ($end   !== null) $parts[] = "$column < '$end'";
    return $parts ? ' AND ' . implode(' AND ', $parts) : '';
}
