<?php
/**
 * api/stocks/watchlist_remove.php
 * HTMX POST endpoint — deactivates a stock and returns the updated table partial.
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

$stockId = (int) ($_POST['stock_id'] ?? 0);

if ($stockId <= 0) {
    echo '<div class="alert alert-danger m-3">Invalid stock ID.</div>';
} else {
    $priceSvc = new PriceDataService();
    $priceSvc->removeStock($stockId);
}

$priceSvc = new PriceDataService();
require __DIR__ . '/_watchlist_rows.php';
