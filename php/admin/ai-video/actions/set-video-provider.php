<?php
/**
 * admin/ai-video/actions/set-video-provider.php
 * Saves the active video generation provider (runway or veo) to workflow_settings.
 * Called via HTMX POST — returns an HTML badge confirming the change.
 */
require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../../../config/ai_video.php';
requireRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$provider = strtolower(trim($_POST['provider'] ?? ''));
if (!in_array($provider, ['runway', 'veo'])) {
    http_response_code(400);
    echo '<span class="text-danger small">Invalid provider.</span>';
    exit;
}

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$pdo->prepare(
    'INSERT INTO workflow_settings (setting_key, setting_value, updated_at)
     VALUES (:key, :val, NOW())
     ON DUPLICATE KEY UPDATE setting_value = :val2, updated_at = NOW()'
)->execute([':key' => 'video_provider', ':val' => $provider, ':val2' => $provider]);

// Keep in-memory cache in sync for this request
$GLOBALS['appSettings']['video_provider'] = $provider;

if ($provider === 'runway') {
    $mock    = defined('ENABLE_MOCK_RUNWAY_MODE') && ENABLE_MOCK_RUNWAY_MODE;
    $label   = 'Runway Gen-4.5';
    $color   = 'primary';
    $icon    = 'bi-camera-reels';
} else {
    $mock    = defined('ENABLE_MOCK_VEO_MODE') && ENABLE_MOCK_VEO_MODE;
    $label   = 'Google Veo';
    $color   = 'danger';
    $icon    = 'bi-google';
}

$mockBadge = $mock
    ? '<span class="badge bg-warning text-dark ms-1">Mock</span>'
    : '<span class="badge bg-success ms-1">Live</span>';

echo '<span id="active-provider-badge" class="badge bg-' . $color . ' fs-6">'
   . '<i class="bi ' . $icon . ' me-1"></i>' . $label . '</span>' . $mockBadge
   . '<span class="text-success small ms-2"><i class="bi bi-check-circle-fill"></i> Saved</span>';
