<?php
/**
 * src/AdScheduleService.php
 * Manages annual per-client publication ad placement schedules.
 */

class AdScheduleService extends BaseService
{
    // ── Plans ──────────────────────────────────────────────────────────────────

    /** All plans with client name, sorted by year desc then client name. */
    public function getPlans(): array
    {
        return $this->db->query(
            'SELECT p.*, c.company_name,
                    COUNT(pl.id)                                          AS placement_count,
                    SUM(pl.cost_to_client)                               AS total_client_cost,
                    SUM(pl.placement_invoiced)                           AS billed_count,
                    SUM(
                        pl.run_date IS NOT NULL
                        AND pl.run_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                        AND pl.placement_invoiced = 0
                    )                                                    AS overdue_count
               FROM annual_ad_plans p
               JOIN clients c ON c.id = p.client_id
          LEFT JOIN annual_ad_placements pl ON pl.plan_id = p.id
           GROUP BY p.id
           ORDER BY p.year DESC, c.company_name ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPlan(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, c.company_name
               FROM annual_ad_plans p
               JOIN clients c ON c.id = p.client_id
              WHERE p.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getPlanByClientYear(int $clientId, int $year): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, c.company_name
               FROM annual_ad_plans p
               JOIN clients c ON c.id = p.client_id
              WHERE p.client_id = :cid AND p.year = :year LIMIT 1'
        );
        $stmt->execute([':cid' => $clientId, ':year' => $year]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Get or create a plan for client+year. Returns plan id. */
    public function ensurePlan(int $clientId, int $year): int
    {
        $plan = $this->getPlanByClientYear($clientId, $year);
        if ($plan) return (int) $plan['id'];

        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $this->db->prepare(
            'INSERT INTO annual_ad_plans (client_id, year, created_by) VALUES (:cid, :year, :uid)'
        )->execute([':cid' => $clientId, ':year' => $year, ':uid' => $userId ?: null]);
        return (int) $this->db->lastInsertId();
    }

    public function deletePlan(int $id): void
    {
        $this->db->prepare('DELETE FROM annual_ad_plans WHERE id = :id')->execute([':id' => $id]);
    }

    // ── Placements ─────────────────────────────────────────────────────────────

    public function getPlacements(int $planId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM annual_ad_placements
              WHERE plan_id = :pid
              ORDER BY sort_order ASC, run_date ASC, id ASC'
        );
        $stmt->execute([':pid' => $planId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPlacement(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM annual_ad_placements WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function savePlacement(array $data): int
    {
        $id     = (int) ($data['id'] ?? 0);
        $planId = (int) ($data['plan_id'] ?? 0);

        $fields = [
            ':plan_id'                  => $planId,
            ':client_id'                => (int) ($data['client_id'] ?? 0),
            ':publication'              => trim($data['publication']   ?? ''),
            ':contact_name'             => trim($data['contact_name']  ?? '') ?: null,
            ':editorial'                => trim($data['editorial']      ?? '') ?: null,
            ':ad_number'                => trim($data['ad_number']      ?? '') ?: null,
            ':run_date'                 => $data['run_date']               ?: null,
            ':artwork_deadline'         => $data['artwork_deadline']       ?: null,
            ':client_approval_deadline' => $data['client_approval_deadline'] ?: null,
            ':ad_size'                  => trim($data['ad_size']           ?? '') ?: null,
            ':circulation'              => $data['circulation'] !== '' ? (int)$data['circulation'] : null,
            ':num_ads'                  => max(1, (int)($data['num_ads']   ?? 1)),
            ':cost_to_agency'           => $data['cost_to_agency']  !== '' ? round((float)$data['cost_to_agency'], 2)  : null,
            ':cost_to_client'           => $data['cost_to_client']  !== '' ? round((float)$data['cost_to_client'], 2)  : null,
            ':markup_pct'               => $data['markup_pct']       !== '' ? round((float)$data['markup_pct'], 2)     : null,
            ':notes'                    => trim($data['notes']       ?? '') ?: null,
            ':sort_order'               => (int) ($data['sort_order'] ?? 0),
        ];

        if ($id > 0) {
            $fields[':id'] = $id;
            $this->db->prepare(
                'UPDATE annual_ad_placements
                    SET plan_id = :plan_id, client_id = :client_id,
                        publication = :publication, contact_name = :contact_name,
                        editorial = :editorial, ad_number = :ad_number,
                        run_date = :run_date, artwork_deadline = :artwork_deadline,
                        client_approval_deadline = :client_approval_deadline,
                        ad_size = :ad_size, circulation = :circulation, num_ads = :num_ads,
                        cost_to_agency = :cost_to_agency, cost_to_client = :cost_to_client,
                        markup_pct = :markup_pct, notes = :notes, sort_order = :sort_order,
                        updated_at = NOW()
                  WHERE id = :id'
            )->execute($fields);
            return $id;
        }

        $this->db->prepare(
            'INSERT INTO annual_ad_placements
                 (plan_id, client_id, publication, contact_name, editorial, ad_number,
                  run_date, artwork_deadline, client_approval_deadline,
                  ad_size, circulation, num_ads,
                  cost_to_agency, cost_to_client, markup_pct,
                  notes, sort_order)
             VALUES
                 (:plan_id, :client_id, :publication, :contact_name, :editorial, :ad_number,
                  :run_date, :artwork_deadline, :client_approval_deadline,
                  :ad_size, :circulation, :num_ads,
                  :cost_to_agency, :cost_to_client, :markup_pct,
                  :notes, :sort_order)'
        )->execute($fields);
        return (int) $this->db->lastInsertId();
    }

    public function deletePlacement(int $id): void
    {
        $this->db->prepare('DELETE FROM annual_ad_placements WHERE id = :id')->execute([':id' => $id]);
    }

    /**
     * Toggle a single boolean field on a placement.
     * Also stamps the *_at timestamp when toggling billing fields to true.
     */
    public function toggleField(int $id, string $field): bool
    {
        $allowed = [
            'is_reserved', 'is_designed', 'is_sent_to_client',
            'is_sent_to_publication', 'design_invoiced', 'placement_invoiced',
        ];
        if (!in_array($field, $allowed, true)) return false;

        // Read current value
        $stmt = $this->db->prepare("SELECT {$field} FROM annual_ad_placements WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;

        $newVal = $row[$field] ? 0 : 1;
        $extra  = '';
        if ($field === 'design_invoiced') {
            $extra = ', design_invoiced_at = ' . ($newVal ? 'NOW()' : 'NULL');
        } elseif ($field === 'placement_invoiced') {
            $extra = ', placement_invoiced_at = ' . ($newVal ? 'NOW()' : 'NULL');
        }

        $this->db->prepare(
            "UPDATE annual_ad_placements SET {$field} = :val{$extra}, updated_at = NOW() WHERE id = :id"
        )->execute([':val' => $newVal, ':id' => $id]);

        return (bool) $newVal;
    }

    // ── Alerts ─────────────────────────────────────────────────────────────────

    /**
     * Placements where run_date was 7+ days ago and placement is not yet billed.
     */
    public function getOverdueUnbilled(?int $clientId = null): array
    {
        $where = 'pl.run_date IS NOT NULL
              AND pl.run_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
              AND pl.placement_invoiced = 0';
        $params = [];

        if ($clientId) {
            $where  .= ' AND pl.client_id = :cid';
            $params[':cid'] = $clientId;
        }

        $stmt = $this->db->prepare(
            "SELECT pl.*, c.company_name, p.year,
                    DATEDIFF(CURDATE(), pl.run_date) AS days_overdue
               FROM annual_ad_placements pl
               JOIN annual_ad_plans p ON p.id = pl.plan_id
               JOIN clients c ON c.id = pl.client_id
              WHERE {$where}
              ORDER BY pl.run_date ASC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOverdueCount(): int
    {
        return (int) $this->db->query(
            'SELECT COUNT(*) FROM annual_ad_placements
              WHERE run_date IS NOT NULL
                AND run_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                AND placement_invoiced = 0'
        )->fetchColumn();
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function getClients(): array
    {
        return $this->db->query(
            'SELECT id, company_name FROM clients WHERE is_active = 1 ORDER BY company_name ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Years that have plans, plus surrounding range for the picker. */
    public function getAvailableYears(): array
    {
        $rows = $this->db->query(
            'SELECT DISTINCT year FROM annual_ad_plans ORDER BY year DESC'
        )->fetchAll(PDO::FETCH_COLUMN);

        $current = (int) date('Y');
        $range   = range($current - 3, $current + 1);
        return array_values(array_unique(array_merge((array)$rows, $range)));
    }

    /** Plan summary stats for a single plan. */
    public function getPlanStats(int $planId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                COUNT(*)                                                       AS total,
                SUM(cost_to_agency)                                            AS total_agency_cost,
                SUM(cost_to_client)                                            AS total_client_cost,
                SUM(placement_invoiced)                                        AS billed_count,
                SUM(
                    run_date IS NOT NULL
                    AND run_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    AND placement_invoiced = 0
                )                                                              AS overdue_count
             FROM annual_ad_placements
             WHERE plan_id = :pid'
        );
        $stmt->execute([':pid' => $planId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
