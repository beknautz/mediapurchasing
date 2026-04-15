<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['loggedIn'])) {
    redirect('/auth/login.php');
}

$emailService = new EmailService();

$typeFilter      = trim($_GET['type']      ?? '');
$directionFilter = trim($_GET['direction'] ?? '');
$page            = max(1, (int) ($_GET['page'] ?? 1));
$pageSize        = 30;

$validTypes      = ['email', 'sms'];
$validDirections = ['inbound', 'outbound'];

if ($typeFilter && !in_array($typeFilter, $validTypes, true)) {
    $typeFilter = '';
}
if ($directionFilter && !in_array($directionFilter, $validDirections, true)) {
    $directionFilter = '';
}

$result  = $emailService->getHistory(
    type:      $typeFilter,
    direction: $directionFilter,
    page:      $page,
    pageSize:  $pageSize
);

$history = $result['data']  ?? [];
$total   = $result['total'] ?? 0;
$pages   = $result['pages'] ?? 1;

$typeBadges = [
    'email' => ['class' => 'bg-primary', 'icon' => 'envelope'],
    'sms'   => ['class' => 'bg-success', 'icon' => 'chat-dots'],
];
$directionBadges = [
    'inbound'  => ['class' => 'bg-info text-dark',    'icon' => 'arrow-down-left'],
    'outbound' => ['class' => 'bg-warning text-dark',  'icon' => 'arrow-up-right'],
];
$statusColors = [
    'sent'      => 'success',
    'delivered' => 'success',
    'failed'    => 'danger',
    'bounced'   => 'danger',
    'pending'   => 'warning',
    'received'  => 'info',
];

function commPagerUrl(int $p): string {
    $params = $_GET;
    $params['page'] = $p;
    return '?' . http_build_query($params);
}

function commFilterUrl(array $changes): string {
    $params = array_merge($_GET, $changes, ['page' => 1]);
    return '?' . http_build_query($params);
}

$isBuyer = in_array($_SESSION['role'] ?? '', ['admin', 'buyer'], true);

$pageTitle = 'Communications';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">
            <i class="bi bi-chat-dots me-2 text-primary"></i>Communications
        </h1>
        <p class="text-muted mb-0 small"><?= number_format($total) ?> message<?= $total !== 1 ? 's' : '' ?></p>
    </div>
    <?php if ($isBuyer): ?>
    <a href="/communications/compose.php" class="btn btn-primary">
        <i class="bi bi-send me-1"></i>Compose
    </a>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap align-items-center gap-3">

            <div>
                <span class="text-muted small me-2">Type:</span>
                <a href="<?= h(commFilterUrl(['type' => ''])) ?>"
                   class="btn btn-sm <?= $typeFilter === '' ? 'btn-dark' : 'btn-outline-secondary' ?>">All</a>
                <a href="<?= h(commFilterUrl(['type' => 'email'])) ?>"
                   class="btn btn-sm <?= $typeFilter === 'email' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="bi bi-envelope me-1"></i>Email
                </a>
                <a href="<?= h(commFilterUrl(['type' => 'sms'])) ?>"
                   class="btn btn-sm <?= $typeFilter === 'sms' ? 'btn-success' : 'btn-outline-success' ?>">
                    <i class="bi bi-chat-dots me-1"></i>SMS
                </a>
            </div>

            <div class="vr d-none d-md-block"></div>

            <div>
                <span class="text-muted small me-2">Direction:</span>
                <a href="<?= h(commFilterUrl(['direction' => ''])) ?>"
                   class="btn btn-sm <?= $directionFilter === '' ? 'btn-dark' : 'btn-outline-secondary' ?>">All</a>
                <a href="<?= h(commFilterUrl(['direction' => 'inbound'])) ?>"
                   class="btn btn-sm <?= $directionFilter === 'inbound' ? 'btn-info text-dark' : 'btn-outline-info' ?>">
                    <i class="bi bi-arrow-down-left me-1"></i>Inbound
                </a>
                <a href="<?= h(commFilterUrl(['direction' => 'outbound'])) ?>"
                   class="btn btn-sm <?= $directionFilter === 'outbound' ? 'btn-warning text-dark' : 'btn-outline-warning' ?>">
                    <i class="bi bi-arrow-up-right me-1"></i>Outbound
                </a>
            </div>

            <?php if ($typeFilter || $directionFilter): ?>
            <a href="/communications/index.php" class="btn btn-sm btn-outline-danger ms-auto">
                <i class="bi bi-x me-1"></i>Clear Filters
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($history)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
            No communications found<?= $typeFilter || $directionFilter ? ' with the selected filters' : '' ?>.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width:80px;">Type</th>
                        <th style="width:90px;">Direction</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Subject / Content</th>
                        <th>Date</th>
                        <th style="width:90px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($history as $comm):
                    $commType  = $comm['type']      ?? 'email';
                    $commDir   = $comm['direction'] ?? 'outbound';
                    $typeBadge = $typeBadges[$commType]       ?? ['class' => 'bg-secondary', 'icon' => 'question'];
                    $dirBadge  = $directionBadges[$commDir]   ?? ['class' => 'bg-secondary', 'icon' => 'arrow-right'];
                    $commStatus= $comm['status'] ?? '';
                    $statColor = $statusColors[$commStatus] ?? 'secondary';
                ?>
                    <tr>
                        <td class="ps-3">
                            <span class="badge <?= $typeBadge['class'] ?>">
                                <i class="bi bi-<?= $typeBadge['icon'] ?> me-1"></i>
                                <?= h(strtoupper($commType)) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= $dirBadge['class'] ?>">
                                <i class="bi bi-<?= $dirBadge['icon'] ?> me-1"></i>
                                <?= h(ucfirst($commDir)) ?>
                            </span>
                        </td>
                        <td class="small">
                            <div class="text-truncate" style="max-width:150px;" title="<?= h($comm['from_email'] ?? '') ?>">
                                <?= h($comm['from_email'] ?? $comm['from_number'] ?? '—') ?>
                            </div>
                            <?php if (!empty($comm['from_name'])): ?>
                                <div class="text-muted" style="font-size:.75rem;"><?= h($comm['from_name']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="small">
                            <div class="text-truncate" style="max-width:150px;" title="<?= h($comm['to_email'] ?? '') ?>">
                                <?= h($comm['to_email'] ?? $comm['to_number'] ?? '—') ?>
                            </div>
                            <?php if (!empty($comm['to_name'])): ?>
                                <div class="text-muted" style="font-size:.75rem;"><?= h($comm['to_name']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($comm['subject'])): ?>
                                <div class="fw-semibold small text-truncate" style="max-width:280px;">
                                    <?= h($comm['subject']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($comm['body_text']) || !empty($comm['body_html'])): ?>
                                <div class="text-muted small text-truncate" style="max-width:280px;">
                                    <?= h(strip_tags(substr($comm['body_text'] ?? $comm['body_html'] ?? '', 0, 120))) ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($comm['media_buy_id'])): ?>
                                <div style="font-size:.7rem;">
                                    <a href="/media-buys/view.php?id=<?= (int)$comm['media_buy_id'] ?>">
                                        <i class="bi bi-collection-play me-1"></i>Buy #<?= (int)$comm['media_buy_id'] ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted text-nowrap">
                            <?= !empty($comm['created_at']) ? h(date('M j, Y g:ia', strtotime($comm['created_at']))) : '—' ?>
                        </td>
                        <td>
                            <?php if ($commStatus): ?>
                            <span class="badge bg-<?= $statColor ?>">
                                <?= h(ucfirst($commStatus)) ?>
                            </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($pages > 1): ?>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
        <div class="small text-muted">Page <?= $page ?> of <?= $pages ?></div>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= h(commPagerUrl($page - 1)) ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= h(commPagerUrl($p)) ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= h(commPagerUrl($page + 1)) ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
