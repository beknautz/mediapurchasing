<?php
require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../../../config/ai_video.php';
requireRole(['admin', 'buyer']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-danger">POST method required.</div>';
    exit;
}

$jobId      = (int)($_POST['job_id'] ?? 0);
$campaignId = (int)($_POST['campaign_id'] ?? 0);

if (!$jobId || !$campaignId) {
    echo '<div class="alert alert-danger">Job ID and Campaign ID required.</div>';
    exit;
}

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

$camp = $pdo->prepare('SELECT * FROM ai_video_campaigns WHERE id = :id');
$camp->execute([':id' => $campaignId]);
$campaign = $camp->fetch();
if (!$campaign) {
    echo '<div class="alert alert-danger">Campaign not found.</div>';
    exit;
}

// Update or insert review record
$existing = $pdo->prepare(
    'SELECT id FROM ai_video_reviews WHERE campaign_id = :cid AND job_id = :jid ORDER BY id DESC LIMIT 1'
);
$existing->execute([':cid' => $campaignId, ':jid' => $jobId]);
$reviewRow = $existing->fetch();

if ($reviewRow) {
    $pdo->prepare(
        'UPDATE ai_video_reviews
            SET review_status = "approved", approved_at = NOW()
          WHERE id = :id'
    )->execute([':id' => $reviewRow['id']]);
} else {
    $pdo->prepare(
        'INSERT INTO ai_video_reviews
            (campaign_id, job_id, client_id, review_status, approved_at, created_at)
         VALUES
            (:cid, :jid, :client_id, "approved", NOW(), NOW())'
    )->execute([
        ':cid'       => $campaignId,
        ':jid'       => $jobId,
        ':client_id' => (int)$campaign['client_id'],
    ]);
}

// Update campaign status
$pdo->prepare(
    'UPDATE ai_video_campaigns SET status = "approved", updated_at = NOW() WHERE id = :id'
)->execute([':id' => $campaignId]);

// Notify admin
try {
    $notifSvc = new AiVideoNotificationService();
    $notifSvc->notifyAdminVideoReady($campaignId, $jobId);
} catch (Throwable $e) {
    error_log('[AI Video] approve notification error: ' . $e->getMessage());
}

echo '<div class="alert alert-success">';
echo '<h5 class="alert-heading"><i class="bi bi-check-circle-fill me-2"></i>Video Approved!</h5>';
echo '<div class="mb-2">Campaign status updated to <strong>Approved</strong>.</div>';
echo '<div class="mb-0 d-flex gap-2">';
echo '<a href="/admin/ai-video/view-campaign.php?id=' . (int)$campaignId . '" class="btn btn-sm btn-success"><i class="bi bi-eye me-1"></i>View Campaign</a>';
echo '<a href="/admin/ai-video/index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list me-1"></i>All Campaigns</a>';
echo '</div>';
echo '</div>';
