<?php
require_once __DIR__ . '/../bootstrap.php';

if (!empty($_SESSION['loggedIn'])) {
    redirect('/dashboard.php');
}

$submitted = false;
$error     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $authService = new AuthService();
        $token       = $authService->generatePasswordReset($email);

        // Always show success to avoid disclosing whether the email exists
        if ($token !== null) {
            $resetUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                      . '://' . $_SERVER['HTTP_HOST']
                      . '/auth/reset_password.php?token=' . urlencode($token);

            $appName = APP_NAME;

            $bodyHtml = <<<HTML
<p>Hi,</p>
<p>We received a request to reset the password for your <strong>{$appName}</strong> account associated with this email address.</p>
<p><a href="{$resetUrl}" style="display:inline-block;padding:10px 20px;background:#0d6efd;color:#fff;text-decoration:none;border-radius:4px;">Reset My Password</a></p>
<p>Or copy this link into your browser:<br><a href="{$resetUrl}">{$resetUrl}</a></p>
<p>This link expires in <strong>1 hour</strong>. If you did not request a password reset, you can safely ignore this email.</p>
<p>&mdash; The {$appName} Team</p>
HTML;

            $bodyText = "Hi,\n\nReset your {$appName} password by visiting:\n{$resetUrl}\n\n"
                      . "This link expires in 1 hour. If you did not request this, ignore this email.\n";

            $emailService = new EmailService();
            $emailService->send($email, '', "Reset your {$appName} password", $bodyHtml, $bodyText);
        }

        $submitted = true;
    }
}

$pageTitle = 'Forgot Password — MediaBuy';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh;">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-sm-10 col-md-7 col-lg-5 col-xl-4">

            <div class="text-center mb-4">
                <i class="bi bi-play-btn-fill display-4 text-primary"></i>
                <h1 class="h3 mt-2 fw-bold text-dark">MediaBuy Platform</h1>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-secondary text-white text-center py-3">
                    <h5 class="mb-0"><i class="bi bi-envelope-open me-2"></i>Forgot Password</h5>
                </div>
                <div class="card-body p-4">

                    <?php if ($submitted): ?>
                        <div class="alert alert-success text-center" role="alert">
                            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                            <strong>Check your inbox.</strong><br>
                            If that email address exists in our system, a password reset link has been sent. The link expires in 1 hour.
                        </div>
                        <div class="d-grid mt-3">
                            <a href="/auth/login.php" class="btn btn-outline-primary">
                                <i class="bi bi-arrow-left me-2"></i>Back to Login
                            </a>
                        </div>

                    <?php else: ?>

                        <p class="text-muted small mb-3">
                            Enter the email address associated with your account and we will send you a password reset link.
                        </p>

                        <?php if ($error !== ''): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= h($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="/auth/forgot_password.php" novalidate>

                            <div class="mb-4">
                                <label for="email" class="form-label fw-semibold">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" id="email" name="email" class="form-control"
                                           placeholder="you@example.com"
                                           value="<?= h($_POST['email'] ?? '') ?>"
                                           autocomplete="email" required autofocus>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-send me-2"></i>Send Reset Link
                                </button>
                            </div>

                        </form>

                    <?php endif; ?>

                </div>
                <div class="card-footer text-center bg-light py-3">
                    <a href="/auth/login.php" class="text-decoration-none small">
                        <i class="bi bi-arrow-left me-1"></i>Back to Login
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
