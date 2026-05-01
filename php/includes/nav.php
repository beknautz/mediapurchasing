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

                <?php
                $marketingActive = str_contains($currentUri, '/campaigns')
                    || str_contains($currentUri, '/media-buys')
                    || str_contains($currentUri, '/ad-schedules')
                    || str_contains($currentUri, '/budget-planner');
                $invoicingActive = str_contains($currentUri, '/approvals')
                    || str_contains($currentUri, '/billing');
                ?>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= $marketingActive ? 'active' : '' ?>"
                       href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-megaphone me-1"></i>Marketing
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark">
                        <li>
                            <a class="dropdown-item <?= navActive('/campaigns') ?>" href="/campaigns/index.php">
                                <i class="bi bi-collection-play-fill me-2"></i>Campaigns
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= navActive('/media-buys') ?>" href="/media-buys/index.php">
                                <i class="bi bi-collection-play me-2"></i>Media Buys
                            </a>
                        </li>
                        <?php if ($isBuyer): ?>
                        <li>
                            <a class="dropdown-item <?= navActive('/ad-schedules') ?>" href="/ad-schedules/index.php">
                                <i class="bi bi-calendar3 me-2"></i>Ad Schedules
                            </a>
                        </li>
                        <?php if ($isBuyer): ?>
                        <li>
                            <a class="dropdown-item <?= navActive('/ad-automation') ?>" href="/ad-automation/index.php">
                                <i class="bi bi-robot me-2"></i>Ad Automation
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= navActive('/ad-automation/performance') ?>" href="/ad-automation/performance.php">
                                <i class="bi bi-bar-chart me-2"></i>Ad Performance
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= navActive('/ad-automation/google-settings') ?>" href="/ad-automation/google-settings.php">
                                <i class="bi bi-google me-2"></i>Google Settings
                            </a>
                        </li>
                        <?php endif; ?>
                        <li>
                            <a class="dropdown-item <?= navActive('/budget-planner') ?>" href="/budget-planner/index.php">
                                <i class="bi bi-robot me-2"></i>Budget Planner
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= navActive('/admin/ai-video') ?>" href="/admin/ai-video/index.php">
                                <i class="bi bi-camera-video-fill me-2 text-danger"></i>AI Video Studio
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= $invoicingActive ? 'active' : '' ?>"
                       href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-receipt me-1"></i>Invoicing
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark">
                        <li>
                            <a class="dropdown-item <?= navActive('/approvals') ?>" href="/approvals/index.php">
                                <i class="bi bi-check2-square me-2"></i>Approvals
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= navActive('/billing') ?>" href="/billing/index.php">
                                <i class="bi bi-receipt me-2"></i>Billing
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= navActive('/communications') ?>" href="/communications/index.php">
                        <i class="bi bi-chat-dots me-1"></i>Communications
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= navActive('/press-releases') ?>" href="/press-releases/index.php">
                        <i class="bi bi-newspaper me-1"></i>Press Releases
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= navActive('/proposals') ?>" href="/proposals/index.php">
                        <i class="bi bi-file-earmark-richtext me-1"></i>Proposals
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
