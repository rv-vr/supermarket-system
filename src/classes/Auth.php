<?php
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/UserRole.php';

class Auth {
    private $usersJsonPath; // Store path for flexibility

    public function __construct() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $this->usersJsonPath = __DIR__ . '/../data/users.json';
    }

    private function loadUsers(): array {
        if (!file_exists($this->usersJsonPath)) {
            return [];
        }
        $json = file_get_contents($this->usersJsonPath);
        return json_decode($json, true) ?? [];
    }

    private function validateCredentials(string $username, string $password): ?array {
        $users = $this->loadUsers();
        foreach ($users as $userData) {
            if ($userData['username'] === $username && password_verify($password, $userData['password'])) {
                return $userData; // Return the raw user data array from JSON
            }
        }
        return null;
    }

    public function login(string $username, string $password): bool
    {
        $userData = $this->validateCredentials($username, $password);

        if ($userData) {
            $userRoleEnum = UserRole::from($userData['role']);

            if (!$userRoleEnum) {
                // Invalid role in users.json, should not happen with correct data
                return false;
            }

            // Use fullName from userData, fallback to username if not present
            $fullName = $userData['fullName'] ?? $userData['username'];

            $loggedInUser = new User(
                $userData['username'],
                $userData['password'], // This is the HASHED password from users.json
                $userRoleEnum,
                $fullName // Pass fullName to User constructor
            );

            $_SESSION['user'] = $loggedInUser; // Store the User object
            // Store the redirect path segment (e.g., "pos", "inventory") for login.php redirection
            $_SESSION['user_redirect_segment'] = $userRoleEnum->getRedirectPath();
            
            // Clean up old session variables if they exist from previous logic
            unset($_SESSION['username']);
            unset($_SESSION['user_role']);


            return true;
        }
        
        return false;
    }

    public function logout(): void
    {
        // session_start() should have been called by the script calling logout or constructor
        if (session_status() == PHP_SESSION_NONE) { // Defensive check
            session_start();
        }
        session_unset(); 
        session_destroy(); 
    }

    public function isLoggedIn(): bool
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user']) && $_SESSION['user'] instanceof User;
    }

    public function getCurrentUser(): ?User
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['user']) && $_SESSION['user'] instanceof User) {
            return $_SESSION['user'];
        }
        return null;
    }

    // getRoleRedirectPath method is removed as UserRole enum now handles this.
    // getCurrentUserRole and getCurrentUsername string methods are replaced by getCurrentUser() returning User object.
}