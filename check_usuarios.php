<?php
require_once 'config.php';
$res = $conn->query("DESCRIBE usuarios");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
?>
