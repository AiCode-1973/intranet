-- Estrutura do banco de dados para o sistema Intranet

-- Tabela de setores
CREATE TABLE IF NOT EXISTS setores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de usuários
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cpf VARCHAR(14) UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    setor_id INT,
    is_admin TINYINT(1) DEFAULT 0,
    ativo TINYINT(1) DEFAULT 1,
    ultimo_acesso DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (setor_id) REFERENCES setores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de módulos do sistema
CREATE TABLE IF NOT EXISTS modulos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    slug VARCHAR(50) NOT NULL UNIQUE,
    icone VARCHAR(50),
    ordem INT DEFAULT 0,
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de permissões (relaciona setores com módulos)
CREATE TABLE IF NOT EXISTS permissoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setor_id INT NOT NULL,
    modulo_id INT NOT NULL,
    pode_visualizar TINYINT(1) DEFAULT 0,
    pode_criar TINYINT(1) DEFAULT 0,
    pode_editar TINYINT(1) DEFAULT 0,
    pode_excluir TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (setor_id) REFERENCES setores(id) ON DELETE CASCADE,
    FOREIGN KEY (modulo_id) REFERENCES modulos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_setor_modulo (setor_id, modulo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de logs de acesso
CREATE TABLE IF NOT EXISTS logs_acesso (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    acao VARCHAR(255),
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir usuário administrador padrão (CPF: 000.000.000-00, senha: admin123)
INSERT INTO usuarios (nome, cpf, email, senha, is_admin, ativo) 
VALUES ('Administrador', '00000000000', 'admin@intranet.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1);

-- Inserir setores padrão
INSERT INTO setores (nome, descricao) VALUES 
('Administração', 'Setor administrativo com acesso completo'),
('Recursos Humanos', 'Departamento de recursos humanos'),
('Financeiro', 'Setor financeiro e contabilidade'),
('TI', 'Tecnologia da Informação'),
('Operacional', 'Setor operacional');

-- Inserir módulos padrão
INSERT INTO modulos (nome, descricao, slug, icone, ordem) VALUES
('Dashboard', 'Painel principal do sistema', 'dashboard', 'home', 1),
('Usuários', 'Gerenciamento de usuários', 'usuarios', 'users', 2),
('Setores', 'Gerenciamento de setores', 'setores', 'briefcase', 3),
('Permissões', 'Gerenciamento de permissões', 'permissoes', 'lock', 4),
('Relatórios', 'Relatórios do sistema', 'relatorios', 'file-text', 5);
