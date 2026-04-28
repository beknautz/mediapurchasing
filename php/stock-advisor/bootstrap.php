<?php
/**
 * stock-advisor/bootstrap.php
 * Extends the main platform bootstrap with stock-advisor autoloader and config.
 */
// Prepend stock-advisor/src/ so its classes (e.g. AuthService) take precedence
// over identically-named classes in the main php/src/ autoloader.
spl_autoload_register(function (string $className): void {
    $file = __DIR__ . '/src/' . $className . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
}, true, true);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/config/schwab.php';
