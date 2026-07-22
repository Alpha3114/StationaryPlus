<?php
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once __DIR__ . '/env.php';

$isProd = stripos($_SERVER['DOCUMENT_ROOT'] ?? '', 'xampp') === false;

$DB_HOST = $isProd ? getenv('PROD_DB_HOST') : getenv('DB_HOST');
$DB_USER = $isProd ? getenv('PROD_DB_USER') : getenv('DB_USER');
$DB_PASS = $isProd ? getenv('PROD_DB_PASS') : getenv('DB_PASS');
$DB_NAME = $isProd ? getenv('PROD_DB_NAME') : getenv('DB_NAME');

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    error_log('DB connect error: ' . $conn->connect_error);
    die("Database connection error. Please try again later.");
}
$conn->set_charset('utf8mb4');
