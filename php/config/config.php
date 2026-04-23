<?php
/**
 * config/config.php
 * Media Buying Platform — application configuration
 */

// ---------------------------------------------------------------------------
// Database constants
// ---------------------------------------------------------------------------
defined('DB_HOST')    || define('DB_HOST',    $_ENV['DB_HOST']    ?? 'mysql2-p2.ezhostingserver.com');
defined('DB_NAME')    || define('DB_NAME',    $_ENV['DB_NAME']    ?? 'mediapurchasing');
defined('DB_USER')    || define('DB_USER',    $_ENV['DB_USER']    ?? 'mediapurchasing');
defined('DB_PASS')    || define('DB_PASS',    $_ENV['DB_PASS']    ?? 'Access$1');
defined('DB_CHARSET') || define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');

// ---------------------------------------------------------------------------
// SMTP (outbound mail for transactional/system emails)
// ---------------------------------------------------------------------------
defined('SMTP_HOST')      || define('SMTP_HOST',      $_ENV['SMTP_HOST']      ?? 'mail10.ezhostingserver.com');
defined('SMTP_PORT')      || define('SMTP_PORT',      (int) ($_ENV['SMTP_PORT'] ?? 587));
defined('SMTP_USER')      || define('SMTP_USER',      $_ENV['SMTP_USER']      ?? 'noreply@enigmamarketing.com');
defined('SMTP_PASS')      || define('SMTP_PASS',      $_ENV['SMTP_PASS']      ?? 'Access$1');
defined('SMTP_FROM')      || define('SMTP_FROM',      $_ENV['SMTP_FROM']      ?? 'noreply@enigmamarketing.com');
defined('SMTP_FROM_NAME') || define('SMTP_FROM_NAME', $_ENV['SMTP_FROM_NAME'] ?? 'MediaBuy Platform');

// ---------------------------------------------------------------------------
// Schwab API (OAuth 2.0 + Market Data)
// Register your app at https://developer.schwab.com to get these values.
// ---------------------------------------------------------------------------
defined('SCHWAB_CLIENT_ID')     || define('SCHWAB_CLIENT_ID',     $_ENV['SCHWAB_CLIENT_ID']     ?? '');
defined('SCHWAB_CLIENT_SECRET') || define('SCHWAB_CLIENT_SECRET', $_ENV['SCHWAB_CLIENT_SECRET'] ?? '');
defined('SCHWAB_REDIRECT_URI')  || define('SCHWAB_REDIRECT_URI',  $_ENV['SCHWAB_REDIRECT_URI']  ?? 'https://yourdomain.com/stocks/callback.php');
defined('SCHWAB_OAUTH_BASE')    || define('SCHWAB_OAUTH_BASE',    'https://api.schwabapi.com/v1/oauth');
defined('SCHWAB_API_BASE')      || define('SCHWAB_API_BASE',      'https://api.schwabapi.com/marketdata/v1');

// ---------------------------------------------------------------------------
// Application constants
// ---------------------------------------------------------------------------
defined('APP_NAME')    || define('APP_NAME',    'Media Buying Platform');
defined('APP_VERSION') || define('APP_VERSION', '1.0.0');

defined('PAGE_SIZE')    || define('PAGE_SIZE',    25);
defined('MAX_UPLOAD_MB')|| define('MAX_UPLOAD_MB', 20);
defined('ALLOWED_EXTS') || define('ALLOWED_EXTS', serialize(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'png', 'jpg', 'jpeg', 'gif']));

// ---------------------------------------------------------------------------
// Load workflow settings from DB into $GLOBALS['appSettings']
// ---------------------------------------------------------------------------
$GLOBALS['appSettings'] = [];

try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $stmt = $pdo->query("SELECT setting_key, setting_value FROM workflow_settings");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $GLOBALS['appSettings'][$row['setting_key']] = $row['setting_value'];
        // Mirror into $_ENV as well so getenv() callers work
        $_ENV[$row['setting_key']] = $row['setting_value'];
    }

    unset($pdo, $stmt, $rows, $row);
} catch (Throwable $e) {
    // Settings table may not exist yet during install — fail silently
    // but record the error so developers can diagnose problems.
    error_log('[MediaBuy] config.php: could not load workflow_settings — ' . $e->getMessage());
}
