<?php
require_once 'includes/common.php';
require_login();

$scope = $_GET['scope'] ?? 'all';
$format = $_GET['format'] ?? 'csv';
$regular = ($scope === 'all' || $scope === 'regular') ? fetch_inventory_flat($conn, false) : [];
$outsourced = ($scope === 'all' || $scope === 'outsourced') ? fetch_inventory_flat($conn, true) : [];
$rows = [];
foreach ($regular as $row) { $row['source_type'] = 'Regular'; $rows[] = $row; }
foreach ($outsourced as $row) { $row['source_type'] = 'Outsourced'; $rows[] = $row; }

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventory_export_' . $scope . '_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Source', 'Generic Name', 'Brand Name', 'Dosage & Strength', 'Batch No', 'Mfg Date', 'Exp Date', 'Manufacturer', 'Reg. No', 'Distributor', 'Available Qty', 'Qty In', 'Qty Out', 'Qty Returned', 'Expiry State']);
    foreach ($rows as $row) {
        fputcsv($out, [$row['source_type'], $row['generic_name'] ?? '', $row['brand_name'] ?? '', $row['dosage_strength'] ?? '', $row['batch_no'] ?? '', $row['mfg_date'] ?? '', $row['exp_date'] ?? '', $row['manufacturer'] ?? '', $row['registration_no'] ?? '', $row['distributor_name'] ?? '', $row['qty'] ?? 0, $row['qty_in'] ?? 0, $row['qty_out'] ?? 0, $row['qty_returned'] ?? 0, expiry_state($row['exp_date'] ?? '')]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Inventory PDF View</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/app.css"></head><body>
<div class="print-layout"><div class="print-header"><div><div class="eyebrow">Print-ready Inventory Report</div><h1>Inventory Export</h1><p>Scope: <?php echo h(ucfirst($scope)); ?> • Generated: <?php echo h(date('M d, Y h:i A')); ?></p></div><div class="print-actions"><button class="btn btn-primary" onclick="window.print()">Print / Save as PDF</button></div></div>
<div class="stats-grid stats-grid-compact"><article class="card stat-card"><div class="stat-label">Rows</div><div class="stat-value"><?php echo number_format(count($rows)); ?></div></article><article class="card stat-card"><div class="stat-label">Available Qty</div><div class="stat-value"><?php echo number_format(array_sum(array_map(static fn($r)=>(int)$r['qty'],$rows))); ?></div></article><article class="card stat-card"><div class="stat-label">Low Stock</div><div class="stat-value"><?php echo number_format(count(array_filter($rows, static fn($r)=>(int)$r['qty'] <= 10))); ?></div></article><article class="card stat-card"><div class="stat-label">Expiring Soon</div><div class="stat-value"><?php echo number_format(count(array_filter($rows, static fn($r)=>expiry_badge_class($r['exp_date'] ?? '') !== 'expiry-safe'))); ?></div></article></div>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Source</th><th>Medicine</th><th>Batch</th><th>Expiry</th><th>Supplier</th><th class="ta-right">Available</th><th class="ta-right">In</th><th class="ta-right">Out</th><th class="ta-right">Returned</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><?php echo h($row['source_type']); ?></td><td><strong><?php echo h($row['generic_name']); ?></strong><br><span class="muted"><?php echo h(($row['brand_name'] ?? '') . ' • ' . ($row['dosage_strength'] ?? '')); ?></span></td><td><?php echo h($row['batch_no']); ?></td><td><?php echo h($row['exp_date']); ?></td><td><?php echo h($row['source_type'] === 'Outsourced' ? ($row['distributor_name'] ?? '') : ($row['manufacturer'] ?? '')); ?></td><td class="ta-right qty-mini"><?php echo number_format((int)$row['qty']); ?></td><td class="ta-right qty-mini"><?php echo number_format((int)$row['qty_in']); ?></td><td class="ta-right qty-mini"><?php echo number_format((int)$row['qty_out']); ?></td><td class="ta-right qty-mini"><?php echo number_format((int)$row['qty_returned']); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<script>window.onload=()=>setTimeout(()=>window.print(),300);</script></body></html>
