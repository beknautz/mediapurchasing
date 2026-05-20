<?php
/**
 * proposals/actions/upload-client-logo.php
 * HTMX action — upload client logo, save to uploads/logos/, update clients.logo_url.
 * Returns an HTML fragment with the new logo preview.
 */
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../config/config.php';
requireRole(['admin', 'buyer']);

header('Content-Type: text/html; charset=utf-8');

$clientId = (int) ($_POST['client_id'] ?? 0);

if ($clientId <= 0) {
    echo '<div class="alert alert-danger py-2 mb-0">Invalid or missing client ID. Please select a client first.</div>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['logo_file'])) {
    echo '<div class="alert alert-danger py-2 mb-0">No file received.</div>';
    exit;
}

$file    = $_FILES['logo_file'];
$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo '<div class="alert alert-danger py-2 mb-0">Upload error code: ' . (int)$file['error'] . '</div>';
    exit;
}
if (!in_array($file['type'], $allowed, true)) {
    echo '<div class="alert alert-danger py-2 mb-0">Only JPEG, PNG, GIF, SVG, and WebP images are allowed.</div>';
    exit;
}
if ($file['size'] > 2 * 1024 * 1024) {
    echo '<div class="alert alert-danger py-2 mb-0">File too large — maximum 2 MB.</div>';
    exit;
}

$ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$filename = 'client-' . $clientId . '-logo-' . time() . '.' . $ext;
$destDir  = __DIR__ . '/../../uploads/logos/';
$destPath = $destDir . $filename;

if (!is_dir($destDir)) {
    mkdir($destDir, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo '<div class="alert alert-danger py-2 mb-0">Could not save file. Check uploads/logos/ permissions.</div>';
    exit;
}

$logoUrl = '/uploads/logos/' . $filename;

// Save to clients table
try {
    $db   = Database::getInstance();
    $stmt = $db->prepare('UPDATE clients SET logo_url = :url WHERE id = :id');
    $stmt->execute([':url' => $logoUrl, ':id' => $clientId]);
} catch (Exception $e) {
    echo '<div class="alert alert-warning py-2 mb-0">Logo saved to disk but could not update database: '
        . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</div>';
    exit;
}

// Return HTMX fragment: preview img + success message + hidden input carrying the new URL
echo '<div class="d-flex align-items-center gap-3 mt-2" id="client-logo-preview-area">';
echo '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES) . '" alt="Client Logo"'
   . ' style="max-height:64px;max-width:200px;object-fit:contain;border:1px solid #dee2e6;border-radius:4px;padding:4px;">';
echo '<div>';
echo '<div class="small text-success fw-semibold"><i class="bi bi-check-circle me-1"></i>Client logo uploaded &amp; saved</div>';
echo '<div class="small text-muted font-monospace">' . htmlspecialchars($logoUrl, ENT_QUOTES) . '</div>';
echo '</div>';
echo '<input type="hidden" name="client_logo_url" value="' . htmlspecialchars($logoUrl, ENT_QUOTES) . '">';
echo '</div>';
