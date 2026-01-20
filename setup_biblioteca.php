<?php
require_once 'config.php';
require_once 'functions.php';

// 1. Criar pasta de uploads se não existir
$upload_dir = 'uploads/biblioteca';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
    // Criar um index.html ou .htaccess para proteção se necessário
    file_put_contents($upload_dir . '/index.html', '');
    echo "Diretório de uploads criado.<br>";
}

// 2. Criar tabela biblioteca
$sql_table = "CREATE TABLE IF NOT EXISTS biblioteca (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT,
    arquivo_path VARCHAR(255) NOT NULL,
    categoria VARCHAR(100) NOT NULL,
    usuario_id INT NOT NULL,
    data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
)";

if ($conn->query($sql_table)) {
    echo "Tabela 'biblioteca' criada com sucesso.<br>";
} else {
    echo "Erro ao criar tabela: " . $conn->error . "<br>";
}

// 3. Registrar módulo
$check_modulo = $conn->query("SELECT id FROM modulos WHERE slug = 'biblioteca'");
if ($check_modulo->num_rows == 0) {
    $stmt = $conn->prepare("INSERT INTO modulos (nome, descricao, slug, icone, ordem) VALUES (?, ?, ?, ?, ?)");
    $nome = "Documentos & Biblioteca";
    $desc = "Repositório central de manuais, POPs e formulários.";
    $slug = "biblioteca";
    $icone = "files";
    $ordem = 10;
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
