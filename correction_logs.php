<?php
require_once 'includes/common.php';
require_login();
require_permission('corrections.view');

$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'record_type' => trim((string) ($_GET['record_type'] ?? 'all')),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
];
$rows = fetch_correction_logs($conn, $filters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correction Log</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="app-shell app-shell-form">
    <?php render_app_nav($conn, 'Correction Log', 'Append-only correction trail with actor, reason, and before/after values for product, batch, OUT, and RETURN edits.', 'Audit Trail'); ?>
    <section class="card form-page-card">
        <form method="get" class="filter-toolbar filter-toolbar-wide">
            <div class="search-control"><span class="search-icon">S</span><input class="search-input" type="text" name="search" value="<?php echo h($filters['search']); ?>" placeholder="Search reason, actor, record type..."></div>
            <select class="search-input search-select" name="record_type"><option value="all" <?php echo selected_attr($filters['record_type'], 'all'); ?>>All record types</option><option value="product" <?php echo selected_attr($filters['record_type'], 'product'); ?>>Product</option><option value="batch_regular" <?php echo selected_attr($filters['record_type'], 'batch_regular'); ?>>Regular Batch</option><option value="batch_outsourced" <?php echo selected_attr($filters['record_type'], 'batch_outsourced'); ?>>Outsourced Batch</option><option value="out_record" <?php echo selected_attr($filters['record_type'], 'out_record'); ?>>OUT</option><option value="return_record" <?php echo selected_attr($filters['record_type'], 'return_record'); ?>>RETURN</option></select>
            <input class="search-input search-select" type="date" name="date_from" value="<?php echo h($filters['date_from']); ?>">
            <input class="search-input search-select" type="date" name="date_to" value="<?php echo h($filters['date_to']); ?>">
            <button class="btn btn-primary" type="submit">Apply</button>
            <a class="btn btn-outline" href="correction_logs.php">Reset</a>
        </form>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Date</th><th>Actor</th><th>Role</th><th>Record Type</th><th>Record ID</th><th>Reason</th><th>Old Values</th><th>New Values</th></tr></thead>
                <tbody>
                <?php if ($rows): foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo h(format_datetime($row['created_at'] ?? '')); ?></td>
                        <td><?php echo h((string) $row['actor_name']); ?></td>
                        <td><?php echo h((string) $row['actor_role']); ?></td>
                        <td><?php echo h((string) $row['record_type']); ?></td>
                        <td><?php echo (int) $row['record_id']; ?></td>
                        <td><?php echo h((string) $row['reason']); ?></td>
                        <td><pre class="code-block"><?php echo h((string) $row['old_values']); ?></pre></td>
                        <td><pre class="code-block"><?php echo h((string) $row['new_values']); ?></pre></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="8" class="empty-state">No correction entries matched your filters.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php close_page_stack(); ?>
</div>
</body>
</html>
