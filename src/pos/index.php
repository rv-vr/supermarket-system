<?php
require_once __DIR__ . '/../classes/UserRole.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/InventoryManager.php';
require_once __DIR__ . '/../classes/SalesRecorder.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || !($_SESSION['user'] instanceof User)) {
    header('Location: ../../login.php');
    exit();
}

$user = $_SESSION['user'];
if ($user->getRole() !== UserRole::Cashier) {
    header('Location: ../../login.php'); // Or a generic access denied page
    exit();
}

$inventoryManager = new InventoryManager();
$salesRecorder = new SalesRecorder();

// Initialize POS state variables in session if they don't exist
$_SESSION['pos_cart'] = $_SESSION['pos_cart'] ?? [];
$_SESSION['pos_search_term'] = $_SESSION['pos_search_term'] ?? '';
$_SESSION['pos_search_results'] = $_SESSION['pos_search_results'] ?? [];
$_SESSION['pos_show_modal'] = $_SESSION['pos_show_modal'] ?? null; // 'quantity' or 'payment'
$_SESSION['pos_modal_sku'] = $_SESSION['pos_modal_sku'] ?? null;   // SKU for the quantity modal
$_SESSION['pos_feedback'] = $_SESSION['pos_feedback'] ?? ['message' => '', 'type' => 'info'];


// --- ACTION HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pos_action'])) {
    $action = $_POST['pos_action'];
    $_SESSION['pos_feedback'] = ['message' => '', 'type' => 'info']; // Reset feedback

    // Clear modals unless the action is specifically to show one or process its data
    if (!in_array($action, ['select_product', 'add_to_cart', 'show_payment_modal', 'process_payment'])) {
        $_SESSION['pos_show_modal'] = null;
        $_SESSION['pos_modal_sku'] = null;
    }
    // Clear search results if not a search action or adding from search
    if (!in_array($action, ['search_product', 'select_product', 'add_to_cart'])) {
        $_SESSION['pos_search_results'] = [];
        // $_SESSION['pos_search_term'] = ''; // Keep search term for context if needed
    }


    switch ($action) {
        case 'search_product':
            $_SESSION['pos_search_term'] = trim($_POST['search_query'] ?? '');
            if (!empty($_SESSION['pos_search_term'])) {
                $_SESSION['pos_search_results'] = $inventoryManager->searchProducts($_SESSION['pos_search_term']);
                // The following line is responsible for the "No products found" message.
                // We will comment it out to disable the message.
                /*
                if (empty($_SESSION['pos_search_results'])) {
                    $_SESSION['pos_feedback'] = ['message' => "No products found for '" . htmlspecialchars($_SESSION['pos_search_term']) . "'.", 'type' => 'warning'];
                }
                */
            } else {
                $_SESSION['pos_search_results'] = [];
            }
            break;

        case 'select_product': // User clicked a product from search results
            if (isset($_POST['sku'])) {
                $product = $inventoryManager->getProductBySku($_POST['sku']);
                if ($product) {
                    if ($product['stock_quantity'] > 0) {
                        $_SESSION['pos_modal_sku'] = $_POST['sku'];
                        $_SESSION['pos_show_modal'] = 'quantity';
                    } else {
                        $_SESSION['pos_feedback'] = ['message' => htmlspecialchars($product['name']) . " is out of stock.", 'type' => 'danger'];
                    }
                }
            }
            break;

        case 'add_to_cart': // Submitted from quantity modal
            $sku = $_POST['sku'] ?? null;
            $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
            $product = $sku ? $inventoryManager->getProductBySku($sku) : null;

            if ($product && $quantity > 0) {
                if ($inventoryManager->checkStock($sku, $quantity)) {
                    if (isset($_SESSION['pos_cart'][$sku])) {
                        $_SESSION['pos_cart'][$sku]['quantity'] += $quantity;
                    } else {
                        $_SESSION['pos_cart'][$sku] = [
                            'sku' => $product['sku'],
                            'name' => $product['name'],
                            'price' => $product['price'],
                            'quantity' => $quantity,
                        ];
                    }
                    $_SESSION['pos_feedback'] = ['message' => htmlspecialchars($product['name']) . " (x{$quantity}) added to cart.", 'type' => 'success'];
                    $_SESSION['pos_show_modal'] = null; // Close modal
                    $_SESSION['pos_modal_sku'] = null;
                    // $_SESSION['pos_search_results'] = []; // Optionally clear search results
                    // $_SESSION['pos_search_term'] = '';
                } else {
                    $_SESSION['pos_feedback'] = ['message' => "Not enough stock for " . htmlspecialchars($product['name']) . ". Available: " . $product['stock_quantity'], 'type' => 'danger'];
                    $_SESSION['pos_show_modal'] = 'quantity'; // Keep modal open to adjust
                }
            } else {
                $_SESSION['pos_feedback'] = ['message' => "Invalid product or quantity.", 'type' => 'danger'];
                if ($sku) $_SESSION['pos_show_modal'] = 'quantity'; // Keep modal open if it was a quantity attempt
            }
            break;

        case 'remove_from_cart':
            if (isset($_POST['sku']) && isset($_SESSION['pos_cart'][$_POST['sku']])) {
                $removedItemName = $_SESSION['pos_cart'][$_POST['sku']]['name'];
                unset($_SESSION['pos_cart'][$_POST['sku']]);
                $_SESSION['pos_feedback'] = ['message' => htmlspecialchars($removedItemName) . " removed from cart.", 'type' => 'info'];
            }
            break;

        case 'new_sale':
            $_SESSION['pos_cart'] = [];
            $_SESSION['pos_search_term'] = '';
            $_SESSION['pos_search_results'] = [];
            $_SESSION['pos_show_modal'] = null;
            $_SESSION['pos_modal_sku'] = null;
            $_SESSION['pos_feedback'] = ['message' => "New sale started. Cart cleared.", 'type' => 'info'];
            break;

        case 'show_payment_modal':
            if (!empty($_SESSION['pos_cart'])) {
                $_SESSION['pos_show_modal'] = 'payment';
            } else {
                $_SESSION['pos_feedback'] = ['message' => "Cart is empty. Add items before payment.", 'type' => 'warning'];
            }
            break;

        case 'process_payment':
            $cartTotal = 0;
            foreach ($_SESSION['pos_cart'] as $item) {
                $cartTotal += $item['price'] * $item['quantity'];
            }

            $paymentMethod = $_POST['payment_method'] ?? '';
            $amountGiven = isset($_POST['amount_given']) && $_POST['amount_given'] !== '' ? (float)$_POST['amount_given'] : null;
            $referenceNumber = trim($_POST['reference_number'] ?? '');
            $validTransaction = true;

            if (empty($paymentMethod)) {
                $_SESSION['pos_feedback'] = ['message' => "Payment method is required.", 'type' => 'danger'];
                $validTransaction = false;
            } elseif ($paymentMethod === 'Cash' && ($amountGiven === null || $amountGiven < $cartTotal)) {
                $_SESSION['pos_feedback'] = ['message' => "Amount given for cash payment is insufficient or not provided.", 'type' => 'danger'];
                $validTransaction = false;
            } elseif (in_array($paymentMethod, ['Card', 'E-wallet']) && empty($referenceNumber)) {
                // Making reference number optional for now, can be made mandatory
                // $_SESSION['pos_feedback'] = ['message' => "Reference number is required for {$paymentMethod}.", 'type' => 'danger'];
                // $validTransaction = false;
            }

            if ($validTransaction && !empty($_SESSION['pos_cart'])) {
                // Final stock check before committing sale
                $stockSufficient = true;
                foreach ($_SESSION['pos_cart'] as $sku => $item) {
                    if (!$inventoryManager->checkStock($sku, $item['quantity'])) {
                        $_SESSION['pos_feedback'] = ['message' => "Checkout failed: Not enough stock for " . htmlspecialchars($item['name']) . ". Sale not completed.", 'type' => 'danger'];
                        $stockSufficient = false;
                        break;
                    }
                }

                if ($stockSufficient) {
                    if ($salesRecorder->recordSale($user->getUsername(), $_SESSION['pos_cart'], $cartTotal, $paymentMethod, $amountGiven, $referenceNumber)) {
                        // Update stock for each item sold
                        foreach ($_SESSION['pos_cart'] as $sku => $item) {
                            $inventoryManager->updateStock($sku, -$item['quantity']);
                        }
                        $successMsg = "Sale completed! Total: $" . number_format($cartTotal, 2);
                        if ($paymentMethod === 'Cash' && $amountGiven !== null) {
                            $change = $amountGiven - $cartTotal;
                            $successMsg .= ". Change: $" . number_format($change, 2);
                        }
                        $_SESSION['pos_feedback'] = ['message' => $successMsg, 'type' => 'success'];
                        // Reset for next sale
                        $_SESSION['pos_cart'] = [];
                        $_SESSION['pos_search_term'] = '';
                        $_SESSION['pos_search_results'] = [];
                        $_SESSION['pos_show_modal'] = null;
                    } else {
                        $_SESSION['pos_feedback'] = ['message' => "Error: Could not record sale. Please try again.", 'type' => 'danger'];
                    }
                }
            } elseif ($validTransaction && empty($_SESSION['pos_cart'])) {
                 $_SESSION['pos_feedback'] = ['message' => "Cannot process payment. Cart is empty.", 'type' => 'danger'];
            }
            
            if (!$validTransaction || (isset($stockSufficient) && !$stockSufficient)) {
                 $_SESSION['pos_show_modal'] = 'payment'; // Keep payment modal open on error
            }
            break;
    }
    // PRG Pattern: Redirect to the same page to prevent form resubmission
    header("Location: index.php");
    exit();
}
// --- END ACTION HANDLING ---

$currentCart = $_SESSION['pos_cart'];
$currentCartTotal = 0;
foreach ($currentCart as $item) {
    $currentCartTotal += $item['price'] * $item['quantity'];
}

$productForModal = null;
if ($_SESSION['pos_show_modal'] === 'quantity' && $_SESSION['pos_modal_sku']) {
    $productForModal = $inventoryManager->getProductBySku($_SESSION['pos_modal_sku']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Terminal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/styles.css"> <!-- Global styles -->
    <link rel="stylesheet" href="../../public/css/pos_styles.css"> <!-- POS specific styles -->
    <!-- Google Icons will be added in Part 3, typically in header.php or here if only for POS -->
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="main-content-wrapper container-fluid px-0">
        <!-- Top Row of Buttons - New Sale button removed -->
        <div class="container-fluid py-2 px-3 border-bottom">
            <!-- Placeholder for other global POS buttons -->
        </div>

        <!-- POS Main Layout: Search, Cart, Total, Logo -->
        <div class="pos-main-layout">
            <!-- Left Panel: Search and Cart Items -->
            <div class="pos-left-panel">
                <form action="index.php" method="POST" class="mb-2" id="posSearchForm">
                    <input type="hidden" name="pos_action" value="search_product">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search_query" id="posSearchQueryInput" placeholder="Enter SKU, Barcode, or Product Name" value="<?php echo htmlspecialchars($_SESSION['pos_search_term']); ?>" autofocus>
                    </div>
                </form>

                <?php if (!empty($_SESSION['pos_search_results'])): ?>
                    <h6>Search Results:</h6>
                    <div class="list-group search-results-container mb-3">
                        <?php foreach ($_SESSION['pos_search_results'] as $product): ?>
                            <form action="index.php" method="POST" class="d-block">
                                <input type="hidden" name="pos_action" value="select_product">
                                <input type="hidden" name="sku" value="<?php echo htmlspecialchars($product['sku']); ?>">
                                <button type="submit" class="list-group-item list-group-item-action <?php echo ($product['stock_quantity'] <= 0) ? 'list-group-item-danger disabled' : ''; ?>" <?php echo ($product['stock_quantity'] <= 0) ? 'disabled' : ''; ?>>
                                    <?php echo htmlspecialchars($product['name']); ?> (SKU: <?php echo htmlspecialchars($product['sku']); ?>) - $<?php echo number_format($product['price'], 2); ?>
                                    <?php if ($product['stock_quantity'] <= 0): ?>
                                        <span class="badge bg-danger float-end">Out of Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-success float-end">Stock: <?php echo $product['stock_quantity']; ?></span>
                                    <?php endif; ?>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <h5>Items in Current Sale:</h5>
                <div class="cart-items-container table-responsive">
                    <?php if (empty($currentCart)): ?>
                        <p class="text-muted">Cart is empty. Scan or search for items.</p>
                    <?php else: ?>
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr><th>SKU</th><th>Name</th><th class="text-end">Price</th><th class="text-center">Qty</th><th class="text-end">Subtotal</th><th>Action</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($currentCart as $sku => $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['sku']); ?></td>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td class="text-end">$<?php echo number_format($item['price'], 2); ?></td>
                                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                                        <td class="text-end">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                        <td>
                                            <form action="index.php" method="POST" class="d-inline">
                                                <input type="hidden" name="pos_action" value="remove_from_cart">
                                                <input type="hidden" name="sku" value="<?php echo htmlspecialchars($sku); ?>">
                                                <button type="submit" class="btn btn-danger btn-sm py-0 px-1">&times;</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Panel: Total and Logo -->
            <div class="pos-right-panel">
                <div class="pos-right-top">
                    <h2>Total Amount Due</h2>
                    <h1 class="display-3 my-3 fw-bold">$<?php echo number_format($currentCartTotal, 2); ?></h1>
                    <form action="index.php" method="POST">
                        <input type="hidden" name="pos_action" value="show_payment_modal">
                        <button type="submit" class="btn btn-success btn-lg px-5" <?php echo empty($currentCart) ? 'disabled' : ''; ?>>Finalize Payment</button>
                    </form>
                </div>
                <div class="pos-right-bottom">
                    <span class="logo-placeholder">SUPERMARKET SYSTEM</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Quantity Input Modal -->
    <div class="modal fade" id="quantityModal" tabindex="-1" aria-labelledby="quantityModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <?php if ($productForModal): ?>
                <form action="index.php" method="POST">
                    <input type="hidden" name="pos_action" value="add_to_cart">
                    <input type="hidden" name="sku" value="<?php echo htmlspecialchars($productForModal['sku']); ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="quantityModalLabel">Enter Quantity for <?php echo htmlspecialchars($productForModal['name']); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p><strong>Price:</strong> $<?php echo number_format($productForModal['price'], 2); ?> | <strong>Available Stock:</strong> <?php echo $productForModal['stock_quantity']; ?></p>
                        <div class="mb-3">
                            <label for="quantity" class="form-label">Quantity:</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="1" max="<?php echo $productForModal['stock_quantity']; ?>" value="1" required autofocus>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add to Cart</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Payment Finalization Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="index.php" method="POST" id="paymentForm">
                    <input type="hidden" name="pos_action" value="process_payment">
                    <div class="modal-header">
                        <h5 class="modal-title" id="paymentModalLabel">Finalize Payment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <h4 class="mb-3">Total Due: <span class="fw-bold">$<?php echo number_format($currentCartTotal, 2); ?></span></h4>
                        <div class="mb-3">
                            <label for="payment_method" class="form-label">Payment Method:</label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="">Select Method...</option>
                                <option value="Cash">Cash</option>
                                <option value="Card">Card</option>
                                <option value="E-wallet">E-wallet</option>
                            </select>
                        </div>
                        <div id="cashFieldsContainer" class="mb-3" style="display:none;">
                            <label for="amount_given" class="form-label">Amount Given:</label>
                            <input type="number" step="0.01" class="form-control" id="amount_given" name="amount_given">
                            <p class="mt-2">Change Due: $<span id="change_due_display">0.00</span></p>
                        </div>
                        <div id="referenceFieldsContainer" class="mb-3" style="display:none;">
                            <label for="reference_number" class="form-label">Waybill/Reference No.:</label>
                            <input type="text" class="form-control" id="reference_number" name="reference_number">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Confirm Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="toast-container">
        <!-- Toasts will be dynamically added here by JavaScript -->
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        window.posConfig = {
            feedback: <?php echo isset($_SESSION['pos_feedback']) && !empty($_SESSION['pos_feedback']['message']) ? json_encode($_SESSION['pos_feedback']) : 'null'; ?>,
            showModal: <?php echo json_encode($_SESSION['pos_show_modal']); ?>,
            isProductForModalAvailable: <?php echo $productForModal ? 'true' : 'false'; ?>,
            isCartNotEmpty: <?php echo !empty($currentCart) ? 'true' : 'false'; ?>,
            currentCartTotal: <?php echo json_encode($currentCartTotal); ?>
        };
        <?php
        // Clear session feedback after passing it to JS
        if (isset($_SESSION['pos_feedback']) && !empty($_SESSION['pos_feedback']['message'])) {
            $_SESSION['pos_feedback'] = ['message' => '', 'type' => 'info'];
        }
        // Clear modal flags after passing them
        if (isset($_SESSION['pos_show_modal'])) { // Check if set before trying to clear
             if ($_SESSION['pos_show_modal'] === 'quantity' && $productForModal) {
                $_SESSION['pos_show_modal'] = null; $_SESSION['pos_modal_sku'] = null;
            } elseif ($_SESSION['pos_show_modal'] === 'payment' && !empty($currentCart)) {
                $_SESSION['pos_show_modal'] = null;
            }
        }
        ?>
    </script>
    <script src="../../public/js/pos_scripts.js" defer></script>
</body>
</html>