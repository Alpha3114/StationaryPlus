<?php
// ============================================================
//  audit.php — Admin/staff action audit trail
//  Usage: require_once 'audit.php'; (after db.php + auth.php)
//         log_audit($conn, 'PRODUCT_UPDATE', 'product', $productId, 'price 10.00 -> 12.00');
// ============================================================

/**
 * Records one audit-log entry. Best-effort — a logging failure must never
 * block the action it's describing, so any DB error here is swallowed.
 */
function log_audit(mysqli $conn, string $action, string $entityType, ?string $entityId = null, ?string $details = null): void {
    try {
        $logId     = 'AUD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
        $actorId   = $_SESSION['user_id']   ?? null;
        $actorName = $_SESSION['user_name'] ?? null;
        $actorRole = $_SESSION['user_role'] ?? null;
        $now       = date('Y-m-d H:i:s');

        $stmt = $conn->prepare(
            "INSERT INTO audit_log
                (log_id, actor_id, actor_name, actor_role, action, entity_type, entity_id, details, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'sssssssss',
            $logId, $actorId, $actorName, $actorRole, $action, $entityType, $entityId, $details, $now
        );
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log('log_audit failed: ' . $e->getMessage());
    }
}
