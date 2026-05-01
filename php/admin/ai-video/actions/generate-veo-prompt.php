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

// Load latest script
$scriptStmt = $pdo->prepare(
    'SELECT * FROM ai_video_scripts WHERE campaign_id = :cid ORDER BY version_number DESC LIMIT 1'
);
$scriptStmt->execute([':cid' => $campaignId]);
$script = $scriptStmt->fetch();

if (!$script) {
    echo '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>No script found. Generate a script first.</div>';
    exit;
}

try {
    $svc    = new ClaudeVideoService();
    $prompt = $svc->generateVeoPrompt($script, $campaign);

    // Update campaign status
    $pdo->prepare(
        'UPDATE ai_video_campaigns SET status = "prompt_generated", updated_at = NOW() WHERE id = :id'
    )->execute([':id' => $campaignId]);

    $preview = mb_strimwidth($prompt['veo_prompt'] ?? '', 0, 200, '…');

    echo '<div class="alert alert-success">';
    echo '<h5 class="alert-heading"><i class="bi bi-check-circle-fill me-2"></i>Veo Prompt Generated!</h5>';
    echo '<div class="mb-2"><strong>Version:</strong> ' . (int)$prompt['version_number'] . '</div>';
    echo '<div class="mb-2 p-2 bg-white rounded border small text-dark">' . h($preview) . '</div>';
    echo '<div class="mb-2">';
    echo '<strong>Style:</strong> ' . h($prompt['visual_style'] ?? '—') . ' &middot; ';
    echo '<strong>Aspect:</strong> ' . h($prompt['aspect_ratio'] ?? '—') . ' &middot; ';
    echo '<strong>Duration:</strong> ' . (int)($prompt['duration_seconds'] ?? 0) . 's';
    echo '</div>';
    echo '<div class="mb-2">';
    echo '<strong>Cost:</strong> $' . number_format((float)($prompt['claude_cost'] ?? 0), 4);
    echo '</div>';
    echo '<div class="d-flex gap-2">';
    echo '<a href="/admin/ai-video/prompt-editor.php?campaign_id=' . (int)$campaignId . '" class="btn btn-sm btn-primary"><i class="bi bi-pencil me-1"></i>Edit Prompt</a>';
    echo '<button class="btn btn-sm btn-outline-primary" hx-post="/admin/ai-video/actions/queue-video-job.php" hx-vals=\'{"campaign_id":"' . (int)$campaignId . '"}\' hx-target="#action-result" hx-swap="innerHTML" hx-indicator="#spinner-queue"><i class="bi bi-film me-1"></i>Queue Video</button>';
    echo '</div>';
    echo '</div>';

} catch (Throwable $e) {
    error_log('[AI Video] generate-veo-prompt error: ' . $e->getMessage());
    echo '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>';
    echo '<strong>Prompt generation failed:</strong> ' . h($e->getMessage());
    echo '</div>';
}
