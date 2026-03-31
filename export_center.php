<?php
require_once 'includes/common.php';
require_login();
require_permission('exports.view');
$stats = fetch_dashboard_stats($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Center</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="app-shell app-shell-form">
    <?php render_app_nav($conn, 'Export Center', 'Generate CSV exports and print-friendly pages for inventory, batches, and analytics.', 'Reporting & Downloads'); ?>
    <section class="card form-page-card">
        <div class="export-grid">
            <div class="export-card"><h2 class="section-title">Regular Inventory</h2><p class="section-subtitle">Export the current active regular inventory table.</p><div class="inline-actions"><a class="btn btn-primary" href="export_inventory.php?scope=regular&format=csv">Export CSV</a><a class="btn btn-outline" target="_blank" href="export_inventory.php?scope=regular&format=pdf">Open Print View</a></div></div>
            <div class="export-card"><h2 class="section-title">Outsourced Inventory</h2><p class="section-subtitle">Export active outsourced batches with distributor and importer fields.</p><div class="inline-actions"><a class="btn btn-primary" href="export_inventory.php?scope=outsourced&format=csv">Export CSV</a><a class="btn btn-outline" target="_blank" href="export_inventory.php?scope=outsourced&format=pdf">Open Print View</a></div></div>
            <div class="export-card"><h2 class="section-title">Combined Inventory</h2><p class="section-subtitle">Export both regular and outsourced stock in one report.</p><div class="inline-actions"><a class="btn btn-primary" href="export_inventory.php?scope=all&format=csv">Export CSV</a><a class="btn btn-outline" target="_blank" href="export_inventory.php?scope=all&format=pdf">Open Print View</a></div></div>
            <div class="export-card"><h2 class="section-title">Analytics Reports</h2><p class="section-subtitle">Export report analytics with date range filters.</p><div class="inline-actions"><a class="btn btn-primary" href="export_report.php?format=csv">Export Report CSV</a><a class="btn btn-outline" target="_blank" href="reports.php">Open Reports</a></div></div>
        </div>
        <div class="card info-banner" style="margin-top:18px;"><strong>Current active snapshot:</strong> <?php echo number_format((int) $stats['regular_items'] + (int) $stats['outsourced_items']); ?> total batches | <?php echo number_format((int) $stats['low_stock']); ?> low stock | <?php echo number_format((int) $stats['expired']); ?> expired | <?php echo number_format((int) $stats['expiring_soon']); ?> expiring soon</div>
    </section>
    <?php close_page_stack(); ?>
</div>
</body>
</html>
