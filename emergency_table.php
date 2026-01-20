<?php
require 'c:/xampp1/htdocs/intranet/config.php';

$sql = "CREATE TABLE IF NOT EXISTS mural (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    conteudo TEXT NOT NULL,
    categoria VARCHAR(50) DEFAULT 'Informativo',
    prioridade ENUM('Normal', 'Alta') DEFAULT 'Normal',
    autor_id INT,
    data_expiracao DATE NULL,
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql)) {
    echo "TABELA MURAL CRIADA COM SUCESSO";
    $conn->query("INSERT INTO modulos (nome, descricao, slug, icone, ordem) 
                  SELECT 'Mural de Avisos', 'Comunicados e notícias internas', 'mural', 'megaphone', 6
                  WHERE NOT EXISTS (SELECT 1 FROM modulos WHERE slug = 'mural')");
} else {
    echo "ERRO AO CRIAR TABELA: " . $conn->error;
}
$conn->close();
?>
