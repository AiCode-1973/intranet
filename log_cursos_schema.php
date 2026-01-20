<?php
require_once 'config.php';
$res = $conn->query("DESCRIBE edu_cursos");
$log = "";
while($row = $res->fetch_assoc()) {
    $log .= "Field: " . $row['Field'] . " | Type: " . $row['Type'] . "\n";
}
file_put_contents('edu_cursos_schema.txt', $log);
?>
