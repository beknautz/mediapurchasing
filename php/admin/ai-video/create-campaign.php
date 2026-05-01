<?php
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../config/ai_video.php';
requireRole(['admin', 'buyer']);

$errors = [];
$form   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'client_id'       => (int)($_POST['client_id'] ?? 0),
        'campaign_name'   => trim($_POST['campaign_name'] ?? ''),
        'objective'       => trim($_POST['objective'] ?? ''),
        'target_audience' => trim($_POST['target_audience'] ?? ''),
        'offer'           => trim($_POST['offer'] ?? ''),
        'brand_voice'     => trim($_POST['brand_voice'] ?? ''),
        'platform'        => trim($_POST['platform'] ?? ''),
        'video_length'    => (int)($_POST['video_length'] ?? 30),
        'aspect_ratio'    => trim($_POST['aspect_ratio'] ?? '9:16'),
        'call_to_action'  => trim($_POST['call_to_action'] ?? ''),
        'landing_page_url'=> trim($_POST['landing_page_url'] ?? ''),
        'notes'           => trim($_POST['notes'] ?? ''),
    ];

    if (empty($form['campaign_name'])) {
        $errors[] = 'Campaign name is required.';
    }
    if ($form['client_id'] <= 0) {
        $errors[] = 'Please select a client.';
    }

    if (empty($errors)) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $reviewToken = bin2hex(random_bytes(32));
        $createdBy   = (int)($_SESSION['user']['id'] ?? 0);

        $stmt = $pdo->prepare(
            'INSERT INTO ai_video_campaigns
                (client_id, campaign_name, objective, target_audience, offer,
                 brand_voice, platform, video_length, aspect_ratio,
                 call_to_action, landing_page_url, notes,
                 status, review_token, created_by, created_at, updated_at)
             VALUES
                (:client_id, :name, :objective, :audience, :offer,
                 :voice, :platform, :length, :ratio,
                 :cta, :url, :notes,
                 "draft", :token, :created_by, NOW(), NOW())'
        );
        $stmt->execute([
            ':client_id'  => $form['client_id'],
            ':name'       => $form['campaign_name'],
            ':objective'  => $form['objective'],
            ':audience'   => $form['target_audience'],
            ':offer'      => $form['offer'],
            ':voice'      => $form['brand_voice'],
            ':platform'   => $form['platform'],
            ':length'     => $form['video_length'],
            ':ratio'      => $form['aspect_ratio'],
            ':cta'        => $form['call_to_action'],
            ':url'        => $form['landing_page_url'],
            ':notes'      => $form['notes'],
            ':token'      => $reviewToken,
            ':created_by' => $createdBy,
        ]);
        $newId = (int)$pdo->lastInsertId();

        flash('success', 'Campaign created! Now generate a script.');
        redirect('/admin/ai-video/view-campaign.php?id=' . $newId);
    }
}

// Load clients for dropdown
$clients = [];
try {
    $dsn     = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    $pdo     = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $clients = $pdo->query("SELECT id, company_name FROM clients ORDER BY company_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // clients table may differ — try alternate column names
    try {
        $clients = $pdo->query("SELECT id, COALESCE(company_name, name, 'Client') AS company_name FROM clients ORDER BY company_name")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e2) {
        $clients = [];
    }
}

$pageTitle = 'New AI Video Campaign — MediaBuy';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="mb-4">
    <h1 class="h3 fw-bold mb-0"><i class="bi bi-camera-video-fill me-2 text-danger"></i>New AI Video Campaign</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="/admin/ai-video/index.php">AI Video Studio</a></li>
            <li class="breadcrumb-item active">New Campaign</li>
        </ol>
    </nav>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <strong>Please fix the following:</strong>
    <ul class="mb-0 mt-1">
        <?php foreach ($errors as $e): ?>
            <li><?= h($e) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="POST" action="" novalidate>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-clipboard-fill me-2 text-danger"></i>Campaign Brief
        </div>
        <div class="card-body">
            <div class="row g-3">
                <!-- Client -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Client <span class="text-danger">*</span></label>
                    <select name="client_id" class="form-select" required>
                        <option value="">— Select Client —</option>
                        <?php foreach ($clients as $cl): ?>
                            <option value="<?= (int)$cl['id'] ?>"
                                <?= ((int)($form['client_id'] ?? 0) === (int)$cl['id']) ? 'selected' : '' ?>>
                                <?= h($cl['company_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Campaign Name -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Campaign Name <span class="text-danger">*</span></label>
                    <input type="text" name="campaign_name" class="form-control"
                           value="<?= h($form['campaign_name'] ?? '') ?>" required placeholder="e.g. Summer Sale Launch">
                </div>

                <!-- Objective -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Campaign Objective</label>
                    <textarea name="objective" class="form-control" rows="3"
                              placeholder="What is the goal? (awareness, conversions, etc.)"><?= h($form['objective'] ?? '') ?></textarea>
                </div>

                <!-- Target Audience -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Target Audience</label>
                    <textarea name="target_audience" class="form-control" rows="3"
                              placeholder="Who should this video speak to? (age, interests, demographics)"><?= h($form['target_audience'] ?? '') ?></textarea>
                </div>

                <!-- Offer -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Offer / Product</label>
                    <input type="text" name="offer" class="form-control"
                           value="<?= h($form['offer'] ?? '') ?>" placeholder="What is being advertised or promoted?">
                </div>

                <!-- Brand Voice -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Brand Voice</label>
                    <select name="brand_voice" class="form-select">
                        <option value="">— Select Voice —</option>
                        <?php foreach (['Professional', 'Friendly', 'Energetic', 'Authoritative', 'Playful', 'Emotional'] as $v): ?>
                            <option value="<?= h($v) ?>" <?= (($form['brand_voice'] ?? '') === $v) ? 'selected' : '' ?>><?= h($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Platform -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Platform</label>
                    <select name="platform" class="form-select">
                        <option value="">— Select Platform —</option>
                        <?php foreach (['Facebook', 'Instagram', 'TikTok', 'YouTube Shorts', 'YouTube', 'Website', 'Google Ads'] as $p): ?>
                            <option value="<?= h($p) ?>" <?= (($form['platform'] ?? '') === $p) ? 'selected' : '' ?>><?= h($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Video Length -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Video Length</label>
                    <select name="video_length" class="form-select">
                        <?php foreach ([6, 15, 30, 60] as $len): ?>
                            <option value="<?= $len ?>" <?= ((int)($form['video_length'] ?? 30) === $len) ? 'selected' : '' ?>>
                                <?= $len ?> seconds
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Aspect Ratio -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Aspect Ratio</label>
                    <select name="aspect_ratio" class="form-select">
                        <?php foreach (['9:16', '1:1', '16:9'] as $ar): ?>
                            <option value="<?= h($ar) ?>" <?= (($form['aspect_ratio'] ?? '9:16') === $ar) ? 'selected' : '' ?>>
                                <?= h($ar) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Call to Action -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Call to Action</label>
                    <input type="text" name="call_to_action" class="form-control"
                           value="<?= h($form['call_to_action'] ?? '') ?>" placeholder="e.g. Shop Now, Learn More, Sign Up">
                </div>

                <!-- Landing Page URL -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Landing Page URL</label>
                    <input type="url" name="landing_page_url" class="form-control"
                           value="<?= h($form['landing_page_url'] ?? '') ?>" placeholder="https://...">
                </div>

                <!-- Notes -->
                <div class="col-12">
                    <label class="form-label fw-semibold">Additional Notes</label>
                    <textarea name="notes" class="form-control" rows="3"
                              placeholder="Any extra context, brand guidelines, specific requirements..."><?= h($form['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-between">
            <a href="/admin/ai-video/index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Cancel
            </a>
            <button type="submit" class="btn btn-danger">
                <i class="bi bi-plus-circle me-1"></i>Create Campaign
            </button>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
