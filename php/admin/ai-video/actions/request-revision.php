<?php
require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../../../config/ai_video.php';
requireRole(['admin', 'buyer']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-danger">POST method required.</div>';
    exit;
}

$jobId         = (int)($_POST['job_id'] ?? 0);
$campaignId    = (int)($_POST['campaign_id'] ?? 0);
$revisionNotes = trim($_POST['revision_notes'] ?? '');

if (!$jobId || !$campaignId) {
    echo '<div class="alert alert-danger">Job ID and Campaign ID required.</div>';
    exit;
}

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

// Verify campaign + job
$camp = $pdo->prepare('SELECT * FROM ai_video_campaigns WHERE id = :id');
$camp->execute([':id' => $campaignId]);
$campaign = $camp->fetch();
if (!$campaign) {
    echo '<div class="alert alert-danger">Campaign not found.</div>';
    exit;
}

// Upsert review record
$existing = $pdo->prepare(
    'SELECT id FROM ai_video_reviews WHERE campaign_id = :cid AND job_id = :jid ORDER BY id DESC LIMIT 1'
);
$existing->execute([':cid' => $campaignId, ':jid' => $jobId]);
$reviewRow = $existing->fetch();

if ($reviewRow) {
    $pdo->prepare(
        'UPDATE ai_video_reviews
            SET review_status = "revision_requested", revision_notes = :notes,
                revision_requested_at = NOW()
          WHERE id = :id'
    )->execute([':notes' => $revisionNotes, ':id' => $reviewRow['id']]);
    $reviewId = (int)$reviewRow['id'];
} else {
    $pdo->prepare(
        'INSERT INTO ai_video_reviews
            (campaign_id, job_id, client_id, review_status, revision_notes, revision_requested_at, created_at)
         VALUES
            (:cid, :jid, :client_id, "revision_requested", :notes, NOW(), NOW())'
    )->execute([
        ':cid'       => $campaignId,
        ':jid'       => $jobId,
        ':client_id' => (int)$campaign['client_id'],
        ':notes'     => $revisionNotes,
    ]);
    $reviewId = (int)$pdo->lastInsertId();
}

// Update campaign status
$pdo->prepare(
    'UPDATE ai_video_campaigns SET status = "revision_requested", updated_at = NOW() WHERE id = :id'
)->execute([':id' => $campaignId]);

// Notify team
try {
    $notifSvc = new AiVideoNotificationService();
    $notifSvc->notifyRevisionRequested($campaignId, $reviewId);
} catch (Throwable $e) {
    error_log('[AI Video] revision notification error: ' . $e->getMessage());
}

echo '<div class="alert alert-warning">';
echo '<h5 class="alert-heading"><i class="bi bi-arrow-counterclockwise me-2"></i>Revision Requested</h5>';
echo '<div class="mb-2">Campaign status updated to <strong>Revision Requested</strong>.</div>';
if ($revisionNotes) {
    echo '<div class="mb-2"><strong>Notes:</strong> ' . h($revisionNotes) . '</div>';
}
echo '<div class="mb-0">The team has been notified via email.</div>';
echo '</div>';
