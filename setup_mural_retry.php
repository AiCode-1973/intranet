<?php
require_once 'config.php';
require_once 'functions.php';

// Check if user is admin
requireAdmin();

$sql = "
CREATE TABLE IF NOT EXISTS mural (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    conteudo TEXT NOT NULL,
    categoria VARCHAR(50) DEFAULT 'Informativo',
    prioridade ENUM('Normal', 'Alta') DEFAULT 'Normal',
    autor_id INT,
    data_expiracao DATE NULL,
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert module if not exists
INSERT INTO modulos (nome, descricao, slug, icone, ordem) 
SELECT 'Mural de Avisos', 'Comunicados e notícias internas', 'mural', 'megaphone', 6
WHERE NOT EXISTS (SELECT 1 FROM modulos WHERE slug = 'mural');
";

// Execute one command at a time for better error handling
if ($conn->query("CREATE TABLE IF NOT EXISTS mural (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    conteudo TEXT NOT NULL,
    categoria VARCHAR(50) DEFAULT 'Informativo',
    prioridade ENUM('Normal', 'Alta') DEFAULT 'Normal',
    autor_id INT,
    data_expiracao DATE NULL,
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci")) {
    
    $conn->query("INSERT INTO modulos (nome, descricao, slug, icone, ordem) 
                  SELECT 'Mural de Avisos', 'Comunicados e notícias internas', 'mural', 'megaphone', 6
                  WHERE NOT EXISTS (SELECT 1 FROM modulos WHERE slug = 'mural')");
                  
    echo "✅ Tabela 'mural' criada com sucesso!";
} else {
    echo "❌ Erro: " . $conn->error;
}
?>
