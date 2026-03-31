<?php
require_once 'includes/common.php';
require_login();
require_permission('dashboard.view');

seed_alert_notifications($conn);

$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'source' => trim((string) ($_GET['source'] ?? 'all')),
    'stock_filter' => trim((string) ($_GET['stock_filter'] ?? 'all')),
    'expiry_filter' => trim((string) ($_GET['expiry_filter'] ?? 'all')),
    'manufacturer' => trim((string) ($_GET['manufacturer'] ?? '')),
    'distributor' => trim((string) ($_GET['distributor'] ?? '')),
    'qty_min' => trim((string) ($_GET['qty_min'] ?? '')),
    'qty_max' => trim((string) ($_GET['qty_max'] ?? '')),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
];

$stats = fetch_dashboard_stats($conn, $filters);
$options = fetch_dashboard_filter_options($conn);
$inventory = fetch_inventory_grouped($conn, false, $filters);
$outsourcedInventory = fetch_inventory_grouped($conn, true, $filters);
$recentActivity = fetch_recent_activity($conn, 8);
$alerts = array_slice(fetch_alert_rows($conn, $filters), 0, 6);
$reportData = fetch_report_dataset($conn, [
    'date_from' => date('Y-m-d', strtotime('-13 days')),
    'date_to' => date('Y-m-d'),
    'source' => $filters['source'],
]);

$regularQty = (int) ($stats['regular_qty'] ?? 0);
$regularItems = (int) ($stats['regular_items'] ?? 0);
$outsourcedQty = (int) ($stats['outsourced_qty'] ?? 0);
$outsourcedItems = (int) ($stats['outsourced_items'] ?? 0);
$lowStock = (int) ($stats['low_stock'] ?? 0);
$movementsToday = (int) ($stats['movements_today'] ?? 0);
$expiringSoon = (int) ($stats['expiring_soon'] ?? 0);
$expired = (int) ($stats['expired'] ?? 0);
$outOfStock = (int) ($stats['out_of_stock'] ?? 0);
$totalBatches = $regularItems + $outsourcedItems;
$totalAvailableQty = $regularQty + $outsourcedQty;
[$healthLabel, $healthClass] = inventory_health_label($stats);

function activity_badge_class(string $type): string
{
    return match (strtoupper($type)) {
        'OUT' => 'badge-red',
        'RETURN' => 'badge-orange',
        'AUDIT' => 'badge-blue',
        default => 'badge-green',
    };
}

function dashboard_expiry_badge(mysqli $conn, string $expDate): string
{
    return match (expiry_bucket($conn, $expDate)) {
        'expired', 'critical' => 'badge-red',
        'soon' => 'badge-orange',
        default => 'badge-green',
    };
}

function dashboard_expiry_text(string $expDate): string
{
    return $expDate !== '' ? $expDate : 'N/A';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PITC Inventory Flagship</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
    <script src="assets/app.js" defer></script>
</head>
<body>
<div class="app-shell">
    <header class="hero-shell">
        <div class="hero-main">
            <div class="hero-brand">
                <div class="hero-logo-wrap">
                    <img src="images/logo.png" alt="PITC logo" class="hero-logo">
                </div>
                <div class="hero-copy">
                    <div class="eyebrow">Medicine Inventory Flagship</div>
                    <h1>PITC Inventory Dashboard</h1>
                    <p>Advanced filters, movement charts, product-aware thresholds, alerts, correction-ready operations, and role-based controls for the current medicine inventory workflow.</p>
                </div>
            </div>
            <div class="hero-meta-row">
                <div class="hero-pill"><span class="hero-pill-label">User</span><strong><?php echo h(current_user_name()); ?></strong></div>
                <div class="hero-pill"><span class="hero-pill-label">Role</span><strong><?php echo h(current_user_role_label()); ?></strong></div>
                <div class="hero-pill"><span class="hero-pill-label">System Time</span><strong id="liveDateTime"></strong></div>
                <div class="hero-pill <?php echo h($healthClass); ?>"><span class="hero-pill-label">Inventory Health</span><strong><?php echo h($healthLabel); ?></strong></div>
            </div>
        </div>
        <div class="hero-side">
            <div class="hero-side-card">
                <div class="side-card-label">Total Available Quantity</div>
                <div class="side-card-value"><?php echo number_format($totalAvailableQty); ?></div>
                <div class="side-card-sub"><?php echo number_format($totalBatches); ?> active batches in the filtered view</div>
            </div>
            <div class="hero-side-stack">
                <div class="side-mini"><span>Low Stock</span><strong><?php echo number_format($lowStock); ?></strong></div>
                <div class="side-mini"><span>Expiring Soon</span><strong><?php echo number_format($expiringSoon); ?></strong></div>
                <div class="side-mini"><span>Expired</span><strong><?php echo number_format($expired); ?></strong></div>
                <div class="side-mini"><span>Out of Stock</span><strong><?php echo number_format($outOfStock); ?></strong></div>
            </div>
            <form action="logout.php" method="post" class="logout-form">
                <?php echo csrf_field(); ?>
                <button class="btn btn-danger btn-block" type="submit">Logout</button>
            </form>
        </div>
    </header>

    <section class="card page-nav-card">
        <div class="nav-shell">
            <div class="nav-links">
                <?php foreach (navigation_items() as $item): ?>
                    <?php if (!user_can($item['permission'])) {
                        continue;
                    } ?>
                    <a class="nav-link <?php echo basename($item['href']) === 'dashboard.php' ? 'is-active' : ''; ?>" href="<?php echo h($item['href']); ?>">
                        <?php echo h($item['label']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="nav-actions">
                <?php if (user_can('notifications.view')): ?>
                    <a class="btn btn-soft btn-mini" href="notifications.php">Notifications (<?php echo number_format(unread_notification_count($conn)); ?>)</a>
                <?php endif; ?>
                <?php if (user_can('history.view')): ?>
                    <a class="btn btn-soft btn-mini" href="batch_history.php">Batch History</a>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($flash = get_flash()): ?>
            <div class="flash <?php echo $flash['type'] === 'success' ? 'flash-success' : 'flash-error'; ?>">
                <?php echo h($flash['message']); ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="stats-grid">
        <article class="card stat-card stat-primary"><div class="stat-head"><span class="stat-icon">R</span><span class="stat-label">Regular Inventory</span></div><div class="stat-value"><?php echo number_format($regularQty); ?></div><div class="stat-sub"><?php echo number_format($regularItems); ?> regular batches</div></article>
        <article class="card stat-card stat-secondary"><div class="stat-head"><span class="stat-icon">O</span><span class="stat-label">Outsourced Inventory</span></div><div class="stat-value"><?php echo number_format($outsourcedQty); ?></div><div class="stat-sub"><?php echo number_format($outsourcedItems); ?> outsourced batches</div></article>
        <article class="card stat-card stat-warning"><div class="stat-head"><span class="stat-icon">A</span><span class="stat-label">At-Risk Rows</span></div><div class="stat-value"><?php echo number_format($lowStock + $expired + $expiringSoon); ?></div><div class="stat-sub"><?php echo number_format($lowStock); ?> low stock and <?php echo number_format($expired); ?> expired</div></article>
        <article class="card stat-card stat-success"><div class="stat-head"><span class="stat-icon">M</span><span class="stat-label">Movements Today</span></div><div class="stat-value"><?php echo number_format($movementsToday); ?></div><div class="stat-sub">IN, OUT, and RETURN transactions logged today</div></article>
    </section>

    <section class="card action-panel">
        <div class="action-panel-top">
            <div>
                <h2 class="section-title">Quick actions</h2>
                <p class="section-subtitle">Run transactions, review traceability, open reports, and work from the product master without leaving the main dashboard.</p>
            </div>
        </div>
        <div class="action-toolbar">
            <div class="action-group">
                <?php if (user_can('inventory.in.create')): ?><a class="btn btn-primary" href="form_in.php">Inventory IN</a><?php endif; ?>
                <?php if (user_can('inventory.out.create')): ?><a class="btn btn-success" href="form_out.php">Inventory OUT</a><?php endif; ?>
                <?php if (user_can('inventory.return.create')): ?><a class="btn btn-warning" href="form_return.php">Inventory RETURN</a><?php endif; ?>
                <?php if (user_can('products.view')): ?><a class="btn btn-soft" href="products.php">Product Master</a><?php endif; ?>
            </div>
            <div class="action-group">
                <?php if (user_can('reports.view')): ?><a class="btn btn-soft" href="reports.php">Analytics Reports</a><?php endif; ?>
                <?php if (user_can('corrections.view')): ?><a class="btn btn-soft" href="correction_logs.php">Correction Log</a><?php endif; ?>
                <?php if (user_can('exports.view')): ?><a class="btn btn-soft" href="export_center.php">Export Center</a><?php endif; ?>
            </div>
        </div>
    </section>

    <section class="card filter-panel">
        <div class="section-header">
            <div>
                <h2 class="section-title">Advanced dashboard filters</h2>
                <p class="section-subtitle">Sharable URL-based filters for source, stock health, expiry risk, manufacturer, distributor, quantity range, and entry window.</p>
            </div>
        </div>
        <form method="get" class="filter-grid">
            <div class="field field-span-2"><label for="search">Search</label><input id="search" type="text" name="search" value="<?php echo h($filters['search']); ?>" placeholder="Medicine, batch, manufacturer, distributor, barcode..."></div>
            <div class="field"><label for="source">Source</label><select id="source" name="source"><option value="all" <?php echo selected_attr($filters['source'], 'all'); ?>>All Sources</option><option value="regular" <?php echo selected_attr($filters['source'], 'regular'); ?>>Regular</option><option value="outsourced" <?php echo selected_attr($filters['source'], 'outsourced'); ?>>Outsourced</option></select></div>
            <div class="field"><label for="stock_filter">Stock Status</label><select id="stock_filter" name="stock_filter"><option value="all" <?php echo selected_attr($filters['stock_filter'], 'all'); ?>>All</option><option value="healthy" <?php echo selected_attr($filters['stock_filter'], 'healthy'); ?>>Healthy</option><option value="low_stock" <?php echo selected_attr($filters['stock_filter'], 'low_stock'); ?>>Low Stock</option><option value="out_of_stock" <?php echo selected_attr($filters['stock_filter'], 'out_of_stock'); ?>>Out of Stock</option></select></div>
            <div class="field"><label for="expiry_filter">Expiry Status</label><select id="expiry_filter" name="expiry_filter"><option value="all" <?php echo selected_attr($filters['expiry_filter'], 'all'); ?>>All</option><option value="healthy" <?php echo selected_attr($filters['expiry_filter'], 'healthy'); ?>>Healthy</option><option value="expiring_soon" <?php echo selected_attr($filters['expiry_filter'], 'expiring_soon'); ?>>Expiring Soon</option><option value="critical" <?php echo selected_attr($filters['expiry_filter'], 'critical'); ?>>Critical</option><option value="expired" <?php echo selected_attr($filters['expiry_filter'], 'expired'); ?>>Expired</option></select></div>
            <div class="field"><label for="manufacturer">Manufacturer</label><select id="manufacturer" name="manufacturer"><option value="">All Manufacturers</option><?php foreach ($options['manufacturers'] as $manufacturer): ?><option value="<?php echo h($manufacturer); ?>" <?php echo selected_attr($filters['manufacturer'], $manufacturer); ?>><?php echo h($manufacturer); ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="distributor">Distributor</label><select id="distributor" name="distributor"><option value="">All Distributors</option><?php foreach ($options['distributors'] as $distributor): ?><option value="<?php echo h($distributor); ?>" <?php echo selected_attr($filters['distributor'], $distributor); ?>><?php echo h($distributor); ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="qty_min">Min Qty</label><input id="qty_min" type="number" name="qty_min" min="0" value="<?php echo h($filters['qty_min']); ?>"></div>
            <div class="field"><label for="qty_max">Max Qty</label><input id="qty_max" type="number" name="qty_max" min="0" value="<?php echo h($filters['qty_max']); ?>"></div>
            <div class="field"><label for="date_from">Entry Date From</label><input id="date_from" type="date" name="date_from" value="<?php echo h($filters['date_from']); ?>"></div>
            <div class="field"><label for="date_to">Entry Date To</label><input id="date_to" type="date" name="date_to" value="<?php echo h($filters['date_to']); ?>"></div>
            <div class="field field-full"><div class="inline-actions"><button class="btn btn-primary" type="submit">Apply Filters</button><a class="btn btn-outline" href="dashboard.php">Reset</a></div></div>
        </form>
    </section>

    <section class="chart-grid">
        <article class="card chart-card">
            <div class="section-header"><div><h2 class="section-title">Daily movement totals</h2><p class="section-subtitle">Last 14 days of IN, OUT, and RETURN quantities.</p></div></div>
            <canvas class="chart-canvas" data-chart="multi-line" data-labels='<?php echo h(json_encode($reportData['chart_data']['movement_labels'])); ?>' data-series='<?php echo h(json_encode([["label" => "IN", "data" => $reportData['chart_data']['movement_in'], "color" => "#1a73e8"], ["label" => "OUT", "data" => $reportData['chart_data']['movement_out'], "color" => "#d93025"], ["label" => "RETURN", "data" => $reportData['chart_data']['movement_return'], "color" => "#f29900"]])); ?>'></canvas>
        </article>
        <article class="card chart-card">
            <div class="section-header"><div><h2 class="section-title">Stock composition</h2><p class="section-subtitle">Current quantity split between regular and outsourced stock.</p></div></div>
            <canvas class="chart-canvas" data-chart="bar" data-labels='<?php echo h(json_encode($reportData['chart_data']['source_labels'])); ?>' data-values='<?php echo h(json_encode($reportData['chart_data']['source_values'])); ?>' data-color="#0f5fd6"></canvas>
        </article>
        <article class="card chart-card">
            <div class="section-header"><div><h2 class="section-title">Stock health mix</h2><p class="section-subtitle">Healthy, low stock, and out-of-stock batch counts.</p></div></div>
            <canvas class="chart-canvas" data-chart="bar" data-labels='<?php echo h(json_encode($reportData['chart_data']['stock_health_labels'])); ?>' data-values='<?php echo h(json_encode($reportData['chart_data']['stock_health_values'])); ?>' data-color="#188038"></canvas>
        </article>
        <article class="card chart-card">
            <div class="section-header"><div><h2 class="section-title">Top moved products</h2><p class="section-subtitle">Highest outgoing products in the current reporting window.</p></div></div>
            <canvas class="chart-canvas" data-chart="bar" data-labels='<?php echo h(json_encode($reportData['chart_data']['top_moved_labels'])); ?>' data-values='<?php echo h(json_encode($reportData['chart_data']['top_moved_values'])); ?>' data-color="#174ea6"></canvas>
        </article>
    </section>

    <section class="content-grid">
        <div class="content-main">
            <div class="card section-card">
                <div class="section-header"><div><h2 class="section-title">Regular inventory</h2><p class="section-subtitle">Filtered regular stock view with product thresholds, barcode search support, and quick links into batch traceability.</p></div><div class="section-tools"><span class="badge badge-blue">Batches: <?php echo number_format($regularItems); ?></span></div></div>
                <div class="table-toolbar"><div class="search-control"><span class="search-icon">S</span><input id="regularSearch" class="search-input" type="text" placeholder="Filter visible regular rows..." onkeyup="liveSearch('regularSearch','regularTable','regularVisibleCount')"></div><div class="table-meta"><span class="table-counter">Visible rows: <strong id="regularVisibleCount">0</strong></span></div></div>
                <div class="table-wrap table-wrap-dashboard" id="regularTable">
                    <table class="data-table data-table-dashboard">
                        <thead><tr><th>Medicine</th><th>Batch No</th><th>Expiry</th><th>Manufacturer</th><th class="ta-right qty-col-header">Available</th><th class="ta-right">In</th><th class="ta-right">Out</th><th class="ta-right">Returned</th><th class="ta-center">Action</th></tr></thead>
                        <tbody>
                        <?php if ($inventory): foreach ($inventory as $groupKey => $batches): [$g, $b, $d] = explode('|', $groupKey); $totalQty = array_sum(array_column($batches, 'qty')); ?>
                            <tr class="group-row" data-group-row="1"><td colspan="9"><div class="group-row-inner"><span class="group-title"><?php echo h($g . ' - ' . $b . ' (' . $d . ')'); ?></span><span class="group-total">Total available: <?php echo number_format($totalQty); ?></span></div></td></tr>
                            <?php foreach ($batches as $item): ?>
                                <tr data-search-row="1" class="<?php echo h(expiry_badge_class($item['exp_date'] ?? '')); ?>">
                                    <td><div class="medicine-cell"><strong><?php echo h($item['generic_name']); ?></strong><span><?php echo h($item['brand_name'] . ' | ' . $item['dosage_strength']); ?></span></div></td>
                                    <td><span class="batch-pill"><?php echo h($item['batch_no']); ?></span></td>
                                    <td><span class="badge <?php echo dashboard_expiry_badge($conn, (string) ($item['exp_date'] ?? '')); ?>"><?php echo h(dashboard_expiry_text((string) ($item['exp_date'] ?? ''))); ?></span></td>
                                    <td><?php echo h($item['manufacturer']); ?></td>
                                    <td class="ta-right qty-cell qty-available"><?php echo number_format((int) $item['qty']); ?></td>
                                    <td class="ta-right qty-mini"><?php echo number_format((int) $item['qty_in']); ?></td>
                                    <td class="ta-right qty-mini"><?php echo number_format((int) $item['qty_out']); ?></td>
                                    <td class="ta-right qty-mini"><?php echo number_format((int) $item['qty_returned']); ?></td>
                                    <td class="ta-center"><a class="btn btn-mini btn-soft" href="batch_history.php?batch=<?php echo urlencode((string) $item['batch_no']); ?>">View</a></td>
                                </tr>
                            <?php endforeach; endforeach; else: ?>
                            <tr><td colspan="9" class="empty-state">No regular inventory rows matched the current filters.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card section-card">
                <div class="section-header"><div><h2 class="section-title">Outsourced inventory</h2><p class="section-subtitle">Distributor-aware outsourced stock with the same compact layout and shared filters.</p></div><div class="section-tools"><span class="badge badge-blue">Batches: <?php echo number_format($outsourcedItems); ?></span></div></div>
                <div class="table-toolbar"><div class="search-control"><span class="search-icon">S</span><input id="outsourcedSearch" class="search-input" type="text" placeholder="Filter visible outsourced rows..." onkeyup="liveSearch('outsourcedSearch','outsourcedTable','outsourcedVisibleCount')"></div><div class="table-meta"><span class="table-counter">Visible rows: <strong id="outsourcedVisibleCount">0</strong></span></div></div>
                <div class="table-wrap table-wrap-dashboard" id="outsourcedTable">
                    <table class="data-table data-table-dashboard">
                        <thead><tr><th>Medicine</th><th>Batch No</th><th>Expiry</th><th>Distributor</th><th class="ta-right qty-col-header">Available</th><th class="ta-right">In</th><th class="ta-right">Out</th><th class="ta-right">Returned</th><th class="ta-center">Action</th></tr></thead>
                        <tbody>
                        <?php if ($outsourcedInventory): foreach ($outsourcedInventory as $groupKey => $batches): [$g, $b, $d] = explode('|', $groupKey); $totalQty = array_sum(array_column($batches, 'qty')); ?>
                            <tr class="group-row" data-group-row="1"><td colspan="9"><div class="group-row-inner"><span class="group-title"><?php echo h($g . ' - ' . $b . ' (' . $d . ')'); ?></span><span class="group-total">Total available: <?php echo number_format($totalQty); ?></span></div></td></tr>
                            <?php foreach ($batches as $item): ?>
                                <tr data-search-row="1" class="<?php echo h(expiry_badge_class($item['exp_date'] ?? '')); ?>">
                                    <td><div class="medicine-cell"><strong><?php echo h($item['generic_name']); ?></strong><span><?php echo h($item['brand_name'] . ' | ' . $item['dosage_strength']); ?></span></div></td>
                                    <td><span class="batch-pill"><?php echo h($item['batch_no']); ?></span></td>
                                    <td><span class="badge <?php echo dashboard_expiry_badge($conn, (string) ($item['exp_date'] ?? '')); ?>"><?php echo h(dashboard_expiry_text((string) ($item['exp_date'] ?? ''))); ?></span></td>
                                    <td><?php echo h($item['distributor_name'] ?? ''); ?></td>
                                    <td class="ta-right qty-cell qty-available"><?php echo number_format((int) $item['qty']); ?></td>
                                    <td class="ta-right qty-mini"><?php echo number_format((int) $item['qty_in']); ?></td>
                                    <td class="ta-right qty-mini"><?php echo number_format((int) $item['qty_out']); ?></td>
                                    <td class="ta-right qty-mini"><?php echo number_format((int) $item['qty_returned']); ?></td>
                                    <td class="ta-center"><a class="btn btn-mini btn-soft" href="batch_history.php?batch=<?php echo urlencode((string) $item['batch_no']); ?>">View</a></td>
                                </tr>
                            <?php endforeach; endforeach; else: ?>
                            <tr><td colspan="9" class="empty-state">No outsourced inventory rows matched the current filters.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <aside class="content-side">
            <div class="card side-panel">
                <div class="side-panel-head"><div><h2 class="section-title">Alerts snapshot</h2><p class="section-subtitle">Filtered items that need attention right now.</p></div><a class="btn btn-mini btn-soft" href="alerts.php">Open all</a></div>
                <div class="insight-list">
                    <?php if ($alerts): foreach ($alerts as $row): ?>
                        <div class="insight-item insight-alert-item"><div class="insight-copy"><strong><?php echo h($row['generic_name'] . ' | ' . $row['batch_no']); ?></strong><span><?php echo h(implode(' | ', $row['issues'])); ?> | <?php echo h($row['source_type']); ?></span></div><div class="insight-value"><?php echo number_format((int) $row['qty']); ?></div></div>
                    <?php endforeach; else: ?>
                        <div class="empty-state">No alert rows matched the current filters.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card side-panel">
                <div class="side-panel-head"><div><h2 class="section-title">Recent activity</h2><p class="section-subtitle">Latest movements and audit events.</p></div><a class="btn btn-mini btn-soft" href="activity_logs.php">View log</a></div>
                <div class="activity-list activity-list-premium">
                    <?php if ($recentActivity): foreach ($recentActivity as $activity): ?>
                        <div class="activity-card"><div class="activity-card-top"><span class="badge <?php echo activity_badge_class((string) ($activity['movement_type'] ?? 'IN')); ?>"><?php echo h($activity['movement_type']); ?></span><span class="activity-qty"><?php echo (int) ($activity['qty'] ?? 0) > 0 ? number_format((int) $activity['qty']) . ' qty' : 'Log'; ?></span></div><div class="activity-ref"><?php echo h($activity['reference']); ?></div><div class="activity-meta-grid"><div><span class="activity-meta-label">Actor</span><strong><?php echo h($activity['actor']); ?></strong></div><div><span class="activity-meta-label">Time</span><strong><?php echo h(format_datetime($activity['activity_at'] ?? '')); ?></strong></div></div></div>
                    <?php endforeach; else: ?>
                        <div class="empty-state">No recent activity found.</div>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
    </section>

    <footer class="footer">PITC Inventory Flagship | Role-aware medicine inventory, reporting, corrections, reversals, notifications, and barcode labels</footer>
</div>
</body>
</html>
