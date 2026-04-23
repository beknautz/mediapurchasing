<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';
requireRole(['admin', 'buyer']);

$proposalService = new ProposalService();

$page      = max(1, (int) ($_GET['page'] ?? 1));
$flashMsg  = flash('success');
$errorMsg  = flash('error');

try {
    $result    = $proposalService->getProposals($page, 25);
    $proposals = $result['data'];
    $totalPages = $result['pages'];
    $total      = $result['total'];
} catch (Exception $e) {
    $proposals  = [];
    $totalPages = 1;
    $total      = 0;
    $errorMsg   = 'Could not load proposals. Run sql/migrate_proposals.sql first. (' . $e->getMessage() . ')';
}

$pageTitle = 'Proposals — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">
            <i class="bi bi-file-earmark-richtext me-2 text-primary"></i>Proposals
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Proposals</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        <a href="/proposals/templates.php" class="btn btn-outline-secondary">
            <i class="bi bi-file-earmark-text me-1"></i>Templates
        </a>
        <a href="/proposals/create.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>New Proposal
        </a>
    </div>
</div>

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

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($proposals)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-file-earmark-richtext fs-2 d-block mb-2 opacity-50"></i>
            <div class="fw-semibold">No proposals yet.</div>
            <a href="/proposals/create.php" class="btn btn-sm btn-outline-primary mt-3">
                <i class="bi bi-plus-circle me-1"></i>Create First Proposal
            </a>
        </div>
        <?php else: ?>
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Title</th>
                    <th>Client</th>
                    <th>Status</th>
                    <th class="text-end">Total</th>
                    <th>Valid Until</th>
                    <th>Created By</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($proposals as $p): ?>
            <?php
                $color = ProposalService::STATUS_COLORS[$p['status']] ?? 'secondary';
                $label = ProposalService::STATUS_LABELS[$p['status']] ?? $p['status'];
            ?>
            <tr>
                <td>
                    <a href="/proposals/view.php?id=<?= (int)$p['id'] ?>" class="fw-semibold text-decoration-none">
                        <?= h($p['title']) ?>
                    </a>
                </td>
                <td class="text-muted small"><?= h($p['client_name'] ?? '—') ?></td>
                <td>
                    <span class="badge bg-<?= $color ?>"><?= h($label) ?></span>
                </td>
                <td class="text-end fw-semibold">
                    $<?= number_format((float)$p['total_amount'], 2) ?>
                </td>
                <td class="small text-muted">
                    <?= $p['valid_until'] ? h(date('M j, Y', strtotime($p['valid_until']))) : '—' ?>
                </td>
                <td class="small text-muted"><?= h($p['created_by_name'] ?? '—') ?></td>
                <td class="small text-muted text-nowrap">
                    <?= h(date('M j, Y', strtotime($p['created_at']))) ?>
                </td>
                <td class="text-end text-nowrap">
                    <a href="/proposals/view.php?id=<?= (int)$p['id'] ?>"
                       class="btn btn-sm btn-outline-secondary me-1">
                        <i class="bi bi-eye me-1"></i>View
                    </a>
                    <a href="/proposals/create.php?id=<?= (int)$p['id'] ?>"
                       class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-pencil me-1"></i>Edit
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top small text-muted">
            <div><?= $total ?> proposal<?= $total !== 1 ? 's' : '' ?></div>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
