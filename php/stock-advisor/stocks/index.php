<?php
/**
 * stocks/index.php — Daily recommendation dashboard
 */
require_once __DIR__ . '/../bootstrap.php';

if (empty($_SESSION['loggedIn'])) {
    redirect('/login.php');
}

$dbError   = '';
$hasTokens = false;
$recs      = [];
$recDate   = $_GET['date'] ?? '';

try {
    $recSvc    = new RecommendationService();
    $schwabSvc = new SchwabApiService();
    $hasTokens = $schwabSvc->hasTokens();
    $recs      = $hasTokens ? $recSvc->getTodaysRecommendations($recDate) : [];

    if (empty($recDate) && !empty($recs)) {
        $recDate = $recs[0]['rec_date'] ?? date('Y-m-d');
    }
} catch (Throwable $e) {
    $dbError = $e->getMessage();
    error_log('[StockAdvisor] index.php error: ' . $e->getMessage());
}

$buys  = array_filter($recs, fn($r) => $r['action'] === 'BUY');
$sells = array_filter($recs, fn($r) => $r['action'] === 'SELL');
$holds = array_filter($recs, fn($r) => $r['action'] === 'HOLD');

$pageTitle = 'Stock Recommendations';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">
            <i class="bi bi-graph-up-arrow me-2 text-success"></i>Stock Recommendations
        </h1>
        <p class="text-muted mb-0 small">
            <?php if ($recDate): ?>
                As of <?= h(date('F j, Y', strtotime($recDate))) ?>
            <?php else: ?>
                No recommendations yet — run the daily pipeline first
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="/stocks/watchlist.php" class="btn btn-outline-secondary">
            <i class="bi bi-list-stars me-1"></i>Watchlist
        </a>
        <?php if ($hasTokens): ?>
        <button class="btn btn-outline-primary"
                hx-post="/api/run_analysis.php"
                hx-target="#run-result"
                hx-swap="innerHTML"
                hx-indicator="#run-spinner">
            <span id="run-spinner" class="htmx-indicator spinner-border spinner-border-sm me-1" role="status"></span>
            <i class="bi bi-play-fill me-1"></i>Run Analysis Now
        </button>
        <?php else: ?>
        <a href="/stocks/auth.php" class="btn btn-warning">
            <i class="bi bi-key me-1"></i>Connect Schwab Account
        </a>
        <?php endif; ?>
    </div>
</div>

<div id="run-result" class="mb-3"></div>

<?php if ($dbError !== ''): ?>
<div class="alert alert-danger">
    <h6 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>Database Error</h6>
    <p class="mb-1 small"><code><?= h($dbError) ?></code></p>
    <hr class="my-2">
    <p class="mb-0 small">
        If tables are missing, run <strong>sql/migrate_stocks.sql</strong> against your database first.
    </p>
</div>
<?php endif; ?>

<?php if (!$hasTokens && $dbError === ''): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <strong>Schwab account not connected.</strong>
    <a href="/stocks/auth.php" class="alert-link">Authorize via OAuth</a> to enable price fetching and recommendations.
</div>
<?php endif; ?>

<?php if (empty($recs) && $hasTokens): ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle-fill me-2"></i>
    No recommendations for today yet. Click <strong>Run Analysis Now</strong> or wait for the daily cron job (weekdays 4:30 PM ET).
</div>
<?php endif; ?>

<?php if (!empty($recs)): ?>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="card border-0 shadow-sm border-start border-success border-4">
            <div class="card-body text-center py-3">
                <div class="display-5 fw-bold text-success"><?= count($buys) ?></div>
                <div class="small text-muted"><i class="bi bi-arrow-up-circle-fill me-1"></i>BUY</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card border-0 shadow-sm border-start border-danger border-4">
            <div class="card-body text-center py-3">
                <div class="display-5 fw-bold text-danger"><?= count($sells) ?></div>
                <div class="small text-muted"><i class="bi bi-arrow-down-circle-fill me-1"></i>SELL</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card border-0 shadow-sm border-start border-secondary border-4">
            <div class="card-body text-center py-3">
                <div class="display-5 fw-bold text-secondary"><?= count($holds) ?></div>
                <div class="small text-muted"><i class="bi bi-dash-circle-fill me-1"></i>HOLD</div>
            </div>
        </div>
    </div>
</div>

<!-- Recommendations Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-table me-1"></i>All Recommendations</span>
        <form class="d-flex gap-2 align-items-center" method="get">
            <label class="small text-muted mb-0">Date:</label>
            <input type="date" name="date" class="form-control form-control-sm" value="<?= h($recDate) ?>"
                   onchange="this.form.submit()">
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Symbol</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Action</th>
                    <th>Confidence</th>
                    <th>Close Price</th>
                    <th>Signal Summary</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recs as $rec): ?>
                <tr>
                    <td class="fw-bold"><?= h($rec['symbol']) ?></td>
                    <td><?= h($rec['name']) ?></td>
                    <td>
                        <span class="badge bg-light text-dark border">
                            <?= h(strtoupper($rec['asset_type'])) ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        $actionClass = match($rec['action']) {
                            'BUY'  => 'success',
                            'SELL' => 'danger',
                            default=> 'secondary',
                        };
                        ?>
                        <span class="badge bg-<?= $actionClass ?> fs-6 px-3">
                            <?= h($rec['action']) ?>
                        </span>
                    </td>
                    <td style="min-width:140px">
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height:8px">
                                <div class="progress-bar bg-<?= $actionClass ?>"
                                     style="width:<?= (int)$rec['confidence'] ?>%"></div>
                            </div>
                            <span class="small fw-semibold"><?= (int)$rec['confidence'] ?></span>
                        </div>
                    </td>
                    <td><?= $rec['close_price'] ? '$' . number_format((float)$rec['close_price'], 2) : '—' ?></td>
                    <td class="text-muted small" style="max-width:260px">
                        <span title="<?= h($rec['notes'] ?? '') ?>">
                            <?= h(mb_strimwidth($rec['notes'] ?? '', 0, 80, '…')) ?>
                        </span>
                    </td>
                    <td>
                        <a href="/stocks/detail.php?id=<?= (int)$rec['stock_id'] ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-bar-chart-line"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
