<?php
// Ensure Auth class is available if its logout method handles session destruction
// and potentially other cleanup.
require_once __DIR__ . '/src/classes/Auth.php';

// It's good practice to ensure UserRole and User are defined before session_start
// if they are part of the session, though for logout, Auth->logout() should handle it.
require_once __DIR__ . '/src/classes/UserRole.php';
require_once __DIR__ . '/src/classes/User.php';


$auth = new Auth(); // The constructor of Auth calls session_start() if not already started
$auth->logout();

header('Location: login.php');
exit();
?>