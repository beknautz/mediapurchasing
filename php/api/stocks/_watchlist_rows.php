<?php
/**
 * api/stocks/_watchlist_rows.php
 * Shared partial that renders the watchlist table body.
 * Included directly by watchlist.php and returned by watchlist_add/remove endpoints.
 */
if (!isset($priceSvc)) {
    require_once __DIR__ . '/../../bootstrap.php';
    $priceSvc = new PriceDataService();
}

$stocks = $priceSvc->getAllStocks();
?>
<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>Symbol</th>
                <th>Name</th>
                <th>Type</th>
                <th>Sector</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($stocks as $s): ?>
            <tr class="<?= $s['active'] ? '' : 'opacity-50' ?>">
                <td class="fw-bold"><?= h($s['symbol']) ?></td>
                <td><?= h($s['name']) ?></td>
                <td>
                    <span class="badge bg-light text-dark border">
                        <?= h(strtoupper($s['asset_type'])) ?>
                    </span>
                </td>
                <td class="text-muted small"><?= h($s['sector']) ?></td>
                <td>
                    <?php if ($s['active']): ?>
                        <span class="badge bg-success">Active</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Inactive</span>
                    <?php endif; ?>
                </td>
                <td class="text-end">
                    <a href="/stocks/detail.php?id=<?= (int)$s['id'] ?>"
                       class="btn btn-sm btn-outline-primary me-1">
                        <i class="bi bi-bar-chart-line"></i>
                    </a>
                    <?php if ($s['active']): ?>
                    <button class="btn btn-sm btn-outline-danger"
                            hx-post="/api/stocks/watchlist_remove.php"
                            hx-vals='{"stock_id": "<?= (int)$s['id'] ?>"}'
                            hx-target="#watchlist-table"
                            hx-swap="innerHTML"
                            hx-confirm="Remove <?= h($s['symbol']) ?> from watchlist?">
                        <i class="bi bi-trash"></i>
                    </button>
                    <?php else: ?>
                    <button class="btn btn-sm btn-outline-success"
                            hx-post="/api/stocks/watchlist_add.php"
                            hx-vals='{"symbol": "<?= h($s['symbol']) ?>", "name": "<?= h(addslashes($s['name'])) ?>", "asset_type": "<?= h($s['asset_type']) ?>", "sector": "<?= h($s['sector']) ?>"}'
                            hx-target="#watchlist-table"
                            hx-swap="innerHTML">
                        <i class="bi bi-plus"></i> Re-activate
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($stocks)): ?>
            <tr>
                <td colspan="6" class="text-center text-muted py-4">
                    No symbols in watchlist yet. Add one using the form.
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
