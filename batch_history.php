<?php
require_once 'includes/common.php';
require_login();

$batchNo = trim($_GET['batch'] ?? '');
$item = null;
$itemSource = 'inventory';
$outRecords = [];
$returnRecords = [];
$inLog = null;
$summary = ['total_out' => 0, 'total_returned' => 0, 'remaining' => 0];
$error = '';

if ($batchNo !== '') {
    $stmt = $conn->prepare('SELECT *, NULL AS distributor_name FROM inventory WHERE batch_no = ? LIMIT 1');
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
        $itemSource = 'inventory_outsourced';
    }

    if ($item) {
        $stmt = $conn->prepare('SELECT added_by, added_at FROM in_log WHERE batch_no = ? ORDER BY added_at ASC LIMIT 1');
        $stmt->bind_param('s', $batchNo);
        $stmt->execute();
        $inLog = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($itemSource === 'inventory') {
            $stmt = $conn->prepare('SELECT * FROM out_records WHERE inventory_id = ? ORDER BY created_at DESC');
            $stmt->bind_param('i', $item['id']);
            $stmt->execute();
            $outRecords = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $stmt = $conn->prepare('SELECT r.*, o.document_number, o.document_type, o.customer_name FROM return_binded_records r JOIN out_records o ON r.out_record_id = o.id WHERE o.inventory_id = ? ORDER BY r.created_at DESC');
            $stmt->bind_param('i', $item['id']);
            $stmt->execute();
            $returnRecords = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }

        $summary['total_out'] = (int) array_sum(array_column($outRecords, 'qty_out'));
        $summary['total_returned'] = (int) array_sum(array_column($returnRecords, 'qty_returned'));
        $summary['remaining'] = $summary['total_out'] - $summary['total_returned'];
    } else {
        $error = 'Batch not found. Please check the batch number and try again.';
    }
}

function history_badge_class($value)
{
    $value = strtoupper((string) $value);
    if ($value === 'RETURNED' || $value === 'PARTIAL RETURN' || $value === 'PARTIALLY RETURNED' || $value === 'RETURN PARTIAL' || $value === 'RETURN FULL') {
        return 'badge-orange';
    }
    if ($value === 'NO RETURN YET' || $value === '') {
        return 'badge-blue';
    }
    return 'badge-green';
}

$currentAvailable = (int) ($item['qty'] ?? 0);
$totalOut = (int) $summary['total_out'];
$totalReturned = (int) $summary['total_returned'];
$netOutstanding = (int) $summary['remaining'];
$batchTitle = $item ? trim(($item['generic_name'] ?? '') . ' • ' . ($item['brand_name'] ?? '') . ' • ' . ($item['dosage_strength'] ?? '')) : '';
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Batch History</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/app.css"></head><body>
<div class="app-shell"><div class="page-stack">
<section class="card batch-hero"><div class="batch-hero-top"><div><div class="eyebrow">Traceability & Movement History</div><h1 class="hero-page-title">Batch History</h1><p class="hero-page-subtitle">Search a batch number and review its full IN, OUT, and RETURN history in one readable screen.</p></div><a class="btn btn-soft" href="dashboard.php">Back to Dashboard</a></div>
<form method="get" class="batch-search-form"><div class="field batch-search-field"><label for="batch">Batch Number</label><input id="batch" type="text" name="batch" value="<?php echo h($batchNo); ?>" placeholder="Enter batch number" required></div><button class="btn btn-primary" type="submit">Search Batch</button></form><?php if ($error): ?><div class="flash flash-error" style="margin-top:14px;"><?php echo h($error); ?></div><?php endif; ?></section>
<?php if ($item): ?>
<section class="batch-summary-grid"><div class="card batch-kpi batch-kpi-primary"><div class="batch-kpi-label">Current Available</div><div class="batch-kpi-value"><?php echo number_format($currentAvailable); ?></div><div class="batch-kpi-sub">Live quantity from the source inventory table</div></div><div class="card batch-kpi"><div class="batch-kpi-label">Total OUT</div><div class="batch-kpi-value"><?php echo number_format($totalOut); ?></div><div class="batch-kpi-sub">Total quantity released from this batch</div></div><div class="card batch-kpi"><div class="batch-kpi-label">Total Returned</div><div class="batch-kpi-value"><?php echo number_format($totalReturned); ?></div><div class="batch-kpi-sub">Total linked return quantity received back</div></div><div class="card batch-kpi"><div class="batch-kpi-label">Net Outstanding</div><div class="batch-kpi-value"><?php echo number_format($netOutstanding); ?></div><div class="batch-kpi-sub">OUT minus RETURN history</div></div></section>
<section class="card batch-detail-card"><div class="batch-detail-top"><div><div class="batch-title-row"><h2 class="section-title">Batch <?php echo h($batchNo); ?></h2><span class="badge badge-blue"><?php echo h($itemSource === 'inventory_outsourced' ? 'Outsourced Source' : 'Regular Source'); ?></span></div><p class="section-subtitle"><?php echo h($batchTitle); ?></p></div><div class="inline-actions"><a class="btn btn-outline" href="export_batch_excel.php?batch_no=<?php echo urlencode($batchNo); ?>">Export Excel</a><a class="btn btn-outline" target="_blank" href="export_batch_pdf.php?batch_no=<?php echo urlencode($batchNo); ?>">Open PDF View</a></div></div>
<div class="batch-meta-grid"><div class="batch-meta-box"><span class="batch-meta-label">Manufacturer</span><strong><?php echo h($item['manufacturer']); ?></strong></div><div class="batch-meta-box"><span class="batch-meta-label">Registration No</span><strong><?php echo h($item['registration_no']); ?></strong></div><div class="batch-meta-box"><span class="batch-meta-label">Manufacturing Date</span><strong><?php echo h($item['mfg_date']); ?></strong></div><div class="batch-meta-box"><span class="batch-meta-label">Expiry Date</span><strong><?php echo h($item['exp_date']); ?></strong></div><div class="batch-meta-box"><span class="batch-meta-label">Logged IN By</span><strong><?php echo h($inLog['added_by'] ?? 'N/A'); ?></strong></div><div class="batch-meta-box"><span class="batch-meta-label">Logged IN At</span><strong><?php echo h(isset($inLog['added_at']) ? date('M d, Y h:i A', strtotime($inLog['added_at'])) : 'N/A'); ?></strong></div><?php if (!empty($item['distributor_name'])): ?><div class="batch-meta-box"><span class="batch-meta-label">Distributor</span><strong><?php echo h($item['distributor_name']); ?></strong></div><?php endif; ?></div></section>
<section class="batch-history-grid"><div class="card history-card"><div class="history-card-top"><div><h2 class="section-title">OUT Records</h2><p class="section-subtitle">All released movement rows for this batch.</p></div><div class="history-metric-chip history-out-chip"><span>Total OUT Qty</span><strong><?php echo number_format($totalOut); ?></strong></div></div><div class="table-wrap table-wrap-readable"><table class="data-table data-table-readable"><thead><tr><th>#</th><th>Date</th><th>Customer</th><th>Doc Type</th><th>Doc No</th><th class="ta-right qty-col-header">Qty Out</th><th>Return Status</th><th>Added By</th></tr></thead><tbody><?php if ($outRecords): foreach ($outRecords as $index => $out): $returnStatus = trim((string)($out['return_status'] ?? '')); if ($returnStatus === '') $returnStatus = 'No return yet'; ?><tr><td class="row-index"><?php echo $index + 1; ?></td><td><?php echo h(date('M d, Y h:i A', strtotime($out['created_at']))); ?></td><td><?php echo h($out['customer_name']); ?></td><td><?php echo h($out['document_type']); ?></td><td><?php echo h($out['document_number']); ?></td><td class="ta-right qty-cell qty-out"><?php echo number_format((int)$out['qty_out']); ?></td><td><span class="badge <?php echo history_badge_class($returnStatus); ?>"><?php echo h($returnStatus); ?></span></td><td><?php echo h($out['added_by']); ?></td></tr><?php endforeach; else: ?><tr><td colspan="8" class="empty-state">No OUT history found for this batch.</td></tr><?php endif; ?></tbody></table></div></div>
<div class="card history-card"><div class="history-card-top"><div><h2 class="section-title">RETURN Records</h2><p class="section-subtitle">All linked returns mapped back to this batch.</p></div><div class="history-metric-chip history-return-chip"><span>Total Returned Qty</span><strong><?php echo number_format($totalReturned); ?></strong></div></div><div class="table-wrap table-wrap-readable"><table class="data-table data-table-readable"><thead><tr><th>#</th><th>Date</th><th>Customer</th><th>Reference</th><th class="ta-right qty-col-header">Qty Returned</th><th>Returned By</th></tr></thead><tbody><?php if ($returnRecords): foreach ($returnRecords as $index => $ret): ?><tr><td class="row-index"><?php echo $index + 1; ?></td><td><?php echo h(date('M d, Y h:i A', strtotime($ret['created_at']))); ?></td><td><?php echo h($ret['customer_name']); ?></td><td><?php echo h($ret['document_type'] . ' - ' . $ret['document_number']); ?></td><td class="ta-right qty-cell qty-return"><?php echo number_format((int)$ret['qty_returned']); ?></td><td><?php echo h($ret['returned_by']); ?></td></tr><?php endforeach; else: ?><tr><td colspan="6" class="empty-state">No RETURN history found for this batch.</td></tr><?php endif; ?></tbody></table></div></div></section>
<?php endif; ?></div></div></body></html>
