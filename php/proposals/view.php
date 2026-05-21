<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';
requireRole(['admin', 'buyer']);

$proposalService = new ProposalService();

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    redirect('/proposals/index.php');
}

$data = $proposalService->getProposal($id);
if (!$data) {
    flash('error', 'Proposal not found.');
    redirect('/proposals/index.php');
}

$proposal = $data['proposal'];
$blocks   = $data['blocks'];
$flashMsg = flash('success');
$errorMsg = flash('error');

$agSvc    = new AgencyAgreementService();
$branding = $agSvc->getBranding();

// Convert a stored logo URL (/uploads/logos/...) to a base64 data URI for display.
// /uploads/ may not be a mapped web directory on this server, so we read from disk.
$logoDataUri = function(?string $url): string {
    if (empty($url)) return '';
    $root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? realpath(__DIR__ . '/..'), '/\\');
    $path = $root . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $url), DIRECTORY_SEPARATOR);
    if (!file_exists($path) || !is_readable($path)) return '';
    $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = match($ext) {
        'jpg','jpeg' => 'image/jpeg',
        'png'        => 'image/png',
        'gif'        => 'image/gif',
        'webp'       => 'image/webp',
        'svg'        => 'image/svg+xml',
        default      => 'image/png',
    };
    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
};

$agencyLogoSrc = $logoDataUri($branding['logo_url'] ?? '');
$clientLogoSrc = $logoDataUri($proposal['client_logo_url'] ?? '');

// Handle status change POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_status'])) {
    $newStatus = trim($_POST['new_status'] ?? '');
    try {
        $proposalService->updateStatus($id, $newStatus);
        flash('success', 'Status updated to "' . (ProposalService::STATUS_LABELS[$newStatus] ?? $newStatus) . '".');
        redirect('/proposals/view.php?id=' . $id);
    } catch (Exception $e) {
        $errorMsg = 'Could not update status: ' . $e->getMessage();
    }
}

// Handle delete POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_confirm'])) {
    try {
        $proposalService->deleteProposal($id);
        flash('success', 'Proposal deleted.');
        redirect('/proposals/index.php');
    } catch (Exception $e) {
        $errorMsg = 'Could not delete: ' . $e->getMessage();
    }
}

$statusColor = ProposalService::STATUS_COLORS[$proposal['status']] ?? 'secondary';
$statusLabel = ProposalService::STATUS_LABELS[$proposal['status']] ?? $proposal['status'];

function renderViewItemTable(array $items): void {
    $subtotal = array_sum(array_map(fn($i) => (float)$i['total_price'], $items));
    ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-0">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr style="background:#0f172a;color:#fff;">
                        <th>Description</th>
                        <th class="text-center" style="width:80px;">Qty</th>
                        <th class="text-end" style="width:130px;">Unit Price</th>
                        <th class="text-end" style="width:130px;">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= h($item['description']) ?></td>
                    <td class="text-center"><?= rtrim(rtrim(number_format((float)$item['quantity'], 2), '0'), '.') ?></td>
                    <td class="text-end">$<?= number_format((float)$item['unit_price'], 2) ?></td>
                    <td class="text-end fw-semibold">$<?= number_format((float)$item['total_price'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <?php if (count($items) > 1): ?>
                <tfoot>
                    <tr class="table-light">
                        <td colspan="3" class="text-end text-muted small">Subtotal</td>
                        <td class="text-end fw-semibold">$<?= number_format($subtotal, 2) ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
    <?php
}

$pageTitle = h($proposal['title']) . ' — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($flashMsg): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle-fill me-2"></i><?= h($flashMsg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($errorMsg): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= h($errorMsg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Proposal Cover Header -->
<div class="card border-0 shadow-sm mb-4 overflow-hidden">
    <!-- Dark top bar with logos -->
    <div style="background:#0f172a;padding:24px 32px;">
        <table style="width:100%;border-collapse:collapse;">
        <tr>
            <td style="vertical-align:middle;">
                <?php if ($agencyLogoSrc): ?>
                <img src="<?= $agencyLogoSrc ?>" alt="Agency Logo"
                     style="max-height:50px;max-width:180px;object-fit:contain;">
                <?php else: ?>
                <span style="color:#fff;font-size:1.25rem;font-weight:700;"><?= h($branding['name']) ?></span>
                <?php endif; ?>
            </td>
            <td style="text-align:right;vertical-align:middle;">
                <span style="color:#fff;font-size:2rem;font-weight:800;letter-spacing:.12em;opacity:.9;">PROPOSAL</span>
            </td>
        </tr>
        </table>
    </div>
    <!-- Prepared for / by row -->
    <div class="row g-0 border-bottom">
        <div class="col-md-6 p-4 border-end">
            <div class="text-uppercase small fw-bold text-muted mb-2" style="letter-spacing:.08em;font-size:.7rem;">Prepared For</div>
            <?php if ($clientLogoSrc): ?>
            <img src="<?= $clientLogoSrc ?>" alt="Client Logo"
                 style="max-height:48px;max-width:160px;object-fit:contain;margin-bottom:10px;display:block;">
            <?php endif; ?>
            <div class="fw-bold fs-5"><?= h($proposal['client_name'] ?? '—') ?></div>
        </div>
        <div class="col-md-6 p-4">
            <div class="text-uppercase small fw-bold text-muted mb-2" style="letter-spacing:.08em;font-size:.7rem;">Prepared By</div>
            <div class="fw-bold"><?= h($branding['name']) ?></div>
            <div class="small text-muted"><?= h($branding['address']) ?>, <?= h($branding['city_state_zip']) ?></div>
            <div class="small text-muted"><?= h($branding['phone']) ?></div>
        </div>
    </div>
    <!-- Meta bar -->
    <div class="px-4 py-3 bg-light d-flex flex-wrap gap-4 align-items-center justify-content-between">
        <div>
            <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.07em;">Proposal</div>
            <div class="fw-bold"><?= h($proposal['title']) ?></div>
        </div>
        <div class="d-flex gap-4 flex-wrap">
            <?php if ($proposal['valid_until']): ?>
            <div class="text-end">
                <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.07em;">Valid Until</div>
                <div class="fw-semibold <?= (strtotime($proposal['valid_until']) < time()) ? 'text-danger' : '' ?>">
                    <?= h(date('M j, Y', strtotime($proposal['valid_until']))) ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="text-end">
                <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.07em;">Date</div>
                <div class="fw-semibold"><?= h(date('M j, Y', strtotime($proposal['created_at']))) ?></div>
            </div>
            <div class="text-end">
                <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.07em;">Status</div>
                <div><span class="badge bg-<?= $statusColor ?> fs-6"><?= h($statusLabel) ?></span></div>
            </div>
        </div>
    </div>
</div>

<!-- Breadcrumb (below header) -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="/proposals/index.php">Proposals</a></li>
        <li class="breadcrumb-item active"><?= h($proposal['title']) ?></li>
    </ol>
</nav>

<div class="row g-4">

    <!-- ── Main content ─────────────────────────────────────────────────────── -->
    <div class="col-lg-8">

        <?php
        $itemBuffer = [];
        foreach ($blocks as $b):
            if ($b['block_type'] === 'item') {
                $itemBuffer[] = $b;
            } else {
                if (!empty($itemBuffer)) {
                    renderViewItemTable($itemBuffer);
                    $itemBuffer = [];
                }
                if ($b['block_type'] === 'text'):
        ?>
        <div class="mb-4" style="line-height:1.8;">
            <?= $b['content'] /* Summernote HTML — rendered raw */ ?>
        </div>
        <?php       elseif ($b['block_type'] === 'signature'): ?>
        <div class="mb-5 pt-3">
            <div class="row">
                <div class="col-md-7">
                    <div class="border-bottom border-dark border-2 mb-2" style="min-height:52px;"></div>
                    <div class="d-flex justify-content-between small text-muted">
                        <span><?= h($b['sig_label'] ?: 'Authorized Signature') ?></span>
                        <span>Date</span>
                    </div>
                </div>
            </div>
        </div>
        <?php
                endif;
            }
        endforeach;
        // Flush remaining item blocks
        if (!empty($itemBuffer)) {
            renderViewItemTable($itemBuffer);
        }
        ?>

        <?php if (empty($blocks)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-file-earmark-text fs-2 d-block mb-2 opacity-50"></i>
            <div>No content blocks yet.
                <a href="/proposals/create.php?id=<?= $id ?>">Edit this proposal</a> to add content.
            </div>
        </div>
        <?php endif; ?>

        <!-- Grand total -->
        <?php if ((float)$proposal['total_amount'] > 0): ?>
        <div class="text-end py-3 px-4 rounded mb-4" style="background:#0f172a;color:#fff;">
            Grand Total <span class="fs-3 fw-bold ms-3">$<?= number_format((float)$proposal['total_amount'], 2) ?></span>
        </div>
        <?php endif; ?>

    </div>

    <!-- ── Sidebar ───────────────────────────────────────────────────────────── -->
    <div class="col-lg-4">

        <!-- Actions -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-lightning me-2 text-primary"></i>Actions
                </h5>
            </div>
            <div class="card-body d-grid gap-2">
                <a href="/proposals/proposal-pdf.php?id=<?= $id ?>" class="btn btn-outline-dark" target="_blank">
                    <i class="bi bi-file-earmark-pdf me-1"></i>Download PDF
                </a>
                <a href="/proposals/create.php?id=<?= $id ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-pencil me-1"></i>Edit Proposal
                </a>
                <button type="button" class="btn btn-outline-danger" onclick="confirmDelete()">
                    <i class="bi bi-trash me-1"></i>Delete Proposal
                </button>
            </div>
        </div>

        <!-- Change Status -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-arrow-left-right me-2 text-primary"></i>Update Status
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="/proposals/view.php?id=<?= $id ?>">
                    <div class="mb-3">
                        <select class="form-select form-select-sm" name="new_status">
                            <?php foreach (ProposalService::STATUS_LABELS as $sv => $sl): ?>
                            <option value="<?= h($sv) ?>" <?= $proposal['status'] === $sv ? 'selected' : '' ?>>
                                <?= h($sl) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                        <i class="bi bi-check-circle me-1"></i>Update Status
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST" action="/proposals/view.php?id=<?= $id ?>">
                <input type="hidden" name="delete_confirm" value="1">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Proposal?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body small">
                    Delete "<strong><?= h($proposal['title']) ?></strong>"? This cannot be undone.
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

<script>
function confirmDelete() {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
