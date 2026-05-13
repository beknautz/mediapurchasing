<?php
/**
 * src/CampaignService.php
 * Media Buying Platform — campaign management service
 *
 * A campaign groups multiple media channels (TV-Spanish, Radio-English, etc.)
 * under a single client project.  Each channel maps to one vendor and gets
 * an individual RFP email.  Vendor replies are linked back via the
 * reply+cc{channelId} tagged reply-to address.
 */

class CampaignService extends BaseService
{
    const STATUSES = [
        'draft'           => ['label' => 'Draft',           'class' => 'secondary'],
        'rfp_sent'        => ['label' => 'RFP Sent',        'class' => 'info text-dark'],
        'responses_in'    => ['label' => 'Responses In',    'class' => 'primary'],
        'proposal_ready'  => ['label' => 'Proposal Ready',  'class' => 'warning text-dark'],
        'sent_to_client'  => ['label' => 'Sent to Client',  'class' => 'warning text-dark'],
        'approved'        => ['label' => 'Approved',        'class' => 'success'],
        'active'          => ['label' => 'Active',          'class' => 'success'],
        'completed'       => ['label' => 'Completed',       'class' => 'dark'],
        'cancelled'       => ['label' => 'Cancelled',       'class' => 'danger'],
    ];

    const CHANNEL_STATUSES = [
        'pending'           => ['label' => 'Pending',           'class' => 'secondary'],
        'rfp_sent'          => ['label' => 'RFP Sent',          'class' => 'info text-dark'],
        'response_received' => ['label' => 'Response Received', 'class' => 'success'],
        'no_response'       => ['label' => 'No Response',       'class' => 'warning text-dark'],
        'approved'          => ['label' => 'Approved',          'class' => 'success'],
        'rejected'          => ['label' => 'Rejected',          'class' => 'danger'],
    ];

    // -----------------------------------------------------------------------
    // getCampaigns()
    // Returns paginated campaign list with basic counts.
    // -----------------------------------------------------------------------
    public function getCampaigns(
        int    $clientId = 0,
        string $status   = '',
        int    $page     = 1,
        int    $pageSize = 25
    ): array {
        $sql = 'SELECT c.*,
                       cl.company_name AS client_name,
                       u.name          AS created_by_name,
                       (SELECT COUNT(*) FROM campaign_channels cc WHERE cc.campaign_id = c.id)                                  AS channel_count,
                       (SELECT COUNT(*) FROM campaign_channels cc WHERE cc.campaign_id = c.id AND cc.status = "rfp_sent")       AS rfp_sent_count,
                       (SELECT COUNT(*) FROM campaign_channels cc WHERE cc.campaign_id = c.id AND cc.status = "response_received") AS responses_count
                  FROM campaigns c
             LEFT JOIN clients cl ON cl.id = c.client_id
             LEFT JOIN users   u  ON u.id  = c.created_by
                 WHERE 1=1';
        $params = [];

        if ($clientId > 0) {
            $sql .= ' AND c.client_id = :client_id';
            $params[':client_id'] = $clientId;
        }
        if ($status !== '') {
            $sql .= ' AND c.status = :status';
            $params[':status'] = $status;
        }

        $sql .= ' ORDER BY c.updated_at DESC, c.id DESC';

        return $this->paginate($sql, $params, $page, $pageSize);
    }

    // -----------------------------------------------------------------------
    // getCampaign()
    // Fetches a single campaign with its channels.
    //
    // Returns: ['campaign'=>row, 'channels'=>rows] or [] when not found.
    // -----------------------------------------------------------------------
    public function getCampaign(int $id): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*,
                    cl.company_name AS client_name,
                    cl.email        AS client_email,
                    u.name          AS created_by_name
               FROM campaigns c
          LEFT JOIN clients cl ON cl.id = c.client_id
          LEFT JOIN users   u  ON u.id  = c.created_by
              WHERE c.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$campaign) {
            return [];
        }

        $chanStmt = $this->db->prepare(
            'SELECT cc.*,
                    v.company_name AS vendor_name,
                    v.email        AS vendor_email,
                    v.contact_name AS vendor_contact
               FROM campaign_channels cc
          LEFT JOIN vendors v ON v.id = cc.vendor_id
              WHERE cc.campaign_id = :id
              ORDER BY cc.media_category ASC, cc.id ASC'
        );
        $chanStmt->execute([':id' => $id]);
        $channels = $chanStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($channels as &$ch) {
            $ch['vendor_replies'] = json_decode($ch['vendor_replies'] ?? '[]', true) ?: [];
        }
        unset($ch);

        return ['campaign' => $campaign, 'channels' => $channels];
    }

    // -----------------------------------------------------------------------
    // saveCampaign()
    // Inserts or updates a campaign.
    //
    // Returns: ['success'=>bool, 'id'=>int, 'message'=>string]
    // -----------------------------------------------------------------------
    public function saveCampaign(array $data): array
    {
        $id          = (int)   ($data['id']           ?? 0);
        $title       = trim($data['title']            ?? '');
        $clientId    = (int)   ($data['client_id']    ?? 0);
        $language    = trim($data['language']         ?? 'both');
        $status      = trim($data['status']           ?? 'draft');
        $totalBudget = (float) ($data['total_budget'] ?? 0.00);
        $flightStart = $data['flight_start']          ?? null;
        $flightEnd   = $data['flight_end']            ?? null;
        $market      = trim($data['market']           ?? '');
        $notes       = trim($data['notes']            ?? '');
        $createdBy   = (int) ($_SESSION['user']['id'] ?? 0);

        if ($title === '') {
            return ['success' => false, 'id' => 0, 'message' => 'Campaign title is required.'];
        }
        if ($clientId === 0) {
            return ['success' => false, 'id' => 0, 'message' => 'Client is required.'];
        }

        if ($id === 0) {
            $stmt = $this->db->prepare(
                'INSERT INTO campaigns
                     (title, client_id, language, status, total_budget,
                      flight_start, flight_end, market, notes, created_by, created_at, updated_at)
                 VALUES
                     (:title, :client_id, :language, :status, :total_budget,
                      :flight_start, :flight_end, :market, :notes, :created_by, NOW(), NOW())'
            );
            $stmt->execute([
                ':title'        => $title,
                ':client_id'    => $clientId,
                ':language'     => $language,
                ':status'       => $status,
                ':total_budget' => $totalBudget,
                ':flight_start' => $flightStart ?: null,
                ':flight_end'   => $flightEnd   ?: null,
                ':market'       => $market,
                ':notes'        => $notes,
                ':created_by'   => $createdBy > 0 ? $createdBy : null,
            ]);

            $newId = $this->lastInsertId();
            $this->auditLog('create_campaign', 'campaign', $newId, "Created: {$title}");
            return ['success' => true, 'id' => $newId, 'message' => 'Campaign created successfully.'];
        }

        $stmt = $this->db->prepare(
            'UPDATE campaigns
                SET title        = :title,
                    client_id    = :client_id,
                    language     = :language,
                    status       = :status,
                    total_budget = :total_budget,
                    flight_start = :flight_start,
                    flight_end   = :flight_end,
                    market       = :market,
                    notes        = :notes,
                    updated_at   = NOW()
              WHERE id = :id'
        );
        $stmt->execute([
            ':title'        => $title,
            ':client_id'    => $clientId,
            ':language'     => $language,
            ':status'       => $status,
            ':total_budget' => $totalBudget,
            ':flight_start' => $flightStart ?: null,
            ':flight_end'   => $flightEnd   ?: null,
            ':market'       => $market,
            ':notes'        => $notes,
            ':id'           => $id,
        ]);

        $this->auditLog('update_campaign', 'campaign', $id, "Updated: {$title}");
        return ['success' => true, 'id' => $id, 'message' => 'Campaign updated successfully.'];
    }

    // -----------------------------------------------------------------------
    // saveChannel()
    // Inserts or updates a campaign channel (vendor + media category).
    //
    // Returns: ['success'=>bool, 'id'=>int, 'message'=>string]
    // -----------------------------------------------------------------------
    public function saveChannel(array $data): array
    {
        $id              = (int)   ($data['id']              ?? 0);
        $campaignId      = (int)   ($data['campaign_id']     ?? 0);
        $vendorId        = (int)   ($data['vendor_id']       ?? 0);
        $mediaCategory   = trim($data['media_category']      ?? '');
        $budgetAllocated = (float) ($data['budget_allocated'] ?? 0.00);
        $notes           = trim($data['notes']               ?? '');

        if ($campaignId === 0 || $mediaCategory === '') {
            return ['success' => false, 'id' => 0, 'message' => 'Campaign and media category are required.'];
        }

        if ($id === 0) {
            $stmt = $this->db->prepare(
                'INSERT INTO campaign_channels
                     (campaign_id, vendor_id, media_category, budget_allocated, notes, created_at, updated_at)
                 VALUES
                     (:campaign_id, :vendor_id, :media_category, :budget_allocated, :notes, NOW(), NOW())'
            );
            $stmt->execute([
                ':campaign_id'      => $campaignId,
                ':vendor_id'        => $vendorId > 0 ? $vendorId : null,
                ':media_category'   => $mediaCategory,
                ':budget_allocated' => $budgetAllocated,
                ':notes'            => $notes,
            ]);

            $newId = $this->lastInsertId();
            $this->auditLog('create_channel', 'campaign_channel', $newId, "Channel: {$mediaCategory}");
            return ['success' => true, 'id' => $newId, 'message' => 'Channel added.'];
        }

        $stmt = $this->db->prepare(
            'UPDATE campaign_channels
                SET vendor_id        = :vendor_id,
                    media_category   = :media_category,
                    budget_allocated = :budget_allocated,
                    notes            = :notes,
                    updated_at       = NOW()
              WHERE id = :id AND campaign_id = :campaign_id'
        );
        $stmt->execute([
            ':vendor_id'        => $vendorId > 0 ? $vendorId : null,
            ':media_category'   => $mediaCategory,
            ':budget_allocated' => $budgetAllocated,
            ':notes'            => $notes,
            ':id'               => $id,
            ':campaign_id'      => $campaignId,
        ]);

        $this->auditLog('update_channel', 'campaign_channel', $id, "Channel: {$mediaCategory}");
        return ['success' => true, 'id' => $id, 'message' => 'Channel updated.'];
    }

    // -----------------------------------------------------------------------
    // deleteChannel()
    // Removes a channel — only allowed when status is 'pending'.
    //
    // Returns: bool — true if a row was deleted
    // -----------------------------------------------------------------------
    public function deleteChannel(int $id, int $campaignId): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM campaign_channels
              WHERE id = :id AND campaign_id = :campaign_id AND status = 'pending'"
        );
        $stmt->execute([':id' => $id, ':campaign_id' => $campaignId]);
        return $stmt->rowCount() > 0;
    }

    // -----------------------------------------------------------------------
    // sendChannelRfp()
    // Sends an RFP email to the vendor for a specific campaign channel and
    // updates the channel status to 'rfp_sent'.
    //
    // Returns: ['success'=>bool, 'message'=>string, 'logId'=>int]
    // -----------------------------------------------------------------------
    public function sendChannelRfp(int $channelId): array
    {
        $stmt = $this->db->prepare(
            'SELECT cc.*,
                    v.company_name AS vendor_name,
                    v.email        AS vendor_email,
                    v.contact_name AS vendor_contact,
                    c.title        AS campaign_title,
                    c.flight_start AS campaign_start,
                    c.flight_end   AS campaign_end,
                    c.market       AS campaign_market,
                    c.language     AS campaign_language
               FROM campaign_channels cc
          LEFT JOIN vendors   v ON v.id = cc.vendor_id
          LEFT JOIN campaigns c ON c.id = cc.campaign_id
              WHERE cc.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $channelId]);
        $ch = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ch) {
            return ['success' => false, 'message' => 'Channel not found.', 'logId' => 0];
        }
        if (empty($ch['vendor_email'])) {
            return ['success' => false, 'message' => 'Vendor has no email address.', 'logId' => 0];
        }

        // Generate or reuse secure reply token for vendor self-service portal
        $existingToken = $ch['rfp_reply_token'] ?? '';
        $replyToken = ($existingToken !== '' && strlen($existingToken) === 40)
            ? $existingToken
            : bin2hex(random_bytes(20));

        $this->db->prepare('UPDATE campaign_channels SET rfp_reply_token = :t WHERE id = :id')
                 ->execute([':t' => $replyToken, ':id' => $channelId]);

        $baseUrl   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $portalUrl = $baseUrl . '/campaigns/vendor-rfp-reply.php?token=' . $replyToken;

        $flightStart = $ch['campaign_start'] ? date('M j, Y', strtotime($ch['campaign_start'])) : 'TBD';
        $flightEnd   = $ch['campaign_end']   ? date('M j, Y', strtotime($ch['campaign_end']))   : 'TBD';
        $budget      = '$' . number_format((float) $ch['budget_allocated'], 2);
        $market      = $ch['campaign_market'] ?: 'Local Market';
        $contact     = $ch['vendor_contact']  ?: $ch['vendor_name'];
        $category    = $ch['media_category'];
        $language    = ucfirst($ch['campaign_language'] ?? 'both');

        $subject = 'Request for Proposal: ' . $ch['campaign_title'] . ' — ' . $category;

        $bodyHtml = '<p>Dear ' . htmlspecialchars($contact, ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>We are reaching out to request a proposal for an upcoming campaign. '
            . 'Please review the details below and reply with your available placements, rates, and schedule.</p>'
            . '<table cellpadding="8" cellspacing="0" border="1" style="border-collapse:collapse;width:100%;max-width:520px;">'
            . '<tr><td><strong>Campaign</strong></td><td>' . htmlspecialchars($ch['campaign_title'], ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td><strong>Media Category</strong></td><td>' . htmlspecialchars($category, ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td><strong>Language</strong></td><td>' . htmlspecialchars($language, ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td><strong>Market</strong></td><td>' . htmlspecialchars($market, ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td><strong>Flight Dates</strong></td><td>' . $flightStart . ' &ndash; ' . $flightEnd . '</td></tr>'
            . '<tr><td><strong>Budget</strong></td><td>' . $budget . '</td></tr>'
            . '</table>'
            . '<div style="margin:24px 0;text-align:center;">'
            . '<a href="' . htmlspecialchars($portalUrl, ENT_QUOTES, 'UTF-8') . '" '
            . 'style="background:#0d6efd;color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-weight:bold;font-size:15px;display:inline-block;">'
            . '&#128228; Submit Your Proposal Online</a>'
            . '<p style="font-size:12px;color:#888;margin-top:8px;">Or reply directly to this email with your proposal attached.</p>'
            . '</div>'
            . '<p>Please reply to this email with your proposed schedule, rate card, and any available package options. '
            . 'Attach your schedule as a PDF or Excel file if available.</p>'
            . '<p>Thank you,<br>Media Buying Team</p>';

        $bodyText = "Dear {$contact},\n\n"
            . "We are requesting a proposal for the following campaign:\n\n"
            . "Campaign:       {$ch['campaign_title']}\n"
            . "Media Category: {$category}\n"
            . "Language:       {$language}\n"
            . "Market:         {$market}\n"
            . "Flight Dates:   {$flightStart} - {$flightEnd}\n"
            . "Budget:         {$budget}\n\n"
            . "Please reply with your proposed schedule, rate card, and available package options.\n\n"
            . "Thank you,\nMedia Buying Team";

        $emailService = new EmailService();
        $result = $emailService->send(
            $ch['vendor_email'],
            $ch['vendor_name'],
            $subject,
            $bodyHtml,
            $bodyText,
            '',
            '',
            0,
            0,
            0,
            (int) $ch['campaign_id'],
            $channelId
        );

        if ($result['success']) {
            $this->db->prepare(
                "UPDATE campaign_channels
                    SET status = 'rfp_sent', rfp_sent_at = NOW(), rfp_log_id = :log_id, updated_at = NOW()
                  WHERE id = :id"
            )->execute([':log_id' => $result['logId'], ':id' => $channelId]);

            $this->db->prepare(
                "UPDATE campaigns SET status = 'rfp_sent', updated_at = NOW()
                  WHERE id = :id AND status = 'draft'"
            )->execute([':id' => $ch['campaign_id']]);

            $this->auditLog('send_rfp', 'campaign_channel', $channelId,
                "RFP sent to: {$ch['vendor_email']}");
        }

        return $result;
    }

    // -----------------------------------------------------------------------
    // updateCampaignStatus()
    // -----------------------------------------------------------------------
    public function updateCampaignStatus(int $id, string $status): void
    {
        $this->db->prepare(
            'UPDATE campaigns SET status = :status, updated_at = NOW() WHERE id = :id'
        )->execute([':status' => $status, ':id' => $id]);
        $this->auditLog('update_status', 'campaign', $id, "Status: {$status}");
    }

    // -----------------------------------------------------------------------
    // getRfpChannelByToken()
    // Looks up a campaign channel + campaign info by the rfp_reply_token.
    // Tokens are not single-use — vendors may resubmit updated proposals.
    // Returns full context array or [] if token not found.
    // -----------------------------------------------------------------------
    public function getRfpChannelByToken(string $token): array
    {
        $token = trim($token);
        if (strlen($token) !== 40) return [];

        $stmt = $this->db->prepare(
            'SELECT cc.*,
                    v.company_name  AS vendor_name,
                    v.email         AS vendor_email,
                    v.contact_name  AS vendor_contact,
                    c.title         AS campaign_title,
                    c.flight_start  AS campaign_start,
                    c.flight_end    AS campaign_end,
                    c.market        AS campaign_market,
                    c.language      AS campaign_language,
                    c.notes         AS campaign_notes,
                    u.email         AS buyer_email,
                    u.name          AS buyer_name
               FROM campaign_channels cc
          LEFT JOIN vendors   v ON v.id  = cc.vendor_id
          LEFT JOIN campaigns c ON c.id  = cc.campaign_id
          LEFT JOIN users     u ON u.id  = c.created_by
              WHERE cc.rfp_reply_token = :token
              LIMIT 1'
        );
        $stmt->execute([':token' => $token]);
        $ch = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ch) return [];

        $ch['vendor_replies'] = json_decode($ch['vendor_replies'] ?? '[]', true) ?: [];

        // Collect prior replies from this vendor for the resubmit notice
        $priorReplies = $ch['vendor_replies'];

        return ['channel' => $ch, 'prior_replies' => $priorReplies];
    }

    // -----------------------------------------------------------------------
    // saveRfpReplyByToken()
    // Processes a vendor's self-submitted proposal via their unique token.
    // Uploads files to uploads/campaigns/{channelId}/replies/, appends to
    // vendor_replies JSON, updates channel status to 'response_received',
    // and emails the buyer a notification.
    // -----------------------------------------------------------------------
    public function saveRfpReplyByToken(string $token, string $notes, array $filesInput): array
    {
        $ctx = $this->getRfpChannelByToken($token);
        if (empty($ctx)) {
            return ['success' => false, 'message' => 'Invalid or expired link.'];
        }

        $ch         = $ctx['channel'];
        $channelId  = (int)$ch['id'];
        $campaignId = (int)$ch['campaign_id'];
        $vendorName = $ch['vendor_name'] ?: $ch['vendor_email'];

        // Upload files to uploads/campaigns/{channelId}/replies/
        $uploadDir = __DIR__ . '/../uploads/campaigns/' . $channelId . '/replies/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $allowed = [
            'application/pdf',
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        // Normalise multi-file $_FILES structure
        $files = [];
        if (!empty($filesInput['name']) && is_array($filesInput['name'])) {
            foreach ($filesInput['name'] as $i => $name) {
                $files[] = [
                    'name'     => $name,
                    'tmp_name' => $filesInput['tmp_name'][$i],
                    'type'     => $filesInput['type'][$i],
                    'error'    => $filesInput['error'][$i],
                    'size'     => $filesInput['size'][$i],
                ];
            }
        } elseif (!empty($filesInput['name'])) {
            $files[] = $filesInput;
        }

        $attachments = [];
        foreach ($files as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] === 0) continue;
            $mime = mime_content_type($file['tmp_name']);
            if (!in_array($mime, $allowed, true)) continue;
            $safeName = preg_replace('/[^a-zA-Z0-9._\-]/', '_', basename($file['name']));
            $safeName = date('Ymd_His_') . $safeName;
            $destPath = $uploadDir . $safeName;
            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $attachments[] = [
                    'name' => $file['name'],
                    'path' => 'uploads/campaigns/' . $channelId . '/replies/' . $safeName,
                    'type' => $mime,
                ];
            }
        }

        // Append reply to vendor_replies JSON
        $existing   = $ch['vendor_replies'];
        $existing[] = [
            'vendor_name' => $vendorName,
            'notes'       => $notes,
            'replied_at'  => date('Y-m-d H:i:s'),
            'attachments' => $attachments,
            'source'      => 'portal',
        ];

        // Update channel: append reply, set status to response_received
        $this->db->prepare(
            "UPDATE campaign_channels
                SET vendor_replies = :vr,
                    status         = 'response_received',
                    updated_at     = NOW()
              WHERE id = :id"
        )->execute([
            ':vr' => json_encode(array_values($existing)),
            ':id' => $channelId,
        ]);

        // If all channels for campaign have responded, update campaign status
        $pendingStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM campaign_channels
              WHERE campaign_id = :cid AND status = 'rfp_sent'"
        );
        $pendingStmt->execute([':cid' => $campaignId]);
        if ((int)$pendingStmt->fetchColumn() === 0) {
            $this->db->prepare(
                "UPDATE campaigns SET status = 'responses_in', updated_at = NOW()
                  WHERE id = :id AND status = 'rfp_sent'"
            )->execute([':id' => $campaignId]);
        }

        // Email buyer notification
        if (!empty($ch['buyer_email'])) {
            $baseUrl  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $viewUrl  = $baseUrl . '/campaigns/view.php?id=' . $campaignId;
            $isResub  = count($ctx['prior_replies']) > 0;
            $attsNote = !empty($attachments) ? count($attachments) . ' file' . (count($attachments) > 1 ? 's' : '') . ' attached' : 'no files attached';
            $subject  = ($isResub ? 'Updated ' : '') . 'RFP Response Received — ' . $ch['campaign_title'] . ' (' . $ch['media_category'] . ')';
            $h        = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
            $bodyHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;color:#222;font-size:14px;">'
                . '<div style="max-width:600px;margin:0 auto;padding:24px;">'
                . '<p style="display:inline-block;background:#0d6efd;color:#fff;padding:4px 12px;border-radius:20px;font-size:13px;font-weight:bold;">' . ($isResub ? 'Updated Proposal' : 'Proposal Received') . '</p>'
                . '<h2 style="color:#0d6efd;">' . ($isResub ? 'Vendor Proposal Updated' : 'RFP Response Received') . '</h2>'
                . '<p><strong>' . $h($vendorName) . '</strong> has ' . ($isResub ? 'submitted an updated proposal' : 'submitted their proposal') . ' for:</p>'
                . '<ul><li><strong>Campaign:</strong> ' . $h($ch['campaign_title']) . '</li>'
                . '<li><strong>Category:</strong> ' . $h($ch['media_category']) . '</li>'
                . '<li><strong>Attachments:</strong> ' . $attsNote . '</li></ul>'
                . '<a href="' . $h($viewUrl) . '" style="background:#0d6efd;color:#fff;text-decoration:none;padding:10px 24px;border-radius:6px;font-weight:bold;display:inline-block;margin-top:8px;">View Campaign</a>'
                . '<p style="margin-top:32px;font-size:12px;color:#888;border-top:1px solid #eee;padding-top:12px;">Sent via ' . APP_NAME . '</p>'
                . '</div></body></html>';
            $emailSvc = new EmailService();
            $emailSvc->send($ch['buyer_email'], $ch['buyer_name'] ?? '', $subject, $bodyHtml);
        }

        return ['success' => true, 'vendor_name' => $vendorName];
    }

    // -----------------------------------------------------------------------
    // getChannelReplies()
    // Returns all vendor_replies for a channel, decoded.
    // -----------------------------------------------------------------------
    public function getChannelReplies(int $channelId): array
    {
        $stmt = $this->db->prepare('SELECT vendor_replies FROM campaign_channels WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $channelId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return json_decode($row['vendor_replies'] ?? '[]', true) ?: [];
    }

    // -----------------------------------------------------------------------
    // getDashboardCounts()
    // -----------------------------------------------------------------------
    public function getDashboardCounts(): array
    {
        $stmt = $this->db->query(
            'SELECT status, COUNT(*) AS cnt FROM campaigns GROUP BY status'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $map  = [];
        foreach ($rows as $row) {
            $map[$row['status']] = (int) $row['cnt'];
        }
        return [
            'draft'          => $map['draft']          ?? 0,
            'rfp_sent'       => $map['rfp_sent']       ?? 0,
            'responses_in'   => $map['responses_in']   ?? 0,
            'proposal_ready' => $map['proposal_ready'] ?? 0,
            'approved'       => $map['approved']       ?? 0,
            'active'         => $map['active']         ?? 0,
        ];
    }
}
