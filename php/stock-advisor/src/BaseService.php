<?php
/**
 * stock-advisor/src/BaseService.php
 * Base class providing a shared PDO connection and common helpers.
 */
class BaseService
{
    protected PDO $db;

    public function __construct()
    {
        if (!defined('DB_HOST')) {
            throw new RuntimeException('Database constants not defined. Check config/config.php.');
        }

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );

        $this->db = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    protected function paginate(
        string $sql,
        array  $params   = [],
        int    $page     = 1,
        int    $pageSize = 25
    ): array {
        $page     = max(1, $page);
        $pageSize = max(1, $pageSize);

        $countStmt = $this->db->prepare('SELECT COUNT(*) AS total FROM (' . $sql . ') AS _c');
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        $pages  = $total > 0 ? (int) ceil($total / $pageSize) : 1;
        $offset = ($page - 1) * $pageSize;

        $dataStmt = $this->db->prepare($sql . ' LIMIT :_limit OFFSET :_offset');
        foreach ($params as $key => $value) {
            $dataStmt->bindValue($key, $value);
        }
        $dataStmt->bindValue(':_limit',  $pageSize, PDO::PARAM_INT);
        $dataStmt->bindValue(':_offset', $offset,   PDO::PARAM_INT);
        $dataStmt->execute();

        return [
            'data'  => $dataStmt->fetchAll(),
            'total' => $total,
            'page'  => $page,
            'pages' => $pages,
        ];
    }

    protected function lastInsertId(): int
    {
        return (int) $this->db->lastInsertId();
    }
}
