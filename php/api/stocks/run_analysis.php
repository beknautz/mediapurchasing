<?php
/**
 * api/stocks/run_analysis.php
 * HTMX POST endpoint — manually triggers the recommendation pipeline.
 * Returns an inline alert with the summary result.
 *
 * Optional POST param: symbol — restrict run to one symbol.
 */
require_once __DIR__ . '/../../bootstrap.php';

header('Content-Type: text/html; charset=utf-8');

if (empty($_SESSION['loggedIn'])) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Not authenticated.</div>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$onlySymbol = strtoupper(trim($_POST['symbol'] ?? ''));
$recDate    = date('Y-m-d');

$priceSvc  = new PriceDataService();
$indSvc    = new IndicatorService();
$recSvc    = new RecommendationService();
$schwabSvc = new SchwabApiService();

if (!$schwabSvc->hasTokens()) {
    echo '<div class="alert alert-warning"><i class="bi bi-key me-1"></i>'
       . 'Schwab account not connected. <a href="/stocks/auth.php">Authorize here</a>.'
       . '</div>';
    exit;
}

$stocks = $priceSvc->getActiveStocks();

if ($onlySymbol !== '') {
    $stocks = array_values(array_filter($stocks, fn($s) => $s['symbol'] === $onlySymbol));
}

if (empty($stocks)) {
    echo '<div class="alert alert-info">No active symbols to process.</div>';
    exit;
}

$results = [];
$errors  = 0;

foreach ($stocks as $stock) {
    $id     = (int) $stock['id'];
    $symbol = $stock['symbol'];

    try {
        $priceSvc->syncPriceHistory($id, $symbol, 90);
        $indSvc->calculateAndStore($id, $symbol, $recDate);

        $latest = $priceSvc->getLatestCandle($id);
        $close  = (float) ($latest['close'] ?? 0.0);

        $rec = $recSvc->generateAndStore($id, $recDate, $close);

        $badgeColor = match($rec['action']) {
            'BUY'   => 'success',
            'SELL'  => 'danger',
            default => 'secondary',
        };

        $results[] = sprintf(
            '<span class="badge bg-%s me-1">%s</span>'
            . '<strong>%s</strong> — confidence %d/100',
            $badgeColor,
            h($rec['action']),
            h($symbol),
            $rec['confidence']
        );

    } catch (Throwable $e) {
        $results[] = '<strong>' . h($symbol) . '</strong>: <span class="text-danger">' . h($e->getMessage()) . '</span>';
        $errors++;
    }
}

$alertType = $errors === 0 ? 'success' : ($errors === count($results) ? 'danger' : 'warning');
$icon      = $errors === 0 ? 'check-circle-fill' : 'exclamation-triangle-fill';

echo '<div class="alert alert-' . $alertType . ' alert-dismissible fade show" role="alert">';
echo '<i class="bi bi-' . $icon . ' me-2"></i>';
echo '<strong>Analysis complete</strong> — ' . date('H:i') . '<br>';
echo '<ul class="mb-0 mt-1 ps-3">';
foreach ($results as $r) {
    echo '<li>' . $r . '</li>';
}
echo '</ul>';
echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
echo '</div>';
