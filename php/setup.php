<?php
require_once __DIR__ . '/bootstrap.php';

// No auth required — this is the initial setup page.
// After creating the admin account the operator must delete this file.

// Block re-setup if an admin account already exists.
try {
    $__pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET),
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $__adminCount = (int) $__pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    unset($__pdo);
} catch (Throwable $__e) {
    $__adminCount = 0;
}

if ($__adminCount > 0) {
    // Setup already complete — redirect to login
    redirect('/auth/login.php');
}

$success = false;
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name            = trim($_POST['name'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($name === '') {
        $errors[] = 'Full name is required.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $authService = new AuthService();
        $result = $authService->saveUser([
            'name'      => $name,
            'email'     => $email,
            'password'  => $password,
            'role'      => 'admin',
            'is_active' => 1,
        ]);
        if ($result['success']) {
            $success = true;
        } else {
            $errors[] = $result['message'];
        }
    }
}

$pageTitle = 'Initial Setup — MediaBuy';
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
        <div class="col-sm-10 col-md-8 col-lg-6">

            <!-- Security Warning Banner -->
            <div class="alert alert-warning border-warning shadow-sm d-flex align-items-start gap-3 mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-4 text-warning flex-shrink-0 mt-1"></i>
                <div>
                    <strong>Security Warning:</strong> This setup file grants full admin access.
                    <strong>Delete <code>setup.php</code> from your server immediately after creating your account.</strong>
                    Leaving this file accessible is a critical security risk.
                </div>
            </div>

            <div class="text-center mb-4">
                <i class="bi bi-play-btn-fill display-4 text-primary"></i>
                <h1 class="h3 mt-2 fw-bold text-dark">MediaBuy Platform</h1>
                <p class="text-muted">Create your first administrator account to get started.</p>
            </div>

            <div class="card shadow border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-person-plus-fill me-2"></i>Initial Admin Setup</h5>
                </div>
                <div class="card-body p-4">

                    <?php if ($success): ?>
                        <div class="alert alert-success" role="alert">
                            <h5 class="alert-heading"><i class="bi bi-check-circle-fill me-2"></i>Account Created!</h5>
                            <p class="mb-2">The administrator account has been created successfully. You can now log in.</p>
                            <hr>
                            <p class="mb-0 fw-bold text-danger">
                                <i class="bi bi-trash-fill me-1"></i>
                                Action required: Delete <code>setup.php</code> from your server now.
                            </p>
                        </div>
                        <div class="d-grid mt-3">
                            <a href="/auth/login.php" class="btn btn-success btn-lg">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Proceed to Login
                            </a>
                        </div>

                    <?php else: ?>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger" role="alert">
                                <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Please fix the following:</strong>
                                <ul class="mb-0 mt-2">
                                    <?php foreach ($errors as $err): ?>
                                        <li><?= h($err) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="/setup.php" novalidate>

                            <div class="mb-3">
                                <label for="name" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" id="name" name="name" class="form-control"
                                           placeholder="Jane Smith"
                                           value="<?= h($_POST['name'] ?? '') ?>"
                                           required autofocus>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" id="email" name="email" class="form-control"
                                           placeholder="admin@example.com"
                                           value="<?= h($_POST['email'] ?? '') ?>"
                                           autocomplete="email" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-key"></i></span>
                                    <input type="password" id="password" name="password" class="form-control"
                                           placeholder="Minimum 8 characters"
                                           autocomplete="new-password" required>
                                </div>
                                <div class="form-text">Must be at least 8 characters long.</div>
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
                                    <i class="bi bi-shield-check me-2"></i>Create Admin Account
                                </button>
                            </div>

                        </form>

                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
