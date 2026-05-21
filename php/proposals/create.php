<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';
requireRole(['admin', 'buyer']);

$proposalService = new ProposalService();

// Load existing proposal for editing
$proposalId = (int) ($_GET['id'] ?? 0);
$isEdit     = false;
$proposal   = [];
$blocks     = [];

if ($proposalId > 0) {
    $data = $proposalService->getProposal($proposalId);
    if ($data) {
        $isEdit   = true;
        $proposal = $data['proposal'];
        $blocks   = $data['blocks'];
    } else {
        flash('error', 'Proposal not found.');
        redirect('/proposals/index.php');
    }
}

$clients   = $proposalService->getClients();
$templates = $proposalService->getTemplatesWithBlocks();
$agSvc     = new AgencyAgreementService();
$branding  = $agSvc->getBranding();

// Convert stored logo URL to base64 data URI — /uploads/ may not be web-accessible on this server
$logoDataUri = function(?string $url): string {
    if (empty($url)) return '';
    $root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? realpath(__DIR__ . '/..'), '/\\');
    $path = $root . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $url), DIRECTORY_SEPARATOR);
    if (!file_exists($path) || !is_readable($path)) return '';
    $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = match($ext) { 'jpg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp','svg'=>'image/svg+xml',default=>'image/png' };
    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
};
$agencyLogoSrc = $logoDataUri($branding['logo_url'] ?? '');
$clientLogoSrc = $logoDataUri($proposal['client_logo_url'] ?? '');
$errors    = [];

// ── POST handler ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postId     = (int)   ($_POST['id']          ?? 0);
    $title      = trim(    $_POST['title']        ?? '');
    $clientId   = (int)   ($_POST['client_id']    ?? 0);
    $validUntil = trim(    $_POST['valid_until']  ?? '');
    $formAction = trim(    $_POST['form_action']  ?? 'draft');
    $rawBlocks  = (array) ($_POST['blocks']       ?? []);

    if ($title === '') $errors[] = 'Proposal title is required.';

    // Build and sort blocks
    $cleanBlocks = [];
    foreach ($rawBlocks as $b) {
        $type = $b['type'] ?? 'text';
        if ($type === 'text') {
            $content = $b['content'] ?? '';
            // Skip truly empty text blocks
            if (trim(strip_tags($content)) === '' && $content === '') continue;
            $cleanBlocks[] = ['block_type' => 'text', 'content' => $content,
                              'sort_order' => (int)($b['sort_order'] ?? 0)];
        } elseif ($type === 'item') {
            $desc = trim($b['description'] ?? '');
            if ($desc === '') continue;
            $qty  = max(0, (float)($b['quantity']   ?? 1));
            $unit = max(0, (float)($b['unit_price']  ?? 0));
            $cleanBlocks[] = ['block_type' => 'item', 'description' => $desc,
                              'quantity' => $qty, 'unit_price' => $unit,
                              'sort_order' => (int)($b['sort_order'] ?? 0)];
        } elseif ($type === 'signature') {
            $cleanBlocks[] = ['block_type' => 'signature',
                              'sig_label'  => trim($b['sig_label'] ?? ''),
                              'sort_order' => (int)($b['sort_order'] ?? 0)];
        }
    }
    usort($cleanBlocks, fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);

    $total = 0;
    foreach ($cleanBlocks as $b) {
        if ($b['block_type'] === 'item') {
            $total += $b['quantity'] * $b['unit_price'];
        }
    }

    // Determine status
    $status = 'draft';
    if ($formAction === 'sent') {
        $status = 'sent';
    } elseif ($postId > 0) {
        $posted = $_POST['status'] ?? '';
        $status = array_key_exists($posted, ProposalService::STATUS_LABELS)
                ? $posted : ($proposal['status'] ?? 'draft');
    }

    if (empty($errors)) {
        try {
            $savedId = $proposalService->saveProposal([
                'id'           => $postId,
                'title'        => $title,
                'client_id'    => $clientId,
                'status'       => $status,
                'valid_until'  => $validUntil,
                'total_amount' => round($total, 2),
            ]);
            $proposalService->saveBlocks($savedId, $cleanBlocks);

            flash('success', $postId > 0 ? 'Proposal updated.' : 'Proposal created.');
            redirect('/proposals/view.php?id=' . $savedId);
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }

    // Validation error — repopulate
    if (!empty($errors)) {
        $proposal = [
            'id' => $postId, 'title' => $title, 'client_id' => $clientId,
            'status' => $_POST['status'] ?? 'draft', 'valid_until' => $validUntil,
        ];
        $blocks   = $cleanBlocks;
        $isEdit   = $postId > 0;
        $proposalId = $postId;
    }
}

// Pre-calculate total for display
$displayTotal = 0;
foreach ($blocks as $b) {
    $type = $b['block_type'] ?? $b['type'] ?? '';
    if ($type === 'item') {
        $displayTotal += (float)($b['quantity'] ?? 1) * (float)($b['unit_price'] ?? 0);
    }
}

// Serialize existing blocks for JS rendering
$blockJson = json_encode(array_values($blocks));

$extraHead = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-bs5.min.css">';
$pageTitle  = ($isEdit ? 'Edit Proposal' : 'New Proposal') . ' — MediaBuy';
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
                <li class="breadcrumb-item active">
                    <?= $isEdit ? h($proposal['title'] ?? 'Edit') : 'New' ?>
                </li>
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

<!-- ── Branding card — OUTSIDE the form to avoid nested-form issues ─────────── -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold">
            <i class="bi bi-image me-2 text-primary"></i>Branding
        </h5>
    </div>
    <div class="card-body">
        <div class="row g-4">

            <!-- Agency Logo -->
            <div class="col-md-6">
                <div class="fw-semibold small mb-2">Agency Logo</div>
                <div id="agency-logo-preview" class="mb-2">
                    <?php if ($agencyLogoSrc): ?>
                    <img src="<?= $agencyLogoSrc ?>" alt="Agency Logo"
                         style="max-height:60px;max-width:180px;object-fit:contain;border:1px solid #dee2e6;border-radius:4px;padding:4px;display:block;">
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <input type="file" id="agencyLogoFile" accept="image/*"
                           class="form-control form-control-sm" style="max-width:220px;">
                    <button type="button" class="btn btn-sm btn-outline-primary"
                            onclick="uploadAgencyLogo()">
                        <i class="bi bi-upload me-1"></i>Upload
                    </button>
                </div>
            </div>

            <!-- Client Logo -->
            <div class="col-md-6">
                <div class="fw-semibold small mb-2">Client Logo</div>
                <div id="client-logo-preview" class="mb-2">
                    <?php if ($clientLogoSrc): ?>
                    <img src="<?= $clientLogoSrc ?>" alt="Client Logo"
                         style="max-height:60px;max-width:180px;object-fit:contain;border:1px solid #dee2e6;border-radius:4px;padding:4px;display:block;">
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <input type="file" id="clientLogoFile" accept="image/*"
                           class="form-control form-control-sm" style="max-width:220px;">
                    <button type="button" class="btn btn-sm btn-outline-primary"
                            onclick="uploadClientLogo()">
                        <i class="bi bi-upload me-1"></i>Upload
                    </button>
                </div>
                <div class="form-text text-muted mt-1">
                    <i class="bi bi-info-circle me-1"></i>Select a client above first.
                </div>
            </div>

        </div>
    </div>
</div>

<form method="POST" action="/proposals/create.php" id="proposalForm">
<input type="hidden" name="id" value="<?= (int)($proposal['id'] ?? 0) ?>">
<?php if ($isEdit): ?>
<input type="hidden" name="status" id="statusField" value="<?= h($proposal['status'] ?? 'draft') ?>">
<?php endif; ?>

<div class="row g-4">

    <!-- ── Left / main ──────────────────────────────────────────────────────── -->
    <div class="col-lg-8">

        <!-- Proposal meta -->
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
                            Title <span class="text-danger">*</span>
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
                    <div class="col-12">
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

        <!-- Template loader -->
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
                            Choose a template to pre-fill all blocks
                        </label>
                        <select class="form-select" id="templatePicker">
                            <option value="">— Select a template —</option>
                            <?php foreach ($templates as $t): ?>
                            <option value="<?= (int)$t['id'] ?>"><?= h($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="btn btn-outline-primary" id="loadTemplateBtn"
                            onclick="loadTemplate()">
                        <i class="bi bi-arrow-down-circle me-1"></i>Load
                    </button>
                </div>
                <div class="form-text">Loading a template replaces all current blocks.</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Blocks -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-layout-text-sidebar me-2 text-primary"></i>Content Blocks
                    <span class="text-muted fw-normal small ms-2">— drag to reorder</span>
                </h5>
            </div>
            <div class="card-body pb-2">

                <!-- Sortable blocks container — populated by JS on load -->
                <div id="blocksContainer"></div>

                <!-- Add block toolbar -->
                <div class="d-flex gap-2 pt-1 pb-1 border-top mt-2">
                    <button type="button" class="btn btn-sm btn-outline-info" onclick="addTextBlock()">
                        <i class="bi bi-text-paragraph me-1"></i>Add Text
                    </button>
                    <button id="addPriceLineBtn" type="button" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-currency-dollar me-1"></i>Add Price Line
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="addSignature()">
                        <i class="bi bi-pen me-1"></i>Add Signature
                    </button>
                </div>

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

                <!-- Grand total -->
                <div class="mb-3 p-3 bg-light rounded d-flex justify-content-between align-items-center">
                    <span class="text-muted small fw-semibold">Grand Total</span>
                    <span class="fs-5 fw-bold" id="grandTotal">
                        $<?= number_format($displayTotal, 2) ?>
                    </span>
                </div>

                <?php if ($isEdit): ?>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Status</label>
                    <select class="form-select form-select-sm" id="statusSelect"
                            onchange="document.getElementById('statusField').value = this.value">
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
                    <button type="submit" name="form_action" value="draft"
                            class="btn btn-outline-secondary">
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

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-bs5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
// ── Summernote config ─────────────────────────────────────────────────────────
const SNOTE_OPTS = {
    height: 220,
    dialogsInBody: true,
    toolbar: [
        ['style',  ['bold','italic','underline','strikethrough','clear']],
        ['font',   ['fontsize']],
        ['color',  ['color']],
        ['para',   ['ul','ol','paragraph']],
        ['table',  ['table']],
        ['insert', ['link','hr']],
        ['view',   ['fullscreen','codeview']],
    ],
    placeholder: 'Write your text here…',
};

// ── Template data (keyed by id) ───────────────────────────────────────────────
const TEMPLATE_MAP = <?= json_encode(array_values($templates)) ?>.reduce((m, t) => { m[t.id] = t; return m; }, {});

// ── Existing blocks from server (JS renders these, same path as template load) ─
const EXISTING_BLOCKS = <?= $blockJson ?: '[]' ?>;

// ── Block counter ─────────────────────────────────────────────────────────────
let blockCounter = 0;

// ── SortableJS ────────────────────────────────────────────────────────────────
const _blocksEl = document.getElementById('blocksContainer');
const sortable = (typeof Sortable !== 'undefined' && _blocksEl)
    ? Sortable.create(_blocksEl, {
        handle:     '.block-handle',
        animation:  150,
        ghostClass: 'border-primary',
        onStart: syncEditorsToTextareas,
        onEnd:   updateSortOrders,
      })
    : null;

// ── Render blocks on load ─────────────────────────────────────────────────────
$(function () {
    if (EXISTING_BLOCKS.length > 0) {
        EXISTING_BLOCKS.forEach(function (b) {
            if      (b.block_type === 'text')      addTextBlock(b.content || '');
            else if (b.block_type === 'item')      addPriceLine(b.description, b.quantity, b.unit_price);
            else if (b.block_type === 'signature') addSignature(b.sig_label);
        });
    } else {
        addTextBlock(); // new proposal — start with one empty text block
    }
    updateGrandTotal();
});

// ── Sync all open editors to their hidden textareas ───────────────────────────
function syncEditorsToTextareas() {
    document.querySelectorAll('#blocksContainer .summernote-editor').forEach(function (ta) {
        if ($(ta).data('summernote')) {
            ta.value = $(ta).summernote('code');
        }
    });
}

// ── Sort orders ───────────────────────────────────────────────────────────────
function updateSortOrders() {
    document.querySelectorAll('#blocksContainer .block-row').forEach(function (el, i) {
        const inp = el.querySelector('.sort-input');
        if (inp) inp.value = i;
    });
}

// ── Grand total (sum of all item blocks) ──────────────────────────────────────
function updateGrandTotal() {
    var grand = 0;
    document.querySelectorAll('#blocksContainer .block-row[data-type="item"]').forEach(function (el) {
        // support both old .item-qty/.item-unit and new .pl-qty/.pl-unit
        var qty  = parseFloat((el.querySelector('.pl-qty')  || el.querySelector('.item-qty')  || {}).value)  || 0;
        var unit = parseFloat((el.querySelector('.pl-unit') || el.querySelector('.item-unit') || {}).value) || 0;
        grand += qty * unit;
    });
    var fmt = '$' + grand.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    var el = document.getElementById('grandTotal');
    if (el) el.textContent = fmt;
}
function recalcGrandTotal() { updateGrandTotal(); }

// ── Calc one item block ───────────────────────────────────────────────────────
function calcItem(block) {
    const qty   = parseFloat(block.querySelector('.item-qty')?.value)  || 0;
    const price = parseFloat(block.querySelector('.item-unit')?.value) || 0;
    const total = qty * price;
    const fmt   = '$' + total.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
    const disp  = block.querySelector('.item-total-disp');
    const hdr   = block.querySelector('.item-header-total');
    if (disp) disp.textContent = fmt;
    if (hdr)  hdr.textContent  = fmt;
    updateGrandTotal();
}

function bindItemEvents(block) {
    block.querySelector('.item-qty')?.addEventListener('input',  () => calcItem(block));
    block.querySelector('.item-unit')?.addEventListener('input', () => calcItem(block));
}

// ── Add TEXT block ────────────────────────────────────────────────────────────
function addTextBlock(content) {
    const idx = blockCounter++;
    const div = document.createElement('div');
    div.className = 'block-row card border mb-3';
    div.dataset.type = 'text';
    div.innerHTML = `
        <div class="block-handle card-header py-2 d-flex align-items-center gap-2"
             style="cursor:grab;user-select:none;">
            <i class="bi bi-grip-vertical text-muted fs-5"></i>
            <span class="badge bg-info-subtle text-info border border-info-subtle">
                <i class="bi bi-text-paragraph me-1"></i>Text Block
            </span>
            <button type="button" class="btn btn-sm btn-link text-danger ms-auto p-0"
                    onclick="removeBlock(this)" title="Remove block">
                <i class="bi bi-trash"></i>
            </button>
        </div>
        <div class="card-body p-0">
            <input type="hidden" name="blocks[${idx}][type]" value="text">
            <input type="hidden" name="blocks[${idx}][sort_order]" class="sort-input" value="${idx}">
            <textarea name="blocks[${idx}][content]" class="summernote-editor"></textarea>
        </div>`;

    document.getElementById('blocksContainer').appendChild(div);

    const ta = div.querySelector('.summernote-editor');
    $(ta).summernote(SNOTE_OPTS);
    if (content) $(ta).summernote('code', content);
    updateSortOrders();
}

// ── Add PRICE LINE block ──────────────────────────────────────────────────────
function addPriceLine(description, quantity, unitPrice) {
    var idx = blockCounter++;
    var container = document.getElementById('blocksContainer');

    var row = document.createElement('div');
    row.className   = 'block-row card border mb-3';
    row.dataset.type = 'item';

    // Header
    var hdr = document.createElement('div');
    hdr.className = 'block-handle card-header py-2 d-flex align-items-center gap-2';
    hdr.style.cssText = 'cursor:grab;user-select:none;';
    hdr.innerHTML = '<i class="bi bi-grip-vertical text-muted fs-5"></i>'
        + '<span class="fw-semibold small text-primary">Price Line</span>'
        + '<span class="ms-auto fw-bold small pl-line-total text-dark">$0.00</span>';

    var delBtn = document.createElement('button');
    delBtn.type      = 'button';
    delBtn.className = 'btn btn-sm btn-outline-danger ms-2';
    delBtn.textContent = 'Remove';
    delBtn.addEventListener('click', function () {
        container.removeChild(row);
        recalcGrandTotal();
    });
    hdr.appendChild(delBtn);
    row.appendChild(hdr);

    // Body
    var body = document.createElement('div');
    body.className = 'card-body';

    // Hidden type + sort
    var typeInput = document.createElement('input');
    typeInput.type  = 'hidden';
    typeInput.name  = 'blocks[' + idx + '][type]';
    typeInput.value = 'item';
    body.appendChild(typeInput);

    var sortInput = document.createElement('input');
    sortInput.type      = 'hidden';
    sortInput.name      = 'blocks[' + idx + '][sort_order]';
    sortInput.className = 'sort-input';
    sortInput.value     = idx;
    body.appendChild(sortInput);

    // Description
    var descWrap = document.createElement('div');
    descWrap.className = 'mb-2';
    var descLabel = document.createElement('label');
    descLabel.className   = 'form-label small fw-semibold mb-1';
    descLabel.textContent = 'Description';
    var descInput = document.createElement('input');
    descInput.type        = 'text';
    descInput.name        = 'blocks[' + idx + '][description]';
    descInput.className   = 'form-control';
    descInput.placeholder = 'Service or item description';
    descInput.value       = description || '';
    descWrap.appendChild(descLabel);
    descWrap.appendChild(descInput);
    body.appendChild(descWrap);

    // Qty / Unit / Total row
    var numRow = document.createElement('div');
    numRow.className = 'row g-2 align-items-end';

    // Qty
    var qtyCol = document.createElement('div');
    qtyCol.className = 'col-md-3';
    var qtyLabel = document.createElement('label');
    qtyLabel.className   = 'form-label small fw-semibold mb-1';
    qtyLabel.textContent = 'Qty';
    var qtyInput = document.createElement('input');
    qtyInput.type      = 'number';
    qtyInput.name      = 'blocks[' + idx + '][quantity]';
    qtyInput.className = 'form-control pl-qty';
    qtyInput.value     = parseFloat(quantity) > 0 ? parseFloat(quantity) : 1;
    qtyInput.min       = '0';
    qtyInput.step      = '0.01';
    qtyCol.appendChild(qtyLabel);
    qtyCol.appendChild(qtyInput);
    numRow.appendChild(qtyCol);

    // Unit price
    var unitCol = document.createElement('div');
    unitCol.className = 'col-md-4';
    var unitLabel = document.createElement('label');
    unitLabel.className   = 'form-label small fw-semibold mb-1';
    unitLabel.textContent = 'Unit Price ($)';
    var unitInput = document.createElement('input');
    unitInput.type      = 'number';
    unitInput.name      = 'blocks[' + idx + '][unit_price]';
    unitInput.className = 'form-control pl-unit';
    unitInput.value     = parseFloat(unitPrice) >= 0 ? parseFloat(unitPrice) : 0;
    unitInput.min       = '0';
    unitInput.step      = '0.01';
    unitCol.appendChild(unitLabel);
    unitCol.appendChild(unitInput);
    numRow.appendChild(unitCol);

    // Line total (display only)
    var totCol = document.createElement('div');
    totCol.className = 'col-md-5';
    var totLabel = document.createElement('label');
    totLabel.className   = 'form-label small fw-semibold mb-1';
    totLabel.textContent = 'Line Total';
    var totDisp = document.createElement('div');
    totDisp.className = 'form-control bg-light text-end fw-semibold pl-line-disp';
    totDisp.textContent = '$0.00';
    totCol.appendChild(totLabel);
    totCol.appendChild(totDisp);
    numRow.appendChild(totCol);

    body.appendChild(numRow);
    row.appendChild(body);
    container.appendChild(row);

    // Recalc on qty/unit change
    function recalcLine() {
        var q = parseFloat(qtyInput.value)  || 0;
        var u = parseFloat(unitInput.value) || 0;
        var t = q * u;
        var s = '$' + t.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        totDisp.textContent                             = s;
        hdr.querySelector('.pl-line-total').textContent = s;
        recalcGrandTotal();
    }
    qtyInput.addEventListener('input',  recalcLine);
    unitInput.addEventListener('input', recalcLine);
    recalcLine();
    updateSortOrders();
}

// keep old name as alias so template-load code still works
function addLineItem(desc, qty, unit) { addPriceLine(desc, qty, unit); }

// ── Add SIGNATURE block ───────────────────────────────────────────────────────
function addSignature(label) {
    const idx = blockCounter++;
    const div = document.createElement('div');
    div.className = 'block-row card border mb-3';
    div.dataset.type = 'signature';
    div.innerHTML = `
        <div class="block-handle card-header py-2 d-flex align-items-center gap-2"
             style="cursor:grab;user-select:none;">
            <i class="bi bi-grip-vertical text-muted fs-5"></i>
            <span class="badge bg-success-subtle text-success border border-success-subtle">
                <i class="bi bi-pen me-1"></i>Signature Block
            </span>
            <button type="button" class="btn btn-sm btn-link text-danger ms-auto p-0"
                    onclick="removeBlock(this)" title="Remove block">
                <i class="bi bi-trash"></i>
            </button>
        </div>
        <div class="card-body">
            <input type="hidden" name="blocks[${idx}][type]" value="signature">
            <input type="hidden" name="blocks[${idx}][sort_order]" class="sort-input" value="${idx}">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-semibold mb-1">Signature Label</label>
                    <input type="text" name="blocks[${idx}][sig_label]" class="form-control sig-label-input"
                           placeholder="e.g. Authorized Signature, Client Name">
                </div>
                <div class="col-md-7">
                    <div class="border-0 border-bottom border-dark border-2 pb-1" style="min-height:40px;"></div>
                    <div class="d-flex justify-content-between small text-muted mt-1">
                        <span class="sig-preview-label">Signature</span>
                        <span>Date</span>
                    </div>
                </div>
            </div>
        </div>`;

    document.getElementById('blocksContainer').appendChild(div);
    const sigInput = div.querySelector('.sig-label-input');
    if (sigInput) {
        sigInput.value = label || '';
        sigInput.addEventListener('input', function () {
            const preview = div.querySelector('.sig-preview-label');
            if (preview) preview.textContent = this.value || 'Signature';
        });
    }
    updateSortOrders();
}

// ── Remove any block ──────────────────────────────────────────────────────────
function removeBlock(btn) {
    const block = btn.closest('.block-row');
    // Destroy Summernote if this is a text block
    const ta = block.querySelector('.summernote-editor');
    if (ta && $(ta).data('summernote')) {
        ta.value = $(ta).summernote('code');
        $(ta).summernote('destroy');
    }
    block.remove();
    updateGrandTotal();
}

// ── Template loader ───────────────────────────────────────────────────────────
function loadTemplate() {
    const id = parseInt(document.getElementById('templatePicker').value, 10);
    if (!id || !TEMPLATE_MAP[id]) { alert('Please select a template first.'); return; }
    const t = TEMPLATE_MAP[id];

    if (document.querySelectorAll('#blocksContainer .block-row').length > 0) {
        if (!confirm('Loading "' + t.name + '" will replace all current blocks. Continue?')) return;
    }

    // Destroy all Summernote instances, clear container
    document.querySelectorAll('#blocksContainer .summernote-editor').forEach(function (ta) {
        if ($(ta).data('summernote')) { ta.value = $(ta).summernote('code'); $(ta).summernote('destroy'); }
    });
    document.getElementById('blocksContainer').innerHTML = '';
    blockCounter = 0;

    if (t.blocks && t.blocks.length) {
        t.blocks.forEach(function (b) {
            if (b.block_type === 'text')      addTextBlock(b.content);
            else if (b.block_type === 'item') addPriceLine(b.description, b.quantity, b.unit_price);
            else if (b.block_type === 'signature') addSignature(b.sig_label);
        });
    }
    updateGrandTotal();

    // Visual feedback
    const btn = document.getElementById('loadTemplateBtn');
    if (btn) {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>Loaded';
        btn.classList.replace('btn-outline-primary', 'btn-success');
        setTimeout(() => { btn.innerHTML = orig; btn.classList.replace('btn-success', 'btn-outline-primary'); }, 2000);
    }
}

// ── Sync + sort orders before form submit ─────────────────────────────────────
document.getElementById('proposalForm').addEventListener('submit', function () {
    syncEditorsToTextareas();
    updateSortOrders();
});

// ── Logo upload helpers (plain fetch — no HTMX) ───────────────────────────────
function uploadAgencyLogo() {
    const input = document.getElementById('agencyLogoFile');
    if (!input || !input.files.length) { alert('Please select a file first.'); return; }
    const fd = new FormData();
    fd.append('logo_file', input.files[0]);
    fetch('/proposals/actions/upload-logo.php', { method: 'POST', body: fd })
        .then(function (r) { return r.text(); })
        .then(function (html) {
            document.getElementById('agency-logo-preview').innerHTML = html;
            input.value = '';
        })
        .catch(function () { alert('Upload failed. Please try again.'); });
}

function uploadClientLogo() {
    const clientId = document.getElementById('client_id').value;
    if (!clientId) { alert('Please select a client first.'); return; }
    const input = document.getElementById('clientLogoFile');
    if (!input || !input.files.length) { alert('Please select a file first.'); return; }
    const fd = new FormData();
    fd.append('logo_file', input.files[0]);
    fd.append('client_id', clientId);
    fetch('/proposals/actions/upload-client-logo.php', { method: 'POST', body: fd })
        .then(function (r) { return r.text(); })
        .then(function (html) {
            document.getElementById('client-logo-preview').innerHTML = html;
            input.value = '';
        })
        .catch(function () { alert('Upload failed. Please try again.'); });
}

// ── Wire Add Price Line button via addEventListener (no onclick) ───────────────
var addPriceLineBtn = document.getElementById('addPriceLineBtn');
if (addPriceLineBtn) {
    addPriceLineBtn.addEventListener('click', function () { addPriceLine(); });
}

// ── Expose all functions globally (required for inline onclick handlers) ──────
window.addPriceLine     = addPriceLine;
window.addLineItem      = addLineItem;
window.addTextBlock     = addTextBlock;
window.addSignature     = addSignature;
window.removeBlock      = removeBlock;
window.loadTemplate     = loadTemplate;
window.uploadAgencyLogo = uploadAgencyLogo;
window.uploadClientLogo = uploadClientLogo;
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
