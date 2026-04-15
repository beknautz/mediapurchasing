<?php
require_once __DIR__ . '/../bootstrap.php';
requireRole('admin');

$crmService   = new CRMService();
$errors       = [];
$editTemplate = null;

// Handle POST — add or edit email template
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id        = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $name      = trim($_POST['name'] ?? '');
    $slug      = trim($_POST['slug'] ?? '');
    $category  = trim($_POST['category'] ?? '');
    $channel   = trim($_POST['channel'] ?? 'email');
    $subject   = trim($_POST['subject'] ?? '');
    $body_html = $_POST['body_html'] ?? '';
    $body_text = $_POST['body_text'] ?? '';
    $sms_body  = trim($_POST['sms_body'] ?? '');
    $variables = trim($_POST['variables'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') {
        $errors[] = 'Template name is required.';
    }
    if ($slug === '') {
        $errors[] = 'Slug is required.';
    } elseif (!preg_match('/^[a-z0-9_\-]+$/', $slug)) {
        $errors[] = 'Slug may only contain lowercase letters, numbers, hyphens, and underscores.';
    }
    if ($category === '') {
        $errors[] = 'Category is required.';
    }

    if (empty($errors)) {
        $data = [
            'name'      => $name,
            'slug'      => $slug,
            'category'  => $category,
            'channel'   => $channel,
            'subject'   => $subject,
            'body_html' => $body_html,
            'body_text' => $body_text,
            'sms_body'  => $sms_body,
            'variables' => $variables,
            'is_active' => $is_active,
        ];
        if ($id !== null) {
            $data['id'] = $id;
        }

        $crmService->saveTemplate($data);
        $_SESSION['flash'] = ['type' => 'success', 'message' => $id ? 'Template updated successfully.' : 'Template created successfully.'];
        redirect('/admin/templates.php');
    }

    $editTemplate = compact('id', 'name', 'slug', 'category', 'channel', 'subject', 'body_html', 'body_text', 'sms_body', 'variables', 'is_active');
}

$templates = $crmService->getTemplates();

// Group templates by category
$grouped = [];
foreach ($templates as $tpl) {
    $grouped[$tpl['category'] ?? 'Uncategorized'][] = $tpl;
}
ksort($grouped);

$pageTitle = 'Email Templates — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="bi bi-envelope-paper me-2 text-primary"></i>Email Templates</h2>
        <p class="text-muted mb-0 small">Manage notification and communication templates for all channels.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#templateModal"
            onclick="resetTemplateForm()">
        <i class="bi bi-plus-circle-fill me-1"></i>Add Template
    </button>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Please fix the following errors:</strong>
        <ul class="mb-0 mt-2">
            <?php foreach ($errors as $err): ?>
                <li><?= h($err) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (empty($templates)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-envelope-paper fs-1 d-block mb-3"></i>
            <p class="mb-0">No templates found. Create your first template to get started.</p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($grouped as $category => $categoryTemplates): ?>
        <div class="mb-4">
            <h5 class="text-secondary border-bottom pb-2 mb-3">
                <i class="bi bi-folder2 me-2"></i><?= h($category) ?>
                <span class="badge bg-secondary ms-2"><?= count($categoryTemplates) ?></span>
            </h5>
            <div class="card shadow-sm border-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Slug</th>
                                <th scope="col">Channel</th>
                                <th scope="col">Subject</th>
                                <th scope="col">Variables</th>
                                <th scope="col" class="text-center">Status</th>
                                <th scope="col" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categoryTemplates as $tpl): ?>
                                <tr>
                                    <td><strong><?= h($tpl['name']) ?></strong></td>
                                    <td><code class="small"><?= h($tpl['slug']) ?></code></td>
                                    <td>
                                        <?php
                                        $channelIcons = [
                                            'email' => 'bi-envelope',
                                            'sms'   => 'bi-phone',
                                            'both'  => 'bi-layers',
                                        ];
                                        $channelIcon = $channelIcons[$tpl['channel'] ?? 'email'] ?? 'bi-envelope';
                                        ?>
                                        <span class="badge bg-light text-dark border">
                                            <i class="bi <?= h($channelIcon) ?> me-1"></i><?= h(ucfirst($tpl['channel'] ?? 'email')) ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted"><?= $tpl['subject'] ? h(mb_strimwidth($tpl['subject'], 0, 50, '…')) : '—' ?></td>
                                    <td>
                                        <?php
                                        $vars = array_filter(array_map('trim', explode(',', $tpl['variables'] ?? '')));
                                        foreach ($vars as $var): ?>
                                            <span class="badge bg-warning text-dark me-1 small"><?= h($var) ?></span>
                                        <?php endforeach;
                                        if (empty($vars)) echo '<span class="text-muted">—</span>';
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if (!empty($tpl['is_active'])): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><i class="bi bi-x-circle me-1"></i>Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="modal" data-bs-target="#templateModal"
                                                onclick="editTemplate(<?= (int)$tpl['id'] ?>, <?= htmlspecialchars(json_encode($tpl), ENT_QUOTES, 'UTF-8') ?>)">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Add / Edit Template Modal -->
<div class="modal fade" id="templateModal" tabindex="-1" aria-labelledby="templateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content shadow">
            <form method="post" action="/admin/templates.php" novalidate id="templateForm">
                <input type="hidden" name="id" id="templateId">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="templateModalLabel">
                        <i class="bi bi-envelope-paper me-2"></i><span id="templateModalTitleText">Add New Template</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-4">
                            <label for="tplName" class="form-label fw-semibold">Template Name <span class="text-danger">*</span></label>
                            <input type="text" id="tplName" name="name" class="form-control"
                                   placeholder="Welcome Email" required
                                   value="<?= h($editTemplate['name'] ?? '') ?>"
                                   oninput="autoSlug(this.value)">
                        </div>

                        <div class="col-md-4">
                            <label for="tplSlug" class="form-label fw-semibold">Slug <span class="text-danger">*</span></label>
                            <input type="text" id="tplSlug" name="slug" class="form-control"
                                   placeholder="welcome_email" required
                                   value="<?= h($editTemplate['slug'] ?? '') ?>"
                                   pattern="[a-z0-9_\-]+">
                            <div class="form-text">Lowercase letters, numbers, hyphens, underscores only.</div>
                        </div>

                        <div class="col-md-4">
                            <label for="tplChannel" class="form-label fw-semibold">Channel</label>
                            <select id="tplChannel" name="channel" class="form-select" onchange="toggleSmsField(this.value)">
                                <option value="email" <?= ($editTemplate['channel'] ?? 'email') === 'email' ? 'selected' : '' ?>>Email Only</option>
                                <option value="sms"   <?= ($editTemplate['channel'] ?? '') === 'sms'   ? 'selected' : '' ?>>SMS Only</option>
                                <option value="both"  <?= ($editTemplate['channel'] ?? '') === 'both'  ? 'selected' : '' ?>>Email &amp; SMS</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="tplCategory" class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                            <input type="text" id="tplCategory" name="category" class="form-control"
                                   placeholder="Onboarding, Billing, Approval…" required
                                   list="categoryOptions"
                                   value="<?= h($editTemplate['category'] ?? '') ?>">
                            <datalist id="categoryOptions">
                                <?php foreach (array_keys($grouped) as $cat): ?>
                                    <option value="<?= h($cat) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>

                        <div class="col-md-6">
                            <label for="tplVariables" class="form-label fw-semibold">Variables</label>
                            <input type="text" id="tplVariables" name="variables" class="form-control"
                                   placeholder="client_name, campaign_id, amount"
                                   value="<?= h($editTemplate['variables'] ?? '') ?>">
                            <div class="form-text">Comma-separated list of merge variable names.</div>
                        </div>

                        <div class="col-12" id="subjectField">
                            <label for="tplSubject" class="form-label fw-semibold">Email Subject</label>
                            <input type="text" id="tplSubject" name="subject" class="form-control"
                                   placeholder="Welcome to MediaBuy, {{client_name}}!"
                                   value="<?= h($editTemplate['subject'] ?? '') ?>">
                        </div>

                        <div class="col-12" id="bodyHtmlField">
                            <label for="tplBodyHtml" class="form-label fw-semibold">HTML Body</label>
                            <textarea id="tplBodyHtml" name="body_html" class="form-control font-monospace"
                                      rows="8" placeholder="<p>Hello {{client_name}},</p>
<p>Your campaign has been approved...</p>"><?= h($editTemplate['body_html'] ?? '') ?></textarea>
                            <div class="form-text">Supports HTML. Use <code>&#123;&#123;variable_name&#125;&#125;</code> for merge fields.</div>
                        </div>

                        <div class="col-12" id="bodyTextField">
                            <label for="tplBodyText" class="form-label fw-semibold">Plain Text Body</label>
                            <textarea id="tplBodyText" name="body_text" class="form-control font-monospace"
                                      rows="5" placeholder="Hello {{client_name}},&#10;&#10;Your campaign has been approved..."><?= h($editTemplate['body_text'] ?? '') ?></textarea>
                            <div class="form-text">Plain text fallback for email clients that don't render HTML.</div>
                        </div>

                        <div class="col-12" id="smsBdyField" style="display:none;">
                            <label for="tplSmsBody" class="form-label fw-semibold">SMS Body</label>
                            <textarea id="tplSmsBody" name="sms_body" class="form-control" rows="3"
                                      placeholder="Hi {{client_name}}, your campaign is approved. Reply STOP to opt out."><?= h($editTemplate['sms_body'] ?? '') ?></textarea>
                            <div class="form-text">Keep under 160 characters for a single SMS segment.</div>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch fs-5">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       id="tplActive" name="is_active" value="1"
                                       <?= !empty($editTemplate['is_active']) || $editTemplate === null ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold fs-6" for="tplActive">Active Template</label>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Template
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetTemplateForm() {
    document.getElementById('templateId').value = '';
    document.getElementById('templateForm').reset();
    document.getElementById('templateModalTitleText').textContent = 'Add New Template';
    document.getElementById('tplActive').checked = true;
    toggleSmsField('email');
}

function editTemplate(id, data) {
    document.getElementById('templateId').value   = id;
    document.getElementById('tplName').value      = data.name      || '';
    document.getElementById('tplSlug').value      = data.slug      || '';
    document.getElementById('tplCategory').value  = data.category  || '';
    document.getElementById('tplChannel').value   = data.channel   || 'email';
    document.getElementById('tplSubject').value   = data.subject   || '';
    document.getElementById('tplBodyHtml').value  = data.body_html || '';
    document.getElementById('tplBodyText').value  = data.body_text || '';
    document.getElementById('tplSmsBody').value   = data.sms_body  || '';
    document.getElementById('tplVariables').value = data.variables || '';
    document.getElementById('tplActive').checked  = data.is_active == 1;
    document.getElementById('templateModalTitleText').textContent = 'Edit Template: ' + (data.name || '');
    toggleSmsField(data.channel || 'email');
}

function toggleSmsField(channel) {
    var showEmail = (channel === 'email' || channel === 'both');
    var showSms   = (channel === 'sms'   || channel === 'both');
    document.getElementById('subjectField').style.display  = showEmail ? '' : 'none';
    document.getElementById('bodyHtmlField').style.display = showEmail ? '' : 'none';
    document.getElementById('bodyTextField').style.display = showEmail ? '' : 'none';
    document.getElementById('smsBdyField').style.display   = showSms   ? '' : 'none';
}

var _isEditMode = false;
function autoSlug(name) {
    if (_isEditMode) return; // don't override on edit
    var slug = name.toLowerCase()
        .replace(/[^a-z0-9\s_-]/g, '')
        .trim()
        .replace(/[\s]+/g, '_');
    document.getElementById('tplSlug').value = slug;
}

document.getElementById('tplSlug').addEventListener('focus', function () {
    _isEditMode = this.value !== '';
});

// Initialise channel visibility on page load
toggleSmsField(document.getElementById('tplChannel').value);

<?php if (!empty($errors) && $editTemplate !== null): ?>
document.addEventListener('DOMContentLoaded', function () {
    var modal = new bootstrap.Modal(document.getElementById('templateModal'));
    _isEditMode = true;
    <?php if (!empty($editTemplate['id'])): ?>
    editTemplate(<?= (int)$editTemplate['id'] ?>, <?= json_encode($editTemplate) ?>);
    <?php else: ?>
    // Restore posted values
    document.getElementById('tplName').value      = <?= json_encode($editTemplate['name']) ?>;
    document.getElementById('tplSlug').value      = <?= json_encode($editTemplate['slug']) ?>;
    document.getElementById('tplCategory').value  = <?= json_encode($editTemplate['category']) ?>;
    document.getElementById('tplChannel').value   = <?= json_encode($editTemplate['channel']) ?>;
    document.getElementById('tplSubject').value   = <?= json_encode($editTemplate['subject']) ?>;
    document.getElementById('tplBodyHtml').value  = <?= json_encode($editTemplate['body_html']) ?>;
    document.getElementById('tplBodyText').value  = <?= json_encode($editTemplate['body_text']) ?>;
    document.getElementById('tplSmsBody').value   = <?= json_encode($editTemplate['sms_body']) ?>;
    document.getElementById('tplVariables').value = <?= json_encode($editTemplate['variables']) ?>;
    document.getElementById('tplActive').checked  = <?= json_encode((bool)$editTemplate['is_active']) ?>;
    toggleSmsField(<?= json_encode($editTemplate['channel']) ?>);
    <?php endif; ?>
    modal.show();
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
