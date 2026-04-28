<?php
/**
 * diagnostic.php — Server environment & config checker
 * Access at https://stockwatch.enigmaiq.ai/diagnostic.php
 * DELETE THIS FILE after diagnosing.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

$pass = '✅';
$fail = '❌';
$warn = '⚠️';

$results = [];

// ---------------------------------------------------------------------------
// 1. PHP version
// ---------------------------------------------------------------------------
$phpVer = PHP_VERSION;
$results[] = [
    'label' => 'PHP Version',
    'ok'    => version_compare($phpVer, '8.0', '>='),
    'value' => $phpVer,
];

// ---------------------------------------------------------------------------
// 2. Required files exist
// ---------------------------------------------------------------------------
$files = [
    'bootstrap.php',
    'config/config.php',
    'config/schwab.php',
    'src/BaseService.php',
    'src/AuthService.php',
    'src/SchwabApiService.php',
    'src/PriceDataService.php',
    'src/IndicatorService.php',
    'src/RecommendationService.php',
    'stocks/index.php',
    'login.php',
    'logout.php',
    'includes/header.php',
    'includes/footer.php',
];
foreach ($files as $f) {
    $exists = file_exists(__DIR__ . '/' . $f);
    $results[] = ['label' => $f, 'ok' => $exists, 'value' => $exists ? 'Found' : 'MISSING'];
}

// ---------------------------------------------------------------------------
// 3. Load config and test DB
// ---------------------------------------------------------------------------
$configError = '';
try {
    require_once __DIR__ . '/config/config.php';
    $results[] = ['label' => 'config/config.php loaded', 'ok' => true, 'value' => 'OK'];
} catch (Throwable $e) {
    $configError = $e->getMessage();
    $results[] = ['label' => 'config/config.php loaded', 'ok' => false, 'value' => $e->getMessage()];
}

// DB constants
foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_CHARSET'] as $const) {
    $defined = defined($const);
    $val     = $defined ? constant($const) : 'NOT DEFINED';
    // Mask password
    $results[] = ['label' => $const, 'ok' => $defined, 'value' => $val];
}
$results[] = [
    'label' => 'DB_PASS',
    'ok'    => defined('DB_PASS'),
    'value' => defined('DB_PASS') ? (DB_PASS !== 'your_db_password' ? '(set)' : '(still default!)') : 'NOT DEFINED',
];

// DB connection
$pdo = null;
if (defined('DB_HOST')) {
    try {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $results[] = ['label' => 'Database connection', 'ok' => true, 'value' => 'Connected to ' . DB_NAME . ' on ' . DB_HOST];
    } catch (Throwable $e) {
        $results[] = ['label' => 'Database connection', 'ok' => false, 'value' => $e->getMessage()];
    }
}

// ---------------------------------------------------------------------------
// 4. Required tables
// ---------------------------------------------------------------------------
if ($pdo) {
    $tables = ['users', 'stocks', 'price_history', 'indicators', 'recommendations', 'schwab_tokens'];
    foreach ($tables as $t) {
        try {
            $pdo->query("SELECT 1 FROM `{$t}` LIMIT 1");
            $results[] = ['label' => "Table: {$t}", 'ok' => true, 'value' => 'Exists'];
        } catch (Throwable $e) {
            $results[] = ['label' => "Table: {$t}", 'ok' => false, 'value' => 'MISSING — ' . $e->getMessage()];
        }
    }
}

// ---------------------------------------------------------------------------
// 5. Schwab config
// ---------------------------------------------------------------------------
require_once __DIR__ . '/config/schwab.php';
$results[] = [
    'label' => 'SCHWAB_CLIENT_ID',
    'ok'    => defined('SCHWAB_CLIENT_ID') && SCHWAB_CLIENT_ID !== '',
    'value' => defined('SCHWAB_CLIENT_ID') && SCHWAB_CLIENT_ID !== '' ? '(set)' : 'empty',
];
$results[] = [
    'label' => 'SCHWAB_REDIRECT_URI',
    'ok'    => defined('SCHWAB_REDIRECT_URI'),
    'value' => defined('SCHWAB_REDIRECT_URI') ? SCHWAB_REDIRECT_URI : 'NOT DEFINED',
];

// ---------------------------------------------------------------------------
// 6. Session
// ---------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) session_start();
$results[] = ['label' => 'Session', 'ok' => true, 'value' => 'ID: ' . session_id()];

// ---------------------------------------------------------------------------
// Render
// ---------------------------------------------------------------------------
$allOk = array_reduce($results, fn($c, $r) => $c && $r['ok'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Stock Advisor — Diagnostic</title>
<style>
  body { font-family: monospace; background: #0a0f1e; color: #e2e8f0; padding: 2rem; }
  h1   { color: #3b82f6; margin-bottom: 1.5rem; }
  table{ border-collapse: collapse; width: 100%; max-width: 860px; }
  th,td{ padding: .45rem .8rem; border: 1px solid #1f2d40; text-align: left; }
  th   { background: #111827; color: #94a3b8; }
  .ok  { color: #22c55e; }
  .fail{ color: #ef4444; }
  .summary { margin-top: 1.5rem; padding: 1rem; border-radius: .5rem; max-width: 860px; }
  .summary.ok   { background: #052e16; border: 1px solid #166534; }
  .summary.fail { background: #2d1515; border: 1px solid #7f1d1d; }
  .warn { color: #f59e0b; font-size: .85rem; margin-top: 1.5rem; max-width: 860px; }
</style>
</head>
<body>
<h1>🔍 Stock Advisor — Diagnostic</h1>
<table>
  <tr><th>Check</th><th>Status</th><th>Value</th></tr>
  <?php foreach ($results as $r): ?>
  <tr>
    <td><?= htmlspecialchars($r['label']) ?></td>
    <td class="<?= $r['ok'] ? 'ok' : 'fail' ?>"><?= $r['ok'] ? '✅ OK' : '❌ FAIL' ?></td>
    <td><?= htmlspecialchars($r['value']) ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<div class="summary <?= $allOk ? 'ok' : 'fail' ?>">
  <?= $allOk ? '✅ All checks passed.' : '❌ One or more checks failed — fix the items above.' ?>
</div>

<p class="warn">⚠️ Delete diagnostic.php from the server after use — it exposes configuration details.</p>
</body>
</html>
