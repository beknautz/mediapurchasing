<?php
/**
 * communications/partial_log.php
 * HTMX partial — outputs communication log items for a given media buy.
 * No header/footer — consumed via hx-get from media-buys/view.php.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';

// This partial can be requested without a full session for HTMX calls,
// but session auth is still enforced.
if (empty($_SESSION['loggedIn'])) {
    http_response_code(403);
    echo '<div class="text-center text-danger py-3 small"><i class="bi bi-lock me-1"></i>Unauthorized</div>';
    exit;
}

$mediaBuyId = (int) ($_GET['media_buy_id'] ?? 0);

if ($mediaBuyId <= 0) {
    echo '<div class="text-center text-muted py-3 small">No media buy specified.</div>';
    exit;
}

$emailService = new EmailService();

$result = $emailService->getHistory(mediaBuyId: $mediaBuyId, pageSize: 50);
$comms  = $result['data'] ?? [];

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

if (empty($comms)):
?>
<div class="text-center text-muted py-4">
    <i class="bi bi-chat-square-dots fs-3 d-block mb-2 opacity-50"></i>
    <div class="small">No communications logged for this media buy.</div>
    <a href="/communications/compose.php?media_buy_id=<?= $mediaBuyId ?>" class="btn btn-sm btn-outline-primary mt-2">
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
        <!-- Icon -->
        <div class="flex-shrink-0 mt-1">
            <i class="bi bi-<?= $typeBadge['icon'] ?> text-<?= $isInbound ? 'info' : 'primary' ?> fs-5"></i>
        </div>

        <!-- Body -->
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

            <!-- From → To -->
            <div class="small text-muted mb-1">
                <i class="bi bi-arrow-right me-1"></i>
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

            <!-- Subject -->
            <?php if (!empty($comm['subject'])): ?>
            <div class="fw-semibold small mb-1">
                <?= h($comm['subject']) ?>
            </div>
            <?php endif; ?>

            <!-- Body preview -->
            <?php if ($bodyPreview !== ''): ?>
            <div class="small text-muted">
                <?= h($bodyPreview) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php if (($result['total'] ?? 0) > 50): ?>
<div class="text-center py-2 small text-muted border-top">
    Showing 50 of <?= number_format($result['total']) ?> messages.
    <a href="/communications/index.php?media_buy_id=<?= $mediaBuyId ?>">View all</a>
</div>
<?php endif; ?>

<?php endif; ?>
