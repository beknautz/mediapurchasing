<?php
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../config/ai_video.php';
requireRole(['admin', 'buyer']);

// ── Stats ────────────────────────────────────────────────────────────────────
$db = new BaseService();
$pdo = (function() {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
})();

$stats = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        SUM(status NOT IN ('draft','archived')) AS active,
        SUM(status = 'ready_for_review') AS ready_for_review,
        SUM(status = 'approved') AS approved
     FROM ai_video_campaigns"
)->fetch();

$campaigns = $pdo->query(
    "SELECT c.*,
        (SELECT COUNT(*) FROM ai_video_jobs j WHERE j.campaign_id = c.id) AS job_count,
        (SELECT COALESCE(SUM(total_cost),0) FROM ai_video_costs co WHERE co.campaign_id = c.id) AS total_cost
     FROM ai_video_campaigns c
     ORDER BY c.created_at DESC
     LIMIT 200"
)->fetchAll();

function statusBadge(string $status): string {
    $map = [
        'draft'              => 'secondary',
        'script_generated'   => 'info',
        'prompt_generated'   => 'primary',
        'video_queued'       => 'warning',
        'generating'         => 'warning',
        'ready_for_review'   => 'success',
        'approved'           => 'success',
        'revision_requested' => 'danger',
        'failed'             => 'danger',
        'archived'           => 'dark',
    ];
    $color = $map[$status] ?? 'secondary';
    $label = ucwords(str_replace('_', ' ', $status));
    return '<span class="badge bg-' . $color . '">' . h($label) . '</span>';
}

$pageTitle = 'AI Video Studio — MediaBuy';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">
            <i class="bi bi-camera-video-fill me-2 text-danger"></i>AI Video Studio
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">AI Video Studio</li>
            </ol>
        </nav>
    </div>
    <a href="/admin/ai-video/create-campaign.php" class="btn btn-danger">
        <i class="bi bi-plus-circle me-1"></i>New Campaign
    </a>
</div>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3 h-100">
            <div class="fs-2 fw-bold text-secondary"><?= (int)($stats['total'] ?? 0) ?></div>
            <div class="small text-muted">Total Campaigns</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3 h-100">
            <div class="fs-2 fw-bold text-primary"><?= (int)($stats['active'] ?? 0) ?></div>
            <div class="small text-muted">Active</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3 h-100">
            <div class="fs-2 fw-bold text-success"><?= (int)($stats['ready_for_review'] ?? 0) ?></div>
            <div class="small text-muted">Ready for Review</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3 h-100">
            <div class="fs-2 fw-bold text-warning"><?= (int)($stats['approved'] ?? 0) ?></div>
            <div class="small text-muted">Approved</div>
        </div>
    </div>
</div>

<!-- Campaigns Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-film me-2 text-danger"></i>All Campaigns</span>
        <span class="badge bg-secondary"><?= count($campaigns) ?> total</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($campaigns)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-camera-video display-4 d-block mb-3"></i>
                No campaigns yet. <a href="/admin/ai-video/create-campaign.php">Create your first one!</a>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Campaign</th>
                        <th>Platform</th>
                        <th>Status</th>
                        <th>Videos</th>
                        <th>Total Cost</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($campaigns as $c): ?>
                <tr>
                    <td class="text-muted"><?= (int)$c['id'] ?></td>
                    <td>
                        <a href="/admin/ai-video/view-campaign.php?id=<?= (int)$c['id'] ?>" class="fw-semibold text-decoration-none">
                            <?= h($c['campaign_name']) ?>
                        </a>
                        <?php if ($c['platform']): ?>
                            <div class="text-muted small"><?= h($c['platform']) ?> &middot; <?= h($c['video_length'] ?? '') ?>s &middot; <?= h($c['aspect_ratio'] ?? '') ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= h($c['platform'] ?? '—') ?></td>
                    <td><?= statusBadge($c['status']) ?></td>
                    <td class="text-center"><?= (int)$c['job_count'] ?></td>
                    <td>$<?= number_format((float)$c['total_cost'], 2) ?></td>
                    <td class="text-nowrap"><?= h(date('M j, Y', strtotime($c['created_at']))) ?></td>
                    <td class="text-end">
                        <a href="/admin/ai-video/view-campaign.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye me-1"></i>View
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
