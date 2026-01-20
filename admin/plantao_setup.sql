-- Tabela para solicitações de troca de plantão
CREATE TABLE IF NOT EXISTS trocas_plantao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitante_id INT NOT NULL,
    data_plantao_solicitante DATE NOT NULL,
    data_troca_solicitante DATE NOT NULL,
    colaborador_id INT NOT NULL,
    data_plantao_colaborador DATE NOT NULL,
    data_troca_colaborador DATE NOT NULL,
    status ENUM('Pendente', 'Aceito', 'Recusado', 'Aprovado', 'Reprovado') DEFAULT 'Pendente',
    aceite_colaborador TINYINT(1) DEFAULT 0,
    data_aceite TIMESTAMP NULL,
    aprovacao_gerencia TINYINT(1) DEFAULT 0,
    gerente_id INT NULL,
    data_aprovacao TIMESTAMP NULL,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (solicitante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (colaborador_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (gerente_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registrar o novo módulo
INSERT IGNORE INTO modulos (nome, descricao, slug, icone, ordem) 
VALUES ('Troca de Plantão', 'Módulo para solicitação e gestão de trocas de plantão', 'plantao', 'refresh-cw', 10);
