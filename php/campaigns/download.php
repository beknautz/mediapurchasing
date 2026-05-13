<?php
/**
 * campaigns/download.php
 * Authenticated file download handler for campaign RFP proposal attachments.
 *
 * Streams any file stored under uploads/ after verifying the user is
 * authenticated and the path is safe (no directory traversal).
 *
 * Usage: /campaigns/download.php?f=uploads/campaigns/3/replies/quote.pdf
 */

require_once __DIR__ . '/../bootstrap.php';
requireRole(['admin', 'buyer']);

$rel = trim($_GET['f'] ?? '');

// ── Security: only allow paths inside uploads/ with no traversal ──────────
if (
    $rel === '' ||
    !str_starts_with($rel, 'uploads/') ||
    str_contains($rel, '..') ||
    str_contains($rel, "\0")
) {
    http_response_code(400);
    exit('Invalid file path.');
}

$absPath     = realpath(__DIR__ . '/../' . $rel);
$uploadsRoot = realpath(__DIR__ . '/../uploads');

// realpath() returns false if file doesn't exist
if ($absPath === false || $uploadsRoot === false || !str_starts_with($absPath, $uploadsRoot . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit('File not found.');
}

if (!is_file($absPath)) {
    http_response_code(404);
    exit('File not found.');
}

// ── MIME type ────────────────────────────────────────────────────────────
$mime = mime_content_type($absPath) ?: 'application/octet-stream';

// For known safe inline types (PDF, images), display inline in browser.
// Everything else forces a download.
$inlineTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$disposition = in_array($mime, $inlineTypes, true) ? 'inline' : 'attachment';

$fileName = basename($absPath);

// ── Stream the file ───────────────────────────────────────────────────────
header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($fileName) . '"');
header('Content-Length: ' . filesize($absPath));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

readfile($absPath);
exit;
