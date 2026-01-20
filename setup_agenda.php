<?php
require 'c:/xampp1/htdocs/intranet/config.php';

// SQL para criar a tabela de agenda
$sql = "CREATE TABLE IF NOT EXISTS agenda (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT,
    data_evento DATE NOT NULL,
    hora_inicio TIME NULL,
    hora_fim TIME NULL,
    local_evento VARCHAR(255) NULL,
    categoria VARCHAR(50) DEFAULT 'Reunião',
    cor VARCHAR(20) DEFAULT '#0d9488',
    autor_id INT,
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql)) {
    echo "TABELA AGENDA CRIADA COM SUCESSO. ";
    
    // Registrar o módulo na tabela de módulos
    $conn->query("INSERT INTO modulos (nome, descricao, slug, icone, ordem) 
                  SELECT 'Agenda de Eventos', 'Calendário institucional e reuniões', 'agenda', 'calendar', 7
                  WHERE NOT EXISTS (SELECT 1 FROM modulos WHERE slug = 'agenda')");
    
    echo "MÓDULO REGISTRADO.";
} else {
    echo "ERRO AO CRIAR TABELA: " . $conn->error;
}
$conn->close();
?>
