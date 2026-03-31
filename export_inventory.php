<?php
require_once 'includes/common.php';
require_login();
require_permission('exports.view');

$scope = trim((string) ($_GET['scope'] ?? 'all'));
$format = trim((string) ($_GET['format'] ?? 'csv'));
$filters = [
    'source' => $scope === 'regular' || $scope === 'outsourced' ? $scope : 'all',
];
$rows = flatten_inventory_with_source($conn, $filters);

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventory_export_' . preg_replace('/[^a-z0-9_-]/i', '_', $scope) . '_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Source', 'Generic Name', 'Brand Name', 'Dosage & Strength', 'Batch No', 'Mfg Date', 'Exp Date', 'Manufacturer', 'Registration No', 'Importer', 'Distributor', 'Available Qty', 'Qty In', 'Qty Out', 'Qty Returned', 'Low Stock Threshold', 'Record Status', 'Barcode']);
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['source_type'] ?? '',
            $row['generic_name'] ?? '',
            $row['brand_name'] ?? '',
            $row['dosage_strength'] ?? '',
            $row['batch_no'] ?? '',
            $row['mfg_date'] ?? '',
            $row['exp_date'] ?? '',
            $row['manufacturer'] ?? '',
            $row['registration_no'] ?? '',
            $row['importer_name'] ?? '',
            $row['distributor_name'] ?? '',
            $row['qty'] ?? 0,
            $row['qty_in'] ?? 0,
            $row['qty_out'] ?? 0,
            $row['qty_returned'] ?? 0,
            $row['low_stock_threshold_resolved'] ?? 0,
            $row['record_status'] ?? '',
            $row['barcode_value'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Print View</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="print-layout">
    <div class="print-header">
        <div><div class="eyebrow">Print-ready Inventory Report</div><h1>Inventory Export</h1><p>Scope: <?php echo h(ucfirst($scope)); ?> | Generated: <?php echo h(date('M d, Y h:i A')); ?></p></div>
        <div class="print-actions"><button class="btn btn-primary" onclick="window.print()">Print / Save as PDF</button></div>
    </div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Source</th><th>Medicine</th><th>Batch</th><th>Expiry</th><th>Supplier</th><th class="ta-right">Available</th><th class="ta-right">In</th><th class="ta-right">Out</th><th class="ta-right">Returned</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?>
        <tr><td><?php echo h((string) $row['source_type']); ?></td><td><strong><?php echo h((string) $row['generic_name']); ?></strong><br><span class="muted"><?php echo h((string) ($row['brand_name'] . ' | ' . $row['dosage_strength'])); ?></span></td><td><?php echo h((string) $row['batch_no']); ?></td><td><?php echo h((string) $row['exp_date']); ?></td><td><?php echo h($row['source_key'] === 'outsourced' ? (string) ($row['distributor_name'] ?? '') : (string) ($row['manufacturer'] ?? '')); ?></td><td class="ta-right qty-mini"><?php echo number_format((int) $row['qty']); ?></td><td class="ta-right qty-mini"><?php echo number_format((int) $row['qty_in']); ?></td><td class="ta-right qty-mini"><?php echo number_format((int) $row['qty_out']); ?></td><td class="ta-right qty-mini"><?php echo number_format((int) $row['qty_returned']); ?></td><td><?php echo h((string) $row['record_status']); ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
</body>
</html>
