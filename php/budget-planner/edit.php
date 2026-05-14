<?php
/**
 * budget-planner/edit.php
 * Edit an existing budget proposal's allocation table.
 * Allows adding / removing vendors and sub-rows, adjusting amounts.
 */
require_once __DIR__ . '/../bootstrap.php';
requireRole(['admin', 'buyer']);

$plannerService = new BudgetPlannerService();
$userId  = (int)($_SESSION['user']['id'] ?? 0);
$id      = (int)($_GET['id'] ?? 0);

if ($id <= 0) redirect('/budget-planner/index.php');

$proposal = $plannerService->getProposal($id);
if (empty($proposal)) {
    flash('danger', 'Proposal not found.');
    redirect('/budget-planner/index.php');
}

$errors      = [];
$allocations = json_decode($proposal['allocation_json'] ?? '{}', true) ?: [];
$unifiedRows = BudgetPlannerService::buildUnifiedTable($allocations);

$formData = [
    'title'             => $proposal['title']              ?? '',
    'event_description' => $proposal['event_description']  ?? '',
    'event_demographics'=> $proposal['event_demographics'] ?? '',
    'budget_good'       => $proposal['budget_good']        ?? 0,
    'budget_better'     => $proposal['budget_better']      ?? 0,
    'budget_best'       => $proposal['budget_best']        ?? 0,
    'client_id'         => $proposal['client_id']          ?? 0,
    'ai_rationale'      => $proposal['ai_rationale']       ?? '',
];

// ─── POST: save updated allocation + budgets ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && trim($_POST['action'] ?? '') === 'save') {

    $postBudgetGood   = trim($_POST['budget_good']   ?? '');
    $postBudgetBetter = trim($_POST['budget_better'] ?? '');
    $postBudgetBest   = trim($_POST['budget_best']   ?? '');

    if ($postBudgetGood === '' || !is_numeric($postBudgetGood) || (float)$postBudgetGood < 0) {
        $errors[] = 'Good budget must be a valid positive number.';
    }
    if ($postBudgetBetter === '' || !is_numeric($postBudgetBetter) || (float)$postBudgetBetter < 0) {
        $errors[] = 'Better budget must be a valid positive number.';
    }
    if ($postBudgetBest === '' || !is_numeric($postBudgetBest) || (float)$postBudgetBest < 0) {
        $errors[] = 'Best budget must be a valid positive number.';
    }

    // Reflect posted budgets back into formData so they survive validation errors
    $formData['budget_good']   = $postBudgetGood;
    $formData['budget_better'] = $postBudgetBetter;
    $formData['budget_best']   = $postBudgetBest;

    if (empty($errors)) {
        $vendorData = $_POST['vendor_data'] ?? [];
        $newAllocs  = BudgetPlannerService::rebuildAllocationFromPost(is_array($vendorData) ? $vendorData : []);

        $result = $plannerService->save(array_merge($formData, [
            'id'              => $id,
            'budget_good'     => (float)$postBudgetGood,
            'budget_better'   => (float)$postBudgetBetter,
            'budget_best'     => (float)$postBudgetBest,
            'allocation_json' => json_encode($newAllocs),
        ]), $userId);

        if ($result['success']) {
            flash('success', 'Proposal updated successfully.');
            redirect('/budget-planner/view.php?id=' . $id);
        } else {
            $errors[] = $result['message'];
        }
    }

    // Rebuild display rows from POST data so edits aren't lost on error
    $vendorDataRaw = $_POST['vendor_data'] ?? [];
    $unifiedRows   = is_array($vendorDataRaw) ? array_values($vendorDataRaw) : [];
}

$pageTitle = 'Edit Allocation — ' . $proposal['title'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">
            <i class="bi bi-pencil-square me-2 text-primary"></i>Edit Allocation
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="/budget-planner/index.php">Budget Planner</a></li>
                <li class="breadcrumb-item"><a href="/budget-planner/view.php?id=<?= $id ?>"><?= h($proposal['title']) ?></a></li>
                <li class="breadcrumb-item active">Edit Allocation</li>
            </ol>
        </nav>
    </div>
    <a href="/budget-planner/view.php?id=<?= $id ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Cancel
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

<?php if (!empty($formData['ai_rationale'])): ?>
<div class="alert alert-light border mb-4">
    <strong><i class="bi bi-lightbulb me-1 text-warning"></i>AI Strategy:</strong>
    <?= h($formData['ai_rationale']) ?>
</div>
<?php endif; ?>

<!-- ─── Editable Allocation Table ──────────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-semibold">
            <i class="bi bi-table me-2"></i>Media Buy Allocation
        </h5>
        <span class="small opacity-75">
            Adjust amounts, rename vendors, or add rows — then save.
        </span>
    </div>

    <form method="POST" id="saveForm">
        <input type="hidden" name="action" value="save">

        <!-- ── Budget Targets ─────────────────────────────────────────────── -->
        <div class="card-body border-bottom bg-light py-3">
            <div class="row g-3 align-items-end">
                <div class="col-auto d-flex align-items-center">
                    <span class="fw-semibold small text-muted">
                        <i class="bi bi-bullseye me-1"></i>Budget Targets:
                    </span>
                </div>
                <div class="col-sm-3">
                    <label class="form-label small mb-1 fw-semibold text-success">Good</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control budget-target-input" id="budgetGood"
                               name="budget_good" value="<?= (float)$formData['budget_good'] ?>"
                               min="0" step="any" placeholder="0">
                    </div>
                </div>
                <div class="col-sm-3">
                    <label class="form-label small mb-1 fw-semibold text-primary">Better</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control budget-target-input" id="budgetBetter"
                               name="budget_better" value="<?= (float)$formData['budget_better'] ?>"
                               min="0" step="any" placeholder="0">
                    </div>
                </div>
                <div class="col-sm-3">
                    <label class="form-label small mb-1 fw-semibold text-warning">Best</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control budget-target-input" id="budgetBest"
                               name="budget_best" value="<?= (float)$formData['budget_best'] ?>"
                               min="0" step="any" placeholder="0">
                    </div>
                </div>
                <div class="col-auto">
                    <p class="small text-muted mb-0">Change a target to adjust<br>the difference row below.</p>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0" id="allocationTable">
                <thead class="table-dark">
                    <tr>
                        <th style="width:32px"></th><!-- drag handle -->
                        <th style="width:18%">Category</th>
                        <th>Vendor</th>
                        <th class="text-end" style="width:14%">Good</th>
                        <th class="text-end" style="width:14%">Better</th>
                        <th class="text-end" style="width:14%">Best</th>
                        <th style="width:88px"></th><!-- actions -->
                    </tr>
                </thead>
                <tbody id="vendorRows">
                <?php foreach ($unifiedRows as $idx => $row):
                    $isSubrow = !empty($row['is_subrow']);
                ?>
                    <tr class="vendor-row<?= $isSubrow ? ' subrow' : '' ?>"<?= $isSubrow ? ' style="background:#f0f4ff;"' : '' ?>>
                        <td class="drag-handle text-center text-muted" style="cursor:grab;" title="<?= $isSubrow ? 'Sub-row' : 'Drag to reorder' ?>">
                            <?php if ($isSubrow): ?>
                            <i class="bi bi-arrow-return-right fs-5 text-primary opacity-50"></i>
                            <?php else: ?>
                            <i class="bi bi-grip-vertical fs-5"></i>
                            <?php endif; ?>
                        </td>
                        <td>
                            <input type="text" class="form-control form-control-sm"
                                   name="vendor_data[<?= $idx ?>][category]"
                                   value="<?= h($row['category'] ?? '') ?>" placeholder="Category">
                        </td>
                        <td<?= $isSubrow ? ' style="padding-left:2rem; border-left:3px solid #0d6efd;"' : '' ?>>
                            <input type="text" class="form-control form-control-sm fw-semibold"
                                   name="vendor_data[<?= $idx ?>][vendor_name]"
                                   value="<?= h($row['vendor_name'] ?? '') ?>" placeholder="Vendor name">
                            <input type="hidden" name="vendor_data[<?= $idx ?>][vendor_id]"        value="<?= (int)($row['vendor_id'] ?? 0) ?>">
                            <input type="hidden" name="vendor_data[<?= $idx ?>][good_rationale]"   value="<?= h($row['good_rationale']   ?? '') ?>">
                            <input type="hidden" name="vendor_data[<?= $idx ?>][better_rationale]" value="<?= h($row['better_rationale'] ?? '') ?>">
                            <input type="hidden" name="vendor_data[<?= $idx ?>][best_rationale]"   value="<?= h($row['best_rationale']   ?? '') ?>">
                            <input type="hidden" name="vendor_data[<?= $idx ?>][is_subrow]"        value="<?= $isSubrow ? 1 : 0 ?>">
                        </td>
                        <td class="text-end">
                            <div class="input-group input-group-sm justify-content-end">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control text-end tier-input"
                                       name="vendor_data[<?= $idx ?>][good_amount]"
                                       data-tier="good"
                                       value="<?= (float)($row['good_amount'] ?? 0) ?>"
                                       min="0" step="any" style="max-width:100px;">
                            </div>
                        </td>
                        <td class="text-end">
                            <div class="input-group input-group-sm justify-content-end">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control text-end tier-input"
                                       name="vendor_data[<?= $idx ?>][better_amount]"
                                       data-tier="better"
                                       value="<?= (float)($row['better_amount'] ?? 0) ?>"
                                       min="0" step="any" style="max-width:100px;">
                            </div>
                        </td>
                        <td class="text-end">
                            <div class="input-group input-group-sm justify-content-end">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control text-end tier-input"
                                       name="vendor_data[<?= $idx ?>][best_amount]"
                                       data-tier="best"
                                       value="<?= (float)($row['best_amount'] ?? 0) ?>"
                                       min="0" step="any" style="max-width:100px;">
                            </div>
                        </td>
                        <td class="text-center">
                            <?php if (!$isSubrow): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary me-1"
                                    title="Add sub-row for this vendor" onclick="addSubRow(this)">
                                <i class="bi bi-diagram-2"></i>
                            </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                    title="Remove row" onclick="removeRow(this)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-dark fw-bold">
                    <tr>
                        <td colspan="3">Grand Total</td>
                        <td class="text-end" id="totalGood">$0</td>
                        <td class="text-end" id="totalBetter">$0</td>
                        <td class="text-end" id="totalBest">$0</td>
                        <td></td>
                    </tr>
                    <tr class="small">
                        <td colspan="3" class="text-muted">Target</td>
                        <td class="text-end text-muted" id="targetGoodCell">$<?= number_format((float)$formData['budget_good']) ?></td>
                        <td class="text-end text-muted" id="targetBetterCell">$<?= number_format((float)$formData['budget_better']) ?></td>
                        <td class="text-end text-muted" id="targetBestCell">$<?= number_format((float)$formData['budget_best']) ?></td>
                        <td></td>
                    </tr>
                    <tr class="small">
                        <td colspan="3" class="text-muted">Difference</td>
                        <td class="text-end" id="diffGood">$0</td>
                        <td class="text-end" id="diffBetter">$0</td>
                        <td class="text-end" id="diffBest">$0</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
            <button type="button" class="btn btn-outline-primary" onclick="addVendorRow()">
                <i class="bi bi-plus-circle me-1"></i>Add Vendor Row
            </button>
            <div class="d-flex gap-2">
                <a href="/budget-planner/view.php?id=<?= $id ?>" class="btn btn-outline-secondary">
                    Cancel
                </a>
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="bi bi-floppy me-2"></i>Save Changes
                </button>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
function fmt(n) {
    return '$' + Math.round(n).toLocaleString('en-US');
}

function recalc() {
    // Read budget targets live from the editable inputs
    var targetGood   = parseFloat(document.getElementById('budgetGood').value)   || 0;
    var targetBetter = parseFloat(document.getElementById('budgetBetter').value) || 0;
    var targetBest   = parseFloat(document.getElementById('budgetBest').value)   || 0;

    // Update tfoot target row to match
    document.getElementById('targetGoodCell').textContent   = fmt(targetGood);
    document.getElementById('targetBetterCell').textContent = fmt(targetBetter);
    document.getElementById('targetBestCell').textContent   = fmt(targetBest);

    var good = 0, better = 0, best = 0;
    document.querySelectorAll('.tier-input').forEach(function (inp) {
        var v = parseFloat(inp.value) || 0;
        if (inp.dataset.tier === 'good')   good   += v;
        if (inp.dataset.tier === 'better') better += v;
        if (inp.dataset.tier === 'best')   best   += v;
    });
    document.getElementById('totalGood').textContent   = fmt(good);
    document.getElementById('totalBetter').textContent = fmt(better);
    document.getElementById('totalBest').textContent   = fmt(best);

    var dg  = good   - targetGood;
    var db  = better - targetBetter;
    var dbs = best   - targetBest;

    var dGood   = document.getElementById('diffGood');
    var dBetter = document.getElementById('diffBetter');
    var dBest   = document.getElementById('diffBest');

    dGood.textContent   = (dg  >= 0 ? '+' : '') + fmt(dg);
    dBetter.textContent = (db  >= 0 ? '+' : '') + fmt(db);
    dBest.textContent   = (dbs >= 0 ? '+' : '') + fmt(dbs);

    dGood.className   = 'text-end ' + (dg  === 0 ? 'text-success' : 'text-danger');
    dBetter.className = 'text-end ' + (db  === 0 ? 'text-success' : 'text-danger');
    dBest.className   = 'text-end ' + (dbs === 0 ? 'text-success' : 'text-danger');
}

function reindex() {
    document.querySelectorAll('#vendorRows tr.vendor-row').forEach(function (tr, i) {
        tr.querySelectorAll('input[name]').forEach(function (inp) {
            inp.name = inp.name.replace(/vendor_data\[\d+\]/, 'vendor_data[' + i + ']');
        });
    });
    document.querySelectorAll('.tier-input:not([data-wired])').forEach(function (inp) {
        inp.addEventListener('input', recalc);
        inp.dataset.wired = '1';
    });
    recalc();
}

function removeRow(btn) {
    var tr = btn.closest('tr.vendor-row');
    tr.style.transition = 'opacity .15s, background .15s';
    tr.style.opacity    = '0';
    tr.style.background = '#fee2e2';
    setTimeout(function () { tr.remove(); reindex(); }, 180);
}

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function buildRowHtml(idx, opts) {
    var isSubrow   = opts.isSubrow || false;
    var vendorId   = parseInt(opts.vendorId || 0);
    var vendorName = opts.vendorName || '';
    var category   = opts.category  || '';

    var handleHtml = isSubrow
        ? '<i class="bi bi-arrow-return-right fs-5 text-primary opacity-50"></i>'
        : '<i class="bi bi-grip-vertical fs-5"></i>';
    var rowStyle  = isSubrow ? ' style="background:#f0f4ff;"' : '';
    var tdStyle   = isSubrow ? ' style="padding-left:2rem; border-left:3px solid #0d6efd;"' : '';

    var subRowBtn = isSubrow ? '' :
        '<button type="button" class="btn btn-sm btn-outline-primary me-1" title="Add sub-row" onclick="addSubRow(this)">' +
        '<i class="bi bi-diagram-2"></i></button>';

    return '<tr class="vendor-row' + (isSubrow ? ' subrow' : '') + '"' + rowStyle + '>' +
        '<td class="drag-handle text-center text-muted" style="cursor:grab;" title="' + (isSubrow ? 'Sub-row' : 'Drag to reorder') + '">' +
            handleHtml +
        '</td>' +
        '<td><input type="text" class="form-control form-control-sm" ' +
            'name="vendor_data[' + idx + '][category]" ' +
            'value="' + escHtml(category) + '" placeholder="Category"></td>' +
        '<td' + tdStyle + '>' +
            '<input type="text" class="form-control form-control-sm fw-semibold" ' +
                'name="vendor_data[' + idx + '][vendor_name]" ' +
                'value="' + escHtml(vendorName) + '" placeholder="Vendor name">' +
            '<input type="hidden" name="vendor_data[' + idx + '][vendor_id]" value="' + vendorId + '">' +
            '<input type="hidden" name="vendor_data[' + idx + '][good_rationale]" value="">' +
            '<input type="hidden" name="vendor_data[' + idx + '][better_rationale]" value="">' +
            '<input type="hidden" name="vendor_data[' + idx + '][best_rationale]" value="">' +
            '<input type="hidden" name="vendor_data[' + idx + '][is_subrow]" value="' + (isSubrow ? 1 : 0) + '">' +
        '</td>' +
        '<td class="text-end"><div class="input-group input-group-sm justify-content-end">' +
            '<span class="input-group-text">$</span>' +
            '<input type="number" class="form-control text-end tier-input" name="vendor_data[' + idx + '][good_amount]" data-tier="good" value="0" min="0" step="any" style="max-width:100px;">' +
        '</div></td>' +
        '<td class="text-end"><div class="input-group input-group-sm justify-content-end">' +
            '<span class="input-group-text">$</span>' +
            '<input type="number" class="form-control text-end tier-input" name="vendor_data[' + idx + '][better_amount]" data-tier="better" value="0" min="0" step="any" style="max-width:100px;">' +
        '</div></td>' +
        '<td class="text-end"><div class="input-group input-group-sm justify-content-end">' +
            '<span class="input-group-text">$</span>' +
            '<input type="number" class="form-control text-end tier-input" name="vendor_data[' + idx + '][best_amount]" data-tier="best" value="0" min="0" step="any" style="max-width:100px;">' +
        '</div></td>' +
        '<td class="text-center">' +
            subRowBtn +
            '<button type="button" class="btn btn-sm btn-outline-danger" title="Remove row" onclick="removeRow(this)">' +
            '<i class="bi bi-trash"></i></button>' +
        '</td>' +
    '</tr>';
}

function addVendorRow() {
    var tbody = document.getElementById('vendorRows');
    var idx   = tbody.querySelectorAll('tr.vendor-row').length;
    var tmp   = document.createElement('tbody');
    tmp.innerHTML = buildRowHtml(idx, { isSubrow: false });
    var newTr = tmp.firstElementChild;
    tbody.appendChild(newTr);
    reindex();
    newTr.querySelector('input[name*="[category]"]').focus();
}

function addSubRow(btn) {
    var parentTr   = btn.closest('tr.vendor-row');
    var vendorId   = (parentTr.querySelector('input[name*="[vendor_id]"]')   || {}).value || '0';
    var vendorName = (parentTr.querySelector('input[name*="[vendor_name]"]') || {}).value || '';

    var idx = document.querySelectorAll('#vendorRows tr.vendor-row').length;
    var tmp = document.createElement('tbody');
    tmp.innerHTML = buildRowHtml(idx, {
        isSubrow:   true,
        vendorId:   vendorId,
        vendorName: vendorName,
        category:   ''
    });
    var newTr = tmp.firstElementChild;

    var insertBefore = parentTr.nextElementSibling;
    while (insertBefore && insertBefore.classList.contains('subrow')) {
        insertBefore = insertBefore.nextElementSibling;
    }
    parentTr.parentNode.insertBefore(newTr, insertBefore);
    reindex();
    newTr.querySelector('input[name*="[category]"]').focus();
}

// Sortable
var tbody = document.getElementById('vendorRows');
if (tbody) {
    Sortable.create(tbody, {
        handle:     '.drag-handle',
        animation:  150,
        ghostClass: 'table-primary',
        onEnd:      reindex
    });
}

// Wire existing tier inputs
document.querySelectorAll('.tier-input').forEach(function (inp) {
    inp.addEventListener('input', recalc);
    inp.dataset.wired = '1';
});

// Wire budget target inputs — changing them instantly updates the difference row
document.querySelectorAll('.budget-target-input').forEach(function (inp) {
    inp.addEventListener('input', recalc);
});

recalc();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
