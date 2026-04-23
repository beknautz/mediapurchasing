<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';
requireRole(['admin', 'buyer']);

$proposalService = new ProposalService();

// Load for editing if ?id= provided
$proposalId = (int) ($_GET['id'] ?? 0);
$isEdit     = false;
$proposal   = [];
$items      = [];

if ($proposalId > 0) {
    $data = $proposalService->getProposal($proposalId);
    if ($data) {
        $isEdit   = true;
        $proposal = $data['proposal'];
        $items    = $data['items'];
    } else {
        flash('error', 'Proposal not found.');
        redirect('/proposals/index.php');
    }
}

$clients   = $proposalService->getClients();
$templates = $proposalService->getTemplatesWithItems();
$errors    = [];

// ── POST handler ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postId      = (int)   ($_POST['id']          ?? 0);
    $title       = trim(    $_POST['title']        ?? '');
    $clientId    = (int)   ($_POST['client_id']    ?? 0);
    $validUntil  = trim(    $_POST['valid_until']  ?? '');
    $introText   = trim(    $_POST['intro_text']   ?? '');
    $notes       = trim(    $_POST['notes']        ?? '');
    $formAction  = trim(    $_POST['form_action']  ?? 'draft');
    $rawItems    = (array) ($_POST['items']        ?? []);

    if ($title === '') $errors[] = 'Proposal title is required.';

    // Build and sort items
    $cleanItems = [];
    foreach ($rawItems as $item) {
        $desc = trim($item['description'] ?? '');
        if ($desc === '') continue;
        $cleanItems[] = [
            'description' => $desc,
            'quantity'    => max(0, (float) ($item['quantity']   ?? 1)),
            'unit_price'  => max(0, (float) ($item['unit_price'] ?? 0)),
            'sort_order'  => (int) ($item['sort_order'] ?? 0),
        ];
    }
    usort($cleanItems, fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);

    if (empty($errors)) {
        $total = array_sum(array_map(fn($i) => $i['quantity'] * $i['unit_price'], $cleanItems));

        // Determine status
        $status = 'draft';
        if ($formAction === 'sent') {
            $status = 'sent';
        } elseif ($isEdit || $postId > 0) {
            // Preserve existing status unless user changed it
            $posted = $_POST['status'] ?? '';
            $status = array_key_exists($posted, ProposalService::STATUS_LABELS) ? $posted : ($proposal['status'] ?? 'draft');
        }

        try {
            $savedId = $proposalService->saveProposal([
                'id'           => $postId,
                'title'        => $title,
                'client_id'    => $clientId,
                'status'       => $status,
                'intro_text'   => $introText,
                'notes'        => $notes,
                'valid_until'  => $validUntil,
                'total_amount' => round($total, 2),
            ]);
            $proposalService->saveItems($savedId, $cleanItems);

            flash('success', $postId > 0 ? 'Proposal updated.' : 'Proposal created.');
            redirect('/proposals/view.php?id=' . $savedId);
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }

    // On validation error: repopulate form
    if (!empty($errors)) {
        $proposal = [
            'id'          => $postId,
            'title'       => $title,
            'client_id'   => $clientId,
            'status'      => $_POST['status'] ?? 'draft',
            'intro_text'  => $introText,
            'notes'       => $notes,
            'valid_until' => $validUntil,
        ];
        $items = $cleanItems;
        if ($postId > 0) $isEdit = true;
        $proposalId = $postId;
    }
}

$pageTitle = ($isEdit ? 'Edit Proposal' : 'New Proposal') . ' — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">
            <i class="bi bi-file-earmark-richtext me-2 text-primary"></i>
            <?= $isEdit ? 'Edit Proposal' : 'New Proposal' ?>
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="/proposals/index.php">Proposals</a></li>
                <li class="breadcrumb-item active"><?= $isEdit ? h($proposal['title'] ?? 'Edit') : 'New' ?></li>
            </ol>
        </nav>
    </div>
    <a href="/proposals/templates.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-file-earmark-text me-1"></i>Manage Templates
    </a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Please fix the following:</strong>
    <ul class="mb-0 mt-1"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="POST" action="/proposals/create.php" id="proposalForm">
<input type="hidden" name="id" value="<?= (int)($proposal['id'] ?? 0) ?>">
<?php if ($isEdit): ?>
<input type="hidden" name="status" value="<?= h($proposal['status'] ?? 'draft') ?>">
<?php endif; ?>

<div class="row g-4">

    <!-- ── Left column ──────────────────────────────────────────────────────── -->
    <div class="col-lg-8">

        <!-- Template Loader -->
        <?php if (!empty($templates)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-file-earmark-text me-2 text-primary"></i>Load Template
                </h5>
            </div>
            <div class="card-body">
                <div class="d-flex gap-2 align-items-end">
                    <div class="flex-grow-1">
                        <label for="templatePicker" class="form-label small fw-semibold mb-1">
                            Choose a boilerplate to pre-fill intro text and line items
                        </label>
                        <select class="form-select" id="templatePicker">
                            <option value="">— Select a template —</option>
                            <?php foreach ($templates as $t): ?>
                            <option value="<?= (int)$t['id'] ?>"><?= h($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="btn btn-outline-primary" onclick="loadTemplate()">
                        <i class="bi bi-arrow-down-circle me-1"></i>Load
                    </button>
                </div>
                <div class="form-text">Loading replaces the current intro text and line items.</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Proposal Details -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-info-circle me-2 text-primary"></i>Proposal Details
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="title" class="form-label fw-semibold">
                            Proposal Title <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="title" name="title" required
                               value="<?= h($proposal['title'] ?? '') ?>"
                               placeholder="e.g. Q1 2026 Digital Media Campaign">
                    </div>
                    <div class="col-md-4">
                        <label for="valid_until" class="form-label fw-semibold">Valid Until</label>
                        <input type="date" class="form-control" id="valid_until" name="valid_until"
                               value="<?= h($proposal['valid_until'] ?? '') ?>">
                    </div>
                    <div class="col-md-12">
                        <label for="client_id" class="form-label fw-semibold">Client</label>
                        <select class="form-select" id="client_id" name="client_id">
                            <option value="">— No client —</option>
                            <?php foreach ($clients as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"
                                <?= (int)($proposal['client_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= h($c['company_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Intro Text -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-text-paragraph me-2 text-primary"></i>Introduction
                </h5>
            </div>
            <div class="card-body">
                <textarea class="form-control" id="intro_text" name="intro_text" rows="6"
                          placeholder="Opening statement, overview, or cover letter text for this proposal…"><?= h($proposal['intro_text'] ?? '') ?></textarea>
                <div class="form-text">This appears at the top of the proposal before the line items.</div>
            </div>
        </div>

        <!-- Line Items -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-list-ul me-2 text-primary"></i>Line Items
                </h5>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addItemRow()">
                    <i class="bi bi-plus-circle me-1"></i>Add Item
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0" id="itemsTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width:30px;"></th>
                                <th>Description</th>
                                <th style="width:90px;">Qty</th>
                                <th style="width:150px;">Unit Price</th>
                                <th style="width:120px;" class="text-end">Total</th>
                                <th style="width:40px;"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                        <?php foreach ($items as $i => $item): ?>
                        <tr class="line-item-row">
                            <td class="drag-handle text-muted ps-3" style="cursor:grab;vertical-align:middle;">
                                <i class="bi bi-grip-vertical"></i>
                            </td>
                            <td>
                                <input type="text" name="items[<?= $i ?>][description]"
                                       class="form-control form-control-sm"
                                       placeholder="Description" required
                                       value="<?= h($item['description']) ?>">
                                <input type="hidden" name="items[<?= $i ?>][sort_order]"
                                       class="sort-input" value="<?= $i ?>">
                            </td>
                            <td>
                                <input type="number" name="items[<?= $i ?>][quantity]"
                                       class="form-control form-control-sm item-qty"
                                       value="<?= h($item['quantity']) ?>" min="0" step="0.01">
                            </td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="items[<?= $i ?>][unit_price]"
                                           class="form-control item-unit"
                                           value="<?= h($item['unit_price']) ?>" min="0" step="0.01">
                                </div>
                            </td>
                            <td class="text-end fw-semibold" style="vertical-align:middle;">
                                <span class="item-total-disp">
                                    $<?= number_format((float)$item['quantity'] * (float)$item['unit_price'], 2) ?>
                                </span>
                            </td>
                            <td style="vertical-align:middle;">
                                <button type="button" class="btn btn-sm btn-link text-danger p-0"
                                        onclick="removeItem(this)" title="Remove">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <td colspan="4" class="text-end fw-bold pe-3">Grand Total</td>
                                <td class="text-end fw-bold" id="grandTotal">
                                    $<?= number_format(array_sum(array_map(fn($i) => (float)$i['quantity'] * (float)$i['unit_price'], $items)), 2) ?>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Notes -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-sticky me-2 text-primary"></i>Notes / Terms
                </h5>
            </div>
            <div class="card-body">
                <textarea class="form-control" id="notes" name="notes" rows="4"
                          placeholder="Payment terms, expiration notice, disclaimers…"><?= h($proposal['notes'] ?? '') ?></textarea>
                <div class="form-text">Appears at the bottom of the proposal.</div>
            </div>
        </div>

    </div>

    <!-- ── Right sidebar ────────────────────────────────────────────────────── -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm sticky-top" style="top:1rem;">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-floppy me-2 text-primary"></i>Save Proposal
                </h5>
            </div>
            <div class="card-body">
                <?php if ($isEdit): ?>
                <div class="mb-3">
                    <div class="text-muted small mb-1">Current status</div>
                    <?php $sc = ProposalService::STATUS_COLORS[$proposal['status']] ?? 'secondary'; ?>
                    <?php $sl = ProposalService::STATUS_LABELS[$proposal['status']] ?? $proposal['status']; ?>
                    <span class="badge bg-<?= $sc ?> fs-6"><?= h($sl) ?></span>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Change Status</label>
                    <select class="form-select form-select-sm" name="status" form="proposalForm">
                        <?php foreach (ProposalService::STATUS_LABELS as $sv => $sl): ?>
                        <option value="<?= h($sv) ?>"
                            <?= ($proposal['status'] ?? 'draft') === $sv ? 'selected' : '' ?>>
                            <?= h($sl) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="d-grid gap-2">
                    <button type="submit" name="form_action" value="draft" class="btn btn-outline-secondary">
                        <i class="bi bi-floppy me-2"></i>Save Draft
                    </button>
                    <?php if (!$isEdit || ($proposal['status'] ?? '') === 'draft'): ?>
                    <button type="submit" name="form_action" value="sent" class="btn btn-primary">
                        <i class="bi bi-send me-2"></i>Save &amp; Mark Sent
                    </button>
                    <?php else: ?>
                    <button type="submit" name="form_action" value="draft" class="btn btn-primary">
                        <i class="bi bi-check-circle me-2"></i>Save Changes
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-footer bg-white text-center">
                <a href="/proposals/index.php" class="text-muted small">
                    <i class="bi bi-x-circle me-1"></i>Cancel
                </a>
            </div>
        </div>
    </div>

</div>
</form>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
// ── Template data ──────────────────────────────────────────────────────────────
const TEMPLATES = <?= json_encode(array_values($templates)) ?>;
const TEMPLATE_MAP = {};
TEMPLATES.forEach(t => { TEMPLATE_MAP[t.id] = t; });

// ── Row counter (starts after PHP-rendered rows) ──────────────────────────────
let rowCounter = <?= max(count($items), 0) ?>;

// ── SortableJS ────────────────────────────────────────────────────────────────
const itemsSortable = Sortable.create(document.getElementById('itemsBody'), {
    handle:     '.drag-handle',
    animation:  150,
    ghostClass: 'table-active',
    onEnd:      updateSortOrders,
});

// ── Init existing rows ────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    if (document.querySelectorAll('#itemsBody .line-item-row').length === 0) {
        addItemRow();
    }
    document.querySelectorAll('#itemsBody .line-item-row').forEach(bindRow);
    updateGrandTotal();
});

// ── Row management ────────────────────────────────────────────────────────────
function addItemRow(description, quantity, unitPrice) {
    const idx = rowCounter++;
    const tr  = document.createElement('tr');
    tr.className = 'line-item-row';
    tr.innerHTML = `
        <td class="drag-handle text-muted ps-3" style="cursor:grab;vertical-align:middle;">
            <i class="bi bi-grip-vertical"></i>
        </td>
        <td>
            <input type="text" name="items[${idx}][description]"
                   class="form-control form-control-sm" placeholder="Description" required>
            <input type="hidden" name="items[${idx}][sort_order]" class="sort-input" value="${idx}">
        </td>
        <td>
            <input type="number" name="items[${idx}][quantity]"
                   class="form-control form-control-sm item-qty" value="1" min="0" step="0.01">
        </td>
        <td>
            <div class="input-group input-group-sm">
                <span class="input-group-text">$</span>
                <input type="number" name="items[${idx}][unit_price]"
                       class="form-control item-unit" value="0.00" min="0" step="0.01">
            </div>
        </td>
        <td class="text-end fw-semibold" style="vertical-align:middle;">
            <span class="item-total-disp">$0.00</span>
        </td>
        <td style="vertical-align:middle;">
            <button type="button" class="btn btn-sm btn-link text-danger p-0"
                    onclick="removeItem(this)" title="Remove">
                <i class="bi bi-trash"></i>
            </button>
        </td>`;

    // Set values programmatically (avoids XSS in innerHTML)
    tr.querySelector('[name$="[description]"]').value  = description  || '';
    tr.querySelector('[name$="[quantity]"]').value     = parseFloat(quantity)  || 1;
    tr.querySelector('[name$="[unit_price]"]').value   = parseFloat(unitPrice) || 0;

    document.getElementById('itemsBody').appendChild(tr);
    bindRow(tr);
    calcRow(tr);
}

function bindRow(tr) {
    tr.querySelector('.item-qty').addEventListener('input',  () => calcRow(tr));
    tr.querySelector('.item-unit').addEventListener('input', () => calcRow(tr));
}

function removeItem(btn) {
    const rows = document.querySelectorAll('#itemsBody .line-item-row');
    if (rows.length <= 1) {
        // Clear instead of removing the last row
        const tr = btn.closest('tr');
        tr.querySelector('[name$="[description]"]').value = '';
        tr.querySelector('.item-qty').value  = 1;
        tr.querySelector('.item-unit').value = 0;
        calcRow(tr);
        return;
    }
    btn.closest('tr').remove();
    updateGrandTotal();
}

function calcRow(tr) {
    const qty   = parseFloat(tr.querySelector('.item-qty').value)  || 0;
    const price = parseFloat(tr.querySelector('.item-unit').value) || 0;
    tr.querySelector('.item-total-disp').textContent =
        '$' + (qty * price).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    updateGrandTotal();
}

function updateGrandTotal() {
    let grand = 0;
    document.querySelectorAll('#itemsBody .line-item-row').forEach(tr => {
        grand += (parseFloat(tr.querySelector('.item-qty').value)  || 0)
               * (parseFloat(tr.querySelector('.item-unit').value) || 0);
    });
    document.getElementById('grandTotal').textContent =
        '$' + grand.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function updateSortOrders() {
    document.querySelectorAll('#itemsBody .line-item-row').forEach((tr, i) => {
        const inp = tr.querySelector('.sort-input');
        if (inp) inp.value = i;
    });
}

// Update sort orders before form submits
document.getElementById('proposalForm').addEventListener('submit', updateSortOrders);

// ── Template loader ───────────────────────────────────────────────────────────
function loadTemplate() {
    const id = parseInt(document.getElementById('templatePicker').value, 10);
    if (!id || !TEMPLATE_MAP[id]) {
        alert('Please select a template first.');
        return;
    }
    const t = TEMPLATE_MAP[id];

    const hasContent = document.getElementById('intro_text').value.trim() ||
                       document.querySelectorAll('#itemsBody .line-item-row').length > 1 ||
                       (document.querySelectorAll('#itemsBody .line-item-row').length === 1 &&
                        document.querySelector('#itemsBody [name$="[description]"]')?.value.trim());

    if (hasContent && !confirm('Loading "' + t.name + '" will replace the current intro text and line items. Continue?')) {
        return;
    }

    // Fill intro text
    document.getElementById('intro_text').value = t.intro_text || '';

    // Fill notes if template has them and notes is empty
    if (t.notes && !document.getElementById('notes').value.trim()) {
        document.getElementById('notes').value = t.notes;
    }

    // Replace line items
    document.getElementById('itemsBody').innerHTML = '';
    rowCounter = 0;
    if (t.items && t.items.length) {
        t.items.forEach(item => addItemRow(item.description, item.quantity, item.unit_price));
    } else {
        addItemRow();
    }
    updateGrandTotal();

    // Visual confirmation
    const btn = document.querySelector('button[onclick="loadTemplate()"]');
    if (btn) {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>Loaded';
        btn.classList.replace('btn-outline-primary', 'btn-success');
        setTimeout(() => { btn.innerHTML = orig; btn.classList.replace('btn-success', 'btn-outline-primary'); }, 2000);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
