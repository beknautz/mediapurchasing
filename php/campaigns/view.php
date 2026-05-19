<?php
require_once __DIR__ . '/../bootstrap.php';
requireRole(['admin', 'buyer']);

$campaignService = new CampaignService();
$crmService      = new CRMService();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect('/campaigns/index.php');
}

$data = $campaignService->getCampaign($id);
if (empty($data)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Campaign not found.'];
    redirect('/campaigns/index.php');
}

$campaign = $data['campaign'];
$channels = $data['channels'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    // ---- Add / update channel ----
    if ($action === 'save_channel') {
        $channelData = [
            'id'              => (int) ($_POST['channel_id']       ?? 0),
            'campaign_id'     => $id,
            'vendor_id'       => (int) ($_POST['vendor_id']        ?? 0),
            'media_category'  => trim($_POST['media_category']     ?? ''),
            'budget_allocated'=> (float) ($_POST['budget_allocated'] ?? 0),
            'notes'           => trim($_POST['channel_notes']      ?? ''),
        ];
        $r = $campaignService->saveChannel($channelData);
        $_SESSION['flash'] = ['type' => $r['success'] ? 'success' : 'danger', 'message' => $r['message']];
        redirect('/campaigns/view.php?id=' . $id);
    }

    // ---- Delete channel ----
    if ($action === 'delete_channel') {
        $channelId = (int) ($_POST['channel_id'] ?? 0);
        $ok = $campaignService->deleteChannel($channelId, $id);
        $_SESSION['flash'] = $ok
            ? ['type' => 'success', 'message' => 'Channel removed.']
            : ['type' => 'danger',  'message' => 'Cannot remove a channel that already has an RFP sent.'];
        redirect('/campaigns/view.php?id=' . $id);
    }

    // ---- Send RFP (consolidated per vendor) ----
    if ($action === 'send_rfp') {
        // Accept vendor_id directly (new grouped UI) or resolve from channel_id (legacy)
        $vendorId  = (int)($_POST['vendor_id']  ?? 0);
        $channelId = (int)($_POST['channel_id'] ?? 0);
        if ($vendorId === 0 && $channelId > 0) {
            foreach ($channels as $ch) {
                if ((int)$ch['id'] === $channelId) { $vendorId = (int)$ch['vendor_id']; break; }
            }
        }
        if ($vendorId > 0) {
            $r = $campaignService->sendVendorRfp($id, $vendorId);
        } else {
            $r = ['success' => false, 'message' => 'Channel or vendor not found.'];
        }
        $_SESSION['flash'] = ['type' => $r['success'] ? 'success' : 'danger', 'message' => $r['message']];
        redirect('/campaigns/view.php?id=' . $id);
    }

    // ---- Send all pending RFPs (one email per unique vendor) ----
    if ($action === 'send_all_rfps') {
        $sent        = 0;
        $failed      = 0;
        $vendorsSent = [];
        foreach ($channels as $ch) {
            if ($ch['status'] === 'pending' && !empty($ch['vendor_email'])) {
                $vid = (int)$ch['vendor_id'];
                if ($vid > 0 && !in_array($vid, $vendorsSent, true)) {
                    $r = $campaignService->sendVendorRfp($id, $vid);
                    $r['success'] ? $sent++ : $failed++;
                    $vendorsSent[] = $vid;
                }
            }
        }
        $msg = "Sent RFP email" . ($sent !== 1 ? 's' : '') . " to {$sent} vendor" . ($sent !== 1 ? 's' : '') . '.';
        if ($failed > 0) {
            $msg .= " {$failed} failed — check vendor email addresses.";
        }
        $_SESSION['flash'] = ['type' => $sent > 0 ? 'success' : 'warning', 'message' => $msg];
        redirect('/campaigns/view.php?id=' . $id);
    }

    // ---- Update campaign status ----
    if ($action === 'update_status') {
        $newStatus = trim($_POST['new_status'] ?? '');
        if (array_key_exists($newStatus, CampaignService::STATUSES)) {
            $campaignService->updateCampaignStatus($id, $newStatus);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Status updated.'];
        }
        redirect('/campaigns/view.php?id=' . $id);
    }

    // ---- Update campaign details ----
    if ($action === 'save_campaign') {
        $r = $campaignService->saveCampaign([
            'id'           => $id,
            'title'        => trim($_POST['title']        ?? ''),
            'client_id'    => (int) ($_POST['client_id']  ?? 0),
            'language'     => trim($_POST['language']     ?? 'both'),
            'total_budget' => (float) str_replace(',', '', $_POST['total_budget'] ?? '0'),
            'flight_start' => trim($_POST['flight_start'] ?? '') ?: null,
            'flight_end'   => trim($_POST['flight_end']   ?? '') ?: null,
            'market'       => trim($_POST['market']       ?? ''),
            'notes'        => trim($_POST['notes']        ?? ''),
            'status'       => $campaign['status'],
        ]);
        $_SESSION['flash'] = ['type' => $r['success'] ? 'success' : 'danger', 'message' => $r['message']];
        redirect('/campaigns/view.php?id=' . $id);
    }
}

// Reload fresh data after any redirect (first load only reaches here)
$vendors = $crmService->getVendors();
$clients = $crmService->getClients();

$statuses        = CampaignService::STATUSES;
$channelStatuses = CampaignService::CHANNEL_STATUSES;

$statusInfo = $statuses[$campaign['status']] ?? ['label' => $campaign['status'], 'class' => 'secondary'];

$pendingCount  = count(array_filter($channels, fn($c) => $c['status'] === 'pending'));
$rfpSentCount  = count(array_filter($channels, fn($c) => $c['status'] === 'rfp_sent'));
$repliedCount  = count(array_filter($channels, fn($c) => $c['status'] === 'response_received'));
$allocatedSum  = array_sum(array_column($channels, 'budget_allocated'));

$mediaCategories = [
    'TV - Spanish', 'TV - English',
    'Radio - Spanish', 'Radio - English',
    'Digital/Social', 'Newsprint', 'Production', 'Other',
];

$pageTitle = h($campaign['title']) . ' — Campaigns';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumb + header -->
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="/campaigns/index.php">Campaigns</a></li>
                <li class="breadcrumb-item active"><?= h($campaign['title']) ?></li>
            </ol>
        </nav>
        <h2 class="mb-0 fw-bold">
            <?= h($campaign['title']) ?>
            <span class="badge bg-<?= $statusInfo['class'] ?> ms-2 fs-6 align-middle">
                <?= h($statusInfo['label']) ?>
            </span>
        </h2>
        <div class="text-muted small mt-1">
            <i class="bi bi-building me-1"></i><?= h($campaign['client_name'] ?? '—') ?>
            <?php if ($campaign['market']): ?>
                &nbsp;·&nbsp;<i class="bi bi-geo-alt me-1"></i><?= h($campaign['market']) ?>
            <?php endif; ?>
            <?php if ($campaign['flight_start']): ?>
                &nbsp;·&nbsp;<i class="bi bi-calendar-range me-1"></i>
                <?= date('M j, Y', strtotime($campaign['flight_start'])) ?>
                –
                <?= $campaign['flight_end'] ? date('M j, Y', strtotime($campaign['flight_end'])) : 'TBD' ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <!-- Status change dropdown -->
        <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-arrow-repeat me-1"></i>Change Status
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php foreach ($statuses as $key => $info): ?>
                <?php if ($key !== $campaign['status']): ?>
                <li>
                    <form method="post">
                        <input type="hidden" name="action"     value="update_status">
                        <input type="hidden" name="new_status" value="<?= h($key) ?>">
                        <button type="submit" class="dropdown-item">
                            <span class="badge bg-<?= $info['class'] ?> me-2"><?= h($info['label']) ?></span>
                        </button>
                    </form>
                </li>
                <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php if ($pendingCount > 0): ?>
        <form method="post" onsubmit="return confirm('Send RFP emails to all <?= $pendingCount ?> pending vendor(s)?')">
            <input type="hidden" name="action" value="send_all_rfps">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-send-fill me-1"></i>Send All RFPs (<?= $pendingCount ?>)
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Stats bar -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="h4 mb-0 fw-bold text-primary"><?= count($channels) ?></div>
            <div class="small text-muted">Channels</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="h4 mb-0 fw-bold text-info"><?= $rfpSentCount ?></div>
            <div class="small text-muted">RFPs Sent</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="h4 mb-0 fw-bold text-success"><?= $repliedCount ?></div>
            <div class="small text-muted">Responses</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="h4 mb-0 fw-bold">$<?= number_format((float)$campaign['total_budget'], 0) ?></div>
            <div class="small text-muted">Total Budget</div>
        </div>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4" id="campaignTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-channels" data-bs-toggle="tab" data-bs-target="#pane-channels"
                type="button" role="tab">
            <i class="bi bi-grid-3x2-gap me-1"></i>Media Channels
            <span class="badge bg-secondary ms-1"><?= count($channels) ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-comms" data-bs-toggle="tab" data-bs-target="#pane-comms"
                type="button" role="tab">
            <i class="bi bi-chat-dots me-1"></i>Communications
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-details" data-bs-toggle="tab" data-bs-target="#pane-details"
                type="button" role="tab">
            <i class="bi bi-pencil-square me-1"></i>Edit Details
        </button>
    </li>
</ul>

<div class="tab-content">

    <!-- ===== CHANNELS TAB ===== -->
    <div class="tab-pane fade show active" id="pane-channels" role="tabpanel">

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <span class="fw-semibold">Media Channels</span>
                <button type="button" class="btn btn-sm btn-outline-primary"
                        data-bs-toggle="modal" data-bs-target="#channelModal"
                        onclick="resetChannelForm()">
                    <i class="bi bi-plus-lg me-1"></i>Add Channel
                </button>
            </div>
            <div class="card-body p-0">
                <?php
                // ── Group channels by vendor for display ──────────────────
                $vendorGroups = [];
                foreach ($channels as $ch) {
                    $key = ($ch['vendor_id'] > 0) ? (int)$ch['vendor_id'] : 'none_' . $ch['id'];
                    $vendorGroups[$key][] = $ch;
                }
                ?>
                <?php if (empty($channels)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-grid-3x2-gap fs-3 d-block mb-2 opacity-50"></i>
                    No channels yet. Add a media channel to get started.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" id="channelsTable">
                        <thead class="table-dark">
                            <tr>
                                <th style="width:22%">Category</th>
                                <th style="width:14%">Budget</th>
                                <th style="width:14%">Status</th>
                                <th style="width:16%">RFP Sent</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($vendorGroups as $vendorKey => $vChannels):
                            $firstCh      = $vChannels[0];
                            $vendorName   = $firstCh['vendor_name']  ?? '';
                            $vendorEmail  = $firstCh['vendor_email'] ?? '';
                            $vendorId     = (int)$firstCh['vendor_id'];
                            $groupBudget  = array_sum(array_column($vChannels, 'budget_allocated'));
                            $hasPending   = !empty(array_filter($vChannels, fn($c) => $c['status'] === 'pending'));
                            $allStatuses  = array_unique(array_column($vChannels, 'status'));
                            $hasResponse  = in_array('response_received', $allStatuses, true);
                            $rfpDates     = array_filter(array_column($vChannels, 'rfp_sent_at'));
                            $latestRfp    = $rfpDates ? max($rfpDates) : null;
                            $canRfp       = $hasPending && !empty($vendorEmail);
                            $canResend    = !$hasPending && !empty($vendorEmail);
                            $chCount      = count($vChannels);

                            // Collect all replies across this vendor's channels (deduplicated by replied_at)
                            $allReplies = [];
                            foreach ($vChannels as $vc) {
                                foreach ($vc['vendor_replies'] as $r) {
                                    $allReplies[$r['replied_at']] = $r;
                                }
                            }
                            krsort($allReplies);
                            $allReplies = array_values($allReplies);

                            // Vendor-level status summary badge
                            if ($hasResponse) {
                                $groupBadge = '<span class="badge bg-success">Response Received</span>';
                            } elseif (count($allStatuses) === 1 && $allStatuses[0] === 'rfp_sent') {
                                $groupBadge = '<span class="badge bg-info text-dark">RFP Sent</span>';
                            } elseif (!$hasPending) {
                                $groupBadge = '<span class="badge bg-info text-dark">RFP Sent</span>';
                            } else {
                                $groupBadge = '<span class="badge bg-secondary">Pending</span>';
                            }
                        ?>
                        <!-- ── Vendor group header row ── -->
                        <tr class="table-light" style="border-top:2px solid #dee2e6;">
                            <td>
                                <div class="fw-bold"><?= h($vendorName ?: '— No vendor —') ?></div>
                                <?php if ($vendorEmail): ?>
                                    <div class="small text-muted"><?= h($vendorEmail) ?></div>
                                <?php endif; ?>
                                <div class="small text-muted mt-1">
                                    <?= $chCount ?> item<?= $chCount !== 1 ? 's' : '' ?>
                                </div>
                            </td>
                            <td class="fw-bold text-nowrap">$<?= number_format($groupBudget, 0) ?></td>
                            <td><?= $groupBadge ?></td>
                            <td class="small text-muted">
                                <?= $latestRfp ? date('M j, Y g:ia', strtotime($latestRfp)) : '—' ?>
                            </td>
                            <td class="text-end">
                                <div class="d-flex gap-2 justify-content-end flex-wrap">
                                <?php if ($canRfp && $vendorId > 0): ?>
                                    <form method="post"
                                          onsubmit="return confirm('Send one RFP email to <?= h(addslashes($vendorName)) ?> covering all <?= $chCount ?> item<?= $chCount !== 1 ? 's' : '' ?>?')">
                                        <input type="hidden" name="action"    value="send_rfp">
                                        <input type="hidden" name="vendor_id" value="<?= $vendorId ?>">
                                        <button type="submit" class="btn btn-info btn-sm text-dark">
                                            <i class="bi bi-send-fill me-1"></i>Send RFP
                                        </button>
                                    </form>
                                <?php elseif ($canResend && $vendorId > 0): ?>
                                    <form method="post"
                                          onsubmit="return confirm('Resend RFP email to <?= h(addslashes($vendorName)) ?>?')">
                                        <input type="hidden" name="action"    value="send_rfp">
                                        <input type="hidden" name="vendor_id" value="<?= $vendorId ?>">
                                        <button type="submit" class="btn btn-outline-info btn-sm">
                                            <i class="bi bi-arrow-clockwise me-1"></i>Resend RFP
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if (!empty($allReplies)): ?>
                                    <button class="btn btn-sm btn-success" type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#vendor_replies_<?= h($vendorKey) ?>">
                                        <i class="bi bi-paperclip me-1"></i>View Proposal (<?= count($allReplies) ?>)
                                    </button>
                                <?php endif; ?>
                                </div>
                            </td>
                        </tr>

                        <?php if (!empty($allReplies)): ?>
                        <tr class="collapse" id="vendor_replies_<?= h($vendorKey) ?>">
                            <td colspan="5" class="bg-light p-3">
                                <?php foreach ($allReplies as $ri => $reply): ?>
                                <div class="<?= $ri > 0 ? 'mt-3 pt-3 border-top' : '' ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <strong class="text-primary"><?= h($reply['vendor_name'] ?? '') ?></strong>
                                        <span class="text-muted small">
                                            <?= date('M j, Y g:ia', strtotime($reply['replied_at'])) ?>
                                            <?php if (($reply['source'] ?? '') === 'email'): ?>
                                                <span class="badge bg-secondary ms-1">via email</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary ms-1">via portal</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($reply['notes'])): ?>
                                        <p class="text-muted small mb-2 fst-italic"><?= nl2br(h($reply['notes'])) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($reply['attachments'])): ?>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($reply['attachments'] as $att): ?>
                                            <?php
                                            $type = $att['type'] ?? '';
                                            if (str_contains($type, 'pdf')) $icon = 'bi-file-earmark-pdf text-danger';
                                            elseif (str_contains($type, 'sheet') || str_contains($type, 'excel')) $icon = 'bi-file-earmark-excel text-success';
                                            elseif (str_contains($type, 'word')) $icon = 'bi-file-earmark-word text-primary';
                                            else $icon = 'bi-file-earmark-image text-secondary';
                                            ?>
                                            <a href="/campaigns/download.php?f=<?= urlencode($att['path']) ?>"
                                               target="_blank" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi <?= $icon ?> me-1"></i><?= h($att['name']) ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php else: ?>
                                        <span class="text-muted small"><i class="bi bi-paperclip me-1"></i>No files attached.</span>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        <?php endif; ?>

                        <!-- ── Individual channel rows ── -->
                        <?php foreach ($vChannels as $ch):
                            $chStatusInfo = $channelStatuses[$ch['status']] ?? ['label' => $ch['status'], 'class' => 'secondary'];
                            $canChDelete  = $ch['status'] === 'pending';
                        ?>
                        <tr>
                            <td class="ps-4">
                                <i class="bi bi-arrow-return-right text-muted me-1 small"></i>
                                <span class="fw-semibold"><?= h($ch['media_category']) ?></span>
                            </td>
                            <td class="text-nowrap text-muted">$<?= number_format((float)$ch['budget_allocated'], 0) ?></td>
                            <td>
                                <span class="badge bg-<?= $chStatusInfo['class'] ?>">
                                    <?= h($chStatusInfo['label']) ?>
                                </span>
                            </td>
                            <td class="small text-muted">
                                <?= $ch['rfp_sent_at'] ? date('M j, Y g:ia', strtotime($ch['rfp_sent_at'])) : '—' ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary btn-sm"
                                            onclick="openChannelComms(<?= (int)$ch['id'] ?>, <?= htmlspecialchars(json_encode($ch['media_category'] . ' — ' . ($ch['vendor_name'] ?? 'Vendor')), ENT_QUOTES) ?>)">
                                        <i class="bi bi-chat-text me-1"></i>Messages
                                        <?php if ($ch['status'] === 'response_received'): ?>
                                            <span class="badge bg-success ms-1">New</span>
                                        <?php endif; ?>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                            onclick="editChannel(<?= (int)$ch['id'] ?>, <?= htmlspecialchars(json_encode($ch), ENT_QUOTES) ?>)"
                                            data-bs-toggle="modal" data-bs-target="#channelModal"
                                            title="Edit channel">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if ($canChDelete): ?>
                                    <form method="post" class="d-inline"
                                          onsubmit="return confirm('Remove this channel?')">
                                        <input type="hidden" name="action"     value="delete_channel">
                                        <input type="hidden" name="channel_id" value="<?= (int)$ch['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Remove">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; // vChannels ?>
                        <?php endforeach; // vendorGroups ?>
                        </tbody>
                        <?php if ($allocatedSum > 0): ?>
                        <tfoot class="table-light">
                            <tr style="border-top:2px solid #dee2e6;">
                                <td class="text-end fw-semibold pe-3">Allocated Total:</td>
                                <td class="fw-bold text-nowrap">$<?= number_format($allocatedSum, 0) ?></td>
                                <td colspan="3">
                                    <?php $remaining = (float)$campaign['total_budget'] - $allocatedSum; ?>
                                    <?php if ($remaining >= 0): ?>
                                        <span class="text-success small">$<?= number_format($remaining, 0) ?> remaining</span>
                                    <?php else: ?>
                                        <span class="text-danger small">$<?= number_format(abs($remaining), 0) ?> over budget</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ===== COMMUNICATIONS TAB ===== -->
    <div class="tab-pane fade" id="pane-comms" role="tabpanel">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-chat-dots me-1 text-primary"></i>Communication Log</span>
                <a href="/communications/compose.php?campaign_id=<?= $id ?>"
                   class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-send me-1"></i>Compose
                </a>
            </div>
            <div id="comms-log-container">
                <div class="text-center py-4 text-muted small">
                    <i class="bi bi-hourglass-split me-1"></i>Loading…
                </div>
            </div>
        </div>
    </div>

    <!-- ===== EDIT DETAILS TAB ===== -->
    <div class="tab-pane fade" id="pane-details" role="tabpanel">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Campaign Details</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="save_campaign">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required
                                   value="<?= h($campaign['title']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Language</label>
                            <select name="language" class="form-select">
                                <option value="both"    <?= $campaign['language'] === 'both'    ? 'selected' : '' ?>>English &amp; Spanish</option>
                                <option value="english" <?= $campaign['language'] === 'english' ? 'selected' : '' ?>>English Only</option>
                                <option value="spanish" <?= $campaign['language'] === 'spanish' ? 'selected' : '' ?>>Spanish Only</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Client <span class="text-danger">*</span></label>
                            <select name="client_id" class="form-select" required>
                                <option value="">— Select —</option>
                                <?php foreach ($clients as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= (int)$campaign['client_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                                    <?= h($c['company_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Market / DMA</label>
                            <input type="text" name="market" class="form-control"
                                   value="<?= h($campaign['market'] ?? '') ?>" placeholder="e.g. Miami, FL">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Total Budget</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="total_budget" class="form-control"
                                       value="<?= h($campaign['total_budget']) ?>" min="0" step="0.01">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Flight Start</label>
                            <input type="date" name="flight_start" class="form-control"
                                   value="<?= h($campaign['flight_start'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Flight End</label>
                            <input type="date" name="flight_end" class="form-control"
                                   value="<?= h($campaign['flight_end'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea name="notes" class="form-control" rows="3"><?= h($campaign['notes'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i>Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div><!-- /tab-content -->

<!-- ===== Add/Edit Channel Modal ===== -->
<div class="modal fade" id="channelModal" tabindex="-1" aria-labelledby="channelModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content shadow">
            <form method="post" id="channelForm">
                <input type="hidden" name="action"     value="save_channel">
                <input type="hidden" name="channel_id" id="channelId" value="">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="channelModalLabel">
                        <i class="bi bi-grid-3x2-gap me-2"></i>
                        <span id="channelModalTitle">Add Media Channel</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="media_category" class="form-label fw-semibold">Media Category <span class="text-danger">*</span></label>
                        <select id="media_category" name="media_category" class="form-select" required>
                            <option value="">— Select —</option>
                            <?php foreach ($mediaCategories as $cat): ?>
                            <option value="<?= h($cat) ?>"><?= h($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="vendor_id" class="form-label fw-semibold">Vendor</label>
                        <select id="vendor_id" name="vendor_id" class="form-select">
                            <option value="">— Select Vendor —</option>
                            <?php foreach ($vendors as $v): ?>
                            <option value="<?= (int)$v['id'] ?>"
                                    data-category="<?= h($v['media_category'] ?? '') ?>">
                                <?= h($v['company_name']) ?>
                                <?php if (!empty($v['media_category'])): ?>
                                    (<?= h($v['media_category']) ?>)
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Optional — can be set later.</div>
                    </div>

                    <div class="mb-3">
                        <label for="budget_allocated" class="form-label fw-semibold">Budget Allocated</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" id="budget_allocated" name="budget_allocated"
                                   class="form-control" min="0" step="0.01" value="0.00">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="channel_notes" class="form-label fw-semibold">Notes</label>
                        <textarea id="channel_notes" name="channel_notes" class="form-control" rows="2"
                                  placeholder="Specific instructions for this channel…"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Channel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== Channel Communications Modal ===== -->
<div class="modal fade" id="channelCommsModal" tabindex="-1" aria-labelledby="channelCommsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content shadow">
            <div class="modal-header">
                <h5 class="modal-title" id="channelCommsModalLabel">
                    <i class="bi bi-chat-text me-2 text-primary"></i>
                    <span id="channelCommsTitle">Channel Messages</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="channelCommsBody">
                <div class="text-center py-4 text-muted small">
                    <i class="bi bi-hourglass-split me-1"></i>Loading…
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function resetChannelForm() {
    document.getElementById('channelId').value              = '';
    document.getElementById('media_category').value        = '';
    document.getElementById('vendor_id').value             = '';
    document.getElementById('budget_allocated').value      = '0.00';
    document.getElementById('channel_notes').value         = '';
    document.getElementById('channelModalTitle').textContent = 'Add Media Channel';
}

function editChannel(id, data) {
    document.getElementById('channelId').value              = id;
    document.getElementById('media_category').value        = data.media_category   || '';
    document.getElementById('vendor_id').value             = data.vendor_id        || '';
    document.getElementById('budget_allocated').value      = data.budget_allocated || '0.00';
    document.getElementById('channel_notes').value         = data.notes            || '';
    document.getElementById('channelModalTitle').textContent = 'Edit Channel: ' + (data.media_category || '');
}

function loadHtml(url, targetId) {
    var el = document.getElementById(targetId);
    el.innerHTML = '<div class="text-center py-4 text-muted small"><i class="bi bi-hourglass-split me-1"></i>Loading…</div>';
    fetch(url, { credentials: 'same-origin' })
        .then(function(r) { return r.text(); })
        .then(function(html) { el.innerHTML = html; })
        .catch(function() {
            el.innerHTML = '<div class="text-center text-danger py-4 small"><i class="bi bi-exclamation-triangle me-1"></i>Failed to load messages.</div>';
        });
}

// Load campaign-level comms when the Communications tab is first shown
var commsTabLoaded = false;
document.getElementById('tab-comms').addEventListener('shown.bs.tab', function () {
    if (!commsTabLoaded) {
        commsTabLoaded = true;
        loadHtml('/communications/partial_log.php?campaign_id=<?= $id ?>', 'comms-log-container');
    }
});

// Open channel comms modal and load messages for the given channel
function openChannelComms(channelId, label) {
    document.getElementById('channelCommsTitle').textContent = label;
    var modal = new bootstrap.Modal(document.getElementById('channelCommsModal'));
    modal.show();
    loadHtml('/communications/partial_log.php?channel_id=' + encodeURIComponent(channelId), 'channelCommsBody');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
