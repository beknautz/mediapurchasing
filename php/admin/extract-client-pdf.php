<?php
/**
 * admin/extract-client-pdf.php
 * AJAX endpoint: upload a client document (PDF or image), send to Claude,
 * return extracted client + billing fields as JSON.
 * Mirrors the pattern used by billing/extract-invoice.php.
 *
 * ob_start() first so PHP warnings never corrupt JSON output.
 */
ob_start();

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';

ob_clean();
header('Content-Type: application/json');

// Auth — JSON error instead of HTML redirect
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

// ── Validate upload ────────────────────────────────────────────────────────
if (empty($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['pdf']['error'] ?? -1;
    jsonError('No file received (upload error code: ' . $code . ').');
}

$tmpPath  = $_FILES['pdf']['tmp_name'];
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
    jsonError('Unsupported file type: ' . $mimeType . '. Allowed: PDF, JPG, PNG, GIF, WEBP.');
}
if ($fileSize > 20 * 1024 * 1024) {
    jsonError('File too large (' . round($fileSize / 1048576, 1) . ' MB). Maximum is 20 MB.');
}

// ── API key ────────────────────────────────────────────────────────────────
$apiKey = $GLOBALS['appSettings']['anthropic_api_key'] ?? '';
if (!$apiKey) {
    jsonError('Anthropic API key not configured. Go to Settings → Workflow Settings and add your key.');
}

// ── Build Claude message ───────────────────────────────────────────────────
$b64   = base64_encode(file_get_contents($tmpPath));
$isPdf = ($mimeType === 'application/pdf');

$contentBlock = $isPdf
    ? ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $b64]]
    : ['type' => 'image',    'source' => ['type' => 'base64', 'media_type' => $mimeType,           'data' => $b64]];

$prompt = <<<'PROMPT'
Extract client contact and billing information from this document. Return ONLY a JSON object with these keys (use null for any field not found):

{
  "company_name":    "client company or business name",
  "contact_name":    "primary contact person full name",
  "address":         "full street address including city, state, zip",
  "phone":           "primary phone number",
  "secondary_phone": "secondary or mobile phone number, or null",
  "email":           "primary email address",
  "secondary_email": "secondary email address, or null",
  "billing_company": "billing company name if different from above, or null",
  "billing_contact": "accounting or billing contact name, or null",
  "billing_address": "billing address if different from above, or null",
  "billing_email":   "accounting or billing email, or null",
  "billing_phone":   "accounting or billing phone, or null"
}

Rules: output only the JSON object with no surrounding text or markdown fences.
PROMPT;

$payload = [
    'model'      => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
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

// ── Call Claude API ────────────────────────────────────────────────────────
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

if ($curlErr)                    jsonError('cURL error: ' . $curlErr);
if ($raw === false || $raw === '') jsonError('Empty response from Claude API (HTTP ' . $httpCode . ').');
if ($httpCode !== 200) {
    $decoded = json_decode($raw, true);
    $errMsg  = $decoded['error']['message'] ?? ('HTTP ' . $httpCode . ' — ' . substr($raw, 0, 200));
    jsonError('Claude API error: ' . $errMsg);
}

$response = json_decode($raw, true);
$text     = trim($response['content'][0]['text'] ?? '');

// Strip markdown fences if Claude added them
$text = preg_replace('/^```(?:json)?\s*/i', '', $text);
$text = preg_replace('/\s*```$/i', '',  trim($text));

$extracted = json_decode($text, true);
if (!is_array($extracted)) {
    jsonError('Claude returned non-JSON output: ' . substr($text, 0, 300));
}

ob_clean();
echo json_encode(['success' => true, 'data' => $extracted]);
