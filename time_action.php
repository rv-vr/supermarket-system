<?php
require_once __DIR__ . '/src/classes/UserRole.php';
require_once __DIR__ . '/src/classes/User.php';

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
$requestedAction = $_POST['action'] ?? null;
$timestamp = date('Y-m-d H:i:s'); // This will now use Asia/Manila
$logFile = __DIR__ . '/src/data/time_log.json';

$message = "Action not recognized.";
$logs = [];
if (file_exists($logFile)) {
    $logs = json_decode(file_get_contents($logFile), true) ?? [];
}

// Find the last action for the current user
$lastAction = null;
$userLogs = array_filter($logs, function ($log) use ($username) {
    return isset($log['username']) && $log['username'] === $username;
});
if (!empty($userLogs)) {
    $lastLogEntry = end($userLogs); // Get the last entry for the user
    if (isset($lastLogEntry['action'])) {
        $lastAction = $lastLogEntry['action'];
    }
}

$canProceed = false;

if ($requestedAction === 'time_in') {
    if ($lastAction === null || $lastAction === 'time_out') {
        $canProceed = true;
    } else {
        $message = "You are already timed in. Please time out first.";
        $_SESSION['flash_message_type'] = 'warning';
    }
} elseif ($requestedAction === 'time_out') {
    if ($lastAction === 'time_in') {
        $canProceed = true;
    } else {
        $message = "You are not timed in or already timed out.";
        $_SESSION['flash_message_type'] = 'warning';
    }
}

if ($canProceed) {
    $logEntry = [
        'username' => $username,
        'action' => $requestedAction,
        'role' => $user->getRole()->value,
        'timestamp' => $timestamp
    ];
    $logs[] = $logEntry;
    if (file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT))) {
        $message = "Successfully recorded " . htmlspecialchars($requestedAction) . " at " . htmlspecialchars($timestamp);
        $_SESSION['flash_message_type'] = 'success';
    } else {
        $message = "Error saving time log.";
        $_SESSION['flash_message_type'] = 'danger';
    }
}

$_SESSION['flash_message'] = $message;

// Redirect back to the previous page or a default page
$redirectTo = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header('Location: ' . $redirectTo);
exit();
?>