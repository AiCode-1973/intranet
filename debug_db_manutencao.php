<?php
require 'config.php';
echo "--- TABELA manutencao ---\n";
$res = $conn->query("DESCRIBE manutencao");
if ($res) {
    while($row = $res->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "Erro ao descrever manutencao: " . $conn->error . "\n";
}

echo "\n--- TABELA manutencao_comentarios ---\n";
$res = $conn->query("DESCRIBE manutencao_comentarios");
if ($res) {
    while($row = $res->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "Erro ao descrever manutencao_comentarios: " . $conn->error . "\n";
}
