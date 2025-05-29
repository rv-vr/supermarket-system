# Supermarket System

## Overview
The Supermarket System is a web application designed to manage various aspects of a supermarket, including Point of Sale (POS), Inventory Management, Manager Dashboard, and Vendor Delivery Management. The application is built using PHP and utilizes JSON for data storage, ensuring a lightweight and efficient solution without the need for a database.

## Features
- **Point of Sale (POS)**: A dedicated section for cashiers to process transactions and manage sales.
- **Inventory Management**: Allows stockers to manage inventory levels, track products, and update stock information.
- **Manager Dashboard**: Provides managers with insights into sales, inventory, and overall store performance.
- **Vendor Delivery Management**: Enables vendors to manage deliveries and track orders.

## User Roles
The application supports four user roles, each with specific permissions:
- **Cashier**: Access to the POS section for processing sales.
- **Stocker**: Access to the Inventory Management section for managing stock.
- **Manager**: Access to the Manager Dashboard for overseeing operations.
- **Vendor/Supplier**: Access to the Vendor Delivery Management section for managing deliveries.

## Project Structure
```
supermarket-system
├── src
│   ├── classes
│   │   ├── User.php
│   │   ├── Auth.php
│   │   └── UserRole.php
│   ├── pos
│   │   └── index.php
│   ├── inventory
│   │   └── index.php
│   ├── manager
│   │   └── index.php
│   ├── vendor
│   │   └── index.php
│   └── data
│       └── users.json
├── public
│   ├── css
│   │   └── styles.css
│   └── index.php
├── login.php
└── README.md
```

## Setup Instructions
1. Clone the repository to your local machine.
2. Navigate to the project directory.
3. Ensure you have a PHP server running (e.g., XAMPP, MAMP).
4. Access the application via your web browser at `http://localhost/supermarket-system/public/index.php`.
5. Use the login page to authenticate as one of the user roles.

## Technologies Used
- PHP for server-side logic
- JSON for data storage
- Bootstrap CSS for responsive design

## Future Enhancements
- Implement additional features such as reporting and analytics.
- Enhance user interface with more interactive elements.
- Consider integrating a database for more complex data management.