# Petroleum Management System

A PHP and MySQL application for managing petroleum station operations across branches. The system provides role-based tools for sales, fuel inventory, expenses, payments, payroll, and reporting.

## Features

- **Branch operations:** Manage branches, users, attendance, pumps, tanks, and fuel types.
- **Fuel sales:** Record sales with currency and customer details, then review sales history and receipts.
- **Inventory:** Monitor tank levels, stock movements, and related operational records.
- **Financial workflows:** Track expenses, invoices, payments, and monthly staff salary payments.
- **Reports and oversight:** Review dashboards, branch performance, financial reports, and audit records.
- **Role-based access:** Provides separate areas for administrators, managers, accountants, and attendants. Accountant data and payroll actions are limited to the assigned branch.
- **Customer communications:** Includes receipt and email/SMS-related features where configured.

Available pages and actions depend on the signed-in user's role and permissions.

## Requirements

- Apache with PHP enabled (XAMPP is suitable for local development)
- PHP with the `mysqli` extension and session support
- MySQL or MariaDB
- A modern browser

Some optional import/export or messaging features may require additional server-side configuration. No Composer or npm dependency manifest is currently included.

## Local setup with XAMPP

1. Place the project directory under XAMPP's `htdocs` directory. The supplied `.htaccess` is configured for the local directory name `Petrolem Managemennt`; update its `RewriteBase` if you use a different URL path.
2. Start Apache and MySQL from the XAMPP control panel.
3. In phpMyAdmin, create an **empty** database named `godwilling_db` (or choose another name and update the application configuration).
4. Import [`database/schema.sql`](database/schema.sql) into that empty database. This file defines the database structure and contains no application records.

   **Warning:** The schema file contains `DROP TABLE IF EXISTS` statements. Importing it into a database with existing data will remove tables and their data. Back up any database you intend to keep, and use an empty database for a fresh installation.
5. Update the database host, username, password, and database name in [`config/db.php`](config/db.php) to match your local MySQL/MariaDB setup. Do not put real deployment credentials in a public commit.
6. Open `http://localhost/Petrolem%20Managemennt/public/login.php` (or the matching URL for your local setup) and sign in with an administrator account provisioned for your installation.

The schema-only export does not include users, demo credentials, or operational data. No default login or password is provided in this repository; provision access securely for your environment.

## Database migrations

The [`database/migrations/`](database/migrations/) directory contains SQL changes for existing installations. Review the relevant migration before applying it, take a backup, and run migrations only when the corresponding schema changes are not already present. Do not apply a migration to an unrelated or production database without approval and a recovery plan.

## Roles

| Role | Main responsibilities |
| --- | --- |
| Administrator | Manage users, branches, system settings, and organization-wide records and reports. |
| Manager | Oversee assigned branch operations, sales, expenses, staff, and inventory. |
| Accountant | Work with assigned-branch invoices, payments, payroll, and financial reports. |
| Attendant | Record fuel sales, provide receipts, and review sales and shift information. |

For staff workflows and navigation guidance, see [`SYSTEM-GUIDE.md`](SYSTEM-GUIDE.md).

## Security and operational notes

- Use individual accounts and grant only the access required for each role.
- Change default or development database credentials before deployment.
- Keep backups, exports, uploaded files, and environment-specific configuration out of public source control.
- Configure and verify email, SMS, payment integrations, and any required PHP extensions before relying on those features.
- Use HTTPS and follow your organization's access-control, backup, and data-retention policies in deployed environments.
- Test upgrades and migrations against a separate copy of the database before applying them to live data.

## Repository layout

```text
admin/                 Administrator pages
accountant/            Accountant pages, reports, and payroll
attendant/             Sales, receipts, and attendant tools
manager/               Manager pages and reports
api/                   Application API endpoints
assets/                Shared CSS, JavaScript, and images
config/                Application configuration
database/migrations/   Incremental SQL migrations
database/schema.sql    Schema-only database export
includes/              Shared authentication, layout, and helpers
public/                Public-facing pages and sign-in
```
