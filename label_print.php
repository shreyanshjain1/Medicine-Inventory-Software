<?php
require_once 'includes/common.php';
require_login();
require_permission('barcode.view');

$type = trim((string) ($_GET['type'] ?? 'product'));
$id = (int) ($_GET['id'] ?? 0);
$source = trim((string) ($_GET['source'] ?? 'regular'));
$source = $source === 'outsourced' ? 'outsourced' : 'regular';

if ($type === 'batch') {
    $item = fetch_batch_by_source_id($conn, $source, $id, true);
    if (!$item) {
        exit('Batch not found.');
    }
    $labelValue = (string) ($item['batch_no'] ?? '');
    $title = (string) ($item['generic_name'] . ' | ' . $item['brand_name']);
    $subtitle = 'Batch ' . ($item['batch_no'] ?? '');
} else {
    $item = fetch_product_by_id($conn, $id);
    if (!$item) {
        exit('Product not found.');
    }
    $labelValue = (string) ($item['barcode_value'] ?: ($item['registration_no'] ?: 'PRODUCT-' . $item['id']));
    $title = (string) ($item['generic_name'] . ' | ' . $item['brand_name']);
    $subtitle = (string) ($item['dosage_strength'] . ' | ' . $item['manufacturer']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Label</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body class="label-body">
    <div class="label-card">
        <div class="eyebrow">Barcode Label</div>
        <h1><?php echo h($title); ?></h1>
        <p><?php echo h($subtitle); ?></p>
        <div class="barcode-preview"><?php echo code39_svg($labelValue, 90); ?></div>
        <div class="muted">Printed from PITC Inventory Flagship</div>
    </div>
    <script>window.onload = function () { window.print(); };</script>
</body>
</html>
