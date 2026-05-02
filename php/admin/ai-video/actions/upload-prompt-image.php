<?php
/**
 * admin/ai-video/actions/upload-prompt-image.php
 * Handles reference image upload for Runway image-to-video.
 * Called via HTMX multipart POST — returns an HTML preview fragment
 * and fires an HX-Trigger event with the uploaded public URL.
 */
require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../../../config/ai_video.php';
requireRole(['admin', 'buyer']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-danger small py-2">POST required.</div>';
    exit;
}

$file = $_FILES['image'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    $errCodes = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
    ];
    $msg = $errCodes[$file['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'Upload error.';
    echo '<div class="alert alert-danger small py-2"><i class="bi bi-exclamation-triangle me-1"></i>' . h($msg) . '</div>';
    exit;
}

// ── Validate type ─────────────────────────────────────────────────────────
$allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$finfo       = finfo_open(FILEINFO_MIME_TYPE);
$mime        = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowedMime)) {
    echo '<div class="alert alert-danger small py-2">Invalid file type. JPG, PNG, WebP or GIF only.</div>';
    exit;
}

// ── Validate size (10 MB) ─────────────────────────────────────────────────
if ($file['size'] > 10 * 1024 * 1024) {
    echo '<div class="alert alert-danger small py-2">File too large. Maximum 10 MB.</div>';
    exit;
}

// ── Save to disk ──────────────────────────────────────────────────────────
$extMap  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
$ext     = $extMap[$mime];
$saveDir = VIDEO_STORAGE_PATH . '/prompt-images';

if (!is_dir($saveDir)) {
    mkdir($saveDir, 0755, true);
}

$filename = 'ref_' . uniqid() . '_' . time() . '.' . $ext;
$savePath = $saveDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $savePath)) {
    echo '<div class="alert alert-danger small py-2">Failed to save file to disk.</div>';
    exit;
}

// ── Build full public URL (Runway needs an absolute URL) ──────────────────
$scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host      = $_SERVER['HTTP_HOST'] ?? '';
$publicUrl = $scheme . '://' . $host . VIDEO_PUBLIC_URL_BASE . '/prompt-images/' . $filename;

// ── Fire HTMX event so the hidden field in the parent form updates ─────────
header('HX-Trigger: ' . json_encode(['promptImageUploaded' => ['url' => $publicUrl]]));

// ── Return preview HTML ───────────────────────────────────────────────────
?>
<div class="d-flex align-items-start gap-3 p-3 border rounded bg-light">
    <img src="<?= h($publicUrl) ?>"
         alt="Reference image"
         style="max-height:140px;max-width:220px;object-fit:cover;border-radius:6px;border:1px solid #dee2e6;">
    <div>
        <div class="small fw-semibold mb-1">Reference image uploaded</div>
        <div class="small text-muted mb-2"><?= h($filename) ?></div>
        <button type="button" class="btn btn-sm btn-outline-danger"
                onclick="removePromptImage()">
            <i class="bi bi-trash me-1"></i>Remove
        </button>
    </div>
</div>
