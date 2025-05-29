<?php
// Ensure UserRole and User classes are loaded
if (file_exists(__DIR__ . '/../classes/UserRole.php')) {
    require_once __DIR__ . '/../classes/UserRole.php';
}
if (file_exists(__DIR__ . '/../classes/User.php')) {
    require_once __DIR__ . '/../classes/User.php';
}
// Include AttendanceManager
if (file_exists(__DIR__ . '/../classes/AttendanceManager.php')) {
    require_once __DIR__ . '/../classes/AttendanceManager.php';
}


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$currentUser = null;
$userAttendanceStatus = null; // Initialize
if (isset($_SESSION['user']) && $_SESSION['user'] instanceof User) {
    $currentUser = $_SESSION['user'];
    // Get current attendance status if AttendanceManager is available
    if (class_exists('AttendanceManager')) {
        $attendanceManager = new AttendanceManager();
        $userAttendanceStatus = $attendanceManager->getCurrentStatus($currentUser->getUsername());
    }
}

// Dynamically determine the base path for URL links to the project root
$project_root_fs = realpath(__DIR__ . '/../../'); // File system path to project root
$current_script_fs = realpath($_SERVER['SCRIPT_FILENAME']); // File system path to the currently executing script (e.g., profile.php or src/pos/index.php)

// Check if the current script is inside the 'src' directory.
// If it is (e.g. /supermarket-system/src/pos/index.php), links to the root need to go up two levels.
// If it's not (e.g. /supermarket-system/profile.php), links to the root are direct.
if (strpos(dirname($current_script_fs), $project_root_fs . DIRECTORY_SEPARATOR . 'src') === 0) {
    // Script is within a subdirectory of 'src', e.g., src/pos/, src/inventory/
    // dirname($current_script_fs) would be .../supermarket-system/src/pos
    $basePath = '../../';
} else {
    // Script is at the project root, e.g., profile.php
    $basePath = ''; // Or './' if preferred, but empty string works for root files.
}

// Determine button states
$disableTimeIn = false;
$disableTimeOut = true; // Default to disabled

if ($userAttendanceStatus) {
    if ($userAttendanceStatus['action'] === 'time_in') {
        $disableTimeIn = true;
        $disableTimeOut = false;
    } elseif ($userAttendanceStatus['action'] === 'time_out' || $userAttendanceStatus['status'] === 'Never Timed In') {
        $disableTimeIn = false;
        $disableTimeOut = true;
    }
} else {
    // If status couldn't be fetched, or user never timed in, allow time in, disable time out
    $disableTimeIn = false;
    $disableTimeOut = true;
}

?>
<!-- Google Material Symbols -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
<style>
    .material-symbols-outlined {
        vertical-align: middle; /* Align icons nicely with text */
        margin-right: 0.25em; /* Space between icon and text */
    }
    .navbar-nav .btn .material-symbols-outlined {
         margin-right: 0.1em; /* Slightly less margin for buttons in navbar */
    }
    .btn .material-symbols-outlined.no-text { /* If icon is used without text */
        margin-right: 0;
    }
    #liveClock { /* Style for the clock */
        font-size: 0.9em;
        margin-left: 15px;
    }
</style>

<nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo $basePath; ?>index.php">
            <span class="material-symbols-outlined">storefront</span> Supermarket System
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if ($currentUser) : ?>
                    <li class="nav-item">
                        <span class="navbar-text">
                            <span class="material-symbols-outlined">person</span> Welcome, <?php echo htmlspecialchars($currentUser->getFullName()); ?> (<?php echo htmlspecialchars($currentUser->getRole()->value); ?>)
                        </span>
                    </li>
                <?php endif; ?>
                 <li class="nav-item d-none d-lg-block">
                    <span id="liveClock" class="navbar-text"></span>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php if ($currentUser) : ?>
                    <li class="nav-item">
                        <form action="<?php echo $basePath; ?>time_action.php" method="POST" class="d-inline">
                            <input type="hidden" name="action" value="time_in">
                            <button type="submit" class="btn btn-success btn-sm me-2" <?php echo $disableTimeIn ? 'disabled' : ''; ?> onclick="return confirm('Are you sure you want to Time In?');">
                                <span class="material-symbols-outlined">login</span>Time In
                            </button>
                        </form>
                    </li>
                    <li class="nav-item">
                        <form action="<?php echo $basePath; ?>time_action.php" method="POST" class="d-inline">
                            <input type="hidden" name="action" value="time_out">
                            <button type="submit" class="btn btn-warning btn-sm me-2" <?php echo $disableTimeOut ? 'disabled' : ''; ?> onclick="return confirm('Are you sure you want to Time Out?');">
                                <span class="material-symbols-outlined">logout</span>Time Out
                            </button>
                        </form>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-info btn-sm me-2" href="<?php echo $basePath; ?>profile.php">
                            <span class="material-symbols-outlined">account_circle</span>Profile
                        </a>
                    </li>
                     <li class="nav-item">
                        <a class="btn btn-primary btn-sm me-2" href="<?php echo $basePath; ?>request_time_off.php">
                            <span class="material-symbols-outlined">event_busy</span>Time Off
                        </a>
                    </li>
                    <li class="nav-item">
                        <form action="<?php echo $basePath; ?>logout.php" method="post" class="d-inline">
                            <button type="submit" class="btn btn-danger btn-sm">
                                <span class="material-symbols-outlined">power_settings_new</span>Logout
                            </button>
                        </form>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<?php
// Placeholder for a simple alert message system after actions like time_in/time_out
// This should ideally use the new toast system if header is part of POS,
// or have its own consistent notification method.
// For now, keeping the old flash message for non-POS pages.
if (isset($_SESSION['flash_message']) && basename($_SERVER['PHP_SELF']) !== 'index.php' /* crude check if not on POS */) {
    $alertType = $_SESSION['flash_message_type'] ?? 'info'; // Use type from session or default to info
    echo '<div class="container"><div class="alert alert-' . htmlspecialchars($alertType) . '">' . htmlspecialchars($_SESSION['flash_message']) . '</div></div>';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_message_type']); // Clear the type as well
}
?>
<script>
    function updateClock() {
        const clockElement = document.getElementById('liveClock');
        if (clockElement) {
            const now = new Date();
            const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            const dateString = now.toLocaleDateString([], { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
            clockElement.textContent = `${dateString} ${timeString}`;
        }
    }
    // Update the clock every second
    setInterval(updateClock, 1000);
    // Initial call to display clock immediately
    updateClock();
</script>