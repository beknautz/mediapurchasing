<?php
/**
 * config/google.php
 * Google API credentials — fill in your values below.
 * This file is gitignored and must never be committed.
 */

// ── Google Cloud Console OAuth2 ───────────────────────────────────────────────
define('GOOGLE_CLIENT_ID',     '');   // e.g. 123456789.apps.googleusercontent.com
define('GOOGLE_CLIENT_SECRET', '');   // e.g. GOCSPX-xxxxxxxxxxxxxxx

// Must match exactly what you set in Cloud Console → Credentials → Redirect URIs
define('GOOGLE_REDIRECT_URI',  'https://media.enigmamarketing.com/ad-automation/google-callback.php');

// ── Google Ads ────────────────────────────────────────────────────────────────
define('GOOGLE_ADS_DEVELOPER_TOKEN', '');  // Google Ads → Tools → API Center
define('GOOGLE_ADS_CUSTOMER_ID',     '');  // Your agency account ID digits only e.g. 3523716554
define('GOOGLE_ADS_MANAGER_ID',      '');  // Your Manager Account ID digits only e.g. 8468743800

// ── Token storage ─────────────────────────────────────────────────────────────
define('GOOGLE_TOKENS_PATH', __DIR__ . '/google_tokens.json');
