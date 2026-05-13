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

        $bid['printer_vendor_ids'] = json_decode($bid['printer_vendor_ids'] ?? '[]', true) ?: [];
        $bid['signage_vendor_ids'] = json_decode($bid['signage_vendor_ids'] ?? '[]', true) ?: [];
        $bid['attachments']        = json_decode($bid['attachments']        ?? '[]', true) ?: [];
        $bid['items']              = $this->getItems($id);

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

        foreach ($vendors as $vendor) {
            $vid        = (int)$vendor['id'];
            $isPrinter  = in_array($vid, $printerIds, true);
            $isSignage  = in_array($vid, $signageIds, true);

            // Determine which items to include for this vendor
            $vendorPrintItems   = $isPrinter ? $printItems   : [];
            $vendorSignageItems = $isSignage  ? $signageItems : [];

            if (empty($vendorPrintItems) && empty($vendorSignageItems)) continue;

            $subject  = 'Print Bid Request — ' . $bid['client_name'];
            $bodyHtml = $this->buildBidEmailHtml($bid, $vendor, $vendorPrintItems, $vendorSignageItems);

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

            $result = $emailSvc->send($vendor['email'], $vendor['company_name'], $subject, $bodyHtml, '', '', '', 0, 0, 0, 0, 0, $sgAttachments);

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
    private function buildBidEmailHtml(array $bid, array $vendor, array $printItems, array $signageItems): string
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
        $out  = ['draft' => 0, 'sent' => 0, 'approved' => 0, 'rejected' => 0, '' => 0];
        foreach ($rows as $r) {
            $out[$r['status']] = (int)$r['cnt'];
            $out['']          += (int)$r['cnt'];
        }
        return $out;
    }
}
