<?php
/**
 * src/GoogleBusinessService.php
 * Google Business Profile API integration — posts, locations, insights.
 * Uses REST API directly via PHP curl (no Guzzle dependency).
 *
 * @see https://developers.google.com/my-business/reference/businessinformation/rest
 * @see https://developers.google.com/my-business/reference/rest/v4/accounts.locations.localPosts
 */

class GoogleBusinessService
{
    private array  $tokens = [];
    private string $accessToken = '';

    public function __construct()
    {
        if (!defined('GOOGLE_CLIENT_ID')) {
            require_once __DIR__ . '/../config/google.php';
        }
        $this->tokens = $this->loadTokens();
    }

    // ── Connection ─────────────────────────────────────────────────────────

    public function isConfigured(): bool
    {
        return defined('GOOGLE_CLIENT_ID')
            && GOOGLE_CLIENT_ID !== ''
            && !empty($this->tokens['refresh_token']);
    }

    private function loadTokens(): array
    {
        if (!defined('GOOGLE_TOKENS_PATH') || !file_exists(GOOGLE_TOKENS_PATH)) return [];
        return json_decode(file_get_contents(GOOGLE_TOKENS_PATH), true) ?? [];
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

        // Refresh the token
        $ch = curl_init('https://oauth2.googleapis.com/token');
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
        if ($err) throw new RuntimeException('cURL error refreshing token: ' . $err);

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

    private function request(string $method, string $url, ?array $body = null, array $query = []): array
    {
        if ($query) $url .= '?' . http_build_query($query);

        $headers = [
            'Authorization: Bearer ' . $this->getAccessToken(),
            'Content-Type: application/json',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 20,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        } elseif (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, '');
        }

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err) throw new RuntimeException('cURL error: ' . $err);

        $data = json_decode($raw, true) ?? [];

        if ($httpCode >= 400) {
            $msg = $data['error']['message'] ?? ('HTTP ' . $httpCode . ': ' . $raw);
            throw new RuntimeException('Google API error: ' . $msg);
        }

        return $data;
    }

    // ── Accounts & Locations ───────────────────────────────────────────────

    public function getAccounts(): array
    {
        $data = $this->request('GET', 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts');
        return $data['accounts'] ?? [];
    }

    /**
     * $accountName — format: "accounts/{accountId}"
     */
    public function getLocations(string $accountName): array
    {
        $data = $this->request(
            'GET',
            "https://mybusinessbusinessinformation.googleapis.com/v1/{$accountName}/locations",
            null,
            ['readMask' => 'name,title,storefrontAddress,websiteUri,regularHours,phoneNumbers']
        );
        return $data['locations'] ?? [];
    }

    // ── Local Posts ────────────────────────────────────────────────────────

    /**
     * Create a local post on a Business Profile location.
     * $locationName — format: "locations/{locationId}"
     */
    public function createPost(string $locationName, array $postData): array
    {
        $locationName = $this->normalizeLoc($locationName);
        $body = [
            'languageCode' => 'en',
            'summary'      => $postData['summary'] ?? '',
            'topicType'    => 'STANDARD',
        ];

        if (!empty($postData['image_url'])) {
            $body['media'] = [[
                'mediaFormat' => 'PHOTO',
                'sourceUrl'   => $postData['image_url'],
            ]];
        }

        if (!empty($postData['cta_type']) && !empty($postData['cta_url'])) {
            $body['callToAction'] = [
                'actionType' => $postData['cta_type'],
                'url'        => $postData['cta_url'],
            ];
        }

        if (!empty($postData['start_date'])) {
            $body['topicType'] = 'EVENT';
            $body['event'] = [
                'title'    => $postData['event_title'] ?? ($postData['summary'] ?? ''),
                'schedule' => [
                    'startDate' => $this->dateToApiFormat($postData['start_date']),
                    'endDate'   => $this->dateToApiFormat($postData['end_date'] ?? $postData['start_date']),
                ],
            ];
        }

        return $this->request('POST', "https://mybusiness.googleapis.com/v4/{$locationName}/localPosts", $body);
    }

    public function listPosts(string $locationName): array
    {
        $locationName = $this->normalizeLoc($locationName);
        $data = $this->request('GET', "https://mybusiness.googleapis.com/v4/{$locationName}/localPosts");
        return $data['localPosts'] ?? [];
    }

    public function deletePost(string $postName): void
    {
        // $postName is the full resource path returned by the API (e.g. accounts/.../locations/.../localPosts/...)
        $this->request('DELETE', "https://mybusiness.googleapis.com/v4/{$postName}");
    }

    // ── Insights ───────────────────────────────────────────────────────────

    public function getLocationInsights(string $locationName): array
    {
        $endDate      = new DateTime();
        $startDate    = (new DateTime())->modify('-30 days');
        $fullLocation = $this->normalizeLoc($locationName);
        $accountName  = 'accounts/' . ($this->tokens['business_account_id'] ?? '');

        $body = [
            'locationNames' => [$fullLocation],
            'basicRequest'  => [
                'metricRequests' => [
                    ['metric' => 'QUERIES_DIRECT'],
                    ['metric' => 'QUERIES_INDIRECT'],
                    ['metric' => 'VIEWS_SEARCH'],
                    ['metric' => 'VIEWS_MAPS'],
                    ['metric' => 'ACTIONS_WEBSITE'],
                    ['metric' => 'ACTIONS_PHONE'],
                    ['metric' => 'ACTIONS_DRIVING_DIRECTIONS'],
                ],
                'timeRange' => [
                    'startTime' => $startDate->format('Y-m-d\TH:i:s\Z'),
                    'endTime'   => $endDate->format('Y-m-d\TH:i:s\Z'),
                ],
            ],
        ];

        try {
            $data = $this->request(
                'POST',
                "https://mybusiness.googleapis.com/v4/{$accountName}/locations:reportInsights",
                $body
            );
            return $data['locationMetrics'][0]['metricValues'] ?? [];
        } catch (RuntimeException $e) {
            return [];
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Build the full GBP resource path for a location.
     *
     * The v4 localPosts API requires the full parent path:
     *   accounts/{accountId}/locations/{locationId}
     *
     * Accepts any of:
     *   "12345"                               → accounts/{stored}/locations/12345
     *   "locations/12345"                     → accounts/{stored}/locations/12345
     *   "accounts/111/locations/12345"        → unchanged (already full)
     */
    private function normalizeLoc(string $location): string
    {
        $location = trim($location);
        if ($location === '') return '';

        // Already a full path — return as-is
        if (str_starts_with($location, 'accounts/')) return $location;

        // Strip "locations/" prefix if present to get bare ID
        $locId = str_starts_with($location, 'locations/')
            ? substr($location, strlen('locations/'))
            : $location;

        $accountId = $this->tokens['business_account_id'] ?? '';

        if (!$accountId) {
            // Account ID wasn't saved during OAuth — fetch once per request and persist
            static $fetchedAccountId = null;
            if ($fetchedAccountId !== null) {
                $accountId = $fetchedAccountId;
            } else {
                try {
                    $accounts  = $this->getAccounts();
                    $accountId = str_replace('accounts/', '', $accounts[0]['name'] ?? '');
                    $fetchedAccountId = $accountId;
                    if ($accountId && defined('GOOGLE_TOKENS_PATH')) {
                        $this->tokens['business_account_id']   = $accountId;
                        $this->tokens['business_account_name'] = $accounts[0]['accountName'] ?? '';
                        file_put_contents(GOOGLE_TOKENS_PATH, json_encode($this->tokens, JSON_PRETTY_PRINT));
                    }
                } catch (Throwable $e) {
                    throw new RuntimeException(
                        'Could not retrieve Google Business Profile account: ' . $e->getMessage() . ' — ' .
                        'Try <a href="/ad-automation/google-settings.php">reconnecting your Google account</a>.'
                    );
                }
            }
        }

        if (!$accountId) {
            throw new RuntimeException(
                'No Google Business Profile found for the connected Google account. ' .
                'Visit <a href="https://business.google.com" target="_blank">business.google.com</a> ' .
                'to create or claim a Business Profile, then ' .
                '<a href="/ad-automation/google-settings.php">reconnect your Google account</a>.'
            );
        }

        return 'accounts/' . $accountId . '/locations/' . $locId;
    }

    private function dateToApiFormat(string $date): array
    {
        $d = new DateTime($date);
        return [
            'year'  => (int) $d->format('Y'),
            'month' => (int) $d->format('m'),
            'day'   => (int) $d->format('d'),
        ];
    }
}
