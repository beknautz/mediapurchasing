<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['loggedIn'])) {
    redirect('/auth/login.php');
}

$approvalService = new ApprovalService();

$statusFilter = trim($_GET['status'] ?? '');
$page         = max(1, (int) ($_GET['page'] ?? 1));
$pageSize     = 25;

$validStatuses = ['pending', 'approved', 'revision_requested', 'rejected', 'expired'];
if ($statusFilter && !in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = '';
}

$result    = $approvalService->getApprovals(status: $statusFilter, page: $page, pageSize: $pageSize);
$approvals = $result['data']  ?? [];
$total     = $result['total'] ?? 0;
$pages     = $result['pages'] ?? 1;

$tabs = [
    ''                   => 'All',
    'pending'            => 'Pending',
    'approved'           => 'Approved',
    'revision_requested' => 'Revision Requested',
    'rejected'           => 'Rejected',
    'expired'            => 'Expired',
];

$statusColors = [
    'pending'            => 'warning',
    'approved'           => 'success',
    'revision_requested' => 'info',
    'rejected'           => 'danger',
    'expired'            => 'secondary',
];

function approvalPagerUrl(int $p): string {
    $params = $_GET;
    $params['page'] = $p;
    return '?' . http_build_query($params);
}

$pageTitle = 'Approvals';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0 fw-bold">
        <i class="bi bi-check2-square me-2 text-success"></i>Approvals
    </h1>
    <span class="text-muted small"><?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?></span>
</div>

<!-- Status Tabs -->
<ul class="nav nav-tabs mb-0">
    <?php foreach ($tabs as $val => $label): ?>
    <li class="nav-item">
        <a class="nav-link <?= $statusFilter === $val ? 'active' : '' ?>"
           href="?status=<?= urlencode($val) ?>">
            <?php if ($val !== ''): ?>
                <span class="badge bg-<?= $statusColors[$val] ?? 'secondary' ?> me-1" style="font-size:.65rem;">&nbsp;</span>
            <?php endif; ?>
            <?= h($label) ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<div class="card border-0 shadow-sm border-top-0 rounded-top-0">
    <div class="card-body p-0">
        <?php if (empty($approvals)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-check-all fs-2 d-block mb-2"></i>
                No approvals found<?= $statusFilter ? ' with status <strong>' . h(ucwords(str_replace('_', ' ', $statusFilter))) . '</strong>' : '' ?>.
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Campaign</th>
                        <th>Client</th>
                        <th>Requested</th>
                        <th>Expires</th>
                        <th>Responded</th>
                        <th>Status</th>
                        <th style="width:80px;"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($approvals as $appr):
                    $apprStatus  = $appr['status'] ?? 'pending';
                    $apprColor   = $statusColors[$apprStatus] ?? 'secondary';
                    $apprLabel   = ucwords(str_replace('_', ' ', $apprStatus));
                    $now         = time();
                    $expiresTime = !empty($appr['expires_at']) ? strtotime($appr['expires_at']) : null;
                    $isOverdue   = $expiresTime && $expiresTime < $now && $apprStatus === 'pending';
                ?>
                    <tr>
                        <td class="ps-3">
                            <div class="fw-semibold">
                                <?php if (!empty($appr['media_buy_id'])): ?>
                                <a href="/media-buys/view.php?id=<?= (int)$appr['media_buy_id'] ?>">
                                    <?= h($appr['campaign_title'] ?? $appr['title'] ?? '#' . $appr['media_buy_id']) ?>
                                </a>
                                <?php else: ?>
                                    <?= h($appr['campaign_title'] ?? $appr['title'] ?? 'Untitled') ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?= h($appr['client_name'] ?? '—') ?></td>
                        <td class="small text-muted text-nowrap">
                            <?= !empty($appr['created_at']) ? h(date('M j, Y', strtotime($appr['created_at']))) : '—' ?>
                        </td>
                        <td class="small text-nowrap <?= $isOverdue ? 'text-danger fw-semibold' : 'text-muted' ?>">
                            <?php if ($expiresTime): ?>
                                <?php if ($isOverdue): ?>
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                <?php endif; ?>
                                <?= h(date('M j, Y', $expiresTime)) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted text-nowrap">
                            <?= !empty($appr['responded_at']) ? h(date('M j, Y', strtotime($appr['responded_at']))) : '—' ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $apprColor ?>">
                                <?= h($apprLabel) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($appr['token'])): ?>
                            <a href="/approvals/portal.php?token=<?= h($appr['token']) ?>"
                               class="btn btn-sm btn-outline-primary" target="_blank" title="Open Client Portal">
                                <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (!empty($appr['response_notes'])): ?>
                    <tr class="table-light">
                        <td colspan="7" class="ps-3 py-1 small text-muted">
                            <i class="bi bi-chat-text me-1"></i>
                            <em><?= h($appr['response_notes']) ?></em>
                        </td>
                    </tr>
                    <?php endif; ?>
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
                    <a class="page-link" href="<?= h(approvalPagerUrl($page - 1)) ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= h(approvalPagerUrl($p)) ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= h(approvalPagerUrl($page + 1)) ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
