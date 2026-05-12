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
              WHERE JSON_CONTAINS(COALESCE(service_options,'[]'), '\"Printing\"')
                 OR service_options LIKE '%\"Printing\"%'
           ORDER BY company_name"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }

    // -----------------------------------------------------------------------
    // getVendorsForSignage()
    // Returns vendors tagged "Signage" — sign shops, not billboard ad sellers.
    // Falls back to all vendors if none matched.
    // -----------------------------------------------------------------------
    public function getVendorsForSignage(): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, company_name FROM vendors
              WHERE JSON_CONTAINS(COALESCE(service_options,'[]'), '\"Signage\"')
                 OR service_options LIKE '%\"Signage\"%'
           ORDER BY company_name"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
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
