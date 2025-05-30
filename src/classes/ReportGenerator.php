<?php
require_once __DIR__ . '/SalesRecorder.php'; // For sales data
require_once __DIR__ . '/InventoryManager.php'; // For product/inventory data
require_once __DIR__ . '/PurchaseOrderManager.php'; // For PO data
require_once __DIR__ . '/AttendanceManager.php'; // For attendance data

class ReportGenerator {
    private $salesRecorder;
    private $inventoryManager;
    private $poManager;
    private $attendanceManager;
    private $salesDataPath;
    private $productsDataPath;
    private $purchaseOrdersDataPath;
    private $timeLogDataPath;


    public function __construct() {
        $this->salesRecorder = new SalesRecorder();
        $this->inventoryManager = new InventoryManager();
        $this->poManager = new PurchaseOrderManager();
        $this->attendanceManager = new AttendanceManager();

        $this->salesDataPath = __DIR__ . '/../data/sales.json';
        $this->productsDataPath = __DIR__ . '/../data/products.json';
        $this->purchaseOrdersDataPath = __DIR__ . '/../data/purchase_orders.json';
        $this->timeLogDataPath = __DIR__ . '/../data/time_log.json';

        if (date_default_timezone_get() !== 'Asia/Manila') {
            date_default_timezone_set('Asia/Manila');
        }
    }

    private function loadJsonData(string $filePath): array {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            error_log("ReportGenerator: File not found or not readable - " . $filePath);
            return [];
        }
        $json = file_get_contents($filePath);
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("ReportGenerator: JSON decode error for " . $filePath . " - " . json_last_error_msg());
            return [];
        }
        return is_array($data) ? $data : [];
    }

    // --- SALES REPORTS ---
    public function getSalesSummary(string $startDate = null, string $endDate = null): array {
        $sales = $this->salesRecorder->getAllSales(); 
        
        $filteredSales = [];
        if ($startDate || $endDate) { // Allow filtering by start, end, or both
            $startDateTime = $startDate ? strtotime($startDate . " 00:00:00") : null;
            $endDateTime = $endDate ? strtotime($endDate . " 23:59:59") : null;

            foreach ($sales as $sale) {
                $saleTimestamp = strtotime($sale['timestamp']);
                $includeSale = true;
                if ($startDateTime && $saleTimestamp < $startDateTime) {
                    $includeSale = false;
                }
                if ($endDateTime && $saleTimestamp > $endDateTime) {
                    $includeSale = false;
                }
                if ($includeSale) {
                    $filteredSales[] = $sale;
                }
            }
        } else {
            $filteredSales = $sales;
        }

        $totalSalesAmount = 0;
        $totalTransactions = count($filteredSales);
        $salesByCashier = [];
        $salesByPaymentMethod = [];
        $productSales = []; // For top selling products

        foreach ($filteredSales as $sale) {
            $totalSalesAmount += ($sale['total_amount'] ?? 0);
            
            // Sales by Cashier
            $cashier = $sale['cashier_username'] ?? 'Unknown';
            $salesByCashier[$cashier] = ($salesByCashier[$cashier] ?? 0) + ($sale['total_amount'] ?? 0);

            // Sales by Payment Method
            // Access payment_method from the nested payment_details object
            $paymentMethod = $sale['payment_details']['method'] ?? 'Unknown'; 
            $salesByPaymentMethod[$paymentMethod] = ($salesByPaymentMethod[$paymentMethod] ?? 0) + ($sale['total_amount'] ?? 0);

            // Product Sales (Quantity and Revenue)
            if (isset($sale['items']) && is_array($sale['items'])) {
                foreach ($sale['items'] as $item) {
                    $sku = $item['sku'] ?? 'N/A';
                    $productSales[$sku]['name'] = $item['name'] ?? 'Unknown Product';
                    $productSales[$sku]['quantity_sold'] = ($productSales[$sku]['quantity_sold'] ?? 0) + ($item['quantity'] ?? 0);
                    // Use 'price_per_unit' as stored by SalesRecorder
                    $itemPrice = $item['price_per_unit'] ?? 0; // Default to 0 if not set, though it should be
                    $productSales[$sku]['total_revenue'] = ($productSales[$sku]['total_revenue'] ?? 0) + ($itemPrice * ($item['quantity'] ?? 0));
                }
            }
        }

        arsort($salesByCashier);
        arsort($salesByPaymentMethod);
        // Sort products by total revenue
        uasort($productSales, function($a, $b) {
            return ($b['total_revenue'] ?? 0) <=> ($a['total_revenue'] ?? 0);
        });

        return [
            'total_sales_amount' => $totalSalesAmount,
            'total_transactions' => $totalTransactions,
            'average_transaction_value' => $totalTransactions > 0 ? $totalSalesAmount / $totalTransactions : 0,
            'sales_by_cashier' => $salesByCashier,
            'sales_by_payment_method' => $salesByPaymentMethod,
            'top_selling_products' => array_slice($productSales, 0, 10, true) // Top 10
        ];
    }

    // --- INVENTORY REPORTS ---
    public function getInventorySummary(): array {
        $products = $this->inventoryManager->getAllProducts();
        $lowStockItems = [];
        $totalStockValue = 0;
        $categoryCounts = [];
        $outOfStockItems = [];

        foreach ($products as $product) {
            $stockQuantity = $product['stock_quantity'] ?? 0;
            $price = $product['price'] ?? 0;
            // Corrected to use 'reorder_level' from products.json
            $lowStockThreshold = $product['reorder_level'] ?? 10; // Default if not set

            if ($stockQuantity < $lowStockThreshold) {
                $lowStockItems[] = $product;
            }
            if ($stockQuantity <= 0) {
                $outOfStockItems[] = $product;
            }
            $totalStockValue += $stockQuantity * $price;
            
            $category = $product['category'] ?? 'Uncategorized';
            $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
        }
        arsort($categoryCounts);

        // Sort low stock items by urgency (how far below reorder level they are)
        // Items most below their reorder level will appear first.
        usort($lowStockItems, function($a, $b) {
            $urgencyA = ($a['reorder_level'] ?? 10) - ($a['stock_quantity'] ?? 0);
            $urgencyB = ($b['reorder_level'] ?? 10) - ($b['stock_quantity'] ?? 0);
            return $urgencyB <=> $urgencyA; // Sort descending by urgency
        });

        return [
            'total_products' => count($products),
            'total_stock_value' => $totalStockValue,
            'low_stock_items_count' => count($lowStockItems),
            'low_stock_items_list' => array_slice($lowStockItems, 0, 10), // Show top 10 most urgent
            'out_of_stock_items_count' => count($outOfStockItems),
            'products_by_category' => $categoryCounts
        ];
    }

    // --- USER ACTIVITY REPORTS (Placeholder) ---
    public function getUserActivitySummary(string $startDate = null, string $endDate = null): array {
        $timeLogs = $this->attendanceManager->getAllTimeLogs(); // Assuming this method exists or is added
        
        $filteredLogs = [];
        if ($startDate || $endDate) {
            $startDateTime = $startDate ? strtotime($startDate . " 00:00:00") : null;
            $endDateTime = $endDate ? strtotime($endDate . " 23:59:59") : null;
            foreach ($timeLogs as $log) {
                $logTimestamp = strtotime($log['timestamp']);
                $includeLog = true;
                if ($startDateTime && $logTimestamp < $startDateTime) {
                    $includeLog = false;
                }
                if ($endDateTime && $logTimestamp > $endDateTime) {
                    $includeLog = false;
                }
                if ($includeLog) {
                    $filteredLogs[] = $log;
                }
            }
        } else {
            $filteredLogs = $timeLogs;
        }
        
        $loginsByUser = []; // Count 'time_in' actions
        $actionsByType = [];
        $userHours = []; // Calculate hours worked

        // Group logs by user for hour calculation
        $logsByUser = [];
        foreach ($filteredLogs as $log) {
            $user = $log['username'] ?? 'Unknown';
            $logsByUser[$user][] = $log;

            $action = $log['action'] ?? 'Unknown';
            if ($action === 'time_in') {
                 $loginsByUser[$user] = ($loginsByUser[$user] ?? 0) + 1;
            }
            $actionsByType[$action] = ($actionsByType[$action] ?? 0) + 1;
        }
        arsort($loginsByUser);
        arsort($actionsByType);

        // Calculate hours worked
        foreach ($logsByUser as $username => $userSpecificLogs) {
            usort($userSpecificLogs, function($a, $b) { // Sort by timestamp
                return strtotime($a['timestamp']) <=> strtotime($b['timestamp']);
            });

            $totalDurationSeconds = 0;
            $lastTimeIn = null;
            foreach ($userSpecificLogs as $log) {
                if ($log['action'] === 'time_in') {
                    $lastTimeIn = strtotime($log['timestamp']);
                } elseif ($log['action'] === 'time_out' && $lastTimeIn !== null) {
                    $timeOut = strtotime($log['timestamp']);
                    $totalDurationSeconds += ($timeOut - $lastTimeIn);
                    $lastTimeIn = null; // Reset for next pair
                }
            }
            $userHours[$username] = round($totalDurationSeconds / 3600, 2); // Convert seconds to hours
        }
        arsort($userHours);


        return [
            'total_recorded_actions' => count($filteredLogs),
            'logins_per_user' => $loginsByUser,
            'actions_by_type' => $actionsByType,
            'hours_worked_by_user' => $userHours,
            'filtered_logs_sample' => array_slice($filteredLogs, 0, 20) // Sample of recent logs
        ];
    }

    // --- PO REPORTS (Shipment Reports) ---
    public function getPurchaseOrderStatusSummary(string $startDate = null, string $endDate = null): array {
        $purchaseOrders = $this->poManager->getAllPurchaseOrders(); 
        
        $filteredPOs = [];
        if ($startDate || $endDate) {
            $startDateTime = $startDate ? strtotime($startDate . " 00:00:00") : null;
            $endDateTime = $endDate ? strtotime($endDate . " 23:59:59") : null;
            foreach ($purchaseOrders as $po) {
                $poTimestamp = strtotime($po['order_date']); // Filter by order_date
                 $includePO = true;
                if ($startDateTime && $poTimestamp < $startDateTime) {
                    $includePO = false;
                }
                if ($endDateTime && $poTimestamp > $endDateTime) {
                    $includePO = false;
                }
                if ($includePO) {
                    $filteredPOs[] = $po;
                }
            }
        } else {
            $filteredPOs = $purchaseOrders;
        }

        $statusCounts = [];
        $totalPoValue = 0;
        $poByVendor = [];
        $itemsOrderedCount = 0;

        foreach($filteredPOs as $po) {
            $status = $po['status'] ?? 'Unknown';
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            
            $poValue = 0;
            if(isset($po['items']) && is_array($po['items'])){
                foreach($po['items'] as $item){
                    $poValue += ($item['quantity_ordered'] ?? 0) * ($item['unit_price'] ?? 0);
                    $itemsOrderedCount += ($item['quantity_ordered'] ?? 0);
                }
            }
            $totalPoValue += $poValue;

            $vendor = $po['vendor_name'] ?? 'Unknown Vendor';
            $poByVendor[$vendor] = ($poByVendor[$vendor] ?? 0) + 1; 
        }
        arsort($statusCounts);
        arsort($poByVendor);

        return [
            'total_purchase_orders' => count($filteredPOs),
            'po_by_status' => $statusCounts,
            'total_po_value_all' => $totalPoValue, 
            'po_count_by_vendor' => $poByVendor,
            'total_items_ordered' => $itemsOrderedCount,
            'average_po_value' => count($filteredPOs) > 0 ? $totalPoValue / count($filteredPOs) : 0,
        ];
    }
}