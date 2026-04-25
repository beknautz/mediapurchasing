<?php
/**
 * Ad Automation HTMX API endpoint.
 * All responses are HTML snippets (Bootstrap alerts) for hx-swap="innerHTML".
 */
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';
requireRole(['admin', 'buyer']);

header('Content-Type: text/html; charset=UTF-8');

$svc    = new MarketingAutomationService();
$action = $_POST['action'] ?? '';

function alertSuccess(string $msg): string {
    return '<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i>'
        . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')
        . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}
function alertError(string $msg): string {
    return '<div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle-fill me-2"></i>'
        . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')
        . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

try {
    switch ($action) {

        // ── Social Posts ──────────────────────────────────────────────────

        case 'save_post':
            if (empty(trim($_POST['title'] ?? ''))) {
                echo alertError('Title is required.');
                exit;
            }
            $id = $svc->saveSocialPost($_POST);
            echo alertSuccess($id ? 'Post saved.' : 'Post created.');
            break;

        case 'delete_post':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) { echo alertError('Invalid post.'); exit; }
            $svc->deleteSocialPost($id);
            echo alertSuccess('Post deleted.');
            break;

        case 'duplicate_post':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) { echo alertError('Invalid post.'); exit; }
            $svc->duplicateSocialPost($id);
            echo alertSuccess('Post duplicated as draft.');
            break;

        // ── Campaigns ─────────────────────────────────────────────────────

        case 'save_campaign':
            if (empty(trim($_POST['campaign_name'] ?? ''))) {
                echo alertError('Campaign name is required.');
                exit;
            }
            $svc->saveCampaign($_POST);
            echo alertSuccess('Campaign saved.');
            break;

        case 'delete_campaign':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) { echo alertError('Invalid campaign.'); exit; }
            $svc->deleteCampaign($id);
            echo alertSuccess('Campaign deleted.');
            break;

        // ── Ad Copy / Generator ───────────────────────────────────────────

        case 'generate_ad':
            $platform = $_POST['platform'] ?? 'meta';
            $generated = $svc->generateAdCopy($_POST);

            // Save to DB automatically as draft
            $copyId = $svc->saveAdCopy(array_merge($_POST, $generated, ['status' => 'draft', 'ai_generated' => 1]));

            ob_start();
            if ($platform === 'meta'): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                    <span><i class="bi bi-facebook me-2 text-primary"></i>Meta Ad Copy</span>
                    <span class="badge bg-secondary">Draft #<?= (int)$copyId ?></span>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="small fw-semibold text-muted mb-1">HEADLINE</div>
                        <div class="fs-5 fw-semibold"><?= htmlspecialchars($generated['headline'] ?? '') ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="small fw-semibold text-muted mb-1">PRIMARY TEXT</div>
                        <div><?= htmlspecialchars($generated['primary_text'] ?? '') ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="small fw-semibold text-muted mb-1">DESCRIPTION</div>
                        <div class="text-muted"><?= htmlspecialchars($generated['description'] ?? '') ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="small fw-semibold text-muted mb-1">CALL TO ACTION</div>
                        <span class="badge bg-primary"><?= htmlspecialchars($generated['call_to_action'] ?? '') ?></span>
                    </div>
                    <div>
                        <div class="small fw-semibold text-muted mb-1">SUGGESTED AUDIENCE</div>
                        <div class="text-muted small"><?= htmlspecialchars($generated['suggested_audience'] ?? '') ?></div>
                    </div>
                </div>
                <div class="card-footer bg-white d-flex gap-2">
                    <button class="btn btn-success btn-sm"
                            hx-post="/ad-automation/api.php"
                            hx-vals='{"action":"approve_ad_copy","id":"<?= (int)$copyId ?>","status":"approved"}'
                            hx-target="#htmx-alert" hx-swap="innerHTML"
                            hx-on::after-request="if(event.detail.successful) document.getElementById('adResult').innerHTML='<div class=\'alert alert-success\'>Approved and saved!</div>'">
                        <i class="bi bi-check-circle me-1"></i>Approve
                    </button>
                    <button class="btn btn-outline-danger btn-sm"
                            hx-post="/ad-automation/api.php"
                            hx-vals='{"action":"approve_ad_copy","id":"<?= (int)$copyId ?>","status":"rejected"}'
                            hx-target="#htmx-alert" hx-swap="innerHTML">
                        <i class="bi bi-x-circle me-1"></i>Reject
                    </button>
                    <a href="/ad-automation/ad-generator.php" class="btn btn-outline-secondary btn-sm ms-auto">
                        <i class="bi bi-arrow-repeat me-1"></i>Regenerate
                    </a>
                </div>
            </div>
            <?php else: // google ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                    <span><i class="bi bi-google me-2 text-warning"></i>Google Ad Copy</span>
                    <span class="badge bg-secondary">Draft #<?= (int)$copyId ?></span>
                </div>
                <div class="card-body">
                    <?php $headlines = $generated['google_headlines'] ?? []; ?>
                    <div class="mb-3">
                        <div class="small fw-semibold text-muted mb-1">HEADLINES</div>
                        <?php foreach ($headlines as $i => $hl): ?>
                        <div class="fw-semibold"><?= htmlspecialchars($hl) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php $descs = $generated['google_descriptions'] ?? []; ?>
                    <div class="mb-3">
                        <div class="small fw-semibold text-muted mb-1">DESCRIPTIONS</div>
                        <?php foreach ($descs as $d): ?>
                        <div class="text-muted mb-1"><?= htmlspecialchars($d) ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php $kws = $generated['suggested_keywords'] ?? []; ?>
                    <div class="mb-3">
                        <div class="small fw-semibold text-muted mb-1">SUGGESTED KEYWORDS</div>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($kws as $kw): ?>
                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($kw) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div>
                        <div class="small fw-semibold text-muted mb-1">SUGGESTED AUDIENCE</div>
                        <div class="text-muted small"><?= htmlspecialchars($generated['suggested_audience'] ?? '') ?></div>
                    </div>
                </div>
                <div class="card-footer bg-white d-flex gap-2">
                    <button class="btn btn-success btn-sm"
                            hx-post="/ad-automation/api.php"
                            hx-vals='{"action":"approve_ad_copy","id":"<?= (int)$copyId ?>","status":"approved"}'
                            hx-target="#htmx-alert" hx-swap="innerHTML"
                            hx-on::after-request="if(event.detail.successful) document.getElementById('adResult').innerHTML='<div class=\'alert alert-success\'>Approved and saved!</div>'">
                        <i class="bi bi-check-circle me-1"></i>Approve
                    </button>
                    <button class="btn btn-outline-danger btn-sm"
                            hx-post="/ad-automation/api.php"
                            hx-vals='{"action":"approve_ad_copy","id":"<?= (int)$copyId ?>","status":"rejected"}'
                            hx-target="#htmx-alert" hx-swap="innerHTML">
                        <i class="bi bi-x-circle me-1"></i>Reject
                    </button>
                    <a href="/ad-automation/ad-generator.php" class="btn btn-outline-secondary btn-sm ms-auto">
                        <i class="bi bi-arrow-repeat me-1"></i>Regenerate
                    </a>
                </div>
            </div>
            <?php endif;
            echo ob_get_clean();
            break;

        case 'save_ad_copy':
            $svc->saveAdCopy($_POST);
            echo alertSuccess('Ad copy saved.');
            break;

        case 'approve_ad_copy':
            $id     = (int)($_POST['id']     ?? 0);
            $status = $_POST['status'] ?? '';
            if (!$id || !$status) { echo alertError('Invalid request.'); exit; }
            $svc->approveAdCopy($id, $status);
            echo alertSuccess('Ad copy ' . $status . '.');
            break;

        // ── Schedules ─────────────────────────────────────────────────────

        case 'save_schedule':
            if (empty(trim($_POST['ad_name'] ?? ''))) {
                echo alertError('Ad name is required.');
                exit;
            }
            $svc->saveAdSchedule($_POST);
            echo alertSuccess('Schedule saved.');
            break;

        case 'delete_schedule':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) { echo alertError('Invalid schedule.'); exit; }
            $svc->deleteAdSchedule($id);
            echo alertSuccess('Schedule deleted.');
            break;

        case 'update_schedule_status':
            $id     = (int)($_POST['id']     ?? 0);
            $status = $_POST['status'] ?? '';
            if (!$id || !$status) { echo alertError('Invalid request.'); exit; }
            $svc->updateScheduleStatus($id, $status);
            echo alertSuccess('Status updated to ' . $status . '.');
            break;

        default:
            http_response_code(400);
            echo alertError('Unknown action.');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo alertError('Error: ' . $e->getMessage());
}
