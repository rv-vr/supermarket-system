<?php
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/UserRole.php';

class UserManager {
    private string $usersFilePath;

    public function __construct() {
        $this->usersFilePath = __DIR__ . '/../data/users.json';
        if (!file_exists($this->usersFilePath)) {
            // Attempt to create the data directory if it doesn't exist
            $dataDir = dirname($this->usersFilePath);
            if (!is_dir($dataDir)) {
                @mkdir($dataDir, 0777, true);
            }
            // Initialize with an empty array if the file doesn't exist
            if (file_put_contents($this->usersFilePath, json_encode([])) === false) {
                 error_log("UserManager: Failed to create initial users.json file at: " . $this->usersFilePath);
            }
        }
    }

    private function loadUsers(): array {
        if (!file_exists($this->usersFilePath) || !is_readable($this->usersFilePath)) {
            error_log("UserManager: Users file does not exist or is not readable at: " . $this->usersFilePath);
            return [];
        }
        $json = file_get_contents($this->usersFilePath);
        if ($json === false) {
            error_log("UserManager: Failed to read users file from: " . $this->usersFilePath);
            return [];
        }
        $users = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("UserManager: JSON decode error for users.json: " . json_last_error_msg());
            return [];
        }
        return is_array($users) ? $users : [];
    }

    private function saveUsers(array $users): bool {
        $dir = dirname($this->usersFilePath);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
                error_log("UserManager: Users data directory ({$dir}) does not exist and could not be created.");
                return false;
            }
        } elseif (!is_writable($dir)) {
            error_log("UserManager: Users data directory ({$dir}) is not writable.");
            return false;
        }

        // Re-index array to ensure it's saved as a JSON array
        $jsonData = json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($jsonData === false) {
            error_log("UserManager: Failed to encode users to JSON. Error: " . json_last_error_msg());
            return false;
        }

        if (file_put_contents($this->usersFilePath, $jsonData, LOCK_EX) !== false) {
            return true;
        } else {
            error_log("UserManager: Failed to write users to file: {$this->usersFilePath}. Check permissions/disk space.");
            $lastError = error_get_last();
            if ($lastError !== null) {
                error_log("PHP error during file_put_contents: Type: {$lastError['type']}, Message: {$lastError['message']} in {$lastError['file']} on line {$lastError['line']}");
            }
            return false;
        }
    }

    public function getAllUsers(): array {
        return $this->loadUsers();
    }

    public function findUserByUsername(string $username): ?array {
        $users = $this->loadUsers();
        foreach ($users as $userData) {
            if (isset($userData['username']) && strtolower($userData['username']) === strtolower($username)) {
                return $userData;
            }
        }
        return null;
    }

    public function createUser(string $username, string $password, string $fullName, string $role, ?string $associatedVendorName = null): array {
        if (empty($username) || empty($password) || empty($fullName) || empty($role)) {
            return ['success' => false, 'message' => 'Username, password, full name, and role are required.'];
        }
        if (!UserRole::tryFrom($role)) {
            return ['success' => false, 'message' => 'Invalid user role specified.'];
        }
        if ($role === UserRole::Vendor->value && empty($associatedVendorName)) {
            return ['success' => false, 'message' => 'Associated vendor name is required for Vendor role.'];
        }
        if ($this->findUserByUsername($username)) {
            return ['success' => false, 'message' => "Username '{$username}' already exists."];
        }

        $users = $this->loadUsers();
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $newUser = [
            'username' => $username,
            'password' => $hashedPassword,
            'fullName' => $fullName,
            'role' => $role,
        ];
        if ($role === UserRole::Vendor->value) {
            $newUser['associated_vendor_name'] = $associatedVendorName;
        }

        $users[] = $newUser;
        if ($this->saveUsers($users)) {
            return ['success' => true, 'message' => "User '{$username}' created successfully."];
        } else {
            return ['success' => false, 'message' => "Failed to save new user '{$username}'."];
        }
    }

    public function updateUser(string $originalUsername, string $newUsername, string $fullName, string $role, ?string $newPassword = null, ?string $associatedVendorName = null): array {
        if (empty($newUsername) || empty($fullName) || empty($role)) {
            return ['success' => false, 'message' => 'Username, full name, and role are required.'];
        }
        if (!UserRole::tryFrom($role)) {
            return ['success' => false, 'message' => 'Invalid user role specified.'];
        }
        if ($role === UserRole::Vendor->value && empty($associatedVendorName)) {
            return ['success' => false, 'message' => 'Associated vendor name is required for Vendor role.'];
        }

        $users = $this->loadUsers();
        $userIndex = -1;
        foreach ($users as $index => $u) {
            if (isset($u['username']) && strtolower($u['username']) === strtolower($originalUsername)) {
                $userIndex = $index;
                break;
            }
        }

        if ($userIndex === -1) {
            return ['success' => false, 'message' => "User '{$originalUsername}' not found."];
        }

        // Check if new username is already taken by another user
        if (strtolower($originalUsername) !== strtolower($newUsername) && $this->findUserByUsername($newUsername)) {
            return ['success' => false, 'message' => "New username '{$newUsername}' is already taken."];
        }

        $users[$userIndex]['username'] = $newUsername;
        $users[$userIndex]['fullName'] = $fullName;
        $users[$userIndex]['role'] = $role;

        if (!empty($newPassword)) {
            $users[$userIndex]['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        if ($role === UserRole::Vendor->value) {
            $users[$userIndex]['associated_vendor_name'] = $associatedVendorName;
        } else {
            // Remove vendor association if role is not Vendor
            unset($users[$userIndex]['associated_vendor_name']);
        }
        
        if ($this->saveUsers($users)) {
            return ['success' => true, 'message' => "User '{$originalUsername}' updated successfully."];
        } else {
            return ['success' => false, 'message' => "Failed to update user '{$originalUsername}'."];
        }
    }

    public function deleteUser(string $usernameToDelete, string $currentManagerUsername): array {
        if (strtolower($usernameToDelete) === strtolower($currentManagerUsername)) {
            return ['success' => false, 'message' => 'Managers cannot delete their own account.'];
        }

        $users = $this->loadUsers();
        $userIndexToDelete = -1;
        foreach ($users as $index => $u) {
            if (isset($u['username']) && strtolower($u['username']) === strtolower($usernameToDelete)) {
                $userIndexToDelete = $index;
                break;
            }
        }

        if ($userIndexToDelete === -1) {
            return ['success' => false, 'message' => "User '{$usernameToDelete}' not found."];
        }

        array_splice($users, $userIndexToDelete, 1);

        if ($this->saveUsers($users)) {
            return ['success' => true, 'message' => "User '{$usernameToDelete}' deleted successfully."];
        } else {
            return ['success' => false, 'message' => "Failed to delete user '{$usernameToDelete}'."];
        }
    }
}