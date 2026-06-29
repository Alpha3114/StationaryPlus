<?php
// ── Always return JSON, even on fatal errors ──────────────────
ini_set('display_errors', 0);
set_exception_handler(function (Throwable $e) {
    if (!headers_sent()) header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
        'file'    => basename($e->getFile()),
        'line'    => $e->getLine(),
    ]);
    exit;
});
// ============================================================
//  upload_print.php — Print file upload + AI colour detection
//
//  STEP 1 of 2:  Saves file to disk, runs AI analysis, stores
//  all pending data in $_SESSION.  Nothing is written to the DB.
//
//  STEP 2 of 2:  confirm_print.php reads the session entry and
//  does the actual INSERT when the customer clicks Confirm.
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role('CUSTOMER');
require_once 'db.php';
require_once 'config.php';
require_once 'ai_helper.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];

// ── 1. Validate uploaded file ─────────────────────────────────
if (empty($_FILES['print_file']) || $_FILES['print_file']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['print_file']['error'] ?? -1;
    $msg  = match($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds the maximum upload size.',
        UPLOAD_ERR_NO_FILE  => 'No file was selected.',
        default             => 'Upload failed (error ' . $code . ').',
    };
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

$file        = $_FILES['print_file'];
$allowedMime = ['application/pdf', 'image/jpeg', 'image/png'];
$mime        = mime_content_type($file['tmp_name']);

if (!in_array($mime, $allowedMime)) {
    echo json_encode(['success' => false, 'error' => 'Only PDF, JPG, and PNG files are accepted.']);
    exit;
}

if ($file['size'] > 20 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'File must be under 20 MB.']);
    exit;
}

// ── 2. Save file to disk ──────────────────────────────────────
$uploadDir = 'uploads/print_files/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$fileId   = 'PF-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
$saveName = $fileId . '.' . $ext;
$savePath = $uploadDir . $saveName;

if (!move_uploaded_file($file['tmp_name'], $savePath)) {
    echo json_encode(['success' => false, 'error' => 'Could not save file. Check server write permissions.']);
    exit;
}

// ── 3. Read print specs from POST ────────────────────────────
$allowedSizes = ['A0', 'A1', 'A2', 'A3', 'A4', 'A5'];

$linkedOrderId = trim($_POST['preorder_id'] ?? '') ?: null;

$paperSize = in_array($_POST['paper_size'] ?? '', $allowedSizes)
               ? $_POST['paper_size'] : 'A4';
$paperType = trim($_POST['paper_type'] ?? '80gsm Standard');
$copies    = max(1, min(99, (int)($_POST['copies'] ?? 1)));

$bindingMap  = [
    'None'   => 'NONE',   'none'   => 'NONE',
    'Staple' => 'STAPLE', 'staple' => 'STAPLE',
    'Spiral' => 'SPIRAL', 'spiral' => 'SPIRAL',
];
$bindingRaw  = trim($_POST['binding'] ?? 'None');
$bindingType = $bindingMap[$bindingRaw] ?? 'NONE';

$fileType = match($mime) {
    'application/pdf' => 'PDF',
    'image/jpeg'      => 'JPG',
    'image/png'       => 'PNG',
    default           => strtoupper($ext),
};

$printMode = trim($_POST['print_mode'] ?? 'MIXED');
if (!in_array($printMode, ['BW', 'COLOR', 'MIXED'])) $printMode = 'MIXED';

// ── Helper: count pages in a PDF without any library ─────────
function countPdfPages(string $path): int {
    $content = @file_get_contents($path);
    if (!$content) return 1;
    preg_match_all('/\/Type\s*\/Page[^s]/', $content, $matches);
    $count = count($matches[0]);
    return max(1, $count);
}

// ── 4 + 5. Colour detection — branch on print mode ───────────
$colorPages  = 0;
$bwPages     = 0;
$totalPages  = 1;
$pageDetails = [];
$parseOk     = true;
$aiRaw       = '';

if ($printMode === 'BW') {

    $totalPages  = ($fileType === 'PDF') ? countPdfPages($savePath) : 1;
    $bwPages     = $totalPages;
    $colorPages  = 0;
    $pageDetails = array_map(
        fn($i) => ['page' => $i + 1, 'is_color' => false, 'confidence' => 'customer_selected'],
        range(0, $totalPages - 1)
    );

} elseif ($printMode === 'COLOR') {

    $totalPages  = ($fileType === 'PDF') ? countPdfPages($savePath) : 1;
    $colorPages  = $totalPages;
    $bwPages     = 0;
    $pageDetails = array_map(
        fn($i) => ['page' => $i + 1, 'is_color' => true, 'confidence' => 'customer_selected'],
        range(0, $totalPages - 1)
    );

} else {

    $parseOk = false;
    $parsed  = null;

    $base64 = base64_encode(file_get_contents($savePath));
    $isPdf  = ($mime === 'application/pdf');

    $messages = [[
        'role'    => 'user',
        'content' => [
            [
                'type'   => $isPdf ? 'document' : 'image',
                'source' => [
                    'type'       => 'base64',
                    'media_type' => $mime,
                    'data'       => $base64,
                ],
            ],
            [
                'type' => 'text',
                'text' =>
'You are a print shop system. Your ONLY job is to output a JSON object.

Analyse every page of the attached document and determine if each page is colour or black-and-white.

RULES:
- is_color = true  if the page has ANY colour ink (photos, coloured text, charts, logos, highlights)
- is_color = false if the page uses ONLY black, white, or grey
- confidence = "high", "medium", or "low"
- For a single image, total_pages = 1

OUTPUT FORMAT — output ONLY this JSON, nothing else, no explanation, no markdown:
{"total_pages":2,"pages":[{"page":1,"is_color":true,"confidence":"high"},{"page":2,"is_color":false,"confidence":"high"}]}'
            ],
        ],
    ]];

    $aiRaw = callClaudeWithMessages($messages, 4096);

    if (!empty($aiRaw)) {
        $clean   = preg_replace('/```(?:json)?\s*([\s\S]*?)```/', '$1', trim($aiRaw));
        $clean   = trim($clean);
        $attempt = json_decode($clean, true);
        if (is_array($attempt) && isset($attempt['pages'])) $parsed = $attempt;

        if (!$parsed) {
            if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $clean, $m)) {
                $attempt = json_decode($m[0], true);
                if (is_array($attempt) && isset($attempt['pages'])) $parsed = $attempt;
            }
        }
        if (!$parsed) {
            $start = strpos($clean, '{'); $end = strrpos($clean, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $attempt = json_decode(substr($clean, $start, $end - $start + 1), true);
                if (is_array($attempt) && isset($attempt['pages'])) $parsed = $attempt;
            }
        }
    }

    if ($parsed && isset($parsed['pages']) && is_array($parsed['pages'])) {
        $parseOk     = true;
        $pageDetails = $parsed['pages'];
        $totalPages  = max(1, (int)($parsed['total_pages'] ?? count($pageDetails)));
        foreach ($pageDetails as $p) {
            if (!empty($p['is_color'])) $colorPages++;
            else                         $bwPages++;
        }
    } else {
        error_log("upload_print.php: AI parse failed. Raw: " . substr($aiRaw ?? '', 0, 500));
        $totalPages  = 1;
        $bwPages     = 1;
        $pageDetails = [['page' => 1, 'is_color' => false, 'confidence' => 'low']];
    }
}

// ── 6. Calculate price and duration ──────────────────────────
$breakdown    = getPriceBreakdown($colorPages, $bwPages, $paperSize, $bindingType, $copies);
$price        = $breakdown['total'];
$durationMins = estimateDuration($colorPages, $bwPages, $copies);
$printType    = $colorPages > 0 ? 'COLOR' : 'BLACK_WHITE';

// ── 7. Store pending data in session (NO DB write yet) ────────
//  confirm_print.php will read this and do the actual INSERT
//  when the customer clicks "Confirm & Submit".
if (!isset($_SESSION['pending_prints'])) {
    $_SESSION['pending_prints'] = [];
}

$analysisJson = json_encode([
    'pages'      => $pageDetails,
    'parse_ok'   => $parseOk,
    'print_mode' => $printMode,
    'raw'        => substr($aiRaw, 0, 1000),
]);

$_SESSION['pending_prints'][$fileId] = [
    'user_id'        => $userId,
    'file_id'        => $fileId,
    'file_name'      => $file['name'],
    'file_path'      => $savePath,
    'file_type'      => $fileType,
    'linked_order_id'=> $linkedOrderId,   // pre-order to link, if any
    'paper_size'     => $paperSize,
    'paper_type'     => $paperType,
    'binding_type'   => $bindingType,
    'copies'         => $copies,
    'print_type'     => $printType,
    'print_mode'     => $printMode,
    'total_pages'    => $totalPages,
    'color_pages'    => $colorPages,
    'bw_pages'       => $bwPages,
    'estimated_price'=> $price,
    'duration_min'   => $durationMins,
    'analysis_json'  => $analysisJson,
    'created_at'     => time(),           // for expiry check in confirm_print.php
];

// ── 8. Return analysis result to client ──────────────────────
echo json_encode([
    'success'      => true,
    'file_id'      => $fileId,
    'filename'     => htmlspecialchars($file['name']),
    'total_pages'  => $totalPages,
    'color_pages'  => $colorPages,
    'bw_pages'     => $bwPages,
    'price'        => $price,
    'breakdown'    => $breakdown,
    'duration_min' => $durationMins,
    'page_details' => $pageDetails,
    'ai_parse_ok'  => $parseOk,
    'print_mode'   => $printMode,
]);