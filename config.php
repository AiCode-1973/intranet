<?php
define('DB_HOST', '69.49.241.25');
define('DB_USER', 'apassa73_intranet');
define('DB_PASS', 'Dema@1973');
define('DB_NAME', 'apassa73_intranet');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Erro na conexão: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>
