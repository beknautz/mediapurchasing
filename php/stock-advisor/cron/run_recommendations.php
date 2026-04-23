#!/usr/bin/env php
<?php
/**
 * cron/run_recommendations.php
 * Daily pipeline: fetch prices → calculate indicators → generate recommendations.
 *
 * Intended crontab entry (runs at 4:30 PM ET Mon–Fri):
 *   30 16 * * 1-5 /usr/bin/php /var/www/html/mediapurchasing/php/cron/run_recommendations.php >> /var/log/stock_recommendations.log 2>&1
 *
 * Can also be triggered manually:
 *   php cron/run_recommendations.php [--force] [--symbol=AAPL]
 *
 *   --force    Run even if today is a weekend / holiday
 *   --symbol=X Process only the given symbol
 */

// ── Bootstrap ───────────────────────────────────────────────────────────────
define('CRON_CONTEXT', true);
require_once __DIR__ . '/../bootstrap.php';

// ── Argument parsing ─────────────────────────────────────────────────────────
$opts   = getopt('', ['force', 'symbol:']);
$force  = isset($opts['force']);
$only   = isset($opts['symbol']) ? strtoupper(trim($opts['symbol'])) : '';

// ── Weekend guard ────────────────────────────────────────────────────────────
$dow = (int) date('N'); // 1=Mon … 7=Sun
if (!$force && $dow >= 6) {
    log_msg('Market closed (weekend). Pass --force to override.');
    exit(0);
}

// ── Instantiate services ─────────────────────────────────────────────────────
$priceSvc  = new PriceDataService();
$indSvc    = new IndicatorService();
$recSvc    = new RecommendationService();
$schwabSvc = new SchwabApiService();

if (!$schwabSvc->hasTokens()) {
    log_msg('ERROR: No Schwab OAuth tokens. Visit /stock-advisor/stocks/auth.php to authorise.');
    exit(1);
}

// ── Determine run date ───────────────────────────────────────────────────────
$recDate = date('Y-m-d');
log_msg("=== Stock Recommendation Run: {$recDate} ===");

// ── Fetch active watchlist ───────────────────────────────────────────────────
$stocks = $priceSvc->getActiveStocks();

if (empty($stocks)) {
    log_msg('No active stocks in watchlist. Add symbols via /stocks/watchlist.php.');
    exit(0);
}

if ($only !== '') {
    $stocks = array_filter($stocks, fn($s) => $s['symbol'] === $only);
    if (empty($stocks)) {
        log_msg("Symbol {$only} not found in active watchlist.");
        exit(1);
    }
}

// ── Pipeline ─────────────────────────────────────────────────────────────────
$errors = 0;

foreach ($stocks as $stock) {
    $id     = (int) $stock['id'];
    $symbol = $stock['symbol'];

    try {
        // 1. Fetch + store price history (last 90 days)
        log_msg("[{$symbol}] Fetching price history...");
        $rows = $priceSvc->syncPriceHistory($id, $symbol, 90);
        log_msg("[{$symbol}] Stored/updated {$rows} candles.");

        // 2. Calculate indicators and store
        log_msg("[{$symbol}] Calculating indicators...");
        $indSvc->calculateAndStore($id, $symbol, $recDate);
        log_msg("[{$symbol}] Indicators stored.");

        // 3. Get latest close price for the recommendation record
        $latest = $priceSvc->getLatestCandle($id);
        $close  = (float) ($latest['close'] ?? 0.0);

        // 4. Generate and persist recommendation
        $rec = $recSvc->generateAndStore($id, $recDate, $close);
        log_msg(sprintf(
            '[%s] Recommendation: %s (confidence %d/100) — %s',
            $symbol,
            $rec['action'],
            $rec['confidence'],
            $rec['notes']
        ));

    } catch (Throwable $e) {
        log_msg("[{$symbol}] ERROR: " . $e->getMessage());
        $errors++;
    }
}

log_msg("=== Run complete. {$errors} error(s). ===");
exit($errors > 0 ? 1 : 0);

// ── Helpers ──────────────────────────────────────────────────────────────────
function log_msg(string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    echo $line . PHP_EOL;
    error_log($line);
}
