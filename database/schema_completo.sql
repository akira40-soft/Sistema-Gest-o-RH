-- ============================================================================
-- SISTEMA DE GESTÃO RG - FARMÁCIA VALÓDIA
-- Schema MySQL Completo - Versão Profissional
-- Baseado no TCC + Pesquisa de Mercado (HS RH, KuatelaSoft, Activ People HR)
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP DATABASE IF EXISTS farmacia_valodia_rg;

CREATE DATABASE farmacia_valodia_rg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE farmacia_valodia_rg;

-- ============================================================================
-- 1. TABELA: departamentos
-- ============================================================================
CREATE TABLE departamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    descricao TEXT,
    responsavel_id INT NULL,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_dept_ativo (ativo),
    INDEX idx_dept_nome (nome)
) ENGINE = InnoDB;

-- ============================================================================
-- 2. TABELA: cargos
-- ============================================================================
CREATE TABLE cargos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    descricao TEXT,
    salario_base DECIMAL(12, 2) NOT NULL,
    nivel_hierarquico ENUM(
        'operacional',
        'tecnico',
        'gerencial',
        'diretivo'
    ) DEFAULT 'operacional',
    requer_certificacao BOOLEAN DEFAULT FALSE,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cargos_ativo (ativo),
    INDEX idx_cargos_nivel (nivel_hierarquico)
) ENGINE = InnoDB;

-- ============================================================================
-- 3. TABELA: usuarios
-- ============================================================================
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    tipo_acesso ENUM(
        'super_admin',
        'gestor_rh',
        'lider_farmaceutico',
        'funcionario_rh',
        'funcionario'
    ) DEFAULT 'funcionario',
    ativo BOOLEAN DEFAULT TRUE,
    ultimo_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_usuarios_username (username),
    INDEX idx_usuarios_tipo (tipo_acesso),
    INDEX idx_usuarios_ativo (ativo)
) ENGINE = InnoDB;

-- ============================================================================
-- 4. TABELA: funcionarios
-- ============================================================================
CREATE TABLE funcionarios ( id INT AUTO_INCREMENT PRIMARY KEY,

-- Dados Pessoais
nome_completo VARCHAR(200) NOT NULL,
cpf VARCHAR(20) NOT NULL UNIQUE,
bi VARCHAR(20) UNIQUE,
rg VARCHAR(20),
data_nascimento DATE NOT NULL,
sexo ENUM('M', 'F', 'Outro') NOT NULL,
estado_civil ENUM(
    'solteiro',
    'casado',
    'divorciado',
    'viuvo',
    'uniao_facto'
) DEFAULT 'solteiro',
nacionalidade VARCHAR(50) DEFAULT 'Angolana',

-- Contatos
telefone VARCHAR(20),
telefone_emergencia VARCHAR(20),
email VARCHAR(100),
endereco TEXT,
provincia VARCHAR(50),
municipio VARCHAR(50),

-- Dados Profissionais
departamento_id INT NOT NULL,
cargo_id INT NOT NULL,
data_admissao DATE NOT NULL,
data_demissao DATE NULL,
tipo_contrato ENUM(
    'CLT',
    'prazo_determinado',
    'estagio',
    'temporario',
    'prestacao_servicos'
) DEFAULT 'CLT',
status ENUM(
    'ativo',
    'ferias',
    'afastado',
    'licenca',
    'demitido',
    'suspenso'
) DEFAULT 'ativo',

-- Financeiro
salario_atual DECIMAL(12, 2) NOT NULL,
banco VARCHAR(100),
agencia VARCHAR(20),
conta VARCHAR(30),
iban VARCHAR(50),

-- Certificações (obrigatório para farmacêuticos)
numero_ordem_farmaceuticos VARCHAR(50),
validade_certificacao DATE,

-- Vinculação
usuario_id INT NULL, gestor_direto_id INT NULL,

-- Observações
observacoes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (departamento_id) REFERENCES departamentos(id) ON DELETE RESTRICT,
    FOREIGN KEY (cargo_id) REFERENCES cargos(id) ON DELETE RESTRICT,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (gestor_direto_id) REFERENCES funcionarios(id) ON DELETE SET NULL,
    
    INDEX idx_func_cpf (cpf),
    INDEX idx_func_bi (bi),
    INDEX idx_func_nome (nome_completo),
    INDEX idx_func_status (status),
    INDEX idx_func_dept (departamento_id),
    INDEX idx_func_cargo (cargo_id),
    INDEX idx_func_admissao (data_admissao)
) ENGINE=InnoDB;

-- Adicionar FK de responsável em departamentos
ALTER TABLE departamentos
ADD CONSTRAINT fk_dept_responsavel FOREIGN KEY (responsavel_id) REFERENCES funcionarios (id) ON DELETE SET NULL;

-- ============================================================================
-- 5. TABELA: documentos_funcionarios 🆕
-- ============================================================================
CREATE TABLE documentos_funcionarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT NOT NULL,
    tipo_documento ENUM(
        'bi',
        'cv',
        'certificado_habilitacoes',
        'certificado_farmaceutico',
        'registo_criminal',
        'atestado_medico',
        'comprovativo_residencia',
        'foto',
        'contrato',
        'termo_responsabilidade',
        'outro'
    ) NOT NULL,
    nome_original VARCHAR(255) NOT NULL,
    nome_arquivo VARCHAR(255) UNIQUE NOT NULL,
    caminho_arquivo VARCHAR(500) NOT NULL,
    tamanho_kb INT,
    mime_type VARCHAR(100),
    data_validade DATE NULL,
    data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    versao INT DEFAULT 1,
    ativo BOOLEAN DEFAULT TRUE,
    uploaded_por INT,
    observacoes TEXT,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios (id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_por) REFERENCES usuarios (id) ON DELETE SET NULL,
    INDEX idx_doc_funcionario (funcionario_id),
    INDEX idx_doc_tipo (tipo_documento),
    INDEX idx_doc_validade (data_validade),
    INDEX idx_doc_ativo (ativo)
) ENGINE = InnoDB;

-- ============================================================================
-- 6. TABELA: turnos 🆕
-- ============================================================================
CREATE TABLE turnos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fim TIME NOT NULL,
    tipo ENUM(
        'manha',
        'tarde',
        'noite',
        'integral',
        'flexivel'
    ) DEFAULT 'integral',
    duracao_horas DECIMAL(4, 2),
    intervalo_minutos INT DEFAULT 60,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_turno_tipo (tipo),
    INDEX idx_turno_ativo (ativo)
) ENGINE = InnoDB;

-- ============================================================================
-- 7. TABELA: escalas 🆕
-- ============================================================================
CREATE TABLE escalas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT NOT NULL,
    turno_id INT NOT NULL,
    data DATE NOT NULL,
    status ENUM(
        'agendado',
        'confirmado',
        'substituido',
        'cancelado'
    ) DEFAULT 'agendado',
    substituido_por INT NULL,
    motivo_substituicao TEXT,
    observacoes TEXT,
    criado_por INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios (id) ON DELETE CASCADE,
    FOREIGN KEY (turno_id) REFERENCES turnos (id) ON DELETE RESTRICT,
    FOREIGN KEY (substituido_por) REFERENCES funcionarios (id) ON DELETE SET NULL,
    FOREIGN KEY (criado_por) REFERENCES usuarios (id) ON DELETE SET NULL,
    UNIQUE KEY uk_escala_func_data (
        funcionario_id,
        data,
        turno_id
    ),
    INDEX idx_escala_data (data),
    INDEX idx_escala_status (status)
) ENGINE = InnoDB;

-- ============================================================================
-- 8. TABELA: registros_ponto
-- ============================================================================
CREATE TABLE registros_ponto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT NOT NULL,
    data DATE NOT NULL,
    hora_entrada TIME,
    hora_saida TIME,
    horas_trabalhadas DECIMAL(5, 2),
    horas_extras DECIMAL(5, 2) DEFAULT 0.00,
    tipo ENUM(
        'presenca',
        'falta_justificada',
        'falta_injustificada',
        'atestado',
        'ferias',
        'licenca'
    ) DEFAULT 'presenca',
    metodo_registro ENUM(
        'manual',
        'biometrico',
        'mobile',
        'web'
    ) DEFAULT 'manual',
    ip_registro VARCHAR(45),
    gps_latitude DECIMAL(10, 8),
    gps_longitude DECIMAL(11, 8),
    foto_verificacao VARCHAR(500),
    justificativa TEXT,
    aprovado_por INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios (id) ON DELETE CASCADE,
    FOREIGN KEY (aprovado_por) REFERENCES usuarios (id) ON DELETE SET NULL,
    UNIQUE KEY uk_ponto_func_data (funcionario_id, data),
    INDEX idx_ponto_data (data),
    INDEX idx_ponto_tipo (tipo),
    INDEX idx_ponto_func_mes (funcionario_id, data)
) ENGINE = InnoDB;

-- ============================================================================
-- 9. TABELA: licencas 🆕
-- ============================================================================
CREATE TABLE licencas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT NOT NULL,
    tipo ENUM(
        'ferias',
        'medica',
        'maternidade',
        'paternidade',
        'luto',
        'casamento',
        'estudos',
        'sem_vencimento',
        'outro'
    ) NOT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    dias_uteis INT,
    motivo TEXT,
    documento_comprovativo VARCHAR(500),
    status ENUM(
        'pendente',
        'aprovada',
        'rejeitada',
        'cancelada'
    ) DEFAULT 'pendente',
    aprovado_por INT NULL,
    data_aprovacao TIMESTAMP NULL,
    observacoes TEXT,
    remunerada BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios (id) ON DELETE CASCADE,
    FOREIGN KEY (aprovado_por) REFERENCES usuarios (id) ON DELETE SET NULL,
    INDEX idx_licenca_func (funcionario_id),
    INDEX idx_licenca_tipo (tipo),
    INDEX idx_licenca_status (status),
    INDEX idx_licenca_periodo (data_inicio, data_fim)
) ENGINE = InnoDB;

-- ============================================================================
-- 10. TABELA: folha_pagamento
-- ============================================================================
CREATE TABLE folha_pagamento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT NOT NULL,
    mes INT NOT NULL CHECK (mes BETWEEN 1 AND 12),
    ano INT NOT NULL CHECK (ano >= 2020),

-- Proventos
salario_base DECIMAL(12, 2) NOT NULL,
horas_extras DECIMAL(12, 2) DEFAULT 0.00,
subsidio_alimentacao DECIMAL(12, 2) DEFAULT 0.00,
subsidio_transporte DECIMAL(12, 2) DEFAULT 0.00,
bonus DECIMAL(12, 2) DEFAULT 0.00,
comissoes DECIMAL(12, 2) DEFAULT 0.00,
subsidio_ferias DECIMAL(12, 2) DEFAULT 0.00,
decimo_terceiro DECIMAL(12, 2) DEFAULT 0.00,
outros_proventos DECIMAL(12, 2) DEFAULT 0.00,

-- Descontos
desconto_faltas DECIMAL(12, 2) DEFAULT 0.00,
desconto_inss_trabalhador DECIMAL(12, 2) DEFAULT 0.00,
desconto_irt DECIMAL(12, 2) DEFAULT 0.00,
emprestimos DECIMAL(12, 2) DEFAULT 0.00,
adiantamentos DECIMAL(12, 2) DEFAULT 0.00,
outros_descontos DECIMAL(12, 2) DEFAULT 0.00,

-- Totais
total_proventos DECIMAL(12, 2),
total_descontos DECIMAL(12, 2),
salario_liquido DECIMAL(12, 2),

-- Encargos Patronais (informativo)
inss_patronal DECIMAL(12, 2) DEFAULT 0.00,

-- Controle
data_processamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processado_por INT,
    status ENUM('rascunho', 'processado', 'pago', 'cancelado') DEFAULT 'rascunho',
    data_pagamento DATE NULL,
    recibo_gerado BOOLEAN DEFAULT FALSE,
    caminho_recibo VARCHAR(500),
    observacoes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE CASCADE,
    FOREIGN KEY (processado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    
    UNIQUE KEY uk_folha_func_periodo (funcionario_id, mes, ano),
    INDEX idx_folha_mes_ano (mes, ano),
    INDEX idx_folha_status (status)
) ENGINE=InnoDB;

-- ============================================================================
-- 11. TABELA: avaliacoes 🆕
-- ============================================================================
CREATE TABLE avaliacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT NOT NULL,
    avaliador_id INT NOT NULL,
    periodo_inicio DATE NOT NULL,
    periodo_fim DATE NOT NULL,
    tipo ENUM('trimestral', 'semestral', 'anual', 'experiencia') DEFAULT 'anual',

-- Critérios (escala 1-5)
atendimento_cliente INT CHECK (
    atendimento_cliente BETWEEN 1 AND 5
),
conhecimento_tecnico INT CHECK (
    conhecimento_tecnico BETWEEN 1 AND 5
),
pontualidade INT CHECK (pontualidade BETWEEN 1 AND 5),
trabalho_equipe INT CHECK (
    trabalho_equipe BETWEEN 1 AND 5
),
cumprimento_metas INT CHECK (
    cumprimento_metas BETWEEN 1 AND 5
),
proatividade INT CHECK (proatividade BETWEEN 1 AND 5),

-- Resultado
nota_final DECIMAL(3, 2),
classificacao ENUM(
    'insuficiente',
    'regular',
    'bom',
    'muito_bom',
    'excelente'
),

-- Feedback
pontos_fortes TEXT,
pontos_melhoria TEXT,
plano_desenvolvimento TEXT,
comentarios_funcionario TEXT,

-- Controle
status ENUM('rascunho', 'aguardando_assinatura', 'finalizada', 'cancelada') DEFAULT 'rascunho',
    data_assinatura_funcionario TIMESTAMP NULL,
    data_assinatura_avaliador TIMESTAMP NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE CASCADE,
    FOREIGN KEY (avaliador_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    
    INDEX idx_aval_func (funcionario_id),
    INDEX idx_aval_periodo (periodo_inicio, periodo_fim),
    INDEX idx_aval_status (status)
) ENGINE=InnoDB;

-- ============================================================================
-- 12. TABELA: treinamentos 🆕
-- ============================================================================
CREATE TABLE treinamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    descricao TEXT,
    instrutor VARCHAR(100),
    instituicao VARCHAR(150),
    data_inicio DATE NOT NULL,
    data_fim DATE,
    duracao_horas DECIMAL(5, 2),
    local VARCHAR(200),
    tipo ENUM(
        'cpd_farmacia',
        'tecnico',
        'comportamental',
        'seguranca',
        'obrigatorio',
        'outro'
    ) DEFAULT 'outro',
    certificado_emitido BOOLEAN DEFAULT FALSE,
    custo DECIMAL(10, 2) DEFAULT 0.00,
    vagas_disponiveis INT,
    status ENUM(
        'planejado',
        'em_andamento',
        'concluido',
        'cancelado'
    ) DEFAULT 'planejado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_trein_data (data_inicio),
    INDEX idx_trein_tipo (tipo),
    INDEX idx_trein_status (status)
) ENGINE = InnoDB;

CREATE TABLE participacoes_treinamento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    treinamento_id INT NOT NULL,
    funcionario_id INT NOT NULL,
    data_inscricao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    presente BOOLEAN DEFAULT FALSE,
    nota DECIMAL(4, 2) NULL,
    aprovado BOOLEAN NULL,
    certificado_path VARCHAR(500),
    horas_cpd DECIMAL(5, 2),
    observacoes TEXT,
    FOREIGN KEY (treinamento_id) REFERENCES treinamentos (id) ON DELETE CASCADE,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios (id) ON DELETE CASCADE,
    UNIQUE KEY uk_particip_trein_func (
        treinamento_id,
        funcionario_id
    ),
    INDEX idx_particip_func (funcionario_id)
) ENGINE = InnoDB;

-- ============================================================================
-- 13. TABELA: comunicados 🆕
-- ============================================================================
CREATE TABLE comunicados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    conteudo TEXT NOT NULL,
    tipo ENUM(
        'informativo',
        'urgente',
        'politica',
        'evento',
        'felicitacoes',
        'advertencia'
    ) DEFAULT 'informativo',
    prioridade ENUM(
        'baixa',
        'media',
        'alta',
        'critica'
    ) DEFAULT 'media',
    destinatarios ENUM(
        'todos',
        'departamento',
        'cargo',
        'individual'
    ) DEFAULT 'todos',
    departamento_id INT NULL,
    cargo_id INT NULL,
    publicado_por INT NOT NULL,
    data_publicacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_expiracao TIMESTAMP NULL,
    anexo VARCHAR(500),
    ativo BOOLEAN DEFAULT TRUE,
    visualizacoes INT DEFAULT 0,
    FOREIGN KEY (publicado_por) REFERENCES usuarios (id) ON DELETE RESTRICT,
    FOREIGN KEY (departamento_id) REFERENCES departamentos (id) ON DELETE CASCADE,
    FOREIGN KEY (cargo_id) REFERENCES cargos (id) ON DELETE CASCADE,
    INDEX idx_comun_tipo (tipo),
    INDEX idx_comun_ativo (ativo),
    INDEX idx_comun_data (data_publicacao)
) ENGINE = InnoDB;

-- ============================================================================
-- 14. TABELA: vagas 🆕
-- ============================================================================
CREATE TABLE vagas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cargo_id INT NOT NULL,
    departamento_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descricao TEXT,
    requisitos TEXT,
    responsabilidades TEXT,
    beneficios TEXT,
    salario_min DECIMAL(12, 2),
    salario_max DECIMAL(12, 2),
    numero_vagas INT DEFAULT 1,
    tipo_contrato ENUM(
        'CLT',
        'prazo_determinado',
        'estagio',
        'temporario'
    ) DEFAULT 'CLT',
    regime ENUM(
        'full_time',
        'part_time',
        'turnos'
    ) DEFAULT 'full_time',
    data_abertura DATE DEFAULT(CURRENT_DATE),
    data_fechamento DATE,
    status ENUM(
        'aberta',
        'em_andamento',
        'pausada',
        'fechada',
        'cancelada'
    ) DEFAULT 'aberta',
    publicada BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cargo_id) REFERENCES cargos (id) ON DELETE RESTRICT,
    FOREIGN KEY (departamento_id) REFERENCES departamentos (id) ON DELETE RESTRICT,
    INDEX idx_vaga_status (status),
    INDEX idx_vaga_cargo (cargo_id),
    INDEX idx_vaga_data (data_abertura)
) ENGINE = InnoDB;

CREATE TABLE candidaturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vaga_id INT NOT NULL,
    nome_completo VARCHAR(200) NOT NULL,
    email VARCHAR(100) NOT NULL,
    telefone VARCHAR(20),
    data_nascimento DATE,
    cv_path VARCHAR(500),
    carta_motivacao TEXT,
    experiencia_anos INT,
    pretensao_salarial DECIMAL(12, 2),
    disponibilidade ENUM(
        'imediata',
        '15_dias',
        '30_dias',
        'a_combinar'
    ) DEFAULT 'a_combinar',
    status ENUM(
        'nova',
        'em_analise',
        'pre_selecionada',
        'entrevista_agendada',
        'aprovada',
        'rejeitada',
        'contratada'
    ) DEFAULT 'nova',
    pontuacao INT,
    observacoes TEXT,
    entrevistado_por INT NULL,
    data_entrevista TIMESTAMP NULL,
    data_candidatura TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vaga_id) REFERENCES vagas (id) ON DELETE CASCADE,
    FOREIGN KEY (entrevistado_por) REFERENCES usuarios (id) ON DELETE SET NULL,
    INDEX idx_candid_vaga (vaga_id),
    INDEX idx_candid_status (status),
    INDEX idx_candid_data (data_candidatura)
) ENGINE = InnoDB;

-- ============================================================================
-- 15. TABELA: relatorios
-- ============================================================================
CREATE TABLE relatorios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    tipo ENUM(
        'funcionarios',
        'assiduidade',
        'folha_pagamento',
        'avaliacoes',
        'treinamentos',
        'recrutamento',
        'geral'
    ) NOT NULL,
    descricao TEXT,
    parametros JSON,
    gerado_por INT NOT NULL,
    data_geracao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    formato ENUM('pdf', 'excel', 'html', 'csv') DEFAULT 'pdf',
    caminho_arquivo VARCHAR(500),
    tamanho_kb INT,
    FOREIGN KEY (gerado_por) REFERENCES usuarios (id) ON DELETE CASCADE,
    INDEX idx_rel_tipo (tipo),
    INDEX idx_rel_data (data_geracao)
) ENGINE = InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- VIEWS ÚTEIS
-- ============================================================================

-- View: Funcionários Ativos com Informações Completas
CREATE VIEW vw_funcionarios_ativos AS
SELECT
    f.id,
    f.nome_completo,
    f.cpf,
    f.email,
    f.telefone,
    c.nome AS cargo,
    d.nome AS departamento,
    f.salario_atual,
    f.data_admissao,
    TIMESTAMPDIFF(
        YEAR,
        f.data_nascimento,
        CURDATE()
    ) AS idade,
    TIMESTAMPDIFF(
        MONTH,
        f.data_admissao,
        CURDATE()
    ) AS meses_empresa,
    f.status
FROM
    funcionarios f
    JOIN cargos c ON f.cargo_id = c.id
    JOIN departamentos d ON f.departamento_id = d.id
WHERE
    f.status = 'ativo';

-- View: Assiduidade Mês Atual
CREATE VIEW vw_assiduidade_mes_atual AS
SELECT
    f.id AS funcionario_id,
    f.nome_completo,
    d.nome AS departamento,
    COUNT(
        CASE
            WHEN rp.tipo = 'presenca' THEN 1
        END
    ) AS dias_presentes,
    COUNT(
        CASE
            WHEN rp.tipo IN (
                'falta_justificada',
                'falta_injustificada'
            ) THEN 1
        END
    ) AS dias_faltas,
    SUM(rp.horas_trabalhadas) AS total_horas,
    SUM(rp.horas_extras) AS total_horas_extras
FROM
    funcionarios f
    LEFT JOIN registros_ponto rp ON f.id = rp.funcionario_id
    AND MONTH(rp.data) = MONTH(CURRENT_DATE)
    AND YEAR(rp.data) = YEAR(CURRENT_DATE)
    JOIN departamentos d ON f.departamento_id = d.id
WHERE
    f.status = 'ativo'
GROUP BY
    f.id,
    f.nome_completo,
    d.nome;

-- View: Documentos a Vencer (próximos 30 dias)
CREATE VIEW vw_documentos_a_vencer AS
SELECT f.nome_completo, f.email, df.tipo_documento, df.data_validade, DATEDIFF(
        df.data_validade, CURRENT_DATE
    ) AS dias_restantes
FROM
    documentos_funcionarios df
    JOIN funcionarios f ON df.funcionario_id = f.id
WHERE
    df.ativo = TRUE
    AND df.data_validade IS NOT NULL
    AND df.data_validade BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)
    AND f.status = 'ativo'
ORDER BY df.data_validade;

SELECT '✅ Schema completo criado com 15 tabelas + 3 views!' AS status;