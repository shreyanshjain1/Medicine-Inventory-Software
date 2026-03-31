<?php
require_once 'includes/common.php';
require_login();
require_permission('exports.view');

$batchNo = trim((string) ($_GET['batch_no'] ?? $_POST['batch_no'] ?? ''));
if ($batchNo === '') {
    exit('Batch number missing.');
}

redirect('batch_history.php?batch=' . urlencode($batchNo));
