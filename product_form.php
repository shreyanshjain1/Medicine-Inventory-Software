<?php
require_once 'includes/common.php';
require_login();
require_permission('products.view');

$productId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$product = $productId > 0 ? fetch_product_by_id($conn, $productId) : null;
$isEditing = (bool) $product;
$errors = [];

$old = [
    'generic_name' => trim((string) ($_POST['generic_name'] ?? ($product['generic_name'] ?? ''))),
    'brand_name' => trim((string) ($_POST['brand_name'] ?? ($product['brand_name'] ?? ''))),
    'dosage_strength' => trim((string) ($_POST['dosage_strength'] ?? ($product['dosage_strength'] ?? ''))),
    'manufacturer' => trim((string) ($_POST['manufacturer'] ?? ($product['manufacturer'] ?? ''))),
    'registration_no' => trim((string) ($_POST['registration_no'] ?? ($product['registration_no'] ?? ''))),
    'default_low_stock_threshold' => trim((string) ($_POST['default_low_stock_threshold'] ?? ($product['default_low_stock_threshold'] ?? '10'))),
    'product_type' => trim((string) ($_POST['product_type'] ?? ($product['product_type'] ?? 'medicine'))),
    'product_status' => trim((string) ($_POST['product_status'] ?? ($product['product_status'] ?? 'active'))),
    'barcode_value' => trim((string) ($_POST['barcode_value'] ?? ($product['barcode_value'] ?? ''))),
    'reason' => trim((string) ($_POST['reason'] ?? '')),
];

if (request_is_post()) {
    verify_csrf_or_fail();
    require_permission('products.manage');
    $action = trim((string) ($_POST['action'] ?? 'save'));

    if ($action === 'archive' && $product) {
        if ($old['reason'] === '') {
            $errors[] = 'A reason is required to archive a product.';
        } elseif (!archive_product_record($conn, $productId, $old['reason'])) {
            $errors[] = 'Unable to archive the product.';
        } else {
            set_flash('success', 'Product archived successfully.');
            redirect('products.php?status=archived');
        }
    } else {
        $payload = product_payload_from_request($_POST);
        $errors = validate_product_payload($conn, $payload, $isEditing ? $productId : null);
        if ($isEditing && $old['reason'] === '') {
            $errors[] = 'A reason is required when correcting product master details.';
        }

        if (!$errors) {
            if ($isEditing) {
                if (update_product_record($conn, $productId, $payload, $old['reason'])) {
                    set_flash('success', 'Product updated successfully.');
                    redirect('product_form.php?id=' . $productId);
                }
                $errors[] = 'Unable to update the product.';
            } else {
                $newId = create_product_record($conn, $payload);
                if ($newId > 0) {
                    set_flash('success', 'Product created successfully.');
                    redirect('product_form.php?id=' . $newId);
                }
                $errors[] = 'Unable to create the product.';
            }
        }
    }
}

$notes = $product ? fetch_notes($conn, 'product', $productId) : [];
$batchCount = $product ? db_fetch_one($conn, 'SELECT ((SELECT COUNT(*) FROM inventory WHERE product_id = ? AND record_status = "active") + (SELECT COUNT(*) FROM inventory_outsourced WHERE product_id = ? AND record_status = "active")) AS total', 'ii', [$productId, $productId]) : ['total' => 0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEditing ? 'Product Detail' : 'Add Product'; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="app-shell app-shell-form">
    <?php render_app_nav($conn, $isEditing ? 'Product Detail' : 'Add Product', 'Create or correct product master records with reason logging, barcode storage, and threshold control.', 'Product Master'); ?>
    <section class="card form-page-card">
        <?php if ($errors): ?><div class="flash flash-error"><?php foreach ($errors as $error): ?><div><?php echo h($error); ?></div><?php endforeach; ?></div><?php endif; ?>
        <form method="post" class="form-page-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo (int) $productId; ?>">
            <div class="form-block">
                <div class="form-block-head"><h2 class="section-title"><?php echo $isEditing ? 'Product master details' : 'New product master'; ?></h2><p class="section-subtitle">These fields define the reusable medicine profile used during future IN transactions.</p></div>
                <div class="form-grid">
                    <div class="field"><label for="generic_name">Generic Name</label><input id="generic_name" type="text" name="generic_name" value="<?php echo h($old['generic_name']); ?>" required></div>
                    <div class="field"><label for="brand_name">Brand Name</label><input id="brand_name" type="text" name="brand_name" value="<?php echo h($old['brand_name']); ?>" required></div>
                    <div class="field"><label for="dosage_strength">Dosage & Strength</label><input id="dosage_strength" type="text" name="dosage_strength" value="<?php echo h($old['dosage_strength']); ?>" required></div>
                    <div class="field"><label for="manufacturer">Manufacturer</label><input id="manufacturer" type="text" name="manufacturer" value="<?php echo h($old['manufacturer']); ?>" required></div>
                    <div class="field"><label for="registration_no">Registration No</label><input id="registration_no" type="text" name="registration_no" value="<?php echo h($old['registration_no']); ?>"></div>
                    <div class="field"><label for="default_low_stock_threshold">Default Low Stock Threshold</label><input id="default_low_stock_threshold" type="number" min="1" name="default_low_stock_threshold" value="<?php echo h($old['default_low_stock_threshold']); ?>"></div>
                    <div class="field"><label for="product_type">Product Type</label><input id="product_type" type="text" name="product_type" value="<?php echo h($old['product_type']); ?>"></div>
                    <div class="field"><label for="barcode_value">Barcode Value</label><input id="barcode_value" type="text" name="barcode_value" value="<?php echo h($old['barcode_value']); ?>" placeholder="Code39-safe value"></div>
                    <div class="field"><label for="product_status">Status</label><select id="product_status" name="product_status"><option value="active" <?php echo selected_attr($old['product_status'], 'active'); ?>>Active</option><option value="archived" <?php echo selected_attr($old['product_status'], 'archived'); ?>>Archived</option></select></div>
                    <div class="field"><label for="reason">Reason<?php echo $isEditing ? ' for Change' : ' (optional)'; ?></label><input id="reason" type="text" name="reason" value="<?php echo h($old['reason']); ?>" placeholder="<?php echo $isEditing ? 'Required for corrections' : 'Optional create note'; ?>"></div>
                </div>
            </div>
            <div class="form-preview-strip">
                <div class="preview-chip"><span>Active Batches</span><strong><?php echo number_format((int) ($batchCount['total'] ?? 0)); ?></strong></div>
                <div class="preview-chip"><span>Barcode</span><strong><?php echo h($old['barcode_value'] !== '' ? $old['barcode_value'] : 'N/A'); ?></strong></div>
                <div class="preview-chip"><span>Status</span><strong><?php echo h(ucfirst($old['product_status'])); ?></strong></div>
            </div>
            <div class="form-submit-row">
                <div class="form-submit-meta">Editing a product master record updates the linked active batch snapshots and writes a correction log entry with old and new values.</div>
                <div class="inline-actions">
                    <a class="btn btn-outline" href="products.php">Back to Products</a>
                    <?php if (user_can('products.manage')): ?><button class="btn btn-primary btn-lg" type="submit" name="action" value="save"><?php echo $isEditing ? 'Save Changes' : 'Create Product'; ?></button><?php endif; ?>
                </div>
            </div>
        </form>

        <?php if ($product): ?>
            <section class="product-detail-grid">
                <div class="card barcode-card">
                    <div class="section-header"><div><h2 class="section-title">Barcode Label</h2><p class="section-subtitle">Printable Code39 label for scanner or typed-input workflows.</p></div></div>
                    <div class="barcode-preview"><?php echo code39_svg((string) ($product['barcode_value'] ?: $product['id'] . '-' . $product['registration_no'])); ?></div>
                    <div class="inline-actions"><?php if (user_can('barcode.view')): ?><a class="btn btn-outline" target="_blank" href="label_print.php?type=product&id=<?php echo (int) $productId; ?>">Open Print Label</a><?php endif; ?></div>
                </div>
                <?php if (user_can('products.manage') && ($product['product_status'] ?? 'active') !== 'archived'): ?>
                    <div class="card barcode-card">
                        <div class="section-header"><div><h2 class="section-title">Archive Product</h2><p class="section-subtitle">Archive the product master without deleting history or existing transactions.</p></div></div>
                        <form method="post">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id" value="<?php echo (int) $productId; ?>">
                            <input type="hidden" name="action" value="archive">
                            <div class="field"><label for="archive_reason">Archive Reason</label><textarea id="archive_reason" name="reason" rows="3" required></textarea></div>
                            <button class="btn btn-danger" type="submit">Archive Product</button>
                        </form>
                    </div>
                <?php endif; ?>
            </section>

            <section class="card notes-card">
                <div class="section-header"><div><h2 class="section-title">Product Notes</h2><p class="section-subtitle">Record product-level remarks, handling notes, or master-data context.</p></div></div>
                <?php if (user_can('notes.add')): ?>
                    <form method="post" action="notes_action.php" class="note-form">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="target_type" value="product">
                        <input type="hidden" name="target_id" value="<?php echo (int) $productId; ?>">
                        <input type="hidden" name="redirect_url" value="<?php echo h('product_form.php?id=' . $productId); ?>">
                        <div class="field"><label for="product_note_text">Add Note</label><textarea id="product_note_text" name="note_text" rows="4"></textarea></div>
                        <button class="btn btn-primary" type="submit">Save Note</button>
                    </form>
                <?php endif; ?>
                <div class="notes-list">
                    <?php if ($notes): foreach ($notes as $note): ?>
                        <div class="note-item">
                            <div class="note-meta"><strong><?php echo h((string) $note['created_by']); ?></strong><span><?php echo h(format_datetime($note['created_at'] ?? '')); ?></span></div>
                            <div class="note-body"><?php echo nl2br(h((string) $note['note_text'])); ?></div>
                            <?php if (can_manage_note_record($note)): ?>
                                <div class="inline-actions">
                                    <a class="btn btn-soft btn-mini" href="note_edit.php?id=<?php echo (int) $note['id']; ?>&redirect=<?php echo urlencode('product_form.php?id=' . $productId); ?>">Edit</a>
                                    <form method="post" action="notes_action.php"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="note_id" value="<?php echo (int) $note['id']; ?>"><input type="hidden" name="redirect_url" value="<?php echo h('product_form.php?id=' . $productId); ?>"><button class="btn btn-outline btn-mini" type="submit">Delete</button></form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; else: ?>
                        <div class="empty-state">No notes yet for this product.</div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </section>
    <?php close_page_stack(); ?>
</div>
</body>
</html>
