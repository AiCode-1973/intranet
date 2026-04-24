<?php
require_once 'config.php';
$sql = "INSERT INTO usuarios (nome, cpf, email, foto, funcao, data_admissao, senha, setor_id, superior_id, is_admin, is_tecnico, is_manutencao, is_educacao, is_ceh) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
if ($stmt) {
    echo "Statement preparado com sucesso!";
} else {
    echo "Erro no prepare: " . $conn->error;
}
?>