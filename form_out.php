<?php
require_once 'includes/common.php';
require_login();
require_permission('inventory.out.create');

$inventory = fetch_inventory_flat($conn, false, ['stock_filter' => 'all']);
$inventory = array_values(array_filter($inventory, static fn(array $row): bool => (int) ($row['qty'] ?? 0) > 0 && ($row['record_status'] ?? 'active') === 'active'));
$error = '';
$old = [
    'inventory_id' => trim((string) ($_POST['inventory_id'] ?? '')),
    'qty_out' => trim((string) ($_POST['qty_out'] ?? '')),
    'customer_name' => trim((string) ($_POST['customer_name'] ?? '')),
    'document_type' => trim((string) ($_POST['document_type'] ?? '')),
    'document_number' => trim((string) ($_POST['document_number'] ?? '')),
    'note_text' => trim((string) ($_POST['note_text'] ?? '')),
];

if (request_is_post()) {
    verify_csrf_or_fail();

    $inventoryId = (int) $old['inventory_id'];
    $qtyOut = max(1, (int) $old['qty_out']);
    $customerName = $old['customer_name'];
    $documentType = $old['document_type'];
    $documentNumber = $old['document_number'];
    $noteText = $old['note_text'];

    $item = fetch_batch_by_source_id($conn, 'regular', $inventoryId, true);
    if (!$item || ($item['record_status'] ?? 'active') !== 'active') {
        $error = 'Selected inventory batch was not found.';
    } elseif ($customerName === '' || $documentType === '' || $documentNumber === '') {
        $error = 'Customer name and document reference are required.';
    } elseif ((int) ($item['qty'] ?? 0) < $qtyOut) {
        $error = 'Quantity OUT exceeds available stock.';
    } else {
        $conn->begin_transaction();
        try {
            db_execute(
                $conn,
                'UPDATE inventory SET qty = qty - ?, qty_out = qty_out + ?, updated_at = NOW() WHERE id = ?',
                'iii',
                [$qtyOut, $qtyOut, $inventoryId]
            );

            $actor = current_user_actor();
            $stmt = $conn->prepare(
                'INSERT INTO out_records (inventory_id, qty_out, customer_name, document_type, document_number, created_at, qty_returned, return_status, added_by, record_status, updated_at)
                 VALUES (?, ?, ?, ?, ?, NOW(), 0, "Delivered", ?, "active", NOW())'
            );
            $stmt->bind_param('iissss', $inventoryId, $qtyOut, $customerName, $documentType, $documentNumber, $actor['name']);
            $stmt->execute();
            $outId = (int) $stmt->insert_id;
            $stmt->close();

            if ($noteText !== '' && user_can('notes.add')) {
                add_note_record($conn, 'out_record', $outId, $noteText);
            }

            log_audit($conn, 'create', 'out_record', $outId, 'Created OUT transaction', null, [], [
                'inventory_id' => $inventoryId,
                'qty_out' => $qtyOut,
                'customer_name' => $customerName,
                'document_type' => $documentType,
                'document_number' => $documentNumber,
            ]);

            $conn->commit();
            set_flash('success', 'Inventory OUT saved successfully.');
            redirect('dashboard.php');
        } catch (Throwable $exception) {
            $conn->rollback();
            $error = 'Unable to save the OUT transaction: ' . $exception->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory OUT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="app-shell app-shell-form">
    <?php render_app_nav($conn, 'Inventory OUT', 'Release stock from active regular inventory batches with traceable customer and document references.', 'Inventory Transaction'); ?>
    <section class="card form-page-card">
        <?php if ($error !== ''): ?><div class="flash flash-error"><?php echo h($error); ?></div><?php endif; ?>
        <form method="post" class="form-page-form">
            <?php echo csrf_field(); ?>
            <div class="form-block">
                <div class="form-block-head"><h2 class="section-title">Release details</h2><p class="section-subtitle">Choose a live batch, confirm quantity, and capture the receiving reference details.</p></div>
                <div class="form-grid">
                    <div class="field field-full">
                        <label for="inventory_id">Inventory Batch</label>
                        <select name="inventory_id" id="inventory_id" required onchange="updateBatchPreview()">
                            <option value="">Select a batch</option>
                            <?php foreach ($inventory as $row): ?>
                                <option value="<?php echo (int) $row['id']; ?>" data-qty="<?php echo (int) $row['qty']; ?>" data-batch="<?php echo h((string) $row['batch_no']); ?>" <?php echo selected_attr($old['inventory_id'], (string) $row['id']); ?>>
                                    <?php echo h($row['generic_name'] . ' | ' . $row['brand_name'] . ' | ' . $row['dosage_strength'] . ' | Batch ' . $row['batch_no'] . ' | Available ' . number_format((int) $row['qty'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field"><label for="qty_out">Quantity OUT</label><input type="number" id="qty_out" name="qty_out" min="1" value="<?php echo h($old['qty_out']); ?>" required></div>
                    <div class="field"><label for="customer_name">Customer Name</label><input type="text" id="customer_name" name="customer_name" value="<?php echo h($old['customer_name']); ?>" required></div>
                    <div class="field"><label for="document_type">Document Type</label><input type="text" id="document_type" name="document_type" value="<?php echo h($old['document_type']); ?>" placeholder="DR, SI, DONATION, FDA_SAMPLE..." required></div>
                    <div class="field"><label for="document_number">Document Number</label><input type="text" id="document_number" name="document_number" value="<?php echo h($old['document_number']); ?>" required></div>
                    <div class="field field-full"><label for="note_text">Remark / Note</label><textarea id="note_text" name="note_text" rows="4" placeholder="Optional note for this OUT transaction"><?php echo h($old['note_text']); ?></textarea></div>
                </div>
            </div>
            <div class="form-preview-strip">
                <div class="preview-chip"><span>Selected Batch</span><strong id="selectedBatchLabel">-</strong></div>
                <div class="preview-chip"><span>Available Qty</span><strong id="selectedBatchQty">-</strong></div>
                <div class="preview-chip"><span>Processed By</span><strong><?php echo h(current_user_name()); ?></strong></div>
            </div>
            <div class="form-submit-row">
                <div class="form-submit-meta">Managers and admins can later correct or void the transaction with a required reason. Staff can create the transaction and add notes.</div>
                <button type="submit" class="btn btn-success btn-lg">Save Inventory OUT</button>
            </div>
        </form>
    </section>
    <?php close_page_stack(); ?>
</div>
<script>
function updateBatchPreview() {
  const select = document.getElementById('inventory_id');
  const option = select.options[select.selectedIndex];
  document.getElementById('selectedBatchLabel').textContent = option && option.value ? option.getAttribute('data-batch') : '-';
  document.getElementById('selectedBatchQty').textContent = option && option.value ? option.getAttribute('data-qty') : '-';
}
window.addEventListener('DOMContentLoaded', updateBatchPreview);
</script>
</body>
</html>
