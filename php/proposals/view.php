<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';
requireRole(['admin', 'buyer']);

$proposalService = new ProposalService();

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    redirect('/proposals/index.php');
}

$data = $proposalService->getProposal($id);
if (!$data) {
    flash('error', 'Proposal not found.');
    redirect('/proposals/index.php');
}

$proposal = $data['proposal'];
$items    = $data['items'];
$flashMsg = flash('success');
$errorMsg = flash('error');

// Handle status change POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_status'])) {
    $newStatus = trim($_POST['new_status'] ?? '');
    try {
        $proposalService->updateStatus($id, $newStatus);
        flash('success', 'Status updated to "' . (ProposalService::STATUS_LABELS[$newStatus] ?? $newStatus) . '".');
        redirect('/proposals/view.php?id=' . $id);
    } catch (Exception $e) {
        $errorMsg = 'Could not update status: ' . $e->getMessage();
    }
}

// Handle delete POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_confirm'])) {
    try {
        $proposalService->deleteProposal($id);
        flash('success', 'Proposal deleted.');
        redirect('/proposals/index.php');
    } catch (Exception $e) {
        $errorMsg = 'Could not delete: ' . $e->getMessage();
    }
}

$statusColor = ProposalService::STATUS_COLORS[$proposal['status']] ?? 'secondary';
$statusLabel = ProposalService::STATUS_LABELS[$proposal['status']] ?? $proposal['status'];

$pageTitle = h($proposal['title']) . ' — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($flashMsg): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle-fill me-2"></i><?= h($flashMsg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($errorMsg): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= h($errorMsg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <h1 class="h3 mb-1 fw-bold"><?= h($proposal['title']) ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="/proposals/index.php">Proposals</a></li>
                <li class="breadcrumb-item active"><?= h($proposal['title']) ?></li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/proposals/create.php?id=<?= $id ?>" class="btn btn-outline-secondary">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <button type="button" class="btn btn-outline-danger" onclick="confirmDelete()">
            <i class="bi bi-trash me-1"></i>Delete
        </button>
    </div>
</div>

<div class="row g-4">

    <!-- ── Main content ─────────────────────────────────────────────────────── -->
    <div class="col-lg-8">

        <!-- Intro text -->
        <?php if (!empty($proposal['intro_text'])): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div style="white-space:pre-wrap;line-height:1.7;"><?= h($proposal['intro_text']) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Line items -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-list-ul me-2 text-primary"></i>Line Items
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($items)): ?>
                <div class="text-center text-muted py-4 small">No line items.</div>
                <?php else: ?>
                <table class="table mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Description</th>
                            <th class="text-center" style="width:80px;">Qty</th>
                            <th class="text-end" style="width:130px;">Unit Price</th>
                            <th class="text-end" style="width:130px;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= h($item['description']) ?></td>
                        <td class="text-center"><?= h(rtrim(rtrim(number_format((float)$item['quantity'], 2), '0'), '.')) ?></td>
                        <td class="text-end">$<?= number_format((float)$item['unit_price'], 2) ?></td>
                        <td class="text-end fw-semibold">$<?= number_format((float)$item['total_price'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-light fw-bold">
                            <td colspan="3" class="text-end">Total</td>
                            <td class="text-end fs-5">$<?= number_format((float)$proposal['total_amount'], 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notes / Terms -->
        <?php if (!empty($proposal['notes'])): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-sticky me-2 text-primary"></i>Notes / Terms
                </h5>
            </div>
            <div class="card-body">
                <div style="white-space:pre-wrap;line-height:1.7;" class="text-muted small">
                    <?= h($proposal['notes']) ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- ── Sidebar ───────────────────────────────────────────────────────────── -->
    <div class="col-lg-4">

        <!-- Details -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-info-circle me-2 text-primary"></i>Details
                </h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted">Status</dt>
                    <dd class="col-7">
                        <span class="badge bg-<?= $statusColor ?>"><?= h($statusLabel) ?></span>
                    </dd>
                    <dt class="col-5 text-muted">Client</dt>
                    <dd class="col-7"><?= h($proposal['client_name'] ?? '—') ?></dd>
                    <dt class="col-5 text-muted">Total</dt>
                    <dd class="col-7 fw-bold">$<?= number_format((float)$proposal['total_amount'], 2) ?></dd>
                    <dt class="col-5 text-muted">Valid Until</dt>
                    <dd class="col-7">
                        <?php if ($proposal['valid_until']): ?>
                            <?php
                            $isExpired = strtotime($proposal['valid_until']) < strtotime('today');
                            ?>
                            <span class="<?= $isExpired ? 'text-danger fw-semibold' : '' ?>">
                                <?= h(date('M j, Y', strtotime($proposal['valid_until']))) ?>
                            </span>
                            <?php if ($isExpired): ?>
                                <span class="badge bg-danger ms-1">Expired</span>
                            <?php endif; ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </dd>
                    <dt class="col-5 text-muted">Created By</dt>
                    <dd class="col-7"><?= h($proposal['created_by_name'] ?? '—') ?></dd>
                    <dt class="col-5 text-muted">Created</dt>
                    <dd class="col-7"><?= h(date('M j, Y', strtotime($proposal['created_at']))) ?></dd>
                    <?php if ($proposal['sent_at']): ?>
                    <dt class="col-5 text-muted">Sent</dt>
                    <dd class="col-7"><?= h(date('M j, Y', strtotime($proposal['sent_at']))) ?></dd>
                    <?php endif; ?>
                    <?php if ($proposal['accepted_at']): ?>
                    <dt class="col-5 text-muted">Accepted</dt>
                    <dd class="col-7"><?= h(date('M j, Y', strtotime($proposal['accepted_at']))) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <!-- Change Status -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-arrow-left-right me-2 text-primary"></i>Update Status
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="/proposals/view.php?id=<?= $id ?>">
                    <div class="mb-3">
                        <select class="form-select form-select-sm" name="new_status">
                            <?php foreach (ProposalService::STATUS_LABELS as $sv => $sl): ?>
                            <option value="<?= h($sv) ?>" <?= $proposal['status'] === $sv ? 'selected' : '' ?>>
                                <?= h($sl) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                        <i class="bi bi-check-circle me-1"></i>Update Status
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST" action="/proposals/view.php?id=<?= $id ?>">
                <input type="hidden" name="delete_confirm" value="1">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Proposal?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body small">
                    Delete "<strong><?= h($proposal['title']) ?></strong>"? This cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDelete() {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
