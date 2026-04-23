<?php
/**
 * stocks/auth.php — Initiate Schwab OAuth 2.0 authorization flow
 */
require_once __DIR__ . '/../bootstrap.php';

if (empty($_SESSION['loggedIn'])) {
    redirect('/auth/login.php');
}

$schwabSvc = new SchwabApiService();

// Redirect to Schwab if credentials are configured
if (SCHWAB_CLIENT_ID === '') {
    $pageTitle = 'Schwab OAuth Setup';
    require_once __DIR__ . '/../../includes/header.php';
    ?>
    <div class="alert alert-danger mt-4">
        <h5 class="alert-heading"><i class="bi bi-x-circle-fill me-2"></i>Missing Schwab credentials</h5>
        <p class="mb-0">
            Set <code>SCHWAB_CLIENT_ID</code>, <code>SCHWAB_CLIENT_SECRET</code>, and
            <code>SCHWAB_REDIRECT_URI</code> in your environment or
            <code>php/config/config.php</code> before connecting.
        </p>
        <hr>
        <p class="mb-0 small">
            Register at <strong>developer.schwab.com</strong> to obtain your app credentials.
            Set the redirect URI to: <code><?= h(SCHWAB_REDIRECT_URI) ?></code>
        </p>
    </div>
    <?php
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Build and redirect to authorization URL
$url = $schwabSvc->getAuthorizationUrl();
header('Location: ' . $url);
exit;
