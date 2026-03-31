# Medicine Inventory Software

![PHP](https://img.shields.io/badge/PHP-8+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-Frontend-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![HTML5](https://img.shields.io/badge/HTML5-Markup-E34F26?style=for-the-badge&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-Styling-1572B6?style=for-the-badge&logo=css3&logoColor=white)
![Bootstrap Inspired UI](https://img.shields.io/badge/UI-Modern%20Admin-0F172A?style=for-the-badge)
![Status](https://img.shields.io/badge/Status-Active%20Development-16A34A?style=for-the-badge)

A polished web-based medicine inventory management system built with **PHP, MySQL, JavaScript, HTML, and CSS** for tracking stock movement, batch-level inventory, outsourced inventory, return handling, batch history, alerts, exports, and operational activity.

This project was upgraded into a more refined **v3-style inventory platform** while keeping the **existing database logic and table references intact**.

---

## Overview

This system is designed for organizations that need to manage medicine inventory with clear visibility over:

- Inventory IN
- Inventory OUT
- Inventory RETURN
- Batch-level quantity tracking
- Regular and outsourced inventory
- Batch movement history
- Alerts and inventory health visibility
- Export to Excel / CSV and print-ready PDF views
- Activity monitoring

The goal of this project is to provide a more production-minded version of a medicine inventory tracker while still being easy to deploy on common PHP/MySQL hosting environments.

---

## Core Features

### Dashboard
- Modern inventory dashboard UI
- Summary KPI cards
- Compact inventory tables
- Regular inventory and outsourced inventory views
- Visible stock quantities without heavy clutter
- Recent activity sidebar
- Health indicators for inventory status

### Inventory IN
- Add stock for existing products
- Add completely new products
- Support for regular and outsourced inventory
- Distributor field for outsourced entries
- Duplicate batch protection flow
- Cleaner transaction form layout

### Inventory OUT
- Release stock from existing inventory batches
- Store customer name, document type, and document number
- Quantity validation against available stock
- Live selected-batch preview before saving

### Inventory RETURN
- Bind returns directly to original OUT records
- Preserve movement traceability
- Return quantity validation
- Live preview of returnable quantity and linked document info

### Batch History
- Search any batch number
- View IN / OUT / RETURN history
- Batch summary cards
- Readable movement tables
- Export batch history

### Alerts Center
- Low-stock visibility
- Expiring-soon visibility
- Better operational awareness

### Activity Logs
- Inventory movement log view
- Easier review of transaction history

### Export Center
- Export regular inventory
- Export outsourced inventory
- Print-friendly PDF views
- Better reporting workflow

---

## Modules

- `dashboard.php` — main dashboard
- `form_in.php` — inventory in transaction page
- `form_out.php` — inventory out transaction page
- `form_return.php` — inventory return transaction page
- `batch_history.php` — batch traceability and movement history
- `alerts.php` — inventory alerts page
- `activity_logs.php` — transaction activity page
- `export_center.php` — export options page
- `export_inventory.php` — inventory export handler
- `export_batch_excel.php` — batch export to Excel/CSV
- `export_batch_pdf.php` — print-friendly batch PDF view
- `login.php` — user login
- `signup.php` — user registration
- `logout.php` — session logout
- `includes/common.php` — shared helper and utility logic
- `assets/app.css` — global styling
- `assets/app.js` — shared frontend interactions

---

## Main Functional Flow

### 1. Login
Users authenticate into the system through the login page.

### 2. Dashboard
After login, users land on the dashboard where they can:
- review overall stock
- check regular and outsourced inventory
- view recent movement activity
- access quick transaction actions
- open alerts and export tools

### 3. Inventory IN
Users can:
- add stock to an existing product profile
- create a new product batch
- classify new products as regular or outsourced

### 4. Inventory OUT
Users can:
- choose an existing batch
- release stock
- attach customer and document reference details

### 5. Inventory RETURN
Users can:
- select a linked OUT record
- return stock back into inventory
- preserve return traceability against the original release

### 6. Batch History
Users can:
- search by batch number
- review the batch details
- inspect OUT and RETURN records
- export batch-level history

---

## Database Notes

This upgraded version was intentionally built to **keep the original database structure and table references** as much as possible.

### Main tables used
- `inventory`
- `inventory_outsourced`
- `out_records`
- `return_binded_records`
- `in_log`
- user/auth related tables already present in the project

Because of that design choice, this version focuses on **UI, workflow, validation, visibility, and reporting improvements** without requiring a major schema rewrite.

---

## Tech Stack

- **Backend:** PHP
- **Database:** MySQL
- **Frontend:** HTML, CSS, JavaScript
- **Architecture style:** Procedural PHP with shared helper utilities
- **Exports:** CSV / Excel-compatible export and print-friendly PDF views

---

## Setup Instructions

### 1. Clone the repository
```bash
git clone https://github.com/shreyanshjain1/Medicine-Inventory-Software.git
```

### 2. Move project into your local server folder
Example:
- XAMPP `htdocs`
- WAMP `www`
- cPanel public directory or subfolder

### 3. Create the database
- Open **phpMyAdmin**
- Create your database
- Import the project SQL file if included
- Or manually use your existing database structure

### 4. Update database connection
Open:

```php
db.php
```

Update your database credentials there.

### 5. Run the project
Example local URL:

```text
http://localhost/Medicine-Inventory-Software-main/
```

---

## Recommended Local Environment

- PHP 8+
- MySQL / MariaDB
- XAMPP, WAMP, Laragon, or cPanel hosting
- Modern browser such as Chrome or Edge

---

## UI / UX Improvements Included

- Cleaner dashboard layout
- Better spacing and card hierarchy
- Improved inventory tables
- Easier-to-read quantity values
- Better batch history structure
- Improved Inventory IN / OUT / RETURN forms
- Cleaner export entry points
- Stronger visual consistency across the system

---

## Current Strengths

- Batch-based medicine inventory handling
- Separate outsourced inventory support
- Return binding to original OUT records
- Better visibility than a basic student CRUD project
- Usable admin-style interface
- Export-ready operational flow

---

## Suggested Future Improvements

- Role-based permissions
- Edit/correction workflow with reason logging
- Transaction reversal / voiding
- Product master management
- Notes / remarks per batch or transaction
- Stronger reporting analytics
- More advanced dashboard filters
- Chart-based movement analysis
- Email or notification alerts
- Barcode / QR support

---

## Screens to Highlight

Good pages to showcase in screenshots:
- Login page
- Dashboard
- Inventory IN
- Inventory OUT
- Inventory RETURN
- Batch History
- Alerts Center
- Activity Logs
- Export Center

---

## Why This Project Stands Out

This project goes beyond a simple inventory CRUD system by focusing on:

- batch traceability
- real movement workflows
- returns tied to original releases
- outsourced inventory support
- operational reporting
- polished admin interface improvements

It is a strong portfolio project for showcasing:
- PHP/MySQL development
- admin system architecture
- inventory workflow design
- UI improvement work on a legacy-style codebase
- practical business software development

---

## Author

**Shreyansh Jain**  
GitHub: [shreyanshjain1](https://github.com/shreyanshjain1)

---

## License

This project is for portfolio, educational, and internal system enhancement purposes unless otherwise specified.
