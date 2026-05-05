<?php
/**
 * bootstrap.php
 * Media Buying Platform — autoloader, session, and global helpers
 */

// ---------------------------------------------------------------------------
// Temporary error display — remove after diagnosing the 500
// ---------------------------------------------------------------------------
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// ---------------------------------------------------------------------------
// Composer autoloader (Google Ads API, etc.)
// ---------------------------------------------------------------------------
$_composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($_composerAutoload)) {
    require_once $_composerAutoload;
}
unset($_composerAutoload);

// ---------------------------------------------------------------------------
// Config — DB constants and app settings (must load before anything else)
// ---------------------------------------------------------------------------
require_once __DIR__ . '/config/config.php';

// ---------------------------------------------------------------------------
// Session — start only if not already active
// ---------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------------------------------------------------------------------------
// PSR-0-style class autoloader (no namespaces — ClassName → src/ClassName.php)
// ---------------------------------------------------------------------------
spl_autoload_register(function (string $className): void {
    $file = __DIR__ . '/src/' . $className . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// ---------------------------------------------------------------------------
// h() — XSS-safe output
// ---------------------------------------------------------------------------
if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// ---------------------------------------------------------------------------
// redirect() — send Location header and exit
// ---------------------------------------------------------------------------
if (!function_exists('redirect')) {
    function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}

// ---------------------------------------------------------------------------
// requireRole() — abort with 403 if the current user lacks the required role
// ---------------------------------------------------------------------------
if (!function_exists('requireRole')) {
    /**
     * @param string|string[] $roles  One role string or an array of allowed roles.
     */
    function requireRole($roles): void
    {
        $allowed = is_array($roles) ? $roles : [$roles];

        // Must be logged in
        if (empty($_SESSION['user'])) {
            redirect('/login.php');
        }

        $userRole = $_SESSION['user']['role'] ?? '';

        if (!in_array($userRole, $allowed, true)) {
            http_response_code(403);
            echo h('Access denied. Required role: ' . implode(' or ', $allowed));
            exit;
        }
    }
}

// ---------------------------------------------------------------------------
// pdfToken() — generate a signed URL query string for agency-agreement-pdf.cfm
// ---------------------------------------------------------------------------
if (!function_exists('pdfToken')) {
    /**
     * Returns a query string like "?id=42&ts=1715000000&tok=abc123..."
     * ColdFusion verifies the HMAC before generating the PDF.
     */
    function pdfToken(int $id): string {
        $ts  = time();
        $tok = hash_hmac('sha256', $id . '|' . $ts, PDF_HMAC_SECRET);
        return '?id=' . $id . '&ts=' . $ts . '&tok=' . rawurlencode($tok);
    }
}

// ---------------------------------------------------------------------------
// flash() — store or retrieve one-time flash messages via the session
// ---------------------------------------------------------------------------
if (!function_exists('flash')) {
    /**
     * When called with two arguments: stores a flash message.
     *   flash('error', 'Something went wrong');
     *
     * When called with one argument: retrieves (and clears) the flash message
     * for that type, returning '' if none exists.
     *   $msg = flash('error');
     *
     * @param string      $type    Message type key, e.g. 'success', 'error', 'info'.
     * @param string|null $message The message to store. Omit to retrieve.
     * @return string              The stored message when retrieving, or '' when storing.
     */
    function flash(string $type, ?string $message = null): string
    {
        if ($message !== null) {
            // Store mode
            $_SESSION['_flash'][$type] = $message;
            return '';
        }

        // Retrieve-and-clear mode
        $msg = $_SESSION['_flash'][$type] ?? '';
        unset($_SESSION['_flash'][$type]);
        return $msg;
    }
}
