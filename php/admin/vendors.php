<?php
require_once __DIR__ . '/../bootstrap.php';
requireRole(['admin', 'buyer']);

$crmService = new CRMService();
$errors     = [];
$editVendor = null;

// ---------------------------------------------------------------------------
// Coverage areas
// ---------------------------------------------------------------------------
const COVERAGE_AREAS = [
    'Yakima',
    'Tri-Cities',
    'Yakima/Tri-Cities',
    'Yakima/Lower Valley',
    'Ellensburg',
    'Yakima/Ellensburg',
    'Tri-Cities/Wenatchee',
    'Wenatchee',
    'Sunnyside',
    'Toppenish',
    'Selah',
    'Grandview',
    'Prosser',
];

// ---------------------------------------------------------------------------
// Service options (Media Category checkboxes)
// ---------------------------------------------------------------------------
const SERVICE_OPTIONS = [
    'TV Spots',
    'Radio Spots',
    'Live Broadcasts',
    'Print Media',
    'Remote',
    'Digital',
    'Web Takeover',
    'Social Media',
    'Geofencing',
    'Email Blast',
    'Ticket Giveaway',
    'Billboards',
    'Other',
];

// ---------------------------------------------------------------------------
// Handle POST — add or edit vendor
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Delete ──────────────────────────────────────────────────────────────
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $deleteId = (int)($_POST['id'] ?? 0);
        if ($deleteId > 0) {
            $crmService->deleteVendor($deleteId);
            flash('success', 'Vendor deleted.');
        }
        redirect('/admin/vendors.php');
    }

    $id            = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $company_name  = trim($_POST['company_name']  ?? '');
    $contact_name  = trim($_POST['contact_name']  ?? '');
    $email         = trim($_POST['email']          ?? '');
    $phone         = trim($_POST['phone']          ?? '');
    $billing_email = trim($_POST['billing_email']  ?? '');
    $address       = trim($_POST['address']        ?? '');
    $notes         = trim($_POST['notes']          ?? '');
    $demographics  = trim($_POST['demographics']   ?? '');
    $media_kit     = trim($_POST['media_kit']      ?? '');

    // Multi-value arrays from checkboxes
    $coverage_area   = array_values(array_filter($_POST['coverage_area']   ?? []));
    $service_options = array_values(array_filter($_POST['service_options'] ?? []));

    // "Other" text appended to service options
    $other_text = trim($_POST['service_other_text'] ?? '');
    if (in_array('Other', $service_options, true) && $other_text !== '') {
        // Replace plain 'Other' with 'Other: <detail>'
        $service_options = array_filter($service_options, fn($s) => $s !== 'Other');
        $service_options[] = 'Other: ' . $other_text;
        $service_options = array_values($service_options);
    }

    if ($company_name === '') {
        $errors[] = 'Company name is required.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($billing_email !== '' && !filter_var($billing_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid billing email address.';
    }

    if (empty($errors)) {
        $data = [
            'company_name'   => $company_name,
            'contact_name'   => $contact_name,
            'email'          => $email,
            'phone'          => $phone,
            'billing_email'  => $billing_email,
            'address'        => $address,
            'notes'          => $notes,
            'demographics'   => $demographics,
            'media_kit'      => $media_kit,
            'coverage_area'  => $coverage_area,
            'service_options'=> $service_options,
        ];
        if ($id !== null) {
            $data['id'] = $id;
        }

        $crmService->saveVendor($data);
        flash('success', $id ? 'Vendor updated successfully.' : 'Vendor added successfully.');
        redirect('/admin/vendors.php');
    }

    $editVendor = compact(
        'id', 'company_name', 'contact_name', 'email', 'phone',
        'billing_email', 'address', 'notes', 'demographics', 'media_kit',
        'coverage_area', 'service_options'
    );
}

$vendors = $crmService->getVendors();

// TEMP DEBUG — remove after diagnosis
try {
    $_rawPdo      = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS);
    $_rawCount    = $_rawPdo->query('SELECT COUNT(*) FROM vendors')->fetchColumn();
    $_actualHost  = $_rawPdo->query('SELECT @@hostname, @@port')->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $_e) { $_rawCount = 'ERR:'.$_e->getMessage(); $_actualHost = []; }
echo '<!-- DEBUG'
   . ' DB_HOST_CONST=' . DB_HOST
   . ' DB_NAME_CONST=' . DB_NAME
   . ' mysql_hostname=' . ($_actualHost['@@hostname'] ?? '?')
   . ' mysql_port='     . ($_actualHost['@@port']     ?? '?')
   . ' raw_count='      . $_rawCount
   . ' service_count='  . count($vendors)
   . ' -->';

$pageTitle = 'Vendors — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="bi bi-shop me-2 text-primary"></i>Vendors</h2>
        <p class="text-muted mb-0 small">Manage media vendors, contact info, coverage areas, and service options.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/vendors-export.php" class="btn btn-outline-success">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV
        </a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#vendorModal"
                onclick="resetVendorForm()">
            <i class="bi bi-plus-circle-fill me-1"></i>Add Vendor
        </button>
    </div>
</div>

<?php $successMsg = flash('success'); if ($successMsg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= h($successMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Please fix the following errors:</strong>
        <ul class="mb-0 mt-2">
            <?php foreach ($errors as $err): ?>
                <li><?= h($err) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <span class="fw-semibold text-secondary">
            <i class="bi bi-list-ul me-1"></i><?= count($vendors) ?> vendor<?= count($vendors) !== 1 ? 's' : '' ?>
        </span>
        <input type="text" class="form-control form-control-sm w-auto" id="vendorSearch"
               placeholder="Search vendors..." oninput="filterTable(this.value, 'vendorsTable')">
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="vendorsTable">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Company</th>
                        <th>Contact</th>
                        <th>Email</th>
                        <th>Coverage Area</th>
                        <th>Service Options</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vendors)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-shop fs-4 d-block mb-2"></i>No vendors found. Add your first vendor.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($vendors as $i => $vendor): ?>
                            <tr>
                                <td class="text-muted small"><?= $i + 1 ?></td>
                                <td>
                                    <i class="bi bi-shop me-2 text-secondary"></i>
                                    <strong><?= h($vendor['company_name']) ?></strong>
                                </td>
                                <td><?= h($vendor['contact_name'] ?? '—') ?></td>
                                <td>
                                    <?php if (!empty($vendor['email'])): ?>
                                        <a href="mailto:<?= h($vendor['email']) ?>" class="text-decoration-none">
                                            <?= h($vendor['email']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($vendor['coverage_area'])): ?>
                                        <?php foreach ($vendor['coverage_area'] as $area): ?>
                                            <span class="badge bg-info text-dark me-1"><?= h($area) ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($vendor['service_options'])): ?>
                                        <?php foreach ($vendor['service_options'] as $svc): ?>
                                            <span class="badge bg-primary me-1 mb-1"><?= h($svc) ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="modal" data-bs-target="#vendorModal"
                                                onclick="editVendor(<?= (int)$vendor['id'] ?>, <?= htmlspecialchars(json_encode($vendor), ENT_QUOTES, 'UTF-8') ?>)">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                onclick="confirmDelete(<?= (int)$vendor['id'] ?>, <?= htmlspecialchars(json_encode($vendor['company_name']), ENT_QUOTES, 'UTF-8') ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- =========================================================
     Delete Confirmation Modal
     ========================================================= -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content shadow">
            <form method="post" action="/admin/vendors.php" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteVendorId">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">
                        <i class="bi bi-trash me-2"></i>Delete Vendor
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">Permanently delete:</p>
                    <p class="fw-semibold" id="deleteVendorName"></p>
                    <p class="text-muted small mb-0">This cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =========================================================
     Add / Edit Vendor Modal
     ========================================================= -->
<div class="modal fade" id="vendorModal" tabindex="-1" aria-labelledby="vendorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content shadow">
            <form method="post" action="/admin/vendors.php" novalidate id="vendorForm">
                <input type="hidden" name="id" id="vendorId">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="vendorModalLabel">
                        <i class="bi bi-shop me-2"></i><span id="vendorModalTitleText">Add New Vendor</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">

                        <!-- Company Name -->
                        <div class="col-md-6">
                            <label for="vendorCompanyName" class="form-label fw-semibold">
                                Company Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="vendorCompanyName" name="company_name" class="form-control"
                                   placeholder="Broadcast Media Inc." required
                                   value="<?= h($editVendor['company_name'] ?? '') ?>">
                        </div>

                        <!-- Contact Name -->
                        <div class="col-md-6">
                            <label for="vendorContactName" class="form-label fw-semibold">Contact Name</label>
                            <input type="text" id="vendorContactName" name="contact_name" class="form-control"
                                   placeholder="Sarah Jones"
                                   value="<?= h($editVendor['contact_name'] ?? '') ?>">
                        </div>

                        <!-- Email -->
                        <div class="col-md-6">
                            <label for="vendorEmail" class="form-label fw-semibold">Email Address</label>
                            <input type="email" id="vendorEmail" name="email" class="form-control"
                                   placeholder="contact@vendor.com"
                                   value="<?= h($editVendor['email'] ?? '') ?>">
                        </div>

                        <!-- Phone -->
                        <div class="col-md-6">
                            <label for="vendorPhone" class="form-label fw-semibold">Phone</label>
                            <input type="tel" id="vendorPhone" name="phone" class="form-control"
                                   placeholder="+1 (555) 000-0000"
                                   value="<?= h($editVendor['phone'] ?? '') ?>">
                        </div>

                        <!-- Billing Email -->
                        <div class="col-md-6">
                            <label for="vendorBillingEmail" class="form-label fw-semibold">Billing Email</label>
                            <input type="email" id="vendorBillingEmail" name="billing_email" class="form-control"
                                   placeholder="billing@vendor.com"
                                   value="<?= h($editVendor['billing_email'] ?? '') ?>">
                            <div class="form-text">Invoices and billing correspondence will be sent here.</div>
                        </div>

                        <!-- Coverage Area (multi-select) -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-primary">
                                <i class="bi bi-geo-alt me-1"></i>Coverage Area
                            </label>
                            <div class="border rounded p-2" style="max-height:160px;overflow-y:auto;" id="coverageAreaList">
                                <?php foreach (COVERAGE_AREAS as $area): ?>
                                    <div class="form-check">
                                        <input class="form-check-input coverage-check" type="checkbox"
                                               name="coverage_area[]"
                                               value="<?= h($area) ?>"
                                               id="cov_<?= h(str_replace(['/', ' '], '_', $area)) ?>"
                                               <?= in_array($area, $editVendor['coverage_area'] ?? [], true) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="cov_<?= h(str_replace(['/', ' '], '_', $area)) ?>">
                                            <?= h($area) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Service Options (Media Category checkboxes) -->
                        <div class="col-12">
                            <label class="form-label fw-semibold text-primary">
                                <i class="bi bi-grid-3x3-gap me-1"></i>Service Options
                            </label>
                            <div class="border rounded p-3">
                                <div class="row g-2" id="serviceOptionsList">
                                    <?php
                                    $nonOther = array_filter(SERVICE_OPTIONS, fn($s) => $s !== 'Other');
                                    $cols = array_chunk(array_values($nonOther), 4);
                                    foreach ($cols as $col): ?>
                                        <div class="col-md-3">
                                            <?php foreach ($col as $svc): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input svc-check" type="checkbox"
                                                           name="service_options[]"
                                                           value="<?= h($svc) ?>"
                                                           id="svc_<?= h(str_replace([' ', '/'], '_', $svc)) ?>"
                                                           <?= in_array($svc, $editVendor['service_options'] ?? [], true) ? 'checked' : '' ?>>
                                                    <label class="form-check-label text-uppercase fw-semibold small"
                                                           for="svc_<?= h(str_replace([' ', '/'], '_', $svc)) ?>">
                                                        <?= h($svc) ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <!-- Other row -->
                                <div class="row g-2 mt-1 align-items-center">
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <?php
                                            // Detect if a saved "Other: ..." value exists
                                            $savedOther = '';
                                            $otherChecked = false;
                                            foreach ($editVendor['service_options'] ?? [] as $sv) {
                                                if (str_starts_with($sv, 'Other')) {
                                                    $otherChecked = true;
                                                    $savedOther = str_starts_with($sv, 'Other: ')
                                                        ? substr($sv, 7) : '';
                                                }
                                            }
                                            ?>
                                            <input class="form-check-input svc-check" type="checkbox"
                                                   name="service_options[]"
                                                   value="Other"
                                                   id="svc_Other"
                                                   <?= $otherChecked ? 'checked' : '' ?>
                                                   onchange="toggleOtherText(this)">
                                            <label class="form-check-label text-uppercase fw-semibold small" for="svc_Other">Other</label>
                                        </div>
                                    </div>
                                    <div class="col" id="otherTextWrap" style="<?= $otherChecked ? '' : 'display:none' ?>">
                                        <input type="text" name="service_other_text" id="serviceOtherText"
                                               class="form-control form-control-sm"
                                               placeholder="Describe other service..."
                                               value="<?= h($savedOther) ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Address -->
                        <div class="col-12">
                            <label for="vendorAddress" class="form-label fw-semibold">Address</label>
                            <input type="text" id="vendorAddress" name="address" class="form-control"
                                   placeholder="456 Broadcast Ave, City, State 12345"
                                   value="<?= h($editVendor['address'] ?? '') ?>">
                        </div>

                        <!-- Notes -->
                        <div class="col-12">
                            <label for="vendorNotes" class="form-label fw-semibold">Notes</label>
                            <textarea id="vendorNotes" name="notes" class="form-control" rows="2"
                                      placeholder="Contract terms, rate card details, preferred contact times..."><?= h($editVendor['notes'] ?? '') ?></textarea>
                        </div>

                        <!-- Demographics + Media Kit -->
                        <div class="col-md-6">
                            <label for="vendorDemographics" class="form-label fw-semibold">
                                <i class="bi bi-people me-1 text-primary"></i>Demographics
                            </label>
                            <textarea id="vendorDemographics" name="demographics" class="form-control" rows="4"
                                      placeholder="Age range, income level, geography, language, ethnicity, interests..."><?= h($editVendor['demographics'] ?? '') ?></textarea>
                            <div class="form-text">Used by the AI Budget Planner to match vendors to events.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="vendorMediaKit" class="form-label fw-semibold">
                                <i class="bi bi-file-earmark-bar-graph me-1 text-primary"></i>Media Kit
                            </label>
                            <textarea id="vendorMediaKit" name="media_kit" class="form-control" rows="4"
                                      placeholder="Reach, ratings, circulation, impressions, formats, market coverage..."><?= h($editVendor['media_kit'] ?? '') ?></textarea>
                            <div class="form-text">Key stats and capabilities used by the AI Budget Planner.</div>
                        </div>

                    </div><!-- /row -->
                </div><!-- /modal-body -->

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Vendor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ---------------------------------------------------------------------------
// Delete confirmation
// ---------------------------------------------------------------------------
function confirmDelete(id, name) {
    document.getElementById('deleteVendorId').value = id;
    document.getElementById('deleteVendorName').textContent = name;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteModal')).show();
}

// ---------------------------------------------------------------------------
// Toggle the "Other" text field
// ---------------------------------------------------------------------------
function toggleOtherText(cb) {
    document.getElementById('otherTextWrap').style.display = cb.checked ? '' : 'none';
    if (!cb.checked) document.getElementById('serviceOtherText').value = '';
}

// ---------------------------------------------------------------------------
// Reset form for Add
// ---------------------------------------------------------------------------
function resetVendorForm() {
    document.getElementById('vendorId').value = '';
    document.getElementById('vendorForm').reset();
    // Uncheck all coverage + service checkboxes
    document.querySelectorAll('.coverage-check, .svc-check').forEach(cb => cb.checked = false);
    document.getElementById('otherTextWrap').style.display = 'none';
    document.getElementById('vendorModalTitleText').textContent = 'Add New Vendor';
}

// ---------------------------------------------------------------------------
// Populate form for Edit
// ---------------------------------------------------------------------------
function editVendor(id, data) {
    document.getElementById('vendorId').value           = id;
    document.getElementById('vendorCompanyName').value  = data.company_name  || '';
    document.getElementById('vendorContactName').value  = data.contact_name  || '';
    document.getElementById('vendorEmail').value        = data.email         || '';
    document.getElementById('vendorPhone').value        = data.phone         || '';
    document.getElementById('vendorBillingEmail').value = data.billing_email || '';
    document.getElementById('vendorAddress').value      = data.address       || '';
    document.getElementById('vendorNotes').value        = data.notes         || '';
    document.getElementById('vendorDemographics').value = data.demographics  || '';
    document.getElementById('vendorMediaKit').value     = data.media_kit     || '';

    // Coverage area checkboxes
    const coverageAreas = Array.isArray(data.coverage_area) ? data.coverage_area : [];
    document.querySelectorAll('.coverage-check').forEach(cb => {
        cb.checked = coverageAreas.includes(cb.value);
    });

    // Service option checkboxes
    const serviceOptions = Array.isArray(data.service_options) ? data.service_options : [];
    document.querySelectorAll('.svc-check').forEach(cb => {
        cb.checked = false;
    });

    let otherText = '';
    serviceOptions.forEach(svc => {
        if (svc.startsWith('Other')) {
            document.getElementById('svc_Other').checked = true;
            otherText = svc.startsWith('Other: ') ? svc.substring(7) : '';
        } else {
            const cb = document.getElementById('svc_' + svc.replace(/[\s\/]/g, '_'));
            if (cb) cb.checked = true;
        }
    });

    const otherWrap = document.getElementById('otherTextWrap');
    otherWrap.style.display = document.getElementById('svc_Other').checked ? '' : 'none';
    document.getElementById('serviceOtherText').value = otherText;

    document.getElementById('vendorModalTitleText').textContent = 'Edit Vendor: ' + (data.company_name || '');
}

// ---------------------------------------------------------------------------
// Table search
// ---------------------------------------------------------------------------
function filterTable(query, tableId) {
    const q = query.toLowerCase();
    document.querySelectorAll('#' + tableId + ' tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

<?php if (!empty($errors) && $editVendor !== null): ?>
window.addEventListener('load', function () {
    <?php if (!empty($editVendor['id'])): ?>
    editVendor(<?= (int)$editVendor['id'] ?>, <?= json_encode($editVendor) ?>);
    <?php else: ?>
    editVendor(0, <?= json_encode($editVendor) ?>);
    <?php endif; ?>
    bootstrap.Modal.getOrCreateInstance(document.getElementById('vendorModal')).show();
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
