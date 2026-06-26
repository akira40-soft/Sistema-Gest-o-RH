-- ============================================================================
-- Tabela: advertencias (Disciplinar)
-- Adicionada para completar o módulo de gestão disciplinar
-- ============================================================================
CREATE TABLE IF NOT EXISTS advertencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT NOT NULL,
    tipo ENUM('verbal', 'escrita', 'suspensao') NOT NULL,
    gravidade ENUM('leve', 'media', 'grave', 'gravissima') DEFAULT 'leve',
    motivo TEXT NOT NULL,
    descricao TEXT,
    data_ocorrencia DATE NOT NULL,
    data_fim_suspensao DATE NULL,
    dias_suspensao INT DEFAULT 0,
    status ENUM('pendente', 'aprovada_rh', 'aprovada_direcao', 'ativa', 'revogada', 'expirada') DEFAULT 'pendente',
    aplicada_por INT NOT NULL,
    aprovada_rh_por INT NULL,
    aprovada_rh_em TIMESTAMP NULL,
    aprovada_direcao_por INT NULL,
    aprovada_direcao_em TIMESTAMP NULL,
    documento_path VARCHAR(500),
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios (id) ON DELETE CASCADE,
    FOREIGN KEY (aplicada_por) REFERENCES usuarios (id) ON DELETE RESTRICT,
    FOREIGN KEY (aprovada_rh_por) REFERENCES usuarios (id) ON DELETE SET NULL,
    FOREIGN KEY (aprovada_direcao_por) REFERENCES usuarios (id) ON DELETE SET NULL,
    INDEX idx_adv_func (funcionario_id),
    INDEX idx_adv_tipo (tipo),
    INDEX idx_adv_status (status),
    INDEX idx_adv_data (data_ocorrencia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
