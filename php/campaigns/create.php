<?php
require_once __DIR__ . '/../bootstrap.php';
requireRole(['admin', 'buyer']);

$campaignService = new CampaignService();
$crmService      = new CRMService();
$clients         = $crmService->getClients();

$errors   = [];
$formData = [
    'title'        => '',
    'client_id'    => '',
    'language'     => 'both',
    'total_budget' => '',
    'flight_start' => '',
    'flight_end'   => '',
    'market'       => '',
    'notes'        => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['title']        = trim($_POST['title']        ?? '');
    $formData['client_id']    = (int) ($_POST['client_id']    ?? 0);
    $formData['language']     = trim($_POST['language']     ?? 'both');
    $formData['total_budget'] = trim($_POST['total_budget'] ?? '0');
    $formData['flight_start'] = trim($_POST['flight_start'] ?? '');
    $formData['flight_end']   = trim($_POST['flight_end']   ?? '');
    $formData['market']       = trim($_POST['market']       ?? '');
    $formData['notes']        = trim($_POST['notes']        ?? '');

    if ($formData['title'] === '') {
        $errors[] = 'Campaign title is required.';
    }
    if (!$formData['client_id']) {
        $errors[] = 'Please select a client.';
    }

    if (empty($errors)) {
        $result = $campaignService->saveCampaign([
            'id'           => 0,
            'title'        => $formData['title'],
            'client_id'    => $formData['client_id'],
            'language'     => $formData['language'],
            'total_budget' => (float) str_replace(',', '', $formData['total_budget']),
            'flight_start' => $formData['flight_start'] ?: null,
            'flight_end'   => $formData['flight_end']   ?: null,
            'market'       => $formData['market'],
            'notes'        => $formData['notes'],
        ]);

        if ($result['success']) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Campaign created. Now add media channels.'];
            redirect('/campaigns/view.php?id=' . $result['id']);
        } else {
            $errors[] = $result['message'];
        }
    }
}

$pageTitle = 'New Campaign — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">
            <i class="bi bi-plus-circle me-2 text-primary"></i>New Campaign
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="/campaigns/index.php">Campaigns</a></li>
                <li class="breadcrumb-item active">Create</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <strong>Please fix the following:</strong>
    <ul class="mb-0 mt-1"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="POST" action="">
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-info-circle me-2 text-primary"></i>Campaign Details</h5>
            </div>
            <div class="card-body">

                <div class="mb-3">
                    <label for="title" class="form-label fw-semibold">Campaign Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" name="title"
                           value="<?= h($formData['title']) ?>" required autofocus
                           placeholder="e.g. FIFA World Cup 2026 — Summer Campaign">
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="client_id" class="form-label fw-semibold">Client <span class="text-danger">*</span></label>
                        <select class="form-select" id="client_id" name="client_id" required>
                            <option value="">— Select Client —</option>
                            <?php foreach ($clients as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= (int)$formData['client_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= h($c['company_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="language" class="form-label fw-semibold">Language</label>
                        <select class="form-select" id="language" name="language">
                            <option value="both"    <?= $formData['language'] === 'both'    ? 'selected' : '' ?>>English &amp; Spanish</option>
                            <option value="english" <?= $formData['language'] === 'english' ? 'selected' : '' ?>>English Only</option>
                            <option value="spanish" <?= $formData['language'] === 'spanish' ? 'selected' : '' ?>>Spanish Only</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-4">
                        <label for="total_budget" class="form-label fw-semibold">Total Budget</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="total_budget" name="total_budget"
                                   value="<?= h($formData['total_budget']) ?>" min="0" step="0.01"
                                   placeholder="0.00">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="flight_start" class="form-label fw-semibold">Flight Start</label>
                        <input type="date" class="form-control" id="flight_start" name="flight_start"
                               value="<?= h($formData['flight_start']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="flight_end" class="form-label fw-semibold">Flight End</label>
                        <input type="date" class="form-control" id="flight_end" name="flight_end"
                               value="<?= h($formData['flight_end']) ?>">
                    </div>
                </div>

                <div class="mt-3">
                    <label for="market" class="form-label fw-semibold">Market / DMA</label>
                    <input type="text" class="form-control" id="market" name="market"
                           value="<?= h($formData['market']) ?>" placeholder="e.g. Miami, FL">
                </div>

                <div class="mt-3">
                    <label for="notes" class="form-label fw-semibold">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"
                              placeholder="Campaign overview, goals, special instructions…"><?= h($formData['notes']) ?></textarea>
                </div>

            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm sticky-top" style="top:1rem;">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-floppy me-2 text-primary"></i>Save</h5>
            </div>
            <div class="card-body d-grid">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-arrow-right-circle me-2"></i>Create &amp; Add Channels
                </button>
                <p class="small text-muted text-center mt-2 mb-0">
                    You'll add media channels on the next screen.
                </p>
            </div>
            <div class="card-footer bg-white text-center">
                <a href="/campaigns/index.php" class="text-muted small">
                    <i class="bi bi-x-circle me-1"></i>Cancel
                </a>
            </div>
        </div>
    </div>
</div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
