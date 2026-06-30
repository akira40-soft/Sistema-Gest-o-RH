-- ============================================================================
-- SISTEMA DE GESTÃO DE RH - FARMÁCIA GINGONGO RG
-- Script de Criação do Banco de Dados
-- Baseado no TCC: "Desenvolvimento de um sistema de gestão para RH"
-- Adaptado à legislação trabalhista de Angola
-- ============================================================================

-- Criar banco de dados se não existir
CREATE DATABASE IF NOT EXISTS farmacia_gingongo_rh
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE farmacia_gingongo_rh;

-- ============================================================================
-- 1. TABELA: departamentos
-- Descrição: Armazena os departamentos da farmácia
-- ============================================================================
CREATE TABLE IF NOT EXISTS departamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    descricao TEXT,
    responsavel_id INT NULL,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ativo (ativo),
    INDEX idx_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. TABELA: cargos
-- Descrição: Define os cargos e suas respectivas informações salariais
-- ============================================================================
CREATE TABLE IF NOT EXISTS cargos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    salario_base DECIMAL(10, 2) NOT NULL,
    descricao TEXT,
    nivel_hierarquico ENUM('operacional', 'tecnico', 'gerencial', 'diretivo') DEFAULT 'operacional',
    requer_certificacao BOOLEAN DEFAULT FALSE,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ativo (ativo),
    INDEX idx_nivel (nivel_hierarquico)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. TABELA: usuarios
-- Descrição: Tabela de autenticação do sistema (login e permissões)
-- ============================================================================
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    nome VARCHAR(200) NULL,
    email VARCHAR(100) NULL,
    foto VARCHAR(500) NULL,
    tipo_acesso ENUM('super_admin', 'admin', 'gestor_rh', 'funcionario_rh', 'lider_farmaceutico', 'funcionario') NOT NULL DEFAULT 'funcionario',
    ativo BOOLEAN DEFAULT TRUE,
    ultimo_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_tipo_acesso (tipo_acesso),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. TABELA: funcionarios
-- Descrição: Dados pessoais e profissionais dos funcionários
-- ============================================================================
CREATE TABLE IF NOT EXISTS funcionarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_completo VARCHAR(200) NOT NULL,
    bi VARCHAR(30) NOT NULL UNIQUE,
    data_nascimento DATE NOT NULL,
    sexo ENUM('M', 'F', 'Outro') NOT NULL,
    telefone VARCHAR(20),
    email VARCHAR(100),
    endereco TEXT,

    -- Dados Profissionais
    departamento_id INT NOT NULL,
    cargo_id INT NOT NULL,
    data_admissao DATE NOT NULL,
    data_demissao DATE NULL,
    tipo_contrato ENUM('Tempo_Indeterminado', 'Tempo_Determinado', 'Estagio', 'Temporario') DEFAULT 'Tempo_Indeterminado',
    status ENUM('ativo', 'ferias', 'afastado', 'demitido') DEFAULT 'ativo',

    -- Financeiro
    salario_atual DECIMAL(10, 2) NOT NULL,
    banco VARCHAR(100),
    agencia VARCHAR(20),
    conta VARCHAR(30),
    iban VARCHAR(50) NULL,
    nif_angolano VARCHAR(20) NULL,
    foto VARCHAR(500) NULL,
    
    -- Vinculação com usuário do sistema (opcional)
    usuario_id INT NULL,
    
    -- Metadados
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Constraints
    FOREIGN KEY (departamento_id) REFERENCES departamentos(id) ON DELETE RESTRICT,
    FOREIGN KEY (cargo_id) REFERENCES cargos(id) ON DELETE RESTRICT,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    
    -- Índices
    INDEX idx_bi (bi),
    INDEX idx_nome (nome_completo),
    INDEX idx_status (status),
    INDEX idx_departamento (departamento_id),
    INDEX idx_cargo (cargo_id),
    INDEX idx_data_admissao (data_admissao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. TABELA: registros_ponto
-- Descrição: Controle de assiduidade (ponto eletrônico)
-- ============================================================================
CREATE TABLE IF NOT EXISTS registros_ponto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT NOT NULL,
    data DATE NOT NULL,
    hora_entrada TIME,
    hora_saida TIME,
    tipo ENUM('presenca', 'falta_justificada', 'falta_injustificada', 'atestado', 'ferias') DEFAULT 'presenca',
    justificativa TEXT,
    horas_trabalhadas DECIMAL(4, 2) AS (
        CASE 
            WHEN hora_entrada IS NOT NULL AND hora_saida IS NOT NULL 
            THEN TIMESTAMPDIFF(MINUTE, hora_entrada, hora_saida) / 60.0
            ELSE 0
        END
    ) STORED,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_funcionario_data (funcionario_id, data),
    INDEX idx_data (data),
    INDEX idx_tipo (tipo),
    INDEX idx_funcionario_data (funcionario_id, data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. TABELA: folha_pagamento
-- Descrição: Processamento mensal de salários
-- ============================================================================
CREATE TABLE IF NOT EXISTS folha_pagamento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT NOT NULL,
    mes INT NOT NULL CHECK (mes BETWEEN 1 AND 12),
    ano INT NOT NULL CHECK (ano >= 2020),
    
    -- Valores brutos
    salario_base DECIMAL(10, 2) NOT NULL,
    horas_extras DECIMAL(10, 2) DEFAULT 0.00,
    bonus DECIMAL(10, 2) DEFAULT 0.00,
    
    -- Descontos
    desconto_faltas DECIMAL(10, 2) DEFAULT 0.00,
    desconto_inss DECIMAL(10, 2) DEFAULT 0.00,
    desconto_irt DECIMAL(10, 2) DEFAULT 0.00,
    outros_descontos DECIMAL(10, 2) DEFAULT 0.00,

    -- Cálculos (IRT = Imposto sobre o Rendimento do Trabalho - Lei Angolana)
    total_proventos DECIMAL(10, 2) AS (salario_base + horas_extras + bonus) STORED,
    total_descontos DECIMAL(10, 2) AS (desconto_faltas + desconto_inss + desconto_irt + outros_descontos) STORED,
    salario_liquido DECIMAL(10, 2) AS (salario_base + horas_extras + bonus - desconto_faltas - desconto_inss - desconto_irt - outros_descontos) STORED,
    
    -- Metadados
    data_processamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processado_por INT,
    status ENUM('rascunho', 'processado', 'pago') DEFAULT 'rascunho',
    observacoes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE CASCADE,
    FOREIGN KEY (processado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    
    UNIQUE KEY unique_funcionario_mes_ano (funcionario_id, mes, ano),
    INDEX idx_mes_ano (mes, ano),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. TABELA: relatorios
-- Descrição: Metadados de relatórios gerados pelo sistema
-- ============================================================================
CREATE TABLE IF NOT EXISTS relatorios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    tipo ENUM('funcionarios', 'assiduidade', 'folha_pagamento', 'geral') NOT NULL,
    descricao TEXT,
    parametros JSON,
    gerado_por INT NOT NULL,
    data_geracao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    formato ENUM('pdf', 'excel', 'html') DEFAULT 'pdf',
    caminho_arquivo VARCHAR(500),
    
    FOREIGN KEY (gerado_por) REFERENCES usuarios(id) ON DELETE CASCADE,
    
    INDEX idx_tipo (tipo),
    INDEX idx_data_geracao (data_geracao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DADOS SEED (INICIAIS) PARA TESTES
-- ============================================================================

-- Inserir Departamentos
INSERT IGNORE INTO departamentos (nome, descricao) VALUES
('Vendas', 'Atendimento ao cliente e vendas de medicamentos'),
('Administração', 'Gestão administrativa e recursos humanos'),
('Farmácia', 'Manipulação e controle de medicamentos'),
('Estoque', 'Controle de inventário e logística'),
('Suporte', 'Tecnologia da informação e suporte técnico');

-- Inserir Cargos
INSERT IGNORE INTO cargos (nome, salario_base, nivel_hierarquico, descricao) VALUES
('Farmacêutico Chefe', 8500.00, 'gerencial', 'Responsável técnico pela farmácia'),
('Farmacêutico', 5500.00, 'tecnico', 'Atendimento farmacêutico e manipulação'),
('Atendente', 2500.00, 'operacional', 'Atendimento ao cliente e vendas'),
('Gestor de RH', 7000.00, 'gerencial', 'Coordenação de recursos humanos'),
('Auxiliar Administrativo', 3000.00, 'operacional', 'Suporte administrativo'),
('Técnico de TI', 4500.00, 'tecnico', 'Suporte técnico e manutenção de sistemas'),
('Gerente de Estoque', 6000.00, 'gerencial', 'Gestão de estoque e logística');

-- Inserir Usuários (Senhas: todos usam 'senha123' - hash bcrypt)
-- Nota: Em produção, usar hash real. Aqui é apenas exemplo
INSERT IGNORE INTO usuarios (username, password_hash, tipo_acesso, nome, email) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 'Isaac Nascimento Quarenta', 'isaac@farmacia-gingongo.ao'),
('josemar_quarenta', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 'Josemar Quarenta', 'josemar@farmacia-gingongo.ao'),
('livenia', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'gestor_rh', 'Livenia Alexandra', 'livenia@farmacia-gingongo.ao'),
('jardel', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'funcionario', 'Jardel Ilunga P. Banoyo', 'jardel@farmacia-gingongo.ao'),
('ilda', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'lider_farmaceutico', 'Ilda Alexandra Livenia', 'ilda@farmacia-gingongo.ao');

-- Inserir Funcionários (usando BI angolano em vez de CPF)
INSERT IGNORE INTO funcionarios (nome_completo, bi, data_nascimento, sexo, telefone, email, departamento_id, cargo_id, data_admissao, salario_atual, status, usuario_id) VALUES
('Isaac Nascimento Quarenta', '001234567LA045', '1995-03-15', 'M', '+244 923 456 789', 'isaac@farmacia-gingongo.ao', 3, 1, '2023-01-10', 8500.00, 'ativo', 2),
('Ilda Alexandra Livénia', '001234568LA045', '1992-07-22', 'F', '+244 924 567 890', 'ilda@farmacia-gingongo.ao', 2, 4, '2023-02-01', 7000.00, 'ativo', 3),
('Jardel Ilunga P. Banoyo', '001234569LA045', '1998-11-05', 'M', '+244 925 678 901', 'jardel@farmacia-gingongo.ao', 5, 6, '2023-03-15', 4500.00, 'ferias', 4),
('Jared Armando', '001234570LA045', '1996-05-18', 'M', '+244 926 789 012', 'jared@farmacia-gingongo.ao', 3, 2, '2023-04-01', 5500.00, 'ativo', NULL),
('Mauricio Manuel F. Chitula', '001234571LA045', '1994-09-30', 'M', '+244 927 890 123', 'mauricio@farmacia-gingongo.ao', 1, 3, '2023-05-10', 2500.00, 'ativo', NULL),
('Francisco da Silva K. Chihamba', '001234572LA045', '1997-12-12', 'M', '+244 928 901 234', 'francisco@farmacia-gingongo.ao', 4, 7, '2023-06-20', 6000.00, 'ativo', NULL),
('Vasco Alexandre', '001234573LA045', '1993-02-28', 'M', '+244 929 012 345', 'vasco@farmacia-gingongo.ao', 1, 3, '2023-07-05', 2500.00, 'ativo', NULL);

-- Inserir Registros de Ponto (última semana)
INSERT IGNORE INTO registros_ponto (funcionario_id, data, hora_entrada, hora_saida, tipo) VALUES
-- Segunda-feira
(1, CURDATE() - INTERVAL 4 DAY, '08:00:00', '17:00:00', 'presenca'),
(2, CURDATE() - INTERVAL 4 DAY, '08:15:00', '17:10:00', 'presenca'),
(4, CURDATE() - INTERVAL 4 DAY, '08:05:00', '17:05:00', 'presenca'),
(5, CURDATE() - INTERVAL 4 DAY, '08:00:00', '17:00:00', 'presenca'),
(6, CURDATE() - INTERVAL 4 DAY, '08:10:00', '17:15:00', 'presenca'),
(7, CURDATE() - INTERVAL 4 DAY, '08:00:00', '17:00:00', 'presenca'),

-- Terça-feira
(1, CURDATE() - INTERVAL 3 DAY, '08:00:00', '17:00:00', 'presenca'),
(2, CURDATE() - INTERVAL 3 DAY, '08:15:00', '17:10:00', 'presenca'),
(4, CURDATE() - INTERVAL 3 DAY, '08:05:00', '17:05:00', 'presenca'),
(5, CURDATE() - INTERVAL 3 DAY, NULL, NULL, 'falta_justificada'),
(6, CURDATE() - INTERVAL 3 DAY, '08:10:00', '17:15:00', 'presenca'),
(7, CURDATE() - INTERVAL 3 DAY, '08:00:00', '17:00:00', 'presenca'),

-- Hoje
(1, CURDATE(), '08:00:00', NULL, 'presenca'),
(2, CURDATE(), '08:15:00', NULL, 'presenca'),
(4, CURDATE(), '08:05:00', NULL, 'presenca'),
(5, CURDATE(), '08:00:00', NULL, 'presenca'),
(6, CURDATE(), '08:10:00', NULL, 'presenca'),
(7, CURDATE(), '08:00:00', NULL, 'presenca');

-- ============================================================================
-- VIEWS (VISUALIZAÇÕES) ÚTEIS
-- ============================================================================

-- View: Funcionários Ativos com Informações Completas
CREATE OR REPLACE VIEW vw_funcionarios_ativos AS
SELECT
    f.id,
    f.nome_completo,
    f.bi,
    f.nif_angolano,
    f.email,
    f.telefone,
    d.nome AS departamento,
    c.nome AS cargo,
    f.salario_atual,
    f.data_admissao,
    f.status,
    TIMESTAMPDIFF(YEAR, f.data_admissao, CURDATE()) AS anos_empresa
FROM funcionarios f
JOIN departamentos d ON f.departamento_id = d.id
JOIN cargos c ON f.cargo_id = c.id
WHERE f.status = 'ativo';

-- View: Resumo de Assiduidade do Mês Atual
CREATE OR REPLACE VIEW vw_assiduidade_mes_atual AS
SELECT 
    f.id AS funcionario_id,
    f.nome_completo,
    d.nome AS departamento,
    COUNT(CASE WHEN rp.tipo = 'presenca' THEN 1 END) AS dias_presentes,
    COUNT(CASE WHEN rp.tipo IN ('falta_justificada', 'falta_injustificada') THEN 1 END) AS dias_faltosos,
    SUM(rp.horas_trabalhadas) AS total_horas_mes
FROM funcionarios f
LEFT JOIN registros_ponto rp ON f.id = rp.funcionario_id 
    AND MONTH(rp.data) = MONTH(CURDATE()) 
    AND YEAR(rp.data) = YEAR(CURDATE())
LEFT JOIN departamentos d ON f.departamento_id = d.id
WHERE f.status = 'ativo'
GROUP BY f.id, f.nome_completo, d.nome;

-- ============================================================================
-- PROCEDURES (PROCEDIMENTOS ARMAZENADOS)
-- ============================================================================

DELIMITER //

-- Procedure: Registrar Presença
CREATE PROCEDURE sp_registrar_presenca(
    IN p_funcionario_id INT,
    IN p_data DATE,
    IN p_hora_entrada TIME,
    IN p_hora_saida TIME
)
BEGIN
    INSERT INTO registros_ponto (funcionario_id, data, hora_entrada, hora_saida, tipo)
    VALUES (p_funcionario_id, p_data, p_hora_entrada, p_hora_saida, 'presenca')
    ON DUPLICATE KEY UPDATE
        hora_saida = p_hora_saida,
        updated_at = CURRENT_TIMESTAMP;
END //

-- Procedure: Calcular Folha de Pagamento do Mês
CREATE PROCEDURE sp_calcular_folha_mensal(
    IN p_mes INT,
    IN p_ano INT,
    IN p_processado_por INT
)
BEGIN
    DECLARE v_funcionario_id INT;
    DECLARE v_salario_base DECIMAL(10,2);
    DECLARE v_faltas INT;
    DECLARE v_desconto_faltas DECIMAL(10,2);
    DECLARE done INT DEFAULT FALSE;
    
    DECLARE cur_funcionarios CURSOR FOR 
        SELECT id, salario_atual FROM funcionarios WHERE status = 'ativo';
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur_funcionarios;
    
    read_loop: LOOP
        FETCH cur_funcionarios INTO v_funcionario_id, v_salario_base;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Contar faltas injustificadas
        SELECT COUNT(*) INTO v_faltas
        FROM registros_ponto
        WHERE funcionario_id = v_funcionario_id
            AND MONTH(data) = p_mes
            AND YEAR(data) = p_ano
            AND tipo = 'falta_injustificada';
        
        -- Calcular desconto (1 dia = salário/30)
        SET v_desconto_faltas = (v_salario_base / 30) * v_faltas;
        
        -- Inserir na folha de pagamento
        INSERT INTO folha_pagamento (
            funcionario_id, mes, ano, salario_base, 
            desconto_faltas, processado_por, status
        ) VALUES (
            v_funcionario_id, p_mes, p_ano, v_salario_base,
            v_desconto_faltas, p_processado_por, 'processado'
        )
        ON DUPLICATE KEY UPDATE
            salario_base = v_salario_base,
            desconto_faltas = v_desconto_faltas,
            updated_at = CURRENT_TIMESTAMP;
    END LOOP;
    
    CLOSE cur_funcionarios;
END //

DELIMITER ;

-- ============================================================================
-- FIM DO SCRIPT
-- ============================================================================

-- ============================================================================
-- 8. TABELA: notifications
-- ============================================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    mensagem TEXT,
    tipo ENUM('success', 'warning', 'danger', 'info') DEFAULT 'info',
    link VARCHAR(500) NULL,
    canal VARCHAR(20) DEFAULT 'in_app',
    lida BOOLEAN DEFAULT FALSE,
    lida_em TIMESTAMP NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_user_lida (user_id, lida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 9. TABELA: licencas
-- ============================================================================
CREATE TABLE IF NOT EXISTS licencas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT NOT NULL,
    tipo VARCHAR(50) NOT NULL DEFAULT 'ferias',
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    motivo TEXT,
    status ENUM('pendente', 'aprovada', 'rejeitada') DEFAULT 'pendente',
    aprovado_por INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE CASCADE,
    FOREIGN KEY (aprovado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 10. TABELA: documentos_funcionarios
-- ============================================================================
CREATE TABLE IF NOT EXISTS documentos_funcionarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT NOT NULL,
    tipo_documento VARCHAR(100) NOT NULL,
    nome_arquivo VARCHAR(500) NOT NULL,
    caminho VARCHAR(500) NOT NULL,
    data_validade DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 11. TABELA: vagas
-- ============================================================================
CREATE TABLE IF NOT EXISTS vagas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    departamento_id INT NULL,
    cargo_id INT NULL,
    descricao TEXT,
    requisitos TEXT,
    salario_min DECIMAL(10, 2) NULL,
    salario_max DECIMAL(10, 2) NULL,
    vagas_disponiveis INT DEFAULT 1,
    data_abertura DATE,
    data_fechamento DATE NULL,
    status ENUM('aberta', 'em_processo', 'fechada') DEFAULT 'aberta',
    criado_por INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (departamento_id) REFERENCES departamentos(id) ON DELETE SET NULL,
    FOREIGN KEY (cargo_id) REFERENCES cargos(id) ON DELETE SET NULL,
    FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 12. TABELA: treinamentos
-- ============================================================================
CREATE TABLE IF NOT EXISTS treinamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    descricao TEXT,
    instrutor VARCHAR(200),
    data_inicio DATE,
    data_fim DATE,
    carga_horaria INT DEFAULT 0,
    local VARCHAR(200),
    status ENUM('planejado', 'em_andamento', 'concluido', 'cancelado') DEFAULT 'planejado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 13. TABELA: configuracoes_sistema
-- ============================================================================
CREATE TABLE IF NOT EXISTS configuracoes_sistema (
    chave VARCHAR(100) PRIMARY KEY,
    valor TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 14. TABELA: dashboard_preferencias
-- ============================================================================
CREATE TABLE IF NOT EXISTS dashboard_preferencias (
    id INT PRIMARY KEY DEFAULT 1,
    background VARCHAR(500) DEFAULT 'assets/uploads/backgrounds/default-pharmacy.jpg',
    overlay_opacity REAL DEFAULT 0.65,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 15. TABELA: beneficios
-- ============================================================================
CREATE TABLE IF NOT EXISTS beneficios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(200) NOT NULL,
    descricao TEXT,
    tipo VARCHAR(50) DEFAULT 'geral',
    valor DECIMAL(10, 2) DEFAULT 0,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 16. TABELA: audit_logs
-- ============================================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    acao VARCHAR(50) NOT NULL,
    entidade VARCHAR(100) NOT NULL,
    entidade_id INT NULL,
    detalhes TEXT,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_entidade (entidade, entidade_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'Banco de dados criado com sucesso!' AS mensagem;
