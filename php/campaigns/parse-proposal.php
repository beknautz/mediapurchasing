<?php
/**
 * campaigns/parse-proposal.php
 * AJAX: read a vendor's uploaded proposal file (PDF or XLSX),
 * send to Claude API, return structured ad schedule + production
 * schedule JSON.  Optionally saves directly to DB when save=1.
 *
 * POST params:
 *   file_path   — relative path stored in attachment record (e.g. uploads/campaigns/3/replies/20260518_file.xlsx)
 *   file_name   — original filename (for display)
 *   campaign_id — int
 *   vendor_id   — int
 *   vendor_name — string
 *   save        — 1 to persist to DB, 0 to just return JSON preview
 */
ob_start();
require_once __DIR__ . '/../bootstrap.php';
ob_clean();
header('Content-Type: application/json');

// Auth
if (empty($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
    exit;
}
$userRole = $_SESSION['user']['role'] ?? '';
if (!in_array($userRole, ['admin', 'buyer'], true)) {
    echo json_encode(['success' => false, 'error' => 'Access denied.']);
    exit;
}

function jsonErr(string $msg): never {
    ob_clean();
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// ── Inputs ────────────────────────────────────────────────────────────────────
$relPath    = trim($_POST['file_path']   ?? '');
$fileName   = trim($_POST['file_name']   ?? 'proposal');
$campaignId = (int)($_POST['campaign_id'] ?? 0);
$vendorId   = (int)($_POST['vendor_id']   ?? 0);
$vendorName = trim($_POST['vendor_name']  ?? '');
$doSave     = (int)($_POST['save']        ?? 0) === 1;

if (!$relPath || !$campaignId || !$vendorId) {
    jsonErr('Missing required parameters (file_path, campaign_id, vendor_id).');
}

// Prevent directory traversal
$relPath = ltrim(str_replace(['..', "\0"], '', $relPath), '/\\');
$absPath = realpath(__DIR__ . '/../' . $relPath);
$allowed = realpath(__DIR__ . '/../uploads');
if (!$absPath || !$allowed || !str_starts_with($absPath, $allowed)) {
    jsonErr('Invalid file path.');
}
if (!is_file($absPath)) {
    jsonErr('File not found on server.');
}

$mimeType = mime_content_type($absPath);
$isXlsx   = in_array($mimeType, [
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-excel',
    'application/zip',  // some xlsx files are detected as zip
], true) || str_ends_with(strtolower($fileName), '.xlsx') || str_ends_with(strtolower($fileName), '.xls');

$isPdf = ($mimeType === 'application/pdf') || str_ends_with(strtolower($fileName), '.pdf');

if (!$isPdf && !$isXlsx) {
    jsonErr('Only PDF and XLSX files can be parsed. Received: ' . $mimeType);
}

// ── API key ───────────────────────────────────────────────────────────────────
$apiKey = $GLOBALS['appSettings']['anthropic_api_key'] ?? '';
if (!$apiKey) {
    jsonErr('Anthropic API key not configured. Go to Settings → Workflow Settings.');
}

// ── Build content block for Claude ───────────────────────────────────────────
$headers = [
    'Content-Type: application/json',
    'x-api-key: ' . $apiKey,
    'anthropic-version: 2023-06-01',
];

if ($isPdf) {
    $b64          = base64_encode(file_get_contents($absPath));
    $contentBlock = [
        ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $b64]],
    ];
    $headers[] = 'anthropic-beta: pdfs-2024-09-25';
} else {
    // XLSX — extract cell text via ZipArchive (no library required)
    $sheetText = extractXlsxText($absPath);
    if ($sheetText === '') {
        jsonErr('Could not read XLSX file. It may be corrupt or password-protected.');
    }
    $contentBlock = [
        ['type' => 'text', 'text' => "XLSX spreadsheet contents (tab-separated):\n\n" . $sheetText],
    ];
}

// ── Prompt ────────────────────────────────────────────────────────────────────
$prompt = <<<'PROMPT'
You are parsing a media vendor's RFP proposal/rate card. Extract all line items and return ONLY a JSON object with this exact structure (no markdown, no surrounding text):

{
  "ad_schedule": [
    {
      "media_category": "Radio",
      "placement": "Morning Drive 6-10am",
      "unit_type": ":30 Spot",
      "flight_start": "2026-07-01",
      "flight_end": "2026-07-31",
      "quantity": 40,
      "unit_rate": 75.00,
      "total_cost": 3000.00,
      "notes": ""
    }
  ],
  "production_items": [
    {
      "media_type": "Radio",
      "ad_name": "Morning Drive :30",
      "unit_specs": ":30 second radio spot, mono MP3",
      "material_due_date": "2026-06-20",
      "notes": ""
    }
  ]
}

Rules:
- Dates must be YYYY-MM-DD; use null if unknown.
- Rates and costs must be plain numbers (no $ or commas).
- quantity must be an integer or null.
- For production_items, infer material_due_date as ~10 days before flight_start when possible; use null otherwise.
- Infer unit_specs from the media type and unit type (e.g. ":30 radio spot", "300x250 digital banner at 72dpi", "full page 4-color print ad").
- If the document has no clear line items, return {"ad_schedule":[],"production_items":[]}.
- Output only the JSON object.
PROMPT;

$contentBlock[] = ['type' => 'text', 'text' => $prompt];

$payload = [
    'model'      => 'claude-sonnet-4-6',
    'max_tokens' => 4096,
    'messages'   => [['role' => 'user', 'content' => $contentBlock]],
];

// ── Call Claude ────────────────────────────────────────────────────────────────
$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_TIMEOUT        => 90,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$raw      = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) jsonErr('cURL error: ' . $curlErr);
if ($httpCode !== 200) {
    $dec = json_decode($raw, true);
    jsonErr('Claude API error: ' . ($dec['error']['message'] ?? 'HTTP ' . $httpCode));
}

$response = json_decode($raw, true);
$text     = trim($response['content'][0]['text'] ?? '');
$text     = preg_replace('/^```(?:json)?\s*/i', '', $text);
$text     = preg_replace('/\s*```$/i', '', trim($text));

$parsed = json_decode($text, true);
if (!is_array($parsed) || !isset($parsed['ad_schedule'])) {
    jsonErr('Claude returned unexpected output: ' . substr($text, 0, 300));
}

// ── Optionally save to DB ─────────────────────────────────────────────────────
if ($doSave) {
    $svc = new CampaignService();
    $svc->saveAdScheduleLines($campaignId, $vendorId, $vendorName,
        $parsed['ad_schedule'] ?? [], basename($relPath));
    if (!empty($parsed['production_items'])) {
        $svc->saveProductionItems($campaignId, $vendorId, $vendorName,
            $parsed['production_items']);
    }
}

ob_clean();
echo json_encode([
    'success'     => true,
    'saved'       => $doSave,
    'ad_schedule' => $parsed['ad_schedule']    ?? [],
    'production'  => $parsed['production_items'] ?? [],
    'source_file' => basename($relPath),
]);

// ── XLSX text extractor ───────────────────────────────────────────────────────
function extractXlsxText(string $path): string
{
    if (!class_exists('ZipArchive')) return '';

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return '';

    // Read shared strings
    $sharedStrings = [];
    $ssRaw = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssRaw !== false) {
        $dom = new DOMDocument();
        @$dom->loadXML($ssRaw);
        foreach ($dom->getElementsByTagName('si') as $si) {
            // Concatenate all <t> text nodes within <si>
            $val = '';
            foreach ($si->getElementsByTagName('t') as $t) {
                $val .= $t->textContent;
            }
            $sharedStrings[] = $val;
        }
    }

    // Try each worksheet (sheet1, sheet2 …) until we get content
    $rows = [];
    for ($sheetNum = 1; $sheetNum <= 5; $sheetNum++) {
        $wsRaw = $zip->getFromName("xl/worksheets/sheet{$sheetNum}.xml");
        if ($wsRaw === false) break;

        $dom = new DOMDocument();
        @$dom->loadXML($wsRaw);

        foreach ($dom->getElementsByTagName('row') as $rowEl) {
            $cells = [];
            /** @var DOMElement $cell */
            foreach ($rowEl->getElementsByTagName('c') as $cell) {
                $type = $cell->getAttribute('t');
                $vEl  = $cell->getElementsByTagName('v')->item(0);
                $v    = $vEl ? $vEl->textContent : '';
                if ($type === 's') {
                    // Shared string index
                    $cells[] = $sharedStrings[(int)$v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $is = $cell->getElementsByTagName('is')->item(0);
                    $cells[] = $is ? $is->textContent : '';
                } else {
                    $cells[] = $v;
                }
            }
            if (array_filter($cells, fn($c) => trim($c) !== '')) {
                $rows[] = implode("\t", $cells);
            }
        }
        if (!empty($rows)) break;
    }
    $zip->close();

    return implode("\n", $rows);
}
