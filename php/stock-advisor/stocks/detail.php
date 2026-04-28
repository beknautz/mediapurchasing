<?php
/**
 * stocks/detail.php — Symbol detail: price history, indicators, recommendation history
 */
require_once __DIR__ . '/../bootstrap.php';

if (empty($_SESSION['loggedIn'])) {
    redirect('/login.php');
}

$stockId = (int) ($_GET['id'] ?? 0);
if ($stockId <= 0) {
    redirect('/stocks/index.php');
}

$priceSvc  = new PriceDataService();
$indSvc    = new IndicatorService();
$recSvc    = new RecommendationService();

$stock      = $priceSvc->getStockById($stockId);
if (empty($stock)) {
    redirect('/stocks/index.php');
}

$latestRec  = $recSvc->getLatest($stockId);
$latestInd  = $indSvc->getLatest($stockId);
$recHistory = $recSvc->getHistory($stockId, 20);
$priceData  = $priceSvc->getPriceHistory($stockId, pageSize: 30);

$pageTitle  = h($stock['symbol']) . ' — Detail';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="/stocks/index.php" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
        </a>
        <h1 class="h3 mb-0 fw-bold mt-1">
            <?= h($stock['symbol']) ?>
            <span class="badge bg-light text-dark border fw-normal fs-6 ms-1"><?= h(strtoupper($stock['asset_type'])) ?></span>
        </h1>
        <p class="text-muted mb-0"><?= h($stock['name']) ?><?= $stock['sector'] ? ' · ' . h($stock['sector']) : '' ?></p>
    </div>
    <?php if (!empty($latestRec)): ?>
    <?php
    $actionClass = match($latestRec['action']) {
        'BUY'  => 'success',
        'SELL' => 'danger',
        default=> 'secondary',
    };
    ?>
    <div class="text-end">
        <span class="badge bg-<?= $actionClass ?> fs-4 px-4 py-2"><?= h($latestRec['action']) ?></span>
        <div class="small text-muted mt-1">
            Confidence: <?= (int)$latestRec['confidence'] ?>/100 &nbsp;|&nbsp;
            <?= h(date('M j, Y', strtotime($latestRec['rec_date']))) ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="row g-4">

    <!-- Left column: Latest indicators -->
    <div class="col-lg-4">

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold border-bottom">
                <i class="bi bi-activity me-1 text-primary"></i>Latest Indicators
                <?php if (!empty($latestInd)): ?>
                <span class="small text-muted fw-normal ms-1"><?= h($latestInd['trade_date']) ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body">
            <?php if (empty($latestInd)): ?>
                <p class="text-muted small mb-0">No indicators calculated yet.</p>
            <?php else: ?>
                <?php
                $rsi = $latestInd['rsi14'] !== null ? (float)$latestInd['rsi14'] : null;
                $rsiColor = 'secondary';
                if ($rsi !== null) {
                    $rsiColor = $rsi < 30 ? 'success' : ($rsi > 70 ? 'danger' : 'primary');
                }
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="fw-semibold">RSI (14)</span>
                        <span class="text-<?= $rsiColor ?> fw-bold">
                            <?= $rsi !== null ? number_format($rsi, 2) : '—' ?>
                        </span>
                    </div>
                    <?php if ($rsi !== null): ?>
                    <div class="progress" style="height:6px">
                        <div class="progress-bar bg-<?= $rsiColor ?>" style="width:<?= min(100,$rsi) ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-between text-muted" style="font-size:.65rem">
                        <span>Oversold &lt;30</span><span>Overbought &gt;70</span>
                    </div>
                    <?php endif; ?>
                </div>

                <hr class="my-2">

                <div class="mb-2 d-flex justify-content-between small">
                    <span class="fw-semibold">MACD Line</span>
                    <span><?= $latestInd['macd_line'] !== null ? number_format((float)$latestInd['macd_line'],4) : '—' ?></span>
                </div>
                <div class="mb-2 d-flex justify-content-between small">
                    <span class="fw-semibold">Signal Line</span>
                    <span><?= $latestInd['signal_line'] !== null ? number_format((float)$latestInd['signal_line'],4) : '—' ?></span>
                </div>
                <?php
                $hist = $latestInd['macd_hist'] !== null ? (float)$latestInd['macd_hist'] : null;
                $histColor = $hist !== null ? ($hist >= 0 ? 'text-success' : 'text-danger') : '';
                ?>
                <div class="mb-3 d-flex justify-content-between small">
                    <span class="fw-semibold">Histogram</span>
                    <span class="<?= $histColor ?> fw-bold">
                        <?= $hist !== null ? number_format($hist, 4) : '—' ?>
                    </span>
                </div>

                <hr class="my-2">

                <div class="mb-2 d-flex justify-content-between small">
                    <span class="fw-semibold">SMA 20</span>
                    <span><?= $latestInd['sma20'] !== null ? '$'.number_format((float)$latestInd['sma20'],2) : '—' ?></span>
                </div>
                <div class="mb-3 d-flex justify-content-between small">
                    <span class="fw-semibold">SMA 50</span>
                    <span><?= $latestInd['sma50'] !== null ? '$'.number_format((float)$latestInd['sma50'],2) : '—' ?></span>
                </div>

                <hr class="my-2">

                <div class="mb-1 d-flex justify-content-between small">
                    <span class="fw-semibold">BB Upper</span>
                    <span><?= $latestInd['bb_upper'] !== null ? '$'.number_format((float)$latestInd['bb_upper'],2) : '—' ?></span>
                </div>
                <div class="mb-1 d-flex justify-content-between small">
                    <span class="fw-semibold">BB Middle</span>
                    <span><?= $latestInd['bb_middle'] !== null ? '$'.number_format((float)$latestInd['bb_middle'],2) : '—' ?></span>
                </div>
                <div class="d-flex justify-content-between small">
                    <span class="fw-semibold">BB Lower</span>
                    <span><?= $latestInd['bb_lower'] !== null ? '$'.number_format((float)$latestInd['bb_lower'],2) : '—' ?></span>
                </div>
            <?php endif; ?>
            </div>
        </div>

        <!-- Actions -->
        <div class="card border-0 shadow-sm">
            <div class="card-body d-grid gap-2">
                <button class="btn btn-outline-primary btn-sm"
                        hx-post="/api/run_analysis.php"
                        hx-vals='{"symbol": "<?= h($stock['symbol']) ?>"}'
                        hx-target="#detail-run-result"
                        hx-swap="innerHTML">
                    <i class="bi bi-arrow-clockwise me-1"></i>Re-run Analysis
                </button>
                <button class="btn btn-outline-danger btn-sm"
                        hx-post="/api/watchlist_remove.php"
                        hx-vals='{"stock_id": "<?= $stockId ?>"}'
                        hx-confirm="Remove <?= h($stock['symbol']) ?> from watchlist?"
                        hx-target="body"
                        hx-push-url="/stocks/watchlist.php">
                    <i class="bi bi-trash me-1"></i>Remove from Watchlist
                </button>
            </div>
        </div>

        <div id="detail-run-result" class="mt-2"></div>
    </div>

    <!-- Right column: Price history + rec history -->
    <div class="col-lg-8">

        <!-- Price History Table -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold border-bottom">
                <i class="bi bi-calendar3 me-1 text-primary"></i>Recent Price History
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th class="text-end">Open</th>
                            <th class="text-end">High</th>
                            <th class="text-end">Low</th>
                            <th class="text-end">Close</th>
                            <th class="text-end">Volume</th>
                            <th class="text-end">Change</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $rows = $priceData['data'] ?? [];
                    $prev = null;
                    foreach ($rows as $row):
                        $change = $prev !== null ? ($row['close'] - $prev) : null;
                        $changePct = ($prev !== null && $prev > 0) ? ($change / $prev * 100) : null;
                        $prev = (float)$row['close'];
                    ?>
                        <tr>
                            <td><?= h($row['trade_date']) ?></td>
                            <td class="text-end"><?= '$'.number_format((float)$row['open'],2) ?></td>
                            <td class="text-end text-success"><?= '$'.number_format((float)$row['high'],2) ?></td>
                            <td class="text-end text-danger"><?= '$'.number_format((float)$row['low'],2) ?></td>
                            <td class="text-end fw-semibold"><?= '$'.number_format((float)$row['close'],2) ?></td>
                            <td class="text-end text-muted"><?= number_format((int)$row['volume']) ?></td>
                            <td class="text-end">
                                <?php if ($changePct !== null): ?>
                                <span class="<?= $changePct >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= ($changePct >= 0 ? '+' : '') . number_format($changePct, 2) ?>%
                                </span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-3">No price history available.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recommendation History -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold border-bottom">
                <i class="bi bi-clock-history me-1 text-primary"></i>Recommendation History
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Action</th>
                            <th>Confidence</th>
                            <th>Close</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recHistory as $r): ?>
                        <?php
                        $ac = match($r['action']) {
                            'BUY'  => 'success',
                            'SELL' => 'danger',
                            default=> 'secondary',
                        };
                        ?>
                        <tr>
                            <td><?= h($r['rec_date']) ?></td>
                            <td><span class="badge bg-<?= $ac ?>"><?= h($r['action']) ?></span></td>
                            <td>
                                <div class="d-flex align-items-center gap-1">
                                    <div class="progress flex-grow-1" style="height:6px;min-width:60px">
                                        <div class="progress-bar bg-<?= $ac ?>" style="width:<?= (int)$r['confidence'] ?>%"></div>
                                    </div>
                                    <small><?= (int)$r['confidence'] ?></small>
                                </div>
                            </td>
                            <td><?= $r['close_price'] ? '$'.number_format((float)$r['close_price'],2) : '—' ?></td>
                            <td class="text-muted small" style="max-width:220px">
                                <?= h(mb_strimwidth($r['notes'] ?? '', 0, 60, '…')) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recHistory)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">No recommendations yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /col -->
</div><!-- /row -->

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
