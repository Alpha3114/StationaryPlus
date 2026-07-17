<?php
// ============================================================
//  c_profile.php — Customer Profile Management
//  Allows customers to update name, email, phone, and password
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'auth.php';
require_role('CUSTOMER');
require_once 'db.php';

$userId  = $_SESSION['user_id'];
$message = '';
$msgType = '';

// ── Load current user data ────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT user_id, name, email, phone_number, user_role, account_status, registration_date, password_hash
     FROM users WHERE user_id = ? LIMIT 1"
);
$stmt->bind_param('s', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    // ── Update profile info ───────────────────────────────────
    if ($action === 'update_profile') {
        $name  = trim($_POST['name']         ?? '');
        $phone = trim($_POST['phone_number'] ?? '');
        // Email is intentionally NOT read from $_POST — it cannot be
        // changed from this form. The existing value is always kept.

        if (mb_strlen($name) < 2) {
            $message = 'Name must be at least 2 characters.';
            $msgType = 'error';

        } else {
            $stmt = $conn->prepare(
                "UPDATE users SET name = ?, phone_number = ? WHERE user_id = ?"
            );
            $stmt->bind_param('sss', $name, $phone, $userId);
            $stmt->execute();
            $stmt->close();

            // Update session name so sidebar reflects the change immediately
            $_SESSION['user_name'] = $name;

            // Refresh user data (email stays untouched)
            $user['name']         = $name;
            $user['phone_number'] = $phone;

            $message = 'Profile updated successfully.';
            $msgType = 'success';
        }
    }

    // ── Change password ───────────────────────────────────────
    if ($action === 'change_password') {
        $currentPw  = $_POST['current_password']  ?? '';
        $newPw      = $_POST['new_password']       ?? '';
        $confirmPw  = $_POST['confirm_password']   ?? '';

        if (!password_verify($currentPw, $user['password_hash'])) {
            $message = 'Current password is incorrect.';
            $msgType = 'error';

        } elseif (strlen($newPw) < 8 ||
                  !preg_match('/[A-Za-z]/', $newPw) ||
                  !preg_match('/[0-9]/', $newPw)) {
            $message = 'New password must be at least 8 characters and include letters and numbers.';
            $msgType = 'error';

        } elseif ($newPw !== $confirmPw) {
            $message = 'New passwords do not match.';
            $msgType = 'error';

        } elseif (password_verify($newPw, $user['password_hash'])) {
            $message = 'New password must be different from your current password.';
            $msgType = 'error';

        } else {
            $hash = password_hash($newPw, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                "UPDATE users SET password_hash = ? WHERE user_id = ?"
            );
            $stmt->bind_param('ss', $hash, $userId);
            $stmt->execute();
            $stmt->close();

            // Refresh the hash so repeat submissions work correctly
            $user['password_hash'] = $hash;

            $message = 'Password changed successfully.';
            $msgType = 'success';
        }
    }
}

$userInitial = strtoupper(mb_substr($user['name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus — My Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/tokens.css?v=<?= @filemtime(__DIR__.'/assets/css/tokens.css') ?>">
    <script src="assets/js/theme.js?v=<?= @filemtime(__DIR__.'/assets/js/theme.js') ?>"></script>
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?= @filemtime(__DIR__.'/assets/css/sidebar.css') ?>">
    <style>
        :root {
            --primary:#A83535; --secondary:#F4A261; --accent:#F1EDE8;
            --background:#FAFAFA; --text-primary:#2E2E2E; --text-secondary:#707070;
            --border:#E0E0E0; --white:#FFFFFF;
            --sidebar-width:260px; --card-shadow:0 4px 12px rgba(0,0,0,0.05);
        }
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',system-ui,sans-serif;}
        body{background:var(--background);color:var(--text-primary);min-height:100vh;display:flex;}

        /* ── Main ── */
        .main-content{flex-grow:1;margin-left:var(--sidebar-width);min-height:100vh;display:flex;flex-direction:column;}
        .top-header{background:var(--white);padding:20px 30px;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:10;display:flex;justify-content:space-between;align-items:center;}
        .page-title{font-size:24px;font-weight:700;}
        .page-subtitle{font-size:14px;color:var(--text-secondary);margin-top:4px;}
        .content-wrap{padding:28px 30px;flex-grow:1;display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;}

        /* ── Card ── */
        .card{background:var(--white);border-radius:12px;border:1px solid var(--border);box-shadow:var(--card-shadow);}
        .card-header{padding:18px 24px;border-bottom:1px solid var(--border);background:var(--primary-tint-subtle);}
        .card-title{font-size:16px;font-weight:700;color:var(--primary);display:flex;align-items:center;gap:10px;}
        .card-body{padding:24px;}

        /* ── Avatar block ── */
        .avatar-block{display:flex;align-items:center;gap:16px;padding:20px 24px;border-bottom:1px solid var(--border);background:var(--primary-tint-subtle);}
        .avatar-lg{width:64px;height:64px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;color:var(--on-primary);font-size:26px;font-weight:700;flex-shrink:0;}
        .avatar-info .name{font-size:18px;font-weight:700;}
        .avatar-info .id{font-family:monospace;font-size:12px;color:var(--text-secondary);margin-top:3px;}
        .avatar-info .since{font-size:12px;color:var(--text-secondary);margin-top:2px;}

        /* ── Form ── */
        .form-group{margin-bottom:18px;}
        .form-label{display:block;font-weight:600;font-size:13px;margin-bottom:7px;color:var(--text-primary);}
        .form-input{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;background:var(--accent);color:var(--text-primary);transition:all 0.2s;}
        .form-input:focus{outline:none;border-color:var(--primary);background:var(--white);box-shadow:0 0 0 3px var(--primary-tint-light);}
        .form-input[readonly]{background:#f3f4f6;color:var(--text-secondary);cursor:not-allowed;}
        .input-hint{font-size:12px;color:var(--text-secondary);margin-top:5px;}

        /* ── Password strength ── */
        .strength-bar{height:4px;border-radius:2px;background:var(--border);margin-top:8px;overflow:hidden;}
        .strength-fill{height:100%;width:0;border-radius:2px;transition:width 0.3s,background 0.3s;}
        .strength-label{font-size:11px;margin-top:4px;font-weight:600;}

        /* ── Buttons ── */
        .btn{padding:11px 24px;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:8px;transition:all 0.2s;}
        .btn-primary{background:var(--primary);color:var(--on-primary);}
        .btn-primary:hover{background:var(--primary-dark);}
        .btn-block{width:100%;justify-content:center;}

        /* ── Alert ── */
        .alert{padding:13px 16px;border-radius:8px;font-size:14px;display:flex;align-items:flex-start;gap:10px;margin-bottom:18px;}
        .alert-success{background:var(--success-bg);color:var(--success);border:1px solid var(--success-border);}
        .alert-error{background:var(--danger-bg);color:var(--danger);border:1px solid var(--danger);}

        /* ── Info row (read-only fields) ── */
        .info-row{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--border);}
        .info-row:last-child{border-bottom:none;}
        .info-label{font-size:13px;color:var(--text-secondary);font-weight:500;}
        .info-value{font-size:13px;font-weight:600;color:var(--text-primary);}
        .role-pill{padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:var(--primary-tint-light);color:var(--primary);}
        .status-pill{padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:var(--success-bg);color:var(--success);}

        /* ── Footer ── */
        .page-footer{grid-column:span 2;text-align:center;padding:16px;color:var(--text-secondary);font-size:13px;border-top:1px solid var(--border);background:var(--white);}

        @media(max-width:768px){
            .content-wrap{grid-template-columns:1fr;}
            .page-footer{grid-column:span 1;}
        }
    </style>
</head>
<body>

<?php include 'c_sidebar.php'; ?>

<main class="main-content">
    <header class="top-header">
        <div>
            <h1 class="page-title">My Profile</h1>
            <p class="page-subtitle">Manage your account details and password</p>
        </div>
        <button type="button" class="theme-toggle-header-btn" data-theme-cycle title="Theme" aria-label="Theme"><i class="fas fa-sun"></i></button>
    </header>
    <script>if (window.initThemeToggle) initThemeToggle();</script>

    <div class="content-wrap">

        <!-- ── Left column: Profile info + Account details ── -->
        <div style="display:flex;flex-direction:column;gap:24px;">

            <?php if ($message): ?>
            <div class="alert alert-<?= $msgType === 'success' ? 'success' : 'error' ?>">
                <i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-circle' ?>" style="flex-shrink:0;margin-top:2px;"></i>
                <div><?= htmlspecialchars($message) ?></div>
            </div>
            <?php endif; ?>

            <!-- Profile info card -->
            <div class="card">
                <div class="avatar-block">
                    <div class="avatar-lg"><?= htmlspecialchars($userInitial) ?></div>
                    <div class="avatar-info">
                        <div class="name"><?= htmlspecialchars($user['name']) ?></div>
                        <div class="id"><?= htmlspecialchars($user['user_id']) ?></div>
                        <div class="since">Member since <?= date('d M Y', strtotime($user['registration_date'])) ?></div>
                    </div>
                </div>

                <div class="card-header">
                    <div class="card-title"><i class="fas fa-user-edit"></i> Edit Profile</div>
                </div>

                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-input"
                                value="<?= htmlspecialchars($user['name']) ?>"
                                required minlength="2">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-input"
                                value="<?= htmlspecialchars($user['email']) ?>"
                                readonly>
                            <div class="input-hint"><i class="fas fa-lock" style="margin-right:4px;"></i>Email can't be changed here. Contact support if you need it updated.</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone_number" class="form-input"
                                value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>"
                                placeholder="e.g. 0123456789">
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </form>
                </div>
            </div>

            <!-- Account details (read-only) -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-id-card"></i> Account Details</div>
                </div>
                <div class="card-body">
                    <div class="info-row">
                        <span class="info-label">Account ID</span>
                        <span class="info-value" style="font-family:monospace;font-size:12px;"><?= htmlspecialchars($user['user_id']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Role</span>
                        <span class="role-pill"><?= ucfirst(strtolower($user['user_role'])) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Account Status</span>
                        <span class="status-pill"><i class="fas fa-circle" style="font-size:8px;"></i> <?= ucfirst(strtolower($user['account_status'])) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Registered</span>
                        <span class="info-value"><?= date('d M Y, g:i A', strtotime($user['registration_date'])) ?></span>
                    </div>
                </div>
            </div>

        </div>

        <!-- ── Right column: Change password ── -->
        <div>
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-lock"></i> Change Password</div>
                </div>
                <div class="card-body">
                    <form method="POST" id="pwForm">
                        <input type="hidden" name="action" value="change_password">

                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <div style="position:relative;">
                                <input type="password" name="current_password" id="currentPw"
                                    class="form-input" required
                                    style="padding-right:42px;">
                                <button type="button" onclick="togglePw('currentPw',this)"
                                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-secondary);cursor:pointer;font-size:15px;">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <div style="position:relative;">
                                <input type="password" name="new_password" id="newPw"
                                    class="form-input" required
                                    style="padding-right:42px;"
                                    oninput="checkStrength(this.value)">
                                <button type="button" onclick="togglePw('newPw',this)"
                                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-secondary);cursor:pointer;font-size:15px;">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                            <div class="strength-label" id="strengthLabel" style="color:var(--text-secondary);">Enter a new password</div>
                            <div class="input-hint" style="margin-top:6px;">Min. 8 characters, must include letters and numbers.</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Confirm New Password</label>
                            <div style="position:relative;">
                                <input type="password" name="confirm_password" id="confirmPw"
                                    class="form-input" required
                                    style="padding-right:42px;"
                                    oninput="checkMatch()">
                                <button type="button" onclick="togglePw('confirmPw',this)"
                                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-secondary);cursor:pointer;font-size:15px;">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="input-hint" id="matchHint"></div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block" id="pwSubmitBtn">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </form>

                    <div style="margin-top:20px;padding:14px;background:var(--accent);border-radius:8px;border:1px solid var(--border);">
                        <div style="font-size:13px;font-weight:600;margin-bottom:8px;color:var(--text-primary);">
                            <i class="fas fa-shield-alt" style="color:var(--primary);"></i> Password tips
                        </div>
                        <ul style="font-size:12px;color:var(--text-secondary);padding-left:16px;line-height:1.9;">
                            <li>Use at least 8 characters</li>
                            <li>Mix letters and numbers</li>
                            <li>Avoid using your name or email</li>
                            <li>Don't reuse a previous password</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <footer class="page-footer">
            &copy; <?= date('Y') ?> StationaryPlus &mdash; Stationery &amp; Printing Management System
        </footer>

    </div>
</main>

<script>
// ── Show/hide password toggle ─────────────────────────────────
function togglePw(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type  = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type  = 'password';
        icon.className = 'fas fa-eye';
    }
}

// ── Password strength meter ───────────────────────────────────
function checkStrength(val) {
    const fill  = document.getElementById('strengthFill');
    const label = document.getElementById('strengthLabel');

    let score = 0;
    if (val.length >= 8)                          score++;
    if (/[A-Z]/.test(val))                        score++;
    if (/[0-9]/.test(val))                        score++;
    if (/[^A-Za-z0-9]/.test(val))                score++;
    if (val.length >= 12)                         score++;

    const levels = [
        { w:'0%',   c:'transparent',  t:'' },
        { w:'25%',  c:'var(--danger)',  t:'Weak' },
        { w:'50%',  c:'var(--warning)', t:'Fair' },
        { w:'75%',  c:'#3b82f6',        t:'Good' },
        { w:'100%', c:'var(--success)', t:'Strong' },
    ];

    const lvl = levels[Math.min(score, 4)];
    fill.style.width      = lvl.w;
    fill.style.background = lvl.c;
    label.textContent     = lvl.t;
    label.style.color     = lvl.c;

    checkMatch();
}

// ── Confirm password match hint ───────────────────────────────
function checkMatch() {
    const np   = document.getElementById('newPw').value;
    const cp   = document.getElementById('confirmPw').value;
    const hint = document.getElementById('matchHint');
    if (!cp) { hint.textContent = ''; return; }
    if (np === cp) {
        hint.textContent = '✓ Passwords match';
        hint.style.color = 'var(--success)';
    } else {
        hint.textContent = '✗ Passwords do not match';
        hint.style.color = 'var(--danger)';
    }
}
</script>
</body>
</html>