<?php
require_once __DIR__ . '/../bootstrap.php';
requireRole(['admin', 'buyer']);

$campaignService = new CampaignService();
$crmService      = new CRMService();

$filterStatus   = trim($_GET['status']    ?? '');
$filterClientId = (int) ($_GET['client_id'] ?? 0);
$page           = max(1, (int) ($_GET['page'] ?? 1));

$result    = $campaignService->getCampaigns($filterClientId, $filterStatus, $page, 25);
$campaigns = $result['data'];
$total     = $result['total'];
$pages     = $result['pages'];

$clients = $crmService->getClients();

$statuses   = CampaignService::STATUSES;
$pageTitle  = 'Campaigns — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="bi bi-collection-play-fill me-2 text-primary"></i>Campaigns</h2>
        <p class="text-muted mb-0 small">Manage media campaigns — send RFPs to vendors and compile client proposals.</p>
    </div>
    <a href="/campaigns/create.php" class="btn btn-primary">
        <i class="bi bi-plus-circle-fill me-1"></i>New Campaign
    </a>
</div>

<!-- Filters -->
<form method="get" class="row g-2 mb-4 align-items-end">
    <div class="col-auto">
        <label class="form-label small fw-semibold">Status</label>
        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <?php foreach ($statuses as $key => $info): ?>
            <option value="<?= h($key) ?>" <?= $filterStatus === $key ? 'selected' : '' ?>>
                <?= h($info['label']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <label class="form-label small fw-semibold">Client</label>
        <select name="client_id" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All Clients</option>
            <?php foreach ($clients as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $filterClientId === (int)$c['id'] ? 'selected' : '' ?>>
                <?= h($c['company_name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if ($filterStatus || $filterClientId): ?>
    <div class="col-auto">
        <a href="/campaigns/index.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-x-circle me-1"></i>Clear
        </a>
    </div>
    <?php endif; ?>
</form>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <span class="fw-semibold text-secondary">
            <i class="bi bi-list-ul me-1"></i><?= number_format($total) ?> campaign<?= $total !== 1 ? 's' : '' ?>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Campaign</th>
                        <th>Client</th>
                        <th>Language</th>
                        <th>Flight</th>
                        <th>Budget</th>
                        <th>Channels</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($campaigns)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <i class="bi bi-collection-play fs-3 d-block mb-2 opacity-50"></i>
                            No campaigns found.
                            <a href="/campaigns/create.php" class="d-block mt-2">Create your first campaign</a>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($campaigns as $c): ?>
                    <?php
                        $statusInfo  = $statuses[$c['status']] ?? ['label' => $c['status'], 'class' => 'secondary'];
                        $flightStart = $c['flight_start'] ? date('M j', strtotime($c['flight_start'])) : '—';
                        $flightEnd   = $c['flight_end']   ? date('M j, Y', strtotime($c['flight_end'])) : '—';
                        $responded   = (int)$c['responses_count'];
                        $rfpSent     = (int)$c['rfp_sent_count'];
                        $channels    = (int)$c['channel_count'];
                    ?>
                    <tr>
                        <td>
                            <a href="/campaigns/view.php?id=<?= (int)$c['id'] ?>" class="fw-semibold text-decoration-none">
                                <?= h($c['title']) ?>
                            </a>
                            <?php if ($c['market']): ?>
                                <div class="text-muted small"><i class="bi bi-geo-alt me-1"></i><?= h($c['market']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= h($c['client_name'] ?? '—') ?></td>
                        <td>
                            <span class="badge bg-light text-dark border text-capitalize">
                                <?= h($c['language'] ?? 'both') ?>
                            </span>
                        </td>
                        <td class="small text-nowrap">
                            <?= $flightStart ?> – <?= $flightEnd ?>
                        </td>
                        <td class="text-nowrap">$<?= number_format((float)$c['total_budget'], 0) ?></td>
                        <td>
                            <?php if ($channels > 0): ?>
                                <span class="badge bg-secondary"><?= $channels ?> total</span>
                                <?php if ($rfpSent > 0): ?>
                                    <span class="badge bg-info text-dark"><?= $rfpSent ?> sent</span>
                                <?php endif; ?>
                                <?php if ($responded > 0): ?>
                                    <span class="badge bg-success"><?= $responded ?> replied</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted small">No channels</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $statusInfo['class'] ?>">
                                <?= h($statusInfo['label']) ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="/campaigns/view.php?id=<?= (int)$c['id'] ?>"
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye me-1"></i>View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($pages > 1): ?>
    <div class="card-footer bg-white text-center py-3">
        <nav>
            <ul class="pagination pagination-sm justify-content-center mb-0">
                <?php for ($p = 1; $p <= $pages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $p ?>&status=<?= urlencode($filterStatus) ?>&client_id=<?= $filterClientId ?>">
                        <?= $p ?>
                    </a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
