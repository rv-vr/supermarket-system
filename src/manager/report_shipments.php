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
$filterStartDate = $_GET['start_date'] ?? null;
$filterEndDate = $_GET['end_date'] ?? null;
if ($filterStartDate && !preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $filterStartDate)) { $filterStartDate = null; }
if ($filterEndDate && !preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $filterEndDate)) { $filterEndDate = null; }

$poSummary = $reportGenerator->getPurchaseOrderStatusSummary($filterStartDate, $filterEndDate);
$reportTitle = "Shipment & PO Report";
if ($filterStartDate && $filterEndDate) { $reportTitle .= " (from " . htmlspecialchars($filterStartDate) . " to " . htmlspecialchars($filterEndDate) . ")"; }
elseif ($filterStartDate) { $reportTitle .= " (from " . htmlspecialchars($filterStartDate) . ")"; }
elseif ($filterEndDate) { $reportTitle .= " (up to " . htmlspecialchars($filterEndDate) . ")"; }
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
            <h1><span class="material-symbols-outlined" style="font-size: 1.2em; vertical-align: bottom;">local_shipping</span> <?php echo htmlspecialchars($reportTitle); ?></h1>
            <div>
                <button class="btn btn-success" onclick="window.print();"><span class="material-symbols-outlined">print</span> Print Report</button>
                <a href="reports.php" class="btn btn-outline-secondary ms-2"><span class="material-symbols-outlined">arrow_back</span> Back to Reports Hub</a>
            </div>
        </div>
        
        <div class="report-section no-print">
            <h4>Filter by PO Order Date</h4>
            <form action="report_shipments.php" method="GET" class="row g-3 align-items-end">
                <div class="col-md-4"><label for="start_date" class="form-label">Start Date:</label><input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($filterStartDate ?? ''); ?>"></div>
                <div class="col-md-4"><label for="end_date" class="form-label">End Date:</label><input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($filterEndDate ?? ''); ?>"></div>
                <div class="col-md-auto"><button type="submit" class="btn btn-primary">Apply</button><a href="report_shipments.php" class="btn btn-outline-secondary ms-2">Clear</a></div>
            </form>
        </div>
        <div class="print-only mb-3">
             <?php if ($filterStartDate && $filterEndDate): ?><p><strong>Date Range (PO Order Date):</strong> <?php echo htmlspecialchars($filterStartDate); ?> to <?php echo htmlspecialchars($filterEndDate); ?></p><?php elseif ($filterStartDate): ?><p><strong>Date Range (PO Order Date):</strong> From <?php echo htmlspecialchars($filterStartDate); ?></p><?php elseif ($filterEndDate): ?><p><strong>Date Range (PO Order Date):</strong> Up to <?php echo htmlspecialchars($filterEndDate); ?></p><?php else: ?><p><strong>Date Range (PO Order Date):</strong> All data.</p><?php endif; ?>
        </div>

        <div class="report-section">
            <h4>Overall Purchase Order Summary</h4>
            <div class="row">
                <div class="col-md-3"><div class="card text-center bg-light p-3 mb-2"><h5>Total POs</h5><p class="fs-4 fw-bold"><?php echo number_format($poSummary['total_purchase_orders']); ?></p></div></div>
                <div class="col-md-3"><div class="card text-center bg-light p-3 mb-2"><h5>Total PO Value</h5><p class="fs-4 fw-bold">$<?php echo number_format($poSummary['total_po_value_all'], 2); ?></p></div></div>
                <div class="col-md-3"><div class="card text-center bg-light p-3 mb-2"><h5>Avg. PO Value</h5><p class="fs-4 fw-bold">$<?php echo number_format($poSummary['average_po_value'], 2); ?></p></div></div>
                <div class="col-md-3"><div class="card text-center bg-light p-3 mb-2"><h5>Total Items Ordered</h5><p class="fs-4 fw-bold"><?php echo number_format($poSummary['total_items_ordered']); ?></p></div></div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="report-section">
                    <h4>Purchase Orders by Status</h4>
                    <?php if (!empty($poSummary['po_by_status'])): ?>
                        <table class="table table-sm table-striped table-hover">
                            <thead class="table-light"><tr><th>Status</th><th class="text-end">Number of POs</th></tr></thead>
                            <tbody>
                            <?php foreach ($poSummary['po_by_status'] as $status => $count): ?>
                                <tr><td><?php echo htmlspecialchars($status); ?></td><td class="text-end"><?php echo number_format($count); ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted">No PO data found for the selected period.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="report-section">
                    <h4>Purchase Orders by Vendor</h4>
                     <?php if (!empty($poSummary['po_count_by_vendor'])): ?>
                        <table class="table table-sm table-striped table-hover">
                            <thead class="table-light"><tr><th>Vendor</th><th class="text-end">Number of POs</th></tr></thead>
                            <tbody>
                            <?php foreach ($poSummary['po_count_by_vendor'] as $vendor => $count): ?>
                                <tr><td><?php echo htmlspecialchars($vendor); ?></td><td class="text-end"><?php echo number_format($count); ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted">No PO data found for vendors in the selected period.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="print-footer print-only"><p>&copy; <?php echo date('Y'); ?> Supermarket System. Page <span class="page-number"></span></p></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" class="no-print"></script>
</body>
</html>