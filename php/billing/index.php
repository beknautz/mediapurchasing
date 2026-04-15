<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['loggedIn'])) {
    redirect('/auth/login.php');
}

$billingService = new BillingService();

$statusFilter   = trim($_GET['status']   ?? '');
$priorityFilter = trim($_GET['priority'] ?? '');
$page           = max(1, (int) ($_GET['page'] ?? 1));
$pageSize       = 24; // 3-column card grid works well with multiples of 3

$validStatuses = ['waiting', 'in_progress', 'on_hold', 'completed'];
if ($statusFilter && !in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = '';
}

$queueResult = $billingService->getQueue(
    status:   $statusFilter,
    priority: $priorityFilter,
    page:     $page,
    pageSize: $pageSize
);

$bills  = $queueResult['data']  ?? [];
$total  = $queueResult['total'] ?? 0;
$pages  = $queueResult['pages'] ?? 1;

$counts = $billingService->getQueueCounts();

$statusTabs = [
    ''           => 'All',
    'waiting'    => 'Waiting',
    'in_progress'=> 'In Progress',
    'on_hold'    => 'On Hold',
    'completed'  => 'Completed',
];

$statusColors = [
    'waiting'     => 'secondary',
    'in_progress' => 'primary',
    'on_hold'     => 'warning',
    'completed'   => 'success',
];

$priorityColors = [
    'urgent' => 'danger',
    'high'   => 'orange',   // handled via inline style
    'normal' => 'primary',
    'low'    => 'secondary',
];

$priorityBadgeClass = [
    'urgent' => 'bg-danger text-white',
    'high'   => 'bg-warning text-dark',
    'normal' => 'bg-primary text-white',
    'low'    => 'bg-secondary text-white',
];

$priorityBorderClass = [
    'urgent' => 'border-danger border-start border-4',
    'high'   => 'border-warning border-start border-4',
    'normal' => '',
    'low'    => 'border-secondary border-start border-2',
];

function billingPagerUrl(int $p): string {
    $params = $_GET;
    $params['page'] = $p;
    return '?' . http_build_query($params);
}

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$isBuyer = in_array($_SESSION['role'] ?? '', ['admin', 'buyer'], true);

$pageTitle = 'Billing Queue';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-0 fw-bold">
            <i class="bi bi-receipt me-2 text-primary"></i>Billing Queue
        </h1>
        <p class="text-muted mb-0 small"><?= number_format($total) ?> invoice<?= $total !== 1 ? 's' : '' ?></p>
    </div>
    <?php if ($isBuyer): ?>
    <a href="/billing/create.php" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Log Invoice
    </a>
    <?php endif; ?>
</div>

<!-- Summary strip -->
<div class="row g-2 mb-3">
    <div class="col-auto">
        <span class="badge bg-danger fs-6 px-3 py-2">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <?= (int)($counts['urgent'] ?? 0) ?> Urgent
        </span>
    </div>
    <div class="col-auto">
        <span class="badge bg-warning text-dark fs-6 px-3 py-2">
            <?= (int)($counts['high'] ?? 0) ?> High Priority
        </span>
    </div>
    <div class="col-auto">
        <span class="badge bg-secondary fs-6 px-3 py-2">
            <?= (int)($counts['waiting'] ?? 0) ?> Waiting
        </span>
    </div>
    <div class="col-auto">
        <span class="badge bg-primary fs-6 px-3 py-2">
            <?= (int)($counts['in_progress'] ?? 0) ?> In Progress
        </span>
    </div>
</div>

<!-- Status Tabs -->
<ul class="nav nav-tabs mb-0">
    <?php foreach ($statusTabs as $val => $label): ?>
    <li class="nav-item">
        <a class="nav-link <?= $statusFilter === $val ? 'active' : '' ?>"
           href="?status=<?= urlencode($val) ?>">
            <?= h($label) ?>
            <?php if ($val !== '' && isset($counts[$val]) && $counts[$val] > 0): ?>
                <span class="badge bg-<?= $statusColors[$val] ?? 'secondary' ?> ms-1">
                    <?= (int)$counts[$val] ?>
                </span>
            <?php endif; ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<div class="card border-0 shadow-sm border-top-0 rounded-top-0">
    <div class="card-body">

        <!-- Priority filter pills -->
        <div class="d-flex flex-wrap gap-2 mb-3">
            <span class="text-muted small me-1 align-self-center">Priority:</span>
            <?php
            $priorities = ['' => 'All', 'urgent' => 'Urgent', 'high' => 'High', 'normal' => 'Normal', 'low' => 'Low'];
            foreach ($priorities as $pv => $pl):
                $activeClass = $priorityFilter === $pv ? 'active' : '';
                $params = array_merge($_GET, ['priority' => $pv, 'page' => 1]);
            ?>
            <a href="?<?= http_build_query($params) ?>"
               class="btn btn-sm btn-outline-secondary <?= $activeClass ?>">
                <?= h($pl) ?>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($bills)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
            No invoices found<?= $statusFilter ? ' with status <strong>' . h(ucwords(str_replace('_', ' ', $statusFilter))) . '</strong>' : '' ?>.
            <?php if ($isBuyer): ?>
                <a href="/billing/create.php" class="d-block mt-2">Log the first invoice</a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="row g-3">
        <?php foreach ($bills as $bill):
            $priority     = $bill['priority'] ?? 'normal';
            $bStatus      = $bill['status']   ?? 'waiting';
            $badgeClass   = $priorityBadgeClass[$priority]   ?? 'bg-secondary text-white';
            $borderClass  = $priorityBorderClass[$priority]  ?? '';
            $statusColor  = $statusColors[$bStatus]          ?? 'secondary';
            $statusLabel  = ucwords(str_replace('_', ' ', $bStatus));
            $dueDate      = $bill['due_date'] ?? null;
            $isDueOverdue = $dueDate && strtotime($dueDate) < time() && $bStatus !== 'completed';
        ?>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100 shadow-sm border-0 <?= $borderClass ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge <?= $badgeClass ?> text-uppercase small">
                            <?= h($priority) ?>
                        </span>
                        <span class="badge bg-<?= $statusColor ?> small">
                            <?= h($statusLabel) ?>
                        </span>
                    </div>

                    <h6 class="card-title fw-bold mb-1">
                        <?= h($bill['vendor_name'] ?? '—') ?>
                    </h6>

                    <?php if (!empty($bill['invoice_number'])): ?>
                    <div class="text-muted small mb-2">
                        <i class="bi bi-hash"></i><?= h($bill['invoice_number']) ?>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <div class="text-muted small">Amount</div>
                            <div class="fw-bold fs-5 text-primary">
                                <?= !empty($bill['amount']) ? '$' . number_format((float)$bill['amount'], 2) : '—' ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="text-muted small">Due</div>
                            <div class="fw-semibold <?= $isDueOverdue ? 'text-danger' : '' ?>">
                                <?php if ($dueDate): ?>
                                    <?php if ($isDueOverdue): ?>
                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                    <?php endif; ?>
                                    <?= h(date('M j, Y', strtotime($dueDate))) ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($bill['assigned_user_name'])): ?>
                    <div class="text-muted small mb-2">
                        <i class="bi bi-person me-1"></i><?= h($bill['assigned_user_name']) ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($bill['media_buy_title'])): ?>
                    <div class="text-muted small text-truncate mb-2">
                        <i class="bi bi-collection-play me-1"></i><?= h($bill['media_buy_title']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-transparent border-top py-2 text-end">
                    <a href="/billing/view.php?id=<?= (int)$bill['id'] ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-eye me-1"></i>View
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
        <div class="d-flex justify-content-between align-items-center mt-4">
            <div class="small text-muted">Page <?= $page ?> of <?= $pages ?></div>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= h(billingPagerUrl($page - 1)) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= h(billingPagerUrl($p)) ?>"><?= $p ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= h(billingPagerUrl($page + 1)) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
