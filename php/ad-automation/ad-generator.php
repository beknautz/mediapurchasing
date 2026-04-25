<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';
requireRole(['admin', 'buyer']);

$svc       = new MarketingAutomationService();
$flashMsg  = flash('success');
$errorMsg  = flash('error');
$campaigns = $svc->getCampaignOptions();
$savedCopy = $svc->getAdCopy([]);

$pageTitle = 'Ad Generator — Ad Automation — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold"><i class="bi bi-stars me-2 text-success"></i>AI Ad Generator</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="/ad-automation/index.php">Ad Automation</a></li>
                <li class="breadcrumb-item active">Ad Generator</li>
            </ol>
        </nav>
    </div>
</div>

<div id="htmx-alert"></div>
<?php if ($flashMsg): ?><div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i><?= h($flashMsg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($errorMsg):  ?><div class="alert alert-danger  alert-dismissible fade show"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= h($errorMsg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="row g-4">
    <!-- ── Generator Form ──────────────────────────────────────────────── -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-pencil-square me-2 text-success"></i>Generate Ad Copy
            </div>
            <div class="card-body">
                <form hx-post="/ad-automation/api.php"
                      hx-target="#adResult"
                      hx-swap="innerHTML"
                      hx-indicator="#genSpinner">
                    <input type="hidden" name="action" value="generate_ad">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Platform <span class="text-danger">*</span></label>
                        <select class="form-select" name="platform" id="genPlatform" required>
                            <option value="meta">Meta (Facebook/Instagram)</option>
                            <option value="google">Google Ads</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Business / Service <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="business_service" required placeholder="e.g. Harvest Capital">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Offer</label>
                        <input type="text" class="form-control" name="offer" placeholder="e.g. Free consultation">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Target Audience</label>
                        <input type="text" class="form-control" name="target_audience" placeholder="e.g. Farmers in Oregon">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Location</label>
                        <input type="text" class="form-control" name="location" placeholder="e.g. Pacific Northwest">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tone</label>
                        <select class="form-select" name="tone">
                            <option value="professional">Professional</option>
                            <option value="friendly">Friendly</option>
                            <option value="urgent">Urgent</option>
                            <option value="inspirational">Inspirational</option>
                            <option value="humorous">Humorous</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Objective</label>
                        <select class="form-select" name="objective">
                            <option value="awareness">Awareness</option>
                            <option value="traffic">Traffic</option>
                            <option value="leads">Leads</option>
                            <option value="sales">Sales</option>
                            <option value="engagement">Engagement</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Campaign (optional)</label>
                        <select class="form-select" name="campaign_id">
                            <option value="">— None —</option>
                            <?php foreach ($campaigns as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= h($c['campaign_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <span id="genSpinner" class="htmx-indicator spinner-border spinner-border-sm me-2"></span>
                        <i class="bi bi-stars me-1"></i>Generate Ad Copy
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Result Panel ────────────────────────────────────────────────── -->
    <div class="col-lg-8">
        <div id="adResult">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-stars fs-2 d-block mb-2 opacity-50"></i>
                    <div>Fill in the form and click <strong>Generate Ad Copy</strong> to see results here.</div>
                    <div class="small mt-2 text-muted">AI generation placeholder active — connect OpenAI or Claude API to get real copy.</div>
                </div>
            </div>
        </div>

        <!-- ── Saved Ad Copy ────────────────────────────────────────────── -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-archive me-2"></i>Saved Ad Copy</span>
                <span class="badge bg-secondary"><?= count($savedCopy) ?></span>
            </div>
            <div class="card-body p-0" id="savedCopyList">
                <?php if (empty($savedCopy)): ?>
                <div class="text-center text-muted py-4 small">No saved copy yet.</div>
                <?php else: ?>
                <table class="table table-sm table-hover mb-0 align-middle small">
                    <thead class="table-light">
                        <tr><th>Headline / Copy</th><th>Platform</th><th>Campaign</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($savedCopy as $copy): ?>
                    <?php
                        $headline = $copy['headline'];
                        if (!$headline && $copy['google_headlines']) {
                            $hl = json_decode($copy['google_headlines'], true);
                            $headline = $hl[0] ?? '';
                        }
                    ?>
                    <tr>
                        <td><?= h($headline ?: '—') ?></td>
                        <td><?= platformBadge($copy['platform']) ?></td>
                        <td class="text-muted"><?= h($copy['campaign_name'] ?? '—') ?></td>
                        <td><?= statusBadge($copy['status']) ?></td>
                        <td class="text-end text-nowrap">
                            <?php if ($copy['status'] !== 'approved'): ?>
                            <button class="btn btn-link btn-sm p-0 text-success me-1"
                                    hx-post="/ad-automation/api.php"
                                    hx-vals='{"action":"approve_ad_copy","id":"<?= (int)$copy['id'] ?>","status":"approved"}'
                                    hx-target="#htmx-alert" hx-swap="innerHTML"
                                    hx-on::after-request="if(event.detail.successful) setTimeout(()=>location.reload(),400)"
                                    title="Approve"><i class="bi bi-check-circle"></i></button>
                            <?php endif; ?>
                            <?php if ($copy['status'] !== 'rejected'): ?>
                            <button class="btn btn-link btn-sm p-0 text-danger"
                                    hx-post="/ad-automation/api.php"
                                    hx-vals='{"action":"approve_ad_copy","id":"<?= (int)$copy['id'] ?>","status":"rejected"}'
                                    hx-target="#htmx-alert" hx-swap="innerHTML"
                                    hx-confirm="Reject this ad copy?"
                                    hx-on::after-request="if(event.detail.successful) setTimeout(()=>location.reload(),400)"
                                    title="Reject"><i class="bi bi-x-circle"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
function platformBadge(string $p): string {
    $map=['facebook'=>['primary','Facebook'],'instagram'=>['danger','Instagram'],'google_business'=>['warning','Google Business'],'google'=>['warning','Google Ads'],'linkedin'=>['info','LinkedIn'],'meta'=>['primary','Meta'],'both'=>['secondary','Meta+Google']];
    $d=$map[$p]??['secondary',htmlspecialchars($p)];$t=$d[0]==='warning'?' text-dark':'';
    return '<span class="badge bg-'.$d[0].$t.'">'.htmlspecialchars($d[1]).'</span>';
}
function statusBadge(string $s): string {
    $map=['draft'=>'secondary','scheduled'=>'primary','published'=>'success','active'=>'success','approved'=>'success','failed'=>'danger','cancelled'=>'dark','rejected'=>'danger','paused'=>'warning','completed'=>'info','ready'=>'info','archived'=>'secondary'];
    $c=$map[$s]??'secondary';$t=$c==='warning'?' text-dark':'';
    return '<span class="badge bg-'.$c.$t.'">'.htmlspecialchars(ucfirst($s)).'</span>';
}
require_once __DIR__ . '/../includes/footer.php';
?>
