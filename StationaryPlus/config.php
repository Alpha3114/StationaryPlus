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
