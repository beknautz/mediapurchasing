<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/config.php';

requireRole(['admin', 'buyer']);

$role   = $_SESSION['role'] ?? '';
$userId = (int) ($_SESSION['user']['id'] ?? 0);

$billingService = new BillingService();

// Load vendors and media buys for selects
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

$vendors   = [];
$mediaBuys = [];

if ($pdo) {
    $vendors   = $pdo->query("SELECT id, company_name FROM vendors WHERE is_active = 1 ORDER BY company_name")->fetchAll();
    $mediaBuys = $pdo->query("SELECT id, title FROM media_buys WHERE status NOT IN ('cancelled') ORDER BY updated_at DESC LIMIT 200")->fetchAll();
}

$errors   = [];
$formData = [
    'vendor_id'      => (int) ($_GET['vendor_id']   ?? 0),
    'media_buy_id'   => (int) ($_GET['media_buy_id'] ?? 0),
    'invoice_number' => '',
    'invoice_date'   => date('Y-m-d'),
    'due_date'       => '',
    'amount'         => '',
    'priority'       => 'normal',
    'intake_method'  => 'email',
    'notes'          => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['vendor_id']      = (int) ($_POST['vendor_id']      ?? 0);
    $formData['media_buy_id']   = (int) ($_POST['media_buy_id']   ?? 0);
    $formData['invoice_number'] = trim($_POST['invoice_number']    ?? '');
    $formData['invoice_date']   = trim($_POST['invoice_date']      ?? '');
    $formData['due_date']       = trim($_POST['due_date']          ?? '');
    $formData['amount']         = trim($_POST['amount']            ?? '');
    $formData['priority']       = trim($_POST['priority']          ?? 'normal');
    $formData['intake_method']  = trim($_POST['intake_method']     ?? 'email');
    $formData['notes']          = trim($_POST['notes']             ?? '');

    // Validation
    if (!$formData['vendor_id']) {
        $errors[] = 'Please select a vendor.';
    }
    if ($formData['invoice_number'] === '') {
        $errors[] = 'Invoice number is required.';
    }
    if ($formData['amount'] === '' || !is_numeric($formData['amount']) || (float)$formData['amount'] < 0) {
        $errors[] = 'Please enter a valid invoice amount.';
    }
    if ($formData['invoice_date'] === '') {
        $errors[] = 'Invoice date is required.';
    }

    $validPriorities    = ['normal', 'high', 'urgent', 'low'];
    $validIntakeMethods = ['email', 'mail', 'portal', 'fax', 'hand_delivered', 'other'];

    if (!in_array($formData['priority'], $validPriorities, true)) {
        $formData['priority'] = 'normal';
    }
    if (!in_array($formData['intake_method'], $validIntakeMethods, true)) {
        $formData['intake_method'] = 'email';
    }

    // Handle file upload
    $filePath = null;
    if (!empty($_FILES['invoice_file']['name'])) {
        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType     = mime_content_type($_FILES['invoice_file']['tmp_name']);
        $fileSize     = $_FILES['invoice_file']['size'];

        if (!in_array($fileType, $allowedTypes, true)) {
            $errors[] = 'Invalid file type. Allowed: PDF, JPG, PNG, GIF, WEBP.';
        } elseif ($fileSize > 10 * 1024 * 1024) {
            $errors[] = 'File too large. Maximum 10 MB.';
        } elseif ($_FILES['invoice_file']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload failed. Please try again.';
        } else {
            $uploadDir = __DIR__ . '/../../uploads/invoices/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext       = pathinfo($_FILES['invoice_file']['name'], PATHINFO_EXTENSION);
            $fileName  = 'inv_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destPath  = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['invoice_file']['tmp_name'], $destPath)) {
                $filePath = '/uploads/invoices/' . $fileName;
            } else {
                $errors[] = 'Failed to save uploaded file.';
            }
        }
    }

    if (empty($errors)) {
        $createResult = $billingService->createBill([
            'vendor_id'      => $formData['vendor_id'],
            'media_buy_id'   => $formData['media_buy_id'] ?: null,
            'invoice_number' => $formData['invoice_number'],
            'invoice_date'   => $formData['invoice_date'],
            'due_date'       => $formData['due_date'] ?: null,
            'amount'         => (float) $formData['amount'],
            'priority'       => $formData['priority'],
            'intake_method'  => $formData['intake_method'],
            'notes'          => $formData['notes'],
            'file_path'      => $filePath,
            'created_by'     => $userId,
            'status'         => 'waiting',
        ]);

        if ($createResult['success'] ?? false) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Invoice logged successfully.'];
            redirect('/billing/view.php?id=' . $createResult['id']);
        } else {
            $errors[] = $createResult['message'] ?? 'Failed to create invoice.';
        }
    }
}

$priorities    = ['normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent', 'low' => 'Low'];
$intakeMethods = [
    'email'          => 'Email',
    'mail'           => 'Mail',
    'portal'         => 'Vendor Portal',
    'fax'            => 'Fax',
    'hand_delivered' => 'Hand Delivered',
    'other'          => 'Other',
];

$pageTitle = 'Log Invoice';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 fw-bold">
            <i class="bi bi-receipt me-2 text-primary"></i>Log Invoice
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="/billing/index.php">Billing Queue</a></li>
                <li class="breadcrumb-item active">Log Invoice</li>
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

<form method="POST" action="" enctype="multipart/form-data">
<div class="row g-4">

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-info-circle me-2 text-primary"></i>Invoice Information</h5>
            </div>
            <div class="card-body">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="vendor_id" class="form-label fw-semibold">Vendor <span class="text-danger">*</span></label>
                        <select class="form-select" id="vendor_id" name="vendor_id" required>
                            <option value="">— Select Vendor —</option>
                            <?php foreach ($vendors as $v): ?>
                                <option value="<?= (int)$v['id'] ?>" <?= (int)$formData['vendor_id'] === (int)$v['id'] ? 'selected' : '' ?>>
                                    <?= h($v['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="media_buy_id" class="form-label fw-semibold">Linked Media Buy <span class="text-muted fw-normal">(optional)</span></label>
                        <select class="form-select" id="media_buy_id" name="media_buy_id">
                            <option value="">— None —</option>
                            <?php foreach ($mediaBuys as $mb): ?>
                                <option value="<?= (int)$mb['id'] ?>" <?= (int)$formData['media_buy_id'] === (int)$mb['id'] ? 'selected' : '' ?>>
                                    <?= h($mb['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="invoice_number" class="form-label fw-semibold">Invoice Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="invoice_number" name="invoice_number"
                               value="<?= h($formData['invoice_number']) ?>" required
                               placeholder="e.g. INV-2024-00123">
                    </div>
                    <div class="col-md-3">
                        <label for="invoice_date" class="form-label fw-semibold">Invoice Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="invoice_date" name="invoice_date"
                               value="<?= h($formData['invoice_date']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="due_date" class="form-label fw-semibold">Due Date</label>
                        <input type="date" class="form-control" id="due_date" name="due_date"
                               value="<?= h($formData['due_date']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="amount" class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="amount" name="amount"
                                   value="<?= h($formData['amount']) ?>" min="0" step="0.01" required
                                   placeholder="0.00">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="priority" class="form-label fw-semibold">Priority</label>
                        <select class="form-select" id="priority" name="priority">
                            <?php foreach ($priorities as $pv => $pl): ?>
                                <option value="<?= h($pv) ?>" <?= $formData['priority'] === $pv ? 'selected' : '' ?>>
                                    <?= h($pl) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="intake_method" class="form-label fw-semibold">Intake Method</label>
                        <select class="form-select" id="intake_method" name="intake_method">
                            <?php foreach ($intakeMethods as $iv => $il): ?>
                                <option value="<?= h($iv) ?>" <?= $formData['intake_method'] === $iv ? 'selected' : '' ?>>
                                    <?= h($il) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="notes" class="form-label fw-semibold">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"
                                  placeholder="Payment instructions, PO number, special handling…"><?= h($formData['notes']) ?></textarea>
                    </div>
                </div>

            </div>
        </div>

        <!-- File Upload -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-paperclip me-2 text-primary"></i>Invoice File</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="invoice_file" class="form-label fw-semibold">Upload Invoice PDF or Image</label>
                    <input type="file" class="form-control" id="invoice_file" name="invoice_file"
                           accept=".pdf,.jpg,.jpeg,.png,.gif,.webp">
                    <div class="form-text">Accepted: PDF, JPG, PNG, GIF, WEBP. Max 10 MB.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm sticky-top" style="top:1rem;">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-floppy me-2 text-primary"></i>Save Invoice</h5>
            </div>
            <div class="card-body">
                <div class="mb-3 p-3 bg-light rounded">
                    <div class="text-muted small mb-1">Status after saving</div>
                    <span class="badge bg-secondary fs-6">Waiting</span>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-save me-2"></i>Log Invoice
                    </button>
                </div>
            </div>
            <div class="card-footer bg-white text-center">
                <a href="/billing/index.php" class="text-muted small">
                    <i class="bi bi-x-circle me-1"></i>Cancel
                </a>
            </div>
        </div>
    </div>

</div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
