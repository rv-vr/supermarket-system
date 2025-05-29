<?php
require_once __DIR__ . '/src/classes/UserRole.php';
require_once __DIR__ . '/src/classes/User.php';
require_once __DIR__ . '/src/classes/ScheduleManager.php';
require_once __DIR__ . '/src/classes/AttendanceManager.php';

// Set the default timezone to Philippine Time
date_default_timezone_set('Asia/Manila');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || !($_SESSION['user'] instanceof User)) {
    header('Location: login.php');
    exit();
}
$user = $_SESSION['user'];
$username = $user->getUsername();

$scheduleManager = new ScheduleManager();
$userSchedule = $scheduleManager->getUserSchedule($username);

$attendanceManager = new AttendanceManager();
$currentStatus = $attendanceManager->getCurrentStatus($username);
$attendanceSummary = $attendanceManager->getAttendanceSummary($username, 7); // Summary for last 7 days

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - <?php echo htmlspecialchars($user->getFullName()); ?></title>
    <link rel="stylesheet" href="public/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .profile-section { margin-bottom: 2rem; }
        .schedule-table th, .schedule-table td { padding: 0.5rem; }
        .status-timed-in { color: green; font-weight: bold; }
        .status-timed-out { color: red; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/src/includes/header.php'; ?>
    <div class="container">
        <h1 class="mb-4">Employee Profile</h1>

        <div class="row">
            <div class="col-md-4 profile-section">
                <div class="card">
                    <div class="card-header">
                        <h4><span class="material-symbols-outlined">badge</span> Employee Details</h4>
                    </div>
                    <div class="card-body">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($user->getFullName()); ?></p>
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($user->getUsername()); ?></p>
                        <p><strong>Role:</strong> <?php echo htmlspecialchars($user->getRole()->value); ?></p>
                    </div>
                </div>
            </div>

            <div class="col-md-8 profile-section">
                <div class="card">
                    <div class="card-header">
                        <h4><span class="material-symbols-outlined">person_check</span> Attendance Status</h4>
                    </div>
                    <div class="card-body">
                        <p><strong>Current Status:</strong>
                            <span class="<?php echo $currentStatus['action'] === 'time_in' ? 'status-timed-in' : 'status-timed-out'; ?>">
                                <?php echo htmlspecialchars($currentStatus['status']); ?>
                            </span>
                            <?php if ($currentStatus['timestamp']): ?>
                                <!-- The date function here will now use Asia/Manila for formatting -->
                                (since <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($currentStatus['timestamp']))); ?>)
                            <?php endif; ?>
                        </p>
                        <p><strong>Days with Activity (Last 7 days):</strong> <?php echo $attendanceSummary['total_timed_in_days']; ?></p>
                        <h5>Recent Activity:</h5>
                        <?php if (!empty($attendanceSummary['recent_activity'])): ?>
                            <ul class="list-group list-group-flush">
                            <?php foreach($attendanceSummary['recent_activity'] as $activity): ?>
                                <li class="list-group-item">
                                    <?php echo htmlspecialchars(ucfirst($activity['action'])); ?> at <?php echo htmlspecialchars($activity['timestamp']); ?>
                                    <!-- Note: $activity['timestamp'] from AttendanceManager is already formatted as 'M d, Y H:i' -->
                                </li>
                            <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted">No recent activity in the last 7 days.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="profile-section">
            <div class="card">
                <div class="card-header">
                    <h4><span class="material-symbols-outlined">calendar_month</span> Weekly Schedule</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($userSchedule)): ?>
                        <table class="table table-striped table-bordered schedule-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Day</th>
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $daysOfWeek = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
                                $scheduledDays = array_column($userSchedule, 'day');
                                ?>
                                <?php foreach ($daysOfWeek as $day): ?>
                                    <?php
                                    $daySchedule = null;
                                    foreach ($userSchedule as $s) {
                                        if ($s['day'] === $day) {
                                            $daySchedule = $s;
                                            break;
                                        }
                                    }
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($day); ?></td>
                                        <?php if ($daySchedule): ?>
                                            <td><?php echo htmlspecialchars($daySchedule['start_time']); ?></td>
                                            <td><?php echo htmlspecialchars($daySchedule['end_time']); ?></td>
                                            <td><?php echo htmlspecialchars($daySchedule['notes'] ?? ''); ?></td>
                                        <?php else: ?>
                                            <td colspan="3" class="text-muted text-center">Off Duty</td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted">No schedule assigned.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <p class="mt-4"><a href="index.php" class="btn btn-primary"><span class="material-symbols-outlined">arrow_back</span> Back to Dashboard</a></p>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>