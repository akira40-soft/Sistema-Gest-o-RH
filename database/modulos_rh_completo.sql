-- Tabela de Avaliações de Desempenho
CREATE TABLE IF NOT EXISTS avaliacoes_desempenho (
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
    nota_final DECIMAL(4, 2) GENERATED ALWAYS AS (
        (
            nota_pontualidade + nota_qualidade + nota_comportamento + nota_produtividade
        ) / 4.0
    ) STORED,
    observacoes TEXT,
    plano_melhoria TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios (id) ON DELETE CASCADE,
    FOREIGN KEY (avaliador_id) REFERENCES funcionarios (id)
);

-- Tabela de Treinamentos
CREATE TABLE IF NOT EXISTS treinamentos (
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de Participantes em Treinamentos
CREATE TABLE IF NOT EXISTS treinamentos_participantes (
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
    FOREIGN KEY (treinamento_id) REFERENCES treinamentos (id) ON DELETE CASCADE,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios (id) ON DELETE CASCADE,
    UNIQUE KEY (
        treinamento_id,
        funcionario_id
    )
);

-- Tabela de Benefícios
CREATE TABLE IF NOT EXISTS beneficios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
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
    ativo BOOLEAN DEFAULT TRUE
);

-- Tabela de Benefícios dos Funcionários
CREATE TABLE IF NOT EXISTS funcionarios_beneficios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT NOT NULL,
    beneficio_id INT NOT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE,
    valor_personalizado DECIMAL(10, 2),
    ativo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios (id) ON DELETE CASCADE,
    FOREIGN KEY (beneficio_id) REFERENCES beneficios (id)
);

-- Tabela de Férias
CREATE TABLE IF NOT EXISTS ferias (
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
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios (id) ON DELETE CASCADE,
    FOREIGN KEY (aprovado_por) REFERENCES usuarios (id)
);

-- Tabela de Advertências (Disciplinar)
CREATE TABLE IF NOT EXISTS advertencias (
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
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios (id) ON DELETE CASCADE,
    FOREIGN KEY (aplicada_por) REFERENCES usuarios (id)
);

-- Tabela de Certificações Profissionais (Farmácia)
CREATE TABLE IF NOT EXISTS certificacoes_profissionais (
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
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios (id) ON DELETE CASCADE
);

-- Tabela de Vagas (Recrutamento)
CREATE TABLE IF NOT EXISTS vagas (
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
    FOREIGN KEY (departamento_id) REFERENCES departamentos (id),
    FOREIGN KEY (cargo_id) REFERENCES cargos (id),
    FOREIGN KEY (criado_por) REFERENCES usuarios (id)
);

-- Tabela de Candidatos
CREATE TABLE IF NOT EXISTS candidatos (
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
    FOREIGN KEY (vaga_id) REFERENCES vagas (id) ON DELETE CASCADE
);