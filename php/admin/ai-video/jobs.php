<?php
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../config/ai_video.php';
requireRole(['admin', 'buyer']);

$campaignId = (int)($_GET['campaign_id'] ?? 0);
if (!$campaignId) redirect('/admin/ai-video/index.php');

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

$campaign = $pdo->prepare('SELECT * FROM ai_video_campaigns WHERE id = :id');
$campaign->execute([':id' => $campaignId]);
$campaign = $campaign->fetch();
if (!$campaign) redirect('/admin/ai-video/index.php');

$jobs = $pdo->prepare(
    'SELECT j.*,
        p.veo_prompt
     FROM ai_video_jobs j
     LEFT JOIN ai_video_prompts p ON p.id = j.prompt_id
     WHERE j.campaign_id = :cid
     ORDER BY j.created_at DESC'
);
$jobs->execute([':cid' => $campaignId]);
$jobs = $jobs->fetchAll();

$pageTitle = 'Video Jobs — ' . h($campaign['campaign_name']);
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="mb-4">
    <h1 class="h3 fw-bold mb-0"><i class="bi bi-film me-2 text-primary"></i>Video Jobs</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="/admin/ai-video/index.php">AI Video Studio</a></li>
            <li class="breadcrumb-item"><a href="/admin/ai-video/view-campaign.php?id=<?= (int)$campaignId ?>"><?= h($campaign['campaign_name']) ?></a></li>
            <li class="breadcrumb-item active">Jobs</li>
        </ol>
    </nav>
</div>

<div id="jobs-list">
<?php if (empty($jobs)): ?>
<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>No video generation jobs yet for this campaign.</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-film me-2 text-primary"></i>Jobs for <?= h($campaign['campaign_name']) ?>
        <span class="badge bg-secondary ms-1"><?= count($jobs) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle small mb-0">
            <thead class="table-light">
                <tr>
                    <th>Job #</th>
                    <th>Status</th>
                    <th>Provider</th>
                    <th>Duration</th>
                    <th>Ratio</th>
                    <th>Est. Cost</th>
                    <th>Actual Cost</th>
                    <th>Created</th>
                    <th>Completed</th>
                    <th>Video</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($jobs as $j): ?>
            <?php
                $jmap = ['queued'=>'warning','processing'=>'info','completed'=>'success','failed'=>'danger'];
                $jc   = $jmap[$j['job_status']] ?? 'secondary';
                $isParent = $j['parent_job_id'] ? 'ps-3' : '';
            ?>
            <tr id="job-row-<?= (int)$j['id'] ?>">
                <td class="<?= $isParent ?>">
                    <?php if ($j['parent_job_id']): ?>
                        <span class="text-muted small"><i class="bi bi-arrow-return-right me-1"></i></span>
                    <?php endif; ?>
                    <strong>#<?= (int)$j['id'] ?></strong>
                </td>
                <td>
                    <span class="badge bg-<?= $jc ?>"><?= h($j['job_status']) ?></span>
                    <?php if ($j['progress_percent'] > 0 && $j['progress_percent'] < 100): ?>
                    <div class="progress mt-1" style="height:4px;width:60px;">
                        <div class="progress-bar" style="width:<?= (int)$j['progress_percent'] ?>%"></div>
                    </div>
                    <?php endif; ?>
                </td>
                <td><?= h($j['provider']) ?></td>
                <td><?= $j['requested_duration_seconds'] ? h($j['requested_duration_seconds']).'s' : '—' ?></td>
                <td><?= h($j['requested_aspect_ratio'] ?? '—') ?></td>
                <td>$<?= number_format((float)$j['estimated_provider_cost'], 2) ?></td>
                <td>$<?= number_format((float)$j['actual_provider_cost'], 2) ?></td>
                <td class="text-nowrap"><?= h(date('M j, Y g:ia', strtotime($j['created_at']))) ?></td>
                <td class="text-nowrap">
                    <?= $j['completed_at'] ? h(date('M j, Y g:ia', strtotime($j['completed_at']))) : '—' ?>
                </td>
                <td>
                    <?php if ($j['video_url']): ?>
                    <a href="<?= h($j['video_url']) ?>" target="_blank" class="btn btn-sm btn-outline-success py-0 px-2">
                        <i class="bi bi-play-circle me-1"></i>Watch
                    </a>
                    <a href="/admin/ai-video/actions/download-video.php?job_id=<?= (int)$j['id'] ?>"
                       class="btn btn-sm btn-outline-primary py-0 px-2" title="Download to desktop">
                        <i class="bi bi-download me-1"></i>Download
                    </a>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td>
                    <?php if (in_array($j['job_status'], ['queued','processing'])): ?>
                    <button class="btn btn-sm btn-outline-warning py-0 px-2"
                            hx-get="/admin/ai-video/actions/poll-video-status.php?job_id=<?= (int)$j['id'] ?>"
                            hx-target="#poll-result-<?= (int)$j['id'] ?>"
                            hx-swap="innerHTML">
                        <i class="bi bi-arrow-repeat me-1"></i>Check
                    </button>
                    <?php elseif ($j['job_status'] === 'failed' && $j['error_message']): ?>
                    <span class="text-danger small" title="<?= h($j['error_message']) ?>">
                        <i class="bi bi-exclamation-circle me-1"></i>Error
                    </span>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-outline-secondary py-0 px-2 mt-1"
                            hx-post="/admin/ai-video/actions/rerender-job.php"
                            hx-vals='{"job_id": "<?= (int)$j['id'] ?>"}'
                            hx-target="#poll-result-<?= (int)$j['id'] ?>"
                            hx-swap="innerHTML"
                            hx-confirm="Queue a new render using the same prompt?"
                            title="Rerender with current Veo settings">
                        <i class="bi bi-arrow-clockwise me-1"></i>Rerender
                    </button>
                    <div id="poll-result-<?= (int)$j['id'] ?>" class="mt-1"></div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
</div>

<div class="mt-3">
    <a href="/admin/ai-video/view-campaign.php?id=<?= (int)$campaignId ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Campaign
    </a>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
