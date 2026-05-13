<?php
require_once __DIR__ . '/../bootstrap.php';
requireRole(['admin', 'buyer']);

$svc    = new PrintBidService();
$status = trim($_GET['status'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));

$validStatuses = ['draft', 'sent', 'replied', 'approved', 'rejected'];
if ($status && !in_array($status, $validStatuses, true)) $status = '';

$result = $svc->getBids($status, $page, 25);
$bids   = $result['data']  ?? [];
$total  = $result['total'] ?? 0;
$pages  = $result['pages'] ?? 1;
$counts = $svc->getCounts();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $did = (int)($_POST['id'] ?? 0);
    if ($did > 0) {
        $svc->deleteBid($did);
        flash('success', 'Print bid deleted.');
    }
    redirect('/print-bids/index.php');
}

$statusTabs   = ['' => 'All', 'draft' => 'Draft', 'sent' => 'Sent', 'replied' => 'Replied', 'approved' => 'Approved', 'rejected' => 'Rejected'];
$statusColors = ['draft' => 'secondary', 'sent' => 'primary', 'replied' => 'danger', 'approved' => 'success', 'rejected' => 'danger'];

$pageTitle = 'Print Bids — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Page header ── -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="bi bi-printer me-2 text-primary"></i>Print Bids</h2>
        <p class="text-muted mb-0 small">Printing &amp; signage bid requests sent to vendors.</p>
    </div>
    <a href="/print-bids/create.php" class="btn btn-primary">
        <i class="bi bi-plus-circle-fill me-1"></i>New Print Bid
    </a>
</div>

<?php $msg = flash('success'); if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill me-2"></i><?= h($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- ── Status tabs ── -->
<ul class="nav nav-tabs mb-3">
    <?php foreach ($statusTabs as $s => $label):
        $cnt   = $counts[$s] ?? 0;
        $qs    = $s ? '?status=' . $s : '?';
        $active = ($status === $s) ? 'active' : '';
    ?>
    <li class="nav-item">
        <a class="nav-link <?= $active ?>" href="/print-bids/index.php<?= $s ? '?status=' . $s : '' ?>">
            <?= h($label) ?>
            <?php if ($cnt > 0): ?>
                <span class="badge bg-<?= $statusColors[$s] ?? 'secondary' ?> ms-1"><?= $cnt ?></span>
            <?php endif; ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<!-- ── Table ── -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <span class="fw-semibold text-secondary">
            <i class="bi bi-list-ul me-1"></i><?= $total ?> bid<?= $total !== 1 ? 's' : '' ?>
        </span>
        <input type="text" class="form-control form-control-sm w-auto" id="bidSearch"
               placeholder="Search…" oninput="filterTable(this.value,'bidsTable')">
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="bidsTable">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Client</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($bids)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-printer fs-2 d-block mb-2"></i>No print bids yet. <a href="/print-bids/create.php">Create one</a>.
                    </td></tr>
                <?php else: foreach ($bids as $i => $b): $sc = $statusColors[$b['status']] ?? 'secondary'; ?>
                    <tr>
                        <td class="text-muted small"><?= ($page - 1) * 25 + $i + 1 ?></td>
                        <td><i class="bi bi-building me-1 text-secondary"></i><strong><?= h($b['client_name']) ?></strong></td>
                        <td><?= h($b['title'] ?: '—') ?></td>
                        <td>
                            <?php if ($b['status'] === 'replied'): ?>
                                <a href="/print-bids/view.php?id=<?= (int)$b['id'] ?>#vendor-replies"
                                   class="badge bg-danger text-decoration-none">
                                    <i class="bi bi-reply-fill me-1"></i>Replied
                                </a>
                            <?php else: ?>
                                <span class="badge bg-<?= $sc ?>"><?= h(ucfirst($b['status'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= h($b['created_by_name'] ?? '—') ?></td>
                        <td class="small text-muted"><?= date('M j, Y', strtotime($b['created_at'])) ?></td>
                        <td class="text-end">
                            <div class="d-flex gap-1 justify-content-end">
                                <a href="/print-bids/view.php?id=<?= (int)$b['id'] ?>"
                                   class="btn btn-sm btn-outline-secondary" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="/print-bids/create.php?id=<?= (int)$b['id'] ?>"
                                   class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <?php if (in_array($b['status'], ['sent','replied'], true)): ?>
                                <a href="/print-bids/reply.php?id=<?= (int)$b['id'] ?>"
                                   class="btn btn-sm btn-outline-danger" title="Log Vendor Reply">
                                    <i class="bi bi-reply-fill"></i>
                                </a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                        onclick="confirmDelete(<?= (int)$b['id'] ?>, <?= htmlspecialchars(json_encode($b['client_name']), ENT_QUOTES) ?>)"
                                        title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($pages > 1): ?>
    <div class="card-footer bg-white">
        <nav><ul class="pagination pagination-sm mb-0 justify-content-center">
            <?php for ($p = 1; $p <= $pages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= $status ? 'status='.$status.'&' : '' ?>page=<?= $p ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<!-- Delete modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content shadow">
            <form method="post" action="/print-bids/index.php">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteBidId">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Delete Bid</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">Permanently delete bid for:</p>
                    <p class="fw-semibold" id="deleteBidClient"></p>
                    <p class="text-muted small mb-0">This cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash me-1"></i>Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    document.getElementById('deleteBidId').value        = id;
    document.getElementById('deleteBidClient').textContent = name;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteModal')).show();
}
function filterTable(q, tableId) {
    q = q.toLowerCase();
    document.querySelectorAll('#' + tableId + ' tbody tr').forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
