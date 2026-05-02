<?php
/**
 * TEMPORARY DEBUG FILE — DELETE AFTER USE
 * Shows which VEO_API_KEY is loaded at runtime.
 */
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../config/ai_video.php';
requireRole(['admin']);

$key = defined('VEO_API_KEY') ? VEO_API_KEY : '(not defined)';
$mock = defined('ENABLE_MOCK_VEO_MODE') ? (ENABLE_MOCK_VEO_MODE ? 'TRUE (mock on)' : 'FALSE (live)') : '(not defined)';
$model = defined('VEO_MODEL') ? VEO_MODEL : '(not defined)';

echo '<pre>';
echo 'VEO_API_KEY first 12 chars : ' . htmlspecialchars(substr($key, 0, 12)) . '...' . "\n";
echo 'VEO_API_KEY length         : ' . strlen($key) . "\n";
echo 'ENABLE_MOCK_VEO_MODE       : ' . $mock . "\n";
echo 'VEO_MODEL                  : ' . htmlspecialchars($model) . "\n";
echo 'Config file path           : ' . realpath(__DIR__ . '/../../config/ai_video.php') . "\n";
echo '</pre>';
