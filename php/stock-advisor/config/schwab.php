<?php
/**
 * stock-advisor/config/schwab.php
 * Schwab Individual Developer API credentials.
 * Register your app at https://developer.schwab.com and set these values
 * via environment variables or override the defaults below.
 */
defined('SCHWAB_CLIENT_ID')     || define('SCHWAB_CLIENT_ID',     $_ENV['SCHWAB_CLIENT_ID']     ?? '');
defined('SCHWAB_CLIENT_SECRET') || define('SCHWAB_CLIENT_SECRET', $_ENV['SCHWAB_CLIENT_SECRET'] ?? '');
defined('SCHWAB_REDIRECT_URI')  || define('SCHWAB_REDIRECT_URI',  $_ENV['SCHWAB_REDIRECT_URI']  ?? 'https://yourdomain.com/stocks/callback.php');
defined('SCHWAB_OAUTH_BASE')    || define('SCHWAB_OAUTH_BASE',    'https://api.schwabapi.com/v1/oauth');
defined('SCHWAB_API_BASE')      || define('SCHWAB_API_BASE',      'https://api.schwabapi.com/marketdata/v1');
