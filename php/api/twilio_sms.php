<?php
/**
 * api/twilio_sms.php
 * Twilio SMS webhook endpoint.
 *
 * No authentication required — Twilio POST's inbound SMS data here.
 * See: https://www.twilio.com/docs/messaging/guides/webhook-request
 *
 * Accepts POST, calls SMSService->processInbound(), returns HTTP 200
 * with TwiML response body (empty Response).
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: text/xml; charset=UTF-8');
    echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
    exit;
}

try {
    $smsService = new SMSService();
    $smsService->processInbound($_POST);
} catch (Throwable $e) {
    // Log silently — always return 200 with valid TwiML to avoid Twilio retries
    error_log('[Twilio SMS] Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
}

// Twilio expects HTTP 200 with TwiML content-type
http_response_code(200);
header('Content-Type: text/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
exit;
