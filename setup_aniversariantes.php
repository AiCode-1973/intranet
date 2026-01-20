<?php
require 'c:/xampp1/htdocs/intranet/config.php';

// SQL para adicionar coluna de data de nascimento se não existir
$sql_check = "SHOW COLUMNS FROM usuarios LIKE 'data_nascimento'";
$result = $conn->query($sql_check);

if ($result->num_rows == 0) {
    if ($conn->query("ALTER TABLE usuarios ADD COLUMN data_nascimento DATE NULL AFTER email")) {
        echo "COLUNA DATA_NASCIMENTO ADICIONADA. ";
    } else {
        echo "ERRO AO ADICIONAR COLUNA: " . $conn->error;
    }
} else {
    echo "COLUNA DATA_NASCIMENTO JÁ EXISTE. ";
}

// Registrar o módulo na tabela de módulos
$conn->query("INSERT INTO modulos (nome, descricao, slug, icone, ordem) 
              SELECT 'Aniversariantes', 'Calendário de aniversários dos colaboradores', 'aniversariantes', 'cake', 8
              WHERE NOT EXISTS (SELECT 1 FROM modulos WHERE slug = 'aniversariantes')");

echo "MÓDULO ANIVERSARIANTES REGISTRADO.";
$conn->close();
?>
