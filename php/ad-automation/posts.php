<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';
requireRole(['admin', 'buyer']);

$svc       = new MarketingAutomationService();
$flashMsg  = flash('success');
$errorMsg  = flash('error');

$filterStatus   = $_GET['status']   ?? '';
$filterPlatform = $_GET['platform'] ?? '';
$posts     = $svc->getSocialPosts(array_filter(['status' => $filterStatus, 'platform' => $filterPlatform]));
$campaigns = $svc->getCampaignOptions();

$editPost  = null;
$openModal = false;
if (!empty($_GET['edit'])) {
    $editPost  = $svc->getSocialPost((int)$_GET['edit']);
    $openModal = (bool)$editPost;
} elseif (!empty($_GET['new'])) {
    $openModal = true;
}

$pageTitle = 'Social Posts — Ad Automation — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold"><i class="bi bi-collection me-2 text-primary"></i>Social Posts</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="/ad-automation/index.php">Ad Automation</a></li>
                <li class="breadcrumb-item active">Posts</li>
            </ol>
        </nav>
    </div>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#postModal">
        <i class="bi bi-plus-circle me-1"></i>New Post
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
                    <?php foreach (['draft','scheduled','published','failed','cancelled'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label small mb-1">Platform</label>
                <select name="platform" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All</option>
                    <?php foreach (['facebook','instagram','google_business','linkedin'] as $p): ?>
                    <option value="<?= $p ?>" <?= $filterPlatform === $p ? 'selected' : '' ?>><?= ucwords(str_replace('_',' ',$p)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($filterStatus || $filterPlatform): ?>
            <a href="/ad-automation/posts.php" class="btn btn-sm btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- ── Posts Table ─────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($posts)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-collection fs-2 d-block mb-2 opacity-50"></i>
            <div>No posts yet.</div>
        </div>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle small">
            <thead class="table-light">
                <tr>
                    <th>Title</th>
                    <th>Platform</th>
                    <th>Campaign</th>
                    <th>Status</th>
                    <th>Scheduled</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($posts as $p): ?>
            <tr>
                <td class="fw-semibold"><?= h($p['title']) ?></td>
                <td><?= platformBadge($p['platform']) ?></td>
                <td class="text-muted"><?= h($p['campaign_name'] ?? '—') ?></td>
                <td><?= statusBadge($p['status']) ?></td>
                <td class="text-nowrap text-muted">
                    <?= $p['scheduled_at'] ? h(date('M j, Y g:ia', strtotime($p['scheduled_at']))) : '—' ?>
                </td>
                <td class="text-end text-nowrap">
                    <button type="button" class="btn btn-link btn-sm p-0 text-secondary me-2"
                            onclick="openEditPost(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)"
                            title="Edit"><i class="bi bi-pencil"></i></button>
                    <button type="button" class="btn btn-link btn-sm p-0 text-info me-2"
                            hx-post="/ad-automation/api.php"
                            hx-vals='{"action":"duplicate_post","id":"<?= (int)$p['id'] ?>"}'
                            hx-target="#htmx-alert" hx-swap="innerHTML"
                            hx-confirm="Duplicate this post?"
                            title="Duplicate"><i class="bi bi-copy"></i></button>
                    <button type="button" class="btn btn-link btn-sm p-0 text-danger"
                            hx-post="/ad-automation/api.php"
                            hx-vals='{"action":"delete_post","id":"<?= (int)$p['id'] ?>"}'
                            hx-target="#htmx-alert" hx-swap="innerHTML"
                            hx-confirm="Delete this post?"
                            hx-on::after-request="if(event.detail.successful) htmx.trigger('#posts-list','refresh')"
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

<!-- ── Post Modal ──────────────────────────────────────────────────────── -->
<div class="modal fade" id="postModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form hx-post="/ad-automation/api.php"
                  hx-target="#htmx-alert" hx-swap="innerHTML"
                  hx-on::after-request="if(event.detail.successful && event.detail.xhr.status===200 && !document.querySelector('#htmx-alert .alert-danger')) { bootstrap.Modal.getInstance(document.getElementById('postModal')).hide(); setTimeout(()=>location.reload(),400); }">
                <input type="hidden" name="action" value="save_post">
                <input type="hidden" name="id" id="postId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title fw-semibold" id="postModalTitle">
                        <i class="bi bi-plus-circle me-2 text-primary"></i>New Post
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" id="postTitle" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Platform <span class="text-danger">*</span></label>
                            <select class="form-select" name="platform" id="postPlatform" required>
                                <option value="facebook">Facebook</option>
                                <option value="instagram">Instagram</option>
                                <option value="google_business">Google Business Profile</option>
                                <option value="linkedin">LinkedIn</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Caption</label>
                            <textarea class="form-control" name="caption" id="postCaption" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Image URL</label>
                            <input type="url" class="form-control" name="image_url" id="postImageUrl" placeholder="https://...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Video URL</label>
                            <input type="url" class="form-control" name="video_url" id="postVideoUrl" placeholder="https://...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Campaign</label>
                            <select class="form-select" name="campaign_id" id="postCampaign">
                                <option value="">— None —</option>
                                <?php foreach ($campaigns as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"><?= h($c['campaign_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Status</label>
                            <select class="form-select" name="status" id="postStatus">
                                <option value="draft">Draft</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="published">Published</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Scheduled At</label>
                            <input type="datetime-local" class="form-control" name="scheduled_at" id="postScheduled">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea class="form-control" name="notes" id="postNotes" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <span class="htmx-indicator spinner-border spinner-border-sm text-primary me-2"></span>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-floppy me-1"></i>Save Post</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditPost(p) {
    document.getElementById('postModalTitle').innerHTML = '<i class="bi bi-pencil me-2 text-primary"></i>Edit Post';
    document.getElementById('postId').value           = p.id;
    document.getElementById('postTitle').value        = p.title        || '';
    document.getElementById('postPlatform').value     = p.platform     || 'facebook';
    document.getElementById('postCaption').value      = p.caption      || '';
    document.getElementById('postImageUrl').value     = p.image_url    || '';
    document.getElementById('postVideoUrl').value     = p.video_url    || '';
    document.getElementById('postCampaign').value     = p.campaign_id  || '';
    document.getElementById('postStatus').value       = p.status       || 'draft';
    document.getElementById('postScheduled').value    = p.scheduled_at ? p.scheduled_at.replace(' ','T').slice(0,16) : '';
    document.getElementById('postNotes').value        = p.notes        || '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('postModal')).show();
}
document.getElementById('postModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('postModalTitle').innerHTML = '<i class="bi bi-plus-circle me-2 text-primary"></i>New Post';
    document.getElementById('postId').value = '0';
    document.querySelector('#postModal form').reset();
});
<?php if ($openModal): ?>
window.addEventListener('load', function() {
    <?php if ($editPost): ?>
    openEditPost(<?= json_encode($editPost) ?>);
    <?php else: ?>
    bootstrap.Modal.getOrCreateInstance(document.getElementById('postModal')).show();
    <?php endif; ?>
});
<?php endif; ?>
</script>

<?php
function platformBadge(string $p): string {
    $map = ['facebook'=>['primary','Facebook'],'instagram'=>['danger','Instagram'],'google_business'=>['warning','Google Business'],'google'=>['warning','Google Ads'],'linkedin'=>['info','LinkedIn'],'meta'=>['primary','Meta'],'both'=>['secondary','Meta+Google']];
    $d = $map[$p] ?? ['secondary', htmlspecialchars($p)];
    $t = $d[0]==='warning' ? ' text-dark' : '';
    return '<span class="badge bg-'.$d[0].$t.'">'.htmlspecialchars($d[1]).'</span>';
}
function statusBadge(string $s): string {
    $map = ['draft'=>'secondary','scheduled'=>'primary','published'=>'success','active'=>'success','approved'=>'success','failed'=>'danger','cancelled'=>'dark','rejected'=>'danger','paused'=>'warning','completed'=>'info','ready'=>'info','archived'=>'secondary'];
    $c = $map[$s] ?? 'secondary';
    $t = $c==='warning' ? ' text-dark' : '';
    return '<span class="badge bg-'.$c.$t.'">'.htmlspecialchars(ucfirst($s)).'</span>';
}

require_once __DIR__ . '/../includes/footer.php';
?>
