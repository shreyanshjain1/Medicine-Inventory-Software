<?php
require_once 'includes/common.php';
require_login();
require_permission('inventory.in.create');

$products = fetch_products($conn, ['status' => 'active']);
$errors = [];
$old = [
    'product_mode' => trim((string) ($_POST['product_mode'] ?? 'existing')),
    'existing_product_id' => trim((string) ($_POST['existing_product_id'] ?? '')),
    'generic_name' => trim((string) ($_POST['generic_name'] ?? '')),
    'brand_name' => trim((string) ($_POST['brand_name'] ?? '')),
    'dosage_strength' => trim((string) ($_POST['dosage_strength'] ?? '')),
    'manufacturer' => trim((string) ($_POST['manufacturer'] ?? '')),
    'registration_no' => trim((string) ($_POST['registration_no'] ?? '')),
    'default_low_stock_threshold' => trim((string) ($_POST['default_low_stock_threshold'] ?? '10')),
    'product_type' => trim((string) ($_POST['product_type'] ?? 'medicine')),
    'barcode_value' => trim((string) ($_POST['barcode_value'] ?? '')),
    'source' => trim((string) ($_POST['source'] ?? 'regular')),
    'batch_no' => trim((string) ($_POST['batch_no'] ?? '')),
    'qty_in' => trim((string) ($_POST['qty_in'] ?? '')),
    'mfg_date' => trim((string) ($_POST['mfg_date'] ?? '')),
    'exp_date' => trim((string) ($_POST['exp_date'] ?? '')),
    'distributor_name' => trim((string) ($_POST['distributor_name'] ?? '')),
    'importer_name' => trim((string) ($_POST['importer_name'] ?? '')),
    'low_stock_threshold' => trim((string) ($_POST['low_stock_threshold'] ?? '10')),
    'note_text' => trim((string) ($_POST['note_text'] ?? '')),
];

if (request_is_post()) {
    verify_csrf_or_fail();

    $mode = $old['product_mode'] === 'new' ? 'new' : 'existing';
    $source = $old['source'] === 'outsourced' ? 'outsourced' : 'regular';
    $batchNo = $old['batch_no'];
    $qtyIn = max(1, (int) $old['qty_in']);
    $mfgDate = date_input_to_month_year($old['mfg_date']);
    $expDate = date_input_to_month_year($old['exp_date']);
    $distributor = $old['distributor_name'];
    $importer = $old['importer_name'];
    $threshold = max(1, (int) $old['low_stock_threshold']);
    $noteText = $old['note_text'];
    $product = null;

    if ($batchNo === '') {
        $errors[] = 'Batch number is required.';
    } elseif (batch_exists($conn, $batchNo)) {
        $errors[] = 'Batch number already exists.';
    }

    if ($mfgDate === '' || $expDate === '') {
        $errors[] = 'Manufacturing and expiry dates are required.';
    }

    if ($source === 'outsourced' && $distributor === '') {
        $errors[] = 'Distributor name is required for outsourced entries.';
    }

    if ($mode === 'existing') {
        $product = fetch_product_by_id($conn, (int) $old['existing_product_id']);
        if (!$product || ($product['product_status'] ?? 'active') !== 'active') {
            $errors[] = 'Please select a valid active product.';
        }
    } else {
        $productPayload = product_payload_from_request($_POST);
        $errors = array_merge($errors, validate_product_payload($conn, $productPayload));
    }

    if (!$errors) {
        $conn->begin_transaction();
        try {
            if ($mode === 'new') {
                $productId = create_product_record($conn, $productPayload);
                $product = fetch_product_by_id($conn, $productId);
                if (!$product) {
                    throw new RuntimeException('The new product could not be created.');
                }
            }

            if ($source === 'outsourced') {
                $stmt = $conn->prepare(
                    'INSERT INTO inventory_outsourced
                    (product_id, generic_name, brand_name, dosage_strength, batch_no, mfg_date, exp_date, manufacturer, registration_no, importer_name, distributor_name, qty_in, qty, qty_out, qty_returned, low_stock_threshold, record_status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, "active", NOW(), NOW())'
                );
                $stmt->bind_param(
                    'issssssssssiii',
                    $product['id'],
                    $product['generic_name'],
                    $product['brand_name'],
                    $product['dosage_strength'],
                    $batchNo,
                    $mfgDate,
                    $expDate,
                    $product['manufacturer'],
                    $product['registration_no'],
                    $importer,
                    $distributor,
                    $qtyIn,
                    $qtyIn,
                    $threshold
                );
                $stmt->execute();
                $inventoryId = (int) $stmt->insert_id;
                $stmt->close();
                $inventoryTable = 'inventory_outsourced';
                $targetType = 'batch_outsourced';
            } else {
                $stmt = $conn->prepare(
                    'INSERT INTO inventory
                    (product_id, generic_name, brand_name, dosage_strength, batch_no, mfg_date, exp_date, manufacturer, registration_no, qty_in, qty, qty_out, qty_returned, low_stock_threshold, record_status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, "active", NOW(), NOW())'
                );
                $stmt->bind_param(
                    'issssssssiii',
                    $product['id'],
                    $product['generic_name'],
                    $product['brand_name'],
                    $product['dosage_strength'],
                    $batchNo,
                    $mfgDate,
                    $expDate,
                    $product['manufacturer'],
                    $product['registration_no'],
                    $qtyIn,
                    $qtyIn,
                    $threshold
                );
                $stmt->execute();
                $inventoryId = (int) $stmt->insert_id;
                $stmt->close();
                $inventoryTable = 'inventory';
                $targetType = 'batch_regular';
            }

            $actor = current_user_actor();
            $stmt = $conn->prepare(
                'INSERT INTO in_log
                (generic_name, brand_name, dosage_strength, batch_no, inventory_table, inventory_ref_id, product_id, mfg_date, exp_date, manufacturer, registration_no, qty_in, source_type, added_by, added_at, record_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), "active")'
            );
            $stmt->bind_param(
                'sssssiissssiss',
                $product['generic_name'],
                $product['brand_name'],
                $product['dosage_strength'],
                $batchNo,
                $inventoryTable,
                $inventoryId,
                $product['id'],
                $mfgDate,
                $expDate,
                $product['manufacturer'],
                $product['registration_no'],
                $qtyIn,
                $source,
                $actor['name']
            );
            $stmt->execute();
            $inLogId = (int) $stmt->insert_id;
            $stmt->close();

            if ($noteText !== '' && user_can('notes.add')) {
                add_note_record($conn, $targetType, $inventoryId, $noteText);
                add_note_record($conn, 'in_log', $inLogId, $noteText);
            }

            log_audit(
                $conn,
                'create',
                $targetType,
                $inventoryId,
                'Created IN batch',
                null,
                [],
                [
                    'batch_no' => $batchNo,
                    'qty_in' => $qtyIn,
                    'source' => $source,
                    'product_id' => $product['id'],
                ]
            );

            $conn->commit();
            set_flash('success', 'Inventory IN saved successfully for batch ' . $batchNo . '.');
            redirect('dashboard.php');
        } catch (Throwable $exception) {
            $conn->rollback();
            $errors[] = 'Unable to save the IN transaction: ' . $exception->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory IN</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="app-shell app-shell-form">
    <?php render_app_nav($conn, 'Inventory IN', 'Add stock from an existing product master entry or create a new product and its first batch in one controlled workflow.', 'Inventory Transaction'); ?>
    <section class="card form-page-card">
        <?php if ($errors): ?>
            <div class="flash flash-error"><?php foreach ($errors as $error): ?><div><?php echo h($error); ?></div><?php endforeach; ?></div>
        <?php endif; ?>
        <form method="post" class="form-page-form">
            <?php echo csrf_field(); ?>
            <div class="form-block form-mode-group">
                <div class="form-section-label">Product Mode</div>
                <div class="segmented segmented-large">
                    <label class="segment-card"><input type="radio" name="product_mode" value="existing" <?php echo checked_attr($old['product_mode'], 'existing'); ?> onclick="toggleProductMode('existing')"><span>Existing Product</span></label>
                    <label class="segment-card"><input type="radio" name="product_mode" value="new" <?php echo checked_attr($old['product_mode'], 'new'); ?> onclick="toggleProductMode('new')"><span>New Product</span></label>
                </div>
            </div>

            <div id="existingProductFields" class="form-block">
                <div class="form-block-head"><h2 class="section-title">Existing product entry</h2><p class="section-subtitle">Choose a product master profile and log a new regular or outsourced batch against it.</p></div>
                <div class="form-grid">
                    <div class="field field-full">
                        <label for="existing_product_id">Product Master</label>
                        <select name="existing_product_id" id="existing_product_id">
                            <option value="">Select product</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo (int) $product['id']; ?>" <?php echo selected_attr($old['existing_product_id'], (string) $product['id']); ?>>
                                    <?php echo h($product['generic_name'] . ' | ' . $product['brand_name'] . ' | ' . $product['dosage_strength'] . ' | ' . $product['manufacturer'] . ' | Barcode: ' . ($product['barcode_value'] ?: 'N/A')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div id="newProductFields" class="form-block">
                <div class="form-block-head"><h2 class="section-title">New product master</h2><p class="section-subtitle">Create a reusable product profile and immediately create its first tracked batch.</p></div>
                <div class="form-grid">
                    <div class="field"><label for="generic_name">Generic Name</label><input id="generic_name" type="text" name="generic_name" value="<?php echo h($old['generic_name']); ?>"></div>
                    <div class="field"><label for="brand_name">Brand Name</label><input id="brand_name" type="text" name="brand_name" value="<?php echo h($old['brand_name']); ?>"></div>
                    <div class="field"><label for="dosage_strength">Dosage & Strength</label><input id="dosage_strength" type="text" name="dosage_strength" value="<?php echo h($old['dosage_strength']); ?>"></div>
                    <div class="field"><label for="manufacturer">Manufacturer</label><input id="manufacturer" type="text" name="manufacturer" value="<?php echo h($old['manufacturer']); ?>"></div>
                    <div class="field"><label for="registration_no">Registration No</label><input id="registration_no" type="text" name="registration_no" value="<?php echo h($old['registration_no']); ?>"></div>
                    <div class="field"><label for="default_low_stock_threshold">Default Low Stock Threshold</label><input id="default_low_stock_threshold" type="number" min="1" name="default_low_stock_threshold" value="<?php echo h($old['default_low_stock_threshold']); ?>"></div>
                    <div class="field"><label for="product_type">Product Type</label><input id="product_type" type="text" name="product_type" value="<?php echo h($old['product_type']); ?>"></div>
                    <div class="field"><label for="barcode_value">Barcode Value</label><input id="barcode_value" type="text" name="barcode_value" value="<?php echo h($old['barcode_value']); ?>" placeholder="Code39-safe value"></div>
                </div>
            </div>

            <div class="form-block">
                <div class="form-block-head"><h2 class="section-title">Batch details</h2><p class="section-subtitle">Batch metadata is stored in the existing inventory tables while keeping product master data reusable.</p></div>
                <div class="form-grid">
                    <div class="field field-full">
                        <label>Batch Source</label>
                        <div class="segmented">
                            <label><input type="radio" name="source" value="regular" <?php echo checked_attr($old['source'], 'regular'); ?> onclick="toggleSourceFields('regular')">Regular</label>
                            <label><input type="radio" name="source" value="outsourced" <?php echo checked_attr($old['source'], 'outsourced'); ?> onclick="toggleSourceFields('outsourced')">Outsourced</label>
                        </div>
                    </div>
                    <div class="field"><label for="batch_no">Batch No</label><input id="batch_no" type="text" name="batch_no" value="<?php echo h($old['batch_no']); ?>" required></div>
                    <div class="field"><label for="qty_in">Qty IN</label><input id="qty_in" type="number" min="1" name="qty_in" value="<?php echo h($old['qty_in']); ?>" required></div>
                    <div class="field"><label for="mfg_date">Manufacturing Date</label><input id="mfg_date" type="date" name="mfg_date" value="<?php echo h($old['mfg_date']); ?>" required></div>
                    <div class="field"><label for="exp_date">Expiry Date</label><input id="exp_date" type="date" name="exp_date" value="<?php echo h($old['exp_date']); ?>" required></div>
                    <div class="field"><label for="low_stock_threshold">Batch Low Stock Threshold</label><input id="low_stock_threshold" type="number" min="1" name="low_stock_threshold" value="<?php echo h($old['low_stock_threshold']); ?>"></div>
                    <div class="field" id="importerField"><label for="importer_name">Importer Name</label><input id="importer_name" type="text" name="importer_name" value="<?php echo h($old['importer_name']); ?>"></div>
                    <div class="field" id="distributorField"><label for="distributor_name">Distributor Name</label><input id="distributor_name" type="text" name="distributor_name" value="<?php echo h($old['distributor_name']); ?>"></div>
                    <div class="field field-full"><label for="note_text">Initial Note / Remark</label><textarea id="note_text" name="note_text" rows="4" placeholder="Optional note for this batch or transaction"><?php echo h($old['note_text']); ?></textarea></div>
                </div>
            </div>

            <div class="form-submit-row">
                <div class="form-submit-meta">This action creates the IN transaction, maintains the active quantity, and optionally records notes immediately for the new batch.</div>
                <button type="submit" class="btn btn-primary btn-lg">Save Inventory IN</button>
            </div>
        </form>
    </section>
    <?php close_page_stack(); ?>
</div>
<script>
function toggleProductMode(mode) {
  document.getElementById('existingProductFields').style.display = mode === 'existing' ? 'block' : 'none';
  document.getElementById('newProductFields').style.display = mode === 'new' ? 'block' : 'none';
}
function toggleSourceFields(source) {
  const showOutsourced = source === 'outsourced';
  document.getElementById('importerField').style.display = showOutsourced ? 'block' : 'none';
  document.getElementById('distributorField').style.display = showOutsourced ? 'block' : 'none';
}
window.addEventListener('DOMContentLoaded', function () {
  toggleProductMode('<?php echo h($old['product_mode']); ?>');
  toggleSourceFields('<?php echo h($old['source']); ?>');
});
</script>
</body>
</html>
