<?php
require_once __DIR__ . '/../bootstrap.php';
requireRole(['admin', 'buyer']);

$plannerService = new BudgetPlannerService();

$page      = max(1, (int)($_GET['page'] ?? 1));
$proposals = $plannerService->getProposals($page);

$statusColors = [
    'draft'     => 'secondary',
    'sent'      => 'info',
    'approved'  => 'success',
    'converted' => 'primary',
];
$statusLabels = [
    'draft'     => 'Draft',
    'sent'      => 'Sent to Client',
    'approved'  => 'Approved',
    'converted' => 'Converted',
];

$flashMsg = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle = 'AI Budget Planner';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">
            <i class="bi bi-robot me-2 text-primary"></i>AI Budget Planner
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Budget Planner</li>
            </ol>
        </nav>
    </div>
    <a href="/budget-planner/create.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>New Budget Proposal
    </a>
</div>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= h($flashMsg['type']) ?> alert-dismissible fade show">
    <i class="bi bi-<?= $flashMsg['type'] === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i>
    <?= h($flashMsg['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($proposals['data'])): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-robot fs-1 d-block mb-3 opacity-50"></i>
            <p class="mb-2">No budget proposals yet.</p>
            <a href="/budget-planner/create.php" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i>Create First Proposal
            </a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Title</th>
                        <th>Client</th>
                        <th class="text-end">Good</th>
                        <th class="text-end">Better</th>
                        <th class="text-end">Best</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($proposals['data'] as $p): ?>
                <tr style="cursor:pointer;" onclick="window.location='/budget-planner/view.php?id=<?= (int)$p['id'] ?>'">
                    <td>
                        <div class="fw-semibold"><?= h($p['title']) ?></div>
                        <?php if (!empty($p['approved_tier'])): ?>
                        <div class="small text-muted">Approved: <?= h(ucfirst($p['approved_tier'])) ?> tier</div>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted"><?= h($p['client_name'] ?? '—') ?></td>
                    <td class="text-end small">$<?= number_format((float)$p['budget_good'], 0) ?></td>
                    <td class="text-end small">$<?= number_format((float)$p['budget_better'], 0) ?></td>
                    <td class="text-end small">$<?= number_format((float)$p['budget_best'], 0) ?></td>
                    <td>
                        <span class="badge bg-<?= $statusColors[$p['status']] ?? 'secondary' ?>">
                            <?= $statusLabels[$p['status']] ?? h($p['status']) ?>
                        </span>
                    </td>
                    <td class="small text-muted text-nowrap">
                        <?= !empty($p['created_at']) ? h(date('M j, Y', strtotime($p['created_at']))) : '—' ?>
                    </td>
                    <td class="text-end">
                        <a href="/budget-planner/view.php?id=<?= (int)$p['id'] ?>"
                           class="btn btn-sm btn-outline-secondary" onclick="event.stopPropagation()">
                            View
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (($proposals['pages'] ?? 1) > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-center py-3">
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($i = 1; $i <= $proposals['pages']; $i++): ?>
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
