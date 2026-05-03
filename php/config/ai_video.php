<?php
/**
 * config/ai_video.php
 * AI Video Studio — configuration constants
 *
 * Copy this file to the server and fill in real values via environment variables.
 * Never commit real API keys to version control.
 */

// Claude API key + model are read from workflow_settings (Admin → Settings →
// anthropic_api_key / anthropic_model) — the same values used by Budget Planner.
// No separate key needed here; do not add one.

// -----------------------------------------------------------------------
// Runway ML — primary video generation provider
// Get your API key at: https://app.runwayml.com/settings/api-keys
// Models: gen3a_turbo (fast/default), gen3a (higher quality)
// -----------------------------------------------------------------------
defined('RUNWAY_API_KEY')  || define('RUNWAY_API_KEY',  $_ENV['RUNWAY_API_KEY']  ?? '');
defined('RUNWAY_MODEL')    || define('RUNWAY_MODEL',    $_ENV['RUNWAY_MODEL']    ?? 'gen4.5');

// ENABLE_MOCK_RUNWAY_MODE covers video, audio (TTS/SFX), and character performance.
// Set to false and provide a real RUNWAY_API_KEY to use live Runway APIs.
defined('ENABLE_MOCK_RUNWAY_MODE')
    || define('ENABLE_MOCK_RUNWAY_MODE', (bool)($_ENV['ENABLE_MOCK_RUNWAY_MODE'] ?? true));

// -----------------------------------------------------------------------
// Google Veo — Vertex AI (aiplatform.googleapis.com)
//
// DO NOT use an API key here. Veo requires OAuth2 service account auth.
// 1. Create a GCP project (personal Gmail account, outside bacebuilt-org)
// 2. Enable the Vertex AI API on that project
// 3. Create a service account with the "Vertex AI User" role
// 4. Download the JSON key and set VEO_SERVICE_ACCOUNT_JSON_PATH
// 5. Set VEO_PROJECT_ID to your GCP project ID (e.g. gen-lang-client-XXXXXXXXX)
// -----------------------------------------------------------------------
defined('VEO_PROJECT_ID')
    || define('VEO_PROJECT_ID', $_ENV['VEO_PROJECT_ID'] ?? '');

defined('VEO_SERVICE_ACCOUNT_JSON_PATH')
    || define('VEO_SERVICE_ACCOUNT_JSON_PATH', $_ENV['VEO_SERVICE_ACCOUNT_JSON_PATH'] ?? __DIR__ . '/veo-key.json');

defined('VEO_MODEL')
    || define('VEO_MODEL', $_ENV['VEO_MODEL'] ?? 'veo-2.0-generate-001');

defined('ENABLE_MOCK_VEO_MODE')
    || define('ENABLE_MOCK_VEO_MODE', (bool)($_ENV['ENABLE_MOCK_VEO_MODE'] ?? true));

defined('VIDEO_STORAGE_PATH')
    || define('VIDEO_STORAGE_PATH', $_ENV['VIDEO_STORAGE_PATH'] ?? __DIR__ . '/../uploads/ai-videos');

defined('VIDEO_PUBLIC_URL_BASE')
    || define('VIDEO_PUBLIC_URL_BASE', $_ENV['VIDEO_PUBLIC_URL_BASE'] ?? '/uploads/ai-videos');

defined('DEFAULT_PROVIDER_COST_PER_GENERATION')
    || define('DEFAULT_PROVIDER_COST_PER_GENERATION', (float)($_ENV['DEFAULT_PROVIDER_COST_PER_GENERATION'] ?? 0.35));

defined('DEFAULT_PROVIDER_COST_PER_SECOND')
    || define('DEFAULT_PROVIDER_COST_PER_SECOND', (float)($_ENV['DEFAULT_PROVIDER_COST_PER_SECOND'] ?? 0.05));

defined('ADMIN_NOTIFICATION_EMAIL')
    || define('ADMIN_NOTIFICATION_EMAIL', $_ENV['ADMIN_NOTIFICATION_EMAIL'] ?? (defined('SMTP_FROM') ? SMTP_FROM : ''));

// Ensure the video upload directory exists
if (!is_dir(VIDEO_STORAGE_PATH)) {
    @mkdir(VIDEO_STORAGE_PATH, 0755, true);
}
