<?php
// Load class definitions BEFORE session_start() if session might contain objects of these types
require_once __DIR__ . '/src/classes/UserRole.php';
require_once __DIR__ . '/src/classes/User.php';
require_once __DIR__ . '/src/classes/Auth.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// If user is already logged in, redirect them to the main index page
if (isset($_SESSION['user']) && $_SESSION['user'] instanceof User) {
    header('Location: index.php'); // Redirect to main dashboard/router
    exit();
}

$error = null; // Initialize error variable

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $auth = new Auth();
    if ($auth->login($username, $password)) {
        // login() in Auth.php sets 'user_redirect_segment'
        if (isset($_SESSION['user_redirect_segment'])) {
            header('Location: src/' . $_SESSION['user_redirect_segment'] . '/index.php');
            exit();
        } else {
            // Fallback if redirect segment isn't set, though it should be by Auth::login
            header('Location: index.php');
            exit();
        }
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="public/css/styles.css"> <!-- Your custom styles -->
    <title>Login</title>
    <style>
        /* Ensure body allows modal to be centered and backdrop to cover */
        body {
            background-color: #f8f9fa; /* Light background for the page */
        }
    </style>
</head>
<body>

    <!-- Bootstrap Modal -->
    <!-- The modal is set to be shown by default using class="show" and style="display: block;" -->
    <!-- A backdrop is manually added as well -->
    <div class="modal show" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" style="display: block;" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title w-100 text-center" id="loginModalLabel">Supermarket System Login</h5>
                    <!-- Close button functionality relies on Bootstrap JS -->
                    <!-- <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button> -->
                </div>
                <div class="modal-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <form method="POST" action="login.php">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>
                </div>
                <!-- Optional: Modal Footer can be used for other actions or links -->
                <div class="modal-footer">
                    <p class="text-muted small">Contact admin@supermarket.com if you have issues.</p>
                </div>
            </div>
        </div>
    </div>
    <!-- Manual backdrop for the modal -->

    <!-- Bootstrap JS Bundle (includes Popper) - Required for dynamic modal behaviors like closing -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>