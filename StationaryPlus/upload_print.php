<?php
// ============================================================
//  upload_print.php — Print file upload + AI colour detection
//
//  Expects multipart/form-data POST:
//    print_file   FILE   — PDF, JPG, or PNG (max 20 MB)
//    preorder_id  string — optional, links to a preorder
//    paper_size   string — A0 | A1 | A2 | A3 | A4 | A5
//    paper_type   string — free text
//    binding      string — None | Staple | Spiral
//    copies       int    — 1-99
//
//  Returns JSON: { success, file_id, total_pages, color_pages,
//                  bw_pages, price, breakdown, duration_min,
//                  page_details, ai_parse_ok }
//
//  DB columns used (print_files):
//    file_id, preorder_id, file_name, file_path, file_type,
//    file_status, print_type, paper_size, paper_type,
//    binding_type, copies, total_pages,
//    color_pages*, bw_pages*, estimated_price*, ai_analysis*
//    (* added via ALTER TABLE — see README)
// ============================================================
 
if (session_status() === PHP_SESSION_NONE) session_start();
 
require_once 'auth.php';
require_role('CUSTOMER');
require_once 'db.php';
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
$allowedSizes = ['A0', 'A1', 'A2', 'A3', 'A4', 'A5'];   // matches DB enum
 
$preorderId = trim($_POST['preorder_id'] ?? '') ?: null;
$paperSize  = in_array($_POST['paper_size'] ?? '', $allowedSizes)
                ? $_POST['paper_size'] : 'A4';
$paperType  = trim($_POST['paper_type'] ?? '80gsm Standard');
$copies     = max(1, min(99, (int)($_POST['copies'] ?? 1)));
 
// Map UI labels → DB enum values  (NONE | STAPLE | SPIRAL)
$bindingMap  = [
    'None'   => 'NONE',   'none'   => 'NONE',
    'Staple' => 'STAPLE', 'staple' => 'STAPLE',
    'Spiral' => 'SPIRAL', 'spiral' => 'SPIRAL',
];
$bindingRaw  = trim($_POST['binding'] ?? 'None');
$bindingType = $bindingMap[$bindingRaw] ?? 'NONE';   // safe fallback
 
// Derive file_type from mime for the file_type column
$fileType = match($mime) {
    'application/pdf' => 'PDF',
    'image/jpeg'      => 'JPG',
    'image/png'       => 'PNG',
    default           => strtoupper($ext),
};
 
// ── 4. Build Claude vision request ───────────────────────────
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
'You are a print shop system. Analyse every page of this document.
 
For each page decide:
- is_color: true  → page contains ANY colour ink (photos, coloured text, graphics, logos, highlights, coloured backgrounds)
- is_color: false → page uses ONLY black, white, or shades of grey
 
Reply with ONLY this JSON — no explanation, no markdown fences:
{"total_pages":<integer>,"pages":[{"page":1,"is_color":true,"confidence":"high"},{"page":2,"is_color":false,"confidence":"high"}]}
 
confidence must be "high", "medium", or "low".
For a single image file set total_pages to 1.'
        ],
    ],
]];
 
$aiRaw = callClaudeWithMessages($messages, 1200);
 
// ── 5. Parse the AI response ──────────────────────────────────
// Strip any accidental markdown fences then extract the JSON object
$clean = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($aiRaw));
preg_match('/\{.*\}/s', $clean, $m);
$parsed = isset($m[0]) ? json_decode($m[0], true) : null;
 
$colorPages  = 0;
$bwPages     = 0;
$totalPages  = 1;
$pageDetails = [];
$parseOk     = false;
 
if ($parsed && isset($parsed['pages']) && is_array($parsed['pages'])) {
    $parseOk     = true;
    $pageDetails = $parsed['pages'];
    $totalPages  = max(1, (int)($parsed['total_pages'] ?? count($pageDetails)));
 
    foreach ($pageDetails as $p) {
        if (!empty($p['is_color'])) $colorPages++;
        else                         $bwPages++;
    }
} else {
    // Safe fallback — log for debugging
    error_log("upload_print.php: Claude parse failed. Raw: " . substr($aiRaw, 0, 300));
    $totalPages  = 1;
    $bwPages     = 1;
    $pageDetails = [['page' => 1, 'is_color' => false, 'confidence' => 'low']];
}
 
// ── 6. Calculate price and duration ──────────────────────────
// Pass the normalised enum value; ai_helper maps NONE/STAPLE/SPIRAL to prices
$breakdown    = getPriceBreakdown($colorPages, $bwPages, $paperSize, $bindingType, $copies);
$price        = $breakdown['total'];
$durationMins = estimateDuration($colorPages, $bwPages, $copies);
 
// DB enum: 'COLOR' if any colour page exists, otherwise 'BLACK_WHITE'
$printType = $colorPages > 0 ? 'COLOR' : 'BLACK_WHITE';
 
// ── 7. Persist to DB ─────────────────────────────────────────
$stmt = $conn->prepare(
    "INSERT INTO print_files
        (file_id, preorder_id, user_id, file_name, file_path, file_type,
         print_type, paper_size, paper_type, binding_type, copies,
         total_pages, color_pages, bw_pages,
         estimated_price, ai_analysis, file_status)
     VALUES (?,?,?,?,?,?, ?,?,?,?,?, ?,?,?,?,?, 'RECEIVED')"
);
 
$analysisJson = json_encode([
    'pages'        => $pageDetails,
    'parse_ok'     => $parseOk,
    'raw_response' => substr($aiRaw, 0, 2000),
]);
 
// s×6, s×4 i×1, i×3, d×1, s×1 = 16 params
$stmt->bind_param(
    'ssssss ssss i iiids',
    $fileId, $preorderId, $userId, $file['name'], $savePath, $fileType,
    $printType, $paperSize, $paperType, $bindingType, $copies,
    $totalPages, $colorPages, $bwPages,
    $price, $analysisJson
);
$stmt->execute();
$stmt->close();
 
// ── 8. Return result to client ────────────────────────────────
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
]);