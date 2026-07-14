<?php
// ============================================================
//  pos_customer_search.php — POS: customer typeahead
//  GET ?q=name_or_phone
//  Returns JSON { success, customers: [...] }
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role(['STAFF', 'ADMIN']);
require_once 'db.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    echo json_encode(['success' => false, 'error' => 'Type at least 2 characters.']);
    exit;
}

$like = "%$q%";
$stmt = $conn->prepare(
    "SELECT user_id, name, phone_number, email, loyalty_points
     FROM users
     WHERE user_role = 'CUSTOMER'
       AND account_status = 'ACTIVE'
       AND (name LIKE ? OR phone_number LIKE ? OR email LIKE ?)
     ORDER BY name ASC
     LIMIT 8"
);
$stmt->bind_param('sss', $like, $like, $like);
$stmt->execute();
$customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['success' => true, 'customers' => $customers]);