<?php
/**
 * stock-advisor/logout.php — Clears the session and returns to stock advisor login
 */
require_once __DIR__ . '/bootstrap.php';

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

redirect('/stock-advisor/login.php');
