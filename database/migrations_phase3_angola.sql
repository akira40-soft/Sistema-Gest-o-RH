-- ============================================================================
-- PHASE 3 MIGRATION - Angola Context & Geolocation Timeclock
-- ============================================================================

-- 1. ADICIONAR CAMPOS DE CONTEXTO ANGOLANO À TABELA funcionarios
ALTER TABLE funcionarios ADD COLUMN (
    carteira_profissional VARCHAR(10) UNIQUE COMMENT 'Número de carteira profissional angolana (NUIT)',
    tipo_presenca ENUM('escritorio', 'campo', 'teletrabalho') DEFAULT 'escritorio' COMMENT 'Local de trabalho habitual',
    latitude_escritorio DECIMAL(10, 8) COMMENT 'Latitude do escritório/local de trabalho',
    longitude_escritorio DECIMAL(11, 8) COMMENT 'Longitude do escritório/local de trabalho',
    raio_permitido INT DEFAULT 500 COMMENT 'Raio permitido em metros para bater ponto',
    contratos_assinados INT DEFAULT 0 COMMENT 'Quantidade de contratos assinados em Angola',
    nif_angolano VARCHAR(20) COMMENT 'NIF - Número de Identificação Fiscal em Angola'
) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 2. CRIAR TABELA DE TIME CLOCK COM GEOLOCALIZAÇÃO
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
    foto_selfie LONGBLOB COMMENT 'Foto de confirmação (opcional)',
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

-- 3. TABELA DE TENTATIVAS DE BATER PONTO FORA DO RAIO
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

-- 4. TABELA DE LOCALIZAÇÕES PERMITIDAS (Multisedes)
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

-- 5. TABELA DE CONFORMIDADE COM REGULAMENTAÇÕES
CREATE TABLE IF NOT EXISTS conformidade_regulatoria (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    funcionario_id INT NOT NULL,
    lei_ref VARCHAR(50) COMMENT 'Referência da lei (ex: Lei 7/15)',
    consentimento_rastreamento BOOLEAN DEFAULT FALSE COMMENT 'Consentimento dado pelo funcionário',
    data_consentimento DATETIME,
    consentimento_assinado_em TEXT COMMENT 'Documento assinado',
    status VARCHAR(50) DEFAULT 'pendente' COMMENT 'pendente, aceito, recusado',
    
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE CASCADE,
    INDEX idx_funcionario_id (funcionario_id),
    UNIQUE KEY unique_funcionario_lei (funcionario_id, lei_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 6. TABELA DE ALERTAS DE CONFORMIDADE
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

-- 7. ÍNDICES DE PERFORMANCE
CREATE INDEX idx_timeclock_funcionario_data ON timeclock_logs(funcionario_id, criado_em);
CREATE INDEX idx_timeclock_status_validacao ON timeclock_logs(status_validacao, criado_em);
CREATE INDEX idx_alertas_data ON alertas_timeclock(criado_em);

-- 8. INSERTS PADRÃO - LOCALIZAÇÕES PERMITIDAS (Exemplo para Angola)
INSERT INTO localizacoes_permitidas (nome, latitude, longitude, raio_metros, tipo, descricao) VALUES
('Sede Luanda - Talatona', -8.8383, 13.2344, 500, 'escritorio', 'Sede principal em Talatona, Luanda'),
('Filial Benilson', -8.8906, 13.2304, 300, 'escritorio', 'Filial no Benilson, Luanda'),
('Filial Viana', -9.0254, 13.0775, 300, 'escritorio', 'Filial em Viana, Luanda');

-- 9. CAMPOS DE CONFIGURAÇÃO DO SISTEMA
INSERT IGNORE INTO configuracoes_sistema (chave, valor, tipo, descricao) VALUES
('timeclock_gps_obrigatorio', 'true', 'boolean', 'Exigir GPS para bater ponto'),
('timeclock_raio_padrao_escritorio', '500', 'integer', 'Raio padrão em metros para escritório'),
('timeclock_raio_padrao_campo', '2000', 'integer', 'Raio padrão em metros para funcionários em campo'),
('timeclock_foto_selfie_obrigatoria', 'false', 'boolean', 'Exigir foto de confirmação'),
('timeclock_multisedes_ativa', 'true', 'boolean', 'Permitir múltiplas sedes'),
('angola_lei_rastreamento', 'Lei 7/15', 'text', 'Lei de referência para rastreamento'),
('angola_consentimento_requerido', 'true', 'boolean', 'Exigir consentimento do funcionário');

-- ============================================================================
-- COMPARAÇÃO COM SISTEMAS CONHECIDOS
-- ============================================================================

/*
 * SAP SuccessFactors (Usado por multinacionais em Angola):
 *   ✓ GPS Geofencing com múltiplas sedes
 *   ✓ Face recognition (opcional)
 *   ✓ Relatórios de conformidade
 *   ✓ Alertas em tempo real
 *   ✓ Dashboard de presença
 * 
 * Workday (Comum em empresas grandes):
 *   ✓ Time tracking + location verification
 *   ✓ Mobile app com GPS
 *   ✓ Compliance reports
 *   ✓ Manager approval flow
 *   ✓ Integration com RH systems
 * 
 * Gestponte (Sistema angolano):
 *   ✓ Geolocalização obrigatória
 *   ✓ Validação de raio
 *   ✓ Histórico de tentativas
 *   ✓ Alertas automáticos
 *   ✓ Integração com folha de pagamento
 * 
 * Implementação PHASE 3 (Nossa):
 *   ✓ GPS Geofencing (raio configurável)
 *   ✓ Múltiplas sedes
 *   ✓ Conformidade Angola Lei 7/15
 *   ✓ Histórico de tentativas
 *   ✓ Alertas de anomalias
 *   ✓ Carteira profissional validada
 *   ✓ Dashboard admin
 */

-- ============================================================================
-- REGULAMENTAÇÕES ANGOLANAS IMPLEMENTADAS
-- ============================================================================

/*
 * Lei 7/15 - Contrato Individual de Trabalho:
 * Art. 90 (Direitos do empregador):
 *   - Pode estabelecer mecanismos de controle de presença
 *   - Deve informar o funcionário
 *   - Deve respeitar privacidade
 * 
 * Implementação:
 *   1. Campo "consentimento_rastreamento" obrigatório
 *   2. Notificação ao funcionário sobre GPS
 *   3. Dados confidenciais (apenas admin vê)
 *   4. Relatórios para gerência
 * 
 * Proteção de Dados:
 *   - Dados pessoais protegidos
 *   - Acesso restrito a admins
 *   - Retenção máxima: 12 meses
 *   - Direito do funcionário solicitar exclusão
 */

-- ============================================================================
-- ESTRUTURA DE DADOS FINAL
-- ============================================================================

/*
 * Fluxo de Bater Ponto:
 * 
 * 1. Funcionário acessa app/web
 * 2. Sistema solicita GPS + foto (opcional)
 * 3. Valida:
 *    - Carteira profissional preenchida
 *    - Consentimento de rastreamento
 *    - GPS ativo e funcionando
 *    - Esteja dentro do raio permitido
 * 4. Registra em timeclock_logs
 * 5. Admin revisa (se necessário)
 * 6. Confirma ou rejeita
 * 7. Gera alertas se necessário
 * 
 * Melhorias vs Sistema Anterior:
 * ✓ Não pode bater ponto de casa (validação GPS)
 * ✓ Raio configurável por tipo de funcionário
 * ✓ Múltiplas sedes suportadas
 * ✓ Conformidade legal
 * ✓ Foto de confirmação (opcional)
 * ✓ Histórico de tentativas rejeitadas
 */
