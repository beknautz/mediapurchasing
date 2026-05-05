<?php
/**
 * src/AgencyAgreementService.php
 * CRUD for the Agency Agreement document type.
 *
 * Each agreement stores client details, a list of marketing services,
 * a pricing section, and a four-category budget breakdown table.
 * The heavy legal boilerplate (Terms + MOU) is rendered statically in the
 * view; only the variable fields are stored in the DB.
 */
class AgencyAgreementService extends BaseService
{
    const STATUS_LABELS = [
        'draft'    => 'Draft',
        'sent'     => 'Sent',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
    ];

    const STATUS_COLORS = [
        'draft'    => 'secondary',
        'sent'     => 'primary',
        'accepted' => 'success',
        'rejected' => 'danger',
    ];

    // Default services matching the PDF template
    const DEFAULT_SERVICES = [
        'Graphic Design',
        'TV/Radio Production',
        'Social Media Creative(s)',
        'Media Buying and Placement of all Marketing',
    ];

    // -----------------------------------------------------------------------
    // List
    // -----------------------------------------------------------------------
    public function getAll(int $page = 1, int $pageSize = 25): array
    {
        $sql = 'SELECT a.id, a.title, a.status, a.total_amount,
                       a.client_name, a.contract_start, a.contract_end,
                       a.created_at, a.sent_at, a.accepted_at,
                       u.name AS created_by_name
                  FROM agency_agreements a
             LEFT JOIN users u ON u.id = a.created_by
              ORDER BY a.created_at DESC';
        return $this->paginate($sql, [], $page, $pageSize);
    }

    // -----------------------------------------------------------------------
    // Single
    // -----------------------------------------------------------------------
    public function get(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT a.*, u.name AS created_by_name
               FROM agency_agreements a
          LEFT JOIN users u ON u.id = a.created_by
              WHERE a.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        // Decode JSON columns
        $row['services']       = json_decode($row['services_json']       ?? '[]', true) ?: [];
        $row['budget_tv']      = json_decode($row['budget_tv_json']      ?? '[]', true) ?: [];
        $row['budget_radio']   = json_decode($row['budget_radio_json']   ?? '[]', true) ?: [];
        $row['budget_news']    = json_decode($row['budget_newspaper_json']?? '[]', true) ?: [];
        $row['budget_social']  = json_decode($row['budget_social_json']  ?? '[]', true) ?: [];

        return $row;
    }

    // -----------------------------------------------------------------------
    // Save (insert or update)
    // -----------------------------------------------------------------------
    public function save(array $d): int
    {
        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $id     = (int)($d['id'] ?? 0);

        $fields = [
            ':title'                  => trim($d['title']                 ?? ''),
            ':status'                 => $d['status']                     ?? 'draft',
            ':client_id'              => ($d['client_id'] ?? 0) ?: null,
            ':client_name'            => trim($d['client_name']           ?? ''),
            ':client_address'         => trim($d['client_address']        ?? ''),
            ':client_city_state_zip'  => trim($d['client_city_state_zip'] ?? ''),
            ':client_phone'           => trim($d['client_phone']          ?? ''),
            ':client_representative'  => trim($d['client_representative'] ?? ''),
            ':client_title'           => trim($d['client_title']          ?? ''),
            ':contract_start'         => $d['contract_start']             ?: null,
            ':contract_end'           => $d['contract_end']               ?: null,
            ':services_json'          => json_encode($d['services']       ?? []),
            ':deposit_amount'         => (float)($d['deposit_amount']     ?? 0),
            ':deposit_due_description'=> trim($d['deposit_due_description']?? ''),
            ':balance_amount'         => (float)($d['balance_amount']     ?? 0),
            ':balance_due_description'=> trim($d['balance_due_description']?? ''),
            ':total_amount'           => (float)($d['total_amount']       ?? 0),
            ':budget_tv_json'         => json_encode($d['budget_tv']      ?? []),
            ':budget_radio_json'      => json_encode($d['budget_radio']   ?? []),
            ':budget_newspaper_json'  => json_encode($d['budget_news']    ?? []),
            ':budget_social_json'     => json_encode($d['budget_social']  ?? []),
            ':contingency_monthly'    => ($d['contingency_monthly'] !== '' && $d['contingency_monthly'] !== null)
                                          ? (float)$d['contingency_monthly'] : null,
            ':mileage_rate'           => (float)($d['mileage_rate']       ?? 0.60),
            ':hourly_rate'            => (float)($d['hourly_rate']        ?? 80.00),
            ':additional_notes'       => trim($d['additional_notes']      ?? '') ?: null,
        ];

        if ($id > 0) {
            $set = implode(', ', array_map(fn($k) => ltrim($k,':') . ' = ' . $k, array_keys($fields)));
            $this->db->prepare("UPDATE agency_agreements SET $set, updated_at = NOW() WHERE id = :id_where")
                     ->execute($fields + [':id_where' => $id]);
            return $id;
        }

        $cols = implode(', ', array_map(fn($k) => ltrim($k,':'), array_keys($fields)));
        $phs  = implode(', ', array_keys($fields));
        $this->db->prepare("INSERT INTO agency_agreements ($cols, created_by, created_at)
                             VALUES ($phs, :created_by, NOW())")
                 ->execute($fields + [':created_by' => $userId]);
        return $this->lastInsertId();
    }

    // -----------------------------------------------------------------------
    // Status
    // -----------------------------------------------------------------------
    public function updateStatus(int $id, string $status): void
    {
        if (!array_key_exists($status, self::STATUS_LABELS)) {
            throw new InvalidArgumentException('Invalid status: ' . $status);
        }
        $extra = match($status) {
            'sent'     => ', sent_at     = COALESCE(sent_at,     NOW())',
            'accepted' => ', accepted_at = COALESCE(accepted_at, NOW())',
            default    => '',
        };
        $this->db->prepare("UPDATE agency_agreements SET status = :s $extra WHERE id = :id")
                 ->execute([':s' => $status, ':id' => $id]);
    }

    // -----------------------------------------------------------------------
    // Delete
    // -----------------------------------------------------------------------
    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM agency_agreements WHERE id = :id')
                 ->execute([':id' => $id]);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /** Sum all budget line items across all four categories */
    public function budgetTotal(array $agreement): float
    {
        $total = 0.0;
        foreach (['budget_tv','budget_radio','budget_news','budget_social'] as $cat) {
            foreach ($agreement[$cat] ?? [] as $row) {
                $total += (float)($row['amount'] ?? 0);
            }
        }
        return $total;
    }

    public function getClients(): array
    {
        try {
            $stmt = $this->db->query(
                'SELECT id, company_name FROM clients WHERE active = 1 ORDER BY company_name ASC'
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
}
