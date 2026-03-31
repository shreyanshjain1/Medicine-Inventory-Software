<?php
require_once 'includes/common.php';

if (isset($_SESSION['user'])) {
    redirect('dashboard.php');
}

redirect('login.php');
