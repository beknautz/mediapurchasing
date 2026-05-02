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

defined('VEO_API_KEY')       || define('VEO_API_KEY',       $_ENV['VEO_API_KEY']       ?? '');
defined('VEO_MODEL')         || define('VEO_MODEL',         $_ENV['VEO_MODEL']         ?? 'veo-2.0-generate-001');
// 'api_key' = Google AI Studio key — uses x-goog-api-key header (simplest, default)
// 'oauth'   = Vertex AI service account — uses Authorization: Bearer header
defined('VEO_AUTH_TYPE')     || define('VEO_AUTH_TYPE',     $_ENV['VEO_AUTH_TYPE']     ?? 'api_key');

defined('VIDEO_STORAGE_PATH')
    || define('VIDEO_STORAGE_PATH', $_ENV['VIDEO_STORAGE_PATH'] ?? __DIR__ . '/../uploads/ai-videos');

defined('VIDEO_PUBLIC_URL_BASE')
    || define('VIDEO_PUBLIC_URL_BASE', $_ENV['VIDEO_PUBLIC_URL_BASE'] ?? '/uploads/ai-videos');

defined('ENABLE_MOCK_VEO_MODE')
    || define('ENABLE_MOCK_VEO_MODE', (bool)($_ENV['ENABLE_MOCK_VEO_MODE'] ?? true));

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
