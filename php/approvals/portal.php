<?php
/**
 * approvals/portal.php
 * Public-facing client approval portal — no authentication required.
 * Accessed via unique token link sent to client email.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';

$token = trim($_GET['token'] ?? '');

$approvalService = new ApprovalService();

$approval    = null;
$mediaBuy    = null;
$items       = [];
$submitted   = false;
$tokenError  = false;
$alreadyDone = false;
$isExpired   = false;

if ($token === '') {
    $tokenError = true;
} else {
    $lookup = $approvalService->getApprovalByToken($token);

    if (!$lookup['found']) {
        $tokenError = true;
    } else {
        $approval = $lookup['approval'];
        $items    = $lookup['items'];

        // Check if already responded
        if (!empty($approval['status']) && $approval['status'] !== 'pending') {
            $alreadyDone = true;
        }

        // Check expiry
        if (!empty($approval['expires_at']) && strtotime($approval['expires_at']) < time() && !$alreadyDone) {
            $isExpired = true;
        }

        // Load media buy details
        if (!empty($approval['media_buy_id'])) {
            $mediaBuyService = new MediaBuyService();
            $detail  = $mediaBuyService->getMediaBuy((int) $approval['media_buy_id']);
            $mediaBuy = $detail['buy']   ?? null;
            $items    = $detail['items'] ?? [];
        }
    }
}

$formErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$tokenError && !$alreadyDone && !$isExpired) {
    $response    = trim($_POST['response']      ?? '');
    $clientNotes = trim($_POST['client_notes']  ?? '');

    $validResponses = ['approved', 'revision_requested', 'rejected'];

    if (!in_array($response, $validResponses, true)) {
        $formErrors[] = 'Please select a response option.';
    }

    if (empty($formErrors)) {
        $result = $approvalService->processResponse($token, $response, $clientNotes);

        if ($result['success'] ?? false) {
            $submitted = true;
            $refreshed = $approvalService->getApprovalByToken($token);
            $approval  = $refreshed['approval'] ?? $approval;
        } else {
            $formErrors[] = $result['message'] ?? 'Failed to submit your response. Please try again.';
        }
    }
}

$statusLabels = [
    'approved'           => 'Approved',
    'revision_requested' => 'Revision Requested',
    'rejected'           => 'Rejected',
    'pending'            => 'Pending',
    'expired'            => 'Expired',
];

$responseColors = [
    'approved'           => 'success',
    'revision_requested' => 'info',
    'rejected'           => 'danger',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campaign Approval Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f0f4f8; }
        .portal-card { max-width: 780px; margin: 0 auto; }
        .line-items-table th { font-size: .8rem; }
    </style>
</head>
<body class="py-4 py-md-5">

<div class="portal-card">

    <!-- Header -->
    <div class="text-center mb-4">
        <i class="bi bi-play-btn-fill text-primary fs-1"></i>
        <h2 class="mt-2 fw-bold">Campaign Approval</h2>
        <p class="text-muted">Please review the media buy details below and submit your response.</p>
    </div>

    <?php if ($tokenError): ?>
    <!-- Invalid token -->
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-exclamation-triangle text-danger fs-1 d-block mb-3"></i>
            <h4 class="fw-bold">Invalid or Missing Link</h4>
            <p class="text-muted">This approval link is invalid or has already been used. Please contact your media buyer for a new link.</p>
        </div>
    </div>

    <?php elseif ($isExpired && !$alreadyDone): ?>
    <!-- Expired -->
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-clock-history text-warning fs-1 d-block mb-3"></i>
            <h4 class="fw-bold">Approval Link Expired</h4>
            <p class="text-muted">
                This approval request expired on
                <strong><?= h(date('F j, Y', strtotime($approval['expires_at']))) ?></strong>.
                Please contact your media buyer to request a new approval link.
            </p>
            <?php if ($mediaBuy): ?>
            <div class="mt-3 p-3 bg-light rounded text-start">
                <div class="fw-semibold"><?= h($mediaBuy['title'] ?? '') ?></div>
                <div class="text-muted small"><?= h($mediaBuy['client_name'] ?? '') ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ($alreadyDone && !$submitted): ?>
    <!-- Already responded -->
    <?php
    $doneStatus = $approval['status'] ?? '';
    $doneColor  = $responseColors[$doneStatus] ?? 'secondary';
    $doneLabel  = $statusLabels[$doneStatus]   ?? ucfirst($doneStatus);
    ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-check-circle text-success fs-1 d-block mb-3"></i>
            <h4 class="fw-bold">Already Responded</h4>
            <p class="text-muted">You have already submitted a response for this approval request.</p>
            <span class="badge bg-<?= $doneColor ?> fs-6 px-3 py-2">
                <?= h($doneLabel) ?>
            </span>
            <?php if (!empty($approval['response_notes'])): ?>
            <div class="mt-3 p-3 bg-light rounded text-start">
                <div class="text-muted small mb-1">Your notes:</div>
                <div><?= nl2br(h($approval['response_notes'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ($submitted): ?>
    <!-- Success confirmation -->
    <?php
    $subStatus = $approval['status'] ?? 'approved';
    $subColor  = $responseColors[$subStatus] ?? 'success';
    $subLabel  = $statusLabels[$subStatus]   ?? ucfirst($subStatus);
    $subIcons  = ['approved' => 'check-circle-fill', 'revision_requested' => 'pencil-square', 'rejected' => 'x-circle-fill'];
    $subIcon   = $subIcons[$subStatus] ?? 'check-circle-fill';
    ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-<?= $subIcon ?> text-<?= $subColor ?> fs-1 d-block mb-3"></i>
            <h4 class="fw-bold">Response Submitted</h4>
            <p class="text-muted mb-3">
                Thank you. Your response has been recorded and your media buyer has been notified.
            </p>
            <span class="badge bg-<?= $subColor ?> fs-6 px-3 py-2 mb-3 d-inline-block">
                <?= h($subLabel) ?>
            </span>
            <?php if (!empty($approval['response_notes'])): ?>
            <div class="p-3 bg-light rounded text-start">
                <div class="text-muted small mb-1">Your notes:</div>
                <div><?= nl2br(h($approval['response_notes'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php else: ?>
    <!-- Main approval form -->

    <!-- Campaign Details Card -->
    <?php if ($mediaBuy): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-primary text-white py-3">
            <h5 class="mb-0 fw-semibold"><i class="bi bi-collection-play me-2"></i>Campaign Details</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-8">
                    <div class="text-muted small">Campaign</div>
                    <div class="fw-bold fs-5"><?= h($mediaBuy['title'] ?? '') ?></div>
                </div>
                <div class="col-sm-4">
                    <div class="text-muted small">Media Type</div>
                    <div class="fw-semibold">
                        <?= !empty($mediaBuy['media_type']) ? h($mediaBuy['media_type']) : '—' ?>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="text-muted small">Client</div>
                    <div class="fw-semibold"><?= h($mediaBuy['client_name'] ?? '—') ?></div>
                </div>
                <div class="col-sm-6">
                    <div class="text-muted small">Vendor / Station</div>
                    <div class="fw-semibold"><?= h($mediaBuy['vendor_name'] ?? '—') ?></div>
                </div>
                <?php if (!empty($mediaBuy['flight_start'])): ?>
                <div class="col-sm-6">
                    <div class="text-muted small">Flight Dates</div>
                    <div class="fw-semibold">
                        <?= h(date('M j, Y', strtotime($mediaBuy['flight_start']))) ?>
                        <?php if (!empty($mediaBuy['flight_end'])): ?>
                            — <?= h(date('M j, Y', strtotime($mediaBuy['flight_end']))) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($mediaBuy['market'])): ?>
                <div class="col-sm-6">
                    <div class="text-muted small">Market</div>
                    <div class="fw-semibold"><?= h($mediaBuy['market']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($mediaBuy['total_cost'])): ?>
                <div class="col-sm-6">
                    <div class="text-muted small">Total Budget</div>
                    <div class="fw-bold text-primary fs-5">
                        $<?= number_format((float)$mediaBuy['total_cost'], 2) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($mediaBuy['notes'])): ?>
            <hr>
            <div>
                <div class="text-muted small mb-1">Campaign Notes</div>
                <div><?= nl2br(h($mediaBuy['notes'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Line Items -->
    <?php if (!empty($items)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-semibold"><i class="bi bi-list-ul me-2 text-primary"></i>Line Items</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table line-items-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Description</th>
                            <th>Placement</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Unit Cost</th>
                            <th class="text-end pe-3">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $grandTotal = 0;
                    foreach ($items as $item):
                        $grandTotal += (float)($item['total_cost'] ?? 0);
                    ?>
                        <tr>
                            <td class="ps-3"><?= h($item['description'] ?? '') ?></td>
                            <td class="small text-muted"><?= h($item['placement'] ?? $item['notes'] ?? '') ?></td>
                            <td class="text-center"><?= h($item['quantity'] ?? 1) ?></td>
                            <td class="text-end">$<?= number_format((float)($item['unit_cost'] ?? 0), 2) ?></td>
                            <td class="text-end pe-3 fw-semibold">$<?= number_format((float)($item['total_cost'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="4" class="text-end fw-bold ps-3 pe-2">Grand Total</td>
                            <td class="text-end pe-3 fw-bold text-primary fs-5">
                                $<?= number_format($grandTotal, 2) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Response Form -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-semibold"><i class="bi bi-chat-square-dots me-2 text-primary"></i>Your Response</h5>
        </div>
        <div class="card-body">

            <?php if (!empty($formErrors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($formErrors as $err): ?>
                        <li><?= h($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (!empty($approval['expires_at'])): ?>
            <div class="alert alert-info py-2 small mb-3">
                <i class="bi bi-clock me-1"></i>
                This approval request expires on
                <strong><?= h(date('F j, Y', strtotime($approval['expires_at']))) ?></strong>.
            </div>
            <?php endif; ?>

            <form method="POST" action="">

                <div class="mb-4">
                    <label class="form-label fw-semibold mb-3">Select your response <span class="text-danger">*</span></label>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <input type="radio" class="btn-check" name="response" id="res_approved"
                                   value="approved" required>
                            <label class="btn btn-outline-success w-100 py-3" for="res_approved">
                                <i class="bi bi-check-circle-fill d-block fs-3 mb-2"></i>
                                <strong>Approve</strong>
                                <div class="small text-muted mt-1">I approve this buy as submitted.</div>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <input type="radio" class="btn-check" name="response" id="res_revision"
                                   value="revision_requested">
                            <label class="btn btn-outline-info w-100 py-3" for="res_revision">
                                <i class="bi bi-pencil-square d-block fs-3 mb-2"></i>
                                <strong>Request Revision</strong>
                                <div class="small text-muted mt-1">I need changes before approving.</div>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <input type="radio" class="btn-check" name="response" id="res_rejected"
                                   value="rejected">
                            <label class="btn btn-outline-danger w-100 py-3" for="res_rejected">
                                <i class="bi bi-x-circle-fill d-block fs-3 mb-2"></i>
                                <strong>Reject</strong>
                                <div class="small text-muted mt-1">I do not approve this buy.</div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="client_notes" class="form-label fw-semibold">Notes / Comments</label>
                    <textarea class="form-control" id="client_notes" name="client_notes" rows="5"
                              placeholder="Optional — provide any feedback, questions, or requested changes…"><?= h($_POST['client_notes'] ?? '') ?></textarea>
                    <div class="form-text">Your notes will be sent directly to your media buyer.</div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-send-fill me-2"></i>Submit My Response
                    </button>
                </div>

            </form>
        </div>
    </div>

    <?php endif; // end main form ?>

    <!-- Footer -->
    <div class="text-center mt-4 text-muted small">
        <p>
            This is a secure approval portal generated by MediaBuy Platform.<br>
            If you received this link in error, please disregard it.
        </p>
    </div>

</div><!-- /.portal-card -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
