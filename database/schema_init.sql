-- Script de Inicialização - Farmácia Gingongo RG
-- Cria todas as tabelas necessárias

-- Departamentos
CREATE TABLE IF NOT EXISTS departamentos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL UNIQUE,
    descricao TEXT,
    ativo INTEGER DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Cargos
CREATE TABLE IF NOT EXISTS cargos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL UNIQUE,
    departamento_id INTEGER,
    salario_base DECIMAL(12,2),
    descricao TEXT,
    ativo INTEGER DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(departamento_id) REFERENCES departamentos(id)
);

-- Funcionários
CREATE TABLE IF NOT EXISTS funcionarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome_completo TEXT NOT NULL,
    cpf TEXT UNIQUE,
    data_nascimento DATE,
    email TEXT UNIQUE,
    telefone TEXT,
    endereco TEXT,
    cidade TEXT,
    estado TEXT,
    cep TEXT,
    status TEXT DEFAULT 'ativo',
    data_admissao DATE,
    departamento_id INTEGER,
    cargo_id INTEGER,
    salario_atual DECIMAL(12,2) DEFAULT 0,
    usuario_id INTEGER,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(departamento_id) REFERENCES departamentos(id),
    FOREIGN KEY(cargo_id) REFERENCES cargos(id),
    FOREIGN KEY(usuario_id) REFERENCES usuarios(id)
);

-- Usuários
CREATE TABLE IF NOT EXISTS usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    funcionario_id INTEGER,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    tipo_acesso TEXT DEFAULT 'funcionario',
    ativo INTEGER DEFAULT 1,
    ultimo_login TIMESTAMP,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(funcionario_id) REFERENCES funcionarios(id)
);

-- RGs (Registros Gerais/Cédula de Identidade)
CREATE TABLE IF NOT EXISTS rgs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    funcionario_id INTEGER NOT NULL,
    numero_rg TEXT NOT NULL UNIQUE,
    orgao_expedidor TEXT,
    uf_expedidor TEXT,
    data_expedicao DATE,
    data_validade DATE,
    mae_nome TEXT,
    data_nascimento DATE,
    naturalidade TEXT,
    filiacao TEXT,
    status TEXT DEFAULT 'ativo',
    observacoes TEXT,
    arquivo_caminho TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(funcionario_id) REFERENCES funcionarios(id)
);

-- Registros de Ponto
CREATE TABLE IF NOT EXISTS registros_ponto (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    funcionario_id INTEGER NOT NULL,
    data DATE NOT NULL,
    hora_entrada TIME,
    hora_saida TIME,
    tipo TEXT DEFAULT 'presenca',
    justificativa TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(funcionario_id) REFERENCES funcionarios(id),
    UNIQUE(funcionario_id, data)
);

-- Férias
CREATE TABLE IF NOT EXISTS ferias (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    funcionario_id INTEGER NOT NULL,
    data_inicio_ferias DATE,
    data_fim_ferias DATE,
    status TEXT DEFAULT 'planejada',
    observacoes TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(funcionario_id) REFERENCES funcionarios(id)
);

-- Licenças
CREATE TABLE IF NOT EXISTS licencas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    funcionario_id INTEGER NOT NULL,
    tipo TEXT,
    data_inicio DATE,
    data_fim DATE,
    dias_uteis INTEGER,
    motivo TEXT,
    status TEXT DEFAULT 'pendente',
    documento_comprovativo TEXT,
    aprovado_por INTEGER,
    data_aprovacao DATETIME,
    observacoes TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(funcionario_id) REFERENCES funcionarios(id),
    FOREIGN KEY(aprovado_por) REFERENCES usuarios(id)
);

-- Advertências
CREATE TABLE IF NOT EXISTS advertencias (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    funcionario_id INTEGER NOT NULL,
    tipo TEXT,
    motivo TEXT,
    data_ocorrencia DATE,
    gerado_por INTEGER,
    status TEXT DEFAULT 'ativa',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(funcionario_id) REFERENCES funcionarios(id),
    FOREIGN KEY(gerado_por) REFERENCES usuarios(id)
);

-- Benefícios
CREATE TABLE IF NOT EXISTS beneficios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL UNIQUE,
    valor_mensal DECIMAL(10,2),
    descricao TEXT,
    ativo INTEGER DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Benefícios por Funcionário
CREATE TABLE IF NOT EXISTS funcionario_beneficios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    funcionario_id INTEGER NOT NULL,
    beneficio_id INTEGER NOT NULL,
    data_inicio DATE,
    data_fim DATE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(funcionario_id) REFERENCES funcionarios(id),
    FOREIGN KEY(beneficio_id) REFERENCES beneficios(id)
);

-- Configurações do Sistema
CREATE TABLE IF NOT EXISTS configuracoes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    chave TEXT NOT NULL UNIQUE,
    valor TEXT,
    tipo TEXT DEFAULT 'string',
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Logs de Auditoria
CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER,
    acao TEXT NOT NULL,
    tabela TEXT,
    registro_id INTEGER,
    dados_anteriores TEXT,
    dados_novos TEXT,
    ip_address TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(usuario_id) REFERENCES usuarios(id)
);

-- Criar índices para melhor performance
CREATE INDEX IF NOT EXISTS idx_funcionarios_status ON funcionarios(status);
CREATE INDEX IF NOT EXISTS idx_usuarios_username ON usuarios(username);
CREATE INDEX IF NOT EXISTS idx_rgs_funcionario ON rgs(funcionario_id);
CREATE INDEX IF NOT EXISTS idx_registros_ponto_data ON registros_ponto(data);
CREATE INDEX IF NOT EXISTS idx_licencas_status ON licencas(status);
CREATE INDEX IF NOT EXISTS idx_audit_usuario ON audit_logs(usuario_id);

-- Insertar dados iniciais

-- Administrador padrão (usuário: augusto, senha: Augusto@Gingongo2026)
INSERT OR IGNORE INTO funcionarios (id, nome_completo, status, data_admissao) 
VALUES (2, 'Augusto', 'ativo', date('now'));

INSERT OR IGNORE INTO usuarios (id, funcionario_id, username, password_hash, tipo_acesso, ativo) 
VALUES (1, 2, 'augusto', '$2y$12$8/Lw3gAkWWkP1vYzVYKtee4M.O.fzT1gHdOcJaM5ReFbKKDW6bnmu', 'admin', 1);

-- Configurações padrão
INSERT OR IGNORE INTO configuracoes (chave, valor, tipo) VALUES 
('nome_empresa', 'Farmácia Gingongo RG', 'string'),
('email_empresa', 'contato@gingongo.com', 'string'),
('telefone_empresa', '+244-923-000-000', 'string'),
('endereco_empresa', 'Luanda, Angola', 'string'),
('data_inicio_operacoes', '2026-01-01', 'string'),
('dias_ferias_anuais', '20', 'integer'),
('horas_trabalhadas_dia', '8', 'integer');
