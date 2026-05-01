<?php
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../config/ai_video.php';
requireRole(['admin', 'buyer']);

$campaignId = (int)($_GET['campaign_id'] ?? 0);
if (!$campaignId) redirect('/admin/ai-video/index.php');

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

$campaign = $pdo->prepare('SELECT * FROM ai_video_campaigns WHERE id = :id');
$campaign->execute([':id' => $campaignId]);
$campaign = $campaign->fetch();
if (!$campaign) redirect('/admin/ai-video/index.php');

$markupPct = (float)($_GET['markup'] ?? 30.0);

$costSvc     = new AiVideoCostService();
$summary     = $costSvc->getCostSummary($campaignId, $markupPct);
$costRows    = $costSvc->getCostRows($campaignId);

$pageTitle = 'Cost Report — ' . h($campaign['campaign_name']);
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="mb-4">
    <h1 class="h3 fw-bold mb-0"><i class="bi bi-currency-dollar me-2 text-success"></i>Cost Report</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="/admin/ai-video/index.php">AI Video Studio</a></li>
            <li class="breadcrumb-item"><a href="/admin/ai-video/view-campaign.php?id=<?= (int)$campaignId ?>"><?= h($campaign['campaign_name']) ?></a></li>
            <li class="breadcrumb-item active">Cost Report</li>
        </ol>
    </nav>
</div>

<!-- Summary Cards -->
<div id="cost-summary-section">
<div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
        <div class="card border-0 shadow-sm text-center py-3 h-100">
            <div class="fs-4 fw-bold text-info">$<?= number_format($summary['claude_cost'], 4) ?></div>
            <div class="small text-muted">Claude AI Cost</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card border-0 shadow-sm text-center py-3 h-100">
            <div class="fs-4 fw-bold text-warning">$<?= number_format($summary['provider_cost'], 4) ?></div>
            <div class="small text-muted">Video Generation</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card border-0 shadow-sm text-center py-3 h-100 border-primary">
            <div class="fs-4 fw-bold text-primary">$<?= number_format($summary['internal_cost'], 4) ?></div>
            <div class="small text-muted">Internal Total</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card border-0 shadow-sm text-center py-3 h-100">
            <div class="fs-4 fw-bold text-success">$<?= number_format($summary['markup_amount'], 2) ?></div>
            <div class="small text-muted">Markup (<?= number_format($markupPct, 0) ?>%)</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card border-0 shadow-sm text-center py-3 h-100 bg-success bg-opacity-10">
            <div class="fs-4 fw-bold text-success">$<?= number_format($summary['billable_fee'], 2) ?></div>
            <div class="small text-muted">Billable Fee</div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card border-0 shadow-sm text-center py-3 h-100">
            <div class="fs-4 fw-bold text-secondary"><?= number_format($summary['margin'], 1) ?>%</div>
            <div class="small text-muted">Margin</div>
        </div>
    </div>
</div>
</div>

<!-- Markup Adjustment -->
<div class="d-flex align-items-center gap-3 mb-4">
    <label class="fw-semibold small">Adjust Markup %:</label>
    <form method="GET" class="d-flex gap-2 align-items-center">
        <input type="hidden" name="campaign_id" value="<?= (int)$campaignId ?>">
        <input type="number" name="markup" class="form-control form-control-sm" style="width:80px;"
               value="<?= number_format($markupPct, 0) ?>" min="0" max="500" step="1">
        <button type="submit" class="btn btn-sm btn-outline-success">Recalculate</button>
    </form>
    <button class="btn btn-sm btn-outline-secondary ms-2"
            hx-post="/admin/ai-video/actions/recalculate-costs.php"
            hx-vals='{"campaign_id":"<?= (int)$campaignId ?>","markup_pct":"<?= (float)$markupPct ?>"}'
            hx-target="#cost-summary-section"
            hx-swap="outerHTML">
        <i class="bi bi-arrow-repeat me-1"></i>Refresh
    </button>
</div>

<!-- Cost Rows Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-table me-2 text-success"></i>Cost Breakdown
        <span class="badge bg-secondary ms-1"><?= count($costRows) ?> entries</span>
    </div>
    <?php if (empty($costRows)): ?>
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-currency-dollar display-4 d-block mb-2"></i>No cost records yet.
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle small mb-0">
            <thead class="table-light">
                <tr>
                    <th>Type</th>
                    <th>Provider / Model</th>
                    <th>Units</th>
                    <th>Unit Cost</th>
                    <th>Total Cost</th>
                    <th>Job #</th>
                    <th>Notes</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($costRows as $row): ?>
            <tr>
                <td><?= h(ucwords(str_replace('_', ' ', $row['cost_type']))) ?></td>
                <td>
                    <div class="fw-semibold"><?= h($row['provider']) ?></div>
                    <?php if ($row['model']): ?><div class="text-muted small"><?= h($row['model']) ?></div><?php endif; ?>
                </td>
                <td><?= number_format((float)$row['units'], 2) ?></td>
                <td>$<?= number_format((float)$row['unit_cost'], 6) ?></td>
                <td class="fw-semibold">$<?= number_format((float)$row['total_cost'], 4) ?></td>
                <td><?= $row['job_id'] ? '#' . (int)$row['job_id'] : '—' ?></td>
                <td class="text-muted" style="max-width:200px;">
                    <?= $row['notes'] ? h(mb_strimwidth($row['notes'], 0, 80, '…')) : '—' ?>
                </td>
                <td class="text-nowrap text-muted"><?= h(date('M j, Y', strtotime($row['created_at']))) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <td colspan="4" class="text-end fw-semibold">Total:</td>
                    <td class="fw-bold text-primary">$<?= number_format($summary['internal_cost'], 4) ?></td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="mt-3">
    <a href="/admin/ai-video/view-campaign.php?id=<?= (int)$campaignId ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Campaign
    </a>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
