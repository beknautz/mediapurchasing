<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['loggedIn'])) {
    redirect('/auth/login.php');
}

$role    = $_SESSION['role'] ?? '';
$userId  = (int) ($_SESSION['user']['id'] ?? 0);
$isAdmin = $role === 'admin';
$isBuyer = in_array($role, ['admin', 'buyer'], true);

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid media buy ID.'];
    redirect('/media-buys/index.php');
}

$mediaBuyService = new MediaBuyService();
$approvalService = new ApprovalService();
$emailService    = new EmailService();

// Load data first — needed by both POST handlers and display
$detail = $mediaBuyService->getMediaBuy($id);

if (empty($detail)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Media buy not found.'];
    redirect('/media-buys/index.php');
}

$buy = $detail['buy'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    switch ($action) {

        case 'send_to_vendor':
            $emailService->sendTemplate(
                'media_buy_request_vendor',
                $buy['vendor_email'] ?? '',
                $buy['vendor_name']  ?? '',
                [
                    'buy_title'      => $buy['title']       ?? '',
                    'vendor_contact' => $buy['vendor_name']  ?? '',
                    'media_type'     => $buy['media_type']   ?? '',
                    'flight_start'   => $buy['flight_start'] ?? '',
                    'flight_end'     => $buy['flight_end']   ?? '',
                    'market'         => $buy['market']       ?? '',
                    'original_cost'  => number_format((float)($buy['original_cost'] ?? 0), 2),
                    'description'    => $buy['description']  ?? '',
                    'buyer_name'     => $buy['buyer_name']   ?? '',
                    'agency_name'    => APP_NAME,
                ],
                $id
            );
            $mediaBuyService->updateStatus($id, 'sent_to_vendor');
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Sent to vendor successfully.'];
            redirect('/media-buys/view.php?id=' . $id);

        case 'request_approval':
            $approvalResult = $approvalService->createApproval($id, (int)($buy['client_id'] ?? 0));
            if (!empty($approvalResult['id'])) {
                $approvalLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                              . '://' . $_SERVER['HTTP_HOST']
                              . '/approvals/portal.php?token=' . urlencode($approvalResult['token'] ?? '');
                $emailService->sendTemplate(
                    'client_approval_request',
                    $buy['client_email'] ?? '',
                    $buy['client_name']  ?? '',
                    [
                        'buy_title'      => $buy['title']       ?? '',
                        'client_contact' => $buy['client_name']  ?? '',
                        'vendor_name'    => $buy['vendor_name']  ?? '',
                        'media_type'     => $buy['media_type']   ?? '',
                        'flight_start'   => $buy['flight_start'] ?? '',
                        'flight_end'     => $buy['flight_end']   ?? '',
                        'total_cost'     => number_format((float)($buy['total_cost'] ?? $buy['original_cost'] ?? 0), 2),
                        'approval_link'  => $approvalLink,
                        'expires_at'     => $approvalResult['expiresAt'] ?? '',
                    ],
                    $id,
                    $approvalResult['id']
                );
                $mediaBuyService->updateStatus($id, 'pending_client_approval');
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Approval request sent to client.'];
            } else {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to create approval request.'];
            }
            redirect('/media-buys/view.php?id=' . $id);

        case 'negotiate':
            $proposedCost = (float) ($_POST['proposed_cost'] ?? 0);
            $negNotes     = trim($_POST['negotiation_notes'] ?? '');
            $mediaBuyService->saveNegotiation([
                'media_buy_id'     => $id,
                'user_id'          => $userId,
                'proposed_cost'    => $proposedCost,
                'notes'            => $negNotes,
                'negotiation_type' => 'counter_offer',
            ]);
            $emailService->sendTemplate(
                'negotiation_counter',
                $buy['vendor_email'] ?? '',
                $buy['vendor_name']  ?? '',
                [
                    'buy_title'      => $buy['title']      ?? '',
                    'vendor_contact' => $buy['vendor_name'] ?? '',
                    'vendor_rate'    => number_format((float)($buy['total_cost'] ?? $buy['original_cost'] ?? 0), 2),
                    'proposed_rate'  => number_format($proposedCost, 2),
                    'buyer_notes'    => $negNotes,
                    'buyer_name'     => $buy['buyer_name']  ?? '',
                ],
                $id
            );
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Counter-offer submitted.'];
            redirect('/media-buys/view.php?id=' . $id);

        case 'resend_vendor_email':
            $emailService->sendTemplate(
                'media_buy_request_vendor',
                $buy['vendor_email'] ?? '',
                $buy['vendor_name']  ?? '',
                [
                    'buy_title'      => $buy['title']       ?? '',
                    'vendor_contact' => $buy['vendor_name']  ?? '',
                    'media_type'     => $buy['media_type']   ?? '',
                    'flight_start'   => $buy['flight_start'] ?? '',
                    'flight_end'     => $buy['flight_end']   ?? '',
                    'market'         => $buy['market']       ?? '',
                    'original_cost'  => number_format((float)($buy['original_cost'] ?? 0), 2),
                    'description'    => $buy['description']  ?? '',
                    'buyer_name'     => $buy['buyer_name']   ?? '',
                    'agency_name'    => APP_NAME,
                ],
                $id
            );
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Vendor email resent.'];
            redirect('/media-buys/view.php?id=' . $id);

        case 'finalize':
            $agreedCost = (float) ($_POST['agreed_cost'] ?? 0);
            $mediaBuyService->finalizeNegotiation($id, $agreedCost);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Negotiation finalized. Media buy approved.'];
            redirect('/media-buys/view.php?id=' . $id);

        case 'update_status':
            if ($isAdmin) {
                $newStatus   = trim($_POST['new_status']    ?? '');
                $statusNotes = trim($_POST['status_notes']  ?? '');
                $validStatuses = ['draft','sent_to_vendor','pending_client_approval','negotiating','client_approved','finalized','cancelled'];
                if (in_array($newStatus, $validStatuses, true)) {
                    $mediaBuyService->updateStatus($id, $newStatus, $statusNotes);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Status updated.'];
                } else {
                    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid status.'];
                }
            }
            redirect('/media-buys/view.php?id=' . $id);
    }
}

$items        = $detail['items']        ?? [];
$negotiations = $detail['negotiations'] ?? [];
$approvals    = $detail['approvals']    ?? [];

$statusColors = [
    'draft'                   => 'secondary',
    'sent_to_vendor'          => 'info',
    'pending_client_approval' => 'warning',
    'negotiating'             => 'primary',
    'client_approved'         => 'success',
    'finalized'               => 'dark',
    'cancelled'               => 'danger',
];
$statusLabels = [
    'draft'                   => 'Draft',
    'sent_to_vendor'          => 'Sent to Vendor',
    'pending_client_approval' => 'Pending Approval',
    'negotiating'             => 'Negotiating',
    'client_approved'         => 'Client Approved',
    'finalized'               => 'Finalized',
    'cancelled'               => 'Cancelled',
];

$currentStatus = $buy['status'] ?? 'draft';
$statusColor   = $statusColors[$currentStatus] ?? 'secondary';
$statusLabel   = $statusLabels[$currentStatus] ?? ucfirst($currentStatus);

// Timeline steps
$timelineSteps = [
    'draft'                   => ['icon' => 'file-earmark-text', 'label' => 'Draft'],
    'sent_to_vendor'          => ['icon' => 'send',              'label' => 'Sent to Vendor'],
    'negotiating'             => ['icon' => 'arrow-left-right',  'label' => 'Negotiating'],
    'pending_client_approval' => ['icon' => 'hourglass-split',   'label' => 'Pending Approval'],
    'client_approved'         => ['icon' => 'check-circle',      'label' => 'Approved'],
    'finalized'               => ['icon' => 'trophy',            'label' => 'Finalized'],
];
$timelineOrder = array_keys($timelineSteps);
$currentIdx    = array_search($currentStatus, $timelineOrder, true);

$allStatuses = array_keys($statusLabels);

$isOwner = (int)($buy['buyer_id'] ?? 0) === $userId;
$canEdit = $isBuyer && ($isAdmin || $isOwner) && !in_array($currentStatus, ['finalized', 'cancelled'], true);

$pageTitle = h($buy['title']) . ' — Media Buy';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumb + heading -->
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="/media-buys/index.php">Media Buys</a></li>
                <li class="breadcrumb-item active"><?= h($buy['title']) ?></li>
            </ol>
        </nav>
        <h1 class="h3 mb-0 fw-bold"><?= h($buy['title']) ?></h1>
        <div class="mt-1">
            <span class="badge bg-<?= $statusColor ?> fs-6"><?= h($statusLabel) ?></span>
            <?php if (!empty($buy['media_type'])): ?>
                <span class="badge bg-light text-dark border ms-1"><?= h($buy['media_type']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($canEdit): ?>
        <a href="/media-buys/edit.php?id=<?= $id ?>" class="btn btn-outline-primary">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <?php endif; ?>
        <a href="/media-buys/index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<!-- Status Timeline -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2" style="min-height:50px;">
            <?php foreach ($timelineSteps as $stepKey => $step):
                if ($stepKey === 'cancelled') continue;
                $stepIdx  = array_search($stepKey, $timelineOrder, true);
                $isDone   = $currentIdx !== false && $stepIdx <= $currentIdx && $currentStatus !== 'cancelled';
                $isCurrent= $stepKey === $currentStatus;
                $stepColor= $isDone ? ($isCurrent ? $statusColor : 'success') : 'secondary';
            ?>
            <div class="d-flex align-items-center gap-1 text-<?= $isDone ? $stepColor : 'muted' ?>">
                <i class="bi bi-<?= $step['icon'] ?> fs-5"></i>
                <span class="small fw-<?= $isCurrent ? 'bold' : 'normal' ?>"><?= h($step['label']) ?></span>
            </div>
            <?php if ($stepKey !== 'finalized'): ?>
                <div class="flex-grow-1 border-top border-2 d-none d-md-block mx-2" style="min-width:20px;"></div>
            <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($currentStatus === 'cancelled'): ?>
                <span class="badge bg-danger ms-auto">Cancelled</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-4">

    <!-- Main content -->
    <div class="col-lg-8">

        <!-- Buy Details Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-info-circle me-2 text-primary"></i>Buy Details</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="text-muted small">Client</div>
                        <div class="fw-semibold"><?= h($buy['client_name'] ?? '—') ?></div>
                        <?php if (!empty($buy['client_email'])): ?>
                            <div class="small">
                                <a href="mailto:<?= h($buy['client_email']) ?>"><?= h($buy['client_email']) ?></a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Vendor</div>
                        <div class="fw-semibold"><?= h($buy['vendor_name'] ?? '—') ?></div>
                        <?php if (!empty($buy['vendor_email'])): ?>
                            <div class="small">
                                <a href="mailto:<?= h($buy['vendor_email']) ?>"><?= h($buy['vendor_email']) ?></a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Buyer</div>
                        <div class="fw-semibold"><?= h($buy['buyer_name'] ?? '—') ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Market</div>
                        <div class="fw-semibold"><?= h($buy['market'] ?? '—') ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Flight Dates</div>
                        <div class="fw-semibold">
                            <?php if (!empty($buy['flight_start'])): ?>
                                <?= h(date('M j, Y', strtotime($buy['flight_start']))) ?>
                                <?php if (!empty($buy['flight_end'])): ?>
                                    — <?= h(date('M j, Y', strtotime($buy['flight_end']))) ?>
                                <?php endif; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="text-muted small">Budget</div>
                        <div class="fw-semibold text-primary">
                            <?= !empty($buy['total_cost']) ? '$' . number_format((float)$buy['total_cost'], 2) : '—' ?>
                        </div>
                    </div>
                    <?php if (!empty($buy['agreed_cost'])): ?>
                    <div class="col-sm-3">
                        <div class="text-muted small">Agreed Cost</div>
                        <div class="fw-semibold text-success">
                            $<?= number_format((float)$buy['agreed_cost'], 2) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($buy['notes'])): ?>
                <hr>
                <div>
                    <div class="text-muted small mb-1">Description</div>
                    <div class="text-break"><?= nl2br(h($buy['notes'])) ?></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($buy['internal_notes']) && $isBuyer): ?>
                <div class="mt-3 p-2 bg-light rounded">
                    <div class="text-muted small mb-1"><i class="bi bi-eye-slash me-1"></i>Internal Notes</div>
                    <div class="text-break small"><?= nl2br(h($buy['internal_notes'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Line Items -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-list-ul me-2 text-primary"></i>Line Items</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($items)): ?>
                    <div class="text-center text-muted py-4">No line items on this buy.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Description</th>
                                <th>Placement</th>
                                <th class="text-center">Spots/Qty</th>
                                <th class="text-end">Unit Cost</th>
                                <th class="text-end pe-3">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $lineTotal = 0;
                        foreach ($items as $item):
                            $lineTotal += (float)($item['total_cost'] ?? 0);
                        ?>
                            <tr>
                                <td class="ps-3"><?= h($item['description'] ?? '') ?></td>
                                <td class="text-muted small"><?= h($item['placement'] ?? $item['notes'] ?? '') ?></td>
                                <td class="text-center"><?= h($item['quantity'] ?? '') ?></td>
                                <td class="text-end">$<?= number_format((float)($item['unit_cost'] ?? 0), 2) ?></td>
                                <td class="text-end pe-3 fw-semibold">$<?= number_format((float)($item['total_cost'] ?? 0), 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="4" class="text-end fw-bold ps-3">Total</td>
                                <td class="text-end pe-3 fw-bold text-primary">$<?= number_format($lineTotal, 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Negotiation History -->
        <?php if (!empty($negotiations)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-arrow-left-right me-2 text-primary"></i>Negotiation History</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Date</th>
                                <th>By</th>
                                <th>Type</th>
                                <th class="text-end">Proposed Cost</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($negotiations as $neg): ?>
                            <tr>
                                <td class="ps-3 small text-muted text-nowrap">
                                    <?= !empty($neg['created_at']) ? h(date('M j, Y g:ia', strtotime($neg['created_at']))) : '—' ?>
                                </td>
                                <td class="small"><?= h($neg['negotiator_name'] ?? '—') ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= h(ucwords(str_replace('_', ' ', $neg['negotiation_type'] ?? ''))) ?>
                                    </span>
                                </td>
                                <td class="text-end fw-semibold">
                                    $<?= number_format((float)($neg['proposed_cost'] ?? 0), 2) ?>
                                </td>
                                <td class="small text-muted"><?= h($neg['notes'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Communication Log (HTMX) -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-chat-dots me-2 text-primary"></i>Communication Log</h5>
                <a href="/communications/compose.php?media_buy_id=<?= $id ?>"
                   class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-send me-1"></i>Compose
                </a>
            </div>
            <div class="card-body p-0"
                 hx-get="/communications/partial_log.php?media_buy_id=<?= $id ?>"
                 hx-trigger="load"
                 hx-swap="innerHTML">
                <div class="text-center text-muted py-4">
                    <div class="spinner-border spinner-border-sm me-2"></div>
                    Loading communications…
                </div>
            </div>
        </div>

    </div><!-- /col-lg-8 -->

    <!-- Right Sidebar -->
    <div class="col-lg-4">

        <!-- Workflow Actions -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-lightning me-2 text-warning"></i>Actions</h5>
            </div>
            <div class="card-body d-grid gap-2">

                <?php if ($currentStatus === 'draft' && $isBuyer && ($isAdmin || $isOwner)): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="send_to_vendor">
                    <button type="submit" class="btn btn-info w-100">
                        <i class="bi bi-send me-1"></i>Send to Vendor
                    </button>
                </form>
                <?php endif; ?>

                <?php if ($currentStatus !== 'draft' && !empty($buy['vendor_email']) && ($isAdmin || $isOwner)): ?>
                <form method="POST" onsubmit="return confirm('Resend the vendor request email?')">
                    <input type="hidden" name="action" value="resend_vendor_email">
                    <button type="submit" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-arrow-repeat me-1"></i>Resend Vendor Email
                    </button>
                </form>
                <?php endif; ?>

                <?php if (in_array($currentStatus, ['sent_to_vendor', 'negotiating'], true) && $isBuyer && ($isAdmin || $isOwner)): ?>
                <button type="button" class="btn btn-warning w-100" data-bs-toggle="modal" data-bs-target="#negotiateModal">
                    <i class="bi bi-arrow-left-right me-1"></i>Submit Counter-Offer
                </button>
                <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#finalizeModal">
                    <i class="bi bi-check-circle me-1"></i>Finalize / Approve
                </button>
                <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#requestApprovalModal">
                    <i class="bi bi-person-check me-1"></i>Request Client Approval
                </button>
                <?php endif; ?>

                <?php if ($currentStatus === 'draft' && $isBuyer && ($isAdmin || $isOwner)): ?>
                <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#requestApprovalModal">
                    <i class="bi bi-person-check me-1"></i>Request Client Approval
                </button>
                <?php endif; ?>

                <?php if ($currentStatus === 'pending_client_approval' && !empty($approvals)): ?>
                    <?php $latestApproval = $approvals[0]; ?>
                    <?php if (!empty($latestApproval['token'])): ?>
                    <a href="/approvals/portal.php?token=<?= h($latestApproval['token']) ?>"
                       class="btn btn-outline-primary w-100" target="_blank">
                        <i class="bi bi-box-arrow-up-right me-1"></i>View Approval Portal
                    </a>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($isBuyer && ($isAdmin || $isOwner) && !in_array($currentStatus, ['finalized', 'cancelled'], true)): ?>
                <a href="/media-buys/edit.php?id=<?= $id ?>" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-pencil me-1"></i>Edit Media Buy
                </a>
                <?php endif; ?>

                <?php if ($isAdmin): ?>
                <hr class="my-1">
                <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#statusOverrideModal">
                    <i class="bi bi-sliders me-1"></i>Override Status (Admin)
                </button>
                <?php endif; ?>

                <?php if (!$isBuyer && $currentStatus === 'draft'): ?>
                <div class="alert alert-secondary py-2 mb-0 text-center small">No actions available.</div>
                <?php endif; ?>

            </div>
        </div>

        <!-- Approval History -->
        <?php if (!empty($approvals)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-check2-square me-2 text-success"></i>Approval History</h5>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($approvals as $appr):
                    $apprColors = ['pending'=>'warning','approved'=>'success','revision_requested'=>'info','rejected'=>'danger','expired'=>'secondary'];
                    $apprColor  = $apprColors[$appr['status'] ?? ''] ?? 'secondary';
                    $isExpired  = !empty($appr['expires_at']) && strtotime($appr['expires_at']) < time() && $appr['status'] === 'pending';
                ?>
                <li class="list-group-item px-3 py-2 small">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="badge bg-<?= $apprColor ?> mb-1"><?= h(ucfirst($appr['status'] ?? '')) ?></span>
                            <?php if ($isExpired): ?>
                                <span class="badge bg-danger mb-1">Expired</span>
                            <?php endif; ?>
                            <div class="text-muted">
                                Requested <?= !empty($appr['created_at']) ? h(date('M j, Y', strtotime($appr['created_at']))) : '' ?>
                            </div>
                            <?php if (!empty($appr['expires_at'])): ?>
                            <div class="text-muted">
                                Expires <?= h(date('M j, Y', strtotime($appr['expires_at']))) ?>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($appr['response_notes'])): ?>
                            <div class="mt-1 text-break"><?= h($appr['response_notes']) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($appr['token'])): ?>
                        <a href="/approvals/portal.php?token=<?= h($appr['token']) ?>"
                           class="btn btn-xs btn-outline-secondary btn-sm" target="_blank">
                            Portal
                        </a>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Meta info -->
        <div class="card border-0 shadow-sm">
            <div class="card-body small text-muted">
                <div class="mb-1"><strong>ID:</strong> #<?= (int)$buy['id'] ?></div>
                <div class="mb-1"><strong>Created:</strong> <?= !empty($buy['created_at']) ? h(date('M j, Y g:ia', strtotime($buy['created_at']))) : '—' ?></div>
                <div><strong>Last Updated:</strong> <?= !empty($buy['updated_at']) ? h(date('M j, Y g:ia', strtotime($buy['updated_at']))) : '—' ?></div>
            </div>
        </div>

    </div><!-- /col-lg-4 -->
</div>

<!-- Modal: Negotiate -->
<div class="modal fade" id="negotiateModal" tabindex="-1" aria-labelledby="negotiateModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="negotiate">
                <div class="modal-header">
                    <h5 class="modal-title" id="negotiateModalLabel">
                        <i class="bi bi-arrow-left-right me-2"></i>Submit Counter-Offer
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="proposed_cost" class="form-label fw-semibold">Proposed Cost</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="proposed_cost" name="proposed_cost"
                                   value="<?= h($buy['total_cost'] ?? '0.00') ?>"
                                   min="0" step="0.01" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="negotiation_notes" class="form-label fw-semibold">Notes / Reasoning</label>
                        <textarea class="form-control" id="negotiation_notes" name="negotiation_notes"
                                  rows="4" placeholder="Explain the counter-offer…"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-send me-1"></i>Submit Counter-Offer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Finalize -->
<div class="modal fade" id="finalizeModal" tabindex="-1" aria-labelledby="finalizeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="finalize">
                <div class="modal-header">
                    <h5 class="modal-title" id="finalizeModalLabel">
                        <i class="bi bi-check-circle me-2 text-success"></i>Finalize Deal
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Confirm the agreed cost and mark this media buy as <strong>Approved</strong>.</p>
                    <div class="mb-3">
                        <label for="agreed_cost" class="form-label fw-semibold">Agreed Cost</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="agreed_cost" name="agreed_cost"
                                   value="<?= h($buy['agreed_cost'] ?? $buy['total_cost'] ?? '0.00') ?>"
                                   min="0" step="0.01" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-trophy me-1"></i>Finalize &amp; Approve
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Request Approval -->
<div class="modal fade" id="requestApprovalModal" tabindex="-1" aria-labelledby="requestApprovalModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="request_approval">
                <div class="modal-header">
                    <h5 class="modal-title" id="requestApprovalModalLabel">
                        <i class="bi bi-person-check me-2 text-primary"></i>Request Client Approval
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>An approval link will be emailed to the client at <strong><?= h($buy['client_email'] ?? 'their email address') ?></strong>.</p>
                    <div class="mb-3">
                        <label for="expires_days" class="form-label fw-semibold">Expires After</label>
                        <select class="form-select" id="expires_days" name="expires_days">
                            <option value="3">3 days</option>
                            <option value="5">5 days</option>
                            <option value="7" selected>7 days</option>
                            <option value="14">14 days</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i>Send Approval Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Admin Status Override -->
<?php if ($isAdmin): ?>
<div class="modal fade" id="statusOverrideModal" tabindex="-1" aria-labelledby="statusOverrideLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update_status">
                <div class="modal-header border-danger">
                    <h5 class="modal-title text-danger" id="statusOverrideLabel">
                        <i class="bi bi-sliders me-2"></i>Override Status (Admin)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning py-2 small">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        This will force-change the status without following normal workflow.
                    </div>
                    <div class="mb-3">
                        <label for="new_status" class="form-label fw-semibold">New Status</label>
                        <select class="form-select" id="new_status" name="new_status">
                            <?php foreach ($statusLabels as $sv => $sl): ?>
                                <option value="<?= h($sv) ?>" <?= $sv === $currentStatus ? 'selected' : '' ?>>
                                    <?= h($sl) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="status_notes" class="form-label fw-semibold">Notes (optional)</label>
                        <textarea class="form-control" id="status_notes" name="status_notes" rows="2"
                                  placeholder="Reason for override…"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-check-lg me-1"></i>Apply Override
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
