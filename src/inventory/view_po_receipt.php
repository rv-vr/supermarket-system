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
$userRole = $user->getRole();

// Allow Stockers, Managers, and Vendors to view receipts
if (!in_array($userRole, [UserRole::Stocker, UserRole::Manager, UserRole::Vendor])) {
    $_SESSION['feedback_message'] = 'Access Denied. You do not have permission to view receipts.';
    $_SESSION['feedback_type'] = 'danger';
    // Redirect based on role or to a generic access denied page
    if ($userRole === UserRole::Vendor) {
        header('Location: ../vendor/index.php');
    } else {
        header('Location: index.php');
    }
    exit();
}

$poManager = new PurchaseOrderManager();
$poDetails = null;
$poId = $_GET['po_id'] ?? null;

if (!$poId) {
    $_SESSION['feedback_message'] = 'No Purchase Order ID provided for receipt.';
    $_SESSION['feedback_type'] = 'danger';
    if ($userRole === UserRole::Vendor) {
        header('Location: ../vendor/index.php');
    } else {
        header('Location: index.php');
    }
    exit();
}

$poDetails = $poManager->getPurchaseOrderById($poId);

if (!$poDetails) {
    $_SESSION['feedback_message'] = "Purchase Order #{$poId} not found.";
    $_SESSION['feedback_type'] = 'danger';
    if ($userRole === UserRole::Vendor) {
        header('Location: ../vendor/index.php');
    } else {
        header('Location: index.php');
    }
    exit();
}

// If the user is a Vendor, ensure the PO belongs to them
if ($userRole === UserRole::Vendor) {
    $loggedInVendorName = $user->getAssociatedVendorName();
    if (empty($loggedInVendorName) || $poDetails['vendor_name'] !== $loggedInVendorName) {
        $_SESSION['feedback_message'] = 'Access Denied. You can only view Purchase Orders associated with your vendor account.';
        $_SESSION['feedback_type'] = 'danger';
        header('Location: ../vendor/index.php');
        exit();
    }
}


// It's good practice to ensure the PO is in a state where a receipt makes sense,
// e.g., Received, Partially Received, or even Cancelled if you want a record.
// For now, we'll allow viewing if the PO exists and passes above checks.

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../public/css/styles.css"> <!-- Your main styles -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Purchase Order Receipt - <?php echo htmlspecialchars($poId); ?></title>
    <style>
        body { 
            background-color: #f8f9fa; /* Light background for the page */
        }
        .receipt-container { 
            max-width: 800px; 
            margin: 30px auto; 
            background-color: #fff; 
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 0 15px rgba(0,0,0,0.1); 
        }
        .receipt-header { 
            text-align: center; 
            margin-bottom: 30px; 
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
        }
        .receipt-header h2 { 
            margin-bottom: 5px; 
            color: #333;
        }
        .receipt-details p { 
            margin-bottom: 0.5rem; 
            font-size: 0.95rem;
        }
        .item-table th, .item-table td { 
            vertical-align: middle; 
            font-size: 0.9rem;
        }
        .item-table thead th {
            background-color: #f8f9fa;
        }
        .history-log { 
            font-size: 0.85em; 
            color: #6c757d; 
        }
        .history-log .list-group-item {
            padding: 0.5rem 1rem;
            border-color: #e9ecef;
        }
        .history-log h6 {
            font-size: 0.9em;
            font-weight: bold;
        }
        .total-summary {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        @media print {
            body { background-color: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .receipt-container { margin: 0; width: 100%; box-shadow: none; border: none; padding: 10px; }
            .no-print { display: none !important; }
            .receipt-header { margin-bottom: 20px; padding-bottom: 10px;}
            .btn { display: none; } /* Hide all buttons on print */
            .table-bordered th, .table-bordered td { border: 1px solid #dee2e6 !important; } /* Ensure borders print */
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="receipt-header">
            <h2>Purchase Order Details</h2>
            <p class="text-muted">PO ID: <strong><?php echo htmlspecialchars($poDetails['po_id']); ?></strong></p>
        </div>

        <div class="row receipt-details mb-4">
            <div class="col-md-6">
                <p><strong>Vendor:</strong> <?php echo htmlspecialchars($poDetails['vendor_name']); ?></p>
                <p><strong>Order Date:</strong> <?php echo htmlspecialchars(date('F j, Y, g:i a', strtotime($poDetails['order_date']))); ?></p>
            </div>
            <div class="col-md-6 text-md-end">
                <p><strong>Requested By:</strong> <?php echo htmlspecialchars($poDetails['requested_by']); ?></p>
                <p><strong>Current Status:</strong> 
                    <span class="badge bg-<?php
                        switch ($poDetails['status']) {
                            case PurchaseOrderManager::STATUS_DRAFT: echo 'secondary'; break;
                            case PurchaseOrderManager::STATUS_SENT_TO_VENDOR: echo 'primary'; break;
                            case PurchaseOrderManager::STATUS_SHIPPED: echo 'info'; break;
                            case PurchaseOrderManager::STATUS_PARTIALLY_RECEIVED: echo 'warning text-dark'; break;
                            case PurchaseOrderManager::STATUS_RECEIVED: echo 'success'; break;
                            case PurchaseOrderManager::STATUS_CANCELLED: echo 'danger'; break;
                            default: echo 'light text-dark';
                        }
                    ?>">
                        <?php echo htmlspecialchars($poDetails['status']); ?>
                    </span>
                </p>
            </div>
        </div>

        <h5 class="mb-3">Order Items:</h5>
        <div class="table-responsive mb-4">
            <table class="table table-bordered item-table">
                <thead>
                    <tr>
                        <th style="width:5%;">#</th>
                        <th>SKU</th>
                        <th>Product Name</th>
                        <th class="text-end" style="width:15%;">Qty Ordered</th>
                        <th class="text-end" style="width:15%;">Qty Received</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalItemsOrdered = 0;
                    $totalItemsReceived = 0;
                    foreach ($poDetails['items'] as $index => $item): 
                        $qtyOrdered = (int)$item['quantity_ordered'];
                        $qtyReceived = (int)($item['quantity_received'] ?? 0);
                        $totalItemsOrdered += $qtyOrdered;
                        $totalItemsReceived += $qtyReceived;
                    ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($item['sku']); ?></td>
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td class="text-end"><?php echo htmlspecialchars($qtyOrdered); ?></td>
                            <td class="text-end"><?php echo htmlspecialchars($qtyReceived); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                 <tfoot>
                    <tr class="table-light">
                        <th colspan="3" class="text-end"><strong>Totals:</strong></th>
                        <th class="text-end"><strong><?php echo $totalItemsOrdered; ?></strong></th>
                        <th class="text-end"><strong><?php echo $totalItemsReceived; ?></strong></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php if (!empty($poDetails['history'])): ?>
            <h5 class="mb-3">Order History:</h5>
            <div class="list-group history-log mb-4">
                <?php foreach (array_reverse($poDetails['history']) as $log): // Show newest first ?>
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo htmlspecialchars($log['action']); ?></h6>
                            <small class="text-muted"><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($log['timestamp']))); ?></small>
                        </div>
                        <p class="mb-1"><small>User: <?php echo htmlspecialchars($log['user']); ?>
                        <?php if (isset($log['status_change']) && $log['status_change']): ?>
                            | Status Change: <?php echo htmlspecialchars($log['status_change']); ?>
                        <?php endif; ?>
                        </small></p>
                        <?php if (isset($log['notes']) && !empty($log['notes'])): ?>
                            <small class="text-muted">Notes: <?php echo nl2br(htmlspecialchars($log['notes'])); ?></small>
                        <?php endif; ?>
                         <?php // Display delivery reference and transit notes if they exist (added for vendor shipment)
                            if (isset($log['delivery_reference'])): ?>
                            <small class="text-muted d-block">Delivery Ref: <?php echo htmlspecialchars($log['delivery_reference']); ?></small>
                        <?php endif; ?>
                        <?php if (isset($log['transit_notes'])): ?>
                            <small class="text-muted d-block">Transit Notes: <?php echo nl2br(htmlspecialchars($log['transit_notes'])); ?></small>
                        <?php endif; ?>
                        <?php if (isset($log['cancellation_reason'])): // Display cancellation reason ?>
                            <small class="text-danger d-block">Cancellation Reason: <?php echo nl2br(htmlspecialchars($log['cancellation_reason'])); ?></small>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="text-center mt-4 no-print">
            <button class="btn btn-primary" onclick="window.print();"><span class="material-symbols-outlined" style="vertical-align: middle; font-size: 1.2em; margin-right: 0.25em;">print</span> Print Details</button>
            <?php // Adjust back button based on user role ?>
            <?php if ($userRole === UserRole::Vendor): ?>
                <a href="../vendor/index.php" class="btn btn-secondary"><span class="material-symbols-outlined" style="vertical-align: middle; font-size: 1.2em; margin-right: 0.25em;">arrow_back</span> Back to Vendor Portal</a>
            <?php else: ?>
                <a href="index.php" class="btn btn-secondary"><span class="material-symbols-outlined" style="vertical-align: middle; font-size: 1.2em; margin-right: 0.25em;">arrow_back</span> Back to Inventory</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Include Bootstrap JS (Optional, for components like dropdowns if any) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>