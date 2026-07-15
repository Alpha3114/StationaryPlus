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

$banner_id   = trim($_POST['banner_id'] ?? '');
$title       = trim($_POST['title'] ?? '');
$link_url    = trim($_POST['link_url'] ?? '') ?: null;
$target_page = trim($_POST['target_page'] ?? 'ALL');
$sort_order  = (int)($_POST['sort_order'] ?? 0);
$is_active   = isset($_POST['is_active']) && $_POST['is_active'] === '1' ? 1 : 0;
$starts_at   = trim($_POST['starts_at'] ?? '') ?: null;
$ends_at     = trim($_POST['ends_at'] ?? '') ?: null;
$now         = date('Y-m-d H:i:s');
$userId      = $_SESSION['user_id'];

$validPages = ['ALL', 'INDEX', 'LOGIN', 'REGISTER', 'C_DASHBOARD'];
if (!in_array($target_page, $validPages)) $target_page = 'ALL';

if ($title === '') {
    echo json_encode(['success' => false, 'error' => 'Banner title is required']);
    exit;
}

// Only allow a relative page (no scheme) or an http(s) absolute URL — blocks
// javascript:/data:/vbscript: URIs, which would run as stored XSS on click
// since banners render as an <a href> to every visitor, including logged-out
// ones on the homepage/login/register pages.
if ($link_url !== null
    && preg_match('#^[a-z][a-z0-9+.\-]*:#i', $link_url)
    && !preg_match('#^https?://#i', $link_url)) {
    echo json_encode(['success' => false, 'error' => 'Link URL must be a relative page (e.g. c_viewproducts.php) or start with http:// or https://']);
    exit;
}

function generateBannerId(): string {
    return 'BAN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

/**
 * Validates and saves an optional uploaded banner image, following the same
 * pattern as save_product.php's handleProductImageUpload(). Returns the new
 * relative path, or null if no file was submitted (caller then leaves the
 * existing image_path untouched on update).
 */
function handleBannerImageUpload(): ?string {
    if (empty($_FILES['banner_image']) || $_FILES['banner_image']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES['banner_image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Image upload failed. Please try again.');
    }

    // Extension is derived from the validated MIME type, never from the
    // client-supplied filename — otherwise a file with real image bytes but
    // a ".php" name (a "polyglot") would pass the MIME check and be saved
    // as a web-executable script.
    $allowedExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime    = mime_content_type($_FILES['banner_image']['tmp_name']);
    $size    = $_FILES['banner_image']['size'];

    if (!isset($allowedExt[$mime])) {
        throw new Exception('Banner image must be JPG, PNG, or WEBP.');
    }
    if ($size > 5 * 1024 * 1024) {
        throw new Exception('Banner image must be under 5MB.');
    }

    $uploadDir = 'uploads/banners/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $ext      = $allowedExt[$mime];
    $filename = 'banner_' . uniqid() . '.' . $ext;
    move_uploaded_file($_FILES['banner_image']['tmp_name'], $uploadDir . $filename);
    return $uploadDir . $filename;
}

$imagePath = null;

try {
    $imagePath = handleBannerImageUpload();

    if ($banner_id !== '') {
        $check = $conn->prepare("SELECT image_path FROM banners WHERE banner_id = ?");
        $check->bind_param('s', $banner_id);
        $check->execute();
        $existingRow = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$existingRow) {
            echo json_encode(['success' => false, 'error' => 'Banner not found']);
            exit;
        }

        $oldImagePath = $existingRow['image_path'] ?? null;

        if ($imagePath !== null) {
            $stmt = $conn->prepare(
                "UPDATE banners SET title=?, image_path=?, link_url=?, target_page=?, sort_order=?, is_active=?, starts_at=?, ends_at=?, last_updated=?
                 WHERE banner_id = ?"
            );
            $stmt->bind_param('ssssiissss', $title, $imagePath, $link_url, $target_page, $sort_order, $is_active, $starts_at, $ends_at, $now, $banner_id);
        } else {
            $stmt = $conn->prepare(
                "UPDATE banners SET title=?, link_url=?, target_page=?, sort_order=?, is_active=?, starts_at=?, ends_at=?, last_updated=?
                 WHERE banner_id = ?"
            );
            $stmt->bind_param('sssiissss', $title, $link_url, $target_page, $sort_order, $is_active, $starts_at, $ends_at, $now, $banner_id);
        }
        $stmt->execute();
        $stmt->close();

        log_audit($conn, 'BANNER_UPDATE', 'banner', $banner_id, "Updated \"$title\" (page $target_page, active=$is_active)");

        if ($imagePath !== null && $oldImagePath && $oldImagePath !== $imagePath && file_exists($oldImagePath)) {
            @unlink($oldImagePath);
        }

        echo json_encode(['success' => true, 'action' => 'updated', 'banner_id' => $banner_id]);
        exit;
    } else {
        if ($imagePath === null) {
            echo json_encode(['success' => false, 'error' => 'Banner image is required for a new banner']);
            exit;
        }

        $banner_id = generateBannerId();
        $stmt = $conn->prepare(
            "INSERT INTO banners
                (banner_id, title, image_path, link_url, target_page, sort_order, is_active, starts_at, ends_at, created_by, created_at, last_updated)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->bind_param(
            'sssssiisssss',
            $banner_id, $title, $imagePath, $link_url, $target_page, $sort_order, $is_active, $starts_at, $ends_at, $userId, $now, $now
        );
        $stmt->execute();
        $stmt->close();

        log_audit($conn, 'BANNER_CREATE', 'banner', $banner_id, "Created \"$title\" (page $target_page, active=$is_active)");

        echo json_encode(['success' => true, 'action' => 'inserted', 'banner_id' => $banner_id]);
        exit;
    }
} catch (Exception $e) {
    if ($imagePath !== null) @unlink($imagePath);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
