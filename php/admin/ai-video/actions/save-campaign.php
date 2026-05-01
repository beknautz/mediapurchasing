<?php
require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../../../config/ai_video.php';
requireRole(['admin', 'buyer']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && empty($_GET['campaign_id'])) {
    http_response_code(400);
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit;
}

// Allow GET for simple status updates (archive)
$campaignId = (int)($_REQUEST['campaign_id'] ?? 0);
$newStatus  = trim($_REQUEST['status'] ?? '');

if (!$campaignId) {
    http_response_code(400);
    echo '<div class="alert alert-danger">Campaign ID required.</div>';
    exit;
}

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

// Verify campaign exists
$stmt = $pdo->prepare('SELECT id FROM ai_video_campaigns WHERE id = :id');
$stmt->execute([':id' => $campaignId]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo '<div class="alert alert-danger">Campaign not found.</div>';
    exit;
}

$updates  = [];
$params   = [':id' => $campaignId];

// Status update (e.g. archive)
if ($newStatus !== '') {
    $allowed = ['draft','script_generated','prompt_generated','video_queued','generating',
                'ready_for_review','approved','revision_requested','failed','archived'];
    if (!in_array($newStatus, $allowed, true)) {
        http_response_code(400);
        echo '<div class="alert alert-danger">Invalid status value.</div>';
        exit;
    }
    $updates[] = 'status = :status';
    $params[':status'] = $newStatus;
}

// POST field updates
$fieldMap = [
    'campaign_name'   => 'campaign_name',
    'objective'       => 'objective',
    'target_audience' => 'target_audience',
    'offer'           => 'offer',
    'brand_voice'     => 'brand_voice',
    'platform'        => 'platform',
    'video_length'    => 'video_length',
    'aspect_ratio'    => 'aspect_ratio',
    'call_to_action'  => 'call_to_action',
    'landing_page_url'=> 'landing_page_url',
    'notes'           => 'notes',
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($fieldMap as $postKey => $dbCol) {
        if (isset($_POST[$postKey])) {
            $updates[] = '`' . $dbCol . '` = :' . $postKey;
            $params[':' . $postKey] = trim($_POST[$postKey]);
        }
    }
}

if (empty($updates)) {
    echo '<div class="alert alert-info">Nothing to update.</div>';
    exit;
}

$sql = 'UPDATE ai_video_campaigns SET ' . implode(', ', $updates) . ', updated_at = NOW() WHERE id = :id';
$pdo->prepare($sql)->execute($params);

if ($newStatus === 'archived') {
    // Redirect to list after archiving
    echo '<div class="alert alert-success">Campaign archived. <a href="/admin/ai-video/index.php">Return to list</a></div>';
} else {
    echo '<div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i>Campaign updated successfully.</div>';
}
