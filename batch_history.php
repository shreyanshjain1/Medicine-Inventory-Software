<?php
require_once 'includes/common.php';
require_login();
require_permission('history.view');

$batchNo = trim((string) ($_GET['batch'] ?? ''));
$error = '';

if (request_is_post()) {
    verify_csrf_or_fail();
    if (!user_can('reversals.manage')) {
        set_flash('error', 'You do not have permission to void IN batches.');
        redirect('batch_history.php?batch=' . urlencode($batchNo));
    }

    $action = trim((string) ($_POST['action'] ?? ''));
    if ($action === 'void_batch') {
        $sourceKey = trim((string) ($_POST['source_key'] ?? 'regular'));
        $batchId = (int) ($_POST['batch_id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $errors = [];
        if ($reason === '') {
            $errors[] = 'A reason is required to void an IN batch.';
        } elseif (!void_in_batch($conn, $sourceKey, $batchId, $reason, $errors)) {
            if (!$errors) {
                $errors[] = 'Unable to void the batch.';
            }
        }

        if ($errors) {
            set_flash('error', implode(' ', $errors));
        } else {
            set_flash('success', 'IN batch voided successfully.');
        }
        redirect('batch_history.php?batch=' . urlencode($batchNo));
    }
}

$bundle = $batchNo !== '' ? fetch_batch_history_bundle($conn, $batchNo) : ['batch' => null, 'in_log' => null, 'out_records' => [], 'return_records' => [], 'summary' => ['active_out' => 0, 'active_returned' => 0, 'voided_out' => 0, 'voided_returns' => 0]];
$batch = $bundle['batch'];
$inLog = $bundle['in_log'];
$outRecords = $bundle['out_records'];
$returnRecords = $bundle['return_records'];
$summary = $bundle['summary'];
$batchNotes = $batch ? fetch_notes($conn, $batch['target_type'], (int) $batch['id']) : [];

if ($batchNo !== '' && !$batch) {
    $error = 'Batch not found. Please check the batch number and try again.';
}

function history_badge_class(string $status): string
{
    $status = strtolower(trim($status));
    return match ($status) {
        'voided' => 'badge-red',
        'return partial', 'return full' => 'badge-orange',
        'delivered', 'active' => 'badge-green',
        default => 'badge-blue',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch History</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="app-shell">
    <?php render_app_nav($conn, 'Batch History', 'Search any batch number to inspect its IN source, OUT releases, RETURN records, notes, labels, and current status.', 'Traceability & Movement History'); ?>
    <section class="card batch-hero">
        <div class="batch-hero-top">
            <div>
                <h2 class="section-title">Batch lookup</h2>
                <p class="section-subtitle">Search a batch number and review its full movement record.</p>
            </div>
        </div>
        <form method="get" class="batch-search-form">
            <div class="field batch-search-field"><label for="batch">Batch Number</label><input id="batch" type="text" name="batch" value="<?php echo h($batchNo); ?>" placeholder="Enter batch number" required></div>
            <button class="btn btn-primary" type="submit">Search Batch</button>
        </form>
        <?php if ($error !== ''): ?><div class="flash flash-error" style="margin-top:14px;"><?php echo h($error); ?></div><?php endif; ?>
    </section>

    <?php if ($batch): ?>
        <section class="batch-summary-grid">
            <div class="card batch-kpi batch-kpi-primary"><div class="batch-kpi-label">Current Available</div><div class="batch-kpi-value"><?php echo number_format((int) $batch['qty']); ?></div><div class="batch-kpi-sub">Live active quantity for this batch</div></div>
            <div class="card batch-kpi"><div class="batch-kpi-label">Active OUT</div><div class="batch-kpi-value"><?php echo number_format((int) $summary['active_out']); ?></div><div class="batch-kpi-sub">Released quantity from active OUT records</div></div>
            <div class="card batch-kpi"><div class="batch-kpi-label">Active Returned</div><div class="batch-kpi-value"><?php echo number_format((int) $summary['active_returned']); ?></div><div class="batch-kpi-sub">Returned quantity on active RETURN rows</div></div>
            <div class="card batch-kpi"><div class="batch-kpi-label">Record Status</div><div class="batch-kpi-value"><?php echo h(ucfirst((string) ($batch['record_status'] ?? 'active'))); ?></div><div class="batch-kpi-sub"><?php echo h($batch['source_type']); ?> source</div></div>
        </section>

        <section class="card batch-detail-card">
            <div class="batch-detail-top">
                <div>
                    <div class="batch-title-row">
                        <h2 class="section-title">Batch <?php echo h($batch['batch_no']); ?></h2>
                        <span class="badge badge-blue"><?php echo h($batch['source_type']); ?></span>
                        <span class="badge <?php echo history_badge_class((string) ($batch['record_status'] ?? 'active')); ?>"><?php echo h(ucfirst((string) ($batch['record_status'] ?? 'active'))); ?></span>
                    </div>
                    <p class="section-subtitle"><?php echo h(product_display_name($batch)); ?></p>
                </div>
                <div class="inline-actions">
                    <a class="btn btn-outline" href="export_batch_excel.php?batch_no=<?php echo urlencode((string) $batch['batch_no']); ?>">Export CSV</a>
                    <?php if (user_can('barcode.view')): ?><a class="btn btn-outline" href="label_print.php?type=batch&source=<?php echo urlencode((string) $batch['source_key']); ?>&id=<?php echo (int) $batch['id']; ?>" target="_blank">Print Label</a><?php endif; ?>
                    <?php if (user_can('corrections.manage')): ?><a class="btn btn-soft" href="edit_record.php?type=batch&source=<?php echo urlencode((string) $batch['source_key']); ?>&id=<?php echo (int) $batch['id']; ?>">Correct Batch</a><?php endif; ?>
                </div>
            </div>
            <div class="batch-meta-grid">
                <div class="batch-meta-box"><span class="batch-meta-label">Manufacturer</span><strong><?php echo h((string) $batch['manufacturer']); ?></strong></div>
                <div class="batch-meta-box"><span class="batch-meta-label">Registration No</span><strong><?php echo h((string) $batch['registration_no']); ?></strong></div>
                <div class="batch-meta-box"><span class="batch-meta-label">Manufacturing Date</span><strong><?php echo h((string) $batch['mfg_date']); ?></strong></div>
                <div class="batch-meta-box"><span class="batch-meta-label">Expiry Date</span><strong><?php echo h((string) $batch['exp_date']); ?></strong></div>
                <div class="batch-meta-box"><span class="batch-meta-label">Logged IN By</span><strong><?php echo h((string) ($inLog['added_by'] ?? 'N/A')); ?></strong></div>
                <div class="batch-meta-box"><span class="batch-meta-label">Logged IN At</span><strong><?php echo h(format_datetime($inLog['added_at'] ?? '')); ?></strong></div>
                <?php if (!empty($batch['distributor_name'])): ?><div class="batch-meta-box"><span class="batch-meta-label">Distributor</span><strong><?php echo h((string) $batch['distributor_name']); ?></strong></div><?php endif; ?>
                <?php if (!empty($batch['importer_name'])): ?><div class="batch-meta-box"><span class="batch-meta-label">Importer</span><strong><?php echo h((string) $batch['importer_name']); ?></strong></div><?php endif; ?>
                <div class="batch-meta-box"><span class="batch-meta-label">Barcode</span><strong><?php echo h((string) ($batch['barcode_value'] ?: $batch['batch_no'])); ?></strong></div>
            </div>
            <?php if (user_can('reversals.manage') && ($batch['record_status'] ?? 'active') === 'active'): ?>
                <form method="post" class="void-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="void_batch">
                    <input type="hidden" name="source_key" value="<?php echo h((string) $batch['source_key']); ?>">
                    <input type="hidden" name="batch_id" value="<?php echo (int) $batch['id']; ?>">
                    <div class="form-grid" style="margin-top:18px;">
                        <div class="field field-full"><label for="void_reason">Void IN Batch Reason</label><textarea id="void_reason" name="reason" rows="3" placeholder="Required reason for voiding this untouched IN batch"></textarea></div>
                        <div class="field field-full"><button class="btn btn-danger" type="submit">Void IN Batch</button></div>
                    </div>
                </form>
            <?php endif; ?>
        </section>

        <section class="batch-history-grid">
            <div class="card history-card">
                <div class="history-card-top"><div><h2 class="section-title">OUT Records</h2><p class="section-subtitle">All release movements for this batch, including void status.</p></div><div class="history-metric-chip history-out-chip"><span>Active OUT Qty</span><strong><?php echo number_format((int) $summary['active_out']); ?></strong></div></div>
                <div class="table-wrap table-wrap-readable">
                    <table class="data-table data-table-readable">
                        <thead><tr><th>#</th><th>Date</th><th>Customer</th><th>Doc Type</th><th>Doc No</th><th class="ta-right qty-col-header">Qty Out</th><th>Status</th><th>Added By</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php if ($outRecords): foreach ($outRecords as $index => $out): ?>
                            <tr>
                                <td class="row-index"><?php echo $index + 1; ?></td>
                                <td><?php echo h(format_datetime($out['created_at'] ?? '')); ?></td>
                                <td><?php echo h((string) $out['customer_name']); ?></td>
                                <td><?php echo h((string) $out['document_type']); ?></td>
                                <td><?php echo h((string) $out['document_number']); ?></td>
                                <td class="ta-right qty-cell qty-out"><?php echo number_format((int) $out['qty_out']); ?></td>
                                <td><span class="badge <?php echo history_badge_class((string) (($out['record_status'] ?? 'active') === 'voided' ? 'voided' : ($out['return_status'] ?? 'delivered'))); ?>"><?php echo h(($out['record_status'] ?? 'active') === 'voided' ? 'Voided' : (string) ($out['return_status'] ?? 'Delivered')); ?></span></td>
                                <td><?php echo h((string) $out['added_by']); ?></td>
                                <td><a class="btn btn-mini btn-soft" href="transaction_detail.php?type=out&id=<?php echo (int) $out['id']; ?>">Open</a></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="9" class="empty-state">No OUT history found for this batch.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card history-card">
                <div class="history-card-top"><div><h2 class="section-title">RETURN Records</h2><p class="section-subtitle">All linked returns mapped back to the original release rows.</p></div><div class="history-metric-chip history-return-chip"><span>Active Returned Qty</span><strong><?php echo number_format((int) $summary['active_returned']); ?></strong></div></div>
                <div class="table-wrap table-wrap-readable">
                    <table class="data-table data-table-readable">
                        <thead><tr><th>#</th><th>Date</th><th>Customer</th><th>Reference</th><th class="ta-right qty-col-header">Qty Returned</th><th>Status</th><th>Returned By</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php if ($returnRecords): foreach ($returnRecords as $index => $ret): ?>
                            <tr>
                                <td class="row-index"><?php echo $index + 1; ?></td>
                                <td><?php echo h(format_datetime($ret['created_at'] ?? '')); ?></td>
                                <td><?php echo h((string) $ret['customer_name']); ?></td>
                                <td><?php echo h((string) ($ret['document_type'] . ' - ' . $ret['document_number'])); ?></td>
                                <td class="ta-right qty-cell qty-return"><?php echo number_format((int) $ret['qty_returned']); ?></td>
                                <td><span class="badge <?php echo history_badge_class((string) ($ret['record_status'] ?? 'active')); ?>"><?php echo h(($ret['record_status'] ?? 'active') === 'voided' ? 'Voided' : 'Active'); ?></span></td>
                                <td><?php echo h((string) $ret['returned_by']); ?></td>
                                <td><a class="btn btn-mini btn-soft" href="transaction_detail.php?type=return&id=<?php echo (int) $ret['id']; ?>">Open</a></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="8" class="empty-state">No RETURN history found for this batch.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="card notes-card">
            <div class="section-header"><div><h2 class="section-title">Batch Notes</h2><p class="section-subtitle">Operational remarks attached directly to this batch record.</p></div></div>
            <?php if (user_can('notes.add')): ?>
                <form method="post" action="notes_action.php" class="note-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="target_type" value="<?php echo h((string) $batch['target_type']); ?>">
                    <input type="hidden" name="target_id" value="<?php echo (int) $batch['id']; ?>">
                    <input type="hidden" name="redirect_url" value="<?php echo h('batch_history.php?batch=' . urlencode((string) $batch['batch_no'])); ?>">
                    <div class="field"><label for="batch_note_text">Add Note</label><textarea id="batch_note_text" name="note_text" rows="4" placeholder="Add a batch remark, storage reminder, correction context, or handling note"></textarea></div>
                    <button class="btn btn-primary" type="submit">Save Note</button>
                </form>
            <?php endif; ?>
            <div class="notes-list">
                <?php if ($batchNotes): foreach ($batchNotes as $note): ?>
                        <div class="note-item">
                            <div class="note-meta"><strong><?php echo h((string) $note['created_by']); ?></strong><span><?php echo h(format_datetime($note['created_at'] ?? '')); ?></span></div>
                            <div class="note-body"><?php echo nl2br(h((string) $note['note_text'])); ?></div>
                            <?php if (can_manage_note_record($note)): ?>
                                <div class="inline-actions">
                                    <a class="btn btn-soft btn-mini" href="note_edit.php?id=<?php echo (int) $note['id']; ?>&redirect=<?php echo urlencode('batch_history.php?batch=' . (string) $batch['batch_no']); ?>">Edit</a>
                                    <form method="post" action="notes_action.php">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="note_id" value="<?php echo (int) $note['id']; ?>">
                                    <input type="hidden" name="redirect_url" value="<?php echo h('batch_history.php?batch=' . urlencode((string) $batch['batch_no'])); ?>">
                                    <button class="btn btn-outline btn-mini" type="submit">Delete</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; else: ?>
                    <div class="empty-state">No notes yet for this batch.</div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
    <?php close_page_stack(); ?>
</div>
</body>
</html>
