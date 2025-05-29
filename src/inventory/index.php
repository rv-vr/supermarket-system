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
    header('Location: ../../login.php');
    exit();
}

$inventoryManager = new InventoryManager();
$poManager = new PurchaseOrderManager();
$feedbackMessage = '';
$feedbackType = '';

// Load vendors
$vendorsForDropdown = [];
$vendorsDataFull = [];
$vendorsFilePath = __DIR__ . '/../data/vendors.json';
if (file_exists($vendorsFilePath)) {
    $rawVendorsData = json_decode(file_get_contents($vendorsFilePath), true);
    if (is_array($rawVendorsData)) {
        foreach ($rawVendorsData as $vendor) {
            if (isset($vendor['name'])) {
                $vendorsForDropdown[] = $vendor['name'];
                $vendorsDataFull[] = $vendor;
            }
        }
        sort($vendorsForDropdown);
    }
}

// Handle PO Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_po') {
    $selectedVendorName = trim($_POST['vendor_name'] ?? '');
    $items = [];
    if (isset($_POST['items']) && is_array($_POST['items'])) {
        foreach ($_POST['items'] as $itemData) {
            if (!empty($itemData['sku']) && !empty($itemData['quantity']) && (int)$itemData['quantity'] > 0) {
                $productDetails = $inventoryManager->getProductBySku(trim($itemData['sku']));
                $items[] = [
                    'sku' => trim($itemData['sku']),
                    'product_name' => $productDetails ? $productDetails['name'] : (isset($itemData['product_name']) ? trim($itemData['product_name']) : 'Unknown Product'),
                    'quantity' => (int)$itemData['quantity']
                ];
            }
        }
    }

    if (empty($selectedVendorName)) {
        $feedbackMessage = "Please select a Vendor.";
        $feedbackType = "danger";
    } elseif (empty($items)) {
        $feedbackMessage = "Please add at least one item with a valid quantity.";
        $feedbackType = "danger";
    } else {
        $newPoId = $poManager->createPurchaseOrder($selectedVendorName, $items, $user->getUsername());
        if ($newPoId) {
            $feedbackMessage = "Purchase Order #{$newPoId} for vendor '{$selectedVendorName}' created successfully with status 'Draft'.";
            $feedbackType = "success";
        } else {
            $feedbackMessage = "Failed to create Purchase Order. Please check item details.";
            $feedbackType = "danger";
        }
    }
}

// Handle PO Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_po_status') {
    $poIdToUpdate = $_POST['po_id'] ?? null;
    $newStatus = $_POST['new_status'] ?? null;

    if ($poIdToUpdate && $newStatus) {
        // Optional: Add a check here to ensure the transition is valid.
        // For "Send to Vendor", the current status should ideally be "Draft".
        $currentPo = $poManager->getPurchaseOrderById($poIdToUpdate);
        if ($currentPo && $newStatus === PurchaseOrderManager::STATUS_SENT_TO_VENDOR && $currentPo['status'] !== PurchaseOrderManager::STATUS_DRAFT) {
            $_SESSION['feedback_message'] = "Purchase Order #{$poIdToUpdate} cannot be sent to vendor. Current status: {$currentPo['status']}.";
            $_SESSION['feedback_type'] = "warning";
        } else {
            if ($poManager->updatePurchaseOrderStatus($poIdToUpdate, $newStatus, $user->getUsername())) {
                $_SESSION['feedback_message'] = "Purchase Order #{$poIdToUpdate} status updated to '{$newStatus}'.";
                $_SESSION['feedback_type'] = "success";
            } else {
                $_SESSION['feedback_message'] = "Failed to update status for Purchase Order #{$poIdToUpdate}.";
                $_SESSION['feedback_type'] = "danger";
            }
        }
    } else {
        $_SESSION['feedback_message'] = "Invalid request to update PO status.";
        $_SESSION['feedback_type'] = "danger";
    }
    // Redirect to prevent form resubmission
    header("Location: index.php");
    exit();
}

// Handle PO Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_po') {
    $poIdToDelete = $_POST['po_id_delete'] ?? null;

    if ($poIdToDelete) {
        $deleteResult = $poManager->deletePurchaseOrder($poIdToDelete, $user->getUsername());
        $_SESSION['feedback_message'] = $deleteResult['message'];
        $_SESSION['feedback_type'] = $deleteResult['success'] ? "success" : "danger";
    } else {
        $_SESSION['feedback_message'] = "Invalid request to delete PO.";
        $_SESSION['feedback_type'] = "danger";
    }
    // Redirect to prevent form resubmission
    header("Location: index.php");
    exit();
}


// Feedback from other pages (like receive_po.php) or from PRG redirects
if (isset($_SESSION['feedback_message'])) {
    $feedbackMessage = $_SESSION['feedback_message'];
    $feedbackType = $_SESSION['feedback_type'];
    unset($_SESSION['feedback_message']);
    unset($_SESSION['feedback_type']);
}

$allPurchaseOrders = $poManager->getAllPurchaseOrders();
$allProducts = $inventoryManager->getAllProducts(); // For inventory table and JS

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../public/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Inventory Management</title>
    <style>
        .item-row { display: flex; align-items: center; margin-bottom: 0.75rem; padding: 0.5rem; border: 1px solid #eee; border-radius: 0.25rem; }
        .status-draft { color: gray; }
        .status-sent-to-vendor { color: blue; }
        .status-shipped { color: teal; }
        .status-partially-received { color: orange; }
        .status-received { color: green; }
        .status-cancelled { color: red; }
        #po-search-results .list-group-item { cursor: pointer; }
        #po-search-results { 
            max-height: 250px; 
            overflow-y: auto; 
            border: 1px solid #dee2e6; 
            border-top: none;
        }
        #toggle_list_vendor_products_btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        #toggle_list_vendor_products_btn .material-symbols-outlined {
            font-size: 1.2rem; 
            line-height: 1; 
        }
        .table th, .table td { vertical-align: middle; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container">
        <h1 class="my-4">Inventory Dashboard - Stocker</h1>

        <?php if ($feedbackMessage): ?>
            <div class="alert alert-<?php echo $feedbackType; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($feedbackMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">
                <h4><span class="material-symbols-outlined">add_shopping_cart</span> Create New Purchase Order</h4>
            </div>
            <div class="card-body">
                <form action="index.php" method="POST" id="createPoForm">
                    <input type="hidden" name="action" value="create_po">
                    <div class="mb-3">
                        <label for="vendor_name_select" class="form-label">Select Vendor:</label>
                        <select class="form-select" id="vendor_name_select" name="vendor_name" required>
                            <option value="">Choose a vendor...</option>
                            <?php foreach ($vendorsForDropdown as $vendorOption): ?>
                                <option value="<?php echo htmlspecialchars($vendorOption); ?>"><?php echo htmlspecialchars($vendorOption); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="product_search_po" class="form-label">Search or List Products from Selected Vendor:</label>
                        <div class="input-group">
                            <input type="text" class="form-control mb-1" id="product_search_po" placeholder="Select a vendor first..." disabled>
                            <button class="btn btn-outline-secondary mb-1 mt-0" type="button" id="toggle_list_vendor_products_btn" title="Toggle list of all products from this vendor" disabled>
                                <span class="material-symbols-outlined">arrow_drop_down</span>
                            </button>
                        </div>
                        <div id="po-search-results" class="list-group"></div>
                    </div>

                    <h5>Items to Order:</h5>
                    <div id="po-items-container" class="mb-3">
                        <p class="text-muted" id="po-items-placeholder">No items added yet. Select a vendor and search or list products.</p>
                    </div>

                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">
                            <span class="material-symbols-outlined">save</span> Create Purchase Order (as Draft)
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- View Purchase Orders Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h4><span class="material-symbols-outlined">list_alt</span> Existing Purchase Orders</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($allPurchaseOrders)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>PO ID</th><th>Vendor</th><th>Order Date</th><th>Requested By</th><th>Status</th><th>Items</th><th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allPurchaseOrders as $po): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($po['po_id']); ?></td>
                                        <td><?php echo htmlspecialchars($po['vendor_name']); ?></td>
                                        <td><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($po['order_date']))); ?></td>
                                        <td><?php echo htmlspecialchars($po['requested_by']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php
                                                switch ($po['status']) {
                                                    case PurchaseOrderManager::STATUS_DRAFT: echo 'secondary'; break;
                                                    case PurchaseOrderManager::STATUS_SENT_TO_VENDOR: echo 'primary'; break;
                                                    case PurchaseOrderManager::STATUS_SHIPPED: echo 'info'; break;
                                                    case PurchaseOrderManager::STATUS_PARTIALLY_RECEIVED: echo 'warning text-dark'; break;
                                                    case PurchaseOrderManager::STATUS_RECEIVED: echo 'success'; break;
                                                    case PurchaseOrderManager::STATUS_CANCELLED: echo 'danger'; break;
                                                    default: echo 'light text-dark';
                                                }
                                            ?>">
                                                <?php echo htmlspecialchars($po['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <ul class="list-unstyled mb-0 small">
                                            <?php foreach ($po['items'] as $item): ?>
                                                <li>
                                                    <?php echo htmlspecialchars($item['sku']); ?>
                                                    (<?php echo htmlspecialchars($item['product_name']); ?>) - Ord: <?php echo $item['quantity_ordered']; ?>
                                                    <?php if (isset($item['quantity_received'])): ?>
                                                        , Rcv: <?php echo $item['quantity_received']; ?>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                            </ul>
                                        </td>
                                        <td>
                                            <?php if ($po['status'] === PurchaseOrderManager::STATUS_DRAFT): ?>
                                                <form action="index.php" method="POST" class="d-inline me-1">
                                                    <input type="hidden" name="action" value="update_po_status">
                                                    <input type="hidden" name="po_id" value="<?php echo htmlspecialchars($po['po_id']); ?>">
                                                    <input type="hidden" name="new_status" value="<?php echo PurchaseOrderManager::STATUS_SENT_TO_VENDOR; ?>">
                                                    <button type="submit" class="btn btn-sm btn-info" title="Send to Vendor">
                                                        <span class="material-symbols-outlined">send</span>
                                                    </button>
                                                </form>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deletePoModal"
                                                        data-po-id-delete="<?php echo htmlspecialchars($po['po_id']); ?>"
                                                        data-po-vendor-delete="<?php echo htmlspecialchars($po['vendor_name']); ?>"
                                                        title="Delete Draft PO">
                                                    <span class="material-symbols-outlined">delete</span>
                                                </button>
                                            <?php elseif (in_array($po['status'], [PurchaseOrderManager::STATUS_SHIPPED, PurchaseOrderManager::STATUS_PARTIALLY_RECEIVED])): ?>
                                                <a href="receive_po.php?po_id=<?php echo htmlspecialchars($po['po_id']); ?>" class="btn btn-sm btn-success" title="Receive Items">
                                                    <span class="material-symbols-outlined">inventory</span>
                                                </a>
                                            <?php elseif ($po['status'] === PurchaseOrderManager::STATUS_RECEIVED || $po['status'] === PurchaseOrderManager::STATUS_CANCELLED): ?>
                                                <a href="view_po_receipt.php?po_id=<?php echo htmlspecialchars($po['po_id']); ?>" class="btn btn-sm btn-secondary" title="View Details">
                                                    <span class="material-symbols-outlined">receipt_long</span>
                                                </a>
                                            <?php endif; ?>
                                            <!-- Add other actions like cancel for draft/sent if needed -->
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No purchase orders found.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Current Inventory Stock Section -->
        <div class="card mt-4 mb-4">
            <div class="card-header">
                <h4><span class="material-symbols-outlined">inventory_2</span> Current Inventory Stock</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($allProducts)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>SKU</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th class="text-end">Price</th>
                                    <th class="text-end">Stock Quantity</th> <!-- Changed Header -->
                                    <th>Last Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allProducts as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['sku']); ?></td>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td><?php echo htmlspecialchars($product['category'] ?? 'N/A'); ?></td>
                                        <td class="text-end">
                                            <?php echo htmlspecialchars(isset($product['price']) ? number_format($product['price'], 2) : 'N/A'); ?>
                                        </td>
                                        <td class="text-end <?php echo (isset($product['stock_quantity']) && $product['stock_quantity'] < 10 && $product['stock_quantity'] > 0) ? 'text-warning fw-bold' : ((isset($product['stock_quantity']) && $product['stock_quantity'] == 0) ? 'text-danger fw-bold' : ''); ?>">
                                            <?php echo htmlspecialchars($product['stock_quantity'] ?? 0); ?> <!-- Changed key -->
                                        </td>
                                        <td><?php echo htmlspecialchars(isset($product['last_updated']) ? date('M d, Y H:i', strtotime($product['last_updated'])) : 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No products found in inventory.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Create New PO Modal -->
    <div class="modal fade" id="createPoModal" tabindex="-1" aria-labelledby="createPoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="index.php" method="POST" id="modalCreatePoForm">
                    <input type="hidden" name="action" value="create_po">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createPoModalLabel">Create New Purchase Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="modal_vendor_name" class="form-label">Vendor:</label>
                            <select class="form-select" id="modal_vendor_name" name="vendor_name" required>
                                <option value="">Select a vendor...</option>
                                <?php foreach ($vendorsForDropdown as $vendorOption): ?>
                                    <option value="<?php echo htmlspecialchars($vendorOption); ?>"><?php echo htmlspecialchars($vendorOption); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="modal_product_search" class="form-label">Search Products:</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="modal_product_search" placeholder="Search for products..." disabled>
                                <button class="btn btn-outline-secondary" type="button" id="modal_toggle_list_vendor_products_btn" title="Toggle list of all products from this vendor" disabled>
                                    <span class="material-symbols-outlined">arrow_drop_down</span>
                                </button>
                            </div>
                            <div id="modal_po-search-results" class="list-group"></div>
                        </div>

                        <h5>Items to Order:</h5>
                        <div id="modal_po-items-container" class="mb-3">
                            <p class="text-muted" id="modal_po-items-placeholder">No items added yet. Select a vendor and search products.</p>
                        </div>

                        <div class="mb-3">
                            <label for="modal_notes" class="form-label">Notes:</label>
                            <textarea class="form-control" id="modal_notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="material-symbols-outlined">save</span> Create Purchase Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete PO Confirmation Modal -->
    <div class="modal fade" id="deletePoModal" tabindex="-1" aria-labelledby="deletePoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="index.php" method="POST">
                    <input type="hidden" name="action" value="delete_po">
                    <input type="hidden" name="po_id_delete" id="modal_po_id_delete" value="">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deletePoModalLabel">Confirm Delete Purchase Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete Draft Purchase Order #<strong id="modal_po_id_delete_display"></strong> for vendor <strong id="modal_po_vendor_delete_display"></strong>?</p>
                        <p class="text-danger">This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete PO</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const productsData = <?php echo json_encode($allProducts); ?>; // Already available
            const vendorsData = <?php echo json_encode($vendorsDataFull); ?>;

            const vendorSelect = document.getElementById('vendor_name_select');
            const productSearchInput = document.getElementById('product_search_po');
            const toggleListButton = document.getElementById('toggle_list_vendor_products_btn');
            const searchResultsContainer = document.getElementById('po-search-results');
            const poItemsContainer = document.getElementById('po-items-container');
            const poItemsPlaceholder = document.getElementById('po-items-placeholder');

            let currentSelectedVendor = null;
            let poItemIndex = 0;
            let isListAllActive = false;

            function displayProductsInResults(productsToList) {
                searchResultsContainer.innerHTML = '';
                if (productsToList.length > 0) {
                    productsToList.forEach(product => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action';
                        btn.innerHTML = `${product.name} (SKU: ${product.sku})`;
                        btn.dataset.sku = product.sku;
                        btn.dataset.name = product.name;
                        btn.addEventListener('click', function() {
                            addSkuToPoForm(this.dataset.sku, this.dataset.name);
                        });
                        searchResultsContainer.appendChild(btn);
                    });
                } else {
                    searchResultsContainer.innerHTML = '<div class="list-group-item text-muted">No products found.</div>';
                }
            }

            vendorSelect.addEventListener('change', function() {
                const selectedVendorName = this.value;
                currentSelectedVendor = vendorsData.find(v => v.name === selectedVendorName);

                poItemsContainer.innerHTML = ''; // Clear existing items when vendor changes
                poItemIndex = 0; // Reset index
                productSearchInput.value = '';
                searchResultsContainer.innerHTML = '';
                poItemsPlaceholder.style.display = 'block';
                isListAllActive = false; 
                toggleListButton.innerHTML = '<span class="material-symbols-outlined">arrow_drop_down</span>';


                if (currentSelectedVendor && currentSelectedVendor.provided_skus) {
                    productSearchInput.disabled = false;
                    toggleListButton.disabled = false;
                    productSearchInput.placeholder = `Search products from ${currentSelectedVendor.name}...`;
                } else {
                    productSearchInput.disabled = true;
                    toggleListButton.disabled = true;
                    productSearchInput.placeholder = 'Select a vendor first...';
                    currentSelectedVendor = null;
                }
            });

            productSearchInput.addEventListener('input', function() {
                const searchTerm = this.value.trim().toLowerCase();
                isListAllActive = false; 
                toggleListButton.innerHTML = '<span class="material-symbols-outlined">arrow_drop_down</span>';
                
                if (!currentSelectedVendor || !currentSelectedVendor.provided_skus) {
                    searchResultsContainer.innerHTML = '';
                    return;
                }
                if (searchTerm.length < 1) { 
                    searchResultsContainer.innerHTML = '';
                    return;
                }

                const vendorSkus = currentSelectedVendor.provided_skus.map(s => String(s).toLowerCase());
                const filteredProducts = productsData.filter(p => {
                    const productSkuLower = String(p.sku).toLowerCase();
                    return vendorSkus.includes(productSkuLower) &&
                           (productSkuLower.includes(searchTerm) || String(p.name).toLowerCase().includes(searchTerm));
                });
                displayProductsInResults(filteredProducts);
            });

            toggleListButton.addEventListener('click', function() {
                if (!currentSelectedVendor || !currentSelectedVendor.provided_skus) {
                    searchResultsContainer.innerHTML = '<div class="list-group-item text-muted">Please select a vendor first.</div>';
                    return;
                }

                if (isListAllActive) {
                    searchResultsContainer.innerHTML = ''; 
                    isListAllActive = false;
                    this.innerHTML = '<span class="material-symbols-outlined">arrow_drop_down</span>';
                } else {
                    productSearchInput.value = ''; 
                    const vendorSkus = currentSelectedVendor.provided_skus.map(s => String(s).toLowerCase());
                    const allVendorProducts = productsData.filter(p => vendorSkus.includes(String(p.sku).toLowerCase()));
                    displayProductsInResults(allVendorProducts);
                    isListAllActive = true;
                    this.innerHTML = '<span class="material-symbols-outlined">arrow_drop_up</span>'; 
                }
            });

            function addSkuToPoForm(sku, productName) {
                const existingSkus = Array.from(poItemsContainer.querySelectorAll('input[name$="[sku]"]')).map(input => input.value);
                if (existingSkus.includes(sku)) {
                    alert(`Product ${productName} (SKU: ${sku}) is already in the list. Please adjust its quantity if needed.`);
                    const existingQtyInput = poItemsContainer.querySelector(`input[name="items[${existingSkus.indexOf(sku)}][quantity]"]`);
                    if(existingQtyInput) existingQtyInput.focus();
                    return;
                }
                
                poItemsPlaceholder.style.display = 'none';

                const newItemRow = document.createElement('div');
                newItemRow.className = 'item-row mb-2';
                newItemRow.innerHTML = `
                    <input type="hidden" name="items[${poItemIndex}][sku]" value="${sku}">
                    <input type="hidden" name="items[${poItemIndex}][product_name]" value="${productName}">
                    <div class="flex-grow-1">
                        <strong>${productName}</strong><br><small class="text-muted">SKU: ${sku}</small>
                    </div>
                    <input type="number" name="items[${poItemIndex}][quantity]" class="form-control form-control-sm ms-2" placeholder="Qty" min="1" style="width: 80px;" required>
                    <button type="button" class="btn btn-danger btn-sm remove-po-item-btn ms-2" title="Remove Item">
                        <span class="material-symbols-outlined" style="font-size: 1em; vertical-align: middle;">delete</span>
                    </button>
                `;
                poItemsContainer.appendChild(newItemRow);
                poItemIndex++;
            }

            poItemsContainer.addEventListener('click', function(e) {
                if (e.target.closest('.remove-po-item-btn')) {
                    e.target.closest('.item-row').remove();
                    if (poItemsContainer.children.length === 0) {
                        poItemsPlaceholder.style.display = 'block';
                    }
                }
            });
        });

        // JavaScript for Create PO Modal
        document.addEventListener('DOMContentLoaded', function() {
            const modalVendorSelect = document.getElementById('modal_vendor_name');
            const modalProductSearchInput = document.getElementById('modal_product_search');
            const modalToggleListButton = document.getElementById('modal_toggle_list_vendor_products_btn');
            const modalSearchResultsContainer = document.getElementById('modal_po-search-results');
            const modalPoItemsContainer = document.getElementById('modal_po-items-container');
            const modalPoItemsPlaceholder = document.getElementById('modal_po-items-placeholder');

            let modalCurrentSelectedVendor = null;
            let modalPoItemIndex = 0;
            let modalIsListAllActive = false;

            function modalDisplayProductsInResults(productsToList) {
                modalSearchResultsContainer.innerHTML = '';
                if (productsToList.length > 0) {
                    productsToList.forEach(product => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action';
                        btn.innerHTML = `${product.name} (SKU: ${product.sku})`;
                        btn.dataset.sku = product.sku;
                        btn.dataset.name = product.name;
                        btn.addEventListener('click', function() {
                            modalAddSkuToPoForm(this.dataset.sku, this.dataset.name);
                        });
                        modalSearchResultsContainer.appendChild(btn);
                    });
                } else {
                    modalSearchResultsContainer.innerHTML = '<div class="list-group-item text-muted">No products found.</div>';
                }
            }

            modalVendorSelect.addEventListener('change', function() {
                const selectedVendorName = this.value;
                modalCurrentSelectedVendor = vendorsData.find(v => v.name === selectedVendorName);

                modalPoItemsContainer.innerHTML = ''; // Clear existing items when vendor changes
                modalPoItemIndex = 0; // Reset index
                modalProductSearchInput.value = '';
                modalSearchResultsContainer.innerHTML = '';
                modalPoItemsPlaceholder.style.display = 'block';
                modalIsListAllActive = false; 
                modalToggleListButton.innerHTML = '<span class="material-symbols-outlined">arrow_drop_down</span>';


                if (modalCurrentSelectedVendor && modalCurrentSelectedVendor.provided_skus) {
                    modalProductSearchInput.disabled = false;
                    modalToggleListButton.disabled = false;
                    modalProductSearchInput.placeholder = `Search products from ${modalCurrentSelectedVendor.name}...`;
                } else {
                    modalProductSearchInput.disabled = true;
                    modalToggleListButton.disabled = true;
                    modalProductSearchInput.placeholder = 'Select a vendor first...';
                    modalCurrentSelectedVendor = null;
                }
            });

            modalProductSearchInput.addEventListener('input', function() {
                const searchTerm = this.value.trim().toLowerCase();
                modalIsListAllActive = false; 
                modalToggleListButton.innerHTML = '<span class="material-symbols-outlined">arrow_drop_down</span>';
                
                if (!modalCurrentSelectedVendor || !modalCurrentSelectedVendor.provided_skus) {
                    modalSearchResultsContainer.innerHTML = '';
                    return;
                }
                if (searchTerm.length < 1) { 
                    modalSearchResultsContainer.innerHTML = '';
                    return;
                }

                const vendorSkus = modalCurrentSelectedVendor.provided_skus.map(s => String(s).toLowerCase());
                const filteredProducts = productsData.filter(p => {
                    const productSkuLower = String(p.sku).toLowerCase();
                    return vendorSkus.includes(productSkuLower) &&
                           (productSkuLower.includes(searchTerm) || String(p.name).toLowerCase().includes(searchTerm));
                });
                modalDisplayProductsInResults(filteredProducts);
            });

            modalToggleListButton.addEventListener('click', function() {
                if (!modalCurrentSelectedVendor || !modalCurrentSelectedVendor.provided_skus) {
                    modalSearchResultsContainer.innerHTML = '<div class="list-group-item text-muted">Please select a vendor first.</div>';
                    return;
                }

                if (modalIsListAllActive) {
                    modalSearchResultsContainer.innerHTML = ''; 
                    modalIsListAllActive = false;
                    this.innerHTML = '<span class="material-symbols-outlined">arrow_drop_down</span>';
                } else {
                    modalProductSearchInput.value = ''; 
                    const vendorSkus = modalCurrentSelectedVendor.provided_skus.map(s => String(s).toLowerCase());
                    const allVendorProducts = productsData.filter(p => vendorSkus.includes(String(p.sku).toLowerCase()));
                    modalDisplayProductsInResults(allVendorProducts);
                    modalIsListAllActive = true;
                    this.innerHTML = '<span class="material-symbols-outlined">arrow_drop_up</span>'; 
                }
            });

            function modalAddSkuToPoForm(sku, productName) {
                const existingSkus = Array.from(modalPoItemsContainer.querySelectorAll('input[name$="[sku]"]')).map(input => input.value);
                if (existingSkus.includes(sku)) {
                    alert(`Product ${productName} (SKU: ${sku}) is already in the list. Please adjust its quantity if needed.`);
                    const existingQtyInput = modalPoItemsContainer.querySelector(`input[name="items[${existingSkus.indexOf(sku)}][quantity]"]`);
                    if(existingQtyInput) existingQtyInput.focus();
                    return;
                }
                
                modalPoItemsPlaceholder.style.display = 'none';

                const newItemRow = document.createElement('div');
                newItemRow.className = 'item-row mb-2';
                newItemRow.innerHTML = `
                    <input type="hidden" name="items[${modalPoItemIndex}][sku]" value="${sku}">
                    <input type="hidden" name="items[${modalPoItemIndex}][product_name]" value="${productName}">
                    <div class="flex-grow-1">
                        <strong>${productName}</strong><br><small class="text-muted">SKU: ${sku}</small>
                    </div>
                    <input type="number" name="items[${modalPoItemIndex}][quantity]" class="form-control form-control-sm ms-2" placeholder="Qty" min="1" style="width: 80px;" required>
                    <button type="button" class="btn btn-danger btn-sm remove-po-item-btn ms-2" title="Remove Item">
                        <span class="material-symbols-outlined" style="font-size: 1em; vertical-align: middle;">delete</span>
                    </button>
                `;
                modalPoItemsContainer.appendChild(newItemRow);
                modalPoItemIndex++;
            }

            modalPoItemsContainer.addEventListener('click', function(e) {
                if (e.target.closest('.remove-po-item-btn')) {
                    e.target.closest('.item-row').remove();
                    if (modalPoItemsContainer.children.length === 0) {
                        modalPoItemsPlaceholder.style.display = 'block';
                    }
                }
            });
        });

        // JavaScript to set PO ID in the Delete PO modal
        var deletePoModal = document.getElementById('deletePoModal');
        if (deletePoModal) {
            deletePoModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget; // Button that triggered the modal
                var poId = button.getAttribute('data-po-id-delete');
                var vendorName = button.getAttribute('data-po-vendor-delete');
                
                var modalPoIdInput = deletePoModal.querySelector('#modal_po_id_delete');
                var modalPoIdDisplay = deletePoModal.querySelector('#modal_po_id_delete_display');
                var modalPoVendorDisplay = deletePoModal.querySelector('#modal_po_vendor_delete_display');
                
                modalPoIdInput.value = poId;
                modalPoIdDisplay.textContent = poId;
                modalPoVendorDisplay.textContent = vendorName;
            });
        }
    </script>
</body>
</html>