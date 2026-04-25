<?php
/**
 * src/GoogleAdsService.php
 * Google Ads API integration — campaigns, RSAs, budgets, performance reporting.
 * Uses Google Ads API V18 via googleads/google-ads-php library.
 *
 * @see https://developers.google.com/google-ads/api/docs/start
 */

use Google\Ads\GoogleAds\Lib\V18\GoogleAdsClientBuilder;
use Google\Ads\GoogleAds\Lib\OAuth2\OAuth2TokenBuilder;
use Google\Ads\GoogleAds\Util\V18\ResourceNames;
use Google\Ads\GoogleAds\V18\Common\ManualCpc;
use Google\Ads\GoogleAds\V18\Common\MaximizeClicks;
use Google\Ads\GoogleAds\V18\Common\ResponsiveSearchAdInfo;
use Google\Ads\GoogleAds\V18\Common\AdTextAsset;
use Google\Ads\GoogleAds\V18\Enums\AdGroupStatusEnum\AdGroupStatus;
use Google\Ads\GoogleAds\V18\Enums\AdGroupTypeEnum\AdGroupType;
use Google\Ads\GoogleAds\V18\Enums\AdGroupAdStatusEnum\AdGroupAdStatus;
use Google\Ads\GoogleAds\V18\Enums\AdvertisingChannelTypeEnum\AdvertisingChannelType;
use Google\Ads\GoogleAds\V18\Enums\BudgetDeliveryMethodEnum\BudgetDeliveryMethod;
use Google\Ads\GoogleAds\V18\Enums\CampaignStatusEnum\CampaignStatus;
use Google\Ads\GoogleAds\V18\Resources\Ad;
use Google\Ads\GoogleAds\V18\Resources\AdGroup;
use Google\Ads\GoogleAds\V18\Resources\AdGroupAd;
use Google\Ads\GoogleAds\V18\Resources\Campaign;
use Google\Ads\GoogleAds\V18\Resources\Campaign\NetworkSettings;
use Google\Ads\GoogleAds\V18\Resources\CampaignBudget;
use Google\Ads\GoogleAds\V18\Services\AdGroupAdOperation;
use Google\Ads\GoogleAds\V18\Services\AdGroupOperation;
use Google\Ads\GoogleAds\V18\Services\CampaignBudgetOperation;
use Google\Ads\GoogleAds\V18\Services\CampaignOperation;
use Google\ApiCore\ApiException;

class GoogleAdsService
{
    private ?object $client = null;
    private int     $customerId;
    private array   $tokens = [];

    public function __construct()
    {
        if (!defined('GOOGLE_ADS_CUSTOMER_ID')) {
            require_once __DIR__ . '/../config/google.php';
        }
        $this->customerId = (int) preg_replace('/\D/', '', GOOGLE_ADS_CUSTOMER_ID);
        $this->tokens     = $this->loadTokens();
    }

    // ── Connection helpers ─────────────────────────────────────────────────

    public function isConfigured(): bool
    {
        return defined('GOOGLE_CLIENT_ID')
            && GOOGLE_CLIENT_ID !== ''
            && defined('GOOGLE_ADS_DEVELOPER_TOKEN')
            && GOOGLE_ADS_DEVELOPER_TOKEN !== ''
            && !empty($this->tokens['refresh_token']);
    }

    private function getClient(): object
    {
        if ($this->client) return $this->client;

        if (!$this->isConfigured()) {
            throw new RuntimeException('Google Ads API is not configured. Visit Ad Automation → Google Settings.');
        }

        $oauth2 = (new OAuth2TokenBuilder())
            ->withClientId(GOOGLE_CLIENT_ID)
            ->withClientSecret(GOOGLE_CLIENT_SECRET)
            ->withRefreshToken($this->tokens['refresh_token'])
            ->build();

        $builder = (new GoogleAdsClientBuilder())
            ->withDeveloperToken(GOOGLE_ADS_DEVELOPER_TOKEN)
            ->withOAuth2Credential($oauth2);

        if (defined('GOOGLE_ADS_MANAGER_ID') && GOOGLE_ADS_MANAGER_ID !== '') {
            $builder->withLoginCustomerId((int) preg_replace('/\D/', '', GOOGLE_ADS_MANAGER_ID));
        }

        $this->client = $builder->build();
        return $this->client;
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

    // ── Campaigns ─────────────────────────────────────────────────────────

    /**
     * Create a Search campaign with a daily budget.
     * Returns ['campaign_resource' => '...', 'budget_resource' => '...']
     */
    public function createSearchCampaign(
        string $name,
        float  $dailyBudgetUsd,
        string $startDate = '',
        string $endDate   = '',
        bool   $startPaused = true
    ): array {
        $client = $this->getClient();

        // 1. Campaign Budget
        $budget = new CampaignBudget([
            'name'            => $name . ' Budget',
            'delivery_method' => BudgetDeliveryMethod::STANDARD,
            'amount_micros'   => (int)($dailyBudgetUsd * 1_000_000),
        ]);
        $budgetOp       = new CampaignBudgetOperation(['create' => $budget]);
        $budgetResponse = $client->getCampaignBudgetServiceClient()
            ->mutateCampaignBudgets($this->customerId, [$budgetOp]);
        $budgetResource = $budgetResponse->getResults()[0]->getResourceName();

        // 2. Campaign
        $campaign = new Campaign([
            'name'                      => $name,
            'advertising_channel_type'  => AdvertisingChannelType::SEARCH,
            'status'                    => $startPaused ? CampaignStatus::PAUSED : CampaignStatus::ENABLED,
            'manual_cpc'                => new ManualCpc(['enhanced_cpc_enabled' => false]),
            'campaign_budget'           => $budgetResource,
            'network_settings'          => new NetworkSettings([
                'target_google_search'  => true,
                'target_search_network' => true,
            ]),
        ]);
        if ($startDate) $campaign->setStartDate(str_replace('-', '', $startDate));
        if ($endDate)   $campaign->setEndDate(str_replace('-', '', $endDate));

        $campaignOp       = new CampaignOperation(['create' => $campaign]);
        $campaignResponse = $client->getCampaignServiceClient()
            ->mutateCampaigns($this->customerId, [$campaignOp]);
        $campaignResource = $campaignResponse->getResults()[0]->getResourceName();

        return [
            'campaign_resource' => $campaignResource,
            'budget_resource'   => $budgetResource,
        ];
    }

    /**
     * Create an ad group + Responsive Search Ad within an existing campaign.
     * $adCopy — row from crm_ad_copy (google_headlines, google_descriptions as JSON strings)
     * $finalUrl — landing page URL
     * Returns ['ad_group_resource' => '...', 'ad_resource' => '...']
     */
    public function createAdGroupWithRSA(
        string $campaignResource,
        array  $adCopy,
        string $finalUrl
    ): array {
        $client = $this->getClient();

        // Ad Group
        $adGroup = new AdGroup([
            'name'     => ($adCopy['headline'] ?? 'Ad Group') . ' — Group',
            'campaign' => $campaignResource,
            'status'   => AdGroupStatus::ENABLED,
            'type'     => AdGroupType::SEARCH_STANDARD,
        ]);
        $adGroupOp       = new AdGroupOperation(['create' => $adGroup]);
        $adGroupResponse = $client->getAdGroupServiceClient()
            ->mutateAdGroups($this->customerId, [$adGroupOp]);
        $adGroupResource = $adGroupResponse->getResults()[0]->getResourceName();

        // RSA headlines and descriptions (max 15 / 4, each truncated to limit)
        $rawHeadlines = json_decode($adCopy['google_headlines']    ?? '[]', true) ?: [];
        $rawDescs     = json_decode($adCopy['google_descriptions'] ?? '[]', true) ?: [];

        $headlines = array_map(
            fn($h) => new AdTextAsset(['text' => mb_substr($h, 0, 30)]),
            array_slice($rawHeadlines, 0, 15)
        );
        $descs = array_map(
            fn($d) => new AdTextAsset(['text' => mb_substr($d, 0, 90)]),
            array_slice($rawDescs, 0, 4)
        );

        if (empty($headlines)) {
            throw new RuntimeException('Ad copy must have at least one Google headline.');
        }
        if (empty($descs)) {
            throw new RuntimeException('Ad copy must have at least one Google description.');
        }

        $rsa = new ResponsiveSearchAdInfo([
            'headlines'    => $headlines,
            'descriptions' => $descs,
        ]);
        $ad = new Ad([
            'final_urls'           => [$finalUrl],
            'responsive_search_ad' => $rsa,
        ]);
        $adGroupAd = new AdGroupAd([
            'ad_group' => $adGroupResource,
            'status'   => AdGroupAdStatus::ENABLED,
            'ad'       => $ad,
        ]);
        $adGroupAdOp = new AdGroupAdOperation(['create' => $adGroupAd]);
        $adResponse  = $client->getAdGroupAdServiceClient()
            ->mutateAdGroupAds($this->customerId, [$adGroupAdOp]);
        $adResource  = $adResponse->getResults()[0]->getResourceName();

        return [
            'ad_group_resource' => $adGroupResource,
            'ad_resource'       => $adResource,
        ];
    }

    // ── Campaign Status Management ────────────────────────────────────────

    public function setCampaignStatus(string $campaignResource, string $status): void
    {
        $statusMap = [
            'active'  => CampaignStatus::ENABLED,
            'paused'  => CampaignStatus::PAUSED,
            'removed' => CampaignStatus::REMOVED,
        ];
        if (!isset($statusMap[$status])) {
            throw new InvalidArgumentException("Unknown status: {$status}");
        }

        $campaign = new Campaign([
            'resource_name' => $campaignResource,
            'status'        => $statusMap[$status],
        ]);
        $op = new CampaignOperation(['update' => $campaign]);
        $op->setUpdateMask(new \Google\Protobuf\FieldMask(['paths' => ['status']]));

        $this->getClient()->getCampaignServiceClient()
            ->mutateCampaigns($this->customerId, [$op]);
    }

    // ── Campaign Listing ──────────────────────────────────────────────────

    public function listCampaigns(): array
    {
        $gaql = 'SELECT campaign.id, campaign.name, campaign.status,
                        campaign.start_date, campaign.end_date,
                        campaign_budget.amount_micros
                   FROM campaign
                  WHERE campaign.status != "REMOVED"
                  ORDER BY campaign.name ASC';

        return $this->runQuery($gaql);
    }

    // ── Performance Reporting ─────────────────────────────────────────────

    /**
     * Get campaign performance for last $days days.
     * Returns an array of rows with campaign name, impressions, clicks, CTR, avg_cpc, cost_usd.
     */
    public function getCampaignPerformance(int $days = 30): array
    {
        $gaql = "SELECT campaign.id, campaign.name,
                        metrics.impressions, metrics.clicks,
                        metrics.ctr, metrics.average_cpc, metrics.cost_micros,
                        metrics.conversions
                   FROM campaign
                  WHERE campaign.status = 'ENABLED'
                    AND segments.date DURING LAST_{$days}_DAYS
                  ORDER BY metrics.impressions DESC
                  LIMIT 50";

        $rows = $this->runQuery($gaql);

        return array_map(function (array $row) {
            $row['cost_usd']  = round(($row['metrics']['cost_micros'] ?? 0) / 1_000_000, 2);
            $row['avg_cpc']   = round(($row['metrics']['average_cpc'] ?? 0) / 1_000_000, 2);
            $row['ctr_pct']   = round(($row['metrics']['ctr'] ?? 0) * 100, 2);
            return $row;
        }, $rows);
    }

    public function getAdPerformance(string $campaignResource, int $days = 30): array
    {
        $gaql = "SELECT ad_group_ad.ad.id, ad_group_ad.ad.responsive_search_ad.headlines,
                        metrics.impressions, metrics.clicks, metrics.ctr,
                        metrics.average_cpc, metrics.cost_micros
                   FROM ad_group_ad
                  WHERE campaign.resource_name = '{$campaignResource}'
                    AND ad_group_ad.status = 'ENABLED'
                    AND segments.date DURING LAST_{$days}_DAYS
                  LIMIT 25";

        return $this->runQuery($gaql);
    }

    // ── Helper ────────────────────────────────────────────────────────────

    private function runQuery(string $gaql): array
    {
        $results = [];
        $stream  = $this->getClient()->getGoogleAdsServiceClient()
            ->searchStream($this->customerId, $gaql);

        foreach ($stream->iterateAllElements() as $row) {
            $results[] = json_decode($row->serializeToJsonString(), true);
        }
        return $results;
    }

    /**
     * Build an OAuth2 authorization URL for the consent screen.
     * Scopes cover both Google Ads and Business Profile.
     */
    public static function buildAuthUrl(): string
    {
        if (!defined('GOOGLE_CLIENT_ID')) {
            require_once __DIR__ . '/../config/google.php';
        }
        $params = http_build_query([
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
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
    }

    /**
     * Exchange an authorization code for access + refresh tokens.
     */
    public static function exchangeCode(string $code): array
    {
        if (!defined('GOOGLE_CLIENT_ID')) {
            require_once __DIR__ . '/../config/google.php';
        }
        $response = (new \GuzzleHttp\Client())->post(
            'https://oauth2.googleapis.com/token',
            ['form_params' => [
                'code'          => $code,
                'client_id'     => GOOGLE_CLIENT_ID,
                'client_secret' => GOOGLE_CLIENT_SECRET,
                'redirect_uri'  => GOOGLE_REDIRECT_URI,
                'grant_type'    => 'authorization_code',
            ]]
        );
        return json_decode((string) $response->getBody(), true);
    }
}
