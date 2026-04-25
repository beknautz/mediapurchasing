<?php
/**
 * config/google.php
 * Google API credentials — fill these in after completing OAuth setup.
 * Keep this file out of version control (add to .gitignore).
 */

// ── Google Cloud Console OAuth2 credentials ───────────────────────────────
// From: console.cloud.google.com → APIs & Services → Credentials
defined('GOOGLE_CLIENT_ID')     || define('GOOGLE_CLIENT_ID',     $_ENV['GOOGLE_CLIENT_ID']     ?? '');
defined('GOOGLE_CLIENT_SECRET') || define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? '');

// OAuth2 redirect URI — must match exactly what you set in Cloud Console
defined('GOOGLE_REDIRECT_URI')  || define('GOOGLE_REDIRECT_URI',
    $_ENV['GOOGLE_REDIRECT_URI'] ?? 'https://media.enigmamarketing.com/ad-automation/google-callback.php'
);

// ── Google Ads ─────────────────────────────────────────────────────────────
// Developer token: Google Ads → Tools → API Center
defined('GOOGLE_ADS_DEVELOPER_TOKEN') || define('GOOGLE_ADS_DEVELOPER_TOKEN', $_ENV['GOOGLE_ADS_DEVELOPER_TOKEN'] ?? '');

// Your Google Ads Customer ID (digits only, no dashes)
defined('GOOGLE_ADS_CUSTOMER_ID') || define('GOOGLE_ADS_CUSTOMER_ID', $_ENV['GOOGLE_ADS_CUSTOMER_ID'] ?? '');

// Manager/MCC account customer ID — leave blank if using a standalone account
defined('GOOGLE_ADS_MANAGER_ID') || define('GOOGLE_ADS_MANAGER_ID', $_ENV['GOOGLE_ADS_MANAGER_ID'] ?? '');

// ── Token storage ─────────────────────────────────────────────────────────
// Path to the JSON file where refresh tokens are stored after OAuth
define('GOOGLE_TOKENS_PATH', __DIR__ . '/google_tokens.json');
