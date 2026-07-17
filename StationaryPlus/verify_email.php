<?php
// ============================================================
//  verify_email.php — Activates a customer account once they
//  click the verification link sent by Registration.php
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'db.php';

$token   = trim($_GET['token'] ?? '');
$status  = 'error'; // 'success' | 'error' | 'expired'
$message = '';

if (!$token) {
    $message = 'Invalid or missing verification link.';
} else {
    $stmt = $conn->prepare(
        "SELECT ev.id, ev.user_id, ev.expires_at, ev.used, u.name, u.email, u.account_status
         FROM email_verifications ev
         JOIN users u ON ev.user_id = u.user_id
         WHERE ev.token = ? LIMIT 1"
    );
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $message = 'This verification link is invalid.';

    } elseif ($row['account_status'] === 'ACTIVE') {
        // Already verified (e.g. link clicked twice) — treat as success
        $status  = 'success';
        $message = 'Your email is already verified. You can log in.';

    } elseif ($row['used']) {
        $message = 'This verification link has already been used.';

    } elseif (strtotime($row['expires_at']) < time()) {
        $status  = 'expired';
        $message = 'This verification link has expired. Please request a new one.';

    } else {
        // Activate the account
        $upd = $conn->prepare("UPDATE users SET account_status = 'ACTIVE' WHERE user_id = ?");
        $upd->bind_param('s', $row['user_id']);
        $upd->execute();
        $upd->close();

        $mark = $conn->prepare("UPDATE email_verifications SET used = 1 WHERE token = ?");
        $mark->bind_param('s', $token);
        $mark->execute();
        $mark->close();

        $status  = 'success';
        $message = 'Your email has been verified! You can now log in.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus - Email Verification</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/tokens.css?v=<?= @filemtime(__DIR__.'/assets/css/tokens.css') ?>">
    <script src="assets/js/theme.js?v=<?= @filemtime(__DIR__.'/assets/js/theme.js') ?>"></script>
    <style>
        :root { --primary:#A83535; --background:#FAFAFA; --text-primary:#2E2E2E; --text-secondary:#707070; }
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',system-ui,sans-serif; }
        body { background:var(--background); color:var(--text-primary); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
        .box { background:var(--white); max-width:440px; width:100%; padding:44px 36px; border-radius:12px; box-shadow:0 8px 24px rgba(0,0,0,0.08); text-align:center; }
        .icon { width:64px; height:64px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; font-size:28px; }
        .icon.success { background:var(--success-bg); color:var(--success); }
        .icon.error   { background:var(--danger-bg); color:var(--danger); }
        h1 { font-size:20px; margin-bottom:10px; }
        p { color:var(--text-secondary); font-size:14px; line-height:1.6; margin-bottom:26px; }
        a.btn { display:inline-block; padding:12px 28px; background:var(--primary); color:var(--on-primary); border-radius:8px; text-decoration:none; font-weight:600; font-size:14px; }
    </style>
</head>
<body>
    <div class="box">
        <div class="icon <?= $status === 'success' ? 'success' : 'error' ?>">
            <i class="fas <?= $status === 'success' ? 'fa-check' : 'fa-exclamation' ?>"></i>
        </div>
        <h1><?= $status === 'success' ? 'Email Verified' : 'Verification Failed' ?></h1>
        <p><?= htmlspecialchars($message) ?></p>
        <?php if ($status === 'expired'): ?>
            <a href="Registration.php?resend=<?= urlencode($row['email']) ?>" class="btn">Resend Verification Email</a>
        <?php else: ?>
            <a href="login.php" class="btn">Go to Login</a>
        <?php endif; ?>
    </div>
</body>
</html>