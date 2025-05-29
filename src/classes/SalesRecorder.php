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
            return false;
        }
        $json = json_encode($sales, JSON_PRETTY_PRINT);
        return file_put_contents($this->salesFilePath, $json) !== false;
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

        $saleEntry = [
            "sale_id" => $saleId,
            "timestamp" => date("Y-m-d H:i:s"),
            "cashier_username" => $cashierUsername,
            "items" => [],
            "total_amount" => round($totalAmount, 2),
            "payment_details" => [
                "method" => $paymentMethod,
                "amount_given" => ($paymentMethod === 'Cash' && $amountGiven !== null) ? round($amountGiven, 2) : null,
                "change_due" => ($paymentMethod === 'Cash' && $amountGiven !== null && $amountGiven >= $totalAmount) ? round($amountGiven - $totalAmount, 2) : null,
                "reference_number" => $referenceNumber
            ]
        ];

        foreach ($cartItems as $item) {
            $saleEntry['items'][] = [
                "sku" => $item['sku'],
                "name" => $item['name'],
                "quantity" => $item['quantity'],
                "price_per_unit" => round($item['price'], 2),
                "subtotal" => round($item['price'] * $item['quantity'], 2)
            ];
        }

        $sales[] = $saleEntry;
        return $this->saveSales($sales);
    }

    public function getAllSales(): array
    {
        return $this->loadSales();
    }
}