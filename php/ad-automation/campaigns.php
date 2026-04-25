<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';
requireRole(['admin', 'buyer']);

$svc      = new MarketingAutomationService();
$flashMsg = flash('success');
$errorMsg = flash('error');

$filterStatus = $_GET['status'] ?? '';
$campaigns    = $svc->getCampaigns($filterStatus);

$editCampaign = null;
$openModal    = false;
if (!empty($_GET['edit'])) {
    $editCampaign = $svc->getCampaign((int)$_GET['edit']);
    $openModal    = (bool)$editCampaign;
} elseif (!empty($_GET['new'])) {
    $openModal = true;
}

$pageTitle = 'Campaigns — Ad Automation — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold"><i class="bi bi-megaphone me-2 text-warning"></i>Campaigns</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="/ad-automation/index.php">Ad Automation</a></li>
                <li class="breadcrumb-item active">Campaigns</li>
            </ol>
        </nav>
    </div>
    <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#campaignModal">
        <i class="bi bi-plus-circle me-1"></i>New Campaign
    </button>
</div>

<div id="htmx-alert"></div>
<?php if ($flashMsg): ?><div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i><?= h($flashMsg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($errorMsg):  ?><div class="alert alert-danger  alert-dismissible fade show"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= h($errorMsg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<!-- ── Filter ──────────────────────────────────────────────────────────── -->
<div class="mb-3 d-flex gap-2 flex-wrap">
    <?php foreach ([''=>'All','draft'=>'Draft','active'=>'Active','paused'=>'Paused','completed'=>'Completed','archived'=>'Archived'] as $val => $label): ?>
    <a href="/ad-automation/campaigns.php<?= $val ? '?status='.$val : '' ?>"
       class="btn btn-sm <?= $filterStatus === $val ? 'btn-dark' : 'btn-outline-secondary' ?>">
        <?= $label ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- ── Campaigns Table ─────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($campaigns)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-megaphone fs-2 d-block mb-2 opacity-50"></i>
            <div>No campaigns yet.</div>
        </div>
        <?php else: ?>
        <table class="table table-hover mb-0 align-middle small">
            <thead class="table-light">
                <tr>
                    <th>Campaign</th>
                    <th>Platform</th>
                    <th>Type</th>
                    <th class="text-end">Daily Budget</th>
                    <th class="text-end">Total Budget</th>
                    <th>Dates</th>
                    <th class="text-center">Posts</th>
                    <th class="text-center">Ads</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($campaigns as $c): ?>
            <tr>
                <td class="fw-semibold"><?= h($c['campaign_name']) ?></td>
                <td><?= platformBadge($c['platform']) ?></td>
                <td class="text-muted"><?= h(ucfirst($c['campaign_type'])) ?></td>
                <td class="text-end"><?= $c['budget_daily']  !== null ? '$'.number_format((float)$c['budget_daily'],  2) : '—' ?></td>
                <td class="text-end"><?= $c['budget_total']  !== null ? '$'.number_format((float)$c['budget_total'],  2) : '—' ?></td>
                <td class="text-nowrap text-muted">
                    <?= $c['start_date'] ? h(date('M j', strtotime($c['start_date']))) : '—' ?>
                    <?= $c['end_date']   ? ' – ' . h(date('M j, Y', strtotime($c['end_date']))) : '' ?>
                </td>
                <td class="text-center"><?= (int)$c['post_count'] ?></td>
                <td class="text-center"><?= (int)$c['schedule_count'] ?></td>
                <td><?= statusBadge($c['status']) ?></td>
                <td class="text-end text-nowrap">
                    <button type="button" class="btn btn-link btn-sm p-0 text-secondary me-2"
                            onclick="openEditCampaign(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)"
                            title="Edit"><i class="bi bi-pencil"></i></button>
                    <button type="button" class="btn btn-link btn-sm p-0 text-danger"
                            hx-post="/ad-automation/api.php"
                            hx-vals='{"action":"delete_campaign","id":"<?= (int)$c['id'] ?>"}'
                            hx-target="#htmx-alert" hx-swap="innerHTML"
                            hx-confirm="Delete campaign &quot;<?= h(addslashes($c['campaign_name'])) ?>&quot;? Posts and schedules will be unlinked."
                            hx-on::after-request="if(event.detail.successful) setTimeout(()=>location.reload(),400)"
                            title="Delete"><i class="bi bi-trash"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- ── Campaign Modal ──────────────────────────────────────────────────── -->
<div class="modal fade" id="campaignModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form hx-post="/ad-automation/api.php"
                  hx-target="#htmx-alert" hx-swap="innerHTML"
                  hx-on::after-request="if(event.detail.successful && !document.querySelector('#htmx-alert .alert-danger')) { bootstrap.Modal.getInstance(document.getElementById('campaignModal')).hide(); setTimeout(()=>location.reload(),400); }">
                <input type="hidden" name="action" value="save_campaign">
                <input type="hidden" name="id" id="campaignId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title fw-semibold" id="campaignModalTitle">
                        <i class="bi bi-plus-circle me-2 text-warning"></i>New Campaign
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Campaign Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="campaign_name" id="campName" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status</label>
                            <select class="form-select" name="status" id="campStatus">
                                <option value="draft">Draft</option>
                                <option value="active">Active</option>
                                <option value="paused">Paused</option>
                                <option value="completed">Completed</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Platform</label>
                            <select class="form-select" name="platform" id="campPlatform">
                                <option value="meta">Meta (Facebook/Instagram)</option>
                                <option value="google">Google Ads</option>
                                <option value="both">Both</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Type</label>
                            <select class="form-select" name="campaign_type" id="campType">
                                <option value="awareness">Awareness</option>
                                <option value="traffic">Traffic</option>
                                <option value="leads">Leads</option>
                                <option value="sales">Sales</option>
                                <option value="engagement">Engagement</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Objective</label>
                            <input type="text" class="form-control" name="objective" id="campObjective" placeholder="e.g. Drive website traffic">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Daily Budget</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="budget_daily" id="campBudgetDaily" min="0" step="0.01">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Total Budget</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="budget_total" id="campBudgetTotal" min="0" step="0.01">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Start Date</label>
                            <input type="date" class="form-control" name="start_date" id="campStartDate">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">End Date</label>
                            <input type="date" class="form-control" name="end_date" id="campEndDate">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea class="form-control" name="notes" id="campNotes" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <span class="htmx-indicator spinner-border spinner-border-sm text-primary me-2"></span>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-floppy me-1"></i>Save Campaign</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditCampaign(c) {
    document.getElementById('campaignModalTitle').innerHTML = '<i class="bi bi-pencil me-2 text-warning"></i>Edit Campaign';
    document.getElementById('campaignId').value         = c.id;
    document.getElementById('campName').value           = c.campaign_name  || '';
    document.getElementById('campStatus').value         = c.status         || 'draft';
    document.getElementById('campPlatform').value       = c.platform       || 'meta';
    document.getElementById('campType').value           = c.campaign_type  || 'awareness';
    document.getElementById('campObjective').value      = c.objective      || '';
    document.getElementById('campBudgetDaily').value    = c.budget_daily   || '';
    document.getElementById('campBudgetTotal').value    = c.budget_total   || '';
    document.getElementById('campStartDate').value      = c.start_date     || '';
    document.getElementById('campEndDate').value        = c.end_date       || '';
    document.getElementById('campNotes').value          = c.notes          || '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('campaignModal')).show();
}
document.getElementById('campaignModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('campaignModalTitle').innerHTML = '<i class="bi bi-plus-circle me-2 text-warning"></i>New Campaign';
    document.getElementById('campaignId').value = '0';
    document.querySelector('#campaignModal form').reset();
});
<?php if ($openModal && $editCampaign): ?>
window.addEventListener('load', () => openEditCampaign(<?= json_encode($editCampaign) ?>));
<?php elseif ($openModal): ?>
window.addEventListener('load', () => bootstrap.Modal.getOrCreateInstance(document.getElementById('campaignModal')).show());
<?php endif; ?>
</script>

<?php
function platformBadge(string $p): string {
    $map=['facebook'=>['primary','Facebook'],'instagram'=>['danger','Instagram'],'google_business'=>['warning','Google Business'],'google'=>['warning','Google Ads'],'linkedin'=>['info','LinkedIn'],'meta'=>['primary','Meta'],'both'=>['secondary','Meta+Google'],'other'=>['secondary','Other']];
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
