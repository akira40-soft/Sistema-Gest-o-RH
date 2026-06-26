-- ========================================
-- FASE 2 - Migrações do Banco de Dados
-- Sistema de Gestão RG - Farmácia Gingongo
-- ========================================

-- ========================================
-- ALTERAÇÕES EM TABELAS EXISTENTES
-- ========================================

-- Adicionar campos a tabela funcionarios para aprovação
ALTER TABLE funcionarios ADD COLUMN IF NOT EXISTS status_aprovacao TEXT DEFAULT 'pendente';
-- Valores: pendente, aprovado, rejeitado
ALTER TABLE funcionarios ADD COLUMN IF NOT EXISTS data_aprovacao DATETIME;
ALTER TABLE funcionarios ADD COLUMN IF NOT EXISTS aprovado_por INTEGER;
-- FK para quem aprovou
ALTER TABLE funcionarios ADD COLUMN IF NOT EXISTS observacoes_aprovacao TEXT;

-- Adicionar campos a tabela usuarios para customização
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS foto_perfil VARCHAR(255);
-- Caminho relativo da foto
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS tema TEXT DEFAULT 'dark';
-- light, dark, auto
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS notificacoes_ativas INTEGER DEFAULT 1;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS dois_fatores_ativo INTEGER DEFAULT 0;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS ultimo_login DATETIME;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS ativo INTEGER DEFAULT 1;

-- Adicionar índices para performance em buscas
CREATE INDEX IF NOT EXISTS idx_funcionarios_status_aprovacao ON funcionarios(status_aprovacao);
CREATE INDEX IF NOT EXISTS idx_funcionarios_departamento ON funcionarios(departamento_id);
CREATE INDEX IF NOT EXISTS idx_usuarios_tipo_acesso ON usuarios(tipo_acesso);
CREATE INDEX IF NOT EXISTS idx_rgs_funcionario ON rgs(funcionario_id);

-- ========================================
-- NOVAS TABELAS
-- ========================================

-- Tabela: Aprovações de Funcionários
CREATE TABLE IF NOT EXISTS employee_approvals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    funcionario_id INTEGER NOT NULL,
    status TEXT DEFAULT 'pendente',
    -- pendente, aprovado, rejeitado
    observacoes TEXT,
    aprovado_por INTEGER,
    data_aprovacao DATETIME,
    motivo_rejeicao TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(funcionario_id) REFERENCES funcionarios(id) ON DELETE CASCADE,
    FOREIGN KEY(aprovado_por) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_employee_approvals_status ON employee_approvals(status);
CREATE INDEX IF NOT EXISTS idx_employee_approvals_funcionario ON employee_approvals(funcionario_id);

-- Tabela: Fotos de Perfil
CREATE TABLE IF NOT EXISTS user_photos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL UNIQUE,
    caminho_arquivo VARCHAR(255) NOT NULL,
    tipo_mime VARCHAR(50),
    tamanho_bytes INTEGER,
    largura INTEGER,
    altura INTEGER,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_user_photos_usuario ON user_photos(usuario_id);

-- Tabela: Widgets Customizados do Dashboard
CREATE TABLE IF NOT EXISTS dashboard_widgets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL,
    widget_tipo VARCHAR(100) NOT NULL,
    -- estatisticas, graficos, tarefas, etc
    posicao INTEGER DEFAULT 0,
    ativo INTEGER DEFAULT 1,
    configuracao_json TEXT,
    -- JSON com configurações específicas do widget
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE(usuario_id, widget_tipo)
);

CREATE INDEX IF NOT EXISTS idx_dashboard_widgets_usuario ON dashboard_widgets(usuario_id);

-- Tabela: Logs de Auditoria Detalhados
CREATE TABLE IF NOT EXISTS audit_logs_detailed (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER,
    acao VARCHAR(100) NOT NULL,
    -- create, update, delete, approve, reject, login, logout, etc
    tabela VARCHAR(100),
    id_registro INTEGER,
    dados_antes TEXT,
    -- JSON com valores anteriores
    dados_depois TEXT,
    -- JSON com novos valores
    ip_address VARCHAR(45),
    user_agent TEXT,
    descricao TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_audit_logs_detailed_usuario ON audit_logs_detailed(usuario_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_detailed_acao ON audit_logs_detailed(acao);
CREATE INDEX IF NOT EXISTS idx_audit_logs_detailed_tabela ON audit_logs_detailed(tabela);
CREATE INDEX IF NOT EXISTS idx_audit_logs_detailed_criado ON audit_logs_detailed(criado_em);

-- Tabela: Notificações
CREATE TABLE IF NOT EXISTS notificacoes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT,
    tipo VARCHAR(50),
    -- info, sucesso, aviso, erro
    link VARCHAR(255),
    lida INTEGER DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    lida_em DATETIME,
    FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_notificacoes_usuario ON notificacoes(usuario_id);
CREATE INDEX IF NOT EXISTS idx_notificacoes_lida ON notificacoes(lida);
CREATE INDEX IF NOT EXISTS idx_notificacoes_criado ON notificacoes(criado_em);

-- Tabela: Sessões Ativas (para controle melhor)
CREATE TABLE IF NOT EXISTS sessoes_ativas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL,
    sessao_id VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_acesso DATETIME,
    expirado INTEGER DEFAULT 0,
    FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_sessoes_ativas_usuario ON sessoes_ativas(usuario_id);
CREATE INDEX IF NOT EXISTS idx_sessoes_ativas_expirado ON sessoes_ativas(expirado);

-- Tabela: Configurações do Sistema
CREATE TABLE IF NOT EXISTS configuracoes_sistema (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    chave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT,
    tipo VARCHAR(50),
    -- string, integer, boolean, json
    descricao TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Inserts iniciais de configurações
INSERT OR IGNORE INTO configuracoes_sistema (chave, valor, tipo, descricao) VALUES
('app_nome', 'Farmácia Gingongo - RG', 'string', 'Nome da aplicação'),
('app_versao', '2.0', 'string', 'Versão do sistema'),
('tamanho_maximo_foto', '5242880', 'integer', 'Tamanho máximo de upload em bytes (5MB)'),
('tipos_foto_permitidos', 'jpg,jpeg,png,gif', 'string', 'Tipos de arquivo permitidos para foto'),
('backup_automatico', '1', 'boolean', 'Ativar backup automático'),
('notificacoes_email', '0', 'boolean', 'Enviar notificações por email'),
('modo_manutencao', '0', 'boolean', 'Sistema em modo de manutenção'),
('log_auditoria', '1', 'boolean', 'Ativar logs de auditoria');

-- Tabela: Backups
CREATE TABLE IF NOT EXISTS backups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome VARCHAR(255) NOT NULL,
    caminho_arquivo VARCHAR(255) NOT NULL,
    tamanho_bytes INTEGER,
    tipo VARCHAR(50),
    -- completo, incremental
    criado_por INTEGER,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    restaurado_em DATETIME,
    FOREIGN KEY(criado_por) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_backups_criado ON backups(criado_em);

-- ========================================
-- TRIGGERS (Se suportar - SQLite)
-- ========================================

-- Atualizar timestamp de atualização automaticamente
CREATE TRIGGER IF NOT EXISTS update_funcionarios_timestamp 
AFTER UPDATE ON funcionarios
BEGIN
  UPDATE funcionarios SET atualizado_em = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

CREATE TRIGGER IF NOT EXISTS update_usuarios_timestamp 
AFTER UPDATE ON usuarios
BEGIN
  UPDATE usuarios SET atualizado_em = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

CREATE TRIGGER IF NOT EXISTS update_employee_approvals_timestamp 
AFTER UPDATE ON employee_approvals
BEGIN
  UPDATE employee_approvals SET atualizado_em = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

-- ========================================
-- ÍNDICES DE PERFORMANCE
-- ========================================

CREATE INDEX IF NOT EXISTS idx_configuracoes_sistema_chave ON configuracoes_sistema(chave);

-- ========================================
-- FIM DAS MIGRAÇÕES PHASE 2
-- ========================================
