<?php
/**
 * src/BillingService.php
 * Media Buying Platform — billing queue management service
 */

class BillingService extends BaseService
{
    // -----------------------------------------------------------------------
    // getQueue()
    // Returns a paginated list of billing queue entries, optionally filtered
    // by status.
    //
    // Returns: ['data'=>[], 'total'=>int, 'page'=>int, 'pages'=>int]
    // -----------------------------------------------------------------------
    public function getQueue(string $status = '', int $page = 1, string $priority = '', int $pageSize = PAGE_SIZE): array
    {
        $sql = 'SELECT bq.*,
                       v.company_name  AS vendor_name,
                       u.name          AS assigned_to_name,
                       mb.title        AS media_buy_title,
                       c.title         AS campaign_title
                  FROM billing_queue bq
             LEFT JOIN vendors    v  ON v.id  = bq.vendor_id
             LEFT JOIN users      u  ON u.id  = bq.assigned_to
             LEFT JOIN media_buys mb ON mb.id = bq.media_buy_id
             LEFT JOIN campaigns  c  ON c.id  = bq.campaign_id';

        $params = [];

        if ($status !== '') {
            $sql               .= ' WHERE bq.status = :status';
            $params[':status'] = $status;
        }

        $sql .= ' ORDER BY bq.created_at DESC';

        return $this->paginate($sql, $params, $page, $pageSize);
    }

    // -----------------------------------------------------------------------
    // getBill()
    // Fetches a single billing queue entry by ID.
    //
    // Returns: associative array of the row, or [] if not found.
    // -----------------------------------------------------------------------
    public function getBill(int $id): array
    {
        $stmt = $this->db->prepare(
            'SELECT bq.*,
                    v.company_name                          AS vendor_name,
                    v.email                                 AS vendor_email,
                    u.name                                 AS assigned_to_name,
                    mb.title                                AS media_buy_title,
                    mb.agreed_cost                          AS media_buy_agreed_cost,
                    c.title                                 AS campaign_title
               FROM billing_queue bq
          LEFT JOIN vendors    v  ON v.id  = bq.vendor_id
          LEFT JOIN users      u  ON u.id  = bq.assigned_to
          LEFT JOIN media_buys mb ON mb.id = bq.media_buy_id
          LEFT JOIN campaigns  c  ON c.id  = bq.campaign_id
              WHERE bq.id = :id
              LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);

        return $bill !== false ? $bill : [];
    }

    // -----------------------------------------------------------------------
    // createBill()
    // Inserts a new billing queue entry.
    //
    // $data keys: vendor_id, media_buy_id, invoice_number, invoice_date,
    //             due_date, amount, notes, source, vendor_email
    //
    // Returns: ['success'=>bool, 'id'=>int, 'message'=>string]
    // -----------------------------------------------------------------------
    public function createBill(array $data): array
    {
        $vendorId      = (int)   ($data['vendor_id']      ?? 0);
        $campaignId    = (int)   ($data['campaign_id']    ?? 0);
        $mediaBuyId    = (int)   ($data['media_buy_id']   ?? 0);
        $invoiceNumber = trim($data['invoice_number']      ?? '');
        $invoiceDate   = $data['invoice_date']             ?? null;
        $dueDate       = $data['due_date']                 ?? null;
        $amount        = (float) ($data['amount']          ?? 0.00);
        $notes         = trim($data['notes']               ?? '');
        $source        = trim($data['source']              ?? 'manual');
        $vendorEmail   = trim($data['vendor_email']        ?? '');

        $stmt = $this->db->prepare(
            'INSERT INTO billing_queue
                 (vendor_id, campaign_id, media_buy_id, invoice_number, invoice_date, due_date,
                  amount, notes, status, source, vendor_email, created_at, updated_at)
             VALUES
                 (:vendor_id, :campaign_id, :media_buy_id, :invoice_number, :invoice_date, :due_date,
                  :amount, :notes, "pending", :source, :vendor_email, NOW(), NOW())'
        );
        $stmt->execute([
            ':vendor_id'      => $vendorId      > 0 ? $vendorId      : null,
            ':campaign_id'    => $campaignId    > 0 ? $campaignId    : null,
            ':media_buy_id'   => $mediaBuyId    > 0 ? $mediaBuyId    : null,
            ':invoice_number' => $invoiceNumber,
            ':invoice_date'   => $invoiceDate,
            ':due_date'       => $dueDate,
            ':amount'         => $amount,
            ':notes'          => $notes,
            ':source'         => $source,
            ':vendor_email'   => $vendorEmail,
        ]);

        $newId = $this->lastInsertId();

        $this->auditLog(
            'create_bill',
            'billing',
            $newId,
            "Invoice: {$invoiceNumber} | Amount: {$amount} | Source: {$source}"
        );

        return ['success' => true, 'id' => $newId, 'message' => 'Bill created successfully.'];
    }

    // -----------------------------------------------------------------------
    // updateBillStatus()
    // Changes the status of a billing queue entry and appends an optional
    // note to its history.
    // -----------------------------------------------------------------------
    public function updateBillStatus(int $billId, string $status, string $notes = ''): void
    {
        $stmt = $this->db->prepare(
            'UPDATE billing_queue
                SET status     = :status,
                    updated_at = NOW()
              WHERE id = :id'
        );
        $stmt->execute([':status' => $status, ':id' => $billId]);

        if ($notes !== '') {
            // Append to notes field
            $this->db->prepare(
                'UPDATE billing_queue
                    SET notes = CONCAT(COALESCE(notes, ""), "\n", :note)
                  WHERE id = :id'
            )->execute([':note' => '[' . date('Y-m-d H:i') . '] ' . $notes, ':id' => $billId]);
        }

        $this->auditLog(
            'update_bill_status',
            'billing',
            $billId,
            "Status changed to {$status}" . ($notes !== '' ? ": {$notes}" : '')
        );
    }

    // -----------------------------------------------------------------------
    // assignBill()
    // Assigns a billing queue entry to a staff user.
    // -----------------------------------------------------------------------
    public function assignBill(int $billId, int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE billing_queue
                SET assigned_to = :user_id,
                    updated_at  = NOW()
              WHERE id = :id'
        );
        $stmt->execute([':user_id' => $userId, ':id' => $billId]);

        $this->auditLog('assign_bill', 'billing', $billId, "Assigned to user #{$userId}");
    }

    // -----------------------------------------------------------------------
    // getQueueCounts()
    // Returns counts of billing entries grouped by status for the dashboard.
    //
    // Returns: ['pending'=>int, 'processing'=>int, 'paid'=>int, 'disputed'=>int, 'cancelled'=>int]
    // -----------------------------------------------------------------------
    public function getQueueCounts(): array
    {
        $stmt = $this->db->query(
            'SELECT status, COUNT(*) AS cnt FROM billing_queue GROUP BY status'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rows as $row) {
            $map[$row['status']] = (int) $row['cnt'];
        }

        return [
            'pending'    => $map['pending']    ?? 0,
            'processing' => $map['processing'] ?? 0,
            'paid'       => $map['paid']       ?? 0,
            'disputed'   => $map['disputed']   ?? 0,
            'cancelled'  => $map['cancelled']  ?? 0,
        ];
    }

    // -----------------------------------------------------------------------
    // getVendors()
    // Returns a simple list of all active vendors for select dropdowns.
    //
    // Returns: array of ['id'=>int, 'company_name'=>string] rows
    // -----------------------------------------------------------------------
    public function getVendors(): array
    {
        $stmt = $this->db->query(
            'SELECT id, company_name, email
               FROM vendors
              WHERE is_active = 1
              ORDER BY company_name ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
