<?php
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../config/ai_video.php';
requireRole(['admin', 'buyer']);

$campaignId = (int)($_GET['campaign_id'] ?? 0);
if (!$campaignId) redirect('/admin/ai-video/index.php');

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

$campaign = $pdo->prepare('SELECT * FROM ai_video_campaigns WHERE id = :id');
$campaign->execute([':id' => $campaignId]);
$campaign = $campaign->fetch();
if (!$campaign) redirect('/admin/ai-video/index.php');

$savedMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_prompt'])) {
    $promptId = (int)($_POST['prompt_id'] ?? 0);
    if ($promptId) {
        $upd = $pdo->prepare(
            'UPDATE ai_video_prompts
                SET veo_prompt = :veo, negative_prompt = :neg,
                    visual_style = :style, camera_direction = :cam,
                    lighting = :light, pacing = :pacing,
                    aspect_ratio = :ratio, duration_seconds = :dur
              WHERE id = :id AND campaign_id = :cid'
        );
        $upd->execute([
            ':veo'   => trim($_POST['veo_prompt'] ?? ''),
            ':neg'   => trim($_POST['negative_prompt'] ?? ''),
            ':style' => trim($_POST['visual_style'] ?? ''),
            ':cam'   => trim($_POST['camera_direction'] ?? ''),
            ':light' => trim($_POST['lighting'] ?? ''),
            ':pacing'=> trim($_POST['pacing'] ?? ''),
            ':ratio' => trim($_POST['aspect_ratio'] ?? ''),
            ':dur'   => (int)($_POST['duration_seconds'] ?? 0),
            ':id'    => $promptId,
            ':cid'   => $campaignId,
        ]);
        $savedMsg = 'Prompt saved.';
    }
}

$prompts = $pdo->prepare('SELECT * FROM ai_video_prompts WHERE campaign_id = :cid ORDER BY version_number DESC');
$prompts->execute([':cid' => $campaignId]);
$prompts = $prompts->fetchAll();

$latest = $prompts[0] ?? null;

$pageTitle = 'Prompt Editor — ' . h($campaign['campaign_name']);
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="mb-4">
    <h1 class="h3 fw-bold mb-0"><i class="bi bi-magic me-2 text-primary"></i>Veo Prompt Editor</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="/admin/ai-video/index.php">AI Video Studio</a></li>
            <li class="breadcrumb-item"><a href="/admin/ai-video/view-campaign.php?id=<?= (int)$campaignId ?>"><?= h($campaign['campaign_name']) ?></a></li>
            <li class="breadcrumb-item active">Prompt Editor</li>
        </ol>
    </nav>
</div>

<?php if ($savedMsg): ?>
<div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i><?= h($savedMsg) ?></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-pencil me-2 text-primary"></i>
                    Edit Prompt
                    <?php if ($latest): ?><span class="badge bg-primary ms-1">v<?= (int)$latest['version_number'] ?></span><?php endif; ?>
                </span>
                <button class="btn btn-sm btn-outline-primary"
                        hx-post="/admin/ai-video/actions/generate-veo-prompt.php"
                        hx-vals='{"campaign_id": "<?= (int)$campaignId ?>"}'
                        hx-target="#regen-result"
                        hx-swap="innerHTML"
                        hx-indicator="#regen-spinner">
                    <i class="bi bi-stars me-1"></i>Regenerate
                </button>
                <span id="regen-spinner" class="htmx-indicator ms-2">
                    <span class="spinner-border spinner-border-sm"></span>
                </span>
            </div>

            <?php if ($latest): ?>
            <form method="POST">
                <input type="hidden" name="save_prompt" value="1">
                <input type="hidden" name="prompt_id" value="<?= (int)$latest['id'] ?>">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Veo Prompt</label>
                        <textarea name="veo_prompt" class="form-control" rows="8"><?= h($latest['veo_prompt'] ?? '') ?></textarea>
                        <div class="form-text">This is the main prompt sent to the Veo video generation model. Be detailed and visual.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Negative Prompt</label>
                        <textarea name="negative_prompt" class="form-control" rows="3"><?= h($latest['negative_prompt'] ?? '') ?></textarea>
                        <div class="form-text">What to avoid generating.</div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Visual Style</label>
                            <input type="text" name="visual_style" class="form-control" value="<?= h($latest['visual_style'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Camera Direction</label>
                            <input type="text" name="camera_direction" class="form-control" value="<?= h($latest['camera_direction'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Lighting</label>
                            <input type="text" name="lighting" class="form-control" value="<?= h($latest['lighting'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Pacing</label>
                            <input type="text" name="pacing" class="form-control" value="<?= h($latest['pacing'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Aspect Ratio</label>
                            <select name="aspect_ratio" class="form-select">
                                <?php foreach (['9:16','1:1','16:9'] as $ar): ?>
                                <option value="<?= h($ar) ?>" <?= ($latest['aspect_ratio'] === $ar) ? 'selected' : '' ?>><?= h($ar) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Duration (s)</label>
                            <input type="number" name="duration_seconds" class="form-control" value="<?= (int)($latest['duration_seconds'] ?? 15) ?>" min="5" max="120">
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Changes</button>
                    <a href="/admin/ai-video/view-campaign.php?id=<?= (int)$campaignId ?>" class="btn btn-outline-secondary ms-2">
                        <i class="bi bi-arrow-left me-1"></i>Back
                    </a>
                </div>
            </form>
            <?php else: ?>
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-magic display-4 d-block mb-3"></i>
                No Veo prompt yet. Generate a script first, then generate the Veo prompt.
            </div>
            <?php endif; ?>
            <div id="regen-result" class="p-3"></div>
        </div>
    </div>

    <!-- Version History -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-clock-history me-2 text-secondary"></i>Version History
            </div>
            <div class="card-body p-0">
                <?php if (empty($prompts)): ?>
                <div class="text-muted text-center py-3 small">No versions yet.</div>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($prompts as $p): ?>
                    <li class="list-group-item small <?= ($latest && $p['id'] === $latest['id']) ? 'active' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong>v<?= (int)$p['version_number'] ?></strong>
                                <div class="text-<?= ($latest && $p['id'] === $latest['id']) ? 'light' : 'muted' ?>">
                                    <?= h(date('M j, Y g:ia', strtotime($p['created_at']))) ?>
                                </div>
                            </div>
                            <span class="badge bg-<?= ($latest && $p['id'] === $latest['id']) ? 'light text-dark' : 'secondary' ?>">
                                $<?= number_format((float)$p['claude_cost'], 4) ?>
                            </span>
                        </div>
                        <div class="mt-1 text-<?= ($latest && $p['id'] === $latest['id']) ? 'light' : 'muted' ?>" style="font-size:.75rem;">
                            <?= h($p['aspect_ratio'] ?? '') ?> &middot; <?= (int)$p['duration_seconds'] ?>s
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
