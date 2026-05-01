<?php
/**
 * src/GoogleAdsService.php
 * Google Ads REST API — PHP 8.1+ required. No Composer/gRPC.
 * API version is controlled by GOOGLE_ADS_API_VERSION in config/google.php.
 *
 * @see https://developers.google.com/google-ads/api/rest/reference/rest
 */

class GoogleAdsService
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private string $apiBase;
    private int    $customerId;
    private array  $tokens      = [];
    private string $accessToken = '';

    public function __construct(?string $customerId = null)
    {
        if (!defined('GOOGLE_ADS_CUSTOMER_ID')) {
            require_once __DIR__ . '/../config/google.php';
        }

        $version       = defined('GOOGLE_ADS_API_VERSION') ? GOOGLE_ADS_API_VERSION : 'v20';
        $this->apiBase = 'https://googleads.googleapis.com/' . $version;

        $raw = $customerId ?? GOOGLE_ADS_CUSTOMER_ID;
        $this->customerId = (int) preg_replace('/\D/', '', (string) $raw);
        $this->tokens     = $this->loadTokens();
    }

    // ── Connection ─────────────────────────────────────────────────────────────

    public function isConfigured(): bool
    {
        return $this->customerId > 0
            && defined('GOOGLE_CLIENT_ID')    && GOOGLE_CLIENT_ID !== ''
            && defined('GOOGLE_ADS_DEVELOPER_TOKEN') && GOOGLE_ADS_DEVELOPER_TOKEN !== ''
            && !empty($this->tokens['refresh_token']);
    }

    // ── Token management ───────────────────────────────────────────────────────

    private function loadTokens(): array
    {
        if (!defined('GOOGLE_TOKENS_PATH') || !file_exists(GOOGLE_TOKENS_PATH)) {
            return [];
        }
        $json = file_get_contents(GOOGLE_TOKENS_PATH);
        if ($json === false) return [];
        return json_decode($json, true) ?? [];
    }

    public static function saveTokens(array $tokens): void
    {
        if (!defined('GOOGLE_TOKENS_PATH')) {
            require_once __DIR__ . '/../config/google.php';
        }
        // GOOGLE_TOKENS_PATH must be outside the public web root.
        $path = GOOGLE_TOKENS_PATH;
        $dir  = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $json = json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Failed to encode token JSON: ' . json_last_error_msg());
        }
        if (file_put_contents($path, $json, LOCK_EX) === false) {
            throw new RuntimeException('Failed to write token file: ' . $path);
        }
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken) return $this->accessToken;

        // Use cached token if still valid (60 s buffer)
        if (!empty($this->tokens['access_token'])
            && time() < ($this->tokens['expires_at'] ?? 0) - 60) {
            $this->accessToken = $this->tokens['access_token'];
            return $this->accessToken;
        }

        if (empty($this->tokens['refresh_token'])) {
            throw new RuntimeException('No refresh token. Reconnect on the Google Settings page.');
        }

        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'client_id'     => GOOGLE_CLIENT_ID,
                'client_secret' => GOOGLE_CLIENT_SECRET,
                'refresh_token' => $this->tokens['refresh_token'],
                'grant_type'    => 'refresh_token',
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT    => 15,
        ]);
        $raw     = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) throw new RuntimeException('Token refresh cURL error: ' . $curlErr);

        $data = json_decode((string) $raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($data['access_token'])) {
            throw new RuntimeException('Token refresh failed: ' . ($data['error_description'] ?? 'Invalid response'));
        }

        $this->accessToken            = $data['access_token'];
        $this->tokens['access_token'] = $data['access_token'];
        $this->tokens['expires_at']   = time() + (int) ($data['expires_in'] ?? 3600);

        try {
            self::saveTokens($this->tokens);
        } catch (RuntimeException $e) {
            // Non-fatal — token still works in memory this request
        }

        return $this->accessToken;
    }

    // ── Low-level HTTP ─────────────────────────────────────────────────────────

    private function request(string $method, string $path, ?array $body = null): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Google Ads API not configured. Visit Ad Automation → Google Settings.');
        }

        $url     = $this->apiBase . $path;
        $headers = [
            'Authorization: Bearer ' . $this->getAccessToken(),
            'developer-token: '      . GOOGLE_ADS_DEVELOPER_TOKEN,
            'Content-Type: application/json',
        ];

        // Only send login-customer-id when a manager account is explicitly configured.
        // Do NOT fall back to GOOGLE_ADS_CUSTOMER_ID — that causes 403 for sub-accounts.
        if (defined('GOOGLE_ADS_MANAGER_ID') && GOOGLE_ADS_MANAGER_ID !== '') {
            $headers[] = 'login-customer-id: ' . preg_replace('/\D/', '', GOOGLE_ADS_MANAGER_ID);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
        ]);
        if ($body !== null) {
            $encoded = json_encode($body);
            if ($encoded === false) {
                throw new RuntimeException('Failed to encode request body: ' . json_last_error_msg());
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
        }

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) throw new RuntimeException('cURL error (' . $httpCode . '): ' . $curlErr);

        $data = json_decode((string) $raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                'Non-JSON response (HTTP ' . $httpCode . '): ' . mb_substr((string) $raw, 0, 200)
            );
        }

        if ($httpCode >= 400) {
            $msg = $data['error']['message'] ?? ('HTTP ' . $httpCode);

            // Extract field-level errors from the details array
            $extras = [];
            foreach ((array) ($data['error']['details'] ?? []) as $detail) {
                foreach ((array) ($detail['errors'] ?? []) as $fe) {
                    $code  = is_array($fe['errorCode'] ?? null)
                        ? implode('/', array_values($fe['errorCode']))
                        : (string) ($fe['errorCode'] ?? '');
                    $field = $fe['trigger']['stringValue']
                        ?? ($fe['location']['fieldPathElements'][0]['fieldName'] ?? '');
                    $extras[] = $code . ($field ? " [{$field}]" : '');
                }
            }
            if ($extras) $msg .= ' — ' . implode('; ', $extras);

            // Detect test-mode token (never expose the token value itself)
            if ($httpCode === 403
                || strpos($msg, 'DEVELOPER_TOKEN') !== false
                || strpos($msg, 'developer token') !== false) {
                $msg .= ' — Ensure your developer token has Basic Access in the manager account.';
            }

            throw new RuntimeException($msg);
        }

        return $data;
    }

    /**
     * Execute a GAQL query. Returns a flat array of result rows across all pages.
     */
    private function gaql(string $query): array
    {
        $rows      = [];
        $pageToken = null;

        do {
            $body = ['query' => $query, 'pageSize' => 500];
            if ($pageToken) $body['pageToken'] = $pageToken;

            $data = $this->request(
                'POST',
                '/customers/' . $this->customerId . '/googleAds:search',
                $body
            );

            foreach ($data['results'] ?? [] as $row) {
                $rows[] = $row;
            }
            $pageToken = $data['nextPageToken'] ?? null;

        } while ($pageToken);

        return $rows;
    }

    // ── Validation helpers ─────────────────────────────────────────────────────

    private static function validateCampaignResource(string $resource): void
    {
        if (!preg_match('#^customers/\d+/campaigns/\d+$#', $resource)) {
            throw new \InvalidArgumentException(
                "Invalid campaign resource name: {$resource}. "
                . 'Expected format: customers/{id}/campaigns/{id}'
            );
        }
    }

    private static function validateDays(int $days): int
    {
        if ($days < 1 || $days > 365) {
            throw new \InvalidArgumentException("Days must be between 1 and 365, got {$days}.");
        }
        return $days;
    }

    // ── Campaigns ──────────────────────────────────────────────────────────────

    /**
     * Create a Search campaign with a daily budget.
     * Returns ['campaign_resource' => '...', 'budget_resource' => '...']
     */
    public function createSearchCampaign(
        string $name,
        float  $dailyBudgetUsd,
        string $startDate   = '',
        string $endDate     = '',
        bool   $startPaused = true
    ): array {
        $name = trim($name);
        if ($name === '') throw new \InvalidArgumentException('Campaign name cannot be blank.');
        if ($dailyBudgetUsd <= 0) throw new \InvalidArgumentException('Daily budget must be greater than 0.');

        // 1. Campaign Budget
        $budgetResp     = $this->request('POST', '/customers/' . $this->customerId . '/campaignBudgets:mutate', [
            'operations' => [['create' => [
                'name'           => $name . ' Budget',
                'deliveryMethod' => 'STANDARD',
                'amountMicros'   => (int) round($dailyBudgetUsd * 1_000_000),
            ]]],
        ]);
        $budgetResource = $budgetResp['results'][0]['resourceName']
            ?? throw new RuntimeException('Failed to create campaign budget.');

        // 2. Campaign
        $campaign = [
            'name'                   => $name,
            'advertisingChannelType' => 'SEARCH',
            'status'                 => $startPaused ? 'PAUSED' : 'ENABLED',
            'manualCpc'              => ['enhancedCpcEnabled' => false],
            'campaignBudget'         => $budgetResource,
            'networkSettings'        => [
                'targetGoogleSearch'  => true,
                'targetSearchNetwork' => true,
            ],
        ];
        if ($startDate) $campaign['startDate'] = str_replace('-', '', $startDate);
        if ($endDate)   $campaign['endDate']   = str_replace('-', '', $endDate);

        $campResp = $this->request('POST', '/customers/' . $this->customerId . '/campaigns:mutate', [
            'operations' => [['create' => $campaign]],
        ]);
        $campaignResource = $campResp['results'][0]['resourceName']
            ?? throw new RuntimeException('Failed to create campaign.');

        return [
            'campaign_resource' => $campaignResource,
            'budget_resource'   => $budgetResource,
        ];
    }

    /**
     * Create an ad group + Responsive Search Ad inside an existing campaign.
     * Requires at least 3 headlines and 2 descriptions (Google's practical minimum).
     */
    public function createAdGroupWithRSA(
        string $campaignResource,
        array  $adCopy,
        string $finalUrl
    ): array {
        self::validateCampaignResource($campaignResource);

        if (!filter_var($finalUrl, FILTER_VALIDATE_URL)
            || strpos($finalUrl, 'https://') !== 0) {
            throw new \InvalidArgumentException('Final URL must be a valid https:// URL.');
        }

        $rawHeadlines = json_decode($adCopy['google_headlines']    ?? '[]', true) ?: [];
        $rawDescs     = json_decode($adCopy['google_descriptions'] ?? '[]', true) ?: [];

        // Enforce string type on each element
        $rawHeadlines = array_filter($rawHeadlines, 'is_string');
        $rawDescs     = array_filter($rawDescs,     'is_string');

        if (count($rawHeadlines) < 3) {
            throw new RuntimeException('At least 3 Google headlines are required for a Responsive Search Ad.');
        }
        if (count($rawDescs) < 2) {
            throw new RuntimeException('At least 2 Google descriptions are required for a Responsive Search Ad.');
        }

        // Ad Group
        $agResp = $this->request('POST', '/customers/' . $this->customerId . '/adGroups:mutate', [
            'operations' => [['create' => [
                'name'     => mb_substr(($adCopy['headline'] ?? 'Ad Group'), 0, 255) . ' — Group',
                'campaign' => $campaignResource,
                'status'   => 'ENABLED',
                'type'     => 'SEARCH_STANDARD',
            ]]],
        ]);
        $adGroupResource = $agResp['results'][0]['resourceName']
            ?? throw new RuntimeException('Failed to create ad group.');

        $headlines = array_map(
            fn($h) => ['text' => mb_substr((string) $h, 0, 30)],
            array_slice(array_values($rawHeadlines), 0, 15)
        );
        $descs = array_map(
            fn($d) => ['text' => mb_substr((string) $d, 0, 90)],
            array_slice(array_values($rawDescs), 0, 4)
        );

        $adResp = $this->request('POST', '/customers/' . $this->customerId . '/adGroupAds:mutate', [
            'operations' => [['create' => [
                'adGroup' => $adGroupResource,
                'status'  => 'ENABLED',
                'ad'      => [
                    'finalUrls'          => [$finalUrl],
                    'responsiveSearchAd' => [
                        'headlines'    => $headlines,
                        'descriptions' => $descs,
                    ],
                ],
            ]]],
        ]);
        $adResource = $adResp['results'][0]['resourceName']
            ?? throw new RuntimeException('Failed to create RSA.');

        return [
            'ad_group_resource' => $adGroupResource,
            'ad_resource'       => $adResource,
        ];
    }

    // ── Campaign Status ────────────────────────────────────────────────────────

    public function setCampaignStatus(string $campaignResource, string $status): void
    {
        self::validateCampaignResource($campaignResource);
        $map = ['active' => 'ENABLED', 'paused' => 'PAUSED', 'removed' => 'REMOVED'];
        $apiStatus = $map[$status] ?? throw new \InvalidArgumentException("Unknown status: {$status}");

        $this->request('POST', '/customers/' . $this->customerId . '/campaigns:mutate', [
            'operations' => [['update' => [
                'resourceName' => $campaignResource,
                'status'       => $apiStatus,
            ], 'updateMask' => 'status']],
        ]);
    }

    // ── Campaign Listing ───────────────────────────────────────────────────────

    public function listCampaigns(): array
    {
        return $this->gaql(
            'SELECT campaign.resource_name, campaign.id, campaign.name, campaign.status,
                    campaign.start_date, campaign.end_date,
                    campaign_budget.amount_micros
               FROM campaign
              WHERE campaign.status NOT IN (\'REMOVED\')
              ORDER BY campaign.name ASC
              LIMIT 500'
        );
    }

    // ── Performance Reporting ──────────────────────────────────────────────────

    public function getCampaignPerformance(int $days = 30): array
    {
        $days      = self::validateDays($days);
        $endDate   = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $rows = $this->gaql(
            "SELECT campaign.resource_name, campaign.id, campaign.name,
                    metrics.impressions, metrics.clicks,
                    metrics.ctr, metrics.average_cpc, metrics.cost_micros,
                    metrics.conversions
               FROM campaign
              WHERE campaign.status = 'ENABLED'
                AND segments.date BETWEEN '{$startDate}' AND '{$endDate}'
              ORDER BY metrics.impressions DESC
              LIMIT 50"
        );

        return array_map(function (array $row) {
            $row['cost_usd'] = round(($row['metrics']['costMicros'] ?? 0) / 1_000_000, 2);
            $row['avg_cpc']  = round(($row['metrics']['averageCpc'] ?? 0) / 1_000_000, 2);
            $row['ctr_pct']  = round(($row['metrics']['ctr']        ?? 0) * 100,        2);
            return $row;
        }, $rows);
    }

    public function getAdPerformance(string $campaignResource, int $days = 30): array
    {
        self::validateCampaignResource($campaignResource);
        $days      = self::validateDays($days);
        $endDate   = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        // Escape the resource name for safe GAQL insertion
        $safeResource = addslashes($campaignResource);

        return $this->gaql(
            "SELECT ad_group_ad.ad.id,
                    ad_group_ad.ad.responsive_search_ad.headlines,
                    metrics.impressions, metrics.clicks, metrics.ctr,
                    metrics.average_cpc, metrics.cost_micros
               FROM ad_group_ad
              WHERE campaign.resource_name = '{$safeResource}'
                AND ad_group_ad.status = 'ENABLED'
                AND segments.date BETWEEN '{$startDate}' AND '{$endDate}'
              LIMIT 25"
        );
    }

    // ── OAuth helpers ──────────────────────────────────────────────────────────

    public static function buildAuthUrl(): string
    {
        if (!defined('GOOGLE_CLIENT_ID')) {
            require_once __DIR__ . '/../config/google.php';
        }
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => GOOGLE_CLIENT_ID,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'response_type' => 'code',
            'scope'         => implode(' ', [
                'https://www.googleapis.com/auth/adwords',
                'https://www.googleapis.com/auth/business.manage',
            ]),
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        ]);
    }

    public static function exchangeCode(string $code): array
    {
        if (!defined('GOOGLE_CLIENT_ID')) {
            require_once __DIR__ . '/../config/google.php';
        }
        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'code'          => $code,
                'client_id'     => GOOGLE_CLIENT_ID,
                'client_secret' => GOOGLE_CLIENT_SECRET,
                'redirect_uri'  => GOOGLE_REDIRECT_URI,
                'grant_type'    => 'authorization_code',
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT    => 15,
        ]);
        $raw     = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);
        if ($curlErr) throw new RuntimeException('cURL error during token exchange: ' . $curlErr);
        $data = json_decode((string) $raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON from token endpoint.');
        }
        return $data ?? [];
    }
}
