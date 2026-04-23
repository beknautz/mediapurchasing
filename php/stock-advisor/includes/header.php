<?php
$flash = null;
if (!empty($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}
$pageTitle = $pageTitle ?? 'Stock Advisor';
$currentUri = $_SERVER['REQUEST_URI'] ?? '';

function saNavActive(string $path): string {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return str_contains($uri, $path) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> — Stock Advisor</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .sa-navbar { background: #0a0f1e; border-bottom: 1px solid #1f2d40; }
        .sa-navbar .navbar-brand { color: #e2e8f0 !important; }
        .sa-navbar .nav-link { color: #94a3b8 !important; }
        .sa-navbar .nav-link:hover,
        .sa-navbar .nav-link.active { color: #fff !important; }
        .sa-navbar .nav-link.active { border-bottom: 2px solid #3b82f6; }
        body { background: #f1f5f9; }
    </style>
    <?php if (!empty($extraHead)) echo $extraHead; ?>
</head>
<body>

<nav class="navbar navbar-expand-lg sa-navbar mb-0">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="/stock-advisor/stocks/index.php">
            <span style="background:linear-gradient(135deg,#1d4ed8,#0ea5e9);border-radius:.4rem;padding:.2rem .45rem;">
                <i class="bi bi-graph-up-arrow text-white"></i>
            </span>
            Stock Advisor
        </a>
        <button class="navbar-toggler border-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#saNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="saNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= saNavActive('/stock-advisor/stocks/index') ?>"
                       href="/stock-advisor/stocks/index.php">
                        <i class="bi bi-speedometer2 me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= saNavActive('/stock-advisor/stocks/watchlist') ?>"
                       href="/stock-advisor/stocks/watchlist.php">
                        <i class="bi bi-list-stars me-1"></i>Watchlist
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= saNavActive('/stock-advisor/stocks/auth') ?>"
                       href="/stock-advisor/stocks/auth.php">
                        <i class="bi bi-key me-1"></i>Schwab Connect
                    </a>
                </li>
            </ul>

            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                <li class="nav-item me-3 small" style="color:#94a3b8;">
                    <i class="bi bi-person-circle me-1"></i>
                    <?= h($_SESSION['user']['name'] ?? 'User') ?>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-warning" href="/stock-advisor/logout.php">
                        <i class="bi bi-box-arrow-right me-1"></i>Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="container-fluid py-4">

<?php if ($flash): ?>
    <?php
    $flashType = $flash['type'] ?? 'info';
    $flashMsg  = $flash['message'] ?? $flash;
    if (!is_string($flashMsg)) $flashMsg = 'An unexpected error occurred.';
    ?>
    <div class="alert alert-<?= h($flashType) ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?= $flashType === 'success' ? 'check-circle' : ($flashType === 'danger' ? 'exclamation-triangle' : 'info-circle') ?>-fill me-2"></i>
        <?= h($flashMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
