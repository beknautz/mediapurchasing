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
        $id        = (int)   ($_POST['id']         ?? 0);
        $name      = trim(    $_POST['name']        ?? '');
        $introText = trim(    $_POST['intro_text']  ?? '');
        $notes     = trim(    $_POST['notes']       ?? '');
        $rawItems  = (array) ($_POST['items']       ?? []);

        if ($name === '') $errors[] = 'Template name is required.';

        // Build + sort items
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
            try {
                $proposalService->saveTemplate(
                    ['id' => $id, 'name' => $name, 'intro_text' => $introText, 'notes' => $notes],
                    $cleanItems
                );
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

// Reload state for re-opening modal on error
$postId        = (int)   ($_POST['id']         ?? 0);
$postName      = trim(    $_POST['name']        ?? '');
$postIntroText = trim(    $_POST['intro_text']  ?? '');
$postNotes     = trim(    $_POST['notes']       ?? '');
$postItems     = [];
foreach ((array)($_POST['items'] ?? []) as $item) {
    $desc = trim($item['description'] ?? '');
    if ($desc !== '') {
        $postItems[] = [
            'description' => $desc,
            'quantity'    => (float)($item['quantity']   ?? 1),
            'unit_price'  => (float)($item['unit_price'] ?? 0),
            'sort_order'  => (int)  ($item['sort_order'] ?? 0),
        ];
    }
}
usort($postItems, fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);
$reopenModal = ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save' && !empty($errors));

try {
    $templates = $proposalService->getTemplatesWithItems();
} catch (Exception $e) {
    $templates = [];
    $errors[]  = 'Could not load templates — run sql/migrate_proposals.sql first. (' . $e->getMessage() . ')';
}
$flashMsg = flash('success');

// Build id-keyed map for JS
$templateMap = [];
foreach ($templates as $t) {
    $templateMap[$t['id']] = $t;
}

$pageTitle = 'Proposal Templates — MediaBuy';
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
                    <th>Line Items</th>
                    <th>Created By</th>
                    <th>Last Updated</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($templates as $t): ?>
            <tr>
                <td class="fw-semibold"><?= h($t['name']) ?></td>
                <td class="small text-muted">
                    <?php if (!empty($t['items'])): ?>
                        <?= count($t['items']) ?> item<?= count($t['items']) !== 1 ? 's' : '' ?>
                        <span class="text-muted">—
                            $<?= number_format(array_sum(array_map(fn($i) => $i['quantity'] * $i['unit_price'], $t['items'])), 2) ?>
                        </span>
                    <?php else: ?>
                        No items
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
                    <div class="row g-3 mb-3">
                        <div class="col-md-12">
                            <label for="tmplName" class="form-label fw-semibold">
                                Template Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="tmplName" name="name" required
                                   value="<?= h($postName) ?>"
                                   placeholder="e.g. Standard Media Buy Proposal">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="tmplIntro" class="form-label fw-semibold">Introduction Text</label>
                        <textarea class="form-control" id="tmplIntro" name="intro_text" rows="5"
                                  placeholder="Opening statement or boilerplate intro that will pre-fill the proposal…"><?= h($postIntroText) ?></textarea>
                    </div>

                    <!-- Line Items -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-semibold mb-0">Default Line Items</label>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    onclick="addTemplateItem()">
                                <i class="bi bi-plus-circle me-1"></i>Add Item
                            </button>
                        </div>
                        <div class="table-responsive border rounded">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:30px;"></th>
                                        <th>Description</th>
                                        <th style="width:80px;">Qty</th>
                                        <th style="width:140px;">Unit Price</th>
                                        <th style="width:35px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="tmplItemsBody">
                                <?php foreach ($postItems as $i => $item): ?>
                                <tr class="tmpl-item-row">
                                    <td class="tmpl-drag text-muted ps-2" style="cursor:grab;vertical-align:middle;">
                                        <i class="bi bi-grip-vertical"></i>
                                    </td>
                                    <td>
                                        <input type="text" name="items[<?= $i ?>][description]"
                                               class="form-control form-control-sm" placeholder="Description" required
                                               value="<?= h($item['description']) ?>">
                                        <input type="hidden" name="items[<?= $i ?>][sort_order]"
                                               class="tmpl-sort" value="<?= $i ?>">
                                    </td>
                                    <td>
                                        <input type="number" name="items[<?= $i ?>][quantity]"
                                               class="form-control form-control-sm"
                                               value="<?= h($item['quantity']) ?>" min="0" step="0.01">
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">$</span>
                                            <input type="number" name="items[<?= $i ?>][unit_price]"
                                                   class="form-control"
                                                   value="<?= h($item['unit_price']) ?>" min="0" step="0.01">
                                        </div>
                                    </td>
                                    <td style="vertical-align:middle;">
                                        <button type="button" class="btn btn-sm btn-link text-danger p-0"
                                                onclick="removeTmplItem(this)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="form-text">Drag to reorder. Prices are defaults — editable in each proposal.</div>
                    </div>

                    <div>
                        <label for="tmplNotes" class="form-label fw-semibold">Default Notes / Terms</label>
                        <textarea class="form-control" id="tmplNotes" name="notes" rows="3"
                                  placeholder="Payment terms, disclaimers… (optional)"><?= h($postNotes) ?></textarea>
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

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
const TEMPLATE_MAP = <?= json_encode($templateMap) ?>;

// ── Template modal item counter ───────────────────────────────────────────────
let tmplCounter = <?= max(count($postItems), 0) ?>;

// ── SortableJS inside modal ───────────────────────────────────────────────────
let tmplSortable = null;
document.getElementById('templateModal').addEventListener('shown.bs.modal', function () {
    if (!tmplSortable) {
        tmplSortable = Sortable.create(document.getElementById('tmplItemsBody'), {
            handle:    '.tmpl-drag',
            animation: 150,
            onEnd:     updateTmplSortOrders,
        });
    }
});

function updateTmplSortOrders() {
    document.querySelectorAll('#tmplItemsBody .tmpl-item-row').forEach((tr, i) => {
        const inp = tr.querySelector('.tmpl-sort');
        if (inp) inp.value = i;
    });
}

// ── Open / populate modal ─────────────────────────────────────────────────────
function openModal() {
    document.getElementById('modalTitle').innerHTML =
        '<i class="bi bi-file-earmark-text me-2 text-primary"></i>New Template';
    document.getElementById('tmplId').value    = '0';
    document.getElementById('tmplName').value  = '';
    document.getElementById('tmplIntro').value = '';
    document.getElementById('tmplNotes').value = '';
    document.getElementById('tmplItemsBody').innerHTML = '';
    tmplCounter = 0;
    addTemplateItem(); // start with one empty row
    bootstrap.Modal.getOrCreateInstance(document.getElementById('templateModal')).show();
}

function editTemplate(id) {
    const t = TEMPLATE_MAP[id];
    if (!t) return;

    document.getElementById('modalTitle').innerHTML =
        '<i class="bi bi-pencil me-2 text-primary"></i>Edit Template';
    document.getElementById('tmplId').value    = t.id;
    document.getElementById('tmplName').value  = t.name       || '';
    document.getElementById('tmplIntro').value = t.intro_text || '';
    document.getElementById('tmplNotes').value = t.notes      || '';

    document.getElementById('tmplItemsBody').innerHTML = '';
    tmplCounter = 0;
    if (t.items && t.items.length) {
        t.items.forEach(item => addTemplateItem(item.description, item.quantity, item.unit_price));
    } else {
        addTemplateItem();
    }
    bootstrap.Modal.getOrCreateInstance(document.getElementById('templateModal')).show();
}

function addTemplateItem(description, quantity, unitPrice) {
    const idx = tmplCounter++;
    const tr  = document.createElement('tr');
    tr.className = 'tmpl-item-row';
    tr.innerHTML = `
        <td class="tmpl-drag text-muted ps-2" style="cursor:grab;vertical-align:middle;">
            <i class="bi bi-grip-vertical"></i>
        </td>
        <td>
            <input type="text" name="items[${idx}][description]"
                   class="form-control form-control-sm" placeholder="Description" required>
            <input type="hidden" name="items[${idx}][sort_order]" class="tmpl-sort" value="${idx}">
        </td>
        <td>
            <input type="number" name="items[${idx}][quantity]"
                   class="form-control form-control-sm" value="1" min="0" step="0.01">
        </td>
        <td>
            <div class="input-group input-group-sm">
                <span class="input-group-text">$</span>
                <input type="number" name="items[${idx}][unit_price]"
                       class="form-control" value="0.00" min="0" step="0.01">
            </div>
        </td>
        <td style="vertical-align:middle;">
            <button type="button" class="btn btn-sm btn-link text-danger p-0"
                    onclick="removeTmplItem(this)">
                <i class="bi bi-trash"></i>
            </button>
        </td>`;

    tr.querySelector('[name$="[description]"]').value = description || '';
    tr.querySelector('[name$="[quantity]"]').value     = parseFloat(quantity)  || 1;
    tr.querySelector('[name$="[unit_price]"]').value   = parseFloat(unitPrice) || 0;

    document.getElementById('tmplItemsBody').appendChild(tr);
}

function removeTmplItem(btn) {
    const rows = document.querySelectorAll('#tmplItemsBody .tmpl-item-row');
    if (rows.length <= 1) {
        const tr = btn.closest('tr');
        tr.querySelector('[name$="[description]"]').value = '';
        tr.querySelector('[name$="[quantity]"]').value    = 1;
        tr.querySelector('[name$="[unit_price]"]').value  = 0;
        return;
    }
    btn.closest('tr').remove();
}

function confirmDelete(id, name) {
    document.getElementById('deleteId').value          = id;
    document.getElementById('deleteName').textContent  = name;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteModal')).show();
}

// Update sort orders before template form submits
document.getElementById('templateForm').addEventListener('submit', updateTmplSortOrders);

<?php if ($reopenModal && $postId > 0): ?>
window.addEventListener('load', function () {
    editTemplate(<?= $postId ?>);
});
<?php elseif ($reopenModal): ?>
window.addEventListener('load', function () {
    // Reopen with POST data after validation error
    document.getElementById('modalTitle').innerHTML =
        '<i class="bi bi-file-earmark-text me-2 text-primary"></i>New Template';
    <?php if (!empty($postItems)): ?>
    document.getElementById('tmplItemsBody').innerHTML = '';
    tmplCounter = 0;
    <?php foreach ($postItems as $pi): ?>
    addTemplateItem(<?= json_encode($pi['description']) ?>, <?= (float)$pi['quantity'] ?>, <?= (float)$pi['unit_price'] ?>);
    <?php endforeach; ?>
    <?php endif; ?>
    bootstrap.Modal.getOrCreateInstance(document.getElementById('templateModal')).show();
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
