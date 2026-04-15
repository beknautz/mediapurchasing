<?php
/**
 * config/config.php
 * Media Buying Platform — application configuration
 */

// ---------------------------------------------------------------------------
// Database constants
// ---------------------------------------------------------------------------
define('DB_HOST',    $_ENV['DB_HOST']    ?? 'localhost');
define('DB_NAME',    $_ENV['DB_NAME']    ?? 'mediabuy');
define('DB_USER',    $_ENV['DB_USER']    ?? 'root');
define('DB_PASS',    $_ENV['DB_PASS']    ?? '');
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');

// ---------------------------------------------------------------------------
// Application constants
// ---------------------------------------------------------------------------
define('APP_NAME',    'Media Buying Platform');
define('APP_VERSION', '1.0.0');

define('PAGE_SIZE',      25);
define('MAX_UPLOAD_MB',  20);
define('ALLOWED_EXTS',   serialize(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'png', 'jpg', 'jpeg', 'gif']));

// ---------------------------------------------------------------------------
// Session — start only if none is active yet
// ---------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------------------------------------------------------------------------
// Autoloader bootstrap
// ---------------------------------------------------------------------------
require_once __DIR__ . '/../bootstrap.php';

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
