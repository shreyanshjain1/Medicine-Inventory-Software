<?php
require_once 'includes/common.php';
require_login();
require_permission('activity.view');

$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'type' => trim((string) ($_GET['type'] ?? 'ALL')),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
];
$rows = fetch_activity_log($conn, $filters);

function activity_badge_class2(string $type): string
{
    return match (strtoupper($type)) {
        'OUT' => 'badge-red',
        'RETURN' => 'badge-orange',
        'AUDIT' => 'badge-blue',
        default => 'badge-green',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="app-shell app-shell-form">
    <?php render_app_nav($conn, 'Activity Log', 'Review movement and audit events across IN, OUT, RETURN, and system-level corrections or voids.', 'Operational Traceability'); ?>
    <section class="card form-page-card">
        <form method="get" class="filter-toolbar filter-toolbar-wide">
            <div class="search-control"><span class="search-icon">S</span><input class="search-input" type="text" name="search" value="<?php echo h($filters['search']); ?>" placeholder="Search actor, medicine, batch, reference..."></div>
            <select class="search-input search-select" name="type"><option value="ALL" <?php echo selected_attr(strtoupper($filters['type']), 'ALL'); ?>>All types</option><option value="IN" <?php echo selected_attr(strtoupper($filters['type']), 'IN'); ?>>IN</option><option value="OUT" <?php echo selected_attr(strtoupper($filters['type']), 'OUT'); ?>>OUT</option><option value="RETURN" <?php echo selected_attr(strtoupper($filters['type']), 'RETURN'); ?>>RETURN</option><option value="AUDIT" <?php echo selected_attr(strtoupper($filters['type']), 'AUDIT'); ?>>AUDIT</option></select>
            <input class="search-input search-select" type="date" name="date_from" value="<?php echo h($filters['date_from']); ?>">
            <input class="search-input search-select" type="date" name="date_to" value="<?php echo h($filters['date_to']); ?>">
            <button class="btn btn-primary" type="submit">Apply</button>
            <a class="btn btn-outline" href="activity_logs.php">Reset</a>
        </form>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Type</th><th>Activity Date</th><th>Medicine / Event</th><th>Batch</th><th>Reference</th><th class="ta-right">Qty</th><th>Actor</th><th>Source</th><th>Status</th></tr></thead><tbody>
        <?php if ($rows): foreach ($rows as $row): ?>
            <tr><td><span class="badge <?php echo activity_badge_class2((string) ($row['movement_type'] ?? 'IN')); ?>"><?php echo h((string) $row['movement_type']); ?></span></td><td><?php echo h(format_datetime($row['activity_at'] ?? '')); ?></td><td><strong><?php echo h((string) $row['generic_name']); ?></strong><br><span class="muted"><?php echo h(trim((string) (($row['brand_name'] ?? '') . ' ' . ($row['dosage_strength'] ?? '')))); ?></span></td><td><?php echo h((string) ($row['batch_no'] ?? '')); ?></td><td><?php echo h((string) ($row['reference'] ?? '')); ?></td><td class="ta-right qty-mini"><?php echo number_format((int) ($row['qty'] ?? 0)); ?></td><td><?php echo h((string) ($row['actor'] ?? '')); ?></td><td><?php echo h((string) ($row['source_type'] ?? '')); ?></td><td><?php echo h((string) ($row['record_status'] ?? '')); ?></td></tr>
        <?php endforeach; else: ?>
            <tr><td colspan="9" class="empty-state">No activity rows matched your filters.</td></tr>
        <?php endif; ?>
        </tbody></table></div>
    </section>
    <?php close_page_stack(); ?>
</div>
</body>
</html>
