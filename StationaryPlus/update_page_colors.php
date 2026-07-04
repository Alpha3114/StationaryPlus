<?php
// ============================================================
//  update_page_colors.php — AJAX: apply a customer's manual
//  per-page B&W/colour override to a pending (not-yet-confirmed)
//  print job, and recalculate price/duration server-side.
//
//  POST: file_id, pages (JSON array of {page, is_color})
//  Returns JSON: same shape as upload_print.php's analysis result
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role('CUSTOMER');
require_once 'config.php';
require_once 'ai_helper.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];
$fileId = trim($_POST['file_id'] ?? '');
$pages  = json_decode($_POST['pages'] ?? '', true);

if (!$fileId || !isset($_SESSION['pending_prints'][$fileId])) {
    echo json_encode(['success' => false, 'error' => 'Session expired or file not found. Please re-upload your file.']);
    exit;
}

$pending = $_SESSION['pending_prints'][$fileId];

if ($pending['user_id'] !== $userId) {
    echo json_encode(['success' => false, 'error' => 'Unauthorised.']);
    exit;
}

if (!is_array($pages) || empty($pages)) {
    echo json_encode(['success' => false, 'error' => 'No page data provided.']);
    exit;
}

// Rebuild page_details from the client's override list, trusting only
// the page number and is_color flag — recompute everything else here.
$pageDetails = [];
$colorPages  = 0;
$bwPages     = 0;

foreach ($pages as $p) {
    $pageNum = (int)($p['page'] ?? 0);
    $isColor = !empty($p['is_color']);
    if ($pageNum < 1) continue;

    $pageDetails[] = ['page' => $pageNum, 'is_color' => $isColor, 'confidence' => 'customer_selected'];
    if ($isColor) $colorPages++;
    else          $bwPages++;
}

if (empty($pageDetails)) {
    echo json_encode(['success' => false, 'error' => 'No valid page data provided.']);
    exit;
}

$totalPages = count($pageDetails);

$breakdown    = getPriceBreakdown($colorPages, $bwPages, $pending['paper_size'], $pending['binding_type'], $pending['copies']);
$price        = $breakdown['total'];
$durationMins = estimateDuration($colorPages, $bwPages, $pending['copies']);
$printType    = $colorPages > 0 ? 'COLOR' : 'BLACK_WHITE';

// Persist the override into the pending session entry so confirm_print.php
// writes the corrected values when the customer submits.
$_SESSION['pending_prints'][$fileId]['total_pages']    = $totalPages;
$_SESSION['pending_prints'][$fileId]['color_pages']    = $colorPages;
$_SESSION['pending_prints'][$fileId]['bw_pages']       = $bwPages;
$_SESSION['pending_prints'][$fileId]['estimated_price']= $price;
$_SESSION['pending_prints'][$fileId]['duration_min']   = $durationMins;
$_SESSION['pending_prints'][$fileId]['print_type']     = $printType;

$analysis = json_decode($pending['analysis_json'], true) ?: [];
$analysis['pages'] = $pageDetails;
$_SESSION['pending_prints'][$fileId]['analysis_json'] = json_encode($analysis);

echo json_encode([
    'success'      => true,
    'file_id'      => $fileId,
    'filename'     => htmlspecialchars($pending['file_name']),
    'total_pages'  => $totalPages,
    'color_pages'  => $colorPages,
    'bw_pages'     => $bwPages,
    'price'        => $price,
    'breakdown'    => $breakdown,
    'duration_min' => $durationMins,
    'page_details' => $pageDetails,
    'ai_parse_ok'  => true,
    'print_mode'   => $pending['print_mode'],
]);
