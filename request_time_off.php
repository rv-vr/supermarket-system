<?php
require_once __DIR__ . '/src/classes/UserRole.php';
require_once __DIR__ . '/src/classes/User.php';
require_once __DIR__ . '/src/classes/TimeOffManager.php'; // Include the TimeOffManager

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user']) || !($_SESSION['user'] instanceof User)) {
    header('Location: login.php');
    exit();
}
$user = $_SESSION['user'];
$username = $user->getUsername();
$fullName = $user->getFullName(); 

$feedbackMessage = '';
$feedbackType = '';

$timeOffManager = new TimeOffManager(); // Instantiate the manager

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $startDate = $_POST['start_date'] ?? null;
    $endDate = $_POST['end_date'] ?? null;
    $reason = trim($_POST['reason'] ?? '');
    // $requestTimestamp = date('Y-m-d H:i:s'); // TimeOffManager handles this

    if (empty($startDate) || empty($endDate) || empty($reason)) {
        $feedbackMessage = "All fields (Start Date, End Date, Reason) are required.";
        $feedbackType = "danger";
    } elseif (strtotime($startDate) > strtotime($endDate)) {
        $feedbackMessage = "End Date cannot be before Start Date.";
        $feedbackType = "danger";
    } elseif (strtotime($startDate) < strtotime(date('Y-m-d'))) {
        $feedbackMessage = "Start Date cannot be in the past.";
        $feedbackType = "danger";
    } else {
        // Use TimeOffManager to submit the request
        $result = $timeOffManager->submitRequest($username, $startDate, $endDate, $reason);

        if ($result['success']) {
            $feedbackMessage = $result['message']; // "Time off request submitted successfully. Status: Pending."
            $feedbackType = "success";
        } else {
            $feedbackMessage = $result['message'] ?? "Error submitting your request. Please try again.";
            $feedbackType = "danger";
        }
    }
}

// Load user's existing requests for display using TimeOffManager
// The getAllRequests method in TimeOffManager already sorts by requested_on descending.
$allUserRequests = $timeOffManager->getAllRequests(null, $username);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Time Off - <?php echo htmlspecialchars($fullName); ?></title>
    <link rel="stylesheet" href="public/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .status-pending { color: orange; font-weight: bold; }
        .status-approved { color: green; font-weight: bold; }
        .status-denied { color: red; font-weight: bold; } /* Changed from rejected to denied for consistency */
        .request-history-table th, .request-history-table td { vertical-align: middle; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/src/includes/header.php'; ?>
    <div class="container">
        <h1 class="my-4">Request Time Off</h1>

        <?php if ($feedbackMessage): ?>
            <div class="alert alert-<?php echo $feedbackType; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($feedbackMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">
                <h4><span class="material-symbols-outlined">event_busy</span> Submit New Request</h4>
            </div>
            <div class="card-body">
                <form action="request_time_off.php" method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="start_date" class="form-label">Start Date:</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="end_date" class="form-label">End Date:</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for Leave:</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <span class="material-symbols-outlined">send</span> Submit Request
                    </button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h4><span class="material-symbols-outlined">history</span> Your Time Off Requests</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($allUserRequests)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover request-history-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Requested On</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Action By</th>
                                    <th>Action On</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allUserRequests as $request): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($request['requested_on']))); ?></td>
                                        <td><?php echo htmlspecialchars(date('M d, Y', strtotime($request['start_date']))); ?></td>
                                        <td><?php echo htmlspecialchars(date('M d, Y', strtotime($request['end_date']))); ?></td>
                                        <td><?php echo nl2br(htmlspecialchars($request['reason'])); ?></td>
                                        <td>
                                            <span class="status-<?php echo strtolower(htmlspecialchars($request['status'])); ?>">
                                                <?php echo htmlspecialchars($request['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($request['action_taken_by'] ?? 'N/A'); ?></td>
                                        <td><?php echo isset($request['action_taken_on']) ? htmlspecialchars(date('M d, Y H:i', strtotime($request['action_taken_on']))) : 'N/A'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">You have not submitted any time off requests yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <p class="mt-4"><a href="index.php" class="btn btn-secondary"><span class="material-symbols-outlined">arrow_back</span> Back to Dashboard</a></p>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');

        if(startDateInput && endDateInput) {
            startDateInput.addEventListener('change', function() {
                if (this.value) {
                    endDateInput.min = this.value;
                    if (endDateInput.value && endDateInput.value < this.value) {
                        endDateInput.value = this.value;
                    }
                }
            });
            if (startDateInput.value) {
                 endDateInput.min = startDateInput.value;
            }
        }
    </script>
</body>
</html>