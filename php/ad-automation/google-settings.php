<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/google.php';
requireRole(['admin', 'buyer']);

$flashMsg = flash('success');
$errorMsg = flash('error');

$adsSvc  = new GoogleAdsService();
$bizSvc  = new GoogleBusinessService();
$adsOk   = $adsSvc->isConfigured();
$bizOk   = $bizSvc->isConfigured();

// Load stored tokens for display
$tokens   = file_exists(GOOGLE_TOKENS_PATH)
    ? (json_decode(file_get_contents(GOOGLE_TOKENS_PATH), true) ?? [])
    : [];

// Disconnect action
if (($_POST['action'] ?? '') === 'disconnect') {
    if (file_exists(GOOGLE_TOKENS_PATH)) unlink(GOOGLE_TOKENS_PATH);
    flash('success', 'Google account disconnected.');
    redirect('/ad-automation/google-settings.php');
}

// Fetch live data if connected
$locations    = [];
$campaigns    = [];
$gbpAccounts  = [];
$gbpError     = '';
$adsError     = '';

if ($bizOk) {
    try {
        // Use stored account ID from tokens to avoid hammering the rate-limited
        // My Business Account Management API on every page load.
        $storedAccountId = $tokens['business_account_id'] ?? '';
        if ($storedAccountId) {
            $accountName = 'accounts/' . $storedAccountId;
            $gbpAccounts = [['name' => $accountName, 'accountName' => ($tokens['business_account_name'] ?? '')]];
            $locations   = $bizSvc->getLocations($accountName);
        } else {
            // Fall back to live API call only when account ID not yet stored
            $gbpAccounts = $bizSvc->getAccounts();
            $accountName = $gbpAccounts[0]['name'] ?? '';
            if ($accountName) {
                $locations = $bizSvc->getLocations($accountName);
                // Persist for future loads
                if (!empty($gbpAccounts[0]['name'])) {
                    $aid = str_replace('accounts/', '', $gbpAccounts[0]['name']);
                    $tokens['business_account_id']   = $aid;
                    $tokens['business_account_name'] = $gbpAccounts[0]['accountName'] ?? '';
                    file_put_contents(GOOGLE_TOKENS_PATH, json_encode($tokens, JSON_PRETTY_PRINT));
                }
            }
        }
    } catch (Throwable $e) {
        $gbpError = $e->getMessage();
    }
}
if ($adsOk) {
    try {
        $campaigns = $adsSvc->listCampaigns();
    } catch (Throwable $e) {
        $adsError = $e->getMessage();
    }
}

$authUrl    = GoogleAdsService::buildAuthUrl();
$pageTitle  = 'Google Settings — Ad Automation — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">
            <i class="bi bi-google me-2 text-warning"></i>Google API Settings
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="/ad-automation/index.php">Ad Automation</a></li>
                <li class="breadcrumb-item active">Google Settings</li>
            </ol>
        </nav>
    </div>
</div>

<?php if ($flashMsg): ?><div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i><?= h($flashMsg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($errorMsg):  ?><div class="alert alert-danger  alert-dismissible fade show"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= h($errorMsg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="row g-4">

    <!-- ── Connection Status ────────────────────────────────────────────── -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-plug me-2"></i>Connection Status
            </div>
            <div class="card-body">
                <!-- Google Ads -->
                <div class="d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom">
                    <div>
                        <div class="fw-semibold"><i class="bi bi-google me-2 text-warning"></i>Google Ads</div>
                        <div class="small text-muted">
                            <?php if ($adsOk): ?>
                                Customer ID: <?= h(GOOGLE_ADS_CUSTOMER_ID) ?>
                                <?php if (!empty($tokens['authorized_at'])): ?>
                                <br>Connected: <?= h($tokens['authorized_at']) ?>
                                <?php endif; ?>
                            <?php else: ?>
                                Not connected — credentials missing
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="badge bg-<?= $adsOk ? 'success' : 'danger' ?> fs-6">
                        <i class="bi bi-<?= $adsOk ? 'check-circle' : 'x-circle' ?>"></i>
                        <?= $adsOk ? 'Connected' : 'Disconnected' ?>
                    </span>
                </div>

                <!-- Google Business Profile -->
                <div class="d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom">
                    <div>
                        <div class="fw-semibold"><i class="bi bi-shop me-2 text-primary"></i>Google Business Profile</div>
                        <div class="small text-muted">
                            <?php if ($bizOk && !empty($tokens['business_account_name'])): ?>
                                <?= h($tokens['business_account_name']) ?>
                            <?php elseif ($bizOk): ?>
                                Connected
                            <?php else: ?>
                                Not connected
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="badge bg-<?= $bizOk ? 'success' : 'danger' ?> fs-6">
                        <i class="bi bi-<?= $bizOk ? 'check-circle' : 'x-circle' ?>"></i>
                        <?= $bizOk ? 'Connected' : 'Disconnected' ?>
                    </span>
                </div>

                <!-- Actions -->
                <?php if (!$adsOk || !$bizOk): ?>
                <a href="<?= h($authUrl) ?>" class="btn btn-warning w-100 mb-2">
                    <i class="bi bi-google me-2"></i>Connect Google Account
                </a>
                <?php endif; ?>
                <?php if ($adsOk || $bizOk): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="disconnect">
                    <button type="submit" class="btn btn-outline-danger btn-sm w-100"
                            onclick="return confirm('Disconnect Google account? You will need to reconnect to publish ads.')">
                        <i class="bi bi-plug me-1"></i>Disconnect
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Credentials Checklist ─────────────────────────────────────── -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-key me-2"></i>Credentials Checklist
            </div>
            <div class="card-body small">
                <?php
                $checks = [
                    'GOOGLE_CLIENT_ID'            => GOOGLE_CLIENT_ID,
                    'GOOGLE_CLIENT_SECRET'         => GOOGLE_CLIENT_SECRET,
                    'GOOGLE_ADS_DEVELOPER_TOKEN'   => GOOGLE_ADS_DEVELOPER_TOKEN,
                    'GOOGLE_ADS_CUSTOMER_ID'       => GOOGLE_ADS_CUSTOMER_ID,
                    'Refresh Token (OAuth)'        => $tokens['refresh_token'] ?? '',
                ];
                foreach ($checks as $label => $value):
                    $ok = !empty($value);
                ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="<?= $ok ? '' : 'text-danger' ?>"><?= h($label) ?></span>
                    <span class="badge bg-<?= $ok ? 'success' : 'secondary' ?>">
                        <?= $ok ? '✓ Set' : 'Missing' ?>
                    </span>
                </div>
                <?php endforeach; ?>
                <div class="mt-3 text-muted">
                    Edit <code>php/config/google.php</code> to add credentials.
                </div>
            </div>
        </div>
    </div>

    <!-- ── Live Account Data ────────────────────────────────────────────── -->
    <div class="col-lg-7">

        <!-- Business Locations -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-geo-alt me-2 text-primary"></i>Business Profile Locations
            </div>
            <div class="card-body p-0">
                <?php if (!$bizOk): ?>
                <div class="text-muted small p-3">Connect your Google account to see locations.</div>
                <?php elseif ($gbpError): ?>
                <div class="alert alert-warning m-3 mb-0 small">
                    <i class="bi bi-exclamation-triangle me-1"></i><?= h($gbpError) ?>
                </div>
                <?php elseif (empty($gbpAccounts)): ?>
                <div class="text-muted small p-3">
                    No Business Profile accounts returned by the API for this Google login.<br>
                    Make sure <code><?= h($tokens['authorized_email'] ?? 'your Google account') ?></code>
                    manages a Business Profile at
                    <a href="https://business.google.com" target="_blank">business.google.com</a>.
                </div>
                <?php elseif (empty($locations)): ?>
                <div class="text-muted small p-3">Account found (<code><?= h($gbpAccounts[0]['name'] ?? '') ?></code>) but no locations returned.
                Make sure this account manages at least one verified Business Profile location.</div>
                <?php else: ?>
                <table class="table table-sm mb-0 small">
                    <thead class="table-light"><tr><th>Name</th><th>Address</th><th>Location ID (copy to client)</th></tr></thead>
                    <tbody>
                    <?php foreach ($locations as $loc):
                        $locName = $loc['name'] ?? '';
                    ?>
                    <tr>
                        <td class="fw-semibold"><?= h($loc['title'] ?? '') ?></td>
                        <td class="text-muted"><?= h(($loc['storefrontAddress']['addressLines'][0] ?? '') . ', ' . ($loc['storefrontAddress']['locality'] ?? '')) ?></td>
                        <td>
                            <code class="small"><?= h($locName) ?></code>
                            <?php if ($locName): ?>
                            <button type="button" class="btn btn-link btn-sm p-0 ms-1"
                                    onclick="navigator.clipboard.writeText('<?= h(addslashes($locName)) ?>').then(()=>this.innerHTML='<i class=\'bi bi-check-lg text-success\'></i>').catch(()=>{})"
                                    title="Copy to clipboard">
                                <i class="bi bi-clipboard"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="p-2 text-muted small border-top">
                    <i class="bi bi-info-circle me-1"></i>
                    Copy the Location ID and paste it into <a href="/admin/clients.php">Admin → Clients</a> → Edit Client → Google Business Profile Location.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Google Ads Campaigns -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-google me-2 text-warning"></i>Google Ads Campaigns</span>
                <?php if ($adsOk): ?>
                <a href="/ad-automation/performance.php" class="btn btn-sm btn-outline-warning">
                    <i class="bi bi-bar-chart me-1"></i>Performance
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (!$adsOk): ?>
                <div class="text-muted small p-3">Connect your Google account to see campaigns.</div>
                <?php elseif ($adsError): ?>
                <div class="alert alert-warning m-3 mb-0 small">
                    <i class="bi bi-exclamation-triangle me-1"></i><?= h($adsError) ?>
                </div>
                <?php elseif (empty($campaigns)): ?>
                <div class="text-muted small p-3">No campaigns found in account <?= h(GOOGLE_ADS_CUSTOMER_ID) ?>.</div>
                <?php else: ?>
                <table class="table table-sm mb-0 small">
                    <thead class="table-light"><tr><th>Campaign</th><th>Status</th><th>Budget / day</th></tr></thead>
                    <tbody>
                    <?php foreach ($campaigns as $c):
                        $status  = strtolower($c['campaign']['status'] ?? '');
                        $micros  = $c['campaignBudget']['amountMicros'] ?? 0;
                        $budgetUsd = number_format($micros / 1_000_000, 2);
                    ?>
                    <tr>
                        <td class="fw-semibold"><?= h($c['campaign']['name'] ?? '') ?></td>
                        <td>
                            <?php if ($status === 'enabled'): ?>
                            <span class="badge bg-success">Active</span>
                            <?php elseif ($status === 'paused'): ?>
                            <span class="badge bg-warning text-dark">Paused</span>
                            <?php else: ?>
                            <span class="badge bg-secondary"><?= h(ucfirst($status)) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>$<?= h($budgetUsd) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Diagnostics ────────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-bug me-2"></i>API Diagnostics</span>
        <button class="btn btn-sm btn-outline-secondary" type="button"
                data-bs-toggle="collapse" data-bs-target="#diagPanel">
            Show / Hide
        </button>
    </div>
    <div class="collapse" id="diagPanel">
        <div class="card-body small font-monospace">
            <div class="row g-3">
                <div class="col-md-6">
                    <strong>Stored Tokens (sensitive fields redacted)</strong>
                    <pre class="bg-light p-2 mt-1 rounded small" style="max-height:180px;overflow:auto"><?php
                        $diag = $tokens;
                        if (!empty($diag['access_token']))  $diag['access_token']  = substr($diag['access_token'],  0, 8) . '…';
                        if (!empty($diag['refresh_token'])) $diag['refresh_token'] = substr($diag['refresh_token'], 0, 8) . '…';
                        echo h(json_encode($diag, JSON_PRETTY_PRINT));
                    ?></pre>
                </div>
                <div class="col-md-6">
                    <strong>GBP getAccounts() response</strong>
                    <pre class="bg-light p-2 mt-1 rounded small" style="max-height:180px;overflow:auto"><?php
                        if ($gbpError) {
                            echo h('ERROR: ' . $gbpError);
                        } elseif (empty($gbpAccounts)) {
                            echo h('Empty array — no accounts returned');
                        } else {
                            echo h(json_encode($gbpAccounts, JSON_PRETTY_PRINT));
                        }
                    ?></pre>
                    <strong class="mt-2 d-block">Google Ads API config</strong>
                    <pre class="bg-light p-2 mt-1 rounded small"><?php
                        echo h("CUSTOMER_ID: " . (GOOGLE_ADS_CUSTOMER_ID ?: '(empty)') . "\n");
                        echo h("MANAGER_ID:  " . ((defined('GOOGLE_ADS_MANAGER_ID') ? GOOGLE_ADS_MANAGER_ID : '') ?: '(empty — will use CUSTOMER_ID as login-customer-id)') . "\n");
                        echo h("ADS ERROR:   " . ($adsError ?: 'none'));
                    ?></pre>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
