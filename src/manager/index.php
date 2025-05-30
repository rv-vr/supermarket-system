<?php
require_once __DIR__ . '/../classes/UserRole.php';
require_once __DIR__ . '/../classes/User.php';
// We will create and include UserManager.php later
// require_once __DIR__ . '/../classes/UserManager.php'; 

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user']) || !($_SESSION['user'] instanceof User)) {
    header('Location: ../../login.php');
    exit();
}

$user = $_SESSION['user'];

if ($user->getRole() !== UserRole::Manager) {
    // Redirect to login or an appropriate error page if not a manager
    $_SESSION['feedback_message'] = 'Access Denied. Manager account required.';
    $_SESSION['feedback_type'] = 'danger';
    header('Location: ../../login.php');
    exit();
}

// Placeholder for manager-specific data loading
// $userManager = new UserManager();
// $allUsers = $userManager->getAllUsers(); 
// $pendingTimeOffRequests = ... ;
// $salesData = ... ;

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
    <link rel="stylesheet" href="../../public/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Manager Dashboard</title>
    <style>
        .dashboard-card {
            margin-bottom: 20px;
        }
        .dashboard-card .card-body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 150px;
        }
        .dashboard-card .card-title .material-symbols-outlined {
            font-size: 1.5em;
            margin-right: 0.3em;
            vertical-align: sub;
        }
         .table th, .table td { vertical-align: middle; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container">
        <h1 class="my-4">Manager Dashboard</h1>

        <?php if ($feedbackMessage): ?>
            <div class="alert alert-<?php echo htmlspecialchars($feedbackType); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($feedbackMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <h5 class="card-title"><span class="material-symbols-outlined">group</span>User Management</h5>
                        <p class="card-text">Create, edit, and delete user accounts.</p>
                        <a href="manage_users.php" class="btn btn-primary">Manage Users</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <h5 class="card-title"><span class="material-symbols-outlined">summarize</span>Reports</h5>
                        <p class="card-text">Generate sales, shipment, and other reports.</p>
                        <a href="reports.php" class="btn btn-primary">View Reports</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <h5 class="card-title"><span class="material-symbols-outlined">event_available</span>Schedule Management</h5>
                        <p class="card-text">Approve time off requests and manage schedules.</p>
                        <a href="manage_schedule.php" class="btn btn-primary">Manage Schedules</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <h5 class="card-title"><span class="material-symbols-outlined">store</span>Vendor Management</h5>
                        <p class="card-text">Add new vendors to the system.</p>
                        <a href="manage_vendors.php" class="btn btn-primary">Manage Vendors</a>
                    </div>
                </div>
            </div>
            <!-- Add more cards for other sections as needed -->
        </div>

        <!-- Future sections for quick stats or alerts can go here -->

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>