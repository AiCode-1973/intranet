<?php
require_once 'config.php';
$res = $conn->query("SHOW TABLES LIKE 'setores'");
if ($res->num_rows > 0) {
    echo "Table: setores\n";
    $res = $conn->query("DESCRIBE setores");
    while($row = $res->fetch_assoc()) {
        echo "  " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "Table 'setores' does not exist.\n";
}
?>
