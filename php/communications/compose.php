<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';

requireRole(['admin', 'buyer']);

$emailService = new EmailService();

// Pre-fill from GET params
$formData = [
    'to_email'    => trim($_GET['to_email']    ?? ''),
    'to_name'     => trim($_GET['to_name']     ?? ''),
    'subject'     => trim($_GET['subject']     ?? ''),
    'body_html'   => trim($_GET['body_html']   ?? ''),
    'body_text'   => trim($_GET['body_text']   ?? ''),
    'media_buy_id'=> (int) ($_GET['media_buy_id'] ?? 0),
    'template_id' => (int) ($_GET['template_id']  ?? 0),
];

// Load templates for dropdown
$templates = [];
$pdo = (function () {
    if (!defined('DB_HOST')) return null;
    try {
        return new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET),
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (PDOException $e) { return null; }
})();

$mediaBuys = [];

if ($pdo) {
    $templates = $pdo->query(
        "SELECT id, name, subject, body_html, body_text FROM email_templates WHERE is_active = 1 ORDER BY name"
    )->fetchAll();

    $mediaBuys = $pdo->query(
        "SELECT id, title FROM media_buys WHERE status NOT IN ('cancelled') ORDER BY updated_at DESC LIMIT 200"
    )->fetchAll();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['to_email']     = trim($_POST['to_email']     ?? '');
    $formData['to_name']      = trim($_POST['to_name']      ?? '');
    $formData['subject']      = trim($_POST['subject']      ?? '');
    $formData['body_html']    = trim($_POST['body_html']    ?? '');
    $formData['body_text']    = trim($_POST['body_text']    ?? '');
    $formData['media_buy_id'] = (int) ($_POST['media_buy_id'] ?? 0);
    $formData['template_id']  = (int) ($_POST['template_id']  ?? 0);

    if ($formData['to_email'] === '') {
        $errors[] = 'Recipient email address is required.';
    } elseif (!filter_var($formData['to_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($formData['subject'] === '') {
        $errors[] = 'Subject is required.';
    }
    if ($formData['body_html'] === '' && $formData['body_text'] === '') {
        $errors[] = 'Please enter a message body.';
    }

    if (empty($errors)) {
        $sendResult = $emailService->sendAdHoc([
            'to_email'     => $formData['to_email'],
            'to_name'      => $formData['to_name'],
            'subject'      => $formData['subject'],
            'body_html'    => $formData['body_html'],
            'body_text'    => $formData['body_text'],
            'media_buy_id' => $formData['media_buy_id'] ?: null,
            'template_id'  => $formData['template_id']  ?: null,
            'sent_by'      => $_SESSION['user']['id'] ?? 0,
        ]);

        if ($sendResult['success'] ?? false) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Email sent successfully.'];
            redirect('/communications/index.php');
        } else {
            $errors[] = $sendResult['message'] ?? 'Failed to send email. Please try again.';
        }
    }
}

// Build JSON for template auto-populate
$templatesJson = json_encode(array_column($templates, null, 'id'), JSON_HEX_TAG | JSON_HEX_APOS);

$pageTitle = 'Compose Email';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">
            <i class="bi bi-send me-2 text-primary"></i>Compose Email
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="/communications/index.php">Communications</a></li>
                <li class="breadcrumb-item active">Compose</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Please fix the following:</strong>
    <ul class="mb-0 mt-1">
        <?php foreach ($errors as $err): ?>
            <li><?= h($err) ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">
<div class="col-lg-8">

<form method="POST" action="" id="composeForm">
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-envelope me-2 text-primary"></i>New Message</h5>
    </div>
    <div class="card-body">

        <!-- Template selector -->
        <?php if (!empty($templates)): ?>
        <div class="mb-4 p-3 bg-light rounded">
            <label for="template_id" class="form-label fw-semibold small">
                <i class="bi bi-file-earmark-text me-1"></i>Load from Template
            </label>
            <select class="form-select form-select-sm" id="template_id" name="template_id">
                <option value="">— Select a template (optional) —</option>
                <?php foreach ($templates as $tpl): ?>
                    <option value="<?= (int)$tpl['id'] ?>" <?= (int)$formData['template_id'] === (int)$tpl['id'] ? 'selected' : '' ?>>
                        <?= h($tpl['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Selecting a template will populate the subject and body fields.</div>
        </div>
        <?php endif; ?>

        <div class="row g-3 mb-3">
            <div class="col-md-7">
                <label for="to_email" class="form-label fw-semibold">To (Email) <span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="to_email" name="to_email"
                       value="<?= h($formData['to_email']) ?>" required
                       placeholder="recipient@example.com">
            </div>
            <div class="col-md-5">
                <label for="to_name" class="form-label fw-semibold">To (Name)</label>
                <input type="text" class="form-control" id="to_name" name="to_name"
                       value="<?= h($formData['to_name']) ?>"
                       placeholder="Recipient's name">
            </div>
        </div>

        <div class="mb-3">
            <label for="subject" class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="subject" name="subject"
                   value="<?= h($formData['subject']) ?>" required
                   placeholder="Email subject line">
        </div>

        <div class="mb-3">
            <label for="body_html" class="form-label fw-semibold">HTML Body</label>
            <textarea class="form-control font-monospace" id="body_html" name="body_html" rows="10"
                      placeholder="HTML email body…"><?= h($formData['body_html']) ?></textarea>
            <div class="form-text">HTML formatting is supported.</div>
        </div>

        <div class="mb-3">
            <label for="body_text" class="form-label fw-semibold">Plain Text Body</label>
            <textarea class="form-control" id="body_text" name="body_text" rows="5"
                      placeholder="Plain text fallback for email clients that don't support HTML…"><?= h($formData['body_text']) ?></textarea>
        </div>

        <div class="mb-3">
            <label for="media_buy_id" class="form-label fw-semibold">Link to Media Buy <span class="text-muted fw-normal">(optional)</span></label>
            <select class="form-select" id="media_buy_id" name="media_buy_id">
                <option value="">— None —</option>
                <?php foreach ($mediaBuys as $mb): ?>
                    <option value="<?= (int)$mb['id'] ?>" <?= (int)$formData['media_buy_id'] === (int)$mb['id'] ? 'selected' : '' ?>>
                        <?= h($mb['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
        <a href="/communications/index.php" class="btn btn-outline-secondary">
            <i class="bi bi-x me-1"></i>Cancel
        </a>
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-send-fill me-2"></i>Send Email
        </button>
    </div>
</div>
</form>

</div><!-- /col-lg-8 -->

<!-- Sidebar tips -->
<div class="col-lg-4">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-lightbulb me-2 text-warning"></i>Tips</h6>
        </div>
        <div class="card-body small text-muted">
            <ul class="ps-3 mb-0">
                <li class="mb-1">Select a <strong>template</strong> to pre-populate common messages.</li>
                <li class="mb-1">Linking a <strong>media buy</strong> logs this message in the buy's communication history.</li>
                <li class="mb-1">Both HTML and plain text can be sent — recipients see whichever their client supports.</li>
                <li>Sent messages appear in the <a href="/communications/index.php">Communication Log</a>.</li>
            </ul>
        </div>
    </div>

    <?php if (!empty($templates)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Available Templates</h6>
        </div>
        <ul class="list-group list-group-flush">
            <?php foreach (array_slice($templates, 0, 8) as $tpl): ?>
            <li class="list-group-item px-3 py-2 small">
                <button type="button" class="btn btn-link btn-sm p-0 text-start text-decoration-none"
                        onclick="loadTemplate(<?= (int)$tpl['id'] ?>)">
                    <?= h($tpl['name']) ?>
                </button>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>

</div><!-- /row -->

<script>
var templates = <?= $templatesJson ?>;

function loadTemplate(id) {
    var tpl = templates[id];
    if (!tpl) return;

    if (document.getElementById('subject').value === '' || confirm('Replace current subject and body with template content?')) {
        document.getElementById('template_id').value = id;
        document.getElementById('subject').value     = tpl.subject   || '';
        document.getElementById('body_html').value   = tpl.body_html || '';
        document.getElementById('body_text').value   = tpl.body_text || '';
    }
}

document.getElementById('template_id').addEventListener('change', function () {
    var id = parseInt(this.value, 10);
    if (id) {
        loadTemplate(id);
    }
});

<?php if ($formData['template_id'] && !empty($templates)): ?>
// Pre-select template on page load if passed via GET
loadTemplate(<?= (int)$formData['template_id'] ?>);
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
