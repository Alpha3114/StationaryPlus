<?php
// ============================================================
//  login.php — Login page
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Already logged in? Skip straight to dashboard
if (!empty($_SESSION['user_id'])) {
    $_SESSION['branch_id'] = $user['branch_id'] ?? null; // existing staff line
// For customers, load preferred branch
if ($user['user_role'] === 'CUSTOMER') {
    $stmt = $conn->prepare("SELECT preferred_branch_id FROM users WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('s', $user['user_id']);
    $stmt->execute();
    $pref = $stmt->get_result()->fetch_assoc()['preferred_branch_id'] ?? null;
    $stmt->close();
    $_SESSION['branch_id'] = $pref; // null = show popup, set = skip popup
}
    $map = [
        'ADMIN'    => 'a_dashboard.php',
        'STAFF'    => 's_dashboard.php',
        'CUSTOMER' => 'c_dashboard.php',
    ];
    header('Location: ' . ($map[$_SESSION['user_role']] ?? 'login.php'));
    exit;
}

require_once 'db.php';

$error     = '';
$email_val = '';

// ---- Handle POST --------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $email_val = $email;

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';

    } else {
        // MySQLi prepared statement with ? placeholder
        $stmt = $conn->prepare(
            "SELECT user_id, name, password_hash, user_role, account_status, branch_id
            FROM users WHERE email = ? LIMIT 1"
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();

        if (!$user || $user['account_status'] !== 'ACTIVE') {
            $error = 'Invalid email or password.';

        } elseif (!password_verify($password, $user['password_hash'])) {
            $error = 'Invalid email or password.';

        } else {
            session_regenerate_id(true);

            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['user_role'];
            $_SESSION['branch_id'] = $user['branch_id'];

            $map = [
                'ADMIN'    => 'a_dashboard.php',
                'STAFF'    => 's_dashboard.php',
                'CUSTOMER' => 'c_dashboard.php',
            ];
            header('Location: ' . ($map[$user['user_role']] ?? 'login.php'));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus — Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #A83535;
            --secondary: #F4A261;
            --accent: #F1EDE8;
            --background: #FAFAFA;
            --text-primary: #2E2E2E;
            --text-secondary: #707070;
            --border: #E0E0E0;
            --white: #FFFFFF;
        }

        /* Icon fix: do NOT include ::before/::after here */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        body {
            background-color: var(--background);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .login-container {
            width: 100%;
            max-width: 1100px;
            display: flex;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            border-radius: 12px;
            overflow: hidden;
            background-color: var(--white);
        }

        /* Info Panel */
        .info-panel {
            flex: 1;
            background: linear-gradient(145deg, var(--primary) 0%, #8b2a2a 100%);
            color: var(--white);
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
        }

        .logo {
            display: flex;
            align-items: center;
            margin-bottom: 50px;
        }

        .logo-icon {
            background-color: rgba(255,255,255,0.15);
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 24px;
        }

        .logo-text {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .system-title {
            font-size: 32px;
            font-weight: 600;
            margin-bottom: 25px;
            line-height: 1.2;
        }

        .system-description {
            font-size: 17px;
            line-height: 1.6;
            margin-bottom: 40px;
            opacity: 0.9;
            max-width: 90%;
        }

        .features-container { margin-top: 20px; }

        .feature {
            display: flex;
            align-items: flex-start;
            margin-bottom: 25px;
        }

        .feature-icon {
            background-color: rgba(255,255,255,0.15);
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
            font-size: 18px;
        }

        .feature-text { font-size: 16px; line-height: 1.5; }

        .role-badges {
            display: flex;
            gap: 10px;
            margin-top: auto;
            padding-top: 40px;
            flex-wrap: wrap;
        }

        .badge {
            background-color: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 20px;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Form Panel */
        .form-panel {
            flex: 1;
            padding: 50px 40px;
            background-color: var(--white);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header { margin-bottom: 36px; }

        .form-title {
            font-size: 30px;
            color: var(--text-primary);
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-subtitle { color: var(--text-secondary); font-size: 16px; }

        .form-group {
            margin-bottom: 22px;
            position: relative;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 15px;
        }

        .input-wrapper { position: relative; }

        input {
            width: 100%;
            padding: 14px 14px 14px 46px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.2s ease;
            background-color: var(--accent);
            color: var(--text-primary);
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(168,53,53,0.1);
            background-color: var(--white);
        }

        input.input-error {
            border-color: #D32F2F;
            background-color: #fff5f5;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 16px;
        }

        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 16px;
        }

        .alert-error {
            background: #fff0f0;
            color: #c62828;
            border: 1px solid #ef9a9a;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background-color: var(--primary);
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease;
            margin-top: 8px;
            margin-bottom: 28px;
        }

        .btn-login:hover { background-color: #8b2a2a; }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            color: var(--text-secondary);
            font-size: 13px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .register-cta {
            background: var(--accent);
            border-radius: 10px;
            padding: 22px 20px;
            text-align: center;
        }

        .register-cta p {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 14px;
            line-height: 1.5;
        }

        .btn-register {
            display: inline-block;
            padding: 10px 28px;
            border: 2px solid var(--primary);
            border-radius: 8px;
            color: var(--primary);
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-register:hover {
            background-color: var(--primary);
            color: var(--white);
        }

        .form-footer {
            margin-top: 24px;
            font-size: 13px;
            color: var(--text-secondary);
            text-align: center;
        }

        .form-footer a { color: var(--primary); text-decoration: none; }

        @media (max-width: 900px) {
            .login-container {
                flex-direction: column;
                max-width: 600px;
            }
            .info-panel, .form-panel { padding: 40px 30px; }
            .role-badges { padding-top: 30px; }
        }
    </style>
</head>
<body>

<div class="login-container">

    <!-- Info Panel -->
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
                <div class="feature-text">
                    <strong>Manage inventory</strong> across all branches from a single dashboard.
                </div>
            </div>
            <div class="feature">
                <div class="feature-icon"><i class="fas fa-print"></i></div>
                <div class="feature-text">
                    <strong>Track print jobs</strong> with automated cost and time estimation.
                </div>
            </div>
            <div class="feature">
                <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                <div class="feature-text">
                    <strong>View sales reports</strong> and inventory forecasts powered by analytics.
                </div>
            </div>
        </div>

        <div class="role-badges">
            <div class="badge"><i class="fas fa-user-shield"></i> Admin</div>
            <div class="badge"><i class="fas fa-user-tie"></i> Staff</div>
            <div class="badge"><i class="fas fa-user"></i> Customer</div>
        </div>
    </div>

    <!-- Form Panel -->
    <div class="form-panel">
        <div class="form-header">
            <h2 class="form-title">Sign in</h2>
            <p class="form-subtitle">Enter your credentials to access your account</p>
        </div>

        <?php if ($error): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">

            <div class="form-group">
                <label for="email">Email address</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope input-icon"></i>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="example@domain.com"
                        value="<?= htmlspecialchars($email_val) ?>"
                        class="<?= $error ? 'input-error' : '' ?>"
                        required
                        autocomplete="email"
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="••••••••"
                        class="<?= $error ? 'input-error' : '' ?>"
                        required
                        autocomplete="current-password"
                    >
                    <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                </div>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i>&nbsp; Sign in
            </button>

        </form>

        <div class="divider">New to StationaryPlus?</div>

        <div class="register-cta">
            <p>Create a free customer account to place orders, upload print files, and track your requests in real time.</p>
            <a href="Registration.php" class="btn-register">
                <i class="fas fa-user-plus"></i>&nbsp; Create Account
            </a>
        </div>

        <div class="form-footer">
            &copy; <?= date('Y') ?> StationaryPlus. All rights reserved.
        </div>

    </div>
</div>

<script>
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput  = document.getElementById('password');

    togglePassword.addEventListener('click', function () {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });

    document.querySelectorAll('input').forEach(input => {
        input.addEventListener('input', function () {
            this.classList.remove('input-error');
        });
    });
</script>

</body>
</html>