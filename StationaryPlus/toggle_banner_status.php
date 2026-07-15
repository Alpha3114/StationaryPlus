<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role('ADMIN');

include 'db.php';
require_once 'audit.php';
header('Content-Type: application/json');

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$banner_id = trim($_POST['banner_id'] ?? '');

if ($banner_id === '') {
    echo json_encode(['success' => false, 'error' => 'Missing banner ID']);
    exit;
}

$stmt = $conn->prepare("SELECT is_active FROM banners WHERE banner_id = ? LIMIT 1");
$stmt->bind_param('s', $banner_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$existing) {
    echo json_encode(['success' => false, 'error' => 'Banner not found']);
    exit;
}

$newStatus = (int)$existing['is_active'] === 1 ? 0 : 1;
$now = date('Y-m-d H:i:s');

$stmt = $conn->prepare("UPDATE banners SET is_active = ?, last_updated = ? WHERE banner_id = ?");
$stmt->bind_param('iss', $newStatus, $now, $banner_id);
$stmt->execute();
$stmt->close();

log_audit($conn, 'BANNER_STATUS_TOGGLE', 'banner', $banner_id, "Status changed to " . ($newStatus ? 'ACTIVE' : 'INACTIVE'));

echo json_encode(['success' => true, 'new_status' => $newStatus]);
