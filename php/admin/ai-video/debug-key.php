<?php
/**
 * TEMPORARY DEBUG FILE — DELETE AFTER USE
 * Shows which API keys and Veo config are loaded at runtime.
 */
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../config/ai_video.php';
requireRole(['admin']);

$runwayKey  = defined('RUNWAY_API_KEY') ? RUNWAY_API_KEY : '(not defined)';
$runwayMock = defined('ENABLE_MOCK_RUNWAY_MODE') ? (ENABLE_MOCK_RUNWAY_MODE ? 'TRUE (mock on)' : 'FALSE (live)') : '(not defined)';

$veoMock      = defined('ENABLE_MOCK_VEO_MODE') ? (ENABLE_MOCK_VEO_MODE ? 'TRUE (mock on)' : 'FALSE (live)') : '(not defined)';
$veoModel     = defined('VEO_MODEL')                    ? VEO_MODEL                    : '(not defined)';
$veoProjectId = defined('VEO_PROJECT_ID')               ? VEO_PROJECT_ID               : '(not defined)';
$veoJsonPath  = defined('VEO_SERVICE_ACCOUNT_JSON_PATH') ? VEO_SERVICE_ACCOUNT_JSON_PATH : '(not defined)';
$veoJsonExists = $veoJsonPath && file_exists($veoJsonPath) ? 'YES ✓' : 'NO — file not found!';

// Construct the URL the service will actually call
$builtUrl = 'https://us-central1-aiplatform.googleapis.com/v1/projects/'
          . $veoProjectId
          . '/locations/us-central1/publishers/google/models/'
          . $veoModel
          . ':predictLongRunning';

echo '<pre>';
echo '=== RUNWAY ===' . "\n";
echo 'RUNWAY_API_KEY first 12 chars  : ' . htmlspecialchars(substr($runwayKey, 0, 12)) . '...' . "\n";
echo 'RUNWAY_API_KEY length          : ' . strlen($runwayKey) . "\n";
echo 'ENABLE_MOCK_RUNWAY_MODE        : ' . $runwayMock . "\n\n";

echo '=== VEO (Vertex AI) ===' . "\n";
echo 'ENABLE_MOCK_VEO_MODE           : ' . $veoMock . "\n";
echo 'VEO_MODEL                      : ' . htmlspecialchars($veoModel) . "\n";
echo 'VEO_PROJECT_ID                 : ' . htmlspecialchars($veoProjectId) . "\n";
echo 'VEO_SERVICE_ACCOUNT_JSON_PATH  : ' . htmlspecialchars($veoJsonPath) . "\n";
echo 'JSON file exists on disk       : ' . $veoJsonExists . "\n\n";

echo '=== CONSTRUCTED VERTEX AI URL ===' . "\n";
echo htmlspecialchars($builtUrl) . "\n\n";

echo '=== ACTIVE PROVIDER ===' . "\n";
echo 'video_provider setting         : ' . htmlspecialchars($GLOBALS['appSettings']['video_provider'] ?? '(not set — defaults to runway)') . "\n\n";

echo 'Config file path               : ' . realpath(__DIR__ . '/../../config/ai_video.php') . "\n";
echo '</pre>';
