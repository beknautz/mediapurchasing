<?php
/**
 * admin/ai-video/actions/rerender-job.php
 * Creates a new video generation job using the same prompt as an existing job.
 * Returns an HTML fragment for HTMX swap.
 */
require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../../../config/ai_video.php';
requireRole(['admin', 'buyer']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-danger">POST required.</div>';
    exit;
}

$jobId = (int)($_POST['job_id'] ?? 0);
if (!$jobId) {
    echo '<div class="alert alert-danger">Missing job_id.</div>';
    exit;
}

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Load the original job
$stmt = $pdo->prepare('SELECT * FROM ai_video_jobs WHERE id = :id');
$stmt->execute([':id' => $jobId]);
$originalJob = $stmt->fetch();

if (!$originalJob) {
    echo '<div class="alert alert-danger">Job #' . $jobId . ' not found.</div>';
    exit;
}

$campaignId = (int)$originalJob['campaign_id'];
$promptId   = (int)($originalJob['prompt_id'] ?? 0);
$scriptId   = (int)($originalJob['script_id'] ?? 0);
$createdBy  = (int)($_SESSION['user']['id'] ?? 0);

// Load the prompt to pass to VeoVideoService
if (!$promptId) {
    // Fall back to latest prompt for the campaign
    $ps = $pdo->prepare('SELECT * FROM ai_video_prompts WHERE campaign_id = :cid ORDER BY version_number DESC LIMIT 1');
    $ps->execute([':cid' => $campaignId]);
    $prompt = $ps->fetch();
} else {
    $ps = $pdo->prepare('SELECT * FROM ai_video_prompts WHERE id = :id');
    $ps->execute([':id' => $promptId]);
    $prompt = $ps->fetch();
}

if (!$prompt) {
    echo '<div class="alert alert-warning">No Veo prompt found for this job. Generate a prompt first.</div>';
    exit;
}

try {
    $svc    = new VeoVideoService();
    $newJob = $svc->queueVideoJob($prompt, $campaignId, $scriptId, (int)$prompt['id'], $createdBy);

    // Link new job back to original as parent
    $pdo->prepare('UPDATE ai_video_jobs SET parent_job_id = :parent WHERE id = :id')
        ->execute([':parent' => $jobId, ':id' => (int)$newJob['id']]);

    // Reset campaign to video_queued
    $pdo->prepare("UPDATE ai_video_campaigns SET status = 'video_queued', updated_at = NOW() WHERE id = :id")
        ->execute([':id' => $campaignId]);

    $mockBadge = ENABLE_MOCK_VEO_MODE
        ? '<span class="badge bg-warning text-dark ms-1">Mock</span>'
        : '<span class="badge bg-success ms-1">Live Veo</span>';

    echo '<div class="alert alert-success mt-2">';
    echo '<strong><i class="bi bi-arrow-clockwise me-1"></i>Rerender queued!</strong> ' . $mockBadge . '<br>';
    echo 'New Job #' . (int)$newJob['id'] . ' — provider ID: <code>' . h($newJob['provider_job_id'] ?? '—') . '</code><br>';
    echo '<small class="text-muted">Check Status in 2–5 minutes for real Veo renders.</small><br>';
    echo '<div class="mt-2">';
    echo '<button class="btn btn-sm btn-warning"'
       . ' hx-get="/admin/ai-video/actions/poll-video-status.php?job_id=' . (int)$newJob['id'] . '"'
       . ' hx-target="closest .alert" hx-swap="outerHTML">'
       . '<i class="bi bi-arrow-repeat me-1"></i>Check Status</button>';
    echo '</div>';
    echo '</div>';

} catch (Throwable $e) {
    error_log('[AI Video] rerender-job error: ' . $e->getMessage());
    echo '<div class="alert alert-danger mt-2">';
    echo '<i class="bi bi-exclamation-triangle-fill me-2"></i>';
    echo '<strong>Rerender failed:</strong> ' . h($e->getMessage());
    echo '</div>';
}
