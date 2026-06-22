<?php
// ============================================================
//  Registration.php — Customer self-registration
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Already logged in? Go to dashboard
if (!empty($_SESSION['user_id'])) {
    $map = [
        'ADMIN'    => 'a_dashboard.php',
        'STAFF'    => 's_dashboard.php',
        'CUSTOMER' => 'c_dashboard.php',
    ];
    header('Location: ' . ($map[$_SESSION['user_role']] ?? 'login.php'));
    exit;
}

require_once 'db.php';

// ---- Helpers ------------------------------------------------

function generate_user_id(): string {
    // Format: USR-YYYYMMDD-XXXXX (fits varchar(28))
    return 'USR-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

// ---- Field values & errors ----------------------------------
$fields  = ['name' => '', 'email' => '', 'phone' => ''];
$errors  = [];
$success = false;

// ---- Handle POST --------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Sanitise inputs
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $phone    = trim($_POST['phone']    ?? '');

    $fields = compact('name', 'email', 'phone');

    // 2. Server-side validation
    if (mb_strlen($name) < 2) {
        $errors['name'] = 'Please enter a valid name (minimum 2 characters).';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password must be at least 8 characters and include letters and numbers.';
    }

    $phoneDigits = preg_replace('/\D/', '', $phone);
    if (strlen($phoneDigits) < 10) {
        $errors['phone'] = 'Please enter a valid phone number (at least 10 digits).';
    }

    // 3. Duplicate email check (MySQLi)
    if (empty($errors['email'])) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors['email'] = 'This email address is already registered. Try logging in.';
        }
        $stmt->close();
    }

    // 4. Insert if no errors (MySQLi with ? placeholders)
    if (empty($errors)) {
        $userId       = generate_user_id();
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $role         = 'CUSTOMER';
        $status       = 'ACTIVE';

        $stmt = $conn->prepare(
            "INSERT INTO users (user_id, name, email, password_hash, phone_number, user_role, account_status)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        // s = string for all 7 values
        $stmt->bind_param('sssssss', $userId, $name, $email, $passwordHash, $phone, $role, $status);
        $stmt->execute();
        $stmt->close();

        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus - Registration</title>
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

        .registration-container {
            width: 100%;
            max-width: 1100px;
            display: flex;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            border-radius: 12px;
            overflow: hidden;
            background-color: var(--white);
        }

        /* Information Panel */
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

        .benefits-container { margin-top: 20px; }

        .benefit {
            display: flex;
            align-items: flex-start;
            margin-bottom: 25px;
        }

        .benefit-icon {
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

        .benefit-text { font-size: 16px; line-height: 1.5; }

        /* Form Panel */
        .form-panel {
            flex: 1;
            padding: 50px 40px;
            background-color: var(--white);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header { margin-bottom: 32px; }

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

        .input-hint {
            display: block;
            margin-top: 6px;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .error-message {
            color: #D32F2F;
            font-size: 13px;
            margin-top: 5px;
            display: none;
        }

        .error-message.visible { display: block; }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }

        .alert-error {
            background: #fff0f0;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }

        .button-container {
            margin-top: 8px;
            margin-bottom: 24px;
        }

        .register-button {
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
        }

        .register-button:hover { background-color: #8b2a2a; }

        .secondary-action {
            text-align: center;
            color: var(--text-secondary);
            font-size: 15px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .secondary-action a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .secondary-action a:hover { text-decoration: underline; }

        .form-footer {
            margin-top: 20px;
            font-size: 13px;
            color: var(--text-secondary);
            text-align: center;
        }

        .form-footer a { color: var(--primary); text-decoration: none; }

        @media (max-width: 900px) {
            .registration-container {
                flex-direction: column;
                max-width: 600px;
            }
            .info-panel, .form-panel { padding: 40px 30px; }
        }
    </style>
</head>
<body>

<div class="registration-container">

    <!-- Information Panel -->
    <div class="info-panel">
        <div class="logo">
            <div class="logo-icon"><i class="fas fa-pen-nib"></i></div>
            <div class="logo-text">StationaryPlus</div>
        </div>
        <h2 class="system-title">Stationery &amp; Printing Management System</h2>
        <p class="system-description">
            A comprehensive solution for managing stationery orders, printing services, and pre-order tracking for educational institutions and businesses.
        </p>
        <div class="benefits-container">
            <div class="benefit">
                <div class="benefit-icon"><i class="fas fa-clipboard-check"></i></div>
                <div class="benefit-text"><strong>Make pre-orders</strong> for stationery items and printing services in advance.</div>
            </div>
            <div class="benefit">
                <div class="benefit-icon"><i class="fas fa-upload"></i></div>
                <div class="benefit-text"><strong>Upload printing files</strong> directly to the system for quick processing and printing.</div>
            </div>
            <div class="benefit">
                <div class="benefit-icon"><i class="fas fa-search"></i></div>
                <div class="benefit-text"><strong>Check order or pre-order status</strong> in real-time with detailed tracking information.</div>
            </div>
        </div>
    </div>

    <!-- Registration Form Panel -->
    <div class="form-panel">
        <div class="form-header">
            <h2 class="form-title">Create Account</h2>
            <p class="form-subtitle">Register to access StationaryPlus services</p>
        </div>

        <?php if ($success): ?>
            <!-- SUCCESS STATE -->
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <strong>Account created successfully!</strong> You can now log in.
            </div>
            <div class="button-container">
                <a href="login.php" style="display:block; text-align:center; padding:14px; background:var(--primary); color:#fff; border-radius:8px; font-size:16px; font-weight:600; text-decoration:none;">
                    Go to Login
                </a>
            </div>

        <?php else: ?>

            <?php if (!empty($errors) && count($errors) > 1): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    Please fix the errors below before continuing.
                </div>
            <?php endif; ?>

            <form class="registration-form" id="registrationForm" method="POST" action="Registration.php">

                <!-- Full Name -->
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            placeholder="Enter your full name"
                            value="<?= htmlspecialchars($fields['name']) ?>"
                            class="<?= isset($errors['name']) ? 'input-error' : '' ?>"
                            required
                        >
                    </div>
                    <span class="error-message <?= isset($errors['name']) ? 'visible' : '' ?>" id="name-error">
                        <?= htmlspecialchars($errors['name'] ?? 'Please enter a valid name (minimum 2 characters)') ?>
                    </span>
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope input-icon"></i>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="example@domain.com"
                            value="<?= htmlspecialchars($fields['email']) ?>"
                            class="<?= isset($errors['email']) ? 'input-error' : '' ?>"
                            required
                        >
                    </div>
                    <span class="input-hint">We'll use this for order notifications and account recovery</span>
                    <span class="error-message <?= isset($errors['email']) ? 'visible' : '' ?>" id="email-error">
                        <?= htmlspecialchars($errors['email'] ?? 'Please enter a valid email address') ?>
                    </span>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Create a secure password"
                            class="<?= isset($errors['password']) ? 'input-error' : '' ?>"
                            required
                        >
                        <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                    </div>
                    <span class="input-hint">Minimum 8 characters with letters and numbers</span>
                    <span class="error-message <?= isset($errors['password']) ? 'visible' : '' ?>" id="password-error">
                        <?= htmlspecialchars($errors['password'] ?? 'Password must be at least 8 characters long') ?>
                    </span>
                </div>

                <!-- Phone -->
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <div class="input-wrapper">
                        <i class="fas fa-phone input-icon"></i>
                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            placeholder="Enter your phone number"
                            value="<?= htmlspecialchars($fields['phone']) ?>"
                            class="<?= isset($errors['phone']) ? 'input-error' : '' ?>"
                            required
                        >
                    </div>
                    <span class="input-hint">For delivery updates and account verification</span>
                    <span class="error-message <?= isset($errors['phone']) ? 'visible' : '' ?>" id="phone-error">
                        <?= htmlspecialchars($errors['phone'] ?? 'Please enter a valid phone number') ?>
                    </span>
                </div>

                <div class="button-container">
                    <button type="submit" class="register-button">Register Account</button>
                </div>

                <div class="secondary-action">
                    Already have an account? <a href="login.php">Login here</a>
                </div>

                <div class="form-footer">
                    By registering, you agree to our <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
                </div>

            </form>
        <?php endif; ?>

    </div>
</div>

<script>
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput  = document.getElementById('password');

    if (togglePassword) {
        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    }

    const form = document.getElementById('registrationForm');

    if (form) {
        form.addEventListener('submit', function (e) {
            let isValid = true;

            document.querySelectorAll('.error-message').forEach(el => el.classList.remove('visible'));
            document.querySelectorAll('input').forEach(el => el.classList.remove('input-error'));

            const name  = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const pass  = document.getElementById('password').value;
            const phone = document.getElementById('phone').value.replace(/\D/g, '');

            if (name.length < 2)                                              { showError('name-error',     'name');     isValid = false; }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))                   { showError('email-error',    'email');    isValid = false; }
            if (pass.length < 8 || !/[A-Za-z]/.test(pass) || !/[0-9]/.test(pass)) { showError('password-error', 'password'); isValid = false; }
            if (phone.length < 10)                                            { showError('phone-error',    'phone');    isValid = false; }

            if (!isValid) e.preventDefault();
        });

        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', function () {
                const errEl = document.getElementById(this.id + '-error');
                if (errEl) errEl.classList.remove('visible');
                this.classList.remove('input-error');
            });
        });
    }

    function showError(errorId, inputId) {
        const errEl   = document.getElementById(errorId);
        const inputEl = document.getElementById(inputId);
        if (errEl)   errEl.classList.add('visible');
        if (inputEl) inputEl.classList.add('input-error');
    }
</script>

</body>
</html>