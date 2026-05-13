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

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vendorId   = (int)($_POST['vendor_id'] ?? 0);
    $vendorName = trim($_POST['vendor_name_manual'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');

    // If vendor_id was selected from dropdown, look up their name
    if ($vendorId > 0) {
        $allVendors = $svc->getAllVendors();
        $vendorMap  = array_column($allVendors, 'company_name', 'id');
        $vendorName = $vendorMap[$vendorId] ?? $vendorName;
    }

    if (empty($vendorName)) {
        $errors[] = 'Please select a vendor or enter a vendor name.';
    }

    if (empty($errors)) {
        $result = $svc->saveVendorReply($id, $vendorId, $vendorName, $notes, $_FILES['reply_files'] ?? []);
        if ($result['success']) {
            flash('success', 'Vendor reply from ' . $vendorName . ' logged successfully.');
            redirect('/print-bids/view.php?id=' . $id . '#vendor-replies');
        } else {
            $errors[] = $result['message'];
        }
    }
}

// Build vendor list from the bid's printer + signage vendor IDs
$allVendors  = $svc->getAllVendors();
$vendorMap   = array_column($allVendors, 'company_name', 'id');
$bidVendorIds = array_unique(array_merge(
    array_map('intval', $bid['printer_vendor_ids']),
    array_map('intval', $bid['signage_vendor_ids'])
));
$bidVendors = array_values(array_filter($allVendors, fn($v) => in_array((int)$v['id'], $bidVendorIds, true)));

$pageTitle = 'Log Vendor Reply — Print Bid #' . $bid['id'];
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.reply-card { border: 2px solid #b02a37; border-radius: .5rem; overflow: hidden; }
.reply-card-header { background: #b02a37; color: #fff; padding: .75rem 1.25rem; font-weight: 700; }
</style>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="/print-bids/index.php">Print Bids</a></li>
        <li class="breadcrumb-item"><a href="/print-bids/view.php?id=<?= $bid['id'] ?>">Bid #<?= $bid['id'] ?></a></li>
        <li class="breadcrumb-item active">Log Vendor Reply</li>
    </ol>
</nav>

<div class="d-flex align-items-center mb-4 gap-3">
    <div>
        <h2 class="mb-0"><i class="bi bi-reply-fill me-2 text-danger"></i>Log Vendor Reply</h2>
        <p class="text-muted mb-0 small">
            <?= h($bid['client_name']) ?><?= $bid['title'] ? ' — ' . h($bid['title']) : '' ?>
        </p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <?= implode('<br>', array_map('h', $errors)) ?>
    </div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="reply-card mb-4">
            <div class="reply-card-header">
                <i class="bi bi-reply-fill me-2"></i>Vendor Pricing Reply
            </div>
            <div class="p-4">
                <form method="post" enctype="multipart/form-data" novalidate>

                    <!-- Vendor -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Which vendor is replying?</label>
                        <?php if (!empty($bidVendors)): ?>
                        <select name="vendor_id" class="form-select mb-2" id="vendorSelect"
                                onchange="handleVendorSelect(this.value)">
                            <option value="">— Select a vendor —</option>
                            <?php foreach ($bidVendors as $v): ?>
                            <option value="<?= (int)$v['id'] ?>"><?= h($v['company_name']) ?></option>
                            <?php endforeach; ?>
                            <option value="0">Other / not in list</option>
                        </select>
                        <?php else: ?>
                        <input type="hidden" name="vendor_id" value="0">
                        <?php endif; ?>
                        <div id="manualVendorWrap" class="<?= empty($bidVendors) ? '' : 'd-none' ?>">
                            <input type="text" name="vendor_name_manual" class="form-control"
                                   placeholder="Vendor name" value="<?= h($_POST['vendor_name_manual'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Pricing Files -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-paperclip me-1"></i>Pricing Attachments
                        </label>
                        <input type="file" name="reply_files[]" class="form-control" multiple
                               accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.doc,.docx">
                        <div class="form-text">Upload the vendor's price quote files (PDF, Excel, Word, or images). Multiple files allowed.</div>
                    </div>

                    <!-- Notes -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Notes <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea name="notes" class="form-control" rows="3"
                                  placeholder="e.g. Lead time 5 days, price valid 30 days…"><?= h($_POST['notes'] ?? '') ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-check-circle-fill me-1"></i>Log Reply &amp; Mark Replied
                        </button>
                        <a href="/print-bids/view.php?id=<?= $bid['id'] ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Existing replies (if any) -->
        <?php if (!empty($bid['vendor_replies'])): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light small fw-semibold text-muted">
                <i class="bi bi-clock-history me-1"></i>Previous Replies (<?= count($bid['vendor_replies']) ?>)
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($bid['vendor_replies'] as $reply): ?>
                <li class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <strong><?= h($reply['vendor_name']) ?></strong>
                        <span class="text-muted small"><?= date('M j, Y g:ia', strtotime($reply['replied_at'])) ?></span>
                    </div>
                    <?php if (!empty($reply['notes'])): ?>
                        <p class="mb-1 text-muted small"><?= nl2br(h($reply['notes'])) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($reply['attachments'])): ?>
                    <div class="d-flex flex-wrap gap-2 mt-1">
                        <?php foreach ($reply['attachments'] as $att): ?>
                            <?php $icon = str_contains($att['type'], 'pdf') ? 'bi-file-earmark-pdf text-danger' : (str_contains($att['type'], 'sheet') || str_contains($att['type'], 'excel') ? 'bi-file-earmark-excel text-success' : 'bi-file-earmark text-secondary'); ?>
                            <a href="/print-bids/download.php?f=<?= urlencode($att['path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                <i class="bi <?= $icon ?> me-1"></i><?= h($att['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function handleVendorSelect(val) {
    const wrap = document.getElementById('manualVendorWrap');
    wrap.classList.toggle('d-none', val !== '0');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
