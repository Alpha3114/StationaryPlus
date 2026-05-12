<?php
include 'db.php';
header('Content-Type: application/json');

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$product_id = trim($_POST['product_id'] ?? '');
$product_name = trim($_POST['product_name'] ?? '');
$category = trim($_POST['category'] ?? '');
$price = floatval($_POST['price'] ?? 0);
$product_status = trim($_POST['product_status'] ?? 'Inactive');
$now = date('Y-m-d H:i:s');

if ($product_name === '') {
    echo json_encode(['success' => false, 'error' => 'Product name is required']);
    exit;
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

            echo json_encode(['success' => true, 'action' => 'inserted', 'product_id' => $product_id]);
            exit;
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO products (product_name, category, price, product_status, last_updated) VALUES (?,?,?,?,?)");
        $stmt->bind_param('ssdss', $product_name, $category, $price, $product_status, $now);
        $stmt->execute();
        $newId = $conn->insert_id;
        $stmt->close();

        echo json_encode(['success' => true, 'action' => 'inserted', 'insert_id' => $newId]);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>
