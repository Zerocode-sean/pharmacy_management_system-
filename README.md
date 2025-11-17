# Pharmacy Management System

A modern, full-featured pharmacy management system for retail pharmacies, built with PHP, MySQL, HTML, CSS, and JavaScript. This system streamlines medicine inventory, sales, expenses, and user management for pharmacists and administrators.

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
