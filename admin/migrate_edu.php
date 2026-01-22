<?php
require_once 'config.php';
$sql = "ALTER TABLE edu_cursos ADD COLUMN formacao_instrutor TEXT NULL AFTER instrutor";
if ($conn->query($sql)) {
    echo "OK";
} else {
    echo "Error: " . $conn->error;
}
?>
