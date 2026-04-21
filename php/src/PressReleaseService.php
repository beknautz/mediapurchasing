<?php
/**
 * src/PressReleaseService.php
 */

class PressReleaseService extends BaseService
{
    const MIME_MAP = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'mp4'  => 'video/mp4',
        'mov'  => 'video/quicktime',
        'avi'  => 'video/x-msvideo',
        'wmv'  => 'video/x-ms-wmv',
        'mkv'  => 'video/x-matroska',
    ];

    const ALLOWED_EXTS = ['pdf', 'doc', 'docx', 'mp4', 'mov', 'avi', 'wmv', 'mkv'];

    // -----------------------------------------------------------------------
    // Template methods
    // -----------------------------------------------------------------------

    public function getTemplates(): array
    {
        return $this->db->query(
            'SELECT t.*, u.name AS created_by_name
               FROM press_release_templates t
          LEFT JOIN users u ON u.id = t.created_by
           ORDER BY t.name ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTemplate(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM press_release_templates WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveTemplate(array $data): int
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $id     = (int) ($data['id'] ?? 0);

        if ($id > 0) {
            $this->db->prepare(
                'UPDATE press_release_templates
                    SET name = :name, subject = :subject, body_text = :body_text, updated_at = NOW()
                  WHERE id = :id'
            )->execute([
                ':name'      => $data['name'],
                ':subject'   => $data['subject'],
                ':body_text' => $data['body_text'],
                ':id'        => $id,
            ]);
            return $id;
        }

        $this->db->prepare(
            'INSERT INTO press_release_templates (name, subject, body_text, created_by, created_at, updated_at)
             VALUES (:name, :subject, :body_text, :created_by, NOW(), NOW())'
        )->execute([
            ':name'       => $data['name'],
            ':subject'    => $data['subject'],
            ':body_text'  => $data['body_text'],
            ':created_by' => $userId ?: null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function deleteTemplate(int $id): void
    {
        $this->db->prepare('DELETE FROM press_release_templates WHERE id = :id')
                 ->execute([':id' => $id]);
    }

    // -----------------------------------------------------------------------

    public function getPressReleases(int $page = 1, int $pageSize = 25): array
    {
        $sql = 'SELECT pr.id, pr.subject, pr.status, pr.recipient_count, pr.sent_at, pr.created_at,
                       u.name AS created_by_name
                  FROM press_releases pr
             LEFT JOIN users u ON u.id = pr.created_by
              ORDER BY pr.created_at DESC';
        return $this->paginate($sql, [], $page, $pageSize);
    }

    public function getPressRelease(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT pr.*, u.name AS created_by_name
               FROM press_releases pr
          LEFT JOIN users u ON u.id = pr.created_by
              WHERE pr.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $pr = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pr) return null;

        $rStmt = $this->db->prepare(
            'SELECT prr.*, v.company_name, v.media_category
               FROM press_release_recipients prr
          LEFT JOIN vendors v ON v.id = prr.vendor_id
              WHERE prr.press_release_id = :id
           ORDER BY prr.status ASC, v.company_name ASC'
        );
        $rStmt->execute([':id' => $id]);

        return [
            'pr'         => $pr,
            'recipients' => $rStmt->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function create(string $subject, string $bodyHtml, string $bodyText, array $savedFiles): int
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $stmt   = $this->db->prepare(
            'INSERT INTO press_releases
                 (subject, body_html, body_text, attachments, status, recipient_count, created_by, created_at, updated_at)
             VALUES
                 (:subject, :body_html, :body_text, :attachments, "draft", 0, :created_by, NOW(), NOW())'
        );
        $stmt->execute([
            ':subject'     => $subject,
            ':body_html'   => $bodyHtml,
            ':body_text'   => $bodyText,
            ':attachments' => json_encode($savedFiles),
            ':created_by'  => $userId ?: null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateAttachments(int $id, array $files): void
    {
        $this->db->prepare('UPDATE press_releases SET attachments = :att WHERE id = :id')
                 ->execute([':att' => json_encode($files), ':id' => $id]);
    }

    public function markSent(int $id, int $recipientCount): void
    {
        $this->db->prepare(
            'UPDATE press_releases
                SET status = "sent", recipient_count = :cnt, sent_at = NOW(), updated_at = NOW()
              WHERE id = :id'
        )->execute([':cnt' => $recipientCount, ':id' => $id]);
    }

    public function addRecipient(
        int    $pressReleaseId,
        int    $vendorId,
        string $vendorEmail,
        string $status,
        string $error  = '',
        int    $logId  = 0
    ): void {
        $this->db->prepare(
            'INSERT INTO press_release_recipients
                 (press_release_id, vendor_id, vendor_email, status, error_message, log_id, sent_at)
             VALUES
                 (:pr_id, :v_id, :v_email, :status, :error, :log_id, :sent_at)'
        )->execute([
            ':pr_id'   => $pressReleaseId,
            ':v_id'    => $vendorId,
            ':v_email' => $vendorEmail,
            ':status'  => $status,
            ':error'   => $error,
            ':log_id'  => $logId ?: null,
            ':sent_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getVendorsByCategory(): array
    {
        $stmt = $this->db->query(
            'SELECT id, company_name, contact_name, email, billing_email, media_category
               FROM vendors
              WHERE is_active = 1
           ORDER BY COALESCE(media_category, "~"), company_name ASC'
        );
        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $v) {
            $cat = $v['media_category'] ?: 'Uncategorized';
            $grouped[$cat][] = $v;
        }
        return $grouped;
    }

    public function saveUploadedFiles(int $pressReleaseId, array $phpFiles, string $baseUploadDir): array
    {
        $saved = [];
        $dir   = rtrim($baseUploadDir, '/\\') . DIRECTORY_SEPARATOR . $pressReleaseId . DIRECTORY_SEPARATOR;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $count = is_array($phpFiles['name']) ? count($phpFiles['name']) : 0;
        for ($i = 0; $i < $count; $i++) {
            if ($phpFiles['error'][$i] !== UPLOAD_ERR_OK) continue;

            $origName = basename($phpFiles['name'][$i]);
            $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_EXTS, true)) continue;

            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $origName);
            $destPath = $dir . $safeName;
            if (move_uploaded_file($phpFiles['tmp_name'][$i], $destPath)) {
                $saved[] = [
                    'name' => $origName,
                    'safe' => $safeName,
                    'path' => $destPath,
                    'type' => self::MIME_MAP[$ext] ?? 'application/octet-stream',
                    'size' => $phpFiles['size'][$i],
                    'ext'  => $ext,
                ];
            }
        }
        return $saved;
    }
}
