-- ════════════════════════════════════════════════════════════════════════════
-- PHASE 3 - ANGOLA CONTEXT - SQL DIRETO PARA phpMyAdmin
-- 
-- Copie TUDO isto, abra phpMyAdmin → Aba SQL → Cole aqui → Clique Execute
-- ════════════════════════════════════════════════════════════════════════════

-- 1. ADICIONAR COLUNAS À TABELA funcionarios
-- ════════════════════════════════════════════════════════════════════════════

ALTER TABLE funcionarios ADD COLUMN carteira_profissional VARCHAR(10) UNIQUE 
  COMMENT 'Número de carteira profissional angolana (NUIT)';

ALTER TABLE funcionarios ADD COLUMN tipo_presenca ENUM('escritorio', 'campo', 'teletrabalho') DEFAULT 'escritorio' 
  COMMENT 'Local de trabalho habitual';

ALTER TABLE funcionarios ADD COLUMN latitude_escritorio DECIMAL(10, 8) 
  COMMENT 'Latitude do escritório/local de trabalho';

ALTER TABLE funcionarios ADD COLUMN longitude_escritorio DECIMAL(11, 8) 
  COMMENT 'Longitude do escritório/local de trabalho';

ALTER TABLE funcionarios ADD COLUMN raio_permitido INT DEFAULT 500 
  COMMENT 'Raio permitido em metros para bater ponto';

ALTER TABLE funcionarios ADD COLUMN nif_angolano VARCHAR(20) 
  COMMENT 'NIF - Número de Identificação Fiscal em Angola';


-- 2. CRIAR TABELA timeclock_logs (Histórico de Batidas)
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS timeclock_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    funcionario_id INT NOT NULL,
    tipo_evento VARCHAR(20) NOT NULL COMMENT 'entrada, saida, pausa, retorno',
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    precisao_gps INT COMMENT 'Precisão do GPS em metros',
    ip_address VARCHAR(45) COMMENT 'IP do dispositivo',
    user_agent TEXT COMMENT 'User agent do navegador/app',
    dispositivo VARCHAR(100) COMMENT 'Tipo de dispositivo (mobile, desktop, etc)',
    status_validacao ENUM('pendente', 'validado', 'rejeitado') DEFAULT 'pendente',
    motivo_rejeicao TEXT,
    distancia_escritorio INT COMMENT 'Distância do escritório em metros',
    dentro_raio BOOLEAN DEFAULT FALSE COMMENT 'Se estava dentro do raio permitido',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE CASCADE,
    INDEX idx_funcionario_id (funcionario_id),
    INDEX idx_criado_em (criado_em),
    INDEX idx_tipo_evento (tipo_evento),
    INDEX idx_status_validacao (status_validacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;


-- 3. CRIAR TABELA timeclock_attempts (Tentativas Rejeitadas)
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS timeclock_attempts (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    funcionario_id INT NOT NULL,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    status VARCHAR(50) COMMENT 'ACEITO, REJEITADO, PENDENTE',
    reason TEXT COMMENT 'Motivo da rejeição',
    tentativa_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE CASCADE,
    INDEX idx_funcionario_id (funcionario_id),
    INDEX idx_tentativa_em (tentativa_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;


-- 4. CRIAR TABELA localizacoes_permitidas (Sedes Angola)
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS localizacoes_permitidas (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL COMMENT 'Nome da localização (ex: Sede Luanda)',
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    raio_metros INT DEFAULT 500,
    tipo VARCHAR(50) DEFAULT 'escritorio',
    descricao TEXT,
    ativa BOOLEAN DEFAULT TRUE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_ativa (ativa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;


-- 5. CRIAR TABELA conformidade_regulatoria (Lei 7/15)
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS conformidade_regulatoria (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    funcionario_id INT NOT NULL,
    lei_ref VARCHAR(50) COMMENT 'Referência da lei (ex: Lei 7/15)',
    consentimento_rastreamento BOOLEAN DEFAULT FALSE COMMENT 'Consentimento dado pelo funcionário',
    data_consentimento DATETIME,
    status VARCHAR(50) DEFAULT 'pendente' COMMENT 'pendente, aceito, recusado',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE CASCADE,
    INDEX idx_funcionario_id (funcionario_id),
    UNIQUE KEY unique_funcionario_lei (funcionario_id, lei_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;


-- 6. CRIAR TABELA alertas_timeclock (Alertas Automáticos)
-- ════════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS alertas_timeclock (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    funcionario_id INT NOT NULL,
    tipo_alerta VARCHAR(100) COMMENT 'fora_do_raio, multiplas_tentativas, dispositivo_novo, etc',
    descricao TEXT,
    severidade ENUM('baixa', 'media', 'alta') DEFAULT 'media',
    resolvido BOOLEAN DEFAULT FALSE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE CASCADE,
    INDEX idx_funcionario_id (funcionario_id),
    INDEX idx_severidade (severidade),
    INDEX idx_resolvido (resolvido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;


-- 7. POPULAR LOCALIZAÇÕES PERMITIDAS (Dados Angola)
-- ════════════════════════════════════════════════════════════════════════════

INSERT INTO localizacoes_permitidas (nome, latitude, longitude, raio_metros, tipo, descricao, ativa)
VALUES 
  ('Sede Luanda - Talatona', -8.8383, 13.2344, 500, 'escritorio', 'Sede principal da farmácia em Talatona, Luanda', 1),
  ('Filial Benilson', -8.8906, 13.2304, 500, 'escritorio', 'Filial em Benilson, Luanda', 1),
  ('Filial Viana', -9.0254, 13.0775, 1000, 'campo', 'Filial em Viana com equipa de campo', 1);


-- ════════════════════════════════════════════════════════════════════════════
-- FIM - Executar tudo isto no phpMyAdmin
-- ════════════════════════════════════════════════════════════════════════════
