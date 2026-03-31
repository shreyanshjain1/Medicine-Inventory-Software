<?php
require_once 'includes/common.php';
require_login();
require_permission('corrections.manage');

$type = trim((string) ($_GET['type'] ?? $_POST['type'] ?? 'batch'));
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$source = trim((string) ($_GET['source'] ?? $_POST['source'] ?? 'regular'));
$source = $source === 'outsourced' ? 'outsourced' : 'regular';
$errors = [];
$products = fetch_products($conn, ['status' => 'active']);

if ($type === 'batch') {
    $record = fetch_batch_by_source_id($conn, $source, $id, true);
} elseif ($type === 'out') {
    $record = fetch_out_record_detail($conn, $id);
} else {
    $type = 'return';
    $record = fetch_return_record_detail($conn, $id);
}

if (!$record) {
    set_flash('error', 'Record not found.');
    redirect('dashboard.php');
}

$old = $_POST ?: $record;

if (request_is_post()) {
    verify_csrf_or_fail();
    $reason = trim((string) ($_POST['reason'] ?? ''));
    if ($reason === '') {
        $errors[] = 'A reason is required for every correction.';
    } else {
        if ($type === 'batch') {
            correct_inventory_batch($conn, $source, $id, $_POST, $reason, $errors);
        } elseif ($type === 'out') {
            correct_out_record($conn, $id, $_POST, $reason, $errors);
        } else {
            correct_return_record($conn, $id, $_POST, $reason, $errors);
        }

        if (!$errors) {
            set_flash('success', 'Record corrected successfully.');
            if ($type === 'batch') {
                redirect('batch_history.php?batch=' . urlencode((string) ($_POST['batch_no'] ?? $record['batch_no'])));
            }
            redirect('transaction_detail.php?type=' . urlencode($type) . '&id=' . $id);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correct Record</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="app-shell app-shell-form">
    <?php render_app_nav($conn, 'Correct Record', 'All edits require a reason and are written to the correction log with before and after values.', 'Correction Workflow'); ?>
    <section class="card form-page-card">
        <?php if ($errors): ?><div class="flash flash-error"><?php foreach ($errors as $error): ?><div><?php echo h($error); ?></div><?php endforeach; ?></div><?php endif; ?>
        <form method="post" class="form-page-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="type" value="<?php echo h($type); ?>">
            <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
            <input type="hidden" name="source" value="<?php echo h($source); ?>">
            <div class="form-block">
                <div class="form-block-head"><h2 class="section-title"><?php echo h(ucfirst($type)); ?> correction</h2><p class="section-subtitle">Update the live record safely while preserving an append-only audit trail.</p></div>
                <div class="form-grid">
                    <?php if ($type === 'batch'): ?>
                        <div class="field field-full"><label for="product_id">Product Master</label><select id="product_id" name="product_id"><?php foreach ($products as $product): ?><option value="<?php echo (int) $product['id']; ?>" <?php echo selected_attr($old['product_id'] ?? '', (string) $product['id']); ?>><?php echo h($product['generic_name'] . ' | ' . $product['brand_name'] . ' | ' . $product['dosage_strength']); ?></option><?php endforeach; ?></select></div>
                        <div class="field"><label for="batch_no">Batch No</label><input id="batch_no" type="text" name="batch_no" value="<?php echo h((string) ($old['batch_no'] ?? '')); ?>"></div>
                        <div class="field"><label for="qty_in">Qty In</label><input id="qty_in" type="number" min="1" name="qty_in" value="<?php echo h((string) ($old['qty_in'] ?? '')); ?>"></div>
                        <div class="field"><label for="mfg_date">Manufacturing Date</label><input id="mfg_date" type="date" name="mfg_date" value="<?php echo h(month_year_to_date_input((string) ($old['mfg_date'] ?? ''))); ?>"></div>
                        <div class="field"><label for="exp_date">Expiry Date</label><input id="exp_date" type="date" name="exp_date" value="<?php echo h(month_year_to_date_input((string) ($old['exp_date'] ?? ''))); ?>"></div>
                        <div class="field"><label for="low_stock_threshold">Low Stock Threshold</label><input id="low_stock_threshold" type="number" min="1" name="low_stock_threshold" value="<?php echo h((string) ($old['low_stock_threshold'] ?? '10')); ?>"></div>
                        <?php if ($source === 'outsourced'): ?>
                            <div class="field"><label for="importer_name">Importer Name</label><input id="importer_name" type="text" name="importer_name" value="<?php echo h((string) ($old['importer_name'] ?? '')); ?>"></div>
                            <div class="field"><label for="distributor_name">Distributor Name</label><input id="distributor_name" type="text" name="distributor_name" value="<?php echo h((string) ($old['distributor_name'] ?? '')); ?>"></div>
                        <?php endif; ?>
                    <?php elseif ($type === 'out'): ?>
                        <div class="field"><label for="qty_out">Qty Out</label><input id="qty_out" type="number" min="1" name="qty_out" value="<?php echo h((string) ($old['qty_out'] ?? '')); ?>"></div>
                        <div class="field"><label for="customer_name">Customer Name</label><input id="customer_name" type="text" name="customer_name" value="<?php echo h((string) ($old['customer_name'] ?? '')); ?>"></div>
                        <div class="field"><label for="document_type">Document Type</label><input id="document_type" type="text" name="document_type" value="<?php echo h((string) ($old['document_type'] ?? '')); ?>"></div>
                        <div class="field"><label for="document_number">Document Number</label><input id="document_number" type="text" name="document_number" value="<?php echo h((string) ($old['document_number'] ?? '')); ?>"></div>
                    <?php else: ?>
                        <div class="field"><label for="qty_returned">Qty Returned</label><input id="qty_returned" type="number" min="1" name="qty_returned" value="<?php echo h((string) ($old['qty_returned'] ?? '')); ?>"></div>
                    <?php endif; ?>
                    <div class="field field-full"><label for="reason">Correction Reason</label><textarea id="reason" name="reason" rows="4" placeholder="Required"><?php echo h((string) ($_POST['reason'] ?? '')); ?></textarea></div>
                </div>
            </div>
            <div class="form-submit-row">
                <div class="form-submit-meta">The system will recalculate the live stock impact, reject invalid corrections, and store both old and new values in the correction log.</div>
                <button class="btn btn-primary btn-lg" type="submit">Save Correction</button>
            </div>
        </form>
    </section>
    <?php close_page_stack(); ?>
</div>
</body>
</html>
