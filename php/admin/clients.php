<?php
require_once __DIR__ . '/../bootstrap.php';
requireRole(['admin', 'buyer']);

$crmService = new CRMService();
$errors     = [];
$editClient = null;

// Handle POST — add or edit client
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id           = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $company_name = trim($_POST['company_name'] ?? '');
    $contact_name = trim($_POST['contact_name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $notes        = trim($_POST['notes'] ?? '');
    $google_ads_customer_id = preg_replace('/\D/', '', $_POST['google_ads_customer_id'] ?? '');

    if ($company_name === '') {
        $errors[] = 'Company name is required.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($errors)) {
        $data = [
            'company_name' => $company_name,
            'contact_name' => $contact_name,
            'email'        => $email,
            'phone'        => $phone,
            'address'      => $address,
            'notes'        => $notes,
            'google_ads_customer_id' => $google_ads_customer_id ?: null,
        ];
        if ($id !== null) {
            $data['id'] = $id;
        }

        $crmService->saveClient($data);
        $_SESSION['flash'] = ['type' => 'success', 'message' => $id ? 'Client updated successfully.' : 'Client added successfully.'];
        redirect('/admin/clients.php');
    }

    $editClient = compact('id', 'company_name', 'contact_name', 'email', 'phone', 'address', 'notes', 'google_ads_customer_id');
}

$clients = $crmService->getClients();

$pageTitle = 'Clients — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="bi bi-building me-2 text-primary"></i>Clients</h2>
        <p class="text-muted mb-0 small">Manage your advertising clients and their contact details.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#clientModal"
            onclick="resetClientForm()">
        <i class="bi bi-plus-circle-fill me-1"></i>Add Client
    </button>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Please fix the following errors:</strong>
        <ul class="mb-0 mt-2">
            <?php foreach ($errors as $err): ?>
                <li><?= h($err) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <span class="fw-semibold text-secondary">
            <i class="bi bi-list-ul me-1"></i><?= count($clients) ?> client<?= count($clients) !== 1 ? 's' : '' ?>
        </span>
        <input type="text" class="form-control form-control-sm w-auto" id="clientSearch"
               placeholder="Search clients..." oninput="filterTable(this.value, 'clientsTable')">
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="clientsTable">
                <thead class="table-dark">
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Company</th>
                        <th scope="col">Contact</th>
                        <th scope="col">Email</th>
                        <th scope="col">Phone</th>
                        <th scope="col">Notes</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clients)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-building fs-4 d-block mb-2"></i>No clients found. Add your first client.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clients as $i => $client): ?>
                            <tr>
                                <td class="text-muted small"><?= (int)($i + 1) ?></td>
                                <td>
                                    <i class="bi bi-building me-2 text-secondary"></i>
                                    <strong><?= h($client['company_name']) ?></strong>
                                </td>
                                <td><?= h($client['contact_name'] ?? '—') ?></td>
                                <td>
                                    <?php if (!empty($client['email'])): ?>
                                        <a href="mailto:<?= h($client['email']) ?>" class="text-decoration-none">
                                            <?= h($client['email']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= h($client['phone'] ?? '—') ?></td>
                                <td class="text-muted small" style="max-width:200px;">
                                    <?= !empty($client['notes'] ?? '') ? h(mb_strimwidth($client['notes'] ?? '', 0, 60, '…')) : '—' ?>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="modal" data-bs-target="#clientModal"
                                            onclick="editClient(<?= (int)$client['id'] ?>, <?= htmlspecialchars(json_encode($client), ENT_QUOTES, 'UTF-8') ?>)">
                                        <i class="bi bi-pencil-square"></i> Edit
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

<!-- Add / Edit Client Modal -->
<div class="modal fade" id="clientModal" tabindex="-1" aria-labelledby="clientModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow">
            <form method="post" action="/admin/clients.php" novalidate id="clientForm">
                <input type="hidden" name="id" id="clientId">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="clientModalLabel">
                        <i class="bi bi-building me-2"></i><span id="clientModalTitleText">Add New Client</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label for="companyName" class="form-label fw-semibold">Company Name <span class="text-danger">*</span></label>
                            <input type="text" id="companyName" name="company_name" class="form-control"
                                   placeholder="Acme Corp" required
                                   value="<?= h($editClient['company_name'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="contactName" class="form-label fw-semibold">Contact Name</label>
                            <input type="text" id="contactName" name="contact_name" class="form-control"
                                   placeholder="John Doe"
                                   value="<?= h($editClient['contact_name'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="clientEmail" class="form-label fw-semibold">Email Address</label>
                            <input type="email" id="clientEmail" name="email" class="form-control"
                                   placeholder="contact@acme.com"
                                   value="<?= h($editClient['email'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="clientPhone" class="form-label fw-semibold">Phone</label>
                            <input type="tel" id="clientPhone" name="phone" class="form-control"
                                   placeholder="+1 (555) 000-0000"
                                   value="<?= h($editClient['phone'] ?? '') ?>">
                        </div>

                        <div class="col-12">
                            <label for="clientAddress" class="form-label fw-semibold">Address</label>
                            <input type="text" id="clientAddress" name="address" class="form-control"
                                   placeholder="123 Main St, City, State 12345"
                                   value="<?= h($editClient['address'] ?? '') ?>">
                        </div>

                        <div class="col-12">
                            <label for="clientNotes" class="form-label fw-semibold">Notes</label>
                            <textarea id="clientNotes" name="notes" class="form-control" rows="3"
                                      placeholder="Any relevant notes about this client..."><?= h($editClient['notes'] ?? '') ?></textarea>
                        </div>

                        <div class="col-md-6">
                            <label for="googleAdsCustomerId" class="form-label fw-semibold">
                                <i class="bi bi-google me-1 text-warning"></i>Google Ads Customer ID
                            </label>
                            <input type="text" id="googleAdsCustomerId" name="google_ads_customer_id"
                                   class="form-control" placeholder="e.g. 352-371-6554"
                                   value="<?= h($editClient['google_ads_customer_id'] ?? '') ?>">
                            <div class="form-text">Found in the top-right of the client's Google Ads account.</div>
                        </div>

                    </div>
                </div>

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
function resetClientForm() {
    document.getElementById('clientId').value = '';
    document.getElementById('clientForm').reset();
    document.getElementById('clientModalTitleText').textContent = 'Add New Client';
}

function editClient(id, data) {
    document.getElementById('clientId').value = id;
    document.getElementById('companyName').value = data.company_name || '';
    document.getElementById('contactName').value = data.contact_name || '';
    document.getElementById('clientEmail').value  = data.email || '';
    document.getElementById('clientPhone').value  = data.phone || '';
    document.getElementById('clientAddress').value = data.address || '';
    document.getElementById('clientNotes').value   = data.notes || '';
    document.getElementById('googleAdsCustomerId').value = data.google_ads_customer_id || '';
    document.getElementById('clientModalTitleText').textContent = 'Edit Client: ' + (data.company_name || '');
}

function filterTable(query, tableId) {
    const rows = document.querySelectorAll('#' + tableId + ' tbody tr');
    const q = query.toLowerCase();
    rows.forEach(function (row) {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

<?php if (!empty($errors) && $editClient !== null): ?>
document.addEventListener('DOMContentLoaded', function () {
    var modal = new bootstrap.Modal(document.getElementById('clientModal'));
    <?php if (!empty($editClient['id'])): ?>
    editClient(<?= (int)$editClient['id'] ?>, <?= json_encode($editClient) ?>);
    <?php else: ?>
    resetClientForm();
    // Restore posted values
    document.getElementById('companyName').value  = <?= json_encode($editClient['company_name']) ?>;
    document.getElementById('contactName').value  = <?= json_encode($editClient['contact_name']) ?>;
    document.getElementById('clientEmail').value   = <?= json_encode($editClient['email']) ?>;
    document.getElementById('clientPhone').value   = <?= json_encode($editClient['phone']) ?>;
    document.getElementById('clientAddress').value = <?= json_encode($editClient['address']) ?>;
    document.getElementById('clientNotes').value   = <?= json_encode($editClient['notes']) ?>;
    <?php endif; ?>
    modal.show();
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
