<?php
require_once __DIR__ . '/../bootstrap.php';
requireRole(['admin', 'buyer']);

$id        = (int) ($_GET['id'] ?? 0);
$prService = new PressReleaseService();
$data      = $prService->getPressRelease($id);

if (!$data) {
    http_response_code(404);
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="alert alert-danger mt-4">Press release not found.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$pr         = $data['pr'];
$recipients = $data['recipients'];
$attachments = !empty($pr['attachments']) ? json_decode($pr['attachments'], true) : [];

$sentCount   = count(array_filter($recipients, fn($r) => $r['status'] === 'sent'));
$failedCount = count(array_filter($recipients, fn($r) => $r['status'] === 'failed'));
$total       = count($recipients);

$pageTitle = h($pr['subject']) . ' — Press Releases';
require_once __DIR__ . '/../includes/header.php';

$flash = flash('success');
?>

<?php if ($flash): ?>
<div class="alert alert-success alert-dismissible fade show mb-3">
    <i class="bi bi-check-circle-fill me-2"></i><?= h($flash) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">
            <i class="bi bi-newspaper me-2 text-primary"></i><?= h($pr['subject']) ?>
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="/press-releases/index.php">Press Releases</a></li>
                <li class="breadcrumb-item active">View</li>
            </ol>
        </nav>
    </div>
    <a href="/press-releases/compose.php" class="btn btn-primary">
        <i class="bi bi-send me-1"></i>New Press Release
    </a>
</div>

<!-- Stats bar -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-3 fw-bold text-primary"><?= $total ?></div>
            <div class="small text-muted">Total Recipients</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-3 fw-bold text-success"><?= $sentCount ?></div>
            <div class="small text-muted">Sent</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-3 fw-bold <?= $failedCount > 0 ? 'text-danger' : 'text-muted' ?>"><?= $failedCount ?></div>
            <div class="small text-muted">Failed</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fs-3 fw-bold text-secondary"><?= count($attachments) ?></div>
            <div class="small text-muted">Attachments</div>
        </div>
    </div>
</div>

<div class="row g-4">

    <!-- Left: Message -->
    <div class="col-lg-7">

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-envelope me-2 text-primary"></i>Message</h5>
                <div class="small text-muted">
                    <?php if (!empty($pr['sent_at'])): ?>
                        Sent <?= h(date('M j, Y g:ia', strtotime($pr['sent_at']))) ?>
                        <?= !empty($pr['created_by_name']) ? ' by ' . h($pr['created_by_name']) : '' ?>
                    <?php else: ?>
                        Created <?= h(date('M j, Y', strtotime($pr['created_at']))) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="border rounded p-3 bg-light" style="white-space:pre-wrap;font-size:.9rem;"><?= h($pr['body_text'] ?: strip_tags($pr['body_html'])) ?></div>
            </div>
        </div>

        <?php if (!empty($attachments)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-paperclip me-2 text-primary"></i>Attachments</h5>
            </div>
            <div class="card-body d-flex flex-wrap gap-2">
                <?php foreach ($attachments as $att):
                    $icons = ['pdf'=>'file-earmark-pdf','doc'=>'file-earmark-word','docx'=>'file-earmark-word',
                              'mp4'=>'film','mov'=>'film','avi'=>'film','wmv'=>'film','mkv'=>'film'];
                    $icon  = $icons[$att['ext'] ?? ''] ?? 'file-earmark';
                    $kb    = isset($att['size']) ? number_format($att['size'] / 1024, 0) : '—';
                ?>
                <span class="badge bg-light text-dark border px-3 py-2">
                    <i class="bi bi-<?= h($icon) ?> me-1 fs-6"></i>
                    <?= h($att['name']) ?>
                    <span class="text-muted ms-1 small"><?= h($kb) ?>KB</span>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Right: Recipients -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-people me-2 text-primary"></i>Recipients</h5>
                <?php if ($failedCount > 0): ?>
                <span class="badge bg-danger"><?= $failedCount ?> failed</span>
                <?php endif; ?>
            </div>
            <div class="card-body p-0" style="max-height:70vh;overflow-y:auto;">
                <?php if (empty($recipients)): ?>
                <div class="text-center text-muted py-4 small">No recipients recorded.</div>
                <?php else: ?>
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Vendor</th>
                            <th>Category</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recipients as $r): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold small"><?= h($r['company_name'] ?? '—') ?></div>
                            <div class="text-muted" style="font-size:.72rem;"><?= h($r['vendor_email']) ?></div>
                        </td>
                        <td class="small text-muted"><?= h($r['media_category'] ?? '—') ?></td>
                        <td>
                            <?php if ($r['status'] === 'sent'): ?>
                                <span class="badge bg-success">Sent</span>
                            <?php elseif ($r['status'] === 'failed'): ?>
                                <span class="badge bg-danger" <?= !empty($r['error_message']) ? 'title="'.h($r['error_message']).'"' : '' ?>>
                                    Failed
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Pending</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
