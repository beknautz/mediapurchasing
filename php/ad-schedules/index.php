<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';
requireRole(['admin', 'buyer']);

$svc     = new AdScheduleService();
$flashMsg = flash('success');
$errorMsg = flash('error');

// ── POST: create new plan ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'new_plan') {
    $clientId = (int) ($_POST['client_id'] ?? 0);
    $year     = (int) ($_POST['year']      ?? date('Y'));
    if ($clientId > 0 && $year >= 2000) {
        try {
            $planId = $svc->ensurePlan($clientId, $year);
            redirect('/ad-schedules/plan.php?id=' . $planId);
        } catch (Exception $e) {
            $errorMsg = 'Could not create plan: ' . $e->getMessage();
        }
    } else {
        $errorMsg = 'Select a client and year.';
    }
}

try {
    $plans   = $svc->getPlans();
    $clients = $svc->getClients();
    $overdue = $svc->getOverdueUnbilled();
} catch (Exception $e) {
    $plans   = [];
    $clients = [];
    $overdue = [];
    $errorMsg = 'Run sql/migrate_ad_schedules.sql first. (' . $e->getMessage() . ')';
}

$years      = range((int)date('Y') + 1, (int)date('Y') - 3);
$pageTitle  = 'Annual Ad Schedules — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">
            <i class="bi bi-calendar3 me-2 text-primary"></i>Annual Ad Schedules
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Ad Schedules</li>
            </ol>
        </nav>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newPlanModal">
        <i class="bi bi-plus-circle me-1"></i>New Plan
    </button>
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

<!-- ── Overdue Unbilled Alert ──────────────────────────────────────────────── -->
<?php if (!empty($overdue)): ?>
<div class="alert alert-warning alert-dismissible fade show mb-4">
    <div class="d-flex align-items-start gap-2">
        <i class="bi bi-alarm-fill fs-5 mt-1 flex-shrink-0"></i>
        <div>
            <strong><?= count($overdue) ?> ad<?= count($overdue) !== 1 ? 's' : '' ?> need billing</strong>
            — past run date by 7+ days, placement not yet invoiced.
            <div class="mt-2 d-flex flex-wrap gap-2">
                <?php foreach (array_slice($overdue, 0, 6) as $od): ?>
                <a href="/ad-schedules/plan.php?id=<?= (int)$od['plan_id'] ?>#row-<?= (int)$od['id'] ?>"
                   class="badge bg-warning text-dark text-decoration-none">
                    <?= h($od['publication']) ?> — <?= h($od['editorial'] ?? '') ?>
                    <span class="ms-1 opacity-75">(<?= (int)$od['days_overdue'] ?>d ago)</span>
                </a>
                <?php endforeach; ?>
                <?php if (count($overdue) > 6): ?>
                <span class="badge bg-secondary">+<?= count($overdue) - 6 ?> more</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ── Plans Table ─────────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($plans)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-calendar3 fs-2 d-block mb-2 opacity-50"></i>
            <div>No annual plans yet.</div>
            <button type="button" class="btn btn-sm btn-outline-primary mt-3"
                    data-bs-toggle="modal" data-bs-target="#newPlanModal">
                <i class="bi bi-plus-circle me-1"></i>Create First Plan
            </button>
        </div>
        <?php else: ?>
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Client</th>
                    <th class="text-center">Year</th>
                    <th class="text-center">Placements</th>
                    <th class="text-end">Client Spend</th>
                    <th class="text-center">Billed</th>
                    <th class="text-center">Alerts</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($plans as $plan):
                $pct = $plan['placement_count'] > 0
                     ? round(100 * $plan['billed_count'] / $plan['placement_count'])
                     : 0;
            ?>
            <tr>
                <td class="fw-semibold"><?= h($plan['company_name']) ?></td>
                <td class="text-center">
                    <span class="badge bg-secondary"><?= (int)$plan['year'] ?></span>
                </td>
                <td class="text-center text-muted small"><?= (int)$plan['placement_count'] ?></td>
                <td class="text-end fw-semibold">
                    <?= $plan['total_client_cost'] !== null
                        ? '$' . number_format((float)$plan['total_client_cost'], 2)
                        : '—' ?>
                </td>
                <td class="text-center" style="width:140px;">
                    <?php if ($plan['placement_count'] > 0): ?>
                    <div class="progress" style="height:8px;" title="<?= $pct ?>% billed">
                        <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
                    </div>
                    <div class="small text-muted mt-1"><?= (int)$plan['billed_count'] ?> / <?= (int)$plan['placement_count'] ?></div>
                    <?php else: ?>
                    <span class="text-muted small">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php if ($plan['overdue_count'] > 0): ?>
                    <span class="badge bg-danger">
                        <i class="bi bi-alarm me-1"></i><?= (int)$plan['overdue_count'] ?>
                    </span>
                    <?php else: ?>
                    <span class="text-success small"><i class="bi bi-check-circle"></i></span>
                    <?php endif; ?>
                </td>
                <td class="text-end">
                    <a href="/ad-schedules/plan.php?id=<?= (int)$plan['id'] ?>"
                       class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-table me-1"></i>Open
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- ── New Plan Modal ──────────────────────────────────────────────────────── -->
<div class="modal fade" id="newPlanModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST" action="/ad-schedules/index.php">
                <input type="hidden" name="action" value="new_plan">
                <div class="modal-header">
                    <h5 class="modal-title fw-semibold">New Annual Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="newClient" class="form-label fw-semibold">Client</label>
                        <select class="form-select" id="newClient" name="client_id" required>
                            <option value="">— Select client —</option>
                            <?php foreach ($clients as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= h($c['company_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="newYear" class="form-label fw-semibold">Year</label>
                        <select class="form-select" id="newYear" name="year">
                            <?php foreach ($years as $y): ?>
                            <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>>
                                <?= $y ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-arrow-right-circle me-1"></i>Create &amp; Open
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
