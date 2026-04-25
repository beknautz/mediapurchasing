<?php
/**
 * src/GoogleBusinessService.php
 * Google Business Profile API integration — posts, locations, insights.
 *
 * Uses REST API directly via Guzzle (the discovery-based client does not
 * cover the newer mybusinesspostings/mybusinessbusinessinformation endpoints).
 *
 * @see https://developers.google.com/my-business/reference/businessinformation/rest
 * @see https://developers.google.com/my-business/reference/rest/v4/accounts.locations.localPosts
 */

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;

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

    /**
     * Get a fresh access token, refreshing if necessary.
     */
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

        // Refresh token
        $response = (new GuzzleClient())->post('https://oauth2.googleapis.com/token', [
            'form_params' => [
                'client_id'     => GOOGLE_CLIENT_ID,
                'client_secret' => GOOGLE_CLIENT_SECRET,
                'refresh_token' => $this->tokens['refresh_token'],
                'grant_type'    => 'refresh_token',
            ],
        ]);
        $data = json_decode((string) $response->getBody(), true);

        $this->accessToken           = $data['access_token'];
        $this->tokens['access_token'] = $data['access_token'];
        $this->tokens['expires_at']   = time() + (int)($data['expires_in'] ?? 3600);

        // Persist updated tokens
        if (defined('GOOGLE_TOKENS_PATH')) {
            file_put_contents(GOOGLE_TOKENS_PATH, json_encode($this->tokens, JSON_PRETTY_PRINT));
        }

        return $this->accessToken;
    }

    private function http(): GuzzleClient
    {
        return new GuzzleClient([
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'Content-Type'  => 'application/json',
            ],
        ]);
    }

    // ── Accounts & Locations ───────────────────────────────────────────────

    /**
     * List Google Business accounts the authenticated user manages.
     */
    public function getAccounts(): array
    {
        $response = $this->http()->get(
            'https://mybusinessaccountmanagement.googleapis.com/v1/accounts'
        );
        $data = json_decode((string) $response->getBody(), true);
        return $data['accounts'] ?? [];
    }

    /**
     * List locations (businesses) under an account.
     * $accountName — format: "accounts/{accountId}"
     */
    public function getLocations(string $accountName): array
    {
        $response = $this->http()->get(
            "https://mybusinessbusinessinformation.googleapis.com/v1/{$accountName}/locations",
            ['query' => ['readMask' => 'name,title,storefrontAddress,websiteUri,regularHours,phoneNumbers']]
        );
        $data = json_decode((string) $response->getBody(), true);
        return $data['locations'] ?? [];
    }

    // ── Local Posts ────────────────────────────────────────────────────────

    /**
     * Create a local post on a Business Profile location.
     *
     * $locationName — format: "locations/{locationId}"
     * $postData keys:
     *   summary     string  (caption / body text)
     *   image_url   string  (optional photo URL)
     *   cta_type    string  LEARN_MORE | CALL | BOOK | ORDER | SIGN_UP | SHOP | GET_OFFER
     *   cta_url     string  (URL for the call-to-action button)
     *   start_date  string  YYYY-MM-DD (for events/offers)
     *   end_date    string  YYYY-MM-DD
     */
    public function createPost(string $locationName, array $postData): array
    {
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

        $response = $this->http()->post(
            "https://mybusiness.googleapis.com/v4/{$locationName}/localPosts",
            ['json' => $body]
        );
        return json_decode((string) $response->getBody(), true);
    }

    /**
     * List local posts for a location.
     */
    public function listPosts(string $locationName): array
    {
        $response = $this->http()->get(
            "https://mybusiness.googleapis.com/v4/{$locationName}/localPosts"
        );
        $data = json_decode((string) $response->getBody(), true);
        return $data['localPosts'] ?? [];
    }

    /**
     * Delete a local post.
     * $postName — format: "locations/{locationId}/localPosts/{postId}"
     */
    public function deletePost(string $postName): void
    {
        $this->http()->delete(
            "https://mybusiness.googleapis.com/v4/{$postName}"
        );
    }

    // ── Insights ───────────────────────────────────────────────────────────

    /**
     * Get basic location metrics.
     * Returns searches, views, clicks, direction requests for the last 30 days.
     */
    public function getLocationInsights(string $locationName): array
    {
        $endDate   = new DateTime();
        $startDate = (new DateTime())->modify('-30 days');

        $body = [
            'locationNames' => [$locationName],
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

        // Get account name from location name (locations/{id} → need account context)
        // NOTE: reportInsights requires account-level endpoint
        // Use the parent account from the location resource name
        $accountName = 'accounts/' . ($this->tokens['business_account_id'] ?? '');

        try {
            $response = $this->http()->post(
                "https://mybusiness.googleapis.com/v4/{$accountName}/locations:reportInsights",
                ['json' => $body]
            );
            $data = json_decode((string) $response->getBody(), true);
            return $data['locationMetrics'][0]['metricValues'] ?? [];
        } catch (ClientException $e) {
            return [];
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────

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
