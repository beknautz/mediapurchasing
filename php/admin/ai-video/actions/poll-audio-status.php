<?php
/**
 * admin/ai-video/actions/poll-audio-status.php
 * Poll a Runway audio job and return an HTML status fragment.
 * Called via HTMX GET.
 */
require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../../../config/ai_video.php';
requireRole(['admin', 'buyer']);

$jobId = (int)($_GET['job_id'] ?? 0);
if (!$jobId) {
    echo '<div class="alert alert-danger small py-2">Missing job_id.</div>';
    exit;
}

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

try {
    $svc = new RunwayAudioService($pdo);
    $job = $svc->getJobStatus($jobId);
    $status = $job['job_status'] ?? 'unknown';
    $type   = strtoupper($job['job_type'] ?? 'audio');
?>
<div id="audio-job-result-<?= (int)$jobId ?>" class="border rounded p-3 bg-light">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="fw-semibold small">
            <i class="bi bi-music-note me-1"></i><?= h($type) ?> Job #<?= (int)$jobId ?>
        </span>
        <span class="badge bg-<?= $status === 'completed' ? 'success' : ($status === 'failed' ? 'danger' : 'warning') ?>">
            <?= h(ucfirst($status)) ?>
        </span>
    </div>

    <?php if ($status === 'completed' && !empty($job['audio_url'])): ?>
        <div class="mb-2">
            <audio controls class="w-100">
                <source src="<?= h($job['audio_url']) ?>" type="audio/mpeg">
                Your browser does not support the audio element.
            </audio>
        </div>
        <div class="small text-muted">
            Voice: <strong><?= h($job['voice_preset'] ?? '—') ?></strong>
            &middot; Cost: $<?= number_format((float)($job['actual_cost'] ?? 0), 4) ?>
        </div>
        <div class="mt-2">
            <a href="<?= h($job['audio_url']) ?>" download class="btn btn-sm btn-outline-primary">
                <i class="bi bi-download me-1"></i>Download Audio
            </a>
        </div>

    <?php elseif ($status === 'failed'): ?>
        <div class="alert alert-danger small py-2 mb-0">
            <i class="bi bi-x-circle me-1"></i><?= h($job['error_message'] ?? 'Generation failed.') ?>
        </div>

    <?php else: ?>
        <div class="d-flex align-items-center gap-2">
            <div class="spinner-border spinner-border-sm text-warning"></div>
            <span class="small text-muted"><?= h(ucfirst($status)) ?>… check again in a few seconds.</span>
        </div>
        <div class="mt-2">
            <button class="btn btn-sm btn-outline-secondary"
                    hx-get="/admin/ai-video/actions/poll-audio-status.php?job_id=<?= (int)$jobId ?>"
                    hx-target="#audio-job-result-<?= (int)$jobId ?>"
                    hx-swap="outerHTML"
                    hx-indicator="#poll-spinner-<?= (int)$jobId ?>">
                <i class="bi bi-arrow-repeat me-1"></i>Check Again
            </button>
            <span id="poll-spinner-<?= (int)$jobId ?>" class="htmx-indicator ms-2">
                <span class="spinner-border spinner-border-sm"></span>
            </span>
        </div>
    <?php endif; ?>
</div>
<?php
} catch (Throwable $e) {
    echo '<div class="alert alert-danger small py-2" id="audio-job-result-' . (int)$jobId . '">'
        . h('Poll failed: ' . $e->getMessage()) . '</div>';
}
