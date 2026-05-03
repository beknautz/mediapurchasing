<?php
/**
 * admin/ai-video/actions/queue-audio-job.php
 * Queue a Runway TTS or SFX audio job.
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

$campaignId = (int)($_POST['campaign_id'] ?? 0);
$jobType    = $_POST['job_type'] ?? 'tts'; // 'tts' | 'sfx'

if (!$campaignId) {
    echo '<div class="alert alert-danger small py-2">Missing campaign_id.</div>';
    exit;
}

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

try {
    $svc = new RunwayAudioService($pdo);

    if ($jobType === 'sfx') {
        $promptText = trim($_POST['sfx_prompt'] ?? '');
        $duration   = (float)($_POST['sfx_duration'] ?? 15.0);
        $loop       = !empty($_POST['sfx_loop']);

        if (!$promptText) {
            echo '<div class="alert alert-warning small py-2">Sound effect description is required.</div>';
            exit;
        }

        $job = $svc->queueSfxJob(
            $campaignId, $promptText, $duration, $loop,
            (int)($_SESSION['user']['id'] ?? 0)
        );
    } else {
        // TTS
        $scriptStmt = $pdo->prepare(
            'SELECT * FROM ai_video_scripts WHERE campaign_id = :cid ORDER BY version_number DESC LIMIT 1'
        );
        $scriptStmt->execute([':cid' => $campaignId]);
        $latestScript = $scriptStmt->fetch();

        $voicePreset = trim($_POST['voice_preset'] ?? 'Maya');
        $scriptText  = trim($_POST['tts_text'] ?? ($latestScript['script_text'] ?? ''));

        if (!$scriptText) {
            echo '<div class="alert alert-warning small py-2">No script text to convert. Generate a script first.</div>';
            exit;
        }

        $job = $svc->queueTtsJob(
            $campaignId,
            (int)($latestScript['id'] ?? 0),
            $scriptText,
            $voicePreset,
            (int)($_SESSION['user']['id'] ?? 0)
        );
    }

    $typeLabel = $jobType === 'sfx' ? 'Sound Effect' : 'Voiceover';
    $provider  = ENABLE_MOCK_RUNWAY_MODE ? 'mock' : 'Runway';
?>
<div class="alert alert-success py-2">
    <i class="bi bi-check-circle-fill me-2"></i>
    <strong><?= h($typeLabel) ?> Job #<?= (int)$job['id'] ?></strong> queued via <?= h($provider) ?>.
    <div class="mt-1 small">
        Status: <strong><?= h($job['job_status']) ?></strong>
        — <a href="#" class="alert-link"
             hx-get="/admin/ai-video/actions/poll-audio-status.php?job_id=<?= (int)$job['id'] ?>"
             hx-target="#audio-job-result-<?= (int)$job['id'] ?>"
             hx-swap="outerHTML">Check Status</a>
    </div>
</div>
<div id="audio-job-result-<?= (int)$job['id'] ?>"></div>
<?php
} catch (Throwable $e) {
    echo '<div class="alert alert-danger small py-2"><i class="bi bi-exclamation-triangle me-1"></i>'
        . h('Audio queue failed: ' . $e->getMessage()) . '</div>';
}
