<?php
/**
 * src/EmailService.php
 * Media Buying Platform — email send/receive service via SendGrid
 */

class EmailService extends BaseService
{
    // -----------------------------------------------------------------------
    // send()
    // Sends an email via the SendGrid v3 Mail Send API using cURL.
    //
    // Returns: ['success'=>bool, 'message'=>string, 'logId'=>int]
    // -----------------------------------------------------------------------
    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $bodyHtml,
        string $bodyText     = '',
        string $fromEmail    = '',
        string $fromName     = '',
        int    $mediaBuyId   = 0,
        int    $approvalId   = 0,
        int    $billId       = 0
    ): array {
        $apiKey    = $this->getSetting('sendgrid_api_key', '');
        $fromEmail = $fromEmail !== '' ? $fromEmail : $this->getSetting('sendgrid_from_email', 'noreply@example.com');
        $fromName  = $fromName  !== '' ? $fromName  : $this->getSetting('sendgrid_from_name',  'Media Buying Platform');
        $bodyText  = $bodyText  !== '' ? $bodyText  : $this->stripTags($bodyHtml);

        $payload = [
            'personalizations' => [
                [
                    'to'      => [['email' => $toEmail, 'name' => $toName]],
                    'subject' => $subject,
                ],
            ],
            'from'    => ['email' => $fromEmail, 'name' => $fromName],
            'content' => [
                ['type' => 'text/plain', 'value' => $bodyText],
                ['type' => 'text/html',  'value' => $bodyHtml],
            ],
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response   = curl_exec($ch);
        $httpCode   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError  = curl_error($ch);
        curl_close($ch);

        $success = ($httpCode >= 200 && $httpCode < 300);
        $message = $success ? 'Email sent successfully.' : "SendGrid error (HTTP {$httpCode}): {$response}";

        if ($curlError !== '') {
            $success = false;
            $message = "cURL error: {$curlError}";
        }

        $logId = $this->logCommunication(
            'email',
            $toEmail,
            $toName,
            $fromEmail,
            $fromName,
            $subject,
            $bodyHtml,
            $bodyText,
            $success ? 'sent' : 'failed',
            $message,
            $mediaBuyId,
            $approvalId,
            $billId
        );

        return ['success' => $success, 'message' => $message, 'logId' => $logId];
    }

    // -----------------------------------------------------------------------
    // sendTemplate()
    // Looks up a named template from the DB, merges {{var}} placeholders,
    // and calls send().
    //
    // Returns: ['success'=>bool, 'message'=>string, 'logId'=>int]
    // -----------------------------------------------------------------------
    public function sendTemplate(
        string $slug,
        string $toEmail,
        string $toName,
        array  $vars,
        int    $mediaBuyId = 0,
        int    $approvalId = 0,
        int    $billId     = 0
    ): array {
        $stmt = $this->db->prepare(
            'SELECT * FROM email_templates WHERE slug = :slug AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([':slug' => $slug]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$template) {
            return ['success' => false, 'message' => "Email template '{$slug}' not found.", 'logId' => 0];
        }

        $subject  = $this->mergeVars($template['subject'],   $vars);
        $bodyHtml = $this->mergeVars($template['body_html'], $vars);
        $bodyText = $this->mergeVars($template['body_text'] ?? '', $vars);

        return $this->send(
            $toEmail,
            $toName,
            $subject,
            $bodyHtml,
            $bodyText,
            $template['from_email'] ?? '',
            $template['from_name']  ?? '',
            $mediaBuyId,
            $approvalId,
            $billId
        );
    }

    // -----------------------------------------------------------------------
    // processInbound()
    // Handles SendGrid Inbound Parse webhook POST data. Logs the message and,
    // when invoice-related keywords are detected, auto-creates a billing record.
    //
    // Returns: ['success'=>bool, 'action'=>string, 'billId'=>int]
    // -----------------------------------------------------------------------
    public function processInbound(array $postData): array
    {
        $from     = $postData['from']    ?? '';
        $to       = $postData['to']      ?? '';
        $subject  = $postData['subject'] ?? '';
        $bodyText = $postData['text']    ?? '';
        $bodyHtml = $postData['html']    ?? '';

        // Log the inbound communication
        $logId = $this->logCommunication(
            'email_inbound',
            $to,
            '',
            $from,
            '',
            $subject,
            $bodyHtml,
            $bodyText,
            'received',
            '',
            0,
            0,
            0
        );

        // Check for invoice keywords
        $invoiceKeywords = ['invoice', 'bill', 'payment due', 'amount due', 'remittance'];
        $combined        = strtolower($subject . ' ' . $bodyText);
        $isInvoice       = false;

        foreach ($invoiceKeywords as $keyword) {
            if (str_contains($combined, $keyword)) {
                $isInvoice = true;
                break;
            }
        }

        if (!$isInvoice) {
            return ['success' => true, 'action' => 'logged', 'billId' => 0];
        }

        // Auto-create a billing record in the queue
        $vendorEmail = $from;

        // Try to find matching vendor
        $vendorStmt = $this->db->prepare(
            'SELECT id, company_name FROM vendors WHERE email = :email LIMIT 1'
        );
        $vendorStmt->execute([':email' => $vendorEmail]);
        $vendor = $vendorStmt->fetch(PDO::FETCH_ASSOC);

        $billStmt = $this->db->prepare(
            'INSERT INTO billing_queue
                 (vendor_id, vendor_email, notes,
                  status, source, created_at, updated_at)
             VALUES
                 (:vendor_id, :vendor_email, :notes,
                  "pending", "email_inbound", NOW(), NOW())'
        );
        $billStmt->execute([
            ':vendor_id'    => $vendor['id'] ?? null,
            ':vendor_email' => $vendorEmail,
            ':notes'        => '[Inbound email] Subject: ' . $subject,
        ]);

        $billId = $this->lastInsertId();

        $this->auditLog('inbound_invoice_detected', 'billing', $billId, "From: {$from} | Subject: {$subject}");

        return ['success' => true, 'action' => 'bill_created', 'billId' => $billId];
    }

    // -----------------------------------------------------------------------
    // getHistory()
    // Returns paginated communication log entries.
    //
    // $type      — 'email' | 'sms' | '' (all)
    // $direction — 'inbound' | 'outbound' | '' (all)
    // $mediaBuyId, $approvalId, $billId — entity ID filters (0 = no filter)
    // $page, $pageSize — pagination
    //
    // Returns: ['data'=>[], 'total'=>int, 'page'=>int, 'pages'=>int]
    // Each row includes virtual 'type' and 'direction' columns.
    // -----------------------------------------------------------------------
    public function getHistory(
        string $type       = '',
        string $direction  = '',
        int    $mediaBuyId = 0,
        int    $approvalId = 0,
        int    $billId     = 0,
        int    $page       = 1,
        int    $pageSize   = PAGE_SIZE
    ): array {
        $sql = 'SELECT *,
                       CASE WHEN comm_type = \'email_inbound\' THEN \'email\' ELSE comm_type END AS type,
                       CASE WHEN comm_type = \'email_inbound\' THEN \'inbound\' ELSE \'outbound\' END AS direction
                  FROM communication_logs
                 WHERE 1=1';
        $params = [];

        if ($type === 'email') {
            $sql .= ' AND comm_type IN ("email", "email_inbound")';
        } elseif ($type === 'sms') {
            $sql .= ' AND comm_type = "sms"';
        }

        if ($direction === 'inbound') {
            $sql .= ' AND comm_type = "email_inbound"';
        } elseif ($direction === 'outbound') {
            $sql .= ' AND comm_type NOT IN ("email_inbound")';
        }

        if ($mediaBuyId > 0) {
            $sql                     .= ' AND media_buy_id = :media_buy_id';
            $params[':media_buy_id'] = $mediaBuyId;
        }
        if ($approvalId > 0) {
            $sql                     .= ' AND approval_id = :approval_id';
            $params[':approval_id']  = $approvalId;
        }
        if ($billId > 0) {
            $sql                 .= ' AND bill_id = :bill_id';
            $params[':bill_id']  = $billId;
        }

        $sql .= ' ORDER BY created_at DESC';

        return $this->paginate($sql, $params, $page, $pageSize);
    }

    // -----------------------------------------------------------------------
    // sendAdHoc()
    // Sends a one-off email from a data array (e.g. from a web form).
    //
    // $data keys: to_email, to_name, subject, body_html, body_text,
    //             from_email, from_name, media_buy_id, approval_id, bill_id
    //
    // Returns: ['success'=>bool, 'message'=>string, 'logId'=>int]
    // -----------------------------------------------------------------------
    public function sendAdHoc(array $data): array
    {
        return $this->send(
            $data['to_email']    ?? '',
            $data['to_name']     ?? '',
            $data['subject']     ?? '',
            $data['body_html']   ?? '',
            $data['body_text']   ?? '',
            $data['from_email']  ?? '',
            $data['from_name']   ?? '',
            (int) ($data['media_buy_id'] ?? 0),
            (int) ($data['approval_id']  ?? 0),
            (int) ($data['bill_id']      ?? 0)
        );
    }

    // -----------------------------------------------------------------------
    // mergeVars()  [private]
    // Replaces {{key}} placeholders in $template with values from $vars.
    // -----------------------------------------------------------------------
    private function mergeVars(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
        }
        return $template;
    }

    // -----------------------------------------------------------------------
    // stripTags()  [private]
    // Converts HTML to a plain-text approximation for the text/plain part.
    // -----------------------------------------------------------------------
    private function stripTags(string $html): string
    {
        // Preserve line breaks from block elements
        $html = preg_replace('/<(br\s*\/?|\/p|\/div|\/tr|\/h[1-6])>/i', "\n", $html);
        // Strip all remaining tags
        $text = strip_tags($html);
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Normalise whitespace — collapse multiple blank lines to at most two
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    // -----------------------------------------------------------------------
    // logCommunication()  [private]
    // Inserts a row into the communication_logs table.
    //
    // Returns: int — the new log row id
    // -----------------------------------------------------------------------
    private function logCommunication(
        string $commType,
        string $toEmail,
        string $toName,
        string $fromEmail,
        string $fromName,
        string $subject,
        string $bodyHtml,
        string $bodyText,
        string $status,
        string $errorMessage,
        int    $mediaBuyId,
        int    $approvalId,
        int    $billId
    ): int {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);

        $stmt = $this->db->prepare(
            'INSERT INTO communication_logs
                 (comm_type, to_email, to_name, from_email, from_name, subject,
                  body_html, body_text, status, error_message,
                  media_buy_id, approval_id, bill_id, sent_by, created_at)
             VALUES
                 (:comm_type, :to_email, :to_name, :from_email, :from_name, :subject,
                  :body_html, :body_text, :status, :error_message,
                  :media_buy_id, :approval_id, :bill_id, :sent_by, NOW())'
        );
        $stmt->execute([
            ':comm_type'     => $commType,
            ':to_email'      => $toEmail,
            ':to_name'       => $toName,
            ':from_email'    => $fromEmail,
            ':from_name'     => $fromName,
            ':subject'       => $subject,
            ':body_html'     => $bodyHtml,
            ':body_text'     => $bodyText,
            ':status'        => $status,
            ':error_message' => $errorMessage,
            ':media_buy_id'  => $mediaBuyId  > 0 ? $mediaBuyId  : null,
            ':approval_id'   => $approvalId  > 0 ? $approvalId  : null,
            ':bill_id'       => $billId      > 0 ? $billId      : null,
            ':sent_by'       => $userId      > 0 ? $userId      : null,
        ]);

        return $this->lastInsertId();
    }
}
