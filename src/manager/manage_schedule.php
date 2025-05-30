<?php
require_once __DIR__ . '/../classes/UserRole.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/UserManager.php'; // To get list of users
require_once __DIR__ . '/../classes/ScheduleManager.php';
require_once __DIR__ . '/../classes/TimeOffManager.php'; // Added

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

$userManager = new UserManager();
$scheduleManager = new ScheduleManager();
$timeOffManager = new TimeOffManager(); // Added

$feedbackMessage = $_SESSION['feedback_message'] ?? null;
$feedbackType = $_SESSION['feedback_type'] ?? null;
if ($feedbackMessage) {
    unset($_SESSION['feedback_message']);
    unset($_SESSION['feedback_type']);
}

$currentManagerUsername = $_SESSION['user']->getUsername();

// Handle Schedule Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_schedule') {
    $usernameToUpdate = $_POST['username'] ?? null;
    $schedulesInput = $_POST['schedule'] ?? []; 

    if ($usernameToUpdate) {
        $dailySchedules = [];
        $daysOfWeek = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
        foreach ($daysOfWeek as $day) {
            if (isset($schedulesInput[$day])) {
                if (!empty($schedulesInput[$day]['start_time']) || !empty($schedulesInput[$day]['end_time']) || !empty($schedulesInput[$day]['notes'])) {
                    $dailySchedules[] = [
                        'day' => $day,
                        'start_time' => trim($schedulesInput[$day]['start_time']),
                        'end_time' => trim($schedulesInput[$day]['end_time']),
                        'notes' => trim($schedulesInput[$day]['notes'])
                    ];
                }
            }
        }
        
        $result = $scheduleManager->setScheduleForUser($usernameToUpdate, $dailySchedules);
        $_SESSION['feedback_message'] = $result['message'];
        $_SESSION['feedback_type'] = $result['success'] ? 'success' : 'danger';
    } else {
        $_SESSION['feedback_message'] = 'Invalid user selected for schedule update.';
        $_SESSION['feedback_type'] = 'danger';
    }
    header('Location: manage_schedule.php' . ($usernameToUpdate ? '?edit_user=' . urlencode($usernameToUpdate) : ''));
    exit();
}

// Handle Time Off Request Action (Approve/Deny)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_time_off'])) {
    $requestId = $_POST['request_id'] ?? null;
    $timeOffAction = $_POST['time_off_submit_action'] ?? null; // 'Approve' or 'Deny'

    if ($requestId && $timeOffAction) {
        $newStatus = ($timeOffAction === 'Approve') ? TimeOffManager::STATUS_APPROVED : TimeOffManager::STATUS_DENIED;
        $result = $timeOffManager->updateRequestStatus($requestId, $newStatus, $currentManagerUsername);
        $_SESSION['feedback_message'] = $result['message'];
        $_SESSION['feedback_type'] = $result['success'] ? 'success' : 'danger';
    } else {
        $_SESSION['feedback_message'] = 'Invalid time off request action.';
        $_SESSION['feedback_type'] = 'danger';
    }
    header('Location: manage_schedule.php#time-off-requests'); // Redirect back to the section
    exit();
}


$allUsers = $userManager->getAllUsers();
$schedulableUsers = array_filter($allUsers, function($u) {
    return in_array($u['role'], [UserRole::Cashier->value, UserRole::Stocker->value]);
});

$userToEditSchedule = null;
$scheduleForEdit = [];
$daysOfWeek = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

if (isset($_GET['edit_user'])) {
    $usernameForEdit = trim($_GET['edit_user']);
    $foundUser = $userManager->findUserByUsername($usernameForEdit);
    if ($foundUser && in_array($foundUser['role'], [UserRole::Cashier->value, UserRole::Stocker->value])) {
        $userToEditSchedule = $foundUser;
        $rawSchedule = $scheduleManager->getUserSchedule($usernameForEdit);
        foreach ($daysOfWeek as $day) {
            $scheduleForEdit[$day] = ['start_time' => '', 'end_time' => '', 'notes' => '']; 
            foreach ($rawSchedule as $daySchedule) {
                if ($daySchedule['day'] === $day) {
                    $scheduleForEdit[$day] = $daySchedule;
                    break;
                }
            }
        }
    } else {
        $_SESSION['feedback_message'] = 'User not found or cannot have a schedule managed.';
        $_SESSION['feedback_type'] = 'warning';
    }
}

$pendingTimeOffRequests = $timeOffManager->getPendingRequests();
$processedTimeOffRequests = array_merge(
    $timeOffManager->getAllRequests(TimeOffManager::STATUS_APPROVED),
    $timeOffManager->getAllRequests(TimeOffManager::STATUS_DENIED)
);
// Sort processed requests by action_taken_on date, newest first
usort($processedTimeOffRequests, function($a, $b) {
    return strtotime($b['action_taken_on'] ?? 0) <=> strtotime($a['action_taken_on'] ?? 0);
});


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Employee Schedules & Time Off</title>
    <link rel="stylesheet" href="../../public/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .form-section { margin-bottom: 2rem; padding: 1.5rem; border: 1px solid #ddd; border-radius: 0.5rem; background-color: #f9f9f9;}
        .schedule-table th, .schedule-table td { vertical-align: middle; }
        .time-input { width: 120px; }
        .nav-tabs .nav-link.active { background-color: #e9ecef; border-color: #dee2e6 #dee2e6 #fff; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center my-4">
            <h1><span class="material-symbols-outlined" style="font-size: 1.2em; vertical-align: bottom;">event_available</span> Manage Schedules & Time Off</h1>
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

        <!-- Tabs for Schedule Editing and Time Off Requests -->
        <ul class="nav nav-tabs mb-3" id="scheduleTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="edit-schedule-tab" data-bs-toggle="tab" data-bs-target="#edit-schedule-content" type="button" role="tab" aria-controls="edit-schedule-content" aria-selected="true">
                    <span class="material-symbols-outlined">edit_calendar</span> Edit Schedules
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="time-off-tab" data-bs-toggle="tab" data-bs-target="#time-off-content" type="button" role="tab" aria-controls="time-off-content" aria-selected="false">
                    <span class="material-symbols-outlined">approval</span> Time Off Requests 
                    <?php if(count($pendingTimeOffRequests) > 0): ?>
                        <span class="badge bg-danger ms-1"><?php echo count($pendingTimeOffRequests); ?></span>
                    <?php endif; ?>
                </button>
            </li>
        </ul>

        <div class="tab-content" id="scheduleTabsContent">
            <!-- Edit Schedule Form Section -->
            <div class="tab-pane fade show active" id="edit-schedule-content" role="tabpanel" aria-labelledby="edit-schedule-tab">
                <div class="form-section">
                    <h4><?php echo $userToEditSchedule ? 'Edit Schedule for: ' . htmlspecialchars($userToEditSchedule['fullName']) . ' (' . htmlspecialchars($userToEditSchedule['username']) . ')' : 'Select User to Edit Schedule'; ?></h4>
                    
                    <?php if (!$userToEditSchedule && !isset($_GET['edit_user'])): ?>
                        <p class="text-muted">Select a user from the list below to edit their schedule.</p>
                    <?php elseif (isset($_GET['edit_user']) && !$userToEditSchedule): ?>
                         <div class="alert alert-warning">User "<?php echo htmlspecialchars(trim($_GET['edit_user'])); ?>" not found or cannot be scheduled.</div>
                    <?php endif; ?>

                    <?php if ($userToEditSchedule): ?>
                    <form action="manage_schedule.php?edit_user=<?php echo urlencode($userToEditSchedule['username']); ?>" method="POST">
                        <input type="hidden" name="action" value="update_schedule">
                        <input type="hidden" name="username" value="<?php echo htmlspecialchars($userToEditSchedule['username']); ?>">
                        <table class="table table-bordered schedule-table">
                            <thead class="table-light">
                                <tr><th>Day</th><th>Start Time (HH:MM)</th><th>End Time (HH:MM)</th><th>Notes</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($daysOfWeek as $day): 
                                    $currentDaySchedule = $scheduleForEdit[$day] ?? ['start_time' => '', 'end_time' => '', 'notes' => ''];
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($day); ?></td>
                                    <td><input type="time" class="form-control time-input" name="schedule[<?php echo $day; ?>][start_time]" value="<?php echo htmlspecialchars($currentDaySchedule['start_time']); ?>"></td>
                                    <td><input type="time" class="form-control time-input" name="schedule[<?php echo $day; ?>][end_time]" value="<?php echo htmlspecialchars($currentDaySchedule['end_time']); ?>"></td>
                                    <td><input type="text" class="form-control" name="schedule[<?php echo $day; ?>][notes]" value="<?php echo htmlspecialchars($currentDaySchedule['notes']); ?>" placeholder="e.g., Opening shift"></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="submit" class="btn btn-primary mt-3"><span class="material-symbols-outlined">save</span> Update Schedule</button>
                        <a href="manage_schedule.php" class="btn btn-secondary mt-3">Cancel Edit / Clear Selection</a>
                    </form>
                    <?php endif; ?>
                </div>

                <!-- User List with Schedules -->
                <div class="card mt-4">
                    <div class="card-header"><h4><span class="material-symbols-outlined">list</span> Employee Schedules Overview</h4></div>
                    <div class="card-body">
                        <?php if (!empty($schedulableUsers)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-sm">
                                    <thead class="table-light"><tr><th>Username</th><th>Full Name</th><th>Role</th><th>Schedule Summary</th><th>Actions</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($schedulableUsers as $u): $userSched = $scheduleManager->getUserSchedule($u['username']); ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($u['username']); ?></td>
                                                <td><?php echo htmlspecialchars($u['fullName'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($u['role']); ?></td>
                                                <td>
                                                    <?php if (!empty($userSched)): ?>
                                                        <ul class="list-unstyled mb-0 small">
                                                        <?php foreach ($userSched as $sDay): ?>
                                                            <li><strong><?php echo htmlspecialchars($sDay['day']); ?>:</strong> <?php echo htmlspecialchars($sDay['start_time'] ?: '--:--'); ?> - <?php echo htmlspecialchars($sDay['end_time'] ?: '--:--'); ?><?php if(!empty($sDay['notes'])): ?> (<?php echo htmlspecialchars($sDay['notes']); ?>)<?php endif; ?></li>
                                                        <?php endforeach; ?></ul>
                                                    <?php else: ?><span class="text-muted">No schedule set.</span><?php endif; ?>
                                                </td>
                                                <td><a href="manage_schedule.php?edit_user=<?php echo urlencode($u['username']); ?>" class="btn btn-sm btn-warning" title="Edit Schedule"><span class="material-symbols-outlined">edit_calendar</span></a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?><p class="text-muted">No users found that can be scheduled (e.g., Cashiers, Stockers).</p><?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Time Off Requests Section -->
            <div class="tab-pane fade" id="time-off-content" role="tabpanel" aria-labelledby="time-off-tab">
                <div class="form-section" id="time-off-requests">
                    <h4>Pending Time Off Requests</h4>
                    <?php if (!empty($pendingTimeOffRequests)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light"><tr><th>User</th><th>Dates Requested</th><th>Reason</th><th>Requested On</th><th>Actions</th></tr></thead>
                                <tbody>
                                <?php foreach ($pendingTimeOffRequests as $req): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($req['username']); ?></td>
                                        <td><?php echo htmlspecialchars($req['start_date']); ?> to <?php echo htmlspecialchars($req['end_date']); ?></td>
                                        <td><?php echo nl2br(htmlspecialchars($req['reason'])); ?></td>
                                        <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($req['requested_on']))); ?></td>
                                        <td>
                                            <form action="manage_schedule.php#time-off-requests" method="POST" class="d-inline">
                                                <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($req['request_id']); ?>">
                                                <input type="hidden" name="action_time_off" value="true">
                                                <button type="submit" name="time_off_submit_action" value="Approve" class="btn btn-sm btn-success" title="Approve Request"><span class="material-symbols-outlined">thumb_up</span></button>
                                                <button type="submit" name="time_off_submit_action" value="Deny" class="btn btn-sm btn-danger" title="Deny Request"><span class="material-symbols-outlined">thumb_down</span></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No pending time off requests.</p>
                    <?php endif; ?>
                </div>

                <div class="form-section mt-4">
                    <h4>Processed Time Off Requests (Recent First)</h4>
                     <?php if (!empty($processedTimeOffRequests)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light"><tr><th>User</th><th>Dates</th><th>Reason</th><th>Status</th><th>Action By</th><th>Action On</th></tr></thead>
                                <tbody>
                                <?php foreach (array_slice($processedTimeOffRequests, 0, 20) as $req): // Show recent 20 ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($req['username']); ?></td>
                                        <td><?php echo htmlspecialchars($req['start_date']); ?> to <?php echo htmlspecialchars($req['end_date']); ?></td>
                                        <td><?php echo nl2br(htmlspecialchars($req['reason'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $req['status'] === TimeOffManager::STATUS_APPROVED ? 'success' : ($req['status'] === TimeOffManager::STATUS_DENIED ? 'danger' : 'secondary'); ?>">
                                                <?php echo htmlspecialchars($req['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($req['action_taken_by'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars(isset($req['action_taken_on']) ? date('Y-m-d H:i', strtotime($req['action_taken_on'])) : 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No processed time off requests found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Script to activate tab based on URL hash
        document.addEventListener('DOMContentLoaded', function() {
            var hash = window.location.hash;
            if (hash) {
                var triggerEl = document.querySelector('.nav-tabs button[data-bs-target="' + hash + '-content"]');
                if (!triggerEl) { // Fallback for #time-off-requests to #time-off-content
                    if (hash === '#time-off-requests') {
                         triggerEl = document.querySelector('.nav-tabs button[data-bs-target="#time-off-content"]');
                    }
                }
                if (triggerEl) {
                    var tab = new bootstrap.Tab(triggerEl);
                    tab.show();
                }
            }
        });
    </script>
</body>
</html>