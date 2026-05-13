<?php
/**
 * print-bids/vendor-reply.php
 * Public vendor pricing submission page — no login required.
 * Vendors access this via a unique token link in their bid request email.
 */
require_once __DIR__ . '/../bootstrap.php';
// NO requireRole() — this is intentionally public

$svc   = new PrintBidService();
$token = trim($_GET['token'] ?? '');

if (strlen($token) !== 40) {
    http_response_code(404);
    die('Invalid link. Please contact us for assistance.');
}

$ctx = $svc->getBidByVendorToken($token);

if (empty($ctx)) {
    http_response_code(404);
    die('This link is invalid or has expired. Please contact us for assistance.');
}

$bid          = $ctx['bid'];
$vendorName   = $ctx['vendor_name'];
$vendorId     = $ctx['vendor_id'];
$priorReplies = $ctx['prior_replies'] ?? [];   // previous submissions from this vendor
$submitted    = false;
$errors       = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notes = trim($_POST['notes'] ?? '');
    $result = $svc->saveVendorReplyByToken($token, $notes, $_FILES['reply_files'] ?? []);
    if ($result['success']) {
        $submitted = true;
    } else {
        $errors[] = $result['message'];
    }
}

$printItems   = array_values(array_filter($bid['items'] ?? [], fn($i) => $i['type'] === 'print'));
$signageItems = array_values(array_filter($bid['items'] ?? [], fn($i) => $i['type'] === 'signage'));
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Submit Pricing — <?= htmlspecialchars($bid['client_name'], ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f4f6fa; }
        .brand-bar { background: #b02a37; color: #fff; padding: .75rem 1.5rem; }
        .brand-bar .brand { font-weight: 800; font-size: 1.1rem; letter-spacing: .02em; }
        .card-header-red { background: #b02a37; color: #fff; }
        .items-table th { background: #f8f9fa; font-size: .78rem; text-transform: uppercase; letter-spacing: .03em; }
        .success-wrap { text-align: center; padding: 3rem 1rem; }
        .success-wrap .icon { font-size: 4rem; color: #198754; }
    </style>
</head>
<body>

<!-- Brand bar -->
<div class="brand-bar d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-printer-fill fs-5"></i>
    <span class="brand">MediaBuy</span>
    <span class="ms-2 opacity-75 small">Print Bid Pricing Submission</span>
</div>

<div class="container" style="max-width: 780px;">

    <?php if ($submitted): ?>
    <!-- ── Success state ── -->
    <div class="card shadow border-0">
        <div class="card-body success-wrap">
            <i class="bi bi-check-circle-fill icon d-block mb-3"></i>
            <h3 class="fw-bold">Pricing Submitted!</h3>
            <p class="text-muted">Thank you, <strong><?= htmlspecialchars($vendorName, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
            <p class="text-muted">Your pricing for <strong><?= htmlspecialchars($bid['client_name'], ENT_QUOTES, 'UTF-8') ?></strong> has been received.
            We'll be in touch soon.</p>
        </div>
    </div>

    <?php else: ?>
    <!-- ── Job summary ── -->
    <div class="card shadow border-0 mb-4">
        <div class="card-header card-header-red">
            <i class="bi bi-file-earmark-text me-1"></i>
            Bid Request — <?= htmlspecialchars($bid['client_name'], ENT_QUOTES, 'UTF-8') ?>
            <?php if (!empty($bid['title'])): ?>
                <small class="opacity-75 ms-2"><?= htmlspecialchars($bid['title'], ENT_QUOTES, 'UTF-8') ?></small>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <p class="mb-2">Hello <strong><?= htmlspecialchars($vendorName, ENT_QUOTES, 'UTF-8') ?></strong>, please review the items below and submit your pricing.</p>

            <?php if (!empty($printItems)): ?>
            <h6 class="text-uppercase text-muted small fw-bold mt-3 mb-2"><i class="bi bi-printer me-1"></i>Printing Items</h6>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered items-table mb-0">
                    <thead><tr><th>Description</th><th>Size</th><th>Paper</th><th>Ink</th><th>Quantities</th><th>Notes</th></tr></thead>
                    <tbody>
                    <?php foreach ($printItems as $item): ?>
                        <?php $qtys = array_filter([$item['qty_1'],$item['qty_2'],$item['qty_3'],$item['qty_4'],$item['qty_5']]); ?>
                        <tr>
                            <td><?= htmlspecialchars($item['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><code><?= htmlspecialchars($item['size'] ?? '', ENT_QUOTES, 'UTF-8') ?></code></td>
                            <td><?= htmlspecialchars($item['paper'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($item['ink_spec'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="small"><?= htmlspecialchars(implode(' / ', $qtys), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-muted small"><?= htmlspecialchars($item['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if (!empty($signageItems)): ?>
            <h6 class="text-uppercase text-muted small fw-bold mt-3 mb-2"><i class="bi bi-sign-stop me-1"></i>Signage Items</h6>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-bordered items-table mb-0">
                    <thead><tr><th>Description</th><th>Size</th><th>Material</th><th>Quantity</th><th>Notes</th></tr></thead>
                    <tbody>
                    <?php foreach ($signageItems as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><code><?= htmlspecialchars($item['size'] ?? '', ENT_QUOTES, 'UTF-8') ?></code></td>
                            <td><?= htmlspecialchars($item['material'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($item['qty_1'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-muted small"><?= htmlspecialchars($item['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if (!empty($bid['notes'])): ?>
            <div class="alert alert-light border small">
                <strong>Additional Notes:</strong> <?= nl2br(htmlspecialchars($bid['notes'], ENT_QUOTES, 'UTF-8')) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Resubmit notice ── -->
    <?php if (!empty($priorReplies)): ?>
    <div class="alert alert-warning d-flex gap-2 align-items-start mb-4">
        <i class="bi bi-arrow-clockwise fs-5 flex-shrink-0 mt-1"></i>
        <div>
            <strong>You've already submitted pricing</strong> — last received
            <?= date('M j, Y \a\t g:ia', strtotime(end($priorReplies)['replied_at'])) ?>.
            <br>Use the form below to send us an updated quote. Your previous submission will be kept on file.
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Submission form ── -->
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= implode('<br>', array_map(fn($e) => htmlspecialchars($e, ENT_QUOTES, 'UTF-8'), $errors)) ?></div>
    <?php endif; ?>

    <div class="card shadow border-0 mb-5">
        <div class="card-header card-header-red fw-semibold">
            <i class="bi bi-upload me-1"></i><?= !empty($priorReplies) ? 'Submit Updated Pricing' : 'Submit Your Pricing' ?>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" novalidate>

                <div class="mb-4">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-paperclip me-1"></i>Pricing Files <span class="text-danger">*</span>
                    </label>
                    <input type="file" name="reply_files[]" class="form-control" multiple required
                           accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.doc,.docx">
                    <div class="form-text">Upload your price quote (PDF, Excel, Word, or images). Multiple files allowed.</div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Notes <span class="fw-normal text-muted">(optional)</span></label>
                    <textarea name="notes" class="form-control" rows="4"
                              placeholder="Lead time, price validity, any conditions or clarifications…"><?= htmlspecialchars($_POST['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <button type="submit" class="btn btn-danger btn-lg w-100">
                    <i class="bi bi-send-fill me-2"></i>Submit Pricing
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /container -->
</body>
</html>
