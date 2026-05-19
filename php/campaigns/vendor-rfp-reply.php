<?php
/**
 * campaigns/vendor-rfp-reply.php
 * Public vendor proposal submission page for campaign RFPs.
 * No login required — vendors access via unique token link in their RFP email.
 */
require_once __DIR__ . '/../bootstrap.php';
// NO requireRole() — intentionally public

$svc   = new CampaignService();
$token = trim($_GET['token'] ?? '');

if (strlen($token) !== 40) {
    http_response_code(404);
    die('Invalid link. Please contact us for assistance.');
}

$ctx = $svc->getRfpChannelByToken($token);
if (empty($ctx)) {
    http_response_code(404);
    die('This link is invalid or has expired. Please contact us for assistance.');
}

$ch           = $ctx['channel'];              // primary channel (campaign/vendor info)
$allChannels  = $ctx['channels'];             // all channels for this vendor
$priorReplies = $ctx['prior_replies'];
$vendorName   = $ch['vendor_name'] ?: $ch['vendor_email'];
$submitted    = false;
$errors       = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notes  = trim($_POST['notes'] ?? '');
    $result = $svc->saveRfpReplyByToken($token, $notes, $_FILES['reply_files'] ?? []);
    if ($result['success']) {
        $submitted = true;
    } else {
        $errors[] = $result['message'];
    }
}

$flightStart = !empty($ch['campaign_start']) ? date('M j, Y', strtotime($ch['campaign_start'])) : 'TBD';
$flightEnd   = !empty($ch['campaign_end'])   ? date('M j, Y', strtotime($ch['campaign_end']))   : 'TBD';
$totalBudget = array_sum(array_column($allChannels, 'budget_allocated'));
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Submit Proposal — <?= $h($ch['campaign_title']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f4f6fa; }
        .brand-bar { background: #0d6efd; color: #fff; padding: .75rem 1.5rem; }
        .brand-bar .brand { font-weight: 800; font-size: 1.1rem; }
        .card-header-blue { background: #0d6efd; color: #fff; }
        .success-wrap { text-align: center; padding: 3rem 1rem; }
    </style>
</head>
<body>

<div class="brand-bar d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-megaphone-fill fs-5"></i>
    <span class="brand">MediaBuy</span>
    <span class="ms-2 opacity-75 small">RFP Proposal Submission</span>
</div>

<div class="container" style="max-width:720px;">

    <?php if ($submitted): ?>
    <div class="card shadow border-0">
        <div class="card-body success-wrap">
            <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-3"></i>
            <h3 class="fw-bold">Proposal Submitted!</h3>
            <p class="text-muted">Thank you, <strong><?= $h($vendorName) ?></strong>.</p>
            <p class="text-muted">Your proposal for <strong><?= $h($ch['campaign_title']) ?></strong>
            (<?= count($allChannels) ?> item<?= count($allChannels) !== 1 ? 's' : '' ?>) has been received. We'll be in touch soon.</p>
        </div>
    </div>

    <?php else: ?>

    <!-- Campaign summary card -->
    <div class="card shadow border-0 mb-4">
        <div class="card-header card-header-blue fw-semibold">
            <i class="bi bi-file-earmark-text me-1"></i>
            RFP — <?= $h($ch['campaign_title']) ?>
        </div>
        <div class="card-body">
            <p class="mb-3">Hello <strong><?= $h($vendorName) ?></strong>, please review the details below and submit your proposal covering all requested items.</p>
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <div class="text-muted small">Campaign</div>
                    <div class="fw-semibold"><?= $h($ch['campaign_title']) ?></div>
                </div>
                <div class="col-sm-6">
                    <div class="text-muted small">Market</div>
                    <div><?= $h($ch['campaign_market'] ?: 'Local Market') ?></div>
                </div>
                <div class="col-sm-6">
                    <div class="text-muted small">Language</div>
                    <div><?= $h(ucfirst($ch['campaign_language'] ?? 'Both')) ?></div>
                </div>
                <div class="col-sm-6">
                    <div class="text-muted small">Flight Dates</div>
                    <div><?= $h($flightStart) ?> – <?= $h($flightEnd) ?></div>
                </div>
                <?php if (!empty($ch['campaign_notes'])): ?>
                <div class="col-12">
                    <div class="text-muted small">Notes</div>
                    <div class="fst-italic small"><?= nl2br($h($ch['campaign_notes'])) ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- All requested media items for this vendor -->
            <h6 class="fw-semibold mb-2"><i class="bi bi-list-check me-1 text-primary"></i>Requested Media Items</h6>
            <table class="table table-sm table-bordered mb-0">
                <thead class="table-primary">
                    <tr>
                        <th>Media Category</th>
                        <th class="text-end">Budget</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($allChannels as $item): ?>
                <tr>
                    <td class="fw-semibold"><?= $h($item['media_category']) ?></td>
                    <td class="text-end text-success fw-semibold">$<?= number_format((float)$item['budget_allocated'], 0) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td>Total</td>
                        <td class="text-end">$<?= number_format($totalBudget, 0) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Resubmit notice -->
    <?php if (!empty($priorReplies)): ?>
    <div class="alert alert-warning d-flex gap-2 align-items-start mb-4">
        <i class="bi bi-arrow-clockwise fs-5 flex-shrink-0 mt-1"></i>
        <div>
            <strong>You've already submitted a proposal</strong> — last received
            <?= date('M j, Y \a\t g:ia', strtotime(end($priorReplies)['replied_at'])) ?>.
            <br>Use the form below to send us an updated proposal. Your previous submission is kept on file.
        </div>
    </div>
    <?php endif; ?>

    <!-- Error display -->
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <?= implode('<br>', array_map(fn($e) => $h($e), $errors)) ?>
    </div>
    <?php endif; ?>

    <!-- Submission form -->
    <div class="card shadow border-0 mb-5">
        <div class="card-header card-header-blue fw-semibold">
            <i class="bi bi-upload me-1"></i><?= !empty($priorReplies) ? 'Submit Updated Proposal' : 'Submit Your Proposal' ?>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" novalidate>

                <div class="mb-4">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-paperclip me-1"></i>Proposal Files <span class="text-danger">*</span>
                    </label>
                    <input type="file" name="reply_files[]" class="form-control" multiple
                           accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.doc,.docx">
                    <div class="form-text">Upload your rate card, schedule, or proposal (PDF, Excel, Word, or images). Multiple files allowed.</div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Notes <span class="fw-normal text-muted">(optional)</span></label>
                    <textarea name="notes" class="form-control" rows="4"
                              placeholder="Available placements, lead time, package options, pricing notes…"><?= $h($_POST['notes'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-send-fill me-2"></i><?= !empty($priorReplies) ? 'Submit Updated Proposal' : 'Submit Proposal' ?>
                </button>
            </form>
        </div>
    </div>

    <?php endif; ?>

</div>
</body>
</html>
