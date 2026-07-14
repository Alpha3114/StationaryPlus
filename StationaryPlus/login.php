<?php
// ============================================================
//  login.php — Login + Forgot Password + Reset Password
//
//  All three flows live in this file, controlled by ?step=
//    (none)           → login form
//    ?step=forgot     → enter email
//    ?step=reset&token=xxx → enter new password
//    ?step=done       → success confirmation
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'db.php';

// Already logged in? Go to dashboard
if (!empty($_SESSION['user_id'])) {
    $map = ['ADMIN' => 'a_dashboard.php', 'STAFF' => 's_dashboard.php', 'CUSTOMER' => 'c_dashboard.php'];
    header('Location: ' . ($map[$_SESSION['user_role']] ?? 'login.php'));
    exit;
}

$step         = $_GET['step'] ?? 'login';
$error        = '';
$success      = '';
$email_val    = '';
$showResend   = false;   // true when we should render the "resend verification" link
$resendEmail  = '';      // email to prefill into the resend link

// ── Helpers ───────────────────────────────────────────────────
function siteUrl(): string {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $proto . '://' . $_SERVER['HTTP_HOST'];
}

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    // ── 1. Login ──────────────────────────────────────────────
    if ($action === 'do_login') {
        $email     = trim($_POST['email']    ?? '');
        $password  = $_POST['password']      ?? '';
        $email_val = $email;

        if ($email === '' || $password === '') {
            $error = 'Please enter both email and password.';
            $step  = 'login';
        } else {
            $stmt = $conn->prepare(
                "SELECT user_id, name, password_hash, user_role, account_status, branch_id, preferred_branch_id
                 FROM users WHERE email = ? LIMIT 1"
            );
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $error = 'Invalid email or password.';
                $step  = 'login';

            } elseif ($user['account_status'] === 'PENDING') {
                $error       = 'Please verify your email before logging in. Check your inbox for the verification link.';
                $step        = 'login';
                $showResend  = true;
                $resendEmail = $email;

            } elseif ($user['account_status'] !== 'ACTIVE') {
                $error = 'This account is inactive. Please contact support.';
                $step  = 'login';

            } else {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['user_id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['user_role'];
                $_SESSION['branch_id'] = $user['branch_id']; // null for customers (staff field)

                // For customers, load their preferred branch instead
                if ($user['user_role'] === 'CUSTOMER') {
                    $_SESSION['branch_id'] = $user['preferred_branch_id'] ?? null;
                }

                $map = ['ADMIN' => 'a_dashboard.php', 'STAFF' => 's_dashboard.php', 'CUSTOMER' => 'c_dashboard.php'];
                header('Location: ' . ($map[$user['user_role']] ?? 'login.php'));
                exit;
            }
        }
    }

    // ── 2. Forgot password: send reset link ───────────────────
    elseif ($action === 'do_forgot') {
        $email = trim($_POST['email'] ?? '');
        $step  = 'forgot';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Look up the user — don't reveal if they exist or not
            $stmt = $conn->prepare(
                "SELECT user_id, name FROM users WHERE email = ? AND account_status = 'ACTIVE' LIMIT 1"
            );
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($user) {
                // Invalidate any previous unused tokens for this user
                $stmt = $conn->prepare(
                    "UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0"
                );
                $stmt->bind_param('s', $user['user_id']);
                $stmt->execute();
                $stmt->close();

                // Create new token (valid 1 hour)
                $token     = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', time() + 3600);

                $stmt = $conn->prepare(
                    "INSERT INTO password_resets (token, user_id, expires_at) VALUES (?, ?, ?)"
                );
                $stmt->bind_param('sss', $token, $user['user_id'], $expiresAt);
                $stmt->execute();
                $stmt->close();

                // Build and send the email
                $resetUrl = siteUrl() . '/login.php?step=reset&token=' . $token;
                $subject  = 'StationaryPlus — Password Reset Request';
                $body     =
                    "Hi " . $user['name'] . ",\n\n" .
                    "We received a request to reset your StationaryPlus password.\n\n" .
                    "Click the link below to set a new password. " .
                    "This link is valid for 1 hour:\n\n" .
                    $resetUrl . "\n\n" .
                    "If you did not request a password reset, you can safely ignore this email — " .
                    "your account has not been changed.\n\n" .
                    "— StationaryPlus Support";

                require_once 'mailer.php';
                sendAppEmail($email, $subject, $body);
            }

            // Always redirect to done — never confirm whether email exists
            header('Location: login.php?step=done&mode=forgot');
            exit;
        }
    }

    // ── 3. Reset password: update hash ───────────────────────
    elseif ($action === 'do_reset') {
        $token   = trim($_POST['token']            ?? '');
        $pass1   = $_POST['new_password']          ?? '';
        $pass2   = $_POST['confirm_password']      ?? '';
        $step    = 'reset';

        // Re-validate token server-side (never trust client)
        $stmt = $conn->prepare(
            "SELECT pr.user_id FROM password_resets pr
             WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
             LIMIT 1"
        );
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $resetRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$resetRow) {
            $error = 'This reset link is invalid or has expired. Please request a new one.';
        } elseif (strlen($pass1) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Za-z]/', $pass1) || !preg_match('/[0-9]/', $pass1)) {
            $error = 'Password must contain both letters and numbers.';
        } elseif ($pass1 !== $pass2) {
            $error = 'Passwords do not match.';
        } else {
            // Update the password
            $hash = password_hash($pass1, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $stmt->bind_param('ss', $hash, $resetRow['user_id']);
            $stmt->execute();
            $stmt->close();

            // Mark token as consumed
            $stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->bind_param('s', $token);
            $stmt->execute();
            $stmt->close();

            header('Location: login.php?step=done&mode=reset');
            exit;
        }
    }
}

// ── Validate token for the reset form (GET) ───────────────────
$tokenData  = null;
$tokenError = '';
if ($step === 'reset') {
    $token = trim($_GET['token'] ?? '');

    if (!$token) {
        $tokenError = 'Invalid or missing reset link.';
    } else {
        $stmt = $conn->prepare(
            "SELECT pr.token, pr.user_id, pr.expires_at, pr.used, u.name
             FROM password_resets pr
             JOIN users u ON pr.user_id = u.user_id
             WHERE pr.token = ? LIMIT 1"
        );
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $tokenData = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$tokenData) {
            $tokenError = 'This reset link is invalid.';
        } elseif ($tokenData['used']) {
            $tokenError = 'This link has already been used. Please request a new one.';
        } elseif (strtotime($tokenData['expires_at']) < time()) {
            $tokenError = 'This link has expired (valid for 1 hour). Please request a new one.';
        }
    }
}

$doneMode = $_GET['mode'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus <?= $step !== 'login' ? '— ' . match($step) { 'forgot' => 'Forgot Password', 'reset' => 'Reset Password', 'done' => 'All Done', default => '' } : '' ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #A83535; --secondary: #F4A261; --accent: #F1EDE8;
            --background: #F5F5F5; --text-primary: #2E2E2E; --text-secondary: #707070;
            --border: #E0E0E0; --white: #FFFFFF;
        }
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',system-ui,sans-serif; }
        body { background-color:var(--background); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }

        .login-container {
            display: flex;
            width: 100%;
            max-width: 960px;
            min-height: 600px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }

        /* ── Info panel (left) ── */
        .info-panel {
            flex: 1;
            background: linear-gradient(135deg, var(--primary) 0%, #7a2020 100%);
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            color: var(--white);
        }
        .logo { display:flex;align-items:center;margin-bottom:40px; }
        .logo-icon { background:rgba(255,255,255,0.15);width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-right:12px;font-size:22px; }
        .logo-text { font-size:24px;font-weight:700;letter-spacing:0.5px; }
        .system-title { font-size:28px;font-weight:600;margin-bottom:20px;line-height:1.3; }
        .system-description { font-size:15px;line-height:1.7;margin-bottom:36px;opacity:0.9; }
        .features-container { margin-top:10px; }
        .feature { display:flex;align-items:flex-start;margin-bottom:22px; }
        .feature-icon { background:rgba(255,255,255,0.15);width:38px;height:38px;border-radius:8px;display:flex;align-items:center;justify-content:center;margin-right:14px;flex-shrink:0;font-size:16px; }
        .feature-text { font-size:15px;line-height:1.5; }
        .role-badges { display:flex;gap:10px;margin-top:auto;padding-top:36px;flex-wrap:wrap; }
        .badge { background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);border-radius:20px;padding:6px 14px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:6px; }

        /* ── Form panel (right) ── */
        .form-panel {
            flex: 1;
            padding: 50px 40px;
            background-color: var(--white);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .form-header { margin-bottom: 32px; }
        .form-title { font-size:28px;color:var(--text-primary);margin-bottom:8px;font-weight:600; }
        .form-subtitle { color:var(--text-secondary);font-size:15px;line-height:1.5; }

        /* Inputs */
        .form-group { margin-bottom:22px;position:relative; }
        label { display:block;margin-bottom:8px;font-weight:600;color:var(--text-primary);font-size:15px; }
        .input-wrapper { position:relative; }
        input[type=email], input[type=password], input[type=text] {
            width:100%;padding:14px 14px 14px 46px;border:1.5px solid var(--border);border-radius:8px;
            font-size:15px;transition:all 0.2s;background-color:var(--accent);color:var(--text-primary);
        }
        input:focus { outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(168,53,53,0.1);background-color:var(--white); }
        input.input-error { border-color:#D32F2F;background-color:#fff5f5; }
        .input-icon { position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-secondary);font-size:16px;pointer-events:none; }
        .password-toggle { position:absolute;right:14px;top:50%;transform:translateY(-50%);color:var(--text-secondary);cursor:pointer;font-size:16px; }

        /* Password strength */
        .pw-hint { font-size:12px;color:var(--text-secondary);margin-top:6px; }

        /* Alerts */
        .alert-error {
            background:#fff0f0;color:#c62828;border:1px solid #ef9a9a;border-radius:8px;
            padding:12px 16px;font-size:14px;margin-bottom:20px;display:flex;align-items:flex-start;gap:8px;
        }
        .alert-success {
            background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;border-radius:8px;
            padding:12px 16px;font-size:14px;margin-bottom:20px;display:flex;align-items:flex-start;gap:8px;
        }
        .alert-info {
            background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;border-radius:8px;
            padding:12px 16px;font-size:14px;margin-bottom:20px;display:flex;align-items:flex-start;gap:8px;line-height:1.6;
        }

        /* Buttons */
        .btn-primary {
            width:100%;padding:14px;background-color:var(--primary);color:var(--white);
            border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;
            transition:background-color 0.2s;margin-top:8px;margin-bottom:24px;
            display:flex;align-items:center;justify-content:center;gap:8px;
        }
        .btn-primary:hover { background-color:#8b2a2a; }
        .btn-done {
            display:inline-flex;align-items:center;gap:8px;padding:12px 28px;
            background:var(--primary);color:white;border-radius:8px;text-decoration:none;
            font-size:15px;font-weight:600;transition:background 0.2s;
        }
        .btn-done:hover { background:#8b2a2a; }

        /* Back / secondary links */
        .back-link {
            display:inline-flex;align-items:center;gap:7px;color:var(--text-secondary);
            font-size:14px;text-decoration:none;margin-bottom:24px;transition:color 0.2s;
        }
        .back-link:hover { color:var(--primary); }
        .forgot-link {
            display:block;text-align:right;font-size:13px;color:var(--primary);
            text-decoration:none;margin-top:-14px;margin-bottom:20px;font-weight:600;
        }
        .forgot-link:hover { text-decoration:underline; }

        /* Divider */
        .divider { display:flex;align-items:center;gap:12px;margin-bottom:20px;color:var(--text-secondary);font-size:13px; }
        .divider::before, .divider::after { content:'';flex:1;height:1px;background:var(--border); }

        /* Register CTA */
        .register-cta { background:var(--accent);border-radius:10px;padding:20px;text-align:center; }
        .register-cta p { font-size:14px;color:var(--text-secondary);margin-bottom:14px;line-height:1.5; }
        .btn-register { display:inline-block;padding:10px 28px;border:2px solid var(--primary);border-radius:8px;color:var(--primary);font-size:15px;font-weight:600;text-decoration:none;transition:all 0.2s; }
        .btn-register:hover { background-color:var(--primary);color:var(--white); }

        /* Done screen */
        .done-icon { width:64px;height:64px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:28px; }
        .done-icon.success { background:#ecfdf5;color:#059669; }
        .done-icon.info    { background:#eff6ff;color:#1d4ed8; }

        .form-footer { margin-top:24px;font-size:13px;color:var(--text-secondary);text-align:center; }

        @media (max-width:900px) {
            .login-container { flex-direction:column;max-width:600px; }
            .info-panel, .form-panel { padding:40px 30px; }
            .role-badges { padding-top:26px; }
        }
    </style>
</head>
<body>

<div class="login-container">

    <!-- ── Info panel (always visible) ── -->
    <div class="info-panel">
        <div class="logo">
            <div class="logo-icon"><i class="fas fa-pen-nib"></i></div>
            <div class="logo-text">StationaryPlus</div>
        </div>

        <h2 class="system-title">Welcome back to StationaryPlus</h2>
        <p class="system-description">
            Your all-in-one platform for stationery management, printing services, and multi-branch operations.
        </p>

        <div class="features-container">
            <div class="feature">
                <div class="feature-icon"><i class="fas fa-cubes"></i></div>
                <div class="feature-text"><strong>Manage inventory</strong> across all branches from a single dashboard.</div>
            </div>
            <div class="feature">
                <div class="feature-icon"><i class="fas fa-print"></i></div>
                <div class="feature-text"><strong>Track print jobs</strong> with automated cost and time estimation.</div>
            </div>
            <div class="feature">
                <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                <div class="feature-text"><strong>View sales reports</strong> and inventory forecasts powered by analytics.</div>
            </div>
        </div>

        <div class="role-badges" title="StationaryPlus supports all three account types — sign in below with whichever account you have; you don't pick a role here.">
            <div class="badge" title="For branch/company administrators"><i class="fas fa-user-shield"></i> Admin</div>
            <div class="badge" title="For branch staff"><i class="fas fa-user-tie"></i> Staff</div>
            <div class="badge" title="For registered customers"><i class="fas fa-user"></i> Customer</div>
        </div>
    </div>

    <!-- ── Form panel ── -->
    <div class="form-panel">

        <?php if ($step === 'login'): ?>
        <!-- ════ LOGIN SCREEN ════ -->
        <div class="form-header">
            <h2 class="form-title">Sign in</h2>
            <p class="form-subtitle">Enter your credentials to access your account</p>
        </div>

        <?php if ($error): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-circle" style="flex-shrink:0;margin-top:2px;"></i>
            <div>
                <?= htmlspecialchars($error) ?>
                <?php if ($showResend): ?>
                    <br><a href="Registration.php?resend=<?= urlencode($resendEmail) ?>" style="color:#1d4ed8;font-weight:600;">Resend verification email</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <input type="hidden" name="action" value="do_login">

            <div class="form-group">
                <label for="email">Email address</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" id="email" name="email"
                           placeholder="example@domain.com"
                           value="<?= htmlspecialchars($email_val) ?>"
                           class="<?= $error ? 'input-error' : '' ?>"
                           required autocomplete="email">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="password" name="password"
                           placeholder="••••••••"
                           class="<?= $error ? 'input-error' : '' ?>"
                           required autocomplete="current-password">
                    <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                </div>
            </div>

            <a href="login.php?step=forgot" class="forgot-link">Forgot password?</a>

            <button type="submit" class="btn-primary">
                <i class="fas fa-sign-in-alt"></i> Sign in
            </button>
        </form>

        <div class="divider">New to StationaryPlus?</div>

        <div class="register-cta">
            <p>Create a free customer account to place orders, upload print files, and track your requests in real time.</p>
            <a href="Registration.php" class="btn-register">
                <i class="fas fa-user-plus"></i>&nbsp; Create Account
            </a>
        </div>

        <?php elseif ($step === 'forgot'): ?>
        <!-- ════ FORGOT PASSWORD SCREEN ════ -->
        <a href="login.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to sign in
        </a>

        <div class="form-header">
            <h2 class="form-title">Forgot password?</h2>
            <p class="form-subtitle">
                Enter your account email and we'll send you a link to reset your password.
                The link expires in 1 hour.
            </p>
        </div>

        <?php if ($error): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-circle" style="flex-shrink:0;margin-top:2px;"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="login.php?step=forgot">
            <input type="hidden" name="action" value="do_forgot">

            <div class="form-group">
                <label for="forgot_email">Email address</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" id="forgot_email" name="email"
                           placeholder="example@domain.com"
                           required autocomplete="email">
                </div>
            </div>

            <button type="submit" class="btn-primary">
                <i class="fas fa-paper-plane"></i> Send Reset Link
            </button>
        </form>

        <?php elseif ($step === 'reset'): ?>
        <!-- ════ RESET PASSWORD SCREEN ════ -->
        <a href="login.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to sign in
        </a>

        <div class="form-header">
            <h2 class="form-title">Set new password</h2>
            <p class="form-subtitle">
                <?= $tokenData ? 'Hi ' . htmlspecialchars($tokenData['name']) . ', choose a strong new password.' : 'Reset your password below.' ?>
            </p>
        </div>

        <?php if ($tokenError): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-circle" style="flex-shrink:0;margin-top:2px;"></i>
            <?= htmlspecialchars($tokenError) ?>
        </div>
        <p style="text-align:center;margin-top:10px;">
            <a href="login.php?step=forgot" style="color:var(--primary);font-weight:600;">Request a new link</a>
        </p>

        <?php else: ?>

        <?php if ($error): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-circle" style="flex-shrink:0;margin-top:2px;"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="login.php?step=reset">
            <input type="hidden" name="action" value="do_reset">
            <input type="hidden" name="token"  value="<?= htmlspecialchars($_GET['token'] ?? '') ?>">

            <div class="form-group">
                <label for="new_password">New password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="new_password" name="new_password"
                           placeholder="Min. 8 characters"
                           required autocomplete="new-password"
                           oninput="checkStrength(this.value)">
                    <i class="fas fa-eye password-toggle" id="toggleNew"></i>
                </div>
                <div class="pw-hint" id="pwStrength"></div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm new password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="confirm_password" name="confirm_password"
                           placeholder="Repeat your password"
                           required autocomplete="new-password">
                    <i class="fas fa-eye password-toggle" id="toggleConfirm"></i>
                </div>
            </div>

            <button type="submit" class="btn-primary">
                <i class="fas fa-check-circle"></i> Set New Password
            </button>
        </form>
        <?php endif; ?>

        <?php elseif ($step === 'done'): ?>
        <!-- ════ DONE SCREEN ════ -->
        <div style="text-align:center;padding:20px 0;">
            <?php if ($doneMode === 'reset'): ?>
            <div class="done-icon success"><i class="fas fa-check"></i></div>
            <h2 class="form-title" style="text-align:center;">Password updated!</h2>
            <p style="color:var(--text-secondary);margin:12px 0 28px;font-size:15px;line-height:1.6;">
                Your password has been changed successfully.<br>
                You can now sign in with your new password.
            </p>
            <a href="login.php" class="btn-done">
                <i class="fas fa-sign-in-alt"></i> Sign in now
            </a>
            <?php else: ?>
            <div class="done-icon info"><i class="fas fa-envelope"></i></div>
            <h2 class="form-title" style="text-align:center;">Check your email</h2>
            <p style="color:var(--text-secondary);margin:12px 0 28px;font-size:15px;line-height:1.6;">
                If an account exists for that email address, we've sent a password reset link.
                Please check your inbox — the link expires in <strong>1 hour</strong>.
            </p>
            <div class="alert-info" style="text-align:left;">
                <i class="fas fa-info-circle" style="flex-shrink:0;margin-top:2px;"></i>
                <div>
                    Didn't receive it? Check your spam folder, or
                    <a href="login.php?step=forgot" style="color:#1d4ed8;font-weight:600;">try again</a>
                    with the same email.
                </div>
            </div>
            <a href="login.php" class="back-link" style="justify-content:center;">
                <i class="fas fa-arrow-left"></i> Back to sign in
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="form-footer">
            &copy; <?= date('Y') ?> StationaryPlus. All rights reserved.
        </div>

    </div><!-- /.form-panel -->
</div><!-- /.login-container -->

<script>
// ── Login: password show/hide ─────────────────────────────────
const togglePassword = document.getElementById('togglePassword');
const passwordInput  = document.getElementById('password');
if (togglePassword && passwordInput) {
    togglePassword.addEventListener('click', function () {
        const t = passwordInput.type === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', t);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });
}

// ── Reset: password show/hide (new + confirm) ─────────────────
[['toggleNew','new_password'],['toggleConfirm','confirm_password']].forEach(([btnId, inputId]) => {
    const btn = document.getElementById(btnId);
    const inp = document.getElementById(inputId);
    if (btn && inp) {
        btn.addEventListener('click', function () {
            const t = inp.type === 'password' ? 'text' : 'password';
            inp.setAttribute('type', t);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    }
});

// ── Reset: live password strength hint ────────────────────────
function checkStrength(val) {
    const el = document.getElementById('pwStrength');
    if (!el) return;
    if (!val) { el.textContent = ''; el.style.color = ''; return; }

    const hasLetter  = /[A-Za-z]/.test(val);
    const hasNumber  = /[0-9]/.test(val);
    const hasSpecial = /[^A-Za-z0-9]/.test(val);
    const long       = val.length >= 8;

    if (!long) {
        el.textContent = 'Too short — minimum 8 characters';
        el.style.color = '#dc2626';
    } else if (hasLetter && hasNumber && hasSpecial) {
        el.textContent = '✓ Strong password';
        el.style.color = '#059669';
    } else if (hasLetter && hasNumber) {
        el.textContent = '✓ Good — add a symbol to make it stronger';
        el.style.color = '#d97706';
    } else {
        el.textContent = 'Use letters and numbers';
        el.style.color = '#dc2626';
    }
}

// ── Clear input-error on type ─────────────────────────────────
document.querySelectorAll('input').forEach(input => {
    input.addEventListener('input', function () {
        this.classList.remove('input-error');
    });
});
</script>

</body>
</html>