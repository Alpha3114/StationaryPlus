<?php
// ============================================================
//  index.php — Landing Page
//  If already logged in, skip straight to the right dashboard.
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['user_id']) && !empty($_SESSION['user_role'])) {
    $dashboards = [
        'ADMIN'    => 'a_dashboard.php',
        'STAFF'    => 's_dashboard.php',
        'CUSTOMER' => 'c_dashboard.php',
    ];
    $target = $dashboards[$_SESSION['user_role']] ?? 'login.php';
    header('Location: ' . $target);
    exit;
}

require_once 'db.php';
require_once 'banner_slot.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StationaryPlus — Stationery &amp; Printing, Done Properly</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/tokens.css?v=<?= @filemtime(__DIR__.'/assets/css/tokens.css') ?>">
    <script src="assets/js/theme.js?v=<?= @filemtime(__DIR__.'/assets/js/theme.js') ?>"></script>
    <style>
        :root {
            --primary: #A83535;
            --primary-dark: #8b2a2a;
            --secondary: #F4A261;
            --accent: #F1EDE8;
            --background: #FAFAFA;
            --text-primary: #2E2E2E;
            --text-secondary: #707070;
            --border: #E0E0E0;
            --white: #FFFFFF;
            --stamp: #4C7A63;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--background);
            color: var(--text-primary);
            overflow-x: hidden;
        }

        .display { font-family: 'Fraunces', Georgia, serif; }
        .mono { font-family: 'IBM Plex Mono', monospace; }

        a { text-decoration: none; color: inherit; }

        :focus-visible {
            outline: 2.5px solid var(--primary);
            outline-offset: 3px;
        }

        /* ── Nav ── */
        .nav {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 22px 6vw;
            background: color-mix(in srgb, var(--background) 85%, transparent);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid transparent;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .nav.scrolled {
            border-bottom-color: var(--border);
            box-shadow: 0 2px 16px rgba(0,0,0,0.03);
        }
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 11px;
            font-family: 'Fraunces', serif;
            font-weight: 600;
            font-size: 20px;
            color: var(--primary);
        }
        .nav-brand-icon {
            width: 34px; height: 34px;
            border-radius: 8px;
            background: var(--primary);
            color: var(--on-primary);
            display: flex; align-items: center; justify-content: center;
            font-size: 15px;
            transform: rotate(-4deg);
        }
        .nav-actions { display: flex; align-items: center; gap: 22px; }
        .nav-link {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-secondary);
            transition: color 0.2s ease;
        }
        .nav-link:hover { color: var(--primary); }
        .nav-cta {
            padding: 10px 22px;
            background: var(--primary);
            color: var(--on-primary);
            border-radius: 7px;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.2s ease, transform 0.15s ease;
        }
        .nav-cta:hover { background: var(--primary-dark); transform: translateY(-1px); }
        .theme-toggle-nav { display: flex; gap: 4px; }
        .theme-toggle-nav .theme-toggle-btn { width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; background: var(--primary-tint-subtle); color: var(--text-secondary); border: 1px solid var(--border); border-radius: 7px; cursor: pointer; font-size: 12px; transition: all 0.15s; }
        .theme-toggle-nav .theme-toggle-btn:hover { background: var(--primary-tint-light); color: var(--primary); }

        /* ── Hero ── */
        .hero {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            align-items: center;
            gap: 40px;
            padding: 140px 6vw 80px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: 'IBM Plex Mono', monospace;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--stamp);
            background: rgba(76,122,99,0.08);
            border: 1px solid rgba(76,122,99,0.25);
            padding: 6px 13px;
            border-radius: 20px;
            margin-bottom: 26px;
        }
        .hero-eyebrow i { font-size: 10px; }

        .hero h1 {
            font-family: 'Fraunces', serif;
            font-weight: 600;
            font-size: clamp(38px, 4.6vw, 60px);
            line-height: 1.08;
            letter-spacing: -0.01em;
            color: var(--text-primary);
            margin-bottom: 22px;
        }
        .hero h1 em {
            font-style: italic;
            color: var(--primary);
        }

        .hero-sub {
            font-size: 17px;
            line-height: 1.65;
            color: var(--text-secondary);
            max-width: 460px;
            margin-bottom: 34px;
        }

        .hero-ctas { display: flex; align-items: center; gap: 16px; margin-bottom: 46px; flex-wrap: wrap; }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 15px 28px;
            background: var(--primary);
            color: var(--on-primary);
            border-radius: 9px;
            font-size: 15px;
            font-weight: 600;
            transition: background 0.2s ease, transform 0.15s ease, box-shadow 0.2s ease;
            box-shadow: 0 4px 14px var(--primary-tint-active);
        }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 6px 20px var(--primary-tint-active); }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 15px 26px;
            background: transparent;
            color: var(--text-primary);
            border: 1.5px solid var(--border);
            border-radius: 9px;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .btn-secondary:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-tint-subtle); }

        /* Store directory strip — literal shop-aisle signage, not decoration */
        .directory-strip {
            display: flex;
            align-items: center;
            gap: 0;
            font-family: 'IBM Plex Mono', monospace;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.04em;
            color: var(--text-secondary);
            flex-wrap: wrap;
        }
        .directory-strip span { padding: 0 14px 0 0; }
        .directory-strip span:not(:last-child) { border-right: 1px solid var(--border); margin-right: 14px; }

        /* ── Receipt stack (signature element) ── */
        .receipt-stack {
            position: relative;
            height: 520px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .receipt {
            position: absolute;
            width: 280px;
            background: var(--white);
            border-radius: 3px 3px 0 0;
            box-shadow: 0 18px 40px rgba(0,0,0,0.10), 0 2px 8px rgba(0,0,0,0.05);
            padding: 26px 24px 0;
            opacity: 0;
            transform: translateY(40px) rotate(0deg);
            animation: receiptDrop 0.7s cubic-bezier(0.2, 0.8, 0.3, 1) forwards;
        }
        .receipt::after {
            content: '';
            display: block;
            height: 14px;
            margin-top: 16px;
            background: var(--white);
            clip-path: polygon(
                0% 0%, 100% 0%,
                95% 100%, 90% 0%, 85% 100%, 80% 0%, 75% 100%, 70% 0%, 65% 100%,
                60% 0%, 55% 100%, 50% 0%, 45% 100%, 40% 0%, 35% 100%, 30% 0%,
                25% 100%, 20% 0%, 15% 100%, 10% 0%, 5% 100%, 0% 0%
            );
        }

        .receipt-1 { top: 10px; left: 50%; margin-left: -180px; transform: translateY(40px) rotate(-7deg); animation-delay: 0.15s; z-index: 1; }
        .receipt-2 { top: 40px; left: 50%; margin-left: -90px; transform: translateY(40px) rotate(4deg); animation-delay: 0.32s; z-index: 3; }
        .receipt-3 { top: 20px; left: 50%; margin-left: 10px; transform: translateY(40px) rotate(-2deg); animation-delay: 0.48s; z-index: 2; }

        @keyframes receiptDrop {
            to { opacity: 1; transform: translateY(0) rotate(var(--rot, 0deg)); }
        }
        .receipt-1 { --rot: -7deg; }
        .receipt-2 { --rot: 4deg; }
        .receipt-3 { --rot: -2deg; }

        .receipt-head {
            text-align: center;
            margin-bottom: 16px;
        }
        .receipt-head .rname {
            font-family: 'Fraunces', serif;
            font-weight: 600;
            font-size: 15px;
            color: var(--text-primary);
        }
        .receipt-head .rid {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 10px;
            color: var(--text-secondary);
            margin-top: 3px;
            letter-spacing: 0.04em;
        }

        .receipt-line {
            display: flex;
            justify-content: space-between;
            font-size: 12.5px;
            padding: 6px 0;
            border-bottom: 1px dotted var(--border);
            color: var(--text-primary);
        }
        .receipt-line span:last-child {
            font-family: 'IBM Plex Mono', monospace;
            font-weight: 500;
        }
        .receipt-total {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            font-weight: 700;
            padding: 11px 0 14px;
            color: var(--primary);
        }
        .receipt-total span:last-child { font-family: 'IBM Plex Mono', monospace; }

        .stamp-badge {
            position: absolute;
            top: 14px; right: -10px;
            width: 58px; height: 58px;
            border: 2.5px solid var(--stamp);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            transform: rotate(14deg);
            color: var(--stamp);
            font-family: 'IBM Plex Mono', monospace;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-align: center;
            line-height: 1.3;
            opacity: 0.85;
        }

        /* ── Shelf directory (feature cards) ── */
        .shelf {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 6vw 110px;
        }
        .shelf-label {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--text-secondary);
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .shelf-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .shelf-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
        }
        .shelf-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 30px 26px;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }
        .shelf-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.06);
            border-color: var(--primary-tint-active);
        }
        .shelf-icon {
            width: 46px; height: 46px;
            border-radius: 10px;
            background: var(--accent);
            color: var(--primary);
            display: flex; align-items: center; justify-content: center;
            font-size: 19px;
            margin-bottom: 18px;
        }
        .shelf-card h3 {
            font-family: 'Fraunces', serif;
            font-weight: 600;
            font-size: 18px;
            margin-bottom: 9px;
        }
        .shelf-card p {
            font-size: 14px;
            line-height: 1.6;
            color: var(--text-secondary);
        }

        /* ── Footer ── */
        .footer {
            border-top: 1px solid var(--border);
            padding: 30px 6vw;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
            color: var(--text-secondary);
            flex-wrap: wrap;
            gap: 12px;
        }
        .footer-staff-link {
            font-weight: 600;
            color: var(--text-secondary);
            transition: color 0.2s ease;
        }
        .footer-staff-link:hover { color: var(--primary); }

        /* ── Responsive ── */
        @media (max-width: 980px) {
            .hero { grid-template-columns: 1fr; padding-top: 120px; text-align: center; }
            .hero-sub { margin-left: auto; margin-right: auto; }
            .hero-ctas { justify-content: center; }
            .directory-strip { justify-content: center; }
            .receipt-stack { height: 420px; margin-top: 20px; }
            .receipt { width: 240px; }
            .receipt-1 { margin-left: -140px; }
            .receipt-2 { margin-left: -70px; }
            .receipt-3 { margin-left: 5px; }
            .shelf-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 560px) {
            .nav { padding: 16px 5vw; }
            .nav-link { display: none; }
            .hero { padding: 110px 5vw 40px; }
            .receipt-stack { height: 240px; }
            .receipt-2, .receipt-3 { display: none; }
            .receipt-1 { margin-left: -120px; }
            .shelf { padding-top: 10px; }
        }

        @media (prefers-reduced-motion: reduce) {
            .receipt { animation: none; opacity: 1; transform: translateY(0) rotate(var(--rot, 0deg)); }
            .shelf-card, .btn-primary, .btn-secondary, .nav-cta { transition: none; }
        }
    </style>
</head>
<body>

    <nav class="nav" id="nav">
        <a href="index.php" class="nav-brand">
            <div class="nav-brand-icon"><i class="fas fa-pen-nib"></i></div>
            StationaryPlus
        </a>
        <div class="nav-actions">
            <div class="theme-toggle-nav" role="group" aria-label="Theme">
                <button type="button" class="theme-toggle-btn" data-theme-cycle title="Theme" aria-label="Theme"><i class="fas fa-sun"></i></button>
            </div>
            <script>if (window.initThemeToggle) initThemeToggle();</script>
            <a href="Registration.php" class="nav-link">Create account</a>
            <a href="login.php" class="nav-cta">Log In</a>
        </div>
    </nav>

    <section class="hero">
        <div>
            <div class="hero-eyebrow"><i class="fas fa-circle"></i> Multi-branch · Online &amp; in-store</div>
            <h1 class="display">Stationery and printing,<br><em>sorted properly.</em></h1>
            <p class="hero-sub">
                Order supplies, upload print jobs, and track everything from cart to
                collection — across every StationaryPlus branch, in one place.
            </p>
            <div class="hero-ctas">
                <a href="login.php" class="btn-primary">
                    <i class="fas fa-arrow-right-to-bracket"></i> Log In
                </a>
                <a href="Registration.php" class="btn-secondary">
                    <i class="fas fa-user-plus"></i> Create an Account
                </a>
            </div>
            <div class="directory-strip">
                <span>STATIONERY</span>
                <span>PRINT JOBS</span>
                <span>PRE-ORDERS</span>
                <span>MULTI-BRANCH PICKUP</span>
            </div>
        </div>

        <div class="receipt-stack" aria-hidden="true">
            <div class="receipt receipt-1">
                <div class="receipt-head">
                    <div class="rname display">StationaryPlus</div>
                    <div class="rid">ORDER · PRE-20260702</div>
                </div>
                <div class="receipt-line"><span>A4 Paper Ream</span><span>2</span></div>
                <div class="receipt-line"><span>Gel Pens (Set)</span><span>1</span></div>
                <div class="receipt-line"><span>Spiral Binding</span><span>1</span></div>
                <div class="receipt-total"><span>Total</span><span>RM 38.50</span></div>
            </div>
            <div class="receipt receipt-2">
                <div class="stamp-badge">READY<br>FOR<br>PICKUP</div>
                <div class="receipt-head">
                    <div class="rname display">Print Job</div>
                    <div class="rid">FILE · report_final.pdf</div>
                </div>
                <div class="receipt-line"><span>Pages</span><span>24</span></div>
                <div class="receipt-line"><span>Colour</span><span>B&amp;W</span></div>
                <div class="receipt-line"><span>Binding</span><span>Staple</span></div>
                <div class="receipt-total"><span>Est. Price</span><span>RM 12.00</span></div>
            </div>
            <div class="receipt receipt-3">
                <div class="receipt-head">
                    <div class="rname display">Branch Pickup</div>
                    <div class="rid">STATIONARYPLUS · KLCC</div>
                </div>
                <div class="receipt-line"><span>Status</span><span>NEW</span></div>
                <div class="receipt-line"><span>Payment</span><span>Pending</span></div>
                <div class="receipt-line"><span>Branch</span><span>KLCC</span></div>
                <div class="receipt-total"><span>Track</span><span>→</span></div>
            </div>
        </div>
    </section>

    <section class="shelf" style="padding-bottom:0;">
        <?php render_banner_slot($conn, 'INDEX'); ?>
    </section>

    <section class="shelf">
        <div class="shelf-label">What's on the shelf</div>
        <div class="shelf-grid">
            <div class="shelf-card">
                <div class="shelf-icon"><i class="fas fa-box"></i></div>
                <h3 class="display">Browse &amp; Order</h3>
                <p>Pens, paper, filing, binding supplies and more — reserve online, pick a branch, and collect when it's ready.</p>
            </div>
            <div class="shelf-card">
                <div class="shelf-icon"><i class="fas fa-print"></i></div>
                <h3 class="display">Upload &amp; Print</h3>
                <p>Send a file, choose colour or black &amp; white, and get an instant price estimate before you commit.</p>
            </div>
            <div class="shelf-card">
                <div class="shelf-icon"><i class="fas fa-store"></i></div>
                <h3 class="display">Any Branch, One Account</h3>
                <p>Set a preferred branch or browse another for a one-off pickup — your orders follow you either way.</p>
            </div>
        </div>
    </section>

    <footer class="footer">
        &copy; <?= date('Y') ?> StationaryPlus &mdash; Stationery &amp; Printing Management System
    </footer>

    <script>
        // Nav shadow/border on scroll
        const nav = document.getElementById('nav');
        window.addEventListener('scroll', () => {
            nav.classList.toggle('scrolled', window.scrollY > 12);
        });
    </script>

</body>
</html>
