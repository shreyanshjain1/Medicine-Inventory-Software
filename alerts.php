<?php
require_once 'includes/common.php';
require_login();
require_permission('alerts.view');

seed_alert_notifications($conn);

$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'source' => trim((string) ($_GET['source'] ?? 'all')),
    'stock_filter' => trim((string) ($_GET['stock_filter'] ?? 'all')),
    'expiry_filter' => trim((string) ($_GET['expiry_filter'] ?? 'all')),
];
$summary = fetch_alert_summary($conn, $filters);
$rows = fetch_alert_rows($conn, $filters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alerts Center</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="app-shell app-shell-form">
    <?php render_app_nav($conn, 'Alerts Center', 'Track low stock, expiring, expired, and out-of-stock rows with role-aware access and notification seeding.', 'Operational Alerts'); ?>
    <section class="card form-page-card">
        <section class="stats-grid stats-grid-compact">
            <article class="card stat-card stat-warning"><div class="stat-label">Low Stock</div><div class="stat-value"><?php echo number_format($summary['low_stock']); ?></div></article>
            <article class="card stat-card stat-danger"><div class="stat-label">Out of Stock</div><div class="stat-value"><?php echo number_format($summary['out_of_stock']); ?></div></article>
            <article class="card stat-card stat-warning"><div class="stat-label">Expiring Soon</div><div class="stat-value"><?php echo number_format($summary['expiring_soon']); ?></div></article>
            <article class="card stat-card stat-danger"><div class="stat-label">Expired / Critical</div><div class="stat-value"><?php echo number_format($summary['expired'] + $summary['critical_expiry']); ?></div></article>
        </section>
        <form method="get" class="filter-toolbar filter-toolbar-wide">
            <div class="search-control"><span class="search-icon">S</span><input class="search-input" type="text" name="search" value="<?php echo h($filters['search']); ?>" placeholder="Search alerts..."></div>
            <select class="search-input search-select" name="source"><option value="all" <?php echo selected_attr($filters['source'], 'all'); ?>>All sources</option><option value="regular" <?php echo selected_attr($filters['source'], 'regular'); ?>>Regular</option><option value="outsourced" <?php echo selected_attr($filters['source'], 'outsourced'); ?>>Outsourced</option></select>
            <select class="search-input search-select" name="stock_filter"><option value="all" <?php echo selected_attr($filters['stock_filter'], 'all'); ?>>All stock states</option><option value="low_stock" <?php echo selected_attr($filters['stock_filter'], 'low_stock'); ?>>Low stock</option><option value="out_of_stock" <?php echo selected_attr($filters['stock_filter'], 'out_of_stock'); ?>>Out of stock</option></select>
            <select class="search-input search-select" name="expiry_filter"><option value="all" <?php echo selected_attr($filters['expiry_filter'], 'all'); ?>>All expiry states</option><option value="expiring_soon" <?php echo selected_attr($filters['expiry_filter'], 'expiring_soon'); ?>>Expiring soon</option><option value="critical" <?php echo selected_attr($filters['expiry_filter'], 'critical'); ?>>Critical</option><option value="expired" <?php echo selected_attr($filters['expiry_filter'], 'expired'); ?>>Expired</option></select>
            <button class="btn btn-primary" type="submit">Apply</button>
            <a class="btn btn-outline" href="alerts.php">Reset</a>
        </form>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Medicine</th><th>Batch</th><th>Source</th><th>Issue</th><th>Expiry</th><th class="ta-right">Available</th><th>Supplier</th><th>Action</th></tr></thead>
                <tbody>
                <?php if ($rows): foreach ($rows as $row): ?>
                    <tr class="<?php echo h(expiry_badge_class($row['exp_date'] ?? '')); ?>">
                        <td><strong><?php echo h((string) $row['generic_name']); ?></strong><br><span class="muted"><?php echo h((string) ($row['brand_name'] . ' | ' . $row['dosage_strength'])); ?></span></td>
                        <td><?php echo h((string) $row['batch_no']); ?></td>
                        <td><?php echo h((string) $row['source_type']); ?></td>
                        <td><?php foreach ($row['issues'] as $issue): ?><span class="badge <?php echo str_contains(strtolower($issue), 'expired') || str_contains(strtolower($issue), 'critical') || str_contains(strtolower($issue), 'out of stock') ? 'badge-red' : 'badge-orange'; ?>"><?php echo h($issue); ?></span> <?php endforeach; ?></td>
                        <td><?php echo h((string) $row['exp_date']); ?></td>
                        <td class="ta-right qty-cell"><?php echo number_format((int) $row['qty']); ?></td>
                        <td><?php echo h($row['source_key'] === 'outsourced' ? (string) ($row['distributor_name'] ?? '') : (string) ($row['manufacturer'] ?? '')); ?></td>
                        <td><a class="btn btn-mini btn-soft" href="batch_history.php?batch=<?php echo urlencode((string) $row['batch_no']); ?>">View batch</a></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="8" class="empty-state">No alert rows matched your filters.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php close_page_stack(); ?>
</div>
</body>
</html>
