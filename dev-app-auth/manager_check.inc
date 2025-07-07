<?php
require_once('session_check.inc');

if ($_SESSION['role'] !== 'manager') {
    header("Location: unauthorized.php");
    exit();
}
?>
