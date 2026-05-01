<?php
/**
 * portal/actions/approve.php
 * Client portal action — approve video via review token.
 * Returns JSON. No internal auth required — uses review_token.
 */

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../config/ai_video.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$token = trim($_POST['token'] ?? '');

if ($token === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid review token.']);
    exit;
}

try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Validate token
    $stmt = $pdo->prepare('SELECT * FROM ai_video_campaigns WHERE review_token = :token LIMIT 1');
    $stmt->execute([':token' => $token]);
    $campaign = $stmt->fetch();

    if (!$campaign) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Review link not found.']);
        exit;
    }

    $campaignId = (int)$campaign['id'];
    $clientId   = (int)$campaign['client_id'];

    // Get latest job
    $jobStmt = $pdo->prepare(
        'SELECT id FROM ai_video_jobs WHERE campaign_id = :cid ORDER BY CASE job_status WHEN "completed" THEN 0 ELSE 1 END, id DESC LIMIT 1'
    );
    $jobStmt->execute([':cid' => $campaignId]);
    $jobId = (int)($jobStmt->fetchColumn() ?: 0);

    // Update or create review record
    $existing = $pdo->prepare(
        'SELECT id FROM ai_video_reviews WHERE campaign_id = :cid ORDER BY id DESC LIMIT 1'
    );
    $existing->execute([':cid' => $campaignId]);
    $reviewRow = $existing->fetch();

    if ($reviewRow) {
        $pdo->prepare(
            'UPDATE ai_video_reviews SET review_status = "approved", approved_at = NOW() WHERE id = :id'
        )->execute([':id' => $reviewRow['id']]);
        $reviewId = (int)$reviewRow['id'];
    } else {
        $pdo->prepare(
            'INSERT INTO ai_video_reviews
                (campaign_id, job_id, client_id, review_status, approved_at, created_at)
             VALUES
                (:cid, :jid, :client_id, "approved", NOW(), NOW())'
        )->execute([':cid' => $campaignId, ':jid' => $jobId ?: null, ':client_id' => $clientId]);
        $reviewId = (int)$pdo->lastInsertId();
    }

    // Update campaign status
    $pdo->prepare(
        'UPDATE ai_video_campaigns SET status = "approved", updated_at = NOW() WHERE id = :id'
    )->execute([':id' => $campaignId]);

    // Notify admin
    try {
        $notifSvc = new AiVideoNotificationService();
        $notifSvc->notifyAdminVideoReady($campaignId, $jobId);
    } catch (Throwable $e) {
        error_log('[AI Video Portal] admin notify error: ' . $e->getMessage());
    }

    echo json_encode(['success' => true, 'campaign_id' => $campaignId]);

} catch (Throwable $e) {
    error_log('[AI Video Portal] approve.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An unexpected error occurred.']);
}
