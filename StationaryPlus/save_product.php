<?php
include 'db.php';
header('Content-Type: application/json');

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$product_id     = trim($_POST['product_id'] ?? '');
$product_name   = trim($_POST['product_name'] ?? '');
$category       = trim($_POST['category'] ?? '');
$price          = floatval($_POST['price'] ?? 0);
$product_status = trim($_POST['product_status'] ?? 'Inactive');
$now            = date('Y-m-d H:i:s');

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

try {
    if ($product_id !== '') {
        $check = $conn->prepare("SELECT 1 FROM products WHERE product_id = ?");
        $check->bind_param('s', $product_id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $check->close();
            $stmt = $conn->prepare("UPDATE products SET product_name = ?, category = ?, price = ?, product_status = ?, last_updated = ? WHERE product_id = ?");
            $stmt->bind_param('ssdsss', $product_name, $category, $price, $product_status, $now, $product_id);
            $stmt->execute();
            $stmt->close();

            echo json_encode(['success' => true, 'action' => 'updated', 'product_id' => $product_id]);
            exit;
        } else {
            $check->close();
            $stmt = $conn->prepare("INSERT INTO products (product_id, product_name, category, price, product_status, last_updated) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param('sssdss', $product_id, $product_name, $category, $price, $product_status, $now);
            $stmt->execute();
            $stmt->close();

            provisionInventoryAtActiveBranches($conn, $product_id);

            echo json_encode(['success' => true, 'action' => 'inserted', 'product_id' => $product_id]);
            exit;
        }
    } else {
        // Blank ID = new product, auto-generate a real one (was previously
        // omitting product_id from the INSERT entirely — see function docblock)
        $product_id = generateProductId($conn);

        $stmt = $conn->prepare("INSERT INTO products (product_id, product_name, category, price, product_status, last_updated) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param('sssdss', $product_id, $product_name, $category, $price, $product_status, $now);
        $stmt->execute();
        $stmt->close();

        provisionInventoryAtActiveBranches($conn, $product_id);

        echo json_encode(['success' => true, 'action' => 'inserted', 'product_id' => $product_id]);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}