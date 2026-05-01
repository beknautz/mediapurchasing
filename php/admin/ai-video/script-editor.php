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

// Handle save
$savedMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_script'])) {
    $scriptId  = (int)($_POST['script_id'] ?? 0);
    $scriptTxt = trim($_POST['script_text'] ?? '');
    $hook      = trim($_POST['hook'] ?? '');
    $cta       = trim($_POST['cta_text'] ?? '');

    if ($scriptId) {
        $upd = $pdo->prepare(
            'UPDATE ai_video_scripts
                SET script_text = :text, hook = :hook, cta_text = :cta
              WHERE id = :id AND campaign_id = :cid'
        );
        $upd->execute([':text'=>$scriptTxt,':hook'=>$hook,':cta'=>$cta,':id'=>$scriptId,':cid'=>$campaignId]);
        $savedMsg = 'Script saved.';
    }
}

$scripts = $pdo->prepare('SELECT * FROM ai_video_scripts WHERE campaign_id = :cid ORDER BY version_number DESC');
$scripts->execute([':cid' => $campaignId]);
$scripts = $scripts->fetchAll();

$latestScript = $scripts[0] ?? null;

$pageTitle = 'Script Editor — ' . h($campaign['campaign_name']);
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="mb-4">
    <h1 class="h3 fw-bold mb-0"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Script Editor</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="/admin/ai-video/index.php">AI Video Studio</a></li>
            <li class="breadcrumb-item"><a href="/admin/ai-video/view-campaign.php?id=<?= (int)$campaignId ?>"><?= h($campaign['campaign_name']) ?></a></li>
            <li class="breadcrumb-item active">Script Editor</li>
        </ol>
    </nav>
</div>

<?php if ($savedMsg): ?>
<div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i><?= h($savedMsg) ?></div>
<?php endif; ?>

<div class="row g-3">
    <!-- Edit Latest Script -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-pencil me-2 text-primary"></i>
                    Edit Script
                    <?php if ($latestScript): ?>
                        <span class="badge bg-info ms-1">v<?= (int)$latestScript['version_number'] ?></span>
                    <?php endif; ?>
                </span>
                <button class="btn btn-sm btn-outline-primary"
                        hx-post="/admin/ai-video/actions/generate-script.php"
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
            <?php if ($latestScript): ?>
            <form method="POST">
                <input type="hidden" name="save_script" value="1">
                <input type="hidden" name="script_id" value="<?= (int)$latestScript['id'] ?>">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Hook / Opening Line</label>
                        <input type="text" name="hook" class="form-control" value="<?= h($latestScript['hook'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Full Script</label>
                        <textarea name="script_text" class="form-control font-monospace" rows="15"><?= h($latestScript['script_text'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">CTA Text</label>
                        <input type="text" name="cta_text" class="form-control" value="<?= h($latestScript['cta_text'] ?? '') ?>">
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Changes
                    </button>
                    <a href="/admin/ai-video/view-campaign.php?id=<?= (int)$campaignId ?>" class="btn btn-outline-secondary ms-2">
                        <i class="bi bi-arrow-left me-1"></i>Back to Campaign
                    </a>
                </div>
            </form>
            <?php else: ?>
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-file-earmark-text display-4 d-block mb-3"></i>
                No script yet. Generate one first.
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
                <?php if (empty($scripts)): ?>
                <div class="text-muted text-center py-3 small">No versions yet.</div>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($scripts as $s): ?>
                    <li class="list-group-item small <?= ($latestScript && $s['id'] === $latestScript['id']) ? 'active' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong>v<?= (int)$s['version_number'] ?></strong>
                                <div class="text-<?= ($latestScript && $s['id'] === $latestScript['id']) ? 'light' : 'muted' ?>">
                                    <?= h(date('M j, Y g:ia', strtotime($s['created_at']))) ?>
                                </div>
                            </div>
                            <div class="text-<?= ($latestScript && $s['id'] === $latestScript['id']) ? 'light' : 'muted' ?>">
                                $<?= number_format((float)$s['claude_cost'], 4) ?>
                            </div>
                        </div>
                        <div class="mt-1">
                            <span class="badge bg-<?= ($latestScript && $s['id'] === $latestScript['id']) ? 'light text-dark' : 'secondary' ?>">
                                <?= number_format((int)$s['claude_input_tokens']) ?> in / <?= number_format((int)$s['claude_output_tokens']) ?> out
                            </span>
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
