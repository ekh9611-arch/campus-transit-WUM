<?php
session_start();
unset($_SESSION['driver_id']);
header('Location: driver_login.php');
exit();