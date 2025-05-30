<?php
require_once __DIR__ . '/../classes/UserRole.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/ReportGenerator.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user']) || !($_SESSION['user'] instanceof User) || $_SESSION['user']->getRole() !== UserRole::Manager) {
    $_SESSION['feedback_message'] = 'Access Denied. Manager account required.';
    $_SESSION['feedback_type'] = 'danger';
    header('Location: ../../login.php');
    exit();
}

$reportGenerator = new ReportGenerator();

// Date filtering
$filterStartDate = $_GET['start_date'] ?? null;
$filterEndDate = $_GET['end_date'] ?? null;

// Validate date format if provided (basic validation)
if ($filterStartDate && !preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $filterStartDate)) {
    $filterStartDate = null; // Invalid format
}
if ($filterEndDate && !preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $filterEndDate)) {
    $filterEndDate = null; // Invalid format
}


$salesSummary = $reportGenerator->getSalesSummary($filterStartDate, $filterEndDate);
$reportTitle = "Sales Report";
if ($filterStartDate && $filterEndDate) {
    $reportTitle .= " (from " . htmlspecialchars($filterStartDate) . " to " . htmlspecialchars($filterEndDate) . ")";
} elseif ($filterStartDate) {
    $reportTitle .= " (from " . htmlspecialchars($filterStartDate) . ")";
} elseif ($filterEndDate) {
    $reportTitle .= " (up to " . htmlspecialchars($filterEndDate) . ")";
}


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
        .report-section { margin-bottom: 2rem; padding: 1.5rem; border: 1px solid #ddd; border-radius: 0.5rem; background-color: #f9f9f9;}
        .table th, .table td { vertical-align: middle; }
        .table-sm th, .table-sm td { padding: 0.4rem; }
        .print-only { display: none; } /* Initially hidden */

        @media print {
            body {
                font-size: 10pt; /* Adjust base font size for print */
                margin: 0;
                padding: 0;
                background-color: #fff; /* Ensure white background */
            }
            .no-print, .no-print * {
                display: none !important; /* Hide elements marked with .no-print */
            }
            .container {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                max-width: none !important; /* Override Bootstrap container width */
            }
            .report-section {
                border: 1px solid #ccc !important; /* Lighter border for print */
                padding: 0.5rem !important;
                margin-bottom: 1rem !important;
                background-color: #fff !important; /* No background color for sections */
                page-break-inside: avoid; /* Try to avoid breaking sections across pages */
            }
            .card {
                border: 1px solid #eee !important;
                box-shadow: none !important;
            }
            .table {
                font-size: 9pt; /* Smaller font for tables in print */
                border-collapse: collapse !important; /* Ensure borders collapse */
                width: 100% !important;
            }
            .table th, .table td {
                border: 1px solid #ddd !important; /* Add borders to all table cells */
                padding: 0.25rem !important;
            }
            .table thead.table-light th { /* Ensure header is visible */
                background-color: #f8f9fa !important; /* Light gray, or remove for white */
                -webkit-print-color-adjust: exact; /* Force background color printing in Chrome/Safari */
                color-adjust: exact; /* Standard property */
            }
            h1, h4, h5 {
                page-break-after: avoid; /* Avoid breaking after headings */
                color: #000 !important; /* Ensure text is black */
            }
            a { text-decoration: none; color: #000 !important; } /* Remove underlines and color from links */
            a[href]:after { content: none !important; } /* Remove URL display after links */

            .print-header { display: block !important; text-align: center; margin-bottom: 20px; }
            .print-header h3 { margin-bottom: 5px; }
            .print-header p { margin-bottom: 15px; font-size: 0.9em; }
            .print-only { display: block !important; } /* Show elements meant only for print */
            .print-footer { display: block !important; text-align: center; font-size: 0.8em; margin-top: 20px; position: fixed; bottom: 10px; width:100%; }
        }
    </style>
</head>
<body>
    <div class="print-header print-only"> <!-- Header for print version -->
        <h3>Supermarket System - <?php echo htmlspecialchars($reportTitle); ?></h3>
        <p>Generated on: <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>

    <div class="no-print"> <!-- This div will be hidden during print -->
        <?php include __DIR__ . '/../includes/header.php'; ?>
    </div>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center my-4 no-print"> <!-- Actions bar, hidden on print -->
            <h1><span class="material-symbols-outlined" style="font-size: 1.2em; vertical-align: bottom;">receipt_long</span> Sales Reports</h1>
            <div>
                <button class="btn btn-success" onclick="window.print();">
                    <span class="material-symbols-outlined">print</span> Print Report
                </button>
                <a href="reports.php" class="btn btn-outline-secondary ms-2">
                    <span class="material-symbols-outlined">arrow_back</span> Back to Reports Hub
                </a>
            </div>
        </div>

        <!-- Date Filter Form - Hidden on print -->
        <div class="report-section no-print">
            <h4>Filter by Date Range</h4>
            <form action="report_sales.php" method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="start_date" class="form-label">Start Date:</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($filterStartDate ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label for="end_date" class="form-label">End Date:</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($filterEndDate ?? ''); ?>">
                </div>
                <div class="col-md-auto">
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                    <a href="report_sales.php" class="btn btn-outline-secondary ms-2">Clear Filter</a>
                </div>
            </form>
        </div>
        
        <div class="print-only mb-3"> <!-- Date range info for print version -->
             <?php if ($filterStartDate && $filterEndDate): ?>
                <p class="mt-2"><strong>Date Range:</strong> <?php echo htmlspecialchars($filterStartDate); ?> to <?php echo htmlspecialchars($filterEndDate); ?></p>
            <?php elseif ($filterStartDate): ?>
                 <p class="mt-2"><strong>Date Range:</strong> From <?php echo htmlspecialchars($filterStartDate); ?> onwards.</p>
            <?php elseif ($filterEndDate): ?>
                 <p class="mt-2"><strong>Date Range:</strong> Up to <?php echo htmlspecialchars($filterEndDate); ?>.</p>
            <?php else: ?>
                <p class="mt-2"><strong>Date Range:</strong> All sales data.</p>
            <?php endif; ?>
        </div>


        <!-- Sales Summary Section -->
        <div class="report-section">
            <h4>Overall Sales Summary</h4>
            <div class="row">
                <div class="col-md-4">
                    <div class="card text-center bg-light p-3 mb-2"> <!-- Added mb-2 for print spacing -->
                        <h5>Total Sales Amount</h5>
                        <p class="fs-4 fw-bold">$<?php echo number_format($salesSummary['total_sales_amount'], 2); ?></p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center bg-light p-3 mb-2">
                        <h5>Total Transactions</h5>
                        <p class="fs-4 fw-bold"><?php echo number_format($salesSummary['total_transactions']); ?></p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center bg-light p-3 mb-2">
                        <h5>Average Transaction Value</h5>
                        <p class="fs-4 fw-bold">$<?php echo number_format($salesSummary['average_transaction_value'], 2); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Sales by Cashier -->
            <div class="col-md-6">
                <div class="report-section">
                    <h4>Sales by Cashier</h4>
                    <?php if (!empty($salesSummary['sales_by_cashier'])): ?>
                        <table class="table table-sm table-striped table-hover">
                            <thead class="table-light"><tr><th>Cashier</th><th class="text-end">Total Sales</th></tr></thead>
                            <tbody>
                            <?php foreach ($salesSummary['sales_by_cashier'] as $cashier => $amount): ?>
                                <tr><td><?php echo htmlspecialchars($cashier); ?></td><td class="text-end">$<?php echo number_format($amount, 2); ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted">No sales data found for cashiers in the selected period.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sales by Payment Method -->
            <div class="col-md-6">
                <div class="report-section">
                    <h4>Sales by Payment Method</h4>
                     <?php if (!empty($salesSummary['sales_by_payment_method'])): ?>
                        <table class="table table-sm table-striped table-hover">
                            <thead class="table-light"><tr><th>Payment Method</th><th class="text-end">Total Sales</th></tr></thead>
                            <tbody>
                            <?php foreach ($salesSummary['sales_by_payment_method'] as $method => $amount): ?>
                                <tr><td><?php echo htmlspecialchars($method); ?></td><td class="text-end">$<?php echo number_format($amount, 2); ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted">No sales data found for payment methods in the selected period.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Top Selling Products -->
        <div class="report-section">
            <h4>Top Selling Products (by Revenue)</h4>
            <?php if (!empty($salesSummary['top_selling_products'])): ?>
                <table class="table table-sm table-striped table-hover">
                    <thead class="table-light"><tr><th>SKU</th><th>Product Name</th><th class="text-end">Quantity Sold</th><th class="text-end">Total Revenue</th></tr></thead>
                    <tbody>
                    <?php foreach ($salesSummary['top_selling_products'] as $sku => $productData): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sku); ?></td>
                            <td><?php echo htmlspecialchars($productData['name']); ?></td>
                            <td class="text-end"><?php echo number_format($productData['quantity_sold']); ?></td>
                            <td class="text-end">$<?php echo number_format($productData['total_revenue'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-muted">No product sales data found in the selected period.</p>
            <?php endif; ?>
        </div>

    </div>
    <div class="print-footer print-only">
        <p>&copy; <?php echo date('Y'); ?> Supermarket System. Page <span class="page-number"></span></p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" class="no-print"></script>
</body>
</html>