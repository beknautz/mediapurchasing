<?php
require_once __DIR__ . '/../bootstrap.php';

// Already logged in — send to dashboard
if (!empty($_SESSION['loggedIn'])) {
    redirect('/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        $authService = new AuthService();
        $user = $authService->login($email, $password);

        if ($user) {
            session_regenerate_id(true);
            $_SESSION['loggedIn'] = true;
            $_SESSION['user']     = [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
                'phone' => $user['phone'] ?? '',
            ];
            $_SESSION['role'] = $user['role'];
            redirect('/dashboard.php');
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}

$pageTitle = 'Login — MediaBuy';
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
                <p class="text-muted small">Media Purchasing &amp; Campaign Management</p>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white text-center py-3">
                    <h5 class="mb-0"><i class="bi bi-lock-fill me-2"></i>Sign In</h5>
                </div>
                <div class="card-body p-4">

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= h($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="/auth/login.php" novalidate>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" id="email" name="email" class="form-control"
                                       placeholder="you@example.com"
                                       value="<?= h($_POST['email'] ?? '') ?>"
                                       autocomplete="email" required autofocus>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label fw-semibold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-key"></i></span>
                                <input type="password" id="password" name="password" class="form-control"
                                       placeholder="••••••••" autocomplete="current-password" required>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                            </button>
                        </div>

                    </form>
                </div>
                <div class="card-footer text-center bg-light py-3">
                    <a href="/auth/forgot_password.php" class="text-decoration-none small">
                        <i class="bi bi-question-circle me-1"></i>Forgot your password?
                    </a>
                </div>
            </div>

            <p class="text-center text-muted mt-3 small">
                &copy; <?= date('Y') ?> MediaBuy Platform
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
