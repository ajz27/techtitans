<?php
require_once('session_check.inc');

if ($_SESSION['role'] !== 'admin') {
    header("Location: unauthorized.php");
    exit();
}
?>
