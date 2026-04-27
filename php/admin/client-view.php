<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/google.php';
requireRole(['admin', 'buyer']);

$crmService = new CRMService();
$clientId   = (int)($_GET['id'] ?? 0);
if (!$clientId) redirect('/admin/clients.php');

$result = $crmService->getClient($clientId);
if (empty($result)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Client not found.'];
    redirect('/admin/clients.php');
}

$client     = $result['client'];
$recentBuys = $result['recentBuys'];

// ── Google Ads ─────────────────────────────────────────────────────────────
$adsCampaigns  = [];
$adsError      = '';
$hasAdsId      = !empty($client['google_ads_customer_id']);

if ($hasAdsId) {
    try {
        $adsSvc       = new GoogleAdsService($client['google_ads_customer_id']);
        $adsConnected = $adsSvc->isConfigured();
        if ($adsConnected) {
            $adsCampaigns = $adsSvc->listCampaigns();
        }
    } catch (Throwable $e) {
        $adsError     = $e->getMessage();
        $adsConnected = false;
    }
} else {
    $adsConnected = false;
}

// ── Google Business Profile ────────────────────────────────────────────────
$gbpPosts     = [];
$gbpInsights  = [];
$gbpError     = '';
$hasGbpLoc    = !empty($client['google_business_location']);

if ($hasGbpLoc) {
    try {
        $bizSvc       = new GoogleBusinessService();
        $bizConnected = $bizSvc->isConfigured();
        if ($bizConnected) {
            $gbpPosts    = $bizSvc->listPosts($client['google_business_location']);
            $gbpInsights = $bizSvc->getLocationInsights($client['google_business_location']);
        }
    } catch (Throwable $e) {
        $gbpError     = $e->getMessage();
        $bizConnected = false;
    }
} else {
    $bizConnected = false;
}

// ── Handle GBP post creation ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_gbp_post') {
    try {
        $bizSvc = new GoogleBusinessService();
        $bizSvc->createPost($client['google_business_location'], [
            'summary'      => trim($_POST['summary'] ?? ''),
            'image_url'    => trim($_POST['image_url'] ?? '') ?: null,
            'cta_type'     => $_POST['cta_type'] ?? '',
            'cta_url'      => trim($_POST['cta_url'] ?? '') ?: null,
            'start_date'   => $_POST['start_date'] ?? '',
            'end_date'     => $_POST['end_date'] ?? '',
            'event_title'  => trim($_POST['event_title'] ?? '') ?: null,
        ]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Post published to Google Business Profile.'];
    } catch (Throwable $e) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to publish post: ' . $e->getMessage()];
    }
    redirect('/admin/client-view.php?id=' . $clientId);
}

// ── Handle GBP post deletion ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_gbp_post') {
    try {
        $bizSvc = new GoogleBusinessService();
        $bizSvc->deletePost(trim($_POST['post_name'] ?? ''));
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Post deleted.'];
    } catch (Throwable $e) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Delete failed: ' . $e->getMessage()];
    }
    redirect('/admin/client-view.php?id=' . $clientId);
}

$pageTitle = h($client['company_name']) . ' — Clients — MediaBuy';
require_once __DIR__ . '/../includes/header.php';

// ── helpers ────────────────────────────────────────────────────────────────
function statusBadge(string $s): string {
    $map = ['ENABLED'=>'success','PAUSED'=>'warning text-dark','REMOVED'=>'secondary',
            'draft'=>'secondary','active'=>'success','paused'=>'warning text-dark','completed'=>'info'];
    $c = $map[$s] ?? 'secondary';
    return '<span class="badge bg-' . $c . '">' . h(ucfirst(strtolower($s))) . '</span>';
}

function insightLabel(string $metric): string {
    $map = [
        'QUERIES_DIRECT'            => 'Direct Searches',
        'QUERIES_INDIRECT'          => 'Discovery Searches',
        'VIEWS_SEARCH'              => 'Search Views',
        'VIEWS_MAPS'                => 'Maps Views',
        'ACTIONS_WEBSITE'           => 'Website Clicks',
        'ACTIONS_PHONE'             => 'Phone Calls',
        'ACTIONS_DRIVING_DIRECTIONS'=> 'Direction Requests',
    ];
    return $map[$metric] ?? $metric;
}
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="/admin/clients.php">Clients</a></li>
        <li class="breadcrumb-item active"><?= h($client['company_name']) ?></li>
    </ol>
</nav>

<!-- ── Client Header ─────────────────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h2 class="mb-1 fw-bold"><i class="bi bi-building me-2 text-primary"></i><?= h($client['company_name']) ?></h2>
        <div class="text-muted small">
            <?php if ($client['contact_name']): ?>
                <i class="bi bi-person me-1"></i><?= h($client['contact_name']) ?>
            <?php endif; ?>
            <?php if ($client['email']): ?>
                &nbsp;·&nbsp;<a href="mailto:<?= h($client['email']) ?>"><?= h($client['email']) ?></a>
            <?php endif; ?>
            <?php if ($client['phone']): ?>
                &nbsp;·&nbsp;<a href="tel:<?= h($client['phone']) ?>"><?= h($client['phone']) ?></a>
            <?php endif; ?>
        </div>
        <?php if ($client['address']): ?>
            <div class="text-muted small mt-1"><i class="bi bi-geo-alt me-1"></i><?= h($client['address']) ?></div>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm"
                data-bs-toggle="modal" data-bs-target="#editClientModal">
            <i class="bi bi-pencil me-1"></i>Edit Client
        </button>
        <a href="/ad-automation/schedules.php?client_id=<?= $clientId ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-calendar-plus me-1"></i>New Ad Schedule
        </a>
    </div>
</div>

<div class="row g-4">

    <!-- ── LEFT COLUMN ──────────────────────────────────────────────────── -->
    <div class="col-lg-6">

        <!-- Google Ads Campaigns -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-google me-2 text-warning"></i>Google Ads Campaigns</span>
                <?php if ($hasAdsId && $adsConnected): ?>
                    <a href="/ad-automation/performance.php?customer_id=<?= h($client['google_ads_customer_id']) ?>"
                       class="btn btn-sm btn-outline-warning">
                        <i class="bi bi-bar-chart me-1"></i>Performance
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (!$hasAdsId): ?>
                    <div class="text-center text-muted py-4 small">
                        <i class="bi bi-google fs-3 d-block mb-2 opacity-25"></i>
                        No Google Ads Customer ID set.
                        <a href="#" data-bs-toggle="modal" data-bs-target="#editClientModal">Add one →</a>
                    </div>
                <?php elseif (!$adsConnected): ?>
                    <div class="alert alert-warning m-3 mb-0">
                        <?= $adsError
                            ? '<i class="bi bi-exclamation-triangle me-1"></i>' . h($adsError)
                            : 'Google Ads not connected. <a href="/ad-automation/google-settings.php">Connect →</a>' ?>
                    </div>
                <?php elseif (empty($adsCampaigns)): ?>
                    <div class="text-center text-muted py-4 small">
                        No campaigns found in account <?= h($client['google_ads_customer_id']) ?>.
                    </div>
                <?php else: ?>
                    <table class="table table-sm table-hover mb-0 align-middle small">
                        <thead class="table-light">
                            <tr>
                                <th>Campaign</th>
                                <th class="text-center">Status</th>
                                <th class="text-end">Budget/day</th>
                                <th>Dates</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($adsCampaigns as $camp):
                            $cname  = $camp['campaign']['name']   ?? '—';
                            $cstat  = $camp['campaign']['status'] ?? '';
                            $budget = isset($camp['campaignBudget']['amountMicros'])
                                ? '$' . number_format($camp['campaignBudget']['amountMicros'] / 1_000_000, 2)
                                : '—';
                            $start  = $camp['campaign']['startDate'] ?? '';
                            $end    = $camp['campaign']['endDate']   ?? '';
                            $res    = $camp['campaign']['resourceName'] ?? '';
                        ?>
                        <tr>
                            <td class="fw-semibold"><?= h($cname) ?></td>
                            <td class="text-center"><?= statusBadge($cstat) ?></td>
                            <td class="text-end text-muted"><?= h($budget) ?></td>
                            <td class="text-muted">
                                <?= $start ? date('M j, Y', strtotime($start)) : '' ?>
                                <?= ($start && $end) ? '–' : '' ?>
                                <?= $end   ? date('M j, Y', strtotime($end))   : '' ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <?php if ($res): ?>
                                <form method="post" class="d-inline"
                                      action="/ad-automation/api.php">
                                    <input type="hidden" name="action" value="set_google_campaign_status">
                                    <input type="hidden" name="campaign_resource" value="<?= h($res) ?>">
                                    <?php if ($cstat === 'PAUSED'): ?>
                                    <button name="status" value="active" class="btn btn-xs btn-outline-success py-0 px-1 small"
                                            title="Enable"><i class="bi bi-play-fill"></i></button>
                                    <?php else: ?>
                                    <button name="status" value="paused" class="btn btn-xs btn-outline-warning py-0 px-1 small"
                                            title="Pause"><i class="bi bi-pause-fill"></i></button>
                                    <?php endif; ?>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Media Buys -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-cart3 me-2 text-primary"></i>Recent Media Buys</span>
                <a href="/media-buys/create.php?client_id=<?= $clientId ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-plus me-1"></i>New Buy
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentBuys)): ?>
                    <div class="text-center text-muted py-4 small">No media buys yet for this client.</div>
                <?php else: ?>
                    <table class="table table-sm table-hover mb-0 align-middle small">
                        <thead class="table-light">
                            <tr><th>Title</th><th class="text-center">Status</th><th class="text-end">Cost</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentBuys as $buy): ?>
                        <tr>
                            <td>
                                <a href="/media-buys/view.php?id=<?= (int)$buy['id'] ?>" class="text-decoration-none fw-semibold">
                                    <?= h($buy['title']) ?>
                                </a>
                                <div class="text-muted" style="font-size:.75rem"><?= date('M j, Y', strtotime($buy['created_at'])) ?></div>
                            </td>
                            <td class="text-center"><?= statusBadge($buy['status']) ?></td>
                            <td class="text-end text-muted">
                                <?= !empty($buy['agreed_cost']) ? '$' . number_format($buy['agreed_cost'], 2)
                                    : (!empty($buy['total_cost']) ? '$' . number_format($buy['total_cost'], 2) : '—') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /LEFT -->

    <!-- ── RIGHT COLUMN ─────────────────────────────────────────────────── -->
    <div class="col-lg-6">

        <!-- GBP Insights -->
        <?php if ($hasGbpLoc && $bizConnected && !empty($gbpInsights)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-graph-up me-2 text-success"></i>Business Profile — Last 30 Days
            </div>
            <div class="card-body">
                <div class="row g-2 text-center">
                <?php foreach ($gbpInsights as $metric):
                    $vals = $metric['dimensionalValues'] ?? [];
                    $total = array_sum(array_column($vals, 'value'));
                ?>
                <div class="col-6 col-md-4">
                    <div class="border rounded p-2">
                        <div class="fs-5 fw-bold"><?= number_format((int)$total) ?></div>
                        <div class="text-muted" style="font-size:.7rem"><?= insightLabel($metric['metric'] ?? '') ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- GBP Posts -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-geo-alt-fill me-2 text-success"></i>Google Business Posts</span>
                <?php if ($hasGbpLoc && $bizConnected): ?>
                <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#gbpPostModal">
                    <i class="bi bi-plus me-1"></i>New Post
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body <?= (empty($gbpPosts) && $hasGbpLoc) ? '' : 'p-0' ?>">
                <?php if (!$hasGbpLoc): ?>
                    <div class="text-center text-muted py-3 small">
                        <i class="bi bi-geo-alt fs-3 d-block mb-2 opacity-25"></i>
                        No Google Business Profile location set.
                        <a href="#" data-bs-toggle="modal" data-bs-target="#editClientModal">Add one →</a>
                    </div>
                <?php elseif (!$bizConnected): ?>
                    <div class="alert alert-warning mb-0">
                        <?= $gbpError
                            ? h($gbpError)
                            : 'Google not connected. <a href="/ad-automation/google-settings.php">Connect →</a>' ?>
                    </div>
                <?php elseif (empty($gbpPosts)): ?>
                    <div class="text-center text-muted py-3 small">No posts yet. Create the first one!</div>
                <?php else: ?>
                    <?php foreach ($gbpPosts as $post):
                        $postName    = $post['name']    ?? '';
                        $summary     = $post['summary'] ?? '';
                        $state       = $post['state']   ?? '';
                        $createTime  = $post['createTime'] ?? '';
                        $media       = $post['media'][0]['googleUrl'] ?? ($post['media'][0]['sourceUrl'] ?? '');
                    ?>
                    <div class="d-flex gap-3 p-3 border-bottom align-items-start">
                        <?php if ($media): ?>
                        <img src="<?= h($media) ?>" alt="Post image"
                             class="rounded" style="width:64px;height:64px;object-fit:cover;flex-shrink:0">
                        <?php else: ?>
                        <div class="rounded bg-light d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:64px;height:64px">
                            <i class="bi bi-image text-muted fs-4"></i>
                        </div>
                        <?php endif; ?>
                        <div class="flex-grow-1 min-w-0">
                            <div class="small fw-semibold mb-1"><?= h(mb_strimwidth($summary, 0, 120, '…')) ?></div>
                            <div class="d-flex gap-2 align-items-center">
                                <span class="badge bg-<?= $state === 'LIVE' ? 'success' : 'secondary' ?>"><?= h(ucfirst(strtolower($state))) ?></span>
                                <?php if ($createTime): ?>
                                <span class="text-muted" style="font-size:.7rem"><?= date('M j, Y', strtotime($createTime)) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($postName): ?>
                        <form method="post" class="flex-shrink-0"
                              onsubmit="return confirm('Delete this post from Google Business Profile?')">
                            <input type="hidden" name="action" value="delete_gbp_post">
                            <input type="hidden" name="post_name" value="<?= h($postName) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1" title="Delete">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($client['notes']): ?>
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-sticky me-2"></i>Notes</div>
            <div class="card-body text-muted small"><?= nl2br(h($client['notes'])) ?></div>
        </div>
        <?php endif; ?>

    </div><!-- /RIGHT -->
</div>

<!-- ── Edit Client Modal ─────────────────────────────────────────────────── -->
<div class="modal fade" id="editClientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow">
            <form method="post" action="/admin/clients.php" novalidate>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int)$client['id'] ?>">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit <?= h($client['company_name']) ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Company Name <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" class="form-control" required
                                   value="<?= h($client['company_name']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contact Name</label>
                            <input type="text" name="contact_name" class="form-control"
                                   value="<?= h($client['contact_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?= h($client['email'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="tel" name="phone" class="form-control"
                                   value="<?= h($client['phone'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Address</label>
                            <input type="text" name="address" class="form-control"
                                   value="<?= h($client['address'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"><?= h($client['notes'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12"><hr class="my-1">
                            <p class="fw-semibold small text-muted mb-0"><i class="bi bi-google me-1 text-warning"></i>Google Integration</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Google Ads Customer ID</label>
                            <input type="text" name="google_ads_customer_id" class="form-control"
                                   placeholder="e.g. 352-371-6554"
                                   value="<?= h($client['google_ads_customer_id'] ?? '') ?>">
                            <div class="form-text">Digits only, hyphens ignored.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Google Business Profile Location</label>
                            <input type="text" name="google_business_location" class="form-control font-monospace"
                                   placeholder="locations/1234567890"
                                   value="<?= h($client['google_business_location'] ?? '') ?>">
                            <div class="form-text">
                                <a href="/ad-automation/google-settings.php" target="_blank">Find on Google Settings →</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── New GBP Post Modal ─────────────────────────────────────────────────── -->
<?php if ($hasGbpLoc && $bizConnected): ?>
<div class="modal fade" id="gbpPostModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content shadow">
            <form method="post">
                <input type="hidden" name="action" value="create_gbp_post">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-geo-alt-fill me-2"></i>New Google Business Post</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Post Text <span class="text-danger">*</span></label>
                        <textarea name="summary" class="form-control" rows="4" required
                                  placeholder="What's new? Announce an offer, event, or update…" maxlength="1500"></textarea>
                        <div class="form-text">Max 1,500 characters.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Image URL</label>
                        <input type="url" name="image_url" class="form-control"
                               placeholder="https://…/photo.jpg">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Call-to-Action</label>
                            <select name="cta_type" class="form-select">
                                <option value="">— None —</option>
                                <option value="LEARN_MORE">Learn More</option>
                                <option value="CALL">Call</option>
                                <option value="BOOK">Book</option>
                                <option value="ORDER">Order</option>
                                <option value="SIGN_UP">Sign Up</option>
                                <option value="SHOP">Shop</option>
                                <option value="GET_OFFER">Get Offer</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">CTA URL</label>
                            <input type="url" name="cta_url" class="form-control" placeholder="https://…">
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Event Start</label>
                            <input type="date" name="start_date" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Event End</label>
                            <input type="date" name="end_date" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-send me-1"></i>Publish Post</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
