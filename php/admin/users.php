<?php
require_once __DIR__ . '/../bootstrap.php';
requireRole('admin');

$authService = new AuthService();
$errors = [];
$editUser = null;

// Handle POST — add or edit user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id        = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $name      = trim($_POST['name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $role      = $_POST['role'] ?? 'buyer';
    $phone     = trim($_POST['phone'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') {
        $errors[] = 'Full name is required.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
    if ($id === null && strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters for new users.';
    }
    if (!in_array($role, ['admin', 'buyer'], true)) {
        $errors[] = 'Invalid role selected.';
    }

    if (empty($errors)) {
        $data = [
            'name'      => $name,
            'email'     => $email,
            'role'      => $role,
            'phone'     => $phone,
            'is_active' => $is_active,
        ];
        if ($id !== null) {
            $data['id'] = $id;
        }
        if ($password !== '') {
            $data['password'] = $password;
        }

        $authService->saveUser($data);
        $_SESSION['flash'] = ['type' => 'success', 'message' => $id ? 'User updated successfully.' : 'User created successfully.'];
        redirect('/admin/users.php');
    }

    // Preserve posted data for re-display in modal
    $editUser = [
        'id'        => $id,
        'name'      => $name,
        'email'     => $email,
        'role'      => $role,
        'phone'     => $phone,
        'is_active' => $is_active,
    ];
}

$users = $authService->getUsers()['data'] ?? [];

$pageTitle = 'User Management — MediaBuy';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="bi bi-people-fill me-2 text-primary"></i>User Management</h2>
        <p class="text-muted mb-0 small">Manage platform users and their access roles.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal"
            onclick="resetUserForm()">
        <i class="bi bi-person-plus-fill me-1"></i>Add User
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

<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <span class="fw-semibold text-secondary">
            <i class="bi bi-list-ul me-1"></i><?= count($users) ?> user<?= count($users) !== 1 ? 's' : '' ?>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Name</th>
                        <th scope="col">Email</th>
                        <th scope="col">Role</th>
                        <th scope="col">Phone</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-4 d-block mb-2"></i>No users found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $i => $user): ?>
                            <tr>
                                <td class="text-muted small"><?= (int)($i + 1) ?></td>
                                <td>
                                    <i class="bi bi-person-circle me-2 text-secondary"></i>
                                    <strong><?= h($user['name']) ?></strong>
                                </td>
                                <td><?= h($user['email']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : 'primary' ?>">
                                        <?= h(ucfirst($user['role'])) ?>
                                    </span>
                                </td>
                                <td><?= h($user['phone'] ?? '—') ?></td>
                                <td class="text-center">
                                    <?php if (!empty($user['is_active'])): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="bi bi-x-circle me-1"></i>Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="modal" data-bs-target="#userModal"
                                            onclick="editUser(<?= (int)$user['id'] ?>, <?= htmlspecialchars(json_encode($user), ENT_QUOTES, 'UTF-8') ?>)">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add / Edit User Modal -->
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow">
            <form method="post" action="/admin/users.php" novalidate id="userForm">
                <input type="hidden" name="id" id="userId">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="userModalLabel">
                        <i class="bi bi-person-plus-fill me-2"></i><span id="userModalTitleText">Add New User</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label for="userName" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" id="userName" name="name" class="form-control"
                                   placeholder="Jane Smith" required
                                   value="<?= h($editUser['name'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="userEmail" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" id="userEmail" name="email" class="form-control"
                                   placeholder="jane@example.com" required
                                   value="<?= h($editUser['email'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="userPassword" class="form-label fw-semibold">
                                Password <span class="text-danger" id="passwordRequired">*</span>
                            </label>
                            <input type="password" id="userPassword" name="password" class="form-control"
                                   placeholder="Min. 8 characters" autocomplete="new-password">
                            <div class="form-text" id="passwordHint">Leave blank to keep the existing password when editing.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="userRole" class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                            <select id="userRole" name="role" class="form-select" required>
                                <option value="buyer" <?= ($editUser['role'] ?? '') === 'buyer' ? 'selected' : '' ?>>Buyer</option>
                                <option value="admin" <?= ($editUser['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="userPhone" class="form-label fw-semibold">Phone</label>
                            <input type="tel" id="userPhone" name="phone" class="form-control"
                                   placeholder="+1 (555) 000-0000"
                                   value="<?= h($editUser['phone'] ?? '') ?>">
                        </div>

                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check form-switch fs-5">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       id="userActive" name="is_active" value="1"
                                       <?= !empty($editUser['is_active']) || $editUser === null ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold fs-6" for="userActive">Active Account</label>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetUserForm() {
    document.getElementById('userId').value = '';
    document.getElementById('userForm').reset();
    document.getElementById('userModalTitleText').textContent = 'Add New User';
    document.getElementById('passwordHint').style.display = 'none';
    document.getElementById('passwordRequired').style.display = 'inline';
    document.getElementById('userActive').checked = true;
}

function editUser(id, userData) {
    document.getElementById('userId').value = id;
    document.getElementById('userName').value = userData.name || '';
    document.getElementById('userEmail').value = userData.email || '';
    document.getElementById('userPassword').value = '';
    document.getElementById('userRole').value = userData.role || 'buyer';
    document.getElementById('userPhone').value = userData.phone || '';
    document.getElementById('userActive').checked = userData.is_active == 1;
    document.getElementById('userModalTitleText').textContent = 'Edit User: ' + (userData.name || '');
    document.getElementById('passwordHint').style.display = '';
    document.getElementById('passwordRequired').style.display = 'none';
}

<?php if (!empty($errors) && $editUser !== null): ?>
// Re-open modal if there were validation errors
document.addEventListener('DOMContentLoaded', function () {
    var modal = new bootstrap.Modal(document.getElementById('userModal'));
    <?php if (!empty($editUser['id'])): ?>
    editUser(<?= (int)$editUser['id'] ?>, <?= json_encode($editUser) ?>);
    <?php else: ?>
    resetUserForm();
    <?php endif; ?>
    modal.show();
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
