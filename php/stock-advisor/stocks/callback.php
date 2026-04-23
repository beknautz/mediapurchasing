<?php
/**
 * stocks/callback.php — Schwab OAuth 2.0 callback handler
 * Registered as the redirect_uri in your Schwab developer app.
 */
require_once __DIR__ . '/../bootstrap.php';

if (empty($_SESSION['loggedIn'])) {
    redirect('/auth/login.php');
}

$schwabSvc = new SchwabApiService();
$pageTitle = 'Schwab Authorization';

$code  = $_GET['code']  ?? '';
$state = $_GET['state'] ?? '';
$error = $_GET['error'] ?? '';

if ($error !== '') {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Schwab authorization denied: ' . htmlspecialchars($error)];
    redirect('/stock-advisor/stocks/index.php');
}

if ($code === '' || $state === '') {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid callback — missing code or state.'];
    redirect('/stock-advisor/stocks/index.php');
}

try {
    $schwabSvc->handleCallback($code, $state);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Schwab account connected successfully!'];
} catch (Throwable $e) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'OAuth error: ' . $e->getMessage()];
}

redirect('/stock-advisor/stocks/index.php');
