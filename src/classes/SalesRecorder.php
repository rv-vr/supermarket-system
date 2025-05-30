<?php

class SalesRecorder
{
    private string $salesFilePath;

    public function __construct()
    {
        $this->salesFilePath = __DIR__ . '/../data/sales.json';
        if (!file_exists($this->salesFilePath)) {
            if (file_put_contents($this->salesFilePath, json_encode([])) === false) {
                // Handle error
            }
        }
    }

    private function loadSales(): array
    {
        if (!file_exists($this->salesFilePath) || !is_readable($this->salesFilePath)) {
            return [];
        }
        $json = file_get_contents($this->salesFilePath);
        return json_decode($json, true) ?? [];
    }

    private function saveSales(array $sales): bool
    {
        if (!is_writable(dirname($this->salesFilePath)) || (file_exists($this->salesFilePath) && !is_writable($this->salesFilePath))) {
            error_log("SalesRecorder: Sales data directory or file is not writable."); // Added error log
            return false;
        }
        $json = json_encode($sales, JSON_PRETTY_PRINT);
        if ($json === false) {
            error_log("SalesRecorder: Failed to encode sales to JSON. Error: " . json_last_error_msg());
            return false;
        }
        return file_put_contents($this->salesFilePath, $json, LOCK_EX) !== false; // Added LOCK_EX
    }

    public function recordSale(
        string $cashierUsername,
        array $cartItems, // Expects items with 'sku', 'name', 'quantity', 'price'
        float $totalAmount,
        string $paymentMethod,
        ?float $amountGiven = null,
        ?string $referenceNumber = null
    ): bool {
        $sales = $this->loadSales();
        $saleId = "S" . date("YmdHis") . substr(uniqid(), -4); // Unique Sale ID

        $itemsForRecord = []; // Initialize the structured items array
        foreach ($cartItems as $item) {
            $itemsForRecord[] = [
                "sku" => $item['sku'],
                "name" => $item['name'],
                "quantity" => $item['quantity'],
                "price_per_unit" => round($item['price'], 2),
                "subtotal" => round($item['price'] * $item['quantity'], 2)
            ];
        }

        $newSale = [
            'transaction_id' => uniqid('sale_'),
            'timestamp' => date('Y-m-d H:i:s'),
            'cashier_username' => $cashierUsername,
            'items' => $itemsForRecord, // Use the structured items
            'total_amount' => $totalAmount,
            'payment_method' => $paymentMethod,
            'amount_given' => $amountGiven,
            'change_due' => ($paymentMethod === 'Cash' && $amountGiven !== null) ? ($amountGiven - $totalAmount) : null,
            'reference_number' => $referenceNumber
        ];

        $sales[] = $newSale;
        return $this->saveSales($sales);
    }

    public function getAllSales(): array
    {
        return $this->loadSales();
    }
}