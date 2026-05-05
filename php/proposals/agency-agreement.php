<?php
/**
 * proposals/agency-agreement.php
 * Fast-fill Agency Agreement creator/editor.
 * Budget categories are fully dynamic — serialized to/from budget_json.
 */
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';
requireRole(['admin', 'buyer']);

$svc = new AgencyAgreementService();

$id     = (int)($_GET['id'] ?? 0);
$isEdit = false;
$ag     = [
    'id' => 0, 'title' => '', 'status' => 'draft',
    'client_id' => 0, 'client_name' => '', 'client_address' => '',
    'client_city_state_zip' => '', 'client_phone' => '',
    'client_representative' => '', 'client_title' => '',
    'contract_start' => '', 'contract_end' => '',
    'services'           => AgencyAgreementService::DEFAULT_SERVICES,
    'budget_categories'  => AgencyAgreementService::DEFAULT_BUDGET_CATEGORIES,
    'deposit_amount' => '', 'deposit_due_description' => '',
    'balance_amount' => '', 'balance_due_description' => '',
    'total_amount'   => '',
    'contingency_monthly' => '', 'mileage_rate' => '0.60',
    'hourly_rate' => '80.00', 'additional_notes' => '',
];

if ($id > 0) {
    $loaded = $svc->get($id);
    if (!$loaded) { flash('error','Agreement not found.'); redirect('/proposals/index.php'); }
    $ag     = $loaded;
    $isEdit = true;
}

$clients = $svc->getClients();
$errors  = [];

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Parse services: array of non-empty strings
    $services = array_values(array_filter(
        array_map('trim', (array)($_POST['services'] ?? []))
    ));

    // Parse dynamic budget categories from the JSON hidden field
    $budgetCats = json_decode(trim($_POST['budget_json'] ?? '[]'), true);
    if (!is_array($budgetCats)) $budgetCats = [];

    $postData = [
        'id'                     => $id,
        'title'                  => trim($_POST['title']                   ?? ''),
        'status'                 => $_POST['status']                        ?? 'draft',
        'client_id'              => (int)($_POST['client_id']              ?? 0),
        'client_name'            => trim($_POST['client_name']             ?? ''),
        'client_address'         => trim($_POST['client_address']          ?? ''),
        'client_city_state_zip'  => trim($_POST['client_city_state_zip']   ?? ''),
        'client_phone'           => trim($_POST['client_phone']            ?? ''),
        'client_representative'  => trim($_POST['client_representative']   ?? ''),
        'client_title'           => trim($_POST['client_title']            ?? ''),
        'contract_start'         => trim($_POST['contract_start']          ?? ''),
        'contract_end'           => trim($_POST['contract_end']            ?? ''),
        'services'               => $services,
        'budget_categories'      => $budgetCats,
        'deposit_amount'         => trim($_POST['deposit_amount']          ?? ''),
        'deposit_due_description'=> trim($_POST['deposit_due_description'] ?? ''),
        'balance_amount'         => trim($_POST['balance_amount']          ?? ''),
        'balance_due_description'=> trim($_POST['balance_due_description'] ?? ''),
        'total_amount'           => trim($_POST['total_amount']            ?? ''),
        'contingency_monthly'    => trim($_POST['contingency_monthly']     ?? ''),
        'mileage_rate'           => trim($_POST['mileage_rate']            ?? '0.60'),
        'hourly_rate'            => trim($_POST['hourly_rate']             ?? '80.00'),
        'additional_notes'       => trim($_POST['additional_notes']        ?? ''),
    ];

    if ($postData['title'] === '')        $errors[] = 'Document title is required.';
    if ($postData['client_name'] === '')  $errors[] = 'Client name is required.';
    if (empty($postData['services']))     $errors[] = 'At least one marketing service is required.';

    if (empty($errors)) {
        try {
            $savedId = $svc->save($postData);
            flash('success', $isEdit ? 'Agreement updated.' : 'Agreement created.');
            redirect('/proposals/agency-agreement-view.php?id=' . $savedId);
        } catch (Exception $e) {
            $errors[] = 'Save failed: ' . $e->getMessage();
        }
    }

    // Repopulate on error
    $ag     = $postData + ['id' => $id];
    $isEdit = $id > 0;
}

$pageTitle = ($isEdit ? 'Edit' : 'New') . ' Agency Agreement';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.budget-row { display: flex; gap: .5rem; align-items: center; margin-bottom: .4rem; }
.budget-row input[type=text]   { flex: 1; }
.budget-row input[type=number] { width: 110px; }
.section-divider { border-top: 2px solid #dee2e6; margin: 1.75rem 0 1.25rem; }
.form-section-title {
    font-size: .7rem; font-weight: 700; letter-spacing: .08em;
    text-transform: uppercase; color: #6c757d; margin-bottom: .75rem;
}
.budget-category {
    border: 1px solid #dee2e6;
    border-radius: .5rem;
    padding: .75rem;
    margin-bottom: .75rem;
    background: #fafbff;
}
.budget-category .cat-header {
    display: flex; gap: .5rem; align-items: center; margin-bottom: .5rem;
}
.budget-category .cat-label-input {
    font-weight: 600;
    font-size: .9rem;
    border: 1px solid #ced4da;
    border-radius: .375rem;
    padding: .25rem .5rem;
    flex: 1;
}
</style>

<div class="mb-4">
    <h1 class="h3 fw-bold mb-0">
        <i class="bi bi-file-earmark-text me-2 text-primary"></i>
        <?= $isEdit ? 'Edit Agency Agreement' : 'New Agency Agreement' ?>
    </h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="/proposals/index.php">Proposals</a></li>
            <li class="breadcrumb-item active"><?= $isEdit ? 'Edit Agreement' : 'New Agreement' ?></li>
        </ol>
    </nav>
</div>

<?php foreach ($errors as $e): ?>
<div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle me-1"></i><?= h($e) ?></div>
<?php endforeach; ?>

<form method="POST" id="ag-form" autocomplete="off">
<input type="hidden" name="id"          value="<?= (int)$ag['id'] ?>">
<input type="hidden" name="status"      id="status_hidden" value="<?= h($ag['status'] ?? 'draft') ?>">
<input type="hidden" name="budget_json" id="budget_json_input" value="">

<div class="row g-4">

    <!-- ── LEFT COLUMN ──────────────────────────────────────────────── -->
    <div class="col-lg-8">

        <!-- Document Title -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="form-section-title">Document</div>
                <div class="row g-2">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control"
                               value="<?= h($ag['title']) ?>"
                               placeholder="e.g. Aces Wild Rodeo 2025 — Agency Agreement">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Status</label>
                        <select class="form-select" id="status_select">
                            <?php foreach (AgencyAgreementService::STATUS_LABELS as $val => $lbl): ?>
                            <option value="<?= h($val) ?>" <?= ($ag['status'] ?? 'draft') === $val ? 'selected' : '' ?>><?= h($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Client Info -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="form-section-title">Client Information</div>

                <?php if (!empty($clients)): ?>
                <div class="mb-3">
                    <label class="form-label small text-muted">Quick-fill from existing client</label>
                    <select id="client-picker" class="form-select form-select-sm">
                        <option value="">— select to pre-fill fields —</option>
                        <?php foreach ($clients as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"
                                data-name="<?= h($c['company_name']) ?>">
                            <?= h($c['company_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <input type="hidden" name="client_id" id="client_id_hidden" value="<?= (int)($ag['client_id'] ?? 0) ?>">

                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Client / Company Name <span class="text-danger">*</span></label>
                        <input type="text" name="client_name" id="field_client_name" class="form-control"
                               value="<?= h($ag['client_name']) ?>" placeholder="Aces Wild Rodeo">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Representative Name</label>
                        <input type="text" name="client_representative" class="form-control"
                               value="<?= h($ag['client_representative']) ?>" placeholder="Jane Smith">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Title</label>
                        <input type="text" name="client_title" class="form-control"
                               value="<?= h($ag['client_title']) ?>" placeholder="CEO">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Address</label>
                        <input type="text" name="client_address" class="form-control"
                               value="<?= h($ag['client_address']) ?>" placeholder="123 Main St">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Phone</label>
                        <input type="text" name="client_phone" class="form-control"
                               value="<?= h($ag['client_phone']) ?>" placeholder="509-555-0100">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">City, State, Zip</label>
                        <input type="text" name="client_city_state_zip" class="form-control"
                               value="<?= h($ag['client_city_state_zip']) ?>" placeholder="Yakima, WA 98901">
                    </div>
                </div>
            </div>
        </div>

        <!-- Contract Dates -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="form-section-title">Contract Dates</div>
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Start Date</label>
                        <input type="date" name="contract_start" class="form-control"
                               value="<?= h($ag['contract_start'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">End Date</label>
                        <input type="date" name="contract_end" class="form-control"
                               value="<?= h($ag['contract_end'] ?? '') ?>">
                        <div class="form-text">Leave blank to use "12 months after signing"</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Marketing Services -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="form-section-title mb-0">Marketing Services</div>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addService()">
                        <i class="bi bi-plus me-1"></i>Add Service
                    </button>
                </div>
                <div id="services-list">
                <?php
                $services = $ag['services'] ?? AgencyAgreementService::DEFAULT_SERVICES;
                foreach ($services as $i => $svc_item): ?>
                <div class="d-flex gap-2 align-items-center mb-2 service-row">
                    <span class="text-muted small" style="width:18px;"><?= $i + 1 ?>.</span>
                    <input type="text" name="services[]" class="form-control"
                           value="<?= h($svc_item) ?>" placeholder="Service description">
                    <button type="button" class="btn btn-sm btn-outline-danger flex-shrink-0"
                            onclick="this.closest('.service-row').remove(); reindexServices()">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Pricing -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="form-section-title">Pricing</div>
                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Deposit Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="deposit_amount" id="deposit_amount"
                                   class="form-control" step="0.01" min="0"
                                   value="<?= h($ag['deposit_amount']) ?>"
                                   oninput="calcTotal()">
                        </div>
                    </div>
                    <div class="col-md-9">
                        <label class="form-label fw-semibold">Deposit Due</label>
                        <input type="text" name="deposit_due_description" class="form-control"
                               value="<?= h($ag['deposit_due_description']) ?>"
                               placeholder="e.g. due on January 4, 2025">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Balance Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="balance_amount" id="balance_amount"
                                   class="form-control" step="0.01" min="0"
                                   value="<?= h($ag['balance_amount']) ?>"
                                   oninput="calcTotal()">
                        </div>
                    </div>
                    <div class="col-md-9">
                        <label class="form-label fw-semibold">Balance Due</label>
                        <input type="text" name="balance_due_description" class="form-control"
                               value="<?= h($ag['balance_due_description']) ?>"
                               placeholder="e.g. upon completion of event, no later than February 21, 2025">
                    </div>
                </div>
                <div class="row g-2 align-items-center">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Total</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="total_amount" id="total_amount"
                                   class="form-control fw-bold" step="0.01" min="0"
                                   value="<?= h($ag['total_amount']) ?>">
                        </div>
                    </div>
                    <div class="col-md-9 pt-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                onclick="calcTotal()">
                            <i class="bi bi-calculator me-1"></i>Auto-sum deposit + balance
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Budget Breakdown — dynamic categories -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-section-title mb-0">Budget Breakdown</div>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="fw-bold text-success small">
                            Total: $<span id="budget-total">0.00</span>
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="addBudgetCategory()">
                            <i class="bi bi-plus-circle me-1"></i>Add Category
                        </button>
                    </div>
                </div>

                <div id="budget-categories-container">
                <?php
                $budgetCats = $ag['budget_categories'] ?? AgencyAgreementService::DEFAULT_BUDGET_CATEGORIES;
                foreach ($budgetCats as $catIdx => $cat):
                    $catLabel = $cat['label'] ?? '';
                    $catRows  = $cat['rows']  ?? [['name'=>'','amount'=>0]];
                    if (empty($catRows)) $catRows = [['name'=>'','amount'=>0]];
                ?>
                <div class="budget-category" data-cat-idx="<?= $catIdx ?>">
                    <div class="cat-header">
                        <i class="bi bi-grip-vertical text-muted"></i>
                        <input type="text" class="cat-label-input"
                               value="<?= h($catLabel) ?>"
                               placeholder="Category name (e.g. TV, Radio)">
                        <button type="button" class="btn btn-sm btn-outline-secondary ms-1"
                                onclick="addBudgetRow(this)">
                            <i class="bi bi-plus"></i> Add Row
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger ms-1"
                                onclick="removeBudgetCategory(this)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                    <div class="cat-rows">
                    <?php foreach ($catRows as $row): ?>
                    <div class="budget-row">
                        <input type="text" class="form-control form-control-sm row-name"
                               value="<?= h($row['name'] ?? '') ?>"
                               placeholder="Station / Platform name">
                        <div class="input-group input-group-sm" style="width:140px;">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control row-amount"
                                   value="<?= h($row['amount'] ?? 0) ?>"
                                   step="0.01" min="0" placeholder="0.00"
                                   oninput="calcBudgetTotal()">
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                onclick="this.closest('.budget-row').remove(); calcBudgetTotal()">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                </div><!-- /budget-categories-container -->

            </div>
        </div>

        <!-- Additional Terms -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="form-section-title">Additional Terms</div>
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Monthly Contingency Reserve</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="contingency_monthly" class="form-control"
                                   step="0.01" min="0"
                                   value="<?= h($ag['contingency_monthly'] ?? '') ?>"
                                   placeholder="200.00">
                        </div>
                        <div class="form-text">Leave blank to omit from document.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Mileage Rate ($/mi)</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="mileage_rate" class="form-control"
                                   step="0.01" min="0"
                                   value="<?= h($ag['mileage_rate'] ?? '0.60') ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Hourly Rate ($/hr)</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="hourly_rate" class="form-control"
                                   step="0.01" min="0"
                                   value="<?= h($ag['hourly_rate'] ?? '80.00') ?>">
                        </div>
                    </div>
                </div>
                <div>
                    <label class="form-label fw-semibold">Additional Notes</label>
                    <textarea name="additional_notes" class="form-control" rows="3"
                              placeholder="Any additional notes to appear in the document…"><?= h($ag['additional_notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

    </div><!-- /col-lg-8 -->

    <!-- ── RIGHT COLUMN ─────────────────────────────────────────────── -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3 sticky-top" style="top:80px;">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-check2-square me-2 text-success"></i>Save & Preview
            </div>
            <div class="card-body d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i><?= $isEdit ? 'Update Agreement' : 'Create Agreement' ?>
                </button>
                <?php if ($isEdit): ?>
                <a href="/proposals/agency-agreement-view.php?id=<?= (int)$id ?>"
                   target="_blank" class="btn btn-outline-success">
                    <i class="bi bi-eye me-1"></i>View / Print
                </a>
                <?php endif; ?>
                <a href="/proposals/index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
            <div class="card-body border-top pt-3">
                <div class="form-section-title">Document Summary</div>
                <dl class="small mb-0 row">
                    <dt class="col-5 text-muted">Client</dt>
                    <dd class="col-7" id="summary-client">—</dd>
                    <dt class="col-5 text-muted">Services</dt>
                    <dd class="col-7" id="summary-services">—</dd>
                    <dt class="col-5 text-muted">Total</dt>
                    <dd class="col-7 fw-bold text-success" id="summary-total">—</dd>
                    <dt class="col-5 text-muted">Budget</dt>
                    <dd class="col-7" id="summary-budget">—</dd>
                    <dt class="col-5 text-muted">Categories</dt>
                    <dd class="col-7" id="summary-categories">—</dd>
                </dl>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold small">
                <i class="bi bi-info-circle me-1 text-info"></i>What's included
            </div>
            <div class="card-body small text-muted">
                <ul class="ps-3 mb-0">
                    <li class="mb-1">Agency Agreement cover page with your filled details</li>
                    <li class="mb-1">Standard Terms (Contract Duration, Payment, Scope, Cancellation Policy)</li>
                    <li class="mb-1">Memorandum of Understanding with full legal terms</li>
                    <li class="mb-1">Dual signature blocks (Agency + Client)</li>
                    <li class="mb-1">Print-ready layout — use browser Print → Save as PDF</li>
                </ul>
            </div>
        </div>
    </div>

</div><!-- /row -->
</form>

<script>
// ── Service list ───────────────────────────────────────────────────────────
function addService() {
    const list = document.getElementById('services-list');
    const idx  = list.querySelectorAll('.service-row').length + 1;
    const div  = document.createElement('div');
    div.className = 'd-flex gap-2 align-items-center mb-2 service-row';
    div.innerHTML = `
        <span class="text-muted small" style="width:18px;">${idx}.</span>
        <input type="text" name="services[]" class="form-control" placeholder="Service description">
        <button type="button" class="btn btn-sm btn-outline-danger flex-shrink-0"
                onclick="this.closest('.service-row').remove(); reindexServices()">
            <i class="bi bi-trash"></i>
        </button>`;
    list.appendChild(div);
    div.querySelector('input').focus();
}

function reindexServices() {
    document.querySelectorAll('.service-row').forEach((row, i) => {
        const num = row.querySelector('span');
        if (num) num.textContent = (i + 1) + '.';
    });
    updateSummary();
}

// ── Dynamic budget categories ───────────────────────────────────────────────
function makeBudgetRowHTML() {
    return `<div class="budget-row">
        <input type="text" class="form-control form-control-sm row-name" placeholder="Station / Platform name">
        <div class="input-group input-group-sm" style="width:140px;">
            <span class="input-group-text">$</span>
            <input type="number" class="form-control row-amount" step="0.01" min="0" placeholder="0.00" oninput="calcBudgetTotal()">
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger"
                onclick="this.closest('.budget-row').remove(); calcBudgetTotal()">
            <i class="bi bi-x"></i>
        </button>
    </div>`;
}

function addBudgetRow(btn) {
    const cat = btn.closest('.budget-category');
    const rowsDiv = cat.querySelector('.cat-rows');
    const div = document.createElement('div');
    div.innerHTML = makeBudgetRowHTML();
    rowsDiv.appendChild(div.firstElementChild);
    rowsDiv.querySelector('.row-name:last-of-type')?.focus();
    calcBudgetTotal();
}

function addBudgetCategory() {
    const container = document.getElementById('budget-categories-container');
    const div = document.createElement('div');
    div.className = 'budget-category';
    div.innerHTML = `
        <div class="cat-header">
            <i class="bi bi-grip-vertical text-muted"></i>
            <input type="text" class="cat-label-input" placeholder="Category name (e.g. TV, Radio)">
            <button type="button" class="btn btn-sm btn-outline-secondary ms-1" onclick="addBudgetRow(this)">
                <i class="bi bi-plus"></i> Add Row
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger ms-1" onclick="removeBudgetCategory(this)">
                <i class="bi bi-trash"></i>
            </button>
        </div>
        <div class="cat-rows">${makeBudgetRowHTML()}</div>`;
    container.appendChild(div);
    div.querySelector('.cat-label-input').focus();
    updateSummary();
}

function removeBudgetCategory(btn) {
    const cats = document.querySelectorAll('.budget-category');
    if (cats.length <= 1) {
        alert('You need at least one budget category.');
        return;
    }
    btn.closest('.budget-category').remove();
    calcBudgetTotal();
}

// ── Serialize budget to JSON hidden field ───────────────────────────────────
function serializeBudgetToJSON() {
    const cats = [];
    document.querySelectorAll('.budget-category').forEach(catEl => {
        const label = catEl.querySelector('.cat-label-input')?.value.trim() || '';
        const rows  = [];
        catEl.querySelectorAll('.budget-row').forEach(rowEl => {
            const name = rowEl.querySelector('.row-name')?.value.trim() || '';
            const amt  = parseFloat(rowEl.querySelector('.row-amount')?.value) || 0;
            rows.push({ name, amount: amt });
        });
        if (label !== '') {
            cats.push({ label, rows });
        }
    });
    document.getElementById('budget_json_input').value = JSON.stringify(cats);
}

// ── Totals ──────────────────────────────────────────────────────────────────
function calcBudgetTotal() {
    let t = 0;
    document.querySelectorAll('.row-amount').forEach(el => {
        t += parseFloat(el.value) || 0;
    });
    document.getElementById('budget-total').textContent = t.toFixed(2);
    document.getElementById('summary-budget').textContent =
        '$' + t.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    const cats = document.querySelectorAll('.budget-category').length;
    document.getElementById('summary-categories').textContent =
        cats + ' categor' + (cats === 1 ? 'y' : 'ies');
}

function calcTotal() {
    const dep = parseFloat(document.getElementById('deposit_amount').value)  || 0;
    const bal = parseFloat(document.getElementById('balance_amount').value) || 0;
    document.getElementById('total_amount').value = (dep + bal).toFixed(2);
    updateSummary();
}

function updateSummary() {
    const client = document.querySelector('[name=client_name]')?.value || '—';
    document.getElementById('summary-client').textContent = client || '—';

    const svcs = [...document.querySelectorAll('[name="services[]"]')]
        .map(i => i.value.trim()).filter(Boolean);
    document.getElementById('summary-services').textContent =
        svcs.length ? svcs.length + ' service' + (svcs.length === 1 ? '' : 's') : '—';

    const total = document.getElementById('total_amount')?.value;
    document.getElementById('summary-total').textContent =
        total ? '$' + parseFloat(total).toLocaleString('en-US', {minimumFractionDigits: 2}) : '—';

    calcBudgetTotal();
}

// ── Client quick-fill ───────────────────────────────────────────────────────
document.getElementById('client-picker')?.addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    if (!opt.value) return;
    document.querySelector('[name=client_name]').value = opt.dataset.name || '';
    document.getElementById('client_id_hidden').value  = opt.value;
    updateSummary();
});

// ── Form submit — serialize budget + sync status ────────────────────────────
document.getElementById('ag-form').addEventListener('submit', function() {
    serializeBudgetToJSON();
    document.getElementById('status_hidden').value =
        document.getElementById('status_select').value;
});

// ── Init ────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    updateSummary();
    calcBudgetTotal();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
