<?php
/**
 * AJAX endpoint: upload an invoice file, send to Claude, return extracted fields as JSON.
 * Called by the drag-and-drop UI on billing/create.php.
 *
 * ob_start() is FIRST so any PHP warnings/errors from includes don't corrupt JSON output.
 */
ob_start();

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';

// Always respond with JSON — discard any stray output from includes
ob_clean();
header('Content-Type: application/json');

// Auth check — return JSON error instead of HTML redirect for AJAX calls
if (empty($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
    exit;
}
$userRole = $_SESSION['user']['role'] ?? '';
if (!in_array($userRole, ['admin', 'buyer'], true)) {
    echo json_encode(['success' => false, 'error' => 'Access denied.']);
    exit;
}

function jsonError(string $msg): void {
    ob_clean();
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// ── Validate upload ────────────────────────────────────────────────────────────
if (empty($_FILES['invoice_file']) || $_FILES['invoice_file']['error'] !== UPLOAD_ERR_OK) {
    $uploadErr = $_FILES['invoice_file']['error'] ?? -1;
    jsonError('No file received (upload error code: ' . $uploadErr . '). Check PHP upload_max_filesize and post_max_size.');
}

$tmpPath  = $_FILES['invoice_file']['tmp_name'];
$mimeType = mime_content_type($tmpPath);
$fileSize = filesize($tmpPath);

$allowedMimes = [
    'application/pdf' => true,
    'image/jpeg'      => true,
    'image/png'       => true,
    'image/gif'       => true,
    'image/webp'      => true,
];

if (!isset($allowedMimes[$mimeType])) {
    jsonError('Unsupported file type detected: ' . $mimeType . '. Allowed: PDF, JPG, PNG, GIF, WEBP.');
}
if ($fileSize > 10 * 1024 * 1024) {
    jsonError('File too large (' . round($fileSize / 1048576, 1) . ' MB). Maximum is 10 MB.');
}

// ── Get API key ────────────────────────────────────────────────────────────────
$apiKey = $GLOBALS['appSettings']['anthropic_api_key'] ?? '';
if (!$apiKey) {
    jsonError('Anthropic API key not configured. Go to Settings → Workflow Settings and add your key.');
}

// ── Build Claude message ───────────────────────────────────────────────────────
$b64   = base64_encode(file_get_contents($tmpPath));
$isPdf = ($mimeType === 'application/pdf');

$contentBlock = $isPdf
    ? ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $b64]]
    : ['type' => 'image',    'source' => ['type' => 'base64', 'media_type' => $mimeType,           'data' => $b64]];

$prompt = <<<'PROMPT'
Extract billing data from this invoice. Return ONLY a JSON object with these keys (null for missing):

{
  "vendor_name":    "company name of the vendor/biller",
  "invoice_number": "invoice or bill number",
  "invoice_date":   "date in YYYY-MM-DD format",
  "due_date":       "payment due date in YYYY-MM-DD format, or null",
  "amount":         123.45,
  "po_number":      "PO number if present, or null",
  "notes":          "payment instructions or special terms, or null"
}

Rules: dates must be YYYY-MM-DD; amount must be a plain number with no currency symbol or commas; output only the JSON with no surrounding text.
PROMPT;

$payload = [
    'model'      => 'claude-sonnet-4-6',
    'max_tokens' => 512,
    'messages'   => [[
        'role'    => 'user',
        'content' => [$contentBlock, ['type' => 'text', 'text' => $prompt]],
    ]],
];

$headers = [
    'Content-Type: application/json',
    'x-api-key: ' . $apiKey,
    'anthropic-version: 2023-06-01',
];
if ($isPdf) {
    $headers[] = 'anthropic-beta: pdfs-2024-09-25';
}

// ── Call Claude API ────────────────────────────────────────────────────────────
$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$raw      = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    jsonError('cURL error reaching Anthropic API: ' . $curlErr);
}
if ($raw === false || $raw === '') {
    jsonError('Empty response from Anthropic API (HTTP ' . $httpCode . ').');
}
if ($httpCode !== 200) {
    $decoded = json_decode($raw, true);
    $errMsg  = $decoded['error']['message'] ?? ('HTTP ' . $httpCode . ' — ' . substr($raw, 0, 200));
    jsonError('Claude API error: ' . $errMsg);
}

$response = json_decode($raw, true);
$text     = trim($response['content'][0]['text'] ?? '');

// Strip markdown code fences if present
$text = preg_replace('/^```(?:json)?\s*/i', '', $text);
$text = preg_replace('/\s*```$/i', '', trim($text));

$extracted = json_decode($text, true);
if (!is_array($extracted)) {
    jsonError('AI returned non-JSON output: ' . substr($text, 0, 300));
}

// Sanitise amount to a plain float
if (isset($extracted['amount']) && $extracted['amount'] !== null) {
    $extracted['amount'] = (float) preg_replace('/[^0-9.]/', '', (string) $extracted['amount']);
}

ob_clean();
echo json_encode(['success' => true, 'data' => $extracted]);
