-- ============================================================================
-- EXPANSÃO DO BANCO DE DADOS - RH FARMÁCIA VALÓDIA
-- Script para adicionar funcionalidades específicas de RH para farmácias em Angola
-- ============================================================================

USE farmacia_valodia_rg;

-- Desabilitar chaves estrangeiras para reset limpo
SET FOREIGN_KEY_CHECKS = 0;

-- Reset de tabelas novas (se já existirem)
DROP TABLE IF EXISTS candidatos;

DROP TABLE IF EXISTS vagas;

DROP TABLE IF EXISTS historico_cargos;

DROP TABLE IF EXISTS uniformes_equipamentos;

DROP TABLE IF EXISTS certificacoes_profissionais;

DROP TABLE IF EXISTS advertencias;

DROP TABLE IF EXISTS ferias;

DROP TABLE IF EXISTS funcionarios_beneficios;

DROP TABLE IF EXISTS beneficios;

DROP TABLE IF EXISTS treinamentos_participantes;

DROP TABLE IF EXISTS treinamentos;

DROP TABLE IF EXISTS avaliacoes_desempenho;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. avaliacoes_desempenho
CREATE TABLE avaliacoes_desempenho (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT NOT NULL,
    avaliador_id INT NOT NULL,
    periodo_inicio DATE NOT NULL,
    periodo_fim DATE NOT NULL,
    nota_pontualidade INT CHECK (
        nota_pontualidade BETWEEN 1 AND 5
    ),
    nota_qualidade INT CHECK (
        nota_qualidade BETWEEN 1 AND 5
    ),
    nota_comportamento INT CHECK (
        nota_comportamento BETWEEN 1 AND 5
    ),
    nota_produtividade INT CHECK (
        nota_produtividade BETWEEN 1 AND 5
    ),
    nota_final DECIMAL(3, 2) AS (
        (
            nota_pontualidade + nota_qualidade + nota_comportamento + nota_produtividade
        ) / 4.0
    ) STORED,
    observacoes TEXT,
    plano_melhoria TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios (id) ON DELETE CASCADE,
    FOREIGN KEY (avaliador_id) REFERENCES funcionarios (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 2. treinamentos
CREATE TABLE treinamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    descricao TEXT,
    tipo ENUM(
        'tecnico',
        'comportamental',
        'obrigatorio',
        'regulatorio'
    ) NOT NULL,
    carga_horaria INT NOT NULL,
    instrutor VARCHAR(200),
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    status ENUM(
        'planejado',
        'em_andamento',
        'concluido',
        'cancelado'
    ) DEFAULT 'planejado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE treinamentos_participantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    treinamento_id INT NOT NULL,
    funcionario_id INT NOT NULL,
    status_participacao ENUM(
        'inscrito',
        'presente',
        'ausente',
        'aprovado',
        'reprovado'
    ) DEFAULT 'inscrito',
    nota DECIMAL(4, 2),
    certificado_emitido BOOLEAN DEFAULT FALSE,
    data_certificado DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (treinamento_id) REFERENCES treinamentos (id) ON DELETE CASCADE,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios (id) ON DELETE CASCADE,
    UNIQUE KEY unique_treinamento_funcionario (
        treinamento_id,
        funcionario_id
    )
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 3. beneficios
CREATE TABLE beneficios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    tipo ENUM(
        'vale_transporte',
        'vale_alimentacao',
        'plano_saude',
        'seguro_vida',
        'subsidio_formacao',
        'outro'
    ) NOT NULL,
    valor_mensal DECIMAL(10, 2),
    descricao TEXT,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE funcionarios_beneficios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT NOT NULL,
    beneficio_id INT NOT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE,
    valor_personalizado DECIMAL(10, 2),
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios (id) ON DELETE CASCADE,
    FOREIGN KEY (beneficio_id) REFERENCES beneficios (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 4. ferias
CREATE TABLE ferias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT NOT NULL,
    periodo_aquisitivo_inicio DATE NOT NULL,
    periodo_aquisitivo_fim DATE NOT NULL,
    data_inicio_ferias DATE NOT NULL,
    data_fim_ferias DATE NOT NULL,
    dias_gozados INT NOT NULL,
    status ENUM(
        'planejada',
        'aprovada',
        'em_gozo',
        'concluida',
        'cancelada'
    ) DEFAULT 'planejada',
    aprovado_por INT,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios (id) ON DELETE CASCADE,
    FOREIGN KEY (aprovado_por) REFERENCES usuarios (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 5. advertencias
CREATE TABLE advertencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT NOT NULL,
    tipo ENUM(
        'verbal',
        'escrita',
        'suspensao'
    ) NOT NULL,
    motivo TEXT NOT NULL,
    descricao TEXT,
    data_ocorrencia DATE NOT NULL,
    aplicada_por INT NOT NULL,
    dias_suspensao INT DEFAULT 0,
    status ENUM(
        'ativa',
        'revogada',
        'expirada'
    ) DEFAULT 'ativa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios (id) ON DELETE CASCADE,
    FOREIGN KEY (aplicada_por) REFERENCES usuarios (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 6. certificacoes_profissionais
CREATE TABLE certificacoes_profissionais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT NOT NULL,
    tipo ENUM(
        'CRF',
        'tecnico_farmacia',
        'farmaceutico',
        'especialista',
        'outro'
    ) NOT NULL,
    numero_registro VARCHAR(50) NOT NULL,
    orgao_emissor VARCHAR(100),
    data_emissao DATE NOT NULL,
    data_validade DATE,
    arquivo_documento VARCHAR(255),
    status ENUM(
        'ativa',
        'vencida',
        'suspensa'
    ) DEFAULT 'ativa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 7. uniformes_equipamentos
CREATE TABLE uniformes_equipamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT NOT NULL,
    tipo ENUM(
        'jaleco',
        'touca',
        'mascara',
        'luvas',
        'sapato',
        'cracha',
        'outro'
    ) NOT NULL,
    tamanho VARCHAR(10),
    data_entrega DATE NOT NULL,
    quantidade INT DEFAULT 1,
    estado ENUM('novo', 'usado', 'danificado') DEFAULT 'novo',
    data_devolucao DATE,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 8. historico_cargos
CREATE TABLE historico_cargos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT NOT NULL,
    cargo_id INT NOT NULL,
    departamento_id INT NOT NULL,
    salario DECIMAL(10, 2) NOT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE,
    tipo_movimentacao ENUM(
        'admissao',
        'promocao',
        'transferencia',
        'rebaixamento',
        'demissao'
    ) NOT NULL,
    motivo TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios (id) ON DELETE CASCADE,
    FOREIGN KEY (cargo_id) REFERENCES cargos (id),
    FOREIGN KEY (departamento_id) REFERENCES departamentos (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- 9. recrutamento
CREATE TABLE vagas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    departamento_id INT NOT NULL,
    cargo_id INT NOT NULL,
    descricao TEXT,
    requisitos TEXT,
    salario_min DECIMAL(10, 2),
    salario_max DECIMAL(10, 2),
    vagas_disponiveis INT DEFAULT 1,
    data_abertura DATE NOT NULL,
    data_fechamento DATE,
    status ENUM(
        'aberta',
        'fechada',
        'cancelada'
    ) DEFAULT 'aberta',
    criado_por INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (departamento_id) REFERENCES departamentos (id),
    FOREIGN KEY (cargo_id) REFERENCES cargos (id),
    FOREIGN KEY (criado_por) REFERENCES usuarios (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE candidatos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vaga_id INT NOT NULL,
    nome_completo VARCHAR(200) NOT NULL,
    email VARCHAR(100) NOT NULL,
    telefone VARCHAR(20),
    cpf VARCHAR(14),
    curriculo_arquivo VARCHAR(255),
    data_aplicacao DATE NOT NULL,
    status ENUM(
        'novo',
        'triagem',
        'entrevista',
        'aprovado',
        'reprovado',
        'contratado'
    ) DEFAULT 'novo',
    nota_avaliacao DECIMAL(4, 2),
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vaga_id) REFERENCES vagas (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Modificações em tabelas existentes
ALTER TABLE funcionarios
ADD COLUMN IF NOT EXISTS numero_crf VARCHAR(50) AFTER cpf,
ADD COLUMN IF NOT EXISTS validade_crf DATE AFTER numero_crf,
ADD COLUMN IF NOT EXISTS nivel_escolaridade ENUM(
    'fundamental',
    'medio',
    'tecnico',
    'superior',
    'pos_graduacao',
    'mestrado',
    'doutorado'
) AFTER sexo,
ADD COLUMN IF NOT EXISTS formacao_especifica VARCHAR(200) AFTER nivel_escolaridade;

ALTER TABLE cargos
ADD COLUMN IF NOT EXISTS departamento_id INT AFTER nome;

ALTER TABLE usuarios
MODIFY COLUMN tipo_acesso ENUM(
    'funcionario',
    'gestor_rh',
    'admin',
    'super_admin'
) NOT NULL DEFAULT 'funcionario';

-- Seeds
INSERT IGNORE INTO
    beneficios (
        nome,
        tipo,
        valor_mensal,
        descricao
    )
VALUES (
        'Vale Transporte',
        'vale_transporte',
        150.00,
        'Subsídio para transporte diário'
    ),
    (
        'Vale Alimentação',
        'vale_alimentacao',
        350.00,
        'Cartão refeição mensal'
    ),
    (
        'Plano de Saúde Básico',
        'plano_saude',
        200.00,
        'Cobertura médica básica'
    ),
    (
        'Seguro de Vida',
        'seguro_vida',
        50.00,
        'Seguro de vida em grupo'
    ),
    (
        'Subsídio Formação',
        'subsidio_formacao',
        500.00,
        'Apoio para cursos e certificações'
    );

INSERT IGNORE INTO
    treinamentos (
        titulo,
        descricao,
        tipo,
        carga_horaria,
        instrutor,
        data_inicio,
        data_fim,
        status
    )
VALUES (
        'Boas Práticas Farmacêuticas',
        'Treinamento obrigatório sobre manipulação e dispensação de medicamentos',
        'obrigatorio',
        20,
        'Dr. Armando Silva',
        CURDATE(),
        DATE_ADD(CURDATE(), INTERVAL 5 DAY),
        'planejado'
    );

-- Views
CREATE OR REPLACE VIEW vw_certificacoes_vencendo AS
SELECT
    f.id AS funcionario_id,
    f.nome_completo,
    cp.tipo,
    cp.numero_registro,
    cp.data_validade,
    DATEDIFF(cp.data_validade, CURDATE()) AS dias_restantes
FROM
    funcionarios f
    INNER JOIN certificacoes_profissionais cp ON f.id = cp.funcionario_id
WHERE
    cp.data_validade IS NOT NULL
    AND cp.data_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)
    AND cp.status = 'ativa';

CREATE OR REPLACE VIEW vw_funcionarios_ferias AS
SELECT f.id AS funcionario_id, f.nome_completo, fe.data_inicio_ferias, fe.data_fim_ferias, fe.dias_gozados, fe.status
FROM funcionarios f
    INNER JOIN ferias fe ON f.id = fe.funcionario_id
WHERE
    fe.status IN ('aprovada', 'em_gozo')
    AND CURDATE() BETWEEN fe.data_inicio_ferias AND fe.data_fim_ferias;

SELECT 'Expansão concluída com sucesso!' AS msg;