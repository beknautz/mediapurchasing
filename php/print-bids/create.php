<?php
require_once __DIR__ . '/../bootstrap.php';
requireRole(['admin', 'buyer']);

$svc    = new PrintBidService();
$errors = [];

// ── Load existing bid for edit ────────────────────────────────────────────
$editId  = !empty($_GET['id']) ? (int)$_GET['id'] : null;
$bid     = $editId ? $svc->getBid($editId) : null;
$isEdit  = !empty($bid);

// ── Vendor lists — strictly tagged vendors only ───────────────────────────
$printVendors   = $svc->getVendorsForPrint();   // tagged "Printing"
$signageVendors = $svc->getVendorsForSignage();  // tagged "Signage"

// ── Handle POST ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $clientName = trim($_POST['client_name'] ?? '');
    $title      = trim($_POST['title']       ?? '');
    $status     = $_POST['save_as_draft'] ?? '' ? 'draft' : 'sent';
    $notes      = trim($_POST['notes']       ?? '');

    $printerIds = array_map('intval', (array)($_POST['printer_vendor_ids'] ?? []));
    $signageIds = array_map('intval', (array)($_POST['signage_vendor_ids'] ?? []));

    // Decode items JSON sent from JS
    $printItemsJson   = $_POST['print_items_json']   ?? '[]';
    $signageItemsJson = $_POST['signage_items_json']  ?? '[]';
    $printItems   = json_decode($printItemsJson,   true) ?: [];
    $signageItems = json_decode($signageItemsJson, true) ?: [];

    if ($clientName === '') {
        $errors[] = 'Client name is required.';
    }

    if (empty($errors)) {
        $bidData = [
            'client_name'        => $clientName,
            'title'              => $title,
            'status'             => $status,
            'printer_vendor_ids' => $printerIds,
            'signage_vendor_ids' => $signageIds,
            'notes'              => $notes,
        ];
        if ($isEdit) $bidData['id'] = $bid['id'];

        $result = $svc->saveBid($bidData);
        $bidId  = $result['id'];

        // Merge and save all items
        $allItems = [];
        foreach ($printItems   as $item) { $item['type'] = 'print';   $allItems[] = $item; }
        foreach ($signageItems as $item) { $item['type'] = 'signage'; $allItems[] = $item; }
        $svc->saveItems($bidId, $allItems);

        // Handle file uploads
        if (!empty($_FILES['attachments']['name'][0]) || !empty($_FILES['attachments']['name'])) {
            $svc->saveAttachments($bidId, $_FILES['attachments']);
        }

        // Send emails to vendors if status is 'sent'
        if ($status === 'sent') {
            $fullBid     = $svc->getBid($bidId);
            $emailResult = $svc->sendBidEmails($fullBid);
            if ($emailResult['sent'] > 0 && $emailResult['failed'] === 0) {
                flash('success', $result['message'] . ' Emailed ' . $emailResult['sent'] . ' vendor' . ($emailResult['sent'] !== 1 ? 's' : '') . '.');
            } elseif ($emailResult['sent'] > 0) {
                flash('success', $result['message'] . ' Emailed ' . $emailResult['sent'] . ' vendor(s); ' . $emailResult['failed'] . ' failed.');
                flash('warning', implode('<br>', $emailResult['errors']));
            } else {
                flash('warning', 'Bid saved but no emails were sent. ' . implode('<br>', $emailResult['errors']));
            }
        } else {
            flash('success', $result['message']);
        }

        redirect('/print-bids/view.php?id=' . $bidId);
    }
}

// Existing items split by type
$existingPrintItems   = array_values(array_filter($bid['items'] ?? [], fn($i) => $i['type'] === 'print'));
$existingSignageItems = array_values(array_filter($bid['items'] ?? [], fn($i) => $i['type'] === 'signage'));
$existingPrinterIds   = $bid['printer_vendor_ids'] ?? [];
$existingSignageIds   = $bid['signage_vendor_ids']  ?? [];

$INK_OPTIONS = ['1/0 (Pantone)','1/1 (Pantone)','2/0 (Pantone)','2/2 (Pantone)',
                '3/0 (Pantone)','3/3 (Pantone)','4/0 (Full Color)','4/4 (Full Color)','See (Notes)'];

$SIGN_MATERIALS = ['8oz Banner','10oz Banner','Foamcore','Printed on Dibond',
                   'Vinyl & Dibond','Corrugated Plastic','See (Notes)'];

$pageTitle = ($isEdit ? 'Edit' : 'New') . ' Print Bid — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* ── Print Bids form styles ── */
.pb-section {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: .5rem;
    margin-bottom: 1.25rem;
}
.pb-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .75rem 1rem;
    border-bottom: 1px solid #dee2e6;
    background: #f8f9fa;
    border-radius: .5rem .5rem 0 0;
}
.pb-section-header h6 {
    margin: 0;
    color: #b02a37;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    font-size: .8rem;
}
.pb-section-body { padding: 1rem; }
.item-card {
    border: 1px solid #e9ecef;
    border-left: 4px solid #0d6efd;
    border-radius: .375rem;
    padding: 1rem;
    margin-bottom: .75rem;
    background: #fdfdff;
    position: relative;
}
.item-card.signage-card { border-left-color: #b02a37; }
.item-remove {
    position: absolute;
    top: .5rem;
    right: .5rem;
    padding: .15rem .4rem;
    font-size: .7rem;
}
.qty-row { display: flex; gap: .4rem; align-items: center; }
.qty-row input { min-width: 0; text-align: center; }
.qty-sep { color: #adb5bd; font-weight: 700; font-size: .9rem; }
.vendor-list {
    max-height: 160px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: .375rem;
    padding: .5rem .75rem;
}
.vendor-list label { display: block; font-size: .875rem; padding: .1rem 0; cursor: pointer; }
</style>

<!-- ── Breadcrumb ── -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="/print-bids/index.php">Print Bids</a></li>
        <li class="breadcrumb-item active"><?= $isEdit ? 'Edit Bid #' . $bid['id'] : 'New Bid' ?></li>
    </ol>
</nav>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Please fix:</strong>
        <ul class="mb-0 mt-1"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="post" action="/print-bids/create.php<?= $isEdit ? '?id=' . $bid['id'] : '' ?>"
      id="printBidForm" enctype="multipart/form-data" novalidate>

    <!-- Hidden JSON fields filled by JS before submit -->
    <input type="hidden" id="printItemsJson"   name="print_items_json"   value="[]">
    <input type="hidden" id="signageItemsJson" name="signage_items_json" value="[]">

    <div class="row g-4">

        <!-- ════════════════════════════════════════════
             LEFT COLUMN — main form
             ════════════════════════════════════════════ -->
        <div class="col-lg-8">

            <!-- ── Details card ── -->
            <div class="pb-section">
                <div class="pb-section-header">
                    <h6><i class="bi bi-info-circle me-2"></i>Printing/Signage Details</h6>
                </div>
                <div class="pb-section-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label for="clientName" class="form-label fw-semibold">
                                Client Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="clientName" name="client_name" class="form-control"
                                   placeholder="e.g. Q3 Brand Awareness — WKRP Radio" required
                                   value="<?= h($bid['client_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-5">
                            <label for="bidTitle" class="form-label fw-semibold">Bid Title <span class="text-muted small fw-normal">(optional)</span></label>
                            <input type="text" id="bidTitle" name="title" class="form-control"
                                   placeholder="e.g. Summer Campaign Print"
                                   value="<?= h($bid['title'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="row g-3">
                        <!-- Printers — tagged "Printing" -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                Printer <span class="text-danger">*</span>
                                <span class="text-muted small fw-normal">(select all that apply)</span>
                            </label>
                            <?php if (empty($printVendors)): ?>
                                <div class="text-muted small border rounded p-2">
                                    No print shops found.
                                    <a href="/admin/vendors.php">Tag vendors with "Printing"</a>.
                                </div>
                            <?php else: ?>
                            <div class="vendor-list">
                                <?php foreach ($printVendors as $v): ?>
                                    <label class="d-flex align-items-center gap-2">
                                        <input type="checkbox" class="form-check-input mt-0"
                                               name="printer_vendor_ids[]"
                                               value="<?= (int)$v['id'] ?>"
                                               <?= in_array((int)$v['id'], $existingPrinterIds, true) ? 'checked' : '' ?>>
                                        <?= h($v['company_name']) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Signage shops — tagged "Signage" -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                Signage <span class="text-danger">*</span>
                                <span class="text-muted small fw-normal">(select all that apply)</span>
                            </label>
                            <?php if (empty($signageVendors)): ?>
                                <div class="text-muted small border rounded p-2">
                                    No signage shops found.
                                    <a href="/admin/vendors.php">Tag vendors with "Signage"</a>.
                                </div>
                            <?php else: ?>
                            <div class="vendor-list">
                                <?php foreach ($signageVendors as $v): ?>
                                    <label class="d-flex align-items-center gap-2">
                                        <input type="checkbox" class="form-check-input mt-0"
                                               name="signage_vendor_ids[]"
                                               value="<?= (int)$v['id'] ?>"
                                               <?= in_array((int)$v['id'], $existingSignageIds, true) ? 'checked' : '' ?>>
                                        <?= h($v['company_name']) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Print Job Items ── -->
            <div class="pb-section">
                <div class="pb-section-header">
                    <h6><i class="bi bi-printer me-2"></i>Printing Job Items</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addPrintItem()">
                        <i class="bi bi-plus-lg me-1"></i>Add Print Item
                    </button>
                </div>
                <div class="pb-section-body">
                    <div id="printItemsContainer">
                        <!-- Populated by JS on load -->
                    </div>
                    <p id="printEmptyMsg" class="text-muted small text-center py-2 d-none">
                        No print items yet. Click <strong>Add Print Item</strong> to begin.
                    </p>
                </div>
            </div>

            <!-- ── Signage Job Items ── -->
            <div class="pb-section">
                <div class="pb-section-header" style="border-left: 4px solid #b02a37; border-radius: .5rem .5rem 0 0;">
                    <h6 style="color:#b02a37;"><i class="bi bi-sign-stop me-2"></i>Signage Job Items</h6>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="addSignageItem()">
                        <i class="bi bi-plus-lg me-1"></i>Add Sign Item
                    </button>
                </div>
                <div class="pb-section-body">
                    <div id="signageItemsContainer">
                        <!-- Populated by JS on load -->
                    </div>
                    <p id="signageEmptyMsg" class="text-muted small text-center py-2 d-none">
                        No signage items yet. Click <strong>Add Sign Item</strong> to begin.
                    </p>
                </div>
            </div>

            <!-- ── Notes ── -->
            <div class="pb-section">
                <div class="pb-section-header">
                    <h6><i class="bi bi-sticky me-2"></i>Notes</h6>
                </div>
                <div class="pb-section-body">
                    <textarea name="notes" class="form-control" rows="3"
                              placeholder="Additional notes or instructions for vendors…"><?= h($bid['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- ── Attachments ── -->
            <div class="pb-section">
                <div class="pb-section-header">
                    <h6><i class="bi bi-paperclip me-2"></i>Attachments</h6>
                    <span class="text-muted small fw-normal">PDF, JPG, PNG — sent with the email to vendors</span>
                </div>
                <div class="pb-section-body">

                    <?php if ($isEdit && !empty($bid['attachments'])): ?>
                    <div class="mb-3">
                        <p class="small fw-semibold mb-1 text-muted">Already attached:</p>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($bid['attachments'] as $att): ?>
                                <li class="d-flex align-items-center gap-2 mb-1 small">
                                    <i class="bi bi-file-earmark-pdf text-danger"></i>
                                    <a href="/<?= h($att['path']) ?>" target="_blank"><?= h($att['name']) ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="small text-muted mt-1">Upload more files below to add to the existing list.</p>
                    </div>
                    <?php endif; ?>

                    <div id="attachDropZone"
                         class="border border-2 border-dashed rounded-3 p-4 text-center"
                         style="border-color:#6c757d!important;cursor:pointer;transition:background .2s;"
                         ondragover="attachDragOver(event)"
                         ondragleave="attachDragLeave(event)"
                         ondrop="attachDrop(event)"
                         onclick="document.getElementById('attachInput').click()">
                        <input type="file" id="attachInput" name="attachments[]"
                               multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.webp"
                               class="d-none" onchange="attachSelected(this)">
                        <i class="bi bi-paperclip fs-3 text-secondary d-block mb-1"></i>
                        <p class="mb-0 fw-semibold text-secondary">Drag &amp; drop files here</p>
                        <p class="mb-0 small text-muted">or click to browse &mdash; PDF, JPG, PNG supported</p>
                    </div>

                    <ul id="attachFileList" class="list-unstyled mt-2 mb-0 small"></ul>
                </div>
            </div>

        </div><!-- /col-lg-8 -->

        <!-- ════════════════════════════════════════════
             RIGHT COLUMN — save actions
             ════════════════════════════════════════════ -->
        <div class="col-lg-4">
            <div class="sticky-top" style="top: 1rem;">

                <!-- Save actions card -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-dark text-white fw-semibold">
                        <i class="bi bi-floppy me-2"></i>Save
                    </div>
                    <div class="card-body d-grid gap-2">
                        <button type="submit" name="save_as_draft" value="" class="btn btn-primary">
                            <i class="bi bi-send-fill me-2"></i>Save &amp; Send
                        </button>
                        <button type="submit" name="save_as_draft" value="1" class="btn btn-outline-secondary">
                            <i class="bi bi-floppy me-2"></i>Save as Draft
                        </button>
                        <p class="text-muted small mb-0 text-center">You can continue editing and send to vendor later.</p>
                        <hr class="my-1">
                        <a href="/print-bids/index.php" class="btn btn-link text-secondary btn-sm text-center">
                            <i class="bi bi-x-circle me-1"></i>Cancel
                        </a>
                    </div>
                </div>

                <!-- Ink quick-reference -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-light fw-semibold small text-muted">
                        <i class="bi bi-palette me-1"></i>Ink Spec Reference
                    </div>
                    <div class="card-body p-2">
                        <?php foreach ($INK_OPTIONS as $ink): ?>
                            <div class="badge bg-light text-dark border m-1 fw-normal"><?= h($ink) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Signage material quick-reference -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light fw-semibold small text-muted">
                        <i class="bi bi-sign-stop me-1"></i>Signage Material Reference
                    </div>
                    <div class="card-body p-2">
                        <?php foreach ($SIGN_MATERIALS as $mat): ?>
                            <div class="badge bg-light text-dark border m-1 fw-normal"><?= h($mat) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>

    </div><!-- /row -->
</form>

<script>
// ── Constants from PHP ─────────────────────────────────────────────────────
const INK_OPTIONS = <?= json_encode($INK_OPTIONS) ?>;
const SIGN_MATERIALS = <?= json_encode($SIGN_MATERIALS) ?>;

// Existing items pre-loaded from DB (edit mode)
let printItems   = <?= json_encode(array_map(fn($i) => [
    'description' => $i['description'] ?? '',
    'size'        => $i['size']        ?? '',
    'paper'       => $i['paper']       ?? '',
    'ink_spec'    => $i['ink_spec']    ?? '',
    'qty_1'       => $i['qty_1']       ?? '50',
    'qty_2'       => $i['qty_2']       ?? '100',
    'qty_3'       => $i['qty_3']       ?? '250',
    'qty_4'       => $i['qty_4']       ?? '500',
    'qty_5'       => $i['qty_5']       ?? '1000',
    'notes'       => $i['notes']       ?? '',
], $existingPrintItems)) ?>;

let signageItems = <?= json_encode(array_map(fn($i) => [
    'description' => $i['description'] ?? '',
    'size'        => $i['size']        ?? '',
    'material'    => $i['material']    ?? '',
    'qty_1'       => $i['qty_1']       ?? '1',
    'notes'       => $i['notes']       ?? '',
], $existingSignageItems)) ?>;

// Start blank — user adds items via the Add buttons

// ── New item factories ─────────────────────────────────────────────────────
function newPrintItem() {
    return { description:'', size:'00 x 00', paper:'100# Gloss', ink_spec:'',
             qty_1:'50', qty_2:'100', qty_3:'250', qty_4:'500', qty_5:'1000', notes:'' };
}
function newSignageItem() {
    return { description:'Banner', size:'00 x 00', material:'Foamcore', qty_1:'00', notes:'' };
}

// ── Render ─────────────────────────────────────────────────────────────────
function renderPrintItems() {
    const c = document.getElementById('printItemsContainer');
    const e = document.getElementById('printEmptyMsg');
    c.innerHTML = '';
    if (printItems.length === 0) { e.classList.remove('d-none'); return; }
    e.classList.add('d-none');
    printItems.forEach((item, idx) => c.appendChild(buildPrintCard(item, idx)));
}

function renderSignageItems() {
    const c = document.getElementById('signageItemsContainer');
    const e = document.getElementById('signageEmptyMsg');
    c.innerHTML = '';
    if (signageItems.length === 0) { e.classList.remove('d-none'); return; }
    e.classList.add('d-none');
    signageItems.forEach((item, idx) => c.appendChild(buildSignageCard(item, idx)));
}

// ── Build print item card ──────────────────────────────────────────────────
function buildPrintCard(item, idx) {
    const d = document.createElement('div');
    d.className = 'item-card';
    d.innerHTML = `
      <button type="button" class="btn btn-sm btn-outline-danger item-remove"
              onclick="removePrintItem(${idx})" title="Remove">
          <i class="bi bi-x-lg"></i>
      </button>
      <div class="row g-2 mb-2">
          <div class="col-md-6">
              <label class="form-label small fw-semibold mb-1">Description</label>
              <input type="text" class="form-control form-control-sm"
                     placeholder="BCard" value="${esc(item.description)}"
                     oninput="printItems[${idx}].description=this.value">
          </div>
          <div class="col-md-6">
              <label class="form-label small fw-semibold mb-1">Size</label>
              <input type="text" class="form-control form-control-sm"
                     placeholder="00 x 00" value="${esc(item.size)}"
                     oninput="printItems[${idx}].size=this.value">
          </div>
          <div class="col-md-6">
              <label class="form-label small fw-semibold mb-1">Paper</label>
              <input type="text" class="form-control form-control-sm"
                     placeholder="100# Gloss" value="${esc(item.paper)}"
                     oninput="printItems[${idx}].paper=this.value">
          </div>
          <div class="col-md-6">
              <label class="form-label small fw-semibold mb-1">Ink #</label>
              <select class="form-select form-select-sm"
                      onchange="printItems[${idx}].ink_spec=this.value">
                  <option value="">— Select Ink —</option>
                  ${INK_OPTIONS.map(o => `<option value="${esc(o)}" ${item.ink_spec===o?'selected':''}>${esc(o)}</option>`).join('')}
              </select>
          </div>
      </div>
      <div class="mb-2">
          <label class="form-label small fw-semibold mb-1">Quantity</label>
          <div class="qty-row">
              ${['qty_1','qty_2','qty_3','qty_4','qty_5'].map((q,qi) => `
                  <input type="text" class="form-control form-control-sm"
                         value="${esc(item[q])}"
                         oninput="printItems[${idx}].${q}=this.value">
                  ${qi < 4 ? '<span class="qty-sep">/</span>' : ''}
              `).join('')}
          </div>
      </div>
      <div>
          <label class="form-label small fw-semibold mb-1">Notes for job</label>
          <textarea class="form-control form-control-sm" rows="2"
                    oninput="printItems[${idx}].notes=this.value">${esc(item.notes)}</textarea>
      </div>
    `;
    return d;
}

// ── Build signage item card ────────────────────────────────────────────────
function buildSignageCard(item, idx) {
    const d = document.createElement('div');
    d.className = 'item-card signage-card';
    d.innerHTML = `
      <button type="button" class="btn btn-sm btn-outline-danger item-remove"
              onclick="removeSignageItem(${idx})" title="Remove">
          <i class="bi bi-x-lg"></i>
      </button>
      <div class="row g-2 mb-2">
          <div class="col-md-6">
              <label class="form-label small fw-semibold mb-1">Description</label>
              <input type="text" class="form-control form-control-sm"
                     placeholder="Banner" value="${esc(item.description)}"
                     oninput="signageItems[${idx}].description=this.value">
          </div>
          <div class="col-md-6">
              <label class="form-label small fw-semibold mb-1">Size</label>
              <input type="text" class="form-control form-control-sm"
                     placeholder="00 x 00" value="${esc(item.size)}"
                     oninput="signageItems[${idx}].size=this.value">
          </div>
          <div class="col-md-6">
              <label class="form-label small fw-semibold mb-1">Material</label>
              <select class="form-select form-select-sm"
                      onchange="signageItems[${idx}].material=this.value">
                  ${SIGN_MATERIALS.map(m => `<option value="${esc(m)}" ${item.material===m?'selected':''}>${esc(m)}</option>`).join('')}
              </select>
          </div>
          <div class="col-md-6">
              <label class="form-label small fw-semibold mb-1">Quantity</label>
              <input type="text" class="form-control form-control-sm"
                     placeholder="00" value="${esc(item.qty_1)}"
                     oninput="signageItems[${idx}].qty_1=this.value">
          </div>
      </div>
      <div>
          <label class="form-label small fw-semibold mb-1">Notes for job</label>
          <textarea class="form-control form-control-sm" rows="2"
                    oninput="signageItems[${idx}].notes=this.value">${esc(item.notes)}</textarea>
      </div>
    `;
    return d;
}

// ── Add / remove ───────────────────────────────────────────────────────────
function addPrintItem()   { printItems.push(newPrintItem());   renderPrintItems();   }
function addSignageItem() { signageItems.push(newSignageItem()); renderSignageItems(); }

function removePrintItem(idx)   { printItems.splice(idx, 1);   renderPrintItems();   }
function removeSignageItem(idx) { signageItems.splice(idx, 1); renderSignageItems(); }

// ── XSS-safe for innerHTML ─────────────────────────────────────────────────
function esc(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Serialize to hidden JSON before submit ─────────────────────────────────
document.getElementById('printBidForm').addEventListener('submit', function () {
    document.getElementById('printItemsJson').value   = JSON.stringify(printItems);
    document.getElementById('signageItemsJson').value = JSON.stringify(signageItems);
});

// ── Attachment drag-drop ──────────────────────────────────────────────────
let attachFiles = new DataTransfer();

function attachDragOver(e) {
    e.preventDefault();
    document.getElementById('attachDropZone').style.background = '#f0f5ff';
}
function attachDragLeave(e) {
    document.getElementById('attachDropZone').style.background = '';
}
function attachDrop(e) {
    e.preventDefault();
    attachDragLeave(e);
    const input = document.getElementById('attachInput');
    [...e.dataTransfer.files].forEach(f => attachFiles.items.add(f));
    input.files = attachFiles.files;
    renderAttachList();
}
function attachSelected(input) {
    [...input.files].forEach(f => attachFiles.items.add(f));
    input.files = attachFiles.files;
    renderAttachList();
}
function renderAttachList() {
    const list  = document.getElementById('attachFileList');
    const files = document.getElementById('attachInput').files;
    list.innerHTML = '';
    [...files].forEach((f, i) => {
        const li = document.createElement('li');
        li.className = 'd-flex align-items-center gap-2 py-1 border-bottom';
        li.innerHTML = `<i class="bi bi-file-earmark text-secondary"></i>
            <span class="flex-grow-1">${esc(f.name)}</span>
            <span class="text-muted">${(f.size/1024).toFixed(0)} KB</span>
            <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeAttach(${i})">
                <i class="bi bi-x-lg"></i>
            </button>`;
        list.appendChild(li);
    });
}
function removeAttach(idx) {
    const dt = new DataTransfer();
    const files = document.getElementById('attachInput').files;
    [...files].forEach((f, i) => { if (i !== idx) dt.items.add(f); });
    attachFiles = dt;
    document.getElementById('attachInput').files = dt.files;
    renderAttachList();
}

// ── Initial render ─────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    renderPrintItems();
    renderSignageItems();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
