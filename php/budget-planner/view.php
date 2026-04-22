<?php
require_once __DIR__ . '/../bootstrap.php';
requireRole(['admin', 'buyer']);

$role    = $_SESSION['role'] ?? '';
$userId  = (int)($_SESSION['user']['id'] ?? 0);
$isAdmin = $role === 'admin';
$isBuyer = in_array($role, ['admin', 'buyer'], true);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid proposal ID.'];
    redirect('/budget-planner/index.php');
}

$plannerService = new BudgetPlannerService();

// Load clients for convert-to-campaign form
$pdo = (function () {
    if (!defined('DB_HOST')) return null;
    try {
        return new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET),
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (PDOException $e) { return null; }
})();
$clients = $pdo ? $pdo->query("SELECT id, company_name FROM clients ORDER BY company_name")->fetchAll() : [];

// ─── POST actions ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    if ($action === 'mark_approved' && $isAdmin) {
        $tier = trim($_POST['approved_tier'] ?? 'good');
        $plannerService->markApproved($id, $tier);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Proposal approved (' . ucfirst($tier) . ' tier).'];
        redirect('/budget-planner/view.php?id=' . $id);
    }

    if ($action === 'mark_sent' && $isBuyer) {
        $plannerService->markSent($id);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Proposal marked as sent to client.'];
        redirect('/budget-planner/view.php?id=' . $id);
    }

    if ($action === 'convert' && $isBuyer) {
        $clientId      = (int)($_POST['client_id']      ?? 0);
        $campaignTitle = trim($_POST['campaign_title']  ?? '');
        if ($clientId === 0 || $campaignTitle === '') {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Campaign title and client are required.'];
            redirect('/budget-planner/view.php?id=' . $id);
        }
        $result = $plannerService->convertToCampaign($id, $clientId, $campaignTitle);
        if ($result['success']) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Campaign created with RFP channels. You can now send RFPs from the campaign page.'];
            redirect('/campaigns/view.php?id=' . $result['campaign_id']);
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => $result['message']];
            redirect('/budget-planner/view.php?id=' . $id);
        }
    }
}

$proposal = $plannerService->getProposal($id);
if (empty($proposal)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Proposal not found.'];
    redirect('/budget-planner/index.php');
}

$allocations = json_decode($proposal['allocation_json'] ?? '{}', true) ?: [];
$unifiedRows = BudgetPlannerService::buildUnifiedTable($allocations);

$status      = $proposal['status'] ?? 'draft';
$approvedTier = $proposal['approved_tier'] ?? null;

$statusColors = [
    'draft'     => 'secondary',
    'sent'      => 'info',
    'approved'  => 'success',
    'converted' => 'primary',
];
$tierColors = ['good' => 'success', 'better' => 'primary', 'best' => 'warning'];

$flashMsg = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle = 'Budget Proposal: ' . h($proposal['title']);
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= h($flashMsg['type']) ?> alert-dismissible fade show mb-4">
    <i class="bi bi-<?= $flashMsg['type'] === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i>
    <?= h($flashMsg['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="/budget-planner/index.php">Budget Planner</a></li>
                <li class="breadcrumb-item active"><?= h($proposal['title']) ?></li>
            </ol>
        </nav>
        <h1 class="h3 mb-0 fw-bold">
            <i class="bi bi-robot me-2 text-primary"></i><?= h($proposal['title']) ?>
        </h1>
        <div class="mt-1">
            <span class="badge bg-<?= $statusColors[$status] ?? 'secondary' ?> me-1">
                <?= ucfirst($status === 'sent' ? 'Sent to Client' : $status) ?>
            </span>
            <?php if ($approvedTier): ?>
            <span class="badge bg-<?= $tierColors[$approvedTier] ?? 'secondary' ?>">
                Approved: <?= ucfirst($approvedTier) ?> Tier
            </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/budget-planner/export.php?id=<?= $id ?>" class="btn btn-outline-success">
            <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
        </a>
        <a href="/budget-planner/index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="row g-4">

    <!-- Left: Allocation Table -->
    <div class="col-lg-9">

        <!-- Summary Cards -->
        <div class="row g-3 mb-4">
            <?php foreach (['good' => 'Good', 'better' => 'Better', 'best' => 'Best'] as $tier => $label):
                $tierBudget = (float)($proposal['budget_' . $tier] ?? 0);
                $tierTotal  = array_sum(array_column($allocations[$tier] ?? [], 'amount'));
                $vendorCount = count($allocations[$tier] ?? []);
                $isApproved  = $approvedTier === $tier;
            ?>
            <div class="col-md-4">
                <div class="card border-<?= $tierColors[$tier] ?> border-2 h-100 <?= $isApproved ? 'shadow' : 'border-0 shadow-sm' ?>">
                    <div class="card-body text-center">
                        <?php if ($isApproved): ?>
                        <div class="badge bg-<?= $tierColors[$tier] ?> mb-2">
                            <i class="bi bi-check-circle me-1"></i>APPROVED
                        </div>
                        <?php endif; ?>
                        <div class="small text-muted fw-semibold text-uppercase"><?= $label ?></div>
                        <div class="display-6 fw-bold text-<?= $tierColors[$tier] ?>">
                            $<?= number_format($tierBudget, 0) ?>
                        </div>
                        <div class="small text-muted mt-1"><?= $vendorCount ?> vendor<?= $vendorCount !== 1 ? 's' : '' ?></div>
                        <?php if ($tierBudget > 0 && abs($tierTotal - $tierBudget) > 1): ?>
                        <div class="small text-warning mt-1">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Allocated: $<?= number_format($tierTotal, 0) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- AI Rationale -->
        <?php if (!empty($proposal['ai_rationale'])): ?>
        <div class="alert alert-light border mb-4">
            <strong><i class="bi bi-lightbulb me-1 text-warning"></i>AI Strategy:</strong>
            <?= h($proposal['ai_rationale']) ?>
        </div>
        <?php endif; ?>

        <!-- Allocation Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-table me-2 text-primary"></i>Media Buy Allocation
                </h5>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Vendor</th>
                            <th>Category</th>
                            <th class="text-end text-success">Good</th>
                            <th class="text-end text-primary">Better</th>
                            <th class="text-end text-warning">Best</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $currentCat  = null;
                    $catGood     = $catBetter = $catBest = 0;
                    $totalGoodAll = $totalBetterAll = $totalBestAll = 0;
                    $rowCount    = count($unifiedRows);

                    foreach ($unifiedRows as $idx => $row):
                        $isNewCat    = $row['category'] !== $currentCat;
                        $isLastInCat = ($idx + 1 >= $rowCount) || ($unifiedRows[$idx + 1]['category'] !== $row['category']);

                        if ($isNewCat && $currentCat !== null):
                    ?>
                        <tr class="table-secondary text-muted fw-semibold small">
                            <td colspan="2" class="ps-4">
                                <i class="bi bi-arrow-return-right me-1"></i><?= h($currentCat) ?> Total
                            </td>
                            <td class="text-end"><?= $catGood > 0 ? '$' . number_format($catGood) : '—' ?></td>
                            <td class="text-end"><?= $catBetter > 0 ? '$' . number_format($catBetter) : '—' ?></td>
                            <td class="text-end"><?= $catBest > 0 ? '$' . number_format($catBest) : '—' ?></td>
                        </tr>
                    <?php
                            $catGood = $catBetter = $catBest = 0;
                        endif;

                        if ($isNewCat):
                            $currentCat = $row['category'];
                    ?>
                        <tr class="table-light">
                            <td colspan="5" class="fw-bold text-primary small py-2">
                                <i class="bi bi-tag-fill me-1"></i><?= h($row['category'] ?: 'Uncategorized') ?>
                            </td>
                        </tr>
                    <?php endif; ?>

                        <tr>
                            <td class="fw-semibold"><?= h($row['vendor_name']) ?></td>
                            <td class="text-muted small"><?= h($row['category']) ?></td>
                            <td class="text-end">
                                <?php if ($row['good_amount'] > 0): ?>
                                    <span class="fw-semibold text-success">$<?= number_format((float)$row['good_amount']) ?></span>
                                    <?php if (!empty($row['good_rationale'])): ?>
                                    <br><small class="text-muted"><?= h($row['good_rationale']) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($row['better_amount'] > 0): ?>
                                    <span class="fw-semibold text-primary">$<?= number_format((float)$row['better_amount']) ?></span>
                                    <?php if (!empty($row['better_rationale'])): ?>
                                    <br><small class="text-muted"><?= h($row['better_rationale']) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($row['best_amount'] > 0): ?>
                                    <span class="fw-semibold text-warning">$<?= number_format((float)$row['best_amount']) ?></span>
                                    <?php if (!empty($row['best_rationale'])): ?>
                                    <br><small class="text-muted"><?= h($row['best_rationale']) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                        </tr>

                    <?php
                        $catGood   += (float)($row['good_amount'] ?? 0);
                        $catBetter += (float)($row['better_amount'] ?? 0);
                        $catBest   += (float)($row['best_amount'] ?? 0);
                        $totalGoodAll   += (float)($row['good_amount'] ?? 0);
                        $totalBetterAll += (float)($row['better_amount'] ?? 0);
                        $totalBestAll   += (float)($row['best_amount'] ?? 0);

                        if ($isLastInCat):
                    ?>
                        <tr class="table-secondary text-muted fw-semibold small">
                            <td colspan="2" class="ps-4">
                                <i class="bi bi-arrow-return-right me-1"></i><?= h($currentCat) ?> Total
                            </td>
                            <td class="text-end"><?= $catGood > 0 ? '$' . number_format($catGood) : '—' ?></td>
                            <td class="text-end"><?= $catBetter > 0 ? '$' . number_format($catBetter) : '—' ?></td>
                            <td class="text-end"><?= $catBest > 0 ? '$' . number_format($catBest) : '—' ?></td>
                        </tr>
                    <?php
                            $catGood = $catBetter = $catBest = 0;
                        endif;
                    endforeach;
                    ?>
                    </tbody>
                    <tfoot class="table-dark fw-bold">
                        <tr>
                            <td colspan="2">GRAND TOTAL</td>
                            <td class="text-end text-success">$<?= number_format($totalGoodAll) ?></td>
                            <td class="text-end text-info">$<?= number_format($totalBetterAll) ?></td>
                            <td class="text-end text-warning">$<?= number_format($totalBestAll) ?></td>
                        </tr>
                        <tr class="small opacity-75">
                            <td colspan="2">Budget Target</td>
                            <td class="text-end">$<?= number_format((float)$proposal['budget_good']) ?></td>
                            <td class="text-end">$<?= number_format((float)$proposal['budget_better']) ?></td>
                            <td class="text-end">$<?= number_format((float)$proposal['budget_best']) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

    </div><!-- /col-lg-9 -->

    <!-- Right sidebar -->
    <div class="col-lg-3">

        <!-- Event Info -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-info-circle me-2 text-primary"></i>Event Info</h6>
            </div>
            <div class="card-body small">
                <?php if (!empty($proposal['client_name'])): ?>
                <div class="mb-2">
                    <div class="text-muted">Client</div>
                    <div class="fw-semibold"><?= h($proposal['client_name']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($proposal['event_description'])): ?>
                <div class="mb-2">
                    <div class="text-muted">Event</div>
                    <div><?= nl2br(h($proposal['event_description'])) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($proposal['event_demographics'])): ?>
                <div class="mb-2">
                    <div class="text-muted">Target Demographics</div>
                    <div><?= nl2br(h($proposal['event_demographics'])) ?></div>
                </div>
                <?php endif; ?>
                <div class="text-muted mt-2">
                    Created: <?= !empty($proposal['created_at']) ? h(date('M j, Y', strtotime($proposal['created_at']))) : '—' ?>
                </div>
                <?php if (!empty($proposal['sent_at'])): ?>
                <div class="text-muted">Sent: <?= h(date('M j, Y', strtotime($proposal['sent_at']))) ?></div>
                <?php endif; ?>
                <?php if (!empty($proposal['approved_at'])): ?>
                <div class="text-success">Approved: <?= h(date('M j, Y', strtotime($proposal['approved_at']))) ?></div>
                <?php endif; ?>
                <?php if (!empty($proposal['campaign_id'])): ?>
                <div class="mt-2">
                    <a href="/campaigns/view.php?id=<?= (int)$proposal['campaign_id'] ?>" class="btn btn-sm btn-outline-primary w-100">
                        <i class="bi bi-collection-play me-1"></i>View Campaign
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Actions -->
        <?php if ($status !== 'converted'): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-lightning me-2 text-primary"></i>Actions</h6>
            </div>
            <div class="card-body d-grid gap-2">

                <a href="/budget-planner/export.php?id=<?= $id ?>" class="btn btn-outline-success">
                    <i class="bi bi-file-earmark-excel me-1"></i>Download Excel
                </a>

                <?php if ($status === 'draft' && $isBuyer): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="mark_sent">
                    <button type="submit" class="btn btn-info w-100">
                        <i class="bi bi-send me-1"></i>Mark as Sent to Client
                    </button>
                </form>
                <?php endif; ?>

                <?php if (in_array($status, ['draft', 'sent'], true) && $isAdmin): ?>
                <hr class="my-1">
                <div class="small fw-semibold text-muted mb-1">Approve a Tier:</div>
                <form method="POST">
                    <input type="hidden" name="action" value="mark_approved">
                    <div class="mb-2">
                        <select class="form-select form-select-sm" name="approved_tier">
                            <option value="good">Good — $<?= number_format((float)$proposal['budget_good']) ?></option>
                            <option value="better">Better — $<?= number_format((float)$proposal['budget_better']) ?></option>
                            <option value="best">Best — $<?= number_format((float)$proposal['budget_best']) ?></option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-check-circle me-1"></i>Approve This Tier
                    </button>
                </form>
                <?php endif; ?>

                <?php if ($status === 'approved' && $isBuyer): ?>
                <hr class="my-1">
                <div class="small fw-semibold text-muted mb-1">Convert to Campaign:</div>
                <form method="POST">
                    <input type="hidden" name="action" value="convert">
                    <div class="mb-2">
                        <input type="text" class="form-control form-control-sm" name="campaign_title"
                               placeholder="Campaign title" value="<?= h($proposal['title']) ?>">
                    </div>
                    <div class="mb-2">
                        <select class="form-select form-select-sm" name="client_id">
                            <option value="">— Select Client —</option>
                            <?php foreach ($clients as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"
                                <?= (int)($proposal['client_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= h($c['company_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-collection-play me-1"></i>Create Campaign + RFPs
                    </button>
                </form>
                <div class="form-text small">
                    Creates a campaign with channels for each vendor in the
                    <strong><?= h(ucfirst($approvedTier ?? 'approved')) ?></strong> tier.
                    You can then send RFPs from the campaign page.
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Meta -->
        <div class="card border-0 shadow-sm">
            <div class="card-body small text-muted">
                <div class="mb-1"><strong>ID:</strong> #<?= (int)$proposal['id'] ?></div>
                <div class="mb-1"><strong>Created:</strong>
                    <?= !empty($proposal['created_at']) ? h(date('M j, Y g:ia', strtotime($proposal['created_at']))) : '—' ?>
                </div>
                <div><strong>Updated:</strong>
                    <?= !empty($proposal['updated_at']) ? h(date('M j, Y g:ia', strtotime($proposal['updated_at']))) : '—' ?>
                </div>
            </div>
        </div>

    </div><!-- /col-lg-3 -->
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
