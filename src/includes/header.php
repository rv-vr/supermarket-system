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
    if (class_exists('AttendanceManager')) {
        $attendanceManager = new AttendanceManager();
        $userAttendanceStatus = $attendanceManager->getCurrentStatus($currentUser->getUsername());
    }
}

// Dynamically determine the base path for URL links to the project root
$project_root_fs = realpath(__DIR__ . '/../../'); 
$current_script_fs = realpath($_SERVER['SCRIPT_FILENAME']); 
$current_page_url = $_SERVER['REQUEST_URI']; // Get current page URL for redirect

if (strpos(dirname($current_script_fs), $project_root_fs . DIRECTORY_SEPARATOR . 'src') === 0) {
    $basePath = '../../';
} else {
    $basePath = ''; 
}

// Determine button states
$disableTimeIn = false;
$disableTimeOut = true; 

if ($userAttendanceStatus) {
    if ($userAttendanceStatus['action'] === 'time_in') {
        $disableTimeIn = true;
        $disableTimeOut = false;
    } elseif ($userAttendanceStatus['action'] === 'time_out' || $userAttendanceStatus['status'] === 'Never Timed In') {
        $disableTimeIn = false;
        $disableTimeOut = true;
    }
} else {
    $disableTimeIn = false;
    $disableTimeOut = true;
}

?>
<!-- Google Material Symbols -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
<style>
    .material-symbols-outlined {
        vertical-align: middle; 
        margin-right: 0.25em; 
    }
    .navbar-nav .btn .material-symbols-outlined {
         margin-right: 0.1em; 
    }
    .btn .material-symbols-outlined.no-text { 
        margin-right: 0;
    }
    #liveClock { 
        font-size: 0.9em;
        margin-left: 15px;
    }
    .dropdown-item.disabled, .dropdown-item:disabled { /* Ensure disabled dropdown items look disabled */
        pointer-events: none;
        opacity: 0.65;
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
                 <li class="nav-item d-none d-lg-block">
                    <span id="liveClock" class="navbar-text"></span>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                <?php if ($currentUser): ?>
                    <li class="nav-item">
                        <span class="navbar-text me-3">
                           <span class="material-symbols-outlined">person</span> Welcome, <?php echo htmlspecialchars($currentUser->getFullName()); ?> (<?php echo htmlspecialchars($currentUser->getRole()->value); ?>)
                        </span>
                    </li>
                    
                    <?php if ($currentUser->getRole() === UserRole::Manager): ?>
                        <li class="nav-item"><a class="nav-link" href="<?php echo $basePath; ?>src/manager/index.php"><span class="material-symbols-outlined">monitoring</span>Manager Dashboard</a></li>
                    <?php elseif ($currentUser->getRole() === UserRole::Stocker): ?>
                        <li class="nav-item"><a class="nav-link" href="<?php echo $basePath; ?>src/inventory/index.php"><span class="material-symbols-outlined">inventory_2</span>Inventory</a></li>
                    <?php elseif ($currentUser->getRole() === UserRole::Cashier): ?>
                        <li class="nav-item"><a class="nav-link" href="<?php echo $basePath; ?>src/pos/index.php"><span class="material-symbols-outlined">point_of_sale</span>POS</a></li>
                    <?php elseif ($currentUser->getRole() === UserRole::Vendor): ?>
                        <li class="nav-item"><a class="nav-link" href="<?php echo $basePath; ?>src/vendor/index.php"><span class="material-symbols-outlined">local_shipping</span>Vendor Portal</a></li>
                    <?php endif; ?>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="material-symbols-outlined">account_circle</span> Profile
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                            <li><a class="dropdown-item" href="<?php echo $basePath; ?>profile.php"><span class="material-symbols-outlined">badge</span> View Profile</a></li>
                            
                            <?php 
                            if ($currentUser && in_array($currentUser->getRole(), [UserRole::Cashier, UserRole::Stocker, UserRole::Manager])): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo $basePath; ?>request_time_off.php"><span class="material-symbols-outlined">event_busy</span> Request Time Off</a></li>
                                
                                <?php 
                                if (class_exists('AttendanceManager')): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <?php if ($userAttendanceStatus && $userAttendanceStatus['action'] === 'time_in'): ?>
                                        <li>
                                            <button type="button" class="dropdown-item <?php if ($disableTimeOut) echo 'disabled'; ?>" 
                                                    data-bs-toggle="modal" data-bs-target="#timeOutModal" <?php if ($disableTimeOut) echo 'disabled'; ?>>
                                                <span class="material-symbols-outlined">logout</span> Time Out
                                            </button>
                                        </li>
                                    <?php else: ?>
                                        <li>
                                            <button type="button" class="dropdown-item <?php if ($disableTimeIn) echo 'disabled'; ?>" 
                                                    data-bs-toggle="modal" data-bs-target="#timeInModal" <?php if ($disableTimeIn) echo 'disabled'; ?>>
                                                <span class="material-symbols-outlined">login</span> Time In
                                            </button>
                                        </li>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="<?php echo $basePath; ?>logout.php" method="post">
                                    <button type="submit" class="dropdown-item">
                                        <span class="material-symbols-outlined">power_settings_new</span> Logout
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $basePath; ?>login.php">
                            <span class="material-symbols-outlined">login</span> Login
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<?php // Modals for Time In/Out Confirmation
if ($currentUser && in_array($currentUser->getRole(), [UserRole::Cashier, UserRole::Stocker, UserRole::Manager]) && class_exists('AttendanceManager')):
?>
<!-- Time In Modal -->
<div class="modal fade" id="timeInModal" tabindex="-1" aria-labelledby="timeInModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <form action="<?php echo $basePath; ?>time_in.php" method="POST">
        <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($current_page_url); ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="timeInModalLabel"><span class="material-symbols-outlined">login</span> Confirm Time In</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          Are you sure you want to time in now?
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Confirm Time In</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Time Out Modal -->
<div class="modal fade" id="timeOutModal" tabindex="-1" aria-labelledby="timeOutModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <form action="<?php echo $basePath; ?>time_out.php" method="POST">
        <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($current_page_url); ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="timeOutModalLabel"><span class="material-symbols-outlined">logout</span> Confirm Time Out</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          Are you sure you want to time out now?
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning">Confirm Time Out</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; // End check for modals ?>

<?php
if (isset($_SESSION['flash_message']) && basename($_SERVER['PHP_SELF']) !== 'index.php' ) {
    $alertType = $_SESSION['flash_message_type'] ?? 'info'; 
    echo '<div class="container mt-3"><div class="alert alert-' . htmlspecialchars($alertType) . ' alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['flash_message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div></div>';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_message_type']); 
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
    setInterval(updateClock, 1000);
    updateClock();
</script>