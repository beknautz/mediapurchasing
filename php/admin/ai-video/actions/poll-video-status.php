<?php
require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../../../config/ai_video.php';
requireRole(['admin', 'buyer']);

$jobId = (int)($_GET['job_id'] ?? $_POST['job_id'] ?? 0);
if (!$jobId) {
    echo '<div class="alert alert-danger">Job ID required.</div>';
    exit;
}

try {
    $svc = new VeoVideoService();
    $job = $svc->getJobStatus($jobId);

    $statusMap = ['queued'=>'warning','processing'=>'info','completed'=>'success','failed'=>'danger'];
    $sc        = $statusMap[$job['job_status']] ?? 'secondary';
    $label     = ucfirst($job['job_status']);

    echo '<div class="card border-' . $sc . '">';
    echo '<div class="card-body py-2 px-3">';
    echo '<div class="d-flex align-items-center gap-2 mb-1">';
    echo '<span class="badge bg-' . $sc . '">' . h($label) . '</span>';
    echo '<strong class="small">Job #' . (int)$job['id'] . '</strong>';
    echo '</div>';

    if ($job['progress_percent'] > 0) {
        echo '<div class="progress mb-2" style="height:6px;">';
        echo '<div class="progress-bar bg-' . $sc . '" style="width:' . (int)$job['progress_percent'] . '%"></div>';
        echo '</div>';
    }

    if ($job['job_status'] === 'completed') {
        echo '<div class="alert alert-success py-2 mb-2">';
        echo '<i class="bi bi-check-circle-fill me-2"></i><strong>Video Ready!</strong>';
        if ($job['video_url']) {
            echo ' <a href="' . h($job['video_url']) . '" target="_blank" class="btn btn-sm btn-success ms-2"><i class="bi bi-play-fill me-1"></i>Watch Video</a>';
        }
        echo '</div>';
        echo '<div class="small text-muted">Completed: ' . h(date('M j, Y g:ia', strtotime($job['completed_at'] ?? 'now'))) . '</div>';
        echo '<div class="small text-muted">Actual Cost: $' . number_format((float)$job['actual_provider_cost'], 2) . '</div>';
        echo '<div class="mt-2">';
        echo '<a href="/admin/ai-video/view-campaign.php?id=' . (int)$job['campaign_id'] . '" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-left me-1"></i>Back to Campaign</a>';
        echo '</div>';

    } elseif ($job['job_status'] === 'failed') {
        echo '<div class="alert alert-danger py-2 mb-0">';
        echo '<i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Job Failed</strong>';
        if ($job['error_message']) {
            echo ': ' . h($job['error_message']);
        }
        echo '</div>';

    } else {
        echo '<div class="small text-muted mb-2">Still ' . h($job['job_status']) . '... Click again to refresh.</div>';
        echo '<button class="btn btn-sm btn-outline-warning" hx-get="/admin/ai-video/actions/poll-video-status.php?job_id=' . (int)$jobId . '" hx-target="closest .card" hx-swap="outerHTML"><i class="bi bi-arrow-repeat me-1"></i>Refresh Status</button>';
    }

    echo '</div></div>';

} catch (Throwable $e) {
    error_log('[AI Video] poll-video-status error: ' . $e->getMessage());
    echo '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>' . h($e->getMessage()) . '</div>';
}
