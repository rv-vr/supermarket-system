<?php
require_once __DIR__ . '/../classes/UserRole.php';
require_once __DIR__ . '/../classes/User.php';

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

$currentManager = $_SESSION['user'];

// Placeholder for any data needed for the reports overview page
// For example, counts or quick stats if desired in the future.

$feedbackMessage = $_SESSION['feedback_message'] ?? null;
$feedbackType = $_SESSION['feedback_type'] ?? null;
if ($feedbackMessage) {
    unset($_SESSION['feedback_message']);
    unset($_SESSION['feedback_type']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Reports</title>
    <link rel="stylesheet" href="../../public/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .report-card {
            margin-bottom: 20px;
        }
        .report-card .card-body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 180px; /* Increased height for more content */
        }
        .report-card .card-title .material-symbols-outlined {
            font-size: 1.5em;
            margin-right: 0.3em;
            vertical-align: sub;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center my-4">
            <h1><span class="material-symbols-outlined" style="font-size: 1.2em; vertical-align: bottom;">assessment</span> Manager Reports</h1>
            <a href="index.php" class="btn btn-outline-secondary">
                <span class="material-symbols-outlined">arrow_back</span> Back to Dashboard
            </a>
        </div>

        <?php if ($feedbackMessage): ?>
            <div class="alert alert-<?php echo htmlspecialchars($feedbackType); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($feedbackMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-6 col-lg-4">
                <div class="card report-card text-center">
                    <div class="card-body">
                        <h5 class="card-title"><span class="material-symbols-outlined">receipt_long</span>Sales Reports</h5>
                        <p class="card-text">View transaction history, daily sales summaries, and product performance.</p>
                        <a href="report_sales.php" class="btn btn-info">View Sales Reports</a> <!-- Enabled link -->
                        <!-- <small class="text-muted d-block mt-2">(Coming Soon)</small> -->
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card report-card text-center">
                    <div class="card-body">
                        <h5 class="card-title"><span class="material-symbols-outlined">inventory</span>Inventory Reports</h5>
                        <p class="card-text">Track stock levels, view low stock items, and analyze product turnover.</p>
                        <a href="report_inventory.php" class="btn btn-info">View Inventory Reports</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card report-card text-center">
                    <div class="card-body">
                        <h5 class="card-title"><span class="material-symbols-outlined">local_shipping</span>Shipment & PO Reports</h5>
                        <p class="card-text">Monitor purchase order statuses, vendor performance, and received goods.</p>
                        <a href="report_shipments.php" class="btn btn-info">View Shipment Reports</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card report-card text-center">
                    <div class="card-body">
                        <h5 class="card-title"><span class="material-symbols-outlined">manage_accounts</span>User Activity Reports</h5>
                        <p class="card-text">Review employee attendance, time logs, and system access patterns.</p>
                        <a href="report_user_activity.php" class="btn btn-info">View User Activity</a>
                    </div>
                </div>
            </div>
            <!-- Add more report category cards as needed -->
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>