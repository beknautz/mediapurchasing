<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';
requireRole(['admin', 'buyer']);

$svc   = new MarketingAutomationService();
$stats = $svc->getDashboardStats();
$upcoming = $svc->getUpcomingSchedule(7);
$recentCopy = $svc->getAdCopy(['status' => '']);

$pageTitle = 'Ad Automation — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">
            <i class="bi bi-robot me-2 text-primary"></i>Ad Automation
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Ad Automation</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/ad-automation/posts.php?new=1" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-plus-circle me-1"></i>New Post
        </a>
        <a href="/ad-automation/ad-generator.php" class="btn btn-sm btn-outline-success">
            <i class="bi bi-stars me-1"></i>Generate Ad
        </a>
        <a href="/ad-automation/campaigns.php?new=1" class="btn btn-sm btn-outline-warning">
            <i class="bi bi-megaphone me-1"></i>New Campaign
        </a>
        <a href="/ad-automation/schedules.php?new=1" class="btn btn-sm btn-outline-info">
            <i class="bi bi-calendar-plus me-1"></i>Schedule Ad
        </a>
    </div>
</div>

<!-- ── Stats Cards ─────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md">
        <a href="/ad-automation/posts.php?status=draft" class="text-decoration-none">
            <div class="card border-0 shadow-sm text-center py-3 h-100">
                <div class="fs-3 fw-bold text-secondary"><?= (int)$stats['draft_posts'] ?></div>
                <div class="small text-muted">Draft Posts</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md">
        <a href="/ad-automation/posts.php?status=scheduled" class="text-decoration-none">
            <div class="card border-0 shadow-sm text-center py-3 h-100">
                <div class="fs-3 fw-bold text-primary"><?= (int)$stats['scheduled_posts'] ?></div>
                <div class="small text-muted">Scheduled Posts</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md">
        <a href="/ad-automation/campaigns.php?status=active" class="text-decoration-none">
            <div class="card border-0 shadow-sm text-center py-3 h-100">
                <div class="fs-3 fw-bold text-success"><?= (int)$stats['active_campaigns'] ?></div>
                <div class="small text-muted">Active Campaigns</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md">
        <a href="/ad-automation/schedules.php" class="text-decoration-none">
            <div class="card border-0 shadow-sm text-center py-3 h-100">
                <div class="fs-3 fw-bold text-warning"><?= (int)$stats['pending_ads'] ?></div>
                <div class="small text-muted">Pending Ads</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md">
        <div class="card border-0 shadow-sm text-center py-3 h-100 <?= $stats['failed_jobs'] > 0 ? 'border-danger border' : '' ?>">
            <div class="fs-3 fw-bold <?= $stats['failed_jobs'] > 0 ? 'text-danger' : 'text-success' ?>">
                <?= $stats['failed_jobs'] > 0 ? (int)$stats['failed_jobs'] : '<i class="bi bi-check-circle"></i>' ?>
            </div>
            <div class="small text-muted">Failed Jobs</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Upcoming Schedule -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-calendar-week me-2 text-primary"></i>Upcoming (Next 7 Days)
            </div>
            <div class="card-body p-0">
                <?php if (empty($upcoming)): ?>
                <div class="text-center text-muted py-4 small">Nothing scheduled in the next 7 days.</div>
                <?php else: ?>
                <table class="table table-sm table-hover mb-0 align-middle small">
                    <thead class="table-light">
                        <tr><th>Name</th><th>Type</th><th>Platform</th><th>Date</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($upcoming as $item): ?>
                    <tr>
                        <td class="fw-semibold"><?= h($item['name']) ?></td>
                        <td><?= $item['item_type'] === 'post' ? '<span class="badge bg-primary">Post</span>' : '<span class="badge bg-warning text-dark">Ad</span>' ?></td>
                        <td><?php echo platformBadge($item['platform']); ?></td>
                        <td class="text-nowrap"><?= $item['starts_at'] ? h(date('M j g:ia', strtotime($item['starts_at']))) : '—' ?></td>
                        <td><?php echo statusBadge($item['status']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Ad Copy -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-earmark-text me-2 text-success"></i>Recent Ad Copy</span>
                <a href="/ad-automation/ad-generator.php" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-stars me-1"></i>Generate
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentCopy)): ?>
                <div class="text-center text-muted py-4 small">No ad copy yet.</div>
                <?php else: ?>
                <table class="table table-sm table-hover mb-0 align-middle small">
                    <thead class="table-light">
                        <tr><th>Headline / Description</th><th>Platform</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach (array_slice($recentCopy, 0, 8) as $copy): ?>
                    <tr>
                        <td><?= h($copy['headline'] ?: ($copy['google_headlines'] ? json_decode($copy['google_headlines'], true)[0] ?? '—' : '—')) ?></td>
                        <td><?php echo platformBadge($copy['platform']); ?></td>
                        <td><?php echo statusBadge($copy['status']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php

function platformBadge(string $platform): string {
    $map = [
        'facebook'        => ['primary',  'Facebook'],
        'instagram'       => ['danger',   'Instagram'],
        'google_business' => ['warning',  'Google Business'],
        'google'          => ['warning',  'Google Ads'],
        'linkedin'        => ['info',     'LinkedIn'],
        'meta'            => ['primary',  'Meta'],
        'both'            => ['secondary','Meta + Google'],
    ];
    $d = $map[$platform] ?? ['secondary', h($platform)];
    $textClass = $d[0] === 'warning' ? ' text-dark' : '';
    return '<span class="badge bg-' . $d[0] . $textClass . '">' . h($d[1]) . '</span>';
}

function statusBadge(string $status): string {
    $map = [
        'draft'     => 'secondary',
        'scheduled' => 'primary',
        'published' => 'success',
        'active'    => 'success',
        'approved'  => 'success',
        'failed'    => 'danger',
        'cancelled' => 'dark',
        'rejected'  => 'danger',
        'paused'    => 'warning',
        'completed' => 'info',
        'ready'     => 'info',
        'archived'  => 'secondary',
    ];
    $color = $map[$status] ?? 'secondary';
    $textClass = $color === 'warning' ? ' text-dark' : '';
    return '<span class="badge bg-' . $color . $textClass . '">' . h(ucfirst($status)) . '</span>';
}

require_once __DIR__ . '/../includes/footer.php';
?>
