<?php
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../config/ai_video.php';
requireRole(['admin', 'buyer']);

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('/admin/ai-video/index.php');

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

$campaign = $pdo->prepare('SELECT * FROM ai_video_campaigns WHERE id = :id');
$campaign->execute([':id' => $id]);
$campaign = $campaign->fetch();
if (!$campaign) {
    http_response_code(404);
    echo '<p>Campaign not found.</p>';
    exit;
}

// Load latest script, prompt, jobs, reviews
$latestScript = $pdo->prepare('SELECT * FROM ai_video_scripts WHERE campaign_id = :cid ORDER BY version_number DESC LIMIT 1');
$latestScript->execute([':cid' => $id]);
$latestScript = $latestScript->fetch();

$latestPrompt = $pdo->prepare('SELECT * FROM ai_video_prompts WHERE campaign_id = :cid ORDER BY version_number DESC LIMIT 1');
$latestPrompt->execute([':cid' => $id]);
$latestPrompt = $latestPrompt->fetch();

$jobs = $pdo->prepare('SELECT * FROM ai_video_jobs WHERE campaign_id = :cid ORDER BY created_at DESC');
$jobs->execute([':cid' => $id]);
$jobs = $jobs->fetchAll();

$reviews = $pdo->prepare('SELECT * FROM ai_video_reviews WHERE campaign_id = :cid ORDER BY created_at DESC');
$reviews->execute([':cid' => $id]);
$reviews = $reviews->fetchAll();

$latestJob = $jobs[0] ?? null;

// Cost summary
$costSvc = new AiVideoCostService();
$costSummary = $costSvc->getCostSummary($id);

$status = $campaign['status'];

$statusSteps = [
    'draft'              => ['icon' => 'pencil',                'label' => 'Draft'],
    'script_generated'   => ['icon' => 'file-earmark-text',    'label' => 'Script Ready'],
    'prompt_generated'   => ['icon' => 'magic',                'label' => 'Prompt Ready'],
    'video_queued'       => ['icon' => 'hourglass-split',      'label' => 'Video Queued'],
    'generating'         => ['icon' => 'arrow-repeat',         'label' => 'Generating'],
    'ready_for_review'   => ['icon' => 'eye',                  'label' => 'Ready for Review'],
    'approved'           => ['icon' => 'check-circle-fill',    'label' => 'Approved'],
    'revision_requested' => ['icon' => 'arrow-counterclockwise','label' => 'Revision Requested'],
    'failed'             => ['icon' => 'x-circle-fill',        'label' => 'Failed'],
    'archived'           => ['icon' => 'archive',              'label' => 'Archived'],
];

function statusBadge(string $s): string {
    $map = [
        'draft'=>'secondary','script_generated'=>'info','prompt_generated'=>'primary',
        'video_queued'=>'warning','generating'=>'warning','ready_for_review'=>'success',
        'approved'=>'success','revision_requested'=>'danger','failed'=>'danger','archived'=>'dark',
    ];
    $c = $map[$s] ?? 'secondary';
    return '<span class="badge bg-'.$c.'">'.h(ucwords(str_replace('_',' ',$s))).'</span>';
}

$pageTitle = h($campaign['campaign_name']) . ' — AI Video Studio';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="mb-4">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <h1 class="h3 fw-bold mb-0">
                <i class="bi bi-camera-video-fill me-2 text-danger"></i><?= h($campaign['campaign_name']) ?>
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="/admin/ai-video/index.php">AI Video Studio</a></li>
                    <li class="breadcrumb-item active"><?= h($campaign['campaign_name']) ?></li>
                </ol>
            </nav>
        </div>
        <div><?= statusBadge($status) ?></div>
    </div>
</div>

<div class="row g-4">
    <!-- LEFT COLUMN -->
    <div class="col-lg-4">
        <!-- Campaign Brief Card -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clipboard me-2 text-danger"></i>Campaign Brief</span>
                <a href="/admin/ai-video/create-campaign.php?clone=<?= (int)$id ?>" class="btn btn-xs btn-outline-secondary btn-sm">
                    <i class="bi bi-pencil me-1"></i>Edit
                </a>
            </div>
            <div class="card-body small">
                <dl class="mb-0 row row-cols-1 g-2">
                    <div class="col"><dt class="text-muted">Platform</dt><dd class="mb-0"><?= h($campaign['platform'] ?? '—') ?></dd></div>
                    <div class="col"><dt class="text-muted">Length</dt><dd class="mb-0"><?= h($campaign['video_length'] ?? '—') ?>s &middot; <?= h($campaign['aspect_ratio'] ?? '—') ?></dd></div>
                    <div class="col"><dt class="text-muted">Brand Voice</dt><dd class="mb-0"><?= h($campaign['brand_voice'] ?? '—') ?></dd></div>
                    <div class="col"><dt class="text-muted">CTA</dt><dd class="mb-0"><?= h($campaign['call_to_action'] ?? '—') ?></dd></div>
                    <div class="col"><dt class="text-muted">Objective</dt><dd class="mb-0"><?= h($campaign['objective'] ?? '—') ?></dd></div>
                    <div class="col"><dt class="text-muted">Audience</dt><dd class="mb-0"><?= h($campaign['target_audience'] ?? '—') ?></dd></div>
                    <div class="col"><dt class="text-muted">Offer</dt><dd class="mb-0"><?= h($campaign['offer'] ?? '—') ?></dd></div>
                    <?php if ($campaign['landing_page_url']): ?>
                    <div class="col"><dt class="text-muted">Landing Page</dt><dd class="mb-0"><a href="<?= h($campaign['landing_page_url']) ?>" target="_blank" class="small"><?= h($campaign['landing_page_url']) ?></a></dd></div>
                    <?php endif; ?>
                    <?php if ($campaign['notes']): ?>
                    <div class="col"><dt class="text-muted">Notes</dt><dd class="mb-0"><?= h($campaign['notes']) ?></dd></div>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <!-- Status Timeline -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-arrow-right-circle me-2 text-primary"></i>Progress
            </div>
            <div class="card-body p-2">
                <?php
                $flow = ['draft','script_generated','prompt_generated','video_queued','generating','ready_for_review','approved'];
                $currentIdx = array_search($status, $flow);
                foreach ($flow as $i => $step):
                    $info    = $statusSteps[$step];
                    $done    = ($currentIdx !== false && $i <= $currentIdx);
                    $current = ($step === $status);
                    $cls     = $done ? 'text-success' : 'text-muted';
                    if ($current) $cls = 'text-primary fw-bold';
                ?>
                <div class="d-flex align-items-center gap-2 py-1 px-2 <?= $current ? 'bg-light rounded' : '' ?>">
                    <i class="bi bi-<?= $done ? 'check-circle-fill text-success' : 'circle text-muted' ?> flex-shrink-0"></i>
                    <span class="small <?= $cls ?>"><?= h($info['label']) ?></span>
                </div>
                <?php if ($i < count($flow)-1): ?>
                    <div class="ms-3 ps-1" style="border-left:1px dashed #dee2e6;height:8px;"></div>
                <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($status === 'revision_requested'): ?>
                <div class="d-flex align-items-center gap-2 py-1 px-2 bg-warning-subtle rounded mt-1">
                    <i class="bi bi-arrow-counterclockwise text-warning flex-shrink-0"></i>
                    <span class="small text-warning fw-bold">Revision Requested</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cost Summary -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-currency-dollar me-2 text-success"></i>Cost Summary
            </div>
            <div class="card-body small" id="cost-summary-widget">
                <div class="d-flex justify-content-between border-bottom pb-1 mb-1">
                    <span class="text-muted">Claude AI</span>
                    <span>$<?= number_format($costSummary['claude_cost'], 4) ?></span>
                </div>
                <div class="d-flex justify-content-between border-bottom pb-1 mb-1">
                    <span class="text-muted">Video Generation</span>
                    <span>$<?= number_format($costSummary['provider_cost'], 4) ?></span>
                </div>
                <div class="d-flex justify-content-between border-bottom pb-1 mb-1 fw-semibold">
                    <span>Internal Total</span>
                    <span>$<?= number_format($costSummary['internal_cost'], 4) ?></span>
                </div>
                <div class="d-flex justify-content-between border-bottom pb-1 mb-1 text-success">
                    <span>Markup (<?= number_format($costSummary['markup_pct'], 0) ?>%)</span>
                    <span>$<?= number_format($costSummary['markup_amount'], 2) ?></span>
                </div>
                <div class="d-flex justify-content-between fw-bold">
                    <span>Billable Fee</span>
                    <span class="text-primary">$<?= number_format($costSummary['billable_fee'], 2) ?></span>
                </div>
                <div class="mt-2 text-center">
                    <a href="/admin/ai-video/costs.php?campaign_id=<?= (int)$id ?>" class="btn btn-sm btn-outline-success w-100">
                        <i class="bi bi-bar-chart me-1"></i>Full Cost Report
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN -->
    <div class="col-lg-8">
        <!-- Action Buttons -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-lightning-fill me-2 text-warning"></i>Actions
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2" id="action-area">
                    <?php if ($status === 'draft'): ?>
                        <button class="btn btn-primary"
                                hx-post="/admin/ai-video/actions/generate-script.php"
                                hx-vals='{"campaign_id": "<?= (int)$id ?>"}'
                                hx-target="#action-result"
                                hx-swap="innerHTML"
                                hx-indicator="#spinner-script">
                            <i class="bi bi-stars me-1"></i>Generate Script with Claude
                        </button>
                        <span id="spinner-script" class="htmx-indicator ms-2 align-self-center">
                            <span class="spinner-border spinner-border-sm me-1"></span>Generating script...
                        </span>

                    <?php elseif ($status === 'script_generated'): ?>
                        <a href="/admin/ai-video/script-editor.php?campaign_id=<?= (int)$id ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-pencil me-1"></i>Edit Script
                        </a>
                        <button class="btn btn-primary"
                                hx-post="/admin/ai-video/actions/generate-veo-prompt.php"
                                hx-vals='{"campaign_id": "<?= (int)$id ?>"}'
                                hx-target="#action-result"
                                hx-swap="innerHTML"
                                hx-indicator="#spinner-prompt">
                            <i class="bi bi-magic me-1"></i>Generate Veo Prompt
                        </button>
                        <span id="spinner-prompt" class="htmx-indicator ms-2 align-self-center">
                            <span class="spinner-border spinner-border-sm me-1"></span>Generating prompt...
                        </span>

                    <?php elseif ($status === 'prompt_generated'): ?>
                        <a href="/admin/ai-video/script-editor.php?campaign_id=<?= (int)$id ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-pencil me-1"></i>Edit Script
                        </a>
                        <a href="/admin/ai-video/prompt-editor.php?campaign_id=<?= (int)$id ?>" class="btn btn-outline-info">
                            <i class="bi bi-magic me-1"></i>Edit Prompt
                        </a>
                        <button class="btn btn-primary"
                                hx-post="/admin/ai-video/actions/queue-video-job.php"
                                hx-vals='{"campaign_id": "<?= (int)$id ?>"}'
                                hx-target="#action-result"
                                hx-swap="innerHTML"
                                hx-indicator="#spinner-queue">
                            <i class="bi bi-film me-1"></i>Queue Video Generation
                        </button>
                        <span id="spinner-queue" class="htmx-indicator ms-2 align-self-center">
                            <span class="spinner-border spinner-border-sm me-1"></span>Queueing...
                        </span>

                    <?php elseif (in_array($status, ['video_queued', 'generating'])): ?>
                        <?php if ($latestJob): ?>
                        <button class="btn btn-warning"
                                hx-get="/admin/ai-video/actions/poll-video-status.php?job_id=<?= (int)$latestJob['id'] ?>"
                                hx-target="#action-result"
                                hx-swap="innerHTML"
                                hx-indicator="#spinner-poll">
                            <i class="bi bi-arrow-repeat me-1"></i>Check Status
                        </button>
                        <span id="spinner-poll" class="htmx-indicator ms-2 align-self-center">
                            <span class="spinner-border spinner-border-sm me-1"></span>Checking...
                        </span>
                        <?php endif; ?>

                    <?php elseif ($status === 'ready_for_review'): ?>
                        <button class="btn btn-success"
                                hx-post="/admin/ai-video/actions/save-review-comment.php"
                                hx-vals='{"campaign_id": "<?= (int)$id ?>", "job_id": "<?= (int)($latestJob['id'] ?? 0) ?>", "action": "notify_client"}'
                                hx-target="#action-result"
                                hx-swap="innerHTML"
                                hx-indicator="#spinner-notify">
                            <i class="bi bi-send me-1"></i>Send to Client for Review
                        </button>
                        <span id="spinner-notify" class="htmx-indicator ms-2 align-self-center">
                            <span class="spinner-border spinner-border-sm me-1"></span>Sending...
                        </span>
                        <?php if ($latestJob): ?>
                        <button class="btn btn-outline-success"
                                hx-post="/admin/ai-video/actions/approve-video.php"
                                hx-vals='{"campaign_id": "<?= (int)$id ?>", "job_id": "<?= (int)$latestJob['id'] ?>"}'
                                hx-target="#action-result"
                                hx-swap="innerHTML">
                            <i class="bi bi-check-circle me-1"></i>Mark Approved
                        </button>
                        <?php endif; ?>

                    <?php elseif ($status === 'revision_requested'): ?>
                        <button class="btn btn-primary"
                                hx-post="/admin/ai-video/actions/generate-script.php"
                                hx-vals='{"campaign_id": "<?= (int)$id ?>"}'
                                hx-target="#action-result"
                                hx-swap="innerHTML"
                                hx-indicator="#spinner-regen">
                            <i class="bi bi-arrow-repeat me-1"></i>Regenerate Script
                        </button>
                        <span id="spinner-regen" class="htmx-indicator ms-2 align-self-center">
                            <span class="spinner-border spinner-border-sm me-1"></span>Regenerating...
                        </span>

                    <?php elseif ($status === 'approved'): ?>
                        <div class="alert alert-success mb-0 py-2 px-3 w-100">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <strong>Campaign Approved!</strong> This video has been approved by the client.
                        </div>
                    <?php endif; ?>

                    <?php if ($status !== 'archived' && $status !== 'approved'): ?>
                        <button class="btn btn-outline-dark btn-sm ms-auto"
                                onclick="if(confirm('Archive this campaign?')) window.location='/admin/ai-video/actions/save-campaign.php?campaign_id=<?= (int)$id ?>&status=archived'">
                            <i class="bi bi-archive me-1"></i>Archive
                        </button>
                    <?php endif; ?>
                </div>
                <div id="action-result" class="mt-3"></div>
            </div>
        </div>

        <!-- Tabs: Script / Prompt / Jobs / Reviews -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white p-0">
                <ul class="nav nav-tabs border-bottom-0 px-3 pt-2" id="campaignTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-script" type="button">
                            <i class="bi bi-file-earmark-text me-1"></i>Script
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-prompt" type="button">
                            <i class="bi bi-magic me-1"></i>Veo Prompt
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-jobs" type="button">
                            <i class="bi bi-film me-1"></i>Jobs <span class="badge bg-secondary ms-1"><?= count($jobs) ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-reviews" type="button">
                            <i class="bi bi-chat-square-text me-1"></i>Reviews <span class="badge bg-secondary ms-1"><?= count($reviews) ?></span>
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body tab-content p-3">
                <!-- Script Tab -->
                <div class="tab-pane fade show active" id="tab-script">
                    <?php if ($latestScript): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-info">Version <?= (int)$latestScript['version_number'] ?></span>
                            <a href="/admin/ai-video/script-editor.php?campaign_id=<?= (int)$id ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil me-1"></i>Edit / View All Versions
                            </a>
                        </div>
                        <?php if ($latestScript['hook']): ?>
                        <div class="alert alert-info py-2 mb-2">
                            <strong><i class="bi bi-lightning me-1"></i>Hook:</strong> <?= h($latestScript['hook']) ?>
                        </div>
                        <?php endif; ?>
                        <pre class="bg-light rounded p-3 small" style="white-space:pre-wrap;max-height:300px;overflow-y:auto;"><?= h($latestScript['script_text'] ?? '') ?></pre>
                        <div class="small text-muted mt-1">
                            Model: <?= h($latestScript['claude_model'] ?? '') ?> &middot;
                            Tokens: <?= number_format((int)$latestScript['claude_input_tokens']) ?> in / <?= number_format((int)$latestScript['claude_output_tokens']) ?> out &middot;
                            Cost: $<?= number_format((float)$latestScript['claude_cost'], 4) ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-file-earmark-text display-5 d-block mb-2"></i>
                            No script yet.
                            <?php if ($status === 'draft'): ?>
                            <div class="mt-2">
                                <button class="btn btn-primary"
                                        hx-post="/admin/ai-video/actions/generate-script.php"
                                        hx-vals='{"campaign_id": "<?= (int)$id ?>"}'
                                        hx-target="#action-result"
                                        hx-swap="innerHTML">
                                    <i class="bi bi-stars me-1"></i>Generate Script
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Prompt Tab -->
                <div class="tab-pane fade" id="tab-prompt">
                    <?php if ($latestPrompt): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-primary">Version <?= (int)$latestPrompt['version_number'] ?></span>
                            <a href="/admin/ai-video/prompt-editor.php?campaign_id=<?= (int)$id ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil me-1"></i>Edit / View All Versions
                            </a>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small text-muted">Veo Prompt</label>
                            <pre class="bg-light rounded p-3 small" style="white-space:pre-wrap;max-height:200px;overflow-y:auto;"><?= h($latestPrompt['veo_prompt'] ?? '') ?></pre>
                        </div>
                        <?php if ($latestPrompt['negative_prompt']): ?>
                        <div class="mb-2">
                            <label class="form-label small text-muted">Negative Prompt</label>
                            <div class="bg-light rounded p-2 small text-muted"><?= h($latestPrompt['negative_prompt']) ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="row g-2 small text-muted">
                            <div class="col-auto"><strong>Style:</strong> <?= h($latestPrompt['visual_style'] ?? '—') ?></div>
                            <div class="col-auto"><strong>Camera:</strong> <?= h($latestPrompt['camera_direction'] ?? '—') ?></div>
                            <div class="col-auto"><strong>Lighting:</strong> <?= h($latestPrompt['lighting'] ?? '—') ?></div>
                            <div class="col-auto"><strong>Pacing:</strong> <?= h($latestPrompt['pacing'] ?? '—') ?></div>
                        </div>
                        <div class="small text-muted mt-1">
                            Cost: $<?= number_format((float)$latestPrompt['claude_cost'], 4) ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-magic display-5 d-block mb-2"></i>
                            No Veo prompt yet.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Jobs Tab -->
                <div class="tab-pane fade" id="tab-jobs">
                    <?php if (empty($jobs)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-film display-5 d-block mb-2"></i>No video jobs yet.
                        </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover small align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>#</th><th>Status</th><th>Provider</th><th>Duration</th><th>Ratio</th><th>Cost</th><th>Created</th><th>Video</th><th></th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($jobs as $j): ?>
                            <tr>
                                <td><?= (int)$j['id'] ?></td>
                                <td>
                                    <?php
                                    $jmap = ['queued'=>'warning','processing'=>'info','completed'=>'success','failed'=>'danger'];
                                    $jc   = $jmap[$j['job_status']] ?? 'secondary';
                                    echo '<span class="badge bg-'.$jc.'">'.h($j['job_status']).'</span>';
                                    ?>
                                </td>
                                <td><?= h($j['provider']) ?></td>
                                <td><?= $j['requested_duration_seconds'] ? h($j['requested_duration_seconds']).'s' : '—' ?></td>
                                <td><?= h($j['requested_aspect_ratio'] ?? '—') ?></td>
                                <td>$<?= number_format((float)$j['actual_provider_cost'], 2) ?></td>
                                <td class="text-nowrap"><?= h(date('M j', strtotime($j['created_at']))) ?></td>
                                <td>
                                    <?php if ($j['video_url']): ?>
                                        <a href="<?= h($j['video_url']) ?>" target="_blank" class="btn btn-xs btn-sm btn-outline-success py-0 px-1">
                                            <i class="bi bi-play-fill"></i>
                                        </a>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td>
                                    <?php if (in_array($j['job_status'], ['queued','processing'])): ?>
                                    <button class="btn btn-sm btn-outline-warning py-0 px-1"
                                            hx-get="/admin/ai-video/actions/poll-video-status.php?job_id=<?= (int)$j['id'] ?>"
                                            hx-target="#action-result"
                                            hx-swap="innerHTML">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Reviews Tab -->
                <div class="tab-pane fade" id="tab-reviews">
                    <?php if (empty($reviews)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-chat-square-text display-5 d-block mb-2"></i>No reviews yet.
                        </div>
                    <?php else: ?>
                    <?php foreach ($reviews as $r): ?>
                    <div class="card border mb-2">
                        <div class="card-body py-2 px-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <?php
                                    $rmap = ['pending'=>'secondary','approved'=>'success','revision_requested'=>'danger'];
                                    $rc   = $rmap[$r['review_status']] ?? 'secondary';
                                    echo '<span class="badge bg-'.$rc.' me-2">'.h($r['review_status']).'</span>';
                                    ?>
                                    <span class="small text-muted"><?= $r['reviewer_name'] ? h($r['reviewer_name']) : 'Client' ?></span>
                                </div>
                                <span class="small text-muted"><?= h(date('M j, Y', strtotime($r['created_at']))) ?></span>
                            </div>
                            <?php if ($r['comments']): ?>
                            <div class="mt-1 small"><?= h($r['comments']) ?></div>
                            <?php endif; ?>
                            <?php if ($r['revision_notes']): ?>
                            <div class="mt-1 small text-danger"><strong>Revision Notes:</strong> <?= h($r['revision_notes']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Add comment form -->
                    <?php if ($latestJob): ?>
                    <div class="mt-3">
                        <form hx-post="/admin/ai-video/actions/save-review-comment.php"
                              hx-target="#review-comment-result"
                              hx-swap="innerHTML">
                            <input type="hidden" name="campaign_id" value="<?= (int)$id ?>">
                            <input type="hidden" name="job_id" value="<?= (int)$latestJob['id'] ?>">
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Add Internal Comment</label>
                                <textarea name="comments" class="form-control form-control-sm" rows="2" placeholder="Internal review notes..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-chat-plus me-1"></i>Save Comment
                            </button>
                        </form>
                        <div id="review-comment-result" class="mt-2"></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
