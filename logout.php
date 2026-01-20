<?php
require_once 'config.php';
require_once 'functions.php';

registrarLog($conn, 'Logout realizado');

session_destroy();
header('Location: login.php');
exit;
?>
