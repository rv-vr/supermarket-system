<?php

class PurchaseOrderManager {
    private $poFilePath;
    private $productsFilePath; // To get product names if not stored in PO

    // Define PO Statuses as constants
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_SENT_TO_VENDOR = 'Sent to Vendor';
    public const STATUS_SHIPPED = 'Shipped'; // For vendor updates later
    public const STATUS_PARTIALLY_RECEIVED = 'Partially Received';
    public const STATUS_RECEIVED = 'Received';
    public const STATUS_CANCELLED = 'Cancelled';


    public function __construct() {
        $this->poFilePath = __DIR__ . '/../data/purchase_orders.json';
        $this->productsFilePath = __DIR__ . '/../data/products.json'; // For product name lookup
        if (!file_exists($this->poFilePath)) {
            file_put_contents($this->poFilePath, json_encode([]));
        }
    }

    private function getAllPOs() {
        $data = file_get_contents($this->poFilePath);
        return json_decode($data, true) ?: [];
    }

    private function savePOs($purchaseOrders) {
        file_put_contents($this->poFilePath, json_encode($purchaseOrders, JSON_PRETTY_PRINT));
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

    public function createPurchaseOrder($vendorName, $items, $requestedBy) {
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

    public function updatePurchaseOrderStatus($poId, $newStatus, $user = 'System', $notes = '') {
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

        if (!in_array($currentPo['status'], [self::STATUS_SENT_TO_VENDOR, self::STATUS_PARTIALLY_RECEIVED])) {
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