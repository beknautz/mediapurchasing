<?php
/**
 * src/ApprovalService.php
 * Media Buying Platform — client approval workflow service
 */

class ApprovalService extends BaseService
{
    // -----------------------------------------------------------------------
    // createApproval()
    // Generates a secure token, inserts an approval request row, and returns
    // the key fields the caller needs to build a notification link.
    //
    // Returns: ['id'=>int, 'token'=>string, 'expiresAt'=>string (datetime)]
    // -----------------------------------------------------------------------
    public function createApproval(int $mediaBuyId, int $clientId): array
    {
        $token     = bin2hex(random_bytes(32));          // 64-char hex string
        $ttlDays   = (int) $this->getSetting('approval_ttl_days', '7');
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$ttlDays} days"));

        $stmt = $this->db->prepare(
            'INSERT INTO media_buy_approvals
                 (media_buy_id, client_id, token, status, expires_at, created_at, updated_at)
             VALUES
                 (:media_buy_id, :client_id, :token, "pending", :expires_at, NOW(), NOW())'
        );
        $stmt->execute([
            ':media_buy_id' => $mediaBuyId,
            ':client_id'    => $clientId,
            ':token'        => $token,
            ':expires_at'   => $expiresAt,
        ]);

        $id = $this->lastInsertId();

        // Move the parent media buy to pending_approval status
        $this->db->prepare(
            "UPDATE media_buys
                SET status     = 'pending_approval',
                    updated_at = NOW()
              WHERE id = :id"
        )->execute([':id' => $mediaBuyId]);

        $this->auditLog(
            'create_approval',
            'media_buy',
            $mediaBuyId,
            "Approval #{$id} created for client #{$clientId}"
        );

        return [
            'id'        => $id,
            'token'     => $token,
            'expiresAt' => $expiresAt,
        ];
    }

    // -----------------------------------------------------------------------
    // getApprovalByToken()
    // Looks up an approval by its public token.
    //
    // Returns:
    //   ['found'=>true,  'approval'=>row, 'items'=>rows]  — when found
    //   ['found'=>false, 'approval'=>null, 'items'=>[]]   — when not found
    // -----------------------------------------------------------------------
    public function getApprovalByToken(string $token): array
    {
        $stmt = $this->db->prepare(
            'SELECT a.*,
                    mb.title        AS buy_title,
                    mb.total_cost   AS buy_total_cost,
                    mb.agreed_cost  AS buy_agreed_cost,
                    mb.notes        AS buy_notes,
                    c.company_name  AS client_name,
                    c.email         AS client_email,
                    v.company_name  AS vendor_name
               FROM media_buy_approvals a
               JOIN media_buys mb ON mb.id = a.media_buy_id
          LEFT JOIN clients c    ON c.id   = a.client_id
          LEFT JOIN vendors v    ON v.id   = mb.vendor_id
              WHERE a.token = :token
              LIMIT 1'
        );
        $stmt->execute([':token' => $token]);
        $approval = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$approval) {
            return ['found' => false, 'approval' => null, 'items' => []];
        }

        // Fetch the line items for the associated media buy
        $itemStmt = $this->db->prepare(
            'SELECT * FROM media_buy_items
              WHERE media_buy_id = :id
              ORDER BY sort_order ASC, id ASC'
        );
        $itemStmt->execute([':id' => $approval['media_buy_id']]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'found'    => true,
            'approval' => $approval,
            'items'    => $items,
        ];
    }

    // -----------------------------------------------------------------------
    // processResponse()
    // Records the client's approve/reject decision.
    //
    // $response — 'approved' | 'rejected'
    //
    // Returns:
    //   ['success'=>bool, 'message'=>string, 'approval'=>row|null]
    // -----------------------------------------------------------------------
    public function processResponse(
        string $token,
        string $response,
        string $notes = '',
        string $ip    = ''
    ): array {
        $lookup = $this->getApprovalByToken($token);

        if (!$lookup['found']) {
            return ['success' => false, 'message' => 'Approval not found.', 'approval' => null];
        }

        $approval = $lookup['approval'];

        if ($approval['status'] !== 'pending') {
            return [
                'success'  => false,
                'message'  => 'This approval request has already been ' . $approval['status'] . '.',
                'approval' => $approval,
            ];
        }

        if (strtotime($approval['expires_at']) < time()) {
            return ['success' => false, 'message' => 'This approval link has expired.', 'approval' => $approval];
        }

        $allowedResponses = ['approved', 'rejected'];
        if (!in_array($response, $allowedResponses, true)) {
            return ['success' => false, 'message' => 'Invalid response value.', 'approval' => $approval];
        }

        $ip = $ip !== '' ? $ip : ($_SERVER['REMOTE_ADDR'] ?? '');

        $stmt = $this->db->prepare(
            'UPDATE media_buy_approvals
                SET status       = :status,
                    response_notes = :notes,
                    responded_at  = NOW(),
                    responder_ip  = :ip,
                    updated_at    = NOW()
              WHERE token = :token'
        );
        $stmt->execute([
            ':status' => $response,
            ':notes'  => $notes,
            ':ip'     => $ip,
            ':token'  => $token,
        ]);

        // Mirror status onto the media buy
        $this->db->prepare(
            "UPDATE media_buys
                SET status     = :status,
                    updated_at = NOW()
              WHERE id = :id"
        )->execute([':status' => $response, ':id' => $approval['media_buy_id']]);

        $this->auditLog(
            'approval_response',
            'media_buy',
            (int) $approval['media_buy_id'],
            "Approval #{$approval['id']} — client responded: {$response}"
        );

        // Re-fetch updated row
        $updStmt = $this->db->prepare(
            'SELECT * FROM media_buy_approvals WHERE token = :token LIMIT 1'
        );
        $updStmt->execute([':token' => $token]);
        $updatedApproval = $updStmt->fetch(PDO::FETCH_ASSOC);

        return [
            'success'  => true,
            'message'  => 'Response recorded. Thank you.',
            'approval' => $updatedApproval,
        ];
    }

    // -----------------------------------------------------------------------
    // getApprovals()
    // Returns a paginated list of approvals, optionally filtered by status.
    //
    // Returns: ['data'=>[], 'total'=>int, 'page'=>int, 'pages'=>int]
    // -----------------------------------------------------------------------
    public function getApprovals(string $status = '', int $page = 1): array
    {
        $sql = 'SELECT a.*,
                       mb.title       AS buy_title,
                       c.company_name AS client_name,
                       c.email        AS client_email
                  FROM media_buy_approvals a
                  JOIN media_buys mb ON mb.id = a.media_buy_id
             LEFT JOIN clients c    ON c.id   = a.client_id';

        $params = [];

        if ($status !== '') {
            $sql           .= ' WHERE a.status = :status';
            $params[':status'] = $status;
        }

        $sql .= ' ORDER BY a.created_at DESC';

        return $this->paginate($sql, $params, $page, PAGE_SIZE);
    }
}
