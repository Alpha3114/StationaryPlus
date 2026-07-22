<?php
// ============================================================
//  config.php — App configuration
//  Values come from .env — see .env.example. This is an example file, No secrets live
//  in this file, so it's safe to commit.
// ============================================================

require_once __DIR__ . '/env.php';

// Gemini model to use — get an API key from aistudio.google.com
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
define('GEMINI_MODEL', getenv('GEMINI_MODEL') ?: 'gemini-2.5-flash');
define('GEMINI_FALLBACK_MODELS', array_values(array_filter(
    array_map('trim', explode(',', getenv('GEMINI_FALLBACK_MODELS') ?: ''))
)));

define('GMAIL_ADDRESS', getenv('GMAIL_ADDRESS') ?: '');
define('GMAIL_APP_PASSWORD', getenv('GMAIL_APP_PASSWORD') ?: '');

// ── Shop payment details — shown to customers on c_payment.php ──
// Edit these to match the shop's real bank account / e-wallet QR.
define('SHOP_BANK_NAME',       getenv('SHOP_BANK_NAME')       ?: 'Maybank');
define('SHOP_ACCOUNT_NAME',    getenv('SHOP_ACCOUNT_NAME')    ?: 'StationaryPlus Sdn Bhd');
define('SHOP_ACCOUNT_NUMBER',  getenv('SHOP_ACCOUNT_NUMBER')  ?: '1234 5678 9012');
// Place the QR code image at this path (relative to StationaryPlus/) for
// the E-Wallet / Other payment method to display it.
define('SHOP_QR_IMAGE', 'assets/images/payment_qr.png');
