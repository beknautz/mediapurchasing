<?php
require_once __DIR__ . '/../bootstrap.php';
requireRole(['admin', 'buyer']);

$svc = new PrintBidService();
$id  = (int)($_GET['id'] ?? 0);

if ($id <= 0) redirect('/print-bids/index.php');

$bid = $svc->getBid($id);
if (empty($bid)) {
    flash('success', 'Print bid not found.');
    redirect('/print-bids/index.php');
}

$printItems   = array_values(array_filter($bid['items'], fn($i) => $i['type'] === 'print'));
$signageItems = array_values(array_filter($bid['items'], fn($i) => $i['type'] === 'signage'));

// Load vendor names for printer/signage IDs
$allVendors   = $svc->getAllVendors();
$vendorMap    = array_column($allVendors, 'company_name', 'id');

$printerNames = array_filter(array_map(fn($vid) => $vendorMap[$vid] ?? null, $bid['printer_vendor_ids']));
$signageNames = array_filter(array_map(fn($vid) => $vendorMap[$vid] ?? null, $bid['signage_vendor_ids']));

$statusColors = ['draft' => 'secondary', 'sent' => 'primary', 'approved' => 'success', 'rejected' => 'danger'];
$sc = $statusColors[$bid['status']] ?? 'secondary';

$pageTitle = 'Print Bid #' . $bid['id'] . ' — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.section-card { border: 1px solid #dee2e6; border-radius: .5rem; overflow: hidden; margin-bottom: 1.25rem; }
.section-card-header { background: #f8f9fa; padding: .65rem 1rem; border-bottom: 1px solid #dee2e6;
                        font-weight: 700; font-size: .8rem; text-transform: uppercase; color: #b02a37; letter-spacing:.04em; }
.section-card-body   { padding: 1rem; }
.items-table th { background: #f8f9fa; font-size: .78rem; text-transform: uppercase; letter-spacing: .03em; }
</style>

<!-- ── Breadcrumb + actions ── -->
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small mb-1">
                <li class="breadcrumb-item"><a href="/print-bids/index.php">Print Bids</a></li>
                <li class="breadcrumb-item active">Bid #<?= $bid['id'] ?></li>
            </ol>
        </nav>
        <h2 class="mb-0">
            <i class="bi bi-printer me-2 text-primary"></i>
            <?= h($bid['client_name']) ?>
            <?php if ($bid['title']): ?><small class="text-muted fs-5 ms-2"><?= h($bid['title']) ?></small><?php endif; ?>
        </h2>
        <span class="badge bg-<?= $sc ?> mt-1"><?= h(ucfirst($bid['status'])) ?></span>
    </div>
    <div class="d-flex gap-2 flex-shrink-0">
        <a href="/print-bids/create.php?id=<?= $bid['id'] ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-pencil-square me-1"></i>Edit
        </a>
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer me-1"></i>Print / Save PDF
        </button>
    </div>
</div>

<?php $msg = flash('success'); if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill me-2"></i><?= h($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php $warn = flash('warning'); if ($warn): ?>
    <div class="alert alert-warning alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $warn ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">

        <!-- ── Details ── -->
        <div class="section-card">
            <div class="section-card-header"><i class="bi bi-info-circle me-1"></i>Bid Details</div>
            <div class="section-card-body">
                <div class="row g-3">
                    <div class="col-sm-4">
                        <div class="text-muted small">Client</div>
                        <div class="fw-semibold"><?= h($bid['client_name']) ?></div>
                    </div>
                    <div class="col-sm-4">
                        <div class="text-muted small">Status</div>
                        <span class="badge bg-<?= $sc ?>"><?= h(ucfirst($bid['status'])) ?></span>
                    </div>
                    <div class="col-sm-4">
                        <div class="text-muted small">Created</div>
                        <div><?= date('M j, Y', strtotime($bid['created_at'])) ?></div>
                    </div>
                    <?php if (!empty($printerNames)): ?>
                    <div class="col-sm-6">
                        <div class="text-muted small">Printer Vendors</div>
                        <div><?= h(implode(', ', $printerNames)) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($signageNames)): ?>
                    <div class="col-sm-6">
                        <div class="text-muted small">Signage Vendors</div>
                        <div><?= h(implode(', ', $signageNames)) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($bid['notes'])): ?>
                    <div class="col-12">
                        <div class="text-muted small">Notes</div>
                        <div class="fst-italic"><?= nl2br(h($bid['notes'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ── Print Items ── -->
        <?php if (!empty($printItems)): ?>
        <div class="section-card">
            <div class="section-card-header"><i class="bi bi-printer me-1"></i>Printing Job Items</div>
            <div class="section-card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 items-table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Size</th>
                                <th>Paper</th>
                                <th>Ink</th>
                                <th>Quantities</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($printItems as $item): ?>
                            <tr>
                                <td><?= h($item['description'] ?? '—') ?></td>
                                <td><code><?= h($item['size'] ?? '') ?></code></td>
                                <td><?= h($item['paper'] ?? '—') ?></td>
                                <td><?= h($item['ink_spec'] ?? '—') ?></td>
                                <td class="small text-nowrap">
                                    <?php
                                    $qtys = array_filter([$item['qty_1'],$item['qty_2'],$item['qty_3'],$item['qty_4'],$item['qty_5']]);
                                    echo h(implode(' / ', $qtys)) ?: '—';
                                    ?>
                                </td>
                                <td class="text-muted small"><?= h($item['notes'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Signage Items ── -->
        <?php if (!empty($signageItems)): ?>
        <div class="section-card" style="border-left: 4px solid #b02a37;">
            <div class="section-card-header" style="color:#b02a37;">
                <i class="bi bi-sign-stop me-1"></i>Signage Job Items
            </div>
            <div class="section-card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 items-table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Size</th>
                                <th>Material</th>
                                <th>Quantity</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($signageItems as $item): ?>
                            <tr>
                                <td><?= h($item['description'] ?? '—') ?></td>
                                <td><code><?= h($item['size'] ?? '') ?></code></td>
                                <td><?= h($item['material'] ?? '—') ?></td>
                                <td><?= h($item['qty_1'] ?? '—') ?></td>
                                <td class="text-muted small"><?= h($item['notes'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($printItems) && empty($signageItems)): ?>
            <div class="alert alert-info">No job items on this bid yet. <a href="/print-bids/create.php?id=<?= $bid['id'] ?>">Edit bid</a> to add items.</div>
        <?php endif; ?>

    </div><!-- /col-lg-8 -->

    <!-- ── Sidebar ── -->
    <div class="col-lg-4">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-dark text-white fw-semibold small">
                <i class="bi bi-lightning-charge me-1"></i>Quick Actions
            </div>
            <div class="card-body d-grid gap-2">
                <a href="/print-bids/create.php?id=<?= $bid['id'] ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-pencil-square me-1"></i>Edit Bid
                </a>
                <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-printer me-1"></i>Print / Save PDF
                </button>
                <hr class="my-1">
                <a href="/print-bids/index.php" class="btn btn-link btn-sm text-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back to List
                </a>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-light small fw-semibold text-muted">
                <i class="bi bi-clock-history me-1"></i>Record Info
            </div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Bid #</span>
                    <strong><?= $bid['id'] ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Status</span>
                    <span class="badge bg-<?= $sc ?>"><?= h(ucfirst($bid['status'])) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Created</span>
                    <span><?= date('M j, Y g:ia', strtotime($bid['created_at'])) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Updated</span>
                    <span><?= date('M j, Y g:ia', strtotime($bid['updated_at'])) ?></span>
                </li>
                <?php if ($bid['created_by_name']): ?>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Created By</span>
                    <span><?= h($bid['created_by_name']) ?></span>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
