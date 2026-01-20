<?php
require_once 'config.php';
require_once 'functions.php';

// 1. Criar tabela educacao_treinamentos
$sql_table = "CREATE TABLE IF NOT EXISTS educacao_treinamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT,
    url_link VARCHAR(255),
    categoria VARCHAR(100) NOT NULL,
    carga_horaria VARCHAR(50),
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql_table)) {
    echo "Tabela 'educacao_treinamentos' criada com sucesso.<br>";
} else {
    echo "Erro ao criar tabela: " . $conn->error . "<br>";
}

// 2. Registrar módulo
$check_modulo = $conn->query("SELECT id FROM modulos WHERE slug = 'educacao'");
if ($check_modulo->num_rows == 0) {
    $stmt = $conn->prepare("INSERT INTO modulos (nome, descricao, slug, icone, ordem) VALUES (?, ?, ?, ?, ?)");
    $nome = "Educação Permanente";
    $desc = "Plataforma de treinamentos e capacitações corporativas.";
    $slug = "educacao";
    $icone = "graduation-cap";
    $ordem = 11;
    $stmt->bind_param("ssssi", $nome, $desc, $slug, $icone, $ordem);
    
    if ($stmt->execute()) {
        echo "Módulo registrado com sucesso.<br>";
    } else {
        echo "Erro ao registrar módulo: " . $conn->error . "<br>";
    }
} else {
    echo "Módulo já registrado.<br>";
}

$conn->close();
?>
