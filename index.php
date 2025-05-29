<?php
// Ensure User and UserRole classes are available for the $_SESSION['user'] object
require_once __DIR__ . '/src/classes/User.php';
require_once __DIR__ . '/src/classes/UserRole.php';
// Auth.php is not strictly needed here if we only rely on the session,
// but it's good practice if any Auth methods were to be called.
// require_once __DIR__ . '/src/classes/Auth.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user']) && $_SESSION['user'] instanceof User) {
    $user = $_SESSION['user'];
    $redirectPath = '';

    switch ($user->getRole()) {
        case UserRole::Cashier:
            $redirectPath = 'src/pos/index.php';
            break;
        case UserRole::Stocker:
            $redirectPath = 'src/inventory/index.php';
            break;
        case UserRole::Manager:
            $redirectPath = 'src/manager/index.php';
            break;
        case UserRole::Vendor: // Assuming UserRole::Vendor is the correct enum case
            $redirectPath = 'src/vendor/index.php';
            break;
        default:
            // If role is unknown or not set, redirect to login
            header('Location: login.php');
            exit();
    }
    header('Location: ' . $redirectPath);
    exit();
} else {
    // If no user in session or not a User object, redirect to login
    header('Location: login.php');
    exit();
}
?>