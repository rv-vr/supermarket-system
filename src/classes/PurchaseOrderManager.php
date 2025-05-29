<?php

class PurchaseOrderManager {
    private $purchaseOrdersFilePath; // Property declaration
    private $vendorsFilePath;
    private $productsFilePath;

    // Define PO Statuses as constants
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_SENT_TO_VENDOR = 'Sent to Vendor';
    public const STATUS_SHIPPED = 'Shipped'; // For vendor updates later
    public const STATUS_PARTIALLY_RECEIVED = 'Partially Received';
    public const STATUS_RECEIVED = 'Received';
    public const STATUS_CANCELLED = 'Cancelled';


    public function __construct() {
        // Initialization of the property
        $this->purchaseOrdersFilePath = __DIR__ . '/../data/purchase_orders.json';
        $this->vendorsFilePath = __DIR__ . '/../data/vendors.json';
        $this->productsFilePath = __DIR__ . '/../data/products.json';

        // Initialize purchase_orders.json if it doesn't exist
        if (!file_exists($this->purchaseOrdersFilePath)) {
            // Try to create the data directory if it doesn't exist
            $dataDir = dirname($this->purchaseOrdersFilePath);
            if (!is_dir($dataDir)) {
                @mkdir($dataDir, 0777, true); // Suppress error if it fails, file_put_contents will also fail
            }
            // Attempt to create the file
            if (file_put_contents($this->purchaseOrdersFilePath, json_encode([])) === false) {
                error_log("Failed to create initial purchase_orders.json file at: " . $this->purchaseOrdersFilePath);
                // Handle error appropriately, maybe throw an exception or ensure methods check for file existence
            }
        }
        // Similar initialization for vendors.json and products.json if necessary
        if (!file_exists($this->vendorsFilePath)) {
            $dataDir = dirname($this->vendorsFilePath);
            if (!is_dir($dataDir)) { @mkdir($dataDir, 0777, true); }
            file_put_contents($this->vendorsFilePath, json_encode([]));
        }
        if (!file_exists($this->productsFilePath)) {
            $dataDir = dirname($this->productsFilePath);
            if (!is_dir($dataDir)) { @mkdir($dataDir, 0777, true); }
            file_put_contents($this->productsFilePath, json_encode([]));
        }
    }

    private function getAllPOs() {
        if (!file_exists($this->purchaseOrdersFilePath)) {
            error_log("Purchase orders file does not exist at: " . $this->purchaseOrdersFilePath);
            return []; // Return empty array if file doesn't exist
        }
        $data = file_get_contents($this->purchaseOrdersFilePath);
        if ($data === false) {
            error_log("Failed to read purchase orders file from: " . $this->purchaseOrdersFilePath);
            return [];
        }
        $decodedData = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error for purchase_orders.json: " . json_last_error_msg());
            return []; // Return empty or handle error, maybe corrupted file
        }
        return $decodedData ?: [];
    }

    private function savePOs($purchaseOrders) {
        // Ensure the property is set before using it
        if (empty($this->purchaseOrdersFilePath)) {
            error_log("Fatal Error: purchaseOrdersFilePath property is not set in PurchaseOrderManager.");
            return false;
        }

        $dir = dirname($this->purchaseOrdersFilePath);

        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
                error_log("Fatal Error: Purchase order data directory ({$dir}) does not exist and could not be created.");
                return false;
            }
        } elseif (!is_writable($dir)) {
             error_log("Fatal Error: Purchase order data directory ({$dir}) is not writable.");
            return false;
        }

        // Ensure $purchaseOrders is an array and re-index it to prevent JSON objects for sequential arrays
        $dataToSave = array_values((array)$purchaseOrders);
        $jsonData = json_encode($dataToSave, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($jsonData === false) {
            // Log JSON encoding error
            $jsonError = json_last_error();
            $jsonErrorMsg = json_last_error_msg();
            error_log("Failed to encode purchase orders to JSON. Error code: {$jsonError}. Message: {$jsonErrorMsg}");
            // Optionally log a snippet of the data that failed to encode for debugging
            // error_log("Data snippet that failed JSON encoding: " . print_r(array_slice($dataToSave, 0, 2), true));
            return false;
        }

        // Attempt to write the file with an exclusive lock
        if (file_put_contents($this->purchaseOrdersFilePath, $jsonData, LOCK_EX) !== false) {
            return true; // Successfully wrote to file
        } else {
            // Log file writing failure
            error_log("Failed to write purchase orders to file: {$this->purchaseOrdersFilePath}. Check file permissions and disk space.");
            // Log the last PHP error which might give more details about the file_put_contents failure
            $lastError = error_get_last();
            if ($lastError !== null) {
                error_log("PHP error during file_put_contents: Type: {$lastError['type']}, Message: {$lastError['message']} in {$lastError['file']} on line {$lastError['line']}");
            }
            return false;
        }
    }

    private function getProductDetailsBySku($sku) {
        if (!file_exists($this->productsFilePath)) {
            return null;
        }
        $productsData = json_decode(file_get_contents($this->productsFilePath), true);
        if (is_array($productsData)) {
            foreach ($productsData as $product) {
                if (isset($product['sku']) && $product['sku'] === $sku) {
                    return $product;
                }
            }
        }
        return null;
    }

    public function createPurchaseOrder($vendorName, $requestedBy, $items) {
        $purchaseOrders = $this->getAllPOs();
        $poId = 'PO-' . date('Ymd-His') . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 4));

        $processedItems = [];
        foreach ($items as $item) {
            $productDetails = $this->getProductDetailsBySku($item['sku']);
            $processedItems[] = [
                'sku' => $item['sku'],
                'product_name' => $productDetails ? $productDetails['name'] : ($item['product_name'] ?? 'Unknown Product'),
                'quantity_ordered' => (int)$item['quantity'],
                'quantity_received' => 0, // Initialize quantity_received
                // Add other fields like unit_price if needed in the future
            ];
        }

        $newPO = [
            'po_id' => $poId,
            'vendor_name' => $vendorName,
            'order_date' => date('Y-m-d H:i:s'),
            'requested_by' => $requestedBy,
            'status' => self::STATUS_DRAFT, // Use constant
            'items' => $processedItems,
            'history' => [
                ['timestamp' => date('Y-m-d H:i:s'), 'user' => $requestedBy, 'action' => 'Created PO', 'status_change' => self::STATUS_DRAFT]
            ]
        ];

        $purchaseOrders[] = $newPO;
        $this->savePOs($purchaseOrders);
        return $poId;
    }

    public function getAllPurchaseOrders() {
        $pos = $this->getAllPOs();
        // Sort by order_date descending
        usort($pos, function ($a, $b) {
            return strtotime($b['order_date']) - strtotime($a['order_date']);
        });
        return $pos;
    }

    public function getPurchaseOrderById($poId) {
        $purchaseOrders = $this->getAllPOs();
        foreach ($purchaseOrders as $po) {
            if ($po['po_id'] === $poId) {
                // Ensure all items have quantity_received, defaulting to 0 if not present
                if (isset($po['items']) && is_array($po['items'])) {
                    foreach ($po['items'] as &$item) { // Use reference to modify directly
                        if (!isset($item['quantity_received'])) {
                            $item['quantity_received'] = 0;
                        }
                    }
                }
                return $po;
            }
        }
        return null;
    }

    public function updatePurchaseOrderStatus($poId, $newStatus, $user = 'System', $notes = '', $details = []) { // Added $details parameter
        $purchaseOrders = $this->getAllPOs();
        $poUpdated = false;
        foreach ($purchaseOrders as &$po) { // Use reference to modify directly
            if ($po['po_id'] === $poId) {
                $oldStatus = $po['status'];
                $po['status'] = $newStatus;
                $historyEntry = [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'user' => $user,
                    'action' => "Status changed from {$oldStatus} to {$newStatus}",
                    'status_change' => $newStatus
                ];
                if (!empty($notes)) {
                    $historyEntry['notes'] = $notes;
                }
                if (!empty($details) && is_array($details)) { // Merge additional details
                    $historyEntry = array_merge($historyEntry, $details);
                }
                $po['history'][] = $historyEntry;
                $poUpdated = true;
                break;
            }
        }

        if ($poUpdated) {
            $this->savePOs($purchaseOrders);
            return true;
        }
        return false;
    }

    public function deletePurchaseOrder($poId, $user = 'System') {
        $purchaseOrders = $this->getAllPOs();
        $poIndexToDelete = -1;
        $poToDelete = null;

        foreach ($purchaseOrders as $index => $po) {
            if ($po['po_id'] === $poId) {
                $poIndexToDelete = $index;
                $poToDelete = $po;
                break;
            }
        }

        if ($poIndexToDelete === -1) {
            return ['success' => false, 'message' => "Purchase Order #{$poId} not found."];
        }

        if ($poToDelete['status'] !== self::STATUS_DRAFT) {
            return ['success' => false, 'message' => "Purchase Order #{$poId} cannot be deleted. Only draft POs can be deleted. Current status: {$poToDelete['status']}."];
        }

        // Remove the PO from the array
        array_splice($purchaseOrders, $poIndexToDelete, 1);
        
        // Optionally, log this action somewhere else if needed, 
        // as the PO itself will be gone, so its history is also gone.
        // For now, we just delete it.

        if ($this->savePOs($purchaseOrders)) {
            // Log deletion action (e.g., to a separate system log or a general audit trail if you have one)
            // For simplicity, we're not adding a history entry to the PO itself as it's being deleted.
            // error_log("User '{$user}' deleted draft Purchase Order #{$poId} on " . date('Y-m-d H:i:s'));
            return ['success' => true, 'message' => "Draft Purchase Order #{$poId} successfully deleted."];
        } else {
            return ['success' => false, 'message' => "Failed to save changes after attempting to delete Purchase Order #{$poId}."];
        }
    }

    public function receivePurchaseOrderItems($poId, $receivedItemsData, $receivedBy, $receivingNotes = '') {
        $purchaseOrders = $this->getAllPOs();
        $poIndex = -1;
        $currentPo = null;

        foreach ($purchaseOrders as $index => $p) {
            if ($p['po_id'] === $poId) {
                $poIndex = $index;
                $currentPo = $p;
                break;
            }
        }

        if ($poIndex === -1 || !$currentPo) {
            return ['success' => false, 'message' => 'PO not found.'];
        }

        // Allow receiving only if PO is 'Shipped' or 'Partially Received'
        if (!in_array($currentPo['status'], [self::STATUS_SHIPPED, self::STATUS_PARTIALLY_RECEIVED])) {
            return ['success' => false, 'message' => 'PO is not in a receivable state. Current status: ' . $currentPo['status']];
        }

        $processedItemsForInventory = [];
        $totalOrdered = 0;
        $totalNowReceivedIncludingPrevious = 0;
        $anyItemReceivedInThisTransaction = false;

        foreach ($currentPo['items'] as &$item) { // Iterate by reference to update
            $item['quantity_received'] = (int)($item['quantity_received'] ?? 0); // Ensure it exists and is int
            $totalOrdered += (int)$item['quantity_ordered'];

            foreach ($receivedItemsData as $receivedItemInput) {
                if ($receivedItemInput['sku'] === $item['sku']) {
                    $qtyReceivedNow = (int)($receivedItemInput['quantity_received_now'] ?? 0);

                    if ($qtyReceivedNow < 0) {
                         return ['success' => false, 'message' => "Received quantity for SKU {$item['sku']} cannot be negative."];
                    }

                    $qtyRemainingToReceive = (int)$item['quantity_ordered'] - $item['quantity_received'];
                    if ($qtyReceivedNow > $qtyRemainingToReceive) {
                        return ['success' => false, 'message' => "Received quantity for SKU {$item['sku']} ({$qtyReceivedNow}) exceeds remaining quantity ({$qtyRemainingToReceive})."];
                    }

                    if ($qtyReceivedNow > 0) {
                        $item['quantity_received'] += $qtyReceivedNow;
                        $processedItemsForInventory[] = [
                            'sku' => $item['sku'],
                            'product_name' => $item['product_name'],
                            'quantity_received_now' => $qtyReceivedNow
                        ];
                        $anyItemReceivedInThisTransaction = true;
                    }
                    break; 
                }
            }
            $totalNowReceivedIncludingPrevious += $item['quantity_received'];
        }
        unset($item); // Unset reference

        // Determine new PO status
        $newPoStatus = $currentPo['status']; // Default to current
        if ($anyItemReceivedInThisTransaction || $currentPo['status'] === self::STATUS_SENT_TO_VENDOR) { // Check if any action was taken or if it's the first receiving attempt
            if ($totalNowReceivedIncludingPrevious >= $totalOrdered) {
                $newPoStatus = self::STATUS_RECEIVED;
            } elseif ($totalNowReceivedIncludingPrevious > 0) {
                $newPoStatus = self::STATUS_PARTIALLY_RECEIVED;
            }
            // If $totalNowReceivedIncludingPrevious is 0 and status was SENT_TO_VENDOR, it remains SENT_TO_VENDOR (no items received)
        }
        
        $currentPo['status'] = $newPoStatus;
        
        // Add history entry
        $historyAction = "Items received.";
        if ($anyItemReceivedInThisTransaction) {
             $historyAction .= " Details: ";
             foreach($processedItemsForInventory as $pi) {
                 if ($pi['quantity_received_now'] > 0) {
                    $historyAction .= "{$pi['sku']} (Qty: {$pi['quantity_received_now']}); ";
                 }
             }
        } else {
            $historyAction .= " No new quantities entered.";
        }


        $historyEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user' => $receivedBy,
            'action' => rtrim($historyAction, '; '),
            'status_change' => ($currentPo['status'] !== $purchaseOrders[$poIndex]['status']) ? $newPoStatus : null // Log status change only if it actually changed
        ];
        if (!empty($receivingNotes)) {
            $historyEntry['notes'] = $receivingNotes;
        }
        $currentPo['history'][] = $historyEntry;

        $purchaseOrders[$poIndex] = $currentPo;
        $this->savePOs($purchaseOrders);

        return [
            'success' => true,
            'new_status' => $newPoStatus,
            'processed_items' => $processedItemsForInventory // Items to update in inventory
        ];
    }
}