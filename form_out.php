<?php
require 'db.php';
session_start();
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit; }
$user = $_SESSION['user']; $name = $user['name'];
$inventory = [];
$sql = "SELECT id, generic_name, brand_name, dosage_strength, batch_no, qty FROM inventory ORDER BY generic_name, brand_name, dosage_strength, batch_no";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $inventory[$row['id']] = [
            'label' => $row['generic_name'] . ' • ' . $row['brand_name'] . ' • ' . $row['dosage_strength'] . ' • Batch ' . $row['batch_no'],
            'qty' => (int) $row['qty'],
            'batch_no' => $row['batch_no']
        ];
    }
}
$error = '';
$old = ['inventory_id' => $_POST['inventory_id'] ?? '', 'qty_out' => $_POST['qty_out'] ?? '', 'customer_name' => $_POST['customer_name'] ?? '', 'document_type' => $_POST['document_type'] ?? '', 'document_number' => $_POST['document_number'] ?? ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inventory_id = (int)($_POST['inventory_id'] ?? 0); $qty_out = (int)($_POST['qty_out'] ?? 0); $customer_name = trim($_POST['customer_name'] ?? ''); $document_type = trim($_POST['document_type'] ?? ''); $document_number = trim($_POST['document_number'] ?? '');
    $stmt = $conn->prepare("SELECT qty FROM inventory WHERE id = ?"); $stmt->bind_param("i", $inventory_id); $stmt->execute(); $item = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$item) $error = "Selected inventory batch was not found.";
    elseif ($qty_out <= 0) $error = "Quantity OUT must be greater than zero.";
    elseif ($customer_name === '') $error = "Customer name is required.";
    elseif ($document_type === '') $error = "Document type is required.";
    elseif ($document_number === '') $error = "Document number is required.";
    elseif ((int)$item['qty'] < $qty_out) $error = "Invalid quantity selected or not enough stock.";
    else {
        $stmt = $conn->prepare("UPDATE inventory SET qty = qty - ?, qty_out = qty_out + ? WHERE id = ?"); $stmt->bind_param("iii", $qty_out, $qty_out, $inventory_id); $stmt->execute(); $stmt->close();
        $stmt = $conn->prepare("INSERT INTO out_records (inventory_id, qty_out, customer_name, document_type, document_number, created_at, added_by, qty_returned, return_status) VALUES (?, ?, ?, ?, ?, NOW(), ?, 0, 'No return yet')"); $stmt->bind_param("iissss", $inventory_id, $qty_out, $customer_name, $document_type, $document_number, $name); $stmt->execute(); $stmt->close();
        header("Location: dashboard.php"); exit;
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Inventory OUT</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/app.css"></head><body>
<div class="app-shell app-shell-form"><section class="card form-page-card"><div class="form-page-top"><div><div class="eyebrow">Inventory Transaction</div><h1 class="hero-page-title">Inventory OUT</h1><p class="hero-page-subtitle">Release stock from an existing regular batch and keep the movement linked in <code>out_records</code>.</p></div><a class="btn btn-soft" href="dashboard.php">Back to Dashboard</a></div>
<?php if ($error): ?><div class="flash flash-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<form method="POST" class="form-page-form"><div class="form-block"><div class="form-block-head"><h2 class="section-title">Release details</h2><p class="section-subtitle">Choose a batch, confirm quantity, and record the customer reference.</p></div><div class="form-grid"><div class="field field-full"><label for="inventory_id">Inventory Batch</label><select name="inventory_id" id="inventory_id" required onchange="updateBatchPreview()"><option value="">Select a batch</option><?php foreach ($inventory as $id => $row): ?><option value="<?php echo $id; ?>" data-qty="<?php echo (int)$row['qty']; ?>" data-batch="<?php echo htmlspecialchars($row['batch_no']); ?>" <?php echo (string)$old['inventory_id'] === (string)$id ? 'selected' : ''; ?>><?php echo htmlspecialchars($row['label'] . ' • Available: ' . number_format($row['qty'])); ?></option><?php endforeach; ?></select></div><div class="field"><label for="qty_out">Quantity OUT</label><input type="number" id="qty_out" name="qty_out" min="1" value="<?php echo htmlspecialchars($old['qty_out']); ?>" placeholder="Enter quantity" required></div><div class="field"><label for="customer_name">Customer Name</label><input type="text" id="customer_name" name="customer_name" value="<?php echo htmlspecialchars($old['customer_name']); ?>" placeholder="Customer or receiving entity" required></div><div class="field"><label for="document_type">Document Type</label><select name="document_type" id="document_type" required><option value="">Select document type</option><option value="DR" <?php echo $old['document_type'] === 'DR' ? 'selected' : ''; ?>>Delivery Receipt (DR)</option><option value="SI" <?php echo $old['document_type'] === 'SI' ? 'selected' : ''; ?>>Sales Invoice (SI)</option><option value="DONATION" <?php echo $old['document_type'] === 'DONATION' ? 'selected' : ''; ?>>For Donation</option><option value="FDA_SAMPLE" <?php echo $old['document_type'] === 'FDA_SAMPLE' ? 'selected' : ''; ?>>Actual Sample to FDA</option><option value="BIDDING" <?php echo $old['document_type'] === 'BIDDING' ? 'selected' : ''; ?>>For Bidding</option></select></div><div class="field"><label for="document_number">Document Number</label><input type="text" id="document_number" name="document_number" value="<?php echo htmlspecialchars($old['document_number']); ?>" placeholder="Reference number" required></div></div></div><div class="form-preview-strip"><div class="preview-chip"><span>Selected Batch</span><strong id="selectedBatchLabel">—</strong></div><div class="preview-chip"><span>Available Qty</span><strong id="selectedBatchQty">—</strong></div><div class="preview-chip"><span>Processed By</span><strong><?php echo htmlspecialchars($name); ?></strong></div></div><div class="form-submit-row"><div class="form-submit-meta">Review the batch and quantity carefully before saving this OUT transaction.</div><button type="submit" class="btn btn-success btn-lg">Save Inventory OUT</button></div></form></section></div>
<script>
function updateBatchPreview(){const select=document.getElementById('inventory_id');const option=select.options[select.selectedIndex];document.getElementById('selectedBatchLabel').textContent=(option&&option.value)?(option.getAttribute('data-batch')||'—'):'—';document.getElementById('selectedBatchQty').textContent=(option&&option.value)?(option.getAttribute('data-qty')||'0'):'—';}
window.addEventListener('DOMContentLoaded', updateBatchPreview);
</script></body></html>
