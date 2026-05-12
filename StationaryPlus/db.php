<?php
// Database connection settings - update with your credentials
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'stationaryplus';

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    error_log('DB connect error: ' . $conn->connect_error);
    $conn = null;
}
?>
