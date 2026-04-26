<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/google.php';
requireRole(['admin', 'buyer']);

echo '<pre>';
echo 'GOOGLE_CLIENT_ID: ' . (defined('GOOGLE_CLIENT_ID') ? (GOOGLE_CLIENT_ID ?: '(empty string)') : '(not defined)') . "\n";
echo 'GOOGLE_CLIENT_SECRET: ' . (defined('GOOGLE_CLIENT_SECRET') ? (GOOGLE_CLIENT_SECRET ? '(set, ' . strlen(GOOGLE_CLIENT_SECRET) . ' chars)' : '(empty string)') : '(not defined)') . "\n";
echo 'GOOGLE_REDIRECT_URI: ' . (defined('GOOGLE_REDIRECT_URI') ? GOOGLE_REDIRECT_URI : '(not defined)') . "\n";
echo 'GOOGLE_ADS_DEVELOPER_TOKEN: ' . (defined('GOOGLE_ADS_DEVELOPER_TOKEN') ? (GOOGLE_ADS_DEVELOPER_TOKEN ? '(set)' : '(empty)') : '(not defined)') . "\n";
echo 'GOOGLE_ADS_CUSTOMER_ID: ' . (defined('GOOGLE_ADS_CUSTOMER_ID') ? (GOOGLE_ADS_CUSTOMER_ID ?: '(empty)') : '(not defined)') . "\n";
echo '</pre>';
