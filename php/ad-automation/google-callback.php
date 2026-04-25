<?php
/**
 * google-callback.php
 * OAuth2 redirect handler — Google sends the user here after they grant access.
 * Exchanges the authorization code for tokens and saves the refresh token.
 */
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/google.php';
requireRole(['admin', 'buyer']);

$error = $_GET['error'] ?? '';
if ($error) {
    flash('error', 'Google authorization denied: ' . $error);
    redirect('/ad-automation/google-settings.php');
}

$code = $_GET['code'] ?? '';
if (!$code) {
    flash('error', 'No authorization code received from Google.');
    redirect('/ad-automation/google-settings.php');
}

try {
    $tokenData = GoogleAdsService::exchangeCode($code);

    if (empty($tokenData['refresh_token'])) {
        flash('error', 'No refresh token returned. Make sure you prompted for consent (access_type=offline, prompt=consent).');
        redirect('/ad-automation/google-settings.php');
    }

    $tokens = [
        'refresh_token' => $tokenData['refresh_token'],
        'access_token'  => $tokenData['access_token']  ?? '',
        'expires_at'    => time() + (int)($tokenData['expires_in'] ?? 3600),
        'scope'         => $tokenData['scope'] ?? '',
        'authorized_at' => date('Y-m-d H:i:s'),
    ];

    // Try to fetch the Business Account ID and store it for insights calls
    try {
        $bizSvc = new GoogleBusinessService();
        // Temporarily inject tokens for the account lookup
        file_put_contents(GOOGLE_TOKENS_PATH, json_encode($tokens, JSON_PRETTY_PRINT));
        $accounts = $bizSvc->getAccounts();
        if (!empty($accounts[0]['name'])) {
            $accountId = str_replace('accounts/', '', $accounts[0]['name']);
            $tokens['business_account_id'] = $accountId;
            $tokens['business_account_name'] = $accounts[0]['accountName'] ?? '';
        }
    } catch (Throwable $e) {
        // Non-fatal — tokens are still valid for Ads
    }

    GoogleAdsService::saveTokens($tokens);

    flash('success', 'Google account connected successfully!');
    redirect('/ad-automation/google-settings.php');

} catch (Throwable $e) {
    flash('error', 'Failed to exchange authorization code: ' . $e->getMessage());
    redirect('/ad-automation/google-settings.php');
}
