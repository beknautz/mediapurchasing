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
if (!$campaignId) {
    echo '<div class="alert alert-danger">Campaign ID required.</div>';
    exit;
}

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

$campaign = $pdo->prepare('SELECT * FROM ai_video_campaigns WHERE id = :id');
$campaign->execute([':id' => $campaignId]);
$campaign = $campaign->fetch();
if (!$campaign) {
    echo '<div class="alert alert-danger">Campaign not found.</div>';
    exit;
}

// Load latest prompt
$promptStmt = $pdo->prepare(
    'SELECT * FROM ai_video_prompts WHERE campaign_id = :cid ORDER BY version_number DESC LIMIT 1'
);
$promptStmt->execute([':cid' => $campaignId]);
$prompt = $promptStmt->fetch();

if (!$prompt) {
    echo '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>No Veo prompt found. Generate a prompt first.</div>';
    exit;
}

// Load latest script for reference
$scriptStmt = $pdo->prepare(
    'SELECT id FROM ai_video_scripts WHERE campaign_id = :cid ORDER BY version_number DESC LIMIT 1'
);
$scriptStmt->execute([':cid' => $campaignId]);
$latestScriptId = (int)($scriptStmt->fetchColumn() ?: 0);

$createdBy = (int)($_SESSION['user']['id'] ?? 0);

try {
    $svc = new RunwayVideoService();
    $job = $svc->queueVideoJob($prompt, $campaignId, $latestScriptId, (int)$prompt['id'], $createdBy);

    // Update campaign status
    $pdo->prepare(
        'UPDATE ai_video_campaigns SET status = "video_queued", updated_at = NOW() WHERE id = :id'
    )->execute([':id' => $campaignId]);

    $mockBadge = ENABLE_MOCK_RUNWAY_MODE
        ? '<span class="badge bg-warning text-dark ms-2">Mock Mode</span>'
        : '<span class="badge bg-success ms-2">Live Runway</span>';

    echo '<div class="alert alert-success">';
    echo '<h5 class="alert-heading"><i class="bi bi-check-circle-fill me-2"></i>Video Job Queued! ' . $mockBadge . '</h5>';
    echo '<div class="mb-1"><strong>Job #:</strong> ' . (int)$job['id'] . '</div>';
    echo '<div class="mb-1"><strong>Provider Job ID:</strong> ' . h($job['provider_job_id'] ?? '—') . '</div>';
    echo '<div class="mb-1"><strong>Duration:</strong> ' . (int)$job['requested_duration_seconds'] . 's &middot; ' . h($job['requested_aspect_ratio'] ?? '') . '</div>';
    echo '<div class="mb-1"><strong>Estimated Cost:</strong> $' . number_format((float)$job['estimated_provider_cost'], 2) . '</div>';
    echo '<div class="mt-2">';
    echo '<button class="btn btn-sm btn-warning" hx-get="/admin/ai-video/actions/poll-video-status.php?job_id=' . (int)$job['id'] . '" hx-target="#action-result" hx-swap="innerHTML" hx-indicator="#spinner-poll"><i class="bi bi-arrow-repeat me-1"></i>Check Status</button>';
    echo '</div>';
    echo '</div>';

} catch (Throwable $e) {
    error_log('[AI Video] queue-video-job error: ' . $e->getMessage());
    echo '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>';
    echo '<strong>Failed to queue video:</strong> ' . h($e->getMessage());
    echo '</div>';
}
