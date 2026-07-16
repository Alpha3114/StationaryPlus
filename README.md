# StationaryPlus

**Stationery & Printing, Done Properly.**

A PHP/MySQL web app for a stationery & print shop, covering online ordering, print-file uploads, in-store POS, inventory, and multi-branch management across three roles: Admin, Staff, and Customer.

## Tech stack

- PHP (procedural, `mysqli`) — no framework, no build step
- MySQL / MariaDB
- Vanilla HTML/CSS/JS — CSS custom properties (`assets/css/tokens.css`) drive a Light / Dark / High Contrast theme system
- [PHPMailer](StationaryPlus/phpmailer/) for transactional email (verification, password reset)
- Google Gemini API for AI-assisted features (restock suggestions, report insights, product recommendations, print-file price estimation)
- Runs on XAMPP (Apache + MySQL) locally

## Requirements

- XAMPP (or any Apache + PHP 8+ + MySQL stack)
- A Gemini API key ([aistudio.google.com](https://aistudio.google.com)) if you want the AI-assisted features to work
- A Gmail account + [App Password](https://myaccount.google.com/apppasswords) if you want outgoing email (verification/reset links) to work

## Setup

1. **Clone into your XAMPP `htdocs`**, e.g. `C:\xampp\htdocs\FYP`, so the app is served from `http://localhost/FYP/StationaryPlus/`.
2. **Create the database.** In phpMyAdmin (or the MySQL CLI), create a database named `stationaryplus` and import your base schema (users, products, orders, payments, branches, etc.), then run the migration in [`StationaryPlus/migrations/2026_07_15_features.sql`](StationaryPlus/migrations/2026_07_15_features.sql) to add loyalty points, product discounts, the audit log, and promo banners.
3. **Copy [`StationaryPlus/.env.example`](StationaryPlus/.env.example) to `StationaryPlus/.env`** and fill in your values — this one file holds every credential the app needs:
   - `DB_HOST` / `DB_USER` / `DB_PASS` / `DB_NAME` — local database connection (leave as the defaults for a stock XAMPP install: `root` / no password / `stationaryplus`).
   - `PROD_DB_*` — production database credentials, only needed if you're deploying (used by `db_server.php`).
   - `GEMINI_API_KEY` / `GEMINI_MODEL` / `GEMINI_FALLBACK_MODELS` — from [aistudio.google.com](https://aistudio.google.com), needed for the AI-assisted features.
   - `GMAIL_ADDRESS` / `GMAIL_APP_PASSWORD` — needed for outgoing email (verification/reset links).

   `.env` is gitignored and never committed — `env.php` loads it automatically wherever it's needed (`db.php`, `db_server.php`, `config.php`), so no other file needs editing.
4. Start Apache + MySQL in the XAMPP control panel and visit `http://localhost/FYP/StationaryPlus/`.

## Roles & key features

**Customer** — browse/pre-order products, upload print jobs, checkout with online/COD payment and loyalty-point redemption, track order status, earn/view loyalty points, manage profile.

**Staff** — POS for in-store sales, inventory management (with AI-assisted restock suggestions), order & print-job fulfillment, payment verification, review uploaded print files.

**Admin** — user management, product & branch management (with discounts), promo banner management, sales/inventory reporting (with AI-generated insights), full audit log of admin/staff actions.

Shared: email verification & password reset, per-device Light/Dark/High-Contrast theming (`assets/js/theme.js`, `assets/css/tokens.css`).

## Project structure

```
StationaryPlus/
├── a_*.php              Admin pages (dashboard, users, branches, products, banners, reports, audit log)
├── c_*.php              Customer pages (dashboard, products, preorder, payment, upload, profile)
├── s_*.php              Staff pages (dashboard, POS, inventory, order management, payments, upload review)
├── *_sidebar.php         Shared per-role sidebar navigation includes
├── env.php                Loads .env into getenv()/$_ENV — no external dependencies
├── .env                   All credentials (DB, Gemini, Gmail) — gitignored, copy from .env.example
├── db.php                 MySQL connection (local, reads from .env)
├── db_server.php          MySQL connection (production, reads from .env)
├── config.php             API keys / mail constants (reads from .env)
├── audit.php, loyalty.php, pricing.php   Shared business-logic helpers
├── ai_*.php               Gemini-backed AI feature endpoints
├── mailer.php, phpmailer/  Outgoing email
├── migrations/            Incremental SQL migrations (run against an existing base schema)
├── assets/css/            tokens.css (theme variables), sidebar.css
├── assets/js/             theme.js (Light/Dark/High-Contrast toggle)
└── uploads/               Print files & payment proof uploads (gitignored)
```

## Security notes

- All credentials live in `StationaryPlus/.env`, which is gitignored and never committed — `config.php`, `db.php`, and `db_server.php` contain no secrets and are safe to commit.
- `uploads/print_files/` is also gitignored — never commit customer uploads.
