<?php
require 'db.php';
session_start();
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit; }
$user = $_SESSION['user']; $name = $user['name'];
$grouped_records = [];
$sql = "SELECT o.id, i.generic_name, i.brand_name, i.dosage_strength, i.batch_no, o.qty_out, o.qty_returned AS already_returned, o.customer_name, o.document_type, o.document_number, o.created_at, o.return_status FROM out_records o JOIN inventory i ON o.inventory_id = i.id WHERE o.return_status != 'Return full' ORDER BY i.generic_name, i.brand_name, i.dosage_strength, o.created_at DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $product_key = $row['generic_name'] . ' - ' . $row['brand_name'] . ' (' . $row['dosage_strength'] . ')';
        $grouped_records[$product_key][] = $row;
    }
}
$error=''; $old=['out_record_id'=>$_POST['out_record_id'] ?? '','qty_returned'=>$_POST['qty_returned'] ?? ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $out_record_id=(int)($_POST['out_record_id'] ?? 0); $qty_returned=(int)($_POST['qty_returned'] ?? 0);
    $stmt=$conn->prepare("SELECT o.inventory_id, o.qty_out, o.qty_returned AS already_returned FROM out_records o WHERE o.id = ?"); $stmt->bind_param("i", $out_record_id); $stmt->execute(); $data=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$data) $error = "Selected OUT record was not found.";
    elseif ($qty_returned <= 0) $error = "Quantity returned must be greater than zero.";
    elseif (($qty_returned + (int)$data['already_returned']) > (int)$data['qty_out']) $error = "Invalid quantity returned or exceeds allowed amount.";
    else {
        $stmt=$conn->prepare("UPDATE inventory SET qty = qty + ?, qty_returned = qty_returned + ? WHERE id = ?"); $stmt->bind_param("iii", $qty_returned, $qty_returned, $data['inventory_id']); $stmt->execute(); $stmt->close();
        $stmt=$conn->prepare("INSERT INTO return_binded_records (out_record_id, qty_returned, returned_by, created_at) VALUES (?, ?, ?, NOW())"); $stmt->bind_param("iis", $out_record_id, $qty_returned, $name); $stmt->execute(); $stmt->close();
        $new_returned_total = $qty_returned + (int)$data['already_returned'];
        $return_status = ($new_returned_total == (int)$data['qty_out']) ? 'Return full' : 'Return partial';
        $stmt=$conn->prepare("UPDATE out_records SET qty_returned = ?, return_status = ? WHERE id = ?"); $stmt->bind_param("isi", $new_returned_total, $return_status, $out_record_id); $stmt->execute(); $stmt->close();
        header("Location: dashboard.php"); exit;
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Inventory RETURN</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/app.css"></head><body>
<div class="app-shell app-shell-form"><section class="card form-page-card"><div class="form-page-top"><div><div class="eyebrow">Inventory Transaction</div><h1 class="hero-page-title">Inventory RETURN</h1><p class="hero-page-subtitle">Bind returns directly to the original OUT record to preserve movement history.</p></div><a class="btn btn-soft" href="dashboard.php">Back to Dashboard</a></div>
<?php if ($error): ?><div class="flash flash-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<form method="POST" class="form-page-form"><div class="form-block"><div class="form-block-head"><h2 class="section-title">Return details</h2><p class="section-subtitle">Search the original OUT record, then enter only the quantity being returned now.</p></div><div class="form-grid"><div class="field field-full"><label for="out_record_id">OUT Record</label><select name="out_record_id" id="out_record_id" required onchange="updateReturnPreview()"><option value="">Select an OUT record</option><?php foreach ($grouped_records as $product => $outs): ?><optgroup label="<?php echo htmlspecialchars($product); ?>"><?php foreach ($outs as $record): ?><option value="<?php echo (int)$record['id']; ?>" data-batch="<?php echo htmlspecialchars($record['batch_no']); ?>" data-customer="<?php echo htmlspecialchars($record['customer_name']); ?>" data-reference="<?php echo htmlspecialchars($record['document_type'] . '-' . $record['document_number']); ?>" data-qty-out="<?php echo (int)$record['qty_out']; ?>" data-already-returned="<?php echo (int)$record['already_returned']; ?>" data-remaining="<?php echo (int)$record['qty_out'] - (int)$record['already_returned']; ?>" <?php echo (string)$old['out_record_id'] === (string)$record['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars('To: ' . $record['customer_name'] . ' | ' . $record['document_type'] . '-' . $record['document_number'] . ' | Qty Out: ' . $record['qty_out'] . ' | Returned: ' . (int)$record['already_returned'] . ' | Batch: ' . $record['batch_no'] . ' | ' . date('M d, Y', strtotime($record['created_at']))); ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></div><div class="field"><label for="qty_returned">Quantity Returned</label><input type="number" id="qty_returned" name="qty_returned" min="1" value="<?php echo htmlspecialchars($old['qty_returned']); ?>" placeholder="Enter quantity to return now" required></div></div></div><div class="form-preview-grid"><div class="preview-chip"><span>Batch</span><strong id="previewBatch">—</strong></div><div class="preview-chip"><span>Customer</span><strong id="previewCustomer">—</strong></div><div class="preview-chip"><span>Reference</span><strong id="previewReference">—</strong></div><div class="preview-chip"><span>Qty Out</span><strong id="previewQtyOut">—</strong></div><div class="preview-chip"><span>Already Returned</span><strong id="previewAlreadyReturned">—</strong></div><div class="preview-chip"><span>Remaining Returnable</span><strong id="previewRemaining">—</strong></div></div><div class="form-submit-row"><div class="form-submit-meta">This return will update both the inventory quantity and the linked OUT record return status.</div><button type="submit" class="btn btn-warning btn-lg">Save Return</button></div></form></section></div>
<script>
function updateReturnPreview(){const select=document.getElementById('out_record_id'); const option=select.options[select.selectedIndex]; const map={previewBatch:(option&&option.value)?option.getAttribute('data-batch'):'—',previewCustomer:(option&&option.value)?option.getAttribute('data-customer'):'—',previewReference:(option&&option.value)?option.getAttribute('data-reference'):'—',previewQtyOut:(option&&option.value)?option.getAttribute('data-qty-out'):'—',previewAlreadyReturned:(option&&option.value)?option.getAttribute('data-already-returned'):'—',previewRemaining:(option&&option.value)?option.getAttribute('data-remaining'):'—'}; Object.keys(map).forEach((id)=>{const el=document.getElementById(id); if(el) el.textContent=map[id];});}
window.addEventListener('DOMContentLoaded', updateReturnPreview);
</script></body></html>
