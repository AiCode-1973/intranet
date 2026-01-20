<?php
require 'c:/xampp1/htdocs/intranet/config.php';

// SQL para criar a tabela de chamados
$sql = "CREATE TABLE IF NOT EXISTS chamados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NOT NULL,
    prioridade ENUM('Baixa', 'Média', 'Alta', 'Urgente') DEFAULT 'Média',
    status ENUM('Aberto', 'Em Atendimento', 'Aguardando Peça', 'Resolvido', 'Cancelado') DEFAULT 'Aberto',
    usuario_id INT,
    tecnico_id INT NULL,
    categoria VARCHAR(50) DEFAULT 'Suporte Geral',
    data_abertura TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_fechamento TIMESTAMP NULL,
    resolucao TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (tecnico_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql)) {
    echo "TABELA CHAMADOS CRIADA COM SUCESSO. ";
    
    // Registrar o módulo na tabela de módulos
    $conn->query("INSERT INTO modulos (nome, descricao, slug, icone, ordem) 
                  SELECT 'Suporte de TI', 'Abertura e acompanhamento de chamados técnicos', 'suporte', 'monitor-dot', 9
                  WHERE NOT EXISTS (SELECT 1 FROM modulos WHERE slug = 'suporte')");
    
    echo "MÓDULO SUPORTE REGISTRADO.";
} else {
    echo "ERRO AO CRIAR TABELA: " . $conn->error;
}
$conn->close();
?>
