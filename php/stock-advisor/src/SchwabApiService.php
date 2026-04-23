<?php
/**
 * src/SchwabApiService.php
 * Schwab Individual Developer API — OAuth 2.0 + market data calls.
 *
 * OAuth flow (Authorization Code):
 *   1. getAuthorizationUrl()  → redirect user to Schwab login
 *   2. handleCallback($code)  → exchange code for tokens, persist to DB
 *   3. getValidAccessToken()  → returns current token, refreshing if needed
 *
 * API calls all go through request(), which automatically attaches a
 * Bearer token and retries once after a 401 (token refresh).
 */
class SchwabApiService extends BaseService
{
    // -----------------------------------------------------------------------
    // OAuth helpers
    // -----------------------------------------------------------------------

    /**
     * Build the URL to redirect the user to Schwab's authorization page.
     * Stores a random state token in the session for CSRF protection.
     */
    public function getAuthorizationUrl(): string
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION['schwab_oauth_state'] = $state;

        return SCHWAB_OAUTH_BASE . '/authorize?' . http_build_query([
            'client_id'     => SCHWAB_CLIENT_ID,
            'redirect_uri'  => SCHWAB_REDIRECT_URI,
            'response_type' => 'code',
            'scope'         => 'readonly',
            'state'         => $state,
        ]);
    }

    /**
     * Exchange the authorization code returned by Schwab for tokens.
     * Validates the state param, stores tokens in schwab_tokens.
     *
     * @throws RuntimeException on state mismatch or API error
     */
    public function handleCallback(string $code, string $state): void
    {
        $expectedState = $_SESSION['schwab_oauth_state'] ?? '';
        unset($_SESSION['schwab_oauth_state']);

        if (!hash_equals($expectedState, $state)) {
            throw new RuntimeException('OAuth state mismatch — possible CSRF attack.');
        }

        $response = $this->tokenRequest([
            'grant_type'   => 'authorization_code',
            'code'         => $code,
            'redirect_uri' => SCHWAB_REDIRECT_URI,
        ]);

        $this->persistTokens($response);
    }

    /**
     * Returns a valid access token, refreshing via the refresh_token if expired.
     *
     * @throws RuntimeException when no tokens exist or refresh fails
     */
    public function getValidAccessToken(): string
    {
        $row = $this->loadTokenRow();

        if ($row === null) {
            throw new RuntimeException('No Schwab tokens found. Complete OAuth setup first.');
        }

        // Refresh 60 seconds early to avoid clock-skew edge cases
        if (strtotime($row['expires_at']) - 60 <= time()) {
            $response = $this->tokenRequest([
                'grant_type'    => 'refresh_token',
                'refresh_token' => $row['refresh_token'],
            ]);
            $this->persistTokens($response, (int) $row['id']);
            $row = $this->loadTokenRow();
        }

        return $row['access_token'];
    }

    /** True if we have stored tokens (does not check expiry). */
    public function hasTokens(): bool
    {
        return $this->loadTokenRow() !== null;
    }

    // -----------------------------------------------------------------------
    // Market Data API calls
    // -----------------------------------------------------------------------

    /**
     * Fetch daily price candles for a symbol.
     *
     * Returns an array of candles, each:
     *   ['date'=>'YYYY-MM-DD', 'open'=>float, 'high'=>float,
     *    'low'=>float, 'close'=>float, 'volume'=>int]
     *
     * @param int $days Number of calendar days of history to pull (default 90)
     */
    public function getPriceHistory(string $symbol, int $days = 90): array
    {
        $endMs   = time() * 1000;
        $startMs = ($endMs - ($days * 86400 * 1000));

        $data = $this->request('GET', '/pricehistory', [
            'symbol'        => strtoupper($symbol),
            'periodType'    => 'month',
            'frequencyType' => 'daily',
            'frequency'     => 1,
            'startDate'     => $startMs,
            'endDate'       => $endMs,
            'needExtendedHoursData' => 'false',
        ]);

        if (empty($data['candles'])) {
            return [];
        }

        $candles = [];
        foreach ($data['candles'] as $c) {
            $candles[] = [
                'date'   => date('Y-m-d', intdiv((int) $c['datetime'], 1000)),
                'open'   => (float) $c['open'],
                'high'   => (float) $c['high'],
                'low'    => (float) $c['low'],
                'close'  => (float) $c['close'],
                'volume' => (int)   $c['volume'],
            ];
        }

        return $candles;
    }

    /**
     * Fetch the real-time (or delayed) quote for one or more symbols.
     *
     * @param string|string[] $symbols  Ticker(s) to quote
     * @return array  Keyed by uppercase symbol, value is the quote array from Schwab
     */
    public function getQuotes(array|string $symbols): array
    {
        $symbolList = is_array($symbols)
            ? implode(',', array_map('strtoupper', $symbols))
            : strtoupper($symbols);

        $data = $this->request('GET', '/quotes', [
            'symbols'   => $symbolList,
            'fields'    => 'quote',
            'indicative'=> 'false',
        ]);

        return $data ?? [];
    }

    // -----------------------------------------------------------------------
    // Internal HTTP helpers
    // -----------------------------------------------------------------------

    /**
     * Generic authenticated request against SCHWAB_API_BASE.
     * Retries once on 401 (token refreshed then retried).
     */
    private function request(string $method, string $path, array $params = []): array
    {
        $token = $this->getValidAccessToken();
        $result = $this->httpRequest($method, SCHWAB_API_BASE . $path, $params, $token);

        // On 401 try a single refresh + retry
        if ($result['status'] === 401) {
            $this->forceRefresh();
            $token  = $this->getValidAccessToken();
            $result = $this->httpRequest($method, SCHWAB_API_BASE . $path, $params, $token);
        }

        if ($result['status'] >= 400) {
            throw new RuntimeException(
                "Schwab API error {$result['status']} on {$method} {$path}: " .
                substr($result['body'], 0, 300)
            );
        }

        return json_decode($result['body'], true) ?? [];
    }

    /**
     * POST to the token endpoint using HTTP Basic auth (client_id:client_secret).
     */
    private function tokenRequest(array $fields): array
    {
        $credentials = base64_encode(SCHWAB_CLIENT_ID . ':' . SCHWAB_CLIENT_SECRET);

        $result = $this->httpRequest('POST', SCHWAB_OAUTH_BASE . '/token', $fields, null, [
            'Authorization: Basic ' . $credentials,
            'Content-Type: application/x-www-form-urlencoded',
        ]);

        if ($result['status'] >= 400) {
            throw new RuntimeException(
                'Schwab token request failed (' . $result['status'] . '): ' .
                substr($result['body'], 0, 300)
            );
        }

        $json = json_decode($result['body'], true);

        if (empty($json['access_token'])) {
            throw new RuntimeException('Schwab returned no access_token: ' . $result['body']);
        }

        return $json;
    }

    /**
     * Low-level cURL wrapper.
     * Returns ['status'=>int, 'body'=>string].
     */
    private function httpRequest(
        string  $method,
        string  $url,
        array   $params  = [],
        ?string $token   = null,
        array   $headers = []
    ): array {
        $ch = curl_init();

        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
            $headers[] = 'Accept: application/json';
        }

        if (strtoupper($method) === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        $body   = (string) curl_exec($ch);
        $status = (int)    curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('cURL error: ' . $err);
        }

        curl_close($ch);

        return ['status' => $status, 'body' => $body];
    }

    // -----------------------------------------------------------------------
    // Token persistence helpers
    // -----------------------------------------------------------------------

    private function loadTokenRow(): ?array
    {
        $stmt = $this->db->query('SELECT * FROM schwab_tokens ORDER BY id DESC LIMIT 1');
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function persistTokens(array $json, int $existingId = 0): void
    {
        $expiresAt = date('Y-m-d H:i:s', time() + (int) ($json['expires_in'] ?? 1800));

        if ($existingId > 0) {
            $stmt = $this->db->prepare(
                'UPDATE schwab_tokens
                    SET access_token  = :at,
                        refresh_token = :rt,
                        token_type    = :tt,
                        expires_at    = :ea,
                        scope         = :sc
                  WHERE id = :id'
            );
            $stmt->execute([
                ':at' => $json['access_token'],
                ':rt' => $json['refresh_token'] ?? '',
                ':tt' => $json['token_type']    ?? 'Bearer',
                ':ea' => $expiresAt,
                ':sc' => $json['scope']         ?? '',
                ':id' => $existingId,
            ]);
        } else {
            // Upsert: truncate to one row then insert
            $this->db->exec('TRUNCATE TABLE schwab_tokens');
            $stmt = $this->db->prepare(
                'INSERT INTO schwab_tokens
                     (access_token, refresh_token, token_type, expires_at, scope)
                 VALUES
                     (:at, :rt, :tt, :ea, :sc)'
            );
            $stmt->execute([
                ':at' => $json['access_token'],
                ':rt' => $json['refresh_token'] ?? '',
                ':tt' => $json['token_type']    ?? 'Bearer',
                ':ea' => $expiresAt,
                ':sc' => $json['scope']         ?? '',
            ]);
        }
    }

    /** Force a refresh regardless of expiry (called after 401). */
    private function forceRefresh(): void
    {
        $row = $this->loadTokenRow();
        if ($row === null) {
            throw new RuntimeException('No tokens to refresh.');
        }
        $response = $this->tokenRequest([
            'grant_type'    => 'refresh_token',
            'refresh_token' => $row['refresh_token'],
        ]);
        $this->persistTokens($response, (int) $row['id']);
    }
}
