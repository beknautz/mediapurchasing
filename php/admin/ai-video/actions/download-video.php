<?php
/**
 * admin/ai-video/actions/download-video.php
 * Streams a video file to the browser as a forced download (Save dialog).
 * Works for both local files and external URLs (e.g. mock W3Schools clip).
 */
require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../../../config/ai_video.php';
requireRole(['admin', 'buyer']);

$jobId = (int)($_GET['job_id'] ?? 0);
if (!$jobId) {
    http_response_code(400);
    exit('Missing job_id.');
}

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$stmt = $pdo->prepare(
    'SELECT j.id, j.video_url, j.local_file_path, j.campaign_id, c.campaign_name
       FROM ai_video_jobs j
       JOIN ai_video_campaigns c ON c.id = j.campaign_id
      WHERE j.id = :id'
);
$stmt->execute([':id' => $jobId]);
$job = $stmt->fetch();

if (!$job || empty($job['video_url'])) {
    http_response_code(404);
    exit('Video not found.');
}

// Build a clean filename from the campaign name
$safeName = preg_replace('/[^a-z0-9]+/', '-', strtolower($job['campaign_name'] ?? 'video'));
$safeName = trim($safeName, '-');
$filename = 'video-' . $safeName . '-job' . $jobId . '.mp4';

// ── Local file — serve directly ───────────────────────────────────────────
$localPath = $job['local_file_path'] ?? '';
if ($localPath && file_exists($localPath)) {
    header('Content-Type: video/mp4');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($localPath));
    header('Cache-Control: no-cache');
    readfile($localPath);
    exit;
}

// ── External URL — proxy through PHP so browser gets a Save dialog ────────
$videoUrl = $job['video_url'];

$ch = curl_init($videoUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => false,        // stream directly, don't buffer
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 120,
    CURLOPT_HTTPHEADER     => ['User-Agent: Mozilla/5.0'],
    CURLOPT_WRITEFUNCTION  => function ($ch, $data) {
        echo $data;
        return strlen($data);
    },
    CURLOPT_HEADERFUNCTION => function ($ch, $header) use ($filename) {
        // Send our own headers on the first real header line
        static $headersSent = false;
        if (!$headersSent && trim($header) === '') {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode >= 200 && $httpCode < 300) {
                header('Content-Type: video/mp4');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Cache-Control: no-cache');
                $headersSent = true;
            }
        }
        return strlen($header);
    },
]);

// Send headers before curl starts streaming
header('Content-Type: video/mp4');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache');

$result  = curl_exec($ch);
$curlErr = curl_error($ch);
$code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($result === false || $curlErr || $code >= 400) {
    // Headers already sent — can't show a nice error, just log it
    error_log('[download-video] Failed to proxy video for job ' . $jobId . ': ' . $curlErr . ' HTTP ' . $code);
}
