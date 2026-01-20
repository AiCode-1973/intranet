<?php
require_once 'config.php';
$res = $conn->query("DESCRIBE edu_aulas");
while($row = $res->fetch_assoc()) {
    file_put_contents('schema_log.txt', "Field: " . $row['Field'] . " | Type: " . $row['Type'] . "\n", FILE_APPEND);
}
echo file_get_contents('schema_log.txt');
unlink('schema_log.txt');
?>
