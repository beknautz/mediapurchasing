<?php
/**
 * admin/ai-video/actions/queue-character-job.php
 * Queue a Runway Act Two character performance job.
 * Called via HTMX POST — returns an HTML fragment.
 */
require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../../../config/ai_video.php';
requireRole(['admin', 'buyer']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-danger small py-2">POST required.</div>';
    exit;
}

$campaignId        = (int)($_POST['campaign_id'] ?? 0);
$characterUrl      = trim($_POST['character_url'] ?? '');
$characterType     = in_array($_POST['character_type'] ?? '', ['image','video']) ? $_POST['character_type'] : 'image';
$referenceVideoUrl = trim($_POST['reference_video_url'] ?? '');
$expressionLevel   = (int)($_POST['expression_intensity'] ?? 3);
$bodyControl       = !empty($_POST['body_control']);
$aspectRatio       = trim($_POST['aspect_ratio'] ?? '9:16');
$videoJobId        = (int)($_POST['video_job_id'] ?? 0);

if (!$campaignId || !$characterUrl || !$referenceVideoUrl) {
    echo '<div class="alert alert-warning small py-2">Character URL and reference video URL are required.</div>';
    exit;
}

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

try {
    $svc = new RunwayCharacterService($pdo);
    $job = $svc->queueCharacterJob(
        $campaignId,
        $videoJobId,
        $characterUrl,
        $characterType,
        $referenceVideoUrl,
        $expressionLevel,
        $bodyControl,
        $aspectRatio,
        (int)($_SESSION['user']['id'] ?? 0)
    );

    $provider = ENABLE_MOCK_RUNWAY_MODE ? 'mock' : 'Runway';
?>
<div class="alert alert-success py-2">
    <i class="bi bi-check-circle-fill me-2"></i>
    <strong>Character Performance Job #<?= (int)$job['id'] ?></strong> queued via <?= h($provider) ?>.
    <div class="mt-1 small">
        Model: Act Two &middot; Status: <strong><?= h($job['job_status']) ?></strong>
        — <a href="#" class="alert-link"
             hx-get="/admin/ai-video/actions/poll-character-status.php?job_id=<?= (int)$job['id'] ?>"
             hx-target="#char-job-result-<?= (int)$job['id'] ?>"
             hx-swap="outerHTML">Check Status</a>
    </div>
</div>
<div id="char-job-result-<?= (int)$job['id'] ?>"></div>
<?php
} catch (Throwable $e) {
    echo '<div class="alert alert-danger small py-2"><i class="bi bi-exclamation-triangle me-1"></i>'
        . h('Character job failed: ' . $e->getMessage()) . '</div>';
}
