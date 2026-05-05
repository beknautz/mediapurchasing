<?php
/**
 * lib/dompdf-autoload.php
 *
 * Registers PSR-4 autoloading for Dompdf (extracted from dompdf-master.zip)
 * and FontLib shim classes — no Composer required.
 *
 * Usage (at the top of any file that needs Dompdf):
 *   require_once __DIR__ . '/../lib/dompdf-autoload.php';
 *   use Dompdf\Dompdf;
 *   use Dompdf\Options;
 */

// ── Cpdf (classmap) ────────────────────────────────────────────────────────
// Dompdf's CPDF adapter requires this raw class loaded before autoloading.
if (!class_exists('Cpdf', false)) {
    require_once __DIR__ . '/dompdf/lib/Cpdf.php';
}

// ── PSR-4 autoloader ────────────────────────────────────────────────────────
spl_autoload_register(function (string $class): void {

    // Dompdf namespace → lib/dompdf/src/
    if (strncmp($class, 'Dompdf\\', 7) === 0) {
        $rel  = str_replace('\\', '/', substr($class, 7));
        $file = __DIR__ . '/dompdf/src/' . $rel . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
        return;
    }

    // FontLib namespace → lib/dompdf-shims/FontLib/
    if (strncmp($class, 'FontLib\\', 8) === 0) {
        $rel  = str_replace('\\', '/', substr($class, 8));
        $file = __DIR__ . '/dompdf-shims/FontLib/' . $rel . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
        return;
    }

    // Masterminds\HTML5 — Dompdf calls this unconditionally in loadHtml()
    // regardless of isHtml5ParserEnabled. Use our DOMDocument-backed stub.
    if (strncmp($class, 'Masterminds\\', 12) === 0) {
        $rel  = str_replace('\\', '/', substr($class, 12));
        $file = __DIR__ . '/dompdf-shims/Masterminds/' . $rel . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
        return;
    }

}, true, false); // append, non-prepend
