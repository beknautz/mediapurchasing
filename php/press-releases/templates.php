<?php
require_once __DIR__ . '/../bootstrap.php';
requireRole(['admin', 'buyer']);

$prService = new PressReleaseService();
$errors    = [];
$success   = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id      = (int) ($_POST['id'] ?? 0);
        $name    = trim($_POST['name']      ?? '');
        $subject = trim($_POST['subject']   ?? '');
        $body    = trim($_POST['body_text'] ?? '');

        if ($name === '')    $errors[] = 'Template name is required.';
        if ($subject === '') $errors[] = 'Subject is required.';
        if ($body === '')    $errors[] = 'Body is required.';

        if (empty($errors)) {
            try {
                $prService->saveTemplate(['id' => $id, 'name' => $name, 'subject' => $subject, 'body_text' => $body]);
                flash('success', $id > 0 ? 'Template updated.' : 'Template created.');
                redirect('/press-releases/templates.php');
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $prService->deleteTemplate($id);
                flash('success', 'Template deleted.');
            } catch (Exception $e) {
                flash('success', ''); // clear any stale flash
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
        if (empty($errors)) redirect('/press-releases/templates.php');
    }
}

try {
    $templates = $prService->getTemplates();
} catch (Exception $e) {
    $templates = [];
    $errors[]  = 'Could not load templates — the database table may not exist yet. '
               . 'Please run the press_release_templates CREATE TABLE statement from sql/migrate_press_releases.sql. '
               . '(' . $e->getMessage() . ')';
}
$flashMsg  = flash('success');

$pageTitle = 'Press Release Templates — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">
            <i class="bi bi-file-earmark-text me-2 text-primary"></i>Press Release Templates
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="/press-releases/index.php">Press Releases</a></li>
                <li class="breadcrumb-item active">Templates</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        <a href="/press-releases/index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
        <button type="button" class="btn btn-primary" onclick="openTemplateModal()">
            <i class="bi bi-plus-circle me-1"></i>New Template
        </button>
    </div>
</div>

<?php if ($flashMsg): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle-fill me-2"></i><?= h($flashMsg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <strong>Please fix the following:</strong>
    <ul class="mb-0 mt-1"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($templates)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-file-earmark-text fs-2 d-block mb-2 opacity-50"></i>
            <div>No templates yet. Create one to speed up future press releases.</div>
            <button type="button" class="btn btn-sm btn-outline-primary mt-3" onclick="openTemplateModal()">
                <i class="bi bi-plus-circle me-1"></i>Create First Template
            </button>
        </div>
        <?php else: ?>
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Subject</th>
                    <th>Created By</th>
                    <th>Last Updated</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($templates as $t): ?>
            <tr>
                <td class="fw-semibold"><?= h($t['name']) ?></td>
                <td class="text-muted small"><?= h($t['subject']) ?></td>
                <td class="small text-muted"><?= h($t['created_by_name'] ?? '—') ?></td>
                <td class="small text-muted text-nowrap"><?= h(date('M j, Y', strtotime($t['updated_at']))) ?></td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-secondary me-1"
                            onclick='editTemplate(<?= json_encode(['id'=>$t['id'],'name'=>$t['name'],'subject'=>$t['subject'],'body_text'=>$t['body_text']]) ?>)'>
                        <i class="bi bi-pencil me-1"></i>Edit
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger"
                            onclick="confirmDelete(<?= (int)$t['id'] ?>, <?= json_encode($t['name']) ?>)">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Create / Edit Modal -->
<?php
$postId      = (int)   ($_POST['id']       ?? 0);
$postName    = (string)($_POST['name']     ?? '');
$postSubject = (string)($_POST['subject']  ?? '');
$postBody    = (string)($_POST['body_text']?? '');
$reopenModal = ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save' && !empty($errors));
?>
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="tmplId" value="<?= $postId ?>">
                <div class="modal-header">
                    <h5 class="modal-title fw-semibold" id="templateModalTitle">
                        <i class="bi bi-file-earmark-text me-2 text-primary"></i><?= $postId > 0 ? 'Edit' : 'New' ?> Template
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="tmplName" class="form-label fw-semibold">Template Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="tmplName" name="name" required
                               value="<?= h($postName) ?>"
                               placeholder="e.g. Event Announcement Boilerplate">
                    </div>
                    <div class="mb-3">
                        <label for="tmplSubject" class="form-label fw-semibold">Default Subject <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="tmplSubject" name="subject" required
                               value="<?= h($postSubject) ?>"
                               placeholder="e.g. [EVENT NAME] — Media Kit {{year}}">
                        <div class="form-text">You can edit the subject when composing.</div>
                    </div>
                    <div class="mb-0">
                        <label for="tmplBody" class="form-label fw-semibold">Message Body <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="tmplBody" name="body_text"
                                  rows="14" required
                                  placeholder="Write the boilerplate text here. Use placeholders like {{event_name}}, {{date}}, etc."><?= h($postBody) ?></textarea>
                        <div class="form-text">Plain text. Use {{placeholders}} for parts you'll fill in when composing.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy me-1"></i>Save Template
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Template?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body small">
                    Delete "<strong id="deleteName"></strong>"? This cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const templateModal = new bootstrap.Modal(document.getElementById('templateModal'));
const deleteModal   = new bootstrap.Modal(document.getElementById('deleteModal'));

function openTemplateModal() {
    document.getElementById('templateModalTitle').innerHTML =
        '<i class="bi bi-file-earmark-text me-2 text-primary"></i>New Template';
    document.getElementById('tmplId').value      = '0';
    document.getElementById('tmplName').value    = '';
    document.getElementById('tmplSubject').value = '';
    document.getElementById('tmplBody').value    = '';
    templateModal.show();
}

function editTemplate(t) {
    document.getElementById('templateModalTitle').innerHTML =
        '<i class="bi bi-pencil me-2 text-primary"></i>Edit Template';
    document.getElementById('tmplId').value      = t.id;
    document.getElementById('tmplName').value    = t.name;
    document.getElementById('tmplSubject').value = t.subject;
    document.getElementById('tmplBody').value    = t.body_text;
    templateModal.show();
}

function confirmDelete(id, name) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteName').textContent = name;
    deleteModal.show();
}

<?php if ($reopenModal): ?>
// Reopen modal with previously entered data after a validation/DB error
document.addEventListener('DOMContentLoaded', function () { templateModal.show(); });
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
