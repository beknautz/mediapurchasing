<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/google.php';
requireRole(['admin', 'buyer']);

$adsSvc = new GoogleAdsService();
$days   = (int)($_GET['days'] ?? 30);
$days   = in_array($days, [7, 14, 30, 90], true) ? $days : 30;

$performance = [];
$error       = '';

if ($adsSvc->isConfigured()) {
    try {
        $performance = $adsSvc->getCampaignPerformance($days);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

// Aggregate totals
$totals = [
    'impressions'  => array_sum(array_column(array_column($performance, 'metrics'), 'impressions')),
    'clicks'       => array_sum(array_column(array_column($performance, 'metrics'), 'clicks')),
    'cost_usd'     => array_sum(array_column($performance, 'cost_usd')),
    'conversions'  => array_sum(array_column(array_column($performance, 'metrics'), 'conversions')),
];
$totals['ctr'] = $totals['impressions'] > 0
    ? round(100 * $totals['clicks'] / $totals['impressions'], 2)
    : 0;
$totals['avg_cpc'] = $totals['clicks'] > 0
    ? round($totals['cost_usd'] / $totals['clicks'], 2)
    : 0;

$pageTitle = 'Google Ads Performance — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h1 class="h3 mb-0 fw-bold">
            <i class="bi bi-bar-chart me-2 text-warning"></i>Google Ads Performance
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="/ad-automation/index.php">Ad Automation</a></li>
                <li class="breadcrumb-item active">Performance</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        <?php foreach ([7,14,30,90] as $d): ?>
        <a href="?days=<?= $d ?>" class="btn btn-sm <?= $days===$d ? 'btn-dark' : 'btn-outline-secondary' ?>">
            <?= $d ?>d
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if (!$adsSvc->isConfigured()): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-2"></i>
    Google Ads is not connected. <a href="/ad-automation/google-settings.php">Go to Google Settings →</a>
</div>
<?php elseif ($error): ?>
<div class="alert alert-danger">
    <i class="bi bi-x-circle me-2"></i><?= h($error) ?>
</div>
<?php else: ?>

<!-- ── Summary Cards ──────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['label'=>'Impressions', 'value'=>number_format($totals['impressions']), 'icon'=>'eye',         'color'=>'primary'],
        ['label'=>'Clicks',      'value'=>number_format($totals['clicks']),      'icon'=>'cursor',      'color'=>'info'],
        ['label'=>'CTR',         'value'=>$totals['ctr'].'%',                    'icon'=>'percent',     'color'=>'warning'],
        ['label'=>'Avg CPC',     'value'=>'$'.$totals['avg_cpc'],                'icon'=>'cash',        'color'=>'success'],
        ['label'=>'Total Spend', 'value'=>'$'.number_format($totals['cost_usd'],2),'icon'=>'credit-card','color'=>'danger'],
        ['label'=>'Conversions', 'value'=>number_format((float)$totals['conversions'],1),'icon'=>'check2-circle','color'=>'success'],
    ];
    foreach ($cards as $card):
    ?>
    <div class="col-6 col-md-2">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-4 fw-bold text-<?= $card['color'] ?>"><?= $card['value'] ?></div>
            <div class="small text-muted"><?= $card['label'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── Campaign Table ─────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">
        Campaign Breakdown — Last <?= $days ?> Days
    </div>
    <div class="card-body p-0">
        <?php if (empty($performance)): ?>
        <div class="text-muted text-center py-5 small">No data for this period.</div>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle small">
            <thead class="table-light">
                <tr>
                    <th>Campaign</th>
                    <th class="text-end">Impressions</th>
                    <th class="text-end">Clicks</th>
                    <th class="text-end">CTR</th>
                    <th class="text-end">Avg CPC</th>
                    <th class="text-end">Spend</th>
                    <th class="text-end">Conversions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($performance as $row):
                $m = $row['metrics'] ?? [];
            ?>
            <tr>
                <td class="fw-semibold"><?= h($row['campaign']['name'] ?? '—') ?></td>
                <td class="text-end"><?= number_format((int)($m['impressions'] ?? 0)) ?></td>
                <td class="text-end"><?= number_format((int)($m['clicks'] ?? 0)) ?></td>
                <td class="text-end"><?= $row['ctr_pct'] ?? '0' ?>%</td>
                <td class="text-end">$<?= $row['avg_cpc'] ?? '0.00' ?></td>
                <td class="text-end fw-semibold">$<?= number_format($row['cost_usd'] ?? 0, 2) ?></td>
                <td class="text-end"><?= number_format((float)($m['conversions'] ?? 0), 1) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light fw-semibold">
                <tr>
                    <td>Totals</td>
                    <td class="text-end"><?= number_format($totals['impressions']) ?></td>
                    <td class="text-end"><?= number_format($totals['clicks']) ?></td>
                    <td class="text-end"><?= $totals['ctr'] ?>%</td>
                    <td class="text-end">$<?= $totals['avg_cpc'] ?></td>
                    <td class="text-end">$<?= number_format($totals['cost_usd'], 2) ?></td>
                    <td class="text-end"><?= number_format((float)$totals['conversions'], 1) ?></td>
                </tr>
            </tfoot>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
