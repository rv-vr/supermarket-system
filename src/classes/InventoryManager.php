<?php

class InventoryManager
{
    private string $productsFilePath;

    public function __construct()
    {
        $this->productsFilePath = __DIR__ . '/../data/products.json';
        if (!file_exists($this->productsFilePath)) {
            // Initialize with an empty array if the file doesn't exist
            file_put_contents($this->productsFilePath, json_encode([]));
        }
    }

    private function getAllProductsData()
    {
        $data = file_get_contents($this->productsFilePath);
        return json_decode($data, true) ?: [];
    }

    private function saveProductsData($products)
    {
        file_put_contents($this->productsFilePath, json_encode(array_values($products), JSON_PRETTY_PRINT));
    }

    public function getAllProducts()
    {
        return $this->getAllProductsData();
    }

    public function getProductBySku($sku)
    {
        $products = $this->getAllProductsData();
        foreach ($products as $product) {
            if (isset($product['sku']) && $product['sku'] === $sku) {
                return $product;
            }
        }
        return null;
    }

    public function updateStockLevel($sku, $newStockQuantity) // Renamed parameter for clarity
    {
        $products = $this->getAllProductsData();
        $productUpdated = false;
        foreach ($products as &$product) { // Use reference to modify directly
            if (isset($product['sku']) && $product['sku'] === $sku) {
                $product['stock_quantity'] = (int)$newStockQuantity; // Changed key
                $productUpdated = true;
                break;
            }
        }
        if ($productUpdated) {
            $this->saveProductsData($products);
            return true;
        }
        return false;
    }

    public function addProduct($sku, $name, $category, $price, $stockQuantity, $supplierInfo = '') // Renamed parameter
    {
        $products = $this->getAllProductsData();
        // Check if SKU already exists
        foreach ($products as $product) {
            if ($product['sku'] === $sku) {
                return false; // Or throw an exception
            }
        }
        $newProduct = [
            'sku' => $sku,
            'name' => $name,
            'category' => $category,
            'price' => (float)$price,
            'stock_quantity' => (int)$stockQuantity, // Changed key
            'supplier_info' => $supplierInfo,
            'last_updated' => date('Y-m-d H:i:s')
        ];
        $products[] = $newProduct;
        $this->saveProductsData($products);
        return true;
    }
    
    public function deductStock($sku, $quantityToDeduct)
    {
        $products = $this->getAllProductsData();
        $productIndex = -1; // Initialize to -1
        foreach ($products as $index => $product) { // Find index to modify
            if (isset($product['sku']) && $product['sku'] === $sku) {
                $productIndex = $index;
                break;
            }
        }

        if ($productIndex !== -1) {
            if ($products[$productIndex]['stock_quantity'] >= $quantityToDeduct) { // Changed key
                $products[$productIndex]['stock_quantity'] -= $quantityToDeduct; // Changed key
                $products[$productIndex]['last_updated'] = date('Y-m-d H:i:s');
                $this->saveProductsData($products);
                return true;
            } else {
                // Not enough stock
                return false; 
            }
        }
        // Product not found
        return false; 
    }

    public function receiveStock($sku, $quantityReceived)
    {
        $products = $this->getAllProductsData();
        $productIndex = -1;
        foreach ($products as $index => $product) {
            if (isset($product['sku']) && $product['sku'] === $sku) {
                $productIndex = $index;
                break;
            }
        }

        if ($productIndex !== -1) {
            $products[$productIndex]['stock_quantity'] = (int)($products[$productIndex]['stock_quantity'] ?? 0) + (int)$quantityReceived; // Changed key
            $products[$productIndex]['last_updated'] = date('Y-m-d H:i:s');
            $this->saveProductsData($products);
            return true;
        }
        // Product not found, should ideally not happen if PO items are valid
        error_log("Attempted to receive stock for non-existent SKU: " . $sku);
        return false; 
    }

    // This function is used by POS, ensure it uses the correct key
    public function checkStock($sku, $quantityNeeded)
    {
        $product = $this->getProductBySku($sku);
        if ($product && isset($product['stock_quantity'])) { // Changed key
            return $product['stock_quantity'] >= $quantityNeeded; // Changed key
        }
        return false;
    }

    // This function is used by POS to update stock after a sale
    // It expects a negative quantity for deduction.
    public function updateStock($sku, $quantityChange)
    {
        $products = $this->getAllProductsData();
        $productIndex = -1;
        foreach ($products as $index => $product) {
            if (isset($product['sku']) && $product['sku'] === $sku) {
                $productIndex = $index;
                break;
            }
        }

        if ($productIndex !== -1) {
            $products[$productIndex]['stock_quantity'] = (int)($products[$productIndex]['stock_quantity'] ?? 0) + (int)$quantityChange; // Changed key
            $products[$productIndex]['last_updated'] = date('Y-m-d H:i:s');
            $this->saveProductsData($products);
            return true;
        }
        return false;
    }

    /**
     * Searches products by SKU or name.
     *
     * @param string $searchTerm The term to search for.
     * @return array An array of matching products.
     */
    public function searchProducts($searchTerm) {
        $allProducts = $this->getAllProductsData();
        $filteredProducts = [];
        $lowerSearchTerm = strtolower(trim($searchTerm));

        if (empty($lowerSearchTerm)) {
            return []; // Or return all products if desired for an empty search
        }

        foreach ($allProducts as $product) {
            // Ensure keys exist before trying to access them
            $sku = isset($product['sku']) ? strtolower($product['sku']) : '';
            $name = isset($product['name']) ? strtolower($product['name']) : '';

            if (strpos($sku, $lowerSearchTerm) !== false || strpos($name, $lowerSearchTerm) !== false) {
                // Optionally, you might want to add a check for stock_quantity > 0 here
                // if you only want to return products that are in stock.
                // Example: if (($product['stock_quantity'] ?? 0) > 0 && (strpos($sku, $lowerSearchTerm) !== false || strpos($name, $lowerSearchTerm) !== false))
                $filteredProducts[] = $product;
            }
        }
        return $filteredProducts;
    }
}