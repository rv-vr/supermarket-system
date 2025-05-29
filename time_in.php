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
// Use the redirect_url from POST, default to index.php if not set or empty
$redirectUrl = !empty($_POST['redirect_url']) ? $_POST['redirect_url'] : 'index.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attendanceManager = new AttendanceManager();
    $result = $attendanceManager->timeIn($username);

    $_SESSION['flash_message'] = $result['message'];
    $_SESSION['flash_message_type'] = $result['success'] ? "success" : "danger";
} else {
    $_SESSION['flash_message'] = "Invalid request method.";
    $_SESSION['flash_message_type'] = "danger";
    // If not POST, redirectUrl might not be set from form, so ensure a safe default
    $redirectUrl = 'index.php'; 
}

// Basic security for redirect URL to prevent open redirect
// Allow only relative URLs starting with '/' or known safe paths.
if (!(strpos($redirectUrl, '/') === 0 && strpos($redirectUrl, '//') !== 0 && strpos($redirectUrl, '..') === false) && $redirectUrl !== 'index.php') {
    // If it's not a safe-looking relative path (e.g. /src/page.php) or index.php, default to a known safe page.
    // This is a simplistic check. A whitelist approach is more secure for production.
    $userRole = $user->getRole();
    switch($userRole) {
        case UserRole::Manager: $redirectUrl = 'src/manager/index.php'; break;
        case UserRole::Stocker: $redirectUrl = 'src/inventory/index.php'; break;
        case UserRole::Cashier: $redirectUrl = 'src/pos/index.php'; break;
        default: $redirectUrl = 'index.php'; break;
    }
    // Prepend basePath if redirecting to src/*
    if (strpos($redirectUrl, 'src/') === 0) {
        // Determine basePath again, as this script is in root
        $project_root_fs_ti = realpath(__DIR__); // time_in.php is in project root
        $basePath_ti = ''; // No need for ../../
        $redirectUrl = $basePath_ti . $redirectUrl;
    }
}


header('Location: ' . $redirectUrl);
exit();
?>