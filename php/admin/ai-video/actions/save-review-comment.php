<?php
require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../../../config/ai_video.php';
requireRole(['admin', 'buyer']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-danger">POST method required.</div>';
    exit;
}

$campaignId = (int)($_POST['campaign_id'] ?? 0);
$jobId      = (int)($_POST['job_id'] ?? 0);
$comments   = trim($_POST['comments'] ?? '');
$action     = trim($_POST['action'] ?? '');

if (!$campaignId) {
    echo '<div class="alert alert-danger">Campaign ID required.</div>';
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

// Handle "notify_client" action — send review email
if ($action === 'notify_client') {
    // Get latest completed job
    if (!$jobId) {
        $latestJob = $pdo->prepare(
            'SELECT id FROM ai_video_jobs WHERE campaign_id = :cid AND job_status = "completed" ORDER BY id DESC LIMIT 1'
        );
        $latestJob->execute([':cid' => $campaignId]);
        $jobId = (int)($latestJob->fetchColumn() ?: 0);
    }

    try {
        $notifSvc = new AiVideoNotificationService();
        $result   = $notifSvc->notifyClientReviewReady($campaignId, $jobId);

        if ($result) {
            echo '<div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i>';
            echo '<strong>Client notified!</strong> Review link sent to client email.';
            echo '</div>';
        } else {
            echo '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>';
            echo 'Notification may not have been sent. Check that client email is configured.';
            $token = $campaign['review_token'] ?? '';
            if ($token) {
                echo '<div class="mt-2 small"><strong>Portal URL:</strong> <a href="/portal/video-review.php?token=' . urlencode($token) . '" target="_blank">/portal/video-review.php?token=' . h($token) . '</a></div>';
            }
            echo '</div>';
        }
    } catch (Throwable $e) {
        error_log('[AI Video] save-review-comment notify error: ' . $e->getMessage());
        echo '<div class="alert alert-danger">' . h($e->getMessage()) . '</div>';
    }
    exit;
}

// Save comment only
if (empty($comments)) {
    echo '<div class="alert alert-warning">No comment to save.</div>';
    exit;
}

if ($jobId) {
    // Try to update existing review, else insert
    $existing = $pdo->prepare(
        'SELECT id FROM ai_video_reviews WHERE campaign_id = :cid AND job_id = :jid ORDER BY id DESC LIMIT 1'
    );
    $existing->execute([':cid' => $campaignId, ':jid' => $jobId]);
    $row = $existing->fetch();

    if ($row) {
        $pdo->prepare(
            'UPDATE ai_video_reviews SET comments = :comments WHERE id = :id'
        )->execute([':comments' => $comments, ':id' => $row['id']]);
    } else {
        $pdo->prepare(
            'INSERT INTO ai_video_reviews (campaign_id, job_id, client_id, review_status, comments, created_at)
             VALUES (:cid, :jid, :client_id, "pending", :comments, NOW())'
        )->execute([
            ':cid'       => $campaignId,
            ':jid'       => $jobId,
            ':client_id' => (int)$campaign['client_id'],
            ':comments'  => $comments,
        ]);
    }
} else {
    // Insert without job
    $pdo->prepare(
        'INSERT INTO ai_video_reviews (campaign_id, job_id, client_id, review_status, comments, created_at)
         VALUES (:cid, NULL, :client_id, "pending", :comments, NOW())'
    )->execute([
        ':cid'       => $campaignId,
        ':client_id' => (int)$campaign['client_id'],
        ':comments'  => $comments,
    ]);
}

echo '<div class="alert alert-success alert-sm py-2">';
echo '<i class="bi bi-check-circle-fill me-2"></i>Comment saved.';
echo '</div>';
