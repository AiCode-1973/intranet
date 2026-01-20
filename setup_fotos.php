<?php
require_once 'config.php';

echo "<h2>Atualizando Banco de Dados para Fotos...</h2>";

$check = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'foto'");
if ($check->num_rows == 0) {
    if ($conn->query("ALTER TABLE usuarios ADD COLUMN foto VARCHAR(255) DEFAULT NULL AFTER email")) {
        echo "<p style='color: green;'>Coluna 'foto' adicionada com sucesso!</p>";
    } else {
        echo "<p style='color: red;'>Erro ao adicionar coluna: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>A coluna 'foto' já existe.</p>";
}

echo "<h3>Criando diretório de uploads...</h3>";
$dir = 'uploads/fotos';
if (!is_dir($dir)) {
    if (mkdir($dir, 0777, true)) {
        echo "<p style='color: green;'>Diretório '$dir' criado com sucesso!</p>";
    } else {
        echo "<p style='color: red;'>Erro ao criar diretório '$dir'.</p>";
    }
} else {
    echo "<p style='color: blue;'>O diretório '$dir' já existe.</p>";
}

echo "<hr><a href='admin/usuarios.php'>Voltar para Gerenciamento de Usuários</a>";
?>
