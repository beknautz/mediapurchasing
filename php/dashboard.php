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

$campaignService = new CampaignService();
$prService       = new PressReleaseService();
$billingService  = new BillingService();
$approvalService = new ApprovalService();

// Stat counts
$campCounts    = $campaignService->getDashboardCounts();
$billingCounts = $billingService->getQueueCounts();
$pendingApprovals = $approvalService->getApprovals(status: 'pending');
$pendingApprovalCount = (int) ($pendingApprovals['total'] ?? 0);

$inProgress = ($campCounts['rfp_sent'] ?? 0)
            + ($campCounts['responses_in'] ?? 0)
            + ($campCounts['proposal_ready'] ?? 0)
            + ($campCounts['approved'] ?? 0)
            + ($campCounts['active'] ?? 0);

// Recent campaigns (last 7)
$recentCampaigns = $campaignService->getCampaigns(pageSize: 7);

// Recent press releases (last 5)
$recentPR = $prService->getPressReleases(pageSize: 5);

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
    <div class="d-flex gap-2">
        <a href="/press-releases/compose.php" class="btn btn-outline-primary">
            <i class="bi bi-newspaper me-1"></i>New Press Release
        </a>
        <a href="/campaigns/create.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>New Campaign
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">

    <div class="col-6 col-md-4 col-xl-2">
        <a href="/campaigns/index.php" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 border-start border-primary border-3">
            <div class="card-body text-center py-3">
                <div class="display-6 fw-bold text-primary"><?= $inProgress ?></div>
                <div class="small text-muted mt-1"><i class="bi bi-collection-play-fill me-1"></i>Campaigns Active</div>
            </div>
        </div>
        </a>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <a href="/campaigns/index.php?status=rfp_sent" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 border-start border-info border-3">
            <div class="card-body text-center py-3">
                <div class="display-6 fw-bold text-info"><?= (int)($campCounts['rfp_sent'] ?? 0) ?></div>
                <div class="small text-muted mt-1"><i class="bi bi-send me-1"></i>RFPs Out</div>
            </div>
        </div>
        </a>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <a href="/campaigns/index.php?status=responses_in" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 border-start border-success border-3">
            <div class="card-body text-center py-3">
                <div class="display-6 fw-bold text-success"><?= (int)($campCounts['responses_in'] ?? 0) ?></div>
                <div class="small text-muted mt-1"><i class="bi bi-inbox-fill me-1"></i>Responses In</div>
            </div>
        </div>
        </a>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <a href="/press-releases/index.php" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 border-start border-secondary border-3">
            <div class="card-body text-center py-3">
                <div class="display-6 fw-bold text-secondary"><?= (int)($recentPR['total'] ?? 0) ?></div>
                <div class="small text-muted mt-1"><i class="bi bi-newspaper me-1"></i>Press Releases</div>
            </div>
        </div>
        </a>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <a href="/approvals/index.php" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 border-start border-warning border-3">
            <div class="card-body text-center py-3">
                <div class="display-6 fw-bold text-warning"><?= $pendingApprovalCount ?></div>
                <div class="small text-muted mt-1"><i class="bi bi-hourglass-split me-1"></i>Pending Approvals</div>
            </div>
        </div>
        </a>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <a href="/billing/index.php?priority=urgent" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 border-start border-danger border-3">
            <div class="card-body text-center py-3">
                <div class="display-6 fw-bold text-danger"><?= (int)($billingCounts['urgent'] ?? 0) ?></div>
                <div class="small text-muted mt-1"><i class="bi bi-exclamation-triangle me-1"></i>Urgent Bills</div>
            </div>
        </div>
        </a>
    </div>

</div>

<div class="row g-4">

    <!-- Recent Campaigns -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-collection-play-fill me-2 text-primary"></i>Recent Campaigns
                </h5>
                <a href="/campaigns/index.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentCampaigns['data'])): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-collection-play fs-2 d-block mb-2 opacity-50"></i>
                    No campaigns yet. <a href="/campaigns/create.php">Create the first one</a>.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Campaign</th>
                                <th>Client</th>
                                <th class="text-center">Channels</th>
                                <th>Flight</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentCampaigns['data'] as $c):
                            $st = CampaignService::STATUSES[$c['status']] ?? ['label' => ucfirst($c['status']), 'class' => 'secondary'];
                        ?>
                        <tr style="cursor:pointer;"
                            onclick="window.location='/campaigns/view.php?id=<?= (int)$c['id'] ?>'">
                            <td>
                                <div class="fw-semibold"><?= h($c['title']) ?></div>
                                <?php if (!empty($c['market'])): ?>
                                <div class="small text-muted"><?= h($c['market']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?= h($c['client_name'] ?? '—') ?></td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border"><?= (int)$c['channel_count'] ?></span>
                                <?php if ((int)$c['responses_count'] > 0): ?>
                                <span class="badge bg-success ms-1" title="Responses received"><?= (int)$c['responses_count'] ?> <i class="bi bi-inbox-fill"></i></span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted text-nowrap">
                                <?php if (!empty($c['flight_start'])): ?>
                                    <?= h(date('M j', strtotime($c['flight_start']))) ?>
                                    <?php if (!empty($c['flight_end'])): ?>
                                        – <?= h(date('M j, Y', strtotime($c['flight_end']))) ?>
                                    <?php endif; ?>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= h($st['class']) ?>"><?= h($st['label']) ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($isBuyer): ?>
            <div class="card-footer bg-white text-center py-2">
                <a href="/campaigns/create.php" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-circle me-1"></i>New Campaign
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right column -->
    <div class="col-lg-4 d-flex flex-column gap-4">

        <!-- Recent Press Releases -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-newspaper me-2 text-primary"></i>Press Releases
                </h5>
                <a href="/press-releases/index.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentPR['data'])): ?>
                <div class="text-center text-muted py-4 small">
                    <i class="bi bi-newspaper fs-2 d-block mb-2 opacity-50"></i>
                    No press releases sent yet.
                </div>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                <?php foreach ($recentPR['data'] as $pr): ?>
                <li class="list-group-item px-3 py-2">
                    <a href="/press-releases/view.php?id=<?= (int)$pr['id'] ?>"
                       class="text-decoration-none text-dark d-flex justify-content-between align-items-start gap-2">
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="fw-semibold small text-truncate"><?= h($pr['subject']) ?></div>
                            <div class="text-muted" style="font-size:.72rem;">
                                <?= !empty($pr['sent_at']) ? h(date('M j, Y', strtotime($pr['sent_at']))) : 'Draft' ?>
                            </div>
                        </div>
                        <span class="badge bg-secondary flex-shrink-0"><?= (int)$pr['recipient_count'] ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-white text-center py-2">
                <a href="/press-releases/compose.php" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-send me-1"></i>New Press Release
                </a>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-lightning me-2 text-primary"></i>Quick Actions</h6>
            </div>
            <div class="list-group list-group-flush">
                <?php if ($isBuyer): ?>
                <a href="/campaigns/create.php" class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-3">
                    <i class="bi bi-collection-play-fill text-primary fs-5"></i>
                    <div><div class="fw-semibold small">New Campaign</div><div class="text-muted" style="font-size:.72rem;">Create and send RFPs to vendors</div></div>
                </a>
                <a href="/press-releases/compose.php" class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-3">
                    <i class="bi bi-newspaper text-info fs-5"></i>
                    <div><div class="fw-semibold small">New Press Release</div><div class="text-muted" style="font-size:.72rem;">Send to vendor groups</div></div>
                </a>
                <a href="/press-releases/templates.php" class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-3">
                    <i class="bi bi-file-earmark-text text-secondary fs-5"></i>
                    <div><div class="fw-semibold small">Manage Templates</div><div class="text-muted" style="font-size:.72rem;">Press release boilerplates</div></div>
                </a>
                <a href="/media-buys/create.php" class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-3">
                    <i class="bi bi-plus-circle text-success fs-5"></i>
                    <div><div class="fw-semibold small">New Media Buy</div><div class="text-muted" style="font-size:.72rem;">Standalone buy outside a campaign</div></div>
                </a>
                <?php endif; ?>
                <a href="/communications/compose.php" class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-3">
                    <i class="bi bi-send text-warning fs-5"></i>
                    <div><div class="fw-semibold small">Compose Email</div><div class="text-muted" style="font-size:.72rem;">Send a one-off message</div></div>
                </a>
            </div>
        </div>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
