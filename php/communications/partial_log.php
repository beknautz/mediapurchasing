<?php
/**
 * communications/partial_log.php
 * Partial — outputs communication log items for a media buy, campaign, or channel.
 * No header/footer — loaded via fetch() from campaign/media-buy view pages.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['loggedIn'])) {
    http_response_code(403);
    echo '<div class="text-center text-danger py-3 small"><i class="bi bi-lock me-1"></i>Unauthorized</div>';
    exit;
}

$mediaBuyId = (int) ($_GET['media_buy_id'] ?? 0);
$campaignId = (int) ($_GET['campaign_id']  ?? 0);
$channelId  = (int) ($_GET['channel_id']   ?? 0);

if ($mediaBuyId <= 0 && $campaignId <= 0 && $channelId <= 0) {
    echo '<div class="text-center text-muted py-3 small">No entity specified.</div>';
    exit;
}

// ---------------------------------------------------------------------------
// Fetch communications
// For channel_id we use a direct DB query keyed on rfp_log_id + vendor email
// so it works regardless of whether the channel_id migration column exists.
// ---------------------------------------------------------------------------
$comms  = [];
$total  = 0;

if ($channelId > 0) {
    $db = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET),
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // Get channel details: outbound RFP log id + vendor email
    $chStmt = $db->prepare(
        'SELECT cc.rfp_log_id, cc.campaign_id,
                v.email AS vendor_email, v.billing_email AS vendor_billing_email
           FROM campaign_channels cc
      LEFT JOIN vendors v ON v.id = cc.vendor_id
          WHERE cc.id = :id LIMIT 1'
    );
    $chStmt->execute([':id' => $channelId]);
    $chInfo = $chStmt->fetch();

    if ($chInfo) {
        $orClauses = [];
        $params    = [];

        // The outbound RFP email (identified by the log id stored on the channel)
        if (!empty($chInfo['rfp_log_id'])) {
            $orClauses[]       = 'cl.id = :rfp_id';
            $params[':rfp_id'] = (int) $chInfo['rfp_log_id'];
        }

        // Inbound replies from the vendor's email address.
        // Use LIKE so rows stored with "Name <email>" format (pre-normalization) also match.
        $vendorEmail = $chInfo['vendor_email'] ?: $chInfo['vendor_billing_email'];
        if ($vendorEmail) {
            $orClauses[]             = "(cl.from_email LIKE :v_email AND cl.comm_type = 'email_inbound')";
            $params[':v_email']      = '%' . $vendorEmail . '%';
        }

        if ($orClauses) {
            $stmt = $db->prepare(
                'SELECT cl.*,
                        CASE WHEN cl.comm_type = "email_inbound" THEN "email" ELSE cl.comm_type END AS type,
                        CASE WHEN cl.comm_type = "email_inbound" THEN "inbound" ELSE "outbound" END AS direction
                   FROM communication_logs cl
                  WHERE ' . implode(' OR ', $orClauses) . '
                  ORDER BY cl.created_at ASC
                  LIMIT 50'
            );
            $stmt->execute($params);
            $comms = $stmt->fetchAll();
            $total = count($comms);
        }
    }
} else {
    $emailService = new EmailService();
    $result = $emailService->getHistory(
        mediaBuyId: $mediaBuyId,
        pageSize:   50,
        campaignId: $campaignId
    );
    $comms = $result['data'] ?? [];
    $total = $result['total'] ?? 0;
}

// ---------------------------------------------------------------------------
// Compose link (for empty-state button)
// ---------------------------------------------------------------------------
$composeLink = '/communications/compose.php?';
if ($campaignId > 0 || $channelId > 0) {
    $composeLink .= 'campaign_id=' . ($campaignId ?: ($chInfo['campaign_id'] ?? 0));
} elseif ($mediaBuyId > 0) {
    $composeLink .= 'media_buy_id=' . $mediaBuyId;
}

// ---------------------------------------------------------------------------
// Badge maps
// ---------------------------------------------------------------------------
$typeBadges = [
    'email' => ['class' => 'bg-primary', 'icon' => 'envelope'],
    'sms'   => ['class' => 'bg-success', 'icon' => 'chat-dots'],
];
$directionBadges = [
    'inbound'  => ['class' => 'bg-info text-dark',   'icon' => 'arrow-down-left'],
    'outbound' => ['class' => 'bg-warning text-dark', 'icon' => 'arrow-up-right'],
];
$statusColors = [
    'sent'      => 'text-success',
    'delivered' => 'text-success',
    'failed'    => 'text-danger',
    'bounced'   => 'text-danger',
    'pending'   => 'text-warning',
    'received'  => 'text-info',
];
?>

<?php if (empty($comms)): ?>
<div class="text-center text-muted py-4">
    <i class="bi bi-chat-square-dots fs-3 d-block mb-2 opacity-50"></i>
    <div class="small">No communications logged yet.</div>
    <a href="<?= h($composeLink) ?>" class="btn btn-sm btn-outline-primary mt-2">
        <i class="bi bi-send me-1"></i>Send First Message
    </a>
</div>
<?php else: ?>
<div class="comm-log">
<?php foreach ($comms as $comm):
    $commType  = $comm['type']      ?? 'email';
    $commDir   = $comm['direction'] ?? 'outbound';
    $typeBadge = $typeBadges[$commType]     ?? ['class' => 'bg-secondary', 'icon' => 'question'];
    $dirBadge  = $directionBadges[$commDir] ?? ['class' => 'bg-secondary', 'icon' => 'arrow-right'];
    $commStatus= $comm['status'] ?? '';
    $statClass = $statusColors[$commStatus] ?? 'text-muted';
    $isInbound = $commDir === 'inbound';

    $bodyPreview = '';
    if (!empty($comm['body_text'])) {
        $bodyPreview = substr(strip_tags($comm['body_text']), 0, 200);
    } elseif (!empty($comm['body_html'])) {
        $bodyPreview = substr(strip_tags($comm['body_html']), 0, 200);
    }
    if (strlen($bodyPreview) === 200) {
        $bodyPreview .= '…';
    }
?>
<div class="comm-item border-bottom px-3 py-3 <?= $isInbound ? 'bg-light' : '' ?>">
    <div class="d-flex align-items-start gap-2">
        <div class="flex-shrink-0 mt-1">
            <i class="bi bi-<?= $typeBadge['icon'] ?> text-<?= $isInbound ? 'info' : 'primary' ?> fs-5"></i>
        </div>
        <div class="flex-grow-1 min-w-0">
            <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                <span class="badge <?= $typeBadge['class'] ?> small">
                    <?= h(strtoupper($commType)) ?>
                </span>
                <span class="badge <?= $dirBadge['class'] ?> small">
                    <i class="bi bi-<?= $dirBadge['icon'] ?> me-1"></i><?= h(ucfirst($commDir)) ?>
                </span>
                <?php if ($commStatus): ?>
                    <span class="small <?= $statClass ?>">
                        <i class="bi bi-circle-fill" style="font-size:.4rem;"></i>
                        <?= h(ucfirst($commStatus)) ?>
                    </span>
                <?php endif; ?>
                <span class="ms-auto small text-muted text-nowrap">
                    <?= !empty($comm['created_at']) ? h(date('M j, Y g:ia', strtotime($comm['created_at']))) : '' ?>
                </span>
            </div>

            <div class="small text-muted mb-1">
                <strong><?= h($comm['from_email'] ?? $comm['from_number'] ?? 'System') ?></strong>
                <?php if (!empty($comm['from_name'])): ?>
                    (<?= h($comm['from_name']) ?>)
                <?php endif; ?>
                &rarr;
                <strong><?= h($comm['to_email'] ?? $comm['to_number'] ?? '—') ?></strong>
                <?php if (!empty($comm['to_name'])): ?>
                    (<?= h($comm['to_name']) ?>)
                <?php endif; ?>
            </div>

            <?php if (!empty($comm['subject'])): ?>
            <div class="fw-semibold small mb-1"><?= h($comm['subject']) ?></div>
            <?php endif; ?>

            <?php if ($bodyPreview !== ''): ?>
            <div class="small text-muted"><?= h($bodyPreview) ?></div>
            <?php endif; ?>

            <?php if (!empty($comm['body_html']) || !empty($comm['body_text'])): ?>
            <div class="mt-2">
                <button class="btn btn-link btn-sm p-0 text-decoration-none small"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#commBody<?= (int)$comm['id'] ?>"
                        aria-expanded="false">
                    <i class="bi bi-chevron-down me-1"></i>View Full Message
                </button>
                <div class="collapse mt-2" id="commBody<?= (int)$comm['id'] ?>">
                    <?php if (!empty($comm['body_html'])): ?>
                    <iframe srcdoc="<?= htmlspecialchars($comm['body_html'], ENT_QUOTES, 'UTF-8') ?>"
                            sandbox
                            class="w-100 border rounded bg-white"
                            style="min-height:150px;max-height:500px;display:block;"
                            onload="this.style.height=Math.min(this.contentDocument.documentElement.scrollHeight+20,500)+'px'">
                    </iframe>
                    <?php else: ?>
                    <pre class="small bg-light p-2 rounded mb-0" style="white-space:pre-wrap;max-height:400px;overflow-y:auto;"><?= h($comm['body_text']) ?></pre>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php
            $attachments = !empty($comm['attachments']) ? json_decode($comm['attachments'], true) : [];
            if (!empty($attachments)):
            ?>
            <div class="mt-2 d-flex flex-wrap gap-2">
                <?php foreach ($attachments as $att):
                    $iconMap = ['pdf'=>'file-earmark-pdf','doc'=>'file-earmark-word','docx'=>'file-earmark-word','xls'=>'file-earmark-excel','xlsx'=>'file-earmark-excel'];
                    $icon    = $iconMap[$att['ext'] ?? ''] ?? 'file-earmark';
                    $dlUrl   = '/api/download_attachment.php?log_id=' . (int)$comm['id'] . '&file=' . urlencode(basename($att['path']));
                ?>
                <a href="<?= h($dlUrl) ?>" class="btn btn-sm btn-outline-secondary" download>
                    <i class="bi bi-<?= h($icon) ?> me-1"></i><?= h($att['name']) ?>
                    <span class="text-muted ms-1" style="font-size:.7rem;"><?= number_format($att['size'] / 1024, 0) ?>KB</span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php if ($total > 50): ?>
<div class="text-center py-2 small text-muted border-top">
    Showing 50 of <?= number_format($total) ?> messages.
    <a href="/communications/index.php?<?= $mediaBuyId > 0 ? 'media_buy_id='.$mediaBuyId : 'campaign_id='.($campaignId ?: ($chInfo['campaign_id'] ?? 0)) ?>">View all</a>
</div>
<?php endif; ?>

<?php endif; ?>
