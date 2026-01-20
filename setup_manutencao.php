<?php
require 'c:/xampp1/htdocs/intranet/config.php';

// SQL para criar a tabela de manutenção
$sql = "CREATE TABLE IF NOT EXISTS manutencao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NOT NULL,
    local VARCHAR(100) NOT NULL,
    prioridade ENUM('Baixa', 'Média', 'Alta', 'Urgente') DEFAULT 'Média',
    status ENUM('Aberto', 'Em Atendimento', 'Aguardando Peça', 'Resolvido', 'Cancelado') DEFAULT 'Aberto',
    usuario_id INT,
    tecnico_id INT NULL,
    categoria ENUM('Elétrica', 'Hidráulica', 'Pedreiro/Pintura', 'Mobiliário', 'Ar Condicionado', 'Outros') DEFAULT 'Outros',
    data_abertura TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_fechamento TIMESTAMP NULL,
    resolucao TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (tecnico_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql)) {
    echo "TABELA MANUTENCAO CRIADA COM SUCESSO. ";
    
    // Registrar o módulo na tabela de módulos
    $conn->query("INSERT INTO modulos (nome, descricao, slug, icone, ordem) 
                  SELECT 'Infraestrutura & Manutenção', 'Abertura e acompanhamento de chamados de manutenção predial', 'manutencao', 'wrench', 10
                  WHERE NOT EXISTS (SELECT 1 FROM modulos WHERE slug = 'manutencao')");
    
    echo "MÓDULO MANUTENÇÃO REGISTRADO.";
} else {
    echo "ERRO AO CRIAR TABELA: " . $conn->error;
}
$conn->close();
?>
