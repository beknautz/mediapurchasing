<?php
/**
 * src/ProposalService.php
 * Unified blocks model: each proposal/template contains an ordered list of
 * blocks typed as 'text' (Summernote HTML), 'item' (line item), or 'signature'.
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

        $bStmt = $this->db->prepare(
            'SELECT * FROM proposal_blocks
              WHERE proposal_id = :id
              ORDER BY sort_order ASC, id ASC'
        );
        $bStmt->execute([':id' => $id]);

        return [
            'proposal' => $proposal,
            'blocks'   => $bStmt->fetchAll(PDO::FETCH_ASSOC),
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
            ':valid_until'  => $data['valid_until'] ?: null,
            ':total_amount' => round((float) ($data['total_amount'] ?? 0), 2),
        ];

        if ($id > 0) {
            $fields[':id'] = $id;
            $this->db->prepare(
                'UPDATE proposals
                    SET title = :title, client_id = :client_id, status = :status,
                        valid_until = :valid_until, total_amount = :total_amount,
                        updated_at = NOW()
                  WHERE id = :id'
            )->execute($fields);
            return $id;
        }

        $fields[':created_by'] = $userId ?: null;
        $this->db->prepare(
            'INSERT INTO proposals
                 (title, client_id, status, valid_until, total_amount, created_by, created_at, updated_at)
             VALUES
                 (:title, :client_id, :status, :valid_until, :total_amount, :created_by, NOW(), NOW())'
        )->execute($fields);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Replace all blocks for a proposal.
     * Blocks are pre-sorted by sort_order before calling this.
     */
    public function saveBlocks(int $proposalId, array $blocks): void
    {
        $this->db->prepare('DELETE FROM proposal_blocks WHERE proposal_id = :id')
                 ->execute([':id' => $proposalId]);

        if (empty($blocks)) return;

        $stmt = $this->db->prepare(
            'INSERT INTO proposal_blocks
                 (proposal_id, block_type, sort_order, content,
                  description, quantity, unit_price, total_price, sig_label,
                  created_at, updated_at)
             VALUES
                 (:proposal_id, :type, :sort, :content,
                  :desc, :qty, :unit, :total, :sig,
                  NOW(), NOW())'
        );

        foreach ($blocks as $i => $b) {
            $type = $b['block_type'] ?? $b['type'] ?? 'text';
            $qty  = max(0, (float) ($b['quantity']   ?? 1));
            $unit = max(0, (float) ($b['unit_price']  ?? 0));
            $stmt->execute([
                ':proposal_id' => $proposalId,
                ':type'        => $type,
                ':sort'        => (int) ($b['sort_order'] ?? $i),
                ':content'     => $type === 'text' ? ($b['content'] ?? null) : null,
                ':desc'        => $type === 'item' ? trim($b['description'] ?? '') : null,
                ':qty'         => $type === 'item' ? $qty : 1,
                ':unit'        => $type === 'item' ? $unit : 0,
                ':total'       => $type === 'item' ? round($qty * $unit, 2) : 0,
                ':sig'         => $type === 'signature' ? ($b['sig_label'] ?? null) : null,
            ]);
        }
    }

    public function recalcTotal(int $proposalId): void
    {
        $this->db->prepare(
            'UPDATE proposals
                SET total_amount = (
                    SELECT COALESCE(SUM(total_price), 0)
                      FROM proposal_blocks
                     WHERE proposal_id = proposals.id AND block_type = "item"
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
     * All templates with their blocks embedded — one JOIN query.
     * Returns array keyed by template id for JS lookup.
     */
    public function getTemplatesWithBlocks(): array
    {
        $rows = $this->db->query(
            'SELECT t.id, t.name, t.updated_at,
                    u.name AS created_by_name,
                    b.id AS block_id, b.block_type, b.sort_order,
                    b.content, b.description, b.quantity, b.unit_price, b.sig_label
               FROM proposal_templates t
          LEFT JOIN users u ON u.id = t.created_by
          LEFT JOIN proposal_template_blocks b ON b.template_id = t.id
              ORDER BY t.name ASC, b.sort_order ASC, b.id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $templates = [];
        foreach ($rows as $row) {
            $tid = $row['id'];
            if (!isset($templates[$tid])) {
                $templates[$tid] = [
                    'id'              => $tid,
                    'name'            => $row['name'],
                    'created_by_name' => $row['created_by_name'] ?? '',
                    'updated_at'      => $row['updated_at'],
                    'blocks'          => [],
                ];
            }
            if ($row['block_id']) {
                $templates[$tid]['blocks'][] = [
                    'block_type'  => $row['block_type'],
                    'sort_order'  => (int)   $row['sort_order'],
                    'content'     => $row['content']     ?? '',
                    'description' => $row['description'] ?? '',
                    'quantity'    => (float) $row['quantity'],
                    'unit_price'  => (float) $row['unit_price'],
                    'sig_label'   => $row['sig_label']   ?? '',
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

        $bStmt = $this->db->prepare(
            'SELECT * FROM proposal_template_blocks
              WHERE template_id = :id
              ORDER BY sort_order ASC, id ASC'
        );
        $bStmt->execute([':id' => $id]);

        return [
            'template' => $template,
            'blocks'   => $bStmt->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function saveTemplate(array $data, array $blocks): int
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $id     = (int) ($data['id'] ?? 0);

        if ($id > 0) {
            $this->db->prepare(
                'UPDATE proposal_templates
                    SET name = :name, updated_at = NOW()
                  WHERE id = :id'
            )->execute([':name' => $data['name'], ':id' => $id]);
        } else {
            $this->db->prepare(
                'INSERT INTO proposal_templates (name, created_by, created_at, updated_at)
                 VALUES (:name, :created_by, NOW(), NOW())'
            )->execute([':name' => $data['name'], ':created_by' => $userId ?: null]);
            $id = (int) $this->db->lastInsertId();
        }

        $this->db->prepare('DELETE FROM proposal_template_blocks WHERE template_id = :id')
                 ->execute([':id' => $id]);

        if (!empty($blocks)) {
            $stmt = $this->db->prepare(
                'INSERT INTO proposal_template_blocks
                     (template_id, block_type, sort_order, content,
                      description, quantity, unit_price, sig_label)
                 VALUES
                     (:template_id, :type, :sort, :content,
                      :desc, :qty, :unit, :sig)'
            );
            foreach ($blocks as $i => $b) {
                $type = $b['block_type'] ?? $b['type'] ?? 'text';
                // Skip empty item blocks
                if ($type === 'item' && trim($b['description'] ?? '') === '') continue;
                $qty  = max(0, (float) ($b['quantity']  ?? 1));
                $unit = max(0, (float) ($b['unit_price'] ?? 0));
                $stmt->execute([
                    ':template_id' => $id,
                    ':type'        => $type,
                    ':sort'        => (int) ($b['sort_order'] ?? $i),
                    ':content'     => $type === 'text'      ? ($b['content']   ?? null) : null,
                    ':desc'        => $type === 'item'      ? trim($b['description'] ?? '') : null,
                    ':qty'         => $type === 'item'      ? $qty  : 1,
                    ':unit'        => $type === 'item'      ? $unit : 0,
                    ':sig'         => $type === 'signature' ? ($b['sig_label'] ?? null) : null,
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
