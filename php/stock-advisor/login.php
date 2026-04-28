<?php
/**
 * stock-advisor/login.php — Stock Advisor dedicated login page
 */
require_once __DIR__ . '/bootstrap.php';

if (!empty($_SESSION['loggedIn'])) {
    redirect('/stock-advisor/stocks/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password =      $_POST['password'] ?? '';

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
                'name'  => $user['full_name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ];
            $_SESSION['role'] = $user['role'];
            redirect('/stock-advisor/stocks/index.php');
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Advisor — Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background: #0a0f1e;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: #111827;
            border: 1px solid #1f2d40;
            border-radius: 1rem;
        }
        .ticker-bar {
            background: #0d1526;
            border-radius: .5rem;
            font-family: 'Courier New', monospace;
            font-size: .75rem;
            overflow: hidden;
            white-space: nowrap;
        }
        .ticker-item { display: inline-block; padding: .4rem 1.2rem; }
        .ticker-item.up   { color: #22c55e; }
        .ticker-item.down { color: #ef4444; }
        .form-control-dark {
            background: #0d1526;
            border-color: #1f2d40;
            color: #e2e8f0;
        }
        .form-control-dark:focus {
            background: #0d1526;
            border-color: #3b82f6;
            color: #e2e8f0;
            box-shadow: 0 0 0 .2rem rgba(59,130,246,.25);
        }
        .form-control-dark::placeholder { color: #4b5563; }
        .btn-signin {
            background: linear-gradient(135deg, #1d4ed8, #2563eb);
            border: none;
            font-weight: 600;
            letter-spacing: .03em;
        }
        .btn-signin:hover { background: linear-gradient(135deg, #1e40af, #1d4ed8); }
        .logo-icon {
            width: 56px; height: 56px;
            background: linear-gradient(135deg, #1d4ed8, #0ea5e9);
            border-radius: .75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-sm-10 col-md-7 col-lg-5 col-xl-4">

            <!-- Ticker bar -->
            <div class="ticker-bar mb-4 px-2 py-1">
                <span class="ticker-item up">▲ AAPL +1.24%</span>
                <span class="ticker-item down">▼ MSFT −0.38%</span>
                <span class="ticker-item up">▲ SPY +0.61%</span>
                <span class="ticker-item up">▲ QQQ +0.87%</span>
                <span class="ticker-item down">▼ TSLA −2.15%</span>
                <span class="ticker-item up">▲ NVDA +3.42%</span>
            </div>

            <div class="login-card p-4 p-sm-5 shadow-lg">

                <!-- Logo -->
                <div class="text-center mb-4">
                    <div class="logo-icon mx-auto mb-3">
                        <i class="bi bi-graph-up-arrow fs-3 text-white"></i>
                    </div>
                    <h1 class="h4 fw-bold text-white mb-1">Stock Advisor</h1>
                    <p class="text-secondary small mb-0">Daily BUY · SELL · HOLD recommendations</p>
                </div>

                <?php if ($error !== ''): ?>
                <div class="alert alert-danger py-2 small border-0" style="background:#2d1515;color:#fca5a5;">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i><?= h($error) ?>
                </div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">Email address</label>
                        <input type="email" name="email" class="form-control form-control-dark"
                               placeholder="you@example.com" autocomplete="email"
                               value="<?= h($_POST['email'] ?? '') ?>" required autofocus>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between">
                            <label class="form-label small fw-semibold text-secondary">Password</label>
                            <a href="/auth/forgot_password.php" class="small text-primary text-decoration-none">Forgot password?</a>
                        </div>
                        <input type="password" name="password" class="form-control form-control-dark"
                               placeholder="••••••••" autocomplete="current-password" required>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-signin btn-primary py-2">
                            Sign In <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </form>

                <hr class="my-4 border-secondary opacity-25">

                <p class="text-center mb-0" style="font-size:.75rem;color:#4b5563;">
                    Market data powered by
                    <span class="text-secondary fw-semibold">Schwab API</span>
                    &nbsp;·&nbsp; Recommendations are advisory only.
                </p>

            </div><!-- /.login-card -->
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
