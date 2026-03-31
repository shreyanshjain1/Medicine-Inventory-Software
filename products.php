<?php
require_once 'includes/common.php';
require_login();
require_permission('products.view');

$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? 'active')),
];
$rows = fetch_products($conn, $filters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Master</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="app-shell app-shell-form">
    <?php
    $actions = [];
    if (user_can('products.manage')) {
        $actions[] = '<a class="btn btn-primary" href="product_form.php">Add Product</a>';
    }
    render_app_nav($conn, 'Product Master', 'Manage reusable medicine profiles, prevent duplicates, control thresholds, and attach barcode values for quick operations.', 'Product Governance', $actions);
    ?>
    <section class="card form-page-card">
        <form method="get" class="filter-toolbar filter-toolbar-wide">
            <div class="search-control"><span class="search-icon">S</span><input class="search-input" type="text" name="search" value="<?php echo h($filters['search']); ?>" placeholder="Search generic, brand, manufacturer, barcode..."></div>
            <select class="search-input search-select" name="status"><option value="active" <?php echo selected_attr($filters['status'], 'active'); ?>>Active</option><option value="archived" <?php echo selected_attr($filters['status'], 'archived'); ?>>Archived</option><option value="all" <?php echo selected_attr($filters['status'], 'all'); ?>>All</option></select>
            <button class="btn btn-primary" type="submit">Apply</button>
            <a class="btn btn-outline" href="products.php">Reset</a>
        </form>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Product</th><th>Manufacturer</th><th>Registration</th><th>Threshold</th><th>Barcode</th><th>Status</th><th>Active Batches</th><th>Action</th></tr></thead>
                <tbody>
                <?php if ($rows): foreach ($rows as $row): ?>
                    <tr>
                        <td><strong><?php echo h((string) $row['generic_name']); ?></strong><br><span class="muted"><?php echo h((string) ($row['brand_name'] . ' | ' . $row['dosage_strength'])); ?></span></td>
                        <td><?php echo h((string) $row['manufacturer']); ?></td>
                        <td><?php echo h((string) $row['registration_no']); ?></td>
                        <td><?php echo number_format((int) $row['default_low_stock_threshold']); ?></td>
                        <td><?php echo h((string) ($row['barcode_value'] ?: 'N/A')); ?></td>
                        <td><span class="badge <?php echo ($row['product_status'] ?? 'active') === 'archived' ? 'badge-orange' : 'badge-green'; ?>"><?php echo h((string) $row['product_status']); ?></span></td>
                        <td><?php echo number_format((int) $row['regular_batches'] + (int) $row['outsourced_batches']); ?></td>
                        <td><a class="btn btn-mini btn-soft" href="product_form.php?id=<?php echo (int) $row['id']; ?>">Open</a></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="8" class="empty-state">No product master rows matched your filters.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php close_page_stack(); ?>
</div>
</body>
</html>
