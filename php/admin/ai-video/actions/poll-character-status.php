<?php
/**
 * admin/ai-video/actions/poll-character-status.php
 * Poll a Runway Act Two character performance job.
 * Called via HTMX GET — returns an HTML status fragment.
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
    $svc = new RunwayCharacterService($pdo);
    $job = $svc->getJobStatus($jobId);
    $status = $job['job_status'] ?? 'unknown';
?>
<div id="char-job-result-<?= (int)$jobId ?>" class="border rounded p-3 bg-light">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="fw-semibold small">
            <i class="bi bi-person-bounding-box me-1"></i>Character Job #<?= (int)$jobId ?>
            <span class="badge bg-secondary ms-1">Act Two</span>
        </span>
        <span class="badge bg-<?= $status === 'completed' ? 'success' : ($status === 'failed' ? 'danger' : 'warning') ?>">
            <?= h(ucfirst($status)) ?>
        </span>
    </div>

    <?php if ($status === 'completed' && !empty($job['output_video_url'])): ?>
        <div class="ratio ratio-9x16 mb-2" style="max-width:240px;">
            <video controls class="w-100 rounded">
                <source src="<?= h($job['output_video_url']) ?>" type="video/mp4">
            </video>
        </div>
        <div class="small text-muted mb-2">
            Expression: <strong><?= (int)$job['expression_intensity'] ?>/5</strong>
            &middot; Body control: <strong><?= $job['body_control'] ? 'On' : 'Off' ?></strong>
            &middot; Cost: $<?= number_format((float)($job['actual_cost'] ?? 0), 4) ?>
        </div>
        <a href="<?= h($job['output_video_url']) ?>" download class="btn btn-sm btn-outline-primary">
            <i class="bi bi-download me-1"></i>Download Video
        </a>

    <?php elseif ($status === 'failed'): ?>
        <div class="alert alert-danger small py-2 mb-0">
            <i class="bi bi-x-circle me-1"></i><?= h($job['error_message'] ?? 'Generation failed.') ?>
        </div>

    <?php else: ?>
        <div class="d-flex align-items-center gap-2">
            <div class="spinner-border spinner-border-sm text-warning"></div>
            <span class="small text-muted"><?= h(ucfirst($status)) ?>… this can take 30–90 seconds.</span>
        </div>
        <div class="mt-2">
            <button class="btn btn-sm btn-outline-secondary"
                    hx-get="/admin/ai-video/actions/poll-character-status.php?job_id=<?= (int)$jobId ?>"
                    hx-target="#char-job-result-<?= (int)$jobId ?>"
                    hx-swap="outerHTML"
                    hx-indicator="#char-poll-spinner-<?= (int)$jobId ?>">
                <i class="bi bi-arrow-repeat me-1"></i>Check Again
            </button>
            <span id="char-poll-spinner-<?= (int)$jobId ?>" class="htmx-indicator ms-2">
                <span class="spinner-border spinner-border-sm"></span>
            </span>
        </div>
    <?php endif; ?>
</div>
<?php
} catch (Throwable $e) {
    echo '<div class="alert alert-danger small py-2" id="char-job-result-' . (int)$jobId . '">'
        . h('Poll failed: ' . $e->getMessage()) . '</div>';
}
