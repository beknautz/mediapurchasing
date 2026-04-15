<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config/config.php';

if (empty($_SESSION['loggedIn'])) {
    redirect('/auth/login.php');
}

$role    = $_SESSION['role'] ?? '';
$userId  = $_SESSION['user']['id'] ?? 0;
$isAdmin = $role === 'admin';
$isBuyer = in_array($role, ['admin', 'buyer'], true);

$mediaBuyService = new MediaBuyService();
$billingService  = new BillingService();
$approvalService = new ApprovalService();

// Dashboard counts
$dashCounts    = $mediaBuyService->getDashboardCounts();
$billingCounts = $billingService->getQueueCounts();

// Buyer filter: buyers only see their own buys
$buyerFilter = $isAdmin ? null : (int) $userId;

// Recent media buys (last 8)
$recentBuys = $mediaBuyService->getMediaBuys(buyerId: $buyerFilter, pageSize: 8);

// Pending approvals
$pendingApprovals = $approvalService->getApprovals(status: 'pending');

$statusColors = [
    'draft'                  => 'secondary',
    'sent_to_vendor'         => 'info',
    'pending_client_approval'=> 'warning',
    'negotiating'            => 'primary',
    'client_approved'        => 'success',
    'finalized'              => 'dark',
    'cancelled'              => 'danger',
];

$statusLabels = [
    'draft'                  => 'Draft',
    'sent_to_vendor'         => 'Sent to Vendor',
    'pending_client_approval'=> 'Pending Approval',
    'negotiating'            => 'Negotiating',
    'client_approved'        => 'Client Approved',
    'finalized'              => 'Finalized',
    'cancelled'              => 'Cancelled',
];

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">
            <i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard
        </h1>
        <p class="text-muted mb-0 small">Welcome back, <?= h($_SESSION['user']['name'] ?? 'User') ?></p>
    </div>
    <?php if ($isBuyer): ?>
    <a href="/media-buys/create.php" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>New Media Buy
    </a>
    <?php endif; ?>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="display-6 fw-bold text-secondary"><?= (int)($dashCounts['drafts'] ?? 0) ?></div>
                <div class="small text-muted mt-1"><i class="bi bi-file-earmark-text me-1"></i>Drafts</div>
            </div>
            <div class="card-footer bg-secondary bg-opacity-10 text-center py-1">
                <a href="/media-buys/index.php?status=draft" class="small text-decoration-none">View all</a>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="display-6 fw-bold text-warning"><?= (int)($dashCounts['pending_client_approval'] ?? 0) ?></div>
                <div class="small text-muted mt-1"><i class="bi bi-hourglass-split me-1"></i>Awaiting Approval</div>
            </div>
            <div class="card-footer bg-warning bg-opacity-10 text-center py-1">
                <a href="/media-buys/index.php?status=pending_client_approval" class="small text-decoration-none">View all</a>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="display-6 fw-bold text-primary"><?= (int)($dashCounts['negotiating'] ?? 0) ?></div>
                <div class="small text-muted mt-1"><i class="bi bi-arrow-left-right me-1"></i>Negotiating</div>
            </div>
            <div class="card-footer bg-primary bg-opacity-10 text-center py-1">
                <a href="/media-buys/index.php?status=negotiating" class="small text-decoration-none">View all</a>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="display-6 fw-bold text-success"><?= (int)($dashCounts['client_approved'] ?? 0) ?></div>
                <div class="small text-muted mt-1"><i class="bi bi-check-circle me-1"></i>Approved</div>
            </div>
            <div class="card-footer bg-success bg-opacity-10 text-center py-1">
                <a href="/media-buys/index.php?status=client_approved" class="small text-decoration-none">View all</a>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="display-6 fw-bold text-danger"><?= (int)($billingCounts['urgent'] ?? 0) ?></div>
                <div class="small text-muted mt-1"><i class="bi bi-exclamation-triangle me-1"></i>Urgent Bills</div>
            </div>
            <div class="card-footer bg-danger bg-opacity-10 text-center py-1">
                <a href="/billing/index.php?priority=urgent" class="small text-decoration-none">View all</a>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="display-6 fw-bold text-info"><?= (int)($billingCounts['queued'] ?? 0) ?></div>
                <div class="small text-muted mt-1"><i class="bi bi-receipt me-1"></i>Bills Queued</div>
            </div>
            <div class="card-footer bg-info bg-opacity-10 text-center py-1">
                <a href="/billing/index.php" class="small text-decoration-none">View all</a>
            </div>
        </div>
    </div>

</div>

<div class="row g-4">

    <!-- Recent Media Buys -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-collection-play me-2 text-primary"></i>Recent Media Buys</h5>
                <a href="/media-buys/index.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentBuys['data'])): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                        No media buys yet. <a href="/media-buys/create.php">Create one now</a>.
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Campaign</th>
                                <th>Client</th>
                                <th>Media</th>
                                <th>Flight</th>
                                <th>Status</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentBuys['data'] as $buy): ?>
                            <?php
                            $statusColor = $statusColors[$buy['status'] ?? ''] ?? 'secondary';
                            $statusLabel = $statusLabels[$buy['status'] ?? ''] ?? ucfirst($buy['status'] ?? '');
                            ?>
                            <tr class="cursor-pointer" style="cursor:pointer;"
                                onclick="window.location='/media-buys/view.php?id=<?= (int)$buy['id'] ?>'">
                                <td>
                                    <div class="fw-semibold"><?= h($buy['title']) ?></div>
                                    <?php if (!empty($buy['market'])): ?>
                                        <div class="small text-muted"><?= h($buy['market']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= h($buy['client_name'] ?? '—') ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= h($buy['media_type'] ?? '—') ?>
                                    </span>
                                </td>
                                <td class="small text-muted">
                                    <?php if (!empty($buy['flight_start'])): ?>
                                        <?= h(date('M j', strtotime($buy['flight_start']))) ?>
                                        <?php if (!empty($buy['flight_end'])): ?>
                                            – <?= h(date('M j, Y', strtotime($buy['flight_end']))) ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $statusColor ?>">
                                        <?= h($statusLabel) ?>
                                    </span>
                                </td>
                                <td class="small text-muted">
                                    <?= !empty($buy['updated_at']) ? h(date('M j, g:ia', strtotime($buy['updated_at']))) : '—' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Pending Approvals -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-check2-square me-2 text-warning"></i>Pending Approvals</h5>
                <a href="/approvals/index.php" class="btn btn-sm btn-outline-warning">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($pendingApprovals['data'])): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-check-all fs-2 d-block mb-2"></i>
                        No pending approvals.
                    </div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                    <?php foreach ($pendingApprovals['data'] as $approval): ?>
                        <?php
                        $isExpired = !empty($approval['expires_at']) && strtotime($approval['expires_at']) < time();
                        ?>
                        <li class="list-group-item px-3 py-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="me-2 flex-grow-1 overflow-hidden">
                                    <div class="fw-semibold text-truncate">
                                        <?= h($approval['campaign_title'] ?? $approval['title'] ?? 'Untitled') ?>
                                    </div>
                                    <div class="small text-muted"><?= h($approval['client_name'] ?? '—') ?></div>
                                    <?php if (!empty($approval['expires_at'])): ?>
                                        <div class="small <?= $isExpired ? 'text-danger fw-semibold' : 'text-muted' ?>">
                                            <i class="bi bi-clock me-1"></i>
                                            <?= $isExpired ? 'Expired ' : 'Expires ' ?>
                                            <?= h(date('M j', strtotime($approval['expires_at']))) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <a href="/approvals/portal.php?token=<?= h($approval['token'] ?? '') ?>"
                                   class="btn btn-sm btn-outline-primary flex-shrink-0" target="_blank">
                                    Portal
                                </a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-lightning me-2 text-primary"></i>Quick Actions</h6>
            </div>
            <div class="list-group list-group-flush">
                <?php if ($isBuyer): ?>
                <a href="/media-buys/create.php" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                    <i class="bi bi-plus-circle text-primary"></i> New Media Buy
                </a>
                <a href="/billing/create.php" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                    <i class="bi bi-receipt text-success"></i> Log Invoice
                </a>
                <?php endif; ?>
                <a href="/communications/compose.php" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                    <i class="bi bi-send text-info"></i> Compose Email
                </a>
                <a href="/communications/index.php" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                    <i class="bi bi-chat-dots text-warning"></i> Communication Log
                </a>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
