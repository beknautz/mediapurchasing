<?php
require_once __DIR__ . '/../bootstrap.php';
requireRole('admin');

$crmService = new CRMService();
$errors     = [];

// Handle POST — save each setting value
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = $_POST['settings'] ?? [];

    if (is_array($posted)) {
        foreach ($posted as $settingKey => $settingValue) {
            $crmService->saveSetting($settingKey, $settingValue);
        }
    }

    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Workflow settings saved successfully.'];
    redirect('/admin/settings.php');
}

$settings = $crmService->getWorkflowSettings();

// Group settings by setting_group
$grouped = [];
foreach ($settings as $setting) {
    $group = $setting['setting_group'] ?? 'General';
    $grouped[$group][] = $setting;
}
ksort($grouped);

// Icon map for common group names
$groupIcons = [
    'General'      => 'bi-gear',
    'Approval'     => 'bi-check2-square',
    'Billing'      => 'bi-receipt',
    'Email'        => 'bi-envelope',
    'SMS'          => 'bi-phone',
    'Notifications'=> 'bi-bell',
    'Campaign'     => 'bi-collection-play',
    'Security'     => 'bi-shield-lock',
];

$pageTitle = 'Workflow Settings — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="bi bi-sliders me-2 text-primary"></i>Workflow Settings</h2>
        <p class="text-muted mb-0 small">Configure platform-wide workflow and system behaviour.</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Errors:</strong>
        <ul class="mb-0 mt-2">
            <?php foreach ($errors as $err): ?>
                <li><?= h($err) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (empty($settings)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-sliders fs-1 d-block mb-3"></i>
            <p class="mb-0">No workflow settings found in the database.</p>
        </div>
    </div>
<?php else: ?>

<form method="post" action="/admin/settings.php" novalidate>

    <?php foreach ($grouped as $groupName => $groupSettings): ?>
        <?php $icon = $groupIcons[$groupName] ?? 'bi-gear'; ?>
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white d-flex align-items-center gap-2 py-3">
                <i class="bi <?= h($icon) ?> text-primary fs-5"></i>
                <h5 class="mb-0 fw-semibold"><?= h($groupName) ?></h5>
                <span class="badge bg-secondary ms-1"><?= count($groupSettings) ?></span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($groupSettings as $setting):
                        $key     = $setting['setting_key']   ?? '';
                        $val     = $setting['setting_value'] ?? '';
                        $type    = $setting['setting_type']  ?? 'text';
                        $label   = $setting['label']         ?? ucwords(str_replace(['_', '-'], ' ', $key));
                        $helpText = $setting['description']  ?? '';
                        $fieldId = 'setting_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
                    ?>
                        <div class="col-md-6">
                            <label for="<?= h($fieldId) ?>" class="form-label fw-semibold">
                                <?= h($label) ?>
                            </label>

                            <?php if ($type === 'boolean' || $type === 'bool'): ?>
                                <div class="form-check form-switch fs-5 mt-1">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="<?= h($fieldId) ?>"
                                           name="settings[<?= h($key) ?>]"
                                           value="1"
                                           <?= ($val == '1' || strtolower((string)$val) === 'true') ? 'checked' : '' ?>>
                                    <label class="form-check-label fs-6 text-muted" for="<?= h($fieldId) ?>">
                                        <?= ($val == '1' || strtolower((string)$val) === 'true') ? 'Enabled' : 'Disabled' ?>
                                    </label>
                                </div>

                            <?php elseif ($type === 'textarea'): ?>
                                <textarea id="<?= h($fieldId) ?>"
                                          name="settings[<?= h($key) ?>]"
                                          class="form-control"
                                          rows="3"><?= h($val) ?></textarea>

                            <?php elseif ($type === 'select' && !empty($setting['options'])): ?>
                                <select id="<?= h($fieldId) ?>"
                                        name="settings[<?= h($key) ?>]"
                                        class="form-select">
                                    <?php
                                    $opts = is_array($setting['options'])
                                        ? $setting['options']
                                        : array_filter(array_map('trim', explode(',', $setting['options'])));
                                    foreach ($opts as $opt): ?>
                                        <option value="<?= h($opt) ?>" <?= $val === $opt ? 'selected' : '' ?>>
                                            <?= h(ucfirst($opt)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                            <?php elseif ($type === 'number' || $type === 'integer'): ?>
                                <input type="number" id="<?= h($fieldId) ?>"
                                       name="settings[<?= h($key) ?>]"
                                       class="form-control"
                                       value="<?= h($val) ?>"
                                       step="<?= $type === 'integer' ? '1' : 'any' ?>">

                            <?php elseif ($type === 'email'): ?>
                                <input type="email" id="<?= h($fieldId) ?>"
                                       name="settings[<?= h($key) ?>]"
                                       class="form-control"
                                       value="<?= h($val) ?>"
                                       placeholder="email@example.com">

                            <?php else: ?>
                                <input type="text" id="<?= h($fieldId) ?>"
                                       name="settings[<?= h($key) ?>]"
                                       class="form-control"
                                       value="<?= h($val) ?>">
                            <?php endif; ?>

                            <?php if ($helpText !== ''): ?>
                                <div class="form-text text-muted"><?= h($helpText) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="d-flex justify-content-end gap-2 mb-5">
        <a href="/dashboard.php" class="btn btn-outline-secondary">
            <i class="bi bi-x-circle me-1"></i>Cancel
        </a>
        <button type="submit" class="btn btn-primary btn-lg px-4">
            <i class="bi bi-save me-2"></i>Save All Settings
        </button>
    </div>

</form>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
