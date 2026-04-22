<?php
// Flash message handling
$flash = null;
if (!empty($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}
$pageTitle = $pageTitle ?? 'Media Purchasing Platform';
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
    <?php if (!empty($extraHead)) echo $extraHead; ?>
</head>
<body class="bg-light">

<?php require_once __DIR__ . '/nav.php'; ?>

<main class="container-fluid py-4">

<?php if ($flash): ?>
    <?php
    $flashType = $flash['type'] ?? 'info';
    $flashMsg  = $flash['message'] ?? $flash;
    if (!is_string($flashMsg)) {
        $flashMsg = 'An unexpected error occurred.';
    }
    ?>
    <div class="alert alert-<?= h($flashType) ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?= $flashType === 'success' ? 'check-circle' : ($flashType === 'danger' ? 'exclamation-triangle' : 'info-circle') ?>-fill me-2"></i>
        <?= h($flashMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
