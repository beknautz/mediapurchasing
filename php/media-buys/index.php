<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['loggedIn'])) {
    redirect('/auth/login.php');
}

$role    = $_SESSION['role'] ?? '';
$userId  = $_SESSION['user']['id'] ?? 0;
$isAdmin = $role === 'admin';
$isBuyer = in_array($role, ['admin', 'buyer'], true);

$mediaBuyService = new MediaBuyService();

$validStatuses = ['draft','sent_to_vendor','pending_client_approval','negotiating','client_approved','finalized','cancelled'];

$statusFilter = $_GET['status'] ?? '';
if ($statusFilter && !in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = '';
}

$page     = max(1, (int)($_GET['page'] ?? 1));
$pageSize = 25;

// Buyers can only see their own buys
$buyerFilter = $isAdmin ? null : (int)$userId;

$result   = $mediaBuyService->getMediaBuys(
    status:   $statusFilter ?: null,
    buyerId:  $buyerFilter,
    page:     $page,
    pageSize: $pageSize
);

$mediaBuys = $result['data']  ?? [];
$total     = $result['total'] ?? 0;
$pages     = $result['pages'] ?? 1;

$statusColors = [
    'draft'                   => 'secondary',
    'sent_to_vendor'          => 'info',
    'pending_client_approval' => 'warning',
    'negotiating'             => 'primary',
    'client_approved'         => 'success',
    'finalized'               => 'dark',
    'cancelled'               => 'danger',
];

$statusLabels = [
    'draft'                   => 'Draft',
    'sent_to_vendor'          => 'Sent to Vendor',
    'pending_client_approval' => 'Pending Approval',
    'negotiating'             => 'Negotiating',
    'client_approved'         => 'Client Approved',
    'finalized'               => 'Finalized',
    'cancelled'               => 'Cancelled',
];

$tabs = [
    ''                        => 'All',
    'draft'                   => 'Draft',
    'sent_to_vendor'          => 'Sent to Vendor',
    'pending_client_approval' => 'Awaiting Approval',
    'negotiating'             => 'Negotiating',
    'client_approved'         => 'Approved',
    'finalized'               => 'Finalized',
    'cancelled'               => 'Cancelled',
];

function pagerUrl(int $p): string {
    $params = $_GET;
    $params['page'] = $p;
    return '?' . http_build_query($params);
}

$pageTitle = 'Media Buys';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0 fw-bold">
        <i class="bi bi-collection-play me-2 text-primary"></i>Media Buys
    </h1>
    <?php if ($isBuyer): ?>
    <a href="/media-buys/create.php" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>New Media Buy
    </a>
    <?php endif; ?>
</div>

<!-- Status Tabs -->
<ul class="nav nav-tabs mb-0">
    <?php foreach ($tabs as $val => $label): ?>
    <li class="nav-item">
        <a class="nav-link <?= $statusFilter === $val ? 'active' : '' ?>"
           href="?status=<?= urlencode($val) ?>">
            <?= h($label) ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<!-- Search + table card -->
<div class="card border-0 shadow-sm border-top-0 rounded-top-0">
    <div class="card-body border-bottom pb-2 pt-3">
        <div class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" id="searchInput" class="form-control border-start-0"
                           placeholder="Search campaigns, clients, vendors…" autocomplete="off">
                </div>
            </div>
            <div class="col-auto ms-auto text-muted small">
                <?= number_format($total) ?> result<?= $total !== 1 ? 's' : '' ?>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <?php if (empty($mediaBuys)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                No media buys found<?= $statusFilter ? ' with status <strong>' . h($statusLabels[$statusFilter] ?? $statusFilter) . '</strong>' : '' ?>.
                <?php if ($isBuyer): ?>
                    <a href="/media-buys/create.php" class="d-block mt-2">Create the first one</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="mediaBuysTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">#</th>
                        <th>Campaign</th>
                        <th>Client</th>
                        <th>Vendor</th>
                        <th>Media</th>
                        <th>Flight</th>
                        <th class="text-end">Cost</th>
                        <th>Status</th>
                        <th>Buyer</th>
                        <th>Updated</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="mediaBuysBody">
                <?php foreach ($mediaBuys as $i => $buy): ?>
                    <?php
                    $statusColor = $statusColors[$buy['status'] ?? ''] ?? 'secondary';
                    $statusLabel = $statusLabels[$buy['status'] ?? ''] ?? ucfirst($buy['status'] ?? '');
                    $rowNum      = ($page - 1) * $pageSize + $i + 1;
                    ?>
                    <tr class="searchable-row" style="cursor:pointer;"
                        onclick="window.location='/media-buys/view.php?id=<?= (int)$buy['id'] ?>'">
                        <td class="ps-3 text-muted small"><?= $rowNum ?></td>
                        <td>
                            <div class="fw-semibold"><?= h($buy['title']) ?></div>
                            <?php if (!empty($buy['market'])): ?>
                                <div class="small text-muted"><?= h($buy['market']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= h($buy['client_name'] ?? '—') ?></td>
                        <td><?= h($buy['vendor_name'] ?? '—') ?></td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                <?= h($buy['media_type'] ?? '—') ?>
                            </span>
                        </td>
                        <td class="small text-nowrap">
                            <?php if (!empty($buy['flight_start'])): ?>
                                <?= h(date('M j', strtotime($buy['flight_start']))) ?>
                                <?php if (!empty($buy['flight_end'])): ?>
                                    –<br><?= h(date('M j, Y', strtotime($buy['flight_end']))) ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap fw-semibold">
                            <?= !empty($buy['total_cost']) ? '$' . number_format((float)$buy['total_cost'], 2) : '—' ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $statusColor ?>">
                                <?= h($statusLabel) ?>
                            </span>
                        </td>
                        <td class="small"><?= h($buy['buyer_name'] ?? '—') ?></td>
                        <td class="small text-muted text-nowrap">
                            <?= !empty($buy['updated_at']) ? h(date('M j, Y', strtotime($buy['updated_at']))) : '—' ?>
                        </td>
                        <td onclick="event.stopPropagation()">
                            <a href="/media-buys/view.php?id=<?= (int)$buy['id'] ?>"
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
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
        <div class="small text-muted">
            Page <?= $page ?> of <?= $pages ?>
        </div>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= h(pagerUrl($page - 1)) ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= h(pagerUrl($p)) ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= h(pagerUrl($page + 1)) ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    const input = document.getElementById('searchInput');
    const rows  = document.querySelectorAll('#mediaBuysBody .searchable-row');
    if (!input) return;

    input.addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        rows.forEach(function (row) {
            const text = row.textContent.toLowerCase();
            row.style.display = (!q || text.includes(q)) ? '' : 'none';
        });
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
