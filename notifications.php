<?php
require_once 'includes/common.php';
require_login();
require_permission('notifications.view');

if (request_is_post()) {
    verify_csrf_or_fail();
    $action = trim((string) ($_POST['action'] ?? ''));
    if ($action === 'mark_all_read') {
        mark_all_notifications_read($conn);
        set_flash('success', 'All notifications marked as read.');
    } elseif ($action === 'toggle_read') {
        $notificationId = (int) ($_POST['notification_id'] ?? 0);
        $isRead = (int) ($_POST['is_read'] ?? 0) === 1;
        mark_notification_read($conn, $notificationId, $isRead);
        set_flash('success', $isRead ? 'Notification marked as read.' : 'Notification marked as unread.');
    }
    redirect('notifications.php');
}

$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'read_filter' => trim((string) ($_GET['read_filter'] ?? 'all')),
    'type_filter' => trim((string) ($_GET['type_filter'] ?? 'all')),
];
$rows = fetch_notifications($conn, $filters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="app-shell app-shell-form">
    <?php render_app_nav($conn, 'Notifications', 'Low stock, expiry, reversal, and correction events with read/unread tracking.', 'In-App Notification Center'); ?>
    <section class="card form-page-card">
        <div class="inline-actions" style="margin-bottom:14px;">
            <form method="post">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="mark_all_read">
                <button class="btn btn-primary" type="submit">Mark All Read</button>
            </form>
        </div>
        <form method="get" class="filter-toolbar filter-toolbar-wide">
            <div class="search-control"><span class="search-icon">S</span><input class="search-input" type="text" name="search" value="<?php echo h($filters['search']); ?>" placeholder="Search notifications..."></div>
            <select class="search-input search-select" name="read_filter"><option value="all" <?php echo selected_attr($filters['read_filter'], 'all'); ?>>All</option><option value="unread" <?php echo selected_attr($filters['read_filter'], 'unread'); ?>>Unread</option><option value="read" <?php echo selected_attr($filters['read_filter'], 'read'); ?>>Read</option></select>
            <select class="search-input search-select" name="type_filter"><option value="all" <?php echo selected_attr($filters['type_filter'], 'all'); ?>>All types</option><option value="alert" <?php echo selected_attr($filters['type_filter'], 'alert'); ?>>Alert</option><option value="correction" <?php echo selected_attr($filters['type_filter'], 'correction'); ?>>Correction</option><option value="reversal" <?php echo selected_attr($filters['type_filter'], 'reversal'); ?>>Reversal</option><option value="product" <?php echo selected_attr($filters['type_filter'], 'product'); ?>>Product</option></select>
            <button class="btn btn-primary" type="submit">Apply</button>
            <a class="btn btn-outline" href="notifications.php">Reset</a>
        </form>
        <div class="notes-list">
            <?php if ($rows): foreach ($rows as $row): ?>
                <div class="notification-item <?php echo (int) ($row['is_read'] ?? 0) === 0 ? 'notification-unread' : ''; ?>">
                    <div class="notification-top">
                        <div><span class="badge <?php echo ($row['severity'] ?? 'info') === 'danger' ? 'badge-red' : (($row['severity'] ?? 'info') === 'warning' ? 'badge-orange' : 'badge-blue'); ?>"><?php echo h((string) $row['notification_type']); ?></span></div>
                        <div class="muted"><?php echo h(format_datetime($row['created_at'] ?? '')); ?></div>
                    </div>
                    <strong><?php echo h((string) $row['title']); ?></strong>
                    <p class="notification-message"><?php echo h((string) $row['message']); ?></p>
                    <div class="inline-actions">
                        <?php if (!empty($row['entity_type']) && !empty($row['entity_id'])): ?>
                            <?php if (str_starts_with((string) $row['entity_type'], 'batch_')): ?>
                                <a class="btn btn-outline btn-mini" href="batch_history.php?batch=<?php echo urlencode((string) db_fetch_one($conn, 'SELECT batch_no FROM ' . ($row['entity_type'] === 'batch_outsourced' ? 'inventory_outsourced' : 'inventory') . ' WHERE id = ? LIMIT 1', 'i', [(int) $row['entity_id']])['batch_no']); ?>">Open Batch</a>
                            <?php elseif (($row['entity_type'] ?? '') === 'product'): ?>
                                <a class="btn btn-outline btn-mini" href="product_form.php?id=<?php echo (int) $row['entity_id']; ?>">Open Product</a>
                            <?php elseif (in_array((string) $row['entity_type'], ['out_record', 'return_record', 'in_log'], true)): ?>
                                <a class="btn btn-outline btn-mini" href="transaction_detail.php?type=<?php echo urlencode((string) str_replace('_record', '', $row['entity_type'])); ?>&id=<?php echo (int) $row['entity_id']; ?>">Open Record</a>
                            <?php endif; ?>
                        <?php endif; ?>
                        <form method="post">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="toggle_read">
                            <input type="hidden" name="notification_id" value="<?php echo (int) $row['id']; ?>">
                            <input type="hidden" name="is_read" value="<?php echo (int) ($row['is_read'] ?? 0) === 1 ? 0 : 1; ?>">
                            <button class="btn btn-soft btn-mini" type="submit"><?php echo (int) ($row['is_read'] ?? 0) === 1 ? 'Mark Unread' : 'Mark Read'; ?></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div class="empty-state">No notifications matched your filters.</div>
            <?php endif; ?>
        </div>
    </section>
    <?php close_page_stack(); ?>
</div>
</body>
</html>
