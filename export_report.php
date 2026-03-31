<?php
require_once 'includes/common.php';
require_login();
require_permission('exports.view');

$filters = [
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
    'source' => trim((string) ($_GET['source'] ?? 'all')),
];
$data = fetch_report_dataset($conn, $filters);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="analytics_report_' . date('Ymd_His') . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Report Window', $data['date_from'] . ' to ' . $data['date_to']]);
fputcsv($out, ['Source Filter', $filters['source'] ?: 'all']);
fputcsv($out, []);
fputcsv($out, ['Metric', 'Value']);
fputcsv($out, ['Current Stock Rows', count($data['inventory_rows'])]);
fputcsv($out, ['Low Stock Rows', count($data['low_stock_rows'])]);
fputcsv($out, ['Expiring Rows', count($data['expiring_rows'])]);
fputcsv($out, ['Expired Rows', count($data['expired_rows'])]);
fputcsv($out, ['Movement IN Qty', $data['movement_summary']['in_qty']]);
fputcsv($out, ['Movement OUT Qty', $data['movement_summary']['out_qty']]);
fputcsv($out, ['Movement RETURN Qty', $data['movement_summary']['return_qty']]);
fputcsv($out, []);
fputcsv($out, ['Top Outgoing Products']);
fputcsv($out, ['Product', 'Qty Out']);
foreach ($data['top_outgoing'] as $name => $qty) {
    fputcsv($out, [$name, $qty]);
}
fputcsv($out, []);
fputcsv($out, ['Top Returned Products']);
fputcsv($out, ['Product', 'Qty Returned']);
foreach ($data['top_returned'] as $name => $qty) {
    fputcsv($out, [$name, $qty]);
}
fputcsv($out, []);
fputcsv($out, ['Supplier Summary']);
fputcsv($out, ['Supplier', 'Rows', 'Qty']);
foreach ($data['supplier_summary'] as $name => $summary) {
    fputcsv($out, [$name, $summary['rows'], $summary['qty']]);
}
fputcsv($out, []);
fputcsv($out, ['Movement By User']);
fputcsv($out, ['User', 'IN', 'OUT', 'RETURN']);
foreach ($data['movement_by_user'] as $name => $summary) {
    fputcsv($out, [$name, $summary['in'], $summary['out'], $summary['return']]);
}
fclose($out);
exit;
