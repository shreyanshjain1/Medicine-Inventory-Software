<?php
require_once 'includes/common.php';
require_login();
require_permission('reports.view');

$filters = [
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
    'source' => trim((string) ($_GET['source'] ?? 'all')),
];
$data = fetch_report_dataset($conn, $filters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
    <script src="assets/app.js" defer></script>
</head>
<body>
<div class="app-shell app-shell-form">
    <?php render_app_nav($conn, 'Reports & Analytics', 'Manager-friendly stock, movement, supplier, user, and expiry analytics with export-ready date filters.', 'Reporting Intelligence', ['<a class="btn btn-primary" href="export_report.php?date_from=' . urlencode($filters['date_from']) . '&date_to=' . urlencode($filters['date_to']) . '&source=' . urlencode($filters['source']) . '&format=csv">Export CSV</a>']); ?>
    <section class="card form-page-card">
        <form method="get" class="filter-toolbar filter-toolbar-wide">
            <input class="search-input search-select" type="date" name="date_from" value="<?php echo h($data['date_from']); ?>">
            <input class="search-input search-select" type="date" name="date_to" value="<?php echo h($data['date_to']); ?>">
            <select class="search-input search-select" name="source"><option value="all" <?php echo selected_attr($filters['source'], 'all'); ?>>All sources</option><option value="regular" <?php echo selected_attr($filters['source'], 'regular'); ?>>Regular</option><option value="outsourced" <?php echo selected_attr($filters['source'], 'outsourced'); ?>>Outsourced</option></select>
            <button class="btn btn-primary" type="submit">Apply</button>
            <a class="btn btn-outline" href="reports.php">Reset</a>
        </form>

        <section class="stats-grid stats-grid-compact">
            <article class="card stat-card stat-primary"><div class="stat-label">Current Stock Rows</div><div class="stat-value"><?php echo number_format(count($data['inventory_rows'])); ?></div></article>
            <article class="card stat-card stat-warning"><div class="stat-label">Low Stock Rows</div><div class="stat-value"><?php echo number_format(count($data['low_stock_rows'])); ?></div></article>
            <article class="card stat-card stat-danger"><div class="stat-label">Expired Rows</div><div class="stat-value"><?php echo number_format(count($data['expired_rows'])); ?></div></article>
            <article class="card stat-card stat-success"><div class="stat-label">Movement Qty</div><div class="stat-value"><?php echo number_format((int) $data['movement_summary']['in_qty'] + (int) $data['movement_summary']['out_qty'] + (int) $data['movement_summary']['return_qty']); ?></div></article>
        </section>

        <section class="chart-grid">
            <article class="card chart-card"><div class="section-header"><div><h2 class="section-title">Movement by day</h2><p class="section-subtitle">IN, OUT, and RETURN quantities within the selected window.</p></div></div><canvas class="chart-canvas" data-chart="multi-line" data-labels='<?php echo h(json_encode($data['chart_data']['movement_labels'])); ?>' data-series='<?php echo h(json_encode([["label" => "IN", "data" => $data['chart_data']['movement_in'], "color" => "#1a73e8"], ["label" => "OUT", "data" => $data['chart_data']['movement_out'], "color" => "#d93025"], ["label" => "RETURN", "data" => $data['chart_data']['movement_return'], "color" => "#f29900"]])); ?>'></canvas></article>
            <article class="card chart-card"><div class="section-header"><div><h2 class="section-title">Expiry mix</h2><p class="section-subtitle">Healthy, expiring soon, critical, and expired batch counts.</p></div></div><canvas class="chart-canvas" data-chart="bar" data-labels='<?php echo h(json_encode($data['chart_data']['expiry_labels'])); ?>' data-values='<?php echo h(json_encode($data['chart_data']['expiry_values'])); ?>' data-color="#f29900"></canvas></article>
            <article class="card chart-card"><div class="section-header"><div><h2 class="section-title">Stock by source</h2><p class="section-subtitle">Available quantity split by regular versus outsourced stock.</p></div></div><canvas class="chart-canvas" data-chart="bar" data-labels='<?php echo h(json_encode($data['chart_data']['source_labels'])); ?>' data-values='<?php echo h(json_encode($data['chart_data']['source_values'])); ?>' data-color="#0f5fd6"></canvas></article>
            <article class="card chart-card"><div class="section-header"><div><h2 class="section-title">Top outgoing products</h2><p class="section-subtitle">Highest outgoing products in the selected window.</p></div></div><canvas class="chart-canvas" data-chart="bar" data-labels='<?php echo h(json_encode($data['chart_data']['top_moved_labels'])); ?>' data-values='<?php echo h(json_encode($data['chart_data']['top_moved_values'])); ?>' data-color="#174ea6"></canvas></article>
        </section>

        <div class="content-grid">
            <div class="content-main">
                <div class="card section-card">
                    <div class="section-header"><div><h2 class="section-title">Top outgoing products</h2><p class="section-subtitle">Products with the highest outgoing quantities.</p></div></div>
                    <div class="table-wrap"><table class="data-table"><thead><tr><th>Product</th><th class="ta-right">Qty Out</th></tr></thead><tbody><?php if ($data['top_outgoing']): foreach ($data['top_outgoing'] as $name => $qty): ?><tr><td><?php echo h((string) $name); ?></td><td class="ta-right"><?php echo number_format((int) $qty); ?></td></tr><?php endforeach; else: ?><tr><td colspan="2" class="empty-state">No outgoing movement in the selected window.</td></tr><?php endif; ?></tbody></table></div>
                </div>
                <div class="card section-card">
                    <div class="section-header"><div><h2 class="section-title">Top returned products</h2><p class="section-subtitle">Products with the highest returned quantities.</p></div></div>
                    <div class="table-wrap"><table class="data-table"><thead><tr><th>Product</th><th class="ta-right">Qty Returned</th></tr></thead><tbody><?php if ($data['top_returned']): foreach ($data['top_returned'] as $name => $qty): ?><tr><td><?php echo h((string) $name); ?></td><td class="ta-right"><?php echo number_format((int) $qty); ?></td></tr><?php endforeach; else: ?><tr><td colspan="2" class="empty-state">No return movement in the selected window.</td></tr><?php endif; ?></tbody></table></div>
                </div>
            </div>
            <aside class="content-side">
                <div class="card side-panel"><div class="side-panel-head"><div><h2 class="section-title">Supplier summary</h2><p class="section-subtitle">Current stock grouped by manufacturer or distributor.</p></div></div><div class="insight-list"><?php if ($data['supplier_summary']): foreach ($data['supplier_summary'] as $name => $summary): ?><div class="insight-item"><div class="insight-copy"><strong><?php echo h((string) $name); ?></strong><span><?php echo number_format((int) $summary['rows']); ?> rows</span></div><div class="insight-value"><?php echo number_format((int) $summary['qty']); ?></div></div><?php endforeach; else: ?><div class="empty-state">No supplier summary data.</div><?php endif; ?></div></div>
                <div class="card side-panel"><div class="side-panel-head"><div><h2 class="section-title">Movement by user</h2><p class="section-subtitle">How transaction volume is distributed by user.</p></div></div><div class="insight-list"><?php if ($data['movement_by_user']): foreach ($data['movement_by_user'] as $name => $summary): ?><div class="insight-item insight-alert-item"><div class="insight-copy"><strong><?php echo h((string) $name); ?></strong><span>IN <?php echo number_format((int) $summary['in']); ?> | OUT <?php echo number_format((int) $summary['out']); ?> | RETURN <?php echo number_format((int) $summary['return']); ?></span></div></div><?php endforeach; else: ?><div class="empty-state">No user movement data.</div><?php endif; ?></div></div>
            </aside>
        </div>
    </section>
    <?php close_page_stack(); ?>
</div>
</body>
</html>
