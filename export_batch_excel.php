<?php
require_once 'includes/common.php';
require_login();

$batchNo = trim($_GET['batch_no'] ?? $_POST['batch_no'] ?? '');
if ($batchNo === '') {
    die('Batch number missing.');
}

$stmt = $conn->prepare('SELECT * FROM inventory WHERE batch_no = ? LIMIT 1');
$stmt->bind_param('s', $batchNo);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    $stmt = $conn->prepare('SELECT * FROM inventory_outsourced WHERE batch_no = ? LIMIT 1');
    $stmt->bind_param('s', $batchNo);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$item) {
    die('Invalid batch number.');
}

$outRecords = [];
$returnRecords = [];
if (isset($item['id'])) {
    $stmt = $conn->prepare('SELECT created_at, customer_name, document_type, document_number, qty_out, return_status, added_by FROM out_records WHERE inventory_id = ? ORDER BY created_at DESC');
    $stmt->bind_param('i', $item['id']);
    $stmt->execute();
    $outRecords = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare('SELECT r.created_at, r.qty_returned, r.returned_by, o.customer_name, o.document_type, o.document_number FROM return_binded_records r JOIN out_records o ON r.out_record_id = o.id WHERE o.inventory_id = ? ORDER BY r.created_at DESC');
    $stmt->bind_param('i', $item['id']);
    $stmt->execute();
    $returnRecords = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="batch_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $batchNo) . '_' . date('Ymd_His') . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Batch Number', $batchNo]);
fputcsv($out, ['Generic Name', $item['generic_name'] ?? '']);
fputcsv($out, ['Brand Name', $item['brand_name'] ?? '']);
fputcsv($out, ['Dosage & Strength', $item['dosage_strength'] ?? '']);
fputcsv($out, ['Manufacturer', $item['manufacturer'] ?? '']);
fputcsv($out, ['Registration No', $item['registration_no'] ?? '']);
fputcsv($out, ['Mfg Date', $item['mfg_date'] ?? '']);
fputcsv($out, ['Exp Date', $item['exp_date'] ?? '']);
fputcsv($out, []);
fputcsv($out, ['OUT RECORDS']);
fputcsv($out, ['Date', 'Customer', 'Doc Type', 'Doc No', 'Qty Out', 'Return Status', 'Added By']);
foreach ($outRecords as $row) { fputcsv($out, array_values($row)); }
fputcsv($out, []);
fputcsv($out, ['RETURN RECORDS']);
fputcsv($out, ['Date', 'Qty Returned', 'Returned By', 'Customer', 'Doc Type', 'Doc No']);
foreach ($returnRecords as $row) { fputcsv($out, array_values($row)); }
fclose($out);
exit;
