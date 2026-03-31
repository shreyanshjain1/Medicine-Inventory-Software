<?php
require 'db.php';
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user'];
$name = $user['name'];
$role = ucfirst($user['role']);

function formatDateToMonthYear($date)
{
    return date('m/Y', strtotime($date));
}

$errors = [];
$old = [
    'product_type' => $_POST['product_type'] ?? 'existing',
    'existing_product' => $_POST['existing_product'] ?? '',
    'generic_name' => $_POST['generic_name'] ?? '',
    'brand_name' => $_POST['brand_name'] ?? '',
    'dosage_strength' => $_POST['dosage_strength'] ?? '',
    'batch_no' => $_POST['batch_no'] ?? '',
    'mfg_date' => $_POST['mfg_date'] ?? '',
    'exp_date' => $_POST['exp_date'] ?? '',
    'manufacturer' => $_POST['manufacturer'] ?? '',
    'registration_no' => $_POST['registration_no'] ?? '',
    'qty_in' => $_POST['qty_in'] ?? '',
    'source' => $_POST['source'] ?? 'regular',
    'distributor_name' => $_POST['distributor_name'] ?? '',
];

$productOptions = [];
$productDetails = [];
$sql1 = "SELECT DISTINCT generic_name, brand_name, dosage_strength, manufacturer, registration_no FROM inventory";
$sql2 = "SELECT DISTINCT generic_name, brand_name, dosage_strength, manufacturer, registration_no FROM inventory_outsourced";
$result1 = $conn->query($sql1);
$result2 = $conn->query($sql2);
foreach ([$result1, $result2] as $result) {
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $key = $row['generic_name'] . '|' . $row['brand_name'] . '|' . $row['dosage_strength'];
            $productOptions[$key] = true;
            $productDetails[$key] = [
                'manufacturer' => $row['manufacturer'],
                'registration_no' => $row['registration_no']
            ];
        }
    }
}

function batch_exists(mysqli $conn, string $batchNo): bool {
    $stmt = $conn->prepare("SELECT batch_no FROM inventory WHERE batch_no = ? LIMIT 1");
    $stmt->bind_param('s', $batchNo);
    $stmt->execute();
    $found = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($found) return true;

    $stmt = $conn->prepare("SELECT batch_no FROM inventory_outsourced WHERE batch_no = ? LIMIT 1");
    $stmt->bind_param('s', $batchNo);
    $stmt->execute();
    $found = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = trim($_POST['product_type'] ?? '');

    if ($type !== 'existing' && $type !== 'new') {
        $errors[] = 'Please select whether this is an existing or new product.';
    }

    if ($type === 'existing') {
        $existingProduct = trim($_POST['existing_product'] ?? '');
        $batch_no = trim($_POST['batch_no'] ?? '');
        $mfg_date_raw = trim($_POST['mfg_date'] ?? '');
        $exp_date_raw = trim($_POST['exp_date'] ?? '');
        $manufacturer = trim($_POST['manufacturer'] ?? '');
        $registration_no = trim($_POST['registration_no'] ?? '');
        $qty_in = isset($_POST['qty_in']) ? (int) $_POST['qty_in'] : 0;

        if ($existingProduct === '') $errors[] = 'Please select an existing product.';
        if ($batch_no === '') $errors[] = 'Batch number is required.';
        if ($mfg_date_raw === '' || $exp_date_raw === '') $errors[] = 'Manufacturing date and expiry date are required.';
        if ($qty_in <= 0) $errors[] = 'Quantity IN must be greater than zero.';
        if ($batch_no !== '' && batch_exists($conn, $batch_no)) $errors[] = 'This batch number already exists. Please use a unique batch number.';

        if (!$errors) {
            list($generic_name, $brand_name, $dosage_strength) = explode('|', $existingProduct);
            $mfg_date = formatDateToMonthYear($mfg_date_raw);
            $exp_date = formatDateToMonthYear($exp_date_raw);

            $stmt = $conn->prepare("INSERT INTO inventory (generic_name, brand_name, dosage_strength, batch_no, mfg_date, exp_date, manufacturer, registration_no, qty_in, qty, qty_out, qty_returned) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)");
            $stmt->bind_param("ssssssssii", $generic_name, $brand_name, $dosage_strength, $batch_no, $mfg_date, $exp_date, $manufacturer, $registration_no, $qty_in, $qty_in);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO in_log (batch_no, added_by, added_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("ss", $batch_no, $name);
            $stmt->execute();
            $stmt->close();

            header("Location: dashboard.php");
            exit;
        }
    }

    if ($type === 'new') {
        $generic_name = trim($_POST['generic_name'] ?? '');
        $brand_name = trim($_POST['brand_name'] ?? '');
        $dosage_strength = trim($_POST['dosage_strength'] ?? '');
        $batch_no = trim($_POST['batch_no'] ?? '');
        $mfg_date_raw = trim($_POST['mfg_date'] ?? '');
        $exp_date_raw = trim($_POST['exp_date'] ?? '');
        $manufacturer = trim($_POST['manufacturer'] ?? '');
        $registration_no = trim($_POST['registration_no'] ?? '');
        $qty_in = isset($_POST['qty_in']) ? (int) $_POST['qty_in'] : 0;
        $source = trim($_POST['source'] ?? 'regular');
        $distributor_name = trim($_POST['distributor_name'] ?? '');

        if ($generic_name === '' || $brand_name === '' || $dosage_strength === '') $errors[] = 'Generic name, brand name, and dosage strength are required.';
        if ($batch_no === '') $errors[] = 'Batch number is required.';
        if ($mfg_date_raw === '' || $exp_date_raw === '') $errors[] = 'Manufacturing date and expiry date are required.';
        if ($qty_in <= 0) $errors[] = 'Quantity IN must be greater than zero.';
        if ($source === 'outsourced' && $distributor_name === '') $errors[] = 'Distributor name is required for outside sourced products.';
        if ($batch_no !== '' && batch_exists($conn, $batch_no)) $errors[] = 'This batch number already exists. Please use a unique batch number.';

        if (!$errors) {
            $mfg_date = formatDateToMonthYear($mfg_date_raw);
            $exp_date = formatDateToMonthYear($exp_date_raw);

            if ($source === 'outsourced') {
                $stmt = $conn->prepare("INSERT INTO inventory_outsourced (generic_name, brand_name, dosage_strength, batch_no, mfg_date, exp_date, manufacturer, registration_no, distributor_name, qty_in, qty, qty_out, qty_returned) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)");
                $stmt->bind_param("sssssssssii", $generic_name, $brand_name, $dosage_strength, $batch_no, $mfg_date, $exp_date, $manufacturer, $registration_no, $distributor_name, $qty_in, $qty_in);
            } else {
                $stmt = $conn->prepare("INSERT INTO inventory (generic_name, brand_name, dosage_strength, batch_no, mfg_date, exp_date, manufacturer, registration_no, qty_in, qty, qty_out, qty_returned) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)");
                $stmt->bind_param("ssssssssii", $generic_name, $brand_name, $dosage_strength, $batch_no, $mfg_date, $exp_date, $manufacturer, $registration_no, $qty_in, $qty_in);
            }
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO in_log (batch_no, added_by, added_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("ss", $batch_no, $name);
            $stmt->execute();
            $stmt->close();

            header("Location: dashboard.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Inventory IN</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/app.css"></head><body>
<div class="app-shell app-shell-form"><section class="card form-page-card"><div class="form-page-top"><div><div class="eyebrow">Inventory Transaction</div><h1 class="hero-page-title">Inventory IN</h1><p class="hero-page-subtitle">Add a new regular or outsourced batch without changing the existing table structure.</p></div><a class="btn btn-soft" href="dashboard.php">Back to Dashboard</a></div>
<?php if ($errors): ?><div class="flash flash-error"><?php foreach ($errors as $error): ?><div><?php echo htmlspecialchars($error); ?></div><?php endforeach; ?></div><?php endif; ?>
<form method="POST" class="form-page-form">
<div class="form-block form-mode-group"><div class="form-section-label">Product Type</div><div class="segmented segmented-large"><label class="segment-card"><input type="radio" name="product_type_selector" value="existing" <?php echo $old['product_type'] === 'existing' ? 'checked' : ''; ?> onclick="toggleProductType('existing')"><span>Existing Product</span></label><label class="segment-card"><input type="radio" name="product_type_selector" value="new" <?php echo $old['product_type'] === 'new' ? 'checked' : ''; ?> onclick="toggleProductType('new')"><span>New Product</span></label></div></div>
<input type="hidden" name="product_type" id="product_type" value="<?php echo htmlspecialchars($old['product_type']); ?>">
<div id="existingFields" class="form-block"><div class="form-block-head"><h2 class="section-title">Existing product entry</h2><p class="section-subtitle">Select an existing product profile, then log the new batch information.</p></div><div class="form-grid"><div class="field field-full"><label for="existing_product">Existing Product</label><select name="existing_product" id="existing_product" onchange="fillProductDetails(this)"><option value="">Select product</option><?php foreach (array_keys($productOptions) as $option): ?><option value="<?php echo htmlspecialchars($option); ?>" <?php echo $old['existing_product'] === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars(str_replace('|', ' • ', $option)); ?></option><?php endforeach; ?></select></div><div class="field"><label for="batch_no_existing">Batch No</label><input type="text" id="batch_no_existing" name="batch_no" value="<?php echo htmlspecialchars($old['batch_no']); ?>" placeholder="Enter batch number"></div><div class="field"><label for="qty_in_existing">Qty IN</label><input type="number" id="qty_in_existing" name="qty_in" min="1" value="<?php echo htmlspecialchars($old['qty_in']); ?>" placeholder="Enter quantity"></div><div class="field"><label for="mfg_date_existing">Manufacturing Date</label><input type="date" id="mfg_date_existing" name="mfg_date" value="<?php echo htmlspecialchars($old['mfg_date']); ?>"></div><div class="field"><label for="exp_date_existing">Expiry Date</label><input type="date" id="exp_date_existing" name="exp_date" value="<?php echo htmlspecialchars($old['exp_date']); ?>"></div><div class="field"><label for="manufacturer_existing">Manufacturer</label><input type="text" id="manufacturer_existing" name="manufacturer" value="<?php echo htmlspecialchars($old['manufacturer']); ?>" placeholder="Manufacturer"></div><div class="field"><label for="registration_no_existing">Registration No</label><input type="text" id="registration_no_existing" name="registration_no" value="<?php echo htmlspecialchars($old['registration_no']); ?>" placeholder="Registration number"></div></div></div>
<div id="newFields" class="form-block"><div class="form-block-head"><h2 class="section-title">New product entry</h2><p class="section-subtitle">Create a completely new regular or outsourced product batch.</p></div><div class="form-grid"><div class="field"><label for="generic_name">Generic Name</label><input type="text" id="generic_name" name="generic_name" value="<?php echo htmlspecialchars($old['generic_name']); ?>" placeholder="Generic name"></div><div class="field"><label for="brand_name">Brand Name</label><input type="text" id="brand_name" name="brand_name" value="<?php echo htmlspecialchars($old['brand_name']); ?>" placeholder="Brand name"></div><div class="field"><label for="dosage_strength">Dosage & Strength</label><input type="text" id="dosage_strength" name="dosage_strength" value="<?php echo htmlspecialchars($old['dosage_strength']); ?>" placeholder="e.g. 500mg, 2mg/mL"></div><div class="field"><label for="batch_no_new">Batch No</label><input type="text" id="batch_no_new" name="batch_no" value="<?php echo htmlspecialchars($old['batch_no']); ?>" placeholder="Enter batch number"></div><div class="field"><label for="qty_in_new">Qty IN</label><input type="number" id="qty_in_new" name="qty_in" min="1" value="<?php echo htmlspecialchars($old['qty_in']); ?>" placeholder="Enter quantity"></div><div class="field"><label for="manufacturer_new">Manufacturer</label><input type="text" id="manufacturer_new" name="manufacturer" value="<?php echo htmlspecialchars($old['manufacturer']); ?>" placeholder="Manufacturer"></div><div class="field"><label for="mfg_date_new">Manufacturing Date</label><input type="date" id="mfg_date_new" name="mfg_date" value="<?php echo htmlspecialchars($old['mfg_date']); ?>"></div><div class="field"><label for="exp_date_new">Expiry Date</label><input type="date" id="exp_date_new" name="exp_date" value="<?php echo htmlspecialchars($old['exp_date']); ?>"></div><div class="field"><label for="registration_no_new">Registration No</label><input type="text" id="registration_no_new" name="registration_no" value="<?php echo htmlspecialchars($old['registration_no']); ?>" placeholder="Registration number"></div><div class="field field-full"><label>Product Source</label><div class="segmented"><label><input type="radio" name="source" value="regular" <?php echo $old['source'] === 'regular' ? 'checked' : ''; ?> onclick="toggleSourceOption('regular')">In-House</label><label><input type="radio" name="source" value="outsourced" <?php echo $old['source'] === 'outsourced' ? 'checked' : ''; ?> onclick="toggleSourceOption('outsourced')">Outside Sourced</label></div></div><div class="field field-full" id="distributorField"><label for="distributor_name">Distributor Name</label><input type="text" id="distributor_name" name="distributor_name" value="<?php echo htmlspecialchars($old['distributor_name']); ?>" placeholder="Distributor name"></div></div></div>
<div class="form-submit-row"><div class="form-submit-meta"><strong>Logged in as:</strong> <?php echo htmlspecialchars($name); ?> · <?php echo htmlspecialchars($role); ?><br>Review the product, batch, quantity, and source carefully before saving this inventory IN transaction.</div><button type="submit" class="btn btn-primary btn-lg">Save Inventory IN</button></div>
</form></section></div>
<script>
const productDetails = <?php echo json_encode($productDetails); ?>;
function toggleProductType(type){document.getElementById('product_type').value=type;document.getElementById('existingFields').style.display=type==='existing'?'block':'none';document.getElementById('newFields').style.display=type==='new'?'block':'none';}
function fillProductDetails(select){const details=productDetails[select.value]||{};document.getElementById('manufacturer_existing').value=details.manufacturer||'';document.getElementById('registration_no_existing').value=details.registration_no||'';}
function toggleSourceOption(value){const distField=document.getElementById('distributorField'); if(distField){distField.style.display=value==='outsourced'?'block':'none';}}
window.addEventListener('DOMContentLoaded',()=>{toggleProductType(document.getElementById('product_type').value||'existing');toggleSourceOption('<?php echo htmlspecialchars($old['source']); ?>');const existingSelect=document.getElementById('existing_product'); if(existingSelect&&existingSelect.value){fillProductDetails(existingSelect);}});
</script>
</body></html>
