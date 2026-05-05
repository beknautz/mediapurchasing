<?php
/**
 * src/AgencyAgreementService.php
 * CRUD for the Agency Agreement document type.
 *
 * Budget categories are fully dynamic — stored as a single budget_json column:
 *   [{"label":"TV","rows":[{"name":"KIMA","amount":850}]}, ...]
 *
 * Agency branding (name, logo, address, signer) is read from workflow_settings
 * via $GLOBALS['appSettings'] and falls back to sensible defaults.
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

    // Default budget categories — label + one empty row each
    const DEFAULT_BUDGET_CATEGORIES = [
        ['label' => 'TV',           'rows' => [['name' => '', 'amount' => 0]]],
        ['label' => 'Radio',        'rows' => [['name' => '', 'amount' => 0]]],
        ['label' => 'Newspaper',    'rows' => [['name' => '', 'amount' => 0]]],
        ['label' => 'Social Media', 'rows' => [['name' => '', 'amount' => 0]]],
    ];

    // -----------------------------------------------------------------------
    // Agency branding helpers — read from workflow_settings with fallbacks
    // -----------------------------------------------------------------------
    public function getBranding(): array
    {
        $s = $GLOBALS['appSettings'] ?? [];
        return [
            'name'         => $s['agency_name']          ?? 'Enigma, Inc. DBA Enigma Marketing',
            'dba'          => $s['agency_dba']            ?? 'Enigma Marketing',
            'address'      => $s['agency_address']        ?? '3601 W Washington STE 130',
            'city_state_zip' => $s['agency_city_state_zip'] ?? 'Yakima, WA 98903',
            'phone'        => $s['agency_phone']          ?? '509-452-3733',
            'signer_name'  => $s['agency_signer_name']   ?? 'Duane Gordon',
            'signer_title' => $s['agency_signer_title']  ?? 'Managing Partner',
            'logo_url'     => $s['agency_logo_url']       ?? '',
        ];
    }

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

        $row['services']          = json_decode($row['services_json'] ?? '[]', true) ?: [];
        $row['budget_categories'] = json_decode($row['budget_json']   ?? '[]', true) ?: [];

        // Ensure at least default categories if empty
        if (empty($row['budget_categories'])) {
            $row['budget_categories'] = self::DEFAULT_BUDGET_CATEGORIES;
        }

        return $row;
    }

    // -----------------------------------------------------------------------
    // Save (insert or update)
    // -----------------------------------------------------------------------
    public function save(array $d): int
    {
        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $id     = (int)($d['id'] ?? 0);

        // Sanitize budget categories
        $budgetCats = [];
        foreach ((array)($d['budget_categories'] ?? []) as $cat) {
            $label = trim($cat['label'] ?? '');
            if ($label === '') continue;
            $rows = [];
            foreach ((array)($cat['rows'] ?? []) as $row) {
                $name = trim($row['name'] ?? '');
                $amt  = (float)($row['amount'] ?? 0);
                if ($name !== '' || $amt > 0) {
                    $rows[] = ['name' => $name, 'amount' => $amt];
                }
            }
            $budgetCats[] = ['label' => $label, 'rows' => $rows];
        }

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
            ':budget_json'            => json_encode($budgetCats),
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
    // Budget total
    // -----------------------------------------------------------------------
    public function budgetTotal(array $agreement): float
    {
        $total = 0.0;
        foreach ($agreement['budget_categories'] ?? [] as $cat) {
            foreach ($cat['rows'] ?? [] as $row) {
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
