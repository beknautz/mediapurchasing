<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';
requireRole(['admin', 'buyer']);

$svc      = new MarketingAutomationService();
$flashMsg = flash('success');
$errorMsg = flash('error');

$filterStatus   = $_GET['status']   ?? '';
$filterPlatform = $_GET['platform'] ?? '';
$schedules  = $svc->getAdSchedules(array_filter(['status'=>$filterStatus,'platform'=>$filterPlatform]));
$campaigns  = $svc->getCampaignOptions();
$adCopyMeta = $svc->getApprovedAdCopyOptions('meta');
$adCopyGoogle = $svc->getApprovedAdCopyOptions('google');

$editSchedule = null;
$openModal    = false;
if (!empty($_GET['edit'])) {
    $editSchedule = $svc->getAdSchedule((int)$_GET['edit']);
    $openModal    = (bool)$editSchedule;
} elseif (!empty($_GET['new'])) {
    $openModal = true;
}

$pageTitle = 'Ad Schedules — Ad Automation — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold"><i class="bi bi-calendar-plus me-2 text-info"></i>Ad Schedules</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="/ad-automation/index.php">Ad Automation</a></li>
                <li class="breadcrumb-item active">Schedules</li>
            </ol>
        </nav>
    </div>
    <button type="button" class="btn btn-info btn-sm text-white" data-bs-toggle="modal" data-bs-target="#scheduleModal">
        <i class="bi bi-plus-circle me-1"></i>New Schedule
    </button>
</div>

<div id="htmx-alert"></div>
<?php if ($flashMsg): ?><div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i><?= h($flashMsg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($errorMsg):  ?><div class="alert alert-danger  alert-dismissible fade show"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= h($errorMsg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<!-- ── Filters ─────────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex gap-2 flex-wrap align-items-end">
            <div>
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All</option>
                    <?php foreach (['draft','ready','scheduled','active','paused','completed','failed'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filterStatus===$s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label small mb-1">Platform</label>
                <select name="platform" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="meta"   <?= $filterPlatform==='meta'   ? 'selected' : '' ?>>Meta</option>
                    <option value="google" <?= $filterPlatform==='google' ? 'selected' : '' ?>>Google</option>
                </select>
            </div>
            <?php if ($filterStatus || $filterPlatform): ?>
            <a href="/ad-automation/schedules.php" class="btn btn-sm btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- ── Schedules Table ─────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($schedules)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-calendar-x fs-2 d-block mb-2 opacity-50"></i>
            <div>No ad schedules yet.</div>
        </div>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle small">
            <thead class="table-light">
                <tr>
                    <th>Ad Name</th>
                    <th>Platform</th>
                    <th>Campaign</th>
                    <th>Ad Copy</th>
                    <th class="text-end">Daily Budget</th>
                    <th>Dates</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($schedules as $s): ?>
            <tr>
                <td class="fw-semibold"><?= h($s['ad_name']) ?></td>
                <td><?= platformBadge($s['platform']) ?></td>
                <td class="text-muted"><?= h($s['campaign_name'] ?? '—') ?></td>
                <td class="text-muted small"><?= $s['headline'] ? h(mb_strimwidth($s['headline'],0,40,'…')) : '—' ?></td>
                <td class="text-end"><?= $s['daily_budget'] !== null ? '$'.number_format((float)$s['daily_budget'],2) : '—' ?></td>
                <td class="text-nowrap text-muted">
                    <?= $s['start_datetime'] ? h(date('M j', strtotime($s['start_datetime']))) : '—' ?>
                    <?= $s['end_datetime']   ? ' – '.h(date('M j', strtotime($s['end_datetime']))) : '' ?>
                </td>
                <td>
                    <select class="form-select form-select-sm"
                            style="min-width:110px;"
                            hx-post="/ad-automation/api.php"
                            hx-vals='{"action":"update_schedule_status","id":"<?= (int)$s['id'] ?>"}'
                            hx-include="this"
                            hx-target="#htmx-alert"
                            hx-swap="innerHTML"
                            name="status">
                        <?php foreach (['draft','ready','scheduled','active','paused','completed','failed'] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $s['status']===$opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td class="text-end text-nowrap">
                    <button type="button" class="btn btn-link btn-sm p-0 text-secondary me-2"
                            onclick="openEditSchedule(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)"
                            title="Edit"><i class="bi bi-pencil"></i></button>
                    <button type="button" class="btn btn-link btn-sm p-0 text-danger"
                            hx-post="/ad-automation/api.php"
                            hx-vals='{"action":"delete_schedule","id":"<?= (int)$s['id'] ?>"}'
                            hx-target="#htmx-alert" hx-swap="innerHTML"
                            hx-confirm="Delete this ad schedule?"
                            hx-on::after-request="if(event.detail.successful) setTimeout(()=>location.reload(),400)"
                            title="Delete"><i class="bi bi-trash"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Schedule Modal ──────────────────────────────────────────────────── -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form hx-post="/ad-automation/api.php"
                  hx-target="#htmx-alert" hx-swap="innerHTML"
                  hx-on::after-request="if(event.detail.successful && !document.querySelector('#htmx-alert .alert-danger')) { bootstrap.Modal.getInstance(document.getElementById('scheduleModal')).hide(); setTimeout(()=>location.reload(),400); }">
                <input type="hidden" name="action" value="save_schedule">
                <input type="hidden" name="id" id="schedId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title fw-semibold" id="schedModalTitle">
                        <i class="bi bi-plus-circle me-2 text-info"></i>New Ad Schedule
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Ad Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="ad_name" id="schedAdName" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Platform <span class="text-danger">*</span></label>
                            <select class="form-select" name="platform" id="schedPlatform" required onchange="updateAdCopyOptions()">
                                <option value="meta">Meta</option>
                                <option value="google">Google Ads</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Campaign</label>
                            <select class="form-select" name="campaign_id" id="schedCampaign">
                                <option value="">— None —</option>
                                <?php foreach ($campaigns as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"><?= h($c['campaign_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Ad Copy (Approved)</label>
                            <select class="form-select" name="ad_copy_id" id="schedAdCopy">
                                <option value="">— None —</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Start</label>
                            <input type="datetime-local" class="form-control" name="start_datetime" id="schedStart">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">End</label>
                            <input type="datetime-local" class="form-control" name="end_datetime" id="schedEnd">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Daily Budget</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="daily_budget" id="schedBudget" min="0" step="0.01">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Target Location</label>
                            <input type="text" class="form-control" name="target_location" id="schedLocation" placeholder="e.g. Oregon">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status</label>
                            <select class="form-select" name="status" id="schedStatus">
                                <option value="draft">Draft</option>
                                <option value="ready">Ready</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="active">Active</option>
                                <option value="paused">Paused</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Target Audience</label>
                            <input type="text" class="form-control" name="target_audience" id="schedAudience" placeholder="e.g. Farmers 35-65">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea class="form-control" name="notes" id="schedNotes" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <span class="htmx-indicator spinner-border spinner-border-sm text-primary me-2"></span>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white"><i class="bi bi-floppy me-1"></i>Save Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const AD_COPY_OPTIONS = {
    meta:   <?= json_encode($adCopyMeta) ?>,
    google: <?= json_encode($adCopyGoogle) ?>,
};

function updateAdCopyOptions(selectedId) {
    const platform = document.getElementById('schedPlatform').value;
    const sel = document.getElementById('schedAdCopy');
    sel.innerHTML = '<option value="">— None —</option>';
    (AD_COPY_OPTIONS[platform] || []).forEach(function(c) {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.headline || '(no headline)';
        if (selectedId && c.id == selectedId) opt.selected = true;
        sel.appendChild(opt);
    });
}

function openEditSchedule(s) {
    document.getElementById('schedModalTitle').innerHTML = '<i class="bi bi-pencil me-2 text-info"></i>Edit Schedule';
    document.getElementById('schedId').value       = s.id;
    document.getElementById('schedAdName').value   = s.ad_name        || '';
    document.getElementById('schedPlatform').value = s.platform       || 'meta';
    document.getElementById('schedCampaign').value = s.campaign_id    || '';
    document.getElementById('schedStart').value    = s.start_datetime ? s.start_datetime.replace(' ','T').slice(0,16) : '';
    document.getElementById('schedEnd').value      = s.end_datetime   ? s.end_datetime.replace(' ','T').slice(0,16)   : '';
    document.getElementById('schedBudget').value   = s.daily_budget   || '';
    document.getElementById('schedLocation').value = s.target_location || '';
    document.getElementById('schedAudience').value = s.target_audience || '';
    document.getElementById('schedStatus').value   = s.status         || 'draft';
    document.getElementById('schedNotes').value    = s.notes          || '';
    updateAdCopyOptions(s.ad_copy_id);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('scheduleModal')).show();
}

document.getElementById('scheduleModal').addEventListener('shown.bs.modal', function () {
    if (!document.getElementById('schedId').value || document.getElementById('schedId').value === '0') {
        updateAdCopyOptions();
    }
});
document.getElementById('scheduleModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('schedModalTitle').innerHTML = '<i class="bi bi-plus-circle me-2 text-info"></i>New Ad Schedule';
    document.getElementById('schedId').value = '0';
    document.querySelector('#scheduleModal form').reset();
    updateAdCopyOptions();
});

<?php if ($openModal && $editSchedule): ?>
window.addEventListener('load', () => openEditSchedule(<?= json_encode($editSchedule) ?>));
<?php elseif ($openModal): ?>
window.addEventListener('load', () => { updateAdCopyOptions(); bootstrap.Modal.getOrCreateInstance(document.getElementById('scheduleModal')).show(); });
<?php endif; ?>
</script>

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
