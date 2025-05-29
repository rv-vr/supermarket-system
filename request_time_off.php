<?php
require_once __DIR__ . '/src/classes/UserRole.php';
require_once __DIR__ . '/src/classes/User.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set the default timezone (important for date consistency)
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user']) || !($_SESSION['user'] instanceof User)) {
    header('Location: login.php');
    exit();
}
$user = $_SESSION['user'];
$username = $user->getUsername();
$fullName = $user->getFullName(); // Get full name for the request

$feedbackMessage = '';
$feedbackType = '';
$timeOffRequestsFile = __DIR__ . '/src/data/time_off_requests.json';

// Function to load requests
function loadTimeOffRequests(string $filePath): array {
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return [];
    }
    $json = file_get_contents($filePath);
    return json_decode($json, true) ?? [];
}

// Function to save requests
function saveTimeOffRequests(string $filePath, array $requests): bool {
    return file_put_contents($filePath, json_encode($requests, JSON_PRETTY_PRINT));
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $startDate = $_POST['start_date'] ?? null;
    $endDate = $_POST['end_date'] ?? null;
    $reason = trim($_POST['reason'] ?? '');
    $requestTimestamp = date('Y-m-d H:i:s');

    // Basic Validation
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
        $allRequests = loadTimeOffRequests($timeOffRequestsFile);
        $newRequest = [
            'id' => uniqid('req_', true), // Unique ID for the request
            'username' => $username,
            'fullName' => $fullName,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => htmlspecialchars($reason), // Sanitize reason
            'requested_at' => $requestTimestamp,
            'status' => 'Pending' // Default status
        ];

        $allRequests[] = $newRequest;

        if (saveTimeOffRequests($timeOffRequestsFile, $allRequests)) {
            $feedbackMessage = "Time off request submitted successfully. Status: Pending.";
            $feedbackType = "success";
        } else {
            $feedbackMessage = "Error submitting your request. Please try again.";
            $feedbackType = "danger";
        }
    }
}

// Load user's existing requests for display
$allUserRequests = [];
$loadedRequests = loadTimeOffRequests($timeOffRequestsFile);
foreach ($loadedRequests as $req) {
    if (isset($req['username']) && $req['username'] === $username) {
        $allUserRequests[] = $req;
    }
}
// Sort by requested_at descending
usort($allUserRequests, function ($a, $b) {
    return strtotime($b['requested_at']) - strtotime($a['requested_at']);
});


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
        .status-rejected { color: red; font-weight: bold; }
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
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allUserRequests as $request): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($request['requested_at']))); ?></td>
                                        <td><?php echo htmlspecialchars(date('M d, Y', strtotime($request['start_date']))); ?></td>
                                        <td><?php echo htmlspecialchars(date('M d, Y', strtotime($request['end_date']))); ?></td>
                                        <td><?php echo nl2br(htmlspecialchars($request['reason'])); ?></td>
                                        <td>
                                            <span class="status-<?php echo strtolower(htmlspecialchars($request['status'])); ?>">
                                                <?php echo htmlspecialchars($request['status']); ?>
                                            </span>
                                        </td>
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
        // Optional: JavaScript to ensure end_date is not before start_date dynamically
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
            // Set initial min for end_date if start_date has a value on load (e.g. after form error)
            if (startDateInput.value) {
                 endDateInput.min = startDateInput.value;
            }
        }
    </script>
</body>
</html>