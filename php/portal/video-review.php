<?php
/**
 * portal/video-review.php
 * Client-facing video review portal — no internal auth required.
 * Access controlled by review_token URL parameter only.
 */

// Bootstrap only for DB + helpers — no session/role check
$_bootstrapPortal = true;
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/ai_video.php';

$token = trim($_GET['token'] ?? '');

if ($token === '') {
    http_response_code(404);
    $pageError = 'Review link not found.';
}

$campaign = null;
$job      = null;
$review   = null;
$client   = null;

if (!isset($pageError)) {
    try {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $stmt = $pdo->prepare('SELECT * FROM ai_video_campaigns WHERE review_token = :token LIMIT 1');
        $stmt->execute([':token' => $token]);
        $campaign = $stmt->fetch();

        if (!$campaign) {
            http_response_code(404);
            $pageError = 'Review link not found or has expired.';
        } else {
            // Load latest completed job
            $jobStmt = $pdo->prepare(
                'SELECT id, job_status, video_url, thumbnail_url, requested_duration_seconds, requested_aspect_ratio, completed_at
                   FROM ai_video_jobs
                  WHERE campaign_id = :cid
                  ORDER BY CASE job_status WHEN "completed" THEN 0 ELSE 1 END, id DESC
                  LIMIT 1'
            );
            $jobStmt->execute([':cid' => $campaign['id']]);
            $job = $jobStmt->fetch();

            // Load latest review
            $revStmt = $pdo->prepare(
                'SELECT * FROM ai_video_reviews WHERE campaign_id = :cid ORDER BY id DESC LIMIT 1'
            );
            $revStmt->execute([':cid' => $campaign['id']]);
            $review = $revStmt->fetch();

            // Try to load client name (graceful — column names may vary)
            try {
                $clStmt = $pdo->prepare(
                    'SELECT COALESCE(company_name, name, contact_name, "") AS client_name FROM clients WHERE id = :id'
                );
                $clStmt->execute([':id' => $campaign['client_id']]);
                $clientRow = $clStmt->fetch();
                $client    = $clientRow['client_name'] ?? '';
            } catch (Throwable $e) {
                $client = '';
            }
        }
    } catch (Throwable $e) {
        error_log('[AI Video Portal] DB error: ' . $e->getMessage());
        http_response_code(500);
        $pageError = 'An unexpected error occurred. Please try again later.';
    }
}

$reviewStatus = $review['review_status'] ?? ($campaign ? $campaign['status'] : 'pending');
$isApproved   = ($reviewStatus === 'approved');
$isRevision   = ($reviewStatus === 'revision_requested');
$videoUrl     = $job['video_url'] ?? null;
$campaignName = $campaign['campaign_name'] ?? 'Video Review';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageError) ? 'Review Not Found' : h($campaignName) . ' — Video Review' ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f0f4f8; min-height: 100vh; }
        .portal-header { background: linear-gradient(135deg, #0d6efd 0%, #6610f2 100%); }
        .video-frame { background: #000; border-radius: 12px; overflow: hidden; max-width: 400px; margin: 0 auto; }
        .action-card { border: 2px solid #dee2e6; border-radius: 12px; transition: border-color .2s; }
        .action-card:hover { border-color: #0d6efd; }
        .status-approved { background: #d1e7dd; border: 1px solid #a3cfbb; border-radius: 8px; }
        .status-revision  { background: #f8d7da; border: 1px solid #f1aeb5; border-radius: 8px; }
    </style>
</head>
<body>

<!-- Portal Header -->
<div class="portal-header text-white py-4 mb-4">
    <div class="container" style="max-width:680px;">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-white bg-opacity-25 rounded-circle p-2">
                <i class="bi bi-camera-video-fill fs-4"></i>
            </div>
            <div>
                <h1 class="h5 mb-0 fw-bold">Video Review Portal</h1>
                <div class="small opacity-75">Powered by MediaBuy AI Video Studio</div>
            </div>
        </div>
    </div>
</div>

<div class="container pb-5" style="max-width:680px;">

<?php if (isset($pageError)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-exclamation-circle-fill display-4 text-danger d-block mb-3"></i>
            <h2 class="h4">Not Found</h2>
            <p class="text-muted"><?= h($pageError) ?></p>
        </div>
    </div>

<?php else: ?>

    <!-- Campaign Header Card -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h2 class="h4 fw-bold mb-1"><?= h($campaignName) ?></h2>
                    <?php if ($client): ?>
                    <div class="text-muted small mb-2"><i class="bi bi-building me-1"></i><?= h($client) ?></div>
                    <?php endif; ?>
                    <?php if ($campaign['platform']): ?>
                    <div class="text-muted small">
                        <i class="bi bi-display me-1"></i><?= h($campaign['platform']) ?>
                        <?php if ($campaign['video_length']): ?>
                            &middot; <?= h($campaign['video_length']) ?>s
                        <?php endif; ?>
                        <?php if ($campaign['aspect_ratio']): ?>
                            &middot; <?= h($campaign['aspect_ratio']) ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if ($isApproved): ?>
                <span class="badge bg-success fs-6"><i class="bi bi-check-circle me-1"></i>Approved</span>
                <?php elseif ($isRevision): ?>
                <span class="badge bg-warning text-dark fs-6"><i class="bi bi-arrow-counterclockwise me-1"></i>Revision Requested</span>
                <?php else: ?>
                <span class="badge bg-primary fs-6"><i class="bi bi-eye me-1"></i>Awaiting Review</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($isApproved): ?>
    <div class="status-approved p-3 mb-3 d-flex align-items-center gap-3">
        <i class="bi bi-check-circle-fill text-success fs-3"></i>
        <div>
            <div class="fw-semibold text-success">Video Approved</div>
            <?php if ($review['approved_at']): ?>
            <div class="small text-muted">Approved on <?= h(date('F j, Y', strtotime($review['approved_at']))) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php elseif ($isRevision): ?>
    <div class="status-revision p-3 mb-3">
        <div class="fw-semibold text-danger"><i class="bi bi-arrow-counterclockwise me-1"></i>Revisions Requested</div>
        <?php if ($review['revision_notes']): ?>
        <div class="small mt-1"><?= h($review['revision_notes']) ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Video Player -->
    <?php if ($videoUrl): ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-play-circle-fill me-2 text-primary"></i>Your Video
        </div>
        <div class="card-body p-3 text-center">
            <div class="video-frame mx-auto mb-3">
                <video controls class="w-100 d-block" style="max-height:500px;"
                       poster="<?= $job['thumbnail_url'] ? h($job['thumbnail_url']) : '' ?>">
                    <source src="<?= h($videoUrl) ?>" type="video/mp4">
                    Your browser does not support video playback.
                    <a href="<?= h($videoUrl) ?>" target="_blank">Download video</a>
                </video>
            </div>
            <a href="<?= h($videoUrl) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-download me-1"></i>Download Video
            </a>
        </div>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-hourglass-split display-4 d-block mb-3"></i>
            <div>Video is being generated. Please check back soon.</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Script Summary (hook + CTA only — no internal details) -->
    <?php
    $dsn2 = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    $pdo2 = new PDO($dsn2, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $scriptRow = $pdo2->prepare('SELECT hook, cta_text FROM ai_video_scripts WHERE campaign_id = :cid ORDER BY version_number DESC LIMIT 1');
    $scriptRow->execute([':cid' => $campaign['id']]);
    $scriptRow = $scriptRow->fetch();
    ?>
    <?php if ($scriptRow && ($scriptRow['hook'] || $scriptRow['cta_text'])): ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <?php if ($scriptRow['hook']): ?>
            <div class="mb-2">
                <div class="small text-muted fw-semibold text-uppercase mb-1"><i class="bi bi-lightning me-1"></i>Opening Hook</div>
                <div class="fs-6 fst-italic text-dark">&ldquo;<?= h($scriptRow['hook']) ?>&rdquo;</div>
            </div>
            <?php endif; ?>
            <?php if ($scriptRow['cta_text']): ?>
            <div>
                <div class="small text-muted fw-semibold text-uppercase mb-1"><i class="bi bi-megaphone me-1"></i>Call to Action</div>
                <div class="fw-semibold text-primary"><?= h($scriptRow['cta_text']) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Review Actions -->
    <?php if (!$isApproved && $videoUrl): ?>
    <div id="review-action-area">
        <div class="row g-3 mb-3">
            <!-- Approve -->
            <div class="col-md-6">
                <div class="action-card p-3 text-center h-100">
                    <i class="bi bi-check-circle-fill text-success display-6 d-block mb-2"></i>
                    <h5 class="fw-bold">Approve Video</h5>
                    <p class="small text-muted">This video looks great! I'm happy to approve it.</p>
                    <button class="btn btn-success w-100"
                            onclick="approveVideo('<?= h($token) ?>')">
                        <i class="bi bi-check-circle me-1"></i>Approve Video
                    </button>
                </div>
            </div>
            <!-- Request Revisions -->
            <div class="col-md-6">
                <div class="action-card p-3 text-center h-100">
                    <i class="bi bi-pencil-square text-warning display-6 d-block mb-2"></i>
                    <h5 class="fw-bold">Request Changes</h5>
                    <p class="small text-muted">I have feedback or would like changes made.</p>
                    <button class="btn btn-warning text-dark w-100"
                            onclick="document.getElementById('revision-form').classList.toggle('d-none')">
                        <i class="bi bi-chat-square-text me-1"></i>Request Changes
                    </button>
                </div>
            </div>
        </div>

        <!-- Revision Form -->
        <div id="revision-form" class="d-none card border-warning border mb-3">
            <div class="card-header bg-warning bg-opacity-10 fw-semibold">
                <i class="bi bi-chat-square-text me-2"></i>Your Revision Feedback
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Please describe what changes you'd like:</label>
                    <textarea id="revision-notes" class="form-control" rows="4"
                              placeholder="Please describe what changes you'd like to see..."></textarea>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-warning" onclick="submitRevision('<?= h($token) ?>')">
                        <i class="bi bi-send me-1"></i>Submit Feedback
                    </button>
                    <button class="btn btn-outline-secondary"
                            onclick="document.getElementById('revision-form').classList.add('d-none')">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="review-result" class="mb-3"></div>

    <?php elseif (!$videoUrl): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>Review actions will be available once the video is ready.
    </div>
    <?php endif; ?>

<?php endif; ?>

    <div class="text-center mt-4 small text-muted">
        <i class="bi bi-shield-lock me-1"></i>This is a secure, private review link.
        &copy; <?= date('Y') ?> MediaBuy Platform
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function approveVideo(token) {
    if (!confirm('Are you sure you want to approve this video? This will confirm the final version.')) return;

    fetch('/portal/actions/approve.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'token=' + encodeURIComponent(token)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('review-action-area').innerHTML =
                '<div class="alert alert-success text-center py-4">' +
                '<i class="bi bi-check-circle-fill display-5 d-block mb-2 text-success"></i>' +
                '<h4>Video Approved!</h4>' +
                '<p class="mb-0">Thank you! Your approval has been recorded. The team will be notified.</p>' +
                '</div>';
        } else {
            document.getElementById('review-result').innerHTML =
                '<div class="alert alert-danger">' + (data.error || 'Something went wrong.') + '</div>';
        }
    })
    .catch(() => {
        document.getElementById('review-result').innerHTML =
            '<div class="alert alert-danger">Network error. Please try again.</div>';
    });
}

function submitRevision(token) {
    var notes = document.getElementById('revision-notes').value.trim();
    if (!notes) {
        alert('Please describe the changes you need before submitting.');
        return;
    }

    fetch('/portal/actions/request-revision.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'token=' + encodeURIComponent(token) + '&revision_notes=' + encodeURIComponent(notes)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('review-action-area').innerHTML =
                '<div class="alert alert-warning text-center py-4">' +
                '<i class="bi bi-arrow-counterclockwise display-5 d-block mb-2 text-warning"></i>' +
                '<h4>Feedback Received!</h4>' +
                '<p class="mb-0">Thank you for your feedback. The team will review your notes and get back to you shortly.</p>' +
                '</div>';
        } else {
            document.getElementById('review-result').innerHTML =
                '<div class="alert alert-danger">' + (data.error || 'Something went wrong.') + '</div>';
        }
    })
    .catch(() => {
        document.getElementById('review-result').innerHTML =
            '<div class="alert alert-danger">Network error. Please try again.</div>';
    });
}
</script>
</body>
</html>
