<?php
/**
 * tools/reset_pw.php
 * Emergency password reset utility — DELETE THIS FILE after use.
 * Access via POST form only (secret prevents casual access).
 */
require_once __DIR__ . '/../bootstrap.php';

// Change this secret before uploading, then delete the file after use.
define('TOOL_SECRET', 'mb-reset-2024');

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $secret   = $_POST['secret']   ?? '';
    $email    = trim(strtolower($_POST['email']    ?? ''));
    $password = $_POST['password'] ?? '';

    if ($secret !== TOOL_SECRET) {
        $message = 'Wrong secret key.';
    } elseif ($email === '' || $password === '') {
        $message = 'Email and password are required.';
    } elseif (strlen($password) < 6) {
        $message = 'Password must be at least 6 characters.';
    } else {
        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET),
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );

            // First verify the user exists and show current state
            $check = $pdo->prepare('SELECT id, email, name, role, is_active FROM users WHERE email = :email LIMIT 1');
            $check->execute([':email' => $email]);
            $user = $check->fetch();

            if (!$user) {
                $message = "No user found with email: {$email}";
            } else {
                $auth = new AuthService();
                $hash = $auth->hashPassword($password);

                $upd = $pdo->prepare('UPDATE users SET password_hash = :hash, is_active = 1 WHERE email = :email');
                $upd->execute([':hash' => $hash, ':email' => $email]);

                $success = true;
                $message = "Password updated for {$user['name']} ({$user['email']}) — role: {$user['role']}. DELETE THIS FILE NOW.";
            }
        } catch (Throwable $e) {
            $message = 'DB error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Emergency Password Reset</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh;">
<div class="container" style="max-width:480px;">

    <div class="alert alert-danger fw-bold">
        &#9888; Security: Delete <code>tools/reset_pw.php</code> immediately after use.
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= $success ? 'success' : 'warning' ?>">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <div class="card shadow-sm">
        <div class="card-header bg-danger text-white"><strong>Emergency Password Reset</strong></div>
        <div class="card-body">
            <form method="post">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Secret Key</label>
                    <input type="password" name="secret" class="form-control" required autofocus>
                    <div class="form-text">Default: <code>mb-reset-2024</code></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email Address</label>
                    <input type="email" name="email" class="form-control"
                           value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">New Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-danger btn-lg">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
