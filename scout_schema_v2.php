<?php
require_once 'config.php';
$tables = ['edu_progresso', 'edu_certificados', 'edu_provas', 'edu_resultados_prova'];
foreach ($tables as $table) {
    echo "--- Table: $table ---\n";
    $res = $conn->query("DESCRIBE $table");
    if ($res) {
        while($row = $res->fetch_assoc()) {
            echo "  " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "  Table does not exist.\n";
    }
    echo "\n";
}
?>
