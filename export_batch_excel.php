<?php
require_once 'includes/common.php';
require_login();
require_permission('exports.view');

$batchNo = trim((string) ($_GET['batch_no'] ?? $_POST['batch_no'] ?? ''));
if ($batchNo === '') {
    exit('Batch number missing.');
}

$bundle = fetch_batch_history_bundle($conn, $batchNo);
$batch = $bundle['batch'];
if (!$batch) {
    exit('Invalid batch number.');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="batch_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $batchNo) . '_' . date('Ymd_His') . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Batch Number', $batchNo]);
fputcsv($out, ['Source', $batch['source_type'] ?? '']);
fputcsv($out, ['Generic Name', $batch['generic_name'] ?? '']);
fputcsv($out, ['Brand Name', $batch['brand_name'] ?? '']);
fputcsv($out, ['Dosage & Strength', $batch['dosage_strength'] ?? '']);
fputcsv($out, ['Manufacturer', $batch['manufacturer'] ?? '']);
fputcsv($out, ['Registration No', $batch['registration_no'] ?? '']);
fputcsv($out, ['Mfg Date', $batch['mfg_date'] ?? '']);
fputcsv($out, ['Exp Date', $batch['exp_date'] ?? '']);
fputcsv($out, ['Available Qty', $batch['qty'] ?? 0]);
fputcsv($out, ['Record Status', $batch['record_status'] ?? '']);
fputcsv($out, []);
fputcsv($out, ['OUT RECORDS']);
fputcsv($out, ['Date', 'Customer', 'Doc Type', 'Doc No', 'Qty Out', 'Return Status', 'Record Status', 'Added By']);
foreach ($bundle['out_records'] as $row) {
    fputcsv($out, [$row['created_at'] ?? '', $row['customer_name'] ?? '', $row['document_type'] ?? '', $row['document_number'] ?? '', $row['qty_out'] ?? 0, $row['return_status'] ?? '', $row['record_status'] ?? '', $row['added_by'] ?? '']);
}
fputcsv($out, []);
fputcsv($out, ['RETURN RECORDS']);
fputcsv($out, ['Date', 'Qty Returned', 'Returned By', 'Customer', 'Doc Type', 'Doc No', 'Record Status']);
foreach ($bundle['return_records'] as $row) {
    fputcsv($out, [$row['created_at'] ?? '', $row['qty_returned'] ?? 0, $row['returned_by'] ?? '', $row['customer_name'] ?? '', $row['document_type'] ?? '', $row['document_number'] ?? '', $row['record_status'] ?? '']);
}
fclose($out);
exit;
