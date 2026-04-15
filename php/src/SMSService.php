<?php
/**
 * src/SMSService.php
 * Media Buying Platform — SMS send/receive service via Twilio REST API
 */

class SMSService extends BaseService
{
    // -----------------------------------------------------------------------
    // send()
    // Posts an outbound SMS message to the Twilio REST API using HTTP Basic
    // authentication (Account SID : Auth Token).
    //
    // Returns: ['success'=>bool, 'message'=>string, 'sid'=>string]
    // -----------------------------------------------------------------------
    public function send(
        string $toNumber,
        string $body,
        int    $mediaBuyId = 0,
        int    $approvalId = 0
    ): array {
        $accountSid = $this->getSetting('twilio_account_sid', '');
        $authToken  = $this->getSetting('twilio_auth_token',  '');
        $fromNumber = $this->getSetting('twilio_from_number', '');

        if ($accountSid === '' || $authToken === '' || $fromNumber === '') {
            $this->logSMS($toNumber, $fromNumber, $body, 'failed', 'Twilio credentials not configured.', '', $mediaBuyId, $approvalId);
            return ['success' => false, 'message' => 'Twilio credentials not configured.', 'sid' => ''];
        }

        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($accountSid) . '/Messages.json';

        $postFields = http_build_query([
            'To'   => $toNumber,
            'From' => $fromNumber,
            'Body' => $body,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_USERPWD        => $accountSid . ':' . $authToken,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $sid     = '';
        $success = false;
        $message = '';

        if ($curlError !== '') {
            $message = "cURL error: {$curlError}";
        } else {
            $decoded = json_decode($response, true);
            if ($httpCode >= 200 && $httpCode < 300) {
                $success = true;
                $sid     = $decoded['sid'] ?? '';
                $message = 'SMS sent successfully.';
            } else {
                $twilioMsg = $decoded['message'] ?? $response;
                $message   = "Twilio error (HTTP {$httpCode}): {$twilioMsg}";
            }
        }

        $this->logSMS($toNumber, $fromNumber, $body, $success ? 'sent' : 'failed', $message, $sid, $mediaBuyId, $approvalId);

        return ['success' => $success, 'message' => $message, 'sid' => $sid];
    }

    // -----------------------------------------------------------------------
    // sendApprovalNotification()
    // Sends a pre-formatted approval notification SMS.
    //
    // Returns: ['success'=>bool, 'message'=>string, 'sid'=>string]
    // -----------------------------------------------------------------------
    public function sendApprovalNotification(
        string $toNumber,
        string $clientName,
        string $buyTitle,
        string $approvalLink
    ): array {
        $appName = APP_NAME;
        $body    = "{$appName}: Hi {$clientName}, your approval is needed for \"{$buyTitle}\". "
                 . "Please review and respond here: {$approvalLink}";

        // Twilio SMS limit is 1600 chars per message segment; keep it tidy
        if (strlen($body) > 320) {
            $body = "{$appName}: Approval needed for \"{$buyTitle}\". Review: {$approvalLink}";
        }

        return $this->send($toNumber, $body, 0, 0);
    }

    // -----------------------------------------------------------------------
    // processInbound()
    // Handles Twilio inbound SMS webhook POST data.
    // Logs the message and could be extended to trigger workflow actions.
    // -----------------------------------------------------------------------
    public function processInbound(array $postData): void
    {
        $from = $postData['From'] ?? '';
        $to   = $postData['To']   ?? '';
        $body = $postData['Body'] ?? '';
        $sid  = $postData['MessageSid'] ?? '';

        $this->logSMS($from, $to, $body, 'received', '', $sid, 0, 0);

        // Look for approval response keywords and auto-update approvals
        $bodyLower = strtolower(trim($body));

        if (in_array($bodyLower, ['approve', 'yes', 'approved'], true)) {
            $this->handleInboundApprovalResponse($from, 'approved');
        } elseif (in_array($bodyLower, ['reject', 'no', 'rejected', 'decline'], true)) {
            $this->handleInboundApprovalResponse($from, 'rejected');
        }
    }

    // -----------------------------------------------------------------------
    // handleInboundApprovalResponse()  [private]
    // Searches for a pending approval linked to the sender's phone number and
    // updates its status based on the inbound keyword.
    // -----------------------------------------------------------------------
    private function handleInboundApprovalResponse(string $fromNumber, string $response): void
    {
        // Find the most recent pending approval for a client with this phone number
        $stmt = $this->db->prepare(
            "SELECT a.id, a.token, a.media_buy_id
               FROM media_buy_approvals a
               JOIN clients c ON c.id = a.client_id
              WHERE c.phone    = :phone
                AND a.status   = 'pending'
                AND a.expires_at > NOW()
              ORDER BY a.created_at DESC
              LIMIT 1"
        );
        $stmt->execute([':phone' => $fromNumber]);
        $approval = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$approval) {
            return;
        }

        $this->db->prepare(
            'UPDATE media_buy_approvals
                SET status       = :status,
                    responded_at = NOW(),
                    response_notes = "Responded via SMS",
                    updated_at   = NOW()
              WHERE id = :id'
        )->execute([':status' => $response, ':id' => $approval['id']]);

        $this->db->prepare(
            "UPDATE media_buys SET status = :status, updated_at = NOW() WHERE id = :id"
        )->execute([':status' => $response, ':id' => $approval['media_buy_id']]);

        $this->auditLog(
            'sms_approval_response',
            'media_buy',
            (int) $approval['media_buy_id'],
            "Approval #{$approval['id']} — SMS response: {$response} from {$fromNumber}"
        );
    }

    // -----------------------------------------------------------------------
    // logSMS()  [private]
    // Inserts a row into communication_logs for an SMS message.
    // -----------------------------------------------------------------------
    private function logSMS(
        string $toNumber,
        string $fromNumber,
        string $body,
        string $status,
        string $errorMessage,
        string $sid,
        int    $mediaBuyId,
        int    $approvalId
    ): void {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);

        $stmt = $this->db->prepare(
            'INSERT INTO communication_logs
                 (comm_type, to_email, from_email, subject, body_text, status,
                  error_message, external_id, media_buy_id, approval_id, sent_by, created_at)
             VALUES
                 (:comm_type, :to_number, :from_number, :subject, :body, :status,
                  :error_message, :sid, :media_buy_id, :approval_id, :sent_by, NOW())'
        );
        $stmt->execute([
            ':comm_type'     => 'sms',
            ':to_number'     => $toNumber,
            ':from_number'   => $fromNumber,
            ':subject'       => 'SMS',
            ':body'          => $body,
            ':status'        => $status,
            ':error_message' => $errorMessage,
            ':sid'           => $sid,
            ':media_buy_id'  => $mediaBuyId  > 0 ? $mediaBuyId  : null,
            ':approval_id'   => $approvalId  > 0 ? $approvalId  : null,
            ':sent_by'       => $userId       > 0 ? $userId       : null,
        ]);
    }
}
