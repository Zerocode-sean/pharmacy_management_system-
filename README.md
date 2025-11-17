# Pharmacy Management System

A modern, full-featured pharmacy management system for retail pharmacies, built with PHP, MySQL, HTML, CSS, and JavaScript. This system streamlines medicine inventory, sales, expenses, and user management for pharmacists and administrators.

## System Architecture & Component Overview

The Pharmacy Management System is designed as a modular, full-stack web application. It consists of several tightly integrated components:

### 1. Backend (API Layer)
- **Location:** `src/backend/api/`
- **Technology:** PHP (RESTful API)
- **Purpose:** Handles all business logic, database operations, authentication, and serves data to the frontend via JSON endpoints.
- **Key Endpoints:**
    - `medicines.php`: CRUD for medicines
    - `expenses.php`: CRUD and stats for expenses
    - `users.php`: User management and authentication
    - `dashboard.php`: Aggregates statistics for dashboards
    - `sales.php`, `purchase_orders.php`, `suppliers.php`: Transactional modules
- **Integration:** All frontend pages communicate with these endpoints using AJAX/fetch requests. Session management and role-based access are enforced here.

### 2. Database
- **Location:** `database/`
- **Technology:** MySQL
- **Purpose:** Stores all persistent data: medicines, users, sales, expenses, suppliers, and logs.
- **Schema Files:**
    - `schema.sql`, `customer_auth_schema.sql`, `expenses_payments_schema.sql`
- **Integration:** The backend API connects to the database using PHP's PDO/MySQLi. All data validation and security checks are performed before database operations.

### 3. Frontend (Admin & Pharmacist Dashboards)
- **Location:** `src/frontend/pages/`, `src/frontend/admin/index.html`, `src/frontend/pharmacist_dashboard_clean.html`
- **Technology:** HTML, CSS, JavaScript
- **Purpose:** Provides interactive dashboards for admins and pharmacists to manage inventory, sales, expenses, and users.
- **Features:**
    - Real-time statistics and charts
    - Expense and medicine management tables
    - Modal forms for CRUD operations
    - Error reporting and notifications
- **Integration:** Uses AJAX/fetch to call backend APIs. UI updates dynamically based on API responses. Role-based UI elements are shown/hidden as needed.

### 4. Customer Portal
- **Location:** `customer-portal/`
- **Technology:** HTML, CSS, JavaScript, PHP
- **Purpose:** Allows customers to view medicines, place orders, and manage their accounts.
- **Features:**
    - Medicine catalog and search
    - Order placement and tracking
    - Email verification and password reset
- **Integration:** Communicates with backend APIs for authentication, order management, and email notifications.

### 5. Shared Includes & Config
- **Location:** `includes/`, `src/backend/config/`
- **Purpose:** Contains shared PHP files for database connection, session management, security, and configuration.
- **Integration:** All backend API endpoints include these files for consistent access control and error handling.

### 6. Email & Notification System
- **Location:** `customer-portal/email-config.php`, `customer-portal/phpmailer/`
- **Technology:** PHP, PHPMailer
- **Purpose:** Sends automated emails for order confirmations, password resets, and system alerts.
- **Integration:** Backend triggers email notifications on relevant events. SMTP settings are configurable.

### 7. Logging & Debugging
- **Location:** `logs/`, various debug HTML files
- **Purpose:** Tracks system errors, API requests, and user actions for troubleshooting and auditing.
- **Integration:** Backend writes logs for critical operations. Frontend displays error messages and logs API responses for debugging.

### 8. Documentation
- **Location:** `docs/`, markdown files throughout the project
- **Purpose:** Provides implementation guides, architecture summaries, and troubleshooting steps for developers and admins.

## How Components Work Together

1. **User logs in** via the dashboard or customer portal. The frontend sends credentials to the backend API, which authenticates and starts a session.
2. **Dashboard loads**: The frontend requests statistics, lists, and user data from the backend API. The backend queries the database and returns JSON responses.
3. **CRUD operations** (add/edit/delete medicines, expenses, users) are performed via modal forms. The frontend sends requests to the API, which validates, updates the database, and returns results.
4. **Customer actions** (view medicines, place orders) are routed through the customer portal, which interacts with the backend and triggers email notifications.
5. **Role-based access** ensures users only see and interact with features appropriate to their role (admin, pharmacist, customer).
6. **Error handling**: Both backend and frontend log errors and display messages to users. Logs are stored for auditing and debugging.
7. **Documentation** is available for onboarding, troubleshooting, and understanding system architecture.

## Features

- **User Authentication**: Secure login for pharmacists, admins, and customers
- **Medicine Inventory**: Add, edit, delete, and track medicines with expiry and stock alerts
- **Sales & POS**: Fast point-of-sale interface, sales tracking, and receipts
- **Expenses Management**: Record, edit, and analyze business expenses with statistics
- **Dashboard**: Real-time statistics, charts, and quick actions for admins and pharmacists
- **Customer Portal**: Customers can view medicines, request orders, and track purchases
- **Role-Based Access**: Separate dashboards and permissions for admins, pharmacists, and customers
- **API Endpoints**: RESTful backend for integration and automation
- **Responsive Design**: Works on desktops, tablets, and mobile devices
- **Email Integration**: Automated email notifications for orders, password resets, and reports

## Technologies Used

- **Backend**: PHP (REST API), MySQL
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Dev Environment**: XAMPP (Apache, MySQL, PHP)

## Setup Instructions

1. **Clone the repository**
2. **Configure your database**: Import the provided SQL schema and update connection settings in `includes/db.php`
3. **Set up XAMPP**: Place the project in your `htdocs` directory and start Apache/MySQL
4. **Configure email**: Update SMTP settings in `customer-portal/email-config.php` for email features
5. **Access the system**:
    - Admin dashboard: `/Phamarcy/admin/index.html`
    - Pharmacist dashboard: `/Phamarcy/pharmacist_dashboard_clean.html`
    - Customer portal: `/Phamarcy/customer-portal/index.html`

## Folder Structure

- `src/backend/api/` — PHP API endpoints
- `src/frontend/pages/` — Dashboard and UI pages
- `customer-portal/` — Customer-facing portal
- `includes/` — Shared PHP includes (DB, session, etc.)
- `database/` — SQL schema and migrations
- `logs/` — System and error logs
- `docs/` — Documentation and guides

## Contributing

1. Fork the repository
2. Create a new branch (`git checkout -b feature-xyz`)
3. Commit your changes
4. Push and open a pull request

## License

This project is licensed under the MIT License.

## Authors

- Zerocode-sean (Lead Developer)
- Contributors: See GitHub history

## Support

For issues, open a GitHub issue or contact the maintainer.
