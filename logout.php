<?php
// logout.php
session_start();
session_destroy();
header('Location: /JUCSU_Election_Management/index.php');
exit();
?>