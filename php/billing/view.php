<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['loggedIn'])) {
    redirect('/auth/login.php');
}

$role    = $_SESSION['role'] ?? '';
$userId  = (int) ($_SESSION['user']['id'] ?? 0);
$isAdmin = $role === 'admin';
$isBuyer = in_array($role, ['admin', 'buyer'], true);

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid invoice ID.'];
    redirect('/billing/index.php');
}

$billingService = new BillingService();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    if ($action === 'update_status' && $isBuyer) {
        $newStatus   = trim($_POST['new_status']   ?? '');
        $statusNotes = trim($_POST['status_notes'] ?? '');
        $validStatuses = ['waiting', 'in_progress', 'on_hold', 'completed'];
        if (in_array($newStatus, $validStatuses, true)) {
            $billingService->updateStatus($id, $newStatus, $statusNotes);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Status updated.'];
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid status.'];
        }
        redirect('/billing/view.php?id=' . $id);
    }

    if ($action === 'assign' && $isAdmin) {
        $assignedUserId = (int) ($_POST['assigned_user_id'] ?? 0);
        $billingService->assign($id, $assignedUserId);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Invoice assigned.'];
        redirect('/billing/view.php?id=' . $id);
    }
}

$bill = $billingService->getBill($id);

if (empty($bill)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invoice not found.'];
    redirect('/billing/index.php');
}

// Load users for assign dropdown (admin only)
$users = [];
if ($isAdmin) {
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
    if ($pdo) {
        $users = $pdo->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM users WHERE active = 1 ORDER BY first_name")->fetchAll();
    }
}

$statusColors = [
    'waiting'     => 'secondary',
    'in_progress' => 'primary',
    'on_hold'     => 'warning',
    'completed'   => 'success',
];
$statusLabels = [
    'waiting'     => 'Waiting',
    'in_progress' => 'In Progress',
    'on_hold'     => 'On Hold',
    'completed'   => 'Completed',
];

$priorityBadgeClass = [
    'urgent' => 'bg-danger text-white',
    'high'   => 'bg-warning text-dark',
    'normal' => 'bg-primary text-white',
    'low'    => 'bg-secondary text-white',
];

$bStatus    = $bill['status']   ?? 'waiting';
$priority   = $bill['priority'] ?? 'normal';
$statusColor= $statusColors[$bStatus] ?? 'secondary';
$statusLabel= $statusLabels[$bStatus] ?? ucfirst($bStatus);
$priBadge   = $priorityBadgeClass[$priority] ?? 'bg-secondary text-white';

$dueDate    = $bill['due_date'] ?? null;
$isOverdue  = $dueDate && strtotime($dueDate) < time() && $bStatus !== 'completed';

$pageTitle = 'Invoice #' . h($bill['invoice_number'] ?? $id);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="/billing/index.php">Billing Queue</a></li>
                <li class="breadcrumb-item active">Invoice #<?= h($bill['invoice_number'] ?? $id) ?></li>
            </ol>
        </nav>
        <h1 class="h3 mb-0 fw-bold">
            <i class="bi bi-receipt me-2 text-primary"></i>
            <?= h($bill['vendor_name'] ?? 'Unknown Vendor') ?>
        </h1>
        <div class="mt-1">
            <span class="badge <?= $priBadge ?> me-1"><?= h(ucfirst($priority)) ?></span>
            <span class="badge bg-<?= $statusColor ?>"><?= h($statusLabel) ?></span>
            <?php if ($isOverdue): ?>
                <span class="badge bg-danger ms-1"><i class="bi bi-exclamation-triangle me-1"></i>Overdue</span>
            <?php endif; ?>
        </div>
    </div>
    <a href="/billing/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Queue
    </a>
</div>

<div class="row g-4">

    <!-- Invoice Details -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Invoice Details</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="text-muted small">Vendor</div>
                        <div class="fw-bold"><?= h($bill['vendor_name'] ?? '—') ?></div>
                        <?php if (!empty($bill['vendor_email'])): ?>
                        <div class="small">
                            <a href="mailto:<?= h($bill['vendor_email']) ?>?subject=Invoice%20<?= urlencode($bill['invoice_number'] ?? '') ?>">
                                <i class="bi bi-envelope me-1"></i><?= h($bill['vendor_email']) ?>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Invoice Number</div>
                        <div class="fw-bold"><?= h($bill['invoice_number'] ?? '—') ?></div>
                    </div>
                    <div class="col-sm-4">
                        <div class="text-muted small">Amount</div>
                        <div class="fw-bold fs-4 text-primary">
                            <?= !empty($bill['amount']) ? '$' . number_format((float)$bill['amount'], 2) : '—' ?>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="text-muted small">Invoice Date</div>
                        <div class="fw-semibold">
                            <?= !empty($bill['invoice_date']) ? h(date('M j, Y', strtotime($bill['invoice_date']))) : '—' ?>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="text-muted small">Due Date</div>
                        <div class="fw-semibold <?= $isOverdue ? 'text-danger' : '' ?>">
                            <?php if ($dueDate): ?>
                                <?php if ($isOverdue): ?>
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                <?php endif; ?>
                                <?= h(date('M j, Y', strtotime($dueDate))) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Intake Method</div>
                        <div class="fw-semibold"><?= h(ucfirst($bill['intake_method'] ?? '—')) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Assigned To</div>
                        <div class="fw-semibold"><?= h($bill['assigned_user_name'] ?? '—') ?></div>
                    </div>
                    <?php if (!empty($bill['media_buy_id'])): ?>
                    <div class="col-sm-12">
                        <div class="text-muted small">Linked Media Buy</div>
                        <a href="/media-buys/view.php?id=<?= (int)$bill['media_buy_id'] ?>" class="fw-semibold">
                            <i class="bi bi-collection-play me-1"></i>
                            <?= h($bill['media_buy_title'] ?? '#' . $bill['media_buy_id']) ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($bill['notes'])): ?>
                <hr>
                <div>
                    <div class="text-muted small mb-1">Notes</div>
                    <div class="text-break"><?= nl2br(h($bill['notes'])) ?></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($bill['file_path'])): ?>
                <hr>
                <div>
                    <div class="text-muted small mb-1">Attached Invoice</div>
                    <a href="/<?= h(ltrim($bill['file_path'], '/')) ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-file-earmark-arrow-down me-1"></i>Download Invoice File
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Vendor Email shortcut -->
        <?php if (!empty($bill['vendor_email'])): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <i class="bi bi-envelope text-primary fs-4"></i>
                <div class="flex-grow-1">
                    <div class="fw-semibold small">Contact Vendor About Invoice</div>
                    <div class="text-muted small"><?= h($bill['vendor_email']) ?></div>
                </div>
                <a href="mailto:<?= h($bill['vendor_email']) ?>?subject=<?= urlencode('Re: Invoice ' . ($bill['invoice_number'] ?? '')) ?>&body=<?= urlencode("Hello,\n\nI'm following up regarding invoice " . ($bill['invoice_number'] ?? '') . '.' . "\n\nThank you.") ?>"
                   class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-envelope-fill me-1"></i>Email Vendor
                </a>
                <a href="/communications/compose.php?to_email=<?= urlencode($bill['vendor_email']) ?>&media_buy_id=<?= (int)($bill['media_buy_id'] ?? 0) ?>"
                   class="btn btn-sm btn-primary">
                    <i class="bi bi-send me-1"></i>Compose
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right Sidebar -->
    <div class="col-lg-4">

        <!-- Update Status -->
        <?php if ($isBuyer): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-arrow-repeat me-2 text-primary"></i>Update Status</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    <div class="mb-3">
                        <label for="new_status" class="form-label fw-semibold">New Status</label>
                        <select class="form-select" id="new_status" name="new_status">
                            <?php foreach ($statusLabels as $sv => $sl): ?>
                                <option value="<?= h($sv) ?>" <?= $sv === $bStatus ? 'selected' : '' ?>>
                                    <?= h($sl) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="status_notes" class="form-label fw-semibold">Notes (optional)</label>
                        <textarea class="form-control" id="status_notes" name="status_notes" rows="2"
                                  placeholder="Reason for status change…"></textarea>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Assign (Admin only) -->
        <?php if ($isAdmin): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-person-check me-2 text-primary"></i>Assign To</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="assign">
                    <div class="mb-3">
                        <label for="assigned_user_id" class="form-label fw-semibold">Team Member</label>
                        <select class="form-select" id="assigned_user_id" name="assigned_user_id">
                            <option value="">— Unassigned —</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= (int)$u['id'] ?>"
                                    <?= (int)($bill['assigned_user_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>>
                                    <?= h($u['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="bi bi-person-check me-1"></i>Assign
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Meta info -->
        <div class="card border-0 shadow-sm">
            <div class="card-body small text-muted">
                <div class="mb-1"><strong>ID:</strong> #<?= (int)$bill['id'] ?></div>
                <div class="mb-1"><strong>Created:</strong>
                    <?= !empty($bill['created_at']) ? h(date('M j, Y g:ia', strtotime($bill['created_at']))) : '—' ?>
                </div>
                <div><strong>Last Updated:</strong>
                    <?= !empty($bill['updated_at']) ? h(date('M j, Y g:ia', strtotime($bill['updated_at']))) : '—' ?>
                </div>
            </div>
        </div>

    </div><!-- /col-lg-4 -->
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
