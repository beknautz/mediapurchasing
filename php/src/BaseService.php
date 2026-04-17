<?php
/**
 * src/BaseService.php
 * Media Buying Platform — abstract base service class
 */

class BaseService
{
    protected PDO $db;

    // -----------------------------------------------------------------------
    // Constructor — creates a shared PDO connection from config constants
    // -----------------------------------------------------------------------
    public function __construct()
    {
        if (!defined('DB_HOST')) {
            throw new RuntimeException('Database constants are not defined. Load config/config.php first.');
        }

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $this->db = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    // -----------------------------------------------------------------------
    // paginate()
    // Executes a SELECT query and wraps the result with pagination metadata.
    //
    // $sql    — the full SELECT … FROM … WHERE … ORDER BY … query (no LIMIT)
    // $params — named parameter bindings for PDO
    // Returns: ['data'=>[], 'total'=>int, 'page'=>int, 'pages'=>int]
    // -----------------------------------------------------------------------
    protected function paginate(
        string $sql,
        array  $params   = [],
        int    $page     = 1,
        int    $pageSize = 25
    ): array {
        $page     = max(1, $page);
        $pageSize = max(1, $pageSize);

        // COUNT query — wrap the caller's SQL as a sub-select
        $countSql  = 'SELECT COUNT(*) AS total FROM (' . $sql . ') AS _paged_count';
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $pages  = $total > 0 ? (int) ceil($total / $pageSize) : 1;
        $offset = ($page - 1) * $pageSize;

        // Data query with LIMIT / OFFSET
        $dataSql  = $sql . ' LIMIT :_limit OFFSET :_offset';
        $dataStmt = $this->db->prepare($dataSql);

        // Bind original params first
        foreach ($params as $key => $value) {
            $dataStmt->bindValue($key, $value);
        }

        // Bind pagination params as integers
        $dataStmt->bindValue(':_limit',  $pageSize, PDO::PARAM_INT);
        $dataStmt->bindValue(':_offset', $offset,   PDO::PARAM_INT);
        $dataStmt->execute();

        $data = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data'  => $data,
            'total' => $total,
            'page'  => $page,
            'pages' => $pages,
        ];
    }

    // -----------------------------------------------------------------------
    // auditLog()
    // Inserts a row into the audit_log table.
    // -----------------------------------------------------------------------
    protected function auditLog(
        string $action,
        string $entityType = '',
        int    $entityId   = 0,
        string $details    = ''
    ): void {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $ip     = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        $sql = 'INSERT INTO audit_log
                    (user_id, action, entity_type, entity_id, details, ip_address, created_at)
                VALUES
                    (:user_id, :action, :entity_type, :entity_id, :details, :ip, NOW())';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id'     => $userId > 0 ? $userId : null,
            ':action'      => $action,
            ':entity_type' => $entityType,
            ':entity_id'   => $entityId > 0 ? $entityId : null,
            ':details'     => $details,
            ':ip'          => $ip,
        ]);
    }

    // -----------------------------------------------------------------------
    // getSetting()
    // Reads a value from the global $appSettings array that was populated in
    // config.php, returning $default when the key is absent.
    // -----------------------------------------------------------------------
    protected function getSetting(string $key, string $default = ''): string
    {
        $settings = $GLOBALS['appSettings'] ?? [];
        return (string) ($settings[$key] ?? $default);
    }

    // -----------------------------------------------------------------------
    // lastInsertId()
    // Convenience wrapper around PDO::lastInsertId().
    // -----------------------------------------------------------------------
    protected function lastInsertId(): int
    {
        return (int) $this->db->lastInsertId();
    }
}
