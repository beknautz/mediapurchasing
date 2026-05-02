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
$model      = defined('RUNWAY_MODEL') ? RUNWAY_MODEL : '(not defined)';

echo '<pre>';
echo 'RUNWAY_API_KEY first 12 chars : ' . htmlspecialchars(substr($runwayKey, 0, 12)) . '...' . "\n";
echo 'RUNWAY_API_KEY length         : ' . strlen($runwayKey) . "\n";
echo 'ENABLE_MOCK_RUNWAY_MODE       : ' . $runwayMock . "\n";
echo 'RUNWAY_MODEL                  : ' . htmlspecialchars($model) . "\n";
echo 'Config file path              : ' . realpath(__DIR__ . '/../../config/ai_video.php') . "\n";
echo '</pre>';
