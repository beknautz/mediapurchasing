<?php
require_once __DIR__ . '/../bootstrap.php';
requireRole(['admin', 'buyer']);

$crmService = new CRMService();
$errors     = [];
$editVendor = null;

// Handle POST — add or edit vendor
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id            = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $company_name  = trim($_POST['company_name'] ?? '');
    $contact_name  = trim($_POST['contact_name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $billing_email  = trim($_POST['billing_email']  ?? '');
    $media_category = trim($_POST['media_category'] ?? '');
    $address        = trim($_POST['address'] ?? '');
    $notes         = trim($_POST['notes'] ?? '');

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
            'media_category' => $media_category,
            'address'        => $address,
            'notes'          => $notes,
        ];
        if ($id !== null) {
            $data['id'] = $id;
        }

        $crmService->saveVendor($data);
        $_SESSION['flash'] = ['type' => 'success', 'message' => $id ? 'Vendor updated successfully.' : 'Vendor added successfully.'];
        redirect('/admin/vendors.php');
    }

    $editVendor = compact('id', 'company_name', 'contact_name', 'email', 'phone', 'billing_email', 'media_category', 'address', 'notes');
}

$vendors = $crmService->getVendors();

const MEDIA_CATEGORIES = [
    'TV - Spanish',
    'TV - English',
    'Radio - Spanish',
    'Radio - English',
    'Digital/Social',
    'Newsprint',
    'Production',
    'Other',
];

$categoryColors = [
    'TV - Spanish'    => 'bg-danger',
    'TV - English'    => 'bg-primary',
    'Radio - Spanish' => 'bg-warning text-dark',
    'Radio - English' => 'bg-info text-dark',
    'Digital/Social'  => 'bg-success',
    'Newsprint'       => 'bg-secondary',
    'Production'      => 'bg-dark',
    'Other'           => 'bg-light text-dark border',
];

function mediaCategoryBadge(string $cat, array $colors): string {
    if ($cat === '') {
        return '<span class="text-muted">—</span>';
    }
    $class = $colors[$cat] ?? 'bg-secondary';
    return '<span class="badge ' . $class . '">' . h($cat) . '</span>';
}

$pageTitle = 'Vendors — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="bi bi-shop me-2 text-primary"></i>Vendors</h2>
        <p class="text-muted mb-0 small">Manage media vendors, their contact information and media types.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#vendorModal"
            onclick="resetVendorForm()">
        <i class="bi bi-plus-circle-fill me-1"></i>Add Vendor
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
                        <th scope="col">#</th>
                        <th scope="col">Company</th>
                        <th scope="col">Contact</th>
                        <th scope="col">Email</th>
                        <th scope="col">Billing Email</th>
                        <th scope="col">Category</th>
                        <th scope="col" class="text-end">Actions</th>
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
                                <td class="text-muted small"><?= (int)($i + 1) ?></td>
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
                                    <?php if (!empty($vendor['billing_email'])): ?>
                                        <a href="mailto:<?= h($vendor['billing_email']) ?>" class="text-decoration-none small">
                                            <?= h($vendor['billing_email']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= mediaCategoryBadge($vendor['media_category'] ?? '', $categoryColors) ?></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="modal" data-bs-target="#vendorModal"
                                            onclick="editVendor(<?= (int)$vendor['id'] ?>, <?= htmlspecialchars(json_encode($vendor), ENT_QUOTES, 'UTF-8') ?>)">
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

<!-- Add / Edit Vendor Modal -->
<div class="modal fade" id="vendorModal" tabindex="-1" aria-labelledby="vendorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow">
            <form method="post" action="/admin/vendors.php" novalidate id="vendorForm">
                <input type="hidden" name="id" id="vendorId">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="vendorModalLabel">
                        <i class="bi bi-shop me-2"></i><span id="vendorModalTitleText">Add New Vendor</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label for="vendorCompanyName" class="form-label fw-semibold">Company Name <span class="text-danger">*</span></label>
                            <input type="text" id="vendorCompanyName" name="company_name" class="form-control"
                                   placeholder="Broadcast Media Inc." required
                                   value="<?= h($editVendor['company_name'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="vendorContactName" class="form-label fw-semibold">Contact Name</label>
                            <input type="text" id="vendorContactName" name="contact_name" class="form-control"
                                   placeholder="Sarah Jones"
                                   value="<?= h($editVendor['contact_name'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="vendorEmail" class="form-label fw-semibold">Email Address</label>
                            <input type="email" id="vendorEmail" name="email" class="form-control"
                                   placeholder="contact@vendor.com"
                                   value="<?= h($editVendor['email'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="vendorPhone" class="form-label fw-semibold">Phone</label>
                            <input type="tel" id="vendorPhone" name="phone" class="form-control"
                                   placeholder="+1 (555) 000-0000"
                                   value="<?= h($editVendor['phone'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="vendorBillingEmail" class="form-label fw-semibold">Billing Email</label>
                            <input type="email" id="vendorBillingEmail" name="billing_email" class="form-control"
                                   placeholder="billing@vendor.com"
                                   value="<?= h($editVendor['billing_email'] ?? '') ?>">
                            <div class="form-text">Invoices and billing correspondence will be sent here.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="vendorMediaCategory" class="form-label fw-semibold">Media Category</label>
                            <select id="vendorMediaCategory" name="media_category" class="form-select">
                                <option value="">— Select category —</option>
                                <?php foreach (MEDIA_CATEGORIES as $cat): ?>
                                <option value="<?= h($cat) ?>"
                                    <?= ($editVendor['media_category'] ?? '') === $cat ? 'selected' : '' ?>>
                                    <?= h($cat) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="vendorAddress" class="form-label fw-semibold">Address</label>
                            <input type="text" id="vendorAddress" name="address" class="form-control"
                                   placeholder="456 Broadcast Ave, City, State 12345"
                                   value="<?= h($editVendor['address'] ?? '') ?>">
                        </div>

                        <div class="col-12">
                            <label for="vendorNotes" class="form-label fw-semibold">Notes</label>
                            <textarea id="vendorNotes" name="notes" class="form-control" rows="3"
                                      placeholder="Contract terms, rate card details, preferred contact times..."><?= h($editVendor['notes'] ?? '') ?></textarea>
                        </div>

                    </div>
                </div>

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
function resetVendorForm() {
    document.getElementById('vendorId').value = '';
    document.getElementById('vendorForm').reset();
    document.getElementById('vendorModalTitleText').textContent = 'Add New Vendor';
}

function editVendor(id, data) {
    document.getElementById('vendorId').value            = id;
    document.getElementById('vendorCompanyName').value   = data.company_name   || '';
    document.getElementById('vendorContactName').value   = data.contact_name   || '';
    document.getElementById('vendorEmail').value         = data.email          || '';
    document.getElementById('vendorPhone').value         = data.phone          || '';
    document.getElementById('vendorBillingEmail').value  = data.billing_email  || '';
    document.getElementById('vendorMediaCategory').value = data.media_category || '';
    document.getElementById('vendorAddress').value       = data.address        || '';
    document.getElementById('vendorNotes').value         = data.notes          || '';
    document.getElementById('vendorModalTitleText').textContent = 'Edit Vendor: ' + (data.company_name || '');
}

function filterTable(query, tableId) {
    const rows = document.querySelectorAll('#' + tableId + ' tbody tr');
    const q = query.toLowerCase();
    rows.forEach(function (row) {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

<?php if (!empty($errors) && $editVendor !== null): ?>
document.addEventListener('DOMContentLoaded', function () {
    var modal = new bootstrap.Modal(document.getElementById('vendorModal'));
    <?php if (!empty($editVendor['id'])): ?>
    editVendor(<?= (int)$editVendor['id'] ?>, <?= json_encode($editVendor) ?>);
    <?php else: ?>
    resetVendorForm();
    document.getElementById('vendorCompanyName').value  = <?= json_encode($editVendor['company_name']) ?>;
    document.getElementById('vendorContactName').value  = <?= json_encode($editVendor['contact_name']) ?>;
    document.getElementById('vendorEmail').value        = <?= json_encode($editVendor['email']) ?>;
    document.getElementById('vendorPhone').value        = <?= json_encode($editVendor['phone']) ?>;
    document.getElementById('vendorBillingEmail').value  = <?= json_encode($editVendor['billing_email']) ?>;
    document.getElementById('vendorMediaCategory').value = <?= json_encode($editVendor['media_category']) ?>;
    document.getElementById('vendorAddress').value       = <?= json_encode($editVendor['address']) ?>;
    document.getElementById('vendorNotes').value        = <?= json_encode($editVendor['notes']) ?>;
    <?php endif; ?>
    modal.show();
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
