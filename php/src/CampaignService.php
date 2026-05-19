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
    // sendVendorRfp()
    // Sends ONE consolidated RFP email covering ALL channels for a given
    // vendor in a campaign.  All their channels share a single reply token
    // so the vendor receives one email, one portal link, and submits once.
    //
    // Returns: ['success'=>bool, 'message'=>string, 'logId'=>int]
    // -----------------------------------------------------------------------
    public function sendVendorRfp(int $campaignId, int $vendorId): array
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
              WHERE cc.campaign_id = :cid AND cc.vendor_id = :vid
              ORDER BY cc.media_category ASC'
        );
        $stmt->execute([':cid' => $campaignId, ':vid' => $vendorId]);
        $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($channels)) {
            return ['success' => false, 'message' => 'No channels found for this vendor.', 'logId' => 0];
        }

        $first = $channels[0];
        if (empty($first['vendor_email'])) {
            return ['success' => false, 'message' => 'Vendor has no email address.', 'logId' => 0];
        }

        // One shared token for all this vendor's channels in this campaign.
        // Reuse if all channels already share the same valid token.
        $existingTokens = array_unique(array_filter(array_column($channels, 'rfp_reply_token')));
        $sharedToken = (count($existingTokens) === 1 && strlen(reset($existingTokens)) === 40)
            ? reset($existingTokens)
            : bin2hex(random_bytes(20));

        $channelIds   = array_column($channels, 'id');
        $placeholders = implode(',', array_fill(0, count($channelIds), '?'));
        $this->db->prepare(
            "UPDATE campaign_channels SET rfp_reply_token = ? WHERE id IN ($placeholders)"
        )->execute(array_merge([$sharedToken], $channelIds));

        $baseUrl   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                   . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $portalUrl = $baseUrl . '/campaigns/vendor-rfp-reply.php?token=' . $sharedToken;

        $flightStart  = $first['campaign_start'] ? date('M j, Y', strtotime($first['campaign_start'])) : 'TBD';
        $flightEnd    = $first['campaign_end']   ? date('M j, Y', strtotime($first['campaign_end']))   : 'TBD';
        $market       = $first['campaign_market'] ?: 'Local Market';
        $language     = ucfirst($first['campaign_language'] ?? 'both');
        $contact      = $first['vendor_contact'] ?: $first['vendor_name'];
        $totalBudget  = array_sum(array_column($channels, 'budget_allocated'));
        $h            = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

        $subject = 'Request for Proposal: ' . $first['campaign_title'];

        // Build category rows for the email table
        $categoryRowsHtml = '';
        $categoryRowsText = '';
        foreach ($channels as $ch) {
            $categoryRowsHtml .= '<tr>'
                . '<td style="padding:8px 12px;border:1px solid #dee2e6;">' . $h($ch['media_category']) . '</td>'
                . '<td style="padding:8px 12px;border:1px solid #dee2e6;text-align:right;font-weight:bold;">$'
                . number_format((float)$ch['budget_allocated'], 0) . '</td>'
                . '</tr>';
            $categoryRowsText .= '  ' . $ch['media_category']
                . str_repeat(' ', max(1, 30 - strlen($ch['media_category'])))
                . '$' . number_format((float)$ch['budget_allocated'], 0) . "\n";
        }
        $categoryRowsHtml .= '<tr style="background:#f8f9fa;">'
            . '<td style="padding:8px 12px;border:1px solid #dee2e6;font-weight:bold;">Total</td>'
            . '<td style="padding:8px 12px;border:1px solid #dee2e6;text-align:right;font-weight:bold;">$'
            . number_format($totalBudget, 0) . '</td>'
            . '</tr>';

        $bodyHtml = '<p>Dear ' . $h($contact) . ',</p>'
            . '<p>We are reaching out to request a proposal for an upcoming campaign. '
            . 'Please review the details below and submit your rates and availability for each item.</p>'
            . '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%;max-width:520px;margin-bottom:16px;">'
            . '<tr><td style="padding:8px 12px;border:1px solid #dee2e6;background:#f8f9fa;width:40%;"><strong>Campaign</strong></td>'
            . '<td style="padding:8px 12px;border:1px solid #dee2e6;">' . $h($first['campaign_title']) . '</td></tr>'
            . '<tr><td style="padding:8px 12px;border:1px solid #dee2e6;background:#f8f9fa;"><strong>Market</strong></td>'
            . '<td style="padding:8px 12px;border:1px solid #dee2e6;">' . $h($market) . '</td></tr>'
            . '<tr><td style="padding:8px 12px;border:1px solid #dee2e6;background:#f8f9fa;"><strong>Language</strong></td>'
            . '<td style="padding:8px 12px;border:1px solid #dee2e6;">' . $h($language) . '</td></tr>'
            . '<tr><td style="padding:8px 12px;border:1px solid #dee2e6;background:#f8f9fa;"><strong>Flight Dates</strong></td>'
            . '<td style="padding:8px 12px;border:1px solid #dee2e6;">' . $flightStart . ' &ndash; ' . $flightEnd . '</td></tr>'
            . '</table>'
            . '<p><strong>Requested Media Items:</strong></p>'
            . '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%;max-width:520px;">'
            . '<thead><tr style="background:#0d6efd;color:#fff;">'
            . '<th style="padding:8px 12px;border:1px solid #0a58ca;text-align:left;">Media Category</th>'
            . '<th style="padding:8px 12px;border:1px solid #0a58ca;text-align:right;">Budget</th>'
            . '</tr></thead>'
            . '<tbody>' . $categoryRowsHtml . '</tbody>'
            . '</table>'
            . '<div style="margin:28px 0;text-align:center;">'
            . '<a href="' . $h($portalUrl) . '" style="background:#0d6efd;color:#fff;text-decoration:none;'
            . 'padding:13px 32px;border-radius:6px;font-weight:bold;font-size:15px;display:inline-block;">'
            . '&#128228;&nbsp; Submit Your Proposal Online</a>'
            . '<p style="font-size:12px;color:#888;margin-top:8px;">Or reply directly to this email with your proposal attached.</p>'
            . '</div>'
            . '<p>Please include your rate card, available schedules, and package options for each item above.</p>'
            . '<p>Thank you,<br>Media Buying Team</p>';

        $bodyText = "Dear {$contact},\n\n"
            . "We are requesting a proposal for the following campaign:\n\n"
            . "Campaign:     {$first['campaign_title']}\n"
            . "Market:       {$market}\n"
            . "Language:     {$language}\n"
            . "Flight Dates: {$flightStart} - {$flightEnd}\n\n"
            . "Requested Media Items:\n"
            . $categoryRowsText
            . "  " . str_repeat('-', 36) . "\n"
            . "  Total" . str_repeat(' ', 25) . '$' . number_format($totalBudget, 0) . "\n\n"
            . "Submit your proposal online: {$portalUrl}\n"
            . "Or reply to this email with your proposal attached.\n\n"
            . "Thank you,\nMedia Buying Team";

        $emailService = new EmailService();
        $result = $emailService->send(
            $first['vendor_email'],
            $first['vendor_name'],
            $subject,
            $bodyHtml,
            $bodyText,
            '', '', 0, 0, 0,
            $campaignId,
            (int)$first['id']
        );

        if ($result['success']) {
            $now = date('Y-m-d H:i:s');
            foreach ($channelIds as $cid) {
                $this->db->prepare(
                    "UPDATE campaign_channels
                        SET status = 'rfp_sent', rfp_sent_at = :now, rfp_log_id = :log_id, updated_at = NOW()
                      WHERE id = :id"
                )->execute([':now' => $now, ':log_id' => $result['logId'], ':id' => $cid]);
            }
            $this->db->prepare(
                "UPDATE campaigns SET status = 'rfp_sent', updated_at = NOW()
                  WHERE id = :id AND status = 'draft'"
            )->execute([':id' => $campaignId]);
            $this->auditLog('send_rfp', 'campaign', $campaignId,
                "RFP sent to {$first['vendor_email']} — " . count($channels) . ' channel(s)');
        }

        return $result;
    }

    // -----------------------------------------------------------------------
    // sendChannelRfp()
    // Legacy single-channel send. Kept for backward compatibility.
    // Prefer sendVendorRfp() which consolidates all vendor channels into one email.
    // -----------------------------------------------------------------------
    public function sendChannelRfp(int $channelId): array
    {
        $stmt = $this->db->prepare(
            'SELECT campaign_id, vendor_id FROM campaign_channels WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $channelId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['success' => false, 'message' => 'Channel not found.', 'logId' => 0];
        }
        return $this->sendVendorRfp((int)$row['campaign_id'], (int)$row['vendor_id']);
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

        // Fetch ALL channels that share this token (one vendor, one campaign)
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
              ORDER BY cc.media_category ASC'
        );
        $stmt->execute([':token' => $token]);
        $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($channels)) return [];

        foreach ($channels as &$ch) {
            $ch['vendor_replies'] = json_decode($ch['vendor_replies'] ?? '[]', true) ?: [];
        }
        unset($ch);

        // Collect all prior replies across all channels for the resubmit notice
        $priorReplies = [];
        foreach ($channels as $ch) {
            foreach ($ch['vendor_replies'] as $r) {
                $priorReplies[] = $r;
            }
        }
        usort($priorReplies, fn($a, $b) => strcmp($a['replied_at'] ?? '', $b['replied_at'] ?? ''));

        return [
            'channel'       => $channels[0],   // primary — kept for backward compat
            'channels'      => $channels,       // all channels for this vendor/token
            'prior_replies' => $priorReplies,
        ];
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

        $ch         = $ctx['channel'];           // primary channel
        $allChannels = $ctx['channels'];         // all channels sharing this token
        $channelId  = (int)$ch['id'];
        $campaignId = (int)$ch['campaign_id'];
        $vendorName = $ch['vendor_name'] ?: $ch['vendor_email'];

        // Upload files under the primary channel's folder
        $uploadDir = __DIR__ . '/../uploads/campaigns/' . $channelId . '/replies/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $allowed = [
            'application/pdf',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',  // some XLSX files are detected as ZIP
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

        // Build the reply entry once, apply it to ALL channels sharing this token
        $replyEntry = [
            'vendor_name' => $vendorName,
            'notes'       => $notes,
            'replied_at'  => date('Y-m-d H:i:s'),
            'attachments' => $attachments,
            'source'      => 'portal',
        ];

        foreach ($allChannels as $chan) {
            $existing   = $chan['vendor_replies'];
            $existing[] = $replyEntry;
            $this->db->prepare(
                "UPDATE campaign_channels
                    SET vendor_replies = :vr,
                        status         = 'response_received',
                        updated_at     = NOW()
                  WHERE id = :id"
            )->execute([
                ':vr' => json_encode(array_values($existing)),
                ':id' => (int)$chan['id'],
            ]);
        }

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
    // Ad Schedule — line items parsed from vendor proposals
    // -----------------------------------------------------------------------

    /** Return all ad schedule lines for a campaign, optionally filtered by vendor. */
    public function getAdScheduleLines(int $campaignId, ?int $vendorId = null): array
    {
        $where  = 'campaign_id = :cid';
        $params = [':cid' => $campaignId];
        if ($vendorId !== null) {
            $where  .= ' AND vendor_id = :vid';
            $params[':vid'] = $vendorId;
        }
        $stmt = $this->db->prepare(
            "SELECT * FROM campaign_ad_schedules
              WHERE {$where}
              ORDER BY vendor_name ASC, sort_order ASC, id ASC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Replace all ad schedule lines for a vendor in this campaign, then write
     * matching production items.  Deletes existing rows first so a re-parse
     * always produces a clean result.
     */
    public function saveAdScheduleLines(
        int    $campaignId,
        int    $vendorId,
        string $vendorName,
        array  $lines,
        string $sourceFile = ''
    ): void {
        $this->db->prepare(
            'DELETE FROM campaign_ad_schedules WHERE campaign_id = :cid AND vendor_id = :vid'
        )->execute([':cid' => $campaignId, ':vid' => $vendorId]);

        $now = date('Y-m-d H:i:s');
        foreach ($lines as $i => $line) {
            $this->db->prepare(
                'INSERT INTO campaign_ad_schedules
                     (campaign_id, vendor_id, vendor_name, media_category, placement, unit_type,
                      flight_start, flight_end, quantity, unit_rate, total_cost, notes,
                      source_file, parsed_at, sort_order)
                 VALUES
                     (:cid, :vid, :vname, :cat, :placement, :unit_type,
                      :fs, :fe, :qty, :rate, :total, :notes,
                      :src, :now, :sort)'
            )->execute([
                ':cid'       => $campaignId,
                ':vid'       => $vendorId,
                ':vname'     => $vendorName,
                ':cat'       => trim($line['media_category'] ?? ''),
                ':placement' => trim($line['placement']      ?? ''),
                ':unit_type' => trim($line['unit_type']      ?? ''),
                ':fs'        => ($line['flight_start'] ?? '') ?: null,
                ':fe'        => ($line['flight_end']   ?? '') ?: null,
                ':qty'       => isset($line['quantity'])   && $line['quantity']   !== '' ? (int)$line['quantity']   : null,
                ':rate'      => isset($line['unit_rate'])  && $line['unit_rate']  !== '' ? (float)$line['unit_rate']  : null,
                ':total'     => isset($line['total_cost']) && $line['total_cost'] !== '' ? (float)$line['total_cost'] : null,
                ':notes'     => trim($line['notes'] ?? ''),
                ':src'       => $sourceFile,
                ':now'       => $now,
                ':sort'      => $i,
            ]);
        }
        $this->auditLog('parse_proposal', 'campaign', $campaignId,
            "Ad schedule saved: {$vendorName} — " . count($lines) . " line(s) from {$sourceFile}");
    }

    // -----------------------------------------------------------------------
    // Production Items — specs/deadlines for creative assets
    // -----------------------------------------------------------------------

    public function getProductionItems(int $campaignId, ?int $vendorId = null): array
    {
        $where  = 'campaign_id = :cid';
        $params = [':cid' => $campaignId];
        if ($vendorId !== null) {
            $where  .= ' AND vendor_id = :vid';
            $params[':vid'] = $vendorId;
        }
        $stmt = $this->db->prepare(
            "SELECT * FROM campaign_production_items
              WHERE {$where}
              ORDER BY vendor_name ASC, sort_order ASC, id ASC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveProductionItems(
        int    $campaignId,
        int    $vendorId,
        string $vendorName,
        array  $items
    ): void {
        $this->db->prepare(
            'DELETE FROM campaign_production_items WHERE campaign_id = :cid AND vendor_id = :vid'
        )->execute([':cid' => $campaignId, ':vid' => $vendorId]);

        foreach ($items as $i => $item) {
            $this->db->prepare(
                'INSERT INTO campaign_production_items
                     (campaign_id, vendor_id, vendor_name, media_type, ad_name,
                      unit_specs, material_due_date, notes, sort_order)
                 VALUES
                     (:cid, :vid, :vname, :mtype, :aname,
                      :specs, :due, :notes, :sort)'
            )->execute([
                ':cid'   => $campaignId,
                ':vid'   => $vendorId,
                ':vname' => $vendorName,
                ':mtype' => trim($item['media_type'] ?? ''),
                ':aname' => trim($item['ad_name']    ?? ''),
                ':specs' => trim($item['unit_specs']  ?? ''),
                ':due'   => ($item['material_due_date'] ?? '') ?: null,
                ':notes' => trim($item['notes'] ?? ''),
                ':sort'  => $i,
            ]);
        }
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
