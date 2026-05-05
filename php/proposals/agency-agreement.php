<?php
/**
 * proposals/agency-agreement.php
 * Fast-fill Agency Agreement creator/editor.
 * Payment schedule and budget categories are fully dynamic (JSON).
 */
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';
requireRole(['admin', 'buyer']);

$svc      = new AgencyAgreementService();
$branding = $svc->getBranding();

$id     = (int)($_GET['id'] ?? 0);
$isEdit = false;
$ag     = [
    'id' => 0, 'title' => '', 'status' => 'draft',
    'client_id' => 0, 'client_name' => '', 'client_address' => '',
    'client_city_state_zip' => '', 'client_phone' => '',
    'client_representative' => '', 'client_title' => '',
    'contract_start' => '', 'contract_end' => '',
    'services'          => AgencyAgreementService::DEFAULT_SERVICES,
    'payment_schedule'  => [
        ['label' => 'Deposit',  'amount' => '', 'due' => ''],
        ['label' => 'Balance',  'amount' => '', 'due' => ''],
    ],
    'budget_categories' => AgencyAgreementService::DEFAULT_BUDGET_CATEGORIES,
    'total_amount'      => '',
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

    $services = array_values(array_filter(
        array_map('trim', (array)($_POST['services'] ?? []))
    ));

    // Dynamic payment schedule from hidden JSON field
    $paymentSchedule = json_decode(trim($_POST['payment_schedule_json'] ?? '[]'), true);
    if (!is_array($paymentSchedule)) $paymentSchedule = [];

    // Dynamic budget categories from hidden JSON field
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
        'payment_schedule'       => $paymentSchedule,
        'budget_categories'      => $budgetCats,
        'total_amount'           => trim($_POST['total_amount']            ?? ''),
        'contingency_monthly'    => trim($_POST['contingency_monthly']     ?? ''),
        'mileage_rate'           => trim($_POST['mileage_rate']            ?? '0.60'),
        'hourly_rate'            => trim($_POST['hourly_rate']             ?? '80.00'),
        'additional_notes'       => trim($_POST['additional_notes']        ?? ''),
        // Legacy fields kept for DB compat
        'deposit_amount'         => 0,
        'deposit_due_description'=> '',
        'balance_amount'         => 0,
        'balance_due_description'=> '',
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

    $ag     = $postData + ['id' => $id];
    $isEdit = $id > 0;
}

$pageTitle = ($isEdit ? 'Edit' : 'New') . ' Agency Agreement';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.form-section-title {
    font-size: .7rem; font-weight: 700; letter-spacing: .08em;
    text-transform: uppercase; color: #6c757d; margin-bottom: .75rem;
}
/* Payment schedule rows */
.payment-row {
    display: grid;
    grid-template-columns: 1fr 130px 1fr 36px;
    gap: .4rem;
    align-items: center;
    margin-bottom: .4rem;
}
/* Budget category card */
.budget-category {
    border: 1px solid #dee2e6;
    border-radius: .5rem;
    padding: .65rem .75rem;
    margin-bottom: .6rem;
    background: #fafbff;
}
.budget-category .cat-header {
    display: flex; gap: .5rem; align-items: center; margin-bottom: .4rem;
}
.cat-label-input {
    font-weight: 600; font-size: .9rem;
    border: 1px solid #ced4da; border-radius: .375rem;
    padding: .25rem .5rem; flex: 1;
}
.budget-row {
    display: grid;
    grid-template-columns: 1fr 130px 36px;
    gap: .4rem; align-items: center; margin-bottom: .3rem;
}
/* Logo preview */
#logo-preview-area img { max-height: 60px; }
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
<input type="hidden" name="id"                   value="<?= (int)$ag['id'] ?>">
<input type="hidden" name="status"               id="status_hidden"          value="<?= h($ag['status'] ?? 'draft') ?>">
<input type="hidden" name="payment_schedule_json" id="payment_schedule_json" value="">
<input type="hidden" name="budget_json"           id="budget_json_input"     value="">

<div class="row g-4">

    <!-- ── LEFT COLUMN ──────────────────────────────────────────────── -->
    <div class="col-lg-8">

        <!-- ── Agency Branding / Logo ──────────────────────────────── -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="form-section-title">Agency Logo</div>
                <div class="d-flex align-items-start gap-3 flex-wrap">
                    <!-- Current logo or placeholder -->
                    <div id="logo-preview-area">
                    <?php if (!empty($branding['logo_url'])): ?>
                        <img src="<?= h($branding['logo_url']) ?>" alt="Agency Logo"
                             style="max-height:64px;max-width:200px;object-fit:contain;
                                    border:1px solid #dee2e6;border-radius:4px;padding:4px;">
                    <?php else: ?>
                        <div class="border border-dashed rounded d-flex align-items-center justify-content-center text-muted"
                             style="width:160px;height:64px;font-size:.8rem;">
                            <i class="bi bi-image me-1"></i>No logo set
                        </div>
                    <?php endif; ?>
                    </div>

                    <!-- Upload form (separate from main form — HTMX) -->
                    <div class="flex-grow-1">
                        <label class="form-label small fw-semibold mb-1">Upload new logo</label>
                        <input type="file" id="logo-file-input" accept="image/*" class="form-control form-control-sm"
                               style="max-width:320px;"
                               hx-post="/proposals/actions/upload-logo.php"
                               hx-encoding="multipart/form-data"
                               hx-include="#logo-file-input"
                               hx-target="#logo-preview-area"
                               hx-swap="innerHTML"
                               hx-trigger="change"
                               name="logo_file">
                        <div class="form-text">JPEG, PNG, SVG — max 2 MB. Applies globally to all agreements.</div>
                        <?php if (!empty($branding['logo_url'])): ?>
                        <div class="small text-muted mt-1 font-monospace"><?= h($branding['logo_url']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Document Title & Status ─────────────────────────────── -->
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

        <!-- ── Client Info ─────────────────────────────────────────── -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="form-section-title">Client Information</div>

                <?php if (!empty($clients)): ?>
                <div class="mb-3">
                    <label class="form-label small text-muted">Quick-fill from existing client</label>
                    <select id="client-picker" class="form-select form-select-sm">
                        <option value="">— select to pre-fill fields —</option>
                        <?php foreach ($clients as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" data-name="<?= h($c['company_name']) ?>">
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

        <!-- ── Contract Dates ──────────────────────────────────────── -->
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
                        <div class="form-text">Leave blank for "12 months after signing"</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Marketing Services ──────────────────────────────────── -->
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

        <!-- ── Payment Schedule ────────────────────────────────────── -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="form-section-title mb-0">Payment Schedule</div>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="fw-bold text-success small">
                            Total: $<span id="payment-total">0.00</span>
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addPaymentRow()">
                            <i class="bi bi-plus me-1"></i>Add Payment
                        </button>
                    </div>
                </div>
                <!-- Column headers -->
                <div class="payment-row mb-1" style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6c757d;">
                    <span>Label</span><span>Amount</span><span>Due / Description</span><span></span>
                </div>
                <div id="payment-rows">
                <?php
                $paySchedule = $ag['payment_schedule'] ?? [
                    ['label'=>'Deposit','amount'=>'','due'=>''],
                    ['label'=>'Balance','amount'=>'','due'=>''],
                ];
                foreach ($paySchedule as $pi => $pitem): ?>
                <div class="payment-row">
                    <input type="text" class="form-control form-control-sm pay-label"
                           value="<?= h($pitem['label'] ?? '') ?>" placeholder="e.g. Deposit">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control pay-amount"
                               value="<?= h($pitem['amount'] ?? '') ?>"
                               step="0.01" min="0" placeholder="0.00"
                               oninput="calcPaymentTotal()">
                    </div>
                    <input type="text" class="form-control form-control-sm pay-due"
                           value="<?= h($pitem['due'] ?? '') ?>"
                           placeholder="e.g. due January 4, 2025">
                    <button type="button" class="btn btn-sm btn-outline-danger"
                            onclick="removePaymentRow(this)">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
                <?php endforeach; ?>
                </div>
                <div class="mt-2 small text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    Total is calculated automatically from the sum of all payment amounts.
                </div>
            </div>
        </div>

        <!-- ── Budget Breakdown — dynamic categories ────────────────── -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-section-title mb-0">Budget Breakdown</div>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="fw-bold text-success small">
                            Budget: $<span id="budget-total">0.00</span>
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="addBudgetCategory()">
                            <i class="bi bi-plus-circle me-1"></i>Add Category
                        </button>
                    </div>
                </div>

                <div id="budget-categories-container">
                <?php
                $budgetCats = $ag['budget_categories'] ?? AgencyAgreementService::DEFAULT_BUDGET_CATEGORIES;
                foreach ($budgetCats as $cat):
                    $catRows = $cat['rows'] ?? [['name'=>'','amount'=>0]];
                    if (empty($catRows)) $catRows = [['name'=>'','amount'=>0]];
                ?>
                <div class="budget-category">
                    <div class="cat-header">
                        <i class="bi bi-grip-vertical text-muted"></i>
                        <input type="text" class="cat-label-input"
                               value="<?= h($cat['label'] ?? '') ?>"
                               placeholder="Category name (e.g. TV, Radio)">
                        <button type="button" class="btn btn-sm btn-outline-secondary ms-1"
                                onclick="addBudgetRow(this)">
                            <i class="bi bi-plus"></i> Row
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
                               value="<?= h($row['name'] ?? '') ?>" placeholder="Station / Platform">
                        <div class="input-group input-group-sm">
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
                </div>
            </div>
        </div>

        <!-- ── Additional Terms ────────────────────────────────────── -->
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
                <a href="/proposals/agency-agreement-view.php?id=<?= (int)$id ?>"
                   target="_blank" class="btn btn-outline-danger">
                    <i class="bi bi-file-earmark-pdf me-1"></i>Print / Save PDF
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
                    <dt class="col-5 text-muted">Payments</dt>
                    <dd class="col-7" id="summary-payments">—</dd>
                    <dt class="col-5 text-muted">Total</dt>
                    <dd class="col-7 fw-bold text-success" id="summary-total">—</dd>
                    <dt class="col-5 text-muted">Budget</dt>
                    <dd class="col-7" id="summary-budget">—</dd>
                </dl>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold small">
                <i class="bi bi-info-circle me-1 text-info"></i>What's included
            </div>
            <div class="card-body small text-muted">
                <ul class="ps-3 mb-0">
                    <li class="mb-1">Branded letterhead with logo on all 3 pages</li>
                    <li class="mb-1">Agency Agreement cover with services &amp; payment schedule</li>
                    <li class="mb-1">Standard Terms and signature blocks</li>
                    <li class="mb-1">Memorandum of Understanding</li>
                    <li class="mb-1">Print or download as PDF</li>
                </ul>
            </div>
        </div>
    </div>

</div><!-- /row -->
</form>

<script>
// ── Services ───────────────────────────────────────────────────────────────
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

// ── Payment Schedule ────────────────────────────────────────────────────────
function addPaymentRow() {
    const container = document.getElementById('payment-rows');
    const div = document.createElement('div');
    div.className = 'payment-row';
    div.innerHTML = `
        <input type="text" class="form-control form-control-sm pay-label" placeholder="e.g. Second Payment">
        <div class="input-group input-group-sm">
            <span class="input-group-text">$</span>
            <input type="number" class="form-control pay-amount" step="0.01" min="0" placeholder="0.00" oninput="calcPaymentTotal()">
        </div>
        <input type="text" class="form-control form-control-sm pay-due" placeholder="e.g. due February 1, 2025">
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removePaymentRow(this)">
            <i class="bi bi-x"></i>
        </button>`;
    container.appendChild(div);
    div.querySelector('.pay-label').focus();
}
function removePaymentRow(btn) {
    const rows = document.querySelectorAll('#payment-rows .payment-row');
    if (rows.length <= 1) { alert('At least one payment row is required.'); return; }
    btn.closest('.payment-row').remove();
    calcPaymentTotal();
}
function calcPaymentTotal() {
    let total = 0;
    document.querySelectorAll('.pay-amount').forEach(el => total += parseFloat(el.value) || 0);
    document.getElementById('payment-total').textContent = total.toFixed(2);
    document.getElementById('summary-total').textContent =
        '$' + total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('summary-payments').textContent =
        document.querySelectorAll('#payment-rows .payment-row').length + ' item(s)';
}
function serializePaymentSchedule() {
    const items = [];
    document.querySelectorAll('#payment-rows .payment-row').forEach(row => {
        const label  = row.querySelector('.pay-label')?.value.trim()  || '';
        const amount = parseFloat(row.querySelector('.pay-amount')?.value) || 0;
        const due    = row.querySelector('.pay-due')?.value.trim()    || '';
        items.push({ label, amount, due });
    });
    document.getElementById('payment_schedule_json').value = JSON.stringify(items);
}

// ── Budget categories ───────────────────────────────────────────────────────
function makeBudgetRowHTML() {
    return `<div class="budget-row">
        <input type="text" class="form-control form-control-sm row-name" placeholder="Station / Platform">
        <div class="input-group input-group-sm">
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
    const rowsDiv = btn.closest('.budget-category').querySelector('.cat-rows');
    const div = document.createElement('div');
    div.innerHTML = makeBudgetRowHTML();
    rowsDiv.appendChild(div.firstElementChild);
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
                <i class="bi bi-plus"></i> Row
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger ms-1" onclick="removeBudgetCategory(this)">
                <i class="bi bi-trash"></i>
            </button>
        </div>
        <div class="cat-rows">${makeBudgetRowHTML()}</div>`;
    container.appendChild(div);
    div.querySelector('.cat-label-input').focus();
}
function removeBudgetCategory(btn) {
    if (document.querySelectorAll('.budget-category').length <= 1) {
        alert('At least one budget category is required.'); return;
    }
    btn.closest('.budget-category').remove();
    calcBudgetTotal();
}
function calcBudgetTotal() {
    let t = 0;
    document.querySelectorAll('.row-amount').forEach(el => t += parseFloat(el.value) || 0);
    document.getElementById('budget-total').textContent = t.toFixed(2);
    document.getElementById('summary-budget').textContent =
        '$' + t.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}
function serializeBudget() {
    const cats = [];
    document.querySelectorAll('.budget-category').forEach(catEl => {
        const label = catEl.querySelector('.cat-label-input')?.value.trim() || '';
        const rows  = [];
        catEl.querySelectorAll('.budget-row').forEach(rowEl => {
            rows.push({
                name:   rowEl.querySelector('.row-name')?.value.trim() || '',
                amount: parseFloat(rowEl.querySelector('.row-amount')?.value) || 0,
            });
        });
        if (label) cats.push({ label, rows });
    });
    document.getElementById('budget_json_input').value = JSON.stringify(cats);
}

// ── Summary ─────────────────────────────────────────────────────────────────
function updateSummary() {
    document.getElementById('summary-client').textContent =
        document.querySelector('[name=client_name]')?.value || '—';
    const svcs = [...document.querySelectorAll('[name="services[]"]')]
        .map(i => i.value.trim()).filter(Boolean);
    document.getElementById('summary-services').textContent =
        svcs.length ? svcs.length + ' service' + (svcs.length === 1 ? '' : 's') : '—';
    calcPaymentTotal();
    calcBudgetTotal();
}

// ── Client quick-fill ────────────────────────────────────────────────────────
document.getElementById('client-picker')?.addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    if (!opt.value) return;
    document.querySelector('[name=client_name]').value = opt.dataset.name || '';
    document.getElementById('client_id_hidden').value  = opt.value;
    updateSummary();
});

// ── Form submit: serialize all JSON + sync status ────────────────────────────
document.getElementById('ag-form').addEventListener('submit', function() {
    serializePaymentSchedule();
    serializeBudget();
    document.getElementById('status_hidden').value =
        document.getElementById('status_select').value;
});

// ── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => { updateSummary(); });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
