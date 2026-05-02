<?php
/**
 * src/GoogleOAuthService.php
 * Generates short-lived OAuth2 Bearer tokens from a GCP service account JSON key.
 *
 * Flow: Service Account JSON → sign JWT → exchange for access_token (1 hour TTL)
 * Tokens are cached to a temp file so we don't hit the token endpoint on every request.
 *
 * Usage:
 *   $oauth = new GoogleOAuthService(VEO_SERVICE_ACCOUNT_JSON_PATH);
 *   $token = $oauth->getAccessToken();  // 'ya29.xxx...'
 */

class GoogleOAuthService
{
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const SCOPE          = 'https://www.googleapis.com/auth/cloud-platform';

    private string $credentialsPath;
    private string $cacheFile;

    public function __construct(string $credentialsPath, string $cacheFile = '')
    {
        if (!file_exists($credentialsPath)) {
            throw new RuntimeException(
                'Service account JSON not found at: ' . $credentialsPath
            );
        }
        $this->credentialsPath = $credentialsPath;
        $this->cacheFile       = $cacheFile ?: sys_get_temp_dir() . '/gcp_veo_token.json';
    }

    // -----------------------------------------------------------------------
    // getAccessToken()
    // Returns a valid Bearer token, refreshing from GCP if expired.
    // -----------------------------------------------------------------------
    public function getAccessToken(): string
    {
        // Return cached token if still valid (60s safety buffer)
        if (file_exists($this->cacheFile)) {
            $cache = json_decode(file_get_contents($this->cacheFile), true) ?? [];
            if (!empty($cache['token']) && (int)($cache['expires_at'] ?? 0) > (time() + 60)) {
                return $cache['token'];
            }
        }

        $creds = json_decode(file_get_contents($this->credentialsPath), true);
        if (empty($creds['client_email']) || empty($creds['private_key'])) {
            throw new RuntimeException('Service account JSON is missing client_email or private_key.');
        }

        $now = time();
        $jwt = $this->buildJwt([
            'iss'   => $creds['client_email'],
            'scope' => self::SCOPE,
            'aud'   => self::TOKEN_ENDPOINT,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ], $creds['private_key']);

        $ch = curl_init(self::TOKEN_ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT    => 30,
        ]);
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err || $code !== 200) {
            throw new RuntimeException(
                'Failed to obtain GCP access token (HTTP ' . $code . '): ' . substr($raw, 0, 300)
            );
        }

        $data  = json_decode($raw, true) ?? [];
        $token = $data['access_token'] ?? '';
        if ($token === '') {
            throw new RuntimeException('GCP token response contained no access_token: ' . substr($raw, 0, 200));
        }

        // Cache for reuse across requests
        file_put_contents($this->cacheFile, json_encode([
            'token'      => $token,
            'expires_at' => $now + 3600,
        ]));

        return $token;
    }

    // -----------------------------------------------------------------------
    // clearCache() — call this if you get a 401 to force token refresh
    // -----------------------------------------------------------------------
    public function clearCache(): void
    {
        if (file_exists($this->cacheFile)) {
            @unlink($this->cacheFile);
        }
    }

    // -----------------------------------------------------------------------
    // buildJwt() — builds and signs a JWT for the service account
    // -----------------------------------------------------------------------
    private function buildJwt(array $payload, string $privateKey): string
    {
        $header  = $this->base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $body    = $this->base64url(json_encode($payload));
        $toSign  = $header . '.' . $body;

        if (!openssl_sign($toSign, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Failed to sign JWT for service account: ' . openssl_error_string());
        }

        return $toSign . '.' . $this->base64url($signature);
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
