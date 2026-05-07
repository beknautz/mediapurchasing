<?php
require_once __DIR__ . '/../bootstrap.php';
requireRole(['admin', 'buyer']);

$crmService = new CRMService();
$errors     = [];
$editClient = null;

// ---------------------------------------------------------------------------
// Handle POST — save or delete
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Delete
    if (($_POST['action'] ?? '') === 'delete') {
        $deleteId = (int)($_POST['id'] ?? 0);
        if ($deleteId > 0) {
            $crmService->deleteClient($deleteId);
            flash('success', 'Client deleted.');
        }
        redirect('/admin/clients.php');
    }

    // Save (add / edit)
    $id              = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $company_name    = trim($_POST['company_name']    ?? '');
    $contact_name    = trim($_POST['contact_name']    ?? '');
    $address         = trim($_POST['address']         ?? '');
    $phone           = trim($_POST['phone']           ?? '');
    $secondary_phone = trim($_POST['secondary_phone'] ?? '');
    $email           = trim($_POST['email']           ?? '');
    $secondary_email = trim($_POST['secondary_email'] ?? '');
    $billing_same_as = !empty($_POST['billing_same_as']) ? 1 : 0;
    $billing_company = trim($_POST['billing_company'] ?? '');
    $billing_contact = trim($_POST['billing_contact'] ?? '');
    $billing_address = trim($_POST['billing_address'] ?? '');
    $billing_email   = trim($_POST['billing_email']   ?? '');
    $billing_phone   = trim($_POST['billing_phone']   ?? '');
    $notes           = trim($_POST['notes']           ?? '');
    $google_ads_id   = trim($_POST['google_ads_customer_id'] ?? '');
    $gbp_location    = trim($_POST['google_business_location'] ?? '');

    if ($company_name === '') {
        $errors[] = 'Company name is required.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Primary email is not valid.';
    }
    if ($secondary_email !== '' && !filter_var($secondary_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Secondary email is not valid.';
    }

    if (empty($errors)) {
        $data = compact(
            'company_name', 'contact_name', 'address',
            'phone', 'secondary_phone', 'email', 'secondary_email',
            'billing_same_as', 'billing_company', 'billing_contact',
            'billing_address', 'billing_email', 'billing_phone',
            'notes'
        );
        $data['google_ads_customer_id']   = $google_ads_id;
        $data['google_business_location'] = $gbp_location;
        if ($id) $data['id'] = $id;

        $result = $crmService->saveClient($data);
        flash('success', $result['message']);
        redirect('/admin/clients.php');
    }

    $editClient = compact(
        'id', 'company_name', 'contact_name', 'address',
        'phone', 'secondary_phone', 'email', 'secondary_email',
        'billing_same_as', 'billing_company', 'billing_contact',
        'billing_address', 'billing_email', 'billing_phone', 'notes'
    );
    $editClient['google_ads_customer_id']   = $google_ads_id;
    $editClient['google_business_location'] = $gbp_location;
}

$clients   = $crmService->getClients();
$pageTitle = 'Clients — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Billing section accent */
.billing-section { border-top: 3px solid #b02a37; }
.billing-label   { color: #b02a37; font-weight: 600; }
.billing-input   { border-color: #b02a37; color: #b02a37; }
.billing-input::placeholder { color: #e07070; }
.billing-input:focus { border-color: #b02a37; box-shadow: 0 0 0 .25rem rgba(176,42,55,.15); }
</style>

<!-- ── Page header ── -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="bi bi-building me-2 text-primary"></i>Clients</h2>
        <p class="text-muted mb-0 small">Manage client accounts and billing contacts.</p>
    </div>
    <button type="button" class="btn btn-primary"
            data-bs-toggle="modal" data-bs-target="#clientModal"
            onclick="resetClientForm()">
        <i class="bi bi-plus-circle-fill me-1"></i>New Client
    </button>
</div>

<!-- Flash messages -->
<?php $msg = flash('success'); if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill me-2"></i><?= h($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Please fix:</strong>
        <ul class="mb-0 mt-1"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- ── Clients table ── -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <span class="fw-semibold text-secondary">
            <i class="bi bi-list-ul me-1"></i><?= count($clients) ?> client<?= count($clients) !== 1 ? 's' : '' ?>
        </span>
        <input type="text" class="form-control form-control-sm w-auto" id="clientSearch"
               placeholder="Search clients…" oninput="filterTable(this.value,'clientsTable')">
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="clientsTable">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Company</th>
                        <th>Contact</th>
                        <th>Primary Email</th>
                        <th>Phone</th>
                        <th>Billing Company</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clients)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">
                            <i class="bi bi-building fs-4 d-block mb-2"></i>No clients yet. Add your first client.
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($clients as $i => $c): ?>
                            <tr>
                                <td class="text-muted small"><?= $i + 1 ?></td>
                                <td><i class="bi bi-building me-2 text-secondary"></i><strong><?= h($c['company_name']) ?></strong></td>
                                <td><?= h($c['contact_name'] ?? '—') ?></td>
                                <td>
                                    <?php if (!empty($c['email'])): ?>
                                        <a href="mailto:<?= h($c['email']) ?>" class="text-decoration-none"><?= h($c['email']) ?></a>
                                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                                </td>
                                <td><?= h($c['phone'] ?? '—') ?></td>
                                <td>
                                    <?php if (!empty($c['billing_same_as'])): ?>
                                        <span class="text-muted fst-italic small">Same as client</span>
                                    <?php else: ?>
                                        <?= h($c['billing_company'] ?? '—') ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="modal" data-bs-target="#clientModal"
                                                onclick="editClient(<?= (int)$c['id'] ?>, <?= htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8') ?>)">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                onclick="confirmDelete(<?= (int)$c['id'] ?>, <?= htmlspecialchars(json_encode($c['company_name']), ENT_QUOTES, 'UTF-8') ?>)">
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
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content shadow">
            <form method="post" action="/admin/clients.php">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteClientId">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Delete Client</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">Permanently delete:</p>
                    <p class="fw-semibold" id="deleteClientName"></p>
                    <p class="text-muted small mb-0">This cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash me-1"></i>Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =========================================================
     Add / Edit Client Modal
     ========================================================= -->
<div class="modal fade" id="clientModal" tabindex="-1" aria-labelledby="clientModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow">
            <form method="post" action="/admin/clients.php" novalidate id="clientForm">
                <input type="hidden" name="id" id="clientId">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="clientModalLabel">
                        <i class="bi bi-building me-2"></i><span id="clientModalTitle">New Client Form</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <!-- ── PDF DROP ZONE ── -->
                    <div id="pdfDropZone"
                         class="border border-2 border-dashed rounded-3 p-3 mb-4 text-center position-relative"
                         style="border-color:#6c757d!important;cursor:pointer;transition:all .2s;"
                         ondragover="pdfDragOver(event)"
                         ondragleave="pdfDragLeave(event)"
                         ondrop="pdfDrop(event)"
                         onclick="document.getElementById('pdfFileInput').click()">

                        <input type="file" id="pdfFileInput" accept="application/pdf"
                               class="d-none" onchange="pdfFileSelected(this)">

                        <div id="pdfDropIdle">
                            <i class="bi bi-file-earmark-arrow-up fs-3 text-secondary d-block mb-1"></i>
                            <p class="mb-0 fw-semibold text-secondary">Drag &amp; drop a client PDF here</p>
                            <p class="mb-0 small text-muted">or click to browse — fields will be auto-filled from the document</p>
                        </div>

                        <div id="pdfDropWorking" class="d-none">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                            <span class="text-primary fw-semibold">Extracting text from PDF…</span>
                        </div>

                        <div id="pdfDropDone" class="d-none">
                            <i class="bi bi-check-circle-fill text-success fs-3 d-block mb-1"></i>
                            <p class="mb-0 fw-semibold text-success" id="pdfDropDoneMsg"></p>
                            <p class="mb-0 small text-muted">Review the fields below and adjust if needed.</p>
                        </div>

                        <div id="pdfDropError" class="d-none">
                            <i class="bi bi-exclamation-triangle-fill text-danger fs-3 d-block mb-1"></i>
                            <p class="mb-0 fw-semibold text-danger" id="pdfDropErrorMsg"></p>
                        </div>
                    </div>

                    <!-- ── CLIENT INFO ── -->
                    <div class="row g-3 mb-3">

                        <div class="col-md-6">
                            <label for="clientCompanyName" class="form-label fw-semibold">
                                Company Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="clientCompanyName" name="company_name" class="form-control"
                                   placeholder="Acme Corp" required
                                   value="<?= h($editClient['company_name'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="clientContactName" class="form-label fw-semibold">Contact Name</label>
                            <input type="text" id="clientContactName" name="contact_name" class="form-control"
                                   placeholder="Jane Smith"
                                   value="<?= h($editClient['contact_name'] ?? '') ?>">
                        </div>

                        <div class="col-12">
                            <label for="clientAddress" class="form-label fw-semibold">Address</label>
                            <input type="text" id="clientAddress" name="address" class="form-control"
                                   placeholder="123 Main St, City, State 12345"
                                   value="<?= h($editClient['address'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="clientPhone" class="form-label fw-semibold">Business Phone</label>
                            <input type="tel" id="clientPhone" name="phone" class="form-control"
                                   placeholder="+1 (555) 000-0000"
                                   value="<?= h($editClient['phone'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="clientSecondaryPhone" class="form-label fw-semibold">Secondary Phone</label>
                            <input type="tel" id="clientSecondaryPhone" name="secondary_phone" class="form-control"
                                   placeholder="+1 (555) 000-0000"
                                   value="<?= h($editClient['secondary_phone'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="clientEmail" class="form-label fw-semibold">Primary Email</label>
                            <input type="email" id="clientEmail" name="email" class="form-control"
                                   placeholder="contact@acme.com"
                                   value="<?= h($editClient['email'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="clientSecondaryEmail" class="form-label fw-semibold">Secondary Email</label>
                            <input type="email" id="clientSecondaryEmail" name="secondary_email" class="form-control"
                                   placeholder="contact@acme.com"
                                   value="<?= h($editClient['secondary_email'] ?? '') ?>">
                        </div>

                    </div>

                    <!-- ── BILLING SECTION ── -->
                    <div class="billing-section pt-3 mt-1">

                        <p class="billing-label mb-2 text-uppercase small letter-spacing-1">Billing Information</p>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="billingSameAs" name="billing_same_as"
                                   value="1" onchange="toggleBillingSameAs(this)"
                                   <?= !empty($editClient['billing_same_as']) ? 'checked' : '' ?>>
                            <label class="form-check-label billing-label fw-semibold" for="billingSameAs">
                                Check this box if billing information is the same as client information.
                            </label>
                        </div>

                        <div id="billingFields" class="row g-3"
                             style="<?= !empty($editClient['billing_same_as']) ? 'opacity:.45;pointer-events:none;' : '' ?>">

                            <div class="col-md-6">
                                <label for="billingCompany" class="form-label billing-label">Company Name</label>
                                <input type="text" id="billingCompany" name="billing_company" class="form-control billing-input"
                                       placeholder="Acme Corp"
                                       value="<?= h($editClient['billing_company'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="billingContact" class="form-label billing-label">Accounting Contact</label>
                                <input type="text" id="billingContact" name="billing_contact" class="form-control billing-input"
                                       placeholder="Jane Smith"
                                       value="<?= h($editClient['billing_contact'] ?? '') ?>">
                            </div>

                            <div class="col-12">
                                <label for="billingAddress" class="form-label billing-label">Billing Address</label>
                                <input type="text" id="billingAddress" name="billing_address" class="form-control billing-input"
                                       placeholder="123 Main St, City, State 12345"
                                       value="<?= h($editClient['billing_address'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="billingEmail" class="form-label billing-label">Accounting Email</label>
                                <input type="email" id="billingEmail" name="billing_email" class="form-control billing-input"
                                       placeholder="contact@acme.com"
                                       value="<?= h($editClient['billing_email'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="billingPhone" class="form-label billing-label">Accounting Phone</label>
                                <input type="tel" id="billingPhone" name="billing_phone" class="form-control billing-input"
                                       placeholder="+1 (555) 000-0000"
                                       value="<?= h($editClient['billing_phone'] ?? '') ?>">
                            </div>

                        </div><!-- /billingFields -->
                    </div><!-- /billing-section -->

                    <!-- ── OPTIONAL / ADVANCED ── -->
                    <div class="row g-3 mt-1">
                        <div class="col-12">
                            <label for="clientNotes" class="form-label fw-semibold">Notes</label>
                            <textarea id="clientNotes" name="notes" class="form-control" rows="2"
                                      placeholder="Additional notes about this client…"><?= h($editClient['notes'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="clientGoogleAdsId" class="form-label fw-semibold text-muted small">Google Ads Customer ID</label>
                            <input type="text" id="clientGoogleAdsId" name="google_ads_customer_id" class="form-control form-control-sm"
                                   placeholder="123-456-7890"
                                   value="<?= h($editClient['google_ads_customer_id'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="clientGbpLocation" class="form-label fw-semibold text-muted small">Google Business Profile Location</label>
                            <input type="text" id="clientGbpLocation" name="google_business_location" class="form-control form-control-sm"
                                   placeholder="locations/1234567890"
                                   value="<?= h($editClient['google_business_location'] ?? '') ?>">
                        </div>
                    </div>

                </div><!-- /modal-body -->

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Client
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ---------------------------------------------------------------------------
// "Same as client" checkbox
// ---------------------------------------------------------------------------
function toggleBillingSameAs(cb) {
    const wrap = document.getElementById('billingFields');
    if (cb.checked) {
        // Mirror client fields into billing fields (read-only visually)
        document.getElementById('billingCompany').value = document.getElementById('clientCompanyName').value;
        document.getElementById('billingContact').value = document.getElementById('clientContactName').value;
        document.getElementById('billingAddress').value = document.getElementById('clientAddress').value;
        document.getElementById('billingEmail').value   = document.getElementById('clientEmail').value;
        document.getElementById('billingPhone').value   = document.getElementById('clientPhone').value;
        wrap.style.opacity        = '0.45';
        wrap.style.pointerEvents  = 'none';
    } else {
        wrap.style.opacity        = '1';
        wrap.style.pointerEvents  = '';
    }
}

// ---------------------------------------------------------------------------
// Reset form for Add
// ---------------------------------------------------------------------------
function resetClientForm() {
    document.getElementById('clientId').value = '';
    document.getElementById('clientForm').reset();
    document.getElementById('clientModalTitle').textContent = 'New Client Form';
    const wrap = document.getElementById('billingFields');
    wrap.style.opacity       = '1';
    wrap.style.pointerEvents = '';
}

// ---------------------------------------------------------------------------
// Populate form for Edit
// ---------------------------------------------------------------------------
function editClient(id, d) {
    document.getElementById('clientId').value              = id;
    document.getElementById('clientCompanyName').value     = d.company_name        || '';
    document.getElementById('clientContactName').value     = d.contact_name        || '';
    document.getElementById('clientAddress').value         = d.address             || '';
    document.getElementById('clientPhone').value           = d.phone               || '';
    document.getElementById('clientSecondaryPhone').value  = d.secondary_phone     || '';
    document.getElementById('clientEmail').value           = d.email               || '';
    document.getElementById('clientSecondaryEmail').value  = d.secondary_email     || '';
    document.getElementById('clientNotes').value           = d.notes               || '';
    document.getElementById('clientGoogleAdsId').value     = d.google_ads_customer_id   || '';
    document.getElementById('clientGbpLocation').value     = d.google_business_location || '';

    const sameAs = !!parseInt(d.billing_same_as || 0);
    document.getElementById('billingSameAs').checked       = sameAs;
    document.getElementById('billingCompany').value        = d.billing_company || '';
    document.getElementById('billingContact').value        = d.billing_contact || '';
    document.getElementById('billingAddress').value        = d.billing_address || '';
    document.getElementById('billingEmail').value          = d.billing_email   || '';
    document.getElementById('billingPhone').value          = d.billing_phone   || '';

    const wrap = document.getElementById('billingFields');
    wrap.style.opacity       = sameAs ? '0.45' : '1';
    wrap.style.pointerEvents = sameAs ? 'none'  : '';

    document.getElementById('clientModalTitle').textContent = 'Edit Client: ' + (d.company_name || '');
}

// ---------------------------------------------------------------------------
// Delete confirmation
// ---------------------------------------------------------------------------
function confirmDelete(id, name) {
    document.getElementById('deleteClientId').value        = id;
    document.getElementById('deleteClientName').textContent = name;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteModal')).show();
}

// ---------------------------------------------------------------------------
// Table search
// ---------------------------------------------------------------------------
// ===========================================================================
// PDF Drop Zone
// ===========================================================================
const PDF_ENDPOINT = '/proposals/extract-client-pdf.cfm';

function pdfDragOver(e) {
    e.preventDefault();
    const z = document.getElementById('pdfDropZone');
    z.style.borderColor  = '#0d6efd';
    z.style.background   = '#f0f5ff';
}
function pdfDragLeave(e) {
    const z = document.getElementById('pdfDropZone');
    z.style.borderColor  = '';
    z.style.background   = '';
}
function pdfDrop(e) {
    e.preventDefault();
    pdfDragLeave(e);
    const file = e.dataTransfer.files[0];
    if (file) processPdf(file);
}
function pdfFileSelected(input) {
    if (input.files[0]) processPdf(input.files[0]);
}

function pdfSetState(state, msg) {
    ['Idle','Working','Done','Error'].forEach(s =>
        document.getElementById('pdfDrop' + s).classList.add('d-none'));
    document.getElementById('pdfDrop' + state).classList.remove('d-none');
    if (state === 'Done'  && msg) document.getElementById('pdfDropDoneMsg').textContent  = msg;
    if (state === 'Error' && msg) document.getElementById('pdfDropErrorMsg').textContent = msg;
}

function processPdf(file) {
    if (file.type && file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
        pdfSetState('Error', 'Please drop a PDF file.');
        return;
    }
    pdfSetState('Working');

    const fd = new FormData();
    fd.append('pdf', file);

    fetch(PDF_ENDPOINT, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error(data.error || 'Extraction failed.');
            const filled = populateFromText(data.text);
            const pageStr = data.pages ? ` (${data.pages} page${data.pages !== 1 ? 's' : ''})` : '';
            pdfSetState('Done', `Filled ${filled} field${filled !== 1 ? 's' : ''} from "${file.name}"${pageStr}`);
        })
        .catch(err => pdfSetState('Error', err.message || 'Could not process PDF.'));
}

// ---------------------------------------------------------------------------
// Field extraction — regex patterns applied to raw PDF text
// ---------------------------------------------------------------------------
function populateFromText(text) {
    const set   = (id, val) => { if (val) document.getElementById(id).value = val.trim(); };
    let filled  = 0;
    const mark  = (id, val) => { if (val && val.trim()) { set(id, val); filled++; } };

    // ── Emails ──────────────────────────────────────────────────────────────
    const emails = [...new Set(
        (text.match(/\b[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}\b/g) || [])
    )];
    mark('clientEmail', emails[0]);
    mark('clientSecondaryEmail', emails[1]);

    // ── Phone numbers ───────────────────────────────────────────────────────
    const phones = [...new Set(
        (text.match(/(\+?1[\s.\-]?)?\(?\d{3}\)?[\s.\-]?\d{3}[\s.\-]?\d{4}/g) || [])
            .map(p => p.trim())
    )];
    mark('clientPhone', phones[0]);
    mark('clientSecondaryPhone', phones[1]);

    // ── Company name ────────────────────────────────────────────────────────
    const coLabels = /(?:company|business|client|organization|firm|account)\s*[:\-]\s*([^\n\r]{2,80})/i;
    const coMatch  = text.match(coLabels);
    mark('clientCompanyName', coMatch ? coMatch[1] : null);

    // ── Contact / person name ───────────────────────────────────────────────
    const ctLabels = /(?:contact|name|attention|att\.?|representative|rep\.?|to)\s*[:\-]\s*([A-Z][a-z]+(?:\s+[A-Z][a-z]+){1,3})/;
    const ctMatch  = text.match(ctLabels);
    mark('clientContactName', ctMatch ? ctMatch[1] : null);

    // ── Street address ──────────────────────────────────────────────────────
    const addrMatch = text.match(
        /\d{1,6}\s+[A-Za-z0-9 .]+(?:St(?:reet)?|Ave(?:nue)?|Blvd|Boulevard|Dr(?:ive)?|Rd|Road|Way|Ln|Lane|Ct|Court|Pl(?:ace)?|Pkwy|Suite|Ste)[^\n\r]{0,60}/i
    );
    mark('clientAddress', addrMatch ? addrMatch[0].replace(/\s+/g, ' ') : null);

    // ── Billing section ─────────────────────────────────────────────────────
    const billingSectionMatch = text.match(
        /billing\s*(?:information|info|contact|address)?[\s:\-]*([\s\S]{10,600}?)(?=\n{2,}|accounting|invoice|$)/i
    );
    if (billingSectionMatch) {
        const bs = billingSectionMatch[1];

        const bEmails = (bs.match(/\b[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}\b/g) || [])
            .filter(e => e !== document.getElementById('clientEmail').value);
        mark('billingEmail', bEmails[0]);

        const bPhones = (bs.match(/(\+?1[\s.\-]?)?\(?\d{3}\)?[\s.\-]?\d{3}[\s.\-]?\d{4}/g) || [])
            .map(p => p.trim())
            .filter(p => p !== document.getElementById('clientPhone').value);
        mark('billingPhone', bPhones[0]);

        const bAddrMatch = bs.match(
            /\d{1,6}\s+[A-Za-z0-9 .]+(?:St(?:reet)?|Ave(?:nue)?|Blvd|Dr(?:ive)?|Rd|Way|Ln|Ct|Pl(?:ace)?|Suite|Ste)[^\n\r]{0,60}/i
        );
        mark('billingAddress', bAddrMatch ? bAddrMatch[0].replace(/\s+/g, ' ') : null);

        const bCoMatch = bs.match(/(?:company|bill\s*to|billing\s*company|payee)\s*[:\-]\s*([^\n\r]{2,80})/i);
        mark('billingCompany', bCoMatch ? bCoMatch[1] : null);

        const bCtMatch = bs.match(/(?:contact|accounting\s*contact|attn\.?|attention)\s*[:\-]\s*([A-Z][a-z]+(?:\s+[A-Z][a-z]+){1,2})/i);
        mark('billingContact', bCtMatch ? bCtMatch[1] : null);
    }

    // Highlight filled fields briefly
    document.querySelectorAll('#clientForm input, #clientForm textarea').forEach(el => {
        if (el.value && el.value !== el.defaultValue) {
            el.style.transition = 'background .3s';
            el.style.background = '#d1fae5';
            setTimeout(() => el.style.background = '', 2000);
        }
    });

    return filled;
}

// ---------------------------------------------------------------------------
// Reset drop zone when modal closes
// ---------------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('clientModal').addEventListener('hidden.bs.modal', function () {
        pdfSetState('Idle');
        document.getElementById('pdfFileInput').value = '';
        const z = document.getElementById('pdfDropZone');
        z.style.borderColor = '';
        z.style.background  = '';
    });
});

function filterTable(q, tableId) {
    q = q.toLowerCase();
    document.querySelectorAll('#' + tableId + ' tbody tr').forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

<?php if (!empty($errors) && $editClient !== null): ?>
window.addEventListener('load', function () {
    editClient(<?= (int)($editClient['id'] ?? 0) ?>, <?= json_encode($editClient) ?>);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('clientModal')).show();
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
