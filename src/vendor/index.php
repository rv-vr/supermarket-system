<?php
require_once __DIR__ . '/../classes/UserRole.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/PurchaseOrderManager.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user']) || !($_SESSION['user'] instanceof User)) {
    header('Location: ../../login.php');
    exit();
}

$user = $_SESSION['user'];

if ($user->getRole() !== UserRole::Vendor) {
    $_SESSION['feedback_message'] = 'Access Denied. Vendor account required.';
    $_SESSION['feedback_type'] = 'danger';
    header('Location: ../../login.php');
    exit();
}

$loggedInVendorName = $user->getAssociatedVendorName();
if (empty($loggedInVendorName)) {
    // This case should ideally be prevented by admin during user creation for vendors
    $_SESSION['feedback_message'] = 'Vendor account not fully configured (missing associated vendor name). Please contact support.';
    $_SESSION['feedback_type'] = 'danger';
    header('Location: ../../login.php');
    exit();
}

$poManager = new PurchaseOrderManager();
$feedbackMessage = '';
$feedbackType = '';

// Handle "Mark as Shipped" action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_shipped') {
    $poIdToShip = $_POST['po_id'] ?? null;
    $deliveryReference = trim($_POST['delivery_reference'] ?? '');
    $transitNotes = trim($_POST['transit_notes'] ?? '');

    if (empty($poIdToShip)) {
        $feedbackMessage = "Error: Purchase Order ID missing.";
        $feedbackType = "danger";
    } elseif (empty($deliveryReference)) {
        $feedbackMessage = "Delivery Reference is required to mark as shipped.";
        $feedbackType = "warning";
    } else {
        $poDetails = $poManager->getPurchaseOrderById($poIdToShip);
        // Security check: Ensure the PO belongs to this vendor and is in the correct status
        if ($poDetails && $poDetails['vendor_name'] === $loggedInVendorName && $poDetails['status'] === PurchaseOrderManager::STATUS_SENT_TO_VENDOR) {
            $historyActionDetails = [
                'delivery_reference' => $deliveryReference
            ];
            if (!empty($transitNotes)) {
                $historyActionDetails['transit_notes'] = $transitNotes;
            }

            if ($poManager->updatePurchaseOrderStatus(
                $poIdToShip,
                PurchaseOrderManager::STATUS_SHIPPED,
                $user->getUsername(), // User performing the action
                "Marked as Shipped by vendor.", // General note for the action
                $historyActionDetails // Specific details for history log
            )) {
                $_SESSION['feedback_message'] = "Purchase Order #{$poIdToShip} successfully marked as Shipped.";
                $_SESSION['feedback_type'] = "success";
            } else {
                $_SESSION['feedback_message'] = "Failed to update Purchase Order #{$poIdToShip}. Please try again.";
                $_SESSION['feedback_type'] = "danger";
            }
        } elseif (!$poDetails) {
            $_SESSION['feedback_message'] = "Purchase Order #{$poIdToShip} not found.";
            $_SESSION['feedback_type'] = "danger";
        } elseif ($poDetails['vendor_name'] !== $loggedInVendorName) {
            $_SESSION['feedback_message'] = "Access denied: You cannot update Purchase Order #{$poIdToShip}.";
            $_SESSION['feedback_type'] = "danger";
        } else { // PO found, belongs to vendor, but wrong status
            $_SESSION['feedback_message'] = "Purchase Order #{$poIdToShip} cannot be marked as shipped (current status: {$poDetails['status']}).";
            $_SESSION['feedback_type'] = "warning";
        }
        // Redirect to prevent form resubmission
        header("Location: index.php");
        exit();
    }
}

// Handle "Cancel PO" action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_po') {
    $poIdToCancel = $_POST['po_id_cancel'] ?? null;
    $cancellationReason = trim($_POST['cancellation_reason'] ?? '');

    if (empty($poIdToCancel)) {
        $_SESSION['feedback_message'] = "Error: Purchase Order ID missing for cancellation.";
        $_SESSION['feedback_type'] = "danger";
    } elseif (empty($cancellationReason)) {
        $_SESSION['feedback_message'] = "Cancellation Reason is required.";
        $_SESSION['feedback_type'] = "warning";
    } else {
        $poDetails = $poManager->getPurchaseOrderById($poIdToCancel);
        // Security check: Ensure the PO belongs to this vendor and is in 'Sent to Vendor' status
        if ($poDetails && $poDetails['vendor_name'] === $loggedInVendorName && $poDetails['status'] === PurchaseOrderManager::STATUS_SENT_TO_VENDOR) {
            $historyActionDetails = [
                'cancellation_reason' => $cancellationReason
            ];

            if ($poManager->updatePurchaseOrderStatus(
                $poIdToCancel,
                PurchaseOrderManager::STATUS_CANCELLED,
                $user->getUsername(), // User performing the action
                "Order cancelled by vendor.", // General note for the action
                $historyActionDetails // Specific details for history log
            )) {
                $_SESSION['feedback_message'] = "Purchase Order #{$poIdToCancel} successfully cancelled.";
                $_SESSION['feedback_type'] = "success";
            } else {
                $_SESSION['feedback_message'] = "Failed to cancel Purchase Order #{$poIdToCancel}. Please try again.";
                $_SESSION['feedback_type'] = "danger";
            }
        } elseif (!$poDetails) {
            $_SESSION['feedback_message'] = "Purchase Order #{$poIdToCancel} not found.";
            $_SESSION['feedback_type'] = "danger";
        } elseif ($poDetails['vendor_name'] !== $loggedInVendorName) {
            $_SESSION['feedback_message'] = "Access denied: You cannot cancel Purchase Order #{$poIdToCancel}.";
            $_SESSION['feedback_type'] = "danger";
        } else { // PO found, belongs to vendor, but wrong status
            $_SESSION['feedback_message'] = "Purchase Order #{$poIdToCancel} cannot be cancelled (current status: {$poDetails['status']}).";
            $_SESSION['feedback_type'] = "warning";
        }
    }
    // Redirect to prevent form resubmission
    header("Location: index.php");
    exit();
}


// Fetch POs relevant to this vendor
$allPOs = $poManager->getAllPurchaseOrders();
$vendorPOs = [];
foreach ($allPOs as $po) {
    if (isset($po['vendor_name']) && $po['vendor_name'] === $loggedInVendorName) {
        // Vendors typically see orders sent to them or in later stages
        if (!in_array($po['status'], [PurchaseOrderManager::STATUS_DRAFT])) {
             $vendorPOs[] = $po;
        }
    }
}

// Feedback from redirect or other actions
if (isset($_SESSION['feedback_message'])) {
    $feedbackMessage = $_SESSION['feedback_message'];
    $feedbackType = $_SESSION['feedback_type'];
    unset($_SESSION['feedback_message']);
    unset($_SESSION['feedback_type']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Portal - <?php echo htmlspecialchars($loggedInVendorName); ?></title>
    <link rel="stylesheet" href="../../public/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .table th, .table td { vertical-align: middle; }
        .modal-body .form-label { font-weight: 500; }
        .items-summary-list { font-size: 0.85em; padding-left: 1.2em; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center my-4">
            <h1>Vendor Portal</h1>
            <h4 class="text-muted"><?php echo htmlspecialchars($loggedInVendorName); ?></h4>
        </div>

        <?php if ($feedbackMessage): ?>
            <div class="alert alert-<?php echo $feedbackType; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($feedbackMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h4><span class="material-symbols-outlined">list_alt</span> Your Purchase Orders</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($vendorPOs)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>PO ID</th>
                                    <th>Order Date</th>
                                    <th>Status</th>
                                    <th>Items Summary</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vendorPOs as $po): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($po['po_id']); ?></td>
                                        <td><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($po['order_date']))); ?></td>
                                        <td>
                                            <span class="badge bg-<?php
                                                switch ($po['status']) {
                                                    case PurchaseOrderManager::STATUS_SENT_TO_VENDOR: echo 'primary'; break;
                                                    case PurchaseOrderManager::STATUS_SHIPPED: echo 'info text-dark'; break;
                                                    case PurchaseOrderManager::STATUS_PARTIALLY_RECEIVED: echo 'warning text-dark'; break;
                                                    case PurchaseOrderManager::STATUS_RECEIVED: echo 'success'; break;
                                                    case PurchaseOrderManager::STATUS_CANCELLED: echo 'danger'; break;
                                                    default: echo 'secondary';
                                                }
                                            ?>">
                                                <?php echo htmlspecialchars($po['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <ul class="list-unstyled mb-0 items-summary-list">
                                            <?php 
                                                $itemCount = 0;
                                                $maxItemsToShow = 3; // Show first N items then 'and X more'
                                                foreach ($po['items'] as $item): 
                                                    if ($itemCount < $maxItemsToShow):
                                            ?>
                                                <li><?php echo htmlspecialchars($item['product_name'] . ' (Qty: ' . $item['quantity_ordered'] . ')'); ?></li>
                                            <?php 
                                                    endif;
                                                    $itemCount++;
                                                endforeach; 
                                                if ($itemCount > $maxItemsToShow):
                                            ?>
                                                <li>...and <?php echo ($itemCount - $maxItemsToShow); ?> more item(s).</li>
                                            <?php endif; ?>
                                            </ul>
                                        </td>
                                        <td>
                                            <?php if ($po['status'] === PurchaseOrderManager::STATUS_SENT_TO_VENDOR): ?>
                                                <button type="button" class="btn btn-sm btn-warning me-1" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#shipOrderModal"
                                                        data-po-id="<?php echo htmlspecialchars($po['po_id']); ?>"
                                                        title="Mark as Shipped">
                                                    <span class="material-symbols-outlined">local_shipping</span> Mark Shipped
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#cancelOrderModal"
                                                        data-po-id-cancel="<?php echo htmlspecialchars($po['po_id']); ?>"
                                                        title="Cancel Order">
                                                    <span class="material-symbols-outlined">cancel</span> Cancel
                                                </button>
                                            <?php elseif (in_array($po['status'], [PurchaseOrderManager::STATUS_SHIPPED, PurchaseOrderManager::STATUS_RECEIVED, PurchaseOrderManager::STATUS_PARTIALLY_RECEIVED, PurchaseOrderManager::STATUS_CANCELLED])): ?>
                                                 <a href="../inventory/view_po_receipt.php?po_id=<?php echo htmlspecialchars($po['po_id']); ?>" class="btn btn-sm btn-outline-secondary" title="View Details">
                                                    <span class="material-symbols-outlined">visibility</span> View
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No purchase orders found for your account at this time.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Mark as Shipped Modal -->
    <div class="modal fade" id="shipOrderModal" tabindex="-1" aria-labelledby="shipOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="index.php" method="POST">
                    <input type="hidden" name="action" value="mark_shipped">
                    <input type="hidden" name="po_id" id="modal_po_id" value="">
                    <div class="modal-header">
                        <h5 class="modal-title" id="shipOrderModalLabel">Mark Purchase Order as Shipped</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>You are about to mark PO #<strong id="modal_po_id_display"></strong> as shipped.</p>
                        <div class="mb-3">
                            <label for="delivery_reference" class="form-label">Delivery Reference / Waybill No.: <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="delivery_reference" name="delivery_reference" required>
                        </div>
                        <div class="mb-3">
                            <label for="transit_notes" class="form-label">Transit Notes (Optional):</label>
                            <textarea class="form-control" id="transit_notes" name="transit_notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Confirm Shipment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Cancel Order Modal -->
    <div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-labelledby="cancelOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="index.php" method="POST">
                    <input type="hidden" name="action" value="cancel_po">
                    <input type="hidden" name="po_id_cancel" id="modal_po_id_cancel" value="">
                    <div class="modal-header">
                        <h5 class="modal-title" id="cancelOrderModalLabel">Cancel Purchase Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>You are about to cancel PO #<strong id="modal_po_id_cancel_display"></strong>.</p>
                        <div class="mb-3">
                            <label for="cancellation_reason" class="form-label">Reason for Cancellation: <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="cancellation_reason" name="cancellation_reason" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Order</button>
                        <button type="submit" class="btn btn-danger">Confirm Cancellation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // JavaScript to set PO ID in the ship modal
        var shipOrderModal = document.getElementById('shipOrderModal');
        if (shipOrderModal) {
            shipOrderModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget; 
                var poId = button.getAttribute('data-po-id'); 
                
                var modalPoIdInput = shipOrderModal.querySelector('#modal_po_id');
                var modalPoIdDisplay = shipOrderModal.querySelector('#modal_po_id_display');
                
                modalPoIdInput.value = poId;
                modalPoIdDisplay.textContent = poId;
            });
        }

        // JavaScript to set PO ID in the cancel modal
        var cancelOrderModal = document.getElementById('cancelOrderModal');
        if (cancelOrderModal) {
            cancelOrderModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget; // Button that triggered the modal
                var poId = button.getAttribute('data-po-id-cancel'); // Extract info from data-* attributes
                
                var modalPoIdCancelInput = cancelOrderModal.querySelector('#modal_po_id_cancel');
                var modalPoIdCancelDisplay = cancelOrderModal.querySelector('#modal_po_id_cancel_display');
                
                modalPoIdCancelInput.value = poId;
                modalPoIdCancelDisplay.textContent = poId;
            });
        }
    </script>
</body>
</html>