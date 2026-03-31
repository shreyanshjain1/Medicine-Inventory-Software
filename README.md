# PITC Medicine Inventory Flagship

![PHP](https://img.shields.io/badge/PHP-8+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-MariaDB%20Compatible-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Procedural PHP](https://img.shields.io/badge/Architecture-Procedural%20PHP-0F172A?style=for-the-badge)
![Inventory](https://img.shields.io/badge/Domain-Medicine%20Inventory-16A34A?style=for-the-badge)
![Status](https://img.shields.io/badge/Status-Flagship%20Upgrade-1A73E8?style=for-the-badge)

Production-minded medicine inventory software built on the current PHP + MySQL codebase and upgraded without removing the existing working flows for:

- dashboard
- inventory IN
- inventory OUT
- inventory RETURN
- batch history
- alerts
- activity logs
- export center
- regular inventory
- outsourced inventory

This version adds role-based permissions, product master management, correction and reversal workflows, notes, reporting analytics, dashboard filters, chart-based movement analysis, in-app notifications, and barcode-ready label printing while keeping the app compatible with normal shared hosting and cPanel-style deployment.

## Overview

The flagship version keeps the original transactional structure centered on:

- `inventory`
- `inventory_outsourced`
- `out_records`
- `return_binded_records`
- `in_log`
- `users`

It extends those tables carefully and introduces supporting tables for product master records, notes, corrections, audits, notifications, and settings.

The result is still a procedural PHP application, but it is now significantly safer for real operational use:

- no silent edits
- no hard-delete transaction reversals
- role-aware UI and server-side guards
- append-only audit trail
- product normalization through product master
- sharable dashboard/report filters
- in-app alert notifications
- barcode label printing for products and batches

## Core Modules

### Dashboard

- advanced server-side filters for source, stock status, expiry status, manufacturer, distributor, quantity range, and date range
- KPI cards for stock, movements, low stock, expired rows, and health state
- chart widgets for movement trends, stock composition, stock health, and top moved products
- regular and outsourced inventory sections with compact grouped tables
- alerts snapshot and recent activity

### Inventory IN

- create a batch from an existing product master record
- create a new product master record and first batch in a single workflow
- regular and outsourced source support
- batch threshold override
- importer and distributor support for outsourced batches
- optional batch and IN notes at creation time

### Inventory OUT

- release stock from regular inventory
- customer and document traceability
- transaction note support
- later correction and void workflows for authorized roles

### Inventory RETURN

- bind returns directly to the original OUT record
- remaining-return validation
- transaction note support
- later correction and void workflows for authorized roles

### Product Master

- reusable medicine profile list
- create, edit, and archive product profiles
- duplicate prevention
- barcode value storage
- low stock threshold control
- product-level notes
- printable barcode label

### Batch History

- search by batch number
- show IN source details
- show OUT and RETURN history including voided rows
- show batch status and notes
- IN batch voiding when safe
- correction entry point
- printable batch barcode label

### Reports & Analytics

- current stock summary
- low stock report
- expiring soon report
- expired stock report
- movement summary by date range
- top outgoing products
- top returned products
- supplier/distributor summary
- regular vs outsourced stock summary
- movement by user
- exportable CSV analytics

### Alerts & Notifications

- low stock alerts
- out of stock alerts
- expiring soon alerts
- expired and critical expiry alerts
- in-app notifications with read/unread tracking
- correction and reversal notifications for higher-trust roles

### Exports

- regular inventory export
- outsourced inventory export
- combined inventory export
- batch CSV export
- analytics CSV export
- print-friendly report pages

## Role System

The system now supports these roles:

- `Admin`
- `Manager`
- `Staff`
- `Viewer`

### Permission summary

- `Admin`
  Full access across inventory, products, corrections, reversals, exports, reports, notes, notifications, and account provisioning.

- `Manager`
  View, create, export, correct, reverse or void, manage products, manage notes, and review analytics.

- `Staff`
  View the system, create IN/OUT/RETURN transactions, review batch history, view reports and exports, and add notes. Staff cannot reverse or void transactions and cannot manage product master corrections.

- `Viewer`
  Read-only access to dashboard, history, reports, alerts, activity logs, exports, product browsing, notes, and notifications.

### Permission behavior

- buttons and navigation are hidden when a role is not allowed
- direct page access is guarded on the server
- note edit/delete respects role and note ownership
- correction and reversal actions always require higher permissions and a reason

## Correction Workflow

Authorized roles can correct:

- inventory IN batch records
- OUT transactions
- RETURN transactions
- product master details

Every correction:

- requires a reason
- writes the actor, time, old values, new values, and reason to `correction_logs`
- also writes an `audit_logs` entry
- recalculates live stock safely before saving

### Correction pages

- `edit_record.php` for batch, OUT, and RETURN corrections
- `product_form.php` for product master corrections
- `correction_logs.php` for filtered audit review

## Reversal / Voiding Workflow

Authorized roles can void:

- OUT transactions
- RETURN transactions
- IN batches when safe

### Rules

- no hard deletes
- original records remain in history and are marked `voided`
- void actions require a reason
- stock totals are adjusted transactionally
- OUT voiding cascades active linked RETURN rows into voided status
- double reversal is prevented by `record_status`

## Notes / Remarks

Notes are supported on:

- products
- regular batches
- outsourced batches
- IN transactions
- OUT transactions
- RETURN transactions

Notes store:

- target type
- target id
- note text
- author
- timestamps
- active or deleted state

## Barcode Support

This version implements barcode support using Code39-friendly values.

### Barcode features

- barcode value on product master
- printable barcode labels for products
- printable barcode labels for batches
- barcode value included in dashboard and product search inputs
- scanner-friendly workflows because common barcode scanner hardware can type directly into search fields

## File Guide

### Main pages

- `dashboard.php`
- `form_in.php`
- `form_out.php`
- `form_return.php`
- `batch_history.php`
- `products.php`
- `product_form.php`
- `reports.php`
- `alerts.php`
- `activity_logs.php`
- `correction_logs.php`
- `notifications.php`
- `transaction_detail.php`
- `edit_record.php`
- `label_print.php`
- `export_center.php`

### Shared logic

- `includes/common.php`
- `includes/domain.php`
- `assets/app.css`
- `assets/app.js`

### Note and export handlers

- `notes_action.php`
- `note_edit.php`
- `export_inventory.php`
- `export_report.php`
- `export_batch_excel.php`
- `export_batch_pdf.php`

### Authentication

- `login.php`
- `signup.php`
- `logout.php`

## Database Migration

Run the migration file:

- `migrations/20260331_flagship_upgrade.sql`

### What the migration does

- upgrades user roles to `admin`, `manager`, `staff`, `viewer`
- adds user activity columns
- creates `products`
- links `inventory` and `inventory_outsourced` to `products`
- extends `inventory`, `inventory_outsourced`, `out_records`, `return_binded_records`, and `in_log` with status and audit fields
- backfills product links
- backfills and normalizes `in_log`
- creates `correction_logs`
- creates `audit_logs`
- creates `notes`
- creates `notifications`
- creates `app_settings`

## Setup Instructions

### 1. Place the project in your PHP web root

Examples:

- XAMPP: `htdocs`
- WAMP: `www`
- Laragon: `www`
- cPanel: `public_html` or a subdirectory

### 2. Configure the database connection

Update `db.php`:

```php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'inventory_db';
```

### 3. Apply the SQL migration

Import:

- `migrations/20260331_flagship_upgrade.sql`

You can use:

- phpMyAdmin
- MySQL command line
- cPanel database tools

### 4. Open the application

Example:

```text
http://localhost/Medicine-Inventory-Software-main/
```

### 5. Bootstrap the first account

If there are no users yet:

- open `signup.php`
- create the first `Admin` account

If users already exist:

- sign in through `login.php`
- only an admin can use `signup.php` to create additional users

## Shared Hosting Notes

This project stays compatible with normal shared hosting because it uses:

- procedural PHP
- no Composer dependency requirement
- no queue workers
- no daemon process
- MySQL-compatible schema changes
- browser-side chart rendering with plain JavaScript

## Operational Workflows

### New product + batch

1. Open `form_in.php`
2. Switch to `New Product`
3. Fill product master details
4. Fill batch details
5. Save the IN record

### Existing product + new batch

1. Open `form_in.php`
2. Keep `Existing Product`
3. Select the product master entry
4. Enter batch details
5. Save the IN record

### Correct a live record

1. Open a batch, OUT, RETURN, or product record
2. Click the correction entry point
3. Update the values
4. Enter a required reason
5. Save the correction

### Void a record

1. Open the transaction or batch detail page
2. Enter a required void or reversal reason
3. Submit the action
4. The system updates status and stock safely without deleting history

## Screenshots

Suggested screenshots for the repository:

- login page
- dashboard with filters and charts
- product master list
- product detail with barcode label
- inventory IN
- inventory OUT
- inventory RETURN
- batch history
- alerts center
- notifications center
- correction log
- reports and analytics

## Post-Migration Checklist

After applying the migration:

1. Sign in with an admin or manager account.
2. Review the `products` table and verify legacy products were backfilled.
3. Open the dashboard and confirm inventory rows still load.
4. Create a test IN, OUT, and RETURN transaction.
5. Correct one OUT or RETURN transaction and verify the correction log updates.
6. Void a test RETURN transaction and verify stock recalculates.
7. Open `notifications.php` and confirm alert rows generate notifications.
8. Open `label_print.php` through a product or batch page and verify barcode output.

## Notes About Email Alerts

This flagship iteration ships with in-app notifications as the default alert delivery mechanism.

Why:

- shared-hosting friendly
- no hardcoded credentials
- no SMTP dependency
- safer first deployment path

If you later want email delivery, the correct next step is to add a mail transport configuration page and use `app_settings` for non-hardcoded SMTP or `mail()` settings.

## Author

**Shreyansh Jain**

GitHub: [shreyanshjain1](https://github.com/shreyanshjain1)

## License

This project is for portfolio, educational, and internal operational enhancement purposes unless otherwise specified.
