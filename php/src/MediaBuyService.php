<?php
/**
 * src/MediaBuyService.php
 * Media Buying Platform — media buy management service
 */

class MediaBuyService extends BaseService
{
    // -----------------------------------------------------------------------
    // getMediaBuys()
    // Returns a paginated list of media buys with optional filters.
    //
    // Returns: ['data'=>[], 'total'=>int, 'page'=>int, 'pages'=>int]
    // -----------------------------------------------------------------------
    public function getMediaBuys(
        int    $clientId  = 0,
        int    $vendorId  = 0,
        int    $buyerId   = 0,
        string $status    = '',
        int    $page      = 1,
        int    $pageSize  = 25
    ): array {
        $sql = 'SELECT mb.id,
                       mb.title,
                       mb.status,
                       mb.total_cost,
                       mb.agreed_cost,
                       mb.notes,
                       mb.created_at,
                       mb.updated_at,
                       c.company_name  AS client_name,
                       v.company_name  AS vendor_name,
                       u.name                                 AS buyer_name
                  FROM media_buys mb
             LEFT JOIN clients  c ON c.id = mb.client_id
             LEFT JOIN vendors  v ON v.id = mb.vendor_id
             LEFT JOIN users    u ON u.id = mb.buyer_id';

        $conditions = [];
        $params     = [];

        if ($clientId > 0) {
            $conditions[]          = 'mb.client_id = :client_id';
            $params[':client_id']  = $clientId;
        }

        if ($vendorId > 0) {
            $conditions[]          = 'mb.vendor_id = :vendor_id';
            $params[':vendor_id']  = $vendorId;
        }

        if ($buyerId > 0) {
            $conditions[]         = 'mb.buyer_id = :buyer_id';
            $params[':buyer_id']  = $buyerId;
        }

        if ($status !== '') {
            $conditions[]      = 'mb.status = :status';
            $params[':status'] = $status;
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY mb.updated_at DESC, mb.id DESC';

        return $this->paginate($sql, $params, $page, $pageSize);
    }

    // -----------------------------------------------------------------------
    // getMediaBuy()
    // Fetches a single media buy with its line items, negotiations, and
    // approvals.
    //
    // Returns: ['buy'=>row, 'items'=>rows, 'negotiations'=>rows, 'approvals'=>rows]
    //          or [] when not found.
    // -----------------------------------------------------------------------
    public function getMediaBuy(int $id): array
    {
        $stmt = $this->db->prepare(
            'SELECT mb.*,
                    c.company_name  AS client_name,
                    c.email         AS client_email,
                    v.company_name  AS vendor_name,
                    v.email         AS vendor_email,
                    u.name                                 AS buyer_name
               FROM media_buys mb
          LEFT JOIN clients c ON c.id = mb.client_id
          LEFT JOIN vendors v ON v.id = mb.vendor_id
          LEFT JOIN users   u ON u.id = mb.buyer_id
              WHERE mb.id = :id
              LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $buy = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$buy) {
            return [];
        }

        // Line items
        $itemStmt = $this->db->prepare(
            'SELECT * FROM media_buy_items WHERE media_buy_id = :id ORDER BY sort_order ASC, id ASC'
        );
        $itemStmt->execute([':id' => $id]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        // Negotiations
        $negStmt = $this->db->prepare(
            'SELECT n.*, u.name AS negotiator_name
               FROM media_buy_negotiations n
          LEFT JOIN users u ON u.id = n.user_id
              WHERE n.media_buy_id = :id
              ORDER BY n.created_at ASC'
        );
        $negStmt->execute([':id' => $id]);
        $negotiations = $negStmt->fetchAll(PDO::FETCH_ASSOC);

        // Approvals
        $appStmt = $this->db->prepare(
            'SELECT a.*
               FROM media_buy_approvals a
              WHERE a.media_buy_id = :id
              ORDER BY a.created_at DESC'
        );
        $appStmt->execute([':id' => $id]);
        $approvals = $appStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'buy'          => $buy,
            'items'        => $items,
            'negotiations' => $negotiations,
            'approvals'    => $approvals,
        ];
    }

    // -----------------------------------------------------------------------
    // saveMediaBuy()
    // Inserts a new media buy or updates an existing one. Delegates line items
    // to saveLineItems().
    //
    // $data keys: id (0=insert), title, client_id, vendor_id, buyer_id,
    //             status, notes, items (array of line-item arrays)
    //
    // Returns: ['success'=>bool, 'id'=>int, 'message'=>string]
    // -----------------------------------------------------------------------
    public function saveMediaBuy(array $data): array
    {
        $id       = (int) ($data['id']        ?? 0);
        $title    = trim($data['title']        ?? '');
        $clientId = (int) ($data['client_id']  ?? 0);
        $vendorId = (int) ($data['vendor_id']  ?? 0);
        $buyerId  = (int) ($data['buyer_id']   ?? 0);
        $status   = trim($data['status']       ?? 'draft');
        $notes    = trim($data['notes']        ?? '');
        $items    = $data['items']             ?? [];

        if ($title === '') {
            return ['success' => false, 'id' => 0, 'message' => 'Title is required.'];
        }

        if ($clientId === 0) {
            return ['success' => false, 'id' => 0, 'message' => 'Client is required.'];
        }

        if ($id === 0) {
            // ---- INSERT ----
            $stmt = $this->db->prepare(
                'INSERT INTO media_buys
                     (title, client_id, vendor_id, buyer_id, status, notes, created_at, updated_at)
                 VALUES
                     (:title, :client_id, :vendor_id, :buyer_id, :status, :notes, NOW(), NOW())'
            );
            $stmt->execute([
                ':title'     => $title,
                ':client_id' => $clientId,
                ':vendor_id' => $vendorId,
                ':buyer_id'  => $buyerId,
                ':status'    => $status,
                ':notes'     => $notes,
            ]);

            $newId = $this->lastInsertId();

            if (!empty($items)) {
                $this->saveLineItems($newId, $items);
            }

            $this->auditLog('create_media_buy', 'media_buy', $newId, "Created: {$title}");

            return ['success' => true, 'id' => $newId, 'message' => 'Media buy created successfully.'];
        }

        // ---- UPDATE ----
        $stmt = $this->db->prepare(
            'UPDATE media_buys
                SET title     = :title,
                    client_id = :client_id,
                    vendor_id = :vendor_id,
                    buyer_id  = :buyer_id,
                    status    = :status,
                    notes     = :notes,
                    updated_at = NOW()
              WHERE id = :id'
        );
        $stmt->execute([
            ':title'     => $title,
            ':client_id' => $clientId,
            ':vendor_id' => $vendorId,
            ':buyer_id'  => $buyerId,
            ':status'    => $status,
            ':notes'     => $notes,
            ':id'        => $id,
        ]);

        if (!empty($items)) {
            $this->saveLineItems($id, $items);
        }

        $this->auditLog('update_media_buy', 'media_buy', $id, "Updated: {$title}");

        return ['success' => true, 'id' => $id, 'message' => 'Media buy updated successfully.'];
    }

    // -----------------------------------------------------------------------
    // updateStatus()
    // Changes the status of a media buy and optionally appends notes.
    // -----------------------------------------------------------------------
    public function updateStatus(int $id, string $status, string $notes = ''): void
    {
        $stmt = $this->db->prepare(
            'UPDATE media_buys
                SET status     = :status,
                    updated_at = NOW()
              WHERE id = :id'
        );
        $stmt->execute([':status' => $status, ':id' => $id]);

        $detail = "Status changed to {$status}" . ($notes !== '' ? ": {$notes}" : '');
        $this->auditLog('status_change', 'media_buy', $id, $detail);
    }

    // -----------------------------------------------------------------------
    // saveNegotiation()
    // Inserts a negotiation note/counter-offer row.
    //
    // $data keys: media_buy_id, user_id, proposed_cost, notes, negotiation_type
    //
    // Returns: int — the new negotiation row id
    // -----------------------------------------------------------------------
    public function saveNegotiation(array $data): int
    {
        $mediaBuyId      = (int) ($data['media_buy_id']    ?? 0);
        $userId          = (int) ($data['user_id']         ?? ($_SESSION['user']['id'] ?? 0));
        $proposedCost    = (float) ($data['proposed_cost'] ?? 0.00);
        $notes           = trim($data['notes']             ?? '');
        $negotiationType = trim($data['negotiation_type']  ?? 'counter_offer');

        $stmt = $this->db->prepare(
            'INSERT INTO media_buy_negotiations
                 (media_buy_id, user_id, proposed_cost, notes, negotiation_type, created_at)
             VALUES
                 (:media_buy_id, :user_id, :proposed_cost, :notes, :negotiation_type, NOW())'
        );
        $stmt->execute([
            ':media_buy_id'    => $mediaBuyId,
            ':user_id'         => $userId,
            ':proposed_cost'   => $proposedCost,
            ':notes'           => $notes,
            ':negotiation_type'=> $negotiationType,
        ]);

        $newId = $this->lastInsertId();

        // Update the media buy status to 'negotiating' if not already further along
        $this->db->prepare(
            "UPDATE media_buys
                SET status     = 'negotiating',
                    updated_at = NOW()
              WHERE id = :id
                AND status NOT IN ('approved', 'finalized', 'cancelled')"
        )->execute([':id' => $mediaBuyId]);

        $this->auditLog('save_negotiation', 'media_buy', $mediaBuyId, "Proposed cost: {$proposedCost}");

        return $newId;
    }

    // -----------------------------------------------------------------------
    // finalizeNegotiation()
    // Stamps the agreed cost and sets the status to 'approved'.
    // -----------------------------------------------------------------------
    public function finalizeNegotiation(int $mediaBuyId, float $agreedCost): void
    {
        $stmt = $this->db->prepare(
            "UPDATE media_buys
                SET agreed_cost = :agreed_cost,
                    status      = 'approved',
                    updated_at  = NOW()
              WHERE id = :id"
        );
        $stmt->execute([':agreed_cost' => $agreedCost, ':id' => $mediaBuyId]);

        $this->auditLog(
            'finalize_negotiation',
            'media_buy',
            $mediaBuyId,
            "Agreed cost: {$agreedCost}"
        );
    }

    // -----------------------------------------------------------------------
    // getDashboardCounts()
    // Returns counts grouped by status for the dashboard summary.
    //
    // Returns:
    //   ['drafts'=>int, 'pending_approval'=>int, 'negotiating'=>int,
    //    'approved'=>int, 'finalized'=>int]
    // -----------------------------------------------------------------------
    public function getDashboardCounts(): array
    {
        $stmt = $this->db->query(
            "SELECT status, COUNT(*) AS cnt
               FROM media_buys
              GROUP BY status"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rows as $row) {
            $map[$row['status']] = (int) $row['cnt'];
        }

        return [
            'drafts'           => $map['draft']            ?? 0,
            'pending_approval' => $map['pending_approval'] ?? 0,
            'negotiating'      => $map['negotiating']      ?? 0,
            'approved'         => $map['approved']         ?? 0,
            'finalized'        => $map['finalized']        ?? 0,
        ];
    }

    // -----------------------------------------------------------------------
    // saveLineItems()  [private]
    // Replaces all line items for a media buy. Deletes existing rows first,
    // then re-inserts from the provided array.
    //
    // $items — array of associative arrays with keys:
    //   description, media_type, start_date, end_date, quantity,
    //   unit_cost, total_cost, notes
    // -----------------------------------------------------------------------
    private function saveLineItems(int $buyId, array $items): void
    {
        // Remove all existing items for this buy
        $del = $this->db->prepare('DELETE FROM media_buy_items WHERE media_buy_id = :id');
        $del->execute([':id' => $buyId]);

        if (empty($items)) {
            return;
        }

        $insert = $this->db->prepare(
            'INSERT INTO media_buy_items
                 (media_buy_id, description, media_type, start_date, end_date,
                  quantity, unit_cost, total_cost, notes, sort_order, created_at)
             VALUES
                 (:media_buy_id, :description, :media_type, :start_date, :end_date,
                  :quantity, :unit_cost, :total_cost, :notes, :sort_order, NOW())'
        );

        $totalBuyCost = 0.0;

        foreach ($items as $sortOrder => $item) {
            $quantity  = (float) ($item['quantity']   ?? 1);
            $unitCost  = (float) ($item['unit_cost']  ?? 0.00);
            $totalCost = (float) ($item['total_cost'] ?? ($quantity * $unitCost));

            $totalBuyCost += $totalCost;

            $insert->execute([
                ':media_buy_id' => $buyId,
                ':description'  => trim($item['description'] ?? ''),
                ':media_type'   => trim($item['media_type']  ?? ''),
                ':start_date'   => $item['start_date']       ?? null,
                ':end_date'     => $item['end_date']         ?? null,
                ':quantity'     => $quantity,
                ':unit_cost'    => $unitCost,
                ':total_cost'   => $totalCost,
                ':notes'        => trim($item['notes']       ?? ''),
                ':sort_order'   => (int) $sortOrder,
            ]);
        }

        // Update the parent media buy's total_cost
        $this->db->prepare(
            'UPDATE media_buys SET total_cost = :total_cost, updated_at = NOW() WHERE id = :id'
        )->execute([':total_cost' => $totalBuyCost, ':id' => $buyId]);
    }
}
