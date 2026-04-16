<?php
require_once __DIR__ . '/../bootstrap.php';

if (!empty($_SESSION['loggedIn'])) {
    redirect('/dashboard.php');
}

$token       = trim($_GET['token'] ?? '');
$authService = new AuthService();
$user        = $token !== '' ? $authService->validateResetToken($token) : null;

$error   = '';
$success = false;

if ($user === null) {
    // Invalid or expired — show a clean error page
    $pageTitle = 'Invalid Reset Link — MediaBuy';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= h($pageTitle) ?></title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    </head>
    <body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh;">
    <div class="container"><div class="row justify-content-center">
    <div class="col-sm-10 col-md-6 col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 text-center">
                <i class="bi bi-exclamation-triangle-fill text-warning display-4"></i>
                <h4 class="mt-3">Link Expired or Invalid</h4>
                <p class="text-muted">This password reset link has expired or already been used. Reset links are valid for 1 hour.</p>
                <a href="/auth/forgot_password.php" class="btn btn-primary mt-2">
                    <i class="bi bi-arrow-repeat me-2"></i>Request a New Link
                </a>
            </div>
        </div>
    </div></div></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password        = $_POST['password']         ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        if ($authService->consumeResetToken($token, $password)) {
            $success = true;
        } else {
            $error = 'This link has expired. Please request a new one.';
        }
    }
}

$pageTitle = 'Reset Password — MediaBuy';
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
                <div class="card-header bg-primary text-white text-center py-3">
                    <h5 class="mb-0"><i class="bi bi-key-fill me-2"></i>Set New Password</h5>
                </div>
                <div class="card-body p-4">

                    <?php if ($success): ?>
                        <div class="alert alert-success text-center" role="alert">
                            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                            <strong>Password updated!</strong><br>
                            You can now sign in with your new password.
                        </div>
                        <div class="d-grid mt-3">
                            <a href="/auth/login.php" class="btn btn-success btn-lg">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                            </a>
                        </div>

                    <?php else: ?>

                        <p class="text-muted small mb-3">
                            Resetting password for <strong><?= h($user['email']) ?></strong>.
                        </p>

                        <?php if ($error !== ''): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= h($error) ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="/auth/reset_password.php?token=<?= urlencode($token) ?>" novalidate>

                            <div class="mb-3">
                                <label for="password" class="form-label fw-semibold">New Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-key"></i></span>
                                    <input type="password" id="password" name="password" class="form-control"
                                           placeholder="Minimum 8 characters"
                                           autocomplete="new-password" required autofocus>
                                </div>
                                <div class="form-text">Must be at least 8 characters.</div>
                            </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                                           placeholder="Repeat your password"
                                           autocomplete="new-password" required>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-shield-check me-2"></i>Update Password
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
