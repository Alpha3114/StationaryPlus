<?php
// ============================================================
//  smart_sidebar.php — Role-aware sidebar router
//  Include this on ALL staff pages instead of s_sidebar.php
//  Admin visiting a staff page → sees a_sidebar.php (with ops active)
//  Staff visiting a staff page → sees s_sidebar.php as normal
// ============================================================
$_role = $_SESSION['user_role'] ?? 'STAFF';
if ($_role === 'ADMIN') {
    include __DIR__ . '/a_sidebar.php';
} else {
    include __DIR__ . '/s_sidebar.php';
}