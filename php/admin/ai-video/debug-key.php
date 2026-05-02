<?php
/**
 * TEMPORARY DEBUG FILE — DELETE AFTER USE
 * Shows which API keys are loaded at runtime.
 */
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../config/ai_video.php';
requireRole(['admin']);

$runwayKey  = defined('RUNWAY_API_KEY') ? RUNWAY_API_KEY : '(not defined)';
$runwayMock = defined('ENABLE_MOCK_RUNWAY_MODE') ? (ENABLE_MOCK_RUNWAY_MODE ? 'TRUE (mock on)' : 'FALSE (live)') : '(not defined)';

$veoKey     = defined('VEO_API_KEY') ? VEO_API_KEY : '(not defined)';
$veoMock    = defined('ENABLE_MOCK_VEO_MODE') ? (ENABLE_MOCK_VEO_MODE ? 'TRUE (mock on)' : 'FALSE (live)') : '(not defined)';
$veoModel   = defined('VEO_MODEL') ? VEO_MODEL : '(not defined)';

$envVeoKey  = $_ENV['VEO_API_KEY'] ?? '(not set)';

echo '<pre>';
echo '=== RUNWAY ===' . "\n";
echo 'RUNWAY_API_KEY first 12 chars : ' . htmlspecialchars(substr($runwayKey, 0, 12)) . '...' . "\n";
echo 'RUNWAY_API_KEY length         : ' . strlen($runwayKey) . "\n";
echo 'ENABLE_MOCK_RUNWAY_MODE       : ' . $runwayMock . "\n\n";

echo '=== VEO ===' . "\n";
echo 'VEO_API_KEY first 12 chars    : ' . htmlspecialchars(substr($veoKey, 0, 12)) . '...' . "\n";
echo 'VEO_API_KEY length            : ' . strlen($veoKey) . "\n";
echo 'ENABLE_MOCK_VEO_MODE          : ' . $veoMock . "\n";
echo 'VEO_MODEL                     : ' . htmlspecialchars($veoModel) . "\n\n";

echo '=== ENV CHECK ===' . "\n";
echo '$_ENV[VEO_API_KEY] first 12   : ' . htmlspecialchars(substr($envVeoKey, 0, 12)) . '...' . "\n";
echo '$_ENV[VEO_API_KEY] length     : ' . strlen($envVeoKey) . "\n\n";

echo '=== ACTIVE PROVIDER ===' . "\n";
echo 'video_provider setting        : ' . htmlspecialchars($GLOBALS['appSettings']['video_provider'] ?? '(not set — defaults to runway)') . "\n\n";

echo 'Config file path              : ' . realpath(__DIR__ . '/../../config/ai_video.php') . "\n";
echo '</pre>';
