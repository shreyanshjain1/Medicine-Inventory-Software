<?php
require_once 'includes/common.php';
require_login();

$summary = fetch_alert_summary($conn);
$rows = fetch_alert_rows($conn);
$search = trim($_GET['search'] ?? '');
$filter = trim($_GET['filter'] ?? 'all');
if ($search !== '' || $filter !== 'all') {
    $rows = array_values(array_filter($rows, static function ($row) use ($search, $filter) {
        if ($filter === 'low_stock' && !((int)$row['qty'] <= 10 && (int)$row['qty'] > 0)) return false;
        if ($filter === 'out_of_stock' && !((int)$row['qty'] <= 0)) return false;
        if ($filter === 'critical_expiry' && expiry_badge_class($row['exp_date'] ?? '') !== 'expiry-critical') return false;
        if ($filter === 'expiring_soon' && expiry_badge_class($row['exp_date'] ?? '') !== 'expiry-warning') return false;
        if ($search !== '') {
            $haystack = strtolower(implode(' ', [$row['generic_name'] ?? '', $row['brand_name'] ?? '', $row['dosage_strength'] ?? '', $row['batch_no'] ?? '', $row['manufacturer'] ?? '', $row['distributor_name'] ?? '', implode(' ', $row['issues'] ?? [])]));
            return str_contains($haystack, strtolower($search));
        }
        return true;
    }));
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Alerts Center</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/app.css"></head><body>
<div class="app-shell app-shell-form">
<section class="card form-page-card">
    <div class="form-page-top"><div><div class="eyebrow">Operational Alerts</div><h1 class="hero-page-title">Alerts Center</h1><p class="hero-page-subtitle">Track low stock, out-of-stock, and expiry risk items from a single view.</p></div><a class="btn btn-soft" href="dashboard.php">Back to Dashboard</a></div>
    <section class="stats-grid stats-grid-compact">
        <article class="card stat-card stat-warning"><div class="stat-label">Low Stock</div><div class="stat-value"><?php echo number_format($summary['low_stock']); ?></div></article>
        <article class="card stat-card stat-danger"><div class="stat-label">Out of Stock</div><div class="stat-value"><?php echo number_format($summary['out_of_stock']); ?></div></article>
        <article class="card stat-card stat-warning"><div class="stat-label">Expiring Soon</div><div class="stat-value"><?php echo number_format($summary['expiring_soon']); ?></div></article>
        <article class="card stat-card stat-danger"><div class="stat-label">Critical Expiry</div><div class="stat-value"><?php echo number_format($summary['critical_expiry']); ?></div></article>
    </section>
    <form method="get" class="filter-toolbar">
        <div class="search-control"><span class="search-icon">⌕</span><input class="search-input" type="text" name="search" value="<?php echo h($search); ?>" placeholder="Search alerts..."></div>
        <select class="search-input search-select" name="filter">
            <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All alerts</option>
            <option value="low_stock" <?php echo $filter === 'low_stock' ? 'selected' : ''; ?>>Low stock</option>
            <option value="out_of_stock" <?php echo $filter === 'out_of_stock' ? 'selected' : ''; ?>>Out of stock</option>
            <option value="expiring_soon" <?php echo $filter === 'expiring_soon' ? 'selected' : ''; ?>>Expiring soon</option>
            <option value="critical_expiry" <?php echo $filter === 'critical_expiry' ? 'selected' : ''; ?>>Critical expiry</option>
        </select>
        <button class="btn btn-primary" type="submit">Apply</button>
    </form>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Medicine</th><th>Batch</th><th>Source</th><th>Issue</th><th>Expiry</th><th class="ta-right">Available</th><th>Supplier</th><th>Action</th></tr></thead>
            <tbody>
            <?php if ($rows): foreach ($rows as $row): ?>
                <tr class="<?php echo expiry_badge_class($row['exp_date'] ?? ''); ?>">
                    <td><strong><?php echo h($row['generic_name']); ?></strong><br><span class="muted"><?php echo h($row['brand_name'] . ' • ' . $row['dosage_strength']); ?></span></td>
                    <td><?php echo h($row['batch_no']); ?></td>
                    <td><?php echo h($row['source_type']); ?></td>
                    <td><?php foreach ($row['issues'] as $issue): ?><span class="badge <?php echo str_contains(strtolower($issue), 'critical') || str_contains(strtolower($issue), 'out of stock') ? 'badge-red' : 'badge-orange'; ?>"><?php echo h($issue); ?></span> <?php endforeach; ?></td>
                    <td><?php echo h($row['exp_date']); ?></td>
                    <td class="ta-right qty-cell"><?php echo number_format((int)$row['qty']); ?></td>
                    <td><?php echo h($row['source_type'] === 'Outsourced' ? ($row['distributor_name'] ?? '') : ($row['manufacturer'] ?? '')); ?></td>
                    <td><a class="btn btn-mini btn-soft" href="batch_history.php?batch=<?php echo urlencode($row['batch_no']); ?>">View batch</a></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="8" class="empty-state">No alert rows matched your filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section></div></body></html>
