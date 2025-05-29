<?php
enum UserRole: string {
    case Cashier = 'Cashier';
    case Stocker = 'Stocker';
    case Manager = 'Manager';
    case Vendor = 'Vendor/Supplier';

    public function getRedirectPath(): string {
        return match($this) {
            self::Cashier => 'pos',
            self::Stocker => 'inventory',
            self::Manager => 'manager',
            self::Vendor => 'vendor',
        };
    }
}
?>