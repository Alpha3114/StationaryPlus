<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role(['STAFF', 'ADMIN']);

include 'db.php';
require_once 'audit.php';
header('Content-Type: application/json');

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$product_id       = trim($_POST['product_id'] ?? '');
$product_name     = trim($_POST['product_name'] ?? '');
$category         = trim($_POST['category'] ?? '');
$price            = floatval($_POST['price'] ?? 0);
$discount_percent = max(0, min(100, floatval($_POST['discount_percent'] ?? 0)));
$product_status   = trim($_POST['product_status'] ?? 'Inactive');
$now              = date('Y-m-d H:i:s');

if ($product_name === '') {
    echo json_encode(['success' => false, 'error' => 'Product name is required']);
    exit;
}

/**
 * Generates a fresh product_id in the PRxxx format, one higher than the
 * current max. product_id is NOT an auto-increment column in this schema
 * (it's an application-generated varchar, same pattern as every other ID
 * in the system) — leaving it blank previously caused an INSERT that
 * omitted the primary key entirely, which either failed outright or
 * silently wrote an empty string depending on SQL mode.
 */
function generateProductId(mysqli $conn): string {
    $res = $conn->query(
        "SELECT product_id FROM products
         WHERE product_id REGEXP '^PR[0-9]+$'
         ORDER BY CAST(SUBSTRING(product_id, 3) AS UNSIGNED) DESC
         LIMIT 1"
    );
    $maxNum = 0;
    if ($res && ($row = $res->fetch_assoc())) {
        $maxNum = (int)substr($row['product_id'], 2);
    }
    return 'PR' . str_pad($maxNum + 1, 3, '0', STR_PAD_LEFT);
}

/**
 * Validates and saves an optional uploaded product image, following the
 * same pattern as c_payment.php's proof-of-payment upload. Returns the new
 * relative path, or null if no file was submitted (not an error — the
 * caller then leaves the existing image_path untouched on update). Throws
 * on an invalid file so the caller can report it as a save failure.
 */
function handleProductImageUpload(): ?string {
    if (empty($_FILES['product_image']) || $_FILES['product_image']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES['product_image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Image upload failed. Please try again.');
    }

    // Extension is derived from the validated MIME type, never from the
    // client-supplied filename — otherwise a file with real image bytes but
    // a ".php" name (a "polyglot") would pass the MIME check and be saved
    // as a web-executable script.
    $allowedExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime    = mime_content_type($_FILES['product_image']['tmp_name']);
    $size    = $_FILES['product_image']['size'];

    if (!isset($allowedExt[$mime])) {
        throw new Exception('Product image must be JPG, PNG, or WEBP.');
    }
    if ($size > 5 * 1024 * 1024) {
        throw new Exception('Product image must be under 5MB.');
    }

    $uploadDir = 'uploads/products/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $ext      = $allowedExt[$mime];
    $filename = 'prod_' . uniqid() . '.' . $ext;
    move_uploaded_file($_FILES['product_image']['tmp_name'], $uploadDir . $filename);
    return $uploadDir . $filename;
}

/**
 * Provisions a zero-stock inventory row for a brand-new product at every
 * currently-ACTIVE branch, so it's immediately visible to branch-scoped
 * queries (POS lookup, customer catalog, collaborative filtering) system-
 * wide. Getting real stock in then goes through the existing restock-
 * request flow per branch, same as any other product — this just closes
 * the gap where a new product previously had NO path into inventory at all.
 */
function provisionInventoryAtActiveBranches(mysqli $conn, string $productId): void {
    $branches = $conn->query("SELECT branch_id FROM branches WHERE status = 'ACTIVE'");
    if (!$branches) return;

    $stmt = $conn->prepare(
        "INSERT INTO inventory (inventory_id, branch_id, product_id, stock_quantity, reserved_quantity, minimum_level)
         VALUES (?, ?, ?, 0, 0, 10)"
    );
    while ($b = $branches->fetch_assoc()) {
        $invId = 'INV-' . $productId . '-' . $b['branch_id'];
        $stmt->bind_param('sss', $invId, $b['branch_id'], $productId);
        $stmt->execute();
    }
    $stmt->close();
}

$imagePath      = null;
$transactionOpen = false;

try {
    $imagePath = handleProductImageUpload(); // null = no new file chosen, leave existing image untouched on update

    $conn->begin_transaction();
    $transactionOpen = true;

    if ($product_id !== '') {
        $check = $conn->prepare("SELECT image_path, discount_percent FROM products WHERE product_id = ?");
        $check->bind_param('s', $product_id);
        $check->execute();
        $existingRow = $check->get_result()->fetch_assoc();
        $check->close();

        if ($existingRow) {
            $oldImagePath = $existingRow['image_path'] ?? null;
            $oldDiscount  = (float)($existingRow['discount_percent'] ?? 0);

            if ($imagePath !== null) {
                $stmt = $conn->prepare("UPDATE products SET product_name = ?, category = ?, price = ?, discount_percent = ?, product_status = ?, last_updated = ?, image_path = ? WHERE product_id = ?");
                $stmt->bind_param('ssddssss', $product_name, $category, $price, $discount_percent, $product_status, $now, $imagePath, $product_id);
            } else {
                $stmt = $conn->prepare("UPDATE products SET product_name = ?, category = ?, price = ?, discount_percent = ?, product_status = ?, last_updated = ? WHERE product_id = ?");
                $stmt->bind_param('ssddsss', $product_name, $category, $price, $discount_percent, $product_status, $now, $product_id);
            }
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            log_audit($conn, 'PRODUCT_UPDATE', 'product', $product_id, "Updated \"$product_name\" (price RM $price, status $product_status)");
            if (abs($oldDiscount - $discount_percent) > 0.001) {
                log_audit($conn, 'PRODUCT_DISCOUNT_CHANGE', 'product', $product_id, "Discount $oldDiscount% -> $discount_percent%");
            }

            // Clean up the replaced image only after a successful commit
            if ($imagePath !== null && $oldImagePath && $oldImagePath !== $imagePath && file_exists($oldImagePath)) {
                @unlink($oldImagePath);
            }

            echo json_encode(['success' => true, 'action' => 'updated', 'product_id' => $product_id]);
            exit;
        } else {
            $stmt = $conn->prepare("INSERT INTO products (product_id, product_name, category, price, discount_percent, product_status, last_updated, image_path) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param('sssddsss', $product_id, $product_name, $category, $price, $discount_percent, $product_status, $now, $imagePath);
            $stmt->execute();
            $stmt->close();

            provisionInventoryAtActiveBranches($conn, $product_id);

            $conn->commit();

            log_audit($conn, 'PRODUCT_CREATE', 'product', $product_id, "Created \"$product_name\" (price RM $price, status $product_status)");

            echo json_encode(['success' => true, 'action' => 'inserted', 'product_id' => $product_id]);
            exit;
        }
    } else {
        // Blank ID = new product, auto-generate a real one (was previously
        // omitting product_id from the INSERT entirely — see function docblock)
        $product_id = generateProductId($conn);

        $stmt = $conn->prepare("INSERT INTO products (product_id, product_name, category, price, discount_percent, product_status, last_updated, image_path) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param('sssddsss', $product_id, $product_name, $category, $price, $discount_percent, $product_status, $now, $imagePath);
        $stmt->execute();
        $stmt->close();

        provisionInventoryAtActiveBranches($conn, $product_id);

        $conn->commit();

        log_audit($conn, 'PRODUCT_CREATE', 'product', $product_id, "Created \"$product_name\" (price RM $price, status $product_status)");

        echo json_encode(['success' => true, 'action' => 'inserted', 'product_id' => $product_id]);
        exit;
    }
} catch (Exception $e) {
    if ($transactionOpen) $conn->rollback();
    if ($imagePath !== null) @unlink($imagePath); // avoid an orphaned upload when the DB write failed
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}