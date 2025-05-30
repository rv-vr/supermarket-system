<?php
require_once __DIR__ . '/../classes/UserRole.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/ReportGenerator.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user']) || !($_SESSION['user'] instanceof User) || $_SESSION['user']->getRole() !== UserRole::Manager) {
    $_SESSION['feedback_message'] = 'Access Denied. Manager account required.';
    $_SESSION['feedback_type'] = 'danger';
    header('Location: ../../login.php');
    exit();
}

$reportGenerator = new ReportGenerator();
$inventorySummary = $reportGenerator->getInventorySummary();
$reportTitle = "Inventory Report";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($reportTitle); ?></title>
    <link rel="stylesheet" href="../../public/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Same print styles as report_sales.php */
        .report-section { margin-bottom: 2rem; padding: 1.5rem; border: 1px solid #ddd; border-radius: 0.5rem; background-color: #f9f9f9;}
        .table th, .table td { vertical-align: middle; } .table-sm th, .table-sm td { padding: 0.4rem; }
        .print-only { display: none; }
        @media print {
            body { font-size: 10pt; margin: 0; padding: 0; background-color: #fff; }
            .no-print, .no-print * { display: none !important; }
            .container { width: 100% !important; margin: 0 !important; padding: 0 !important; max-width: none !important; }
            .report-section { border: 1px solid #ccc !important; padding: 0.5rem !important; margin-bottom: 1rem !important; background-color: #fff !important; page-break-inside: avoid; }
            .card { border: 1px solid #eee !important; box-shadow: none !important; }
            .table { font-size: 9pt; border-collapse: collapse !important; width: 100% !important; }
            .table th, .table td { border: 1px solid #ddd !important; padding: 0.25rem !important; }
            .table thead.table-light th { background-color: #f8f9fa !important; -webkit-print-color-adjust: exact; color-adjust: exact; }
            h1, h4, h5 { page-break-after: avoid; color: #000 !important; }
            a { text-decoration: none; color: #000 !important; } a[href]:after { content: none !important; }
            .print-header { display: block !important; text-align: center; margin-bottom: 20px; }
            .print-header h3 { margin-bottom: 5px; } .print-header p { margin-bottom: 15px; font-size: 0.9em; }
            .print-only { display: block !important; }
            .print-footer { display: block !important; text-align: center; font-size: 0.8em; margin-top: 20px; position: fixed; bottom: 10px; width:100%; }
        }
    </style>
</head>
<body>
    <div class="print-header print-only">
        <h3>Supermarket System - <?php echo htmlspecialchars($reportTitle); ?></h3>
        <p>Generated on: <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>
    <div class="no-print"><?php include __DIR__ . '/../includes/header.php'; ?></div>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center my-4 no-print">
            <h1><span class="material-symbols-outlined" style="font-size: 1.2em; vertical-align: bottom;">inventory</span> <?php echo htmlspecialchars($reportTitle); ?></h1>
            <div>
                <button class="btn btn-success" onclick="window.print();"><span class="material-symbols-outlined">print</span> Print Report</button>
                <a href="reports.php" class="btn btn-outline-secondary ms-2"><span class="material-symbols-outlined">arrow_back</span> Back to Reports Hub</a>
            </div>
        </div>

        <div class="report-section">
            <h4>Overall Inventory Summary</h4>
            <div class="row">
                <div class="col-md-3"><div class="card text-center bg-light p-3 mb-2"><h5>Total Products</h5><p class="fs-4 fw-bold"><?php echo number_format($inventorySummary['total_products']); ?></p></div></div>
                <div class="col-md-3"><div class="card text-center bg-light p-3 mb-2"><h5>Total Stock Value</h5><p class="fs-4 fw-bold">$<?php echo number_format($inventorySummary['total_stock_value'], 2); ?></p></div></div>
                <div class="col-md-3"><div class="card text-center bg-warning p-3 mb-2"><h5>Low Stock Items</h5><p class="fs-4 fw-bold"><?php echo number_format($inventorySummary['low_stock_items_count']); ?></p></div></div>
                <div class="col-md-3"><div class="card text-center bg-danger text-white p-3 mb-2"><h5>Out of Stock Items</h5><p class="fs-4 fw-bold"><?php echo number_format($inventorySummary['out_of_stock_items_count']); ?></p></div></div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-7">
                <div class="report-section">
                    <h4>Low Stock Items (Top 10)</h4>
                    <?php if (!empty($inventorySummary['low_stock_items_list'])): ?>
                        <table class="table table-sm table-striped table-hover">
                            <thead class="table-light"><tr><th>SKU</th><th>Name</th><th>Category</th><th class="text-end">Stock</th><th class="text-end">Reorder Level</th></tr></thead>
                            <tbody>
                            <?php foreach ($inventorySummary['low_stock_items_list'] as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['sku']); ?></td>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['category'] ?? 'N/A'); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars($item['stock_quantity']); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars($item['reorder_level'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted">No items are currently low on stock.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-5">
                <div class="report-section">
                    <h4>Products by Category</h4>
                    <?php if (!empty($inventorySummary['products_by_category'])): ?>
                        <table class="table table-sm table-striped table-hover">
                            <thead class="table-light"><tr><th>Category</th><th class="text-end">Number of Products</th></tr></thead>
                            <tbody>
                            <?php foreach ($inventorySummary['products_by_category'] as $category => $count): ?>
                                <tr><td><?php echo htmlspecialchars($category); ?></td><td class="text-end"><?php echo number_format($count); ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted">No product category data available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="print-footer print-only"><p>&copy; <?php echo date('Y'); ?> Supermarket System. Page <span class="page-number"></span></p></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" class="no-print"></script>
</body>
</html>