<?php
require_once __DIR__ . '/UserRole.php';

class User {
    private string $username;
    private string $hashedPassword; // Stores the hashed password
    private UserRole $role;         // Stores the UserRole enum instance
    private string $fullName;       // New property for full name

    public function __construct(string $username, string $hashedPassword, UserRole $role, string $fullName) {
        $this->username = $username;
        $this->hashedPassword = $hashedPassword;
        $this->role = $role;
        $this->fullName = $fullName; // Assign full name
    }

    public function getUsername(): string {
        return $this->username;
    }

    public function getRole(): UserRole { // Returns the UserRole enum instance
        return $this->role;
    }

    public function getFullName(): string {
        // With __wakeup, this direct return should be safe.
        // The error occurs if $this->fullName is accessed before initialization.
        return $this->fullName;
    }

    public function authenticate(string $inputPassword): bool {
        return password_verify($inputPassword, $this->hashedPassword);
    }

    /**
     * Initializes properties when an object is unserialized from the session.
     * This is good for handling objects from older sessions that might be missing new properties.
     */
    public function __wakeup()
    {
        // If fullName is not set (e.g., from an older session object where the property didn't exist),
        // initialize it. We default to username as a safe fallback.
        // The Auth class already handles defaulting fullName to username if not in users.json during a fresh login.
        // This addresses the case of an uninitialized typed property after unserialization.
        if (!isset($this->fullName)) {
            $this->fullName = $this->username;
        }
    }
}