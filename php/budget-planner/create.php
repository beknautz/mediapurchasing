<?php
require_once __DIR__ . '/../bootstrap.php';
requireRole(['admin', 'buyer']);

$plannerService = new BudgetPlannerService();
$userId = (int)($_SESSION['user']['id'] ?? 0);

// Load clients for dropdown
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

$errors      = [];
$aiResult    = null;
$unifiedRows = [];
$formData    = [
    'title'             => '',
    'event_description' => '',
    'event_demographics'=> '',
    'budget_good'       => '',
    'budget_better'     => '',
    'budget_best'       => '',
    'client_id'         => 0,
    'ai_rationale'      => '',
];

$action = trim($_POST['action'] ?? '');

// ─── POST: save final allocation ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {

    $formData['title']              = trim($_POST['title']              ?? '');
    $formData['event_description']  = trim($_POST['event_description']  ?? '');
    $formData['event_demographics'] = trim($_POST['event_demographics'] ?? '');
    $formData['budget_good']        = trim($_POST['budget_good']        ?? '');
    $formData['budget_better']      = trim($_POST['budget_better']      ?? '');
    $formData['budget_best']        = trim($_POST['budget_best']        ?? '');
    $formData['client_id']          = (int)($_POST['client_id']         ?? 0);
    $formData['ai_rationale']       = trim($_POST['ai_rationale']       ?? '');

    if ($formData['title'] === '') {
        $errors[] = 'Proposal title is required.';
    }
    if ($formData['budget_good'] === '' || !is_numeric($formData['budget_good'])) {
        $errors[] = 'Good budget amount is required.';
    }
    if ($formData['budget_better'] === '' || !is_numeric($formData['budget_better'])) {
        $errors[] = 'Better budget amount is required.';
    }
    if ($formData['budget_best'] === '' || !is_numeric($formData['budget_best'])) {
        $errors[] = 'Best budget amount is required.';
    }

    if (empty($errors)) {
        // Rebuild allocation JSON from POST vendor_data inputs
        $vendorData  = $_POST['vendor_data'] ?? [];
        $allocations = BudgetPlannerService::rebuildAllocationFromPost(is_array($vendorData) ? $vendorData : []);

        $result = $plannerService->save([
            'title'              => $formData['title'],
            'event_description'  => $formData['event_description'],
            'event_demographics' => $formData['event_demographics'],
            'budget_good'        => (float)$formData['budget_good'],
            'budget_better'      => (float)$formData['budget_better'],
            'budget_best'        => (float)$formData['budget_best'],
            'client_id'          => $formData['client_id'],
            'ai_rationale'       => $formData['ai_rationale'],
            'allocation_json'    => json_encode($allocations),
        ], $userId);

        if ($result['success']) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Budget proposal saved successfully.'];
            redirect('/budget-planner/view.php?id=' . $result['id']);
        } else {
            $errors[] = $result['message'];
        }
    }

    // Re-display editable table if save failed
    $vendorDataRaw = $_POST['vendor_data'] ?? [];
    $unifiedRows   = is_array($vendorDataRaw) ? array_values($vendorDataRaw) : [];
}

// ─── POST: generate AI allocation ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'generate') {

    $formData['title']              = trim($_POST['title']              ?? '');
    $formData['event_description']  = trim($_POST['event_description']  ?? '');
    $formData['event_demographics'] = trim($_POST['event_demographics'] ?? '');
    $formData['budget_good']        = trim($_POST['budget_good']        ?? '');
    $formData['budget_better']      = trim($_POST['budget_better']      ?? '');
    $formData['budget_best']        = trim($_POST['budget_best']        ?? '');
    $formData['client_id']          = (int)($_POST['client_id']         ?? 0);

    if ($formData['title'] === '') {
        $errors[] = 'Proposal title is required.';
    }
    if ($formData['budget_good'] === '' || !is_numeric($formData['budget_good']) || (float)$formData['budget_good'] <= 0) {
        $errors[] = 'Good budget must be a positive number.';
    }
    if ($formData['budget_better'] === '' || !is_numeric($formData['budget_better']) || (float)$formData['budget_better'] <= 0) {
        $errors[] = 'Better budget must be a positive number.';
    }
    if ($formData['budget_best'] === '' || !is_numeric($formData['budget_best']) || (float)$formData['budget_best'] <= 0) {
        $errors[] = 'Best budget must be a positive number.';
    }

    if (empty($errors)) {
        $aiResult = $plannerService->generate([
            'title'              => $formData['title'],
            'event_description'  => $formData['event_description'],
            'event_demographics' => $formData['event_demographics'],
            'budget_good'        => (float)$formData['budget_good'],
            'budget_better'      => (float)$formData['budget_better'],
            'budget_best'        => (float)$formData['budget_best'],
        ]);

        if (!$aiResult['success']) {
            $errors[] = $aiResult['message'];
            if (isset($aiResult['raw'])) {
                $errors[] = 'AI raw response (first 500 chars): ' . $aiResult['raw'];
            }
        } else {
            $formData['ai_rationale'] = $aiResult['data']['rationale'] ?? '';
            $unifiedRows = BudgetPlannerService::buildUnifiedTable($aiResult['data']['allocations'] ?? []);
        }
    }
}

$pageTitle = 'New Budget Proposal';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">
            <i class="bi bi-robot me-2 text-primary"></i>New Budget Proposal
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="/budget-planner/index.php">Budget Planner</a></li>
                <li class="breadcrumb-item active">New Proposal</li>
            </ol>
        </nav>
    </div>
    <a href="/budget-planner/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Please fix the following:</strong>
    <ul class="mb-0 mt-1">
        <?php foreach ($errors as $err): ?>
            <li><?= h($err) ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ─── Step 1: Event Details Form ──────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold">
            <i class="bi bi-1-circle me-2 text-primary"></i>Event Details &amp; Budget Targets
        </h5>
    </div>
    <div class="card-body">
        <form method="POST" id="generateForm">
            <input type="hidden" name="action" value="generate">
            <div class="row g-3">
                <div class="col-md-8">
                    <label for="title" class="form-label fw-semibold">Proposal Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" name="title"
                           value="<?= h($formData['title']) ?>"
                           placeholder="e.g. Cinco de Mayo Festival 2025 — Media Budget">
                </div>
                <div class="col-md-4">
                    <label for="client_id" class="form-label fw-semibold">Client</label>
                    <select class="form-select" id="client_id" name="client_id">
                        <option value="">— No Client —</option>
                        <?php foreach ($clients as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= (int)$formData['client_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= h($c['company_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="event_description" class="form-label fw-semibold">Event Description</label>
                    <textarea class="form-control" id="event_description" name="event_description" rows="4"
                              placeholder="Describe the event — what it is, location, date, expected attendance, goals..."><?= h($formData['event_description']) ?></textarea>
                </div>
                <div class="col-md-6">
                    <label for="event_demographics" class="form-label fw-semibold">Target Demographics</label>
                    <textarea class="form-control" id="event_demographics" name="event_demographics" rows="4"
                              placeholder="Describe who is likely to attend — age, income, language, ethnicity, interests, geography..."><?= h($formData['event_demographics']) ?></textarea>
                </div>
                <div class="col-md-4">
                    <label for="budget_good" class="form-label fw-semibold">Good Budget <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" id="budget_good" name="budget_good"
                               value="<?= h($formData['budget_good']) ?>" min="1" step="500"
                               placeholder="50000">
                    </div>
                    <div class="form-text">Entry-level budget option</div>
                </div>
                <div class="col-md-4">
                    <label for="budget_better" class="form-label fw-semibold">Better Budget <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" id="budget_better" name="budget_better"
                               value="<?= h($formData['budget_better']) ?>" min="1" step="500"
                               placeholder="75000">
                    </div>
                    <div class="form-text">Mid-range budget option</div>
                </div>
                <div class="col-md-4">
                    <label for="budget_best" class="form-label fw-semibold">Best Budget <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" id="budget_best" name="budget_best"
                               value="<?= h($formData['budget_best']) ?>" min="1" step="500"
                               placeholder="100000">
                    </div>
                    <div class="form-text">Premium budget option</div>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary btn-lg" id="generateBtn">
                    <i class="bi bi-robot me-2"></i>Generate AI Budget Allocation
                </button>
                <span class="text-muted ms-3 small" id="generatingMsg" style="display:none;">
                    <span class="spinner-border spinner-border-sm me-1"></span>
                    Asking Claude AI to analyze your vendors and allocate the budget… this may take 30–60 seconds.
                </span>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($unifiedRows) && empty($errors)): ?>
<!-- ─── Step 2: Editable Allocation Table ─────────────────────────────── -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-semibold">
            <i class="bi bi-2-circle me-2"></i>Review &amp; Edit AI Budget Allocation
        </h5>
        <span class="small opacity-75">Adjust amounts as needed, then save the proposal</span>
    </div>

    <?php if (!empty($formData['ai_rationale'])): ?>
    <div class="card-body border-bottom bg-light py-3">
        <strong><i class="bi bi-lightbulb me-1 text-warning"></i>AI Strategy:</strong>
        <?= h($formData['ai_rationale']) ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="saveForm">
        <input type="hidden" name="action"              value="save">
        <input type="hidden" name="title"               value="<?= h($formData['title']) ?>">
        <input type="hidden" name="event_description"   value="<?= h($formData['event_description']) ?>">
        <input type="hidden" name="event_demographics"  value="<?= h($formData['event_demographics']) ?>">
        <input type="hidden" name="budget_good"         value="<?= h($formData['budget_good']) ?>">
        <input type="hidden" name="budget_better"       value="<?= h($formData['budget_better']) ?>">
        <input type="hidden" name="budget_best"         value="<?= h($formData['budget_best']) ?>">
        <input type="hidden" name="client_id"           value="<?= h($formData['client_id']) ?>">
        <input type="hidden" name="ai_rationale"        value="<?= h($formData['ai_rationale']) ?>">

        <div class="table-responsive">
            <table class="table align-middle mb-0" id="allocationTable">
                <thead class="table-dark">
                    <tr>
                        <th style="width:20%">Category</th>
                        <th style="width:22%">Vendor</th>
                        <th class="text-end" style="width:16%">
                            Good<br>
                            <small class="fw-normal opacity-75">Target: $<?= number_format((float)$formData['budget_good']) ?></small>
                        </th>
                        <th class="text-end" style="width:16%">
                            Better<br>
                            <small class="fw-normal opacity-75">Target: $<?= number_format((float)$formData['budget_better']) ?></small>
                        </th>
                        <th class="text-end" style="width:16%">
                            Best<br>
                            <small class="fw-normal opacity-75">Target: $<?= number_format((float)$formData['budget_best']) ?></small>
                        </th>
                        <th style="width:10%"></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $currentCat = null;
                $catGood = $catBetter = $catBest = 0;
                $rowCount = count($unifiedRows);

                foreach ($unifiedRows as $idx => $row):
                    $isNewCat = ($row['category'] !== $currentCat);
                    $isLastInCat = ($idx + 1 >= $rowCount) || ($unifiedRows[$idx + 1]['category'] !== $row['category']);

                    if ($isNewCat && $currentCat !== null):
                ?>
                    <!-- Category subtotal -->
                    <tr class="table-secondary fw-semibold">
                        <td colspan="2" class="text-muted small ps-4">
                            <i class="bi bi-arrow-return-right me-1"></i><?= h($currentCat) ?> Total
                        </td>
                        <td class="text-end small" data-cat-total="good-<?= h(preg_replace('/\W/', '_', $currentCat ?? '')) ?>">
                            $<?= number_format($catGood) ?>
                        </td>
                        <td class="text-end small" data-cat-total="better-<?= h(preg_replace('/\W/', '_', $currentCat ?? '')) ?>">
                            $<?= number_format($catBetter) ?>
                        </td>
                        <td class="text-end small" data-cat-total="best-<?= h(preg_replace('/\W/', '_', $currentCat ?? '')) ?>">
                            $<?= number_format($catBest) ?>
                        </td>
                        <td></td>
                    </tr>
                <?php
                        $catGood = $catBetter = $catBest = 0;
                    endif;

                    if ($isNewCat):
                        $currentCat = $row['category'];
                ?>
                    <!-- Category header row -->
                    <tr class="table-light">
                        <td colspan="6" class="fw-bold text-primary small py-2">
                            <i class="bi bi-tag-fill me-1"></i><?= h($row['category'] ?: 'Uncategorized') ?>
                        </td>
                    </tr>
                <?php endif; ?>

                    <!-- Vendor row -->
                    <tr>
                        <td class="ps-4 text-muted small"><?= h($row['category']) ?></td>
                        <td class="fw-semibold">
                            <?= h($row['vendor_name']) ?>
                            <input type="hidden" name="vendor_data[<?= $idx ?>][vendor_id]"   value="<?= (int)$row['vendor_id'] ?>">
                            <input type="hidden" name="vendor_data[<?= $idx ?>][vendor_name]" value="<?= h($row['vendor_name']) ?>">
                            <input type="hidden" name="vendor_data[<?= $idx ?>][category]"    value="<?= h($row['category']) ?>">
                            <input type="hidden" name="vendor_data[<?= $idx ?>][good_rationale]"   value="<?= h($row['good_rationale'] ?? '') ?>">
                            <input type="hidden" name="vendor_data[<?= $idx ?>][better_rationale]" value="<?= h($row['better_rationale'] ?? '') ?>">
                            <input type="hidden" name="vendor_data[<?= $idx ?>][best_rationale]"   value="<?= h($row['best_rationale'] ?? '') ?>">
                        </td>
                        <td class="text-end">
                            <div class="input-group input-group-sm justify-content-end">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control text-end tier-input"
                                       name="vendor_data[<?= $idx ?>][good_amount]"
                                       data-tier="good"
                                       value="<?= (float)($row['good_amount'] ?? 0) ?>"
                                       min="0" step="100" style="max-width:100px;">
                            </div>
                        </td>
                        <td class="text-end">
                            <div class="input-group input-group-sm justify-content-end">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control text-end tier-input"
                                       name="vendor_data[<?= $idx ?>][better_amount]"
                                       data-tier="better"
                                       value="<?= (float)($row['better_amount'] ?? 0) ?>"
                                       min="0" step="100" style="max-width:100px;">
                            </div>
                        </td>
                        <td class="text-end">
                            <div class="input-group input-group-sm justify-content-end">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control text-end tier-input"
                                       name="vendor_data[<?= $idx ?>][best_amount]"
                                       data-tier="best"
                                       value="<?= (float)($row['best_amount'] ?? 0) ?>"
                                       min="0" step="100" style="max-width:100px;">
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="text-muted small" data-bs-toggle="tooltip"
                                  title="<?= h(implode(' | ', array_filter([
                                      $row['good_rationale']   ? 'Good: '   . $row['good_rationale']   : '',
                                      $row['better_rationale'] ? 'Better: ' . $row['better_rationale'] : '',
                                      $row['best_rationale']   ? 'Best: '   . $row['best_rationale']   : '',
                                  ]))) ?>">
                                <i class="bi bi-info-circle"></i>
                            </span>
                        </td>
                    </tr>

                <?php
                    $catGood   += (float)($row['good_amount'] ?? 0);
                    $catBetter += (float)($row['better_amount'] ?? 0);
                    $catBest   += (float)($row['best_amount'] ?? 0);

                    if ($isLastInCat):
                ?>
                    <!-- Last category subtotal -->
                    <tr class="table-secondary fw-semibold">
                        <td colspan="2" class="text-muted small ps-4">
                            <i class="bi bi-arrow-return-right me-1"></i><?= h($currentCat) ?> Total
                        </td>
                        <td class="text-end small">$<?= number_format($catGood) ?></td>
                        <td class="text-end small">$<?= number_format($catBetter) ?></td>
                        <td class="text-end small">$<?= number_format($catBest) ?></td>
                        <td></td>
                    </tr>
                <?php
                        $catGood = $catBetter = $catBest = 0;
                    endif;
                endforeach;
                ?>
                </tbody>
                <tfoot class="table-dark fw-bold">
                    <tr>
                        <td colspan="2">Grand Total</td>
                        <td class="text-end" id="totalGood">$0</td>
                        <td class="text-end" id="totalBetter">$0</td>
                        <td class="text-end" id="totalBest">$0</td>
                        <td></td>
                    </tr>
                    <tr class="small">
                        <td colspan="2" class="text-muted">Target</td>
                        <td class="text-end text-muted">$<?= number_format((float)$formData['budget_good']) ?></td>
                        <td class="text-end text-muted">$<?= number_format((float)$formData['budget_better']) ?></td>
                        <td class="text-end text-muted">$<?= number_format((float)$formData['budget_best']) ?></td>
                        <td></td>
                    </tr>
                    <tr class="small" id="diffRow">
                        <td colspan="2" class="text-muted">Difference</td>
                        <td class="text-end" id="diffGood">$0</td>
                        <td class="text-end" id="diffBetter">$0</td>
                        <td class="text-end" id="diffBest">$0</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
            <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('generateForm').submit();">
                <i class="bi bi-arrow-clockwise me-1"></i>Regenerate
            </button>
            <button type="submit" class="btn btn-success btn-lg">
                <i class="bi bi-floppy me-2"></i>Save Proposal
            </button>
        </div>
    </form>
</div>

<script>
(function () {
    var targetGood   = <?= (float)($formData['budget_good']   ?? 0) ?>;
    var targetBetter = <?= (float)($formData['budget_better'] ?? 0) ?>;
    var targetBest   = <?= (float)($formData['budget_best']   ?? 0) ?>;

    function fmt(n) {
        return '$' + Math.round(n).toLocaleString('en-US');
    }

    function recalc() {
        var good = 0, better = 0, best = 0;
        document.querySelectorAll('.tier-input').forEach(function(inp) {
            var v = parseFloat(inp.value) || 0;
            if (inp.dataset.tier === 'good')   good   += v;
            if (inp.dataset.tier === 'better') better += v;
            if (inp.dataset.tier === 'best')   best   += v;
        });

        document.getElementById('totalGood').textContent   = fmt(good);
        document.getElementById('totalBetter').textContent = fmt(better);
        document.getElementById('totalBest').textContent   = fmt(best);

        var dg = good   - targetGood;
        var db = better - targetBetter;
        var dbs = best  - targetBest;

        var dGood   = document.getElementById('diffGood');
        var dBetter = document.getElementById('diffBetter');
        var dBest   = document.getElementById('diffBest');

        dGood.textContent   = (dg >= 0 ? '+' : '') + fmt(dg);
        dBetter.textContent = (db >= 0 ? '+' : '') + fmt(db);
        dBest.textContent   = (dbs >= 0 ? '+' : '') + fmt(dbs);

        dGood.className   = dg   === 0 ? 'text-end text-success' : 'text-end text-danger';
        dBetter.className = db   === 0 ? 'text-end text-success' : 'text-end text-danger';
        dBest.className   = dbs  === 0 ? 'text-end text-success' : 'text-end text-danger';
    }

    document.querySelectorAll('.tier-input').forEach(function(inp) {
        inp.addEventListener('input', recalc);
    });

    recalc();

    // Init tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
        new bootstrap.Tooltip(el);
    });
})();

document.getElementById('generateForm').addEventListener('submit', function() {
    document.getElementById('generateBtn').disabled = true;
    document.getElementById('generatingMsg').style.display = '';
});
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
