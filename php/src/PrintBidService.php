<?php
/**
 * src/PrintBidService.php
 * Media Buying Platform — Print & Signage Bids service
 */

class PrintBidService extends BaseService
{
    // -----------------------------------------------------------------------
    // getBids()
    // Returns all print bids, newest first.
    // -----------------------------------------------------------------------
    public function getBids(string $status = '', int $page = 1, int $pageSize = 30): array
    {
        $sql = 'SELECT pb.*,
                       u.name AS created_by_name
                  FROM print_bids pb
             LEFT JOIN users u ON u.id = pb.created_by';

        $params = [];
        if ($status !== '') {
            $sql             .= ' WHERE pb.status = :status';
            $params[':status'] = $status;
        }

        $sql .= ' ORDER BY pb.created_at DESC';

        return $this->paginate($sql, $params, $page, $pageSize);
    }

    // -----------------------------------------------------------------------
    // getBid()
    // Returns a single bid + its items.
    // -----------------------------------------------------------------------
    public function getBid(int $id): array
    {
        $stmt = $this->db->prepare(
            'SELECT pb.*, u.name AS created_by_name
               FROM print_bids pb
          LEFT JOIN users u ON u.id = pb.created_by
              WHERE pb.id = :id
              LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $bid = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$bid) return [];

        $bid['printer_vendor_ids']  = json_decode($bid['printer_vendor_ids']  ?? '[]', true) ?: [];
        $bid['signage_vendor_ids']  = json_decode($bid['signage_vendor_ids']  ?? '[]', true) ?: [];
        $bid['attachments']         = json_decode($bid['attachments']         ?? '[]', true) ?: [];
        $bid['vendor_replies']      = json_decode($bid['vendor_replies']      ?? '[]', true) ?: [];
        $bid['vendor_reply_tokens'] = json_decode($bid['vendor_reply_tokens'] ?? '[]', true) ?: [];
        $bid['items']               = $this->getItems($id);

        return $bid;
    }

    // -----------------------------------------------------------------------
    // getItems()
    // Returns all line items for a bid, ordered by type then sort_order.
    // -----------------------------------------------------------------------
    public function getItems(int $bidId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM print_bid_items
              WHERE bid_id = :bid_id
           ORDER BY type, sort_order, id'
        );
        $stmt->execute([':bid_id' => $bidId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // -----------------------------------------------------------------------
    // saveBid()
    // Insert or update a bid record. Does NOT touch items (see saveItems()).
    //
    // $data keys: client_name, title, status, printer_vendor_ids (array),
    //             signage_vendor_ids (array), notes, [id]
    //
    // Returns: ['success'=>bool, 'id'=>int, 'message'=>string]
    // -----------------------------------------------------------------------
    public function saveBid(array $data): array
    {
        $id         = !empty($data['id']) ? (int)$data['id'] : null;
        $userId     = $_SESSION['user']['id'] ?? null;

        $printerIds = json_encode(array_map('intval', (array)($data['printer_vendor_ids'] ?? [])));
        $signageIds = json_encode(array_map('intval', (array)($data['signage_vendor_ids'] ?? [])));

        if ($id) {
            $stmt = $this->db->prepare(
                'UPDATE print_bids
                    SET client_name        = :client_name,
                        title              = :title,
                        status             = :status,
                        printer_vendor_ids = :printer_vendor_ids,
                        signage_vendor_ids = :signage_vendor_ids,
                        notes              = :notes
                  WHERE id = :id'
            );
            $stmt->execute([
                ':client_name'        => $data['client_name'],
                ':title'              => $data['title']   ?? null,
                ':status'             => $data['status']  ?? 'draft',
                ':printer_vendor_ids' => $printerIds,
                ':signage_vendor_ids' => $signageIds,
                ':notes'              => $data['notes']   ?? null,
                ':id'                 => $id,
            ]);
            return ['success' => true, 'id' => $id, 'message' => 'Print bid updated.'];
        }


        $stmt = $this->db->prepare(
            'INSERT INTO print_bids
                   (client_name, title, status, printer_vendor_ids, signage_vendor_ids, notes, created_by)
             VALUES (:client_name, :title, :status, :printer_vendor_ids, :signage_vendor_ids, :notes, :created_by)'
        );
        $stmt->execute([
            ':client_name'        => $data['client_name'],
            ':title'              => $data['title']   ?? null,
            ':status'             => $data['status']  ?? 'draft',
            ':printer_vendor_ids' => $printerIds,
            ':signage_vendor_ids' => $signageIds,
            ':notes'              => $data['notes']   ?? null,
            ':created_by'         => $userId,
        ]);

        return ['success' => true, 'id' => (int)$this->db->lastInsertId(), 'message' => 'Print bid created.'];
    }

    // -----------------------------------------------------------------------
    // saveItems()
    // Replaces all line items for a bid.
    // $items: array of ['type', 'description', 'size', 'paper', 'ink_spec',
    //                   'material', 'qty_1'..'qty_5', 'notes', 'sort_order']
    // -----------------------------------------------------------------------
    public function saveItems(int $bidId, array $items): void
    {
        $this->db->prepare('DELETE FROM print_bid_items WHERE bid_id = :bid_id')
                 ->execute([':bid_id' => $bidId]);

        $stmt = $this->db->prepare(
            'INSERT INTO print_bid_items
                   (bid_id, type, description, size, paper, ink_spec, material,
                    qty_1, qty_2, qty_3, qty_4, qty_5, notes, sort_order)
             VALUES (:bid_id, :type, :description, :size, :paper, :ink_spec, :material,
                    :qty_1, :qty_2, :qty_3, :qty_4, :qty_5, :notes, :sort_order)'
        );

        foreach ($items as $i => $item) {
            $stmt->execute([
                ':bid_id'      => $bidId,
                ':type'        => in_array($item['type'] ?? '', ['print','signage'], true) ? $item['type'] : 'print',
                ':description' => $item['description'] ?? null,
                ':size'        => $item['size']        ?? null,
                ':paper'       => $item['paper']       ?? null,
                ':ink_spec'    => $item['ink_spec']    ?? null,
                ':material'    => $item['material']    ?? null,
                ':qty_1'       => $item['qty_1']       ?? null,
                ':qty_2'       => $item['qty_2']       ?? null,
                ':qty_3'       => $item['qty_3']       ?? null,
                ':qty_4'       => $item['qty_4']       ?? null,
                ':qty_5'       => $item['qty_5']       ?? null,
                ':notes'       => $item['notes']       ?? null,
                ':sort_order'  => $i,
            ]);
        }
    }

    // -----------------------------------------------------------------------
    // sendBidEmails()
    // Emails each selected vendor their relevant job items.
    // Printer vendors receive print items; signage vendors receive signage items.
    // Returns ['sent'=>int, 'failed'=>int, 'errors'=>string[]]
    // -----------------------------------------------------------------------
    public function sendBidEmails(array $bid): array
    {
        $emailSvc = new EmailService();
        $sent     = 0;
        $failed   = 0;
        $errors   = [];

        $printItems   = array_values(array_filter($bid['items'], fn($i) => $i['type'] === 'print'));
        $signageItems = array_values(array_filter($bid['items'], fn($i) => $i['type'] === 'signage'));

        // Fetch vendor rows for printer IDs and signage IDs
        $allIds = array_unique(array_merge(
            array_map('intval', $bid['printer_vendor_ids']),
            array_map('intval', $bid['signage_vendor_ids'])
        ));

        if (empty($allIds)) return ['sent' => 0, 'failed' => 0, 'errors' => ['No vendors selected.']];

        $placeholders = implode(',', array_fill(0, count($allIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT id, company_name, email FROM vendors WHERE id IN ($placeholders) AND email != ''"
        );
        $stmt->execute($allIds);
        $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $printerIds = array_map('intval', $bid['printer_vendor_ids']);
        $signageIds = array_map('intval', $bid['signage_vendor_ids']);

        if (empty($vendors)) {
            return ['sent' => 0, 'failed' => 0, 'errors' => ['No vendor emails found for the selected vendors.']];
        }

        // Generate secure reply tokens for each vendor
        $baseUrl   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $vendorIds = array_column($vendors, 'id');
        $tokenMap  = $this->generateVendorTokens((int)$bid['id'], $vendorIds);

        // Store vendor names in tokens so getBidByVendorToken() can return them
        $stmt = $this->db->prepare('SELECT vendor_reply_tokens FROM print_bids WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $bid['id']]);
        $tokenRow  = $stmt->fetch(PDO::FETCH_ASSOC);
        $tokenList = json_decode($tokenRow['vendor_reply_tokens'] ?? '[]', true) ?: [];
        $vendorNameMap = array_column($vendors, 'company_name', 'id');
        foreach ($tokenList as &$t) {
            if (isset($vendorNameMap[$t['vendor_id']])) {
                $t['vendor_name'] = $vendorNameMap[$t['vendor_id']];
            }
        }
        unset($t);
        $this->db->prepare('UPDATE print_bids SET vendor_reply_tokens = :t WHERE id = :id')
                 ->execute([':t' => json_encode(array_values($tokenList)), ':id' => $bid['id']]);

        foreach ($vendors as $vendor) {
            $vid        = (int)$vendor['id'];
            $isPrinter  = in_array($vid, $printerIds, true);
            $isSignage  = in_array($vid, $signageIds, true);

            // Determine which items to include for this vendor
            $vendorPrintItems   = $isPrinter ? $printItems   : [];
            $vendorSignageItems = $isSignage  ? $signageItems : [];

            if (empty($vendorPrintItems) && empty($vendorSignageItems)) continue;

            $subject  = 'Print Bid Request — ' . $bid['client_name'];
            $replyUrl = isset($tokenMap[$vid]) ? $baseUrl . '/print-bids/vendor-reply.php?token=' . $tokenMap[$vid] : '';
            $bodyHtml = $this->buildBidEmailHtml($bid, $vendor, $vendorPrintItems, $vendorSignageItems, $replyUrl);

            // Build absolute-path attachment list for SendGrid
            $sgAttachments = [];
            foreach ($bid['attachments'] ?? [] as $att) {
                $absPath = __DIR__ . '/../' . $att['path'];
                if (file_exists($absPath)) {
                    $sgAttachments[] = [
                        'name' => $att['name'],
                        'path' => $absPath,
                        'type' => $att['type'],
                    ];
                }
            }

            $result = $emailSvc->send($vendor['email'], $vendor['company_name'], $subject, $bodyHtml, '', '', '', 0, 0, 0, 0, 0, $sgAttachments, (int)$bid['id']);

            if ($result['success']) {
                $sent++;
            } else {
                $failed++;
                $errors[] = $vendor['company_name'] . ' &lt;' . $vendor['email'] . '&gt;: ' . $result['message'];
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'errors' => $errors];
    }

    // -----------------------------------------------------------------------
    // buildBidEmailHtml()  [private]
    // Composes the HTML email body for a vendor.
    // -----------------------------------------------------------------------
    private function buildBidEmailHtml(array $bid, array $vendor, array $printItems, array $signageItems, string $replyUrl = ''): string
    {
        $clientName = htmlspecialchars($bid['client_name'], ENT_QUOTES, 'UTF-8');
        $vendorName = htmlspecialchars($vendor['company_name'], ENT_QUOTES, 'UTF-8');
        $date       = date('F j, Y');
        $notes      = !empty($bid['notes']) ? nl2br(htmlspecialchars($bid['notes'], ENT_QUOTES, 'UTF-8')) : '';

        $h = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

        $html  = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        $html .= '<style>body{font-family:Arial,sans-serif;color:#222;font-size:14px;margin:0;padding:0;}';
        $html .= '.wrap{max-width:680px;margin:0 auto;padding:24px;}';
        $html .= 'h2{color:#b02a37;margin-top:0;} h3{color:#333;border-bottom:2px solid #b02a37;padding-bottom:4px;}';
        $html .= 'table{width:100%;border-collapse:collapse;margin-bottom:20px;}';
        $html .= 'th{background:#f8f9fa;text-align:left;padding:8px;font-size:12px;text-transform:uppercase;border:1px solid #dee2e6;}';
        $html .= 'td{padding:8px;border:1px solid #dee2e6;vertical-align:top;}';
        $html .= '.footer{margin-top:32px;font-size:12px;color:#888;border-top:1px solid #eee;padding-top:12px;}';
        $html .= '</style></head><body><div class="wrap">';

        $html .= "<h2>Print Bid Request</h2>";
        $html .= "<p>Dear <strong>{$vendorName}</strong>,</p>";
        $html .= "<p>Please provide pricing for the following job(s) for our client <strong>{$clientName}</strong>.</p>";
        $html .= "<p><strong>Date:</strong> {$date}</p>";

        // ── Print Items ──────────────────────────────────────────────────────
        if (!empty($printItems)) {
            $html .= '<h3>Printing Job Items</h3>';
            $html .= '<table><thead><tr>';
            $html .= '<th>Description</th><th>Size</th><th>Paper</th><th>Ink</th><th>Quantities</th><th>Notes</th>';
            $html .= '</tr></thead><tbody>';
            foreach ($printItems as $item) {
                $qtys = array_filter([$item['qty_1'],$item['qty_2'],$item['qty_3'],$item['qty_4'],$item['qty_5']]);
                $html .= '<tr>';
                $html .= '<td>' . $h($item['description'] ?? '') . '</td>';
                $html .= '<td>' . $h($item['size']        ?? '') . '</td>';
                $html .= '<td>' . $h($item['paper']       ?? '') . '</td>';
                $html .= '<td>' . $h($item['ink_spec']    ?? '') . '</td>';
                $html .= '<td>' . $h(implode(' / ', $qtys))       . '</td>';
                $html .= '<td>' . $h($item['notes']       ?? '') . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }

        // ── Signage Items ────────────────────────────────────────────────────
        if (!empty($signageItems)) {
            $html .= '<h3>Signage Job Items</h3>';
            $html .= '<table><thead><tr>';
            $html .= '<th>Description</th><th>Size</th><th>Material</th><th>Quantity</th><th>Notes</th>';
            $html .= '</tr></thead><tbody>';
            foreach ($signageItems as $item) {
                $html .= '<tr>';
                $html .= '<td>' . $h($item['description'] ?? '') . '</td>';
                $html .= '<td>' . $h($item['size']        ?? '') . '</td>';
                $html .= '<td>' . $h($item['material']    ?? '') . '</td>';
                $html .= '<td>' . $h($item['qty_1']       ?? '') . '</td>';
                $html .= '<td>' . $h($item['notes']       ?? '') . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }

        if ($notes) {
            $html .= '<h3>Additional Notes</h3><p>' . $notes . '</p>';
        }

        if ($replyUrl !== '') {
            $html .= '<div style="margin:24px 0;text-align:center;">';
            $html .= '<a href="' . htmlspecialchars($replyUrl, ENT_QUOTES, 'UTF-8') . '" ';
            $html .= 'style="background:#b02a37;color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-weight:bold;font-size:15px;display:inline-block;">';
            $html .= '&#128228; Submit Your Pricing</a>';
            $html .= '<p style="font-size:12px;color:#888;margin-top:8px;">Click the button above to upload your price quote directly.</p>';
            $html .= '</div>';
        }

        $html .= '<p>Please reply to this email with your quote at your earliest convenience. Thank you!</p>';
        $html .= '<div class="footer">Sent via ' . APP_NAME . ' &mdash; ' . date('Y') . '</div>';
        $html .= '</div></body></html>';

        return $html;
    }

    // -----------------------------------------------------------------------
    // saveAttachments()
    // Processes $_FILES['attachments'] (multi-file), moves accepted files
    // into uploads/print-bids/{bidId}/, appends to existing attachments,
    // and persists the JSON back to print_bids.attachments.
    //
    // Returns the full updated attachments array.
    // -----------------------------------------------------------------------
    public function saveAttachments(int $bidId, array $filesInput): array
    {
        // Fetch existing attachments
        $stmt = $this->db->prepare('SELECT attachments FROM print_bids WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $bidId]);
        $row         = $stmt->fetch(PDO::FETCH_ASSOC);
        $existing    = json_decode($row['attachments'] ?? '[]', true) ?: [];

        $uploadDir = __DIR__ . '/../uploads/print-bids/' . $bidId . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        // Normalise $_FILES multi-upload structure into a flat list
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

        foreach ($files as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] === 0) continue;

            $mime = mime_content_type($file['tmp_name']);
            if (!in_array($mime, $allowed, true)) continue;

            // Safe filename: strip non-alphanumeric except dot/dash/underscore
            $safeName = preg_replace('/[^a-zA-Z0-9._\-]/', '_', basename($file['name']));
            $safeName = date('Ymd_His_') . $safeName;
            $destPath = $uploadDir . $safeName;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $existing[] = [
                    'name' => $file['name'],
                    'path' => 'uploads/print-bids/' . $bidId . '/' . $safeName,
                    'type' => $mime,
                ];
            }
        }

        // Persist updated attachment list
        $this->db->prepare('UPDATE print_bids SET attachments = :a WHERE id = :id')
                 ->execute([':a' => json_encode($existing), ':id' => $bidId]);

        return $existing;
    }

    // -----------------------------------------------------------------------
    // removeAttachments()
    // Deletes specified attachment files from disk and strips them from the
    // JSON column.  $pathsToRemove is an array of relative paths as stored
    // in the DB (e.g. "uploads/print-bids/7/20250512_logo.pdf").
    // -----------------------------------------------------------------------
    public function removeAttachments(int $bidId, array $pathsToRemove): void
    {
        if (empty($pathsToRemove)) return;

        $stmt = $this->db->prepare('SELECT attachments FROM print_bids WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $bidId]);
        $row      = $stmt->fetch(PDO::FETCH_ASSOC);
        $existing = json_decode($row['attachments'] ?? '[]', true) ?: [];

        $updated = [];
        foreach ($existing as $att) {
            if (in_array($att['path'], $pathsToRemove, true)) {
                // Delete physical file
                $absPath = __DIR__ . '/../' . $att['path'];
                if (file_exists($absPath)) {
                    @unlink($absPath);
                }
            } else {
                $updated[] = $att;
            }
        }

        $this->db->prepare('UPDATE print_bids SET attachments = :a WHERE id = :id')
                 ->execute([':a' => json_encode(array_values($updated)), ':id' => $bidId]);
    }

    // -----------------------------------------------------------------------
    // saveVendorReply()
    // Logs a vendor's reply (pricing quote) with optional file attachments.
    // Uploads files to uploads/print-bids/{bidId}/replies/ and appends to
    // the vendor_replies JSON column.  Sets bid status → 'replied'.
    //
    // Returns ['success'=>bool, 'message'=>string]
    // -----------------------------------------------------------------------
    public function saveVendorReply(int $bidId, int $vendorId, string $vendorName, string $notes, array $filesInput): array
    {
        // Fetch existing replies
        $stmt = $this->db->prepare('SELECT vendor_replies FROM print_bids WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $bidId]);
        $row     = $stmt->fetch(PDO::FETCH_ASSOC);
        $replies = json_decode($row['vendor_replies'] ?? '[]', true) ?: [];

        // Upload pricing files to uploads/print-bids/{bidId}/replies/
        $uploadDir = __DIR__ . '/../uploads/print-bids/' . $bidId . '/replies/';
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

        $replyAttachments = [];
        foreach ($files as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] === 0) continue;
            $mime = mime_content_type($file['tmp_name']);
            if (!in_array($mime, $allowed, true)) continue;
            $safeName = preg_replace('/[^a-zA-Z0-9._\-]/', '_', basename($file['name']));
            $safeName = date('Ymd_His_') . $safeName;
            $destPath = $uploadDir . $safeName;
            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $replyAttachments[] = [
                    'name' => $file['name'],
                    'path' => 'uploads/print-bids/' . $bidId . '/replies/' . $safeName,
                    'type' => $mime,
                ];
            }
        }

        $replies[] = [
            'vendor_id'   => $vendorId,
            'vendor_name' => $vendorName,
            'notes'       => $notes,
            'replied_at'  => date('Y-m-d H:i:s'),
            'attachments' => $replyAttachments,
        ];

        $this->db->prepare(
            'UPDATE print_bids SET vendor_replies = :vr, status = :status WHERE id = :id'
        )->execute([
            ':vr'     => json_encode(array_values($replies)),
            ':status' => 'replied',
            ':id'     => $bidId,
        ]);

        return ['success' => true, 'message' => 'Reply logged.'];
    }

    // -----------------------------------------------------------------------
    // generateVendorTokens()
    // Creates a secure reply token for each vendor being emailed and stores
    // them in the vendor_reply_tokens JSON column.
    // Returns: array keyed by vendor_id => token string
    // -----------------------------------------------------------------------
    public function generateVendorTokens(int $bidId, array $vendorIds): array
    {
        // Load any existing tokens (preserve already-generated ones)
        $stmt = $this->db->prepare('SELECT vendor_reply_tokens FROM print_bids WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $bidId]);
        $row    = $stmt->fetch(PDO::FETCH_ASSOC);
        $tokens = json_decode($row['vendor_reply_tokens'] ?? '[]', true) ?: [];

        // Index existing tokens by vendor_id for lookup
        $existing = [];
        foreach ($tokens as $t) {
            $existing[(int)$t['vendor_id']] = $t['token'];
        }

        $tokenMap = [];
        foreach ($vendorIds as $vid) {
            $vid = (int)$vid;
            if (isset($existing[$vid])) {
                // Re-use existing token (idempotent resend)
                $tokenMap[$vid] = $existing[$vid];
            } else {
                $token = bin2hex(random_bytes(20));
                $tokens[] = [
                    'token'       => $token,
                    'vendor_id'   => $vid,
                    'vendor_name' => '',   // filled in by caller
                    'created_at'  => date('Y-m-d H:i:s'),
                    'used'        => false,
                ];
                $tokenMap[$vid] = $token;
            }
        }

        $this->db->prepare('UPDATE print_bids SET vendor_reply_tokens = :t WHERE id = :id')
                 ->execute([':t' => json_encode(array_values($tokens)), ':id' => $bidId]);

        return $tokenMap;
    }

    // -----------------------------------------------------------------------
    // getBidByVendorToken()
    // Looks up a bid and vendor info by the reply token.
    // Returns ['bid'=>[], 'vendor_id'=>int, 'vendor_name'=>string, 'token'=>string]
    // or empty array if token not found / already used.
    // -----------------------------------------------------------------------
    public function getBidByVendorToken(string $token): array
    {
        $token = trim($token);
        if (strlen($token) !== 40) return [];

        // Scan all bids for matching token (tokens are unique per bid)
        $stmt = $this->db->query(
            'SELECT pb.*, u.name AS created_by_name, u.email AS created_by_email
               FROM print_bids pb
          LEFT JOIN users u ON u.id = pb.created_by
              WHERE pb.vendor_reply_tokens IS NOT NULL
                AND pb.vendor_reply_tokens != \'[]\''
        );
        $bids = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($bids as $row) {
            $tokenList = json_decode($row['vendor_reply_tokens'] ?? '[]', true) ?: [];
            foreach ($tokenList as $t) {
                if ($t['token'] === $token) {
                    if (!empty($t['used'])) {
                        return ['error' => 'This link has already been used.'];
                    }
                    // Decode JSON columns
                    $row['printer_vendor_ids']  = json_decode($row['printer_vendor_ids']  ?? '[]', true) ?: [];
                    $row['signage_vendor_ids']  = json_decode($row['signage_vendor_ids']  ?? '[]', true) ?: [];
                    $row['attachments']         = json_decode($row['attachments']         ?? '[]', true) ?: [];
                    $row['vendor_replies']      = json_decode($row['vendor_replies']      ?? '[]', true) ?: [];
                    $row['vendor_reply_tokens'] = $tokenList;
                    $row['items']               = $this->getItems((int)$row['id']);
                    return [
                        'bid'         => $row,
                        'vendor_id'   => (int)$t['vendor_id'],
                        'vendor_name' => $t['vendor_name'],
                        'token'       => $token,
                    ];
                }
            }
        }
        return [];
    }

    // -----------------------------------------------------------------------
    // saveVendorReplyByToken()
    // Processes a vendor's self-submitted reply via their unique token.
    // Saves files, logs the reply, marks the token used, flips status to
    // 'replied', and emails the buyer a notification.
    // -----------------------------------------------------------------------
    public function saveVendorReplyByToken(string $token, string $notes, array $filesInput): array
    {
        $ctx = $this->getBidByVendorToken($token);
        if (empty($ctx) || !empty($ctx['error'])) {
            return ['success' => false, 'message' => $ctx['error'] ?? 'Invalid or expired link.'];
        }

        $bid        = $ctx['bid'];
        $bidId      = (int)$bid['id'];
        $vendorId   = $ctx['vendor_id'];
        $vendorName = $ctx['vendor_name'];

        // Delegate file upload + reply storage to saveVendorReply()
        $result = $this->saveVendorReply($bidId, $vendorId, $vendorName, $notes, $filesInput);
        if (!$result['success']) return $result;

        // Mark token as used
        $tokenList = $bid['vendor_reply_tokens'];
        foreach ($tokenList as &$t) {
            if ($t['token'] === $token) {
                $t['used'] = true;
                break;
            }
        }
        unset($t);
        $this->db->prepare('UPDATE print_bids SET vendor_reply_tokens = :t WHERE id = :id')
                 ->execute([':t' => json_encode(array_values($tokenList)), ':id' => $bidId]);

        // Email the buyer a notification
        if (!empty($bid['created_by_email'])) {
            $baseUrl  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $viewUrl  = $baseUrl . '/print-bids/view.php?id=' . $bidId . '#vendor-replies';
            $emailSvc = new EmailService();
            $subject  = 'Vendor Pricing Received — ' . $bid['client_name'] . ' (Bid #' . $bidId . ')';
            $bodyHtml = $this->buildBuyerNotificationEmail($bid, $vendorName, $viewUrl);
            $emailSvc->send($bid['created_by_email'], $bid['created_by_name'] ?? '', $subject, $bodyHtml);
        }

        return ['success' => true, 'vendor_name' => $vendorName];
    }

    // -----------------------------------------------------------------------
    // buildBuyerNotificationEmail()  [private]
    // Composes the HTML notification email sent to the buyer when a vendor
    // submits pricing via their self-service link.
    // -----------------------------------------------------------------------
    private function buildBuyerNotificationEmail(array $bid, string $vendorName, string $viewUrl): string
    {
        $h = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $html  = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        $html .= '<style>body{font-family:Arial,sans-serif;color:#222;font-size:14px;margin:0;padding:0;}';
        $html .= '.wrap{max-width:600px;margin:0 auto;padding:24px;}';
        $html .= '.badge{display:inline-block;background:#b02a37;color:#fff;padding:4px 12px;border-radius:20px;font-size:13px;font-weight:bold;}';
        $html .= '.btn{display:inline-block;background:#b02a37;color:#fff;text-decoration:none;padding:10px 24px;border-radius:6px;font-weight:bold;margin-top:16px;}';
        $html .= '.footer{margin-top:32px;font-size:12px;color:#888;border-top:1px solid #eee;padding-top:12px;}';
        $html .= '</style></head><body><div class="wrap">';
        $html .= '<p class="badge">Pricing Received</p>';
        $html .= '<h2 style="color:#b02a37;margin-top:12px;">Vendor Pricing Submitted</h2>';
        $html .= '<p><strong>' . $h($vendorName) . '</strong> has submitted their pricing for:</p>';
        $html .= '<ul><li><strong>Client:</strong> ' . $h($bid['client_name']) . '</li>';
        $html .= '<li><strong>Bid #:</strong> ' . (int)$bid['id'] . '</li>';
        if (!empty($bid['title'])) $html .= '<li><strong>Title:</strong> ' . $h($bid['title']) . '</li>';
        $html .= '</ul>';
        $html .= '<p>Log in to review their pricing attachments and notes:</p>';
        $html .= '<a href="' . $h($viewUrl) . '" class="btn">View Vendor Reply</a>';
        $html .= '<div class="footer">Sent via ' . APP_NAME . '</div>';
        $html .= '</div></body></html>';
        return $html;
    }

    // -----------------------------------------------------------------------
    // deleteBid()
    // -----------------------------------------------------------------------
    public function deleteBid(int $id): void
    {
        $this->db->prepare('DELETE FROM print_bids WHERE id = :id')
                 ->execute([':id' => $id]);
    }

    // -----------------------------------------------------------------------
    // getVendorsForPrint()
    // Returns vendors tagged "Printing" — actual print shops, not newspapers.
    // Falls back to all vendors if none matched.
    // -----------------------------------------------------------------------
    public function getVendorsForPrint(): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, company_name FROM vendors
              WHERE service_options LIKE '%Printing%'
           ORDER BY company_name"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // -----------------------------------------------------------------------
    // getVendorsForSignage()
    // Returns vendors tagged "Signage" — sign shops, not billboard ad sellers.
    // -----------------------------------------------------------------------
    public function getVendorsForSignage(): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, company_name FROM vendors
              WHERE service_options LIKE '%Signage%'
           ORDER BY company_name"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // -----------------------------------------------------------------------
    // getAllVendors()
    // All active vendors for the vendor picker (fallback).
    // -----------------------------------------------------------------------
    public function getAllVendors(): array
    {
        $stmt = $this->db->prepare('SELECT id, company_name FROM vendors ORDER BY company_name');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // -----------------------------------------------------------------------
    // getCounts()
    // Status badge counts for the index page.
    // -----------------------------------------------------------------------
    public function getCounts(): array
    {
        $stmt = $this->db->query(
            "SELECT status, COUNT(*) AS cnt FROM print_bids GROUP BY status"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out  = ['draft' => 0, 'sent' => 0, 'replied' => 0, 'approved' => 0, 'rejected' => 0, '' => 0];
        foreach ($rows as $r) {
            $out[$r['status']] = (int)$r['cnt'];
            $out['']          += (int)$r['cnt'];
        }
        return $out;
    }
}
