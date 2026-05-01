<?php
/**
 * src/GoogleAdsService.php
 * Google Ads API v18 — REST implementation (no composer/gRPC required).
 * Uses the same OAuth2 tokens stored by the Google OAuth flow.
 *
 * @see https://developers.google.com/google-ads/api/rest/reference/rest
 */

class GoogleAdsService
{
    private const API_BASE    = 'https://googleads.googleapis.com/v20';
    private const TOKEN_URL   = 'https://oauth2.googleapis.com/token';

    private int   $customerId;
    private array $tokens = [];
    private string $accessToken = '';

    public function __construct(?string $customerId = null)
    {
        if (!defined('GOOGLE_ADS_CUSTOMER_ID')) {
            require_once __DIR__ . '/../config/google.php';
        }
        $raw = $customerId ?? GOOGLE_ADS_CUSTOMER_ID;
        $this->customerId = (int) preg_replace('/\D/', '', (string)$raw);
        $this->tokens     = $this->loadTokens();
    }

    // ── Connection ─────────────────────────────────────────────────────────

    public function isConfigured(): bool
    {
        return $this->customerId > 0
            && defined('GOOGLE_CLIENT_ID')
            && GOOGLE_CLIENT_ID !== ''
            && defined('GOOGLE_ADS_DEVELOPER_TOKEN')
            && GOOGLE_ADS_DEVELOPER_TOKEN !== ''
            && !empty($this->tokens['refresh_token']);
    }

    private function loadTokens(): array
    {
        if (!defined('GOOGLE_TOKENS_PATH') || !file_exists(GOOGLE_TOKENS_PATH)) return [];
        return json_decode(file_get_contents(GOOGLE_TOKENS_PATH), true) ?? [];
    }

    public static function saveTokens(array $tokens): void
    {
        if (!defined('GOOGLE_TOKENS_PATH')) {
            require_once __DIR__ . '/../config/google.php';
        }
        file_put_contents(GOOGLE_TOKENS_PATH, json_encode($tokens, JSON_PRETTY_PRINT));
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken) return $this->accessToken;

        if (!empty($this->tokens['access_token'])) {
            $expiresAt = $this->tokens['expires_at'] ?? 0;
            if (time() < $expiresAt - 60) {
                $this->accessToken = $this->tokens['access_token'];
                return $this->accessToken;
            }
        }

        // Refresh
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
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) throw new RuntimeException('Token refresh cURL error: ' . $err);

        $data = json_decode($raw, true) ?? [];
        if (empty($data['access_token'])) {
            throw new RuntimeException('Token refresh failed: ' . ($data['error_description'] ?? $raw));
        }

        $this->accessToken            = $data['access_token'];
        $this->tokens['access_token'] = $data['access_token'];
        $this->tokens['expires_at']   = time() + (int)($data['expires_in'] ?? 3600);

        if (defined('GOOGLE_TOKENS_PATH')) {
            file_put_contents(GOOGLE_TOKENS_PATH, json_encode($this->tokens, JSON_PRETTY_PRINT));
        }

        return $this->accessToken;
    }

    // ── Low-level HTTP ─────────────────────────────────────────────────────

    private function request(string $method, string $path, ?array $body = null): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Google Ads API is not configured. Visit Ad Automation → Google Settings.');
        }

        $url     = self::API_BASE . $path;
        $headers = [
            'Authorization: Bearer '   . $this->getAccessToken(),
            'developer-token: '        . GOOGLE_ADS_DEVELOPER_TOKEN,
            'Content-Type: application/json',
        ];

        // login-customer-id must be the MCC/manager account.
        // Use GOOGLE_ADS_MANAGER_ID if set, otherwise fall back to GOOGLE_ADS_CUSTOMER_ID.
        $managerId = (defined('GOOGLE_ADS_MANAGER_ID') && GOOGLE_ADS_MANAGER_ID !== '')
            ? preg_replace('/\D/', '', GOOGLE_ADS_MANAGER_ID)
            : preg_replace('/\D/', '', GOOGLE_ADS_CUSTOMER_ID ?? '');
        if ($managerId) {
            $headers[] = 'login-customer-id: ' . $managerId;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) throw new RuntimeException('cURL error: ' . $curlErr);

        $data = json_decode($raw, true);

        if ($httpCode >= 400) {
            $msg = $data['error']['message'] ?? null;

            // Append any field-level detail so we know exactly what's wrong
            $fieldErrors = [];
            foreach ($data['error']['details'] ?? [] as $detail) {
                foreach ($detail['errors'] ?? [] as $fe) {
                    $code  = $fe['errorCode'] ?? [];
                    $field = $fe['trigger']['stringValue'] ?? ($fe['location']['fieldPathElements'][0]['fieldName'] ?? '');
                    $fieldErrors[] = json_encode($code) . ($field ? " (field: $field)" : '');
                }
            }
            if ($fieldErrors) $msg .= ' | Detail: ' . implode('; ', $fieldErrors);

            if (!$msg) {
                $msg = 'HTTP ' . $httpCode . ' at ' . $url . ' — ' . mb_substr((string)$raw, 0, 400);
            }

            if ($httpCode === 403
                || str_contains((string)$msg, 'DEVELOPER_TOKEN')
                || str_contains((string)$msg, 'developer token')
                || str_contains((string)$msg, 'TEST_ACCOUNT')) {
                $msg .= ' — Developer token may still be in TEST mode.';
            }
            throw new RuntimeException($msg);
        }

        if (!is_array($data)) {
            throw new RuntimeException('Unexpected response from Google Ads API (HTTP ' . $httpCode . '): ' . mb_substr($raw, 0, 300));
        }
        return $data;
    }

    /**
     * Execute a GAQL query via the search endpoint (paged, simpler than searchStream).
     * Returns a flat array of result rows across all pages.
     */
    private function gaql(string $query): array
    {
        $rows      = [];
        $pageToken = null;

        do {
            $body = ['query' => $query, 'pageSize' => 1000];
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

    // ── Campaigns ─────────────────────────────────────────────────────────

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
        // 1. Campaign Budget
        $budgetResp = $this->request(
            'POST',
            '/customers/' . $this->customerId . '/campaignBudgets:mutate',
            ['operations' => [['create' => [
                'name'           => $name . ' Budget',
                'deliveryMethod' => 'STANDARD',
                'amountMicros'   => (int)($dailyBudgetUsd * 1_000_000),
            ]]]]
        );
        $budgetResource = $budgetResp['results'][0]['resourceName']
            ?? throw new RuntimeException('Failed to create campaign budget.');

        // 2. Campaign
        $campaign = [
            'name'                    => $name,
            'advertisingChannelType'  => 'SEARCH',
            'status'                  => $startPaused ? 'PAUSED' : 'ENABLED',
            'manualCpc'               => ['enhancedCpcEnabled' => false],
            'campaignBudget'          => $budgetResource,
            'networkSettings'         => [
                'targetGoogleSearch'  => true,
                'targetSearchNetwork' => true,
            ],
        ];
        if ($startDate) $campaign['startDate'] = str_replace('-', '', $startDate);
        if ($endDate)   $campaign['endDate']   = str_replace('-', '', $endDate);

        $campResp = $this->request(
            'POST',
            '/customers/' . $this->customerId . '/campaigns:mutate',
            ['operations' => [['create' => $campaign]]]
        );
        $campaignResource = $campResp['results'][0]['resourceName']
            ?? throw new RuntimeException('Failed to create campaign.');

        return [
            'campaign_resource' => $campaignResource,
            'budget_resource'   => $budgetResource,
        ];
    }

    /**
     * Create an ad group + Responsive Search Ad inside an existing campaign.
     */
    public function createAdGroupWithRSA(
        string $campaignResource,
        array  $adCopy,
        string $finalUrl
    ): array {
        // Ad Group
        $agResp = $this->request(
            'POST',
            '/customers/' . $this->customerId . '/adGroups:mutate',
            ['operations' => [['create' => [
                'name'     => ($adCopy['headline'] ?? 'Ad Group') . ' — Group',
                'campaign' => $campaignResource,
                'status'   => 'ENABLED',
                'type'     => 'SEARCH_STANDARD',
            ]]]]
        );
        $adGroupResource = $agResp['results'][0]['resourceName']
            ?? throw new RuntimeException('Failed to create ad group.');

        // Build RSA headlines & descriptions
        $rawHeadlines = json_decode($adCopy['google_headlines']    ?? '[]', true) ?: [];
        $rawDescs     = json_decode($adCopy['google_descriptions'] ?? '[]', true) ?: [];

        if (empty($rawHeadlines)) throw new RuntimeException('Ad copy must have at least one Google headline.');
        if (empty($rawDescs))     throw new RuntimeException('Ad copy must have at least one Google description.');

        $headlines = array_map(
            fn($h) => ['text' => mb_substr($h, 0, 30)],
            array_slice($rawHeadlines, 0, 15)
        );
        $descs = array_map(
            fn($d) => ['text' => mb_substr($d, 0, 90)],
            array_slice($rawDescs, 0, 4)
        );

        $adResp = $this->request(
            'POST',
            '/customers/' . $this->customerId . '/adGroupAds:mutate',
            ['operations' => [['create' => [
                'adGroup' => $adGroupResource,
                'status'  => 'ENABLED',
                'ad'      => [
                    'finalUrls'          => [$finalUrl],
                    'responsiveSearchAd' => [
                        'headlines'    => $headlines,
                        'descriptions' => $descs,
                    ],
                ],
            ]]]]
        );
        $adResource = $adResp['results'][0]['resourceName']
            ?? throw new RuntimeException('Failed to create ad.');

        return [
            'ad_group_resource' => $adGroupResource,
            'ad_resource'       => $adResource,
        ];
    }

    // ── Campaign Status ────────────────────────────────────────────────────

    public function setCampaignStatus(string $campaignResource, string $status): void
    {
        $map = ['active' => 'ENABLED', 'paused' => 'PAUSED', 'removed' => 'REMOVED'];
        $apiStatus = $map[$status] ?? throw new InvalidArgumentException("Unknown status: {$status}");

        $this->request(
            'POST',
            '/customers/' . $this->customerId . '/campaigns:mutate',
            ['operations' => [['update' => [
                'resourceName' => $campaignResource,
                'status'       => $apiStatus,
            ], 'updateMask' => 'status']]]
        );
    }

    // ── Campaign Listing ──────────────────────────────────────────────────

    public function listCampaigns(): array
    {
        return $this->gaql(
            "SELECT campaign.resource_name, campaign.id, campaign.name, campaign.status
               FROM campaign
              WHERE campaign.status != 'REMOVED'
              ORDER BY campaign.name ASC"
        );
    }

    // ── Performance Reporting ─────────────────────────────────────────────

    public function getCampaignPerformance(int $days = 30): array
    {
        $rows = $this->gaql(
            "SELECT campaign.resource_name, campaign.id, campaign.name,
                    metrics.impressions, metrics.clicks,
                    metrics.ctr, metrics.average_cpc, metrics.cost_micros,
                    metrics.conversions
               FROM campaign
              WHERE campaign.status = 'ENABLED'
                AND segments.date DURING LAST_{$days}_DAYS
              ORDER BY metrics.impressions DESC
              LIMIT 50"
        );

        return array_map(function (array $row) {
            $row['cost_usd'] = round(($row['metrics']['costMicros']  ?? 0) / 1_000_000, 2);
            $row['avg_cpc']  = round(($row['metrics']['averageCpc']  ?? 0) / 1_000_000, 2);
            $row['ctr_pct']  = round(($row['metrics']['ctr']         ?? 0) * 100, 2);
            return $row;
        }, $rows);
    }

    public function getAdPerformance(string $campaignResource, int $days = 30): array
    {
        return $this->gaql(
            "SELECT ad_group_ad.ad.id, ad_group_ad.ad.responsive_search_ad.headlines,
                    metrics.impressions, metrics.clicks, metrics.ctr,
                    metrics.average_cpc, metrics.cost_micros
               FROM ad_group_ad
              WHERE campaign.resource_name = '{$campaignResource}'
                AND ad_group_ad.status = 'ENABLED'
                AND segments.date DURING LAST_{$days}_DAYS
              LIMIT 25"
        );
    }

    // ── OAuth helpers ─────────────────────────────────────────────────────

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
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) throw new RuntimeException('cURL error during token exchange: ' . $err);
        return json_decode($raw, true) ?? [];
    }
}
