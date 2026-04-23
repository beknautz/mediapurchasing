<?php
/**
 * api/stocks/watchlist_add.php
 * HTMX POST endpoint — adds a symbol to the watchlist and returns the updated table partial.
 */
require_once __DIR__ . '/../bootstrap.php';

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

$symbol    = strtoupper(trim($_POST['symbol']    ?? ''));
$name      = trim($_POST['name']      ?? '');
$assetType = trim($_POST['asset_type'] ?? 'stock');
$sector    = trim($_POST['sector']    ?? '');

if ($symbol === '') {
    echo '<div class="alert alert-danger m-3">Symbol is required.</div>';
    // Re-render existing table
    $priceSvc = new PriceDataService();
    require __DIR__ . '/_watchlist_rows.php';
    exit;
}

try {
    $priceSvc = new PriceDataService();
    $priceSvc->addStock($symbol, $name, $assetType, $sector);

    // Return the refreshed table partial
    require __DIR__ . '/_watchlist_rows.php';

} catch (Throwable $e) {
    echo '<div class="alert alert-danger m-3">' . h($e->getMessage()) . '</div>';
    $priceSvc = new PriceDataService();
    require __DIR__ . '/_watchlist_rows.php';
}
