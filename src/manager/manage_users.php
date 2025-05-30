<?php
require_once __DIR__ . '/../classes/UserRole.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/UserManager.php';
require_once __DIR__ . '/../classes/PurchaseOrderManager.php'; // For vendor list

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user']) || !($_SESSION['user'] instanceof User) || $_SESSION['user']->getRole() !== UserRole::Manager) {
    $_SESSION['feedback_message'] = 'Access Denied. Manager account required.';
    $_SESSION['feedback_type'] = 'danger';
    header('Location: ../../login.php');
    exit();
}
$currentManager = $_SESSION['user'];
$userManager = new UserManager();
$poManager = new PurchaseOrderManager(); // To get vendor names for dropdown

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$feedbackMessage = $_SESSION['feedback_message'] ?? null;
$feedbackType = $_SESSION['feedback_type'] ?? null;

if ($feedbackMessage) {
    unset($_SESSION['feedback_message']);
    unset($_SESSION['feedback_type']);
}

// Handle Create User
if ($action === 'create_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? ''; // No trim for password
    $fullName = trim($_POST['fullName'] ?? '');
    $role = $_POST['role'] ?? '';
    $associatedVendor = ($role === UserRole::Vendor->value) ? trim($_POST['associated_vendor_name'] ?? '') : null;

    $result = $userManager->createUser($username, $password, $fullName, $role, $associatedVendor);
    $_SESSION['feedback_message'] = $result['message'];
    $_SESSION['feedback_type'] = $result['success'] ? 'success' : 'danger';
    header('Location: manage_users.php');
    exit();
}

// Handle Update User
if ($action === 'update_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $originalUsername = trim($_POST['original_username'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['fullName'] ?? '');
    $role = $_POST['role'] ?? '';
    $newPassword = $_POST['password'] ?? ''; // Optional
    $associatedVendor = ($role === UserRole::Vendor->value) ? trim($_POST['associated_vendor_name'] ?? '') : null;

    $result = $userManager->updateUser($originalUsername, $username, $fullName, $role, $newPassword ?: null, $associatedVendor);
    $_SESSION['feedback_message'] = $result['message'];
    $_SESSION['feedback_type'] = $result['success'] ? 'success' : 'danger';
    header('Location: manage_users.php');
    exit();
}

// Handle Delete User
if ($action === 'delete_user' && isset($_POST['username_delete'])) {
    $usernameToDelete = trim($_POST['username_delete']);
    $result = $userManager->deleteUser($usernameToDelete, $currentManager->getUsername());
    $_SESSION['feedback_message'] = $result['message'];
    $_SESSION['feedback_type'] = $result['success'] ? 'success' : 'danger';
    header('Location: manage_users.php');
    exit();
}


$allUsers = $userManager->getAllUsers();
$allUserRoles = UserRole::cases(); // Get all enum cases for roles
$allVendors = $poManager->getVendors(); // Get all vendor names

// Load user data if editing
$userToEdit = null;
if ($action === 'edit' && isset($_GET['username'])) {
    $userToEdit = $userManager->findUserByUsername(trim($_GET['username']));
    if (!$userToEdit) { // If user not found, redirect to avoid errors
        $_SESSION['feedback_message'] = 'User not found for editing.';
        $_SESSION['feedback_type'] = 'warning';
        header('Location: manage_users.php');
        exit();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="../../public/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .table th, .table td { vertical-align: middle; }
        .form-section { margin-bottom: 2rem; padding: 1.5rem; border: 1px solid #ddd; border-radius: 0.5rem; background-color: #f9f9f9;}
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center my-4">
            <h1>Manage Users</h1>
            <a href="index.php" class="btn btn-outline-secondary">
                <span class="material-symbols-outlined">arrow_back</span> Back to Dashboard
            </a>
        </div>

        <?php if ($feedbackMessage): ?>
            <div class="alert alert-<?php echo htmlspecialchars($feedbackType); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($feedbackMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Create or Edit User Form Section -->
        <div class="form-section">
            <h4><?php echo $userToEdit ? 'Edit User: ' . htmlspecialchars($userToEdit['username']) : 'Create New User'; ?></h4>
            <form action="manage_users.php" method="POST">
                <input type="hidden" name="action" value="<?php echo $userToEdit ? 'update_user' : 'create_user'; ?>">
                <?php if ($userToEdit): ?>
                    <input type="hidden" name="original_username" value="<?php echo htmlspecialchars($userToEdit['username']); ?>">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($userToEdit['username'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">Password <?php echo $userToEdit ? '(Leave blank to keep current)' : '<span class="text-danger">*</span>'; ?></label>
                        <input type="password" class="form-control" id="password" name="password" <?php echo !$userToEdit ? 'required' : ''; ?>>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="fullName" class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="fullName" name="fullName" value="<?php echo htmlspecialchars($userToEdit['fullName'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="">Select a role...</option>
                            <?php foreach ($allUserRoles as $roleEnum): ?>
                                <option value="<?php echo $roleEnum->value; ?>" <?php echo (isset($userToEdit['role']) && $userToEdit['role'] === $roleEnum->value) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($roleEnum->value); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3" id="associatedVendorSection" style="<?php echo (isset($userToEdit['role']) && $userToEdit['role'] === UserRole::Vendor->value) ? '' : 'display:none;'; ?>">
                    <label for="associated_vendor_name" class="form-label">Associated Vendor <span class="text-danger">*</span></label>
                    <select class="form-select" id="associated_vendor_name" name="associated_vendor_name">
                        <option value="">Select a vendor...</option>
                        <?php foreach ($allVendors as $vendor): ?>
                             <option value="<?php echo htmlspecialchars($vendor['name']); ?>" <?php echo (isset($userToEdit['associated_vendor_name']) && $userToEdit['associated_vendor_name'] === $vendor['name']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($vendor['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">
                    <span class="material-symbols-outlined"><?php echo $userToEdit ? 'save' : 'add'; ?></span> <?php echo $userToEdit ? 'Update User' : 'Create User'; ?>
                </button>
                <?php if ($userToEdit): ?>
                    <a href="manage_users.php" class="btn btn-secondary">Cancel Edit</a>
                <?php endif; ?>
            </form>
        </div>


        <!-- User List Table -->
        <div class="card mt-4">
            <div class="card-header">
                <h4><span class="material-symbols-outlined">list</span> All Users</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($allUsers)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Full Name</th>
                                    <th>Role</th>
                                    <th>Associated Vendor</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allUsers as $u): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($u['username']); ?></td>
                                        <td><?php echo htmlspecialchars($u['fullName'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($u['role']); ?></td>
                                        <td><?php echo htmlspecialchars($u['associated_vendor_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <a href="manage_users.php?action=edit&username=<?php echo urlencode($u['username']); ?>" class="btn btn-sm btn-warning" title="Edit User">
                                                <span class="material-symbols-outlined">edit</span>
                                            </a>
                                            <?php if (strtolower($currentManager->getUsername()) !== strtolower($u['username'])): // Prevent deleting self ?>
                                            <button type="button" class="btn btn-sm btn-danger" title="Delete User"
                                                    data-bs-toggle="modal" data-bs-target="#deleteUserModal"
                                                    data-username-delete="<?php echo htmlspecialchars($u['username']); ?>">
                                                <span class="material-symbols-outlined">delete</span>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No users found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="manage_users.php" method="POST">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="username_delete" id="modal_username_delete">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteUserModalLabel">Confirm Delete User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to delete user <strong id="modal_username_delete_display"></strong>? This action cannot be undone.
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show/hide Associated Vendor dropdown based on selected role
        const roleSelect = document.getElementById('role');
        const vendorSection = document.getElementById('associatedVendorSection');
        const vendorSelect = document.getElementById('associated_vendor_name');

        if (roleSelect) {
            roleSelect.addEventListener('change', function() {
                if (this.value === '<?php echo UserRole::Vendor->value; ?>') {
                    vendorSection.style.display = 'block';
                    vendorSelect.required = true;
                } else {
                    vendorSection.style.display = 'none';
                    vendorSelect.required = false;
                    vendorSelect.value = ''; // Clear selection
                }
            });
            // Trigger change on page load if editing a vendor to set initial state
            if (roleSelect.value === '<?php echo UserRole::Vendor->value; ?>') {
                 vendorSection.style.display = 'block';
                 vendorSelect.required = true;
            } else {
                 vendorSection.style.display = 'none';
                 vendorSelect.required = false;
            }
        }

        // Populate username in delete confirmation modal
        var deleteUserModal = document.getElementById('deleteUserModal');
        if (deleteUserModal) {
            deleteUserModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var username = button.getAttribute('data-username-delete');
                var modalUsernameInput = deleteUserModal.querySelector('#modal_username_delete');
                var modalUsernameDisplay = deleteUserModal.querySelector('#modal_username_delete_display');
                modalUsernameInput.value = username;
                modalUsernameDisplay.textContent = username;
            });
        }
    </script>
</body>
</html>