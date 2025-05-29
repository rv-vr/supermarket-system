<?php
require_once __DIR__ . '/src/classes/User.php';
require_once __DIR__ . '/src/classes/UserRole.php';
require_once __DIR__ . '/src/classes/AttendanceManager.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user']) || !($_SESSION['user'] instanceof User) || 
    !in_array($_SESSION['user']->getRole(), [UserRole::Cashier, UserRole::Stocker, UserRole::Manager])) {
    $_SESSION['flash_message'] = "Access Denied.";
    $_SESSION['flash_message_type'] = "danger";
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'];
$username = $user->getUsername();
$redirectUrl = !empty($_POST['redirect_url']) ? $_POST['redirect_url'] : 'index.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attendanceManager = new AttendanceManager();
    $result = $attendanceManager->timeOut($username);

    $_SESSION['flash_message'] = $result['message'];
    $_SESSION['flash_message_type'] = $result['success'] ? "success" : "danger";
} else {
    $_SESSION['flash_message'] = "Invalid request method.";
    $_SESSION['flash_message_type'] = "danger";
    $redirectUrl = 'index.php';
}

// Basic security for redirect URL (same as in time_in.php)
if (!(strpos($redirectUrl, '/') === 0 && strpos($redirectUrl, '//') !== 0 && strpos($redirectUrl, '..') === false) && $redirectUrl !== 'index.php') {
    $userRole = $user->getRole();
    switch($userRole) {
        case UserRole::Manager: $redirectUrl = 'src/manager/index.php'; break;
        case UserRole::Stocker: $redirectUrl = 'src/inventory/index.php'; break;
        case UserRole::Cashier: $redirectUrl = 'src/pos/index.php'; break;
        default: $redirectUrl = 'index.php'; break;
    }
     if (strpos($redirectUrl, 'src/') === 0) {
        $project_root_fs_to = realpath(__DIR__);
        $basePath_to = '';
        $redirectUrl = $basePath_to . $redirectUrl;
    }
}

header('Location: ' . $redirectUrl);
exit();
?>