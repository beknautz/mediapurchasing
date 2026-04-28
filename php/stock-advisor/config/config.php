<?php
/**
 * stock-advisor/config/config.php
 * Database credentials and core helper functions.
 * Override any value via environment variable or edit the defaults below.
 */

// ---------------------------------------------------------------------------
// Database
// ---------------------------------------------------------------------------
defined('DB_HOST')    || define('DB_HOST',    $_ENV['DB_HOST']    ?? 'localhost');
defined('DB_NAME')    || define('DB_NAME',    $_ENV['DB_NAME']    ?? 'your_database');
defined('DB_USER')    || define('DB_USER',    $_ENV['DB_USER']    ?? 'your_db_user');
defined('DB_PASS')    || define('DB_PASS',    $_ENV['DB_PASS']    ?? 'your_db_password');
defined('DB_CHARSET') || define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');

// ---------------------------------------------------------------------------
// Application
// ---------------------------------------------------------------------------
defined('APP_NAME')    || define('APP_NAME',    'Stock Advisor');
defined('APP_VERSION') || define('APP_VERSION', '1.0.0');
defined('PAGE_SIZE')   || define('PAGE_SIZE',   25);

// ---------------------------------------------------------------------------
// h() — XSS-safe output
// ---------------------------------------------------------------------------
if (!function_exists('h')) {
    function h($value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// ---------------------------------------------------------------------------
// redirect() — send Location header and exit
// ---------------------------------------------------------------------------
if (!function_exists('redirect')) {
    function redirect(string $url): void {
        header('Location: ' . $url);
        exit;
    }
}
