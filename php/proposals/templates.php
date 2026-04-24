<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';
requireRole(['admin', 'buyer']);

$proposalService = new ProposalService();
$errors  = [];

// ── POST handler ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id       = (int)   ($_POST['id']   ?? 0);
        $name     = trim(    $_POST['name'] ?? '');
        $rawBlocks = (array) ($_POST['blocks'] ?? []);

        if ($name === '') $errors[] = 'Template name is required.';

        $cleanBlocks = [];
        foreach ($rawBlocks as $b) {
            $type = $b['type'] ?? 'text';
            if ($type === 'text') {
                $content = $b['content'] ?? '';
                if (trim(strip_tags($content)) === '' && $content === '') continue;
                $cleanBlocks[] = ['block_type' => 'text', 'content' => $content,
                                  'sort_order' => (int)($b['sort_order'] ?? 0)];
            } elseif ($type === 'item') {
                $desc = trim($b['description'] ?? '');
                if ($desc === '') continue;
                $qty  = max(0, (float)($b['quantity']  ?? 1));
                $unit = max(0, (float)($b['unit_price'] ?? 0));
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

        if (empty($errors)) {
            try {
                $proposalService->saveTemplate(['id' => $id, 'name' => $name], $cleanBlocks);
                flash('success', $id > 0 ? 'Template updated.' : 'Template created.');
                redirect('/proposals/templates.php');
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }

    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $proposalService->deleteTemplate($id);
                flash('success', 'Template deleted.');
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
        if (empty($errors)) redirect('/proposals/templates.php');
    }
}

// Reload state for re-opening modal on validation error
$postId     = (int)   ($_POST['id']   ?? 0);
$postName   = trim(    $_POST['name'] ?? '');
$postBlocks = [];
foreach ((array)($_POST['blocks'] ?? []) as $b) {
    $type = $b['type'] ?? 'text';
    if ($type === 'text') {
        $postBlocks[] = ['block_type' => 'text', 'content' => $b['content'] ?? '',
                         'sort_order' => (int)($b['sort_order'] ?? 0)];
    } elseif ($type === 'item') {
        $desc = trim($b['description'] ?? '');
        if ($desc !== '') {
            $postBlocks[] = ['block_type' => 'item', 'description' => $desc,
                             'quantity'   => (float)($b['quantity']  ?? 1),
                             'unit_price' => (float)($b['unit_price'] ?? 0),
                             'sort_order' => (int)($b['sort_order'] ?? 0)];
        }
    } elseif ($type === 'signature') {
        $postBlocks[] = ['block_type' => 'signature', 'sig_label' => trim($b['sig_label'] ?? ''),
                         'sort_order' => (int)($b['sort_order'] ?? 0)];
    }
}
usort($postBlocks, fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);
$reopenModal = ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save' && !empty($errors));

try {
    $templates = $proposalService->getTemplatesWithBlocks();
} catch (Exception $e) {
    $templates = [];
    $errors[]  = 'Could not load templates — run sql/migrate_proposals_v2.sql first. (' . $e->getMessage() . ')';
}
$flashMsg    = flash('success');
$templateMap = $templates; // already keyed by id

$pageTitle = 'Proposal Templates — MediaBuy';
$extraHead = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-bs5.min.css">';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">
            <i class="bi bi-file-earmark-text me-2 text-primary"></i>Proposal Templates
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="/proposals/index.php">Proposals</a></li>
                <li class="breadcrumb-item active">Templates</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        <a href="/proposals/index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
        <button type="button" class="btn btn-primary" onclick="openModal()">
            <i class="bi bi-plus-circle me-1"></i>New Template
        </button>
    </div>
</div>

<?php if ($flashMsg): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle-fill me-2"></i><?= h($flashMsg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Please fix the following:</strong>
    <ul class="mb-0 mt-1"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($templates)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-file-earmark-text fs-2 d-block mb-2 opacity-50"></i>
            <div>No templates yet. Create one to speed up proposal building.</div>
            <button type="button" class="btn btn-sm btn-outline-primary mt-3" onclick="openModal()">
                <i class="bi bi-plus-circle me-1"></i>Create First Template
            </button>
        </div>
        <?php else: ?>
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Blocks</th>
                    <th>Created By</th>
                    <th>Last Updated</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($templates as $t):
                $itemBlocks = array_filter($t['blocks'], fn($b) => $b['block_type'] === 'item');
                $estValue   = array_sum(array_map(fn($b) => (float)$b['quantity'] * (float)$b['unit_price'], $itemBlocks));
                $blockCount = count($t['blocks']);
            ?>
            <tr>
                <td class="fw-semibold"><?= h($t['name']) ?></td>
                <td class="small text-muted">
                    <?php if ($blockCount > 0): ?>
                        <?= $blockCount ?> block<?= $blockCount !== 1 ? 's' : '' ?>
                        <?php if ($estValue > 0): ?>
                            <span class="text-muted">— $<?= number_format($estValue, 2) ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        Empty
                    <?php endif; ?>
                </td>
                <td class="small text-muted"><?= h($t['created_by_name'] ?? '—') ?></td>
                <td class="small text-muted text-nowrap">
                    <?= h(date('M j, Y', strtotime($t['updated_at']))) ?>
                </td>
                <td class="text-end text-nowrap">
                    <button type="button" class="btn btn-sm btn-outline-secondary me-1"
                            onclick="editTemplate(<?= (int)$t['id'] ?>)">
                        <i class="bi bi-pencil me-1"></i>Edit
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger"
                            onclick="confirmDelete(<?= (int)$t['id'] ?>, <?= json_encode($t['name']) ?>)">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- ── Create / Edit Modal ──────────────────────────────────────────────────── -->
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" action="/proposals/templates.php" id="templateForm">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="tmplId" value="<?= $postId ?>">

                <div class="modal-header">
                    <h5 class="modal-title fw-semibold" id="modalTitle">
                        <i class="bi bi-file-earmark-text me-2 text-primary"></i>New Template
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="tmplName" class="form-label fw-semibold">
                            Template Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="tmplName" name="name" required
                               value="<?= h($postName) ?>"
                               placeholder="e.g. Standard Media Buy Proposal">
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold mb-1">Content Blocks</label>
                        <div class="text-muted small mb-2">Drag to reorder. These blocks will pre-fill the proposal when this template is loaded.</div>
                    </div>

                    <!-- Sortable blocks container -->
                    <div id="tmplBlocksContainer" class="mb-2"></div>

                    <!-- Add block toolbar -->
                    <div class="d-flex gap-2 pt-2 border-top">
                        <button type="button" class="btn btn-sm btn-outline-info"
                                onclick="addTmplTextBlock()">
                            <i class="bi bi-text-paragraph me-1"></i>Add Text
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary"
                                onclick="addTmplLineItem()">
                            <i class="bi bi-receipt me-1"></i>Add Line Item
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success"
                                onclick="addTmplSignature()">
                            <i class="bi bi-pen me-1"></i>Add Signature
                        </button>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy me-1"></i>Save Template
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST" action="/proposals/templates.php">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Template?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body small">
                    Delete "<strong id="deleteName"></strong>"? This cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-bs5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
const TEMPLATE_MAP = <?= json_encode($templateMap) ?>;

const TMPL_SNOTE_OPTS = {
    height: 180,
    dialogsInBody: true,
    toolbar: [
        ['style',  ['bold','italic','underline','strikethrough','clear']],
        ['font',   ['fontsize']],
        ['color',  ['color']],
        ['para',   ['ul','ol','paragraph']],
        ['insert', ['link','hr']],
        ['view',   ['fullscreen','codeview']],
    ],
    placeholder: 'Write your text here…',
};

let tmplBlockCounter = 0;
let tmplSortable     = null;
const templateModal  = document.getElementById('templateModal');

// ── Modal lifecycle ───────────────────────────────────────────────────────────
templateModal.addEventListener('shown.bs.modal', function () {
    // Init Summernote on any text blocks not yet initialized
    templateModal.querySelectorAll('.tmpl-summernote-editor').forEach(function (ta) {
        if (!$(ta).data('summernote')) {
            const content = ta.value;
            $(ta).summernote(TMPL_SNOTE_OPTS);
            if (content) $(ta).summernote('code', content);
        }
    });
    // Init SortableJS once
    if (!tmplSortable) {
        tmplSortable = Sortable.create(document.getElementById('tmplBlocksContainer'), {
            handle:    '.tmpl-block-handle',
            animation: 150,
            onStart:   syncTmplEditors,
            onEnd:     updateTmplSortOrders,
        });
    }
});

templateModal.addEventListener('hidden.bs.modal', function () {
    // Sync then destroy all Summernote instances
    templateModal.querySelectorAll('.tmpl-summernote-editor').forEach(function (ta) {
        if ($(ta).data('summernote')) {
            ta.value = $(ta).summernote('code');
            $(ta).summernote('destroy');
        }
    });
    if (tmplSortable) { tmplSortable.destroy(); tmplSortable = null; }
});

// ── Sync editors to textareas ─────────────────────────────────────────────────
function syncTmplEditors() {
    templateModal.querySelectorAll('.tmpl-summernote-editor').forEach(function (ta) {
        if ($(ta).data('summernote')) {
            ta.value = $(ta).summernote('code');
        }
    });
}

// ── Update sort order hidden inputs ───────────────────────────────────────────
function updateTmplSortOrders() {
    templateModal.querySelectorAll('#tmplBlocksContainer .tmpl-block-row').forEach(function (el, i) {
        const inp = el.querySelector('.tmpl-sort-input');
        if (inp) inp.value = i;
    });
}

// ── Add TEXT block ────────────────────────────────────────────────────────────
function addTmplTextBlock(content) {
    const idx = tmplBlockCounter++;
    const div = document.createElement('div');
    div.className   = 'tmpl-block-row card border mb-3';
    div.dataset.type = 'text';
    div.innerHTML = `
        <div class="tmpl-block-handle card-header py-2 d-flex align-items-center gap-2"
             style="cursor:grab;user-select:none;">
            <i class="bi bi-grip-vertical text-muted fs-5"></i>
            <span class="badge bg-info-subtle text-info border border-info-subtle">
                <i class="bi bi-text-paragraph me-1"></i>Text Block
            </span>
            <button type="button" class="btn btn-sm btn-link text-danger ms-auto p-0"
                    onclick="removeTmplBlock(this)" title="Remove block">
                <i class="bi bi-trash"></i>
            </button>
        </div>
        <div class="card-body p-0">
            <input type="hidden" name="blocks[${idx}][type]" value="text">
            <input type="hidden" name="blocks[${idx}][sort_order]" class="tmpl-sort-input" value="${idx}">
            <textarea name="blocks[${idx}][content]" class="tmpl-summernote-editor"></textarea>
        </div>`;

    document.getElementById('tmplBlocksContainer').appendChild(div);

    // If modal is already visible, init Summernote immediately
    const ta = div.querySelector('.tmpl-summernote-editor');
    if (templateModal.classList.contains('show')) {
        $(ta).summernote(TMPL_SNOTE_OPTS);
        if (content) $(ta).summernote('code', content);
    } else if (content) {
        ta.value = content;
    }
    updateTmplSortOrders();
}

// ── Add LINE ITEM block ───────────────────────────────────────────────────────
function addTmplLineItem(description, quantity, unitPrice) {
    const idx = tmplBlockCounter++;
    const div = document.createElement('div');
    div.className    = 'tmpl-block-row card border mb-3';
    div.dataset.type = 'item';
    div.innerHTML = `
        <div class="tmpl-block-handle card-header py-2 d-flex align-items-center gap-2"
             style="cursor:grab;user-select:none;">
            <i class="bi bi-grip-vertical text-muted fs-5"></i>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                <i class="bi bi-receipt me-1"></i>Line Item
            </span>
            <span class="ms-auto fw-semibold small text-muted tmpl-item-header-total">$0.00</span>
            <button type="button" class="btn btn-sm btn-link text-danger p-0"
                    onclick="removeTmplBlock(this)" title="Remove block">
                <i class="bi bi-trash"></i>
            </button>
        </div>
        <div class="card-body">
            <input type="hidden" name="blocks[${idx}][type]" value="item">
            <input type="hidden" name="blocks[${idx}][sort_order]" class="tmpl-sort-input" value="${idx}">
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold mb-1">Description</label>
                    <input type="text" name="blocks[${idx}][description]"
                           class="form-control" placeholder="Service or item description" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Qty</label>
                    <input type="number" name="blocks[${idx}][quantity]"
                           class="form-control tmpl-item-qty" value="1" min="0" step="0.01">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Unit Price</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" name="blocks[${idx}][unit_price]"
                               class="form-control tmpl-item-unit" value="0.00" min="0" step="0.01">
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Total</label>
                    <div class="form-control bg-light text-end fw-semibold tmpl-item-total-disp">$0.00</div>
                </div>
            </div>
        </div>`;

    div.querySelector('[name$="[description]"]').value = description || '';
    div.querySelector('.tmpl-item-qty').value          = parseFloat(quantity)  || 1;
    div.querySelector('.tmpl-item-unit').value         = parseFloat(unitPrice) || 0;

    document.getElementById('tmplBlocksContainer').appendChild(div);
    bindTmplItemEvents(div);
    calcTmplItem(div);
    updateTmplSortOrders();
}

// ── Add SIGNATURE block ───────────────────────────────────────────────────────
function addTmplSignature(label) {
    const idx = tmplBlockCounter++;
    const div = document.createElement('div');
    div.className    = 'tmpl-block-row card border mb-3';
    div.dataset.type = 'signature';
    div.innerHTML = `
        <div class="tmpl-block-handle card-header py-2 d-flex align-items-center gap-2"
             style="cursor:grab;user-select:none;">
            <i class="bi bi-grip-vertical text-muted fs-5"></i>
            <span class="badge bg-success-subtle text-success border border-success-subtle">
                <i class="bi bi-pen me-1"></i>Signature Block
            </span>
            <button type="button" class="btn btn-sm btn-link text-danger ms-auto p-0"
                    onclick="removeTmplBlock(this)" title="Remove block">
                <i class="bi bi-trash"></i>
            </button>
        </div>
        <div class="card-body">
            <input type="hidden" name="blocks[${idx}][type]" value="signature">
            <input type="hidden" name="blocks[${idx}][sort_order]" class="tmpl-sort-input" value="${idx}">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-semibold mb-1">Signature Label</label>
                    <input type="text" name="blocks[${idx}][sig_label]"
                           class="form-control tmpl-sig-label-input"
                           placeholder="e.g. Authorized Signature, Client Name">
                </div>
                <div class="col-md-7">
                    <div class="border-0 border-bottom border-dark border-2 pb-1" style="min-height:40px;"></div>
                    <div class="d-flex justify-content-between small text-muted mt-1">
                        <span class="tmpl-sig-preview">Signature</span>
                        <span>Date</span>
                    </div>
                </div>
            </div>
        </div>`;

    div.querySelector('.tmpl-sig-label-input').value = label || '';
    div.querySelector('.tmpl-sig-label-input').addEventListener('input', function () {
        const preview = div.querySelector('.tmpl-sig-preview');
        if (preview) preview.textContent = this.value || 'Signature';
    });
    if (label) {
        div.querySelector('.tmpl-sig-preview').textContent = label;
    }

    document.getElementById('tmplBlocksContainer').appendChild(div);
    updateTmplSortOrders();
}

// ── Remove any block ──────────────────────────────────────────────────────────
function removeTmplBlock(btn) {
    const block = btn.closest('.tmpl-block-row');
    const ta    = block.querySelector('.tmpl-summernote-editor');
    if (ta && $(ta).data('summernote')) {
        ta.value = $(ta).summernote('code');
        $(ta).summernote('destroy');
    }
    block.remove();
}

// ── Item calc ─────────────────────────────────────────────────────────────────
function calcTmplItem(block) {
    const qty   = parseFloat(block.querySelector('.tmpl-item-qty')?.value)  || 0;
    const price = parseFloat(block.querySelector('.tmpl-item-unit')?.value) || 0;
    const total = qty * price;
    const fmt   = '$' + total.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
    const disp  = block.querySelector('.tmpl-item-total-disp');
    const hdr   = block.querySelector('.tmpl-item-header-total');
    if (disp) disp.textContent = fmt;
    if (hdr)  hdr.textContent  = fmt;
}

function bindTmplItemEvents(block) {
    block.querySelector('.tmpl-item-qty')?.addEventListener('input',  () => calcTmplItem(block));
    block.querySelector('.tmpl-item-unit')?.addEventListener('input', () => calcTmplItem(block));
}

// ── Open new-template modal ───────────────────────────────────────────────────
function openModal() {
    document.getElementById('modalTitle').innerHTML =
        '<i class="bi bi-file-earmark-text me-2 text-primary"></i>New Template';
    document.getElementById('tmplId').value   = '0';
    document.getElementById('tmplName').value = '';
    clearTmplBlocks();
    addTmplTextBlock(); // start with one empty text block
    bootstrap.Modal.getOrCreateInstance(templateModal).show();
}

// ── Edit existing template ────────────────────────────────────────────────────
function editTemplate(id) {
    const t = TEMPLATE_MAP[id];
    if (!t) return;

    document.getElementById('modalTitle').innerHTML =
        '<i class="bi bi-pencil me-2 text-primary"></i>Edit Template';
    document.getElementById('tmplId').value   = t.id;
    document.getElementById('tmplName').value = t.name || '';

    clearTmplBlocks();
    if (t.blocks && t.blocks.length) {
        t.blocks.forEach(function (b) {
            if      (b.block_type === 'text')      addTmplTextBlock(b.content);
            else if (b.block_type === 'item')      addTmplLineItem(b.description, b.quantity, b.unit_price);
            else if (b.block_type === 'signature') addTmplSignature(b.sig_label);
        });
    } else {
        addTmplTextBlock();
    }
    bootstrap.Modal.getOrCreateInstance(templateModal).show();
}

function clearTmplBlocks() {
    // Destroy any open Summernote instances first
    templateModal.querySelectorAll('.tmpl-summernote-editor').forEach(function (ta) {
        if ($(ta).data('summernote')) {
            ta.value = $(ta).summernote('code');
            $(ta).summernote('destroy');
        }
    });
    document.getElementById('tmplBlocksContainer').innerHTML = '';
    tmplBlockCounter = 0;
}

function confirmDelete(id, name) {
    document.getElementById('deleteId').value         = id;
    document.getElementById('deleteName').textContent = name;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteModal')).show();
}

// ── Sync + sort orders before submit ─────────────────────────────────────────
document.getElementById('templateForm').addEventListener('submit', function () {
    syncTmplEditors();
    updateTmplSortOrders();
});

// ── Reopen on validation error ────────────────────────────────────────────────
<?php if ($reopenModal): ?>
window.addEventListener('load', function () {
    document.getElementById('modalTitle').innerHTML =
        '<?= $postId > 0 ? '<i class="bi bi-pencil me-2 text-primary"></i>Edit Template' : '<i class="bi bi-file-earmark-text me-2 text-primary"></i>New Template' ?>';
    clearTmplBlocks();
    <?php foreach ($postBlocks as $pb): ?>
    <?php if ($pb['block_type'] === 'text'): ?>
    addTmplTextBlock(<?= json_encode($pb['content']) ?>);
    <?php elseif ($pb['block_type'] === 'item'): ?>
    addTmplLineItem(<?= json_encode($pb['description']) ?>, <?= (float)$pb['quantity'] ?>, <?= (float)$pb['unit_price'] ?>);
    <?php elseif ($pb['block_type'] === 'signature'): ?>
    addTmplSignature(<?= json_encode($pb['sig_label']) ?>);
    <?php endif; ?>
    <?php endforeach; ?>
    <?php if (empty($postBlocks)): ?>
    addTmplTextBlock();
    <?php endif; ?>
    bootstrap.Modal.getOrCreateInstance(templateModal).show();
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
