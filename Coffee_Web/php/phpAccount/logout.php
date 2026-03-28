<?php
session_start();
$_SESSION = [];
session_destroy();
header("Location: ../phpAccount/login.php");
exit;
?>