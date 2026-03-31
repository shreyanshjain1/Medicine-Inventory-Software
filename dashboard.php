<?php
require_once 'includes/common.php';
require_login();

$stats = fetch_dashboard_stats($conn);
$flash = get_flash();
$inventory = fetch_inventory_grouped($conn, false);
$outsourcedInventory = fetch_inventory_grouped($conn, true);
$recentActivity = fetch_recent_activity($conn, 8);
$alerts = array_slice(fetch_alert_rows($conn), 0, 6);

$regularQty = (int)($stats['regular_qty'] ?? 0);
$regularItems = (int)($stats['regular_items'] ?? 0);
$outsourcedQty = (int)($stats['outsourced_qty'] ?? 0);
$outsourcedItems = (int)($stats['outsourced_items'] ?? 0);
$lowStock = (int)($stats['low_stock'] ?? 0);
$movementsToday = (int)($stats['movements_today'] ?? 0);
$expiringSoon = (int)($stats['expiring_soon'] ?? 0);
$outOfStock = (int)($stats['out_of_stock'] ?? 0);
$totalBatches = $regularItems + $outsourcedItems;
$totalAvailableQty = $regularQty + $outsourcedQty;
[$healthLabel, $healthClass] = inventory_health_label($stats);

function activity_badge_class($type) {
    $type = strtoupper((string)$type);
    if ($type === 'OUT') return 'badge-red';
    if ($type === 'RETURN') return 'badge-orange';
    return 'badge-green';
}

function dashboard_expiry_badge($expDate) {
    $class = expiry_badge_class($expDate);
    if ($class === 'expiry-critical') return 'badge-red';
    if ($class === 'expiry-warning') return 'badge-orange';
    return 'badge-green';
}

function dashboard_expiry_text($expDate) {
    if (!$expDate) return 'N/A';
    $ts = strtotime($expDate);
    return $ts ? date('M Y', $ts) : (string)$expDate;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PITC Inventory v3</title>
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
                    <img src="images/logo.png" alt="PITC Logo" class="hero-logo">
                </div>
                <div class="hero-copy">
                    <div class="eyebrow">Medicine Inventory Platform v3</div>
                    <h1>PITC Inventory Dashboard</h1>
                    <p>Faster stock reading, clearer alerts, better exports, improved traceability, and a more polished operational workflow without changing your database structure.</p>
                </div>
            </div>

            <div class="hero-meta-row">
                <div class="hero-pill"><span class="hero-pill-label">User</span><strong><?php echo h(current_user_name()); ?></strong></div>
                <div class="hero-pill"><span class="hero-pill-label">Role</span><strong><?php echo h(current_user_role()); ?></strong></div>
                <div class="hero-pill"><span class="hero-pill-label">System Time</span><strong id="liveDateTime"></strong></div>
                <div class="hero-pill <?php echo h($healthClass); ?>"><span class="hero-pill-label">Inventory Health</span><strong><?php echo h($healthLabel); ?></strong></div>
            </div>
        </div>

        <div class="hero-side">
            <div class="hero-side-card">
                <div class="side-card-label">Total Available Quantity</div>
                <div class="side-card-value"><?php echo number_format($totalAvailableQty); ?></div>
                <div class="side-card-sub"><?php echo number_format($totalBatches); ?> total tracked batches</div>
            </div>

            <div class="hero-side-stack">
                <div class="side-mini"><span>Low Stock</span><strong><?php echo number_format($lowStock); ?></strong></div>
                <div class="side-mini"><span>Expiring Soon</span><strong><?php echo number_format($expiringSoon); ?></strong></div>
                <div class="side-mini"><span>Out of Stock</span><strong><?php echo number_format($outOfStock); ?></strong></div>
            </div>

            <form action="logout.php" method="post" class="logout-form">
                <button class="btn btn-danger btn-block" type="submit">Logout</button>
            </form>
        </div>
    </header>

    <?php if ($flash): ?>
        <div class="flash <?php echo $flash['type'] === 'success' ? 'flash-success' : 'flash-error'; ?>"><?php echo h($flash['message']); ?></div>
    <?php endif; ?>

    <section class="stats-grid">
        <article class="card stat-card stat-primary"><div class="stat-head"><span class="stat-icon">📦</span><span class="stat-label">Regular Inventory</span></div><div class="stat-value"><?php echo number_format($regularQty); ?></div><div class="stat-sub"><?php echo number_format($regularItems); ?> regular batches</div></article>
        <article class="card stat-card stat-secondary"><div class="stat-head"><span class="stat-icon">🏭</span><span class="stat-label">Outsourced Inventory</span></div><div class="stat-value"><?php echo number_format($outsourcedQty); ?></div><div class="stat-sub"><?php echo number_format($outsourcedItems); ?> outsourced batches</div></article>
        <article class="card stat-card stat-warning"><div class="stat-head"><span class="stat-icon">⚠️</span><span class="stat-label">Low Stock Rows</span></div><div class="stat-value"><?php echo number_format($lowStock); ?></div><div class="stat-sub">Batches with quantity 10 or below</div></article>
        <article class="card stat-card stat-success"><div class="stat-head"><span class="stat-icon">🔄</span><span class="stat-label">Movements Today</span></div><div class="stat-value"><?php echo number_format($movementsToday); ?></div><div class="stat-sub"><?php echo number_format($expiringSoon); ?> rows expiring within 6 months</div></article>
    </section>

    <section class="card action-panel">
        <div class="action-panel-top">
            <div>
                <h2 class="section-title">Quick actions</h2>
                <p class="section-subtitle">Run transactions, review alerts, inspect activity, and export operational reports from one control bar.</p>
            </div>
        </div>
        <div class="action-toolbar">
            <div class="action-group">
                <a class="btn btn-primary" href="form_in.php">+ Inventory IN</a>
                <a class="btn btn-success" href="form_out.php">- Inventory OUT</a>
                <a class="btn btn-warning" href="form_return.php">↩ Return Entry</a>
                <a class="btn btn-soft" href="batch_history.php">Batch History Lookup</a>
            </div>
            <div class="action-group">
                <a class="btn btn-soft" href="alerts.php">Alerts Center</a>
                <a class="btn btn-soft" href="activity_logs.php">Activity Log</a>
                <a class="btn btn-soft" href="export_center.php">Export Center</a>
            </div>
        </div>
    </section>

    <section class="content-grid">
        <div class="content-main">
            <div class="card section-card">
                <div class="section-header">
                    <div>
                        <h2 class="section-title">Regular inventory</h2>
                        <p class="section-subtitle">Dashboard view optimized for quick stock reading without horizontal scrolling.</p>
                    </div>
                    <div class="section-tools"><span class="badge badge-blue">Batches: <?php echo number_format($regularItems); ?></span></div>
                </div>
                <div class="table-toolbar">
                    <div class="search-control"><span class="search-icon">⌕</span><input id="regularSearch" class="search-input" type="text" placeholder="Search medicine, batch, manufacturer, expiry..." onkeyup="liveSearch('regularSearch','regularTable','regularVisibleCount')"></div>
                    <div class="table-meta"><span class="table-counter">Visible rows: <strong id="regularVisibleCount">0</strong></span></div>
                </div>
                <div class="table-wrap table-wrap-dashboard" id="regularTable">
                    <table class="data-table data-table-dashboard">
                        <thead><tr><th>Medicine</th><th>Batch No</th><th>Expiry</th><th>Manufacturer</th><th class="ta-right qty-col-header">Available</th><th class="ta-right">In</th><th class="ta-right">Out</th><th class="ta-right">Returned</th><th class="ta-center">Action</th></tr></thead>
                        <tbody>
                        <?php if ($inventory): foreach ($inventory as $groupKey => $batches): [$g, $b, $d] = explode('|', $groupKey); $totalQty = array_sum(array_column($batches, 'qty')); ?>
                            <tr class="group-row" data-group-row="1"><td colspan="9"><div class="group-row-inner"><span class="group-title"><?php echo h($g . ' - ' . $b . ' (' . $d . ')'); ?></span><span class="group-total">Total available: <?php echo number_format($totalQty); ?></span></div></td></tr>
                            <?php foreach ($batches as $item): ?>
                                <tr data-search-row="1" class="<?php echo expiry_badge_class($item['exp_date']); ?>">
                                    <td><div class="medicine-cell"><strong><?php echo h($item['generic_name']); ?></strong><span><?php echo h($item['brand_name'] . ' • ' . $item['dosage_strength']); ?></span></div></td>
                                    <td><span class="batch-pill"><?php echo h($item['batch_no']); ?></span></td>
                                    <td><span class="badge <?php echo dashboard_expiry_badge($item['exp_date']); ?>"><?php echo h(dashboard_expiry_text($item['exp_date'])); ?></span></td>
                                    <td><?php echo h($item['manufacturer']); ?></td>
                                    <td class="ta-right qty-cell qty-available"><?php echo number_format((int)$item['qty']); ?></td>
                                    <td class="ta-right qty-mini"><?php echo number_format((int)$item['qty_in']); ?></td>
                                    <td class="ta-right qty-mini"><?php echo number_format((int)$item['qty_out']); ?></td>
                                    <td class="ta-right qty-mini"><?php echo number_format((int)$item['qty_returned']); ?></td>
                                    <td class="ta-center"><a class="btn btn-mini btn-soft" href="batch_history.php?batch=<?php echo urlencode($item['batch_no']); ?>">View</a></td>
                                </tr>
                            <?php endforeach; endforeach; else: ?>
                            <tr><td colspan="9" class="empty-state">No regular inventory rows found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card section-card">
                <div class="section-header">
                    <div>
                        <h2 class="section-title">Outsourced inventory</h2>
                        <p class="section-subtitle">Same compact dashboard format with distributor visibility and readable quantities.</p>
                    </div>
                    <div class="section-tools"><span class="badge badge-blue">Batches: <?php echo number_format($outsourcedItems); ?></span></div>
                </div>
                <div class="table-toolbar">
                    <div class="search-control"><span class="search-icon">⌕</span><input id="outsourcedSearch" class="search-input" type="text" placeholder="Search medicine, batch, distributor, expiry..." onkeyup="liveSearch('outsourcedSearch','outsourcedTable','outsourcedVisibleCount')"></div>
                    <div class="table-meta"><span class="table-counter">Visible rows: <strong id="outsourcedVisibleCount">0</strong></span></div>
                </div>
                <div class="table-wrap table-wrap-dashboard" id="outsourcedTable">
                    <table class="data-table data-table-dashboard">
                        <thead><tr><th>Medicine</th><th>Batch No</th><th>Expiry</th><th>Distributor</th><th class="ta-right qty-col-header">Available</th><th class="ta-right">In</th><th class="ta-right">Out</th><th class="ta-right">Returned</th><th class="ta-center">Action</th></tr></thead>
                        <tbody>
                        <?php if ($outsourcedInventory): foreach ($outsourcedInventory as $groupKey => $batches): [$g, $b, $d] = explode('|', $groupKey); $totalQty = array_sum(array_column($batches, 'qty')); ?>
                            <tr class="group-row" data-group-row="1"><td colspan="9"><div class="group-row-inner"><span class="group-title"><?php echo h($g . ' - ' . $b . ' (' . $d . ')'); ?></span><span class="group-total">Total available: <?php echo number_format($totalQty); ?></span></div></td></tr>
                            <?php foreach ($batches as $item): ?>
                                <tr data-search-row="1" class="<?php echo expiry_badge_class($item['exp_date']); ?>">
                                    <td><div class="medicine-cell"><strong><?php echo h($item['generic_name']); ?></strong><span><?php echo h($item['brand_name'] . ' • ' . $item['dosage_strength']); ?></span></div></td>
                                    <td><span class="batch-pill"><?php echo h($item['batch_no']); ?></span></td>
                                    <td><span class="badge <?php echo dashboard_expiry_badge($item['exp_date']); ?>"><?php echo h(dashboard_expiry_text($item['exp_date'])); ?></span></td>
                                    <td><?php echo h($item['distributor_name'] ?? ''); ?></td>
                                    <td class="ta-right qty-cell qty-available"><?php echo number_format((int)$item['qty']); ?></td>
                                    <td class="ta-right qty-mini"><?php echo number_format((int)$item['qty_in']); ?></td>
                                    <td class="ta-right qty-mini"><?php echo number_format((int)$item['qty_out']); ?></td>
                                    <td class="ta-right qty-mini"><?php echo number_format((int)$item['qty_returned']); ?></td>
                                    <td class="ta-center"><a class="btn btn-mini btn-soft" href="batch_history.php?batch=<?php echo urlencode($item['batch_no']); ?>">View</a></td>
                                </tr>
                            <?php endforeach; endforeach; else: ?>
                            <tr><td colspan="9" class="empty-state">No outsourced inventory rows found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <aside class="content-side">
            <div class="card side-panel">
                <div class="side-panel-head"><div><h2 class="section-title">Alerts snapshot</h2><p class="section-subtitle">Items that need attention right now.</p></div><a class="btn btn-mini btn-soft" href="alerts.php">Open all</a></div>
                <div class="insight-list">
                    <?php if ($alerts): foreach ($alerts as $row): ?>
                        <div class="insight-item insight-alert-item">
                            <div class="insight-copy">
                                <strong><?php echo h($row['generic_name'] . ' • ' . $row['batch_no']); ?></strong>
                                <span><?php echo h(implode(' • ', $row['issues'])); ?> • <?php echo h($row['source_type']); ?></span>
                            </div>
                            <div class="insight-value"><?php echo number_format((int)$row['qty']); ?></div>
                        </div>
                    <?php endforeach; else: ?>
                        <div class="empty-state">No active alerts. Inventory looks healthy.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card side-panel">
                <div class="side-panel-head"><div><h2 class="section-title">Recent activity</h2><p class="section-subtitle">Latest movements across IN, OUT, and RETURN.</p></div><a class="btn btn-mini btn-soft" href="activity_logs.php">View log</a></div>
                <div class="activity-list activity-list-premium">
                    <?php if ($recentActivity): foreach ($recentActivity as $activity): ?>
                        <div class="activity-card">
                            <div class="activity-card-top"><span class="badge <?php echo activity_badge_class($activity['movement_type']); ?>"><?php echo h($activity['movement_type']); ?></span><span class="activity-qty"><?php echo $activity['qty'] ? number_format((int)$activity['qty']) . ' qty' : '-'; ?></span></div>
                            <div class="activity-ref"><?php echo h($activity['reference']); ?></div>
                            <div class="activity-meta-grid"><div><span class="activity-meta-label">Actor</span><strong><?php echo h($activity['actor']); ?></strong></div><div class="activity-time"><span class="activity-meta-label">Time</span><strong><?php echo h(date('M d, Y h:i A', strtotime($activity['activity_at']))); ?></strong></div></div>
                        </div>
                    <?php endforeach; else: ?>
                        <div class="empty-state">No recent activity found.</div>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
    </section>

    <footer class="footer">PITC Inventory Software v3 • Same database tables • Better dashboard, alerts, logs, exports, and forms</footer>
</div>
</body>
</html>
