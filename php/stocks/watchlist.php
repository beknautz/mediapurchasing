<?php
/**
 * stocks/watchlist.php — Add / remove / manage the tracked symbol list
 */
require_once __DIR__ . '/../bootstrap.php';

if (empty($_SESSION['loggedIn'])) {
    redirect('/auth/login.php');
}

$priceSvc = new PriceDataService();
$stocks   = $priceSvc->getAllStocks();

$pageTitle = 'Watchlist Manager';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="/stocks/index.php" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
        </a>
        <h1 class="h3 mb-0 fw-bold mt-1">
            <i class="bi bi-list-stars me-2 text-primary"></i>Watchlist Manager
        </h1>
        <p class="text-muted mb-0 small">Add or remove stock / ETF / mutual fund symbols to track.</p>
    </div>
</div>

<div class="row g-4">

    <!-- Add symbol form -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold border-bottom">
                <i class="bi bi-plus-circle me-1 text-success"></i>Add Symbol
            </div>
            <div class="card-body">
                <form hx-post="/api/stocks/watchlist_add.php"
                      hx-target="#watchlist-table"
                      hx-swap="innerHTML"
                      hx-on::after-request="this.reset()">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Ticker Symbol <span class="text-danger">*</span></label>
                        <input type="text" name="symbol" class="form-control text-uppercase"
                               placeholder="e.g. AAPL, SPY, SWPPX"
                               maxlength="20" required
                               style="text-transform:uppercase">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Name / Description</label>
                        <input type="text" name="name" class="form-control"
                               placeholder="e.g. Apple Inc." maxlength="200">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Asset Type</label>
                        <select name="asset_type" class="form-select">
                            <option value="stock">Stock</option>
                            <option value="etf">ETF</option>
                            <option value="mutual_fund">Mutual Fund</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Sector</label>
                        <select name="sector" class="form-select">
                            <option value="">— Select —</option>
                            <option>Technology</option>
                            <option>Healthcare</option>
                            <option>Financials</option>
                            <option>Consumer Discretionary</option>
                            <option>Consumer Staples</option>
                            <option>Energy</option>
                            <option>Industrials</option>
                            <option>Materials</option>
                            <option>Real Estate</option>
                            <option>Utilities</option>
                            <option>Communication Services</option>
                            <option>Index</option>
                            <option>Other</option>
                        </select>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-plus-lg me-1"></i>Add to Watchlist
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Current watchlist -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold border-bottom">
                <i class="bi bi-table me-1 text-primary"></i>Current Watchlist
                <span class="badge bg-primary ms-1"><?= count(array_filter($stocks, fn($s) => $s['active'])) ?> active</span>
            </div>
            <div id="watchlist-table">
                <?php require __DIR__ . '/../api/stocks/_watchlist_rows.php'; ?>
            </div>
        </div>
    </div>

</div>

<?php
$extraScripts = '<script src="https://unpkg.com/htmx.org@1.9.10/dist/htmx.min.js"></script>';
require_once __DIR__ . '/../includes/footer.php';
?>
