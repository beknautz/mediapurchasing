<?php
/**
 * AJAX endpoint: upload an invoice file, send to Claude, return extracted fields as JSON.
 * Called by the drag-and-drop UI on billing/create.php.
 */
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';

requireRole(['admin', 'buyer']);

header('Content-Type: application/json');

function jsonError(string $msg): void {
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// ── Validate upload ────────────────────────────────────────────────────────────
if (empty($_FILES['invoice_file']) || $_FILES['invoice_file']['error'] !== UPLOAD_ERR_OK) {
    jsonError('No file received or upload error.');
}

$tmpPath  = $_FILES['invoice_file']['tmp_name'];
$origName = $_FILES['invoice_file']['name'];
$mimeType = mime_content_type($tmpPath);
$fileSize = filesize($tmpPath);

$allowedMimes = [
    'application/pdf' => 'application/pdf',
    'image/jpeg'      => 'image/jpeg',
    'image/png'       => 'image/png',
    'image/gif'       => 'image/gif',
    'image/webp'      => 'image/webp',
];

if (!isset($allowedMimes[$mimeType])) {
    jsonError('Unsupported file type: ' . $mimeType);
}
if ($fileSize > 10 * 1024 * 1024) {
    jsonError('File too large (max 10 MB).');
}

// ── Get API key ────────────────────────────────────────────────────────────────
$apiKey = $GLOBALS['appSettings']['anthropic_api_key'] ?? '';
if (!$apiKey) {
    jsonError('Anthropic API key is not configured. Add it in Settings → Workflow Settings.');
}

// ── Build Claude message ───────────────────────────────────────────────────────
$b64 = base64_encode(file_get_contents($tmpPath));

$isPdf = ($mimeType === 'application/pdf');

$contentBlock = $isPdf
    ? [
        'type'   => 'document',
        'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $b64],
      ]
    : [
        'type'   => 'image',
        'source' => ['type' => 'base64', 'media_type' => $mimeType, 'data' => $b64],
      ];

$prompt = <<<'PROMPT'
You are extracting billing data from an invoice document.
Return ONLY a valid JSON object with these exact keys (use null for any field you cannot find):

{
  "vendor_name":      "string — company name of the vendor/biller",
  "invoice_number":   "string — invoice or bill number",
  "invoice_date":     "string — invoice date in YYYY-MM-DD format",
  "due_date":         "string — payment due date in YYYY-MM-DD format, or null",
  "amount":           "number — total amount due (numeric, no currency symbol)",
  "po_number":        "string — purchase order number if present, or null",
  "notes":            "string — any payment instructions, remittance info, or special terms, or null"
}

Rules:
- Dates MUST be YYYY-MM-DD. If you see MM/DD/YYYY convert it.
- Amount must be a plain number like 1250.00, not "$1,250.00".
- Do not include any text outside the JSON object.
PROMPT;

$payload = [
    'model'      => 'claude-sonnet-4-6',
    'max_tokens' => 512,
    'messages'   => [[
        'role'    => 'user',
        'content' => [$contentBlock, ['type' => 'text', 'text' => $prompt]],
    ]],
];

// ── Call Claude API ────────────────────────────────────────────────────────────
$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
        'anthropic-beta: pdfs-2024-09-25',   // required for PDF document type
    ],
    CURLOPT_TIMEOUT        => 60,
]);

$raw      = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    jsonError('Network error contacting AI: ' . $curlErr);
}
if ($httpCode !== 200) {
    $decoded = json_decode($raw, true);
    $errMsg  = $decoded['error']['message'] ?? ('HTTP ' . $httpCode);
    jsonError('Claude API error: ' . $errMsg);
}

$response = json_decode($raw, true);
$text     = $response['content'][0]['text'] ?? '';

// Strip markdown code fences if present
$text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
$text = preg_replace('/\s*```$/', '', $text);

$extracted = json_decode($text, true);
if (!is_array($extracted)) {
    jsonError('Could not parse AI response as JSON. Raw: ' . substr($text, 0, 200));
}

// Sanitise amount to numeric
if (isset($extracted['amount']) && $extracted['amount'] !== null) {
    $extracted['amount'] = (float) preg_replace('/[^0-9.]/', '', (string) $extracted['amount']);
}

echo json_encode(['success' => true, 'data' => $extracted]);
