<?php
require_once __DIR__ . '/UserRole.php';

class User {
    private string $username;
    private string $hashedPassword; // Stores the hashed password
    private UserRole $role;         // Stores the UserRole enum instance
    private string $fullName;       // New property for full name
    private ?string $associatedVendorName; // New property for vendor association

    public function __construct(string $username, string $hashedPassword, UserRole $role, string $fullName, ?string $associatedVendorName = null) {
        $this->username = $username;
        $this->hashedPassword = $hashedPassword;
        $this->role = $role;
        $this->fullName = $fullName; // Assign full name
        $this->associatedVendorName = $associatedVendorName; // Assign associated vendor name
    }

    public function getUsername(): string {
        return $this->username;
    }

    public function getRole(): UserRole { // Returns the UserRole enum instance
        return $this->role;
    }

    public function getFullName(): string {
        return $this->fullName;
    }

    public function getAssociatedVendorName(): ?string { // Getter for associated vendor name
        return $this->associatedVendorName;
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
        if (!isset($this->fullName)) {
            $this->fullName = $this->username;
        }
        // Initialize associatedVendorName if it's not set (for older session objects)
        if (!isset($this->associatedVendorName)) {
            $this->associatedVendorName = null;
        }
    }
}