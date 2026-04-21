<?php
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$isAdmin  = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$isBuyer  = isset($_SESSION['role']) && ($_SESSION['role'] === 'buyer' || $_SESSION['role'] === 'admin');
$userName = $_SESSION['user']['name'] ?? 'User';
$userRole = $_SESSION['role'] ?? '';

function navActive(string $path): string {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return str_contains($uri, $path) ? 'active' : '';
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="/dashboard.php">
            <i class="bi bi-play-btn-fill me-2 text-primary"></i>MediaBuy
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <li class="nav-item">
                    <a class="nav-link <?= navActive('/dashboard') ?>" href="/dashboard.php">
                        <i class="bi bi-speedometer2 me-1"></i>Dashboard
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= navActive('/campaigns') ?>" href="/campaigns/index.php">
                        <i class="bi bi-collection-play-fill me-1"></i>Campaigns
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= navActive('/media-buys') ?>" href="/media-buys/index.php">
                        <i class="bi bi-collection-play me-1"></i>Media Buys
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= navActive('/approvals') ?>" href="/approvals/index.php">
                        <i class="bi bi-check2-square me-1"></i>Approvals
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= navActive('/billing') ?>" href="/billing/index.php">
                        <i class="bi bi-receipt me-1"></i>Billing
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= navActive('/communications') ?>" href="/communications/index.php">
                        <i class="bi bi-chat-dots me-1"></i>Communications
                    </a>
                </li>

                <?php if ($isBuyer): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= navActive('/admin/client') || navActive('/admin/vendor') ? 'active' : '' ?>"
                       href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-people me-1"></i>CRM
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark">
                        <li>
                            <a class="dropdown-item <?= navActive('/admin/clients') ?>" href="/admin/clients.php">
                                <i class="bi bi-building me-2"></i>Clients
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= navActive('/admin/vendors') ?>" href="/admin/vendors.php">
                                <i class="bi bi-shop me-2"></i>Vendors
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if ($isAdmin): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= str_contains($currentUri, '/admin/') ? 'active' : '' ?>"
                       href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-gear me-1"></i>Admin
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark">
                        <li>
                            <a class="dropdown-item <?= navActive('/admin/users') ?>" href="/admin/users.php">
                                <i class="bi bi-person-lines-fill me-2"></i>Users
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= navActive('/admin/clients') ?>" href="/admin/clients.php">
                                <i class="bi bi-building me-2"></i>Clients
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= navActive('/admin/vendors') ?>" href="/admin/vendors.php">
                                <i class="bi bi-shop me-2"></i>Vendors
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item <?= navActive('/admin/templates') ?>" href="/admin/templates.php">
                                <i class="bi bi-envelope-paper me-2"></i>Email Templates
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= navActive('/admin/settings') ?>" href="/admin/settings.php">
                                <i class="bi bi-sliders me-2"></i>Workflow Settings
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item <?= navActive('/admin/audit_log') ?>" href="/admin/audit_log.php">
                                <i class="bi bi-journal-text me-2"></i>Audit Log
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

            </ul>

            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                <li class="nav-item me-3 text-light d-flex align-items-center gap-2">
                    <i class="bi bi-person-circle fs-5"></i>
                    <span class="small"><?= h($userName) ?></span>
                    <?php if ($userRole): ?>
                        <span class="badge bg-<?= $userRole === 'admin' ? 'danger' : 'primary' ?> text-uppercase" style="font-size:.65rem;">
                            <?= h($userRole) ?>
                        </span>
                    <?php endif; ?>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-warning" href="/auth/logout.php">
                        <i class="bi bi-box-arrow-right me-1"></i>Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
