<?php
/**
 * config/config.php
 * Media Buying Platform — application configuration
 */

// ---------------------------------------------------------------------------
// Database constants
// ---------------------------------------------------------------------------
define('DB_HOST',    $_ENV['DB_HOST']    ?? 'mysql2-p2.ezhostingserver.com');
define('DB_NAME',    $_ENV['DB_NAME']    ?? 'mediapurchasing');
define('DB_USER',    $_ENV['DB_USER']    ?? 'mediapurchasing');
define('DB_PASS',    $_ENV['DB_PASS']    ?? 'Access$1');
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');

// ---------------------------------------------------------------------------
// SMTP (outbound mail for transactional/system emails)
// ---------------------------------------------------------------------------
define('SMTP_HOST',      $_ENV['SMTP_HOST']      ?? 'mail10.ezhostingserver.com');
define('SMTP_PORT',      (int) ($_ENV['SMTP_PORT'] ?? 587));
define('SMTP_USER',      $_ENV['SMTP_USER']      ?? 'noreply@enigmamarketing.com');
define('SMTP_PASS',      $_ENV['SMTP_PASS']      ?? 'Access$1');
define('SMTP_FROM',      $_ENV['SMTP_FROM']      ?? 'noreply@enigmamarketing.com');
define('SMTP_FROM_NAME', $_ENV['SMTP_FROM_NAME'] ?? 'MediaBuy Platform');

// ---------------------------------------------------------------------------
// Application constants
// ---------------------------------------------------------------------------
define('APP_NAME',    'Media Buying Platform');
define('APP_VERSION', '1.0.0');

define('PAGE_SIZE',      25);
define('MAX_UPLOAD_MB',  20);
define('ALLOWED_EXTS',   serialize(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'png', 'jpg', 'jpeg', 'gif']));

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
