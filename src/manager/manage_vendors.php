<?php
require_once __DIR__ . '/../classes/UserRole.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/PurchaseOrderManager.php'; // Contains vendor methods

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

$poManager = new PurchaseOrderManager(); // Used for vendor operations

$feedbackMessage = $_SESSION['feedback_message'] ?? null;
$feedbackType = $_SESSION['feedback_type'] ?? null;
if ($feedbackMessage) {
    unset($_SESSION['feedback_message']);
    unset($_SESSION['feedback_type']);
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$vendorToEdit = null;

// Handle Add Vendor
if ($action === 'add_vendor' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $contactPerson = trim($_POST['contact_person'] ?? '');
    $contactEmail = trim($_POST['contact_email'] ?? '');
    $contactPhone = trim($_POST['contact_phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $providedSkusStr = trim($_POST['provided_skus'] ?? '');
    $providedSkus = !empty($providedSkusStr) ? array_map('trim', explode(',', $providedSkusStr)) : [];

    $result = $poManager->addVendor($name, $contactPerson, $contactEmail, $contactPhone, $address, $providedSkus);
    $_SESSION['feedback_message'] = $result['message'];
    $_SESSION['feedback_type'] = $result['success'] ? 'success' : 'danger';
    header('Location: manage_vendors.php');
    exit();
}

// Handle Update Vendor
if ($action === 'update_vendor' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $originalName = trim($_POST['original_name'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $contactPerson = trim($_POST['contact_person'] ?? '');
    $contactEmail = trim($_POST['contact_email'] ?? '');
    $contactPhone = trim($_POST['contact_phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $providedSkusStr = trim($_POST['provided_skus'] ?? '');
    $providedSkus = !empty($providedSkusStr) ? array_map('trim', explode(',', $providedSkusStr)) : [];

    $result = $poManager->updateVendor($originalName, $name, $contactPerson, $contactEmail, $contactPhone, $address, $providedSkus);
    $_SESSION['feedback_message'] = $result['message'];
    $_SESSION['feedback_type'] = $result['success'] ? 'success' : 'danger';
    header('Location: manage_vendors.php');
    exit();
}

// Handle Delete Vendor
if ($action === 'delete_vendor' && isset($_POST['name_delete'])) {
    $nameToDelete = trim($_POST['name_delete']);
    $result = $poManager->deleteVendor($nameToDelete);
    $_SESSION['feedback_message'] = $result['message'];
    $_SESSION['feedback_type'] = $result['success'] ? 'success' : 'danger';
    header('Location: manage_vendors.php');
    exit();
}

// Load vendor data if editing
if ($action === 'edit' && isset($_GET['name'])) {
    $vendorToEdit = $poManager->getVendorByName(trim($_GET['name']));
    if (!$vendorToEdit) {
        $_SESSION['feedback_message'] = 'Vendor not found for editing.';
        $_SESSION['feedback_type'] = 'warning';
        header('Location: manage_vendors.php');
        exit();
    }
}

$allVendors = $poManager->getVendors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Vendors</title>
    <link rel="stylesheet" href="../../public/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .form-section { margin-bottom: 2rem; padding: 1.5rem; border: 1px solid #ddd; border-radius: 0.5rem; background-color: #f9f9f9;}
        .table th, .table td { vertical-align: middle; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center my-4">
            <h1><span class="material-symbols-outlined" style="font-size: 1.2em; vertical-align: bottom;">store</span> Manage Vendors</h1>
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

        <!-- Add/Edit Vendor Form Section -->
        <div class="form-section">
            <h4><?php echo $vendorToEdit ? 'Edit Vendor: ' . htmlspecialchars($vendorToEdit['name']) : 'Add New Vendor'; ?></h4>
            <form action="manage_vendors.php" method="POST">
                <input type="hidden" name="action" value="<?php echo $vendorToEdit ? 'update_vendor' : 'add_vendor'; ?>">
                <?php if ($vendorToEdit): ?>
                    <input type="hidden" name="original_name" value="<?php echo htmlspecialchars($vendorToEdit['name']); ?>">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Vendor Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($vendorToEdit['name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="contact_person" class="form-label">Contact Person</label>
                        <input type="text" class="form-control" id="contact_person" name="contact_person" value="<?php echo htmlspecialchars($vendorToEdit['contact_person'] ?? ''); ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="contact_email" class="form-label">Contact Email</label>
                        <input type="email" class="form-control" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars($vendorToEdit['contact_email'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="contact_phone" class="form-label">Contact Phone</label>
                        <input type="tel" class="form-control" id="contact_phone" name="contact_phone" value="<?php echo htmlspecialchars($vendorToEdit['contact_phone'] ?? ''); ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label for="address" class="form-label">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($vendorToEdit['address'] ?? ''); ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="provided_skus" class="form-label">Provided SKUs (comma-separated)</label>
                    <input type="text" class="form-control" id="provided_skus" name="provided_skus" value="<?php echo htmlspecialchars(implode(', ', $vendorToEdit['provided_skus'] ?? [])); ?>" placeholder="e.g., SKU001, SKU002, SKU003">
                    <small class="form-text text-muted">This helps in associating products with vendors for Purchase Orders.</small>
                </div>
                <button type="submit" class="btn btn-primary">
                    <span class="material-symbols-outlined"><?php echo $vendorToEdit ? 'save' : 'add'; ?></span> <?php echo $vendorToEdit ? 'Update Vendor' : 'Add Vendor'; ?>
                </button>
                <?php if ($vendorToEdit): ?>
                    <a href="manage_vendors.php" class="btn btn-secondary">Cancel Edit</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Vendor List Table -->
        <div class="card mt-4">
            <div class="card-header">
                <h4><span class="material-symbols-outlined">list</span> All Vendors</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($allVendors)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Contact Person</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Address</th>
                                    <th>SKUs Provided (Count)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allVendors as $v): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($v['name']); ?></td>
                                        <td><?php echo htmlspecialchars($v['contact_person'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($v['contact_email'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($v['contact_phone'] ?? 'N/A'); ?></td>
                                        <td><?php echo nl2br(htmlspecialchars($v['address'] ?? 'N/A')); ?></td>
                                        <td><?php echo count($v['provided_skus'] ?? []); ?></td>
                                        <td>
                                            <a href="manage_vendors.php?action=edit&name=<?php echo urlencode($v['name']); ?>" class="btn btn-sm btn-warning" title="Edit Vendor">
                                                <span class="material-symbols-outlined">edit</span>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" title="Delete Vendor"
                                                    data-bs-toggle="modal" data-bs-target="#deleteVendorModal"
                                                    data-vendor-name-delete="<?php echo htmlspecialchars($v['name']); ?>">
                                                <span class="material-symbols-outlined">delete</span>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No vendors found. Add one using the form above.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Delete Vendor Modal -->
    <div class="modal fade" id="deleteVendorModal" tabindex="-1" aria-labelledby="deleteVendorModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="manage_vendors.php" method="POST">
                    <input type="hidden" name="action" value="delete_vendor">
                    <input type="hidden" name="name_delete" id="modal_vendor_name_delete">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteVendorModalLabel">Confirm Delete Vendor</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to delete vendor <strong id="modal_vendor_name_delete_display"></strong>? This action cannot be undone and might fail if the vendor has existing Purchase Orders.
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Vendor</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        var deleteVendorModal = document.getElementById('deleteVendorModal');
        if (deleteVendorModal) {
            deleteVendorModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var vendorName = button.getAttribute('data-vendor-name-delete');
                var modalVendorNameInput = deleteVendorModal.querySelector('#modal_vendor_name_delete');
                var modalVendorNameDisplay = deleteVendorModal.querySelector('#modal_vendor_name_delete_display');
                modalVendorNameInput.value = vendorName;
                modalVendorNameDisplay.textContent = vendorName;
            });
        }
    </script>
</body>
</html>