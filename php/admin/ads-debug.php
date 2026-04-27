<?php
/**
 * Temporary diagnostic — delete after debugging Google Ads 404.
 * Access: /admin/ads-debug.php
 */
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/google.php';
requireRole(['admin']);

header('Content-Type: text/plain');

echo "=== Google Ads Debug ===\n\n";
echo "GOOGLE_ADS_CUSTOMER_ID : " . (defined('GOOGLE_ADS_CUSTOMER_ID') ? GOOGLE_ADS_CUSTOMER_ID : 'NOT DEFINED') . "\n";
echo "GOOGLE_ADS_MANAGER_ID  : " . (defined('GOOGLE_ADS_MANAGER_ID')  ? GOOGLE_ADS_MANAGER_ID  : 'NOT DEFINED') . "\n";
echo "GOOGLE_ADS_DEVELOPER_TOKEN set: " . (defined('GOOGLE_ADS_DEVELOPER_TOKEN') && GOOGLE_ADS_DEVELOPER_TOKEN !== '' ? 'YES' : 'NO') . "\n";
echo "GOOGLE_CLIENT_ID set   : " . (defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== '' ? 'YES' : 'NO') . "\n";

$tokenFile = defined('GOOGLE_TOKENS_PATH') ? GOOGLE_TOKENS_PATH : '(not defined)';
echo "Token file             : " . $tokenFile . "\n";
echo "Token file exists      : " . (file_exists($tokenFile) ? 'YES' : 'NO') . "\n";

$tokens = (file_exists($tokenFile)) ? (json_decode(file_get_contents($tokenFile), true) ?? []) : [];
echo "Has refresh_token      : " . (!empty($tokens['refresh_token']) ? 'YES' : 'NO') . "\n";
echo "Has access_token       : " . (!empty($tokens['access_token'])  ? 'YES' : 'NO') . "\n";
echo "Token expires_at       : " . ($tokens['expires_at'] ?? 'not set') . " (now: " . time() . ")\n\n";

// Compute what login-customer-id will be sent
$managerId = (defined('GOOGLE_ADS_MANAGER_ID') && GOOGLE_ADS_MANAGER_ID !== '')
    ? preg_replace('/\D/', '', GOOGLE_ADS_MANAGER_ID)
    : preg_replace('/\D/', '', GOOGLE_ADS_CUSTOMER_ID ?? '');
echo "login-customer-id that will be sent: " . ($managerId ?: '(empty — not sent)') . "\n\n";

// Make a raw curl call identical to what GoogleAdsService does
echo "=== Making raw curl POST to googleAds:search ===\n\n";

$customerId = preg_replace('/\D/', '', GOOGLE_ADS_CUSTOMER_ID ?? '');
$url = 'https://googleads.googleapis.com/v18/customers/' . $customerId . '/googleAds:search';
echo "URL: $url\n";

// Get access token the same way GoogleAdsService does
$accessToken = '';
if (!empty($tokens['access_token']) && time() < ($tokens['expires_at'] ?? 0) - 60) {
    $accessToken = $tokens['access_token'];
    echo "Using cached access token\n";
} elseif (!empty($tokens['refresh_token'])) {
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'refresh_token' => $tokens['refresh_token'],
            'grant_type'    => 'refresh_token',
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT    => 15,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($raw, true) ?? [];
    $accessToken = $data['access_token'] ?? '';
    echo "Refreshed access token: " . ($accessToken ? 'OK' : 'FAILED — ' . ($data['error_description'] ?? $raw)) . "\n";
}

$headers = [
    'Authorization: Bearer ' . $accessToken,
    'developer-token: ' . GOOGLE_ADS_DEVELOPER_TOKEN,
    'Content-Type: application/json',
];
if ($managerId) {
    $headers[] = 'login-customer-id: ' . $managerId;
}

echo "Headers sent:\n";
foreach ($headers as $h) {
    // Redact sensitive values
    $display = preg_replace('/(Bearer\s+)\S+/', '$1[REDACTED]', $h);
    $display = preg_replace('/(developer-token:\s+)\S+/', '$1[REDACTED]', $display);
    echo "  $display\n";
}

$body = json_encode(['query' => 'SELECT campaign.id, campaign.name FROM campaign WHERE campaign.status != "REMOVED" LIMIT 10', 'pageSize' => 10]);
echo "Body: $body\n\n";

$responseHeaders = [];
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => 'POST',
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$responseHeaders) {
        $responseHeaders[] = trim($header);
        return strlen($header);
    },
]);

$raw      = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

echo "HTTP status: $httpCode\n";
if ($curlErr) echo "cURL error: $curlErr\n";

echo "Response headers:\n";
foreach ($responseHeaders as $h) {
    if ($h) echo "  $h\n";
}

echo "\nResponse body (first 600 chars):\n";
echo mb_substr((string)$raw, 0, 600) . "\n";

// ── listAccessibleCustomers ────────────────────────────────────────────────
echo "\n\n=== listAccessibleCustomers (what this token can actually see) ===\n\n";

$accessHeaders = [
    'Authorization: Bearer ' . $accessToken,
    'developer-token: ' . GOOGLE_ADS_DEVELOPER_TOKEN,
    'Content-Type: application/json',
    'login-customer-id: ' . $managerId,
];

$ch2 = curl_init('https://googleads.googleapis.com/v18/customers:listAccessibleCustomers');
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => 'GET',
    CURLOPT_HTTPHEADER     => $accessHeaders,
    CURLOPT_TIMEOUT        => 30,
]);
$raw2      = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

echo "HTTP status: $httpCode2\n";
$data2 = json_decode($raw2, true);
if (isset($data2['resourceNames'])) {
    echo "Accessible customer resource names:\n";
    foreach ($data2['resourceNames'] as $rn) {
        echo "  $rn\n";
    }
} else {
    echo "Raw response:\n" . mb_substr($raw2, 0, 800) . "\n";
}

// ── Try manager account directly ───────────────────────────────────────────
echo "\n\n=== Try querying manager account " . $managerId . " directly ===\n\n";

$mgr = $managerId;
$mgrUrl = 'https://googleads.googleapis.com/v18/customers/' . $mgr . '/googleAds:search';
$mgrHeaders = [
    'Authorization: Bearer ' . $accessToken,
    'developer-token: ' . GOOGLE_ADS_DEVELOPER_TOKEN,
    'Content-Type: application/json',
    'login-customer-id: ' . $mgr,
];
$ch3 = curl_init($mgrUrl);
curl_setopt_array($ch3, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => 'POST',
    CURLOPT_HTTPHEADER     => $mgrHeaders,
    CURLOPT_POSTFIELDS     => json_encode(['query' => 'SELECT customer_client.id, customer_client.descriptive_name FROM customer_client LIMIT 20', 'pageSize' => 20]),
    CURLOPT_TIMEOUT        => 30,
]);
$raw3      = curl_exec($ch3);
$httpCode3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
curl_close($ch3);

echo "URL: $mgrUrl\n";
echo "HTTP status: $httpCode3\n";
$data3 = json_decode($raw3, true);
if (isset($data3['results'])) {
    echo "Sub-accounts under manager $mgr:\n";
    foreach ($data3['results'] as $row) {
        $id   = $row['customerClient']['id']              ?? '?';
        $name = $row['customerClient']['descriptiveName'] ?? '(no name)';
        echo "  ID: $id  Name: $name\n";
    }
    if (empty($data3['results'])) echo "  (no results)\n";
} else {
    echo "Raw response:\n" . mb_substr($raw3, 0, 800) . "\n";
}
