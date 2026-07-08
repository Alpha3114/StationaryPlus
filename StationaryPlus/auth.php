<?php
// ============================================================
//  auth.php — Session guard
//  Usage: require_once 'auth.php';               (any logged-in user)
//         require_once 'auth.php'; require_role('ADMIN');
//         require_once 'auth.php'; require_role(['ADMIN','STAFF']);
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ----- helpers -----------------------------------------------

/**
 * Check if anyone is logged in.
 * Redirects to login.php if not.
 */
function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Check that the logged-in user has the required role(s).
 * Call AFTER require_login().
 *
 * @param string|string[] $roles  e.g. 'ADMIN' or ['ADMIN','STAFF']
 */
function require_role(string|array $roles): void {
    require_login();

    $roles = (array) $roles;

    if (!in_array($_SESSION['user_role'], $roles, true)) {
        // Wrong role — send them to their own dashboard
        redirect_to_dashboard();
    }
}

/**
 * Whether the logged-in staff member's assigned branch is still ACTIVE.
 * Returns true for ADMIN / unassigned STAFF (no branch_id in session) —
 * this guard only concerns staff tied to a specific branch that may have
 * since gone INACTIVE/RENOVATION out from under them.
 */
function staff_branch_is_active(mysqli $conn): bool {
    $branchId = $_SESSION['branch_id'] ?? null;
    if (!$branchId) {
        return true;
    }

    $stmt = $conn->prepare("SELECT status FROM branches WHERE branch_id = ? LIMIT 1");
    $stmt->bind_param('s', $branchId);
    $stmt->execute();
    $status = $stmt->get_result()->fetch_assoc()['status'] ?? null;
    $stmt->close();

    return $status === 'ACTIVE';
}

/**
 * Redirect user to their role-specific dashboard.
 */
function redirect_to_dashboard(): void {
    $map = [
        'ADMIN'    => 'a_dashboard.php',
        'STAFF'    => 's_dashboard.php',
        'CUSTOMER' => 'c_dashboard.php',
    ];

    $role = $_SESSION['user_role'] ?? '';
    $dest = $map[$role] ?? 'login.php';

    header("Location: $dest");
    exit;
}

/**
 * Destroy the session and log the user out.
 */
function logout(): void {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

// ----- auto-guard --------------------------------------------
// Including this file always checks login.
// Add require_role() on the next line of each page for role checks.
require_login();