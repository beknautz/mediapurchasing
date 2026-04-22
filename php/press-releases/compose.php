<?php
require_once __DIR__ . '/../bootstrap.php';
requireRole(['admin', 'buyer']);

$prService    = new PressReleaseService();
$emailService = new EmailService();

$errors       = [];
$vendorGroups = $prService->getVendorsByCategory();
$templates    = $prService->getTemplates();
$UPLOAD_DIR   = __DIR__ . '/../uploads/press-releases/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject   = trim($_POST['subject']   ?? '');
    $bodyHtml  = trim($_POST['body_html'] ?? '');   // Summernote posts HTML
    $vendorIds = array_map('intval', (array) ($_POST['vendor_ids'] ?? []));

    if ($subject === '') $errors[] = 'Subject is required.';
    if (trim(strip_tags($bodyHtml)) === '') $errors[] = 'Message body is required.';
    if (empty($vendorIds)) $errors[] = 'Select at least one vendor.';

    if (empty($errors)) {
        $bodyText = strip_tags($bodyHtml);
        $bodyHtml = '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.6;">'
                  . $bodyHtml
                  . '</div>';

        // Save the press release record first (files saved into its folder)
        $prId = $prService->create($subject, $bodyHtml, $bodyText, []);

        // Save uploaded files
        $savedFiles = [];
        if (!empty($_FILES['attachments']['name'][0])) {
            $savedFiles = $prService->saveUploadedFiles($prId, $_FILES['attachments'], $UPLOAD_DIR);
            if ($savedFiles) {
                $prService->updateAttachments($prId, $savedFiles);
            }
        }

        // Build attachment array for EmailService
        $emailAttachments = array_map(fn($f) => [
            'name' => $f['name'],
            'path' => $f['path'],
            'type' => $f['type'],
        ], $savedFiles);

        // Collect selected vendor rows
        $allVendors = [];
        foreach ($vendorGroups as $group) {
            foreach ($group as $v) {
                $allVendors[$v['id']] = $v;
            }
        }

        $sentCount = 0;
        foreach ($vendorIds as $vid) {
            $vendor = $allVendors[$vid] ?? null;
            if (!$vendor) continue;

            $email = $vendor['email'] ?: $vendor['billing_email'];
            if (!$email) {
                $prService->addRecipient($prId, $vid, '', 'failed', 'No email address on file');
                continue;
            }

            $result = $emailService->send(
                toEmail:     $email,
                toName:      $vendor['company_name'],
                subject:     $subject,
                bodyHtml:    $bodyHtml,
                bodyText:    $bodyText ?? strip_tags($bodyHtml),
                attachments: $emailAttachments
            );

            $prService->addRecipient(
                $prId,
                $vid,
                $email,
                $result['success'] ? 'sent' : 'failed',
                $result['success'] ? '' : $result['message'],
                $result['logId'] ?? 0
            );
            if ($result['success']) $sentCount++;
        }

        $prService->markSent($prId, $sentCount);
        flash('success', "Press release sent to {$sentCount} of " . count($vendorIds) . " vendors.");
        redirect('/press-releases/view.php?id=' . $prId);
    }
}

$extraHead = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-bs5.min.css">';
$pageTitle = 'New Press Release — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">
            <i class="bi bi-send me-2 text-primary"></i>New Press Release
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="/press-releases/index.php">Press Releases</a></li>
                <li class="breadcrumb-item active">Compose</li>
            </ol>
        </nav>
    </div>
    <a href="/press-releases/templates.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-file-earmark-text me-1"></i>Manage Templates
    </a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <strong>Please fix the following:</strong>
    <ul class="mb-0 mt-1"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
<div class="row g-4">

    <!-- Left: Compose -->
    <div class="col-lg-7">

        <?php if (!empty($templates)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Load Template</h5>
            </div>
            <div class="card-body">
                <div class="d-flex gap-2 align-items-end">
                    <div class="flex-grow-1">
                        <label for="templatePicker" class="form-label small fw-semibold mb-1">Choose a boilerplate to pre-fill the message</label>
                        <select class="form-select" id="templatePicker">
                            <option value="">— Select a template —</option>
                            <?php foreach ($templates as $t): ?>
                            <option value="<?= (int)$t['id'] ?>"><?= h($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="btn btn-outline-primary" onclick="loadTemplate()">
                        <i class="bi bi-arrow-down-circle me-1"></i>Load
                    </button>
                </div>
                <div class="form-text">Loading a template will replace the current subject and body.</div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-envelope me-2 text-primary"></i>Message</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="subject" class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="subject" name="subject"
                           value="<?= h($_POST['subject'] ?? '') ?>" required autofocus
                           placeholder="e.g. SFP Boat Race — Media Kit 2026">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Message Body <span class="text-danger">*</span></label>
                    <textarea id="body_html_editor" name="body_html"><?= $_POST['body_html'] ?? '' ?></textarea>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-paperclip me-2 text-primary"></i>Attachments</h5>
            </div>
            <div class="card-body">
                <input type="file" class="form-control" id="attachments" name="attachments[]"
                       multiple accept=".pdf,.doc,.docx,.mp4,.mov,.avi,.wmv,.mkv">
                <div class="form-text mt-1">
                    Accepted: PDF, Word (.doc/.docx), Video (.mp4, .mov, .avi, .wmv, .mkv).<br>
                    <strong>Note:</strong> SendGrid limits total message size to ~25 MB. Large video files may fail — consider providing an external link instead.
                </div>
                <div id="fileList" class="mt-2 d-flex flex-wrap gap-2"></div>
            </div>
        </div>

    </div>

    <!-- Right: Vendor Selection -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm sticky-top" style="top:1rem;">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-people me-2 text-primary"></i>Recipients</h5>
                <div class="d-flex gap-2 align-items-center">
                    <span class="badge bg-primary" id="selectedCount">0 selected</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll(true)">All</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll(false)">None</button>
                </div>
            </div>
            <div class="card-body p-0" style="max-height:60vh;overflow-y:auto;">

                <?php if (empty($vendorGroups)): ?>
                <div class="text-center text-muted py-4 small">No active vendors found.</div>
                <?php endif; ?>

                <?php foreach ($vendorGroups as $category => $vendors): ?>
                <div class="border-bottom">
                    <div class="px-3 py-2 bg-light d-flex align-items-center justify-content-between">
                        <span class="fw-semibold small"><?= h($category) ?></span>
                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none small"
                                onclick="toggleCategory('cat-<?= h(preg_replace('/[^a-z0-9]/i','-', $category)) ?>', this)">
                            Select all
                        </button>
                    </div>
                    <div class="px-3 py-1" id="cat-<?= h(preg_replace('/[^a-z0-9]/i','-', $category)) ?>">
                        <?php foreach ($vendors as $v):
                            $email = $v['email'] ?: $v['billing_email'];
                            $hasEmail = !empty($email);
                            $prevChecked = isset($_POST['vendor_ids']) && in_array($v['id'], array_map('intval', $_POST['vendor_ids']));
                        ?>
                        <div class="py-1 <?= !$hasEmail ? 'opacity-50' : '' ?>">
                            <div class="form-check">
                                <input class="form-check-input vendor-cb" type="checkbox"
                                       name="vendor_ids[]"
                                       value="<?= (int)$v['id'] ?>"
                                       id="v<?= (int)$v['id'] ?>"
                                       <?= $prevChecked ? 'checked' : '' ?>
                                       <?= !$hasEmail ? 'disabled title="No email address"' : '' ?>
                                       onchange="updateCount()">
                                <label class="form-check-label small" for="v<?= (int)$v['id'] ?>">
                                    <span class="fw-semibold"><?= h($v['company_name']) ?></span>
                                    <?php if ($v['contact_name']): ?>
                                        <span class="text-muted"> — <?= h($v['contact_name']) ?></span>
                                    <?php endif; ?>
                                    <br>
                                    <span class="text-muted" style="font-size:.75rem;">
                                        <?= $hasEmail ? h($email) : '<em>No email on file</em>' ?>
                                    </span>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="card-footer bg-white">
                <button type="submit" class="btn btn-primary w-100" id="sendBtn">
                    <i class="bi bi-send-fill me-2"></i>Send Press Release
                </button>
                <div class="text-center mt-2">
                    <a href="/press-releases/index.php" class="text-muted small">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>
</form>

<script>
function updateCount() {
    const n = document.querySelectorAll('.vendor-cb:checked').length;
    document.getElementById('selectedCount').textContent = n + ' selected';
    document.getElementById('sendBtn').disabled = n === 0;
}

function toggleAll(checked) {
    document.querySelectorAll('.vendor-cb:not([disabled])').forEach(cb => cb.checked = checked);
    updateCount();
}

function toggleCategory(catId, btn) {
    const group = document.getElementById(catId);
    const cbs   = group.querySelectorAll('.vendor-cb:not([disabled])');
    const allOn = [...cbs].every(cb => cb.checked);
    cbs.forEach(cb => cb.checked = !allOn);
    btn.textContent = allOn ? 'Select all' : 'Deselect all';
    updateCount();
}

document.getElementById('attachments').addEventListener('change', function () {
    const list = document.getElementById('fileList');
    list.innerHTML = '';
    [...this.files].forEach(f => {
        const kb   = (f.size / 1024).toFixed(0);
        const mb   = f.size / (1024 * 1024);
        const warn = mb > 20;
        const ext  = f.name.split('.').pop().toLowerCase();
        const icons = {pdf:'file-earmark-pdf', doc:'file-earmark-word', docx:'file-earmark-word',
                       mp4:'film', mov:'film', avi:'film', wmv:'film', mkv:'film'};
        const icon = icons[ext] || 'file-earmark';
        list.insertAdjacentHTML('beforeend',
            `<span class="badge ${warn ? 'bg-warning text-dark' : 'bg-light text-dark'} border small">
                <i class="bi bi-${icon} me-1"></i>${f.name}
                <span class="ms-1 opacity-75">${kb > 1024 ? (mb.toFixed(1)+'MB') : kb+'KB'}</span>
                ${warn ? '<i class="bi bi-exclamation-triangle ms-1 text-danger" title="Large file — may exceed SendGrid limit"></i>' : ''}
             </span>`
        );
    });
});

updateCount();
</script>

<?php
$extraScripts = '
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-bs5.min.js"></script>
<script>
const TEMPLATES = ' . json_encode(
    array_column(
        array_map(fn($t) => ['id' => (int)$t['id'], 'subject' => $t['subject'], 'body_text' => $t['body_text']], $templates),
        null, 'id'
    )
) . ';

$(function () {
    $("#body_html_editor").summernote({
        height: 380,
        placeholder: "Write your press release message here...",
        toolbar: [
            ["style",  ["bold", "italic", "underline", "strikethrough", "clear"]],
            ["font",   ["fontsize"]],
            ["color",  ["color"]],
            ["para",   ["ul", "ol", "paragraph"]],
            ["table",  ["table"]],
            ["insert", ["link", "hr"]],
            ["view",   ["fullscreen", "codeview"]],
        ],
    });
});

function loadTemplate() {
    const id = parseInt(document.getElementById("templatePicker").value, 10);
    if (!id || !TEMPLATES[id]) {
        alert("Please select a template first.");
        return;
    }
    const t = TEMPLATES[id];
    document.getElementById("subject").value = t.subject;

    // Convert plain-text newlines → HTML paragraphs
    const html = (t.body_text || "")
        .split(/\n\n+/)
        .map(p => "<p>" + p.replace(/\n/g, "<br>") + "</p>")
        .join("") || "<p></p>";

    // Try Summernote API first; fall back to raw textarea value
    const $ed = $("#body_html_editor");
    if ($ed.length && $ed.data("summernote")) {
        $ed.summernote("code", html);
    } else {
        document.getElementById("body_html_editor").value = html;
    }

    document.getElementById("subject").focus();
}
</script>';

require_once __DIR__ . '/../includes/footer.php';
