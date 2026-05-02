<?php
/**
 * admin/ai-video/actions/delete-job.php
 * Deletes a video job record (and its local file if stored on disk).
 * Returns an HTMX-friendly response — on success, removes the row via OOB swap.
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

$stmt = $pdo->prepare('SELECT * FROM ai_video_jobs WHERE id = :id');
$stmt->execute([':id' => $jobId]);
$job = $stmt->fetch();

if (!$job) {
    echo '<div class="alert alert-danger">Job #' . $jobId . ' not found.</div>';
    exit;
}

// Block deletion of active jobs
if (in_array($job['job_status'], ['queued', 'processing'])) {
    echo '<div class="alert alert-warning">Cannot delete a job that is currently queued or processing.</div>';
    exit;
}

// Delete local video file if it exists on disk
if (!empty($job['local_file_path']) && file_exists($job['local_file_path'])) {
    @unlink($job['local_file_path']);
}

$pdo->prepare('DELETE FROM ai_video_jobs WHERE id = :id')->execute([':id' => $jobId]);

// Return an empty string — HTMX will swap the row out via hx-target on the row itself
// We use hx-swap="outerHTML" on the row, so echo nothing (removes the row)
http_response_code(200);
// Empty response = the row swap removes the element
