<?php
require_once __DIR__ . '/../bootstrap.php';
requireRole(['admin', 'buyer']);

$prService    = new PressReleaseService();
$crmService   = new CRMService();
$emailService = new EmailService();

$errors              = [];
$vendorGroups        = $prService->getVendorsByCategory();
$mediaContactsByMarket = $prService->getMediaContactsByMarket();
$templates           = $prService->getTemplates();
$clients      = $crmService->getClients();
$UPLOAD_DIR   = __DIR__ . '/../uploads/press-releases/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Remove PHP execution time limit for this request — we may send many emails
    set_time_limit(0);
    ini_set('max_execution_time', '0');
    $subject         = trim($_POST['subject']    ?? '');
    $bodyHtml        = trim($_POST['body_html']  ?? '');   // Summernote posts HTML
    $vendorIds       = array_map('intval', (array) ($_POST['vendor_ids'] ?? []));
    $clientId        = (int) ($_POST['client_id'] ?? 0);
    $mediaContactIds = array_map('intval', (array) ($_POST['media_contact_ids'] ?? []));

    if ($subject === '') $errors[] = 'Subject is required.';
    if (trim(strip_tags($bodyHtml)) === '') $errors[] = 'Message body is required.';
    if (empty($vendorIds) && $clientId === 0 && empty($mediaContactIds)) $errors[] = 'Select at least one recipient.';

    if (empty($errors)) {
        $bodyText = strip_tags($bodyHtml);
        $bodyHtml = '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.6;">'
                  . $bodyHtml
                  . '</div>';

        // Save the press release record first (files saved into its folder)
        $prId = $prService->create($subject, $bodyHtml, $bodyText, [], $clientId);

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

        $sentCount = 0;

        // ── Send to client (primary + secondary email) if selected ───────────
        if ($clientId > 0) {
            $clientRecord = null;
            foreach ($clients as $c) {
                if ((int)$c['id'] === $clientId) { $clientRecord = $c; break; }
            }
            if ($clientRecord) {
                $clientEmails = array_filter([
                    $clientRecord['email']           ?? '',
                    $clientRecord['secondary_email'] ?? '',
                ]);
                foreach ($clientEmails as $cEmail) {
                    $result = $emailService->send(
                        toEmail:     $cEmail,
                        toName:      $clientRecord['company_name'],
                        subject:     $subject,
                        bodyHtml:    $bodyHtml,
                        bodyText:    $bodyText,
                        attachments: $emailAttachments
                    );
                    $prService->addRecipient(
                        $prId, 0, $cEmail,
                        $result['success'] ? 'sent' : 'failed',
                        $result['success'] ? '' : ($result['message'] ?? ''),
                        $result['logId'] ?? 0,
                        'client', $clientId
                    );
                    if ($result['success']) $sentCount++;
                }
            }
        }

        // ── Send to selected vendors ─────────────────────────────────────────
        $allVendors = [];
        foreach ($vendorGroups as $group) {
            foreach ($group as $v) {
                $allVendors[$v['id']] = $v;
            }
        }

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
                $prId, $vid, $email,
                $result['success'] ? 'sent' : 'failed',
                $result['success'] ? '' : ($result['message'] ?? ''),
                $result['logId'] ?? 0
            );
            if ($result['success']) $sentCount++;
        }

        // ── Send to media contacts ────────────────────────────────────────────────
        $allMediaContacts = $prService->getMediaContactsFlat();
        foreach ($mediaContactIds as $mcId) {
            $mc = $allMediaContacts[$mcId] ?? null;
            if (!$mc || !$mc['email']) continue;

            $result = $emailService->send(
                toEmail:     $mc['email'],
                toName:      $mc['contact_name'] ?: $mc['outlet_name'],
                subject:     $subject,
                bodyHtml:    $bodyHtml,
                bodyText:    $bodyText,
                attachments: $emailAttachments
            );

            $prService->addRecipient(
                $prId, 0, $mc['email'],
                $result['success'] ? 'sent' : 'failed',
                $result['success'] ? '' : ($result['message'] ?? ''),
                $result['logId'] ?? 0,
                'media_contact', 0, $mcId
            );
            if ($result['success']) $sentCount++;
        }

        $totalRecipients = count($vendorIds)
            + (isset($clientEmails) ? count($clientEmails) : 0)
            + count($mediaContactIds);
        $prService->markSent($prId, $sentCount);
        flash('success', "Press release sent to {$sentCount} of {$totalRecipients} recipients.");
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

        <!-- Client selector -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-building me-2 text-primary"></i>Client</h5>
            </div>
            <div class="card-body">
                <label for="client_id" class="form-label fw-semibold">Send to Client <span class="text-muted fw-normal">(optional)</span></label>
                <select name="client_id" id="client_id" class="form-select" onchange="updateClientPreview()">
                    <option value="0">— No client —</option>
                    <?php foreach ($clients as $c):
                        $selected = ((int)($_POST['client_id'] ?? 0) === (int)$c['id']) ? 'selected' : '';
                    ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $selected ?>
                            data-email="<?= h($c['email'] ?? '') ?>"
                            data-secondary="<?= h($c['secondary_email'] ?? '') ?>">
                        <?= h($c['company_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div id="clientEmailPreview" class="mt-2"></div>
                <div class="form-text">When selected, the press release is also sent to the client's primary and secondary email addresses.</div>
            </div>
        </div>

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
                <!-- Hidden real input; JS accumulates files into it via DataTransfer -->
                <input type="file" id="attachmentPicker" name="attachments[]"
                       multiple accept=".pdf,.doc,.docx,.mp4,.mov,.avi,.wmv,.mkv,.jpg,.jpeg,.png,.eps"
                       style="display:none;">
                <div class="d-flex gap-2 align-items-center mb-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('attachmentPicker').click()">
                        <i class="bi bi-plus-circle me-1"></i>Add Files
                    </button>
                    <span id="attachTotalSize" class="text-muted small"></span>
                </div>
                <div class="form-text mb-2">
                    Accepted: PDF, Word (.doc/.docx), Video (.mp4, .mov, .avi, .wmv, .mkv), Images (.jpg, .jpeg, .png, .eps).<br>
                    <strong>Note:</strong> SendGrid limits total message size to ~25 MB. Large video files may fail — consider providing an external link instead.
                </div>
                <div id="fileList" class="d-flex flex-wrap gap-2"></div>
            </div>
        </div>

    </div>

    <!-- Right: Recipients -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm sticky-top" style="top:1rem;">
            <div class="card-header bg-white py-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h5 class="mb-0 fw-semibold"><i class="bi bi-people me-2 text-primary"></i>Recipients</h5>
                    <span class="badge bg-primary" id="selectedCount">0 selected</span>
                </div>
                <!-- Tab nav -->
                <ul class="nav nav-tabs card-header-tabs" id="recipientTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-vendors" data-bs-toggle="tab"
                                data-bs-target="#pane-vendors" type="button" role="tab">
                            Vendors
                            <span class="badge bg-secondary ms-1" id="vendorTabCount">0</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-media" data-bs-toggle="tab"
                                data-bs-target="#pane-media" type="button" role="tab">
                            Media Contacts
                            <span class="badge bg-secondary ms-1" id="mediaTabCount">0</span>
                        </button>
                    </li>
                </ul>
            </div>

            <div class="tab-content" style="max-height:58vh;overflow-y:auto;">

                <!-- Vendors pane -->
                <div class="tab-pane fade show active" id="pane-vendors" role="tabpanel">
                    <div class="px-2 py-2 border-bottom bg-light d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0" onclick="toggleAll(true)">All</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0" onclick="toggleAll(false)">None</button>
                    </div>
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
                                           name="vendor_ids[]" value="<?= (int)$v['id'] ?>"
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
                </div><!-- /vendors pane -->

                <!-- Media Contacts pane -->
                <div class="tab-pane fade" id="pane-media" role="tabpanel">
                    <div class="px-2 py-2 border-bottom bg-light d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0" onclick="toggleAllMedia(true)">All</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0" onclick="toggleAllMedia(false)">None</button>
                    </div>
                    <?php if (empty($mediaContactsByMarket)): ?>
                    <div class="text-center text-muted py-4 small">
                        No media contacts yet. <a href="/press-releases/import-media-contacts.php">Import media list</a>
                    </div>
                    <?php else: ?>
                    <?php foreach ($mediaContactsByMarket as $market => $outlets): ?>
                    <div class="border-bottom">
                        <div class="px-3 py-2 bg-light d-flex align-items-center justify-content-between">
                            <span class="fw-semibold small"><?= h($market) ?></span>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none small"
                                    onclick="toggleMarket('mkt-<?= h(preg_replace('/[^a-z0-9]/i','-', $market)) ?>', this)">
                                Select all
                            </button>
                        </div>
                        <div id="mkt-<?= h(preg_replace('/[^a-z0-9]/i','-', $market)) ?>">
                        <?php foreach ($outlets as $outletName => $contacts): ?>
                        <div class="px-3 pt-2 pb-1">
                            <div class="fw-semibold small text-secondary mb-1"><?= h($outletName) ?></div>
                            <?php foreach ($contacts as $mc): ?>
                            <div class="py-0 ps-2">
                                <div class="form-check">
                                    <input class="form-check-input media-cb" type="checkbox"
                                           name="media_contact_ids[]" value="<?= (int)$mc['id'] ?>"
                                           id="mc<?= (int)$mc['id'] ?>"
                                           onchange="updateCount()">
                                    <label class="form-check-label small" for="mc<?= (int)$mc['id'] ?>">
                                        <?php if ($mc['contact_name']): ?>
                                            <span class="fw-semibold"><?= h($mc['contact_name']) ?></span>
                                        <?php else: ?>
                                            <em class="text-muted">No name</em>
                                        <?php endif; ?>
                                        <br>
                                        <span class="text-muted" style="font-size:.75rem;"><?= h($mc['email']) ?></span>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div><!-- /media contacts pane -->

            </div><!-- /tab-content -->

            <div class="card-footer bg-white">
                <button type="submit" class="btn btn-primary w-100" id="sendBtn" disabled>
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

<!-- Submit progress overlay -->
<div id="sendOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;
     flex-direction:column;align-items:center;justify-content:center;gap:1rem;">
    <div class="spinner-border text-light" style="width:3rem;height:3rem;" role="status"></div>
    <div class="text-white fw-semibold fs-5">Sending press release…</div>
    <div id="sendOverlayDetail" class="text-white-50 small">Uploading files and queuing emails, please wait.</div>
</div>

<script>
function updateClientPreview() {
    var sel     = document.getElementById('client_id');
    var opt     = sel.options[sel.selectedIndex];
    var preview = document.getElementById('clientEmailPreview');
    var email   = opt.dataset.email     || '';
    var sec     = opt.dataset.secondary || '';
    if (!email && !sec) { preview.innerHTML = ''; return; }
    var badges = '';
    if (email) badges += '<span class="badge bg-success me-1"><i class="bi bi-envelope me-1"></i>' + escHtml(email) + '</span>';
    if (sec)   badges += '<span class="badge bg-info text-dark me-1"><i class="bi bi-envelope me-1"></i>' + escHtml(sec) + ' <span class="opacity-75">(secondary)</span></span>';
    preview.innerHTML = '<div class="d-flex flex-wrap gap-1 align-items-center"><span class="text-muted small me-1">Will receive:</span>' + badges + '</div>';
}
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
// ── Recipient count / send button ────────────────────────────────────────────
function updateCount() {
    const vn        = document.querySelectorAll('.vendor-cb:checked').length;
    const mn        = document.querySelectorAll('.media-cb:checked').length;
    const hasClient = document.getElementById('client_id').value !== '0';
    const total     = vn + mn + (hasClient ? 1 : 0);

    document.getElementById('selectedCount').textContent = total + ' selected';
    document.getElementById('vendorTabCount').textContent = vn;
    document.getElementById('mediaTabCount').textContent  = mn;
    document.getElementById('sendBtn').disabled = (total === 0);
}

function toggleAll(checked) {
    document.querySelectorAll('.vendor-cb:not([disabled])').forEach(cb => cb.checked = checked);
    updateCount();
}

function toggleAllMedia(checked) {
    document.querySelectorAll('.media-cb').forEach(cb => cb.checked = checked);
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

function toggleMarket(mktId, btn) {
    const group = document.getElementById(mktId);
    const cbs   = group.querySelectorAll('.media-cb');
    const allOn = [...cbs].every(cb => cb.checked);
    cbs.forEach(cb => cb.checked = !allOn);
    btn.textContent = allOn ? 'Select all' : 'Deselect all';
    updateCount();
}

document.getElementById('client_id').addEventListener('change', updateCount);

// ── File attachment accumulation ─────────────────────────────────────────────
const FILE_ICONS = {
    pdf:'file-earmark-pdf', doc:'file-earmark-word', docx:'file-earmark-word',
    mp4:'film', mov:'film', avi:'film', wmv:'film', mkv:'film',
    jpg:'file-earmark-image', jpeg:'file-earmark-image', png:'file-earmark-image', eps:'file-earmark-image'
};

// We keep our own File array and sync it to a hidden <input> via DataTransfer
let attachedFiles = [];

function renderFileList() {
    const list    = document.getElementById('fileList');
    const sizeEl  = document.getElementById('attachTotalSize');
    list.innerHTML = '';

    let totalBytes = 0;
    attachedFiles.forEach((f, idx) => {
        totalBytes += f.size;
        const kb   = (f.size / 1024).toFixed(0);
        const mb   = f.size / (1024 * 1024);
        const warn = mb > 20;
        const ext  = f.name.split('.').pop().toLowerCase();
        const icon = FILE_ICONS[ext] || 'file-earmark';
        list.insertAdjacentHTML('beforeend',
            `<span class="badge ${warn ? 'bg-warning text-dark' : 'bg-light text-dark'} border d-inline-flex align-items-center gap-1" style="font-size:.8rem;padding:.35em .55em;">
                <i class="bi bi-${escHtml(icon)}"></i>
                ${escHtml(f.name)}
                <span class="opacity-75">${mb >= 1 ? mb.toFixed(1)+'MB' : kb+'KB'}</span>
                ${warn ? '<i class="bi bi-exclamation-triangle text-danger" title="Large file — may exceed SendGrid limit"></i>' : ''}
                <button type="button" class="btn-close btn-close-sm ms-1" style="font-size:.6rem;"
                        aria-label="Remove" onclick="removeFile(${idx})"></button>
             </span>`
        );
    });

    // Total size indicator
    const totalMb = totalBytes / (1024 * 1024);
    if (attachedFiles.length > 0) {
        const overLimit = totalMb > 24;
        sizeEl.innerHTML = `<span class="${overLimit ? 'text-danger fw-semibold' : ''}">
            ${attachedFiles.length} file${attachedFiles.length > 1 ? 's' : ''} —
            total ${totalMb.toFixed(1)} MB${overLimit ? ' ⚠ Exceeds 25 MB limit' : ''}
        </span>`;
    } else {
        sizeEl.textContent = '';
    }

    // Sync to actual file input via DataTransfer
    syncFilesToInput();
}

function syncFilesToInput() {
    const dt = new DataTransfer();
    attachedFiles.forEach(f => dt.items.add(f));
    document.getElementById('attachmentPicker').files = dt.files;
}

function removeFile(idx) {
    attachedFiles.splice(idx, 1);
    renderFileList();
}

document.getElementById('attachmentPicker').addEventListener('change', function () {
    const incoming = [...this.files];
    incoming.forEach(f => {
        // Skip duplicates (same name + size)
        const dup = attachedFiles.some(e => e.name === f.name && e.size === f.size);
        if (!dup) attachedFiles.push(f);
    });
    // Reset picker so same file can be re-added after removal
    this.value = '';
    renderFileList();
});

// ── Submit progress overlay ───────────────────────────────────────────────────
document.querySelector('form').addEventListener('submit', function (e) {
    // Re-sync files before submit (safety net)
    syncFilesToInput();

    const vendorCount  = document.querySelectorAll('.vendor-cb:checked').length;
    const mediaCount   = document.querySelectorAll('.media-cb:checked').length;
    const hasClient    = document.getElementById('client_id').value !== '0';
    const totalRecip   = vendorCount + mediaCount + (hasClient ? 1 : 0);
    const fileCount    = attachedFiles.length;

    const detail = [];
    if (totalRecip > 0) detail.push(`Sending to ${totalRecip} recipient${totalRecip !== 1 ? 's' : ''}`);
    if (fileCount  > 0) detail.push(`${fileCount} attachment${fileCount !== 1 ? 's' : ''}`);

    document.getElementById('sendOverlayDetail').textContent =
        detail.length ? detail.join(' · ') + '…' : 'Uploading files and queuing emails, please wait.';

    const overlay = document.getElementById('sendOverlay');
    overlay.style.display = 'flex';

    // Disable submit to prevent double-click
    document.getElementById('sendBtn').disabled = true;
});

updateCount();
updateClientPreview();
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

    if (t.subject) {
        document.getElementById("subject").value = t.subject;
    }

    const rawText = (t.body_text || "").trim();
    if (!rawText) {
        alert("This template has no message body. Go to Manage Templates to add content.");
        return;
    }

    const html = rawText
        .split(/\n\n+/)
        .map(p => "<p>" + p.replace(/\n/g, "<br>") + "</p>")
        .join("");

    // Set via Summernote API
    $("#body_html_editor").summernote("code", html);

    // Direct fallback: write into the contenteditable div Summernote renders
    const editable = document.querySelector(".note-editable[contenteditable]");
    if (editable) editable.innerHTML = html;

    // Visual confirmation the load ran
    const btn = document.querySelector("button[onclick=\"loadTemplate()\"]");
    if (btn) {
        const orig = btn.innerHTML;
        btn.innerHTML = "<i class=\"bi bi-check-circle-fill me-1\"></i>Loaded";
        btn.classList.replace("btn-outline-primary", "btn-success");
        setTimeout(() => { btn.innerHTML = orig; btn.classList.replace("btn-success", "btn-outline-primary"); }, 2000);
    }

    document.getElementById("subject").focus();
}
</script>';

require_once __DIR__ . '/../includes/footer.php';
