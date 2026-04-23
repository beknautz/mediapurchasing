<?php
/**
 * src/ProposalService.php
 */

class ProposalService extends BaseService
{
    const STATUS_LABELS = [
        'draft'    => 'Draft',
        'sent'     => 'Sent',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
        'expired'  => 'Expired',
    ];

    const STATUS_COLORS = [
        'draft'    => 'secondary',
        'sent'     => 'primary',
        'accepted' => 'success',
        'rejected' => 'danger',
        'expired'  => 'warning',
    ];

    // ── Proposals ──────────────────────────────────────────────────────────────

    public function getProposals(int $page = 1, int $pageSize = 25): array
    {
        $sql = 'SELECT p.id, p.title, p.status, p.total_amount, p.valid_until,
                       p.sent_at, p.accepted_at, p.created_at,
                       c.company_name AS client_name,
                       u.name AS created_by_name
                  FROM proposals p
             LEFT JOIN clients c ON c.id = p.client_id
             LEFT JOIN users u   ON u.id = p.created_by
              ORDER BY p.created_at DESC';
        return $this->paginate($sql, [], $page, $pageSize);
    }

    public function getProposal(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, c.company_name AS client_name, u.name AS created_by_name
               FROM proposals p
          LEFT JOIN clients c ON c.id = p.client_id
          LEFT JOIN users u   ON u.id = p.created_by
              WHERE p.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $proposal = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$proposal) return null;

        $iStmt = $this->db->prepare(
            'SELECT * FROM proposal_items
              WHERE proposal_id = :id
              ORDER BY sort_order ASC, id ASC'
        );
        $iStmt->execute([':id' => $id]);

        return [
            'proposal' => $proposal,
            'items'    => $iStmt->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function saveProposal(array $data): int
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $id     = (int) ($data['id'] ?? 0);

        $fields = [
            ':title'        => $data['title'],
            ':client_id'    => ($data['client_id'] ?? 0) ?: null,
            ':status'       => $data['status'] ?? 'draft',
            ':intro_text'   => $data['intro_text'] ?: null,
            ':notes'        => $data['notes']       ?: null,
            ':valid_until'  => $data['valid_until'] ?: null,
            ':total_amount' => round((float) ($data['total_amount'] ?? 0), 2),
        ];

        if ($id > 0) {
            $fields[':id'] = $id;
            $this->db->prepare(
                'UPDATE proposals
                    SET title = :title, client_id = :client_id, status = :status,
                        intro_text = :intro_text, notes = :notes,
                        valid_until = :valid_until, total_amount = :total_amount,
                        updated_at = NOW()
                  WHERE id = :id'
            )->execute($fields);
            return $id;
        }

        $fields[':created_by'] = $userId ?: null;
        $this->db->prepare(
            'INSERT INTO proposals
                 (title, client_id, status, intro_text, notes, valid_until,
                  total_amount, created_by, created_at, updated_at)
             VALUES
                 (:title, :client_id, :status, :intro_text, :notes, :valid_until,
                  :total_amount, :created_by, NOW(), NOW())'
        )->execute($fields);
        return (int) $this->db->lastInsertId();
    }

    public function saveItems(int $proposalId, array $items): void
    {
        $this->db->prepare('DELETE FROM proposal_items WHERE proposal_id = :id')
                 ->execute([':id' => $proposalId]);

        if (empty($items)) return;

        $stmt = $this->db->prepare(
            'INSERT INTO proposal_items
                 (proposal_id, sort_order, description, quantity, unit_price, total_price, created_at, updated_at)
             VALUES
                 (:proposal_id, :sort_order, :description, :qty, :unit_price, :total, NOW(), NOW())'
        );

        foreach ($items as $i => $item) {
            $qty   = max(0, (float) ($item['quantity']   ?? 1));
            $price = max(0, (float) ($item['unit_price'] ?? 0));
            $stmt->execute([
                ':proposal_id' => $proposalId,
                ':sort_order'  => (int) ($item['sort_order'] ?? $i),
                ':description' => trim($item['description'] ?? ''),
                ':qty'         => $qty,
                ':unit_price'  => $price,
                ':total'       => round($qty * $price, 2),
            ]);
        }
    }

    public function recalcTotal(int $proposalId): void
    {
        $this->db->prepare(
            'UPDATE proposals
                SET total_amount = (
                    SELECT COALESCE(SUM(total_price), 0)
                      FROM proposal_items
                     WHERE proposal_id = proposals.id
                ),
                updated_at = NOW()
              WHERE id = :id'
        )->execute([':id' => $proposalId]);
    }

    public function updateStatus(int $id, string $status): void
    {
        if (!array_key_exists($status, self::STATUS_LABELS)) return;

        $extra = '';
        if ($status === 'sent')     $extra = ', sent_at = NOW()';
        if ($status === 'accepted') $extra = ', accepted_at = NOW()';

        $this->db->prepare(
            "UPDATE proposals SET status = :status{$extra}, updated_at = NOW() WHERE id = :id"
        )->execute([':status' => $status, ':id' => $id]);
    }

    public function deleteProposal(int $id): void
    {
        $this->db->prepare('DELETE FROM proposals WHERE id = :id')
                 ->execute([':id' => $id]);
    }

    // ── Templates ──────────────────────────────────────────────────────────────

    public function getTemplates(): array
    {
        return $this->db->query(
            'SELECT t.*, u.name AS created_by_name
               FROM proposal_templates t
          LEFT JOIN users u ON u.id = t.created_by
           ORDER BY t.name ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Returns all templates with their items embedded — one query via JOIN.
     * Result keyed by template id for easy JS lookup.
     */
    public function getTemplatesWithItems(): array
    {
        $rows = $this->db->query(
            'SELECT t.id, t.name, t.intro_text, t.notes, t.updated_at,
                    u.name AS created_by_name,
                    ti.id AS item_id, ti.sort_order, ti.description,
                    ti.quantity, ti.unit_price
               FROM proposal_templates t
          LEFT JOIN users u  ON u.id = t.created_by
          LEFT JOIN proposal_template_items ti ON ti.template_id = t.id
              ORDER BY t.name ASC, ti.sort_order ASC, ti.id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $templates = [];
        foreach ($rows as $row) {
            $tid = $row['id'];
            if (!isset($templates[$tid])) {
                $templates[$tid] = [
                    'id'              => $tid,
                    'name'            => $row['name'],
                    'intro_text'      => $row['intro_text'] ?? '',
                    'notes'           => $row['notes'] ?? '',
                    'created_by_name' => $row['created_by_name'] ?? '',
                    'updated_at'      => $row['updated_at'],
                    'items'           => [],
                ];
            }
            if ($row['item_id']) {
                $templates[$tid]['items'][] = [
                    'description' => $row['description'],
                    'quantity'    => (float) $row['quantity'],
                    'unit_price'  => (float) $row['unit_price'],
                    'sort_order'  => (int)   $row['sort_order'],
                ];
            }
        }
        return $templates;
    }

    public function getTemplate(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM proposal_templates WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$template) return null;

        $iStmt = $this->db->prepare(
            'SELECT * FROM proposal_template_items
              WHERE template_id = :id
              ORDER BY sort_order ASC, id ASC'
        );
        $iStmt->execute([':id' => $id]);

        return [
            'template' => $template,
            'items'    => $iStmt->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function saveTemplate(array $data, array $items): int
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $id     = (int) ($data['id'] ?? 0);

        if ($id > 0) {
            $this->db->prepare(
                'UPDATE proposal_templates
                    SET name = :name, intro_text = :intro_text, notes = :notes, updated_at = NOW()
                  WHERE id = :id'
            )->execute([
                ':name'       => $data['name'],
                ':intro_text' => $data['intro_text'] ?: null,
                ':notes'      => $data['notes']       ?: null,
                ':id'         => $id,
            ]);
        } else {
            $this->db->prepare(
                'INSERT INTO proposal_templates
                     (name, intro_text, notes, created_by, created_at, updated_at)
                 VALUES
                     (:name, :intro_text, :notes, :created_by, NOW(), NOW())'
            )->execute([
                ':name'       => $data['name'],
                ':intro_text' => $data['intro_text'] ?: null,
                ':notes'      => $data['notes']       ?: null,
                ':created_by' => $userId ?: null,
            ]);
            $id = (int) $this->db->lastInsertId();
        }

        $this->db->prepare('DELETE FROM proposal_template_items WHERE template_id = :id')
                 ->execute([':id' => $id]);

        if (!empty($items)) {
            $stmt = $this->db->prepare(
                'INSERT INTO proposal_template_items
                     (template_id, sort_order, description, quantity, unit_price)
                 VALUES
                     (:template_id, :sort_order, :description, :qty, :unit_price)'
            );
            foreach ($items as $i => $item) {
                $desc = trim($item['description'] ?? '');
                if ($desc === '') continue;
                $stmt->execute([
                    ':template_id' => $id,
                    ':sort_order'  => (int)   ($item['sort_order'] ?? $i),
                    ':description' => $desc,
                    ':qty'         => max(0, (float) ($item['quantity']   ?? 1)),
                    ':unit_price'  => max(0, (float) ($item['unit_price'] ?? 0)),
                ]);
            }
        }

        return $id;
    }

    public function deleteTemplate(int $id): void
    {
        $this->db->prepare('DELETE FROM proposal_templates WHERE id = :id')
                 ->execute([':id' => $id]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function getClients(): array
    {
        return $this->db->query(
            'SELECT id, company_name FROM clients WHERE is_active = 1 ORDER BY company_name ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}
