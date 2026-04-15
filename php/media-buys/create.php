<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';

requireRole(['admin', 'buyer']);

$role    = $_SESSION['role'] ?? '';
$userId  = (int) ($_SESSION['user']['id'] ?? 0);
$isAdmin = $role === 'admin';

$mediaBuyService = new MediaBuyService();
$db = (new BaseService())->getDb ?? null;

// Fetch clients and vendors for selects
// We query directly via PDO from BaseService pattern — use a lightweight helper
$pdo = (function () {
    if (!defined('DB_HOST')) {
        return null;
    }
    try {
        return new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET),
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (PDOException $e) {
        return null;
    }
})();

$clients = [];
$vendors = [];
$buyers  = [];

if ($pdo) {
    $clients = $pdo->query("SELECT id, company_name FROM clients WHERE active = 1 ORDER BY company_name")->fetchAll();
    $vendors = $pdo->query("SELECT id, company_name FROM vendors WHERE active = 1 ORDER BY company_name")->fetchAll();
    if ($isAdmin) {
        $buyers = $pdo->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM users WHERE role IN ('admin','buyer') AND active = 1 ORDER BY first_name")->fetchAll();
    }
}

$errors = [];
$formData = [
    'title'          => '',
    'client_id'      => '',
    'vendor_id'      => '',
    'media_type'     => '',
    'flight_start'   => '',
    'flight_end'     => '',
    'market'         => '',
    'buyer_id'       => $isAdmin ? '' : $userId,
    'description'    => '',
    'internal_notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['title']          = trim($_POST['title'] ?? '');
    $formData['client_id']      = (int) ($_POST['client_id'] ?? 0);
    $formData['vendor_id']      = (int) ($_POST['vendor_id'] ?? 0);
    $formData['media_type']     = trim($_POST['media_type'] ?? '');
    $formData['flight_start']   = trim($_POST['flight_start'] ?? '');
    $formData['flight_end']     = trim($_POST['flight_end'] ?? '');
    $formData['market']         = trim($_POST['market'] ?? '');
    $formData['buyer_id']       = $isAdmin ? (int) ($_POST['buyer_id'] ?? 0) : $userId;
    $formData['description']    = trim($_POST['description'] ?? '');
    $formData['internal_notes'] = trim($_POST['internal_notes'] ?? '');

    // Parse line items from POST arrays
    $rawDescriptions = $_POST['item_description'] ?? [];
    $rawPlacements   = $_POST['item_placement']   ?? [];
    $rawSpots        = $_POST['item_spots']        ?? [];
    $rawUnitCosts    = $_POST['item_unit_cost']    ?? [];
    $rawTotalCosts   = $_POST['item_total_cost']   ?? [];

    $items = [];
    foreach ($rawDescriptions as $idx => $desc) {
        if (trim($desc) === '' && empty($rawPlacements[$idx])) {
            continue;
        }
        $spots     = (float) ($rawSpots[$idx]     ?? 1);
        $unitCost  = (float) ($rawUnitCosts[$idx] ?? 0);
        $totalCost = (float) ($rawTotalCosts[$idx] ?? ($spots * $unitCost));
        $items[] = [
            'description' => trim($desc),
            'placement'   => trim($rawPlacements[$idx] ?? ''),
            'quantity'    => $spots,
            'unit_cost'   => $unitCost,
            'total_cost'  => $totalCost,
            'media_type'  => $formData['media_type'],
            'start_date'  => $formData['flight_start'] ?: null,
            'end_date'    => $formData['flight_end']   ?: null,
            'notes'       => '',
        ];
    }

    if ($formData['title'] === '') {
        $errors[] = 'Campaign title is required.';
    }
    if (!$formData['client_id']) {
        $errors[] = 'Please select a client.';
    }

    if (empty($errors)) {
        $saveResult = $mediaBuyService->saveMediaBuy([
            'id'             => 0,
            'title'          => $formData['title'],
            'client_id'      => $formData['client_id'],
            'vendor_id'      => $formData['vendor_id'],
            'buyer_id'       => $formData['buyer_id'] ?: $userId,
            'status'         => isset($_POST['save_draft']) ? 'draft' : 'draft',
            'notes'          => $formData['description'],
            'internal_notes' => $formData['internal_notes'],
            'media_type'     => $formData['media_type'],
            'flight_start'   => $formData['flight_start'] ?: null,
            'flight_end'     => $formData['flight_end']   ?: null,
            'market'         => $formData['market'],
            'items'          => $items,
        ]);

        if ($saveResult['success']) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Media buy created successfully.'];
            redirect('/media-buys/view.php?id=' . $saveResult['id']);
        } else {
            $errors[] = $saveResult['message'];
        }
    }
}

$mediaTypes = ['TV', 'Radio', 'Print', 'Digital', 'OOH', 'Streaming', 'Podcast', 'Social'];

$pageTitle = 'New Media Buy';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">
            <i class="bi bi-plus-circle me-2 text-primary"></i>New Media Buy
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="/media-buys/index.php">Media Buys</a></li>
                <li class="breadcrumb-item active">Create</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <strong>Please fix the following:</strong>
    <ul class="mb-0 mt-1">
        <?php foreach ($errors as $err): ?>
            <li><?= h($err) ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="POST" action="" id="mediaBuyForm">
<div class="row g-4">

    <!-- Main form -->
    <div class="col-lg-8">

        <!-- Basic Details Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-info-circle me-2 text-primary"></i>Campaign Details</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="title" class="form-label fw-semibold">Campaign Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" name="title"
                           value="<?= h($formData['title']) ?>" required autofocus
                           placeholder="e.g. Q3 Brand Awareness — WKRP Radio">
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="client_id" class="form-label fw-semibold">Client <span class="text-danger">*</span></label>
                        <select class="form-select" id="client_id" name="client_id" required>
                            <option value="">— Select Client —</option>
                            <?php foreach ($clients as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= (int)$formData['client_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                                    <?= h($c['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="vendor_id" class="form-label fw-semibold">Vendor / Station</label>
                        <select class="form-select" id="vendor_id" name="vendor_id">
                            <option value="">— Select Vendor —</option>
                            <?php foreach ($vendors as $v): ?>
                                <option value="<?= (int)$v['id'] ?>" <?= (int)$formData['vendor_id'] === (int)$v['id'] ? 'selected' : '' ?>>
                                    <?= h($v['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-4">
                        <label for="media_type" class="form-label fw-semibold">Media Type</label>
                        <select class="form-select" id="media_type" name="media_type">
                            <option value="">— Select —</option>
                            <?php foreach ($mediaTypes as $mt): ?>
                                <option value="<?= h($mt) ?>" <?= $formData['media_type'] === $mt ? 'selected' : '' ?>>
                                    <?= h($mt) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="flight_start" class="form-label fw-semibold">Flight Start</label>
                        <input type="date" class="form-control" id="flight_start" name="flight_start"
                               value="<?= h($formData['flight_start']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="flight_end" class="form-label fw-semibold">Flight End</label>
                        <input type="date" class="form-control" id="flight_end" name="flight_end"
                               value="<?= h($formData['flight_end']) ?>">
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <label for="market" class="form-label fw-semibold">Market / DMA</label>
                        <input type="text" class="form-control" id="market" name="market"
                               value="<?= h($formData['market']) ?>" placeholder="e.g. Chicago, IL">
                    </div>
                    <?php if ($isAdmin): ?>
                    <div class="col-md-6">
                        <label for="buyer_id" class="form-label fw-semibold">Assigned Buyer</label>
                        <select class="form-select" id="buyer_id" name="buyer_id">
                            <option value="">— Select Buyer —</option>
                            <?php foreach ($buyers as $b): ?>
                                <option value="<?= (int)$b['id'] ?>" <?= (int)$formData['buyer_id'] === (int)$b['id'] ? 'selected' : '' ?>>
                                    <?= h($b['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="mt-3">
                    <label for="description" class="form-label fw-semibold">Description / Notes</label>
                    <textarea class="form-control" id="description" name="description" rows="3"
                              placeholder="Campaign overview, goals, special instructions…"><?= h($formData['description']) ?></textarea>
                </div>

                <div class="mt-3">
                    <label for="internal_notes" class="form-label fw-semibold">Internal Notes</label>
                    <textarea class="form-control" id="internal_notes" name="internal_notes" rows="2"
                              placeholder="Internal team notes (not visible to client or vendor)…"><?= h($formData['internal_notes']) ?></textarea>
                    <div class="form-text"><i class="bi bi-eye-slash me-1"></i>Not shared externally.</div>
                </div>
            </div>
        </div>

        <!-- Line Items Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-list-ul me-2 text-primary"></i>Line Items</h5>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addLineItem">
                    <i class="bi bi-plus-lg me-1"></i>Add Row
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0" id="lineItemsTable">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="min-width:200px;">Description</th>
                                <th style="min-width:160px;">Placement</th>
                                <th style="width:90px;">Spots/Qty</th>
                                <th style="width:120px;">Unit Cost</th>
                                <th style="width:130px;">Total</th>
                                <th style="width:50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="lineItemsBody">
                            <!-- Default first row -->
                            <tr class="line-item-row">
                                <td class="ps-3">
                                    <input type="text" class="form-control form-control-sm"
                                           name="item_description[]" placeholder="Description">
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm"
                                           name="item_placement[]" placeholder="Placement / daypart">
                                </td>
                                <td>
                                    <input type="number" class="form-control form-control-sm item-spots"
                                           name="item_spots[]" value="1" min="0" step="1">
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control item-unit-cost"
                                               name="item_unit_cost[]" value="0.00" min="0" step="0.01">
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control item-total-cost"
                                               name="item_total_cost[]" value="0.00" min="0" step="0.01" readonly>
                                    </div>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-line-item">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <td colspan="4" class="text-end fw-bold pe-3 ps-3">Grand Total</td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="text" class="form-control fw-bold bg-white"
                                               id="grandTotal" value="0.00" readonly>
                                    </div>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

    </div><!-- /col-lg-8 -->

    <!-- Sticky sidebar -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm sticky-top" style="top:1rem;">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-floppy me-2 text-primary"></i>Save</h5>
            </div>
            <div class="card-body d-grid gap-2">
                <button type="submit" name="save_draft" class="btn btn-secondary btn-lg">
                    <i class="bi bi-file-earmark me-2"></i>Save as Draft
                </button>
                <p class="small text-muted text-center mb-0">
                    You can continue editing and send to vendor later.
                </p>
            </div>
            <div class="card-footer bg-white text-center">
                <a href="/media-buys/index.php" class="text-muted small">
                    <i class="bi bi-x-circle me-1"></i>Cancel
                </a>
            </div>
        </div>
    </div>

</div>
</form>

<script>
(function () {

    function calcRow(row) {
        const spots    = parseFloat(row.querySelector('.item-spots').value)     || 0;
        const unitCost = parseFloat(row.querySelector('.item-unit-cost').value) || 0;
        const total    = spots * unitCost;
        row.querySelector('.item-total-cost').value = total.toFixed(2);
    }

    function calcGrandTotal() {
        let grand = 0;
        document.querySelectorAll('#lineItemsBody .line-item-row').forEach(function (row) {
            grand += parseFloat(row.querySelector('.item-total-cost').value) || 0;
        });
        document.getElementById('grandTotal').value = grand.toFixed(2);
    }

    function attachRowListeners(row) {
        row.querySelector('.item-spots').addEventListener('input', function () {
            calcRow(row);
            calcGrandTotal();
        });
        row.querySelector('.item-unit-cost').addEventListener('input', function () {
            calcRow(row);
            calcGrandTotal();
        });
        row.querySelector('.remove-line-item').addEventListener('click', function () {
            if (document.querySelectorAll('#lineItemsBody .line-item-row').length > 1) {
                row.remove();
                calcGrandTotal();
            }
        });
    }

    // Attach listeners to existing rows
    document.querySelectorAll('#lineItemsBody .line-item-row').forEach(attachRowListeners);

    // Add new row
    document.getElementById('addLineItem').addEventListener('click', function () {
        const tbody    = document.getElementById('lineItemsBody');
        const template = tbody.querySelector('.line-item-row').cloneNode(true);

        // Clear cloned values
        template.querySelectorAll('input').forEach(function (inp) {
            if (inp.classList.contains('item-spots')) {
                inp.value = '1';
            } else if (inp.type === 'number') {
                inp.value = '0.00';
            } else {
                inp.value = '';
            }
        });

        tbody.appendChild(template);
        attachRowListeners(template);
    });

    calcGrandTotal();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
