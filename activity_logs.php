<?php
require_once 'includes/common.php';
require_login();

$filters = [
    'search' => trim($_GET['search'] ?? ''),
    'type' => trim($_GET['type'] ?? 'ALL'),
    'date_from' => trim($_GET['date_from'] ?? ''),
    'date_to' => trim($_GET['date_to'] ?? ''),
];
$rows = fetch_activity_log($conn, $filters);
function activity_badge_class2($type) {
    $type = strtoupper((string)$type);
    if ($type === 'OUT') return 'badge-red';
    if ($type === 'RETURN') return 'badge-orange';
    return 'badge-green';
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Activity Log</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/app.css"></head><body>
<div class="app-shell app-shell-form"><section class="card form-page-card"><div class="form-page-top"><div><div class="eyebrow">Operational Traceability</div><h1 class="hero-page-title">Activity Log</h1><p class="hero-page-subtitle">Review the complete inventory movement stream across IN, OUT, and RETURN actions.</p></div><a class="btn btn-soft" href="dashboard.php">Back to Dashboard</a></div>
<form method="get" class="filter-toolbar filter-toolbar-wide">
    <div class="search-control"><span class="search-icon">⌕</span><input class="search-input" type="text" name="search" value="<?php echo h($filters['search']); ?>" placeholder="Search actor, medicine, batch, reference..."></div>
    <select class="search-input search-select" name="type"><option value="ALL" <?php echo strtoupper($filters['type']) === 'ALL' ? 'selected' : ''; ?>>All types</option><option value="IN" <?php echo strtoupper($filters['type']) === 'IN' ? 'selected' : ''; ?>>IN</option><option value="OUT" <?php echo strtoupper($filters['type']) === 'OUT' ? 'selected' : ''; ?>>OUT</option><option value="RETURN" <?php echo strtoupper($filters['type']) === 'RETURN' ? 'selected' : ''; ?>>RETURN</option></select>
    <input class="search-input search-select" type="date" name="date_from" value="<?php echo h($filters['date_from']); ?>">
    <input class="search-input search-select" type="date" name="date_to" value="<?php echo h($filters['date_to']); ?>">
    <button class="btn btn-primary" type="submit">Apply</button>
</form>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Type</th><th>Activity Date</th><th>Medicine</th><th>Batch</th><th>Reference</th><th class="ta-right">Qty</th><th>Actor</th><th>Source</th></tr></thead><tbody>
<?php if ($rows): foreach ($rows as $row): ?>
<tr><td><span class="badge <?php echo activity_badge_class2($row['movement_type']); ?>"><?php echo h($row['movement_type']); ?></span></td><td><?php echo h(date('M d, Y h:i A', strtotime($row['activity_at']))); ?></td><td><strong><?php echo h($row['generic_name']); ?></strong><br><span class="muted"><?php echo h($row['brand_name'] . ' • ' . $row['dosage_strength']); ?></span></td><td><?php echo h($row['batch_no']); ?></td><td><?php echo h($row['reference']); ?></td><td class="ta-right qty-mini"><?php echo number_format((int)$row['qty']); ?></td><td><?php echo h($row['actor']); ?></td><td><?php echo h($row['source_type']); ?></td></tr>
<?php endforeach; else: ?>
<tr><td colspan="8" class="empty-state">No activity rows matched your filters.</td></tr>
<?php endif; ?>
</tbody></table></div>
</section></div></body></html>
