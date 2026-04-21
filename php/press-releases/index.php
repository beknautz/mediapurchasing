<?php
require_once __DIR__ . '/../bootstrap.php';
requireRole(['admin', 'buyer']);

$prService = new PressReleaseService();
$page      = max(1, (int) ($_GET['page'] ?? 1));
$result    = $prService->getPressReleases($page, 25);
$releases  = $result['data']  ?? [];
$total     = $result['total'] ?? 0;
$pages     = $result['pages'] ?? 1;

$pageTitle = 'Press Releases — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">
            <i class="bi bi-newspaper me-2 text-primary"></i>Press Releases
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Press Releases</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        <a href="/press-releases/templates.php" class="btn btn-outline-secondary">
            <i class="bi bi-file-earmark-text me-1"></i>Templates
        </a>
        <a href="/press-releases/compose.php" class="btn btn-primary">
            <i class="bi bi-send me-1"></i>New Press Release
        </a>
    </div>
</div>

<?php $flash = flash('success'); if ($flash): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle-fill me-2"></i><?= h($flash) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($releases)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-newspaper fs-2 d-block mb-2 opacity-50"></i>
            <div>No press releases sent yet.</div>
            <a href="/press-releases/compose.php" class="btn btn-sm btn-outline-primary mt-3">
                <i class="bi bi-send me-1"></i>Send First Press Release
            </a>
        </div>
        <?php else: ?>
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Subject</th>
                    <th class="text-center">Recipients</th>
                    <th>Sent By</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($releases as $pr): ?>
                <tr>
                    <td class="fw-semibold"><?= h($pr['subject']) ?></td>
                    <td class="text-center">
                        <span class="badge bg-secondary"><?= (int)$pr['recipient_count'] ?></span>
                    </td>
                    <td class="small text-muted"><?= h($pr['created_by_name'] ?? '—') ?></td>
                    <td class="small text-muted text-nowrap">
                        <?= !empty($pr['sent_at']) ? h(date('M j, Y g:ia', strtotime($pr['sent_at']))) : h(date('M j, Y', strtotime($pr['created_at']))) ?>
                    </td>
                    <td>
                        <?php if ($pr['status'] === 'sent'): ?>
                            <span class="badge bg-success">Sent</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Draft</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="/press-releases/view.php?id=<?= (int)$pr['id'] ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-eye me-1"></i>View
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($pages > 1): ?>
        <div class="d-flex justify-content-center py-3 border-top">
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($p = 1; $p <= $pages; $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
