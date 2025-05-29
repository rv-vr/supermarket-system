<?php
require_once __DIR__ . '/../classes/UserRole.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/InventoryManager.php';
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

if ($user->getRole() !== UserRole::Stocker) {
    header('Location: ../../login.php'); // Or an access denied page
    exit();
}

$inventoryManager = new InventoryManager();
$poManager = new PurchaseOrderManager();
$feedbackMessage = '';
$feedbackType = '';
$poDetails = null;
$poId = $_GET['po_id'] ?? null;

if (!$poId) {
    $_SESSION['feedback_message'] = 'No Purchase Order ID provided.';
    $_SESSION['feedback_type'] = 'danger';
    header('Location: index.php');
    exit();
}

$poDetails = $poManager->getPurchaseOrderById($poId);

if (!$poDetails) {
    $_SESSION['feedback_message'] = "Purchase Order #{$poId} not found.";
    $_SESSION['feedback_type'] = 'danger';
    header('Location: index.php');
    exit();
}

// Allow receiving only if PO is 'Shipped' or 'Partially Received'
if (!in_array($poDetails['status'], [PurchaseOrderManager::STATUS_SHIPPED, PurchaseOrderManager::STATUS_PARTIALLY_RECEIVED])) {
    $_SESSION['feedback_message'] = "Purchase Order #{$poId} is not in a receivable state (current status: {$poDetails['status']}). Must be 'Shipped' or 'Partially Received'.";
    $_SESSION['feedback_type'] = 'warning';
    header('Location: index.php');
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'receive_items') {
    $receivedItemsData = $_POST['items'] ?? [];
    $notes = trim($_POST['receiving_notes'] ?? '');

    if (empty($receivedItemsData)) {
        $feedbackMessage = "No items were marked as received.";
        $feedbackType = "warning";
    } else {
        $result = $poManager->receivePurchaseOrderItems($poId, $receivedItemsData, $user->getUsername(), $notes);

        if ($result['success']) {
            // Update inventory for each successfully received item
            foreach ($result['processed_items'] as $processedItem) {
                if ($processedItem['quantity_received_now'] > 0) {
                    $inventoryManager->receiveStock($processedItem['sku'], $processedItem['quantity_received_now']);
                }
            }
            $_SESSION['feedback_message'] = "Items for PO #{$poId} received. New status: {$result['new_status']}.";
            $_SESSION['feedback_type'] = 'success';
            header('Location: index.php'); // Redirect to avoid re-submission
            exit();
        } else {
            $feedbackMessage = "Error receiving items: " . ($result['message'] ?? "An unknown error occurred.");
            $feedbackType = "danger";
            // Reload PO details to show any partial updates if applicable, or just show error
            $poDetails = $poManager->getPurchaseOrderById($poId);
        }
    }
}


// Feedback from redirect
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
    <link rel="stylesheet" href="../../public/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Receive Purchase Order - <?php echo htmlspecialchars($poId); ?></title>
    <style>
        .item-table th, .item-table td { vertical-align: middle; }
        .qty-remaining { font-size: 0.9em; color: #6c757d; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container">
        <h1 class="my-4">Receive Items for Purchase Order: <?php echo htmlspecialchars($poId); ?></h1>

        <?php if ($feedbackMessage): ?>
            <div class="alert alert-<?php echo $feedbackType; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($feedbackMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($poDetails): ?>
            <div class="card mb-4">
                <div class="card-header">
                    PO Details
                </div>
                <div class="card-body">
                    <p><strong>Vendor:</strong> <?php echo htmlspecialchars($poDetails['vendor_name']); ?></p>
                    <p><strong>Order Date:</strong> <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($poDetails['order_date']))); ?></p>
                    <p><strong>Requested By:</strong> <?php echo htmlspecialchars($poDetails['requested_by']); ?></p>
                    <p><strong>Current Status:</strong> <?php echo htmlspecialchars($poDetails['status']); ?></p>
                </div>
            </div>

            <form action="receive_po.php?po_id=<?php echo htmlspecialchars($poId); ?>" method="POST">
                <input type="hidden" name="action" value="receive_items">
                <div class="card">
                    <div class="card-header">
                        Items to Receive
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table item-table">
                                <thead>
                                    <tr>
                                        <th>SKU</th>
                                        <th>Product Name</th>
                                        <th class="text-end">Qty Ordered</th>
                                        <th class="text-end">Qty Already Received</th>
                                        <th class="text-center">Qty Receiving Now</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($poDetails['items'] as $index => $item):
                                        $qtyOrdered = (int)$item['quantity_ordered'];
                                        $qtyAlreadyReceived = (int)($item['quantity_received'] ?? 0);
                                        $qtyRemainingToReceive = $qtyOrdered - $qtyAlreadyReceived;
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['sku']); ?></td>
                                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                            <td class="text-end"><?php echo $qtyOrdered; ?></td>
                                            <td class="text-end"><?php echo $qtyAlreadyReceived; ?></td>
                                            <td class="text-center">
                                                <?php if ($qtyRemainingToReceive > 0): ?>
                                                    <input type="number" class="form-control form-control-sm text-center"
                                                           name="items[<?php echo $index; ?>][quantity_received_now]"
                                                           min="0"
                                                           max="<?php echo $qtyRemainingToReceive; ?>"
                                                           placeholder="0"
                                                           style="width: 100px; margin: auto;">
                                                    <input type="hidden" name="items[<?php echo $index; ?>][sku]" value="<?php echo htmlspecialchars($item['sku']); ?>">
                                                    <small class="qty-remaining">(Max: <?php echo $qtyRemainingToReceive; ?>)</small>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Fully Received</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mb-3 mt-3">
                            <label for="receiving_notes" class="form-label">Receiving Notes (Optional):</label>
                            <textarea class="form-control" id="receiving_notes" name="receiving_notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <span class="material-symbols-outlined">inventory_2</span> Confirm Received Quantities
                        </button>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <p class="text-danger">Could not load Purchase Order details.</p>
            <a href="index.php" class="btn btn-primary">Back to Inventory</a>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>