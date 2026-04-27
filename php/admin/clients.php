<?php
require_once __DIR__ . '/../bootstrap.php';
requireRole(['admin', 'buyer']);

$crmService = new CRMService();
$errors     = [];
$editClient = null;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $delId = (int)($_POST['id'] ?? 0);
        if ($delId) {
            $crmService->deleteClient($delId);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Client removed.'];
        }
        redirect('/admin/clients.php');
    }

    $id                      = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $company_name            = trim($_POST['company_name'] ?? '');
    $contact_name            = trim($_POST['contact_name'] ?? '');
    $email                   = trim($_POST['email'] ?? '');
    $phone                   = trim($_POST['phone'] ?? '');
    $address                 = trim($_POST['address'] ?? '');
    $notes                   = trim($_POST['notes'] ?? '');
    $google_ads_customer_id  = preg_replace('/\D/', '', $_POST['google_ads_customer_id'] ?? '');
    $google_business_location = trim($_POST['google_business_location'] ?? '');

    if ($company_name === '') $errors[] = 'Company name is required.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';

    if (empty($errors)) {
        $data = compact('company_name', 'contact_name', 'email', 'phone', 'address', 'notes',
                        'google_ads_customer_id', 'google_business_location');
        $data['google_ads_customer_id'] = $google_ads_customer_id ?: null;
        $data['google_business_location'] = $google_business_location ?: null;
        if ($id !== null) $data['id'] = $id;

        $crmService->saveClient($data);
        $_SESSION['flash'] = ['type' => 'success', 'message' => $id ? 'Client updated.' : 'Client added.'];
        redirect('/admin/clients.php');
    }

    $editClient = compact('id', 'company_name', 'contact_name', 'email', 'phone', 'address',
                          'notes', 'google_ads_customer_id', 'google_business_location');
}

$clients   = $crmService->getClients();
$pageTitle = 'Clients — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="bi bi-building me-2 text-primary"></i>Clients</h2>
        <p class="text-muted mb-0 small">Manage clients, their Google Ads accounts, and Business Profile locations.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#clientModal"
            onclick="resetClientForm()">
        <i class="bi bi-plus-circle-fill me-1"></i>Add Client
    </button>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Please fix the following:</strong>
    <ul class="mb-0 mt-1"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

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
                        <th>Email</th>
                        <th>Phone</th>
                        <th class="text-center">Google</th>
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
                        <td>
                            <a href="/admin/client-view.php?id=<?= (int)$c['id'] ?>" class="fw-semibold text-decoration-none">
                                <i class="bi bi-building me-1 text-secondary"></i><?= h($c['company_name']) ?>
                            </a>
                        </td>
                        <td class="text-muted"><?= h($c['contact_name'] ?? '—') ?></td>
                        <td>
                            <?php if (!empty($c['email'])): ?>
                                <a href="mailto:<?= h($c['email']) ?>" class="text-decoration-none small"><?= h($c['email']) ?></a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= h($c['phone'] ?? '—') ?></td>
                        <td class="text-center">
                            <?php if (!empty($c['google_ads_customer_id'])): ?>
                                <span class="badge bg-warning text-dark me-1" title="Google Ads: <?= h($c['google_ads_customer_id']) ?>">
                                    <i class="bi bi-google me-1"></i>Ads
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($c['google_business_location'])): ?>
                                <span class="badge bg-success" title="GBP: <?= h($c['google_business_location']) ?>">
                                    <i class="bi bi-geo-alt-fill me-1"></i>GBP
                                </span>
                            <?php endif; ?>
                            <?php if (empty($c['google_ads_customer_id']) && empty($c['google_business_location'])): ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="/admin/client-view.php?id=<?= (int)$c['id'] ?>"
                               class="btn btn-sm btn-outline-primary me-1" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-secondary me-1"
                                    data-bs-toggle="modal" data-bs-target="#clientModal"
                                    onclick="editClient(<?= (int)$c['id'] ?>, <?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)"
                                    title="Edit">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                    onclick="confirmDelete(<?= (int)$c['id'] ?>, '<?= h(addslashes($c['company_name'])) ?>')"
                                    title="Delete">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add / Edit Modal -->
<div class="modal fade" id="clientModal" tabindex="-1" aria-labelledby="clientModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow">
            <form method="post" action="/admin/clients.php" novalidate id="clientForm">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="clientId">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="clientModalLabel">
                        <i class="bi bi-building me-2"></i><span id="clientModalTitleText">Add New Client</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Company Name <span class="text-danger">*</span></label>
                            <input type="text" id="companyName" name="company_name" class="form-control"
                                   placeholder="Acme Corp" required value="<?= h($editClient['company_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contact Name</label>
                            <input type="text" id="contactName" name="contact_name" class="form-control"
                                   placeholder="Jane Smith" value="<?= h($editClient['contact_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" id="clientEmail" name="email" class="form-control"
                                   placeholder="contact@acme.com" value="<?= h($editClient['email'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="tel" id="clientPhone" name="phone" class="form-control"
                                   placeholder="+1 (555) 000-0000" value="<?= h($editClient['phone'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Address</label>
                            <input type="text" id="clientAddress" name="address" class="form-control"
                                   placeholder="123 Main St, City, State 12345" value="<?= h($editClient['address'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea id="clientNotes" name="notes" class="form-control" rows="2"
                                      placeholder="Internal notes…"><?= h($editClient['notes'] ?? '') ?></textarea>
                        </div>

                        <div class="col-12"><hr class="my-1"><p class="fw-semibold small text-muted mb-0">
                            <i class="bi bi-google me-1 text-warning"></i>Google Integration
                        </p></div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Google Ads Customer ID</label>
                            <input type="text" id="googleAdsCustomerId" name="google_ads_customer_id"
                                   class="form-control" placeholder="e.g. 352-371-6554"
                                   value="<?= h($editClient['google_ads_customer_id'] ?? '') ?>">
                            <div class="form-text">Found in the top-right of their Google Ads account.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Google Business Profile Location</label>
                            <input type="text" id="googleBizLocation" name="google_business_location"
                                   class="form-control font-monospace" placeholder="locations/1234567890"
                                   value="<?= h($editClient['google_business_location'] ?? '') ?>">
                            <div class="form-text">
                                Find this on the
                                <a href="/ad-automation/google-settings.php" target="_blank">Google Settings</a>
                                page under Business Profile Locations.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Client</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete confirmation form (hidden) -->
<form id="deleteForm" method="post" action="/admin/clients.php" style="display:none">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteClientId">
</form>

<script>
function resetClientForm() {
    document.getElementById('clientId').value = '';
    document.getElementById('clientForm').reset();
    document.getElementById('clientModalTitleText').textContent = 'Add New Client';
}

function editClient(id, data) {
    document.getElementById('clientId').value              = id;
    document.getElementById('companyName').value           = data.company_name || '';
    document.getElementById('contactName').value           = data.contact_name || '';
    document.getElementById('clientEmail').value           = data.email || '';
    document.getElementById('clientPhone').value           = data.phone || '';
    document.getElementById('clientAddress').value         = data.address || '';
    document.getElementById('clientNotes').value           = data.notes || '';
    document.getElementById('googleAdsCustomerId').value   = data.google_ads_customer_id || '';
    document.getElementById('googleBizLocation').value     = data.google_business_location || '';
    document.getElementById('clientModalTitleText').textContent = 'Edit: ' + (data.company_name || '');
}

function confirmDelete(id, name) {
    if (!confirm('Remove client "' + name + '"? This will deactivate the record and cannot be undone easily.')) return;
    document.getElementById('deleteClientId').value = id;
    document.getElementById('deleteForm').submit();
}

function filterTable(query, tableId) {
    const q = query.toLowerCase();
    document.querySelectorAll('#' + tableId + ' tbody tr').forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

<?php if (!empty($errors) && $editClient !== null): ?>
document.addEventListener('DOMContentLoaded', function () {
    var modal = new bootstrap.Modal(document.getElementById('clientModal'));
    <?php if (!empty($editClient['id'])): ?>
    editClient(<?= (int)$editClient['id'] ?>, <?= json_encode($editClient) ?>);
    <?php else: ?>
    resetClientForm();
    document.getElementById('companyName').value         = <?= json_encode($editClient['company_name']) ?>;
    document.getElementById('contactName').value         = <?= json_encode($editClient['contact_name']) ?>;
    document.getElementById('clientEmail').value          = <?= json_encode($editClient['email']) ?>;
    document.getElementById('clientPhone').value          = <?= json_encode($editClient['phone']) ?>;
    document.getElementById('clientAddress').value        = <?= json_encode($editClient['address']) ?>;
    document.getElementById('clientNotes').value          = <?= json_encode($editClient['notes']) ?>;
    document.getElementById('googleAdsCustomerId').value  = <?= json_encode($editClient['google_ads_customer_id']) ?>;
    document.getElementById('googleBizLocation').value    = <?= json_encode($editClient['google_business_location']) ?>;
    <?php endif; ?>
    modal.show();
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
