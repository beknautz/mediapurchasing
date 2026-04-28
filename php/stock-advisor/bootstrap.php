<?php
/**
 * stock-advisor/bootstrap.php
 * Self-contained bootstrap — loads config, starts session, registers autoloader.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/schwab.php';

// ---------------------------------------------------------------------------
// Session
// ---------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------------------------------------------------------------------------
// Autoloader — src/ClassName.php
// ---------------------------------------------------------------------------
spl_autoload_register(function (string $className): void {
    $file = __DIR__ . '/src/' . $className . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
