<?php
require_once __DIR__ . '/../bootstrap.php';
requireRole('admin');

// ---------------------------------------------------------------------------
// Pagination & filtering parameters
// ---------------------------------------------------------------------------
$perPage     = 50;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

$filterUser   = trim($_GET['user']        ?? '');
$filterAction = trim($_GET['action']      ?? '');
$filterEntity = trim($_GET['entity_type'] ?? '');
$filterDate   = trim($_GET['date']        ?? '');

// ---------------------------------------------------------------------------
// Query audit_log table directly via PDO
// ---------------------------------------------------------------------------
// The Database/PDO connection is assumed to be accessible via a BaseService
// helper or a dedicated db() / getDb() global function established in bootstrap.
// We use a service-based approach: instantiate BaseService to get the PDO handle,
// or fall back to a direct PDO connection from config.
// ---------------------------------------------------------------------------

try {
    // Attempt to get a PDO instance via a shared service or config
    if (class_exists('BaseService') && method_exists('BaseService', 'getDb')) {
        $db = BaseService::getDb();
    } elseif (function_exists('getDb')) {
        $db = getDb();
    } else {
        // Direct connection fallback using config constants
        $cfg = require __DIR__ . '/../config/database.php';
        $dsn = 'mysql:host=' . ($cfg['host'] ?? 'localhost') .
               ';dbname=' . ($cfg['database'] ?? '') .
               ';charset=utf8mb4';
        $db  = new PDO($dsn, $cfg['username'] ?? '', $cfg['password'] ?? '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    // Build WHERE clause dynamically
    $where  = [];
    $params = [];

    if ($filterUser !== '') {
        $where[]  = '(al.user_name LIKE :user OR al.user_id = :user_exact)';
        $params[':user']       = '%' . $filterUser . '%';
        $params[':user_exact'] = $filterUser;
    }
    if ($filterAction !== '') {
        $where[]  = 'al.action LIKE :action';
        $params[':action'] = '%' . $filterAction . '%';
    }
    if ($filterEntity !== '') {
        $where[]  = 'al.entity_type LIKE :entity';
        $params[':entity'] = '%' . $filterEntity . '%';
    }
    if ($filterDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
        $where[]  = 'DATE(al.created_at) = :date';
        $params[':date'] = $filterDate;
    }

    $whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    // Total count for pagination
    $countSql  = "SELECT COUNT(*) FROM audit_log al $whereSQL";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalRows   = (int)$countStmt->fetchColumn();
    $totalPages  = max(1, (int)ceil($totalRows / $perPage));
    $currentPage = min($currentPage, $totalPages);

    // Fetch current page rows
    $sql = "
        SELECT
            al.id,
            al.created_at,
            COALESCE(al.user_name, CONCAT('User #', al.user_id)) AS user_label,
            al.user_id,
            al.action,
            al.entity_type,
            al.entity_id,
            al.details,
            al.ip_address
        FROM audit_log al
        $whereSQL
        ORDER BY al.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $dbError = null;
} catch (Throwable $e) {
    $rows       = [];
    $totalRows  = 0;
    $totalPages = 1;
    $dbError    = $e->getMessage();
}

// ---------------------------------------------------------------------------
// Helper: build pagination URL preserving filter params
// ---------------------------------------------------------------------------
function auditPageUrl(int $page): string {
    $q = $_GET;
    $q['page'] = $page;
    return '/admin/audit_log.php?' . http_build_query($q);
}

// ---------------------------------------------------------------------------
// Action badge colour map
// ---------------------------------------------------------------------------
function actionBadgeClass(string $action): string {
    $action = strtolower($action);
    if (str_contains($action, 'delete') || str_contains($action, 'remove')) return 'bg-danger';
    if (str_contains($action, 'create') || str_contains($action, 'add'))    return 'bg-success';
    if (str_contains($action, 'update') || str_contains($action, 'edit'))   return 'bg-warning text-dark';
    if (str_contains($action, 'login')  || str_contains($action, 'logout')) return 'bg-info text-dark';
    return 'bg-secondary';
}

$pageTitle = 'Audit Log — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="bi bi-journal-text me-2 text-primary"></i>Audit Log</h2>
        <p class="text-muted mb-0 small">A complete record of system activity and user actions.</p>
    </div>
    <?php if ($totalRows > 0): ?>
        <span class="badge bg-secondary fs-6"><?= number_format($totalRows) ?> records</span>
    <?php endif; ?>
</div>

<?php if ($dbError !== null): ?>
    <div class="alert alert-danger" role="alert">
        <h5 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>Database Error</h5>
        <p class="mb-0"><?= h($dbError) ?></p>
    </div>
<?php endif; ?>

<!-- Filter Form -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-funnel me-2"></i>Filter Log</h6>
    </div>
    <div class="card-body">
        <form method="get" action="/admin/audit_log.php" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="1">

            <div class="col-md-3">
                <label for="filterUser" class="form-label fw-semibold small">User</label>
                <input type="text" id="filterUser" name="user" class="form-control form-control-sm"
                       placeholder="Name or user ID" value="<?= h($filterUser) ?>">
            </div>

            <div class="col-md-3">
                <label for="filterAction" class="form-label fw-semibold small">Action</label>
                <input type="text" id="filterAction" name="action" class="form-control form-control-sm"
                       placeholder="create, update, delete…" value="<?= h($filterAction) ?>">
            </div>

            <div class="col-md-3">
                <label for="filterEntity" class="form-label fw-semibold small">Entity Type</label>
                <input type="text" id="filterEntity" name="entity_type" class="form-control form-control-sm"
                       placeholder="user, client, campaign…" value="<?= h($filterEntity) ?>">
            </div>

            <div class="col-md-2">
                <label for="filterDate" class="form-label fw-semibold small">Date</label>
                <input type="date" id="filterDate" name="date" class="form-control form-control-sm"
                       value="<?= h($filterDate) ?>">
            </div>

            <div class="col-md-1 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-search"></i>
                </button>
                <a href="/admin/audit_log.php" class="btn btn-outline-secondary btn-sm w-100" title="Clear filters">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Results Table -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-dark">
                    <tr>
                        <th scope="col" style="min-width:150px;">Date / Time</th>
                        <th scope="col">User</th>
                        <th scope="col">Action</th>
                        <th scope="col">Entity Type</th>
                        <th scope="col">Entity ID</th>
                        <th scope="col">Details</th>
                        <th scope="col">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows) && $dbError === null): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-journal-x fs-3 d-block mb-2"></i>
                                <?= ($filterUser || $filterAction || $filterEntity || $filterDate)
                                    ? 'No log entries match your filter criteria.'
                                    : 'No audit log entries found.' ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row):
                            $details = $row['details'] ?? '';
                            // Attempt to decode JSON details for pretty display
                            $decodedDetails = json_decode($details, true);
                            $detailDisplay  = is_array($decodedDetails)
                                ? implode(', ', array_map(
                                    fn($k, $v) => h($k) . ': ' . h(is_array($v) ? json_encode($v) : $v),
                                    array_keys($decodedDetails),
                                    $decodedDetails
                                  ))
                                : h($details);
                        ?>
                            <tr>
                                <td class="text-nowrap text-muted">
                                    <?php
                                    $ts = strtotime($row['created_at'] ?? '');
                                    echo $ts ? date('Y-m-d H:i:s', $ts) : h($row['created_at'] ?? '—');
                                    ?>
                                </td>
                                <td>
                                    <i class="bi bi-person-circle me-1 text-secondary"></i>
                                    <?= h($row['user_label'] ?? '—') ?>
                                </td>
                                <td>
                                    <span class="badge <?= actionBadgeClass($row['action'] ?? '') ?>">
                                        <?= h($row['action'] ?? '—') ?>
                                    </span>
                                </td>
                                <td><?= h($row['entity_type'] ?? '—') ?></td>
                                <td class="text-muted"><?= h($row['entity_id'] ?? '—') ?></td>
                                <td style="max-width:300px;">
                                    <?php if (!empty($details)): ?>
                                        <?php if (strlen($details) > 80): ?>
                                            <span data-bs-toggle="tooltip" title="<?= h($details) ?>">
                                                <?= mb_strimwidth($detailDisplay, 0, 80, '…') ?>
                                            </span>
                                        <?php else: ?>
                                            <?= $detailDisplay ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted font-monospace"><?= h($row['ip_address'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
            <small class="text-muted">
                Showing <?= number_format($offset + 1) ?>–<?= number_format(min($offset + $perPage, $totalRows)) ?>
                of <?= number_format($totalRows) ?> entries
            </small>
            <nav aria-label="Audit log pagination">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= h(auditPageUrl($currentPage - 1)) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>

                    <?php
                    $startPage = max(1, $currentPage - 2);
                    $endPage   = min($totalPages, $currentPage + 2);
                    if ($startPage > 1): ?>
                        <li class="page-item"><a class="page-link" href="<?= h(auditPageUrl(1)) ?>">1</a></li>
                        <?php if ($startPage > 2): ?>
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                        <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                            <a class="page-link" href="<?= h(auditPageUrl($p)) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                        <?php endif; ?>
                        <li class="page-item"><a class="page-link" href="<?= h(auditPageUrl($totalPages)) ?>"><?= $totalPages ?></a></li>
                    <?php endif; ?>

                    <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= h(auditPageUrl($currentPage + 1)) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<script>
// Enable Bootstrap tooltips for truncated detail cells
document.addEventListener('DOMContentLoaded', function () {
    var tooltipEls = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipEls.forEach(function (el) {
        new bootstrap.Tooltip(el, { placement: 'top' });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
