<?php
// ============================================================
//  run_forecast.php — Runs the Python forecasting script
//  Called via AJAX from a_report.php
//  POST: (none required)
//  Returns JSON: { success, predictions, historical, model, generated_at }
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../auth.php';
require_role('ADMIN');

header('Content-Type: application/json');

// ── Locate Python executable ──────────────────────────────────
// Adjust PYTHON_PATH in config.php if needed
require_once '../config.php';

$pythonPath  = defined('PYTHON_PATH') ? PYTHON_PATH : 'python';
$scriptPath  = __DIR__ . DIRECTORY_SEPARATOR . 'forecast.py';

if (!file_exists($scriptPath)) {
    echo json_encode(['success' => false, 'error' => 'forecast.py not found at: ' . $scriptPath]);
    exit;
}

// ── Run the script ────────────────────────────────────────────
// Set working directory to forecasting/ so db_config.py is found
$cwd    = __DIR__;
$cmd    = escapeshellcmd("\"$pythonPath\" \"$scriptPath\"");

$output     = [];
$returnCode = 0;

// chdir so Python finds db_config.py in the same folder
$prevDir = getcwd();
chdir($cwd);
exec($cmd . ' 2>&1', $output, $returnCode);
chdir($prevDir);

$raw = implode("\n", $output);

// ── Parse output ──────────────────────────────────────────────
// Find the JSON line (last line that starts with {)
$jsonLine = '';
foreach (array_reverse($output) as $line) {
    $trimmed = trim($line);
    if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
        $jsonLine = $trimmed;
        break;
    }
}

if ($jsonLine === '') {
    echo json_encode([
        'success' => false,
        'error'   => 'Python script produced no output. '
                   . 'Make sure Python is installed and in PATH. '
                   . 'Raw output: ' . substr($raw, 0, 300),
    ]);
    exit;
}

$data = json_decode($jsonLine, true);

if (!is_array($data)) {
    echo json_encode([
        'success' => false,
        'error'   => 'Could not parse Python output as JSON. Raw: ' . substr($raw, 0, 300),
    ]);
    exit;
}

echo $jsonLine;