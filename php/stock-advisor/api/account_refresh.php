<?php
/**
 * api/account_refresh.php — HTMX partial: re-fetches Schwab account data
 * and returns the inner HTML for #account-body.
 */
require_once __DIR__ . '/../bootstrap.php';

if (empty($_SESSION['loggedIn'])) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Not authenticated.</div>';
    exit;
}

$schwabSvc = new SchwabApiService();

if (!$schwabSvc->hasTokens()) {
    echo '<div class="alert alert-warning"><i class="bi bi-key me-2"></i>'
        . 'Schwab not connected. <a href="/stocks/auth.php">Connect now</a>.</div>';
    exit;
}

$accounts = [];
$error    = '';

try {
    $accounts = $schwabSvc->getAccounts();
} catch (Throwable $e) {
    $error = $e->getMessage();
    error_log('[StockAdvisor] account_refresh.php: ' . $e->getMessage());
}

if ($error !== '') {
    echo '<div class="alert alert-danger">'
        . '<i class="bi bi-exclamation-triangle-fill me-2"></i>'
        . '<strong>Schwab API error:</strong> ' . h($error)
        . '</div>';
    exit;
}

if (empty($accounts)) {
    echo '<div class="alert alert-info">No account data returned. '
        . 'Make sure your Schwab app has the <strong>trader</strong> scope enabled.</div>';
    exit;
}

foreach ($accounts as $acct):
    $securitiesAccount = $acct['securitiesAccount'] ?? $acct ?? [];
    $accountNumber     = $securitiesAccount['accountNumber'] ?? '—';
    $accountType       = $securitiesAccount['type']          ?? '—';
    $currentBalances   = $securitiesAccount['currentBalances'] ?? [];
    $positions         = $securitiesAccount['positions']       ?? [];

    $liquidationValue  = $currentBalances['liquidationValue']      ?? $currentBalances['accountValue']             ?? null;
    $cashBalance       = $currentBalances['cashBalance']           ?? $currentBalances['availableFunds']           ?? null;
    $buyingPower       = $currentBalances['buyingPower']           ?? $currentBalances['buyingPowerNonMarginable'] ?? null;
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
        <span class="fw-semibold">
            <i class="bi bi-credit-card me-1 text-primary"></i>
            Account &hellip;<?= h(substr($accountNumber, -4)) ?>
            <span class="badge bg-light text-dark border ms-1"><?= h($accountType) ?></span>
        </span>
    </div>

    <div class="card-body border-bottom">
        <div class="row g-3">
            <?php if ($liquidationValue !== null): ?>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded" style="background:#f8fafc">
                    <div class="small text-muted mb-1">Account Value</div>
                    <div class="fw-bold fs-5 text-dark">$<?= number_format((float)$liquidationValue, 2) ?></div>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($cashBalance !== null): ?>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded" style="background:#f8fafc">
                    <div class="small text-muted mb-1">Cash Balance</div>
                    <div class="fw-bold fs-5 text-dark">$<?= number_format((float)$cashBalance, 2) ?></div>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($buyingPower !== null): ?>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded" style="background:#f8fafc">
                    <div class="small text-muted mb-1">Buying Power</div>
                    <div class="fw-bold fs-5 text-dark">$<?= number_format((float)$buyingPower, 2) ?></div>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($currentBalances)): ?>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded" style="background:#f8fafc">
                    <div class="small text-muted mb-1">Positions</div>
                    <div class="fw-bold fs-5 text-dark"><?= count($positions) ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($positions)): ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Symbol</th>
                    <th>Description</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Avg Cost</th>
                    <th class="text-end">Market Value</th>
                    <th class="text-end">Day P&amp;L</th>
                    <th class="text-end">Total P&amp;L</th>
                    <th class="text-end">% P&amp;L</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($positions as $pos):
                $inst     = $pos['instrument'] ?? [];
                $symbol   = $inst['symbol']      ?? '—';
                $desc     = $inst['description'] ?? $inst['assetType'] ?? '';
                $qty      = (float) ($pos['longQuantity']  ?? $pos['shortQuantity'] ?? 0);
                $avgCost  = (float) ($pos['averagePrice']  ?? 0);
                $mktValue = (float) ($pos['marketValue']   ?? 0);
                $dayPL    = (float) ($pos['currentDayProfitLoss'] ?? 0);
                $longPL   = (float) ($pos['longOpenProfitLoss']   ?? ($mktValue - ($avgCost * $qty)));
                $plPct    = ($avgCost > 0 && $qty > 0)
                            ? (($mktValue - $avgCost * $qty) / ($avgCost * $qty) * 100)
                            : 0;
                $plClass  = $longPL >= 0 ? 'text-success' : 'text-danger';
                $dayClass = $dayPL  >= 0 ? 'text-success' : 'text-danger';
            ?>
                <tr>
                    <td class="fw-bold"><?= h($symbol) ?></td>
                    <td class="text-muted small"><?= h(mb_strimwidth($desc, 0, 40, '…')) ?></td>
                    <td class="text-end"><?= number_format($qty, 3) ?></td>
                    <td class="text-end">$<?= number_format($avgCost, 2) ?></td>
                    <td class="text-end fw-semibold">$<?= number_format($mktValue, 2) ?></td>
                    <td class="text-end <?= $dayClass ?>">
                        <?= ($dayPL >= 0 ? '+' : '') . '$' . number_format(abs($dayPL), 2) ?>
                    </td>
                    <td class="text-end <?= $plClass ?>">
                        <?= ($longPL >= 0 ? '+' : '') . '$' . number_format(abs($longPL), 2) ?>
                    </td>
                    <td class="text-end <?= $plClass ?>">
                        <?= ($plPct >= 0 ? '+' : '') . number_format($plPct, 2) ?>%
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="card-body text-muted small">No open positions in this account.</div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
