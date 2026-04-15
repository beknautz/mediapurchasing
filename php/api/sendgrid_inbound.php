<?php
/**
 * api/sendgrid_inbound.php
 * SendGrid Inbound Parse webhook endpoint.
 *
 * No authentication required — SendGrid POST's parsed inbound emails here.
 * See: https://docs.sendgrid.com/for-developers/parsing-email/setting-up-the-inbound-parse-webhook
 *
 * Accepts POST, calls EmailService->processInbound(), returns HTTP 200.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

try {
    $emailService = new EmailService();
    $emailService->processInbound($_POST);
} catch (Throwable $e) {
    // Log silently — always return 200 to SendGrid to avoid retries
    error_log('[SendGrid Inbound] Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
}

// SendGrid expects a 200 response to acknowledge receipt
http_response_code(200);
exit;
