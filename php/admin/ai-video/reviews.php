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

$reviews = $pdo->prepare(
    'SELECT r.*, j.job_status, j.video_url
     FROM ai_video_reviews r
     LEFT JOIN ai_video_jobs j ON j.id = r.job_id
     WHERE r.campaign_id = :cid
     ORDER BY r.created_at DESC'
);
$reviews->execute([':cid' => $campaignId]);
$reviews = $reviews->fetchAll();

$pageTitle = 'Reviews — ' . h($campaign['campaign_name']);
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="mb-4">
    <h1 class="h3 fw-bold mb-0"><i class="bi bi-chat-square-text me-2 text-primary"></i>Reviews</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="/admin/ai-video/index.php">AI Video Studio</a></li>
            <li class="breadcrumb-item"><a href="/admin/ai-video/view-campaign.php?id=<?= (int)$campaignId ?>"><?= h($campaign['campaign_name']) ?></a></li>
            <li class="breadcrumb-item active">Reviews</li>
        </ol>
    </nav>
</div>

<?php if (empty($reviews)): ?>
<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>No reviews yet for this campaign.</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-chat-square-text me-2 text-primary"></i>Review History
        <span class="badge bg-secondary ms-1"><?= count($reviews) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle small mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Reviewer</th>
                    <th>Status</th>
                    <th>Job</th>
                    <th>Comments</th>
                    <th>Revision Notes</th>
                    <th>Approved</th>
                    <th>Revision Requested</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($reviews as $r): ?>
            <tr>
                <td><?= (int)$r['id'] ?></td>
                <td>
                    <?php if ($r['reviewer_name']): ?>
                        <div class="fw-semibold"><?= h($r['reviewer_name']) ?></div>
                    <?php endif; ?>
                    <?php if ($r['reviewer_email']): ?>
                        <div class="text-muted small"><?= h($r['reviewer_email']) ?></div>
                    <?php endif; ?>
                    <?php if (!$r['reviewer_name'] && !$r['reviewer_email']): ?>
                        <span class="text-muted">Client Portal</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                    $rmap = ['pending'=>'secondary','approved'=>'success','revision_requested'=>'danger'];
                    $rc   = $rmap[$r['review_status']] ?? 'secondary';
                    echo '<span class="badge bg-'.$rc.'">'.h($r['review_status']).'</span>';
                    ?>
                </td>
                <td>
                    #<?= (int)$r['job_id'] ?>
                    <?php if ($r['video_url']): ?>
                    <a href="<?= h($r['video_url']) ?>" target="_blank" class="ms-1">
                        <i class="bi bi-play-circle"></i>
                    </a>
                    <?php endif; ?>
                </td>
                <td style="max-width:200px;">
                    <?= $r['comments'] ? h(mb_strimwidth($r['comments'], 0, 100, '…')) : '<span class="text-muted">—</span>' ?>
                </td>
                <td style="max-width:200px;" class="text-danger">
                    <?= $r['revision_notes'] ? h(mb_strimwidth($r['revision_notes'], 0, 100, '…')) : '<span class="text-muted">—</span>' ?>
                </td>
                <td class="text-nowrap text-success">
                    <?= $r['approved_at'] ? h(date('M j, Y', strtotime($r['approved_at']))) : '—' ?>
                </td>
                <td class="text-nowrap text-danger">
                    <?= $r['revision_requested_at'] ? h(date('M j, Y', strtotime($r['revision_requested_at']))) : '—' ?>
                </td>
                <td class="text-nowrap text-muted"><?= h(date('M j, Y', strtotime($r['created_at']))) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="mt-3">
    <a href="/admin/ai-video/view-campaign.php?id=<?= (int)$campaignId ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Campaign
    </a>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
