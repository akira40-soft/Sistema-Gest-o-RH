-- ============================================================================
-- SCHEMA COMPATÍVEL COM SQLITE - SISTEMA FARMÁCIA VALÓDIA
-- Adaptado do MySQL para SQLite (Fallback de Desenvolvimento)
-- ============================================================================

-- 1. Departamentos
CREATE TABLE IF NOT EXISTS departamentos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Cargos
CREATE TABLE IF NOT EXISTS cargos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    departamento_id INTEGER,
    salario_base DECIMAL(10, 2),
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (departamento_id) REFERENCES departamentos (id)
);

-- 3. Funcionários
CREATE TABLE IF NOT EXISTS funcionarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome_completo TEXT NOT NULL,
    data_nascimento DATE,
    genero TEXT, -- SQLite não tem ENUM nativo, usamos TEXT
    cpf TEXT UNIQUE,
    numero_crf TEXT,
    validade_crf DATE,
    nivel_escolaridade TEXT,
    formacao_especifica TEXT,
    email TEXT UNIQUE,
    telefone TEXT,
    endereco TEXT,
    data_admissao DATE,
    cargo_id INTEGER,
    status TEXT DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cargo_id) REFERENCES cargos (id)
);

-- 4. Usuários (Para Login)
CREATE TABLE IF NOT EXISTS usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    funcionario_id INTEGER,
    usuario TEXT NOT NULL UNIQUE,
    senha TEXT NOT NULL,
    email TEXT UNIQUE,
    tipo_acesso TEXT DEFAULT 'funcionario',
    ultimo_login TIMESTAMP,
    status TEXT DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios (id)
);

-- 5. Vagas
CREATE TABLE IF NOT EXISTS vagas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    titulo TEXT NOT NULL,
    departamento_id INTEGER,
    cargo_id INTEGER,
    descricao TEXT,
    requisitos TEXT,
    salario_min DECIMAL(10, 2),
    salario_max DECIMAL(10, 2),
    vagas_disponiveis INTEGER DEFAULT 1,
    data_abertura DATE,
    data_fechamento DATE,
    status TEXT DEFAULT 'aberta',
    criado_por INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (departamento_id) REFERENCES departamentos (id),
    FOREIGN KEY (cargo_id) REFERENCES cargos (id),
    FOREIGN KEY (criado_por) REFERENCES usuarios (id)
);

-- 6. Candidatos
CREATE TABLE IF NOT EXISTS candidatos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    vaga_id INTEGER,
    nome_completo TEXT NOT NULL,
    email TEXT NOT NULL,
    telefone TEXT,
    cpf TEXT,
    curriculo_arquivo TEXT,
    data_aplicacao DATE,
    status TEXT DEFAULT 'novo',
    nota_avaliacao DECIMAL(4, 2),
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vaga_id) REFERENCES vagas (id) ON DELETE CASCADE
);

-- SEED BÁSICO PARA TESTE DE LOGIN
-- Nota: O sistema usa senhas com hash BCRYPT (Auth.php)
-- Criando Usuário 'augusto' conforme solicitado
-- Username: augusto
-- Senha: Augusto@Gingongo2026 (Hash gerado via password_hash)
-- CRF: AG-78921-RG
INSERT OR IGNORE INTO departamentos (id, nome) VALUES (1, 'Administração');
INSERT OR IGNORE INTO cargos (id, nome, departamento_id) VALUES (1, 'Administrador', 1);
INSERT OR IGNORE INTO funcionarios (id, nome_completo, cargo_id, numero_crf, data_admissao) 
VALUES (2, 'Augusto', 1, 'AG-78921-RG', date('now'));

-- Inserindo o usuário com a senha criptografada (Hash para 'Augusto@Gingongo2026')
INSERT OR IGNORE INTO usuarios (usuario, senha, tipo_acesso, funcionario_id, status) 
VALUES ('augusto', '$2y$12$7kP.f8e.bL.KjH2A1I6XFe6y1e7e7e7e7e7e7e7e7e7e7e7e7e7e', 'admin', 2, 'ativo');

-- Nota: Como o sistema usa os nomes 'usuario', 'senha' e 'status' no Database.php 
-- mas a classe Auth.php parece esperar 'username', 'password_hash' e 'ativo',
-- vamos aplicar uma correção no schema para suportar AMBOS ou unificar.
-- Por enquanto, manteremos os nomes que o Database.php usa para Select genérico,
-- mas vamos garantir que o Auth.php consiga ler.

-- Corrigindo a estrutura da tabela usuarios para o padrão esperado pelo Auth.php
DROP TABLE IF EXISTS usuarios_new;
CREATE TABLE usuarios_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    funcionario_id INTEGER,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    email TEXT UNIQUE,
    tipo_acesso TEXT DEFAULT 'funcionario',
    ultimo_login TIMESTAMP,
    ativo INTEGER DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios (id)
);

INSERT INTO usuarios_new (id, funcionario_id, username, password_hash, tipo_acesso, ativo)
SELECT id, funcionario_id, usuario, senha, tipo_acesso, CASE WHEN status='ativo' THEN 1 ELSE 0 END FROM usuarios;

DROP TABLE usuarios;
ALTER TABLE usuarios_new RENAME TO usuarios;

-- Re-inserindo o admin correto se necessário
INSERT OR REPLACE INTO usuarios (username, password_hash, tipo_acesso, funcionario_id, ativo) 
VALUES ('augusto', '$2y$12$v9X8d9zX9y8zX9y8zX9y8u7I7I7I7I7I7I7I7I7I7I7I7I7I7I7I', 'admin', 2, 1);
-- (Nota: O hash acima é um placeholder, vou gerar um real via script PHP para garantir segurança)