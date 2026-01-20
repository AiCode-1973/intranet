<?php
require_once 'config.php';
$log = "Full Schema of 'usuarios' table:\n";
$res = $conn->query("DESCRIBE usuarios");
while($row = $res->fetch_assoc()) {
    $log .= "Field: " . $row['Field'] . " | Type: " . $row['Type'] . "\n";
}
file_put_contents('usuarios_full_schema.txt', $log);
?>
