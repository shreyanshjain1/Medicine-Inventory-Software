<?php
require_once 'includes/common.php';
require_login();
require_permission('inventory.return.create');

$outRecords = db_fetch_all(
    $conn,
    'SELECT o.*, i.generic_name, i.brand_name, i.dosage_strength, i.batch_no
     FROM out_records o
     INNER JOIN inventory i ON i.id = o.inventory_id
     WHERE o.record_status = "active"
     ORDER BY i.generic_name, i.brand_name, i.dosage_strength, o.created_at DESC'
);
$groupedRecords = [];
foreach ($outRecords as $row) {
    $remaining = (int) ($row['qty_out'] ?? 0) - (int) count_active_returns_for_out($conn, (int) ($row['id'] ?? 0));
    if ($remaining <= 0) {
        continue;
    }
    $row['remaining_returnable'] = $remaining;
    $productKey = ($row['generic_name'] ?? '') . ' - ' . ($row['brand_name'] ?? '') . ' (' . ($row['dosage_strength'] ?? '') . ')';
    $groupedRecords[$productKey][] = $row;
}

$error = '';
$old = [
    'out_record_id' => trim((string) ($_POST['out_record_id'] ?? '')),
    'qty_returned' => trim((string) ($_POST['qty_returned'] ?? '')),
    'note_text' => trim((string) ($_POST['note_text'] ?? '')),
];

if (request_is_post()) {
    verify_csrf_or_fail();

    $outRecordId = (int) $old['out_record_id'];
    $qtyReturned = max(1, (int) $old['qty_returned']);
    $noteText = $old['note_text'];
    $out = fetch_out_record_detail($conn, $outRecordId);
    $activeReturned = count_active_returns_for_out($conn, $outRecordId);

    if (!$out || ($out['record_status'] ?? 'active') !== 'active') {
        $error = 'Selected OUT record was not found.';
    } elseif ($qtyReturned + $activeReturned > (int) ($out['qty_out'] ?? 0)) {
        $error = 'Return quantity exceeds the remaining returnable quantity.';
    } else {
        $conn->begin_transaction();
        try {
            db_execute(
                $conn,
                'UPDATE inventory SET qty = qty + ?, qty_returned = qty_returned + ?, updated_at = NOW() WHERE id = ?',
                'iii',
                [$qtyReturned, $qtyReturned, (int) $out['inventory_id']]
            );

            $actor = current_user_actor();
            $stmt = $conn->prepare(
                'INSERT INTO return_binded_records (out_record_id, qty_returned, returned_by, created_at, record_status, updated_at)
                 VALUES (?, ?, ?, NOW(), "active", NOW())'
            );
            $stmt->bind_param('iis', $outRecordId, $qtyReturned, $actor['name']);
            $stmt->execute();
            $returnId = (int) $stmt->insert_id;
            $stmt->close();

            $newReturnedTotal = $activeReturned + $qtyReturned;
            db_execute(
                $conn,
                'UPDATE out_records SET qty_returned = ?, return_status = ? WHERE id = ?',
                'isi',
                [$newReturnedTotal, derive_return_status('active', (int) ($out['qty_out'] ?? 0), $newReturnedTotal), $outRecordId]
            );

            if ($noteText !== '' && user_can('notes.add')) {
                add_note_record($conn, 'return_record', $returnId, $noteText);
            }

            log_audit($conn, 'create', 'return_record', $returnId, 'Created RETURN transaction', null, [], [
                'out_record_id' => $outRecordId,
                'qty_returned' => $qtyReturned,
            ]);

            $conn->commit();
            set_flash('success', 'Inventory RETURN saved successfully.');
            redirect('dashboard.php');
        } catch (Throwable $exception) {
            $conn->rollback();
            $error = 'Unable to save the RETURN transaction: ' . $exception->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory RETURN</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="app-shell app-shell-form">
    <?php render_app_nav($conn, 'Inventory RETURN', 'Bind returns directly to the original OUT record so stock and traceability stay synchronized.', 'Inventory Transaction'); ?>
    <section class="card form-page-card">
        <?php if ($error !== ''): ?><div class="flash flash-error"><?php echo h($error); ?></div><?php endif; ?>
        <form method="post" class="form-page-form">
            <?php echo csrf_field(); ?>
            <div class="form-block">
                <div class="form-block-head"><h2 class="section-title">Return details</h2><p class="section-subtitle">Search the original release, then post only the quantity being returned now.</p></div>
                <div class="form-grid">
                    <div class="field field-full">
                        <label for="out_record_id">OUT Record</label>
                        <select name="out_record_id" id="out_record_id" required onchange="updateReturnPreview()">
                            <option value="">Select an OUT record</option>
                            <?php foreach ($groupedRecords as $product => $rows): ?>
                                <optgroup label="<?php echo h($product); ?>">
                                    <?php foreach ($rows as $record): ?>
                                        <option value="<?php echo (int) $record['id']; ?>" data-batch="<?php echo h((string) $record['batch_no']); ?>" data-customer="<?php echo h((string) $record['customer_name']); ?>" data-reference="<?php echo h((string) ($record['document_type'] . '-' . $record['document_number'])); ?>" data-qty-out="<?php echo (int) $record['qty_out']; ?>" data-returned="<?php echo (int) count_active_returns_for_out($conn, (int) $record['id']); ?>" data-remaining="<?php echo (int) $record['remaining_returnable']; ?>" <?php echo selected_attr($old['out_record_id'], (string) $record['id']); ?>>
                                            <?php echo h('To: ' . $record['customer_name'] . ' | ' . $record['document_type'] . '-' . $record['document_number'] . ' | Qty Out: ' . $record['qty_out'] . ' | Remaining: ' . $record['remaining_returnable'] . ' | Batch: ' . $record['batch_no']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field"><label for="qty_returned">Quantity Returned</label><input id="qty_returned" type="number" min="1" name="qty_returned" value="<?php echo h($old['qty_returned']); ?>" required></div>
                    <div class="field field-full"><label for="note_text">Remark / Note</label><textarea id="note_text" name="note_text" rows="4" placeholder="Optional note for this RETURN transaction"><?php echo h($old['note_text']); ?></textarea></div>
                </div>
            </div>
            <div class="form-preview-grid">
                <div class="preview-chip"><span>Batch</span><strong id="previewBatch">-</strong></div>
                <div class="preview-chip"><span>Customer</span><strong id="previewCustomer">-</strong></div>
                <div class="preview-chip"><span>Reference</span><strong id="previewReference">-</strong></div>
                <div class="preview-chip"><span>Qty Out</span><strong id="previewQtyOut">-</strong></div>
                <div class="preview-chip"><span>Already Returned</span><strong id="previewAlreadyReturned">-</strong></div>
                <div class="preview-chip"><span>Remaining Returnable</span><strong id="previewRemaining">-</strong></div>
            </div>
            <div class="form-submit-row">
                <div class="form-submit-meta">Managers and admins can later correct or void the return with a required reason. Staff can create and annotate the return.</div>
                <button type="submit" class="btn btn-warning btn-lg">Save Return</button>
            </div>
        </form>
    </section>
    <?php close_page_stack(); ?>
</div>
<script>
function updateReturnPreview() {
  const select = document.getElementById('out_record_id');
  const option = select.options[select.selectedIndex];
  const map = {
    previewBatch: option && option.value ? option.getAttribute('data-batch') : '-',
    previewCustomer: option && option.value ? option.getAttribute('data-customer') : '-',
    previewReference: option && option.value ? option.getAttribute('data-reference') : '-',
    previewQtyOut: option && option.value ? option.getAttribute('data-qty-out') : '-',
    previewAlreadyReturned: option && option.value ? option.getAttribute('data-returned') : '-',
    previewRemaining: option && option.value ? option.getAttribute('data-remaining') : '-'
  };
  Object.keys(map).forEach(function (id) {
    document.getElementById(id).textContent = map[id];
  });
}
window.addEventListener('DOMContentLoaded', updateReturnPreview);
</script>
</body>
</html>
