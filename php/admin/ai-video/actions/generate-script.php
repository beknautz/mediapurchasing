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

$stmt = $pdo->prepare('SELECT * FROM ai_video_campaigns WHERE id = :id');
$stmt->execute([':id' => $campaignId]);
$campaign = $stmt->fetch();

if (!$campaign) {
    echo '<div class="alert alert-danger">Campaign not found.</div>';
    exit;
}

try {
    $svc    = new ClaudeVideoService();
    $script = $svc->generateScript($campaign);

    // Update campaign status
    $pdo->prepare(
        'UPDATE ai_video_campaigns SET status = "script_generated", updated_at = NOW() WHERE id = :id'
    )->execute([':id' => $campaignId]);

    // Parse scene count
    $scenes     = [];
    $scenesJson = $script['scene_breakdown'] ?? '[]';
    if ($scenesJson) {
        $decoded = json_decode($scenesJson, true);
        if (is_array($decoded)) $scenes = $decoded;
    }

    echo '<div class="alert alert-success">';
    echo '<h5 class="alert-heading"><i class="bi bi-check-circle-fill me-2"></i>Script Generated!</h5>';
    echo '<div class="mb-2"><strong>Version:</strong> ' . (int)$script['version_number'] . '</div>';
    if (!empty($script['hook'])) {
        echo '<div class="mb-2"><strong><i class="bi bi-lightning me-1"></i>Hook:</strong> ' . h($script['hook']) . '</div>';
    }
    echo '<div class="mb-2"><strong>Scenes:</strong> ' . count($scenes) . '</div>';
    echo '<div class="mb-2">';
    echo '<strong>Cost:</strong> $' . number_format((float)($script['claude_cost'] ?? 0), 4) . ' ';
    echo '(' . number_format((int)($script['claude_input_tokens'] ?? 0)) . ' in / ';
    echo number_format((int)($script['claude_output_tokens'] ?? 0)) . ' out tokens)';
    echo '</div>';
    echo '<div class="d-flex gap-2">';
    echo '<a href="/admin/ai-video/script-editor.php?campaign_id=' . (int)$campaignId . '" class="btn btn-sm btn-primary"><i class="bi bi-pencil me-1"></i>Edit Script</a>';
    echo '<button class="btn btn-sm btn-outline-primary" hx-post="/admin/ai-video/actions/generate-veo-prompt.php" hx-vals=\'{"campaign_id":"' . (int)$campaignId . '"}\' hx-target="#action-result" hx-swap="innerHTML" hx-indicator="#spinner-prompt"><i class="bi bi-magic me-1"></i>Generate Veo Prompt</button>';
    echo '</div>';
    echo '</div>';

} catch (Throwable $e) {
    error_log('[AI Video] generate-script error: ' . $e->getMessage());
    echo '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>';
    echo '<strong>Script generation failed:</strong> ' . h($e->getMessage());
    echo '</div>';
}
