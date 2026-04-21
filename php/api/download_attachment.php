<?php
/**
 * api/download_attachment.php
 * Serves inbound email attachments — requires an active session.
 *
 * GET params:
 *   log_id  — communication_logs.id
 *   file    — filename (basename only)
 */

require_once __DIR__ . '/../bootstrap.php';

if (empty($_SESSION['loggedIn'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$logId    = (int)  ($_GET['log_id'] ?? 0);
$fileName = basename($_GET['file'] ?? '');

if ($logId <= 0 || $fileName === '') {
    http_response_code(400);
    exit('Bad request');
}

// Verify the log row exists and the file is listed in its attachments
$db = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET),
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$stmt = $db->prepare('SELECT attachments FROM communication_logs WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $logId]);
$row = $stmt->fetch();

if (!$row || empty($row['attachments'])) {
    http_response_code(404);
    exit('Not found');
}

$attachments = json_decode($row['attachments'], true) ?? [];
$match       = null;

foreach ($attachments as $att) {
    if (basename($att['path'] ?? '') === $fileName) {
        $match = $att;
        break;
    }
}

if (!$match) {
    http_response_code(404);
    exit('Not found');
}

$filePath = __DIR__ . '/../' . $match['path'];

if (!file_exists($filePath)) {
    http_response_code(404);
    exit('File not found on disk');
}

$mimeTypes = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
];

$ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . addslashes($match['name']) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, no-cache');
readfile($filePath);
exit;
