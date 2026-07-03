<?php
date_default_timezone_set('Asia/Kuala_Lumpur');
$DB_HOST = 'localhost';
$DB_USER = 'u846121306_splususer';   // your full Hostinger username
$DB_PASS = 'Yj|Ewe4/dV';     // the password you set in Step 7
$DB_NAME = 'u846121306_stationaryplus'; // your full Hostinger database name

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    error_log('DB connect error: ' . $conn->connect_error);
    die("Database connection error. Please try again later.");
}
?>