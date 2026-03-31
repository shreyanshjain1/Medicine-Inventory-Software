<?php
require_once 'includes/common.php';
require_login();

$batchNo = trim($_GET['batch_no'] ?? $_POST['batch_no'] ?? '');
if ($batchNo === '') {
    die('Batch number missing.');
}
header('Location: batch_history.php?batch=' . urlencode($batchNo));
exit;
